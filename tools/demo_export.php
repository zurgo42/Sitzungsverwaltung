<?php
/**
 * Demo-Export: Exportiert eine Demo-Sitzung
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Korrekter Pfad zu config.php (nicht db.php!)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// PDO-Verbindung aufbauen falls nicht vorhanden
if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
    }
}

// Prüfen ob User eingeloggt ist
if (!isset($_SESSION['member_id'])) {
    die("Bitte einloggen!");
}

$current_user = get_current_member($_SESSION['member_id']);
if (!$current_user || !$current_user['is_admin']) {
    die("Keine Berechtigung!");
}

// Meeting-ID aus GET
$meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;

if (!$meeting_id) {
    die("Keine Meeting-ID angegeben!");
}

// Meeting laden
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE meeting_id = ?");
$stmt->execute([$meeting_id]);
$meeting = $stmt->fetch();

if (!$meeting) {
    die("Meeting nicht gefunden!");
}

// Export-Array erstellen
$export = [
    'meeting' => $meeting,
    'participants' => [],
    'agenda_items' => [],
    'todos' => []
];

// Teilnehmer laden
$stmt = $pdo->prepare("
    SELECT mp.*, m.first_name, m.last_name, m.email
    FROM meeting_participants mp
    JOIN members m ON mp.member_id = m.member_id
    WHERE mp.meeting_id = ?
");
$stmt->execute([$meeting_id]);
$export['participants'] = $stmt->fetchAll();

// Agenda Items mit Kommentaren laden
$stmt = $pdo->prepare("SELECT * FROM agenda_items WHERE meeting_id = ? ORDER BY top_number");
$stmt->execute([$meeting_id]);
$items = $stmt->fetchAll();

foreach ($items as $item) {
    $item_id = $item['item_id'];

    // Kommentare laden
    $stmt = $pdo->prepare("
        SELECT ac.*, m.first_name, m.last_name
        FROM agenda_comments ac
        JOIN members m ON ac.member_id = m.member_id
        WHERE ac.item_id = ?
        ORDER BY ac.created_at
    ");
    $stmt->execute([$item_id]);
    $item['comments'] = $stmt->fetchAll();

    // Post-Kommentare laden
    $stmt = $pdo->prepare("
        SELECT apc.*, m.first_name, m.last_name
        FROM agenda_post_comments apc
        JOIN members m ON apc.member_id = m.member_id
        WHERE apc.item_id = ?
        ORDER BY apc.created_at
    ");
    $stmt->execute([$item_id]);
    $item['post_comments'] = $stmt->fetchAll();

    $export['agenda_items'][] = $item;
}

// TODOs laden
$stmt = $pdo->prepare("
    SELECT t.*, m.first_name, m.last_name
    FROM todos t
    JOIN members m ON t.assigned_to_member_id = m.member_id
    WHERE t.meeting_id = ?
");
$stmt->execute([$meeting_id]);
$export['todos'] = $stmt->fetchAll();

// Als JSON ausgeben
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="meeting_' . $meeting_id . '_export.json"');

echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>