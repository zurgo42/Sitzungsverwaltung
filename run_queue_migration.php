<?php
/**
 * Migration Runner: Queue-basiertes kollaboratives Protokoll
 *
 * Erstellt Queue-Tabelle für Master-Slave Pattern
 *
 * Aufruf: php run_queue_migration.php
 * Oder im Browser: http://domain.de/Sitzungsverwaltung/run_queue_migration.php
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
    $migration_file = __DIR__ . '/migrations/add_protocol_queue_system.sql';

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

    echo "📋 Migration: add_protocol_queue_system.sql\n";
    echo "➡️  Führe " . count($statements) . " SQL-Statements aus...\n\n";

    foreach ($statements as $i => $statement) {
        try {
            $pdo->exec($statement);
            $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 80);
            echo "✅ [" . ($i + 1) . "] " . $preview . "...\n";
        } catch (PDOException $e) {
            // Bereits existiert? Dann OK
            if (strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "⚠️  [" . ($i + 1) . "] Bereits vorhanden (übersprungen)\n";
            } else {
                echo "❌ [" . ($i + 1) . "] Fehler: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
    }

    echo "\n✅ Migration abgeschlossen!\n\n";
    echo "🎉 Queue-System ist jetzt aktiv.\n";
    echo "📝 Master-Slave Pattern mit Protokollführung als Master.\n\n";
    echo "ℹ️  Nächste Schritte:\n";
    echo "   1. Hard-Refresh im Browser (Ctrl+F5)\n";
    echo "   2. Kollaboratives Protokoll testen\n";
    echo "   3. Protokollführung hat 2 Felder\n";
    echo "   4. Andere User haben 1 Feld\n";

} catch (Exception $e) {
    echo "\n❌ Fehler bei Migration:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

if (!$is_cli) {
    echo '</pre>';
}
