<?php
/**
 * API: protocol_autosave.php - Auto-Save für kollaboratives Protokoll
 * POST: item_id, content, cursor_pos
 *
 * Speichert Protokoll-Text und erstellt Version für Konflikt-Erkennung
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

// Session-Daten gelesen → Session sofort schließen für parallele Requests
$member_id = $_SESSION['member_id'];
session_write_close();

// Input validieren
$data = json_decode(file_get_contents('php://input'), true);
$item_id = isset($data['item_id']) ? (int)$data['item_id'] : 0;
$content = isset($data['content']) ? $data['content'] : '';
$cursor_pos = isset($data['cursor_pos']) ? (int)$data['cursor_pos'] : 0;
$client_hash = isset($data['client_hash']) ? $data['client_hash'] : '';
$is_typing = isset($data['is_typing']) ? (bool)$data['is_typing'] : false;
$force = isset($data['force']) ? (bool)$data['force'] : false;

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

    // Prüfen ob User Teilnehmer des Meetings ist
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

    // Aktuellen Inhalt aus DB laden für Konflikt-Erkennung
    $stmt = $pdo->prepare("SELECT protocol_notes FROM svagenda_items WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $current_content = $stmt->fetchColumn();
    $current_hash = md5($current_content ?? '');

    // Konflikt-Erkennung: Hat sich der Inhalt seit letztem Load geändert?
    $has_conflict = false;
    if (!empty($client_hash) && $client_hash !== $current_hash) {
        $has_conflict = true;
    }

    // Protokoll-Text speichern
    if ($force) {
        // Bei Force-Save auch force_update_at setzen (falls Spalte existiert)
        try {
            $stmt = $pdo->prepare("
                UPDATE svagenda_items
                SET protocol_notes = ?, force_update_at = NOW()
                WHERE item_id = ?
            ");
            $stmt->execute([$content, $item_id]);
        } catch (PDOException $e) {
            // Spalte existiert noch nicht - normales Update
            $stmt = $pdo->prepare("
                UPDATE svagenda_items
                SET protocol_notes = ?
                WHERE item_id = ?
            ");
            $stmt->execute([$content, $item_id]);
        }
    } else {
        $stmt = $pdo->prepare("
            UPDATE svagenda_items
            SET protocol_notes = ?
            WHERE item_id = ?
        ");
        $stmt->execute([$content, $item_id]);
    }

    // Version für History speichern (falls Tabelle existiert)
    $new_hash = md5($content);
    try {
        $stmt = $pdo->prepare("
            INSERT INTO svprotocol_versions
            (item_id, protocol_text, modified_by, version_hash)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$item_id, $content, $member_id, $new_hash]);
    } catch (PDOException $e) {
        // Tabelle existiert noch nicht - ignorieren
        error_log("svprotocol_versions insert failed: " . $e->getMessage());
    }

    // "Wer editiert gerade" aktualisieren (nur wenn User gerade tippt)
    if ($is_typing) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO svprotocol_editing (item_id, member_id, last_activity)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE member_id = ?, last_activity = NOW()
            ");
            $stmt->execute([$item_id, $member_id, $member_id]);
        } catch (PDOException $e) {
            // Tabelle existiert noch nicht - ignorieren
            error_log("svprotocol_editing update failed: " . $e->getMessage());
        }
    }

    // Force-Update Timestamp laden (falls vorhanden)
    $force_update_at = null;
    try {
        $stmt = $pdo->prepare("SELECT force_update_at FROM svagenda_items WHERE item_id = ?");
        $stmt->execute([$item_id]);
        $force_update_at = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Spalte existiert noch nicht
    }

    echo json_encode([
        'success' => true,
        'new_hash' => $new_hash,
        'has_conflict' => $has_conflict,
        'saved_at' => date('Y-m-d H:i:s'),
        'force_update_at' => $force_update_at,
        'is_force' => $force
    ]);

} catch (PDOException $e) {
    error_log("protocol_autosave Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
