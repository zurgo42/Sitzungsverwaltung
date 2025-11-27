<?php
/**
 * process_todos.php - Verarbeitung von ToDo-Aktionen
 */

// ToDo als erledigt markieren
if (isset($_POST['complete_todo'])) {
    $todo_id = $_POST['todo_id'] ?? 0;
    
    if ($todo_id) {
        try {
            $stmt = $pdo->prepare("UPDATE svtodos SET completed_date = CURDATE() WHERE todo_id = ? AND assigned_to_member_id = ?");
            $stmt->execute([$todo_id, $current_user['member_id']]);
            
            header("Location: ?tab=todos");
            exit;
        } catch (PDOException $e) {
            $error = "Fehler beim Aktualisieren des ToDos";
        }
    }
}
?>