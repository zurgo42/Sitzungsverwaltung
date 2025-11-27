#!/usr/bin/env php
<?php
/**
 * cron_send_poll_reminders.php - Cronjob zum Versand von Erinnerungsmails
 * Erstellt: 2025-11-18
 *
 * VERWENDUNG:
 * Dieses Script sollte täglich via Cronjob ausgeführt werden, z.B.:
 * 0 8 * * * /usr/bin/php /pfad/zu/cron_send_poll_reminders.php
 *
 * Das Script prüft alle finalisierten Umfragen mit aktivierter Erinnerung
 * und versendet die Erinnerungsmail X Tage vor dem Termin.
 */

// Verhindere direkte Web-Ausführung
if (php_sapi_name() !== 'cli') {
    die("Dieses Script kann nur über die Kommandozeile ausgeführt werden.\n");
}

// Autoload und Config laden
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mail_functions.php';

// Logging-Funktion
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    error_log("Poll Reminders Cron: $message");
}

log_message("=== Poll Reminders Cronjob gestartet ===");

try {
    // Basis-URL für Links (aus Config oder Umgebungsvariable)
    $host_url_base = defined('APP_URL') ? APP_URL : 'https://example.com';

    // Finde alle Umfragen mit aktivierter Erinnerung die noch nicht versendet wurde
    $stmt = $pdo->prepare("
        SELECT p.poll_id, p.title, p.reminder_days, p.final_date_id, pd.suggested_date
        FROM svpolls p
        LEFT JOIN svpoll_dates pd ON p.final_date_id = pd.date_id
        WHERE p.status = 'finalized'
          AND p.reminder_enabled = 1
          AND p.reminder_sent = 0
          AND p.final_date_id IS NOT NULL
          AND pd.suggested_date IS NOT NULL
          AND DATEDIFF(pd.suggested_date, NOW()) <= p.reminder_days
          AND DATEDIFF(pd.suggested_date, NOW()) > 0
    ");
    $stmt->execute();
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    log_message("Gefunden: " . count($polls) . " fällige Erinnerungen");

    $total_sent = 0;
    $total_failed = 0;

    foreach ($polls as $poll) {
        $poll_id = $poll['poll_id'];
        $title = $poll['title'];
        $days_until = ceil((strtotime($poll['suggested_date']) - time()) / 86400);

        log_message("Verarbeite Umfrage #$poll_id: \"$title\" (Termin in $days_until Tag(en))");

        try {
            // Erinnerungsmail senden
            $sent_count = send_poll_reminder($pdo, $poll_id, $host_url_base);

            if ($sent_count > 0) {
                // Markiere als versendet
                $update_stmt = $pdo->prepare("
                    UPDATE svpolls
                    SET reminder_sent = 1
                    WHERE poll_id = ?
                ");
                $update_stmt->execute([$poll_id]);

                log_message("  ✓ Erfolgreich: $sent_count E-Mail(s) versendet für Umfrage #$poll_id");
                $total_sent += $sent_count;
            } else {
                log_message("  ⚠ Warnung: Keine E-Mails versendet für Umfrage #$poll_id (evtl. keine Empfänger)");
                // Trotzdem als versendet markieren um Endlos-Wiederholungen zu vermeiden
                $update_stmt = $pdo->prepare("
                    UPDATE svpolls
                    SET reminder_sent = 1
                    WHERE poll_id = ?
                ");
                $update_stmt->execute([$poll_id]);
            }

        } catch (Exception $e) {
            log_message("  ✗ Fehler bei Umfrage #$poll_id: " . $e->getMessage());
            $total_failed++;
        }
    }

    log_message("=== Cronjob beendet ===");
    log_message("Gesamt versendet: $total_sent E-Mail(s)");
    if ($total_failed > 0) {
        log_message("Fehlgeschlagen: $total_failed Umfrage(n)");
    }

    exit(0);

} catch (Exception $e) {
    log_message("KRITISCHER FEHLER: " . $e->getMessage());
    exit(1);
}
