<?php
/**
 * fix_missing_beschluesse.php - Fehlende Beschlüsse in svbeschluesse eintragen
 *
 * PROBLEM:
 * VS-Anträge (beschlossen) existieren in antraege, aber nicht in beschluesse
 *
 * LÖSUNG:
 * 1. Identifiziert alle VS-Anträge in antraege
 * 2. Prüft ob sie in beschluesse existieren
 * 3. Zeigt fehlende Einträge zur Prüfung an
 * 4. Nach Bestätigung: Trägt sie in beschluesse ein
 *
 * VERWENDUNG:
 * php fix_missing_beschluesse.php           # Nur Analyse (Dry-Run)
 * php fix_missing_beschluesse.php --fix     # Mit Eintragung nach Bestätigung
 */

// Config laden wenn vorhanden, sonst Credentials als Parameter
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $db_host = DB_HOST;
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
} else {
    // Standalone-Modus: Credentials als CLI-Parameter
    // Verwendung: php fix_missing_beschluesse.php HOST DB USER PASS [--fix] [--yes]
    $db_host = null;
    $db_name = null;
    $db_user = null;
    $db_pass = null;

    foreach ($argv ?? [] as $i => $arg) {
        if ($i === 0) continue; // Script-Name überspringen
        if (in_array($arg, ['--fix', '--yes'])) continue;
        if ($db_host === null) { $db_host = $arg; continue; }
        if ($db_name === null) { $db_name = $arg; continue; }
        if ($db_user === null) { $db_user = $arg; continue; }
        if ($db_pass === null) { $db_pass = $arg; continue; }
    }

    if (!$db_host || !$db_name || !$db_user) {
        die("VERWENDUNG:\n" .
            "  Mit config.php:    php fix_missing_beschluesse.php [--fix] [--yes]\n" .
            "  Ohne config.php:   php fix_missing_beschluesse.php HOST DB USER PASS [--fix] [--yes]\n\n" .
            "BEISPIEL:\n" .
            "  php fix_missing_beschluesse.php localhost vtool vtool_user 'password' --fix\n\n");
    }

    if ($db_pass === null) $db_pass = ''; // Leeres Passwort falls nicht angegeben
}

// Kommandozeilen-Parameter
$do_fix = in_array('--fix', $argv ?? []);
$auto_yes = in_array('--yes', $argv ?? []);

