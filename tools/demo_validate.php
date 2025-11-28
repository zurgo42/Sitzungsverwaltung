<?php
/**
 * demo_validate.php - Validiert die demo_data.json auf Foreign Key Probleme
 */

require_once __DIR__ . '/../config.php';

$demo_file = __DIR__ . '/demo_data.json';

if (!file_exists($demo_file)) {
    die("demo_data.json nicht gefunden!");
}

$json = file_get_contents($demo_file);
$demo_data = json_decode($json, true);

if (!$demo_data) {
    die("Fehler beim Parsen der JSON-Datei");
}

echo "<h1>🔍 Validierung der demo_data.json</h1>";
echo "<style>body { font-family: Arial; max-width: 1200px; margin: 20px auto; } table { border-collapse: collapse; width: 100%; margin: 20px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f0f0f0; } .error { color: red; } .ok { color: green; }</style>";

// 1. Prüfe svagenda_items auf NULL meeting_id
echo "<h2>Problem 1: svagenda_items mit meeting_id=NULL</h2>";
if (isset($demo_data['tables']['svagenda_items'])) {
    $null_items = [];
    foreach ($demo_data['tables']['svagenda_items'] as $index => $item) {
        if ($item['meeting_id'] === null) {
            $null_items[] = [
                'index' => $index,
                'item_id' => $item['item_id'] ?? 'N/A',
                'title' => $item['title'] ?? 'N/A',
                'top_number' => $item['top_number'] ?? 'N/A'
            ];
        }
    }

    if (empty($null_items)) {
        echo "<p class='ok'>✓ Keine Probleme gefunden</p>";
    } else {
        echo "<p class='error'>✗ " . count($null_items) . " Items mit meeting_id=NULL gefunden:</p>";
        echo "<table>";
        echo "<tr><th>Index</th><th>item_id</th><th>Titel</th><th>TOP</th></tr>";
        foreach ($null_items as $item) {
            echo "<tr>";
            echo "<td>" . $item['index'] . "</td>";
            echo "<td>" . $item['item_id'] . "</td>";
            echo "<td>" . htmlspecialchars($item['title']) . "</td>";
            echo "<td>" . $item['top_number'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// 2. Prüfe svprotocols auf ungültige meeting_id
echo "<h2>Problem 2: svprotocols mit ungültiger meeting_id</h2>";
if (isset($demo_data['tables']['svprotocols']) && isset($demo_data['tables']['svmeetings'])) {
    // Sammle alle gültigen meeting_ids
    $valid_meeting_ids = [];
    foreach ($demo_data['tables']['svmeetings'] as $meeting) {
        $valid_meeting_ids[] = $meeting['meeting_id'];
    }

    echo "<p>Gültige meeting_ids: " . implode(', ', $valid_meeting_ids) . "</p>";

    $invalid_protocols = [];
    foreach ($demo_data['tables']['svprotocols'] as $index => $protocol) {
        $meeting_id = $protocol['meeting_id'];
        if ($meeting_id !== null && !in_array($meeting_id, $valid_meeting_ids)) {
            $invalid_protocols[] = [
                'index' => $index,
                'protocol_id' => $protocol['protocol_id'] ?? 'N/A',
                'meeting_id' => $meeting_id,
                'protocol_type' => $protocol['protocol_type'] ?? 'N/A'
            ];
        }
    }

    if (empty($invalid_protocols)) {
        echo "<p class='ok'>✓ Keine Probleme gefunden</p>";
    } else {
        echo "<p class='error'>✗ " . count($invalid_protocols) . " Protokolle mit ungültiger meeting_id gefunden:</p>";
        echo "<table>";
        echo "<tr><th>Index</th><th>protocol_id</th><th>meeting_id</th><th>Typ</th></tr>";
        foreach ($invalid_protocols as $protocol) {
            echo "<tr>";
            echo "<td>" . $protocol['index'] . "</td>";
            echo "<td>" . $protocol['protocol_id'] . "</td>";
            echo "<td class='error'>" . $protocol['meeting_id'] . " (existiert nicht!)</td>";
            echo "<td>" . $protocol['protocol_type'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// 3. Allgemeine Foreign Key Validierung
echo "<h2>Vollständige Foreign Key Validierung</h2>";

$fk_checks = [
    'svmeeting_participants' => ['meeting_id' => 'svmeetings', 'member_id' => 'svmembers'],
    'svagenda_items' => ['meeting_id' => 'svmeetings', 'created_by_member_id' => 'svmembers'],
    'svagenda_comments' => ['item_id' => 'svagenda_items', 'member_id' => 'svmembers'],
    'svprotocols' => ['meeting_id' => 'svmeetings'],
    'svtodos' => ['meeting_id' => 'svmeetings', 'assigned_to_member_id' => 'svmembers'],
    'svpoll_responses' => ['poll_id' => 'svpolls', 'member_id' => 'svmembers'],
];

// Sammle alle IDs
$all_ids = [];
foreach ($demo_data['tables'] as $table => $rows) {
    $id_field = '';
    if ($table === 'svmembers') $id_field = 'member_id';
    elseif ($table === 'svmeetings') $id_field = 'meeting_id';
    elseif ($table === 'svagenda_items') $id_field = 'item_id';
    elseif ($table === 'svpolls') $id_field = 'poll_id';

    if ($id_field) {
        $all_ids[$table] = [];
        foreach ($rows as $row) {
            if (isset($row[$id_field])) {
                $all_ids[$table][] = $row[$id_field];
            }
        }
    }
}

$total_errors = 0;
foreach ($fk_checks as $table => $foreign_keys) {
    if (!isset($demo_data['tables'][$table])) continue;

    echo "<h3>Tabelle: $table</h3>";

    foreach ($foreign_keys as $fk_field => $parent_table) {
        if (!isset($all_ids[$parent_table])) {
            echo "<p>Warnung: $parent_table nicht in JSON vorhanden</p>";
            continue;
        }

        $errors = [];
        foreach ($demo_data['tables'][$table] as $index => $row) {
            $fk_value = $row[$fk_field] ?? null;

            // NULL ist OK, wenn die Spalte DEFAULT NULL hat
            if ($fk_value === null) {
                // Prüfe, ob NULL erlaubt ist
                if ($table === 'svagenda_items' && $fk_field === 'meeting_id') {
                    $errors[] = "Index $index: $fk_field ist NULL (aber NOT NULL in Schema!)";
                }
                continue;
            }

            // Prüfe ob FK existiert
            if (!in_array($fk_value, $all_ids[$parent_table])) {
                $errors[] = "Index $index: $fk_field=$fk_value existiert nicht in $parent_table";
                $total_errors++;
            }
        }

        if (empty($errors)) {
            echo "<p class='ok'>✓ $fk_field → $parent_table: Alle gültig</p>";
        } else {
            echo "<p class='error'>✗ $fk_field → $parent_table: " . count($errors) . " Fehler</p>";
            echo "<ul>";
            foreach (array_slice($errors, 0, 5) as $error) {
                echo "<li class='error'>$error</li>";
            }
            if (count($errors) > 5) {
                echo "<li>... und " . (count($errors) - 5) . " weitere</li>";
            }
            echo "</ul>";
        }
    }
}

echo "<hr>";
echo "<h2>Zusammenfassung</h2>";
if ($total_errors === 0) {
    echo "<p class='ok' style='font-size: 18px;'>✅ Keine Foreign Key Probleme gefunden!</p>";
} else {
    echo "<p class='error' style='font-size: 18px;'>❌ $total_errors Foreign Key Probleme gefunden!</p>";
    echo "<p><strong>Empfehlung:</strong> Exportieren Sie die Daten neu vom Quellserver mit demo_export.php</p>";
}
