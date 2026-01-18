<?php
/**
 * API: Lock für Mitschrift-Feld freigeben
 */

require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Login-Check
$current_user = check_login();
if (!$current_user) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Input validieren
$input = json_decode(file_get_contents('php://input'), true);
$item_id = isset($input['item_id']) ? intval($input['item_id']) : 0;

if (!$item_id) {
    echo json_encode(['success' => false, 'error' => 'Missing item_id']);
    exit;
}

$member_id = $current_user['member_id'];

try {
    $pdo = get_db_connection();

    // Nur eigenen Lock freigeben
    $stmt = $pdo->prepare("
        DELETE FROM svprotocol_lock
        WHERE item_id = ? AND member_id = ?
    ");
    $stmt->execute([$item_id, $member_id]);

    $released = $stmt->rowCount() > 0;

    echo json_encode([
        'success' => true,
        'released' => $released
    ]);

} catch (PDOException $e) {
    error_log("Lock release error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
