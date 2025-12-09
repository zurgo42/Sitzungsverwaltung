<?php
/**
 * API: Holt Updates für kollaborativen Text (Polling)
 * GET Parameter: text_id, since (ISO DateTime)
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');
require_once('../functions_collab_text.php');

header('Content-Type: application/json');

// Prüfen ob eingeloggt
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Session-Daten gelesen → Session sofort schließen für parallele Requests
$member_id = $_SESSION['member_id'];
session_write_close();

$text_id = isset($_GET['text_id']) ? (int)$_GET['text_id'] : 0;
$since = isset($_GET['since']) ? $_GET['since'] : date('Y-m-d H:i:s', strtotime('-1 hour'));

if ($text_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid text_id']);
    exit;
}

// Zugriffsprüfung
if (!hasCollabTextAccess($pdo, $text_id, $member_id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    // Alte Locks aufräumen (älter als 5 Minuten)
    $pdo->exec("
        DELETE FROM svcollab_text_locks
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");

    // Absätze mit Änderungen seit $since (nur aktive Locks berücksichtigen)
    $stmt = $pdo->prepare("
        SELECT p.paragraph_id, p.paragraph_order, p.content,
               p.last_edited_by, p.last_edited_at,
               m.first_name as editor_first_name,
               m.last_name as editor_last_name,
               l.member_id as locked_by_member_id,
               lm.first_name as locked_by_first_name,
               lm.last_name as locked_by_last_name
        FROM svcollab_text_paragraphs p
        LEFT JOIN svmembers m ON p.last_edited_by = m.member_id
        LEFT JOIN svcollab_text_locks l ON p.paragraph_id = l.paragraph_id
            AND l.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LEFT JOIN svmembers lm ON l.member_id = lm.member_id
        WHERE p.text_id = ? AND p.last_edited_at > ?
        ORDER BY p.paragraph_order ASC
    ");
    $stmt->execute([$text_id, $since]);
    $paragraphs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Online-Teilnehmer
    $online_users = getOnlineParticipants($pdo, $text_id);

    // Text-Status (falls finalized)
    $stmt = $pdo->prepare("SELECT status, finalized_at FROM svcollab_texts WHERE text_id = ?");
    $stmt->execute([$text_id]);
    $text_status = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'paragraphs' => $paragraphs,
        'online_users' => $online_users,
        'text_status' => $text_status['status'],
        'finalized_at' => $text_status['finalized_at'],
        'server_time' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
