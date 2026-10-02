<?php
/**
 * cron_notifications.php – Benachrichtigungs-Logik für den stündlichen Cron-Job
 *
 * Kann auf zwei Arten eingesetzt werden:
 *
 * 1. Include im bestehenden Cron-Skript (empfohlen):
 *      require_once '/pfad/zu/vorstand/Sitzungsverwaltung/cron_notifications.php';
 *    Das Cron-Skript muss $pdo vorher bereitgestellt haben.
 *
 * 2. Direkte Ausführung via PHP-CLI (eigenständig):
 *      php /pfad/zu/vorstand/Sitzungsverwaltung/cron_notifications.php
 *
 * Der pseudo_cron.php übernimmt weiterhin die Echtzeit-Verarbeitung während
 * aktiver Nutzung; dieses Skript schließt die Lücken bei Inaktivität.
 */

// $pdo aus dem aufrufenden Skript verwenden, sonst selbst bootstrappen
if (!isset($pdo)) {
    if (!defined('SV_CONFIG_LOADED')) {
        require_once __DIR__ . '/session_config.php';
        require_once __DIR__ . '/config.php';
    }
    require_once __DIR__ . '/config_adapter.php';
}

if (!isset($pdo)) {
    echo "[" . date('Y-m-d H:i:s') . "] cron_notifications: kein \$pdo – Abbruch\n";
    if (php_sapi_name() === 'cli') exit(1);
    return;
}

$log = function (string $msg) {
    echo "[" . date('Y-m-d H:i:s') . "] cron_notifications: $msg\n";
};

// --- Hilfsfunktionen laden ---
// functions.php wird bewusst ausgelassen – es setzt $pdo selbst auf und
// braucht DB_HOST; wenn wir hier ankommen, ist $pdo bereits vorhanden.
foreach ([
    'notifications_functions.php',
    'voting_helper.php',
    'member_functions.php',
    'mail_functions.php',
    'notification_mailer.php',
] as $f) {
    if (file_exists(__DIR__ . '/' . $f)) {
        require_once __DIR__ . '/' . $f;
    }
}

// --- 1. Meeting-Erinnerungen versenden ---
$table_check = @$pdo->query("SHOW TABLES LIKE 'svnotifications'");
if ($table_check && $table_check->rowCount() > 0 && function_exists('send_meeting_reminder')) {
    // Breiteres Fenster als pseudo_cron: 0–60 Minuten (stündlicher Lauf)
    $stmt = @$pdo->query("
        SELECT meeting_id, meeting_name, meeting_date
        FROM svmeetings
        WHERE status IN ('preparation', 'active')
          AND meeting_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 60 MINUTE)
          AND meeting_id NOT IN (
              SELECT DISTINCT related_meeting_id
              FROM svnotifications
              WHERE type = 'reminder'
                AND related_meeting_id IS NOT NULL
                AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
          )
    ");

    if ($stmt) {
        $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($meetings as $meeting) {
            send_meeting_reminder($pdo, $meeting['meeting_id']);
        }
        if (count($meetings) > 0) {
            $log(count($meetings) . " Meeting-Reminder verschickt");
        }
    }
}

// --- 2. Abstimmungsfristen auswerten ---
$dauer_stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'bart_B_abstimmung_tage' LIMIT 1");
$abstimmung_dauer = $dauer_stmt ? (int)($dauer_stmt->fetchColumn() ?: 7) : 7;
$grenz_date   = date('Y-m-d', strtotime("-{$abstimmung_dauer} days"));
$grenz_yymmdd = date('ymd',   strtotime("-{$abstimmung_dauer} days"));

