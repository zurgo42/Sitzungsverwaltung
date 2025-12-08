<?php
/**
 * Performance-Diagnose für Kollaborative Texte
 * Misst exakte Timings aller kritischen Operationen
 */

// Zeitmessung starten
$start_total = microtime(true);

// Session
$start_session = microtime(true);
session_start();
$time_session = (microtime(true) - $start_session) * 1000;

// Auth check
if (!isset($_SESSION['member_id'])) {
    die("Nicht eingeloggt");
}
$member_id = $_SESSION['member_id'];
session_write_close();

// Config laden
$start_config = microtime(true);
require_once('../config.php');
$time_config = (microtime(true) - $start_config) * 1000;

// DB Connection
$start_db = microtime(true);
require_once('db_connection.php');
$time_db = (microtime(true) - $start_db) * 1000;

// Functions laden
$start_functions = microtime(true);
require_once('../functions_collab_text.php');
$time_functions = (microtime(true) - $start_functions) * 1000;

// Test-Text-ID holen
$stmt = $pdo->query("SELECT text_id FROM svcollab_texts LIMIT 1");
$text = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$text) {
    die("<h1>Kein Text vorhanden</h1><p>Bitte erst einen kollaborativen Text erstellen.</p>");
}

$text_id = $text['text_id'];

echo "<h1>Performance-Diagnose: Kollaborative Texte</h1>";
echo "<p><strong>Test-Text-ID:</strong> $text_id</p>";
echo "<p><strong>User-ID:</strong> $member_id</p>";
echo "<hr>";

// ==========================================
// TEST 1: Initialisierungs-Overhead
// ==========================================
echo "<h2>1. Initialisierungs-Overhead</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Operation</th><th>Zeit (ms)</th><th>Status</th></tr>";

$status_session = $time_session < 100 ? "✅ OK" : "⚠️ LANGSAM";
$status_config = $time_config < 10 ? "✅ OK" : "⚠️ LANGSAM";
$status_db = $time_db < 10 ? "✅ OK" : "⚠️ LANGSAM";
$status_functions = $time_functions < 50 ? "✅ OK" : "⚠️ LANGSAM";

echo "<tr><td>session_start()</td><td>" . round($time_session, 2) . " ms</td><td>$status_session</td></tr>";
echo "<tr><td>require config.php</td><td>" . round($time_config, 2) . " ms</td><td>$status_config</td></tr>";
echo "<tr><td>DB Connection</td><td>" . round($time_db, 2) . " ms</td><td>$status_db</td></tr>";
echo "<tr><td>require functions_collab_text.php</td><td>" . round($time_functions, 2) . " ms</td><td>$status_functions</td></tr>";
echo "</table>";

// ==========================================
// TEST 2: DB-Queries (Heartbeat)
// ==========================================
echo "<h2>2. Heartbeat-Query</h2>";
$start = microtime(true);
$success = updateParticipantHeartbeat($pdo, $text_id, $member_id);
$time_heartbeat = (microtime(true) - $start) * 1000;
$status = $time_heartbeat < 50 ? "✅ OK" : "⚠️ LANGSAM";
echo "<p>Zeit: <strong>" . round($time_heartbeat, 2) . " ms</strong> $status</p>";

// ==========================================
// TEST 3: Online-User-Query
// ==========================================
echo "<h2>3. Online-User abfragen</h2>";
$start = microtime(true);
$online_users = getOnlineParticipants($pdo, $text_id);
$time_online = (microtime(true) - $start) * 1000;
$status = $time_online < 50 ? "✅ OK" : "⚠️ LANGSAM";
echo "<p>Zeit: <strong>" . round($time_online, 2) . " ms</strong> $status</p>";
echo "<p>Gefundene Online-User: " . count($online_users) . "</p>";

// ==========================================
// TEST 4: Text mit Absätzen laden
// ==========================================
echo "<h2>4. Text mit allen Absätzen laden (getCollabText)</h2>";
$start = microtime(true);
$text_data = getCollabText($pdo, $text_id);
$time_load = (microtime(true) - $start) * 1000;
$status = $time_load < 100 ? "✅ OK" : "⚠️ LANGSAM";
echo "<p>Zeit: <strong>" . round($time_load, 2) . " ms</strong> $status</p>";
echo "<p>Absätze geladen: " . count($text_data['paragraphs']) . "</p>";

// ==========================================
// TEST 5: Lock-Operationen
// ==========================================
echo "<h2>5. Lock-Operationen</h2>";

// Lock erwerben
if (!empty($text_data['paragraphs'])) {
    $test_paragraph_id = $text_data['paragraphs'][0]['paragraph_id'];

    $start = microtime(true);
    $lock_success = lockParagraph($pdo, $test_paragraph_id, $member_id);
    $time_lock = (microtime(true) - $start) * 1000;
    $status_lock = $time_lock < 200 ? "✅ OK" : "⚠️ LANGSAM";

    echo "<p><strong>Lock erwerben:</strong> " . round($time_lock, 2) . " ms $status_lock</p>";

    // Lock freigeben
    if ($lock_success) {
        $start = microtime(true);
        unlockParagraph($pdo, $test_paragraph_id, $member_id);
        $time_unlock = (microtime(true) - $start) * 1000;
        $status_unlock = $time_unlock < 50 ? "✅ OK" : "⚠️ LANGSAM";

        echo "<p><strong>Lock freigeben:</strong> " . round($time_unlock, 2) . " ms $status_unlock</p>";
    }
} else {
    echo "<p>❌ Keine Absätze vorhanden für Lock-Test</p>";
}

// ==========================================
// TEST 6: Speichern-Simulation
// ==========================================
echo "<h2>6. Speichern-Simulation</h2>";

