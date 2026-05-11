<?php
/**
 * upload_agenda_attachment.php - Datei-Upload für Tagesordnungspunkte
 *
 * Empfängt Datei-Uploads via Drag & Drop und speichert sie mit dem TOP verknüpft
 */

session_start();
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Login prüfen
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$current_member_id = $_SESSION['member_id'];
$item_id = intval($_POST['item_id'] ?? 0);

// Validierung
if (!$item_id) {
    echo json_encode(['success' => false, 'error' => 'Ungültige TOP-ID']);
    exit;
}

// Berechtigung prüfen: User muss Teilnehmer sein, Admin sein, oder Einlader/Vorsitzender/Schriftführer
$current_user = get_member_by_id($pdo, $current_member_id);
$is_admin = ($current_user['is_admin'] == 1);

$stmt = $pdo->prepare("
    SELECT ai.item_id, ai.meeting_id, m.status, m.invited_by_member_id, m.chairman_member_id, m.secretary_member_id
    FROM svagenda_items ai
    JOIN svmeetings m ON ai.meeting_id = m.meeting_id
    WHERE ai.item_id = ?
");
$stmt->execute([$item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo json_encode(['success' => false, 'error' => 'TOP nicht gefunden']);
    exit;
}

// Berechtigung: Admin ODER Teilnehmer ODER Einlader/Vorsitzender/Schriftführer
$stmt_participant = $pdo->prepare("
    SELECT member_id FROM svmeeting_participants
    WHERE meeting_id = ? AND member_id = ?
");
$stmt_participant->execute([$item['meeting_id'], $current_member_id]);
$is_participant = ($stmt_participant->rowCount() > 0);

$is_meeting_role = in_array($current_member_id, [
    $item['invited_by_member_id'],
    $item['chairman_member_id'],
    $item['secretary_member_id']
]);

if (!$is_admin && !$is_participant && !$is_meeting_role) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

// Während preparation und active erlaubt
if (!in_array($item['status'], ['preparation', 'active'])) {
    echo json_encode(['success' => false, 'error' => 'Uploads nur während Vorbereitung und aktiver Sitzung erlaubt']);
    exit;
}

// Datei-Upload prüfen
if (!isset($_FILES['files'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Datei hochgeladen']);
    exit;
}

// Upload-Verzeichnis sicherstellen
$upload_dir = __DIR__ . '/uploads/agenda_attachments/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$uploaded_files = [];
$errors = [];

// Multiple Files verarbeiten
$files = $_FILES['files'];
$file_count = is_array($files['name']) ? count($files['name']) : 1;

for ($i = 0; $i < $file_count; $i++) {
    // Array-Handling
    $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
    $file_tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $file_size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
    $file_error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

    // Fehler prüfen
    if ($file_error !== UPLOAD_ERR_OK) {
        $errors[] = "$file_name: Upload-Fehler Code $file_error";
        continue;
    }

    // Dateigröße prüfen (max 10 MB)
    if ($file_size > 10 * 1024 * 1024) {
        $errors[] = "$file_name: Datei zu groß (max 10 MB)";
        continue;
    }

    // MIME-Type ermitteln
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);

    // Gefährliche Dateitypen blockieren
    $dangerous_types = ['application/x-php', 'application/x-httpd-php', 'application/x-sh', 'application/x-executable'];
    if (in_array($mime_type, $dangerous_types)) {
        $errors[] = "$file_name: Dateityp nicht erlaubt";
        continue;
    }

    // Sicherer Dateiname generieren
    $extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $safe_filename = uniqid('attach_') . '.' . $extension;
    $filepath = $upload_dir . $safe_filename;

    // Upload durchführen
    if (!move_uploaded_file($file_tmp, $filepath)) {
        $errors[] = "$file_name: Fehler beim Speichern";
        continue;
    }

    // In Datenbank eintragen
    try {
        $stmt = $pdo->prepare("
            INSERT INTO svagenda_attachments
            (item_id, filename, original_filename, filepath, filesize, filetype, uploaded_by_member_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $item_id,
            $safe_filename,
            $file_name,
            'uploads/agenda_attachments/' . $safe_filename,
            $file_size,
            $mime_type,
            $current_member_id
        ]);

        $uploaded_files[] = [
            'attachment_id' => $pdo->lastInsertId(),
            'filename' => $safe_filename,
            'original_filename' => $file_name,
            'filesize' => $file_size,
            'filetype' => $mime_type
        ];
    } catch (PDOException $e) {
        // Bei DB-Fehler: Datei löschen
        unlink($filepath);
        $errors[] = "$file_name: Datenbankfehler";
        error_log("Attachment DB Error: " . $e->getMessage());
    }
}

// Antwort
echo json_encode([
    'success' => count($uploaded_files) > 0,
    'uploaded' => $uploaded_files,
    'errors' => $errors
]);
