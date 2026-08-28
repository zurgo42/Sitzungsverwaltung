<?php
/**
 * Meinungsbild erstellen
 */

if (!$current_user) {
    echo "<p>Bitte melde dich an.</p>";
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
?>

<h3>Neues Meinungsbild erstellen</h3>

<form method="POST" action="<?php echo htmlspecialchars($_tab_process_url); ?>" onsubmit="return validateOpinionForm()">
    <input type="hidden" name="action" value="create_opinion">
    <input type="hidden" name="template_id" id="template_id" value="">
    <?php if (!empty($_tab_redirect_to)): ?><input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_tab_redirect_to); ?>"><?php endif; ?>
    <?php if (!empty($OPINION_MTOOL_MODE) && !empty($MNr)): ?><input type="hidden" name="mtool_mnr" value="<?php echo htmlspecialchars($MNr); ?>"><?php endif; ?>

    <div class="opinion-card">
        <h4>1. Frage formulieren</h4>
        <div class="form-group">
            <label>Deine Frage:*</label>
            <textarea name="title" rows="3" required placeholder="z.B. Sollen wir das neue Feature implementieren?" style="width: 100%;"></textarea>
        </div>
    </div>

    <div class="opinion-card">
        <h4>2. Zielgruppe wählen</h4>
        <div class="form-group">
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="individual" checked onchange="updateTargetOptions()">
                <strong>Individuell</strong> - Link, den du weitergeben kannst
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="list" onchange="updateTargetOptions()">
                <strong>Ausgewählte registrierte Teilnehmer</strong>
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <input type="radio" name="target_type" value="public" onchange="updateTargetOptions()">
                <strong>Öffentlich</strong> - Jeder Besucher der Seite kann antworten
            </label>
        </div>

        <div id="list-selection" style="display: none; margin-top: 15px;">
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
                               data-role="<?php echo htmlspecialchars($member['role']); ?>">
                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="opinion-card">
        <h4>3. Antwortmöglichkeiten festlegen</h4>

        <p>Wähle ein vorgefertigtes Antwort-Set oder gib eigene Antworten ein:</p>

        <?php
        // Templates sortieren: "Frei" ans Ende
        $frei_template = null;
        $other_templates = [];

        foreach ($templates as $template) {
            if (stripos($template['template_name'], 'Frei') !== false) {
                $frei_template = $template;
            } else {
                $other_templates[] = $template;
            }
        }

        // Falls "Frei" gefunden, ans Ende hängen
        if ($frei_template) {
            $sorted_templates = array_merge($other_templates, [$frei_template]);
        } else {
            $sorted_templates = $templates;
        }
        ?>

        <table class="template-table" style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="padding: 10px; text-align: left; width: 50px;">Wahl</th>
                    <th style="padding: 10px; text-align: left;">Template</th>
                    <th style="padding: 10px; text-align: left;">Beschreibung</th>
                    <th style="padding: 10px; text-align: center; width: 100px;">Optionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sorted_templates as $template): ?>
                    <?php
                    // Optionen sammeln
                    $options = [];
                    for ($i = 1; $i <= 10; $i++) {
                        if (!empty($template["option_$i"])) {
                            $options[] = htmlspecialchars($template["option_$i"]);
                        }
                    }

                    // Prüfen ob dies das "Frei" Template ist
                    $is_frei = (stripos($template['template_name'], 'Frei') !== false);
                    ?>
                    <tr>
                        <td colspan="4" style="padding: 0;">
                            <details class="template-accordion" style="border-bottom: 1px solid #ddd;">
                                <summary style="padding: 12px; cursor: pointer; list-style: none; display: flex; align-items: center; gap: 10px;">
                                    <input type="radio" name="template_radio" value="<?php echo $template['template_id']; ?>"
                                           onclick="selectTemplate(<?php echo $template['template_id']; ?>); event.stopPropagation();"
                                           style="margin: 0;">
                                    <span style="font-weight: bold; flex: 1;"><?php echo htmlspecialchars($template['template_name']); ?></span>
                                    <span style="font-size: 12px; color: #666; flex: 2;"><?php echo htmlspecialchars($template['description']); ?></span>
                                    <span style="font-size: 11px; color: #999;"><?php echo $is_frei ? '10 Eingabefelder' : count($options) . ' Optionen'; ?> ▼</span>
                                </summary>
                                <div style="padding: 15px; background: #f9f9f9; border-top: 1px solid #ddd;">
                                    <?php if ($is_frei): ?>
                                        <!-- Eigene Antwortmöglichkeiten für "Frei" Template -->
                                        <strong style="display: block; margin-bottom: 10px;">Eigene Antwortmöglichkeiten eingeben (bis zu 10):</strong>
                                        <div class="custom-options-grid" style="margin-top: 15px;">
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <div>
                                                    <label><?php echo $i; ?>.</label>
                                                    <input type="text" name="custom_option_<?php echo $i; ?>" placeholder="Antwortmöglichkeit <?php echo $i; ?>" style="width: 100%;">
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Vordefinierte Optionen für andere Templates -->
                                        <strong style="display: block; margin-bottom: 10px;">Antwortmöglichkeiten:</strong>
                                        <ol style="margin: 0; padding-left: 20px;">
                                            <?php foreach ($options as $option): ?>
                                                <li style="margin: 5px 0;"><?php echo $option; ?></li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
                <label>Automatisch löschen am:*</label>
                <input type="date" name="delete_at_date" id="delete_at_date" required style="width: 100%;">
                <small style="color: #666;">Datum, zu dem die Umfrage endgültig gelöscht wird</small>
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
        <a href="<?php echo _opinion_url(); ?>" class="btn-secondary" style="text-decoration: none; display: inline-block; padding: 10px 20px;">Abbrechen</a>
    </div>
</form>

<script>
// Löschdatum: min = morgen, default = heute + 90 Tage
(function() {
    const field = document.getElementById('delete_at_date');
    if (!field) return;
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    const default90 = new Date(); default90.setDate(default90.getDate() + 90);
    const fmt = d => d.toISOString().slice(0, 10);
    field.min   = fmt(tomorrow);
    field.value = fmt(default90);
})();

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

// Template-Auswahl
function selectTemplate(templateId) {
    document.getElementById('template_id').value = templateId;
}

// Formular-Validierung
function validateOpinionForm() {
    const templateId = document.getElementById('template_id').value;

    // Prüfen ob ein Template ausgewählt wurde
    if (!templateId) {
        // Prüfen ob mindestens ein custom option Feld ausgefüllt ist
        let hasCustomOption = false;
        for (let i = 1; i <= 10; i++) {
            const field = document.querySelector(`input[name="custom_option_${i}"]`);
            if (field && field.value.trim() !== '') {
                hasCustomOption = true;
                break;
            }
        }

        if (!hasCustomOption) {
            alert('Bitte wähle ein Antwort-Template aus oder gib eigene Antwortmöglichkeiten ein.');
            return false;
        }
    }

    return true;
}
</script>
