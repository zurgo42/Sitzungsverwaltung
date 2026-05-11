<?php
/**
 * tab_agenda_display_ended.php - Anzeige nach Sitzungsende
 * Protokollant kann editieren, Teilnehmer können nachträgliche Kommentare hinzufügen
 */

if (empty($agenda_items)) {
    echo '<div class="info-box">Keine Tagesordnungspunkte vorhanden.</div>';
    return;
}

// DEBUG MODUS (immer sichtbar wenn ?debug=1)
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    echo '<div style="background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; font-family: monospace; font-size: 13px;">';
    echo '<strong style="font-size: 16px;">🔍 DEBUG-INFO</strong><br><br>';
    echo '<strong>Meeting Status:</strong> ' . htmlspecialchars($meeting['status'] ?? 'undefined') . '<br>';
    echo '<strong>Is Secretary:</strong> ' . ($is_secretary ? '✅ JA' : '❌ NEIN') . '<br>';
    echo '<strong>Current User ID:</strong> ' . ($current_user['member_id'] ?? 'undefined') . '<br>';
    echo '<strong>Secretary ID in Meeting:</strong> ' . ($meeting['secretary_member_id'] ?? 'undefined') . '<br>';
    echo '<strong>POST submitted:</strong> ' . ($_SERVER['REQUEST_METHOD'] === 'POST' ? '✅ JA' : '❌ NEIN (GET)') . '<br>';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo '<strong>POST save_ended_changes:</strong> ' . (isset($_POST['save_ended_changes']) ? '✅ Vorhanden' : '❌ Fehlt') . '<br>';
    }
    echo '<strong>URL Success Param:</strong> ' . (isset($_GET['success']) ? $_GET['success'] : 'keiner') . '<br>';
    echo '<strong>URL Error Param:</strong> ' . (isset($_GET['error']) ? $_GET['error'] : 'keiner') . '<br>';
    echo '</div>';
}
?>

<h3 style="margin: 20px 0 15px 0;">📋 Sitzungsverlauf - Protokoll in Bearbeitung</h3>

<!-- TEILNEHMERLISTE -->
<?php if ($is_secretary): ?>
    <details open style="margin: 20px 0; padding: 15px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 8px;">
        <summary style="cursor: pointer; font-weight: 600; color: #1976d2; font-size: 16px; margin-bottom: 10px;">
            👥 Teilnehmerverwaltung (klicken zum Auf-/Zuklappen)
        </summary>

        <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>">
            <input type="hidden" name="update_attendance" value="1">

            <div style="margin-bottom: 15px;">
                <button type="button" onclick="setAllPresent()" style="background: #4caf50; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    ✅ Alle auf "Anwesend" setzen
                </button>
            </div>

            <div style="display: grid; gap: 10px;">
                <?php foreach ($participants as $p):
                    $stmt = $pdo->prepare("SELECT attendance_status FROM svmeeting_participants WHERE meeting_id = ? AND member_id = ?");
                    $stmt->execute([$current_meeting_id, $p['member_id']]);
                    $attendance = $stmt->fetch();
                    $status = $attendance['attendance_status'] ?? 'absent';
                ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 8px; background: white; border-radius: 4px;">
                        <span style="flex: 1; font-weight: 600;">
                            <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                        </span>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="present"
                                   class="attendance-radio"
                                   data-member="<?php echo $p['member_id']; ?>"
                                   <?php echo $status === 'present' ? 'checked' : ''; ?>>
                            <span>✅ Anwesend</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="partial"
                                   <?php echo $status === 'partial' ? 'checked' : ''; ?>>
                            <span>⏱️ Zeitweise</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="absent"
                                   <?php echo $status === 'absent' ? 'checked' : ''; ?>>
                            <span>❌ Abwesend</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" style="margin-top: 15px; background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                💾 Teilnehmerliste speichern
            </button>
        </form>

        <script>
        function setAllPresent() {
            document.querySelectorAll('.attendance-radio').forEach(radio => {
                if (radio.value === 'present') {
                    radio.checked = true;
                }
            });
        }
        </script>
    </details>
<?php else: ?>
    <?php render_readonly_participant_list($pdo, $current_meeting_id, $participants); ?>
<?php endif; ?>