if (!empty($text_data['paragraphs'])) {
    $test_paragraph_id = $text_data['paragraphs'][0]['paragraph_id'];

    // Lock erwerben
    lockParagraph($pdo, $test_paragraph_id, $member_id);

    // Speichern
    $start = microtime(true);
    $save_success = saveParagraph($pdo, $test_paragraph_id, $member_id, "Test-Content vom Diagnose-Script");
    $time_save = (microtime(true) - $start) * 1000;
    $status_save = $time_save < 300 ? "✅ OK" : "⚠️ LANGSAM";

    echo "<p><strong>Speichern (inkl. Lock-Freigabe):</strong> " . round($time_save, 2) . " ms $status_save</p>";

    if (!$save_success) {
        echo "<p>❌ Speichern fehlgeschlagen</p>";
    }
}

// ==========================================
// TEST 7: DB-Indizes prüfen
// ==========================================
echo "<h2>7. Datenbank-Indizes</h2>";

$tables_to_check = [
    'svcollab_text_participants' => ['text_id', 'member_id', 'last_seen'],
    'svcollab_text_paragraphs' => ['text_id', 'paragraph_id', 'paragraph_order'],
    'svcollab_text_locks' => ['paragraph_id', 'member_id', 'last_activity'],
    'svcollab_texts' => ['text_id', 'meeting_id']
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Tabelle</th><th>Feld</th><th>Index vorhanden?</th></tr>";

foreach ($tables_to_check as $table => $fields) {
    $stmt = $pdo->query("SHOW INDEX FROM $table");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $indexed_columns = [];
    foreach ($indexes as $idx) {
        $indexed_columns[] = $idx['Column_name'];
    }

    foreach ($fields as $field) {
        $has_index = in_array($field, $indexed_columns);
        $status = $has_index ? "✅ Ja" : "❌ Fehlt";
        echo "<tr><td>$table</td><td>$field</td><td>$status</td></tr>";
    }
}

echo "</table>";

// ==========================================
// TEST 8: Gesamt-API-Call Simulation
// ==========================================
echo "<h2>8. Kompletter API-Call (get_updates) Simulation</h2>";

$start = microtime(true);

// Alle Operationen die get_updates macht
$stmt = $pdo->prepare("SELECT server_time FROM (SELECT NOW() as server_time) t");
$stmt->execute();
$server_time = $stmt->fetch(PDO::FETCH_ASSOC)['server_time'];

$online = getOnlineParticipants($pdo, $text_id);

$stmt = $pdo->prepare("
    SELECT p.*,
           m.first_name as editor_first_name,
           m.last_name as editor_last_name,
           l.member_id as locked_by_member_id,
           lm.first_name as locked_by_first_name,
           lm.last_name as locked_by_last_name
    FROM svcollab_text_paragraphs p
    LEFT JOIN svmembers m ON p.last_edited_by = m.member_id
    LEFT JOIN svcollab_text_locks l ON p.paragraph_id = l.paragraph_id
    LEFT JOIN svmembers lm ON l.member_id = lm.member_id
    WHERE p.text_id = ?
    ORDER BY p.paragraph_order ASC
");
$stmt->execute([$text_id]);
$paragraphs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$time_api_call = (microtime(true) - $start) * 1000;
$status_api = $time_api_call < 100 ? "✅ OK" : "⚠️ LANGSAM";

echo "<p>Zeit: <strong>" . round($time_api_call, 2) . " ms</strong> $status_api</p>";
echo "<p><em>Ziel: &lt;100ms (wird 40x pro Minute aufgerufen!)</em></p>";

// ==========================================
// ZUSAMMENFASSUNG
// ==========================================
$time_total = (microtime(true) - $start_total) * 1000;

echo "<hr>";
echo "<h2>Zusammenfassung</h2>";
echo "<p><strong>Gesamtdauer aller Tests:</strong> " . round($time_total, 2) . " ms</p>";

echo "<h3>Grenzwerte (Soll-Werte)</h3>";
echo "<ul>";
echo "<li>Polling (get_updates): &lt;100ms (aktuell: " . round($time_api_call, 2) . " ms)</li>";
echo "<li>Heartbeat: &lt;50ms (aktuell: " . round($time_heartbeat, 2) . " ms)</li>";
echo "<li>Lock erwerben: &lt;200ms</li>";
echo "<li>Speichern: &lt;300ms</li>";
echo "</ul>";

// Kritische Probleme identifizieren
$problems = [];
if ($time_session > 100) {
    $problems[] = "⚠️ <strong>Session-Start ist sehr langsam (" . round($time_session, 2) . " ms)</strong> - Möglicherweise Session-File I/O Problem";
}
if ($time_functions > 50) {
    $problems[] = "⚠️ <strong>functions_collab_text.php lädt langsam (" . round($time_functions, 2) . " ms)</strong> - OPcache aktivieren?";
}
if ($time_api_call > 100) {
    $problems[] = "⚠️ <strong>API-Call zu langsam (" . round($time_api_call, 2) . " ms)</strong> - DB-Query Optimierung nötig";
}
if ($time_heartbeat > 50) {
    $problems[] = "⚠️ <strong>Heartbeat zu langsam (" . round($time_heartbeat, 2) . " ms)</strong>";
}

if (!empty($problems)) {
    echo "<h3 style='color: red;'>🔴 Erkannte Probleme:</h3>";
    echo "<ul>";
    foreach ($problems as $problem) {
        echo "<li>$problem</li>";
    }
    echo "</ul>";
} else {
    echo "<h3 style='color: green;'>✅ Alle Performance-Werte im grünen Bereich!</h3>";
}

echo "<hr>";
echo "<p><small>Diagnose durchgeführt am " . date('Y-m-d H:i:s') . "</small></p>";
?>
