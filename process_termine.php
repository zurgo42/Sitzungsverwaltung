<?php
/**
 * process_termine.php - Terminplanung/Umfragen (Business Logic)
 * Erstellt: 17.11.2025
 *
 * Verarbeitet alle POST-Anfragen für Terminplanung
 * Trennung von Business-Logik und Präsentation (MVC-Prinzip)
 *
 * WICHTIG: Diese Datei wird direkt aufgerufen und benötigt eigene Session
 * Voraussetzungen: config.php, functions.php, member_functions.php
 */

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Nur POST-Requests erlaubt');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/member_functions.php';
require_once __DIR__ . '/mail_functions.php';
require_once __DIR__ . '/external_participants_functions.php';

// Session starten (falls noch nicht gestartet)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DEBUG: Session-Info ausgeben
error_log("=== PROCESS_TERMINE.PHP DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Cookie Params: " . print_r(session_get_cookie_params(), true));
error_log("Cookie Header gesendet: " . (isset($_COOKIE[session_name()]) ? 'JA' : 'NEIN'));
error_log("Cookie Session ID: " . ($_COOKIE[session_name()] ?? 'N/A'));
error_log("standalone_mode gesetzt: " . (isset($_SESSION['standalone_mode']) ? 'JA' : 'NEIN'));
error_log("standalone_user gesetzt: " . (isset($_SESSION['standalone_user']) ? 'JA' : 'NEIN'));
error_log("standalone_user member_id: " . ($_SESSION['standalone_user']['member_id'] ?? 'N/A'));
error_log("member_id in Session: " . ($_SESSION['member_id'] ?? 'N/A'));
error_log("Alle Session-Keys: " . implode(', ', array_keys($_SESSION)));
error_log("==================================");

// ============================================
// REDIRECT-HELPER für Standalone-Modus
// ============================================

/**
 * Gibt die richtige Redirect-URL zurück (Standalone oder Normal)
 */
function get_redirect_url($suffix = '') {
    if (isset($_SESSION['standalone_mode']) && $_SESSION['standalone_mode'] === true) {
        // Standalone: Zurück zum aufrufenden Script
        $base = $_SESSION['standalone_redirect'] ?? $_SERVER['HTTP_REFERER'] ?? 'index.php';
        return $base . $suffix;
    }
    // Normal: index.php mit Tab-Parameter
    return 'index.php?tab=termine' . $suffix;
}

// ============================================
// AUTHENTIFIZIERUNG
// ============================================

// User-Daten laden (kann NULL sein bei externen Teilnehmern)
$current_user = null;

// Standalone-Modus: User aus Session laden (von Simple-Script gesetzt)
if (isset($_SESSION['standalone_mode']) && $_SESSION['standalone_mode'] === true) {
    if (isset($_SESSION['standalone_user'])) {
        $current_user = $_SESSION['standalone_user'];
        error_log("DEBUG: current_user aus standalone_user geladen: " . print_r($current_user, true));
    } else {
        error_log("DEBUG: standalone_mode ist true, ABER standalone_user fehlt!");
    }
} elseif (isset($_SESSION['member_id'])) {
    // Normaler Modus: User aus DB laden
    $current_user = get_member_by_id($pdo, $_SESSION['member_id']);
    error_log("DEBUG: current_user aus DB geladen (normaler Modus)");

    // Session ist ungültig (z.B. nach DB-Reset)
    if (!$current_user) {
        // NICHT session_destroy() im Standalone-Modus!
        if (!isset($_SESSION['standalone_mode']) || $_SESSION['standalone_mode'] !== true) {
            session_destroy();
        }
        $_SESSION['error'] = 'Deine Session ist abgelaufen. Bitte melde dich erneut an.';
        error_log("DEBUG: REDIRECT - Session ungültig (normaler Modus)");
        header('Location: ' . get_redirect_url());
        exit;
    }
} else {
    error_log("DEBUG: Weder standalone_mode noch member_id gesetzt!");
}

