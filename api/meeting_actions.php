<?php
/**
 * API: Meeting-Aktionen (TOP aktivieren/deaktivieren)
 * POST Parameter: action, meeting_id, item_id (optional)
 *
 * Einheitliche Live-API-Architektur
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');
require_once('../functions.php');

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
$action = isset($_POST['action']) ? $_POST['action'] : '';
$meeting_id = isset($_POST['meeting_id']) ? intval($_POST['meeting_id']) : 0;

if (!$meeting_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid meeting_id']);
    exit;
}

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

try {
    // Meeting und Berechtigungen laden
    $stmt = $pdo->prepare("SELECT * FROM svmeetings WHERE meeting_id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$meeting) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Meeting not found']);
        exit;
    }

    // Nur Sekretär darf TOP aktivieren/deaktivieren
    if ($meeting['secretary_member_id'] != $member_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. Only secretary can perform this action.']);
        exit;
    }

    // Aktion ausführen
    switch ($action) {
        case 'set_active_top':
            $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

            if (!$item_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid item_id']);
                exit;
            }

            // Prüfen ob Item zum Meeting gehört
            $stmt = $pdo->prepare("SELECT item_id FROM svagenda_items WHERE item_id = ? AND meeting_id = ?");
            $stmt->execute([$item_id, $meeting_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Item not found in this meeting']);
                exit;
            }

            // Active Item ID setzen
            $stmt = $pdo->prepare("UPDATE svmeetings SET active_item_id = ? WHERE meeting_id = ?");
            $stmt->execute([$item_id, $meeting_id]);

            echo json_encode([
                'success' => true,
                'message' => 'TOP activated',
                'active_item_id' => $item_id
            ]);
            break;

        case 'unset_active_top':
            // Active Item ID entfernen
            $stmt = $pdo->prepare("UPDATE svmeetings SET active_item_id = NULL WHERE meeting_id = ?");
            $stmt->execute([$meeting_id]);

            echo json_encode([
                'success' => true,
                'message' => 'TOP deactivated',
                'active_item_id' => null
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }

} catch (PDOException $e) {
    error_log("meeting_actions Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
