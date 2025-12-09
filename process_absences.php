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
    // Wenn Admin und member_id angegeben ist, dann für dieses Mitglied
    // Sonst für sich selbst
    if ($current_user['is_admin'] && !empty($_POST['absence_member_id'])) {
        $member_id = $_POST['absence_member_id'];
    } else {
        $member_id = $current_user['member_id'];
    }

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

            $redirect_tab = ($active_tab === 'vertretung' || $active_tab === 'admin') ? $active_tab : 'meetings';
            header('Location: index.php?tab=' . $redirect_tab . '&msg=absence_added');
            exit;
        } catch (PDOException $e) {
            error_log('Fehler beim Hinzufügen der Abwesenheit: ' . $e->getMessage());
            $redirect_tab = ($active_tab === 'vertretung' || $active_tab === 'admin') ? $active_tab : 'meetings';
            header('Location: index.php?tab=' . $redirect_tab . '&error=absence_failed');
            exit;
        }
    }
}

// Abwesenheit bearbeiten
if (isset($_POST['edit_absence'])) {
    $absence_id = $_POST['absence_id'] ?? null;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $substitute_id = !empty($_POST['substitute_member_id']) ? $_POST['substitute_member_id'] : null;
    $reason = $_POST['reason'] ?? null;

    if ($absence_id && $start_date && $end_date) {
        try {
            // Prüfen ob die Abwesenheit dem aktuellen User gehört ODER ob User Admin ist
            $stmt = $pdo->prepare("SELECT member_id FROM svabsences WHERE absence_id = ?");
            $stmt->execute([$absence_id]);
            $absence = $stmt->fetch();

            if ($absence && ($absence['member_id'] == $current_user['member_id'] || $current_user['is_admin'])) {
                // Admin kann auch das Mitglied ändern, normale User nicht
                if ($current_user['is_admin'] && !empty($_POST['absence_member_id'])) {
                    $stmt = $pdo->prepare("
                        UPDATE svabsences
                        SET member_id = ?, start_date = ?, end_date = ?, substitute_member_id = ?, reason = ?
                        WHERE absence_id = ?
                    ");
                    $stmt->execute([$_POST['absence_member_id'], $start_date, $end_date, $substitute_id, $reason, $absence_id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE svabsences
                        SET start_date = ?, end_date = ?, substitute_member_id = ?, reason = ?
                        WHERE absence_id = ?
                    ");
                    $stmt->execute([$start_date, $end_date, $substitute_id, $reason, $absence_id]);
                }

                $redirect_tab = ($active_tab === 'vertretung' || $active_tab === 'admin') ? $active_tab : 'meetings';
                header('Location: index.php?tab=' . $redirect_tab . '&msg=absence_updated');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Fehler beim Bearbeiten der Abwesenheit: ' . $e->getMessage());
            $redirect_tab = ($active_tab === 'vertretung' || $active_tab === 'admin') ? $active_tab : 'meetings';
            header('Location: index.php?tab=' . $redirect_tab . '&error=absence_update_failed');
            exit;
        }
    }
}

// Abwesenheit löschen
if (isset($_POST['delete_absence'])) {
    $absence_id = $_POST['absence_id'] ?? null;

    if ($absence_id) {
        try {
            // Prüfen ob die Abwesenheit dem aktuellen User gehört ODER ob User Admin ist
            $stmt = $pdo->prepare("SELECT member_id FROM svabsences WHERE absence_id = ?");
            $stmt->execute([$absence_id]);
            $absence = $stmt->fetch();

            if ($absence && ($absence['member_id'] == $current_user['member_id'] || $current_user['is_admin'])) {
                $stmt = $pdo->prepare("DELETE FROM svabsences WHERE absence_id = ?");
                $stmt->execute([$absence_id]);

                $redirect_tab = ($active_tab === 'vertretung' || $active_tab === 'admin') ? $active_tab : 'meetings';
                header('Location: index.php?tab=' . $redirect_tab . '&msg=absence_deleted');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Fehler beim Löschen der Abwesenheit: ' . $e->getMessage());
            $redirect_tab = ($active_tab === 'vertretung' || $active_tab === 'admin') ? $active_tab : 'meetings';
            header('Location: index.php?tab=' . $redirect_tab . '&error=absence_delete_failed');
            exit;
        }
    }
}
?>
