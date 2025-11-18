<?php
/**
 * process_documents.php - Backend-Handler für Dokumentenverwaltung
 * Verarbeitet POST-Requests für Dokumente
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Nur POST-Requests erlaubt');
}

require_once __DIR__ . '/documents_functions.php';

// Session und User laden
if (!isset($_SESSION['member_id'])) {
    $_SESSION['error'] = 'Bitte anmelden um Dokumente zu verwalten';
    header('Location: index.php?tab=documents');
    exit;
}

$current_user = get_member_by_id($pdo, $_SESSION['member_id']);
if (!$current_user) {
    $_SESSION['error'] = 'Benutzer nicht gefunden';
    header('Location: index.php');
    exit;
}

// Admin-Rechte prüfen
$is_admin = is_admin_user($current_user);

// ============================================
// DOKUMENT HOCHLADEN
// ============================================

if (isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!$is_admin) {
        $_SESSION['error'] = 'Keine Berechtigung zum Hochladen';
        header('Location: index.php?tab=documents');
        exit;
    }

    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = 'Bitte wählen Sie eine Datei aus';
        header('Location: index.php?tab=documents&view=upload');
        exit;
    }

    $data = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'keywords' => $_POST['keywords'] ?? '',
        'version' => $_POST['version'] ?? '',
        'short_url' => $_POST['short_url'] ?? '',
        'category' => $_POST['category'] ?? 'sonstige',
        'access_level' => intval($_POST['access_level'] ?? 0)
    ];

    // Validierung
    if (empty($data['title'])) {
        $_SESSION['error'] = 'Titel ist erforderlich';
        header('Location: index.php?tab=documents&view=upload');
        exit;
    }

    $result = upload_document($pdo, $_FILES['document_file'], $data, $_SESSION['member_id']);

    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
        header('Location: index.php?tab=documents');
    } else {
        $_SESSION['error'] = $result['message'];
        header('Location: index.php?tab=documents&view=upload');
    }
    exit;
}

// ============================================
// DOKUMENT AKTUALISIEREN
// ============================================

if (isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!$is_admin) {
        $_SESSION['error'] = 'Keine Berechtigung zum Bearbeiten';
        header('Location: index.php?tab=documents');
        exit;
    }

    $document_id = intval($_POST['document_id'] ?? 0);
    if (!$document_id) {
        $_SESSION['error'] = 'Ungültige Dokument-ID';
        header('Location: index.php?tab=documents');
        exit;
    }

    $data = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'keywords' => $_POST['keywords'] ?? '',
        'version' => $_POST['version'] ?? '',
        'short_url' => $_POST['short_url'] ?? '',
        'category' => $_POST['category'] ?? 'sonstige',
        'access_level' => intval($_POST['access_level'] ?? 0),
        'admin_notes' => $_POST['admin_notes'] ?? ''
    ];

    $result = update_document($pdo, $document_id, $data, $_SESSION['member_id']);

    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }

    header('Location: index.php?tab=documents&view=edit&id=' . $document_id);
    exit;
}

// ============================================
// DOKUMENT STATUS ÄNDERN
// ============================================

if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
    if (!$is_admin) {
        $_SESSION['error'] = 'Keine Berechtigung';
        header('Location: index.php?tab=documents');
        exit;
    }

    $document_id = intval($_POST['document_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if (!$document_id || !in_array($new_status, ['active', 'archived', 'hidden', 'outdated'])) {
        $_SESSION['error'] = 'Ungültige Parameter';
        header('Location: index.php?tab=documents');
        exit;
    }

    $result = update_document($pdo, $document_id, ['status' => $new_status]);

    if ($result['success']) {
        $_SESSION['success'] = 'Status geändert';
    } else {
        $_SESSION['error'] = $result['message'];
    }

    header('Location: index.php?tab=documents');
    exit;
}

// ============================================
// DOKUMENT LÖSCHEN
// ============================================

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!$is_admin) {
        $_SESSION['error'] = 'Keine Berechtigung zum Löschen';
        header('Location: index.php?tab=documents');
        exit;
    }

    $document_id = intval($_POST['document_id'] ?? 0);
    $permanent = isset($_POST['permanent']) && $_POST['permanent'] === '1';

    if (!$document_id) {
        $_SESSION['error'] = 'Ungültige Dokument-ID';
        header('Location: index.php?tab=documents');
        exit;
    }

    // Sicherheitsabfrage für permanentes Löschen
    if ($permanent && (!isset($_POST['confirm']) || $_POST['confirm'] !== 'DELETE')) {
        $_SESSION['error'] = 'Bitte bestätigen Sie das permanente Löschen';
        header('Location: index.php?tab=documents&view=edit&id=' . $document_id);
        exit;
    }

    $result = delete_document($pdo, $document_id, $permanent);

    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }

    header('Location: index.php?tab=documents');
    exit;
}

// ============================================
// BULK-AKTIONEN
// ============================================

if (isset($_POST['action']) && $_POST['action'] === 'bulk') {
    if (!$is_admin) {
        $_SESSION['error'] = 'Keine Berechtigung';
        header('Location: index.php?tab=documents');
        exit;
    }

    $bulk_action = $_POST['bulk_action'] ?? '';
    $selected_docs = $_POST['selected_documents'] ?? [];

    if (empty($selected_docs) || !is_array($selected_docs)) {
        $_SESSION['error'] = 'Keine Dokumente ausgewählt';
        header('Location: index.php?tab=documents');
        exit;
    }

    $success_count = 0;
    $error_count = 0;

    foreach ($selected_docs as $doc_id) {
        $doc_id = intval($doc_id);

        switch ($bulk_action) {
            case 'archive':
                $result = update_document($pdo, $doc_id, ['status' => 'archived']);
                break;

            case 'activate':
                $result = update_document($pdo, $doc_id, ['status' => 'active']);
                break;

            case 'hide':
                $result = update_document($pdo, $doc_id, ['status' => 'hidden']);
                break;

            case 'delete':
                $result = delete_document($pdo, $doc_id, false);
                break;

            default:
                $result = ['success' => false];
                break;
        }

        if ($result['success']) {
            $success_count++;
        } else {
            $error_count++;
        }
    }

    if ($success_count > 0) {
        $_SESSION['success'] = "$success_count Dokument(e) erfolgreich bearbeitet";
    }

    if ($error_count > 0) {
        $_SESSION['error'] = "$error_count Dokument(e) konnten nicht bearbeitet werden";
    }

    header('Location: index.php?tab=documents');
    exit;
}

// Falls keine bekannte Aktion
$_SESSION['error'] = 'Unbekannte Aktion';
header('Location: index.php?tab=documents');
exit;
?>
