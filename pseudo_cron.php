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

    } catch (Exception $e) {
        // Fehler loggen aber nicht ausgeben (um Seite nicht zu stören)
        $error_msg = "[" . date('Y-m-d H:i:s') . "] Pseudo-Cron Error: " . $e->getMessage() . "\n";
        @file_put_contents(__DIR__ . '/pseudo_cron.log', $error_msg, FILE_APPEND);
        // Wichtig: Exception nicht weiterwerfen - Seite soll normal laden
    }
}
