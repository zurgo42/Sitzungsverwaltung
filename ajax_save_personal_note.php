<?php
/**
 * ajax_save_personal_note.php - AJAX-Handler für Autosave persönlicher Notizen
 * Erstellt: 2026-05-09
 *
 * Speichert persönliche Notizen zu Agenda Items automatisch
 */

session_start();
require_once 'functions.php';
require_once 'personal_notes_functions.php';

// JSON-Header
header('Content-Type: application/json');

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// User muss eingeloggt sein
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$member_id = intval($_SESSION['member_id']);

// Parameter validieren
$item_id = intval($_POST['item_id'] ?? 0);
$note_text = $_POST['note_text'] ?? '';

if (!$item_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid item_id']);
    exit;
}

// Notiz speichern (auch leere Texte erlaubt für Löschung)
$success = save_personal_note($pdo, $item_id, $member_id, $note_text);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Notiz gespeichert',
        'timestamp' => date('H:i:s')
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Speichern'
    ]);
}
