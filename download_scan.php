<?php
/**
 * download_scan.php - Geschützter Download für Antragsunterlagen aus Scans/
 *
 * Dateien liegen in ../Scans/ (eine Ebene über dem Webroot von Sitzungsverwaltung).
 * Nur eingeloggte User dürfen herunterladen.
 */

require_once 'session_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['member_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Zugriff verweigert – bitte einloggen.');
}

$raw = $_GET['f'] ?? '';

// Sicherheit: Nur Dateien aus Scans/ erlaubt, keine Pfad-Traversal
$filename = basename($raw); // Entfernt jedes Verzeichnis
if ($filename === '' || $filename === '.' || $filename === '..') {
    header('HTTP/1.1 400 Bad Request');
    exit('Ungültiger Dateiname.');
}

$filepath = __DIR__ . '/../Scans/' . $filename;
$realpath = realpath($filepath);
$scans_dir = realpath(__DIR__ . '/../Scans');

// Sicherstellen, dass die Datei wirklich im Scans-Verzeichnis liegt
if ($realpath === false || $scans_dir === false || strpos($realpath, $scans_dir . DIRECTORY_SEPARATOR) !== 0) {
    header('HTTP/1.1 404 Not Found');
    exit('Datei nicht gefunden.');
}

if (!is_file($realpath) || !is_readable($realpath)) {
    header('HTTP/1.1 404 Not Found');
    exit('Datei nicht gefunden.');
}

// MIME-Typ ermitteln
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime_types = [
    'pdf'  => 'application/pdf',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls'  => 'application/vnd.ms-excel',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'txt'  => 'text/plain',
    'csv'  => 'text/csv',
    'zip'  => 'application/zip',
];
$mime = $mime_types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
header('Content-Length: ' . filesize($realpath));
header('Cache-Control: private, max-age=3600');
readfile($realpath);
exit;
