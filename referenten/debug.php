<?php
/**
 * Debug-Version von referenten.php
 * Zeigt alle Fehler an
 */

// Fehler anzeigen
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug</title></head><body>";
echo "<h1>Debug-Modus</h1>";

echo "<h2>1. PHP-Version</h2>";
echo "PHP: " . phpversion() . "<br>";

echo "<h2>2. Dateien prüfen</h2>";
$files = [
    'config.php',
    'includes/Database.php',
    'includes/ReferentenModel.php',
    'includes/Security.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✅ $file existiert<br>";
    } else {
        echo "❌ $file FEHLT!<br>";
    }
}

echo "<h2>3. Config laden</h2>";
try {
    require_once __DIR__ . '/config.php';
    echo "✅ config.php geladen<br>";

    if (defined('MYSQL_HOST')) {
        echo "✅ MYSQL_HOST definiert: " . MYSQL_HOST . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Fehler beim Laden: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Includes laden</h2>";
try {
    require_once __DIR__ . '/includes/Database.php';
    echo "✅ Database.php geladen<br>";
} catch (Exception $e) {
    echo "❌ Database.php Fehler: " . $e->getMessage() . "<br>";
}

try {
    require_once __DIR__ . '/includes/ReferentenModel.php';
    echo "✅ ReferentenModel.php geladen<br>";
} catch (Exception $e) {
    echo "❌ ReferentenModel.php Fehler: " . $e->getMessage() . "<br>";
}

try {
    require_once __DIR__ . '/includes/Security.php';
    echo "✅ Security.php geladen<br>";
} catch (Exception $e) {
    echo "❌ Security.php Fehler: " . $e->getMessage() . "<br>";
}

echo "<h2>5. Session starten</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "✅ Session gestartet: " . session_id() . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Session Fehler: " . $e->getMessage() . "<br>";
}

echo "<h2>6. Klassen instanziieren</h2>";
try {
    $model = new ReferentenModel();
    echo "✅ ReferentenModel instanziiert<br>";
} catch (Exception $e) {
    echo "❌ ReferentenModel Fehler: " . $e->getMessage() . "<br>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>7. Anzahl aktive Vorträge</h2>";
try {
    $anzahl = $model->countActiveVortraege();
    echo "✅ Anzahl aktive Vorträge: $anzahl<br>";
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "<br>";
}

echo "<h2>8. Template-Dateien prüfen</h2>";
$templates = [
    'templates/header.php',
    'templates/footer.php',
    'templates/formular.php',
    'templates/liste.php'
];

foreach ($templates as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✅ $file existiert<br>";
    } else {
        echo "❌ $file FEHLT!<br>";
    }
}

echo "<h2>9. Originalskript testen</h2>";
echo "<p>Wenn alles oben grün ist, teste jetzt die echte referenten.php...</p>";

// Versuche das Original zu laden
ob_start();
try {
    include __DIR__ . '/referenten.php';
    $output = ob_get_clean();
    if (strlen($output) > 0) {
        echo "✅ Output erzeugt (" . strlen($output) . " Bytes)<br>";
        echo "<hr>";
        echo $output;
    } else {
        echo "⚠️ Kein Output generiert!<br>";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ Fehler beim Laden von referenten.php:<br>";
    echo "Fehler: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>
