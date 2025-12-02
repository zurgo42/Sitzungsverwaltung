<?php
/**
 * API: Erstellt Versions-Snapshot
 * POST: text_id, note (optional)
 */
session_start();
require_once('../config.php');
require_once('../functions.php');
require_once('../functions_collab_text.php');

header('Content-Type: application/json');

if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$text_id = isset($data['text_id']) ? (int)$data['text_id'] : 0;
$note = isset($data['note']) ? trim($data['note']) : '';

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

$version_number = createTextVersion($pdo, $text_id, $_SESSION['member_id'], $note);

if ($version_number) {
    echo json_encode([
        'success' => true,
        'version_number' => $version_number,
        'message' => 'Version created'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create version']);
}
