<?php
/**
 * process_meetings.php - Meeting-Verwaltung (Business Logic)
 * Bereinigt: 29.10.2025 01:00 MEZ
 * 
 * Verarbeitet alle POST-Anfragen für Meeting-Verwaltung
 * Trennung von Business-Logik und Präsentation (MVC-Prinzip)
 * 
 * WICHTIG: Diese Datei wird direkt aufgerufen und benötigt eigene Session
 * Voraussetzungen: config.php, functions.php
 */

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Nur POST-Requests erlaubt');
}

require_once 'config.php';
require_once 'functions.php';

session_start();

// ============================================
// AUTHENTIFIZIERUNG
// ============================================

// Prüfen ob User eingeloggt ist
if (!isset($_SESSION['member_id'])) {
    header('Location: index.php');
    exit;
}

// User-Daten laden (über Wrapper-Funktion)
$current_user = get_member_by_id($pdo, $_SESSION['member_id']);

if (!$current_user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ============================================
// HILFSFUNKTIONEN
// ============================================

/**
 * Prüft ob User berechtigt ist, ein Meeting zu bearbeiten/starten
 *
 * @param array $meeting Meeting-Daten
 * @param array $current_user User-Daten
 * @param array $allowed_statuses Erlaubte Meeting-Status
 * @return bool
 */
function is_authorized_for_meeting($meeting, $current_user, $allowed_statuses = ['preparation']) {
    if (!$meeting) {
        return false;
    }

    // Berechtigung: Ersteller ODER Assistenz/GF
    $is_creator = ($meeting['invited_by_member_id'] == $current_user['member_id']);
    $is_admin = in_array($current_user['role'], ['assistenz', 'gf']);

    // Status muss erlaubt sein
    $status_ok = in_array($meeting['status'], $allowed_statuses);

    return ($is_creator || $is_admin) && $status_ok;
}

/**
 * Prüft ob User berechtigt ist, ein Meeting zu löschen
 *
 * @param array $meeting Meeting-Daten
 * @param array $current_user User-Daten
 * @return bool
 */
function can_delete_meeting($meeting, $current_user) {
    if (!$meeting) {
        return false;
    }

    $is_creator = ($meeting['invited_by_member_id'] == $current_user['member_id']);
    $is_admin = in_array($current_user['role'], ['assistenz', 'gf']);

    // Im preparation-Status: Ersteller ODER Admin
    if ($meeting['status'] === 'preparation') {
        return $is_creator || $is_admin;
    }

    // In anderen Status: Nur Admin
    return $is_admin;
}

/**
 * Erstellt automatisch TOP 0 und TOP 99
 * 
 * @param PDO $pdo
 * @param int $meeting_id
 * @param int $creator_member_id
 */
function create_default_tops($pdo, $meeting_id, $creator_member_id) {
    // TOP 0: Wahl der Sitzungsleitung und Protokollführung - Kategorie: wahl
    $stmt = $pdo->prepare("
        INSERT INTO svagenda_items
        (meeting_id, top_number, title, description, category, priority, estimated_duration,
         is_confidential, is_active, created_by_member_id, created_at)
        VALUES (?, 0, ?, ?, 'wahl', NULL, NULL, 0, 0, ?, NOW())
    ");
    $stmt->execute([
        $meeting_id,
        'Wahl der Sitzungsleitung und Protokollführung',
        'Formale Wahl, Organisatorisches',
        $creator_member_id
    ]);

    // TOP 99: Verschiedenes - Kategorie: sonstiges
    $stmt = $pdo->prepare("
        INSERT INTO svagenda_items
        (meeting_id, top_number, title, description, category, priority, estimated_duration,
         is_confidential, is_active, created_by_member_id, created_at)
        VALUES (?, 99, ?, ?, 'sonstiges', NULL, NULL, 0, 0, ?, NOW())
    ");
    $stmt->execute([
        $meeting_id,
        'Verschiedenes',
        'Informationen, Ankündigungen und Sonstige Themen (keine Beschlüsse)',
        $creator_member_id
    ]);
}

/**
 * Fügt Teilnehmer zu einem Meeting hinzu
 * 
 * @param PDO $pdo
 * @param int $meeting_id
 * @param array $participant_ids
 */
function add_participants($pdo, $meeting_id, $participant_ids) {
    if (empty($participant_ids)) {
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO svmeeting_participants (meeting_id, member_id, attendance_status) 
        VALUES (?, ?, 'absent')
    ");
    
    foreach ($participant_ids as $member_id) {
        $stmt->execute([$meeting_id, intval($member_id)]);
    }
}

// ============================================
// 1. MEETING ERSTELLEN
// ============================================

/**
 * Neues Meeting erstellen
 * 
 * POST-Parameter:
 * - create_meeting: 1
 * - meeting_name: String (required)
 * - meeting_date: DateTime (required)
 * - expected_end_date: DateTime (optional)
 * - location: String (optional)
 * - video_link: URL (optional)
 * - chairman_member_id: Int (optional)
 * - secretary_member_id: Int (optional)
 * - participant_ids[]: Array of Int (optional)
 * 
 * Aktion:
 * 1. Meeting in DB erstellen (Status: preparation)
 * 2. TOP 0 und TOP 99 automatisch erstellen
 * 3. Teilnehmer hinzufügen
 * 
 * Redirect: index.php?tab=meetings&success=created&meeting_id=X
 */
if (isset($_POST['create_meeting'])) {
    // Input-Validierung
    $meeting_name = trim($_POST['meeting_name'] ?? '');
    $meeting_date = $_POST['meeting_date'] ?? '';
    $expected_end_date = !empty($_POST['expected_end_date']) ? $_POST['expected_end_date'] : null;
    $submission_deadline = !empty($_POST['submission_deadline']) ? $_POST['submission_deadline'] : null;
    $location = trim($_POST['location'] ?? '');
    $video_link = trim($_POST['video_link'] ?? '');
    $chairman_member_id = !empty($_POST['chairman_member_id']) ? intval($_POST['chairman_member_id']) : null;
    $secretary_member_id = !empty($_POST['secretary_member_id']) ? intval($_POST['secretary_member_id']) : null;
    $participant_ids = $_POST['participant_ids'] ?? [];
    $visibility_type = $_POST['visibility_type'] ?? 'invited_only';

    // Validierung
    if (empty($meeting_name) || empty($meeting_date)) {
        header("Location: index.php?tab=meetings&error=missing_data");
        exit;
    }

    // Wenn kein Antragsschluss gesetzt wurde, automatisch 24 Stunden vor Start setzen
    if (empty($submission_deadline) && !empty($meeting_date)) {
        $submission_deadline = date('Y-m-d H:i:s', strtotime($meeting_date . ' -24 hours'));
    }

    // Sichtbarkeitstyp validieren
    if (!in_array($visibility_type, ['public', 'authenticated', 'invited_only'])) {
        $visibility_type = 'invited_only';
    }

    try {
        $pdo->beginTransaction();

        // 1. Meeting erstellen
        $stmt = $pdo->prepare("
            INSERT INTO svmeetings
            (meeting_name, meeting_date, expected_end_date, submission_deadline, location, video_link,
             chairman_member_id, secretary_member_id, invited_by_member_id, visibility_type, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'preparation', NOW())
        ");
        $stmt->execute([
            $meeting_name,
            $meeting_date,
            $expected_end_date,
            $submission_deadline,
            $location,
            $video_link,
            $chairman_member_id,
            $secretary_member_id,
            $current_user['member_id'],
            $visibility_type
        ]);
        
        $meeting_id = $pdo->lastInsertId();
        
        // 2. Standard-TOPs erstellen
        create_default_tops($pdo, $meeting_id, $current_user['member_id']);
        
        // 3. Teilnehmer hinzufügen
        add_participants($pdo, $meeting_id, $participant_ids);
        
        $pdo->commit();
        
        header("Location: index.php?tab=meetings&success=created&meeting_id=$meeting_id");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Fehler beim Meeting-Erstellen: " . $e->getMessage());

        // Im Debug-Modus detaillierte Fehlermeldung anzeigen
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            die("Fehler beim Meeting-Erstellen: " . $e->getMessage() . "<br><br>Trace:<br>" . nl2br($e->getTraceAsString()));
        }

        header("Location: index.php?tab=meetings&error=create_failed");
        exit;
    }
}

// ============================================
// 2. MEETING BEARBEITEN
// ============================================

/**
 * Meeting bearbeiten
 * 
 * POST-Parameter:
 * - edit_meeting: 1
 * - meeting_id: Int (required)
 * - [alle Parameter wie bei create_meeting]
 * 
 * Berechtigung: Ersteller ODER Assistenz/GF
 * Status: Nur 'preparation'
 * 
 * Aktion:
 * 1. Meeting-Daten aktualisieren
 * 2. Teilnehmer-Liste neu setzen
 * 
 * Redirect: index.php?tab=meetings&success=updated&meeting_id=X
 */
if (isset($_POST['edit_meeting'])) {
    $meeting_id = intval($_POST['meeting_id'] ?? 0);
    
    if (!$meeting_id) {
        header("Location: index.php?tab=meetings&error=invalid_id");
        exit;
    }
    
    // Meeting laden und Berechtigung prüfen
    $stmt = $pdo->prepare("SELECT * FROM svmeetings WHERE meeting_id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!is_authorized_for_meeting($meeting, $current_user, ['preparation'])) {
        header("Location: index.php?tab=meetings&error=permission");
        exit;
    }

    // Input-Validierung
    $meeting_name = trim($_POST['meeting_name'] ?? '');
    $meeting_date = $_POST['meeting_date'] ?? '';
    $expected_end_date = !empty($_POST['expected_end_date']) ? $_POST['expected_end_date'] : null;
    $submission_deadline = !empty($_POST['submission_deadline']) ? $_POST['submission_deadline'] : null;
    $location = trim($_POST['location'] ?? '');
    $video_link = trim($_POST['video_link'] ?? '');
    $chairman_member_id = !empty($_POST['chairman_member_id']) ? intval($_POST['chairman_member_id']) : null;
    $secretary_member_id = !empty($_POST['secretary_member_id']) ? intval($_POST['secretary_member_id']) : null;
    $participant_ids = $_POST['participant_ids'] ?? [];
    $visibility_type = $_POST['visibility_type'] ?? 'invited_only';

    if (empty($meeting_name) || empty($meeting_date)) {
        header("Location: index.php?tab=meetings&error=missing_data&meeting_id=$meeting_id");
        exit;
    }

    // Wenn kein Antragsschluss gesetzt wurde, automatisch 24 Stunden vor Start setzen
    if (empty($submission_deadline) && !empty($meeting_date)) {
        $submission_deadline = date('Y-m-d H:i:s', strtotime($meeting_date . ' -24 hours'));
    }

    // Sichtbarkeitstyp validieren
    if (!in_array($visibility_type, ['public', 'authenticated', 'invited_only'])) {
        $visibility_type = 'invited_only';
    }

    try {
        $pdo->beginTransaction();

        // 1. Meeting aktualisieren
        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_name = ?, meeting_date = ?, expected_end_date = ?, submission_deadline = ?,
                location = ?, video_link = ?, chairman_member_id = ?, secretary_member_id = ?, visibility_type = ?
            WHERE meeting_id = ?
        ");
        $stmt->execute([
            $meeting_name,
            $meeting_date,
            $expected_end_date,
            $submission_deadline,
            $location,
            $video_link,
            $chairman_member_id,
            $secretary_member_id,
            $visibility_type,
            $meeting_id
        ]);
        
        // 2. Teilnehmer neu setzen
        $stmt = $pdo->prepare("DELETE FROM svmeeting_participants WHERE meeting_id = ?");
        $stmt->execute([$meeting_id]);
        
        add_participants($pdo, $meeting_id, $participant_ids);
        
        $pdo->commit();
        
        header("Location: index.php?tab=meetings&success=updated&meeting_id=$meeting_id");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Fehler beim Meeting-Aktualisieren: " . $e->getMessage());
        header("Location: index.php?tab=meetings&error=update_failed&meeting_id=$meeting_id");
        exit;
    }
}

// ============================================
// 3. MEETING LÖSCHEN
// ============================================

/**
 * Meeting löschen (mit allen abhängigen Daten)
 * 
 * POST-Parameter:
 * - delete_meeting: 1
 * - meeting_id: Int (required)
 * 
 * Berechtigung: Ersteller ODER Assistenz/GF
 * Status: Nur 'preparation'
 * 
 * Aktion (in dieser Reihenfolge):
 * 1. ToDos löschen
 * 2. Kommentare löschen
 * 3. Agenda Items löschen
 * 4. Teilnehmer löschen
 * 5. Meeting löschen
 * 
 * Redirect: index.php?tab=meetings&success=deleted
 */
if (isset($_POST['delete_meeting'])) {
    $meeting_id = intval($_POST['meeting_id'] ?? 0);
    
    if (!$meeting_id) {
        header("Location: index.php?tab=meetings&error=invalid_id");
        exit;
    }
    
    // Meeting laden und Berechtigung prüfen
    $stmt = $pdo->prepare("SELECT * FROM svmeetings WHERE meeting_id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!can_delete_meeting($meeting, $current_user)) {
        header("Location: index.php?tab=meetings&error=permission");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Reihenfolge wichtig wegen Foreign Keys!
        
        // 1. ToDos löschen
        $stmt = $pdo->prepare("DELETE FROM svtodos WHERE meeting_id = ?");
        $stmt->execute([$meeting_id]);
        
        // 2. Kommentare löschen (über Agenda Items)
        $stmt = $pdo->prepare("
            DELETE FROM svagenda_comments
            WHERE item_id IN (SELECT item_id FROM svagenda_items WHERE meeting_id = ?)
        ");
        $stmt->execute([$meeting_id]);

        // 2b. Post-Kommentare löschen (über Agenda Items)
        $stmt = $pdo->prepare("
            DELETE FROM agenda_post_comments
            WHERE item_id IN (SELECT item_id FROM svagenda_items WHERE meeting_id = ?)
        ");
        $stmt->execute([$meeting_id]);

        // 3. Agenda Items löschen
        $stmt = $pdo->prepare("DELETE FROM svagenda_items WHERE meeting_id = ?");
        $stmt->execute([$meeting_id]);
        
        // 4. Teilnehmer löschen
        $stmt = $pdo->prepare("DELETE FROM svmeeting_participants WHERE meeting_id = ?");
        $stmt->execute([$meeting_id]);
        
        // 5. Meeting löschen
        $stmt = $pdo->prepare("DELETE FROM svmeetings WHERE meeting_id = ?");
        $stmt->execute([$meeting_id]);
        
        $pdo->commit();
        
        header("Location: index.php?tab=meetings&success=deleted");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Fehler beim Meeting-Löschen: " . $e->getMessage());
        header("Location: index.php?tab=meetings&error=delete_failed&meeting_id=$meeting_id");
        exit;
    }
}

// ============================================
// 4. MEETING STARTEN
// ============================================

/**
 * Meeting starten (Sitzung beginnen)
 * 
 * POST-Parameter:
 * - start_meeting: 1
 * - meeting_id: Int (required)
 * - chairman_member_id: Int (required)
 * - secretary_member_id: Int (required)
 * 
 * Berechtigung: Ersteller ODER Assistenz/GF
 * Status: Nur 'preparation'
 * 
 * Aktion:
 * 1. Meeting-Status → 'active'
 * 2. Sitzungsleitung und Protokollführung setzen
 * 3. TOP 0 als aktiv markieren
 * 4. TOP 0 Protokoll mit Namen initialisieren
 * 
 * Redirect: index.php?tab=agenda&meeting_id=X
 */
if (isset($_POST['start_meeting'])) {
    $meeting_id = intval($_POST['meeting_id'] ?? 0);
    $chairman_member_id = intval($_POST['chairman_member_id'] ?? 0);
    $secretary_member_id = intval($_POST['secretary_member_id'] ?? 0);
    
    // Validierung
    if (!$meeting_id || !$chairman_member_id || !$secretary_member_id) {
        header("Location: index.php?tab=meetings&error=missing_data&meeting_id=$meeting_id");
        exit;
    }
    
    // Meeting laden und Berechtigung prüfen
    $stmt = $pdo->prepare("SELECT * FROM svmeetings WHERE meeting_id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_authorized_for_meeting($meeting, $current_user, ['preparation'])) {
        header("Location: index.php?tab=meetings&error=permission");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Meeting-Status auf 'active' setzen und Rollen speichern
        $stmt = $pdo->prepare("
            UPDATE svmeetings 
            SET status = 'active', 
                chairman_member_id = ?, 
                secretary_member_id = ?,
                started_at = NOW()
            WHERE meeting_id = ?
        ");
        $stmt->execute([$chairman_member_id, $secretary_member_id, $meeting_id]);
        
        // 2. TOP 0 als aktiv markieren
        $stmt = $pdo->prepare("
            UPDATE svagenda_items 
            SET is_active = 1 
            WHERE meeting_id = ? AND top_number = 0
        ");
        $stmt->execute([$meeting_id]);
        
        // 3. TOP 0 Protokoll initialisieren
        // Namen der Rollen laden (über Wrapper-Funktion)
        $chairman_data = get_member_by_id($pdo, $chairman_member_id);
        $chairman_name = $chairman_data ?
            $chairman_data['first_name'] . ' ' . $chairman_data['last_name'] :
            'Unbekannt';

        $secretary_data = get_member_by_id($pdo, $secretary_member_id);
        $secretary_name = $secretary_data ?
            $secretary_data['first_name'] . ' ' . $secretary_data['last_name'] :
            'Unbekannt';
        
        // Protokolltext erstellen
        $protocol_text = "Sitzungsleitung: {$chairman_name}\nProtokollführung: {$secretary_name}";
        
        // In TOP 0 speichern
        $stmt = $pdo->prepare("
            UPDATE svagenda_items 
            SET protocol_notes = ? 
            WHERE meeting_id = ? AND top_number = 0
        ");
        $stmt->execute([$protocol_text, $meeting_id]);
        
        $pdo->commit();
        
        // Zur Tagesordnung weiterleiten
        header("Location: index.php?tab=agenda&meeting_id=$meeting_id");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Fehler beim Meeting-Starten: " . $e->getMessage());
        header("Location: index.php?tab=meetings&error=start_failed&meeting_id=$meeting_id");
        exit;
    }
}

// ============================================
// UNBEKANNTE AKTION
// ============================================

// Wenn keine Aktion erkannt wurde, zurück zur Meeting-Liste
header("Location: index.php?tab=meetings&error=unknown_action");
exit;

?>