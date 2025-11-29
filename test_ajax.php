<?php
/**
 * test_ajax.php - AJAX-Diagnose-Tool
 * Prüft ob die AJAX-Endpoints erreichbar sind
 */

session_start();
require_once 'config.php';

// Simuliere Login falls nicht eingeloggt
if (!isset($_SESSION['member_id'])) {
    $_SESSION['member_id'] = 1;
    $_SESSION['role'] = 'vorstand';
}

echo "<h1>AJAX Diagnose-Tool</h1>";
echo "<p>Session member_id: " . ($_SESSION['member_id'] ?? 'nicht gesetzt') . "</p>";

// Test 1: ajax_get_protocol.php
echo "<h2>Test 1: ajax_get_protocol.php</h2>";
$test_item_id = 1;
echo "<p>Teste mit item_id=$test_item_id</p>";

$url = "ajax_get_protocol.php?item_id=$test_item_id";
$response = file_get_contents($url);
echo "<pre>Response: " . htmlspecialchars($response) . "</pre>";

$json = json_decode($response, true);
if ($json) {
    echo "<p style='color: green;'>✅ JSON erfolgreich dekodiert</p>";
    echo "<pre>" . print_r($json, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ JSON-Dekodierung fehlgeschlagen</p>";
    echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
}

// Test 2: ajax_get_comments.php
echo "<h2>Test 2: ajax_get_comments.php</h2>";
$url2 = "ajax_get_comments.php?item_id=$test_item_id";
$response2 = file_get_contents($url2);
echo "<pre>Response: " . htmlspecialchars($response2) . "</pre>";

$json2 = json_decode($response2, true);
if ($json2) {
    echo "<p style='color: green;'>✅ JSON erfolgreich dekodiert</p>";
    echo "<pre>" . print_r($json2, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ JSON-Dekodierung fehlgeschlagen</p>";
    echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
}

// Test 3: JavaScript fetch() Test
?>
<h2>Test 3: Browser fetch() Test</h2>
<button onclick="testFetch()">AJAX-Requests im Browser testen</button>
<div id="result"></div>

<script>
function testFetch() {
    const resultDiv = document.getElementById('result');
    resultDiv.innerHTML = '<p>Teste...</p>';

    // Test ajax_get_protocol.php
    fetch('ajax_get_protocol.php?item_id=1')
        .then(response => response.json())
        .then(data => {
            resultDiv.innerHTML += '<p style="color: green;">✅ ajax_get_protocol.php erfolgreich</p>';
            resultDiv.innerHTML += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            resultDiv.innerHTML += '<p style="color: red;">❌ ajax_get_protocol.php Fehler: ' + error + '</p>';
        });

    // Test ajax_get_comments.php
    fetch('ajax_get_comments.php?item_id=1')
        .then(response => response.json())
        .then(data => {
            resultDiv.innerHTML += '<p style="color: green;">✅ ajax_get_comments.php erfolgreich</p>';
            resultDiv.innerHTML += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            resultDiv.innerHTML += '<p style="color: red;">❌ ajax_get_comments.php Fehler: ' + error + '</p>';
        });
}
</script>

<h2>Browser-Konsole überprüfen</h2>
<p>Öffnen Sie die Browser-Entwicklertools (F12) und gehen Sie zum Tab "Console" oder "Konsole".</p>
<p>Dort sollten Sie sehen können, ob JavaScript-Fehler auftreten.</p>

<h2>Network-Tab überprüfen</h2>
<p>Im Tab "Network" oder "Netzwerk" der Entwicklertools können Sie sehen, ob die AJAX-Requests gesendet werden und welche Antworten sie erhalten.</p>
