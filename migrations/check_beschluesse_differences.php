<?php
/**
 * check_beschluesse_differences.php
 *
 * Prüft ob Daten in beschluesse von den zugehörigen antraege abweichen
 */

// Error Reporting aktivieren
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
    <title>Beschlüsse vs. Anträge - Unterschiede</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            font-family: sans-serif;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-family: sans-serif;
            font-size: 14px;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .summary {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .summary h2 {
            margin-top: 0;
            color: #0066cc;
            font-family: sans-serif;
        }
        .stat {
            padding: 10px;
            margin: 5px 0;
            background: #f9f9f9;
            border-left: 4px solid #0066cc;
        }
        .difference {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #ff6b00;
        }
        .antrnr {
            font-weight: bold;
            color: #0066cc;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .field-diff {
            margin: 10px 0;
            padding: 10px;
            background: #fff9e6;
            border-radius: 4px;
        }
        .field-name {
            font-weight: bold;
            color: #856404;
            margin-bottom: 5px;
        }
        .value-block {
            margin: 5px 0 5px 20px;
            padding: 8px;
            background: white;
            border-radius: 4px;
        }
        .label {
            font-weight: bold;
            color: #666;
            display: inline-block;
            width: 100px;
        }
        .value {
            color: #333;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .no-diff {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 18px;
            font-family: sans-serif;
        }
    </style>
</head>
<body>
<h1>Vergleich: Beschlüsse vs. Anträge</h1>

<div class='info-box'>
    <strong>ℹ️ Hinweis zum Vergleich:</strong>
    <ul>
        <li><strong>fintext:</strong> Nicht verglichen (Betrag und Text in antraege getrennt gespeichert)</li>
        <li><strong>ressort:</strong> Nicht verglichen (wird in beschluesse aus Ressortliste aufgelöst)</li>
        <li><strong>wichtig:</strong> Nicht verglichen (identisch)</li>
    </ul>
    <p><strong>Verglichene Felder:</strong> titel, beschluss, pers, sach, begr, int_ext</p>
</div>
";

// Felder zum Vergleichen
// NICHT verglichen: fintext (Betrag+Text in antraege getrennt), ressort (wird aufgelöst), wichtig (identisch)
$fields_to_compare = [
    'titel' => 'Titel',
    'beschluss' => 'Beschluss',
    'pers' => 'Personelle Auswirkungen',
    'sach' => 'Sachliche Auswirkungen',
    'begr' => 'Begründung',
    'int_ext' => 'Intern/Extern'
];

// Hole alle Beschlüsse und vergleiche mit Anträgen
$sql = "
    SELECT
        b.antrnr,
        b.titel as b_titel,
        b.beschluss as b_beschluss,
        b.fintext as b_fintext,
        b.pers as b_pers,
        b.sach as b_sach,
        b.begr as b_begr,
        b.ressort as b_ressort,
        b.int_ext as b_int_ext,
        b.wichtig as b_wichtig,
        a.titel as a_titel,
        a.beschluss as a_beschluss,
        a.fintext as a_fintext,
        a.pers as a_pers,
        a.sach as a_sach,
        a.begr as a_begr,
        CONCAT_WS(', ', NULLIF(a.ressort1, ''), NULLIF(a.ressort2, '')) as a_ressort,
        a.int_ext as a_int_ext,
        a.wichtig as a_wichtig
    FROM beschluesse b
    LEFT JOIN antraege a ON b.antrnr = a.antrnr
    ORDER BY b.antrnr DESC
";

$stmt = $pdo->query($sql);
$beschluesse = $stmt->fetchAll();

$total_beschluesse = count($beschluesse);
$beschluesse_with_differences = 0;
$field_difference_count = [];
$differences_detail = [];

foreach ($fields_to_compare as $field => $label) {
    $field_difference_count[$field] = 0;
}

// Vergleichen
foreach ($beschluesse as $row) {
    $antrnr = $row['antrnr'];
    $has_difference = false;
    $diffs = [];

    foreach ($fields_to_compare as $field => $label) {
        $b_value = $row["b_$field"];
        $a_value = $row["a_$field"];

        // Normalisieren für Vergleich
        $b_normalized = trim($b_value ?? '');
        $a_normalized = trim($a_value ?? '');

        // Spezialfall: ressort (in beschluesse ein Feld, in antraege zwei)
        // Das ist kein echter Unterschied, nur strukturell

        if ($b_normalized !== $a_normalized) {
            $has_difference = true;
            $field_difference_count[$field]++;
            $diffs[$field] = [
                'label' => $label,
                'beschluss' => $b_value,
                'antrag' => $a_value
            ];
        }
    }

    if ($has_difference) {
        $beschluesse_with_differences++;
        $differences_detail[$antrnr] = $diffs;
    }
}

// Zusammenfassung ausgeben
echo "<div class='summary'>";
echo "<h2>Zusammenfassung</h2>";
echo "<div class='stat'><strong>Gesamt Beschlüsse:</strong> $total_beschluesse</div>";
echo "<div class='stat'><strong>Beschlüsse mit Abweichungen:</strong> $beschluesse_with_differences</div>";
echo "<div class='stat'><strong>Beschlüsse ohne Abweichungen:</strong> " . ($total_beschluesse - $beschluesse_with_differences) . "</div>";

if ($beschluesse_with_differences > 0) {
    echo "<h3 style='margin-top: 20px;'>Abweichungen nach Feld:</h3>";
    foreach ($field_difference_count as $field => $count) {
        if ($count > 0) {
            $label = $fields_to_compare[$field];
            echo "<div class='stat'>• <strong>$label:</strong> $count Abweichung(en)</div>";
        }
    }
}
echo "</div>";

// Detaillierte Unterschiede ausgeben
if ($beschluesse_with_differences > 0) {
    echo "<h2>Detaillierte Unterschiede</h2>";

    foreach ($differences_detail as $antrnr => $diffs) {
        echo "<div class='difference'>";
        echo "<div class='antrnr'>$antrnr</div>";

        foreach ($diffs as $field => $diff) {
            echo "<div class='field-diff'>";
            echo "<div class='field-name'>{$diff['label']}</div>";

            echo "<div class='value-block'>";
            echo "<span class='label'>Antrag:</span>";
            echo "<div class='value'>" . htmlspecialchars($diff['antrag'] ?: '(leer)') . "</div>";
            echo "</div>";

            echo "<div class='value-block'>";
            echo "<span class='label'>Beschluss:</span>";
            echo "<div class='value'>" . htmlspecialchars($diff['beschluss'] ?: '(leer)') . "</div>";
            echo "</div>";

            echo "</div>";
        }

        echo "</div>";
    }
} else {
    echo "<div class='no-diff'>";
    echo "✓ Keine Unterschiede gefunden!<br>";
    echo "Alle Beschlüsse stimmen mit den zugehörigen Anträgen überein.";
    echo "</div>";
}

echo "
</body>
</html>";
