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

    // 1. AUSSTEHENDE ABSTIMMUNGEN (ANTRÄGE) - GANZ OBEN
    // Prüfe offene Abstimmungen für aktuellen User in antraege-Tabelle
    $pending_stmt = $pdo->query("SELECT * FROM antraege WHERE antrnr LIKE 'B%'");
    $b_antraege = $pending_stmt->fetchAll();
    $pending_votes = [];
    foreach ($b_antraege as $a) {
        for ($i = 1; $i <= 6; $i++) {
            if ($a["VName$i"] == $member_id && empty($a["Votum$i"])) {
                $pending_votes[] = $a;
                break;
            }
        }
    }

    if (!empty($pending_votes)) {
        $vote_items = [];
        foreach ($pending_votes as $pv) {
            $antrnr = htmlspecialchars($pv['antrnr']);
            $titel = htmlspecialchars(mb_substr($pv['titel'], 0, 40));
            if (mb_strlen($pv['titel']) > 40) $titel .= '...';
            $vote_items[] = '<a href="abstimmungen.php?antrnr=' . urlencode($pv['antrnr']) . '" style="display: inline-block; background: var(--warning); color: #000; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 12px; margin-right: 5px; margin-bottom: 5px;">→ ' . $antrnr . ': ' . $titel . '</a>';
        }

        $notifications[] = [
            'type' => 'proposals_voting',
            'icon' => '🗳️',
            'text' => 'Du hast <strong>' . count($pending_votes) . '</strong> offene Abstimmung' . (count($pending_votes) > 1 ? 'en' : '') . ': ' . implode('', $vote_items),
            'link' => null
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

    // 5. ALTE PROPOSALS-ABSTIMMUNGEN (DEAKTIVIERT)
    // Jetzt in Sektion 1 (ganz oben) implementiert
    /*
    require_once __DIR__ . '/proposals_functions.php';
    require_once __DIR__ . '/adapters/ProposalPermissionAdapter.php';

    $memberAdapter = get_member_adapter($pdo);
    $permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);

    // Ausstehende Abstimmungen
    $voting_stats = $permAdapter->get_voting_stats($member);

    if ($voting_stats['pending'] > 0) {
        // Nächste Deadline ermitteln
        $votable_ids = $permAdapter->get_votable_proposal_ids($member);
        if (!empty($votable_ids)) {
            $placeholders = str_repeat('?,', count($votable_ids) - 1) . '?';
            $stmt_deadline = $pdo->prepare("
                SELECT MIN(voting_deadline) as next_deadline
                FROM svbproposals
                WHERE id IN ($placeholders)
                AND status = 'voting'
                AND voting_deadline IS NOT NULL
            ");
            $stmt_deadline->execute($votable_ids);
            $next_deadline = $stmt_deadline->fetchColumn();

            $deadline_text = '';
            if ($next_deadline) {
                $days_left = ceil((strtotime($next_deadline) - time()) / 86400);
                if ($days_left <= 3) {
                    $deadline_text = ' <span style="color: #d32f2f; font-weight: 600;">(läuft in ' . $days_left . ' ' . ($days_left == 1 ? 'Tag' : 'Tagen') . ' ab!)</span>';
                } elseif ($days_left <= 7) {
                    $deadline_text = ' <span style="color: #ff9800;">(läuft in ' . $days_left . ' Tagen ab)</span>';
                }
            }
        }

        $notifications[] = [
            'type' => 'proposals_voting',
            'icon' => '🗳️',
            'text' => ($voting_stats['pending'] == 1 ? 'Eine Abstimmung wartet auf deine Stimme' : $voting_stats['pending'] . ' Abstimmungen warten auf deine Stimme') . $deadline_text,
            'link' => '?tab=proposals&view=voting',
            'link_text' => '→ Abstimmen',
            'button' => true
        ];
    }

    // 6. EXPEDITE-GENEHMIGUNGEN WARTEND (nur für Vorstand)
    if (strtolower($member_role) === 'vorstand') {
        $stmt_expedite = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM svbproposals
            WHERE status = 'editing'
            AND expedite_justification IS NOT NULL
            AND (
                (expedite_approver1_id IS NULL)
                OR (expedite_approver1_id IS NOT NULL AND expedite_approver2_id IS NULL)
            )
            AND expedite_approver1_id != ?
            AND expedite_approver2_id != ?
            AND submitter_id != ?
        ");
        $stmt_expedite->execute([$member_id, $member_id, $member_id]);
        $expedite_count = $stmt_expedite->fetchColumn();

        if ($expedite_count > 0) {
            $notifications[] = [
                'type' => 'proposals_expedite',
                'icon' => '⚡',
                'text' => ($expedite_count == 1 ? 'Eine Wartezeitverkürzung' : $expedite_count . ' Wartezeitverkürzungen') . ' warten auf Genehmigung',
                'link' => '?tab=proposals&view=list',
                'link_text' => '→ Anträge',
                'button' => true
            ];
        }
    }

    // 7. FINANZ-FREIGABEN ERFORDERLICH (für Finanzverantwortliche)
    if ($permAdapter->can_approve_finance($member)) {
        $stmt_finance = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM svbproposals
            WHERE decision_type = 'department'
            AND status = 'voting'
            AND finance_approval = 0
        ");
        $stmt_finance->execute();
        $finance_count = $stmt_finance->fetchColumn();

        if ($finance_count > 0) {
            $notifications[] = [
                'type' => 'proposals_finance',
                'icon' => '💰',
                'text' => ($finance_count == 1 ? 'Eine Finanzfreigabe wird benötigt' : $finance_count . ' Finanzfreigaben werden benötigt'),
                'link' => '?tab=proposals&view=list&filter_decision_type=department',
                'link_text' => '→ Prüfen',
                'button' => true
            ];
        }
    }
    */

    // 8. ZUSAMMENFASSUNG: KOMMENDE SITZUNGEN & TERMINE
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

    // 9. ABWESENHEITEN (AM ENDE, NACH TERMINEN)
    $all_absences = get_absences_with_names($pdo, "a.end_date >= CURDATE()");

    // is_current Flag hinzufügen
    foreach ($all_absences as &$abs) {
        $abs['is_current'] = (strtotime('today') >= strtotime($abs['start_date']) &&
                              strtotime('today') <= strtotime($abs['end_date'])) ? 1 : 0;
    }

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
