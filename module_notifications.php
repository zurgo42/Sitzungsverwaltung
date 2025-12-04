<?php
/**
 * module_notifications.php - Zentrale Benachrichtigungsfunktion
 *
 * Zeigt dem Benutzer wichtige Meldungen:
 * - Abwesenheiten
 * - Offene Aufgaben (ToDos)
 * - Offene Terminanfragen
 * - Offene Meinungsumfragen
 *
 * Usage: include und render_user_notifications($pdo, $member_id) aufrufen
 */

/**
 * Gibt HTML für Benutzer-Benachrichtigungen aus
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $member_id ID des aktuellen Benutzers
 * @return void (gibt direkt HTML aus)
 */
function render_user_notifications($pdo, $member_id) {
    $notifications = [];

    // 1. ABWESENHEITEN PRÜFEN
    $stmt_absences = $pdo->prepare("
        SELECT a.*, m.first_name, m.last_name, s.first_name AS sub_first_name, s.last_name AS sub_last_name,
               CURDATE() BETWEEN a.start_date AND a.end_date AS is_current
        FROM svabsences a
        JOIN svmembers m ON a.member_id = m.member_id
        LEFT JOIN svmembers s ON a.substitute_member_id = s.member_id
        WHERE a.end_date >= CURDATE()
        ORDER BY a.start_date ASC, m.last_name ASC
    ");
    $stmt_absences->execute();
    $all_absences = $stmt_absences->fetchAll();

    if (!empty($all_absences)) {
        $absence_items = [];
        foreach ($all_absences as $abs) {
            $name = htmlspecialchars($abs['first_name'] . ' ' . $abs['last_name']);
            $dates = date('d.m.', strtotime($abs['start_date'])) . '-' . date('d.m.', strtotime($abs['end_date']));
            $vertr = $abs['substitute_member_id'] ? ' Vertr.: ' . htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']) : '';

            $text = $name . ' (' . $dates . ')' . $vertr;

            // Aktuelle Abwesenheiten in rot
            if ($abs['is_current']) {
                $absence_items[] = '<span style="color: #d32f2f; font-weight: 600;">' . $text . '</span>';
            } else {
                $absence_items[] = $text;
            }
        }

        $notifications[] = [
            'type' => 'absences',
            'icon' => '🏖️',
            'text' => implode(' • ', $absence_items),
            'link' => '?tab=vertretung',
            'link_text' => '→ Details'
        ];
    }

    // 2. OFFENE AUFGABEN (TODOS) PRÜFEN
    $stmt_todos = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM svtodos
        WHERE assigned_to_member_id = ? AND status IN ('open', 'in progress')
    ");
    $stmt_todos->execute([$member_id]);
    $open_todos = $stmt_todos->fetch()['count'];

    if ($open_todos > 0) {
        $notifications[] = [
            'type' => 'todos',
            'icon' => '✅',
            'text' => 'Unter <strong>Erledigen</strong> ' . ($open_todos == 1 ? 'ist' : 'sind') . ' noch ' . $open_todos . ' ' . ($open_todos == 1 ? 'Aufgabe' : 'Aufgaben') . ' offen',
            'link' => '?tab=todos',
            'link_text' => '→ Aufgaben',
            'button' => true
        ];
    }

    // 3. OFFENE TERMINANFRAGEN PRÜFEN
    $stmt_termine = $pdo->prepare("
        SELECT COUNT(DISTINCT p.poll_id) as count
        FROM svpolls p
        LEFT JOIN svpoll_responses pr ON p.poll_id = pr.poll_id AND pr.member_id = ?
        WHERE p.status = 'open'
        AND pr.response_id IS NULL
        AND (
            p.visibility_type = 'all'
            OR (p.visibility_type = 'role' AND ? IN (
                SELECT pr2.allowed_role
                FROM svpoll_roles pr2
                WHERE pr2.poll_id = p.poll_id
            ))
            OR (p.visibility_type = 'meeting' AND ? IN (
                SELECT mp.member_id
                FROM svmeeting_participants mp
                WHERE mp.meeting_id = p.meeting_id
            ))
        )
    ");
    $stmt_termine->execute([$member_id, $member_id, $member_id]);
    $open_termine = $stmt_termine->fetch()['count'];

    if ($open_termine > 0) {
        $notifications[] = [
            'type' => 'termine',
            'icon' => '📆',
            'text' => ($open_termine == 1 ? 'Es liegt noch eine offene Terminanfrage vor' : 'Es liegen noch ' . $open_termine . ' offene Terminanfragen vor'),
            'link' => '?tab=termine',
            'link_text' => '→ Termine',
            'button' => true
        ];
    }

    // 4. OFFENE MEINUNGSUMFRAGEN PRÜFEN
    $stmt_opinions = $pdo->prepare("
        SELECT COUNT(DISTINCT o.opinion_id) as count
        FROM svopinions o
        LEFT JOIN svopinion_responses opr ON o.opinion_id = opr.opinion_id AND opr.member_id = ?
        WHERE o.status = 'open'
        AND o.end_date >= CURDATE()
        AND opr.response_id IS NULL
        AND (
            o.visibility_type = 'all'
            OR (o.visibility_type = 'role' AND EXISTS (
                SELECT 1 FROM svopinion_roles orr
                JOIN svmembers m ON m.role = orr.allowed_role
                WHERE orr.opinion_id = o.opinion_id AND m.member_id = ?
            ))
            OR (o.visibility_type = 'meeting' AND ? IN (
                SELECT mp.member_id
                FROM svmeeting_participants mp
                WHERE mp.meeting_id = o.meeting_id
            ))
        )
    ");
    $stmt_opinions->execute([$member_id, $member_id, $member_id]);
    $open_opinions = $stmt_opinions->fetch()['count'];

    if ($open_opinions > 0) {
        $notifications[] = [
            'type' => 'opinions',
            'icon' => '📊',
            'text' => ($open_opinions == 1 ? 'Ein Meinungsbild wartet auf deine Teilnahme' : $open_opinions . ' Meinungsbilder warten auf deine Teilnahme'),
            'link' => '?tab=opinion',
            'link_text' => '→ Meinungsbild',
            'button' => true
        ];
    }

    // AUSGABE
    if (empty($notifications)) {
        return; // Keine Meldungen
    }

    echo '<div style="background: #f9f9f9; padding: 10px 15px; margin-bottom: 20px; border-radius: 6px; border-left: 4px solid #2196f3;">';

    foreach ($notifications as $notif) {
        echo '<div style="margin-bottom: ' . (end($notifications) === $notif ? '0' : '10px') . ';">';
        echo '<span style="font-size: 14px; color: #666;">';
        echo $notif['icon'] . ' ';

        if ($notif['type'] === 'absences') {
            // Abwesenheiten: Text + kleiner Link
            echo '<strong style="color: #333;">Abwesenheiten:</strong> ' . $notif['text'];
            echo ' <a href="' . $notif['link'] . '" style="margin-left: 8px; color: #2196f3; text-decoration: none; font-size: 12px;">' . $notif['link_text'] . '</a>';
        } else {
            // Andere: Text + Button
            echo $notif['text'];
            echo ' <a href="' . $notif['link'] . '" style="display: inline-block; margin-left: 10px; padding: 4px 12px; background: #2196f3; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600;">' . $notif['link_text'] . '</a>';
        }

        echo '</span>';
        echo '</div>';
    }

    echo '</div>';
}
?>
