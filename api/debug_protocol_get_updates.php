<?php
/**
 * Debug: protocol_get_updates.php
 * Zeigt detaillierte Fehlermeldungen
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');

// DEBUG MODE ON
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['member_id'])) {
        throw new Exception("Not authenticated");
    }

    $member_id = $_SESSION['member_id'];
    session_write_close();

    $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

    if ($item_id <= 0) {
        throw new Exception("Invalid item_id: $item_id");
    }

    echo json_encode([
        'debug' => 'Step 1: Auth OK',
        'item_id' => $item_id,
        'member_id' => $member_id
    ]);

    // Test 1: Prüfe ob collaborative_protocol Spalte existiert
    $columns = $pdo->query("SHOW COLUMNS FROM svmeetings LIKE 'collaborative_protocol'")->fetchAll();
    if (empty($columns)) {
        throw new Exception("Column collaborative_protocol not found");
    }

    echo "\n" . json_encode(['debug' => 'Step 2: Column check OK']);

    // Test 2: Meeting laden
    $stmt = $pdo->prepare("
        SELECT m.meeting_id, m.collaborative_protocol, m.secretary_id
        FROM svagenda_items ai
        JOIN svmeetings m ON ai.meeting_id = m.meeting_id
        WHERE ai.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $item_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item_data) {
        throw new Exception("Item not found: $item_id");
    }

    echo "\n" . json_encode(['debug' => 'Step 3: Item loaded', 'data' => $item_data]);

    // Test 3: force_update_at Spalte
    $columns2 = $pdo->query("SHOW COLUMNS FROM svagenda_items LIKE 'force_update_at'")->fetchAll();
    $has_force = !empty($columns2);

    echo "\n" . json_encode(['debug' => 'Step 4: force_update_at', 'exists' => $has_force]);

    // Test 4: Item Details laden
    if ($has_force) {
        $stmt = $pdo->prepare("SELECT protocol_notes, top_number, title, force_update_at FROM svagenda_items WHERE item_id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT protocol_notes, top_number, title FROM svagenda_items WHERE item_id = ?");
    }
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n" . json_encode(['debug' => 'Step 5: Item details loaded', 'item' => $item]);

    echo "\n" . json_encode(['success' => true, 'message' => 'All tests passed!']);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
