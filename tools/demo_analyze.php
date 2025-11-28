<?php
/**
 * demo_analyze.php - Analysiert die demo_data.json Datei
 *
 * Zeigt genau, welche Daten in der JSON-Datei enthalten sind
 */

require_once __DIR__ . '/../config.php';

$demo_file = __DIR__ . '/demo_data.json';

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo-Daten Analyse</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .highlight {
            background-color: #fff3cd;
            font-weight: bold;
        }
        .good {
            background-color: #d4edda;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 300px;
        }
    </style>
</head>
<body>
    <h1>🔍 Demo-Daten Analyse</h1>

    <?php
    if (!file_exists($demo_file)) {
        echo '<div class="error">';
        echo '<h3>❌ Datei nicht gefunden</h3>';
        echo '<p>Die Datei <code>' . htmlspecialchars($demo_file) . '</code> existiert nicht.</p>';
        echo '</div>';
        exit;
    }

    $file_size = filesize($demo_file);
    $file_modified = filemtime($demo_file);

    echo '<div class="card">';
    echo '<h2>📄 Datei-Informationen</h2>';
    echo '<p><strong>Pfad:</strong> <code>' . htmlspecialchars($demo_file) . '</code></p>';
    echo '<p><strong>Größe:</strong> ' . number_format($file_size / 1024, 2) . ' KB (' . number_format($file_size) . ' Bytes)</p>';
    echo '<p><strong>Geändert:</strong> ' . date('d.m.Y H:i:s', $file_modified) . '</p>';
    echo '</div>';

    // JSON laden und analysieren
    try {
        $json_content = file_get_contents($demo_file);

        if ($json_content === false) {
            throw new Exception('Fehler beim Lesen der Datei');
        }

        $demo_data = json_decode($json_content, true);

        if ($demo_data === null) {
            $json_error = json_last_error_msg();
            throw new Exception('JSON Parse Error: ' . $json_error);
        }

        echo '<div class="card">';
        echo '<h2>📊 JSON-Struktur</h2>';
        echo '<p><strong>Export-Datum:</strong> ' . htmlspecialchars($demo_data['export_date'] ?? 'Nicht gesetzt') . '</p>';
        echo '<p><strong>Version:</strong> ' . htmlspecialchars($demo_data['export_version'] ?? 'Nicht gesetzt') . '</p>';
        echo '</div>';

        // Tabellen-Analyse
        $total_records = 0;
        $empty_tables = [];
        $filled_tables = [];

        echo '<div class="card">';
        echo '<h2>📋 Tabellen-Inhalt (Detailliert)</h2>';
        echo '<table>';
        echo '<tr><th>Tabelle</th><th>Anzahl Datensätze</th><th>Status</th><th>Beispiel-Daten</th></tr>';

        foreach ($demo_data['tables'] as $table => $rows) {
            $count = count($rows);
            $total_records += $count;

            $row_class = '';
            $status = '';
            if ($count === 0) {
                $empty_tables[] = $table;
                $row_class = 'highlight';
                $status = '❌ LEER';
            } else {
                $filled_tables[] = $table;
                $row_class = 'good';
                $status = '✅ OK';
            }

            // Beispieldaten anzeigen (erste Zeile)
            $example = '';
            if ($count > 0) {
                $first_row = $rows[0];
                $example_fields = array_slice($first_row, 0, 3); // Erste 3 Felder
                $example = '<small>' . implode(', ', array_map(function($k, $v) {
                    return htmlspecialchars($k . ': ' . (is_array($v) ? 'Array' : substr($v, 0, 30)));
                }, array_keys($example_fields), $example_fields)) . '</small>';
            }

            echo '<tr class="' . $row_class . '">';
            echo '<td><strong>' . htmlspecialchars($table) . '</strong></td>';
            echo '<td>' . $count . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $example . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '<p style="margin-top: 15px;"><strong>Gesamt-Datensätze:</strong> ' . $total_records . '</p>';
        echo '</div>';

        // Warnung bei leeren wichtigen Tabellen
        $critical_tables = ['svmeetings', 'svagenda_items', 'svmeeting_participants'];
        $missing_critical = array_intersect($critical_tables, $empty_tables);

        if (!empty($missing_critical)) {
            echo '<div class="error">';
            echo '<h3>❌ PROBLEM: Wichtige Tabellen sind leer!</h3>';
            echo '<p>Die folgenden wichtigen Tabellen enthalten keine Daten:</p>';
            echo '<ul>';
            foreach ($missing_critical as $table) {
                echo '<li><strong>' . htmlspecialchars($table) . '</strong></li>';
            }
            echo '</ul>';
            echo '<p><strong>Ursache:</strong> Diese JSON-Datei wurde von einem System ohne Meeting-Daten exportiert!</p>';
            echo '<p><strong>Lösung:</strong> Exportieren Sie die Daten vom RICHTIGEN Quellsystem (localhost mit echten Meetings).</p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h3>✅ JSON-Datei sieht gut aus!</h3>';
            echo '<p>Alle wichtigen Tabellen enthalten Daten.</p>';
            echo '</div>';
        }

        // Zusammenfassung
        echo '<div class="info">';
        echo '<h3>📈 Zusammenfassung</h3>';
        echo '<p><strong>Tabellen mit Daten:</strong> ' . count($filled_tables) . ' (' . implode(', ', array_map('htmlspecialchars', $filled_tables)) . ')</p>';
        echo '<p><strong>Leere Tabellen:</strong> ' . count($empty_tables) . ' (' . implode(', ', array_map('htmlspecialchars', $empty_tables)) . ')</p>';
        echo '</div>';

        // Erste paar Zeilen der JSON anzeigen
        echo '<div class="card">';
        echo '<h3>🔍 JSON-Inhalt (Vorschau)</h3>';
        echo '<pre>' . htmlspecialchars(substr($json_content, 0, 2000)) . '...</pre>';
        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<h3>❌ Fehler beim Analysieren</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>

    <div class="card">
        <h3>🔗 Weiter zu...</h3>
        <p>
            <a href="demo_export.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;">📤 Export</a>
            <a href="demo_import.php" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">📥 Import</a>
        </p>
    </div>

</body>
</html>
