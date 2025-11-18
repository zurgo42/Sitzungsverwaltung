<?php
/**
 * download_document.php - Dokument-Download mit Tracking
 * Stellt Dokumente zum Download bereit und trackt Downloads
 */

session_start();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/documents_functions.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['member_id'])) {
    http_response_code(403);
    die('Bitte anmelden um Dokumente herunterzuladen');
}

$current_user = get_member_by_id($pdo, $_SESSION['member_id']);
if (!$current_user) {
    http_response_code(403);
    die('Benutzer nicht gefunden');
}

// Dokument-ID
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$document_id) {
    http_response_code(400);
    die('Ungültige Dokument-ID');
}

// Dokument laden
$doc = get_document_by_id($pdo, $document_id);
if (!$doc) {
    http_response_code(404);
    die('Dokument nicht gefunden');
}

// Zugriffsberechtigung prüfen
if (!has_document_access($doc, $current_user)) {
    http_response_code(403);
    die('Keine Berechtigung für dieses Dokument');
}

// Dateipfad
$filepath = __DIR__ . '/' . $doc['filepath'];

// Prüfen ob Datei existiert
if (!file_exists($filepath)) {
    http_response_code(404);
    die('Datei nicht gefunden auf Server');
}

// Download tracken
track_download($pdo, $document_id, $_SESSION['member_id']);

// Content-Type ermitteln
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Headers für Download setzen
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . $doc['original_filename'] . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Datei ausgeben
readfile($filepath);
exit;
?>
