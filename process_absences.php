<?php
/**
 * process_absences.php - Verarbeitung von Abwesenheits-Aktionen
 * CRUD-Operationen für Abwesenheiten
 */

session_start();

require_once 'config.php';           // Datenbankverbindung
require_once 'functions.php';
require_login();

$current_user = get_current_member();
$currentMemberID = $_SESSION['member_id'] ?? 0;

// Berechtigung prüfen: Nur Führungsteam
$leadership_roles = ['vorstand', 'gf', 'assistenz', 'fuehrungsteam', 'Vorstand', 'Geschäftsführung', 'Assistenz', 'Führungsteam'];
$is_leadership = in_array($current_user['role'], $leadership_roles);

if (!$is_leadership) {
    $_SESSION['error'] = 'Zugriff verweigert. Nur Führungsteam hat Zugriff auf diese Funktion.';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=absences');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Neue Abwesenheit erstellen
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            $substitute_member_id = !empty($_POST['substitute_member_id']) ? intval($_POST['substitute_member_id']) : null;

            // Validierung
            if (empty($start_date) || empty($end_date)) {
                throw new Exception('Bitte Start- und Enddatum angeben.');
            }

            if (strtotime($end_date) < strtotime($start_date)) {
                throw new Exception('Enddatum muss nach oder gleich Startdatum sein.');
            }

            // Einfügen
            $stmt = $pdo->prepare("
                INSERT INTO absences
                (member_id, start_date, end_date, reason, substitute_member_id, created_by_member_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $currentMemberID,
                $start_date,
                $end_date,
                $reason ?: null,
                $substitute_member_id,
                $currentMemberID
            ]);

            $_SESSION['success'] = 'Abwesenheit erfolgreich eingetragen.';
            break;

        case 'update':
            // Abwesenheit bearbeiten
            $absence_id = intval($_POST['absence_id'] ?? 0);
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            $substitute_member_id = !empty($_POST['substitute_member_id']) ? intval($_POST['substitute_member_id']) : null;

            // Validierung
            if ($absence_id <= 0) {
                throw new Exception('Ungültige Abwesenheits-ID.');
            }

            if (empty($start_date) || empty($end_date)) {
                throw new Exception('Bitte Start- und Enddatum angeben.');
            }

            if (strtotime($end_date) < strtotime($start_date)) {
                throw new Exception('Enddatum muss nach oder gleich Startdatum sein.');
            }

            // Prüfen ob es die eigene Abwesenheit ist
            $stmt = $pdo->prepare("SELECT member_id FROM absences WHERE absence_id = ?");
            $stmt->execute([$absence_id]);
            $absence = $stmt->fetch();

            if (!$absence || $absence['member_id'] != $currentMemberID) {
                throw new Exception('Sie können nur eigene Abwesenheiten bearbeiten.');
            }

            // Aktualisieren
            $stmt = $pdo->prepare("
                UPDATE absences
                SET start_date = ?, end_date = ?, reason = ?, substitute_member_id = ?
                WHERE absence_id = ? AND member_id = ?
            ");
            $stmt->execute([
                $start_date,
                $end_date,
                $reason ?: null,
                $substitute_member_id,
                $absence_id,
                $currentMemberID
            ]);

            $_SESSION['success'] = 'Abwesenheit erfolgreich aktualisiert.';
            break;

        case 'delete':
            // Abwesenheit löschen
            $absence_id = intval($_POST['absence_id'] ?? 0);

            if ($absence_id <= 0) {
                throw new Exception('Ungültige Abwesenheits-ID.');
            }

            // Prüfen ob es die eigene Abwesenheit ist
            $stmt = $pdo->prepare("SELECT member_id FROM absences WHERE absence_id = ?");
            $stmt->execute([$absence_id]);
            $absence = $stmt->fetch();

            if (!$absence || $absence['member_id'] != $currentMemberID) {
                throw new Exception('Sie können nur eigene Abwesenheiten löschen.');
            }

            // Löschen
            $stmt = $pdo->prepare("DELETE FROM absences WHERE absence_id = ? AND member_id = ?");
            $stmt->execute([$absence_id, $currentMemberID]);

            $_SESSION['success'] = 'Abwesenheit erfolgreich gelöscht.';
            break;

        default:
            throw new Exception('Ungültige Aktion.');
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    error_log("Absences Error: " . $e->getMessage());
}

// Zurück zur Übersicht
header('Location: index.php?tab=absences');
exit;
?>
