<?php
/**
 * Migration Runner: Lock-System für kollaborative Mitschrift
 */

require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🔧 Migration: Lock-System für kollaborative Mitschrift\n";
echo str_repeat('=', 60) . "\n\n";

try {
    // Datenbankverbindung
    $pdo = get_db_connection();
    echo "🔌 Datenbankverbindung erfolgreich\n\n";

    // Migration-Datei lesen
    $sql_file = __DIR__ . '/migrations/create_protocol_lock_table.sql';

    if (!file_exists($sql_file)) {
        throw new Exception("Migration-Datei nicht gefunden: $sql_file");
    }

    $sql = file_get_contents($sql_file);

    // SQL in einzelne Statements aufteilen
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    echo "📝 Führe " . count($statements) . " SQL-Statement(s) aus:\n\n";

    $success_count = 0;
    foreach ($statements as $index => $statement) {
        $stmt_num = $index + 1;

        // Erste 50 Zeichen des Statements anzeigen
        $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 50) . '...';
        echo "[$stmt_num] $preview\n";

        try {
            $pdo->exec($statement);
            echo "    ✅ Erfolgreich\n";
            $success_count++;
        } catch (PDOException $e) {
            echo "    ⚠️ Warnung: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    echo str_repeat('=', 60) . "\n";
    echo "✅ Migration abgeschlossen!\n";
    echo "   $success_count von " . count($statements) . " Statements erfolgreich ausgeführt\n\n";

    // Lock-Tabelle überprüfen
    $stmt = $pdo->query("SHOW TABLES LIKE 'svprotocol_lock'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabelle 'svprotocol_lock' existiert\n";

        // Spalten anzeigen
        $columns = $pdo->query("DESCRIBE svprotocol_lock")->fetchAll(PDO::FETCH_COLUMN);
        echo "   Spalten: " . implode(', ', $columns) . "\n";
    } else {
        echo "❌ Tabelle 'svprotocol_lock' nicht gefunden!\n";
    }

} catch (Exception $e) {
    echo "\n❌ FEHLER: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✅ Migration erfolgreich abgeschlossen!\n";