// Externe Teilnehmer-Session prüfen
$external_session = get_external_participant_session();
$is_external_participant = ($external_session !== null);

error_log("DEBUG: current_user ist " . ($current_user ? "GESETZT" : "NULL"));
error_log("DEBUG: is_external_participant ist " . ($is_external_participant ? "TRUE" : "FALSE"));

// Mindestens einer muss identifiziert sein
if (!$current_user && !$is_external_participant) {
    // Weder eingeloggt noch als externer Teilnehmer registriert
    $_SESSION['error'] = 'Du musst eingeloggt sein um Termine zu erstellen';
    error_log("DEBUG: REDIRECT - Keine Authentifizierung erkannt");
    header('Location: ' . get_redirect_url());
    exit;
}

// ============================================
// HILFSFUNKTIONEN
// ============================================

/**
 * Prüft ob User berechtigt ist, eine Umfrage zu bearbeiten/löschen
 *
 * @param array $poll Umfrage-Daten
 * @param array $current_user User-Daten
 * @return bool
 */
function can_edit_poll($poll, $current_user) {
    if (!$poll) {
        return false;
    }

    // Ersteller ODER Admin
    $is_creator = ($poll['created_by_member_id'] == $current_user['member_id']);
    $is_admin = in_array($current_user['role'], ['assistenz', 'gf']);

    return $is_creator || $is_admin;
}

/**
 * Holt eine Umfrage mit ID
 *
 * @param PDO $pdo
 * @param int $poll_id
 * @return array|false
 */
