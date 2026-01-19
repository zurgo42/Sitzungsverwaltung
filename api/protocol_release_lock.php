<?php
/**
 * API: Lock für Mitschrift-Feld freigeben
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

// Input validieren
$input = json_decode(file_get_contents('php://input'), true);
$item_id = isset($input['item_id']) ? intval($input['item_id']) : 0;

if (!$item_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing item_id']);
    exit;
}

try {
    // Nur eigenen Lock freigeben
    $stmt = $pdo->prepare("
        DELETE FROM svprotocol_lock
        WHERE item_id = ? AND member_id = ?
    ");
    $stmt->execute([$item_id, $member_id]);

    $released = $stmt->rowCount() > 0;

    echo json_encode([
        'success' => true,
        'released' => $released,
        'item_id' => $item_id
    ]);

} catch (PDOException $e) {
    error_log("Lock release error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
