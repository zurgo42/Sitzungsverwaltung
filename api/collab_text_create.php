<?php
/**
 * API: Erstellt neuen kollaborativen Text
 * POST: meeting_id, title, initial_content (optional)
 */

// Fehleranzeige komplett deaktivieren (Fehler nur ins Log)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Output buffering für saubere JSON-Ausgabe
ob_start();

session_start();
require_once('../config.php');
require_once('../functions.php');
require_once('../functions_collab_text.php');

header('Content-Type: application/json');

if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    ob_end_flush();
    exit;
}

$raw_input = file_get_contents('php://input');
error_log("collab_text_create.php - Raw input: " . $raw_input);

$data = json_decode($raw_input, true);

// Debug-Logging
error_log("collab_text_create.php - Decoded data: " . json_encode($data));

// Prüfen ob JSON-Dekodierung erfolgreich war
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    error_log("collab_text_create.php - JSON decode error: " . json_last_error_msg());
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    ob_end_flush();
    exit;
}

// meeting_id kann NULL sein (Allgemein-Modus) oder eine Zahl (Meeting-Modus)
$meeting_id = isset($data['meeting_id']) && $data['meeting_id'] !== null ? (int)$data['meeting_id'] : null;
$title = isset($data['title']) ? trim($data['title']) : '';
$initial_content = isset($data['initial_content']) ? trim($data['initial_content']) : '';

error_log("collab_text_create.php - meeting_id: " . var_export($meeting_id, true) . ", title: '$title', initial_content length: " . strlen($initial_content));

if (empty($title)) {
    http_response_code(400);
    error_log("collab_text_create.php - ERROR: Title is empty!");
    echo json_encode(['error' => 'Missing required fields (title)', 'debug' => 'Title is empty or missing']);
    ob_end_flush();
    exit;
}

// Zugriffsprüfung
if ($meeting_id !== null) {
    // MEETING-MODUS: Prüfen ob User Teilnehmer der Sitzung ist
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as is_participant
        FROM svmeeting_participants
        WHERE meeting_id = ? AND member_id = ?
    ");
    $stmt->execute([$meeting_id, $_SESSION['member_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['is_participant'] == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Not a participant of this meeting']);
        ob_end_flush();
        exit;
    }
} else {
    // ALLGEMEIN-MODUS: Nur Vorstand, GF, Assistenz
    $stmt = $pdo->prepare("SELECT role FROM svmembers WHERE member_id = ?");
    $stmt->execute([$_SESSION['member_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !in_array($user['role'], ['vorstand', 'gf', 'assistenz'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied - Nur Vorstand, GF und Assistenz dürfen allgemeine Texte erstellen']);
        ob_end_flush();
        exit;
    }
}

$text_id = createCollabText($pdo, $meeting_id, $_SESSION['member_id'], $title, $initial_content);

error_log("collab_text_create.php - createCollabText returned: " . var_export($text_id, true));

if ($text_id) {
    echo json_encode([
        'success' => true,
        'text_id' => $text_id,
        'message' => 'Text created successfully'
    ]);
} else {
    http_response_code(500);
    error_log("collab_text_create.php - ERROR: createCollabText failed! Check error log above for PDO error.");
    echo json_encode(['error' => 'Failed to create text - check server logs']);
}

// Output buffer sauber beenden
ob_end_flush();
