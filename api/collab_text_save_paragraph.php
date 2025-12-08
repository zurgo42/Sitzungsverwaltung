<?php
/**
 * API: Speichert einen Absatz und gibt Lock frei
 * POST: paragraph_id, content
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');
require_once('../functions_collab_text.php');

header('Content-Type: application/json');

if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Session-Daten gelesen → Session sofort schließen für parallele Requests
$member_id = $_SESSION['member_id'];
session_write_close();

$data = json_decode(file_get_contents('php://input'), true);
$paragraph_id = isset($data['paragraph_id']) ? (int)$data['paragraph_id'] : 0;
$content = isset($data['content']) ? $data['content'] : '';

if ($paragraph_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid paragraph_id']);
    exit;
}

$success = saveParagraph($pdo, $paragraph_id, $member_id, $content);

if ($success) {
    // Editor-Namen holen für sofortige Anzeige
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM svmembers WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $editor = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Paragraph saved',
        'timestamp' => date('Y-m-d H:i:s'),
        'editor_name' => $editor['first_name'] . ' ' . $editor['last_name']
    ]);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Failed to save paragraph - no lock held']);
}