<!-- Info-Box -->
<div style="margin: 15px 0; padding: 12px; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 4px;">
    <strong>ℹ️ Status:</strong> Die Sitzung ist beendet. 
    <?php if ($is_secretary): ?>
        Du kannst dein Protokoll noch bearbeiten.
    <?php else: ?>
        Du kannst nachträgliche Anmerkungen zu den TOPs hinzufügen.
    <?php endif; ?>
</div>

<!-- TOPS ANZEIGEN -->
<form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>">
    <input type="hidden" name="save_ended_changes" value="1">

<?php
// Berechtigung für vertrauliche TOPs prüfen
$can_see_confidential = (
    $current_user['is_admin'] == 1 ||
    $current_user['is_confidential'] == 1 ||
    in_array($current_user['role'], ['vorstand', 'gf']) ||
    $is_secretary ||
    $is_chairman
);

// Debug-Info (nur für Secretary)
if ($is_secretary && isset($_GET['debug'])) {
    echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107;">';
    echo '🔍 <strong>Debug-Info:</strong><br>';
    echo 'Meeting Status: ' . ($meeting['status'] ?? 'undefined') . '<br>';
    echo 'Is Secretary: ' . ($is_secretary ? 'Ja' : 'Nein') . '<br>';
    echo 'User ID: ' . $current_user['member_id'] . '<br>';
    echo '</div>';
}

foreach ($agenda_items as $item): 
    // TOP 999 überspringen
    if ($item['top_number'] == 999) {
        continue;
    }
    
    // Vertrauliche TOPs nur für berechtigte User
    if ($item['is_confidential'] && !$can_see_confidential) {
        continue;
    }
