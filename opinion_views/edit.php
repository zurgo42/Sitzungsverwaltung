<?php
/**
 * Meinungsbild bearbeiten
 */

if (!$current_user) {
    echo "<p>Bitte melde dich an.</p>";
    return;
}

$poll = get_opinion_poll_with_options($pdo, $poll_id);

if (!$poll) {
    echo "<p>Meinungsbild nicht gefunden.</p>";
    return;
}

// Prüfen ob Berechtigung zum Bearbeiten
$is_creator = ($poll['creator_member_id'] == $current_user['member_id']);
$stats = get_opinion_results($pdo, $poll_id);
$can_edit = $is_creator && $stats['total_responses'] <= 1;

if (!$can_edit) {
    echo "<div class='error-box'>Du kannst diese Umfrage nicht mehr bearbeiten, da bereits " . $stats['total_responses'] . " Antworten vorhanden sind.</div>";
    echo "<a href='?tab=opinion&view=detail&poll_id={$poll_id}' class='btn-secondary'>← Zurück</a>";
    return;
}

// Templates laden
$templates = get_answer_templates($pdo);

// Meetings für list-Auswahl laden (optional, falls noch verwendet)
$stmt = $pdo->prepare("
    SELECT meeting_id, meeting_name, meeting_date
    FROM svmeetings
    WHERE status IN ('preparation', 'active')
    ORDER BY meeting_date DESC
    LIMIT 50
");
$stmt->execute();
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alle Mitglieder für Teilnehmer-Auswahl laden
if (!isset($all_members)) {
    $all_members = get_all_members($pdo);
}

// Existierende Teilnehmer laden (für list-type)
$existing_participant_ids = [];
if ($poll['target_type'] === 'list') {
    $stmt = $pdo->prepare("SELECT member_id FROM svopinion_poll_participants WHERE poll_id = ?");
    $stmt->execute([$poll_id]);
    $existing_participant_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'member_id');
}
?>

<h3>Meinungsbild bearbeiten</h3>

<div style="background: #e7f3ff; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #2196F3;">
    <strong>ℹ️ Hinweis:</strong> Du kannst diese Umfrage bearbeiten, solange nur deine eigene Antwort (oder gar keine Antwort) vorhanden ist.
    Sobald weitere Personen geantwortet haben, ist eine Bearbeitung nicht mehr möglich.
</div>

