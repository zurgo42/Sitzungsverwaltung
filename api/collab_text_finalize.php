<?php
/**
 * API: Finalisiert einen Text (beendet Bearbeitung)
 * POST: text_id, final_name
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');
require_once('../functions_collab_text.php');

header('Content-Type: application/json');

if (!isset($member_id)) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;

// Session-Daten gelesen → Session sofort schließen für parallele Requests
$member_id = $_SESSION["member_id"];
session_write_close();
}

$data = json_decode(file_get_contents('php://input'), true);
$text_id = isset($data['text_id']) ? (int)$data['text_id'] : 0;
$final_name = isset($data['final_name']) ? trim($data['final_name']) : '';

if ($text_id <= 0 || empty($final_name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$success = finalizeCollabText($pdo, $text_id, $member_id, $final_name);

if ($success) {
    // Alle Absätze zu einem Text zusammenfügen für Response
    $stmt = $pdo->prepare("
        SELECT content
        FROM svcollab_text_paragraphs
        WHERE text_id = ?
        ORDER BY paragraph_order ASC
    ");
    $stmt->execute([$text_id]);
    $paragraphs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $full_content = implode("\n\n", array_column($paragraphs, 'content'));

    echo json_encode([
        'success' => true,
        'message' => 'Text finalized',
        'final_content' => $full_content
    ]);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Failed to finalize - only initiator can finalize']);
}
