<?php
/**
 * cron_cleanup_external_participants.php - Automatische Löschung alter externer Teilnehmer
 * Erstellt: 2025-12-18
 *
 * BESCHREIBUNG:
 * Löscht externe Teilnehmer, die länger als 6 Monate inaktiv sind (DSGVO-konform)
 *
 * VERWENDUNG:
 * 1. Via Crontab (empfohlen - täglich ausführen):
 *    0 2 * * * /usr/bin/php /pfad/zu/cron_cleanup_external_participants.php >> /var/log/cleanup_external.log 2>&1
 *
 * 2. Via Browser (nur für Tests - sollte in Production deaktiviert werden):
 *    https://ihre-domain.de/Sitzungsverwaltung/cron_cleanup_external_participants.php
 *
 * 3. Via CLI:
 *    php cron_cleanup_external_participants.php
 */

// Sicherheit: Nur via CLI oder mit Secret-Key via Browser
$cli_mode = (php_sapi_name() === 'cli');
$secret_key = ''; // TODO: Secret-Key setzen für Browser-Zugriff (oder leer lassen um Browser-Zugriff zu deaktivieren)

if (!$cli_mode) {
    // Browser-Zugriff
    if (empty($secret_key)) {
        die('Dieser Cron-Job kann nur via CLI ausgeführt werden.');
    }

    $provided_key = $_GET['key'] ?? '';
    if ($provided_key !== $secret_key) {
        http_response_code(403);
        die('Zugriff verweigert.');
    }
}

// Konfiguration und Funktionen laden
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/external_participants_functions.php';

// Log-Ausgabe vorbereiten
$timestamp = date('Y-m-d H:i:s');
echo "[$timestamp] Starte Cleanup externe Teilnehmer...\n";

try {
    // Cleanup ausführen
    $deleted_count = cleanup_old_external_participants($pdo);

    echo "[$timestamp] Erfolgreich $deleted_count externe(r) Teilnehmer gelöscht (älter als 6 Monate inaktiv).\n";

    // Optional: Statistiken ausgeben
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM svexternal_participants");
    $total = $stmt->fetch()['total'];
    echo "[$timestamp] Verbleibende externe Teilnehmer: $total\n";

    // Detailierte Statistik nach Umfrage-Typ
    $stmt = $pdo->query("
        SELECT poll_type, COUNT(*) as count
        FROM svexternal_participants
        GROUP BY poll_type
    ");
    while ($row = $stmt->fetch()) {
        echo "[$timestamp]   - {$row['poll_type']}: {$row['count']}\n";
    }

    // Success Exit-Code
    exit(0);

} catch (Exception $e) {
    $error_msg = $e->getMessage();
    echo "[$timestamp] FEHLER: $error_msg\n";
    error_log("Cleanup externe Teilnehmer fehlgeschlagen: $error_msg");

    // Error Exit-Code
    exit(1);
}
?>
