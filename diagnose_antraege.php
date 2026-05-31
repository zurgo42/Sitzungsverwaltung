<?php
/**
 * diagnose_antraege.php - Analysiert Anträge und Beschlüsse
 *
 * Zeigt welche Präfixe existieren und wo Lücken sind
 */

// Config laden wenn vorhanden, sonst Credentials erfragen
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $db_host = DB_HOST;
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
} else {
    // Standalone-Modus: Credentials als Parameter
    $db_host = $argv[1] ?? readline("DB Host (z.B. localhost): ");
    $db_name = $argv[2] ?? readline("DB Name: ");
    $db_user = $argv[3] ?? readline("DB User: ");
    $db_pass = $argv[4] ?? readline("DB Password: ");
}

$pdo = new PDO(
    "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
    $db_user,
    $db_pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "====================================================================\n";
echo "  DIAGNOSE: ANTRÄGE UND BESCHLÜSSE\n";
echo "====================================================================\n\n";

// Tabellen finden
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$antraege_table = null;
$beschluesse_table = null;

foreach ($tables as $table) {
    if (preg_match('/antraege$/i', $table)) $antraege_table = $table;
    if (preg_match('/beschluesse$/i', $table)) $beschluesse_table = $table;
}

echo "Tabellen:\n";
echo "  Anträge:     " . ($antraege_table ?: "NICHT GEFUNDEN") . "\n";
echo "  Beschlüsse:  " . ($beschluesse_table ?: "NICHT GEFUNDEN") . "\n\n";

if (!$antraege_table) {
    die("❌ Keine Anträge-Tabelle gefunden!\n");
}

// 1. ALLE PRÄFIXE IN ANTRAEGE
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  PRÄFIXE IN $antraege_table\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$stmt = $pdo->query("
    SELECT
        SUBSTRING(antrnr, 1, 1) as prefix1,
        SUBSTRING(antrnr, 1, 2) as prefix2,
        SUBSTRING(antrnr, 1, 3) as prefix3,
        COUNT(*) as anzahl,
        MIN(antrnr) as erste,
        MAX(antrnr) as letzte
    FROM $antraege_table
    GROUP BY prefix1, prefix2, prefix3
    ORDER BY anzahl DESC
");

$praefixe = $stmt->fetchAll();

echo "Gefundene Präfix-Muster:\n\n";
printf("%-8s %-8s %-8s %8s   %-12s   %-12s\n", "1-Char", "2-Char", "3-Char", "Anzahl", "Erste", "Letzte");
echo str_repeat("-", 70) . "\n";

foreach ($praefixe as $p) {
    printf("%-8s %-8s %-8s %8d   %-12s   %-12s\n",
        $p['prefix1'], $p['prefix2'], $p['prefix3'],
        $p['anzahl'], $p['erste'], $p['letzte']
    );
}

echo "\n";

// 2. BESCHLOSSENE ANTRÄGE (bart spalte prüfen)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  STATUS-ANALYSE (BESCHLOSSENE ANTRÄGE)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Prüfe ob es eine praesenz Spalte gibt (VS = Vorstandsbeschluss Schriftlich)
$columns = $pdo->query("SHOW COLUMNS FROM $antraege_table")->fetchAll(PDO::FETCH_COLUMN);

if (in_array('praesenz', $columns)) {
    echo "✓ Spalte 'praesenz' gefunden - analysiere:\n\n";

    $stmt = $pdo->query("
        SELECT praesenz, COUNT(*) as anzahl
        FROM $antraege_table
        WHERE praesenz IS NOT NULL
        GROUP BY praesenz
        ORDER BY anzahl DESC
    ");

    $praesenz_arten = $stmt->fetchAll();

    printf("%-20s %8s\n", "Präsenz-Typ", "Anzahl");
    echo str_repeat("-", 30) . "\n";

    foreach ($praesenz_arten as $pa) {
        printf("%-20s %8d\n", $pa['praesenz'] ?: '(leer)', $pa['anzahl']);
    }

    echo "\n";

    // Zeige Beispiele für jede Präsenz-Art
    echo "Beispiele:\n\n";

    $stmt = $pdo->query("
        SELECT praesenz, antrnr, titel
        FROM $antraege_table
        WHERE praesenz IS NOT NULL
        GROUP BY praesenz
        ORDER BY praesenz
        LIMIT 10
    ");

    $beispiele = $stmt->fetchAll();

    foreach ($beispiele as $b) {
        echo "  " . ($b['praesenz'] ?: '(leer)') . ": ";
        echo $b['antrnr'] . " - ";
        echo substr($b['titel'], 0, 50) . (strlen($b['titel']) > 50 ? '...' : '');
        echo "\n";
    }

    echo "\n";
}

// 3. WENN BESCHLUESSE-TABELLE EXISTIERT: VERGLEICH
if ($beschluesse_table) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  VERGLEICH: ANTRÄGE vs. BESCHLÜSSE\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // Präfixe in beschluesse
    $stmt = $pdo->query("
        SELECT
            SUBSTRING(antrnr, 1, 3) as prefix,
            COUNT(*) as anzahl
        FROM $beschluesse_table
        GROUP BY prefix
        ORDER BY anzahl DESC
    ");

    $beschluss_praefixe = $stmt->fetchAll();

    echo "Präfixe in $beschluesse_table:\n\n";
    printf("%-10s %8s\n", "Präfix", "Anzahl");
    echo str_repeat("-", 20) . "\n";

    foreach ($beschluss_praefixe as $bp) {
        printf("%-10s %8d\n", $bp['prefix'], $bp['anzahl']);
    }

    echo "\n";

    // FEHLENDE FINDEN
    echo "Suche nach Anträgen die als 'Schriftlich' markiert sind aber nicht in beschluesse:\n\n";

    $stmt = $pdo->query("
        SELECT a.antrnr, a.praesenz, a.titel
        FROM $antraege_table a
        LEFT JOIN $beschluesse_table b ON a.antrnr = b.antrnr
        WHERE a.praesenz LIKE '%schrift%'
        AND b.antrnr IS NULL
        ORDER BY a.antrnr DESC
        LIMIT 20
    ");

    $fehlende = $stmt->fetchAll();

    if (count($fehlende) > 0) {
        echo "⚠️  " . count($fehlende) . " fehlende Einträge gefunden:\n\n";

        printf("%-12s %-20s %-50s\n", "Antrnr", "Präsenz", "Titel");
        echo str_repeat("-", 85) . "\n";

        foreach ($fehlende as $f) {
            printf("%-12s %-20s %-50s\n",
                $f['antrnr'],
                $f['praesenz'],
                substr($f['titel'] ?? '', 0, 47) . (strlen($f['titel'] ?? '') > 47 ? '...' : '')
            );
        }

        echo "\n";
        echo "💡 Tipp: Passen Sie fix_missing_beschluesse.php an:\n";
        echo "   WHERE praesenz LIKE '%schrift%'  statt  WHERE antrnr LIKE 'VS%'\n\n";

    } else {
        echo "✓ Keine fehlenden schriftlichen Beschlüsse gefunden.\n\n";
    }
}

echo "====================================================================\n";
echo "  ANALYSE ABGESCHLOSSEN\n";
echo "====================================================================\n";
