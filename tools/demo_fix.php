<?php
/**
 * demo_fix.php - Repariert Foreign Key Probleme in demo_data.json
 */

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Demo-Daten Reparatur</title>
    <style>
        body { font-family: Arial; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .ok { color: green; }
        .error { color: red; }
        .info { background: #d1ecf1; padding: 10px; border-left: 4px solid #0c5460; margin: 10px 0; }
        .success { background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Demo-Daten Reparatur</h1>

<?php
$demo_file = __DIR__ . '/demo_data.json';
$backup_file = __DIR__ . '/demo_data.json.backup';

if (!file_exists($demo_file)) {
    echo '<div class="error">❌ demo_data.json nicht gefunden!</div>';
    exit;
}

// Backup erstellen
copy($demo_file, $backup_file);
echo "<div class='info'>✓ Backup erstellt: demo_data.json.backup</div>";

$json = file_get_contents($demo_file);
$demo_data = json_decode($json, true);

if (!$demo_data) {
    echo '<div class="error">❌ Fehler beim Parsen der JSON-Datei</div>';
    exit;
}

echo '<div class="card"><h2>Schritt 1: Sammle gültige IDs</h2>';

$total_removed = 0;

// 1. Sammle alle gültigen IDs
$valid_ids = [];

// Meeting IDs
if (isset($demo_data['tables']['svmeetings'])) {
    $valid_ids['meeting_id'] = [];
    foreach ($demo_data['tables']['svmeetings'] as $meeting) {
        if (isset($meeting['meeting_id'])) {
            $valid_ids['meeting_id'][] = $meeting['meeting_id'];
        }
    }
    echo "<p class='ok'>✓ " . count($valid_ids['meeting_id']) . " gültige meeting_ids: " . implode(', ', $valid_ids['meeting_id']) . "</p>";
}

// Member IDs
if (isset($demo_data['tables']['svmembers'])) {
    $valid_ids['member_id'] = [];
    foreach ($demo_data['tables']['svmembers'] as $member) {
        if (isset($member['member_id'])) {
            $valid_ids['member_id'][] = $member['member_id'];
        }
    }
    echo "<p class='ok'>✓ " . count($valid_ids['member_id']) . " gültige member_ids</p>";
}

// Agenda Item IDs
if (isset($demo_data['tables']['svagenda_items'])) {
    $valid_ids['item_id'] = [];
    foreach ($demo_data['tables']['svagenda_items'] as $item) {
        if (isset($item['item_id'])) {
            $valid_ids['item_id'][] = $item['item_id'];
        }
    }
    echo "<p class='ok'>✓ " . count($valid_ids['item_id']) . " gültige item_ids</p>";
}

echo '</div><div class="card"><h2>Schritt 2: Repariere defekte Datensätze</h2>';

// 2. Entferne svagenda_items mit meeting_id=NULL
if (isset($demo_data['tables']['svagenda_items'])) {
    $original_count = count($demo_data['tables']['svagenda_items']);
    $filtered = [];
    $removed_items = [];

    foreach ($demo_data['tables']['svagenda_items'] as $item) {
        if ($item['meeting_id'] === null) {
            $removed_items[] = $item['item_id'] ?? 'N/A';
            $total_removed++;
        } else {
            $filtered[] = $item;
        }
    }

    $demo_data['tables']['svagenda_items'] = $filtered;
    $new_count = count($filtered);

    if (!empty($removed_items)) {
        echo "<p class='error'>🔧 svagenda_items: " . ($original_count - $new_count) . " Items mit meeting_id=NULL entfernt (IDs: " . implode(', ', $removed_items) . ")</p>";
    } else {
        echo "<p class='ok'>✓ svagenda_items: Keine Probleme</p>";
    }
}

// 3. Entferne svprotocols mit ungültiger meeting_id
if (isset($demo_data['tables']['svprotocols'])) {
    $original_count = count($demo_data['tables']['svprotocols']);
    $filtered = [];
    $removed_protocols = [];

    foreach ($demo_data['tables']['svprotocols'] as $protocol) {
        $meeting_id = $protocol['meeting_id'];

        // NULL ist OK
        if ($meeting_id === null) {
            $filtered[] = $protocol;
            continue;
        }

        // Prüfe ob meeting_id existiert
        if (!in_array($meeting_id, $valid_ids['meeting_id'])) {
            $removed_protocols[] = "protocol_id=" . ($protocol['protocol_id'] ?? 'N/A') . " (meeting_id=$meeting_id existiert nicht)";
            $total_removed++;
        } else {
            $filtered[] = $protocol;
        }
    }

    $demo_data['tables']['svprotocols'] = $filtered;
    $new_count = count($filtered);

    if (!empty($removed_protocols)) {
        echo "<p class='error'>🔧 svprotocols: " . ($original_count - $new_count) . " Protokolle mit ungültiger meeting_id entfernt:</p><ul>";
        foreach ($removed_protocols as $info) {
            echo "<li>" . htmlspecialchars($info) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='ok'>✓ svprotocols: Keine Probleme</p>";
    }
}

// 4. Weitere Foreign Key Checks
$fk_checks = [
    'svmeeting_participants' => [
        'meeting_id' => 'meeting_id',
        'member_id' => 'member_id'
    ],
    'svagenda_comments' => [
        'item_id' => 'item_id',
        'member_id' => 'member_id'
    ],
    'svtodos' => [
        'assigned_to_member_id' => 'member_id'
    ],
];

foreach ($fk_checks as $table => $foreign_keys) {
    if (!isset($demo_data['tables'][$table])) continue;

    $original_count = count($demo_data['tables'][$table]);
    $filtered = [];
    $removed_count = 0;

    foreach ($demo_data['tables'][$table] as $row) {
        $is_valid = true;

        foreach ($foreign_keys as $fk_field => $id_type) {
            $fk_value = $row[$fk_field] ?? null;

            // NULL ist meistens OK
            if ($fk_value === null) {
                continue;
            }

            // Prüfe ob FK existiert
            if (isset($valid_ids[$id_type]) && !in_array($fk_value, $valid_ids[$id_type])) {
                $is_valid = false;
                $removed_count++;
                $total_removed++;
                break;
            }
        }

        if ($is_valid) {
            $filtered[] = $row;
        }
    }

    if ($removed_count > 0) {
        $demo_data['tables'][$table] = $filtered;
        echo "<p class='error'>🔧 $table: $removed_count Datensätze mit ungültigen Foreign Keys entfernt</p>";
    } else {
        echo "<p class='ok'>✓ $table: Keine Probleme</p>";
    }
}

echo '</div>';

// 5. Speichern
if ($total_removed > 0) {
    $new_json = json_encode($demo_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($demo_file, $new_json);
    echo '<div class="success">';
    echo "<h2>✅ Reparatur abgeschlossen!</h2>";
    echo "<p><strong>$total_removed</strong> defekte Datensätze wurden entfernt.</p>";
    echo "<p>📦 Backup: <code>demo_data.json.backup</code></p>";
    echo "<p><a href='demo_import.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>➡️ Jetzt Import durchführen</a></p>";
    echo '</div>';
} else {
    echo '<div class="success">';
    echo "<h2>✅ Keine Probleme gefunden!</h2>";
    echo "<p>Die demo_data.json ist bereits korrekt - keine Reparatur nötig.</p>";
    echo '</div>';
    unlink($backup_file);
}
?>

</body>
</html>
