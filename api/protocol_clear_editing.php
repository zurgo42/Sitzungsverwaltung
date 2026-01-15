<?php
/**
 * API: protocol_clear_editing.php - Clears "currently editing" status
 * POST: item_id
 *
 * Removes user from active editors list (called on blur)
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

// Session-Daten gelesen → Session sofort schließen
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
    // Editing-Status löschen
    $stmt = $pdo->prepare("
        DELETE FROM svprotocol_editing
        WHERE item_id = ? AND member_id = ?
    ");
    $stmt->execute([$item_id, $member_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Editing status cleared'
    ]);

} catch (PDOException $e) {
    // Tabelle existiert noch nicht - ignorieren
    error_log("protocol_clear_editing Error: " . $e->getMessage());
    // Trotzdem success zurückgeben, damit Client nicht blockiert wird
    echo json_encode([
        'success' => true,
        'message' => 'Table not found, ignored'
    ]);
}
