<?php
/**
 * Migration Runner: Force-Update Tracking
 *
 * Fügt force_update_at Spalte hinzu für besseres Tracking von Prioritäts-Button Updates
 *
 * Aufruf: php run_force_update_migration.php
 * Oder im Browser: http://domain.de/Sitzungsverwaltung/run_force_update_migration.php
 */

require_once('config.php');

// CLI oder Browser?
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    // Im Browser: Login-Prüfung
    session_start();
    if (!isset($_SESSION['member_id'])) {
        die('❌ Nicht eingeloggt. Bitte erst einloggen.');
    }
    echo '<pre>';
}

try {
    // Datenbankverbindung
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "🔌 Datenbankverbindung erfolgreich\n\n";

    // Migration laden
    $migration_file = __DIR__ . '/migrations/add_force_update_tracking.sql';

    if (!file_exists($migration_file)) {
        die("❌ Migration nicht gefunden: $migration_file\n");
    }

    $sql = file_get_contents($migration_file);

    // SQL in einzelne Statements aufteilen
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    echo "📋 Migration: add_force_update_tracking.sql\n";
    echo "➡️  Führe " . count($statements) . " SQL-Statements aus...\n\n";

    foreach ($statements as $i => $statement) {
        try {
            $pdo->exec($statement);
            $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 80);
            echo "✅ [" . ($i + 1) . "] " . $preview . "...\n";
        } catch (PDOException $e) {
            // Spalte existiert bereits? Dann OK
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠️  [" . ($i + 1) . "] Spalte existiert bereits (übersprungen)\n";
            } else {
                echo "❌ [" . ($i + 1) . "] Fehler: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
    }

    echo "\n✅ Migration abgeschlossen!\n\n";
    echo "🎉 Force-Update Tracking ist jetzt aktiv.\n";
    echo "ℹ️  Der Prioritäts-Button funktioniert jetzt zuverlässiger.\n";

} catch (Exception $e) {
    echo "\n❌ Fehler bei Migration:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

if (!$is_cli) {
    echo '</pre>';
}