function get_poll_by_id($pdo, $poll_id) {
    $stmt = $pdo->prepare("SELECT * FROM svpolls WHERE poll_id = ?");
    $stmt->execute([$poll_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Auto-Cleanup alter Umfragen (>1 Monat nach letztem Datum)
 *
 * @param PDO $pdo
 */
function cleanup_old_polls($pdo) {
    try {
        // Finde Umfragen wo der letzte Terminvorschlag >1 Monat her ist
        $stmt = $pdo->prepare("
            DELETE FROM svpolls
            WHERE poll_id IN (
                SELECT p.poll_id FROM (
                    SELECT polls.poll_id, MAX(poll_dates.suggested_date) as last_date
                    FROM svpolls
                    LEFT JOIN svpoll_dates ON polls.poll_id = poll_dates.poll_id
                    WHERE polls.status != 'finalized'
                    GROUP BY polls.poll_id
                    HAVING last_date < DATE_SUB(NOW(), INTERVAL 1 MONTH)
                ) p
            )
        ");
        $stmt->execute();
    } catch (Exception $e) {
        // Fehler ignorieren - Auto-Cleanup ist nicht kritisch
    }
}

// ============================================
// ACTION HANDLING
// ============================================

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ====== NEUE UMFRAGE ERSTELLEN ======
        case 'create_poll':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $target_type = $_POST['target_type'] ?? 'list';
            $participant_ids = $_POST['participant_ids'] ?? [];

            if (empty($title)) {
                $_SESSION['error'] = 'Bitte gib einen Titel ein';
                header('Location: ' . get_redirect_url());
                exit;
            }

            // Teilnehmer nur bei target_type='list' erforderlich
            if ($target_type === 'list' && empty($participant_ids)) {
                $_SESSION['error'] = 'Bitte wähle mindestens einen Teilnehmer aus';
                header('Location: ' . get_redirect_url());
                exit;
            }

            // Umfrage erstellen (meeting_id wird später beim Finalisieren gesetzt)
            // access_token wird automatisch via Trigger generiert wenn target_type='individual'
            $stmt = $pdo->prepare("
                INSERT INTO svpolls (title, description, location, created_by_member_id, meeting_id, target_type, status, created_at)
                VALUES (?, ?, ?, ?, NULL, ?, 'open', NOW())
            ");
            $stmt->execute([$title, $description, $location, $current_user['member_id'], $target_type]);
            $poll_id = $pdo->lastInsertId();

            // Teilnehmer nur bei target_type='list' hinzufügen
            if ($target_type === 'list') {
                $stmt = $pdo->prepare("
                    INSERT INTO svpoll_participants (poll_id, member_id)
                    VALUES (?, ?)
                ");
                foreach ($participant_ids as $member_id) {
                    $stmt->execute([$poll_id, intval($member_id)]);
                }
            }

            // Terminvorschläge hinzufügen (bis zu 20)
            for ($i = 1; $i <= 20; $i++) {
                $date = $_POST["date_$i"] ?? '';
                $time_start = $_POST["time_start_$i"] ?? '';
                $time_end = $_POST["time_end_$i"] ?? '';

                // Termin speichern wenn mindestens Datum vorhanden
                if (!empty($date)) {
                    // Wenn keine Startzeit angegeben, 00:00:00 verwenden
                    $suggested_datetime = $date . ' ' . (!empty($time_start) ? $time_start : '00:00:00');
                    $suggested_end = null;

                    // Ende-Zeit nur setzen wenn auch vorhanden
                    if (!empty($time_end)) {
                        $suggested_end = $date . ' ' . $time_end;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO svpoll_dates (poll_id, suggested_date, suggested_end_date, sort_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$poll_id, $suggested_datetime, $suggested_end, $i]);
                }
            }

            // Einladungsmail senden (optional)
            $success_message = 'Terminumfrage erfolgreich erstellt!';
            if (!empty($_POST['send_invitation_mail'])) {
                try {
                    $host_url_base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                    $sent_count = send_poll_invitation($pdo, $poll_id, $host_url_base);

                    if ($sent_count > 0) {
                        $success_message .= " $sent_count Einladungs-E-Mails wurden versendet.";
                    } else {
                        $success_message .= " (Keine E-Mails versendet - evtl. haben Teilnehmer keine E-Mail-Adressen)";
                    }
                } catch (Exception $e) {
                    error_log("Einladungsmail-Versand fehlgeschlagen: " . $e->getMessage());
                    $success_message .= " (E-Mail-Versand fehlgeschlagen)";
                }
            }

            $_SESSION['success'] = $success_message;
            header('Location: ' . get_redirect_url('&view=poll&poll_id=' . $poll_id));
            exit;

        // ====== ABSTIMMUNG ABGEBEN ======
        case 'submit_vote':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_poll_by_id($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: ' . get_redirect_url());
                exit;
            }

            if ($poll['status'] !== 'open') {
                $_SESSION['error'] = 'Diese Umfrage ist geschlossen';
                header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
                exit;
            }

            // Teilnehmer ermitteln (Member oder Extern)
            $participant = get_current_participant($current_user, $pdo, 'termine', $poll_id);

            if ($participant['type'] === 'none') {
                $_SESSION['error'] = 'Sie müssen sich registrieren um abzustimmen';
                header('Location: terminplanung_standalone.php?poll_id=' . $poll_id);
                exit;
            }

            // IDs je nach Teilnehmer-Typ setzen
            $member_id = ($participant['type'] === 'member') ? $participant['id'] : null;
            $external_id = ($participant['type'] === 'external') ? $participant['id'] : null;

            // Bestehende Antworten des Teilnehmers löschen
            if ($member_id) {
                $stmt = $pdo->prepare("DELETE FROM svpoll_responses WHERE poll_id = ? AND member_id = ?");
                $stmt->execute([$poll_id, $member_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM svpoll_responses WHERE poll_id = ? AND external_participant_id = ?");
                $stmt->execute([$poll_id, $external_id]);
            }

            // Neue Antworten speichern
            $stmt = $pdo->prepare("
                INSERT INTO svpoll_responses (poll_id, date_id, member_id, external_participant_id, vote, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            foreach ($_POST as $key => $value) {
                if (strpos($key, 'vote_') === 0) {
                    $date_id = intval(str_replace('vote_', '', $key));

                    // Leere Werte überspringen (User hat nicht abgestimmt)
                    if ($value === '' || $value === null) {
                        continue;
                    }

                    $vote = intval($value);

                    // Nur gültige Votes speichern (-1, 0, 1)
                    if (in_array($vote, [-1, 0, 1], true)) {
                        $stmt->execute([$poll_id, $date_id, $member_id, $external_id, $vote]);
                    }
                }
            }

            $_SESSION['success'] = 'Deine Abstimmung wurde gespeichert!';

            // Redirect je nach Teilnehmer-Typ
            if ($current_user) {
                header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
            } else {
                header('Location: terminplanung_standalone.php?poll_id=' . $poll_id);
            }
            exit;

        // ====== FINALEN TERMIN FESTLEGEN ======
        case 'finalize_poll':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $final_date_id = intval($_POST['final_date_id'] ?? 0);

            $poll = get_poll_by_id($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: ' . get_redirect_url());
                exit;
            }

            // Berechtigung prüfen
            if (!can_edit_poll($poll, $current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
                exit;
            }

            // Umfrage finalisieren
            $stmt = $pdo->prepare("
                UPDATE svpolls
                SET status = 'finalized', final_date_id = ?, finalized_at = NOW()
                WHERE poll_id = ?
            ");
            $stmt->execute([$final_date_id, $poll_id]);

            // Optional: Meeting erstellen (wenn Checkbox aktiviert)
            $create_meeting = !empty($_POST['create_meeting']);
            $meeting_created = false;
            if ($create_meeting && !empty($final_date_id)) {
                $date_stmt = $pdo->prepare("SELECT * FROM svpoll_dates WHERE date_id = ?");
                $date_stmt->execute([$final_date_id]);
                $final_date = $date_stmt->fetch(PDO::FETCH_ASSOC);

                if ($final_date) {
                    // Ort: Bevorzuge Poll-Ort, fallback auf Datum-Ort
                    $meeting_location = !empty($poll['location']) ? $poll['location'] : $final_date['location'];

                    // Meeting erstellen
                    $meeting_stmt = $pdo->prepare("
                        INSERT INTO svmeetings
                        (meeting_name, meeting_date, expected_end_date, location, invited_by_member_id, status, created_at)
                        VALUES (?, ?, ?, ?, ?, 'preparation', NOW())
                    ");
                    $meeting_stmt->execute([
                        $poll['title'],
                        $final_date['suggested_date'],
                        $final_date['suggested_end_date'],
                        $meeting_location,
                        $current_user['member_id']
                    ]);
                    $new_meeting_id = $pdo->lastInsertId();

                    // Verknüpfung setzen
                    $pdo->prepare("UPDATE svpolls SET meeting_id = ? WHERE poll_id = ?")->execute([$new_meeting_id, $poll_id]);

                    // Teilnehmer vom Poll zum Meeting hinzufügen
                    $participants_stmt = $pdo->prepare("
                        SELECT DISTINCT member_id
                        FROM svpoll_participants
                        WHERE poll_id = ?
                    ");
                    $participants_stmt->execute([$poll_id]);
                    $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $invite_stmt = $pdo->prepare("
                        INSERT INTO svmeeting_participants (meeting_id, member_id, status)
                        VALUES (?, ?, 'invited')
                    ");
                    foreach ($participants as $participant) {
                        $invite_stmt->execute([$new_meeting_id, $participant['member_id']]);
                    }

                    $meeting_created = true;
                }
            }

            // E-Mail-Benachrichtigung an Teilnehmer
            $notification_recipients = $_POST['notification_recipients'] ?? 'voters';
            $success_message = 'Finaler Termin wurde festgelegt!';
            if ($meeting_created) {
                $success_message .= ' Ein Meeting wurde automatisch erstellt.';
            }

            try {
                // Basis-URL für Links bestimmen
                $host_url_base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                $sent_count = send_poll_finalization_notification($pdo, $poll_id, $final_date_id, $host_url_base, $notification_recipients);

                if ($sent_count > 0) {
                    $success_message .= " $sent_count Benachrichtigungs-E-Mails wurden versendet.";
                } elseif ($notification_recipients !== 'none') {
                    $success_message .= " (Keine E-Mails versendet - evtl. haben Teilnehmer keine E-Mail-Adressen)";
                }
            } catch (Exception $e) {
                // E-Mail-Fehler nicht kritisch - Termin wurde trotzdem gesetzt
                error_log("E-Mail-Versand fehlgeschlagen: " . $e->getMessage());
                if ($notification_recipients !== 'none') {
                    $success_message .= " (E-Mail-Versand fehlgeschlagen)";
                }
            }

            // Erinnerungsmail speichern (falls aktiviert)
            if (!empty($_POST['send_reminder'])) {
                $reminder_days = intval($_POST['reminder_days'] ?? 1);
                if ($reminder_days > 0 && $reminder_days <= 30) {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE svpolls
                            SET reminder_enabled = 1,
                                reminder_days = ?,
                                reminder_recipients = ?,
                                reminder_sent = 0
                            WHERE poll_id = ?
                        ");
                        $stmt->execute([$reminder_days, $notification_recipients, $poll_id]);
                        $success_message .= " Erinnerungsmail wird $reminder_days Tag(e) vor dem Termin versendet.";
                    } catch (Exception $e) {
                        error_log("Erinnerungsmail-Konfiguration fehlgeschlagen: " . $e->getMessage());
                        $success_message .= " (Erinnerungsmail-Konfiguration fehlgeschlagen)";
                    }
                }
            }

            $_SESSION['success'] = $success_message;
            header('Location: ' . get_redirect_url('&view=poll&poll_id=' . $poll_id));
            exit;

        // ====== UMFRAGE SCHLIESSEN (ohne Finalisierung) ======
        case 'close_poll':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_poll_by_id($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: ' . get_redirect_url());
                exit;
            }

            // Berechtigung prüfen
            if (!can_edit_poll($poll, $current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE svpolls SET status = 'closed' WHERE poll_id = ?");
            $stmt->execute([$poll_id]);

            $_SESSION['success'] = 'Umfrage wurde geschlossen';
            header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
            exit;

        // ====== UMFRAGE WIEDER ÖFFNEN ======
        case 'reopen_poll':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_poll_by_id($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: ' . get_redirect_url());
                exit;
            }

            // Berechtigung prüfen
            if (!can_edit_poll($poll, $current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE svpolls SET status = 'open', final_date_id = NULL, finalized_at = NULL WHERE poll_id = ?");
            $stmt->execute([$poll_id]);

            $_SESSION['success'] = 'Umfrage wurde wieder geöffnet';
            header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
            exit;

        // ====== UMFRAGE LÖSCHEN ======
        case 'delete_poll':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_poll_by_id($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: ' . get_redirect_url());
                exit;
            }

            // Berechtigung prüfen
            if (!can_edit_poll($poll, $current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: ' . get_redirect_url());
                exit;
            }

            // Löschen (CASCADE löscht auch poll_dates und poll_responses)
            $stmt = $pdo->prepare("DELETE FROM svpolls WHERE poll_id = ?");
            $stmt->execute([$poll_id]);

            $_SESSION['success'] = 'Umfrage wurde gelöscht';
            header('Location: index.php?tab=termine');
            exit;

        default:
            $_SESSION['error'] = 'Unbekannte Aktion';
            header('Location: index.php?tab=termine');
            exit;
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'Ein Fehler ist aufgetreten: ' . $e->getMessage();
    header('Location: index.php?tab=termine');
    exit;
}

// Auto-Cleanup ausführen (einmal pro Session)
if (!isset($_SESSION['polls_cleaned'])) {
    cleanup_old_polls($pdo);
    $_SESSION['polls_cleaned'] = true;
}
?>
