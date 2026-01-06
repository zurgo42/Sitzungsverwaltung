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
    // Mitglied-Rolle ermitteln - über Adapter!
    $member = get_member_by_id($pdo, $member_id);
    $member_role = $member['role'] ?? '';

    // Für "Mitglied" keine Benachrichtigungen anzeigen
    if (strtolower($member_role) === 'mitglied') {
        return;
    }

    $notifications = [];

    // 1. ABWESENHEITEN PRÜFEN
    // Nutzt Adapter-kompatible Funktion statt direktem JOIN auf svmembers
    $all_absences = get_absences_with_names($pdo, "a.end_date >= CURDATE()");

    // is_current Flag hinzufügen
    foreach ($all_absences as &$abs) {
        $abs['is_current'] = (strtotime('today') >= strtotime($abs['start_date']) &&
                              strtotime('today') <= strtotime($abs['end_date'])) ? 1 : 0;
    }
    unset($abs); // Referenz löschen um Seiteneffekte bei späteren foreach zu vermeiden

    if (!empty($all_absences)) {
        $absence_items = [];
        foreach ($all_absences as $abs) {
            // Zeitraum (strong) + Doppelpunkt
            $dates = '<strong>' . date('d.m.', strtotime($abs['start_date'])) . '-' . date('d.m.', strtotime($abs['end_date'])) . ':</strong>';

            // Vorname + erster Buchstabe Nachname mit Punkt
            $first_name = htmlspecialchars($abs['first_name']);
            $last_initial = strtoupper(substr($abs['last_name'], 0, 1)) . '.';
            $name = $first_name . ' ' . $last_initial;

            // Vertretung (falls vorhanden)
            $vertr = '';
            if ($abs['substitute_member_id']) {
                $sub_first = htmlspecialchars($abs['sub_first_name']);
                $sub_initial = strtoupper(substr($abs['sub_last_name'], 0, 1)) . '.';
                $vertr = ' Vertr.: ' . $sub_first . ' ' . $sub_initial;
            }

            $text = $dates . ' ' . $name . $vertr;

            // Aktuelle Abwesenheiten in rot
            if ($abs['is_current']) {
                $absence_items[] = '<span style="color: #d32f2f;">' . $text . '</span>';
            } else {
                $absence_items[] = $text;
            }
        }

        $notifications[] = [
            'type' => 'absences',
            'icon' => '🏖️',
            'text' => implode(' <span style="color: #ffc107; font-weight: 900; font-size: 18px;">•</span> ', $absence_items),
            'link' => '?tab=vertretung',
            'link_text' => 'Details',
            'button' => true
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
        (SELECT 'meeting' as type, m.meeting_id as item_id, m.meeting_name as title, m.meeting_date as date_time
         FROM svmeetings m
         INNER JOIN svmeeting_participants mp ON m.meeting_id = mp.meeting_id
         WHERE mp.member_id = ?
         AND m.meeting_date >= NOW()
         AND m.status IN ('preparation', 'active')
         ORDER BY m.meeting_date ASC)
        UNION ALL
        (SELECT 'poll' as type, p.poll_id as item_id, p.title, pd.suggested_date as date_time
         FROM svpolls p
         INNER JOIN svpoll_participants pp ON p.poll_id = pp.poll_id
         INNER JOIN svpoll_dates pd ON p.final_date_id = pd.date_id
         WHERE pp.member_id = ?
         AND p.status = 'finalized'
         AND p.meeting_id IS NULL
         AND pd.suggested_date >= NOW()
         ORDER BY pd.suggested_date ASC)
        ORDER BY date_time ASC
    ");
    $stmt_summary->execute([$member_id, $member_id]);
    $all_upcoming = $stmt_summary->fetchAll();

    // Duplikate entfernen (gleicher Typ + gleiche ID)
    $seen = [];
    $upcoming = [];
    foreach ($all_upcoming as $item) {
        // Eindeutiger Key: type + item_id (als String für sicheren Vergleich)
        $key = $item['type'] . '_' . (string)$item['item_id'];
        if (!array_key_exists($key, $seen)) {
            $seen[$key] = true;
            $upcoming[] = $item;
            if (count($upcoming) >= 6) break; // Max 6 Einträge
        }
    }

    if (!empty($upcoming)) {
        $items = [];
        foreach ($upcoming as $item) {
            $icon = $item['type'] === 'meeting' ? '📅' : '📆';
            $date_time = '<strong>' . date('d.m. H:i', strtotime($item['date_time'])) . ':</strong>';

            // Titel auf max 25 Zeichen kürzen
            $full_title = htmlspecialchars($item['title']);
            $title = $full_title;
            if (mb_strlen($title) > 25) {
                $title = mb_substr($title, 0, 22) . '...';
            }

            // Link zur Tagesordnung (falls Meeting)
            if ($item['type'] === 'meeting') {
                $link = '?tab=agenda&meeting_id=' . $item['item_id'];
                $items[] = '<a href="' . $link . '" style="text-decoration: none; color: inherit;" title="' . $full_title . '">' . $icon . '</a>&nbsp;' . $date_time . ' ' . $title;
            } else {
                $items[] = $icon . '&nbsp;' . $date_time . ' ' . $title;
            }
        }

        $notifications[] = [
            'type' => 'summary',
            'icon' => '📋',
            'text' => '<strong class="kommende-termine-label">Kommende Termine:</strong><span class="kommende-termine-break"></span> ' . implode(' ', $items),
            'link' => null
        ];
    }

    // AUSGABE
    if (empty($notifications)) {
        return; // Keine Meldungen
    }

    echo '<div style="background: #f9f9f9; padding: 10px 15px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #ffc107; border-left: 4px solid #ffc107;">';

    foreach ($notifications as $notif) {
        echo '<div style="margin-bottom: ' . (end($notifications) === $notif ? '0' : '10px') . ';">';
        echo '<span style="font-size: 14px; color: #666;">';
        echo $notif['icon'] . ' ';

        if ($notif['type'] === 'absences') {
            // Abwesenheiten: Text + Button
            echo '<strong style="color: #333;">Abwesenheiten:</strong> ' . $notif['text'];
            if (isset($notif['button']) && $notif['button']) {
                echo ' <a href="' . $notif['link'] . '" style="display: inline-block; margin-left: 10px; padding: 4px 12px; background: #ffc107; color: #333; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600;">' . $notif['link_text'] . '</a>';
            }
        } elseif ($notif['type'] === 'summary') {
            // Zusammenfassung: Nur Text, kein Button
            echo $notif['text'];
        } else {
            // Andere: Text + Button
            echo $notif['text'];
            if (isset($notif['button']) && $notif['button']) {
                echo ' <a href="' . $notif['link'] . '" style="display: inline-block; margin-left: 10px; padding: 4px 12px; background: #ffc107; color: #333; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600;">' . $notif['link_text'] . '</a>';
            }
        }

        echo '</span>';
        echo '</div>';
    }

    echo '</div>';
}
?>
