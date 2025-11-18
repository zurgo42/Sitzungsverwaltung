<?php
/**
 * Ergebnisse anzeigen
 */

$poll = get_opinion_poll_with_options($pdo, $poll_id);

if (!$poll) {
    echo "<p>Meinungsbild nicht gefunden.</p>";
    return;
}

$is_creator = $current_user && ($poll['creator_member_id'] == $current_user['member_id']);
$is_admin = $current_user && in_array($current_user['role'], ['assistenz', 'gf']);

// Prüfen ob User geantwortet hat
$session_token = $current_user ? null : get_or_create_session_token();
$member_id = $current_user ? $current_user['member_id'] : null;
$has_responded = has_responded($pdo, $poll_id, $member_id, $session_token);

// Berechtigung prüfen
if (!can_show_final_results($poll, $current_user, $has_responded)) {
    $can_show_intermediate = can_show_intermediate_results($poll);

    echo "<div class='opinion-card'>";
    echo "<h3>Ergebnisse noch nicht verfügbar</h3>";
    echo "<p>";
    if (!$has_responded) {
        echo "Sie müssen zuerst an der Umfrage teilnehmen, um die Ergebnisse zu sehen.";
    } elseif (!$can_show_intermediate) {
        $show_date = date('d.m.Y H:i', strtotime($poll['created_at']) + ($poll['show_intermediate_after_days'] * 86400));
        echo "Zwischenergebnisse werden ab dem <strong>{$show_date}</strong> verfügbar sein.";
    } else {
        echo "Die Ergebnisse sind erst nach Ablauf der Umfrage sichtbar.";
    }
    echo "</p>";
    echo "<a href='?tab=opinion&view=detail&poll_id={$poll_id}' class='btn-secondary' style='text-decoration: none; display: inline-block; padding: 8px 16px;'>Zurück zur Umfrage</a>";
    echo "</div>";
    return;
}

// Statistiken laden
$stats = get_opinion_results($pdo, $poll_id);
$all_responses = get_all_responses($pdo, $poll_id, !$poll['is_anonymous'] || $is_creator || $is_admin);
?>

<div style="margin-bottom: 20px;">
    <a href="?tab=opinion&view=detail&poll_id=<?php echo $poll_id; ?>" class="btn-secondary" style="text-decoration: none; display: inline-block; padding: 8px 16px;">← Zurück</a>
</div>

<div class="opinion-card">
    <h3><?php echo htmlspecialchars($poll['title']); ?></h3>

    <div class="poll-meta" style="margin-bottom: 20px;">
        <span class="poll-status <?php echo ($poll['status'] === 'active' && strtotime($poll['ends_at']) > time()) ? 'status-active' : 'status-ended'; ?>">
            <?php echo ($poll['status'] === 'active' && strtotime($poll['ends_at']) > time()) ? 'Aktiv' : 'Beendet'; ?>
        </span>

        <span style="margin-left: 10px;">
            Von: <?php echo htmlspecialchars($poll['first_name'] . ' ' . $poll['last_name']); ?>
        </span>

        <span style="margin-left: 10px;">
            Läuft bis: <?php echo date('d.m.Y H:i', strtotime($poll['ends_at'])); ?>
        </span>

        <span style="margin-left: 10px; font-size: 16px; font-weight: bold; color: #2196F3;">
            📊 <?php echo $stats['total_responses']; ?> Antwort<?php echo $stats['total_responses'] != 1 ? 'en' : ''; ?>
        </span>
    </div>

    <?php if ($stats['total_responses'] === 0): ?>
        <p style="padding: 20px; background: #f9f9f9; border-radius: 6px; text-align: center;">
            Noch keine Antworten vorhanden.
        </p>
    <?php else: ?>
        <h4>Ergebnisse</h4>

        <?php foreach ($stats['option_stats'] as $option_stat): ?>
            <div style="margin-bottom: 20px;">
                <div style="margin-bottom: 5px; font-weight: 500;">
                    <?php echo htmlspecialchars($option_stat['option_text']); ?>
                </div>

                <div class="result-bar">
                    <div class="result-bar-fill" style="width: <?php echo $option_stat['percentage']; ?>%;">
                        <?php if ($option_stat['percentage'] > 15): ?>
                            <?php echo $option_stat['vote_count']; ?> (<?php echo number_format($option_stat['percentage'], 1); ?>%)
                        <?php endif; ?>
                    </div>
                    <?php if ($option_stat['percentage'] <= 15): ?>
                        <div class="result-bar-label">
                            <?php echo $option_stat['vote_count']; ?> (<?php echo number_format($option_stat['percentage'], 1); ?>%)
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (($is_creator || $is_admin) && !empty($all_responses)): ?>
    <div class="opinion-card">
        <h4>Einzelne Antworten (nur für Ersteller/Admins)</h4>

        <div class="response-list">
            <?php foreach ($all_responses as $response): ?>
                <div class="response-item">
                    <div class="response-meta">
                        <?php
                        $name = 'Anonym';
                        if ($response['member_id'] && (!$response['force_anonymous'] || $is_creator || $is_admin)) {
                            $name = htmlspecialchars($response['first_name'] . ' ' . $response['last_name']);
                            if ($response['force_anonymous']) {
                                $name .= ' <em>(will anonym bleiben)</em>';
                            }
                        }
                        echo "👤 $name";
                        ?>
                        •
                        <?php echo date('d.m.Y H:i', strtotime($response['responded_at'])); ?>
                    </div>

                    <div style="font-weight: 500; margin: 8px 0;">
                        Antwort: <?php echo htmlspecialchars($response['selected_options_text'] ?? 'N/A'); ?>
                    </div>

                    <?php if (!empty($response['free_text'])): ?>
                        <div style="background: white; padding: 10px; border-radius: 4px; margin-top: 8px;">
                            <em>"<?php echo nl2br(htmlspecialchars($response['free_text'])); ?>"</em>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
