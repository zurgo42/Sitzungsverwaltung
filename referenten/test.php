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
$config_path = __DIR__ . '/config.php';  // LOKALE config.php im referenten/-Verzeichnis
if (file_exists($config_path)) {
    echo "✅ config.php gefunden: $config_path<br>";
    if (is_readable($config_path)) {
        echo "  → lesbar<br>";
        // Config laden (ohne DB-Verbindung zu testen)
        include_once($config_path);
        if (defined('MYSQL_HOST')) {
            echo "  → MYSQL_HOST definiert: " . MYSQL_HOST . "<br>";
        } elseif (defined('DB_HOST')) {
            echo "  → DB_HOST definiert: " . DB_HOST . "<br>";
        } else {
            echo "  → ❌ Keine Datenbank-Konstanten definiert<br>";
        }
    } else {
        echo "  → ❌ NICHT lesbar!<br>";
    }
} else {
    echo "❌ config.php NICHT gefunden!<br>";
    echo "<strong style='color: red;'>WICHTIG: Bitte erstellen Sie referenten/config.php mit Ihren Datenbank-Zugangsdaten!</strong><br>";
    echo "Erwartet in: $config_path<br><br>";
    echo "Vorlage für config.php:<br>";
    echo "<div style='background: #f0f0f0; padding: 10px; font-family: monospace;'>";
    echo htmlspecialchars("<?php\n");
    echo htmlspecialchars("define('MYSQL_HOST', 'localhost');\n");
    echo htmlspecialchars("define('MYSQL_USER', 'ihr_db_user');\n");
    echo htmlspecialchars("define('MYSQL_PASS', 'ihr_db_passwort');\n");
    echo htmlspecialchars("define('MYSQL_DATABASE', 'ihre_datenbank');\n");
    echo htmlspecialchars("?>");
    echo "</div>";
}

// Datenbankverbindung testen (falls config existiert)
echo "<h2>7. Datenbankverbindung</h2>";

// Unterstütze beide Konstantennamen (DB_* und MYSQL_*)
$hasDbConfig = (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) ||
               (defined('MYSQL_HOST') && defined('MYSQL_USER') && defined('MYSQL_PASS') && defined('MYSQL_DATABASE'));

if ($hasDbConfig) {
    $host = defined('DB_HOST') ? DB_HOST : (defined('MYSQL_HOST') ? MYSQL_HOST : 'localhost');
    $user = defined('DB_USER') ? DB_USER : (defined('MYSQL_USER') ? MYSQL_USER : 'root');
    $pass = defined('DB_PASS') ? DB_PASS : (defined('MYSQL_PASS') ? MYSQL_PASS : '');
    $name = defined('DB_NAME') ? DB_NAME : (defined('MYSQL_DATABASE') ? MYSQL_DATABASE : '');

    echo "Verbindungsdetails:<br>";
    echo "  → Host: " . htmlspecialchars($host) . "<br>";
    echo "  → Datenbank: " . htmlspecialchars($name) . "<br>";
    echo "  → User: " . htmlspecialchars($user) . "<br>";
    echo "  → Konstanten-Typ: " . (defined('DB_HOST') ? 'DB_*' : 'MYSQL_*') . "<br><br>";

    try {
        $dsn = "mysql:host=" . $host . ";dbname=" . $name . ";charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "✅ Datenbankverbindung erfolgreich!<br><br>";

        // Alle Tabellen in der Datenbank anzeigen
        echo "<strong>Alle Tabellen in dieser Datenbank:</strong><br>";
        $stmt = $pdo->query("SHOW TABLES");
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($allTables) > 0) {
            echo "<div style='margin-left: 20px; background: #f0f0f0; padding: 10px; margin-top: 5px;'>";
            foreach ($allTables as $tableName) {
                echo "  → " . htmlspecialchars($tableName) . "<br>";
            }
            echo "</div><br>";
        } else {
            echo "  → ⚠️ Keine Tabellen in dieser Datenbank gefunden!<br><br>";
        }

        // Benötigte Tabellen prüfen (verschiedene Schreibweisen)
        echo "<strong>Prüfe benötigte Tabellen:</strong><br>";
        $requiredTables = ['Refname', 'Refpool', 'PLZ'];

        foreach ($requiredTables as $table) {
            // Exakte Suche
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "  → ✅ Tabelle <strong>$table</strong> existiert<br>";

                // Spalten anzeigen
                $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "     Spalten: " . implode(', ', $columns) . "<br>";

                // Anzahl Einträge
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                echo "     Einträge: $count<br>";
            } else {
                // Case-insensitive Suche
                $found = false;
                foreach ($allTables as $existingTable) {
                    if (strtolower($existingTable) === strtolower($table)) {
                        echo "  → ⚠️ Tabelle '$table' nicht gefunden, aber '$existingTable' existiert (Groß-/Kleinschreibung!)<br>";
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    echo "  → ❌ Tabelle <strong>$table</strong> fehlt!<br>";
                }
            }
        }

        echo "<br><strong>Berechtigungen prüfen:</strong><br>";
        try {
            // Versuche in Refname zu lesen (falls vorhanden)
            if (in_array('Refname', $allTables) || in_array('refname', $allTables)) {
                $tableName = in_array('Refname', $allTables) ? 'Refname' : 'refname';
                $stmt = $pdo->query("SELECT * FROM `$tableName` LIMIT 1");
                echo "  → ✅ SELECT-Berechtigung OK<br>";
            }

            // Versuche eine Test-Tabelle zu erstellen und zu löschen
            $pdo->exec("CREATE TEMPORARY TABLE test_permissions (id INT)");
            echo "  → ✅ CREATE-Berechtigung OK<br>";
            $pdo->exec("DROP TEMPORARY TABLE test_permissions");
        } catch (PDOException $e) {
            echo "  → ⚠️ Eingeschränkte Berechtigungen: " . $e->getMessage() . "<br>";
        }

    } catch (PDOException $e) {
        echo "❌ Datenbankverbindung fehlgeschlagen:<br>";
        echo "Fehler: " . htmlspecialchars($e->getMessage()) . "<br>";
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
