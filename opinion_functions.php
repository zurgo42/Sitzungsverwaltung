<?php
/**
 * opinion_functions.php - Hilfsfunktionen für Meinungsbild-Tool
 * Erstellt: 2025-11-18
 */

// Member-Functions mit Adapter-Support laden
require_once __DIR__ . '/member_functions.php';

/**
 * Lädt alle aktiven Meinungsbilder
 */
function get_all_opinion_polls($pdo, $member_id = null, $include_public = true) {
    // Mitglieder über Adapter laden und zu Polls hinzufügen
    $sql = "
        SELECT op.*,
               (SELECT COUNT(*) FROM svopinion_responses WHERE poll_id = op.poll_id) as response_count
        FROM svopinion_polls op
        WHERE op.status != 'deleted'
    ";

    $params = [];

    if ($member_id) {
        $sql .= " AND (
            op.creator_member_id = ?
            OR op.target_type = 'public'
            OR EXISTS (
                SELECT 1 FROM svopinion_poll_participants opp
                WHERE opp.poll_id = op.poll_id AND opp.member_id = ?
            )
        )";
        $params = [$member_id, $member_id];
    } elseif (!$include_public) {
        return [];
    }

    $sql .= " ORDER BY op.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Creator-Namen über Adapter nachladen
    foreach ($polls as &$poll) {
        if ($poll['creator_member_id']) {
            $creator = get_member_by_id($pdo, $poll['creator_member_id']);
            if ($creator) {
                $poll['first_name'] = $creator['first_name'];
                $poll['last_name'] = $creator['last_name'];
            }
        }
    }

    return $polls;
}

/**
 * Lädt Details einer Umfrage mit Optionen
 */
function get_opinion_poll_with_options($pdo, $poll_id) {
    $stmt = $pdo->prepare("
        SELECT op.*
        FROM svopinion_polls op
        WHERE op.poll_id = ? AND op.status != 'deleted'
    ");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        return null;
    }

    // Creator-Daten über Adapter laden
    if ($poll['creator_member_id']) {
        $creator = get_member_by_id($pdo, $poll['creator_member_id']);
        if ($creator) {
            $poll['first_name'] = $creator['first_name'];
            $poll['last_name'] = $creator['last_name'];
            $poll['email'] = $creator['email'];
        }
    }

    // Optionen laden
    $stmt = $pdo->prepare("
        SELECT * FROM svopinion_poll_options
        WHERE poll_id = ?
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$poll_id]);
    $poll['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $poll;
}

/**
 * Prüft ob User an einer Umfrage teilnehmen darf
 */
function can_participate($poll, $member_id = null) {
    if ($poll['target_type'] === 'public') {
        return true;
    }

    if ($poll['target_type'] === 'individual') {
        // Jeder mit dem Link darf teilnehmen
        return true;
    }

    if ($poll['target_type'] === 'list' && $member_id) {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT 1 FROM svopinion_poll_participants
            WHERE poll_id = ? AND member_id = ?
        ");
        $stmt->execute([$poll['poll_id'], $member_id]);
        return $stmt->fetch() !== false;
    }

    return false;
}

/**
 * Prüft ob User bereits geantwortet hat
 */