if (defined('TABLE_ANTRAEGE') && function_exists('auswerten_abstimmung')) {
    $b_stmt = @$pdo->query("
        SELECT antrnr,
               VBedenk1, Votum1, VBedenk2, Votum2, VBedenk3, Votum3,
               VBedenk4, Votum4, VBedenk5, Votum5, VBedenk6, Votum6
        FROM " . TABLE_ANTRAEGE . "
        WHERE antrnr LIKE 'B%'
          AND LENGTH(antrnr) >= 8
          AND (
            (b_date IS NOT NULL AND b_date <= '" . $grenz_date . "')
            OR
            (b_date IS NULL AND SUBSTR(antrnr, 2, 6) <= '" . $grenz_yymmdd . "')
          )
    ");

    if ($b_stmt) {
        $ausgewertet = 0;
        foreach ($b_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bedenkzeit_aktiv = false;
            for ($i = 1; $i <= 6; $i++) {
                if ((int)($row["Votum$i"] ?? 0) === 5
                    && !empty($row["VBedenk$i"])
                    && strtotime($row["VBedenk$i"]) > time()) {
                    $bedenkzeit_aktiv = true;
                    break;
                }
            }
            if (!$bedenkzeit_aktiv) {
                auswerten_abstimmung($pdo, $row['antrnr'], true);
                $ausgewertet++;
            }
        }
        if ($ausgewertet > 0) {
            $log("{$ausgewertet} Abstimmung(en) nach Fristablauf ausgewertet");
        }
    }
}

// --- 3. Agenda-Erinnerungsmail ---
$col_check = @$pdo->query("SHOW COLUMNS FROM svmeetings LIKE 'send_agenda_reminder'");
if ($col_check && $col_check->fetch() && function_exists('send_agenda_reminder_mail')) {
    $remind_stmt = @$pdo->query("
        SELECT meeting_id
        FROM svmeetings
        WHERE send_agenda_reminder = 1
          AND agenda_reminder_sent = 0
          AND submission_deadline IS NOT NULL
          AND submission_deadline <= NOW()
          AND status IN ('preparation', 'active')
    ");

    if ($remind_stmt) {
        // Basis-URL aus svconfig lesen
        $bu_stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'base_url' LIMIT 1");
        $base_url = $bu_stmt ? (string)($bu_stmt->fetchColumn() ?: '') : '';

        $reminded = 0;
        foreach ($remind_stmt->fetchAll(PDO::FETCH_COLUMN) as $mid) {
            $sent = send_agenda_reminder_mail($pdo, (int)$mid, $base_url);
            if ($sent >= 0) $reminded++;
        }
        if ($reminded > 0) {
            $log("{$reminded} Agenda-Erinnerungsmail(s) versendet");
        }
    }
}

// --- 4. Abgelaufene Meeting-Benachrichtigungen als gelesen markieren ---
$expired = @$pdo->exec("
    UPDATE svnotifications n
    JOIN svmeetings m ON n.related_meeting_id = m.meeting_id
    SET n.is_read = 1
    WHERE n.is_read = 0
      AND n.related_meeting_id IS NOT NULL
      AND n.type IN ('meeting', 'reminder')
      AND m.meeting_date < DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
if ($expired > 0) {
    $log("{$expired} abgelaufene Meeting-Benachrichtigung(en) auf gelesen gesetzt");
}

// --- 5. Leere Antrags-Stubs bereinigen ---
if (defined('TABLE_ANTRAEGE')) {
    $deleted_stubs = @$pdo->exec("
        DELETE FROM " . TABLE_ANTRAEGE . "
        WHERE antrnr LIKE 'A%'
          AND (titel IS NULL OR titel = '')
          AND (beschluss IS NULL OR beschluss = '')
          AND lzugriff < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    if ($deleted_stubs > 0) {
        $log("{$deleted_stubs} leere Antrags-Stub(s) gelöscht");
    }
}

// --- 6. E-Mail-Benachrichtigungen (Sofort + Digest) ---
if (function_exists('nm_process_immediate')) {
    nm_process_immediate($pdo);

    $dh_stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'notification_digest_hour' LIMIT 1");
    $digest_hour = $dh_stmt ? (int)($dh_stmt->fetchColumn() ?: 18) : 18;

    $ld_stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'nm_last_digest_date' LIMIT 1");
    $nm_last_digest_row = $ld_stmt ? $ld_stmt->fetchColumn() : false;
    $nm_last_digest = ($nm_last_digest_row !== false) ? (string)$nm_last_digest_row : '';

    $today_d = date('Y-m-d');
    if ($nm_last_digest !== $today_d && (int)date('H') >= $digest_hour) {
        nm_process_digest($pdo);
        $pdo->prepare(
            "INSERT INTO svconfig (config_key, config_value, config_type, description, category)
             VALUES ('nm_last_digest_date', ?, 'text', 'Datum des letzten Digest-Versands', 'notifications')
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)"
        )->execute([$today_d]);
        $log("Digest versendet");
    }
}

$log("Fertig");
