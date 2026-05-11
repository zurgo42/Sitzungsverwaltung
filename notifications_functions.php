<?php
/**
 * notifications_functions.php - Benachrichtigungs-Funktionen
 *
 * Zentrale Funktionen für das Notification-System
 */

/**
 * Erstellt eine neue Benachrichtigung
 *
 * @param PDO $pdo
 * @param int $member_id Empfänger
 * @param string $type meeting|todo|comment|assignment|reminder|system
 * @param string $title Kurzer Titel
 * @param string $message Nachricht
 * @param string|null $link URL zum Element
 * @param array $related ['meeting_id' => X, 'todo_id' => Y, 'item_id' => Z]
 * @return int notification_id
 */
function create_notification($pdo, $member_id, $type, $title, $message, $link = null, $related = []) {
    $stmt = $pdo->prepare("
        INSERT INTO svnotifications
        (member_id, type, title, message, link, related_meeting_id, related_todo_id, related_item_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $member_id,
        $type,
        $title,
        $message,
        $link,
        $related['meeting_id'] ?? null,
        $related['todo_id'] ?? null,
        $related['item_id'] ?? null
    ]);

    return $pdo->lastInsertId();
}

/**
 * Holt ungelesene Notifications für einen User
 *
 * @param PDO $pdo
 * @param int $member_id
 * @param int $limit Maximale Anzahl
 * @return array
 */
function get_unread_notifications($pdo, $member_id, $limit = 20) {
    $limit = intval($limit); // Sicherstellen dass es ein Integer ist
    $stmt = $pdo->prepare("
        SELECT *
        FROM svnotifications
        WHERE member_id = ? AND is_read = 0
        ORDER BY created_at DESC
        LIMIT $limit
    ");
    $stmt->execute([$member_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Zählt ungelesene Notifications
 *
 * @param PDO $pdo
 * @param int $member_id
 * @return int
 */
function count_unread_notifications($pdo, $member_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM svnotifications
        WHERE member_id = ? AND is_read = 0
    ");
    $stmt->execute([$member_id]);
    return (int) $stmt->fetch()['count'];
}

/**
 * Markiert Notification als gelesen
 *
 * @param PDO $pdo
 * @param int $notification_id
 * @param int $member_id Zur Sicherheit
 * @return bool
 */
function mark_notification_read($pdo, $notification_id, $member_id) {
    $stmt = $pdo->prepare("
        UPDATE svnotifications
        SET is_read = 1
        WHERE notification_id = ? AND member_id = ?
    ");
    return $stmt->execute([$notification_id, $member_id]);
}

/**
 * Markiert alle Notifications als gelesen
 *
 * @param PDO $pdo
 * @param int $member_id
 * @return bool
 */
function mark_all_notifications_read($pdo, $member_id) {
    $stmt = $pdo->prepare("
        UPDATE svnotifications
        SET is_read = 1
        WHERE member_id = ? AND is_read = 0
    ");
    return $stmt->execute([$member_id]);
}

/**
 * Sendet Meeting-Erinnerung (30 Min vorher)
 * Wird von Cron-Job aufgerufen
 *
 * @param PDO $pdo
 * @param int $meeting_id
 */
function send_meeting_reminder($pdo, $meeting_id) {
    // Meeting-Daten laden
    $stmt = $pdo->prepare("
        SELECT m.*, COUNT(mp.member_id) as participant_count
        FROM svmeetings m
        LEFT JOIN svmeeting_participants mp ON m.meeting_id = mp.member_id
        WHERE m.meeting_id = ?
        GROUP BY m.meeting_id
    ");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$meeting) return;

    // Alle Teilnehmer
    $stmt = $pdo->prepare("
        SELECT member_id
        FROM svmeeting_participants
        WHERE meeting_id = ?
    ");
    $stmt->execute([$meeting_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Meeting-Zeit formatieren
    $meeting_time = strtotime($meeting['meeting_date']);
    $meeting_time_formatted = date('H:i', $meeting_time);

    $title = "Sitzung beginnt gleich";
    $message = "Die Sitzung \"" . $meeting['meeting_name'] . "\" beginnt um $meeting_time_formatted Uhr";
    $link = "?tab=agenda&meeting_id=" . $meeting_id;

    foreach ($participants as $member_id) {
        create_notification(
            $pdo,
            $member_id,
            'reminder',
            $title,
            $message,
            $link,
            ['meeting_id' => $meeting_id]
        );

        // Optional: Browser-Push senden
        send_browser_push($pdo, $member_id, $title, $message, $link);
    }
}

/**
 * Sendet Browser-Push-Notification
 *
 * @param PDO $pdo
 * @param int $member_id
 * @param string $title
 * @param string $message
 * @param string|null $link
 */
function send_browser_push($pdo, $member_id, $title, $message, $link = null) {
    // Subscriptions des Users holen
    $stmt = $pdo->prepare("
        SELECT *
        FROM svpush_subscriptions
        WHERE member_id = ?
    ");
    $stmt->execute([$member_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subscriptions)) return;

    // Web Push Library (z.B. Minishlink/web-push) erforderlich
    // Hier nur Platzhalter - tatsächliche Implementation braucht VAPID-Keys
    // composer require minishlink/web-push

    // TODO: Tatsächlichen Push senden wenn Library verfügbar
    error_log("Push Notification: $title - $message (Member: $member_id)");
}
