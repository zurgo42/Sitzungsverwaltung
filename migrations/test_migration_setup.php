<?php
/**
 * test_migration_setup.php - Prüft ob Migration ausgeführt werden kann
 *
 * Usage: php migrations/test_migration_setup.php
 */

echo "===========================================\n";
echo "MIGRATION SETUP TEST\n";
echo "===========================================\n\n";

// 1. PHP Version prüfen
echo "1. PHP Version: " . phpversion();
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo " ✓\n";
} else {
    echo " ✗ (mindestens 7.4 erforderlich)\n";
    exit(1);
}

// 2. Match-Expression Support (PHP 8.0+)
echo "2. Match-Expression: ";
if (version_compare(phpversion(), '8.0.0', '>=')) {
    echo "✓\n";
} else {
    echo "✗ (PHP 8.0+ empfohlen für match())\n";
}

// 3. Config laden
echo "3. Config-Datei: ";
if (file_exists(__DIR__ . '/../config.php')) {
    echo "✓\n";
    require_once __DIR__ . '/../config.php';
} else {
    echo "✗ (config.php nicht gefunden)\n";
    exit(1);
}

// 4. PDO Verbindung prüfen
echo "4. Datenbankverbindung: ";
try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("PDO nicht initialisiert");
    }
    $pdo->query("SELECT 1");
    echo "✓\n";
} catch (Exception $e) {
    echo "✗ (" . $e->getMessage() . ")\n";
    exit(1);
}

// 5. Source-DB Zugriff testen
echo "\n5. SOURCE-DATENBANK (VTool):\n";
$source_dbs = [];
$stmt = $pdo->query("SHOW DATABASES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    if (stripos($row[0], 'vtool') !== false || stripos($row[0], 'v_tool') !== false) {
        $source_dbs[] = $row[0];
    }
}

if (empty($source_dbs)) {
    echo "   ✗ Keine VTool-DB gefunden!\n";
    echo "   Hinweis: Suche nach DB-Namen mit 'vtool' oder 'v_tool'\n";
    echo "   Verfügbare Datenbanken:\n";
    $stmt = $pdo->query("SHOW DATABASES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "     - {$row[0]}\n";
    }
} else {
    echo "   Gefundene VTool-Datenbanken:\n";
    foreach ($source_dbs as $db) {
        echo "     - $db\n";

        // Prüfe ob antraege-Tabelle existiert
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$db`.antraege")->fetchColumn();
            echo "       ✓ Tabelle 'antraege' vorhanden ($count Einträge)\n";
        } catch (PDOException $e) {
            echo "       ✗ Tabelle 'antraege' nicht gefunden\n";
        }
    }
}

// 6. Target-DB Zugriff testen
echo "\n6. TARGET-DATENBANK (Sitzungsverwaltung):\n";
$target_dbs = [];
$stmt = $pdo->query("SHOW DATABASES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    if (stripos($row[0], 'sitzung') !== false || stripos($row[0], 'meeting') !== false) {
        $target_dbs[] = $row[0];
    }
}

if (empty($target_dbs)) {
    echo "   ✗ Keine Sitzungsverwaltung-DB gefunden!\n";
    echo "   Hinweis: Suche nach DB-Namen mit 'sitzung' oder 'meeting'\n";
} else {
    echo "   Gefundene Sitzungsverwaltung-DBs:\n";
    foreach ($target_dbs as $db) {
        echo "     - $db\n";

        // Prüfe ob svbproposals-Tabelle existiert
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$db`.svbproposals")->fetchColumn();
            echo "       ✓ Tabelle 'svbproposals' vorhanden ($count Einträge)\n";
        } catch (PDOException $e) {
            echo "       ✗ Tabelle 'svbproposals' nicht gefunden\n";
            echo "       → Bitte zuerst 'php init-db.php' ausführen!\n";
        }
    }
}

// 7. Empfehlung
echo "\n===========================================\n";
echo "EMPFEHLUNG:\n";
echo "===========================================\n";

if (!empty($source_dbs) && !empty($target_dbs)) {
    $source = $source_dbs[0];
    $target = $target_dbs[0];

    echo "Starte Migration mit:\n\n";
    echo "  php migrations/migrate_vtool_data.php \\\n";
    echo "    --source-db=$source \\\n";
    echo "    --target-db=$target \\\n";
    echo "    --dry-run\n\n";
    echo "Bei Erfolg dann mit --execute ausführen.\n";
} else {
    echo "Bitte zuerst Datenbanken einrichten:\n";
    if (empty($source_dbs)) {
        echo "  - VTool-Datenbank importieren\n";
    }
    if (empty($target_dbs)) {
        echo "  - Sitzungsverwaltung: php init-db.php\n";
    }
}

echo "\n";
?>
