<?php
/**
 * pseudo_cron.php - Cron-Job-Ersatz für Hosting ohne minütliche Cron-Jobs
 *
 * Wird in index.php eingebunden und läuft bei jedem Seitenaufruf
 * Prüft ob genug Zeit vergangen ist und führt dann Reminder-Check aus
 *
 * Performance: Nur 1x pro Minute, auch bei vielen Seitenaufrufen
 */

// Nur ausführen wenn nicht CLI
if (php_sapi_name() === 'cli') {
    return;
}

// Lock-File-Pfad
$lock_file = __DIR__ . '/pseudo_cron.lock';
$lock_timeout = 60; // Sekunden zwischen Ausführungen

// Prüfen ob genug Zeit vergangen ist
$should_run = false;

if (!file_exists($lock_file)) {
    $should_run = true;
} else {
    $last_run = intval(file_get_contents($lock_file));
    if ($last_run === 0) {
        // Datei existiert aber ist leer oder korrupt
        $should_run = true;
    } else {
        $time_since_last = time() - $last_run;
        if ($time_since_last >= $lock_timeout) {
            $should_run = true;
        }
    }
}

// Nur ausführen wenn Intervall abgelaufen
if ($should_run) {
    // Lock-File SOFORT aktualisieren (verhindert Race Conditions)
    file_put_contents($lock_file, time());

    // Meeting-Erinnerungen im Hintergrund ausführen
    try {
        // Prüfen ob $pdo verfügbar ist (aus index.php)
        if (!isset($pdo)) {
            return; // Kein Fehler - einfach überspringen
        }

        // Prüfen ob notifications_functions.php geladen wurde
        if (!function_exists('send_meeting_reminder')) {
            // Nachladen falls noch nicht vorhanden
            if (file_exists(__DIR__ . '/notifications_functions.php')) {
                require_once __DIR__ . '/notifications_functions.php';
            } else {
                return; // Funktion nicht verfügbar
            }
        }

        // Prüfen ob svnotifications Tabelle existiert
        $table_check = @$pdo->query("SHOW TABLES LIKE 'svnotifications'");
        if (!$table_check || $table_check->rowCount() === 0) {
            // Tabelle existiert noch nicht - Migration wurde nicht ausgeführt
            return; // Kein Fehler - einfach überspringen
        }

        // Meetings in 15-45 Minuten finden (breites Fenster für Pseudo-Cron)
        // Verhindert verpasste Erinnerungen bei seltenen Seitenaufrufen
        $stmt = $pdo->query("
            SELECT meeting_id, meeting_name, meeting_date
            FROM svmeetings
            WHERE status IN ('preparation', 'active')
            AND meeting_date BETWEEN DATE_ADD(NOW(), INTERVAL 15 MINUTE) AND DATE_ADD(NOW(), INTERVAL 45 MINUTE)
            AND meeting_id NOT IN (
                SELECT DISTINCT related_meeting_id
                FROM svnotifications
                WHERE type = 'reminder'
                AND related_meeting_id IS NOT NULL
                AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
            )
        ");

        $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Erinnerungen versenden
        foreach ($meetings as $meeting) {
            send_meeting_reminder($pdo, $meeting['meeting_id']);
        }

        // Optional: In Log-Datei schreiben
        if (count($meetings) > 0) {
            $log_msg = "[" . date('Y-m-d H:i:s') . "] Pseudo-Cron: " . count($meetings) . " Reminder(s) sent\n";
            @file_put_contents(__DIR__ . '/pseudo_cron.log', $log_msg, FILE_APPEND);
        }

        // ---- Abstimmungsfrist-Check ----
        // Abstimmungsdauer aus svconfig lesen (Default: 7 Tage)
        $dauer_stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'bart_B_abstimmung_tage' LIMIT 1");
        $abstimmung_dauer = $dauer_stmt ? (int)($dauer_stmt->fetchColumn() ?: 7) : 7;

        // Grenz-Datum im YYMMDD-Format (= Datum vor $abstimmung_dauer Tagen)
        $grenz_yymmdd = date('ymd', strtotime("-{$abstimmung_dauer} days"));

        // Alle B-Anträge suchen, deren Datum (YYMMDD in antrnr) die Frist überschritten hat
        if (defined('TABLE_ANTRAEGE')) {
            $b_stmt = @$pdo->query("
                SELECT antrnr,
                       VBedenk1, Votum1, VBedenk2, Votum2, VBedenk3, Votum3,
                       VBedenk4, Votum4, VBedenk5, Votum5, VBedenk6, Votum6
                FROM " . TABLE_ANTRAEGE . "
                WHERE antrnr LIKE 'B%'
                  AND LENGTH(antrnr) >= 8
                  AND SUBSTR(antrnr, 2, 6) <= '" . $grenz_yymmdd . "'
            ");

            if ($b_stmt) {
                // voting_helper.php laden falls noch nicht geschehen
                if (!function_exists('auswerten_abstimmung')) {
                    $helper = __DIR__ . '/includes/voting_helper.php';
                    if (file_exists($helper)) require_once $helper;
                    // member_functions.php wird von beschluss_annehmen() benötigt
                    if (file_exists(__DIR__ . '/member_functions.php')) {
                        require_once __DIR__ . '/member_functions.php';
                    }
                }

                $ausgewertet = 0;
                foreach ($b_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    // Prüfen ob eine Bedenkzeit noch aktiv ist
                    $bedenkzeit_aktiv = false;
                    for ($i = 1; $i <= 6; $i++) {
                        if ((int)($row["Votum$i"] ?? 0) === 5
                            && !empty($row["VBedenk$i"])
                            && strtotime($row["VBedenk$i"]) > time()) {
                            $bedenkzeit_aktiv = true;
                            break;
                        }
                    }

                    if (!$bedenkzeit_aktiv && function_exists('auswerten_abstimmung')) {
                        auswerten_abstimmung($pdo, $row['antrnr'], true);
                        $ausgewertet++;
                    }
                }

                if ($ausgewertet > 0) {
                    $log_msg = "[" . date('Y-m-d H:i:s') . "] Pseudo-Cron: {$ausgewertet} Abstimmung(en) nach Fristablauf ausgewertet\n";
                    @file_put_contents(__DIR__ . '/pseudo_cron.log', $log_msg, FILE_APPEND);
                }
            }
        }

        // ---- Agenda-Erinnerungsmail-Check ----
        // Sitzungen finden, bei denen der Antragsschluss abgelaufen ist,
        // Erinnerungsmail aktiviert und noch nicht versendet wurde
        $col_check = @$pdo->query("SHOW COLUMNS FROM svmeetings LIKE 'send_agenda_reminder'");
        if ($col_check && $col_check->fetch()) {
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
                // mail_functions.php laden falls noch nicht geschehen
                if (!function_exists('send_agenda_reminder_mail')) {
                    $mail_file = __DIR__ . '/mail_functions.php';
                    if (file_exists($mail_file)) require_once $mail_file;
                }
                // member_functions.php für get_member_by_id() benötigt
                if (!function_exists('get_member_by_id') && file_exists(__DIR__ . '/member_functions.php')) {
                    require_once __DIR__ . '/member_functions.php';
                }

                // Basis-URL ermitteln (für Links in der Mail)
                $base_url = '';
                if (isset($_SERVER['HTTP_HOST'])) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $base_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . (defined('STANDALONE_PATH') ? STANDALONE_PATH : '');
                }

                $reminded = 0;
                foreach ($remind_stmt->fetchAll(PDO::FETCH_COLUMN) as $mid) {
                    if (function_exists('send_agenda_reminder_mail')) {
                        $sent = send_agenda_reminder_mail($pdo, (int)$mid, $base_url);
                        if ($sent >= 0) $reminded++;
                    }
                }

                if ($reminded > 0) {
                    $log_msg = "[" . date('Y-m-d H:i:s') . "] Pseudo-Cron: {$reminded} Agenda-Erinnerungsmail(s) versendet\n";
                    @file_put_contents(__DIR__ . '/pseudo_cron.log', $log_msg, FILE_APPEND);
                }
            }
        }

    } catch (Exception $e) {
        // Fehler loggen aber nicht ausgeben (um Seite nicht zu stören)
        $error_msg = "[" . date('Y-m-d H:i:s') . "] Pseudo-Cron Error: " . $e->getMessage() . "\n";
        @file_put_contents(__DIR__ . '/pseudo_cron.log', $error_msg, FILE_APPEND);
        // Wichtig: Exception nicht weiterwerfen - Seite soll normal laden
    }
}
