<?php
/**
 * ajax_feedback.php - AJAX-Endpunkt für Feedback-System
 *
 * Ermöglicht Speichern und Laden von Feedback-Einträgen
 */

// WICHTIG: JSON-Header als allererstes setzen
header('Content-Type: application/json; charset=utf-8');

// Output-Buffering starten um ungewollte Ausgaben zu verhindern
ob_start();

// Fehlerbehandlung: Keine HTML-Ausgaben
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Eigene Error-Handler für JSON-Ausgabe
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $errstr",
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

try {
    // Session starten
    require_once 'session_config.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Konfiguration laden (beinhaltet jetzt $pdo)
    require_once 'config.php';
    require_once 'config_adapter.php';
    require_once 'member_functions.php';

    // PDO-Datenbankverbindung aufbauen
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Prüfen ob eingeloggt
    if (!isset($_SESSION['member_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
        exit;
    }

    // Prüfen ob Feedback-System aktiviert ist
    if (!defined('ENABLE_FEEDBACK_SYSTEM') || !ENABLE_FEEDBACK_SYSTEM) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Feedback-System nicht aktiviert']);
        exit;
    }

    $member_id = $_SESSION['member_id'];
    $current_user = get_member_by_id($pdo, $member_id);

    // Aktion bestimmen
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'save':
        try {
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

            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Feedback gespeichert',
                'timestamp' => date('d.m.Y H:i')
            ]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Datenbankfehler: ' . $e->getMessage(),
                'hint' => 'Wurde die feedback-Tabelle mit init-db.php erstellt?'
            ]);
        }
        break;

    case 'load':
        try {
            // Eigenes Feedback laden
            $stmt = $pdo->prepare("
                SELECT feedback_text, updated_at
                FROM feedback
                WHERE member_id = ? AND is_deleted = 0
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$member_id]);
            $feedback = $stmt->fetch();

            ob_clean();
            echo json_encode([
                'success' => true,
                'feedback' => $feedback ? $feedback['feedback_text'] : '',
                'updated_at' => $feedback ? date('d.m.Y H:i', strtotime($feedback['updated_at'])) : null
            ]);
        } catch (PDOException $e) {
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Datenbankfehler beim Laden: ' . $e->getMessage()
            ]);
        }
        break;

    case 'load_all':
        try {
            // Alle Feedbacks laden (nur für Admins)
            if (!$current_user['is_admin']) {
                ob_clean();
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

            ob_clean();
            echo json_encode([
                'success' => true,
                'feedbacks' => $result
            ]);
        } catch (Exception $e) {
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Fehler beim Laden: ' . $e->getMessage()
            ]);
        }
        break;

    case 'update_single':
        // Einzelnes Feedback bearbeiten (nur für Admins)
        if (!$current_user['is_admin']) {
            ob_clean();
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

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Feedback aktualisiert'
        ]);
        break;

    case 'delete_single':
        // Einzelnes Feedback löschen (nur für Admins)
        if (!$current_user['is_admin']) {
            ob_clean();
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

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Feedback gelöscht'
        ]);
        break;

    default:
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion']);
}

} catch (Exception $e) {
    // Globaler Fehler-Handler
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Systemfehler: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

// Output-Buffer leeren und nur JSON ausgeben
ob_end_flush();
