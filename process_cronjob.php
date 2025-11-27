<?php

// Skript für den CronJob

require_once 'functions.php'; // Hier wird das $pdo-Objekt bereitgestellt
//echo getcwd();



$currentMemberID = $_SESSION['member_id'] ?? 0;


// Hilfsfunktion für Mailversand
function sendReminderMail($to, $recipientName, $todoTitle, $dueDate) {
    global $mailFrom;
    $subject = "Erinnerung: ToDo '{$todoTitle}' fällig am {$dueDate}";
    $body = "Hallo {$recipientName},\n\n"
          . "dies ist eine automatische Erinnerung: Das ToDo '{$todoTitle}' ist bis zum {$dueDate} zu erledigen.\n\n"
          . "Bitte prüfe deine Aufgabenliste im Mitgliederbereich.\n\nViele Grüße\nDas Team";
    $headers = "From: $mailFrom\r\n";
    @mail($to, $subject, $body, $headers);
}

// 1. Erinnerung verschicken (nur für morgige ToDos, Status open)
$tomorrow = (new DateTime('+1 day'))->format('Y-m-d');
$sql = "SELECT t.todo_id, t.title, t.due_date, t.status,
               m.email, m.first_name, m.last_name
        FROM svtodos t
        JOIN svmembers m ON t.assigned_to_member_id = m.member_id
        WHERE t.status = 'open' AND t.due_date = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$tomorrow]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $email = $row['email'];
    $name = $row['first_name'] . ' ' . $row['last_name'];
    $todoTitle = $row['title'];
    $dueDate = date('d.m.Y', strtotime($row['due_date']));
    sendReminderMail($email, $name, $todoTitle, $dueDate);
}

// 2. Erledigte ToDos nach 14 Tagen löschen
$deleteDate = (new DateTime('-14 days'))->format('Y-m-d');
$sql = "DELETE FROM svtodos WHERE status = 'closed' AND due_date <= ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$deleteDate]);

// Optional: Ausgabe ins Log (nur für dich zum Testen)
// echo "Cronjob fertig: Erinnerungen geschickt und alte ToDos gelöscht.\n";
?>
