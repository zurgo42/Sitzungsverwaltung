<?php
require_once 'functions.php';
session_start();

$currentMemberID = $_SESSION['member_id'] ?? 0;
if (!$currentMemberID) {
    die('Nicht eingeloggt.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $todo_id = isset($_POST['todo_id']) ? (int)$_POST['todo_id'] : 0;

    // Prüfen, ob der aktuelle Nutzer Ersteller ist und der Status noch zurückziehbar
    $stmt = $pdo->prepare(
        "SELECT created_by_member_id, status, title FROM svtodos WHERE todo_id = ?"
    );
    $stmt->execute([$todo_id]);
    $todo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$todo) {
        die('ToDo nicht gefunden.');
    }
    if ($todo['created_by_member_id'] != $currentMemberID) {
        die('Sie dürfen dieses ToDo nicht zurückziehen.');
    }
    if (!in_array($todo['status'], ['open', 'in progress'])) {
        die('Nur offene oder in Bearbeitung befindliche ToDos können zurückgezogen werden.');
    }

    // ToDo löschen (oder Status setzen, falls du Soft-Delete bevorzugst)
    $delete = $pdo->prepare("DELETE FROM svtodos WHERE todo_id = ?");
    $delete->execute([$todo_id]);

    // Logging
    $log = $pdo->prepare(
        "INSERT INTO svtodo_log (todo_id, changed_by, change_type, old_value, new_value)
         VALUES (?, ?, 'todo-retract', ?, NULL)"
    );
    $log->execute([
        $todo_id, $currentMemberID, $todo['status']
    ]);

    // Nach Aktion zurück zur Übersicht
    header('Location: index.php?tab=todos');
    exit;
}
?>
