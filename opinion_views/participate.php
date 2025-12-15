<?php
/**
 * An Meinungsbild teilnehmen
 */

$poll = get_opinion_poll_with_options($pdo, $poll_id);

if (!$poll) {
    echo "<p>Meinungsbild nicht gefunden.</p>";
    return;
}

// Prüfen ob aktiv
$is_active = ($poll['status'] === 'active' && strtotime($poll['ends_at']) > time());
if (!$is_active) {
    echo "<div class='opinion-card'><p>Diese Umfrage ist bereits beendet.</p>";
    echo "<a href='?tab=opinion&view=results&poll_id={$poll_id}'>Zu den Ergebnissen →</a></div>";
    return;
}

// Prüfen ob berechtigt
if (!can_participate($poll, $current_user ? $current_user['member_id'] : null)) {
    echo "<p>Du bist nicht berechtigt, an dieser Umfrage teilzunehmen.</p>";
    return;
}

// Prüfen ob bereits geantwortet
$session_token = $current_user ? null : get_or_create_session_token();
$member_id = $current_user ? $current_user['member_id'] : null;
$existing_response = get_user_response($pdo, $poll_id, $member_id, $session_token);

$is_creator = $current_user && ($poll['creator_member_id'] == $current_user['member_id']);
$stats = get_opinion_results($pdo, $poll_id);
$can_edit = $is_creator && $stats['total_responses'] <= 1;
?>

<div style="margin-bottom: 20px;">
    <a href="?tab=opinion&view=detail&poll_id=<?php echo $poll_id; ?>" class="btn-secondary" style="text-decoration: none; display: inline-block; padding: 8px 16px;">← Zurück</a>
</div>

<div class="opinion-card">
    <h3><?php echo htmlspecialchars($poll['title']); ?></h3>

    <div class="poll-meta" style="margin-bottom: 20px;">
        <span>Von: <?php echo htmlspecialchars($poll['first_name'] . ' ' . $poll['last_name']); ?></span>
        <span style="margin-left: 15px;">Läuft bis: <?php echo date('d.m.Y H:i', strtotime($poll['ends_at'])); ?></span>
        <span style="margin-left: 15px;">📊 <?php echo $stats['total_responses']; ?> Antwort<?php echo $stats['total_responses'] != 1 ? 'en' : ''; ?></span>
    </div>

    <?php if ($existing_response && !$can_edit): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <strong>Du hast bereits geantwortet!</strong><br>
            Deine Antwort: <strong><?php echo htmlspecialchars($existing_response['selected_options_text'] ?? 'N/A'); ?></strong>
            <?php if (!empty($existing_response['free_text'])): ?>
                <br>Kommentar: "<?php echo htmlspecialchars($existing_response['free_text']); ?>"
            <?php endif; ?>
        </div>
        <a href="?tab=opinion&view=results&poll_id=<?php echo $poll_id; ?>" class="btn-primary" style="text-decoration: none;">
            Zu den Ergebnissen →
        </a>
    <?php else: ?>
        <?php if ($existing_response && $can_edit): ?>
            <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <strong>Du bearbeitest deine Antwort</strong><br>
                Als Ersteller kannst du deine Antwort ändern, solange nur du geantwortet hast.
            </div>
        <?php endif; ?>

        <form method="POST" action="process_opinion.php">
            <input type="hidden" name="action" value="submit_response">
            <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">

            <h4>Bitte wähle deine Antwort:
                <?php if (!empty($poll['description'])): ?>
                    <span style="font-size: 0.85em; font-weight: normal; color: #666;"> (<?php echo htmlspecialchars($poll['description']); ?>)</span>
                <?php endif; ?>
            </h4>
            <small style="color: #666; display: block; margin-bottom: 15px;">
                <?php echo $poll['allow_multiple_answers'] ? '☑️ Mehrfachantworten möglich' : '⚪ Bitte nur eine Antwort wählen'; ?>
            </small>

            <ul class="option-list">
                <?php foreach ($poll['options'] as $option): ?>
                    <li class="option-item">
                        <label style="cursor: pointer; display: block;">
                            <?php if ($poll['allow_multiple_answers']): ?>
                                <input type="checkbox" name="options[]" value="<?php echo $option['option_id']; ?>"
                                    <?php echo ($existing_response && in_array($option['option_id'], $existing_response['selected_options'])) ? 'checked' : ''; ?>>
                            <?php else: ?>
                                <input type="radio" name="options[]" value="<?php echo $option['option_id']; ?>" required
                                    <?php echo ($existing_response && in_array($option['option_id'], $existing_response['selected_options'])) ? 'checked' : ''; ?>>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($option['option_text']); ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="form-group" style="margin-top: 20px;">
                <label>Optionaler Kommentar / Begründung:</label>
                <textarea name="free_text" rows="4" placeholder="Du kannst hier einen Kommentar zu deiner Antwort hinzufügen..." style="width: 100%;"><?php echo ($existing_response && isset($existing_response['free_text'])) ? htmlspecialchars($existing_response['free_text']) : ''; ?></textarea>
            </div>

            <?php if (!$poll['is_anonymous'] && $current_user): ?>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="force_anonymous" value="1" <?php echo ($existing_response && !empty($existing_response['force_anonymous'])) ? 'checked' : ''; ?>>
                        <strong>Meine Antwort soll trotzdem anonym bleiben</strong>
                    </label>
                    <small style="display: block; margin-left: 24px; color: #666;">
                        Auch wenn die Umfrage nicht als anonym markiert ist, kannst du verlangen, dass dein Name nicht angezeigt wird
                    </small>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">
                    <?php echo $existing_response ? 'Antwort aktualisieren' : 'Antwort absenden'; ?>
                </button>
                <a href="?tab=opinion&view=detail&poll_id=<?php echo $poll_id; ?>" class="btn-secondary" style="text-decoration: none; padding: 10px 20px;">
                    Abbrechen
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>
