<?php
/**
 * documents_functions.php - Hilfsfunktionen für Dokumentenverwaltung
 * Erstellt: 18.11.2025
 *
 * Stellt Funktionen bereit für:
 * - Dokumente hochladen
 * - Dokumente verwalten
 * - Zugriffskontrolle
 * - Kategorisierung
 */

/**
 * Kategorien für Dokumente
 */
function get_document_categories() {
    return [
        'satzung' => 'Satzung',
        'ordnungen' => 'Ordnungen',
        'richtlinien' => 'Richtlinien',
        'formulare' => 'Formulare',
        'mv_unterlagen' => 'MV-Unterlagen',
        'dokumentationen' => 'Dokumentationen',
        'urteile' => 'Urteile etc.',
        'medien' => 'Medien',
        'sonstige' => 'Sonstige'
    ];
}

/**
 * Erlaubte Dateitypen
 */
function get_allowed_file_types() {
    return [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'rtf' => 'application/rtf',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
}

/**
 * Prüft ob Dateiendung erlaubt ist
 */
function is_allowed_file_type($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return array_key_exists($ext, get_allowed_file_types());
}

/**
 * Holt alle Dokumente mit Filter
 *
 * @param PDO $pdo Datenbankverbindung
 * @param array $filters Optionale Filter
 * @param int $member_access_level Zugriffslevel des Mitglieds
 * @return array Dokumente
 */
function get_documents($pdo, $filters = [], $member_access_level = 0) {
    $where = ['d.access_level <= ?'];
    $params = [$member_access_level];

    // Status-Filter
    if (isset($filters['status'])) {
        $where[] = 'd.status = ?';
        $params[] = $filters['status'];
    } else {
        // Standardmäßig nur aktive Dokumente
        $where[] = 'd.status = ?';
        $params[] = 'active';
    }

    // Kategorie-Filter
    if (!empty($filters['category'])) {
        $where[] = 'd.category = ?';
        $params[] = $filters['category'];
    }

    // Suchbegriff
    if (!empty($filters['search'])) {
        $where[] = '(d.title LIKE ? OR d.description LIKE ? OR d.keywords LIKE ?)';
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Sortierung
    $order = 'd.created_at DESC';
    if (isset($filters['sort'])) {
        switch ($filters['sort']) {
            case 'title':
                $order = 'd.title ASC';
                break;
            case 'category':
                $order = 'd.category ASC, d.created_at DESC';
                break;
            case 'date_asc':
                $order = 'd.created_at ASC';
                break;
            case 'date_desc':
            default:
                $order = 'd.created_at DESC';
                break;
        }
    }

    $sql = "
        SELECT
            d.*,
            m.first_name,
            m.last_name,
            m.email as uploader_email
        FROM svdocuments d
        LEFT JOIN svmembers m ON d.uploaded_by_member_id = m.member_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $order
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Holt ein einzelnes Dokument
 */
function get_document_by_id($pdo, $document_id) {
    $stmt = $pdo->prepare("
        SELECT
            d.*,
            m.first_name,
            m.last_name,
            m.email as uploader_email
        FROM svdocuments d
        LEFT JOIN svmembers m ON d.uploaded_by_member_id = m.member_id
        WHERE d.document_id = ?
    ");
    $stmt->execute([$document_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Upload eines Dokuments
 *
 * @param PDO $pdo Datenbankverbindung
 * @param array $file $_FILES Array-Element
 * @param array $data Metadaten
 * @param int $member_id ID des hochladenden Mitglieds
 * @return array ['success' => bool, 'message' => string, 'document_id' => int]
 */
function upload_document($pdo, $file, $data, $member_id) {
    // Validierung
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Keine Datei ausgewählt'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload-Fehler: ' . $file['error']];
    }

    // Dateiname prüfen
    $original_filename = basename($file['name']);
    if (!is_allowed_file_type($original_filename)) {
        return ['success' => false, 'message' => 'Dateityp nicht erlaubt'];
    }

    // Upload-Verzeichnis prüfen/erstellen
    $upload_dir = __DIR__ . '/uploads/documents';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Upload-Verzeichnis konnte nicht erstellt werden'];
        }
    }

    // Eindeutigen Dateinamen generieren
    $ext = pathinfo($original_filename, PATHINFO_EXTENSION);
    $filename = uniqid('doc_' . date('Ymd') . '_') . '.' . $ext;
    $filepath = $upload_dir . '/' . $filename;

    // Datei verschieben
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Datei konnte nicht gespeichert werden'];
    }

    // In Datenbank eintragen
    try {
        $stmt = $pdo->prepare("
            INSERT INTO svdocuments (
                filename,
                original_filename,
                filepath,
                filesize,
                filetype,
                title,
                description,
                keywords,
                version,
                short_url,
                category,
                access_level,
                status,
                uploaded_by_member_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $filename,
            $original_filename,
            'uploads/documents/' . $filename,
            filesize($filepath),
            $ext,
            $data['title'] ?? $original_filename,
            $data['description'] ?? '',
            $data['keywords'] ?? '',
            $data['version'] ?? '',
            $data['short_url'] ?? '',
            $data['category'] ?? 'sonstige',
            $data['access_level'] ?? 0,
            'active',
            $member_id
        ]);

        return [
            'success' => true,
            'message' => 'Dokument erfolgreich hochgeladen',
            'document_id' => $pdo->lastInsertId()
        ];

    } catch (PDOException $e) {
        // Bei Fehler: Datei wieder löschen
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        return ['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()];
    }
}

/**
 * Aktualisiert ein Dokument
 */
function update_document($pdo, $document_id, $data, $member_id = null) {
    // Prüfen ob Dokument existiert
    $doc = get_document_by_id($pdo, $document_id);
    if (!$doc) {
        return ['success' => false, 'message' => 'Dokument nicht gefunden'];
    }

    // Update vorbereiten
    $fields = [];
    $params = [];

    if (isset($data['title'])) {
        $fields[] = 'title = ?';
        $params[] = $data['title'];
    }

    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $params[] = $data['description'];
    }

    if (isset($data['keywords'])) {
        $fields[] = 'keywords = ?';
        $params[] = $data['keywords'];
    }

    if (isset($data['version'])) {
        $fields[] = 'version = ?';
        $params[] = $data['version'];
    }

    if (isset($data['short_url'])) {
        $fields[] = 'short_url = ?';
        $params[] = $data['short_url'];
    }

    if (isset($data['category'])) {
        $fields[] = 'category = ?';
        $params[] = $data['category'];
    }

    if (isset($data['access_level'])) {
        $fields[] = 'access_level = ?';
        $params[] = $data['access_level'];
    }

    if (isset($data['status'])) {
        $fields[] = 'status = ?';
        $params[] = $data['status'];
    }

    if (isset($data['admin_notes'])) {
        $fields[] = 'admin_notes = ?';
        $params[] = $data['admin_notes'];
    }

    // Externe URL (kann auch NULL sein zum Zurücksetzen)
    if (array_key_exists('external_url', $data)) {
        $fields[] = 'external_url = ?';
        $params[] = $data['external_url'];
    }

    if (empty($fields)) {
        return ['success' => false, 'message' => 'Keine Änderungen'];
    }

    $fields[] = 'updated_at = NOW()';
    $params[] = $document_id;

    try {
        $sql = "UPDATE svdocuments SET " . implode(', ', $fields) . " WHERE document_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return ['success' => true, 'message' => 'Dokument aktualisiert'];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()];
    }
}

/**
 * Löscht ein Dokument (verschiebt in Archiv oder löscht physisch)
 */
function delete_document($pdo, $document_id, $permanent = false) {
    $doc = get_document_by_id($pdo, $document_id);
    if (!$doc) {
        return ['success' => false, 'message' => 'Dokument nicht gefunden'];
    }

    if ($permanent) {
        // Physisch löschen
        try {
            // Datei löschen
            $filepath = __DIR__ . '/' . $doc['filepath'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // DB-Eintrag löschen
            $stmt = $pdo->prepare("DELETE FROM svdocuments WHERE document_id = ?");
            $stmt->execute([$document_id]);

            return ['success' => true, 'message' => 'Dokument permanent gelöscht'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }

    } else {
        // Nur Status auf 'hidden' setzen
        return update_document($pdo, $document_id, ['status' => 'hidden']);
    }
}

/**
 * Formatiert Dateigröße lesbar
 */
function format_filesize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    } else {
        return $bytes . ' Bytes';
    }
}

/**
 * Trackt einen Download
 */
function track_download($pdo, $document_id, $member_id = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO svdocument_downloads (document_id, member_id, downloaded_at, ip_address)
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $document_id,
            $member_id,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Download-Tracking fehlgeschlagen: " . $e->getMessage());
        return false;
    }
}

/**
 * Holt Download-Statistiken für ein Dokument
 */
function get_document_download_stats($pdo, $document_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as download_count
        FROM svdocument_downloads
        WHERE document_id = ?
    ");
    $stmt->execute([$document_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['download_count'] ?? 0;
}

/**
 * Prüft ob Mitglied Zugriff auf Dokument hat
 */
function has_document_access($document, $member) {
    // Admin hat immer Zugriff
    if (is_admin_user($member)) {
        return true;
    }

    // Zugriffslevel prüfen
    $member_level = get_member_access_level($member);
    return $member_level >= $document['access_level'];
}

/**
 * Erstellt einen Link zu einem externen Dokument
 *
 * Statt eine Datei hochzuladen, wird nur ein Link zur externen Quelle gespeichert
 * (z.B. Cloud-Speicher, SharePoint, etc.) - vermeidet doppelte Datenhaltung
 *
 * @param PDO $pdo Datenbankverbindung
 * @param array $data Dokument-Metadaten (inkl. external_url)
 * @param int $member_id Hochladender User
 * @return array ['success' => bool, 'message' => string]
 */
function create_external_document_link($pdo, $data, $member_id) {
    try {
        // Dateiname aus URL extrahieren (oder "Externes Dokument" als Fallback)
        $url_parts = parse_url($data['external_url']);
        $filename = basename($url_parts['path'] ?? 'external-document');

        // Dateityp aus URL erraten
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'link'; // Fallback
        }

        $stmt = $pdo->prepare("
            INSERT INTO svdocuments (
                filename, original_filename, filepath, filesize, filetype,
                external_url,
                title, description, keywords, version, short_url,
                category, access_level, status,
                uploaded_by_member_id, created_at
            ) VALUES (
                ?, ?, '', 0, ?,
                ?,
                ?, ?, ?, ?, ?,
                ?, ?, 'active',
                ?, NOW()
            )
        ");

        $stmt->execute([
            $filename,                          // filename
            $filename,                          // original_filename
            strtolower($extension),             // filetype
            $data['external_url'],              // external_url (NEU!)
            $data['title'],
            $data['description'] ?? '',
            $data['keywords'] ?? '',
            $data['version'] ?? '',
            $data['short_url'] ?? '',
            $data['category'] ?? 'sonstige',
            $data['access_level'] ?? 0,
            $member_id
        ]);

        return [
            'success' => true,
            'message' => 'Externer Link erfolgreich hinzugefügt',
            'document_id' => $pdo->lastInsertId()
        ];

    } catch (Exception $e) {
        error_log("Fehler beim Erstellen des externen Links: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Fehler beim Speichern: ' . $e->getMessage()
        ];
    }
}
?>
