<?php
/**
 * API: protocol_get_updates.php - Lädt Updates für kollaboratives Protokoll
 * GET: item_id, since (optional timestamp)
 *
 * Liefert aktuellen Protokoll-Text, Hash und Info wer gerade schreibt
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
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$since = isset($_GET['since']) ? $_GET['since'] : null;

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid item_id']);
    exit;
}

try {
    // Prüfen ob collaborative_protocol Spalte existiert
    $columns_check = $pdo->query("SHOW COLUMNS FROM svmeetings LIKE 'collaborative_protocol'")->fetchAll();
    if (empty($columns_check)) {
        http_response_code(503);
        echo json_encode([
            'error' => 'Database migration required',
            'details' => 'Please run: php run_collab_migration.php'
        ]);
        exit;
    }

    // Prüfen ob User Zugriff auf dieses Meeting hat
    $stmt = $pdo->prepare("
        SELECT m.meeting_id, m.collaborative_protocol, m.secretary_member_id
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

    // Ist User Protokollführung?
    $is_secretary = ($item_data['secretary_member_id'] == $member_id);

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

    // Aktuellen Protokoll-Text laden
    // Erst prüfen ob force_update_at Spalte existiert
    $has_force_update_column = false;
    try {
        $columns_check = $pdo->query("SHOW COLUMNS FROM svagenda_items LIKE 'force_update_at'")->fetchAll();
        $has_force_update_column = !empty($columns_check);
    } catch (PDOException $e) {
        // Ignorieren
    }

    if ($has_force_update_column) {
        $stmt = $pdo->prepare("
            SELECT protocol_notes, top_number, title, force_update_at
            FROM svagenda_items
            WHERE item_id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT protocol_notes, top_number, title
            FROM svagenda_items
            WHERE item_id = ?
        ");
    }
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback wenn force_update_at Spalte nicht existiert
    if (!isset($item['force_update_at'])) {
        $item['force_update_at'] = null;
    }

    $content = $item['protocol_notes'] ?? '';
    $content_hash = md5($content);

    // Lock-Status prüfen
    $lock_info = null;
    try {
        $stmt = $pdo->prepare("
            SELECT l.member_id, l.locked_at,
                   m.first_name, m.last_name,
                   TIMESTAMPDIFF(SECOND, l.locked_at, NOW()) as lock_age
            FROM svprotocol_lock l
            LEFT JOIN svmembers m ON l.member_id = m.member_id
            WHERE l.item_id = ?
            AND TIMESTAMPDIFF(SECOND, l.locked_at, NOW()) <= 30
        ");
        $stmt->execute([$item_id]);
        $lock_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Lock-Tabelle existiert noch nicht - ignorieren
        error_log("svprotocol_lock table not found: " . $e->getMessage());
    }

    $is_locked = ($lock_info !== false && $lock_info !== null);
    $locked_by_me = $is_locked && ($lock_info['member_id'] == $member_id);
    $locked_by_name = null;
    $locked_by_id = null;

    if ($is_locked) {
        $locked_by_id = $lock_info['member_id'];
        $locked_by_name = trim(($lock_info['first_name'] ?? '') . ' ' . ($lock_info['last_name'] ?? ''));
        if (empty($locked_by_name)) {
            $locked_by_name = "User #" . $locked_by_id;
        }
    }

    echo json_encode([
        'success' => true,
        'content' => $content,
        'content_hash' => $content_hash,
        'top_number' => $item['top_number'],
        'title' => $item['title'],
        'collaborative_mode' => (bool)$item_data['collaborative_protocol'],
        'is_secretary' => $is_secretary,
        'is_locked' => $is_locked,
        'locked_by_me' => $locked_by_me,
        'locked_by_id' => $locked_by_id,
        'locked_by_name' => $locked_by_name,
        'force_update_at' => $item['force_update_at'],
        'server_time' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("protocol_get_updates Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
