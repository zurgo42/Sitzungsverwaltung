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

// Berechtigung prüfen: User muss Teilnehmer der Sitzung sein
$stmt = $pdo->prepare("
    SELECT ai.item_id, ai.meeting_id, m.status
    FROM svagenda_items ai
    JOIN svmeetings m ON ai.meeting_id = m.meeting_id
    LEFT JOIN svmeeting_participants mp ON m.meeting_id = mp.meeting_id AND mp.member_id = ?
    WHERE ai.item_id = ?
    AND (mp.member_id IS NOT NULL OR m.visibility_type IN ('authenticated', 'public'))
");
$stmt->execute([$current_member_id, $item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung oder TOP nicht gefunden']);
    exit;
}

// Nur während preparation erlaubt
if ($item['status'] !== 'preparation') {
    echo json_encode(['success' => false, 'error' => 'Uploads nur während Vorbereitung erlaubt']);
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
