<?php
/**
 * ajax_get_protocol.php - Protokoll-Updates für Live-Anzeige
 */

error_reporting(0);
ini_set('display_errors', '0');

ob_start();
@session_start();

try {
    @require_once 'config.php';
    @require_once 'functions.php';
    
    if (!isset($pdo)) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
} catch (Exception $e) {
    // Fehler ignorieren
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

if (!$item_id) {
    echo json_encode(['success' => false, 'error' => 'Keine Item-ID']);
    exit;
}

try {
    // Protokolltext und aktiven Status holen
    $stmt = $pdo->prepare("
        SELECT 
            ai.protocol_notes,
            ai.top_number,
            ai.vote_yes,
            ai.vote_no,
            ai.vote_abstain,
            ai.vote_result,
            m.active_item_id,
            m.status
        FROM agenda_items ai
        JOIN meetings m ON ai.meeting_id = m.meeting_id
        WHERE ai.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'TOP nicht gefunden']);
        exit;
    }
    
    $is_active = ($item['active_item_id'] == $item_id);
    
    // Live-Kommentare holen (nur wenn aktiv)
    $live_comments = [];
    if ($is_active) {
        $stmt = $pdo->prepare("
            SELECT alc.*, m.first_name, m.last_name
            FROM agenda_live_comments alc
            JOIN members m ON alc.member_id = m.member_id
            WHERE alc.item_id = ?
            ORDER BY alc.created_at ASC
        ");
        $stmt->execute([$item_id]);
        $live_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'protocol_notes' => $item['protocol_notes'] ?? '',
        'is_active' => $is_active,
        'meeting_status' => $item['status'],
        'vote_yes' => $item['vote_yes'],
        'vote_no' => $item['vote_no'],
        'vote_abstain' => $item['vote_abstain'],
        'vote_result' => $item['vote_result'],
        'live_comments' => $live_comments
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}

exit;
?>
