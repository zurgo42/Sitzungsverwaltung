<?php
/**
 * merge_beschluesse_to_antraege.php
 *
 * Kopiert Daten aus beschluesse in die neuen besch_* Felder von antraege
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Config laden
require_once __DIR__ . '/../config.php';

// Datenbankverbindung
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

echo "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <title>Beschlüsse → Anträge Merge</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
        }
        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .ok {
            color: green;
            font-weight: bold;
        }
        .warning {
            color: orange;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .stats {
            background: #e7f3ff;
            padding: 15px;
            border-left: 4px solid #0066cc;
            margin: 10px 0;
        }
        button {
            background: #0066cc;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px 10px 0;
        }
        button:hover {
            background: #0052a3;
        }
        button.danger {
            background: #dc3545;
        }
        button.danger:hover {
            background: #c82333;
        }
        .log {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 13px;
        }
        .log-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <h1>Beschlüsse in Anträge mergen</h1>
";

$action = $_GET['action'] ?? '';

if ($action === '') {
    // Übersicht zeigen
    echo "<div class='section'>";
    echo "<h2>Übersicht</h2>";

    // Statistiken
    $stats = [];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM beschluesse");
    $stats['beschluesse_total'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM antraege WHERE fertig = '1'");
    $stats['antraege_fertig'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM beschluesse b
        INNER JOIN antraege a ON b.antrnr = a.antrnr
    ");
    $stats['matches'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM beschluesse b
        LEFT JOIN antraege a ON b.antrnr = a.antrnr
        WHERE a.antrnr IS NULL
    ");
    $stats['beschluesse_without_antrag'] = $stmt->fetch()['count'];

    echo "<div class='stats'>";
    echo "<strong>Aktuelle Situation:</strong><br>";
    echo "• Beschlüsse in beschluesse-Tabelle: <strong>{$stats['beschluesse_total']}</strong><br>";
    echo "• Anträge mit fertig='1': <strong>{$stats['antraege_fertig']}</strong><br>";
    echo "• Beschlüsse mit passendem Antrag: <strong>{$stats['matches']}</strong><br>";

    if ($stats['beschluesse_without_antrag'] > 0) {
        echo "• <span class='warning'>⚠ Beschlüsse ohne Antrag: {$stats['beschluesse_without_antrag']}</span><br>";
    }
    echo "</div>";

    echo "<h3>Was passiert beim Merge?</h3>";
    echo "<ol>";
    echo "<li>Für jeden Eintrag in <code>beschluesse</code> wird der passende Antrag gesucht</li>";
    echo "<li>Die Felder aus beschluesse werden in die <code>besch_*</code> Felder kopiert</li>";
    echo "<li>Das <code>fertig</code>-Flag wird auf '1' gesetzt</li>";
    echo "<li>Antragsnummer und Status werden geloggt</li>";
    echo "</ol>";

    echo "<h3>Welche Felder werden kopiert?</h3>";
    echo "<ul>";
    echo "<li><code>fertig</code> → fertig (Flag)</li>";
    echo "<li><code>text</code> → besch_text</li>";
    echo "<li><code>titel</code> → besch_titel</li>";
    echo "<li><code>beschluss</code> → besch_beschluss</li>";
    echo "<li><code>fintext</code> → besch_fintext</li>";
    echo "<li><code>pers</code> → besch_pers</li>";
    echo "<li><code>sach</code> → besch_sach</li>";
    echo "<li><code>begr</code> → besch_begr</li>";
    echo "<li><code>ressort</code> → besch_ressort</li>";
    echo "<li><code>int_ext</code> → besch_int_ext</li>";
    echo "<li><code>dafuer</code> → besch_dafuer</li>";
    echo "<li><code>dagegen</code> → besch_dagegen</li>";
    echo "<li><code>enthaltungen</code> → besch_enthaltungen</li>";
    echo "<li><code>anmerkungen</code> → besch_anmerkungen</li>";
    echo "</ul>";

    echo "<button onclick=\"location.href='?action=merge'\">▶ Merge durchführen</button>";
    echo "</div>";

} elseif ($action === 'merge') {
    echo "<div class='section'>";
    echo "<h2>Merge wird durchgeführt...</h2>";
    echo "<div class='log'>";

    $merged = 0;
    $skipped = 0;
    $errors = 0;

    // Alle Beschlüsse holen
    $stmt = $pdo->query("SELECT * FROM beschluesse ORDER BY antrnr");
    $beschluesse = $stmt->fetchAll();

    echo "<div class='log-item'><strong>Gefunden: " . count($beschluesse) . " Beschlüsse</strong></div>";

    foreach ($beschluesse as $b) {
        $antrnr = $b['antrnr'];

        // Prüfen ob Antrag existiert
        $check = $pdo->prepare("SELECT antrnr FROM antraege WHERE antrnr = ?");
        $check->execute([$antrnr]);

        if (!$check->fetch()) {
            echo "<div class='log-item'><span class='warning'>⚠ $antrnr: Antrag nicht gefunden - überspringe</span></div>";
            $skipped++;
            continue;
        }

        try {
            // Update durchführen
            $update = $pdo->prepare("
                UPDATE antraege SET
                    fertig = ?,
                    besch_text = ?,
                    besch_titel = ?,
                    besch_beschluss = ?,
                    besch_fintext = ?,
                    besch_pers = ?,
                    besch_sach = ?,
                    besch_begr = ?,
                    besch_ressort = ?,
                    besch_int_ext = ?,
                    besch_dafuer = ?,
                    besch_dagegen = ?,
                    besch_enthaltungen = ?,
                    besch_anmerkungen = ?
                WHERE antrnr = ?
            ");

            $update->execute([
                $b['fertig'] ?? '1',
                $b['text'],
                $b['titel'],
                $b['beschluss'],
                $b['fintext'],
                $b['pers'],
                $b['sach'],
                $b['begr'],
                $b['ressort'],
                $b['int_ext'],
                $b['dafuer'],
                $b['dagegen'],
                $b['enthaltungen'],
                $b['anmerkungen'],
                $antrnr
            ]);

            echo "<div class='log-item'><span class='ok'>✓</span> $antrnr: Erfolgreich gemergt</div>";
            $merged++;

        } catch (Exception $e) {
            echo "<div class='log-item'><span class='error'>✗ $antrnr: Fehler - " . htmlspecialchars($e->getMessage()) . "</span></div>";
            $errors++;
        }
    }

    echo "</div>";

    echo "<div class='stats' style='margin-top: 20px;'>";
    echo "<strong>Ergebnis:</strong><br>";
    echo "• <span class='ok'>Erfolgreich: $merged</span><br>";
    if ($skipped > 0) {
        echo "• <span class='warning'>Übersprungen: $skipped</span><br>";
    }
    if ($errors > 0) {
        echo "• <span class='error'>Fehler: $errors</span><br>";
    }
    echo "</div>";

    echo "<button onclick=\"location.href='?'\">← Zurück zur Übersicht</button>";
    echo "<button onclick=\"location.href='../../beschlussbuch.php'\">▶ Beschlussbuch anzeigen</button>";
    echo "</div>";
}

echo "
</body>
</html>";
