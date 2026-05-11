<?php
/**
 * process_proposals.php - Proposals Business Logic Handler
 * Erstellt: 2025-05-11
 *
 * Verarbeitet alle POST-Anfragen für Antrags- und Beschlussverwaltung
 * Trennung von Business-Logik und Präsentation (MVC-Prinzip)
 *
 * WICHTIG: Diese Datei wird direkt aufgerufen und benötigt eigene Session
 */

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Nur POST-Requests erlaubt');
}

require_once 'config.php';
require_once 'functions.php';
require_once 'proposals_functions.php';
require_once 'adapters/ProposalPermissionAdapter.php';

// Session starten (falls noch nicht gestartet)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// AUTHENTIFIZIERUNG
// ============================================

// Prüfen ob User eingeloggt ist
if (!isset($_SESSION['member_id'])) {
    header('Location: index.php');
    exit;
}

// User-Daten laden
$current_user = get_member_by_id($pdo, $_SESSION['member_id']);

// Session ist ungültig
if (!$current_user) {
    session_destroy();
    session_start();
    $_SESSION['error'] = 'Deine Session ist abgelaufen. Bitte melde dich erneut an.';
    header('Location: index.php');
    exit;
}

// Permission Adapter initialisieren
$memberAdapter = get_member_adapter($pdo);
$permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);

// ============================================
// ACTION ROUTING
// ============================================

$action = $_POST['action'] ?? '';
$redirect_url = 'index.php?tab=proposals';

