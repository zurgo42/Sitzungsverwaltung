<?php
/**
 * voting_reminder.php - Erinnerung an offene Abstimmungen kurz vor Fristablauf
 *
 * - get_open_votings($pdo, $member_id = null): offene B-Anträge mit Frist und fehlenden Stimmen
 * - send_voting_deadline_reminders($pdo): verschickt ~24 h vor Fristende eine Mail an alle,
 *   die noch nicht abgestimmt haben (einmal pro Antrag und Mitglied). Nicht abschaltbar.
 *
 * Die Frist entspricht der Auswertung in pseudo_cron.php / cron_notifications.php:
 * letzter Abstimmungstag ist b_date + Abstimmungsdauer; ausgewertet wird um 0 Uhr danach.
 */

function voting_duration_days($pdo) {
    try {
        $stmt = $pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'bart_B_abstimmung_tage' LIMIT 1");
        return $stmt ? max(1, (int)($stmt->fetchColumn() ?: 7)) : 7;
    } catch (\Throwable $e) {
        return 7;
    }
}

/**
 * Offene Abstimmungen mit Frist und den Mitgliedern, die noch kein Votum abgegeben haben.
 * Rückgabe: Liste aus ['antrnr','titel','deadline'(Y-m-d H:i:s),'missing'=>[member_id,...]]
 */
function get_open_votings($pdo, $member_id = null) {
    try {
        return get_open_votings_unsafe($pdo, $member_id);
    } catch (\Throwable $e) {
        error_log('get_open_votings: ' . $e->getMessage());
        return [];
    }
}

function get_open_votings_unsafe($pdo, $member_id = null) {
    if (!defined('TABLE_ANTRAEGE')) return [];

    $dauer = voting_duration_days($pdo);
    $cols = '';
    for ($i = 1; $i <= 6; $i++) {
        $cols .= ", VName$i, Votum$i, VBedenk$i";
    }
    $stmt = $pdo->query("
        SELECT antrnr, titel, b_date $cols
        FROM " . TABLE_ANTRAEGE . "
        WHERE antrnr LIKE 'B%' AND LENGTH(antrnr) >= 8 AND b_date IS NOT NULL
          AND b_date >= DATE_SUB(CURDATE(), INTERVAL " . (int)$dauer . " DAY)
    ");
    if (!$stmt) return [];

    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $deadline = date('Y-m-d 00:00:00', strtotime($row['b_date'] . ' +' . ($dauer + 1) . ' days'));
        $missing = [];
        for ($i = 1; $i <= 6; $i++) {
            $vid = (int)($row["VName$i"] ?? 0);
            if ($vid <= 0) continue;
            $votum = (int)($row["Votum$i"] ?? 0);
            if ($votum !== 0 && $votum !== 5) continue; // abgestimmt oder befangen
            if ($votum === 5 && !empty($row["VBedenk$i"]) && strtotime($row["VBedenk$i"]) > time()) continue; // Bedenkzeit läuft
            $missing[] = $vid;
        }
        if ($member_id !== null && !in_array((int)$member_id, $missing, true)) continue;
        if (empty($missing)) continue;
        $result[] = [
            'antrnr'   => $row['antrnr'],
            'titel'    => $row['titel'],
            'deadline' => $deadline,
            'missing'  => $missing,
        ];
    }
    return $result;
}

function send_voting_deadline_reminders($pdo) {
    try {
        return send_voting_deadline_reminders_unsafe($pdo);
    } catch (\Throwable $e) {
        error_log('send_voting_deadline_reminders: ' . $e->getMessage());
        return 0;
    }
}

