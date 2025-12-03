<?php
/**
 * API: Löscht einen kollaborativen Text
 * POST: text_id
 * Berechtigung: Ersteller ODER Admin
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$member_id = $_SESSION['member_id'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
session_write_close();

$data = json_decode(file_get_contents('php://input'), true);
$text_id = isset($data['text_id']) ? (int)$data['text_id'] : 0;

if ($text_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid text_id']);
    exit;
}

try {
    // Text-Info holen
    $stmt = $pdo->prepare("
        SELECT initiator_member_id, title, status
        FROM svcollab_texts
        WHERE text_id = ?
    ");
    $stmt->execute([$text_id]);
    $text = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$text) {
        http_response_code(404);
        echo json_encode(['error' => 'Text not found']);
        exit;
    }

    // Zugriffsprüfung: Nur Ersteller oder Admin
    $is_initiator = ($text['initiator_member_id'] == $member_id);

    if (!$is_initiator && !$is_admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied - Nur Ersteller oder Admin dürfen Texte löschen']);
        exit;
    }

    // Text und alle zugehörigen Daten löschen
    $pdo->beginTransaction();

    // 1. Locks löschen
    $pdo->exec("
        DELETE l FROM svcollab_text_locks l
        JOIN svcollab_text_paragraphs p ON l.paragraph_id = p.paragraph_id
        WHERE p.text_id = $text_id
    ");

    // 2. Absätze löschen
    $stmt = $pdo->prepare("DELETE FROM svcollab_text_paragraphs WHERE text_id = ?");
    $stmt->execute([$text_id]);

    // 3. Teilnehmer löschen
    $stmt = $pdo->prepare("DELETE FROM svcollab_text_participants WHERE text_id = ?");
    $stmt->execute([$text_id]);

    // 4. Versionen löschen
    $stmt = $pdo->prepare("DELETE FROM svcollab_text_versions WHERE text_id = ?");
    $stmt->execute([$text_id]);

    // 5. Text selbst löschen
    $stmt = $pdo->prepare("DELETE FROM svcollab_texts WHERE text_id = ?");
    $stmt->execute([$text_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Text "' . $text['title'] . '" wurde gelöscht'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("collab_text_delete Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete text']);
}
