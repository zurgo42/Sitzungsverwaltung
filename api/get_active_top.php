<?php
/**
 * API: Aktiven TOP abrufen
 * Wird verwendet um zu prüfen ob sich der aktive TOP geändert hat
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');

header('Content-Type: application/json');

// Authentifizierung prüfen
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Session-Daten gelesen → Session sofort schließen
$member_id = $_SESSION['member_id'];
session_write_close();

// Meeting ID validieren
$meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;

if (!$meeting_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing meeting_id']);
    exit;
}

try {
    // Aktiven TOP abrufen
    $stmt = $pdo->prepare("
        SELECT active_item_id
        FROM svmeetings
        WHERE meeting_id = ?
    ");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$meeting) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Meeting not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'active_item_id' => $meeting['active_item_id'] ? intval($meeting['active_item_id']) : null
    ]);

} catch (PDOException $e) {
    error_log("Get active TOP error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
