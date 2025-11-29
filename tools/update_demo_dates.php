<?php
/**
 * update_demo_dates.php - Dynamische Datumsanpassung für Demo-Meetings
 *
 * Wird automatisch aufgerufen, wenn DEMO_MODE_ENABLED aktiv ist.
 * Passt die Datumsangaben der Demo-Meetings an, damit sie immer aktuell sind.
 */

if (!defined('DEMO_MODE_ENABLED') || !DEMO_MODE_ENABLED) {
    // Nur im Demo-Modus ausführen
    return;
}

// Timezone setzen (falls noch nicht gesetzt)
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Berlin');
}

/**
 * Aktualisiert die Datumsangaben der Demo-Meetings
 *
 * @param PDO $pdo Datenbankverbindung
 * @return bool Erfolg
 */
function update_demo_meeting_dates($pdo) {
    try {
        $now = new DateTime();

        // Meeting 50: Heute -30 min bis heute +2h, kein Deadline
        $meeting50_start = clone $now;
        $meeting50_start->modify('-30 minutes');
        $meeting50_end = clone $now;
        $meeting50_end->modify('+2 hours');

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = NULL
            WHERE meeting_id = 50
        ");
        $stmt->execute([
            $meeting50_start->format('Y-m-d H:i:s'),
            $meeting50_end->format('Y-m-d H:i:s')
        ]);

        // Meeting 51: Heute+7 Tage 17:00-19:00, Deadline heute+6 23:59
        $meeting51_start = clone $now;
        $meeting51_start->modify('+7 days')->setTime(17, 0, 0);
        $meeting51_end = clone $meeting51_start;
        $meeting51_end->setTime(19, 0, 0);
        $meeting51_deadline = clone $now;
        $meeting51_deadline->modify('+6 days')->setTime(23, 59, 0);

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = ?
            WHERE meeting_id = 51
        ");
        $stmt->execute([
            $meeting51_start->format('Y-m-d H:i:s'),
            $meeting51_end->format('Y-m-d H:i:s'),
            $meeting51_deadline->format('Y-m-d H:i:s')
        ]);

        // Meeting 63: Heute+4 Tage 19:00-20:30, kein Deadline
        $meeting63_start = clone $now;
        $meeting63_start->modify('+4 days')->setTime(19, 0, 0);
        $meeting63_end = clone $meeting63_start;
        $meeting63_end->setTime(20, 30, 0);

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = NULL
            WHERE meeting_id = 63
        ");
        $stmt->execute([
            $meeting63_start->format('Y-m-d H:i:s'),
            $meeting63_end->format('Y-m-d H:i:s')
        ]);

        // Meeting 64: Heute+2 Tage 19:00-20:30, Deadline heute+2 12:00
        $meeting64_start = clone $now;
        $meeting64_start->modify('+2 days')->setTime(19, 0, 0);
        $meeting64_end = clone $meeting64_start;
        $meeting64_end->setTime(20, 30, 0);
        $meeting64_deadline = clone $now;
        $meeting64_deadline->modify('+2 days')->setTime(12, 0, 0);

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = ?
            WHERE meeting_id = 64
        ");
        $stmt->execute([
            $meeting64_start->format('Y-m-d H:i:s'),
            $meeting64_end->format('Y-m-d H:i:s'),
            $meeting64_deadline->format('Y-m-d H:i:s')
        ]);

        // Meeting 65: Heute+1 Tag 19:00-20:30, Deadline heute 00:01
        $meeting65_start = clone $now;
        $meeting65_start->modify('+1 day')->setTime(19, 0, 0);
        $meeting65_end = clone $meeting65_start;
        $meeting65_end->setTime(20, 30, 0);
        $meeting65_deadline = clone $now;
        $meeting65_deadline->setTime(0, 1, 0);

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = ?
            WHERE meeting_id = 65
        ");
        $stmt->execute([
            $meeting65_start->format('Y-m-d H:i:s'),
            $meeting65_end->format('Y-m-d H:i:s'),
            $meeting65_deadline->format('Y-m-d H:i:s')
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Demo date update failed: " . $e->getMessage());
        return false;
    }
}

// Wenn als Standalone-Skript aufgerufen
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once dirname(__DIR__) . '/config.php';

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        if (update_demo_meeting_dates($pdo)) {
            echo "✅ Demo-Meeting-Datumsangaben erfolgreich aktualisiert!\n";
        } else {
            echo "❌ Fehler beim Aktualisieren der Demo-Datumsangaben.\n";
        }
    } catch (PDOException $e) {
        die("Datenbankfehler: " . $e->getMessage());
    }
}
?>