function send_voting_deadline_reminders_unsafe($pdo) {
    if (!function_exists('multipartmail') && file_exists(__DIR__ . '/mail_functions.php')) {
        require_once __DIR__ . '/mail_functions.php';
    }
    if (!function_exists('multipartmail') || !function_exists('get_member_by_id')) return 0;

    // Ohne aktiven Mailversand nichts markieren (sonst gingen die Erinnerungen später verloren)
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) return 0;

    if ($pdo->query("SHOW TABLES LIKE 'svvote_reminders'")->fetchColumn() === false) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS svvote_reminders (
            antrnr VARCHAR(50) NOT NULL,
            member_id INT NOT NULL,
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (antrnr, member_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $cfg = $pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'meeting_system_url' LIMIT 1");
    $base = $cfg ? rtrim(trim((string)$cfg->fetchColumn()), '/') : '';
    if ($base === '' && defined('APP_URL')) $base = rtrim(APP_URL, '/');
    // Ohne absolute Basis-URL keine Mail mit unbrauchbarem Link versenden
    if (!preg_match('#^https?://#i', $base)) {
        error_log('voting_reminder: svconfig.meeting_system_url nicht gesetzt - keine Erinnerungen versendet');
        return 0;
    }

    $now = time();
    $sent = 0;
    foreach (get_open_votings($pdo) as $v) {
        $deadline_ts = strtotime($v['deadline']);
        if ($now < $deadline_ts - 86400 || $now >= $deadline_ts) continue; // nur im letzten Tag

        foreach ($v['missing'] as $mid) {
            $chk = $pdo->prepare("SELECT 1 FROM svvote_reminders WHERE antrnr = ? AND member_id = ?");
            $chk->execute([$v['antrnr'], $mid]);
            if ($chk->fetchColumn()) continue;

            $member = get_member_by_id($pdo, $mid);
            $email = trim($member['email'] ?? '');
            if (!$member || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            // Vor dem Versand markieren, damit parallele Cron-Läufe nicht doppelt senden
            $ins = $pdo->prepare("INSERT IGNORE INTO svvote_reminders (antrnr, member_id) VALUES (?, ?)");
            $ins->execute([$v['antrnr'], $mid]);
            if ($ins->rowCount() === 0) continue;

            $link = $base . '/abstimmungen.php?antrnr=' . urlencode($v['antrnr']);
            $ende = date('d.m.Y', $deadline_ts - 86400) . ' (bis 24 Uhr)';
            $name = $member['first_name'] ?? '';
            $subject = 'Erinnerung: Abstimmung ' . $v['antrnr'] . ' endet bald';
            $text = "Hallo {$name},\n\n"
                . "bei der folgenden Abstimmung fehlt noch deine Stimme:\n\n"
                . "Antrag {$v['antrnr']}: {$v['titel']}\n"
                . "Letzter Abstimmungstag ist der {$ende}.\n\n"
                . "Hier kannst du direkt abstimmen:\n{$link}\n\n"
                . "Bitte beachte: Wenn du bis dahin nicht abstimmst, wird im Beschlussbuch für dich "
                . "\"kein Votum\" eingetragen.\n\n"
                . "Vielen Dank und viele Grüße\nDein Sitzungstool";
            $html = '<p>Hallo ' . htmlspecialchars($name) . ',</p>'
                . '<p>bei der folgenden Abstimmung fehlt noch deine Stimme:</p>'
                . '<p><strong>Antrag ' . htmlspecialchars($v['antrnr']) . ':</strong> ' . htmlspecialchars($v['titel']) . '<br>'
                . 'Letzter Abstimmungstag ist der <strong>' . htmlspecialchars($ende) . '</strong>.</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">&rarr; Jetzt abstimmen</a></p>'
                . '<p>Bitte beachte: Wenn du bis dahin nicht abstimmst, wird im Beschlussbuch für dich '
                . '<strong>&bdquo;kein Votum&ldquo;</strong> eingetragen.</p>'
                . '<p>Vielen Dank und viele Grüße<br>Dein Sitzungstool</p>';

            // Marker bleibt auch bei Fehlschlag stehen: kein Dauer-Retry im Minutentakt
            if (multipartmail($email, $subject, $text, $html)) {
                $sent++;
            } else {
                error_log('voting_reminder: Versand fehlgeschlagen an Mitglied ' . $mid . ' (Antrag ' . $v['antrnr'] . ')');
            }
        }
    }
    return $sent;
}