// Datenbankverbindung
try {
    $pdo = new PDO(
        "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("❌ Datenbankverbindung fehlgeschlagen: " . $e->getMessage() . "\n");
}

echo "====================================================================\n";
echo "  FEHLENDE BESCHLÜSSE IDENTIFIZIEREN UND EINTRAGEN\n";
echo "====================================================================\n\n";

// Tabellennamen ermitteln
// Priorität: Exakte Namen (VTool) vor Präfix-Varianten (Login-System)
$antraege_table = null;
$beschluesse_table = null;

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// 1. Zuerst exakte Namen suchen (VTool/Adapter-System)
foreach ($tables as $table) {
    if (strtolower($table) === 'antraege') $antraege_table = $table;
    if (strtolower($table) === 'beschluesse') $beschluesse_table = $table;
}

// 2. Falls nicht gefunden: Mit Präfix suchen (Login-System)
if (!$antraege_table || !$beschluesse_table) {
    foreach ($tables as $table) {
        if (!$antraege_table && preg_match('/antraege$/i', $table)) {
            $antraege_table = $table;
        }
        if (!$beschluesse_table && preg_match('/beschluesse$/i', $table)) {
            $beschluesse_table = $table;
        }
    }
}

if (!$antraege_table || !$beschluesse_table) {
    die("❌ FEHLER: Tabellen nicht gefunden!\n" .
        "   antraege: " . ($antraege_table ?: "NICHT GEFUNDEN") . "\n" .
        "   beschluesse: " . ($beschluesse_table ?: "NICHT GEFUNDEN") . "\n");
}

echo "✓ Tabellen gefunden:\n";
echo "  - Anträge: $antraege_table\n";
echo "  - Beschlüsse: $beschluesse_table\n\n";

// 1. BESCHLOSSENE ANTRÄGE LADEN
// Verschiedene Kriterien testen
echo "Schritt 1: Suche beschlossene Anträge in $antraege_table...\n\n";

// Zuerst prüfen: Gibt es die Spalte 'praesenz'?
$columns = $pdo->query("SHOW COLUMNS FROM $antraege_table")->fetchAll(PDO::FETCH_COLUMN);
$has_praesenz = in_array('praesenz', $columns);

$beschlossene = [];

// Strategie 1: Über praesenz='Schriftlich' (typisch für VTool)
if ($has_praesenz) {
    echo "  → Prüfe praesenz='Schriftlich'...\n";
    $stmt = $pdo->query("
        SELECT *
        FROM $antraege_table
        WHERE praesenz LIKE '%schrift%'
        ORDER BY antrnr
    ");
    $beschlossene = $stmt->fetchAll();
    echo "    Gefunden: " . count($beschlossene) . "\n";
}

// Strategie 2: Über antrnr LIKE 'VS%' (falls Spalte praesenz nicht hilft)
if (empty($beschlossene)) {
    echo "  → Prüfe antrnr LIKE 'VS%'...\n";
    $stmt = $pdo->query("
        SELECT *
        FROM $antraege_table
        WHERE antrnr LIKE 'VS%'
        ORDER BY antrnr
    ");
    $beschlossene = $stmt->fetchAll();
    echo "    Gefunden: " . count($beschlossene) . "\n";
}

// Strategie 3: Über antrnr LIKE 'B%' (Beschlussanträge)
if (empty($beschlossene)) {
    echo "  → Prüfe antrnr LIKE 'B%'...\n";
    $stmt = $pdo->query("
        SELECT *
        FROM $antraege_table
        WHERE antrnr LIKE 'B%'
        ORDER BY antrnr
    ");
    $beschlossene = $stmt->fetchAll();
    echo "    Gefunden: " . count($beschlossene) . "\n";
}

echo "\n  ✓ Insgesamt " . count($beschlossene) . " beschlossene Anträge gefunden\n\n";

if (empty($beschlossene)) {
    echo "ℹ️  Keine beschlossenen Anträge gefunden.\n\n";
    echo "Bitte prüfen Sie:\n";
    echo "  1. Gibt es überhaupt Anträge in der Tabelle?\n";
    echo "  2. Wie sind beschlossene Anträge markiert?\n";
    echo "  3. Führen Sie diagnose_antraege.php aus für Details.\n\n";
    exit(0);
}

// 2. PRÜFEN WELCHE IN BESCHLUESSE FEHLEN
echo "Schritt 2: Prüfe welche in $beschluesse_table fehlen...\n";
$missing = [];

foreach ($beschlossene as $antrag) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM $beschluesse_table WHERE antrnr = ?");
    $stmt->execute([$antrag['antrnr']]);
    $exists = $stmt->fetchColumn() > 0;

    if (!$exists) {
        $missing[] = $antrag;
    }
}

echo "  → " . count($missing) . " fehlende Einträge identifiziert\n\n";

if (empty($missing)) {
    echo "✓ Alle VS-Anträge sind bereits in beschluesse vorhanden. Keine Aktion nötig.\n";
    exit(0);
}

// 3. FEHLENDE ANZEIGEN
echo "====================================================================\n";
echo "  FEHLENDE BESCHLÜSSE (nicht in $beschluesse_table)\n";
echo "====================================================================\n\n";

foreach ($missing as $i => $antrag) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  #" . ($i + 1) . " von " . count($missing) . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    echo "Antragsnummer: " . ($antrag['antrnr'] ?? 'N/A') . "\n";
    echo "Beschlussart:  " . ($antrag['bart'] ?? 'N/A') . " (" . get_bart_name($antrag['bart'] ?? '') . ")\n";
    echo "Präsenz:       " . ($antrag['praesenz'] ?? 'N/A') . "\n";
    echo "Wichtig:       " . ($antrag['wichtig'] ?? 'N/A') . "\n";
    echo "Intern/Extern: " . ($antrag['int_ext'] ?? 'N/A') . " (" . get_int_ext_name($antrag['int_ext'] ?? '') . ")\n";
    echo "Antragsteller: " . ($antrag['antrst'] ?? 'N/A') . "\n\n";

    echo "Titel:\n  " . wordwrap($antrag['titel'] ?? '(leer)', 70, "\n  ") . "\n\n";

    if (!empty($antrag['beschluss'])) {
        echo "Beschluss:\n  " . wordwrap($antrag['beschluss'], 70, "\n  ") . "\n\n";
    }

    if (!empty($antrag['begr'])) {
        echo "Begründung:\n  " . wordwrap(substr($antrag['begr'], 0, 200), 70, "\n  ");
        if (strlen($antrag['begr']) > 200) echo " [...]";
        echo "\n\n";
    }

    // Abstimmungsergebnis ermitteln
    $votum_summary = get_votum_summary($antrag);
    if ($votum_summary['total'] > 0) {
        echo "Abstimmungsergebnis:\n";
        echo "  Dafür:        " . implode(', ', $votum_summary['dafuer']) . "\n";
        echo "  Dagegen:      " . implode(', ', $votum_summary['dagegen']) . "\n";
        echo "  Enthaltungen: " . implode(', ', $votum_summary['enthaltungen']) . "\n";
        echo "  Sonstige:     " . implode(', ', $votum_summary['sonstige']) . "\n\n";
    }

    if (!empty($antrag['fintext'])) {
        echo "Finanzen: " . ($antrag['fin'] ?? '0') . " € - " . $antrag['fintext'] . "\n\n";
    }

    echo "\n";
}

echo "====================================================================\n\n";

// 4. AKTION: FIX ODER NUR ANZEIGE
if (!$do_fix) {
    echo "ℹ️  DRY-RUN Modus (nur Anzeige)\n\n";
    echo "Um die fehlenden Einträge einzutragen, führen Sie aus:\n";
    echo "  php fix_missing_beschluesse.php --fix\n\n";
    echo "Oder mit automatischer Bestätigung:\n";
    echo "  php fix_missing_beschluesse.php --fix --yes\n\n";
    exit(0);
}

// BESTÄTIGUNG EINHOLEN
echo "====================================================================\n";
echo "  EINTRAGUNG IN $beschluesse_table\n";
echo "====================================================================\n\n";

if (!$auto_yes) {
    echo "⚠️  Sie sind dabei, " . count($missing) . " Einträge in '$beschluesse_table' einzutragen.\n\n";
    echo "Möchten Sie fortfahren? [j/N]: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (!in_array(strtolower($line), ['j', 'ja', 'y', 'yes'])) {
        echo "\n❌ Abgebrochen. Keine Änderungen vorgenommen.\n";
        exit(0);
    }
}

echo "\n";

// 5. EINTRAGUNG DURCHFÜHREN
$success_count = 0;
$error_count = 0;

foreach ($missing as $i => $antrag) {
    $antrnr = $antrag['antrnr'];
    echo "[" . ($i + 1) . "/" . count($missing) . "] Trage ein: $antrnr ... ";

    try {
        // Ressort-Namen laden (wenn Codes vorhanden)
        $ressort_text = '';
        if (!empty($antrag['ressort1'])) {
            $ressort_text = get_ressort_name($pdo, $antrag['ressort1']);
            if (!empty($antrag['ressort2'])) {
                $ressort_text .= ' + ' . get_ressort_name($pdo, $antrag['ressort2']);
            }
        }

        // Abstimmungsergebnis
        $votum = get_votum_summary($antrag);

        // In beschluesse eintragen
        $stmt = $pdo->prepare("
            INSERT INTO $beschluesse_table
            (antrnr, fertig, wichtig, text, ressort, int_ext, titel, beschluss,
             fintext, pers, sach, begr, dafuer, dagegen, enthaltungen, anmerkungen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $antrag['antrnr'],
            '1',  // fertig = 1 (da VS = beschlossen)
            $antrag['wichtig'] ?? null,
            $antrag['ergebnis'] ?? null,
            $ressort_text ?: null,
            $antrag['int_ext'] ?? null,
            $antrag['titel'] ?? null,
            $antrag['beschluss'] ?? null,
            $antrag['fintext'] ?? null,
            $antrag['pers'] ?? null,
            $antrag['sach'] ?? null,
            $antrag['begr'] ?? null,
            implode(', ', $votum['dafuer']) ?: null,
            implode(', ', $votum['dagegen']) ?: null,
            implode(', ', $votum['enthaltungen']) ?: null,
            ($antrag['hinweis'] ?? '') . "\n" . ($antrag['zbem'] ?? '')  // Anmerkungen
        ]);

        echo "✓ OK\n";
        $success_count++;

    } catch (PDOException $e) {
        echo "❌ FEHLER: " . $e->getMessage() . "\n";
        $error_count++;
    }
}

echo "\n====================================================================\n";
echo "  ZUSAMMENFASSUNG\n";
echo "====================================================================\n\n";
echo "Gefunden:     " . count($beschlossene) . " VS-Anträge\n";
echo "Fehlend:      " . count($missing) . " Einträge\n";
echo "Eingetragen:  $success_count Einträge\n";
echo "Fehler:       $error_count Einträge\n\n";

if ($success_count > 0) {
    echo "✓ Erfolgreich abgeschlossen!\n\n";
}

if ($error_count > 0) {
    echo "⚠️  Es gab Fehler. Bitte Error-Log prüfen.\n\n";
}

// ============================================================================
// HILFSFUNKTIONEN
// ============================================================================

function get_bart_name($bart) {
    $names = [
        'V' => 'Verfügung',
        'R' => 'Ressortbeschluss',
        'B' => 'Vorstandsbeschluss'
    ];
    return $names[$bart] ?? 'Unbekannt';
}

function get_int_ext_name($int_ext) {
    $names = [
        'e' => 'Extern/Öffentlich',
        'n' => 'Nicht öffentlich/Führung',
        'i' => 'Intern/Vorstand'
    ];
    return $names[$int_ext] ?? 'Unbekannt';
}

function get_ressort_name($pdo, $ressort_id) {
    static $cache = [];

    if (isset($cache[$ressort_id])) {
        return $cache[$ressort_id];
    }

    // Ressorts-Tabelle finden
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $ressorts_table = null;
    foreach ($tables as $table) {
        if (preg_match('/ressorts$/i', $table)) {
            $ressorts_table = $table;
            break;
        }
    }

    if (!$ressorts_table) {
        $cache[$ressort_id] = "Ressort-$ressort_id";
        return $cache[$ressort_id];
    }

    try {
        $stmt = $pdo->prepare("SELECT Ressort FROM $ressorts_table WHERE Code = ? OR ID = ?");
        $stmt->execute([$ressort_id, $ressort_id]);
        $name = $stmt->fetchColumn();
        $cache[$ressort_id] = $name ?: "Ressort-$ressort_id";
    } catch (PDOException $e) {
        $cache[$ressort_id] = "Ressort-$ressort_id";
    }

    return $cache[$ressort_id];
}

function get_votum_summary($antrag) {
    $dafuer = [];
    $dagegen = [];
    $enthaltungen = [];
    $sonstige = [];
    $total = 0;

    for ($i = 1; $i <= 6; $i++) {
        $vname = $antrag["VName$i"] ?? null;
        $votum = $antrag["Votum$i"] ?? null;

        if (empty($vname)) continue;

        $total++;
        $name = "Person-$vname";  // Vereinfacht, könnte über Adapter geladen werden

        switch ($votum) {
            case '1':  // Ja
                $dafuer[] = $name;
                break;
            case '2':  // Nein
            case '4':  // Rückverweis (zählt als Nein)
                $dagegen[] = $name;
                break;
            case '3':  // Enthaltung
                $enthaltungen[] = $name;
                break;
            case '5':  // Bedenkzeit
            case '6':  // Befangen
                $sonstige[] = $name . " (" . get_votum_name($votum) . ")";
                break;
        }
    }

    return [
        'dafuer' => $dafuer,
        'dagegen' => $dagegen,
        'enthaltungen' => $enthaltungen,
        'sonstige' => $sonstige,
        'total' => $total
    ];
}

function get_votum_name($votum) {
    $names = [
        '1' => 'Ja',
        '2' => 'Nein',
        '3' => 'Enthaltung',
        '4' => 'Rückverweis',
        '5' => 'Bedenkzeit',
        '6' => 'Befangen'
    ];
    return $names[$votum] ?? 'Unbekannt';
}
