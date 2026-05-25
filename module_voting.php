<?php
/**
 * module_voting.php
 * Voting/Abstimmungs-Funktionen für Sitzungs-TOPs
 * Version 2.0 - Vollständige Implementierung
 */

/**
 * Prüft ob Member eine Abstimmung initiieren darf
 * (Sitzungsleiter, Protokollführer oder Admin)
 */
function can_initiate_voting($member, $meeting) {
    if ($member['is_admin'] == 1) return true;
    if ($member['member_id'] == $meeting['secretary_member_id']) return true;
    if ($member['member_id'] == $meeting['chairman_member_id']) return true;
    return false;
}

/**
 * Prüft ob Member eine Abstimmung schließen darf
 * (Sitzungsleiter oder Protokollführer)
 */
function can_close_voting($member, $meeting) {
    if ($member['member_id'] == $meeting['secretary_member_id']) return true;
    if ($member['member_id'] == $meeting['chairman_member_id']) return true;
    return false;
}

/**
 * Prüft ob Member stimmberechtigt ist
 */
function is_eligible_to_vote($member, $eligibility_type) {
    if ($eligibility_type === 'all') {
        return true; // Alle Teilnehmer
    }
    // 'board' - nur Vorstand
    return in_array(strtolower($member['role']), ['vorstand', 'gf']);
}

/**
 * Holt Liste der stimmberechtigten Member IDs für ein Meeting
 */
