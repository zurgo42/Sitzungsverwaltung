<?php
/**
 * fix_missing_beschluesse.php - Fehlende VS-Beschlüsse eintragen
 *
 * Sucht VS-Anträge in antraege die nicht in beschluesse sind
 * und trägt sie nach Bestätigung ein.
 *
 * VERWENDUNG:
 * - Im Browser: fix_missing_beschluesse.php
 * - Mit Eintragung: fix_missing_beschluesse.php?fix
 * - CLI: php fix_missing_beschluesse.php [--fix]
 */

// Config laden
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $db_host = DB_HOST;
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASS;
} else {
    die("FEHLER: config.php nicht gefunden!");
}

// Parameter
$do_fix = isset($_GET['fix']) || in_array('--fix', $argv ?? []);
$is_cli = php_sapi_name() === 'cli';

// Datenbankverbindung
try {
    $pdo = new PDO(
        "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// HTML-Header (nur wenn nicht CLI)
if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>VS-Beschlüsse prüfen</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 3px solid #0066cc; padding-bottom: 10px; }
        h2 { color: #0066cc; margin-top: 30px; }
        .box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 5px solid #28a745; }
        .error { background: #f8d7da; border-left: 5px solid #dc3545; }
        .warning { background: #fff3cd; border-left: 5px solid #ffc107; }
        .info { background: #d1ecf1; border-left: 5px solid #17a2b8; }
        .antrag { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #6c757d; }
        .label { font-weight: bold; color: #0066cc; display: inline-block; min-width: 150px; }
        .value { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0066cc; color: white; }
        .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px 0 0; background: #0066cc; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style></head><body>";
}

// Tabellennamen ermitteln
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$antraege_table = null;
$beschluesse_table = null;
$ressorts_table = null;

foreach ($tables as $table) {
    if (strtolower($table) === 'antraege') $antraege_table = $table;
    if (strtolower($table) === 'beschluesse') $beschluesse_table = $table;
    if (strtolower($table) === 'ressorts') $ressorts_table = $table;
}

if (!$antraege_table || !$beschluesse_table) {
    foreach ($tables as $table) {
        if (!$antraege_table && preg_match('/antraege$/i', $table)) $antraege_table = $table;
        if (!$beschluesse_table && preg_match('/beschluesse$/i', $table)) $beschluesse_table = $table;
        if (!$ressorts_table && preg_match('/ressorts$/i', $table)) $ressorts_table = $table;
    }
}

if (!$antraege_table || !$beschluesse_table) {
    if ($is_cli) {
        die("FEHLER: Tabellen nicht gefunden!\n");
    } else {
        echo "<div class='box error'><h2>❌ Fehler</h2>";
        echo "<p>Tabellen nicht gefunden:</p>";
        echo "<ul><li>antraege: " . ($antraege_table ?: "NICHT GEFUNDEN") . "</li>";
        echo "<li>beschluesse: " . ($beschluesse_table ?: "NICHT GEFUNDEN") . "</li></ul></div></body></html>";
        exit;
    }
}

// HTML-Ausgabe
if (!$is_cli) {
    echo "<h1>🔍 VS-Beschlüsse prüfen</h1>";
    echo "<div class='box info'>";
    echo "<p><span class='label'>Anträge-Tabelle:</span> <span class='value'>$antraege_table</span></p>";
    echo "<p><span class='label'>Beschlüsse-Tabelle:</span> <span class='value'>$beschluesse_table</span></p>";
    echo "</div>";
}

// 1. VS-ANTRÄGE LADEN
if (!$is_cli) echo "<h2>Schritt 1: VS-Anträge laden</h2>";

$stmt = $pdo->query("SELECT * FROM $antraege_table WHERE antrnr LIKE 'VS%' ORDER BY antrnr DESC");
$vs_antraege = $stmt->fetchAll();

if (!$is_cli) {
    echo "<div class='box success'>";
    echo "<p>✓ " . count($vs_antraege) . " VS-Anträge gefunden</p>";
    echo "</div>";
} else {
    echo "VS-Anträge gefunden: " . count($vs_antraege) . "\n";
}

if (empty($vs_antraege)) {
    if (!$is_cli) {
        echo "<div class='box info'><p>ℹ️ Keine VS-Anträge in der Datenbank.</p></div></body></html>";
    } else {
        echo "Keine VS-Anträge gefunden.\n";
    }
    exit(0);
}

// 2. FEHLENDE ERMITTELN
if (!$is_cli) echo "<h2>Schritt 2: Fehlende Beschlüsse ermitteln</h2>";

$missing = [];
foreach ($vs_antraege as $antrag) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $beschluesse_table WHERE antrnr = ?");
    $stmt->execute([$antrag['antrnr']]);
    if ($stmt->fetchColumn() == 0) {
        $missing[] = $antrag;
    }
}

if (!$is_cli) {
    echo "<div class='box " . (count($missing) > 0 ? 'warning' : 'success') . "'>";
    echo "<p><strong>" . count($missing) . " fehlende Einträge</strong> in $beschluesse_table identifiziert</p>";
    echo "</div>";
} else {
    echo "Fehlende Einträge: " . count($missing) . "\n\n";
}

if (empty($missing)) {
    if (!$is_cli) {
        echo "<div class='box success'><p>✓ Alle VS-Anträge sind bereits in beschluesse vorhanden!</p></div></body></html>";
    } else {
        echo "Alle VS-Anträge bereits vorhanden.\n";
    }
    exit(0);
}

// 3. FEHLENDE ANZEIGEN
if (!$is_cli) {
    echo "<h2>Fehlende Beschlüsse (" . count($missing) . " Einträge)</h2>";

    foreach ($missing as $i => $a) {
        echo "<div class='antrag'>";
        echo "<h3>Antrag #" . ($i + 1) . " von " . count($missing) . "</h3>";
        echo "<p><span class='label'>Antragsnummer:</span> <span class='value'>" . h($a['antrnr']) . "</span></p>";
        echo "<p><span class='label'>Titel:</span> <span class='value'>" . h($a['titel']) . "</span></p>";

        if (!empty($a['beschluss'])) {
            echo "<p><span class='label'>Beschluss:</span></p>";
            echo "<pre>" . h($a['beschluss']) . "</pre>";
        }

        if (!empty($a['begr'])) {
            echo "<p><span class='label'>Begründung:</span></p>";
            echo "<pre>" . h(substr($a['begr'], 0, 300)) . (strlen($a['begr']) > 300 ? '...' : '') . "</pre>";
        }

        echo "<p><span class='label'>Präsenz:</span> " . h($a['praesenz'] ?? 'N/A') . "</p>";
        echo "<p><span class='label'>Intern/Extern:</span> " . h($a['int_ext'] ?? 'N/A') . "</p>";

        if (!empty($a['fintext'])) {
            echo "<p><span class='label'>Finanzen:</span> " . h($a['fin'] ?? '0') . " € - " . h($a['fintext']) . "</p>";
        }

        echo "</div>";
    }

    // Aktions-Buttons
    echo "<div class='box'>";
    if (!$do_fix) {
        echo "<h3>Aktion erforderlich</h3>";
        echo "<p>Um die fehlenden Einträge einzutragen:</p>";
        echo "<a href='?fix' class='btn btn-success'>✓ Jetzt eintragen (" . count($missing) . " Einträge)</a>";
    }
    echo "</div>";

} else {
    // CLI-Ausgabe
    foreach ($missing as $i => $a) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Antrag #" . ($i + 1) . " von " . count($missing) . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Antragsnummer: " . $a['antrnr'] . "\n";
        echo "Titel: " . $a['titel'] . "\n\n";
    }
}

// 4. EINTRAGUNG
if ($do_fix) {
    if (!$is_cli) {
        echo "<h2>Schritt 3: Eintragung durchführen</h2>";
        echo "<div class='box'>";
    }

    $success = 0;
    $errors = 0;

    foreach ($missing as $i => $a) {
        try {
            // Ressort-Namen laden
            $ressort_text = '';
            if (!empty($a['ressort1']) && $ressorts_table) {
                $stmt = $pdo->prepare("SELECT Ressort FROM $ressorts_table WHERE Code = ? OR ID = ?");
                $stmt->execute([$a['ressort1'], $a['ressort1']]);
                $ressort_text = $stmt->fetchColumn() ?: "Ressort-{$a['ressort1']}";

                if (!empty($a['ressort2'])) {
                    $stmt->execute([$a['ressort2'], $a['ressort2']]);
                    $r2 = $stmt->fetchColumn() ?: "Ressort-{$a['ressort2']}";
                    $ressort_text .= ' + ' . $r2;
                }
            } elseif (!empty($a['ressort1'])) {
                // Ressorts-Tabelle nicht gefunden - verwende Code direkt
                $ressort_text = "Ressort-{$a['ressort1']}";
                if (!empty($a['ressort2'])) {
                    $ressort_text .= ' + ' . "Ressort-{$a['ressort2']}";
                }
            }

            // Eintragen
            $stmt = $pdo->prepare("
                INSERT INTO $beschluesse_table
                (antrnr, fertig, wichtig, text, ressort, int_ext, titel, beschluss,
                 fintext, pers, sach, begr, anmerkungen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $a['antrnr'],
                '1',
                $a['wichtig'] ?? null,
                $a['ergebnis'] ?? null,
                $ressort_text ?: null,
                $a['int_ext'] ?? null,
                $a['titel'] ?? null,
                $a['beschluss'] ?? null,
                $a['fintext'] ?? null,
                $a['pers'] ?? null,
                $a['sach'] ?? null,
                $a['begr'] ?? null,
                trim(($a['hinweis'] ?? '') . "\n" . ($a['zbem'] ?? ''))
            ]);

            $success++;
            if (!$is_cli) {
                echo "<p>✓ " . h($a['antrnr']) . " - " . h($a['titel']) . "</p>";
            } else {
                echo "✓ " . $a['antrnr'] . "\n";
            }

        } catch (PDOException $e) {
            $errors++;
            if (!$is_cli) {
                echo "<p class='error'>❌ " . h($a['antrnr']) . ": " . h($e->getMessage()) . "</p>";
            } else {
                echo "❌ " . $a['antrnr'] . ": " . $e->getMessage() . "\n";
            }
        }
    }

    if (!$is_cli) {
        echo "</div>";
        echo "<div class='box " . ($errors > 0 ? 'warning' : 'success') . "'>";
        echo "<h3>Zusammenfassung</h3>";
        echo "<p><strong>Eingetragen:</strong> $success von " . count($missing) . "</p>";
        if ($errors > 0) echo "<p><strong>Fehler:</strong> $errors</p>";
        echo "</div>";
    } else {
        echo "\n✓ Fertig: $success eingetragen, $errors Fehler\n";
    }
}

if (!$is_cli) {
    echo "</body></html>";
}

function h($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
