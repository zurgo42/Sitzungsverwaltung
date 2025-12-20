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
// via_link = true wenn Token oder poll_id Parameter vorhanden (direkter Link-Zugriff)
$via_link = isset($_GET['token']) || isset($_GET['poll_id']);
if (!can_participate($poll, $current_user ? $current_user['member_id'] : null, $via_link)) {
    echo "<p>Du bist nicht berechtigt, an dieser Umfrage teilzunehmen.</p>";
    return;
}

// Prüfen ob bereits geantwortet
// Teilnehmer ermitteln (Member oder Extern)
$participant = get_current_participant($current_user, $pdo, 'meinungsbild', $poll_id);
$member_id = ($participant['type'] === 'member') ? $participant['id'] : null;
$external_id = ($participant['type'] === 'external') ? $participant['id'] : null;
$session_token = ($participant['type'] === 'none') ? get_or_create_session_token() : null;

// Für externe Teilnehmer: Daten speichern für Anzeige
if ($participant['type'] === 'external' && !isset($current_participant_data)) {
    $current_participant_data = $participant['data'];
}

$existing_response = get_user_response($pdo, $poll_id, $member_id, $session_token, $external_id);

$is_creator = $current_user && ($poll['creator_member_id'] == $current_user['member_id']);
$stats = get_opinion_results($pdo, $poll_id);

// ALLE Teilnehmer können ihre ANTWORTEN bearbeiten, solange Umfrage offen ist
$is_external = !$current_user;
$allow_edit = true; // Immer erlaubt, da bereits oben geprüft wurde ob Umfrage aktiv ist

// Ersteller können FRAGEN/EINSTELLUNGEN bearbeiten, solange nur eigene Antwort vorhanden
$can_edit_poll = $is_creator && $stats['total_responses'] <= 1;
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

    <?php if ($is_external && isset($current_participant_data)): ?>
        <div style="background: #e7f3ff; color: #004085; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #0066cc;">
            👤 <strong>Du bist registriert als:</strong>
            <?php echo htmlspecialchars($current_participant_data['first_name'] . ' ' . $current_participant_data['last_name']); ?>
            (<?php echo htmlspecialchars($current_participant_data['email']); ?>)
        </div>
    <?php endif; ?>

    <?php if ($existing_response): ?>
        <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <strong>✏️ Du bearbeitest deine Antwort</strong><br>
            Deine bisherige Antwort ist vorausgewählt. Du kannst sie jederzeit ändern, solange die Umfrage offen ist.
        </div>
    <?php endif; ?>

        <form method="POST" action="<?php echo $current_user ? 'process_opinion.php' : 'opinion_standalone.php'; ?>">
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
</div>
