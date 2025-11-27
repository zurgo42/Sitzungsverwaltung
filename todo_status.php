<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php'; // gibt $pdo und ggf. session_start etc.

session_start();
$currentMemberID = $_SESSION['member_id'] ?? 0;
if (!$currentMemberID) {
    die('Nicht eingeloggt.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $todo_id = isset($_POST['todo_id']) ? (int)$_POST['todo_id'] : 0;
    $new_status = $_POST['new_status'] ?? '';

    // Status nur für Zuweisungsempfänger änderbar!
    $stmt = $pdo->prepare("SELECT status, assigned_to_member_id FROM svtodos WHERE todo_id = ?");
    $stmt->execute([$todo_id]);
    $todo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$todo) {
        die('ToDo nicht gefunden.');
    }
    if ($todo['assigned_to_member_id'] != $currentMemberID) {
        die('Sie dürfen diesen Status nicht ändern.');
    }

    // Status aktualisieren
    $allow = ['open','in progress','delayed','done'];
    if (!in_array($new_status, $allow)) {
        die('Ungültiger Status.');
    }

    // Status (und bei "done" auch completed_at) aktualisieren
    if ($new_status === "done") {
        $stmt = $pdo->prepare("UPDATE svtodos SET status=?, completed_at=NOW() WHERE todo_id=?");
        $stmt->execute([$new_status, $todo_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE svtodos SET status=?, completed_at=NULL WHERE todo_id=?");
        $stmt->execute([$new_status, $todo_id]);
    }

    // Logging machen (Tabelle: todo_log, s.o.)
    $logstmt = $pdo->prepare(
        "INSERT INTO svtodo_log (todo_id, changed_by, change_type, old_value, new_value)
         VALUES (?, ?, 'status-change', ?, ?)"
    );
    $logstmt->execute([
        $todo_id, $currentMemberID, $todo['status'], $new_status
    ]);

    // Nach Aktion zurück zur Übersicht
    header('Location: index.php?tab=todos');
    exit;
}
?>
