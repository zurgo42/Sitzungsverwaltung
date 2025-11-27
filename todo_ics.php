<?php
require_once 'functions.php';
$todo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM svtodos WHERE todo_id = ?");
$stmt->execute([$todo_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    header("HTTP/1.0 404 Not Found"); exit;
}
// ICS-Header
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename=todo_' . $todo_id . '.ics');

// ICS-Escaping
$title = str_replace(["\r\n", "\n", "\r"], ' ', $row['title'] ?? 'ToDo');
$title = str_replace(['\\', ',', ';'], ['\\\\', '\\,', '\\;'], $title);

$desc  = str_replace(["\r\n", "\n", "\r"], '\n', $row['description'] ?? '');
$desc = str_replace(['\\', ',', ';'], ['\\\\', '\\,', '\\;'], $desc);

// Datum für Ganztages-Event
$date = $row['due_date'] && $row['due_date'] !== '0000-00-00' 
    ? date('Ymd', strtotime($row['due_date'])) 
    : date('Ymd', strtotime('+7 days'));

echo "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sitzungsverwaltung//ToDo
BEGIN:VEVENT
UID:todo{$todo_id}@Sitzungsverwaltung
DTSTAMP:" . date('Ymd\THis\Z') . "
DTSTART;VALUE=DATE:{$date}
DTEND;VALUE=DATE:{$date}
SUMMARY:{$title}
DESCRIPTION:{$desc}
STATUS:" . ($row['status'] === 'done' ? 'CONFIRMED' : 'TENTATIVE') . "
TRANSP:TRANSPARENT
END:VEVENT
END:VCALENDAR";
?>