<?php
/**
 * run_collab_migration.php - Führt Collaborative Protocol Migration aus
 */

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h2>Collaborative Protocol Migration</h2>\n";
    echo "<pre>\n";

    // Migration-SQL laden
    $sql = file_get_contents('migrations/add_collaborative_protocol.sql');

    // SQL in einzelne Statements aufteilen (anhand von Semikolon)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Leere Statements und Kommentar-only Zeilen überspringen
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    foreach ($statements as $statement) {
        // Skip reine Kommentare
        if (preg_match('/^\s*--/', $statement)) {
            continue;
        }

        try {
            $pdo->exec($statement);
            // Ersten Teil des Statements für Ausgabe extrahieren
            $preview = substr($statement, 0, 80);
            echo "✓ " . $preview . "...\n";
        } catch (PDOException $e) {
            // Fehler ausgeben aber weitermachen (falls Tabelle bereits existiert)
            echo "⚠ Warnung: " . $e->getMessage() . "\n";
        }
    }

    echo "\n✅ Migration abgeschlossen!\n";
    echo "</pre>\n";

} catch (Exception $e) {
    echo "<pre>❌ Fehler: " . $e->getMessage() . "</pre>\n";
    exit(1);
}