try {
    switch ($action) {

        // ============================================
        // CREATE PROPOSAL
        // ============================================
        case 'create':
            if (!$permAdapter->can_create_proposal($current_user)) {
                throw new Exception('Keine Berechtigung zum Erstellen von Anträgen', 403);
            }

            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'justification' => trim($_POST['justification'] ?? ''),
                'department_id' => $_POST['department_id'] ?? null,
                'co_department_id' => $_POST['co_department_id'] ?? null,
                'visibility' => $_POST['visibility'] ?? PROPOSAL_DEFAULT_VISIBILITY,
                'financial_amount' => floatval($_POST['financial_amount'] ?? 0),
                'financial_description' => trim($_POST['financial_description'] ?? ''),
                'personnel_impact' => trim($_POST['personnel_impact'] ?? ''),
                'material_impact' => trim($_POST['material_impact'] ?? ''),
                'responsible_person' => trim($_POST['responsible_person'] ?? ''),
                'decision_type' => $_POST['decision_type'] ?? null,
                'simplified_approval' => $_POST['simplified_approval'] ?? 'none',
                'review_person' => trim($_POST['review_person'] ?? ''),
                'expedite_justification' => trim($_POST['expedite_justification'] ?? '')
            ];

            // Leere Strings zu NULL konvertieren
            foreach ($data as $key => $value) {
                if ($value === '') {
                    $data[$key] = null;
                }
            }

            $result = create_proposal($pdo, $current_user, $data);

            if ($result['success']) {
                $redirect_url .= '&success=created&view=my';
            } else {
                throw new Exception($result['error'] ?? 'Fehler beim Erstellen', 400);
            }
            break;

        // ============================================
        // UPDATE PROPOSAL
        // ============================================
        case 'update':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            if (!$proposal_id) {
                throw new Exception('Keine Antrags-ID angegeben', 400);
            }

            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'justification' => trim($_POST['justification'] ?? ''),
                'department_id' => $_POST['department_id'] ?? null,
                'co_department_id' => $_POST['co_department_id'] ?? null,
                'visibility' => $_POST['visibility'] ?? null,
                'financial_amount' => floatval($_POST['financial_amount'] ?? 0),
                'financial_description' => trim($_POST['financial_description'] ?? ''),
                'personnel_impact' => trim($_POST['personnel_impact'] ?? ''),
                'material_impact' => trim($_POST['material_impact'] ?? ''),
                'responsible_person' => trim($_POST['responsible_person'] ?? ''),
                'expedite_justification' => trim($_POST['expedite_justification'] ?? '')
            ];

            // Leere Strings zu NULL
            foreach ($data as $key => $value) {
                if ($value === '') {
                    $data[$key] = null;
                }
            }

            $result = update_proposal($pdo, $proposal_id, $current_user, $data);

            if ($result['success']) {
                $redirect_url .= '&success=updated&action=view&id=' . $proposal_id;
            } else {
                throw new Exception($result['error'] ?? 'Fehler beim Aktualisieren', 400);
            }
            break;

        // ============================================
        // FINALIZE PROPOSAL (draft/editing → voting/approved)
        // ============================================
        case 'finalize':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            if (!$proposal_id) {
                throw new Exception('Keine Antrags-ID angegeben', 400);
            }

            $result = finalize_proposal($pdo, $proposal_id, $current_user);

            if ($result['success']) {
                $redirect_url .= '&success=finalized&action=view&id=' . $proposal_id;
            } else {
                throw new Exception($result['error'] ?? 'Fehler beim Finalisieren', 400);
            }
            break;

        // ============================================
        // WITHDRAW PROPOSAL
        // ============================================
        case 'withdraw':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            if (!$proposal_id) {
                throw new Exception('Keine Antrags-ID angegeben', 400);
            }

            $reason = trim($_POST['reason'] ?? '');

            $result = withdraw_proposal($pdo, $proposal_id, $current_user, $reason);

            if ($result['success']) {
                $redirect_url .= '&success=withdrawn&view=my';
            } else {
                throw new Exception($result['error'] ?? 'Fehler beim Zurückziehen', 400);
            }
            break;

        // ============================================
        // SUBMIT VOTE
        // ============================================
        case 'vote':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            $vote_type = $_POST['vote_type'] ?? '';
            $comment = trim($_POST['vote_comment'] ?? '');

            if (!$proposal_id || !$vote_type) {
                throw new Exception('Antrags-ID oder Votum fehlt', 400);
            }

            // Validate vote_type
            $valid_votes = ['yes', 'no', 'abstain', 'refer_back', 'request_time'];
            if (!in_array($vote_type, $valid_votes)) {
                throw new Exception('Ungültiger Abstimmungstyp', 400);
            }

            $result = submit_vote($pdo, $proposal_id, $current_user, $vote_type, $comment);

            if ($result['success']) {
                $redirect_url .= '&success=voted&action=view&id=' . $proposal_id;
            } else {
                throw new Exception($result['error'] ?? 'Fehler beim Abstimmen', 400);
            }
            break;

        // ============================================
        // APPROVE EXPEDITE (Wartezeitverkürzung)
        // ============================================
        case 'approve_expedite':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            if (!$proposal_id) {
                throw new Exception('Keine Antrags-ID angegeben', 400);
            }

            $result = approve_expedite($pdo, $proposal_id, $current_user);

            if ($result['success']) {
                $redirect_url .= '&success=expedite_approved&action=view&id=' . $proposal_id;
            } else {
                throw new Exception($result['error'] ?? 'Fehler bei Genehmigung', 400);
            }
            break;

        // ============================================
        // ADD ATTACHMENT
        // ============================================
        case 'add_attachment':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            $description = trim($_POST['attachment_description'] ?? '');
            $type = $_POST['attachment_type'] ?? 'file'; // 'file' or 'url'

            if (!$proposal_id) {
                throw new Exception('Keine Antrags-ID angegeben', 400);
            }

            if ($type === 'url') {
                $url = trim($_POST['attachment_url'] ?? '');
                if (empty($url)) {
                    throw new Exception('Keine URL angegeben', 400);
                }

                $result = add_proposal_attachment($pdo, $proposal_id, $current_user, $url, $description, 'url');
            } else {
                // File upload
                if (!isset($_FILES['attachment_file']) || $_FILES['attachment_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Keine Datei hochgeladen oder Fehler beim Upload', 400);
                }

                $file = $_FILES['attachment_file'];

                // Validate file size
                if ($file['size'] > PROPOSAL_MAX_FILE_SIZE) {
                    $max_mb = PROPOSAL_MAX_FILE_SIZE / 1024 / 1024;
                    throw new Exception("Datei zu groß (max. {$max_mb} MB)", 400);
                }

                // Validate file type
                global $PROPOSAL_ALLOWED_FILE_TYPES;
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime_type, $PROPOSAL_ALLOWED_FILE_TYPES)) {
                    throw new Exception('Dateityp nicht erlaubt', 400);
                }

                // Create upload directory if not exists
                $upload_dir = __DIR__ . '/' . PROPOSAL_UPLOAD_DIR;
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Generate unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . '/' . $filename;
                $relative_path = PROPOSAL_UPLOAD_DIR . '/' . $filename;

                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                    throw new Exception('Fehler beim Speichern der Datei', 500);
                }

                $result = add_proposal_attachment($pdo, $proposal_id, $current_user, $relative_path, $description, 'file');
            }

            if ($result['success']) {
                $redirect_url .= '&success=attachment_added&action=view&id=' . $proposal_id;
            } else {
                throw new Exception($result['error'] ?? 'Fehler beim Hinzufügen des Anhangs', 400);
            }
            break;

        // ============================================
        // ADD COMMENT
        // ============================================
        case 'add_comment':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');

            if (!$proposal_id || empty($comment)) {
                throw new Exception('Antrags-ID oder Kommentar fehlt', 400);
            }

            // Check permission
            $proposal = get_proposal_by_id($pdo, $proposal_id);
            if (!$proposal) {
                throw new Exception('Antrag nicht gefunden', 404);
            }

            if (!$permAdapter->can_comment($current_user, $proposal)) {
                throw new Exception('Keine Berechtigung zum Kommentieren', 403);
            }

            $result = add_proposal_comment($pdo, $proposal_id, $current_user, $comment);

            if ($result['success']) {
                $redirect_url .= '&success=comment_added&action=view&id=' . $proposal_id;
            } else {
                throw new Exception($result['error'] ?? 'Fehler beim Hinzufügen des Kommentars', 400);
            }
            break;

        // ============================================
        // FINALIZE VOTING (Admin-only, manually finalize a vote)
        // ============================================
        case 'finalize_voting':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            if (!$proposal_id) {
                throw new Exception('Keine Antrags-ID angegeben', 400);
            }

            // Only admins can manually finalize voting
            if (!$current_user['is_admin']) {
                throw new Exception('Nur Admins können Abstimmungen manuell finalisieren', 403);
            }

            $result = finalize_voting($pdo, $proposal_id);

            if ($result['success']) {
                $redirect_url .= '&success=voting_finalized&action=view&id=' . $proposal_id;
            } else {
                throw new Exception($result['error'] ?? 'Fehler beim Finalisieren', 400);
            }
            break;

        // ============================================
        // DELETE PROPOSAL (Admin or draft owner)
        // ============================================
        case 'delete':
            $proposal_id = intval($_POST['proposal_id'] ?? 0);
            if (!$proposal_id) {
                throw new Exception('Keine Antrags-ID angegeben', 400);
            }

            $proposal = get_proposal_by_id($pdo, $proposal_id);
            if (!$proposal) {
                throw new Exception('Antrag nicht gefunden', 404);
            }

            // Permission check
            if (!$permAdapter->can_delete_proposal($current_user, $proposal)) {
                throw new Exception('Keine Berechtigung zum Löschen', 403);
            }

            // Delete proposal (hard delete)
            $stmt = $pdo->prepare("DELETE FROM svbproposals WHERE id = ?");
            $stmt->execute([$proposal_id]);

            $redirect_url .= '&success=deleted&view=my';
            break;

        // ============================================
        // UNKNOWN ACTION
        // ============================================
        default:
            throw new Exception('Unbekannte Aktion: ' . htmlspecialchars($action), 400);
    }

} catch (Exception $e) {
    // Error handling
    $error_code = $e->getCode();
    $error_msg = $e->getMessage();

    // Map error codes to error types
    $error_type = 'unknown';
    switch ($error_code) {
        case 400:
            $error_type = 'invalid_data';
            break;
        case 403:
            $error_type = 'permission';
            break;
        case 404:
            $error_type = 'not_found';
            break;
        default:
            $error_type = 'server_error';
    }

    // Log error
    error_log("Proposal processing error [$action]: " . $error_msg);

    // Redirect with error
    $redirect_url .= '&error=' . $error_type . '&error_msg=' . urlencode($error_msg);
}

// ============================================
// REDIRECT
// ============================================
header('Location: ' . $redirect_url);
exit;
?>
