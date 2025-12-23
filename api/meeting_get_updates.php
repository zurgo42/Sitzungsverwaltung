<?php
/**
 * API: Holt Updates für Meeting-Protokolle (Polling)
 * GET Parameter: item_id
 *
 * Einheitliche Live-API-Architektur für:
 * - Meeting-Protokoll-Updates
 * - Textbearbeitung
 * - Messenger (zukünftig)
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');
require_once('../member_functions.php');

header('Content-Type: application/json');

// Authentifizierung prüfen
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Session-Daten gelesen → Session sofort schließen für parallele Requests
$member_id = $_SESSION['member_id'];
session_write_close();

// Parameter validieren
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid item_id']);
    exit;
}

try {
    // Zugriffsprüfung: User muss Teilnehmer des Meetings sein
    $stmt = $pdo->prepare("
        SELECT mp.member_id
        FROM svagenda_items ai
        JOIN svmeetings m ON ai.meeting_id = m.meeting_id
        JOIN svmeeting_participants mp ON m.meeting_id = mp.meeting_id
        WHERE ai.item_id = ? AND mp.member_id = ?
    ");
    $stmt->execute([$item_id, $member_id]);

    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    // Protokolltext und Status holen (OHNE JOIN auf svmembers)
    $stmt = $pdo->prepare("
        SELECT
            ai.protocol_notes,
            ai.top_number,
            ai.vote_yes,
            ai.vote_no,
            ai.vote_abstain,
            ai.vote_result,
            m.status as meeting_status,
            m.active_item_id,
            m.secretary_member_id
        FROM svagenda_items ai
        JOIN svmeetings m ON ai.meeting_id = m.meeting_id
        WHERE ai.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Secretary-Namen über Adapter holen
    $secretary_name = null;
    if ($data && $data['secretary_member_id']) {
        $secretary = get_member_by_id($pdo, $data['secretary_member_id']);
        if ($secretary) {
            $secretary_name = $secretary['first_name'] . ' ' . $secretary['last_name'];
        }
    }

    if (!$data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit;
    }

    // Prüfen ob dieser TOP gerade aktiv ist
    $is_active = ($data['active_item_id'] == $item_id);

    // Live-Kommentare holen (nur wenn dieser TOP aktiv ist)
    // OHNE JOIN auf svmembers - Namen werden über Adapter geholt
    $live_comments = [];
    if ($is_active) {
        $stmt = $pdo->prepare("
            SELECT alc.*
            FROM svagenda_live_comments alc
            WHERE alc.item_id = ?
            ORDER BY alc.created_at ASC
        ");
        $stmt->execute([$item_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Namen über Adapter holen
        foreach ($comments as $comment) {
            $member = get_member_by_id($pdo, $comment['member_id']);
            if ($member) {
                $comment['first_name'] = $member['first_name'];
                $comment['last_name'] = $member['last_name'];
                $live_comments[] = $comment;
            }
        }
    }

    // Erfolgreich: Daten zurückgeben
    echo json_encode([
        'success' => true,
        'protocol_notes' => $data['protocol_notes'] ?? '',
        'top_number' => $data['top_number'],
        'vote_yes' => $data['vote_yes'],
        'vote_no' => $data['vote_no'],
        'vote_abstain' => $data['vote_abstain'],
        'vote_result' => $data['vote_result'],
        'meeting_status' => $data['meeting_status'],
        'is_active' => $is_active,
        'live_comments' => $live_comments,
        'secretary_name' => $secretary_name,
        'server_time' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("meeting_get_updates Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
