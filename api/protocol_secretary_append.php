<?php
/**
 * API: protocol_secretary_append.php - Priorisiertes Anhängen für Protokollführung
 * POST: item_id, append_text
 *
 * Nur für Protokollführung - Fortsetzungsfeld
 * Text wird DIREKT ans Hauptsystem angehängt (priorisiert, nicht Queue)
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
$append_text = isset($data['append_text']) ? trim($data['append_text']) : '';

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid item_id']);
    exit;
}

if (empty($append_text)) {
    // Leerer Text → nichts zu tun
    echo json_encode([
        'success' => true,
        'message' => 'No text to append',
        'appended' => false
    ]);
    exit;
}

try {
    // Prüfen ob User Zugriff auf dieses Meeting hat
    $stmt = $pdo->prepare("
        SELECT ai.meeting_id, ai.protocol_notes, m.collaborative_protocol, m.secretary_id
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

    // Prüfen ob User Protokollführung ist
    if ($item_data['secretary_id'] != $member_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Only secretary can use append field']);
        exit;
    }

    // Prüfen ob kollaborativer Modus aktiv ist
    if ($item_data['collaborative_protocol'] != 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Collaborative mode not enabled']);
        exit;
    }

    // Aktuellen Protokolltext holen
    $current_text = $item_data['protocol_notes'] ?? '';

    // Text anhängen (mit Zeilenumbruch wenn nicht leer)
    if (!empty($current_text) && !preg_match('/\n$/', $current_text)) {
        $new_text = $current_text . "\n" . $append_text;
    } else {
        $new_text = $current_text . $append_text;
    }

    // Direkt ins Hauptsystem schreiben (PRIORISIERT - keine Queue)
    $stmt = $pdo->prepare("
        UPDATE svagenda_items
        SET protocol_notes = ?, protocol_master_id = ?
        WHERE item_id = ?
    ");
    $stmt->execute([$new_text, $member_id, $item_id]);

    // Version für History speichern (falls Tabelle existiert)
    $new_hash = md5($new_text);
    try {
        $stmt = $pdo->prepare("
            INSERT INTO svprotocol_versions
            (item_id, protocol_text, modified_by, version_hash)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$item_id, $new_text, $member_id, $new_hash]);
    } catch (PDOException $e) {
        // Tabelle existiert noch nicht - ignorieren
        error_log("svprotocol_versions insert failed: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'appended' => true,
        'new_hash' => $new_hash,
        'appended_length' => strlen($append_text),
        'total_length' => strlen($new_text),
        'saved_at' => date('Y-m-d H:i:s'),
        'message' => 'Text angehängt und übertragen'
    ]);

} catch (PDOException $e) {
    error_log("protocol_secretary_append Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
