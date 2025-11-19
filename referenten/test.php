<?php
/**
 * Test-Skript für Diagnose
 */

// Error Reporting aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Server-Test</h1>";

// PHP-Version prüfen
echo "<h2>1. PHP-Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.4.0', '<')) {
    echo "⚠️ <strong>WARNUNG:</strong> PHP-Version zu alt! Mindestens 7.4 erforderlich.<br>";
} else {
    echo "✅ PHP-Version OK<br>";
}

// PDO prüfen
echo "<h2>2. PDO Extension</h2>";
if (extension_loaded('pdo')) {
    echo "✅ PDO ist installiert<br>";
    if (extension_loaded('pdo_mysql')) {
        echo "✅ PDO MySQL Driver ist installiert<br>";
    } else {
        echo "❌ <strong>FEHLER:</strong> PDO MySQL Driver fehlt!<br>";
    }
} else {
    echo "❌ <strong>FEHLER:</strong> PDO ist nicht installiert!<br>";
}

// Weitere wichtige Extensions
echo "<h2>3. Wichtige Extensions</h2>";
$required = ['json', 'mbstring', 'session'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext<br>";
    } else {
        echo "⚠️ $ext fehlt<br>";
    }
}

// Sessions testen
echo "<h2>4. Session-Test</h2>";
if (session_start()) {
    echo "✅ Sessions funktionieren<br>";
    echo "Session ID: " . session_id() . "<br>";
} else {
    echo "❌ Sessions funktionieren NICHT<br>";
}

// Dateiberechtigungen prüfen
echo "<h2>5. Dateiberechtigungen</h2>";
$dirs = [
    __DIR__,
    __DIR__ . '/includes',
    __DIR__ . '/templates',
    __DIR__ . '/css',
    __DIR__ . '/js'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ $dir existiert<br>";
        if (is_readable($dir)) {
            echo "  → lesbar<br>";
        } else {
            echo "  → ❌ NICHT lesbar!<br>";
        }
    } else {
        echo "❌ $dir existiert NICHT!<br>";
    }
}

// config.php prüfen
echo "<h2>6. Config.php</h2>";
$config_path = __DIR__ . '/../config.php';
if (file_exists($config_path)) {
    echo "✅ config.php gefunden: $config_path<br>";
    if (is_readable($config_path)) {
        echo "  → lesbar<br>";
        // Config laden (ohne DB-Verbindung zu testen)
        include_once($config_path);
        if (defined('MYSQL_HOST')) {
            echo "  → MYSQL_HOST definiert: " . MYSQL_HOST . "<br>";
        } else {
            echo "  → ❌ MYSQL_HOST nicht definiert<br>";
        }
    } else {
        echo "  → ❌ NICHT lesbar!<br>";
    }
} else {
    echo "❌ config.php NICHT gefunden! Erwartet in: $config_path<br>";
    echo "Alternative Pfade versuchen:<br>";
    $alternatives = [
        __DIR__ . '/config.php',
        dirname(__DIR__) . '/config.php',
        $_SERVER['DOCUMENT_ROOT'] . '/config.php'
    ];
    foreach ($alternatives as $alt) {
        if (file_exists($alt)) {
            echo "  → Gefunden: $alt<br>";
        }
    }
}

// Datenbankverbindung testen (falls config existiert)
echo "<h2>7. Datenbankverbindung</h2>";
if (defined('MYSQL_HOST') && defined('MYSQL_USER') && defined('MYSQL_PASS') && defined('MYSQL_DATABASE')) {
    try {
        $dsn = "mysql:host=" . MYSQL_HOST . ";dbname=" . MYSQL_DATABASE . ";charset=utf8mb4";
        $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "✅ Datenbankverbindung erfolgreich!<br>";

        // Tabellen prüfen
        $tables = ['Refname', 'Refpool'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "  → ✅ Tabelle $table existiert<br>";
            } else {
                echo "  → ❌ Tabelle $table fehlt!<br>";
            }
        }
    } catch (PDOException $e) {
        echo "❌ Datenbankverbindung fehlgeschlagen:<br>";
        echo "Fehler: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ Datenbank-Konstanten nicht definiert<br>";
}

// .htaccess prüfen
echo "<h2>8. .htaccess</h2>";
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "✅ .htaccess existiert<br>";
    echo "  → Eventuell Probleme verursachen? Versuche sie umzubenennen (.htaccess_backup)<br>";
} else {
    echo "ℹ️ Keine .htaccess vorhanden<br>";
}

echo "<hr>";
echo "<h2>Zusammenfassung</h2>";
echo "<p>Wenn hier Fehler (❌) angezeigt werden, sind das die wahrscheinlichen Ursachen des Internal Server Errors.</p>";
?>
