<?php
/**
 * API: Fügt neuen Absatz hinzu
 * POST: text_id, after_order (optional)
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

$data = json_decode(file_get_contents('php://input'), true);
$text_id = isset($data['text_id']) ? (int)$data['text_id'] : 0;
$after_order = isset($data['after_order']) ? (int)$data['after_order'] : null;

if ($text_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid text_id']);
    exit;
}

if (!hasCollabTextAccess($pdo, $text_id, $_SESSION['member_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$paragraph_id = addParagraph($pdo, $text_id, $_SESSION['member_id'], $after_order);

if ($paragraph_id) {
    echo json_encode([
        'success' => true,
        'paragraph_id' => $paragraph_id,
        'message' => 'Paragraph added'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add paragraph']);
}