<form method="POST" action="<?php echo (isset($form_action_path) ? $form_action_path : '') . 'process_opinion.php'; ?>" onsubmit="return validateOpinionForm()">
    <input type="hidden" name="action" value="update_opinion">
    <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">
    <input type="hidden" name="template_id" id="template_id" value="">

    <div class="opinion-card">
        <h4>1. Frage formulieren</h4>
        <div class="form-group">
            <label>Deine Frage:*</label>
            <textarea name="title" rows="3" required placeholder="z.B. Sollen wir das neue Feature implementieren?" style="width: 100%;"><?php echo htmlspecialchars($poll['title']); ?></textarea>
        </div>
    </div>

    <div class="opinion-card">
        <h4>2. Zielgruppe wählen</h4>
        <div class="form-group">
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="individual" <?php echo ($poll['target_type'] === 'individual') ? 'checked' : ''; ?> onchange="updateTargetOptions()">
                <strong>Individuell</strong> - Link, den du weitergeben kannst
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="list" <?php echo ($poll['target_type'] === 'list') ? 'checked' : ''; ?> onchange="updateTargetOptions()">
                <strong>Ausgewählte registrierte Teilnehmer</strong>
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="public" <?php echo ($poll['target_type'] === 'public') ? 'checked' : ''; ?> onchange="updateTargetOptions()">
                <strong>Öffentlich</strong> - Jeder Besucher der Seite kann antworten
            </label>
        </div>

        <div id="list-selection" style="display: <?php echo ($poll['target_type'] === 'list') ? 'block' : 'none'; ?>; margin-top: 15px;">
            <label>Teilnehmer auswählen (nur diese können antworten):*</label>
            <div class="participant-buttons" style="margin: 10px 0;">
                <button type="button" onclick="toggleAllOpinionParticipants(true)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✓ Alle auswählen</button>
                <button type="button" onclick="toggleAllOpinionParticipants(false)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✗ Alle abwählen</button>
                <button type="button" onclick="toggleOpinionLeadershipRoles()" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">👔 Führungsrollen</button>
                <button type="button" onclick="toggleOpinionTopManagement()" class="btn-secondary" style="padding: 5px 10px;">⭐ Vorstand+GF+Ass</button>
            </div>
            <div class="participants-selector" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                <?php foreach ($all_members as $member): ?>
                    <label style="display: block; margin: 5px 0;">
                        <input type="checkbox"
                               name="opinion_participant_ids[]"
                               value="<?php echo $member['member_id']; ?>"
                               class="opinion-participant-checkbox"
                               data-role="<?php echo htmlspecialchars($member['role']); ?>"
                               <?php echo in_array($member['member_id'], $existing_participant_ids) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="opinion-card">
        <h4>3. Antwortmöglichkeiten</h4>

        <p style="color: #666; font-size: 14px;">Aktuelle Antwortmöglichkeiten (Bearbeitung erfolgt durch Hinzufügen/Entfernen von Optionen):</p>

        <div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 6px;">
            <?php for ($i = 1; $i <= max(10, count($poll['options'])); $i++): ?>
                <?php
                $existing_option = isset($poll['options'][$i-1]) ? $poll['options'][$i-1] : null;
                ?>
                <div style="margin-bottom: 10px;">
                    <label style="font-weight: 600;"><?php echo $i; ?>.</label>
                    <input type="text"
                           name="custom_option_<?php echo $i; ?>"
                           value="<?php echo $existing_option ? htmlspecialchars($existing_option['option_text']) : ''; ?>"
                           placeholder="Antwortmöglichkeit <?php echo $i; ?>"
                           style="width: 100%; padding: 8px;">
                    <?php if ($existing_option): ?>
                        <input type="hidden" name="option_id_<?php echo $i; ?>" value="<?php echo $existing_option['option_id']; ?>">
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="opinion-card">
        <h4>4. Einstellungen</h4>

        <div class="form-group">
            <label>
                <input type="checkbox" name="allow_multiple" value="1" <?php echo $poll['allow_multiple_answers'] ? 'checked' : ''; ?>>
                <strong>Mehrfachantworten erlauben</strong>
            </label>
            <small style="display: block; margin-left: 24px; color: #666;">
                Teilnehmer können mehrere Optionen auswählen
            </small>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label>
                <input type="checkbox" name="is_anonymous" value="1" <?php echo $poll['is_anonymous'] ? 'checked' : ''; ?>>
                <strong>Anonyme Umfrage</strong>
            </label>
            <small style="display: block; margin-left: 24px; color: #666;">
                Namen der Teilnehmer werden nicht in den Ergebnissen angezeigt
            </small>
        </div>

        <?php
        // Berechne verbleibende Tage
        $now = new DateTime();
        $ends_at = new DateTime($poll['ends_at']);
        $remaining_days = max(1, $now->diff($ends_at)->days);

        $created_at = new DateTime($poll['created_at']);
        $show_intermediate_days = $poll['show_intermediate_after_days'];
        ?>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px;">
            <div class="form-group">
                <label>Laufzeit (Tage):*</label>
                <input type="number" name="duration_days" value="<?php echo $remaining_days; ?>" min="1" max="365" required style="width: 100%;">
                <small style="color: #666;">Aktuell noch <?php echo $remaining_days; ?> Tage</small>
            </div>

            <div class="form-group">
                <label>Zwischenergebnisse zeigen nach (Tagen):*</label>
                <input type="number" name="show_intermediate_after_days" value="<?php echo $show_intermediate_days; ?>" min="0" max="365" required style="width: 100%;">
                <small style="color: #666;">0 = sofort sichtbar</small>
            </div>

            <div class="form-group">
                <label>Auto-Löschung nach (Tagen):*</label>
                <input type="number" name="delete_after_days" value="<?php echo $poll['auto_delete_after_days'] ?? 30; ?>" min="1" max="365" required style="width: 100%;">
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 15px;">
        <button type="submit" class="btn-primary">Änderungen speichern</button>
        <a href="?tab=opinion&view=detail&poll_id=<?php echo $poll_id; ?>" class="btn-secondary" style="text-decoration: none; display: inline-block; padding: 10px 20px;">Abbrechen</a>
    </div>
</form>

<script>
function updateTargetOptions() {
    const targetType = document.querySelector('input[name="target_type"]:checked').value;
    const listSelection = document.getElementById('list-selection');

    if (targetType === 'list') {
        listSelection.style.display = 'block';
    } else {
        listSelection.style.display = 'none';
    }
}

// Teilnehmer-Auswahl-Funktionen für Meinungsbilder
function toggleAllOpinionParticipants(select) {
    const checkboxes = document.querySelectorAll('.opinion-participant-checkbox');
    checkboxes.forEach(cb => cb.checked = select);
}

function toggleOpinionLeadershipRoles() {
    const checkboxes = document.querySelectorAll('.opinion-participant-checkbox');
    checkboxes.forEach(cb => {
        const role = cb.getAttribute('data-role')?.toLowerCase();
        if (role === 'vorstand' || role === 'gf' || role === 'assistenz' || role === 'fuehrungsteam' ||
            role === 'geschäftsführung' || role === 'führungsteam') {
            cb.checked = !cb.checked;
        }
    });
}

function toggleOpinionTopManagement() {
    const checkboxes = document.querySelectorAll('.opinion-participant-checkbox');
    checkboxes.forEach(cb => {
        const role = cb.getAttribute('data-role')?.toLowerCase();
        if (role === 'vorstand' || role === 'gf' || role === 'assistenz' || role === 'geschäftsführung') {
            cb.checked = !cb.checked;
        }
    });
}

// Formular-Validierung
function validateOpinionForm() {
    // Prüfen ob mindestens eine Option ausgefüllt ist
    let hasOption = false;
    for (let i = 1; i <= 10; i++) {
        const field = document.querySelector(`input[name="custom_option_${i}"]`);
        if (field && field.value.trim() !== '') {
            hasOption = true;
            break;
        }
    }

    if (!hasOption) {
        alert('Bitte gib mindestens eine Antwortmöglichkeit ein.');
        return false;
    }

    return true;
}
</script>
