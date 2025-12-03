<?php
/**
 * API: Holt eine bestimmte Version eines kollaborativen Textes
 * GET Parameter: text_id, version
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

$text_id = isset($_GET['text_id']) ? (int)$_GET['text_id'] : 0;
$version_number = isset($_GET['version']) ? (int)$_GET['version'] : 0;

if ($text_id <= 0 || $version_number <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// Zugriffsprüfung
$stmt = $pdo->prepare("SELECT meeting_id FROM svcollab_texts WHERE text_id = ?");
$stmt->execute([$text_id]);
$text = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$text) {
    http_response_code(404);
    echo json_encode(['error' => 'Text not found']);
    exit;
}

// Zugriff prüfen
if ($text['meeting_id']) {
    // Meeting-basierter Text
    if (!hasCollabTextAccess($pdo, $text_id, $_SESSION['member_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
} else {
    // Allgemeiner Text: Nur Vorstand, GF, Assistenz
    $stmt = $pdo->prepare("SELECT role FROM svmembers WHERE member_id = ?");
    $stmt->execute([$_SESSION['member_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !in_array($user['role'], ['vorstand', 'gf', 'assistenz'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
}

// Version laden
$version = getTextVersion($pdo, $text_id, $version_number);

if (!$version) {
    http_response_code(404);
    echo json_encode(['error' => 'Version not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'version' => $version
]);
