<?php
/**
 * process_kalender.php - Backend-Handler für das Kalender-Modul
 * Verarbeitet POST-Requests: create_event, update_event, delete_event
 */

// Session konfigurieren und starten (session_config.php MUSS vor session_start() geladen werden)
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session_config.php';
    session_start();
}

// Konfiguration und Funktionen laden
// functions.php bindet config.php, config_adapter.php, member_functions.php und audit_pdo.php ein
require_once __DIR__ . '/functions.php';

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?tab=kalender');
    exit;
}

// Eingeloggten User prüfen
if (!isset($_SESSION['member_id'])) {
    $_SESSION['error'] = 'Bitte anmelden, um Kalender-Einträge zu verwalten.';
    header('Location: login.php');
    exit;
}

$current_user = get_member_by_id($pdo, (int)$_SESSION['member_id']);
if (!$current_user) {
    $_SESSION['error'] = 'Benutzer nicht gefunden.';
    header('Location: index.php');
    exit;
}

$is_admin    = is_admin_user($current_user);
$member_id   = (int)$current_user['member_id'];
$member_role = strtolower($current_user['role'] ?? '');
$action      = $_POST['action'] ?? '';

// Tabellen-Existenz prüfen
if (!$pdo->query("SHOW TABLES LIKE 'svevents'")->fetchColumn()) {
    $_SESSION['error'] = 'Tabelle svevents fehlt – bitte tools/migrate_neue_tabellen.php ausführen.';
    header('Location: index.php?tab=kalender');
    exit;
}

