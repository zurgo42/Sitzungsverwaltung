<?php
/**
 * API: protocol_queue_save.php - Speichert Änderung in Queue
 * POST: item_id, content
 *
 * Für normale User und Protokollführung (wenn Hauptfenster editiert wird)
 * Änderungen werden in Queue eingereiht und chronologisch verarbeitet
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
$content = isset($data['content']) ? $data['content'] : '';

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid item_id']);
    exit;
}

try {
    // Prüfen ob Queue-Tabelle existiert
    $tables_check = $pdo->query("SHOW TABLES LIKE 'svprotocol_changes_queue'")->fetchAll();
    if (empty($tables_check)) {
        http_response_code(503);
        echo json_encode([
            'error' => 'Database migration required',
            'details' => 'Please run: php run_queue_migration.php'
        ]);
        exit;
    }

    // Prüfen ob User Zugriff auf dieses Meeting hat
    $stmt = $pdo->prepare("
        SELECT m.meeting_id, m.collaborative_protocol
        FROM svagenda_items ai
        JOIN svmeetings m ON ai.meeting_id = m.meeting_id
        WHERE ai.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $item_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item_data) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        exit;
    }

    // Prüfen ob User Teilnehmer ist
    $stmt = $pdo->prepare("
        SELECT 1 FROM svmeeting_participants
        WHERE meeting_id = ? AND member_id = ?
    ");
    $stmt->execute([$item_data['meeting_id'], $member_id]);
    $is_participant = $stmt->fetchColumn();

    if (!$is_participant) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Prüfen ob kollaborativer Modus aktiv ist
    if ($item_data['collaborative_protocol'] != 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Collaborative mode not enabled']);
        exit;
    }

    // In Queue eintragen
    $stmt = $pdo->prepare("
        INSERT INTO svprotocol_changes_queue
        (item_id, member_id, protocol_text, submitted_at, processed)
        VALUES (?, ?, ?, NOW(), 0)
    ");
    $stmt->execute([$item_id, $member_id, $content]);

    $change_id = $pdo->lastInsertId();

    // Wie viele Einträge sind vor diesem in der Queue?
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM svprotocol_changes_queue
        WHERE item_id = ? AND processed = 0 AND change_id < ?
    ");
    $stmt->execute([$item_id, $change_id]);
    $position = $stmt->fetchColumn() + 1; // +1 weil dieser auch zählt

    // Gesamt-Queue-Größe
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM svprotocol_changes_queue
        WHERE item_id = ? AND processed = 0
    ");
    $stmt->execute([$item_id]);
    $queue_size = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'change_id' => $change_id,
        'queue_position' => $position,
        'queue_size' => $queue_size,
        'message' => 'In Queue eingereiht',
        'saved_at' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("protocol_queue_save Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
