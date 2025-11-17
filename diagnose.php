<?php
/**
 * diagnose.php - Zeigt detaillierte Fehlermeldungen
 */

// Fehler ALLE anzeigen
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Diagnose-Start</h1>";

try {
    echo "<p>1. Lade config.php...</p>";
    require_once 'config.php';
    echo "<p>✅ config.php geladen</p>";

    echo "<p>2. Lade config_adapter.php...</p>";
    require_once 'config_adapter.php';
    echo "<p>✅ config_adapter.php geladen</p>";
    echo "<p>MEMBER_SOURCE: " . MEMBER_SOURCE . "</p>";
    echo "<p>REQUIRE_LOGIN: " . (REQUIRE_LOGIN ? 'true' : 'false') . "</p>";

    echo "<p>3. Lade member_functions.php...</p>";
    require_once 'member_functions.php';
    echo "<p>✅ member_functions.php geladen</p>";

    echo "<p>4. Lade functions.php...</p>";
    require_once 'functions.php';
    echo "<p>✅ functions.php geladen</p>";

    echo "<p>5. PDO-Verbindung testen...</p>";
    if (isset($pdo)) {
        echo "<p>✅ PDO-Verbindung existiert</p>";
    } else {
        echo "<p>❌ PDO nicht initialisiert</p>";
    }

    echo "<h2>✅ Alle Dateien laden erfolgreich!</h2>";
    echo "<p><a href='index.php'>Jetzt index.php aufrufen</a></p>";

} catch (Exception $e) {
    echo "<h2>❌ FEHLER:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
