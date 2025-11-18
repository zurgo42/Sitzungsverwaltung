#!/usr/bin/env php
<?php
/**
 * cron_delete_expired_opinions.php - Automatisches Löschen abgelaufener Meinungsbilder
 * Erstellt: 2025-11-18
 *
 * VERWENDUNG:
 * Dieses Script sollte täglich via Cronjob ausgeführt werden, z.B.:
 * 0 2 * * * /usr/bin/php /pfad/zu/cron_delete_expired_opinions.php
 *
 * Löscht alle Meinungsbilder deren delete_at Datum überschritten ist.
 */

// Verhindere direkte Web-Ausführung
if (php_sapi_name() !== 'cli') {
    die("Dieses Script kann nur über die Kommandozeile ausgeführt werden.\n");
}

// Autoload und Config laden
require_once __DIR__ . '/config.php';

// Logging-Funktion
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    error_log("Opinion Cleanup Cron: $message");
}

log_message("=== Opinion Cleanup Cronjob gestartet ===");

try {
    // Finde alle Meinungsbilder die gelöscht werden sollen
    $stmt = $pdo->prepare("
        SELECT poll_id, title, delete_at
        FROM opinion_polls
        WHERE status != 'deleted'
          AND delete_at < NOW()
    ");
    $stmt->execute();
    $polls_to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);

    log_message("Gefunden: " . count($polls_to_delete) . " abgelaufene Meinungsbilder");

    $deleted_count = 0;

    foreach ($polls_to_delete as $poll) {
        $poll_id = $poll['poll_id'];
        $title = $poll['title'];
        $delete_at = $poll['delete_at'];

        log_message("Lösche Meinungsbild #$poll_id: \"$title\" (Ablaufdatum: $delete_at)");

        try {
            // Soft-Delete
            $delete_stmt = $pdo->prepare("
                UPDATE opinion_polls
                SET status = 'deleted'
                WHERE poll_id = ?
            ");
            $delete_stmt->execute([$poll_id]);

            log_message("  ✓ Erfolgreich gelöscht: Meinungsbild #$poll_id");
            $deleted_count++;

        } catch (Exception $e) {
            log_message("  ✗ Fehler beim Löschen von Meinungsbild #$poll_id: " . $e->getMessage());
        }
    }

    log_message("=== Cronjob beendet ===");
    log_message("Gelöscht: $deleted_count Meinungsbild(er)");

    exit(0);

} catch (Exception $e) {
    log_message("KRITISCHER FEHLER: " . $e->getMessage());
    exit(1);
}
