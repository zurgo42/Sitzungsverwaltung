<?php
/**
 * poll_ics.php - ICS-Kalender-Export für finalisierte Umfragen
 * Erstellt: 17.11.2025
 *
 * Generiert eine .ics-Datei für den finalen Termin einer Umfrage
 */

require_once 'functions.php';

// Poll-ID aus URL holen
$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Umfrage laden
$stmt = $pdo->prepare("
    SELECT p.*, pd.suggested_date, pd.suggested_end_date
    FROM polls p
    LEFT JOIN poll_dates pd ON p.final_date_id = pd.date_id
    WHERE p.poll_id = ? AND p.status = 'finalized'
");
$stmt->execute([$poll_id]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poll || empty($poll['suggested_date'])) {
    header("HTTP/1.0 404 Not Found");
    die("Umfrage nicht gefunden oder noch nicht finalisiert");
}

// ICS-Header
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename=termin_' . $poll_id . '.ics');

// ICS-Escaping
$title = str_replace(["\r\n", "\n", "\r"], ' ', $poll['title'] ?? 'Termin');
$title = str_replace(['\\', ',', ';'], ['\\\\', '\\,', '\\;'], $title);

$desc = str_replace(["\r\n", "\n", "\r"], '\n', $poll['description'] ?? '');
$desc = str_replace(['\\', ',', ';'], ['\\\\', '\\,', '\\;'], $desc);

// Ort escapieren
$location = '';
if (!empty($poll['location'])) {
    $location = str_replace(["\r\n", "\n", "\r"], ' ', $poll['location']);
    $location = str_replace(['\\', ',', ';'], ['\\\\', '\\,', '\\;'], $location);
}

// Zeitstempel für Event
$start_date = new DateTime($poll['suggested_date']);
$end_date = !empty($poll['suggested_end_date'])
    ? new DateTime($poll['suggested_end_date'])
    : (clone $start_date)->modify('+' . ($poll['duration'] ?? 60) . ' minutes');

// Format für ICS: YYYYMMDDTHHMMSS
$dtstart = $start_date->format('Ymd\THis');
$dtend = $end_date->format('Ymd\THis');
$dtstamp = gmdate('Ymd\THis\Z');

// URL falls vorhanden
$url = '';
if (!empty($poll['video_link'])) {
    $url = "URL:" . str_replace(['\\', ',', ';'], ['\\\\', '\\,', '\\;'], $poll['video_link']) . "\n";
}

// ICS-Datei ausgeben
echo "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sitzungsverwaltung//Terminplanung
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:poll{$poll_id}@Sitzungsverwaltung
DTSTAMP:{$dtstamp}
DTSTART:{$dtstart}
DTEND:{$dtend}
SUMMARY:{$title}
DESCRIPTION:{$desc}
";

if ($location) {
    echo "LOCATION:{$location}\n";
}

if ($url) {
    echo $url;
}

echo "STATUS:CONFIRMED
TRANSP:OPAQUE
END:VEVENT
END:VCALENDAR";
?>
