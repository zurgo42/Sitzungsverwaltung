<?php
/**
 * cron_meeting_reminders.php - Meeting-Erinnerungen versenden
 *
 * Cron-Job: Läuft jede Minute und prüft, ob in 30 Minuten ein Meeting beginnt
 *
 * Crontab-Eintrag:
 * * * * * * /usr/bin/php /pfad/zu/Sitzungsverwaltung/cron_meeting_reminders.php >> /var/log/meeting_reminders.log 2>&1
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/notifications_functions.php';

// Nur CLI erlaubt
if (php_sapi_name() !== 'cli' && !defined('ALLOW_CRON_WEB')) {
    die('CLI only');
}

echo "[" . date('Y-m-d H:i:s') . "] Meeting Reminder Cron started\n";

try {
    // Meetings in 15-45 Minuten finden
    // Breites Zeitfenster um verpasste Erinnerungen zu vermeiden
    $stmt = $pdo->query("
        SELECT meeting_id, meeting_name, meeting_date
        FROM svmeetings
        WHERE status IN ('preparation', 'active')
        AND meeting_date BETWEEN DATE_ADD(NOW(), INTERVAL 15 MINUTE) AND DATE_ADD(NOW(), INTERVAL 45 MINUTE)
        AND meeting_id NOT IN (
            -- Keine doppelten Erinnerungen
            SELECT DISTINCT related_meeting_id
            FROM svnotifications
            WHERE type = 'reminder'
            AND related_meeting_id IS NOT NULL
            AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
        )
    ");

    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($meetings) . " meeting(s) starting in 15-45 minutes\n";

    foreach ($meetings as $meeting) {
        echo "  - Sending reminders for: {$meeting['meeting_name']} ({$meeting['meeting_id']})\n";
        send_meeting_reminder($pdo, $meeting['meeting_id']);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Done\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("Meeting Reminder Cron Error: " . $e->getMessage());
}