// ============================================================
// HILFSFUNKTION: Teilnehmer neu setzen
// ============================================================
function setEventParticipants($pdo, int $event_id, array $participant_ids): void
{
    $pdo->prepare("DELETE FROM svevents_participants WHERE event_id = ?")->execute([$event_id]);
    if (empty($participant_ids)) {
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO svevents_participants (event_id, member_id) VALUES (?, ?)");
    foreach ($participant_ids as $pid) {
        $pid = intval($pid);
        if ($pid > 0) {
            $stmt->execute([$event_id, $pid]);
        }
    }
}

// ============================================================
// EINGABE-VALIDIERUNG / BEREINIGUNG
// ============================================================
function sanitizeEventInput(array $post, bool $is_admin): array
{
    // Ganztags-Logik: Bei ganztags wird start_datetime aus start_date_only gebaut
    $all_day = !empty($post['all_day']) ? 1 : 0;

    if ($all_day && !empty($post['start_date_only'])) {
        $start_datetime = date('Y-m-d', strtotime($post['start_date_only'])) . ' 00:00:00';
        $end_datetime   = null;
    } else {
        $raw_start = $post['start_datetime'] ?? '';
        // datetime-local liefert "YYYY-MM-DDTHH:MM", in Y-m-d H:i:s umwandeln
        $start_ts = strtotime(str_replace('T', ' ', $raw_start));
        $start_datetime = $start_ts ? date('Y-m-d H:i:s', $start_ts) : null;

        $raw_end = $post['end_datetime'] ?? '';
        if (!empty($raw_end)) {
            $end_ts = strtotime(str_replace('T', ' ', $raw_end));
            $end_datetime = $end_ts ? date('Y-m-d H:i:s', $end_ts) : null;
        } else {
            $end_datetime = null;
        }
    }

    // Wiederholung
    $recurrence_values = ['none','weekly','biweekly','monthly','yearly'];
    $recurrence = in_array($post['recurrence'] ?? '', $recurrence_values)
        ? $post['recurrence']
        : 'none';

    $rec_end = null;
    if ($recurrence !== 'none' && !empty($post['recurrence_end_date'])) {
        $ts = strtotime($post['recurrence_end_date']);
        if ($ts) {
            $rec_end = date('Y-m-d', $ts);
        }
    }

    // Zielgruppe
    $target_type_values = ['all','roles','list'];
    $target_type = in_array($post['target_type'] ?? '', $target_type_values)
        ? $post['target_type']
        : 'all';

    // Rollen (nur für Admins)
    $target_roles = null;
    if ($is_admin && $target_type === 'roles' && !empty($post['target_roles'])) {
        $allowed_roles = ['vorstand','gf','assistenz'];
        $roles = array_filter(
            (array)$post['target_roles'],
            fn($r) => in_array($r, $allowed_roles)
        );
        $target_roles = implode(',', $roles) ?: null;
    }
    // Nicht-Admins dürfen keine Rollen setzen
    if (!$is_admin) {
        $target_type  = ($target_type === 'roles') ? 'all' : $target_type;
        $target_roles = null;
    }

    // Typ
    $type_values = ['sitzung','treffen','abwesenheit','frist','sonstiges'];
    $event_type = in_array($post['event_type'] ?? '', $type_values)
        ? $post['event_type']
        : 'sonstiges';

    return [
        'title'               => mb_substr(trim($post['title'] ?? ''), 0, 200),
        'description'         => trim($post['description'] ?? '') ?: null,
        'location'            => mb_substr(trim($post['location'] ?? ''), 0, 200) ?: null,
        'start_datetime'      => $start_datetime,
        'end_datetime'        => $end_datetime,
        'all_day'             => $all_day,
        'event_type'          => $event_type,
        'recurrence'          => $recurrence,
        'recurrence_end_date' => $rec_end,
        'target_type'         => $target_type,
        'target_roles'        => $target_roles,
        'participant_ids'     => array_map('intval', (array)($post['participant_ids'] ?? [])),
    ];
}

// ============================================================
// ACTION: create_event
// ============================================================
if ($action === 'create_event') {
    // Nur nicht-Mitglieder dürfen Termine erstellen
    if ($member_role === 'mitglied') {
        $_SESSION['error'] = 'Keine Berechtigung zum Erstellen von Terminen.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    $data = sanitizeEventInput($_POST, $is_admin);

    if (empty($data['title'])) {
        $_SESSION['error'] = 'Titel darf nicht leer sein.';
        header('Location: index.php?tab=kalender');
        exit;
    }
    if (empty($data['start_datetime'])) {
        $_SESSION['error'] = 'Bitte ein gültiges Startdatum angeben.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO svevents
                (title, description, location, start_datetime, end_datetime, all_day,
                 event_type, recurrence, recurrence_end_date,
                 target_type, target_roles, created_by_member_id, status)
             VALUES
                (:title, :description, :location, :start_datetime, :end_datetime, :all_day,
                 :event_type, :recurrence, :recurrence_end_date,
                 :target_type, :target_roles, :created_by, 'active')"
        );
        $stmt->execute([
            ':title'               => $data['title'],
            ':description'         => $data['description'],
            ':location'            => $data['location'],
            ':start_datetime'      => $data['start_datetime'],
            ':end_datetime'        => $data['end_datetime'],
            ':all_day'             => $data['all_day'],
            ':event_type'          => $data['event_type'],
            ':recurrence'          => $data['recurrence'],
            ':recurrence_end_date' => $data['recurrence_end_date'],
            ':target_type'         => $data['target_type'],
            ':target_roles'        => $data['target_roles'],
            ':created_by'          => $member_id,
        ]);

        $new_event_id = (int)$pdo->lastInsertId();

        if ($data['target_type'] === 'list') {
            setEventParticipants($pdo, $new_event_id, $data['participant_ids']);
        }

        $_SESSION['success'] = 'Termin wurde erfolgreich erstellt.';
    } catch (PDOException $e) {
        error_log('process_kalender create_event: ' . $e->getMessage());
        $_SESSION['error'] = 'Fehler beim Erstellen des Termins.';
    }

    header('Location: index.php?tab=kalender');
    exit;
}

// ============================================================
// ACTION: update_event
// ============================================================
if ($action === 'update_event') {
    $event_id = intval($_POST['event_id'] ?? 0);
    if ($event_id <= 0) {
        $_SESSION['error'] = 'Ungültige Termin-ID.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    // Ereignis laden und Berechtigung prüfen
    $stmt = $pdo->prepare("SELECT * FROM svevents WHERE event_id = ? AND status != 'deleted' LIMIT 1");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        $_SESSION['error'] = 'Termin nicht gefunden.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    // Nur Ersteller oder Admin
    if ((int)$event['created_by_member_id'] !== $member_id && !$is_admin) {
        $_SESSION['error'] = 'Keine Berechtigung zum Bearbeiten dieses Termins.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    $data = sanitizeEventInput($_POST, $is_admin);

    if (empty($data['title'])) {
        $_SESSION['error'] = 'Titel darf nicht leer sein.';
        header('Location: index.php?tab=kalender');
        exit;
    }
    if (empty($data['start_datetime'])) {
        $_SESSION['error'] = 'Bitte ein gültiges Startdatum angeben.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE svevents SET
                title = :title,
                description = :description,
                location = :location,
                start_datetime = :start_datetime,
                end_datetime = :end_datetime,
                all_day = :all_day,
                event_type = :event_type,
                recurrence = :recurrence,
                recurrence_end_date = :recurrence_end_date,
                target_type = :target_type,
                target_roles = :target_roles
             WHERE event_id = :event_id"
        );
        $stmt->execute([
            ':title'               => $data['title'],
            ':description'         => $data['description'],
            ':location'            => $data['location'],
            ':start_datetime'      => $data['start_datetime'],
            ':end_datetime'        => $data['end_datetime'],
            ':all_day'             => $data['all_day'],
            ':event_type'          => $data['event_type'],
            ':recurrence'          => $data['recurrence'],
            ':recurrence_end_date' => $data['recurrence_end_date'],
            ':target_type'         => $data['target_type'],
            ':target_roles'        => $data['target_roles'],
            ':event_id'            => $event_id,
        ]);

        // Teilnehmer immer neu setzen (auch wenn target_type != list, dann werden alle gelöscht)
        if ($data['target_type'] === 'list') {
            setEventParticipants($pdo, $event_id, $data['participant_ids']);
        } else {
            $pdo->prepare("DELETE FROM svevents_participants WHERE event_id = ?")->execute([$event_id]);
        }

        $_SESSION['success'] = 'Termin wurde erfolgreich aktualisiert.';
    } catch (PDOException $e) {
        error_log('process_kalender update_event: ' . $e->getMessage());
        $_SESSION['error'] = 'Fehler beim Aktualisieren des Termins.';
    }

    header('Location: index.php?tab=kalender');
    exit;
}

// ============================================================
// ACTION: delete_event
// ============================================================
if ($action === 'delete_event') {
    $event_id = intval($_POST['event_id'] ?? 0);
    if ($event_id <= 0) {
        $_SESSION['error'] = 'Ungültige Termin-ID.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    // Ereignis laden und Berechtigung prüfen
    $stmt = $pdo->prepare("SELECT created_by_member_id FROM svevents WHERE event_id = ? AND status != 'deleted' LIMIT 1");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        $_SESSION['error'] = 'Termin nicht gefunden.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    // Nur Ersteller oder Admin
    if ((int)$event['created_by_member_id'] !== $member_id && !$is_admin) {
        $_SESSION['error'] = 'Keine Berechtigung zum Löschen dieses Termins.';
        header('Location: index.php?tab=kalender');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE svevents SET status = 'deleted' WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $_SESSION['success'] = 'Termin wurde gelöscht.';
    } catch (PDOException $e) {
        error_log('process_kalender delete_event: ' . $e->getMessage());
        $_SESSION['error'] = 'Fehler beim Löschen des Termins.';
    }

    header('Location: index.php?tab=kalender');
    exit;
}

// Unbekannte Aktion
$_SESSION['error'] = 'Unbekannte Aktion.';
header('Location: index.php?tab=kalender');
exit;
