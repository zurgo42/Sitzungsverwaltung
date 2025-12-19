<?php
/**
 * process_opinion.php - Meinungsbild-Tool Backend
 * Erstellt: 2025-11-18
 *
 * Business Logic für Meinungsbilder/Umfragen
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

session_start();

// Authentifizierung (Member ODER Externer Teilnehmer)
$current_user = null;
$is_authenticated = false;

if (isset($_SESSION['member_id'])) {
    $current_user = get_member_by_id($pdo, $_SESSION['member_id']);
    $is_authenticated = $current_user !== false;
}

// Externe Teilnehmer-Session prüfen
$external_session = get_external_participant_session();
$is_external_participant = ($external_session !== null);

// ============================================
// HILFSFUNKTIONEN
// ============================================

/**
 * Prüft ob User Admin ist
 */
function is_admin($user) {
    if (!$user) return false;
    return in_array($user['role'], ['assistenz', 'gf']);
}

/**
 * Prüft ob User Ersteller der Umfrage ist
 */
function is_creator($poll, $user) {
    if (!$poll || !$user) return false;
    return $poll['creator_member_id'] == $user['member_id'];
}

/**
 * Lädt eine Umfrage
 */
function get_opinion_poll($pdo, $poll_id) {
    $stmt = $pdo->prepare("SELECT * FROM svopinion_polls WHERE poll_id = ? AND status != 'deleted'");
    $stmt->execute([$poll_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Lädt Antwortoptionen einer Umfrage
 */
function get_poll_options($pdo, $poll_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM svopinion_poll_options
        WHERE poll_id = ?
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$poll_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// ACTION HANDLING
// ============================================

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ====== NEUE UMFRAGE ERSTELLEN ======
        case 'create_opinion':
            if (!$is_authenticated) {
                $_SESSION['error'] = 'Bitte melde dich an';
                header('Location: index.php?tab=opinion');
                exit;
            }

            $title = trim($_POST['title'] ?? '');
            $target_type = $_POST['target_type'] ?? 'individual';
            $list_id = !empty($_POST['list_id']) ? intval($_POST['list_id']) : null;
            $opinion_participant_ids = $_POST['opinion_participant_ids'] ?? [];
            $template_id = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
            $allow_multiple = !empty($_POST['allow_multiple']) ? 1 : 0;
            $is_anonymous = !empty($_POST['is_anonymous']) ? 1 : 0;
            $duration_days = intval($_POST['duration_days'] ?? 14);
            $show_intermediate_after_days = intval($_POST['show_intermediate_after_days'] ?? 7);
            $delete_after_days = intval($_POST['delete_after_days'] ?? 30);

            if (empty($title)) {
                $_SESSION['error'] = 'Bitte gib eine Frage ein';
                header('Location: index.php?tab=opinion');
                exit;
            }

            // Umfrage erstellen
            $stmt = $pdo->prepare("
                INSERT INTO svopinion_polls
                (title, creator_member_id, target_type, list_id, template_id,
                 allow_multiple_answers, is_anonymous, duration_days,
                 show_intermediate_after_days, delete_after_days, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $title, $current_user['member_id'], $target_type, $list_id,
                $template_id, $allow_multiple, $is_anonymous, $duration_days,
                $show_intermediate_after_days, $delete_after_days
            ]);
            $poll_id = $pdo->lastInsertId();

            // Access-Token für individual-Typ generieren
            if ($target_type === 'individual') {
                $access_token = bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE svopinion_polls SET access_token = ? WHERE poll_id = ?")
                    ->execute([$access_token, $poll_id]);
            }

            // Antwortoptionen hinzufügen
            $option_count = 0;

            // Custom Optionen (Freitext)
            for ($i = 1; $i <= 10; $i++) {
                $option_text = trim($_POST["custom_option_$i"] ?? '');
                if (!empty($option_text)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO svopinion_poll_options (poll_id, option_text, sort_order)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$poll_id, $option_text, $option_count++]);
                }
            }

            // Wenn Template gewählt und keine custom options, Template-Optionen verwenden
            if ($template_id && $option_count == 0) {
                $template_stmt = $pdo->prepare("SELECT * FROM svopinion_answer_templates WHERE template_id = ?");
                $template_stmt->execute([$template_id]);
                $template = $template_stmt->fetch(PDO::FETCH_ASSOC);

                if ($template) {
                    for ($i = 1; $i <= 10; $i++) {
                        $option_text = $template["option_$i"];
                        if (!empty($option_text)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO svopinion_poll_options (poll_id, option_text, sort_order)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$poll_id, $option_text, $option_count++]);
                        }
                    }
                }
            }

            // Teilnehmer hinzufügen (bei list-Typ)
            if ($target_type === 'list') {
                if (!empty($opinion_participant_ids)) {
                    // Direkt ausgewählte Teilnehmer
                    $invite_stmt = $pdo->prepare("
                        INSERT INTO svopinion_poll_participants (poll_id, member_id)
                        VALUES (?, ?)
                    ");
                    foreach ($opinion_participant_ids as $member_id) {
                        $invite_stmt->execute([$poll_id, intval($member_id)]);
                    }
                } elseif ($list_id) {
                    // Fallback: Hole Teilnehmer vom Meeting (legacy)
                    $participants_stmt = $pdo->prepare("
                        SELECT DISTINCT member_id
                        FROM svmeeting_participants
                        WHERE meeting_id = ?
                    ");
                    $participants_stmt->execute([$list_id]);
                    $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $invite_stmt = $pdo->prepare("
                        INSERT INTO svopinion_poll_participants (poll_id, member_id)
                        VALUES (?, ?)
                    ");
                    foreach ($participants as $participant) {
                        $invite_stmt->execute([$poll_id, $participant['member_id']]);
                    }
                }
            }

            // E-Mail-Versand
            if (!empty($_POST['send_email'])) {
                $email_target = $_POST['email_target'] ?? 'creator';
                // TODO: E-Mail-Funktionen implementieren
            }

            $_SESSION['success'] = 'Meinungsbild erfolgreich erstellt!';
            header('Location: index.php?tab=opinion&view=detail&poll_id=' . $poll_id);
            exit;

        // ====== ANTWORT ABGEBEN ======
        case 'submit_response':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_opinion_poll($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: index.php?tab=opinion');
                exit;
            }

            // Prüfen ob Umfrage noch aktiv
            if ($poll['status'] !== 'active' || strtotime($poll['ends_at']) < time()) {
                $_SESSION['error'] = 'Diese Umfrage ist bereits beendet';
                header('Location: index.php?tab=opinion&view=detail&poll_id=' . $poll_id);
                exit;
            }

            // Teilnehmer ermitteln (Member, Extern, oder alt-session_token)
            $participant = get_current_participant($current_user, $pdo, 'meinungsbild', $poll_id);

            $member_id = ($participant['type'] === 'member') ? $participant['id'] : null;
            $external_id = ($participant['type'] === 'external') ? $participant['id'] : null;
            $session_token = ($participant['type'] === 'none' && !$is_authenticated) ? get_or_create_session_token() : null;

            $selected_options = $_POST['options'] ?? [];
            $free_text = trim($_POST['free_text'] ?? '');
            $force_anonymous = !empty($_POST['force_anonymous']) ? 1 : 0;

            if (empty($selected_options)) {
                $_SESSION['error'] = 'Bitte wähle mindestens eine Antwort';
                header('Location: index.php?tab=opinion&view=detail&poll_id=' . $poll_id);
                exit;
            }

            // Prüfen ob bereits geantwortet
            if ($member_id !== null) {
                // Logged-in Member: Nur nach member_id suchen
                $check_stmt = $pdo->prepare("
                    SELECT response_id FROM svopinion_responses
                    WHERE poll_id = ? AND member_id = ?
                ");
                $check_stmt->execute([$poll_id, $member_id]);
            } else if ($external_id !== null) {
                // Externer Teilnehmer: Nur nach external_participant_id suchen
                $check_stmt = $pdo->prepare("
                    SELECT response_id FROM svopinion_responses
                    WHERE poll_id = ? AND external_participant_id = ?
                ");
                $check_stmt->execute([$poll_id, $external_id]);
            } else if ($session_token !== null) {
                // Anonymous User (alt): Nur nach session_token suchen
                $check_stmt = $pdo->prepare("
                    SELECT response_id FROM svopinion_responses
                    WHERE poll_id = ? AND session_token = ?
                ");
                $check_stmt->execute([$poll_id, $session_token]);
            } else {
                $check_stmt = null;
            }
            $existing = $check_stmt ? $check_stmt->fetch() : false;

            if ($existing) {
                // Editieren erlaubt wenn Ersteller und nur 1 Antwort
                $count_stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM svopinion_responses WHERE poll_id = ?");
                $count_stmt->execute([$poll_id]);
                $count = $count_stmt->fetch()['cnt'];

                if ($count > 1 || !is_creator($poll, $current_user)) {
                    $_SESSION['error'] = 'Sie haben bereits geantwortet';
                    header('Location: index.php?tab=opinion&view=detail&poll_id=' . $poll_id);
                    exit;
                }

                // Update bestehende Antwort
                $response_id = $existing['response_id'];
                $pdo->prepare("UPDATE svopinion_responses SET free_text = ?, force_anonymous = ? WHERE response_id = ?")
                    ->execute([$free_text, $force_anonymous, $response_id]);

                // Optionen löschen und neu einfügen
                $pdo->prepare("DELETE FROM svopinion_response_options WHERE response_id = ?")->execute([$response_id]);
            } else {
                // Neue Antwort erstellen
                $stmt = $pdo->prepare("
                    INSERT INTO svopinion_responses (poll_id, member_id, external_participant_id, session_token, free_text, force_anonymous)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$poll_id, $member_id, $external_id, $session_token, $free_text, $force_anonymous]);
                $response_id = $pdo->lastInsertId();
            }

            // Gewählte Optionen speichern
            $option_stmt = $pdo->prepare("
                INSERT INTO svopinion_response_options (response_id, option_id)
                VALUES (?, ?)
            ");
            foreach ($selected_options as $option_id) {
                $option_stmt->execute([$response_id, intval($option_id)]);
            }

            $_SESSION['success'] = 'Deine Antwort wurde gespeichert!';

            // Redirect je nach Teilnehmer-Typ
            if ($current_user) {
                header('Location: index.php?tab=opinion&view=results&poll_id=' . $poll_id);
            } else {
                header('Location: opinion_standalone.php?poll_id=' . $poll_id);
            }
            exit;

        // ====== UMFRAGE LÖSCHEN ======
        case 'delete_opinion':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_opinion_poll($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: index.php?tab=opinion');
                exit;
            }

            // Berechtigung prüfen
            if (!is_creator($poll, $current_user) && !is_admin($current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: index.php?tab=opinion');
                exit;
            }

            // Soft-Delete
            $pdo->prepare("UPDATE svopinion_polls SET status = 'deleted' WHERE poll_id = ?")
                ->execute([$poll_id]);

            $_SESSION['success'] = 'Meinungsbild wurde gelöscht';
            header('Location: index.php?tab=opinion');
            exit;

        // ====== UMFRAGE BEENDEN ======
        case 'end_opinion':
            $poll_id = intval($_POST['poll_id'] ?? 0);
            $poll = get_opinion_poll($pdo, $poll_id);

            if (!$poll) {
                $_SESSION['error'] = 'Umfrage nicht gefunden';
                header('Location: index.php?tab=opinion');
                exit;
            }

            // Berechtigung prüfen
            if (!is_creator($poll, $current_user) && !is_admin($current_user)) {
                $_SESSION['error'] = 'Keine Berechtigung';
                header('Location: index.php?tab=opinion&view=detail&poll_id=' . $poll_id);
                exit;
            }

            $pdo->prepare("UPDATE svopinion_polls SET status = 'ended', ends_at = NOW() WHERE poll_id = ?")
                ->execute([$poll_id]);

            $_SESSION['success'] = 'Meinungsbild wurde beendet';
            header('Location: index.php?tab=opinion&view=results&poll_id=' . $poll_id);
            exit;

        default:
            $_SESSION['error'] = 'Ungültige Aktion';
            header('Location: index.php?tab=opinion');
            exit;
    }

} catch (Exception $e) {
    error_log("Opinion Poll Error: " . $e->getMessage());
    $_SESSION['error'] = 'Ein Fehler ist aufgetreten: ' . $e->getMessage();
    header('Location: index.php?tab=opinion');
    exit;
}