?>
    <div class="agenda-item" id="top-<?php echo $item['item_id']; ?>" 
         style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border: 3px solid #667eea; border-radius: 8px;">
        
        <!-- TOP-Header -->
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
            <strong style="font-size: 16px; color: #333;">
                TOP <?php echo $item['top_number']; ?>: <?php echo htmlspecialchars($item['title']); ?>
            </strong>
            <?php render_category_badge($item['category']); ?>
            <?php if ($item['is_confidential']): ?>
                <span class="badge" style="background: #f39c12; color: white;">🔒 Vertraulich</span>
            <?php endif; ?>
        </div>
        
        <?php render_proposal_display($item['proposal_text']); ?>
        
        <!-- Beschreibung -->
        <?php if ($item['description']): ?>
            <div style="color: #666; margin: 8px 0; font-size: 14px;">
                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
            </div>
        <?php endif; ?>
        
        <!-- Diskussionsbeiträge aus Vorbereitung (zugeklappt, nur wenn vorhanden) -->
        <?php
        $prep_comments = get_item_comments($pdo, $item['item_id']);
        if (!empty($prep_comments)):
        ?>
            <details style="margin-top: 10px;">
                <summary style="cursor: pointer; color: #667eea; font-weight: 600; padding: 6px; background: #f9f9f9; border-radius: 4px; font-size: 13px;">
                    💬 Diskussionsbeiträge aus Vorbereitung
                </summary>
                <div style="margin-top: 8px; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 4px;">
                    <?php
                    foreach ($prep_comments as $comment):
                        render_comment_line($comment, 'full');
                    endforeach;
                    ?>
                </div>
            </details>
        <?php endif; ?>
        
        <!-- Live-Kommentare (zugeklappt, falls vorhanden) -->
        <?php
        $stmt = $pdo->prepare("
            SELECT alc.*, m.first_name, m.last_name
            FROM svagenda_live_comments alc
            JOIN svmembers m ON alc.member_id = m.member_id
            WHERE alc.item_id = ?
            ORDER BY alc.created_at ASC
        ");
        $stmt->execute([$item['item_id']]);
        $live_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($live_comments)):
        ?>
        <details style="margin-top: 10px;">
            <summary style="cursor: pointer; color: #f44336; font-weight: 600; padding: 6px; background: #ffebee; border-radius: 4px; font-size: 13px;">
                💬 Live-Kommentare während Sitzung
            </summary>
            <div style="margin-top: 8px; padding: 8px; background: white; border: 1px solid #f44336; border-radius: 4px;">
                <?php foreach ($live_comments as $lc): ?>
                    <?php render_comment_line($lc, 'time'); ?>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
        
        <!-- PROTOKOLL -->
        <?php if ($is_secretary): ?>
            <!-- Protokollant kann editieren -->
            <div style="margin-top: 15px; padding: 12px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 6px;">
                <h4 style="color: #1976d2; margin-bottom: 10px;">📝 Protokoll (editierbar)</h4>

                <div class="form-group">
                    <label style="font-weight: 600;">Protokollnotizen:</label>
                    <textarea name="protocol_text[<?php echo $item['item_id']; ?>]"
                              rows="6"
                              placeholder="Notizen zu diesem TOP..."
                              style="width: 100%; padding: 8px; border: 1px solid #2196f3; border-radius: 4px;"><?php echo htmlspecialchars($item['protocol_notes'] ?? ''); ?></textarea>
                </div>

                <?php
                // Abstimmungsfelder bei Antrag/Beschluss
                if ($item['category'] === 'antrag_beschluss') {
                    render_voting_fields($item['item_id'], $item);
                }
                ?>

                <!-- TOP löschen (nur für Protokollant) -->
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <button type="submit"
                            name="delete_agenda_item"
                            value="1"
                            onclick="return confirm('⚠️ TOP wirklich löschen?\n\nDieser Vorgang kann nicht rückgängig gemacht werden!\n\n• Das TOP wird komplett entfernt\n• Alle Kommentare gehen verloren\n• Das Protokoll für diesen TOP wird gelöscht')"
                            style="background: #f44336; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        🗑️ TOP löschen
                    </button>
                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                </div>
            </div>
        <?php elseif (!empty($item['protocol_notes'])): ?>
            <!-- Andere Teilnehmer sehen Protokoll read-only -->
            <div style="margin-top: 15px; padding: 10px; background: #f0f7ff; border-left: 4px solid #2196f3; border-radius: 4px;">
                <strong style="color: #1976d2;">📝 Protokoll:</strong><br>
                <div style="margin-top: 6px; color: #333; font-size: 14px; line-height: 1.6;">
                    <?php echo nl2br(linkify_text($item['protocol_notes'])); ?>
                </div>
                <?php render_voting_result($item); ?>
            </div>
        <?php endif; ?>

        <!-- EIGENE PERSÖNLICHE NOTIZEN (nur für den eingeloggten User, falls vorhanden) -->
        <?php
        require_once 'personal_notes_functions.php';
        $personal_note = get_personal_note($pdo, $item['item_id'], $current_user['member_id']);
        if ($personal_note && !empty(trim($personal_note['note_text']))):
        ?>
            <div style="margin-top: 15px; padding: 12px; background: #f1f8e9; border-left: 4px solid #8bc34a; border-radius: 4px;">
                <strong style="color: #558b2f;">📄 Meine persönlichen Notizen:</strong><br>
                <div style="margin-top: 6px; color: #333; font-size: 13px; line-height: 1.6; white-space: pre-wrap; font-family: inherit;">
                    <?php echo htmlspecialchars($personal_note['note_text']); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- NACHTRÄGLICHE ANMERKUNGEN -->

        <!-- 1. ALLE ANMERKUNGEN (für alle eingeladenen Teilnehmer sichtbar, readonly) -->
        <?php
        // Alle nachträglichen Kommentare laden (inkl. eigener)
        $stmt = $pdo->prepare("
            SELECT *
            FROM svagenda_post_comments
            WHERE item_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$item['item_id']]);
        $all_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mitgliederdaten über Adapter laden
        foreach ($all_comments as &$comment) {
            $member = get_member_by_id($pdo, $comment['member_id']);
            if ($member) {
                $comment['first_name'] = $member['first_name'];
                $comment['last_name'] = $member['last_name'];
            } else {
                $comment['first_name'] = 'Unbekannt';
                $comment['last_name'] = '';
            }
        }
        unset($comment); // Referenz aufheben
        ?>

        <div style="margin-top: 15px; padding: 12px; background: #fff3e0; border: 2px solid #ff9800; border-radius: 6px;">
            <h4 style="color: #e65100; margin-bottom: 8px;">👥 Nachträgliche Anmerkungen zum Protokollentwurf</h4>

            <?php if (!empty($all_comments)): ?>
                <div style="background: white; border: 1px solid #ff9800; border-radius: 4px; padding: 10px; max-height: 300px; overflow-y: auto;">
                    <?php foreach ($all_comments as $pc): ?>
                        <div style="padding: 8px 0; border-bottom: 1px solid #ffe0b2; font-size: 13px;">
                            <strong style="color: <?php echo ($pc['member_id'] == $current_user['member_id']) ? '#2e7d32' : '#e65100'; ?>;">
                                <?php echo htmlspecialchars($pc['first_name'] . ' ' . $pc['last_name']); ?>
                                <?php if ($pc['member_id'] == $current_user['member_id']): ?>
                                    <span style="font-size: 11px; color: #2e7d32;">(Du)</span>
                                <?php endif; ?>
                            </strong>
                            <span style="color: #999; font-size: 11px; margin-left: 8px;">
                                <?php echo date('d.m.Y H:i', strtotime($pc['created_at'])); ?>
                            </span>
                            <div style="margin-top: 4px; color: #333; line-height: 1.5;">
                                <?php echo nl2br(htmlspecialchars($pc['comment_text'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="background: white; border: 1px solid #ff9800; border-radius: 4px; padding: 10px; color: #999; font-style: italic; text-align: center;">
                    Noch keine nachträglichen Anmerkungen vorhanden.
                </div>
            <?php endif; ?>

            <div style="margin-top: 8px; padding: 6px; background: rgba(255,255,255,0.5); border-radius: 4px; font-size: 11px; color: #666; font-style: italic;">
                ℹ️ Diese Anmerkungen sind readonly - nutze das Feld unten zum Bearbeiten
            </div>
        </div>

        <!-- 2. EIGENE ANMERKUNG (editierbar) -->
        <div style="margin-top: 15px; padding: 12px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 6px;">
            <h4 style="color: #2e7d32; margin-bottom: 8px;">✏️ Deine Anmerkung zu diesem TOP</h4>

            <?php
            $stmt = $pdo->prepare("
                SELECT comment_text, comment_id
                FROM svagenda_post_comments
                WHERE item_id = ? AND member_id = ?
            ");
            $stmt->execute([$item['item_id'], $current_user['member_id']]);
            $my_post_comment = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>

            <div class="form-group">
                <textarea name="post_comment[<?php echo $item['item_id']; ?>]"
                          rows="3"
                          placeholder="Ihre nachträgliche Anmerkung zum Protokollentwurf..."
                          style="width: 100%; padding: 8px; border: 1px solid #4caf50; border-radius: 4px; font-size: 13px;"><?php echo htmlspecialchars($my_post_comment['comment_text'] ?? ''); ?></textarea>
            </div>

            <div style="margin-top: 8px; padding: 6px; background: rgba(255,255,255,0.5); border-radius: 4px; font-size: 11px; color: #666; font-style: italic;">
                ℹ️ Diese Anmerkung ist für alle Teilnehmer sichtbar und bleibt bis zur Protokollgenehmigung erhalten
            </div>
        </div>

        <!-- TODO-FUNKTION für alle User -->
        <details style="margin-top: 15px; background: #fff8e1; border: 2px solid #ffc107; border-radius: 6px; overflow: hidden;">
            <summary style="padding: 10px 15px; cursor: pointer; font-weight: 600; color: #f57c00; font-size: 14px; user-select: none;">
                <span style="display: inline-block; transform: rotate(0deg); transition: transform 0.2s;">▶</span>
                📝 TODO zu diesem TOP aufschreiben
            </summary>

            <div style="padding: 15px; background: #fffef5;">
                <!-- KEIN verschachteltes Form-Tag mehr! Nutzt das Hauptformular -->
                <input type="hidden" name="todo_item_id_<?php echo $item['item_id']; ?>" value="<?php echo $item['item_id']; ?>">

                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">
                        Titel:
                    </label>
                    <input type="text" name="todo_title_<?php echo $item['item_id']; ?>"
                           placeholder="z.B. Recherche für nächste Sitzung"
                           style="width: 100%; padding: 8px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px;">
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">
                        Beschreibung (optional):
                    </label>
                    <textarea name="todo_description_<?php echo $item['item_id']; ?>" rows="3"
                              placeholder="Details zum TODO..."
                              style="width: 100%; padding: 8px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px; resize: vertical;"></textarea>
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">
                        Fällig bis (optional):
                    </label>
                    <input type="date" name="todo_due_date_<?php echo $item['item_id']; ?>"
                           style="padding: 8px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px;">
                </div>

                <?php if ($is_secretary): ?>
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">
                            Zuweisen an (nur Protokollführer):
                        </label>
                        <select name="todo_assigned_to_<?php echo $item['item_id']; ?>"
                                style="width: 100%; padding: 8px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px;">
                            <option value="">Mir selbst</option>
                            <?php foreach ($participants as $p): ?>
                                <option value="<?php echo $p['member_id']; ?>">
                                    <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" name="quick_todo_create" value="<?php echo $item['item_id']; ?>"
                            onclick="return confirm('TODO wirklich anlegen?');"
                            style="padding: 8px 16px; background: #ffc107; color: #333; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                        ✅ TODO anlegen
                    </button>
                    <span style="font-size: 12px; color: #666;">
                        <?php echo $is_secretary ? '(Für dich oder andere Teilnehmer)' : '(Privat - nur für dich sichtbar)'; ?>
                    </span>
                </div>
            </div>

            <style>
            /* Rotate arrow when details open */
            details[open] > summary span {
                transform: rotate(90deg) !important;
            }
            </style>
        </details>

    </div>
<?php endforeach; ?>

    <button type="submit" style="background: #2196f3; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 16px; margin: 20px 0;">
        💾 Änderungen speichern
    </button>
</form>

<?php if ($is_secretary): ?>
    <!-- KURZPROTOKOLL ANZEIGEN -->
    <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 2px solid #2196f3; border-radius: 8px;">
        <h3 style="color: #1976d2; margin-bottom: 15px;">📋 Kurzprotokoll (Vorschau)</h3>
        
        <?php 
        // Protokoll generieren
        $protocols = generate_protocol($pdo, $meeting, $agenda_items, $participants);
        
        if (!empty($protocols['public'])):
        ?>
            <h4 style="color: #666; margin: 15px 0 10px 0;">Öffentliches Protokoll:</h4>
            <?php display_protocol($protocols['public']); ?>
        <?php endif; ?>
        
        <?php if (!empty($protocols['confidential'])): ?>
            <h4 style="color: #666; margin: 25px 0 10px 0;">Vertrauliches Protokoll:</h4>
            <?php display_protocol($protocols['confidential']); ?>
        <?php endif; ?>
    </div>
    
    <!-- PROTOKOLL FREIGEBEN -->
    <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">✅ Protokoll zur Genehmigung freigeben</h4>
        <p style="color: #666; margin-bottom: 10px;">
            Wenn das Protokoll fertig ist, kannst du es zur Genehmigung durch den Sitzungsleiter freigeben.
        </p>
        <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>" onsubmit="return confirm('Protokoll wirklich freigeben? Du kannst danach noch Änderungen vornehmen.');">
            <input type="hidden" name="release_protocol" value="1">
            <button type="submit" style="background: #4caf50; color: white; padding: 10px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 4px; cursor: pointer;">
                ✅ Protokoll jetzt freigeben
            </button>
        </form>
    </div>
<?php endif; ?>

<script>
/**
 * Auto-Save für nachträgliche Anmerkungen
 * Speichert Eingaben automatisch im localStorage als Backup
 */
(function() {
    const MEETING_ID = <?php echo $current_meeting_id; ?>;
    const STORAGE_KEY = `meeting_${MEETING_ID}_post_comments`;
    const AUTO_SAVE_DELAY = 2000; // 2 Sekunden nach letzter Eingabe
    const DEBUG = false; // Debug-Modus (auf true setzen bei Problemen)

    let saveTimeout = null;

    function log(...args) {
        if (DEBUG) console.log('[Auto-Save]', ...args);
    }

    /**
     * Initialisierung
     */
    function init() {
        // Alle Anmerkungen-Textareas finden
        const textareas = document.querySelectorAll('textarea[name^="post_comment["]');

        log('Gefundene Textareas:', textareas.length);

        if (textareas.length === 0) {
            log('Keine Textareas gefunden - Auto-Save wird nicht aktiviert');
            return;
        }

        // Event-Listener für Auto-Save
        textareas.forEach((textarea, index) => {
            log(`Textarea ${index}:`, textarea.name);

            textarea.addEventListener('input', function() {
                log('Input-Event in', this.name, '- Plane Speicherung');

                // Gelben Hintergrund entfernen
                if (this.style.backgroundColor === 'rgb(255, 248, 225)') {
                    this.style.backgroundColor = '';
                }

                // Verzögertes Speichern
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    saveToStorage(textareas);
                }, AUTO_SAVE_DELAY);
            });
        });

        // Submit-Event: localStorage löschen
        const form = textareas[0].closest('form');
        if (form) {
            log('Form gefunden, Submit-Event registriert');
            form.addEventListener('submit', function() {
                log('Form wird abgeschickt - Lösche Auto-Save-Daten');
                localStorage.removeItem(STORAGE_KEY);
            });
        } else {
            log('WARNUNG: Kein Form gefunden!');
        }

        // Daten wiederherstellen
        restoreData(textareas);
    }

    /**
     * Daten aus localStorage laden
     */
    function loadFromStorage() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (!saved) {
                log('Keine gespeicherten Daten gefunden');
                return null;
            }

            const data = JSON.parse(saved);
            log('Daten geladen:', data);

            // Prüfen ob Daten älter als 7 Tage
            const age = Date.now() - data.timestamp;
            const ageHours = Math.floor(age / (60 * 60 * 1000));
            log(`Alter der Daten: ${ageHours} Stunden`);

            if (age > 7 * 24 * 60 * 60 * 1000) {
                log('Daten zu alt, werden gelöscht');
                localStorage.removeItem(STORAGE_KEY);
                return null;
            }

            return data.comments;
        } catch (e) {
            console.error('Fehler beim Laden der gespeicherten Anmerkungen:', e);
            return null;
        }
    }

    /**
     * Daten in localStorage speichern
     */
    function saveToStorage(textareas) {
        try {
            const comments = {};
            let hasContent = false;

            textareas.forEach(textarea => {
                const match = textarea.name.match(/post_comment\[(\d+)\]/);
                if (match && textarea.value.trim()) {
                    comments[match[1]] = textarea.value;
                    hasContent = true;
                }
            });

            if (hasContent) {
                const data = {
                    comments: comments,
                    timestamp: Date.now()
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                log('Daten gespeichert:', data);
            } else {
                localStorage.removeItem(STORAGE_KEY);
                log('Alle Felder leer - Auto-Save-Daten gelöscht');
            }
        } catch (e) {
            console.error('Fehler beim Speichern der Anmerkungen:', e);
        }
    }

    /**
     * Gespeicherte Daten wiederherstellen
     */
    function restoreData(textareas) {
        const savedComments = loadFromStorage();
        if (!savedComments) {
            log('Keine Daten zum Wiederherstellen');
            return false;
        }

        let restoredCount = 0;

        textareas.forEach(textarea => {
            const match = textarea.name.match(/post_comment\[(\d+)\]/);
            if (match && savedComments[match[1]]) {
                const currentValue = textarea.value.trim();
                const savedValue = savedComments[match[1]].trim();

                log(`Feld ${match[1]}: Aktuell="${currentValue}", Gespeichert="${savedValue}"`);

                // Wiederherstellen wenn Wert unterschiedlich ist
                if (currentValue !== savedValue) {
                    textarea.value = savedValue;
                    textarea.style.backgroundColor = '#fff8e1'; // Gelber Hintergrund
                    restoredCount++;
                    log(`Feld ${match[1]} wiederhergestellt (unterschiedlicher Wert)`);
                } else {
                    log(`Feld ${match[1]} nicht wiederhergestellt (identisch)`);
                }
            }
        });

        log(`Insgesamt ${restoredCount} Felder wiederhergestellt`);

        if (restoredCount > 0) {
            const banner = document.createElement('div');
            banner.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #ff9800; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10000; font-weight: 600;';
            banner.innerHTML = `
                ✅ ${restoredCount} nicht gespeicherte Anmerkung(en) wiederhergestellt
                <button onclick="this.parentElement.remove()" style="margin-left: 15px; background: white; color: #ff9800; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-weight: 600;">OK</button>
            `;
            document.body.appendChild(banner);

            // Banner nach 10 Sekunden ausblenden
            setTimeout(() => banner.remove(), 10000);

            return true;
        }

        return false;
    }

    // Beim Laden initialisieren
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