function get_eligible_voters($pdo, $meeting_id, $eligibility_type) {
    // Alle Teilnehmer des Meetings holen
    $stmt = $pdo->prepare("
        SELECT mp.member_id
        FROM svmeeting_participants mp
        WHERE mp.meeting_id = ?
    ");
    $stmt->execute([$meeting_id]);
    $participant_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($eligibility_type === 'all') {
        return $participant_ids;
    }

    // Nur Vorstand filtern
    $eligible = [];
    foreach ($participant_ids as $member_id) {
        $member = get_member_by_id($pdo, $member_id);
        if ($member && in_array(strtolower($member['role']), ['vorstand', 'gf'])) {
            $eligible[] = $member_id;
        }
    }

    return $eligible;
}

/**
 * Holt aktive Abstimmung für einen TOP
 */
function get_active_voting($pdo, $item_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM svvotings
        WHERE item_id = ? AND status = 'active'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$item_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Zählt Stimmen für eine Abstimmung
 */
function get_vote_counts($pdo, $voting_id) {
    $stmt = $pdo->prepare("
        SELECT
            vote,
            COUNT(*) as count
        FROM svvotes
        WHERE voting_id = ?
        GROUP BY vote
    ");
    $stmt->execute([$voting_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = ['yes' => 0, 'no' => 0, 'abstain' => 0];
    foreach ($results as $row) {
        $counts[$row['vote']] = (int)$row['count'];
    }

    return $counts;
}

/**
 * Holt alle Stimmen mit Member-Details (für offene Abstimmung)
 */
function get_votes_with_members($pdo, $voting_id) {
    $stmt = $pdo->prepare("
        SELECT
            v.vote_id,
            v.member_id,
            v.vote,
            v.submitted_by_member_id,
            v.created_at
        FROM svvotes v
        WHERE v.voting_id = ?
        ORDER BY v.created_at ASC
    ");
    $stmt->execute([$voting_id]);
    $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Member-Daten über Adapter holen
    foreach ($votes as &$vote) {
        $member = get_member_by_id($pdo, $vote['member_id']);
        $vote['first_name'] = $member['first_name'] ?? '';
        $vote['last_name'] = $member['last_name'] ?? '';

        if ($vote['submitted_by_member_id']) {
            $submitter = get_member_by_id($pdo, $vote['submitted_by_member_id']);
            $vote['submitted_by_first'] = $submitter['first_name'] ?? '';
            $vote['submitted_by_last'] = $submitter['last_name'] ?? '';
        }
    }
    unset($vote);

    return $votes;
}

/**
 * Prüft ob Member bereits abgestimmt hat
 */
function has_voted($pdo, $voting_id, $member_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM svvotes
        WHERE voting_id = ? AND member_id = ?
    ");
    $stmt->execute([$voting_id, $member_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Holt die Stimme eines Members
 */
function get_member_vote($pdo, $voting_id, $member_id) {
    $stmt = $pdo->prepare("
        SELECT vote FROM svvotes
        WHERE voting_id = ? AND member_id = ?
    ");
    $stmt->execute([$voting_id, $member_id]);
    return $stmt->fetchColumn();
}

/**
 * Rendert die Abstimmungs-UI für einen TOP
 */
function render_voting_ui($item, $voting, $current_user, $meeting, $pdo) {
    $is_secretary = ($meeting['secretary_member_id'] == $current_user['member_id']);
    $is_chairman = ($meeting['chairman_member_id'] == $current_user['member_id']);
    $can_initiate = can_initiate_voting($current_user, $meeting);
    $can_close = can_close_voting($current_user, $meeting);

    // Wenn keine aktive Abstimmung: Button zum Starten (nur für berechtigte)
    if (!$voting && $can_initiate) {
        ?>
        <details style="margin: 10px 0; border: 2px solid #ffc107; border-radius: 6px; overflow: hidden;">
            <summary style="padding: 8px 12px; background: #fff3cd; cursor: pointer; font-weight: 600; color: #856404; font-size: 13px;">
                🗳️ Abstimmung durchführen
            </summary>
            <div style="padding: 12px; background: #fffbf0;">
                <form method="POST" action="?tab=agenda&meeting_id=<?= $meeting['meeting_id'] ?>">
                    <input type="hidden" name="initiate_voting" value="1">
                    <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">

                    <?php if (empty($item['antrnr'])): ?>
                    <!-- Frage für Stimmungsbild (nur wenn kein Antrag verknüpft) -->
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #856404;">
                            Stimmungsbild zu der Frage:
                        </label>
                        <input type="text" name="voting_question" required
                               placeholder="z.B. 'Sollen wir das Projekt fortsetzen?'"
                               style="width: 100%; padding: 6px; border: 1px solid #ffc107; border-radius: 4px; font-size: 13px;">
                    </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 20px; margin-bottom: 10px; flex-wrap: wrap;">
                        <div>
                            <strong style="font-size: 12px;">Stimmberechtigung:</strong>
                            <label style="display: inline-flex; align-items: center; margin-left: 10px;">
                                <input type="radio" name="eligible_voters" value="board" checked style="margin-right: 4px;">
                                <span style="font-size: 12px;">Vorstand</span>
                            </label>
                            <label style="display: inline-flex; align-items: center; margin-left: 10px;">
                                <input type="radio" name="eligible_voters" value="all" style="margin-right: 4px;">
                                <span style="font-size: 12px;">Alle</span>
                            </label>
                        </div>
                        <div>
                            <strong style="font-size: 12px;">Art:</strong>
                            <label style="display: inline-flex; align-items: center; margin-left: 10px;">
                                <input type="radio" name="voting_type" value="open" checked style="margin-right: 4px;">
                                <span style="font-size: 12px;">Offen</span>
                            </label>
                            <label style="display: inline-flex; align-items: center; margin-left: 10px;">
                                <input type="radio" name="voting_type" value="secret" style="margin-right: 4px;">
                                <span style="font-size: 12px;">Geheim</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" style="background: #ffc107; color: #333; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">
                        🗳️ Abstimmung starten
                    </button>
                </form>
            </div>
        </details>
        <?php
        return;
    }

    // Wenn aktive Abstimmung vorhanden
    if ($voting) {
        $is_open = ($voting['voting_type'] === 'open');
        $is_eligible = is_eligible_to_vote($current_user, $voting['eligible_voters']);
        $has_voted = has_voted($pdo, $voting['voting_id'], $current_user['member_id']);
        $my_vote = $has_voted ? get_member_vote($pdo, $voting['voting_id'], $current_user['member_id']) : null;

        $counts = get_vote_counts($pdo, $voting['voting_id']);
        $total_votes = $counts['yes'] + $counts['no'] + $counts['abstain'];
        $eligible_voters = get_eligible_voters($pdo, $meeting['meeting_id'], $voting['eligible_voters']);
        $total_eligible = count($eligible_voters);

        // Initiator laden
        $initiator = get_member_by_id($pdo, $voting['initiated_by_member_id']);
        $initiator_name = ($initiator['first_name'] ?? '') . ' ' . ($initiator['last_name'] ?? '');

        ?>
        <div style="margin: 15px 0; padding: 15px; background: #e3f2fd; border: 2px solid #2196f3; border-radius: 8px;">
            <h4 style="margin-top: 0; color: #1976d2;">
                🗳️ Abstimmung läuft
                <?php if ($is_open): ?>
                    <span style="font-size: 12px; background: #4caf50; color: white; padding: 2px 8px; border-radius: 3px; margin-left: 8px;">OFFEN</span>
                <?php else: ?>
                    <span style="font-size: 12px; background: #ff9800; color: white; padding: 2px 8px; border-radius: 3px; margin-left: 8px;">GEHEIM</span>
                <?php endif; ?>
            </h4>

            <?php if (!empty($voting['voting_question'])): ?>
                <div style="margin: 10px 0; padding: 12px; background: white; border-left: 4px solid #2196f3; border-radius: 4px;">
                    <strong style="color: #1976d2; font-size: 14px;">Stimmungsbild zu der Frage:</strong>
                    <div style="margin-top: 5px; font-size: 15px; color: #333;">
                        <?= htmlspecialchars($voting['voting_question']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                Gestartet von <?= htmlspecialchars($initiator_name) ?> am <?= date('d.m.Y H:i', strtotime($voting['created_at'])) ?> Uhr<br>
                Stimmberechtigt: <?= $voting['eligible_voters'] === 'all' ? 'Alle Teilnehmer' : 'Nur Vorstand' ?>
            </div>

            <!-- Stimmabgabe -->
            <?php if ($is_eligible && !$has_voted): ?>
                <form method="POST" action="?tab=agenda&meeting_id=<?= $meeting['meeting_id'] ?>" style="margin: 15px 0; padding: 15px; background: white; border-radius: 6px;">
                    <input type="hidden" name="submit_vote" value="1">
                    <input type="hidden" name="voting_id" value="<?= $voting['voting_id'] ?>">
                    <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">

                    <div style="font-weight: 600; margin-bottom: 10px;">Ihre Stimme:</div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" name="vote" value="yes" style="flex: 1; min-width: 100px; background: #4caf50; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            ✅ Ja
                        </button>
                        <button type="submit" name="vote" value="no" style="flex: 1; min-width: 100px; background: #f44336; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            ❌ Nein
                        </button>
                        <button type="submit" name="vote" value="abstain" style="flex: 1; min-width: 100px; background: #9e9e9e; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            ⊝ Enthaltung
                        </button>
                    </div>
                </form>
            <?php elseif ($is_eligible && $has_voted): ?>
                <div style="margin: 15px 0; padding: 12px; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 6px;">
                    ✅ Sie haben abgestimmt:
                    <strong>
                        <?php
                        if ($my_vote === 'yes') echo '✅ Ja';
                        elseif ($my_vote === 'no') echo '❌ Nein';
                        else echo '⊝ Enthaltung';
                        ?>
                    </strong>
                </div>
            <?php elseif (!$is_eligible): ?>
                <div style="margin: 15px 0; padding: 12px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 6px; color: #666;">
                    Sie sind bei dieser Abstimmung nicht stimmberechtigt.
                </div>
            <?php endif; ?>

            <!-- Protokollführer kann für andere abstimmen -->
            <?php if ($is_secretary && $total_votes < $total_eligible): ?>
                <details style="margin: 15px 0; border: 1px solid #2196f3; border-radius: 6px; overflow: hidden;">
                    <summary style="padding: 10px; background: #bbdefb; cursor: pointer; font-weight: 600;">
                        📝 Für Teilnehmer abstimmen
                    </summary>
                    <div style="padding: 15px; background: white;">
                        <form method="POST" action="?tab=agenda&meeting_id=<?= $meeting['meeting_id'] ?>">
                            <input type="hidden" name="submit_vote_for_member" value="1">
                            <input type="hidden" name="voting_id" value="<?= $voting['voting_id'] ?>">
                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">

                            <div style="margin-bottom: 10px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 5px;">Teilnehmer:</label>
                                <select name="for_member_id" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">-- Bitte wählen --</option>
                                    <?php
                                    foreach ($eligible_voters as $voter_id) {
                                        if (has_voted($pdo, $voting['voting_id'], $voter_id)) continue;
                                        $voter = get_member_by_id($pdo, $voter_id);
                                        if ($voter) {
                                            echo '<option value="' . $voter_id . '">' .
                                                 htmlspecialchars($voter['first_name'] . ' ' . $voter['last_name']) .
                                                 '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div style="margin-bottom: 10px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 5px;">Stimme:</label>
                                <div style="display: flex; gap: 10px;">
                                    <label style="flex: 1;">
                                        <input type="radio" name="vote" value="yes" required> ✅ Ja
                                    </label>
                                    <label style="flex: 1;">
                                        <input type="radio" name="vote" value="no" required> ❌ Nein
                                    </label>
                                    <label style="flex: 1;">
                                        <input type="radio" name="vote" value="abstain" required> ⊝ Enthaltung
                                    </label>
                                </div>
                            </div>

                            <button type="submit" style="background: #2196f3; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                                Stimme eintragen
                            </button>
                        </form>
                    </div>
                </details>
            <?php endif; ?>

            <!-- Aktueller Stand -->
            <div style="margin: 15px 0; padding: 12px; background: white; border-radius: 6px;">
                <div style="font-weight: 600; margin-bottom: 8px;">
                    <?php if ($is_open): ?>
                        Aktueller Stand (<?= $total_votes ?>/<?= $total_eligible ?> Stimmen):
                    <?php else: ?>
                        <?= $total_votes ?>/<?= $total_eligible ?> Stimmen abgegeben
                        <?php if ($total_votes < $total_eligible): ?>
                            <span style="color: #666; font-weight: normal; font-size: 12px;">(Ergebnis wird nach Abschluss angezeigt)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($is_open || $total_votes >= $total_eligible): ?>
                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <div style="flex: 1; text-align: center;">
                            <div style="font-size: 24px; font-weight: 600; color: #4caf50;">✅ <?= $counts['yes'] ?></div>
                            <div style="font-size: 12px; color: #666;">Ja</div>
                        </div>
                        <div style="flex: 1; text-align: center;">
                            <div style="font-size: 24px; font-weight: 600; color: #f44336;">❌ <?= $counts['no'] ?></div>
                            <div style="font-size: 12px; color: #666;">Nein</div>
                        </div>
                        <div style="flex: 1; text-align: center;">
                            <div style="font-size: 24px; font-weight: 600; color: #9e9e9e;">⊝ <?= $counts['abstain'] ?></div>
                            <div style="font-size: 12px; color: #666;">Enthaltung</div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Bei offener Abstimmung: Liste der Stimmen -->
                <?php if ($is_open): ?>
                    <details style="margin-top: 15px;">
                        <summary style="cursor: pointer; font-size: 13px; color: #666;">Einzelne Stimmen anzeigen</summary>
                        <div style="margin-top: 10px; font-size: 13px;">
                            <?php
                            $votes = get_votes_with_members($pdo, $voting['voting_id']);
                            foreach ($votes as $v) {
                                $vote_icon = $v['vote'] === 'yes' ? '✅' : ($v['vote'] === 'no' ? '❌' : '⊝');
                                $vote_label = $v['vote'] === 'yes' ? 'Ja' : ($v['vote'] === 'no' ? 'Nein' : 'Enthaltung');
                                $name = htmlspecialchars($v['first_name'] . ' ' . $v['last_name']);

                                echo '<div style="padding: 4px 0;">';
                                echo $vote_icon . ' <strong>' . $vote_label . '</strong>: ' . $name;
                                if ($v['submitted_by_member_id']) {
                                    $submitter_name = htmlspecialchars($v['submitted_by_first'] . ' ' . $v['submitted_by_last']);
                                    echo ' <span style="color: #666; font-size: 11px;">(eingetragen von ' . $submitter_name . ')</span>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </details>
                <?php endif; ?>
            </div>

            <!-- Abstimmung abschließen -->
            <?php if ($can_close): ?>
                <form method="POST" action="?tab=agenda&meeting_id=<?= $meeting['meeting_id'] ?>" style="margin-top: 15px;">
                    <input type="hidden" name="close_voting" value="1">
                    <input type="hidden" name="voting_id" value="<?= $voting['voting_id'] ?>">
                    <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">

                    <button type="submit" style="background: #ff9800; color: white; padding: 10px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;"
                            onclick="return confirm('Abstimmung jetzt abschließen? Dies kann nicht rückgängig gemacht werden.');">
                        🔒 Abstimmung abschließen
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}

/**
 * Rendert abgeschlossene Abstimmungen für einen TOP
 */
function render_closed_votings($item_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM svvotings
        WHERE item_id = ? AND status = 'closed'
        ORDER BY closed_at DESC
    ");
    $stmt->execute([$item_id]);
    $closed_votings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($closed_votings)) {
        return;
    }

    foreach ($closed_votings as $voting) {
        $counts = get_vote_counts($pdo, $voting['voting_id']);
        $votes = get_votes_with_members($pdo, $voting['voting_id']);

        // Initiator laden
        $initiator = get_member_by_id($pdo, $voting['initiated_by_member_id']);
        $initiator_name = ($initiator['first_name'] ?? '') . ' ' . ($initiator['last_name'] ?? '');

        // Closer laden (falls vorhanden)
        $closer_name = '';
        if ($voting['closed_by_member_id']) {
            $closer = get_member_by_id($pdo, $voting['closed_by_member_id']);
            $closer_name = ($closer['first_name'] ?? '') . ' ' . ($closer['last_name'] ?? '');
        }

        $is_open = ($voting['voting_type'] === 'open');
        ?>
        <div style="margin: 15px 0; padding: 15px; background: #f5f5f5; border: 2px solid #9e9e9e; border-radius: 8px;">
            <h4 style="margin-top: 0; color: #666;">
                🗳️ Abgeschlossene Abstimmung
                <?php if ($is_open): ?>
                    <span style="font-size: 12px; background: #757575; color: white; padding: 2px 8px; border-radius: 3px; margin-left: 8px;">OFFEN</span>
                <?php else: ?>
                    <span style="font-size: 12px; background: #757575; color: white; padding: 2px 8px; border-radius: 3px; margin-left: 8px;">GEHEIM</span>
                <?php endif; ?>
            </h4>

            <?php if (!empty($voting['voting_question'])): ?>
                <div style="margin: 10px 0; padding: 12px; background: white; border-left: 4px solid #9e9e9e; border-radius: 4px;">
                    <strong style="color: #666; font-size: 14px;">Stimmungsbild zu der Frage:</strong>
                    <div style="margin-top: 5px; font-size: 15px; color: #333;">
                        <?= htmlspecialchars($voting['voting_question']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                Gestartet: <?= date('d.m.Y H:i', strtotime($voting['created_at'])) ?> Uhr (<?= htmlspecialchars($initiator_name) ?>)<br>
                Abgeschlossen: <?= date('d.m.Y H:i', strtotime($voting['closed_at'])) ?> Uhr
                <?php if ($closer_name): ?>
                    (<?= htmlspecialchars($closer_name) ?>)
                <?php endif; ?><br>
                Stimmberechtigt: <?= $voting['eligible_voters'] === 'all' ? 'Alle Teilnehmer' : 'Nur Vorstand' ?>
            </div>

            <!-- Ergebnis -->
            <div style="padding: 12px; background: white; border-radius: 6px; margin-bottom: 10px;">
                <div style="font-weight: 600; margin-bottom: 8px;">
                    <?= htmlspecialchars($voting['result_summary'] ?? 'Ergebnis') ?>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 10px;">
                    <div style="flex: 1; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #4caf50;">✅ <?= $counts['yes'] ?></div>
                        <div style="font-size: 12px; color: #666;">Ja</div>
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #f44336;">❌ <?= $counts['no'] ?></div>
                        <div style="font-size: 12px; color: #666;">Nein</div>
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <div style="font-size: 24px; font-weight: 600; color: #9e9e9e;">⊝ <?= $counts['abstain'] ?></div>
                        <div style="font-size: 12px; color: #666;">Enthaltung</div>
                    </div>
                </div>
            </div>

            <!-- Einzelne Stimmen -->
            <details>
                <summary style="cursor: pointer; font-size: 13px; color: #666;">Einzelne Stimmen anzeigen</summary>
                <div style="margin-top: 10px; font-size: 13px; background: white; padding: 10px; border-radius: 4px;">
                    <?php
                    foreach ($votes as $v) {
                        $vote_icon = $v['vote'] === 'yes' ? '✅' : ($v['vote'] === 'no' ? '❌' : '⊝');
                        $vote_label = $v['vote'] === 'yes' ? 'Ja' : ($v['vote'] === 'no' ? 'Nein' : 'Enthaltung');
                        $name = htmlspecialchars($v['first_name'] . ' ' . $v['last_name']);

                        echo '<div style="padding: 4px 0;">';
                        echo $vote_icon . ' <strong>' . $vote_label . '</strong>: ' . $name;
                        if ($v['submitted_by_member_id']) {
                            $submitter_name = htmlspecialchars($v['submitted_by_first'] . ' ' . $v['submitted_by_last']);
                            echo ' <span style="color: #666; font-size: 11px;">(eingetragen von ' . $submitter_name . ')</span>';
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </details>
        </div>
        <?php
    }
}

/**
 * Speichert Abstimmungsergebnis in beschluesse-Tabelle (bei Anträgen)
 */
function save_voting_result_to_beschluesse($pdo, $voting_id, $item) {
    // Nur bei verlinkten Anträgen
    if (empty($item['antrnr'])) {
        return;
    }

    // Voting-Daten laden
    $stmt = $pdo->prepare("SELECT * FROM svvotings WHERE voting_id = ?");
    $stmt->execute([$voting_id]);
    $voting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voting) return;

    // Stimmen laden
    $votes = get_votes_with_members($pdo, $voting_id);
    $counts = get_vote_counts($pdo, $voting_id);

    // Ergebnis bestimmen
    $result = 'abgelehnt';
    if ($counts['yes'] > $counts['no']) {
        $result = 'angenommen';
    }

    // In beschluesse-Tabelle eintragen
    // TODO: Schema der beschluesse-Tabelle prüfen und entsprechend anpassen
    // Für jetzt: Log-Eintrag
    error_log("Voting result for antrnr {$item['antrnr']}: Yes={$counts['yes']}, No={$counts['no']}, Abstain={$counts['abstain']}, Result=$result");

    // Optional: Antrag-Status aktualisieren basierend auf Ergebnis
    // Dies hängt vom vorhandenen Workflow ab
}
