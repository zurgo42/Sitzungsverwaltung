<?php
/**
 * API: Löscht einen Absatz
 * POST: paragraph_id
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

if ($paragraph_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid paragraph_id']);
    exit;
}

// Zugriffsprüfung
$stmt = $pdo->prepare("SELECT text_id FROM svcollab_text_paragraphs WHERE paragraph_id = ?");
$stmt->execute([$paragraph_id]);
$para = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$para || !hasCollabTextAccess($pdo, $para['text_id'], $member_id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Prüfen ob mehr als 1 Absatz vorhanden (mindestens 1 muss bleiben)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM svcollab_text_paragraphs WHERE text_id = ?");
$stmt->execute([$para['text_id']]);
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($count <= 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot delete last paragraph']);
    exit;
}

$success = deleteParagraph($pdo, $paragraph_id, $member_id);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Paragraph deleted']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete paragraph']);
}
