<?php
/**
 * Migration Runner: Lock-System für kollaborative Mitschrift
 */

require_once 'config.php';

// CLI oder Browser?
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    // Im Browser: Login-Prüfung
    session_start();
    if (!isset($_SESSION['member_id'])) {
        die('❌ Nicht eingeloggt. Bitte erst einloggen.');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre>';
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "🔧 Migration: Lock-System für kollaborative Mitschrift\n";
echo str_repeat('=', 60) . "\n\n";

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

    // Migration-Datei lesen
    $sql_file = __DIR__ . '/migrations/create_protocol_lock_table.sql';

    if (!file_exists($sql_file)) {
        throw new Exception("Migration-Datei nicht gefunden: $sql_file");
    }

    $sql = file_get_contents($sql_file);

    // Kommentare entfernen (-- Zeilen)
    $lines = explode("\n", $sql);
    $cleaned_lines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed) || strpos($trimmed, '--') === 0) {
            continue;
        }
        $cleaned_lines[] = $line;
    }
    $sql_cleaned = implode("\n", $cleaned_lines);

    // SQL in einzelne Statements aufteilen
    $statements = array_filter(
        array_map('trim', explode(';', $sql_cleaned)),
        function($stmt) {
            return !empty($stmt);
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

    if (!$is_cli) {
        echo "\n\n" . '<a href="index.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #4caf50; color: white; text-decoration: none; border-radius: 4px;">Zurück zur Anwendung</a>';
    }

} catch (Exception $e) {
    echo "\n❌ FEHLER: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

if (!$is_cli) {
    echo '</pre>';
}

echo "\n✅ Migration erfolgreich abgeschlossen!\n";
