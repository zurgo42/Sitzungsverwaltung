<?php
/**
 * Debug-Skript: Zeigt aktuelle Konfigurationswerte an
 */

// OpCache leeren (falls aktiviert)
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OpCache wurde geleert<br><br>";
}

// Config laden
require_once __DIR__ . '/config.php';

echo "<h2>🔍 Konfigurationswerte</h2>";

echo "<h3>Umgebung:</h3>";
echo "IS_LOCAL: " . (IS_LOCAL ? 'TRUE (Lokal)' : 'FALSE (Produktiv)') . "<br>";
echo "DEBUG_MODE: " . (DEBUG_MODE ? 'TRUE' : 'FALSE') . "<br>";
echo "__FILE__: " . __FILE__ . "<br>";

echo "<h3>Datenbank-Konstanten:</h3>";
echo "MYSQL_HOST: " . (defined('MYSQL_HOST') ? MYSQL_HOST : 'NICHT DEFINIERT') . "<br>";
echo "MYSQL_USER: " . (defined('MYSQL_USER') ? MYSQL_USER : 'NICHT DEFINIERT') . "<br>";
echo "MYSQL_PASS: " . (defined('MYSQL_PASS') ? (MYSQL_PASS === '' ? '(leer)' : '***' . substr(MYSQL_PASS, -3)) : 'NICHT DEFINIERT') . "<br>";
echo "MYSQL_DATABASE: " . (defined('MYSQL_DATABASE') ? MYSQL_DATABASE : 'NICHT DEFINIERT') . "<br>";

echo "<h3>Aliases (DB_*):</h3>";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NICHT DEFINIERT') . "<br>";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NICHT DEFINIERT') . "<br>";
echo "DB_PASS: " . (defined('DB_PASS') ? (DB_PASS === '' ? '(leer)' : '***' . substr(DB_PASS, -3)) : 'NICHT DEFINIERT') . "<br>";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NICHT DEFINIERT') . "<br>";

echo "<h3>Datenbankverbindung testen:</h3>";
try {
    $test_pdo = new PDO(
        "mysql:host=" . MYSQL_HOST . ";dbname=" . MYSQL_DATABASE . ";charset=utf8mb4",
        MYSQL_USER,
        MYSQL_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ <strong style='color: green;'>Datenbankverbindung erfolgreich!</strong><br>";
    echo "Server-Version: " . $test_pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
} catch (PDOException $e) {
    echo "❌ <strong style='color: red;'>Datenbankverbindung fehlgeschlagen:</strong><br>";
    echo "Fehler: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<h3>Server-Info:</h3>";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "<br>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "<br>";
echo "SERVER_ADDR: " . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>";

echo "<hr>";
echo "<p><strong>⚠️ WICHTIG:</strong> Lösche diese Datei nach dem Debugging!</p>";
?>
