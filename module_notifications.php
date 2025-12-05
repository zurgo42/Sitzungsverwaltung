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
    // Mitglied-Rolle ermitteln
    $stmt_role = $pdo->prepare("SELECT role FROM svmembers WHERE member_id = ?");
    $stmt_role->execute([$member_id]);
    $member_role = $stmt_role->fetch()['role'] ?? '';

    // Für "Mitglied" keine Benachrichtigungen anzeigen
    if (strtolower($member_role) === 'mitglied') {
        return;
    }

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

    // 3. OFFENE TERMINANFRAGEN PRÜFEN (nur nicht-finalisierte, wo User Teilnehmer ist)
    $stmt_termine = $pdo->prepare("
        SELECT COUNT(DISTINCT p.poll_id) as count
        FROM svpolls p
        INNER JOIN svpoll_participants pp ON p.poll_id = pp.poll_id
        LEFT JOIN svpoll_responses pr ON p.poll_id = pr.poll_id AND pr.member_id = ?
        WHERE p.status = 'open'
        AND p.final_date_id IS NULL
        AND pp.member_id = ?
        AND pr.response_id IS NULL
    ");
    $stmt_termine->execute([$member_id, $member_id]);
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
        SELECT COUNT(DISTINCT o.poll_id) as count
        FROM svopinion_polls o
        LEFT JOIN svopinion_responses opr ON o.poll_id = opr.poll_id AND opr.member_id = ?
        WHERE o.status = 'active'
        AND o.ends_at >= NOW()
        AND opr.response_id IS NULL
    ");
    $stmt_opinions->execute([$member_id]);
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

    // 5. ZUSAMMENFASSUNG: KOMMENDE SITZUNGEN & TERMINE
    $stmt_summary = $pdo->prepare("
        (SELECT 'meeting' as type, m.meeting_name as title, m.meeting_date as date_time
         FROM svmeetings m
         INNER JOIN svmeeting_participants mp ON m.meeting_id = mp.meeting_id
         WHERE mp.member_id = ?
         AND m.meeting_date >= NOW()
         AND m.status IN ('preparation', 'active')
         ORDER BY m.meeting_date ASC
         LIMIT 3)
        UNION ALL
        (SELECT 'poll' as type, p.title, pd.suggested_date as date_time
         FROM svpolls p
         INNER JOIN svpoll_participants pp ON p.poll_id = pp.poll_id
         INNER JOIN svpoll_dates pd ON p.final_date_id = pd.date_id
         WHERE pp.member_id = ?
         AND p.status = 'finalized'
         AND pd.suggested_date >= NOW()
         ORDER BY pd.suggested_date ASC
         LIMIT 3)
        ORDER BY date_time ASC
        LIMIT 5
    ");
    $stmt_summary->execute([$member_id, $member_id]);
    $upcoming = $stmt_summary->fetchAll();

    if (!empty($upcoming)) {
        $items = [];
        foreach ($upcoming as $item) {
            $icon = $item['type'] === 'meeting' ? '📅' : '📆';
            $date = date('d.m. H:i', strtotime($item['date_time']));
            $items[] = $icon . ' ' . htmlspecialchars($item['title']) . ' (' . $date . ')';
        }

        $notifications[] = [
            'type' => 'summary',
            'icon' => '📋',
            'text' => '<strong>Kommende Termine:</strong> ' . implode(' • ', $items),
            'link' => null
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
        } elseif ($notif['type'] === 'summary') {
            // Zusammenfassung: Nur Text, kein Button
            echo $notif['text'];
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
