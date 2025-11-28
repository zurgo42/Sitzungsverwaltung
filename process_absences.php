<?php
/**
 * process_absences.php - Verarbeitung von Abwesenheiten
 */

// Nur ausführen, wenn POST-Request vorliegt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

// Neue Abwesenheit hinzufügen
if (isset($_POST['add_absence'])) {
    $member_id = $current_user['member_id'];
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $substitute_id = !empty($_POST['substitute_member_id']) ? $_POST['substitute_member_id'] : null;
    $reason = $_POST['reason'] ?? null;

    if ($start_date && $end_date) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO svabsences (member_id, start_date, end_date, substitute_member_id, reason)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$member_id, $start_date, $end_date, $substitute_id, $reason]);

            header('Location: index.php?tab=meetings&msg=absence_added');
            exit;
        } catch (PDOException $e) {
            error_log('Fehler beim Hinzufügen der Abwesenheit: ' . $e->getMessage());
            header('Location: index.php?tab=meetings&error=absence_failed');
            exit;
        }
    }
}

// Abwesenheit löschen
if (isset($_POST['delete_absence'])) {
    $absence_id = $_POST['absence_id'] ?? null;

    if ($absence_id) {
        try {
            // Prüfen ob die Abwesenheit dem aktuellen User gehört
            $stmt = $pdo->prepare("SELECT member_id FROM svabsences WHERE absence_id = ?");
            $stmt->execute([$absence_id]);
            $absence = $stmt->fetch();

            if ($absence && $absence['member_id'] == $current_user['member_id']) {
                $stmt = $pdo->prepare("DELETE FROM svabsences WHERE absence_id = ?");
                $stmt->execute([$absence_id]);

                header('Location: index.php?tab=meetings&msg=absence_deleted');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Fehler beim Löschen der Abwesenheit: ' . $e->getMessage());
            header('Location: index.php?tab=meetings&error=absence_delete_failed');
            exit;
        }
    }
}
?>
