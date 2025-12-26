<?php
/**
 * PDF Download Handler
 * Faltblatt: öffentlich zugänglich
 * Einladungsbogen: nur für Admins
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';
require_once __DIR__ . '/../../src/PdfService.php';

Session::start();

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

// PDF-Pfad ermitteln
$path = null;
$filename = '';

switch ($type) {
    case 'faltblatt':
        // Faltblatt ist öffentlich zugänglich
        $path = $pdfService->getFaltblattPath($reiseId);
        $filename = 'Faltblatt_' . $reise['schiff'] . '.pdf';
        break;

    case 'einladung':
        // Einladungsbogen nur für Admins
        if (!Session::isLoggedIn()) {
            header('HTTP/1.0 403 Forbidden');
            exit('Keine Berechtigung');
        }
        $isAdmin = Session::isSuperuser() || $reiseModel->isReiseAdmin($reiseId, $_SESSION['user_id']);
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

// PDF generieren falls nicht vorhanden (nur für Admins)
if (!$path) {
    if (Session::isLoggedIn()) {
        $isAdmin = Session::isSuperuser() || $reiseModel->isReiseAdmin($reiseId, $_SESSION['user_id']);
        if ($isAdmin) {
            $pdfService->generateForReise($reise);
            $path = ($type === 'faltblatt')
                ? $pdfService->getFaltblattPath($reiseId)
                : $pdfService->getEinladungPath($reiseId);
        }
    }
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
