<?php
/**
 * process_todos.php - ToDo-Aktionen verarbeiten
 * Wird in index.php VOR HTML-Output eingebunden
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return; // Nur POST-Requests verarbeiten
}

$currentMemberID = $_SESSION['member_id'] ?? 0;

if (!$currentMemberID) {
    $_SESSION['error'] = 'Bitte melde dich an';
    header('Location: index.php');
    exit;
}

// STATUS ÄNDERN
if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $todo_id = (int)($_POST['todo_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    $allowed_statuses = ['open', 'in progress', 'delayed', 'done'];
    if (!$todo_id || !in_array($new_status, $allowed_statuses)) {
        $_SESSION['error'] = 'Ungültige Eingabe';
        header('Location: index.php?tab=todos');
        exit;
    }

    // Berechtigung prüfen (nur Empfänger)
    $stmt = $pdo->prepare("SELECT status, assigned_to_member_id FROM svtodos WHERE todo_id = ?");
    $stmt->execute([$todo_id]);
    $todo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$todo || $todo['assigned_to_member_id'] != $currentMemberID) {
        $_SESSION['error'] = 'Keine Berechtigung';
        header('Location: index.php?tab=todos');
        exit;
    }

    try {
        if ($new_status === 'done') {
            $stmt = $pdo->prepare("UPDATE svtodos SET status = ?, completed_at = NOW() WHERE todo_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE svtodos SET status = ?, completed_at = NULL WHERE todo_id = ?");
        }
        $stmt->execute([$new_status, $todo_id]);

        // Logging
        $logstmt = $pdo->prepare("INSERT INTO svtodo_log (todo_id, changed_by, change_type, old_value, new_value) VALUES (?, ?, 'status-change', ?, ?)");
        $logstmt->execute([$todo_id, $currentMemberID, $todo['status'], $new_status]);

        $_SESSION['success'] = 'Status geändert';
        header('Location: index.php?tab=todos');
        exit;
    } catch (PDOException $e) {
        error_log('Todo Status Error: ' . $e->getMessage());
        $_SESSION['error'] = 'Fehler beim Aktualisieren';
        header('Location: index.php?tab=todos');
        exit;
    }
}

// TODO ZURÜCKZIEHEN
if (isset($_POST['action']) && $_POST['action'] === 'retract') {
    $todo_id = (int)($_POST['todo_id'] ?? 0);

    if (!$todo_id) {
        $_SESSION['error'] = 'Ungültige Aufgaben-ID';
        header('Location: index.php?tab=todos');
        exit;
    }

    $stmt = $pdo->prepare("SELECT created_by_member_id, status, title FROM svtodos WHERE todo_id = ?");
    $stmt->execute([$todo_id]);
    $todo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$todo || $todo['created_by_member_id'] != $currentMemberID) {
        $_SESSION['error'] = 'Keine Berechtigung';
        header('Location: index.php?tab=todos');
        exit;
    }

    if (!in_array($todo['status'], ['open', 'in progress'])) {
        $_SESSION['error'] = 'Nur offene Aufgaben können zurückgezogen werden';
        header('Location: index.php?tab=todos');
        exit;
    }

    try {
        // Logging (vor dem Löschen!)
        $log = $pdo->prepare("INSERT INTO svtodo_log (todo_id, changed_by, change_type, old_value, new_value) VALUES (?, ?, 'aufgabe-zurueckziehen', ?, NULL)");
        $log->execute([$todo_id, $currentMemberID, $todo['status']]);

        $delete = $pdo->prepare("DELETE FROM svtodos WHERE todo_id = ?");
        $delete->execute([$todo_id]);

        $_SESSION['success'] = 'ToDo zurückgezogen';
        header('Location: index.php?tab=todos');
        exit;
    } catch (PDOException $e) {
        error_log('Todo Retract Error: ' . $e->getMessage());
        $_SESSION['error'] = 'Fehler beim Zurückziehen';
        header('Location: index.php?tab=todos');
        exit;
    }
}

// NEUES TODO ERSTELLEN
if (isset($_POST['action']) && $_POST['action'] === 'create_todo') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $assigned_to = (int)($_POST['assigned_to_member_id'] ?? 0);
    $is_private = isset($_POST['is_private']) ? 1 : 0;

    // Validierung
    if (empty($title)) {
        $_SESSION['error'] = 'Titel ist erforderlich';
        header('Location: index.php?tab=todos');
        exit;
    }

    // Berechtigung prüfen
    $is_admin = ($current_user['role'] ?? '') === 'assistenz' || ($current_user['is_admin'] ?? 0) == 1;

    // Wenn kein Empfänger angegeben oder User ist kein Admin -> sich selbst zuweisen
    if (!$assigned_to || !$is_admin) {
        $assigned_to = $currentMemberID;
    }

    // Due date validieren
    if (!empty($due_date)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $due_date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $due_date) {
            $_SESSION['error'] = 'Ungültiges Datum';
            header('Location: index.php?tab=todos');
            exit;
        }
    } else {
        $due_date = null;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO svtodos (
                title, description, assigned_to_member_id, created_by_member_id,
                due_date, status, is_private, entry_date
            ) VALUES (?, ?, ?, ?, ?, 'open', ?, NOW())
        ");
        $stmt->execute([
            $title,
            $description,
            $assigned_to,
            $currentMemberID,
            $due_date,
            $is_private
        ]);

        $todo_id = $pdo->lastInsertId();

        // Logging
        $log = $pdo->prepare("INSERT INTO svtodo_log (todo_id, changed_by, change_type, old_value, new_value) VALUES (?, ?, 'todo-erstellt', NULL, ?)");
        $log->execute([$todo_id, $currentMemberID, $title]);

        $_SESSION['success'] = 'ToDo erfolgreich erstellt';
        header('Location: index.php?tab=todos');
        exit;
    } catch (PDOException $e) {
        error_log('Todo Create Error: ' . $e->getMessage());
        $_SESSION['error'] = 'Fehler beim Erstellen des ToDos';
        header('Location: index.php?tab=todos');
        exit;
    }
}
?>
