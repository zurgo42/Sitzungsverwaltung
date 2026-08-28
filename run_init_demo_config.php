<?php
/**
 * run_init_demo_config.php - Führt die Demo-Konfiguration aus
 *
 * Dieses Skript erstellt:
 * - svconfig Tabelle
 * - antraege Tabelle
 * - svressorts Tabelle
 * - Alle Konfigurationseinträge für aktiv-Level, Antragstypen, Voting, Terminologie
 * - Demo-Ressorts
 */

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "<h1>Demo-Konfiguration initialisieren</h1>";
    echo "<p>Führe Migration aus migrations/init_demo_config.sql aus...</p>";

    // SQL-Datei einlesen
    $sql_file = __DIR__ . '/migrations/init_demo_config.sql';

    if (!file_exists($sql_file)) {
        die("<p style='color: red;'>✗ Fehler: Datei $sql_file nicht gefunden!</p>");
    }

    $sql = file_get_contents($sql_file);

    // SQL in einzelne Statements aufteilen (an Semikolons außerhalb von Strings)
    // Vereinfachte Version: Split an ';\n'
    $statements = array_filter(
        array_map('trim', explode(";\n", $sql)),
        function($stmt) {
            // Entferne Kommentare und leere Zeilen
            $stmt = preg_replace('/^--.*$/m', '', $stmt);
            $stmt = trim($stmt);
            return !empty($stmt) && $stmt !== ';';
        }
    );

    $success_count = 0;
    $error_count = 0;

    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $success_count++;
            echo ".";

            // Flush output für besseres Feedback
            if ($success_count % 50 == 0) {
                echo " ($success_count)<br>";
                flush();
            }
        } catch (PDOException $e) {
            $error_count++;
            // Einige Fehler sind OK (z.B. "Table already exists", "Duplicate entry")
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "~"; // Tabelle/Eintrag existiert bereits
            } else {
                echo "<br><p style='color: orange;'>⚠ Warnung: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }

    echo "<br>";
    echo "<h2 style='color: green;'>✓ Migration abgeschlossen!</h2>";
    echo "<p><strong>$success_count</strong> Statements erfolgreich ausgeführt.</p>";

    if ($error_count > 0) {
        echo "<p style='color: orange;'>$error_count Statements wurden übersprungen (Tabellen/Einträge existieren bereits).</p>";
    }

    // Prüfe ob Tabellen erstellt wurden
    echo "<h3>Überprüfung der Tabellen:</h3>";
    echo "<ul>";

    $tables_to_check = ['svconfig', 'antraege', 'svressorts'];
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count_stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
            $count = $count_stmt->fetch()['cnt'];
            echo "<li style='color: green;'>✓ Tabelle <strong>$table</strong> existiert ($count Einträge)</li>";
        } else {
            echo "<li style='color: red;'>✗ Tabelle <strong>$table</strong> fehlt!</li>";
        }
    }
    echo "</ul>";

    // Prüfe Konfigurationseinträge
    $config_stmt = $pdo->query("SELECT category, COUNT(*) as cnt FROM svconfig GROUP BY category");
    echo "<h3>Konfigurationseinträge nach Kategorie:</h3>";
    echo "<ul>";
    while ($row = $config_stmt->fetch()) {
        echo "<li><strong>{$row['category']}</strong>: {$row['cnt']} Einträge</li>";
    }
    echo "</ul>";

    echo "<hr>";
    echo "<h3>Nächste Schritte:</h3>";
    echo "<ul>";
    echo "<li><a href='index.php'>Zur Anwendung</a></li>";
    echo "<li><a href='tab_admin_init.php'>Admin-Bereich öffnen</a> - Konfiguration anpassen</li>";
    echo "<li><a href='tools/demo_export.php'>Demo-Daten exportieren</a> - Nach dem Anlegen von Testdaten</li>";
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Datenbankfehler: " . htmlspecialchars($e->getMessage()) . "</p>";
    die();
}
?>
