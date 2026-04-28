<?php
/**
 * delete_agenda_attachment.php - Löschen von TOP-Attachments
 */

session_start();
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$current_member_id = $_SESSION['member_id'];
$attachment_id = intval($_POST['attachment_id'] ?? 0);

if (!$attachment_id) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Attachment-ID']);
    exit;
}

try {
    // Attachment laden + Berechtigung prüfen
    $stmt = $pdo->prepare("
        SELECT aa.*, ai.meeting_id, m.status
        FROM svagenda_attachments aa
        JOIN svagenda_items ai ON aa.item_id = ai.item_id
        JOIN svmeetings m ON ai.meeting_id = m.meeting_id
        WHERE aa.attachment_id = ?
    ");
    $stmt->execute([$attachment_id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        echo json_encode(['success' => false, 'error' => 'Attachment nicht gefunden']);
        exit;
    }

    // Nur Uploader oder Admin darf löschen
    $current_user = get_member_by_id($pdo, $current_member_id);
    $is_admin = ($current_user['is_admin'] ?? 0) == 1 || ($current_user['role'] ?? '') === 'assistenz';
    $is_uploader = ($attachment['uploaded_by_member_id'] == $current_member_id);

    if (!$is_uploader && !$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }

    // Nur während preparation und active
    if (!in_array($attachment['status'], ['preparation', 'active'])) {
        echo json_encode(['success' => false, 'error' => 'Löschen nur während Vorbereitung und aktiver Sitzung erlaubt']);
        exit;
    }

    // Datei löschen
    $filepath = __DIR__ . '/' . $attachment['filepath'];
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    // DB-Eintrag löschen
    $stmt = $pdo->prepare("DELETE FROM svagenda_attachments WHERE attachment_id = ?");
    $stmt->execute([$attachment_id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Delete Attachment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
