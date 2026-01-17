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

    // Wer editiert gerade? (aktiv in letzten 10 Sekunden - schnelleres Timeout)
    $editors = [];
    try {
        $stmt = $pdo->prepare("
            SELECT pe.member_id, m.first_name, m.last_name,
                   TIMESTAMPDIFF(SECOND, pe.last_activity, NOW()) as seconds_ago
            FROM svprotocol_editing pe
            JOIN svmembers m ON pe.member_id = m.member_id
            WHERE pe.item_id = ?
            AND pe.last_activity > DATE_SUB(NOW(), INTERVAL 10 SECOND)
            AND pe.member_id != ?
        ");
        $stmt->execute([$item_id, $member_id]);
        $editors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Tabelle existiert noch nicht - ignorieren
        error_log("svprotocol_editing table not found: " . $e->getMessage());
    }

    // Editoren-Namen zusammenbauen
    $editor_names = [];
    foreach ($editors as $editor) {
        $editor_names[] = $editor['first_name'] . ' ' . $editor['last_name'];
    }

    // Letzte Änderung
    $last_change = null;
    try {
        $stmt = $pdo->prepare("
            SELECT pv.modified_at, m.first_name, m.last_name
            FROM svprotocol_versions pv
            JOIN svmembers m ON pv.modified_by = m.member_id
            WHERE pv.item_id = ?
            ORDER BY pv.modified_at DESC
            LIMIT 1
        ");
        $stmt->execute([$item_id]);
        $last_change = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Tabelle existiert noch nicht - ignorieren
        error_log("svprotocol_versions table not found: " . $e->getMessage());
    }

    // Queue-Informationen (für Master-Slave Pattern)
    $queue_size = 0;
    $queue_waiting = [];
    try {
        // Anzahl wartender Einträge
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM svprotocol_changes_queue
            WHERE item_id = ? AND processed = 0
        ");
        $stmt->execute([$item_id]);
        $queue_size = (int)$stmt->fetchColumn();

        // Details zu wartenden Einträgen (für Anzeige)
        if ($queue_size > 0 && $is_secretary) {
            $stmt = $pdo->prepare("
                SELECT q.change_id, q.submitted_at, m.first_name, m.last_name
                FROM svprotocol_changes_queue q
                JOIN svmembers m ON q.member_id = m.member_id
                WHERE q.item_id = ? AND q.processed = 0
                ORDER BY q.submitted_at ASC
                LIMIT 5
            ");
            $stmt->execute([$item_id]);
            $queue_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($queue_entries as $entry) {
                $queue_waiting[] = [
                    'name' => $entry['first_name'] . ' ' . $entry['last_name'],
                    'submitted_at' => $entry['submitted_at']
                ];
            }
        }
    } catch (PDOException $e) {
        // Queue-Tabelle existiert noch nicht - ignorieren
        error_log("svprotocol_changes_queue table not found: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'content' => $content,
        'content_hash' => $content_hash,
        'top_number' => $item['top_number'],
        'title' => $item['title'],
        'collaborative_mode' => (bool)$item_data['collaborative_protocol'],
        'is_secretary' => $is_secretary,
        'editors' => $editor_names,
        'editor_count' => count($editor_names),
        'last_modified_by' => $last_change ? ($last_change['first_name'] . ' ' . $last_change['last_name']) : null,
        'last_modified_at' => $last_change ? $last_change['modified_at'] : null,
        'force_update_at' => $item['force_update_at'],
        'queue_size' => $queue_size,
        'queue_waiting' => $queue_waiting,
        'server_time' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("protocol_get_updates Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
