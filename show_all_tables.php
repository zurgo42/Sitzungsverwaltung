<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h1>Alle Tabellen in der Datenbank anzeigen</h1>";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>Verbunden mit Datenbank: <strong>" . DB_NAME . "</strong></h2>";

    // Alle Tabellen anzeigen
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3>Gefundene Tabellen (" . count($tables) . "):</h3>";
    echo "<ol>";
    foreach ($tables as $table) {
        echo "<li><strong>" . htmlspecialchars($table) . "</strong>";

        // Markiere wenn es ähnlich wie "berechtigte" ist
        if (stripos($table, 'berecht') !== false) {
            echo " <span style='color: red; font-weight: bold;'>← GEFUNDEN! (ähnlich wie 'berechtigte')</span>";
        }

        echo "</li>";
    }
    echo "</ol>";

    // Prüfe verschiedene Schreibweisen
    echo "<h3>Prüfe verschiedene Schreibweisen:</h3>";
    $variations = ['berechtigte', 'Berechtigte', 'BERECHTIGTE', 'berechtigten', 'berechtigt'];

    foreach ($variations as $var) {
        $result = $pdo->query("SHOW TABLES LIKE '$var'")->fetchAll();
        if (count($result) > 0) {
            echo "✅ <strong style='color: green;'>Tabelle '$var' existiert!</strong><br>";
        } else {
            echo "❌ Tabelle '$var' existiert nicht<br>";
        }
    }

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Fehler:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
