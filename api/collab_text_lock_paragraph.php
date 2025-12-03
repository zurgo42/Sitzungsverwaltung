<?php
/**
 * API: Sperrt einen Absatz für Bearbeitung
 * POST: paragraph_id
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
$paragraph_id = isset($data['paragraph_id']) ? (int)$data['paragraph_id'] : 0;

if ($paragraph_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid paragraph_id']);
    exit;
}

// Zugriffsprüfung über text_id
$stmt = $pdo->prepare("
    SELECT p.text_id
    FROM svcollab_text_paragraphs p
    WHERE p.paragraph_id = ?
");
$stmt->execute([$paragraph_id]);
$para = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$para || !hasCollabTextAccess($pdo, $para['text_id'], $member_id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$success = lockParagraph($pdo, $paragraph_id, $member_id);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Paragraph locked']);
} else {
    // Wer hat den Lock?
    $stmt = $pdo->prepare("
        SELECT l.member_id, m.first_name, m.last_name
        FROM svcollab_text_locks l
        JOIN svmembers m ON l.member_id = m.member_id
        WHERE l.paragraph_id = ?
    ");
    $stmt->execute([$paragraph_id]);
    $lock_owner = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => false,
        'message' => 'Paragraph is locked by another user',
        'locked_by' => $lock_owner ? $lock_owner['first_name'] . ' ' . $lock_owner['last_name'] : 'Unknown'
    ]);
}
