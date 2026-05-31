<?php
/**
 * ajax_feedback.php - AJAX-Endpunkt für Feedback-System
 *
 * Ermöglicht Speichern und Laden von Feedback-Einträgen
 */

// Session starten
require_once 'session_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfiguration laden
require_once 'config.php';
require_once 'config_adapter.php';
require_once 'member_functions.php';

// JSON-Header setzen
header('Content-Type: application/json');

// Prüfen ob eingeloggt
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

// Prüfen ob Feedback-System aktiviert ist
if (!defined('ENABLE_FEEDBACK_SYSTEM') || !ENABLE_FEEDBACK_SYSTEM) {
    echo json_encode(['success' => false, 'error' => 'Feedback-System nicht aktiviert']);
    exit;
}

$member_id = $_SESSION['member_id'];
$current_user = get_member_by_id($pdo, $member_id);

// Aktion bestimmen
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'save':
        // Eigenes Feedback speichern/aktualisieren
        $feedback_text = trim($_POST['feedback'] ?? '');

        // Prüfen ob bereits ein Eintrag existiert
        $stmt = $pdo->prepare("
            SELECT id FROM feedback
            WHERE member_id = ? AND is_deleted = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$member_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE feedback
                SET feedback_text = ?, updated_at = NOW()
                WHERE id = ? AND member_id = ?
            ");
            $stmt->execute([$feedback_text, $existing['id'], $member_id]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO feedback (member_id, feedback_text, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            $stmt->execute([$member_id, $feedback_text]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Feedback gespeichert',
            'timestamp' => date('d.m.Y H:i')
        ]);
        break;

    case 'load':
        // Eigenes Feedback laden
        $stmt = $pdo->prepare("
            SELECT feedback_text, updated_at
            FROM feedback
            WHERE member_id = ? AND is_deleted = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$member_id]);
        $feedback = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'feedback' => $feedback ? $feedback['feedback_text'] : '',
            'updated_at' => $feedback ? date('d.m.Y H:i', strtotime($feedback['updated_at'])) : null
        ]);
        break;

    case 'load_all':
        // Alle Feedbacks laden (nur für Admins)
        if (!$current_user['is_admin']) {
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
            exit;
        }

        $stmt = $pdo->query("
            SELECT f.id, f.member_id, f.feedback_text, f.created_at, f.updated_at
            FROM feedback f
            WHERE f.is_deleted = 0
            ORDER BY f.updated_at DESC
        ");
        $feedbacks = $stmt->fetchAll();

        // Member-Namen hinzufügen
        $all_members = get_all_members($pdo);
        $members_by_id = [];
        foreach ($all_members as $m) {
            $members_by_id[$m['member_id']] = $m;
        }

        $result = [];
        foreach ($feedbacks as $f) {
            $member = $members_by_id[$f['member_id']] ?? null;
            $result[] = [
                'id' => $f['id'],
                'member_id' => $f['member_id'],
                'member_name' => $member ? $member['first_name'] . ' ' . $member['last_name'] : 'Unbekannt',
                'feedback_text' => $f['feedback_text'],
                'created_at' => date('d.m.Y H:i', strtotime($f['created_at'])),
                'updated_at' => date('d.m.Y H:i', strtotime($f['updated_at']))
            ];
        }

        echo json_encode([
            'success' => true,
            'feedbacks' => $result
        ]);
        break;

    case 'update_single':
        // Einzelnes Feedback bearbeiten (nur für Admins)
        if (!$current_user['is_admin']) {
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
            exit;
        }

        $feedback_id = (int)($_POST['id'] ?? 0);
        $feedback_text = trim($_POST['feedback'] ?? '');

        $stmt = $pdo->prepare("
            UPDATE feedback
            SET feedback_text = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$feedback_text, $feedback_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Feedback aktualisiert'
        ]);
        break;

    case 'delete_single':
        // Einzelnes Feedback löschen (nur für Admins)
        if (!$current_user['is_admin']) {
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
            exit;
        }

        $feedback_id = (int)($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("
            UPDATE feedback
            SET is_deleted = 1, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$feedback_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Feedback gelöscht'
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion']);
}