function has_responded($pdo, $poll_id, $member_id = null, $session_token = null) {
    // Spezielle Behandlung für NULL-Werte in der WHERE-Klausel
    if ($member_id !== null) {
        // Logged-in User: Nur nach member_id suchen
        $stmt = $pdo->prepare("
            SELECT response_id FROM svopinion_responses
            WHERE poll_id = ? AND member_id = ?
        ");
        $stmt->execute([$poll_id, $member_id]);
    } else if ($session_token !== null) {
        // Anonymous User: Nur nach session_token suchen
        $stmt = $pdo->prepare("
            SELECT response_id FROM svopinion_responses
            WHERE poll_id = ? AND session_token = ?
        ");
        $stmt->execute([$poll_id, $session_token]);
    } else {
        // Weder member_id noch session_token: Nicht geantwortet
        return false;
    }

    return $stmt->fetch() !== false;
}

/**
 * Lädt die Antwort eines Users
 */
function get_user_response($pdo, $poll_id, $member_id = null, $session_token = null) {
    // Spezielle Behandlung für NULL-Werte in der WHERE-Klausel
    if ($member_id !== null) {
        // Logged-in User: Nur nach member_id suchen
        $stmt = $pdo->prepare("
            SELECT r.*,
                   GROUP_CONCAT(oro.option_id ORDER BY opo.sort_order) as selected_option_ids,
                   GROUP_CONCAT(opo.option_text ORDER BY opo.sort_order SEPARATOR ', ') as selected_options_text
            FROM svopinion_responses r
            LEFT JOIN svopinion_response_options oro ON r.response_id = oro.response_id
            LEFT JOIN svopinion_poll_options opo ON oro.option_id = opo.option_id
            WHERE r.poll_id = ? AND r.member_id = ?
            GROUP BY r.response_id
        ");
        $stmt->execute([$poll_id, $member_id]);
    } else if ($session_token !== null) {
        // Anonymous User: Nur nach session_token suchen
        $stmt = $pdo->prepare("
            SELECT r.*,
                   GROUP_CONCAT(oro.option_id ORDER BY opo.sort_order) as selected_option_ids,
                   GROUP_CONCAT(opo.option_text ORDER BY opo.sort_order SEPARATOR ', ') as selected_options_text
            FROM svopinion_responses r
            LEFT JOIN svopinion_response_options oro ON r.response_id = oro.response_id
            LEFT JOIN svopinion_poll_options opo ON oro.option_id = opo.option_id
            WHERE r.poll_id = ? AND r.session_token = ?
            GROUP BY r.response_id
        ");
        $stmt->execute([$poll_id, $session_token]);
    } else {
        // Weder member_id noch session_token: Keine Antwort möglich
        return null;
    }

    $response = $stmt->fetch(PDO::FETCH_ASSOC);

    // Keine Antwort gefunden
    if (!$response) {
        return null;
    }

    // selected_options Array aufbauen
    if (!empty($response['selected_option_ids'])) {
        $response['selected_options'] = explode(',', $response['selected_option_ids']);
    } else {
        $response['selected_options'] = [];
    }

    return $response;
}

/**
 * Berechnet Umfrage-Statistiken
 */
function get_opinion_results($pdo, $poll_id) {
    // Gesamtzahl Antworten
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM svopinion_responses WHERE poll_id = ?");
    $stmt->execute([$poll_id]);
    $total_responses = $stmt->fetch()['total'];

    // Pro Option
    $stmt = $pdo->prepare("
        SELECT
            opo.option_id,
            opo.option_text,
            opo.sort_order,
            COUNT(oro.response_option_id) as vote_count,
            ROUND(COUNT(oro.response_option_id) * 100.0 / NULLIF(?, 0), 1) as percentage
        FROM svopinion_poll_options opo
        LEFT JOIN svopinion_response_options oro ON opo.option_id = oro.option_id
        WHERE opo.poll_id = ?
        GROUP BY opo.option_id
        ORDER BY opo.sort_order ASC
    ");
    $stmt->execute([$total_responses, $poll_id]);
    $option_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'total_responses' => $total_responses,
        'option_stats' => $option_stats
    ];
}

/**
 * Lädt alle Antworten (für Admins/Ersteller)
 */
function get_all_responses($pdo, $poll_id, $show_names = false) {
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            GROUP_CONCAT(opo.option_text ORDER BY opo.sort_order SEPARATOR ', ') as selected_options_text
        FROM svopinion_responses r
        LEFT JOIN svopinion_response_options oro ON r.response_id = oro.response_id
        LEFT JOIN svopinion_poll_options opo ON oro.option_id = opo.option_id
        WHERE r.poll_id = ?
        GROUP BY r.response_id
        ORDER BY r.responded_at DESC
    ");
    $stmt->execute([$poll_id]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Member-Daten über Adapter nachladen
    foreach ($responses as &$response) {
        if ($response['member_id']) {
            $member = get_member_by_id($pdo, $response['member_id']);
            if ($member) {
                $response['first_name'] = $member['first_name'];
                $response['last_name'] = $member['last_name'];
            }
        }

        // Namen anonymisieren falls nötig
        if (!$show_names) {
            if ($response['force_anonymous'] || !$response['member_id']) {
                $response['first_name'] = 'Anonym';
                $response['last_name'] = '';
            }
        }
    }

    return $responses;
}

/**
 * Prüft ob Zwischenergebnisse gezeigt werden dürfen
 */
function can_show_intermediate_results($poll) {
    $created = strtotime($poll['created_at']);
    $show_after = $created + ($poll['show_intermediate_after_days'] * 86400);
    return time() >= $show_after;
}

/**
 * Prüft ob Endergebnisse gezeigt werden dürfen
 */
function can_show_final_results($poll, $user = null, $has_responded = false) {
    // Ersteller und Admins dürfen immer sehen
    if ($user) {
        if ($poll['creator_member_id'] == $user['member_id']) {
            return true;
        }
        if (in_array($user['role'], ['assistenz', 'gf'])) {
            return true;
        }
    }

    // Teilnehmer dürfen nach Ablauf sehen (wenn sie geantwortet haben)
    if ($has_responded) {
        if ($poll['status'] === 'ended' || strtotime($poll['ends_at']) < time()) {
            return true;
        }

        // Oder wenn Zwischenergebnisse erlaubt
        return can_show_intermediate_results($poll);
    }

    return false;
}

/**
 * Lädt alle Templates
 */
function get_answer_templates($pdo) {
    $stmt = $pdo->query("SELECT * FROM svopinion_answer_templates ORDER BY template_id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generiert Zugriffs-Link für individual-Umfragen
 */
function get_poll_access_link($poll, $base_url) {
    if ($poll['target_type'] !== 'individual' || empty($poll['access_token'])) {
        return null;
    }

    return rtrim($base_url, '/') . '/index.php?tab=opinion&view=participate&token=' . $poll['access_token'];
}

/**
 * Lädt Umfrage per Access-Token
 */
function get_poll_by_token($pdo, $token) {
    $stmt = $pdo->prepare("
        SELECT * FROM svopinion_polls
        WHERE access_token = ? AND status = 'active'
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
