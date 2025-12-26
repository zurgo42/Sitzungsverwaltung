<?php
/**
 * PDF Download Handler
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';
require_once __DIR__ . '/../../src/PdfService.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$reiseModel = new Reise($db);
$pdfService = new PdfService();

$reiseId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? '';

$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('HTTP/1.0 404 Not Found');
    exit('Reise nicht gefunden');
}

// Berechtigung prüfen (Admin oder Teilnehmer der Reise)
$currentUser = $session->getUser();
$isAdmin = Session::isSuperuser() || $reiseModel->isReiseAdmin($reiseId, $currentUser['user_id']);

// Teilnehmer dürfen nur das Faltblatt herunterladen
$isParticipant = $db->fetchColumn(
    "SELECT COUNT(*) FROM fan_anmeldungen WHERE reise_id = ? AND user_id = ?",
    [$reiseId, $currentUser['user_id']]
) > 0;

if (!$isAdmin && !$isParticipant) {
    header('HTTP/1.0 403 Forbidden');
    exit('Keine Berechtigung');
}

// PDF-Pfad ermitteln
$path = null;
$filename = '';

switch ($type) {
    case 'faltblatt':
        $path = $pdfService->getFaltblattPath($reiseId);
        $filename = 'Faltblatt_' . $reise['schiff'] . '.pdf';
        break;

    case 'einladung':
        // Nur Admins dürfen den Einladungsbogen herunterladen
        if (!$isAdmin) {
            header('HTTP/1.0 403 Forbidden');
            exit('Keine Berechtigung');
        }
        $path = $pdfService->getEinladungPath($reiseId);
        $filename = 'Einladung_' . $reise['schiff'] . '.pdf';
        break;

    default:
        header('HTTP/1.0 400 Bad Request');
        exit('Ungültiger Typ');
}

// PDF generieren falls nicht vorhanden
if (!$path) {
    $pdfService->generateForReise($reise);
    $path = ($type === 'faltblatt')
        ? $pdfService->getFaltblattPath($reiseId)
        : $pdfService->getEinladungPath($reiseId);
}

if (!$path || !file_exists($path)) {
    header('HTTP/1.0 404 Not Found');
    exit('PDF nicht gefunden');
}

// PDF ausliefern
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($path);
exit;
