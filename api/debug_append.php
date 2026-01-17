<?php
/**
 * Debug: protocol_secretary_append.php Fehlersuche
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once('../config.php');
require_once('db_connection.php');

header('Content-Type: application/json');

// Authentifizierung prüfen
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated', 'step' => 0]);
    exit;
}

$member_id = $_SESSION['member_id'];
session_write_close();

echo json_encode([
    'step' => 1,
    'message' => 'Auth OK',
    'member_id' => $member_id
]);
echo "\n";

// Test: Wird POST data empfangen?
$data = json_decode(file_get_contents('php://input'), true);
$item_id = isset($data['item_id']) ? (int)$data['item_id'] : 0;
$append_text = isset($data['append_text']) ? trim($data['append_text']) : '';

echo json_encode([
    'step' => 2,
    'message' => 'POST Data OK',
    'item_id' => $item_id,
    'append_text_length' => strlen($append_text)
]);
echo "\n";

if ($item_id <= 0) {
    echo json_encode(['error' => 'Invalid item_id', 'step' => 2.5]);
    exit;
}

try {
    // Test 1: Tabelle svmeetings checken
    echo json_encode(['step' => 3, 'message' => 'Checking svmeetings columns...']);
    echo "\n";

    $columns = $pdo->query("SHOW COLUMNS FROM svmeetings LIKE 'secretary_member_id'")->fetchAll();
    if (empty($columns)) {
        echo json_encode(['error' => 'Column secretary_member_id missing in svmeetings!', 'step' => 3]);
        exit;
    }

    echo json_encode(['step' => 4, 'message' => 'svmeetings.secretary_member_id EXISTS']);
    echo "\n";

    // Test 2: Meeting-Daten holen
    $stmt = $pdo->prepare("
        SELECT ai.meeting_id, ai.protocol_notes, m.collaborative_protocol, m.secretary_member_id
        FROM svagenda_items ai
        JOIN svmeetings m ON ai.meeting_id = m.meeting_id
        WHERE ai.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $item_data = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'step' => 5,
        'message' => 'Meeting data fetched',
        'found' => !empty($item_data),
        'is_secretary' => ($item_data && $item_data['secretary_member_id'] == $member_id),
        'collab_mode' => ($item_data ? $item_data['collaborative_protocol'] : null)
    ]);
    echo "\n";

    if (!$item_data) {
        echo json_encode(['error' => 'Item not found', 'step' => 5]);
        exit;
    }

    // Test 3: Berechtigung
    if ($item_data['secretary_member_id'] != $member_id) {
        echo json_encode(['error' => 'Not secretary', 'step' => 6, 'secretary_id' => $item_data['secretary_member_id'], 'member_id' => $member_id]);
        exit;
    }

    echo json_encode(['step' => 6, 'message' => 'Permission OK']);
    echo "\n";

    if ($item_data['collaborative_protocol'] != 1) {
        echo json_encode(['error' => 'Collaborative mode not enabled', 'step' => 7]);
        exit;
    }

    echo json_encode(['step' => 7, 'message' => 'Collaborative mode active']);
    echo "\n";

    // Test 4: Tabelle svprotocol_versions
    $tables = $pdo->query("SHOW TABLES LIKE 'svprotocol_versions'")->fetchAll();
    echo json_encode(['step' => 8, 'message' => 'svprotocol_versions exists', 'exists' => !empty($tables)]);
    echo "\n";

    // Test 5: Update durchführen
    $current_text = $item_data['protocol_notes'] ?? '';
    if (!empty($current_text) && !preg_match('/\n$/', $current_text)) {
        $new_text = $current_text . "\n" . $append_text;
    } else {
        $new_text = $current_text . $append_text;
    }

    $stmt = $pdo->prepare("
        UPDATE svagenda_items
        SET protocol_notes = ?, protocol_master_id = ?
        WHERE item_id = ?
    ");
    $stmt->execute([$new_text, $member_id, $item_id]);

    echo json_encode(['step' => 9, 'message' => 'Update SUCCESS', 'new_text_length' => strlen($new_text)]);
    echo "\n";

    echo json_encode(['step' => 10, 'message' => 'ALL TESTS PASSED']);

} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTrace(), 0, 3)
    ]);
}
