<?php
/**
 * CSV-Export der Teilnehmerliste
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$reiseModel = new Reise($db);

$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('Location: ../reisen.php');
    exit;
}

$currentUser = $session->getUser();

// Berechtigung prüfen
$isAdmin = Session::isSuperuser();
if (!$isAdmin) {
    $admins = $reiseModel->getAdmins($reiseId);
    foreach ($admins as $admin) {
        if ($admin['user_id'] === $currentUser['user_id']) {
            $isAdmin = true;
            break;
        }
    }
}

if (!$isAdmin) {
    header('Location: ../dashboard.php');
    exit;
}

// Alle Teilnehmer mit Details laden
$teilnehmer = $db->fetchAll(
    "SELECT t.vorname, t.name, t.nickname, t.mobil, a.kabine, u.email, a.bemerkung
     FROM fan_anmeldungen a
     JOIN fan_users u ON a.user_id = u.user_id
     JOIN fan_teilnehmer t ON t.teilnehmer_id IN (
         a.teilnehmer1_id, a.teilnehmer2_id, a.teilnehmer3_id, a.teilnehmer4_id
     )
     WHERE a.reise_id = ?
     ORDER BY t.name, t.vorname",
    [$reiseId]
);

// Dateiname generieren
$schiffName = preg_replace('/[^a-zA-Z0-9]/', '', $reise['schiff']);
$datum = date('Y-m-d', strtotime($reise['anfang']));
$filename = "Teilnehmerliste_{$schiffName}_{$datum}.csv";

// CSV-Header senden
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// UTF-8 BOM für Excel
echo "\xEF\xBB\xBF";

// CSV-Ausgabe
$output = fopen('php://output', 'w');

// Kopfzeile
fputcsv($output, ['Vorname', 'Nachname', 'Nickname', 'Mobil', 'Kabine', 'E-Mail', 'Bemerkung'], ';');

// Daten
foreach ($teilnehmer as $t) {
    fputcsv($output, [
        $t['vorname'],
        $t['name'],
        $t['nickname'] ?? '',
        $t['mobil'] ?? '',
        $t['kabine'] ?? '',
        $t['email'],
        $t['bemerkung'] ?? ''
    ], ';');
}

fclose($output);
