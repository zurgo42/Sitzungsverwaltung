<?php
/**
 * API: protocol_set_editing.php - Signalisiert dass User gerade editiert
 * POST: item_id
 *
 * Setzt Timestamp in svprotocol_editing
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');

header('Content-Type: application/json');

// Authentifizierung prüfen
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$member_id = $_SESSION['member_id'];
session_write_close();

// Input validieren
$data = json_decode(file_get_contents('php://input'), true);
$item_id = isset($data['item_id']) ? (int)$data['item_id'] : 0;

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid item_id']);
    exit;
}

try {
    // Editing-Status setzen (INSERT or UPDATE)
    $stmt = $pdo->prepare("
        INSERT INTO svprotocol_editing (item_id, member_id, started_at, last_activity)
        VALUES (?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE last_activity = NOW()
    ");
    $stmt->execute([$item_id, $member_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Editing status updated'
    ]);

} catch (PDOException $e) {
    // Tabelle existiert möglicherweise noch nicht - ignorieren
    error_log("protocol_set_editing Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
