<?php
/**
 * API: Speichert Mitschrift (direktes Speichern, kein Queue)
 * Voraussetzung: User muss Lock halten
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
$content = isset($input['content']) ? $input['content'] : '';

if (!$item_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing item_id']);
    exit;
}

try {
    // Prüfen ob Meeting kollaborativ ist
    $stmt = $pdo->prepare("
        SELECT m.collaborative_protocol
        FROM svagenda_items ai
        JOIN svmeetings m ON ai.meeting_id = m.meeting_id
        WHERE ai.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$meeting || $meeting['collaborative_protocol'] != 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Not a collaborative meeting']);
        exit;
    }

    // Prüfen ob User den Lock hält
    $stmt = $pdo->prepare("
        SELECT member_id
        FROM svprotocol_lock
        WHERE item_id = ? AND member_id = ?
        AND TIMESTAMPDIFF(SECOND, locked_at, NOW()) <= 30
    ");
    $stmt->execute([$item_id, $member_id]);
    $has_lock = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$has_lock) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No lock held']);
        exit;
    }

    // Content speichern
    $stmt = $pdo->prepare("
        UPDATE svagenda_items
        SET protocol_notes = ?,
            updated_at = NOW()
        WHERE item_id = ?
    ");
    $stmt->execute([$content, $item_id]);

    // Version speichern (mit korrekten Spaltennamen)
    $stmt = $pdo->prepare("
        INSERT INTO svprotocol_versions (item_id, protocol_text, modified_by)
        VALUES (?, ?, ?)
    ");

    // modified_at wird automatisch auf CURRENT_TIMESTAMP gesetzt
    $stmt->execute([$item_id, $content, $member_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Saved successfully'
    ]);

} catch (PDOException $e) {
    error_log("Protocol save error: " . $e->getMessage());
    error_log("Protocol save SQL state: " . $e->getCode());
    error_log("Protocol save trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'item_id' => $item_id
    ]);
}
