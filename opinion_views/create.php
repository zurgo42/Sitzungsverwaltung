<?php
/**
 * Meinungsbild erstellen
 */

if (!$current_user) {
    echo "<p>Bitte melden Sie sich an.</p>";
    return;
}

// Templates laden
$templates = get_answer_templates($pdo);

// Meetings für list-Auswahl laden
$stmt = $pdo->prepare("
    SELECT meeting_id, meeting_name, meeting_date
    FROM meetings
    WHERE status IN ('preparation', 'active')
    ORDER BY meeting_date DESC
    LIMIT 50
");
$stmt->execute();
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h3>Neues Meinungsbild erstellen</h3>

<form method="POST" action="process_opinion.php">
    <input type="hidden" name="action" value="create_opinion">
    <input type="hidden" name="template_id" id="template_id" value="">

    <div class="opinion-card">
        <h4>1. Frage formulieren</h4>
        <div class="form-group">
            <label>Ihre Frage:*</label>
            <textarea name="title" rows="3" required placeholder="z.B. Sollen wir das neue Feature implementieren?" style="width: 100%;"></textarea>
        </div>
    </div>

    <div class="opinion-card">
        <h4>2. Zielgruppe wählen</h4>
        <div class="form-group">
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="individual" checked onchange="updateTargetOptions()">
                <strong>Individuell</strong> - Link, den Sie weitergeben können
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="list" onchange="updateTargetOptions()">
                <strong>Meeting-Teilnehmer</strong> - Teilnehmer eines bestimmten Meetings
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="public" onchange="updateTargetOptions()">
                <strong>Öffentlich</strong> - Jeder Besucher der Seite kann antworten
            </label>
        </div>

        <div id="list-selection" style="display: none; margin-top: 15px;">
            <label>Meeting auswählen:</label>
            <select name="list_id" style="width: 100%; padding: 8px;">
                <option value="">- Meeting wählen -</option>
                <?php foreach ($meetings as $meeting): ?>
                    <option value="<?php echo $meeting['meeting_id']; ?>">
                        <?php echo htmlspecialchars($meeting['meeting_name'] . ' - ' . date('d.m.Y H:i', strtotime($meeting['meeting_date']))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="opinion-card">
        <h4>3. Antwortmöglichkeiten festlegen</h4>

        <p>Wählen Sie ein vorgefertigtes Antwort-Set oder geben Sie eigene Antworten ein:</p>

        <div class="template-selector">
            <?php foreach ($templates as $template): ?>
                <div class="template-card" onclick="selectTemplate(<?php echo $template['template_id']; ?>)">
                    <input type="radio" name="template_radio" value="<?php echo $template['template_id']; ?>">
                    <div style="font-weight: bold; margin-bottom: 5px;">
                        <?php echo htmlspecialchars($template['template_name']); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <?php echo htmlspecialchars($template['description']); ?>
                    </div>
                    <div style="margin-top: 8px; font-size: 11px; color: #999;">
                        <?php
                        $options = [];
                        for ($i = 1; $i <= 10; $i++) {
                            if (!empty($template["option_$i"])) {
                                $options[] = $template["option_$i"];
                            }
                        }
                        echo count($options) . ' Optionen';
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="custom-options-section" style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #ddd;">
            <h5>Oder: Eigene Antwortmöglichkeiten eingeben (bis zu 10)</h5>
            <small style="color: #666;">Wenn Sie eigene Antworten eingeben, wird kein Template verwendet.</small>

            <div class="custom-options-grid" style="margin-top: 15px;">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div>
                        <label><?php echo $i; ?>.</label>
                        <input type="text" name="custom_option_<?php echo $i; ?>" placeholder="Antwortmöglichkeit <?php echo $i; ?>" style="width: 100%;">
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="opinion-card">
        <h4>4. Einstellungen</h4>

        <div class="form-group">
            <label>
                <input type="checkbox" name="allow_multiple" value="1">
                <strong>Mehrfachantworten erlauben</strong>
            </label>
            <small style="display: block; margin-left: 24px; color: #666;">
                Teilnehmer können mehrere Optionen auswählen
            </small>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label>
                <input type="checkbox" name="is_anonymous" value="1">
                <strong>Anonyme Umfrage</strong>
            </label>
            <small style="display: block; margin-left: 24px; color: #666;">
                Namen der Teilnehmer werden nicht in den Ergebnissen angezeigt
            </small>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px;">
            <div class="form-group">
                <label>Laufzeit (Tage):*</label>
                <input type="number" name="duration_days" value="14" min="1" max="365" required style="width: 100%;">
            </div>

            <div class="form-group">
                <label>Zwischenergebnisse zeigen nach (Tagen):*</label>
                <input type="number" name="show_intermediate_after_days" value="7" min="0" max="365" required style="width: 100%;">
                <small style="color: #666;">0 = sofort sichtbar</small>
            </div>

            <div class="form-group">
                <label>Auto-Löschung nach (Tagen):*</label>
                <input type="number" name="delete_after_days" value="30" min="1" max="365" required style="width: 100%;">
            </div>
        </div>
    </div>

    <div class="opinion-card">
        <h4>5. E-Mail-Benachrichtigung (optional)</h4>

        <div class="form-group">
            <label>
                <input type="checkbox" name="send_email" value="1" id="send_email_checkbox" onchange="toggleEmailOptions()">
                <strong>E-Mail mit Umfrage-Link verschicken</strong>
            </label>
        </div>

        <div id="email-options" style="display: none; margin-left: 24px; margin-top: 10px;">
            <label style="display: block; margin-bottom: 8px;">
                <input type="radio" name="email_target" value="creator" checked>
                An mich (zum Weiterleiten)
            </label>
            <label style="display: block;">
                <input type="radio" name="email_target" value="participants">
                An den gewählten Personenkreis
            </label>
        </div>
    </div>

    <div style="display: flex; gap: 15px;">
        <button type="submit" class="btn-primary">Meinungsbild erstellen</button>
        <a href="?tab=opinion" class="btn-secondary" style="text-decoration: none; display: inline-block; padding: 10px 20px;">Abbrechen</a>
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

function toggleEmailOptions() {
    const checkbox = document.getElementById('send_email_checkbox');
    const options = document.getElementById('email-options');
    options.style.display = checkbox.checked ? 'block' : 'none';
}
</script>
