<?php
/**
 * ajax_meeting_actions.php - Ultra-robuste Version
 * Verhindert JEDE HTML-Ausgabe vor JSON
 */

// SCHRITT 1: Alle Fehler unterdrücken BEVOR irgendwas geladen wird
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// SCHRITT 2: Output Buffer SOFORT starten
ob_start();

// SCHRITT 3: Session starten (kann Warnings ausgeben)
@session_start();

// SCHRITT 4: Dateien laden (können Fehler ausgeben)
$error_msg = null;
try {
    @require_once 'config.php';
    @require_once 'functions.php';
} catch (Exception $e) {
    $error_msg = 'Fehler beim Laden: ' . $e->getMessage();
}

// SCHRITT 5: PDO erstellen falls nötig
if (!$error_msg && !isset($pdo)) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        $error_msg = 'DB-Verbindung fehlgeschlagen';
    }
}

// SCHRITT 6: JETZT erst Buffer leeren und JSON starten
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// SCHRITT 7: Fehler aus Schritt 4/5 ausgeben
if ($error_msg) {
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit;
}

// SCHRITT 8: Login prüfen
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

// SCHRITT 9: User laden
try {
    $current_user = get_current_member($_SESSION['member_id']);
    if (!$current_user) {
        echo json_encode(['success' => false, 'error' => 'Benutzer nicht gefunden']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Fehler beim Laden des Users']);
    exit;
}

// SCHRITT 10: Parameter prüfen
$action = isset($_POST['action']) ? $_POST['action'] : '';
$meeting_id = isset($_POST['meeting_id']) ? intval($_POST['meeting_id']) : 0;

if (!$meeting_id) {
    echo json_encode(['success' => false, 'error' => 'Keine Meeting-ID']);
    exit;
}

// SCHRITT 11: Meeting laden
try {
    $stmt = $pdo->prepare("SELECT * FROM svmeetings WHERE meeting_id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$meeting) {
        echo json_encode(['success' => false, 'error' => 'Meeting nicht gefunden']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Fehler beim Laden des Meetings']);
    exit;
}

// SCHRITT 12: Aktion ausführen
try {
    switch ($action) {
        case 'set_active_top':
            // Berechtigung prüfen
            if ($meeting['secretary_member_id'] != $current_user['member_id']) {
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
            if (!$item_id) {
                echo json_encode(['success' => false, 'error' => 'Keine Item-ID']);
                exit;
            }
            
            // Active Item ID setzen
            $stmt = $pdo->prepare("UPDATE svmeetings SET active_item_id = ? WHERE meeting_id = ?");
            $stmt->execute([$item_id, $meeting_id]);
            
            echo json_encode(['success' => true, 'message' => 'TOP aktiviert']);
            break;
            
        case 'unset_active_top':
            // Berechtigung prüfen
            if ($meeting['secretary_member_id'] != $current_user['member_id']) {
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            
            // Active Item ID entfernen
            $stmt = $pdo->prepare("UPDATE svmeetings SET active_item_id = NULL WHERE meeting_id = ?");
            $stmt->execute([$meeting_id]);
            
            echo json_encode(['success' => true, 'message' => 'TOP deaktiviert']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion: ' . $action]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler', 'details' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Fehler', 'details' => $e->getMessage()]);
}

exit;
?>