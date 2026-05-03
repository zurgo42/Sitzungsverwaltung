<?php
/**
 * debug_config.php - Config-Diagnose
 *
 * WICHTIG: Nach Debug SOFORT löschen oder umbenennen!
 * Enthält sensible Informationen!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Config Debug</h1>";
echo "<pre>";

echo "=== SERVER INFO ===\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'nicht gesetzt') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'nicht gesetzt') . "\n";
echo "SERVER_ADDR: " . ($_SERVER['SERVER_ADDR'] ?? 'nicht gesetzt') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'nicht gesetzt') . "\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "\n";

// Config laden
require_once 'config.php';

echo "=== UMGEBUNGSERKENNUNG ===\n";
echo "IS_LOCAL: " . (IS_LOCAL ? 'TRUE (XAMPP erkannt)' : 'FALSE (Produktiv erkannt)') . "\n";
echo "\n";

echo "=== ALTE MYSQL_* KONSTANTEN ===\n";
echo "MYSQL_HOST: " . (defined('MYSQL_HOST') ? MYSQL_HOST : 'NICHT DEFINIERT') . "\n";
echo "MYSQL_USER: " . (defined('MYSQL_USER') ? MYSQL_USER : 'NICHT DEFINIERT') . "\n";
echo "MYSQL_PASS: " . (defined('MYSQL_PASS') ? '***GESETZT***' : 'NICHT DEFINIERT') . "\n";
echo "MYSQL_DATABASE: " . (defined('MYSQL_DATABASE') ? MYSQL_DATABASE : 'NICHT DEFINIERT') . "\n";
echo "\n";

echo "=== NEUE DB_* KONSTANTEN ===\n";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NICHT DEFINIERT') . "\n";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NICHT DEFINIERT') . "\n";
echo "DB_PASS: " . (defined('DB_PASS') ? (DB_PASS === '' ? 'LEER' : '***GESETZT***') : 'NICHT DEFINIERT') . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NICHT DEFINIERT') . "\n";
echo "\n";

echo "=== DATENBANKVERBINDUNG TESTEN ===\n";
try {
    if (!defined('DB_HOST') || !defined('DB_NAME')) {
        throw new Exception("DB-Konstanten nicht definiert!");
    }

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    echo "DSN: $dsn\n";
    echo "User: " . DB_USER . "\n\n";

    $test_pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "✅ VERBINDUNG ERFOLGREICH!\n";

    // Tabellen prüfen
    $stmt = $test_pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nGefundene Tabellen (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        if (strpos($table, 'sv') === 0) {
            echo "  - $table\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ VERBINDUNG FEHLGESCHLAGEN!\n";
    echo "Fehler: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}

echo "\n=== CONFIG_ADAPTER INFO ===\n";
require_once 'config_adapter.php';
echo "REQUIRE_LOGIN: " . (defined('REQUIRE_LOGIN') ? (REQUIRE_LOGIN ? 'TRUE' : 'FALSE') : 'nicht definiert') . "\n";
echo "MEMBER_SOURCE: " . (defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'nicht definiert') . "\n";

echo "</pre>";

echo "<h2 style='color: red;'>⚠️ WICHTIG: Diese Datei jetzt löschen oder umbenennen!</h2>";
echo "<p>Sie enthält sensible Informationen über deine Datenbank-Konfiguration.</p>";
?>
