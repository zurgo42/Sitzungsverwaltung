<?php
/**
 * API: Heartbeat - signalisiert dass User noch online ist
 * POST: text_id
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
$text_id = isset($data['text_id']) ? (int)$data['text_id'] : 0;

if ($text_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid text_id']);
    exit;
}

if (!hasCollabTextAccess($pdo, $text_id, $member_id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$success = updateParticipantHeartbeat($pdo, $text_id, $member_id);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update heartbeat']);
}
