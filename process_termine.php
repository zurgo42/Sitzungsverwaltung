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
    $stmt = $pdo->prepare("SELECT * FROM polls WHERE poll_id = ?");
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
            DELETE FROM polls
            WHERE poll_id IN (
                SELECT p.poll_id FROM (
                    SELECT polls.poll_id, MAX(poll_dates.suggested_date) as last_date
                    FROM polls
                    LEFT JOIN poll_dates ON polls.poll_id = poll_dates.poll_id
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
            $meeting_id = !empty($_POST['meeting_id']) ? intval($_POST['meeting_id']) : null;
            $participant_ids = $_POST['participant_ids'] ?? [];

            if (empty($title)) {
                $_SESSION['error'] = 'Bitte geben Sie einen Titel ein';
                header('Location: index.php?tab=termine');
                exit;
            }

            if (empty($participant_ids)) {
                $_SESSION['error'] = 'Bitte wählen Sie mindestens einen Teilnehmer aus';
                header('Location: index.php?tab=termine');
                exit;
            }

            // Umfrage erstellen
            $stmt = $pdo->prepare("
                INSERT INTO polls (title, description, created_by_member_id, meeting_id, status, created_at)
                VALUES (?, ?, ?, ?, 'open', NOW())
            ");
            $stmt->execute([$title, $description, $current_user['member_id'], $meeting_id]);
            $poll_id = $pdo->lastInsertId();

            // Teilnehmer hinzufügen
            $stmt = $pdo->prepare("
                INSERT INTO poll_participants (poll_id, member_id)
                VALUES (?, ?)
            ");
            foreach ($participant_ids as $member_id) {
                $stmt->execute([$poll_id, intval($member_id)]);
            }

            // Terminvorschläge hinzufügen (bis zu 20)
            for ($i = 1; $i <= 20; $i++) {
                $date = $_POST["date_$i"] ?? '';
                $time_start = $_POST["time_start_$i"] ?? '';
                $time_end = $_POST["time_end_$i"] ?? '';

                if (!empty($date) && !empty($time_start)) {
                    $suggested_datetime = $date . ' ' . $time_start;
                    $suggested_end = null;

                    if (!empty($time_end)) {
                        $suggested_end = $date . ' ' . $time_end;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO poll_dates (poll_id, suggested_date, suggested_end_date, sort_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$poll_id, $suggested_datetime, $suggested_end, $i]);
                }
            }

            $_SESSION['success'] = 'Terminumfrage erfolgreich erstellt!';
            header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
            exit;

        // ====== ABSTIMMUNG ABGEBEN ======
        case 'submit_vote':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_poll_by_id($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: index.php?tab=termine');
                exit;
            }

            if ($poll['status'] !== 'open') {
                $_SESSION['error'] = 'Diese Umfrage ist geschlossen';
                header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
                exit;
            }

            // Bestehende Antworten des Users löschen
            $stmt = $pdo->prepare("
                DELETE FROM poll_responses
                WHERE poll_id = ? AND member_id = ?
            ");
            $stmt->execute([$poll_id, $current_user['member_id']]);

            // Neue Antworten speichern
            $stmt = $pdo->prepare("
                INSERT INTO poll_responses (poll_id, date_id, member_id, vote, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            foreach ($_POST as $key => $value) {
                if (strpos($key, 'vote_') === 0) {
                    $date_id = intval(str_replace('vote_', '', $key));
                    $vote = intval($value);

                    // Nur gültige Votes speichern (-1, 0, 1)
                    if (in_array($vote, [-1, 0, 1])) {
                        $stmt->execute([$poll_id, $date_id, $current_user['member_id'], $vote]);
                    }
                }
            }

            $_SESSION['success'] = 'Ihre Abstimmung wurde gespeichert!';
            header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
            exit;

        // ====== FINALEN TERMIN FESTLEGEN ======
        case 'finalize_poll':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $final_date_id = intval($_POST['final_date_id'] ?? 0);

            $poll = get_poll_by_id($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: index.php?tab=termine');
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
                UPDATE polls
                SET status = 'finalized', final_date_id = ?, finalized_at = NOW()
                WHERE poll_id = ?
            ");
            $stmt->execute([$final_date_id, $poll_id]);

            // Optional: Meeting erstellen wenn meeting_id noch nicht gesetzt
            if (empty($poll['meeting_id']) && !empty($final_date_id)) {
                $date_stmt = $pdo->prepare("SELECT * FROM poll_dates WHERE date_id = ?");
                $date_stmt->execute([$final_date_id]);
                $final_date = $date_stmt->fetch(PDO::FETCH_ASSOC);

                if ($final_date) {
                    // Meeting erstellen
                    $meeting_stmt = $pdo->prepare("
                        INSERT INTO meetings
                        (meeting_name, meeting_date, expected_end_date, location, invited_by_member_id, status, created_at)
                        VALUES (?, ?, ?, ?, ?, 'preparation', NOW())
                    ");
                    $meeting_stmt->execute([
                        $poll['title'],
                        $final_date['suggested_date'],
                        $final_date['suggested_end_date'],
                        $final_date['location'],
                        $current_user['member_id']
                    ]);
                    $new_meeting_id = $pdo->lastInsertId();

                    // Verknüpfung setzen
                    $pdo->prepare("UPDATE polls SET meeting_id = ? WHERE poll_id = ?")->execute([$new_meeting_id, $poll_id]);
                }
            }

            // E-Mail-Benachrichtigung an Teilnehmer
            try {
                // Basis-URL für Links bestimmen
                $host_url_base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                $sent_count = send_poll_finalization_notification($pdo, $poll_id, $final_date_id, $host_url_base);

                if ($sent_count > 0) {
                    $_SESSION['success'] = "Finaler Termin wurde festgelegt! $sent_count Benachrichtigungs-E-Mails wurden versendet.";
                } else {
                    $_SESSION['success'] = 'Finaler Termin wurde festgelegt!';
                }
            } catch (Exception $e) {
                // E-Mail-Fehler nicht kritisch - Termin wurde trotzdem gesetzt
                error_log("E-Mail-Versand fehlgeschlagen: " . $e->getMessage());
                $_SESSION['success'] = 'Finaler Termin wurde festgelegt! (E-Mail-Versand fehlgeschlagen)';
            }
            header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
            exit;

        // ====== UMFRAGE SCHLIESSEN (ohne Finalisierung) ======
        case 'close_poll':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_poll_by_id($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: index.php?tab=termine');
                exit;
            }

            // Berechtigung prüfen
            if (!can_edit_poll($poll, $current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE polls SET status = 'closed' WHERE poll_id = ?");
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
                header('Location: index.php?tab=termine');
                exit;
            }

            // Berechtigung prüfen
            if (!can_edit_poll($poll, $current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: index.php?tab=termine&view=poll&poll_id=' . $poll_id);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE polls SET status = 'open', final_date_id = NULL, finalized_at = NULL WHERE poll_id = ?");
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
                header('Location: index.php?tab=termine');
                exit;
            }

            // Berechtigung prüfen
            if (!can_edit_poll($poll, $current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: index.php?tab=termine');
                exit;
            }

            // Löschen (CASCADE löscht auch poll_dates und poll_responses)
            $stmt = $pdo->prepare("DELETE FROM polls WHERE poll_id = ?");
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
