<?php
/**
 * tab_agenda_display_ended.php - Anzeige nach Sitzungsende
 * Protokollant kann editieren, Teilnehmer können nachträgliche Kommentare hinzufügen
 */

if (empty($agenda_items)) {
    echo '<div class="info-box">Keine Tagesordnungspunkte vorhanden.</div>';
    return;
}
?>

<h3 style="margin: 20px 0 15px 0;">📋 Sitzungsverlauf - Protokoll in Bearbeitung</h3>

<!-- TEILNEHMERLISTE -->
<?php if ($is_secretary): ?>
    <details open style="margin: 20px 0; padding: 15px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 8px;">
        <summary style="cursor: pointer; font-weight: 600; color: #1976d2; font-size: 16px; margin-bottom: 10px;">
            👥 Teilnehmerverwaltung (klicken zum Auf-/Zuklappen)
        </summary>

        <form method="POST" action="">
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

    <!-- Vorsitz und Protokollführung -->
    <?php
    global $members_by_id;
    $chairman_name = 'Nicht zugewiesen';
    $secretary_name = 'Nicht zugewiesen';

    if (!empty($meeting['chairman_member_id']) && isset($members_by_id[$meeting['chairman_member_id']])) {
        $chairman = $members_by_id[$meeting['chairman_member_id']];
        $chairman_name = htmlspecialchars($chairman['first_name'] . ' ' . $chairman['last_name']);
    }

    if (!empty($meeting['secretary_member_id']) && isset($members_by_id[$meeting['secretary_member_id']])) {
        $secretary = $members_by_id[$meeting['secretary_member_id']];
        $secretary_name = htmlspecialchars($secretary['first_name'] . ' ' . $secretary['last_name']);
    }
    ?>
    <div style="margin-top: 10px; padding: 10px; background: #e8f4f8; border: 1px solid #90caf9; border-radius: 4px; font-size: 13px;">
        <strong>Vorsitz:</strong> <?php echo $chairman_name; ?> &nbsp;|&nbsp;
        <strong>Protokollführung:</strong> <?php echo $secretary_name; ?>
    </div>
<?php else: ?>
    <?php render_readonly_participant_list($pdo, $current_meeting_id, $participants); ?>

    <!-- Vorsitz und Protokollführung -->
    <?php
    global $members_by_id;
    $chairman_name = 'Nicht zugewiesen';
    $secretary_name = 'Nicht zugewiesen';

    if (!empty($meeting['chairman_member_id']) && isset($members_by_id[$meeting['chairman_member_id']])) {
        $chairman = $members_by_id[$meeting['chairman_member_id']];
        $chairman_name = htmlspecialchars($chairman['first_name'] . ' ' . $chairman['last_name']);
    }

    if (!empty($meeting['secretary_member_id']) && isset($members_by_id[$meeting['secretary_member_id']])) {
        $secretary = $members_by_id[$meeting['secretary_member_id']];
        $secretary_name = htmlspecialchars($secretary['first_name'] . ' ' . $secretary['last_name']);
    }
    ?>
    <div style="margin-top: 10px; padding: 10px; background: #e8f4f8; border: 1px solid #90caf9; border-radius: 4px; font-size: 13px;">
        <strong>Vorsitz:</strong> <?php echo $chairman_name; ?> &nbsp;|&nbsp;
        <strong>Protokollführung:</strong> <?php echo $secretary_name; ?>
    </div>
<?php endif; ?>

<!-- Info-Box -->
<div style="margin: 15px 0; padding: 12px; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 4px;">
    <strong>ℹ️ Status:</strong> Die Sitzung ist beendet.
    <?php if ($is_secretary): ?>
        Du kannst dein Protokoll noch bearbeiten.
    <?php else: ?>
        Du kannst nun noch Anmerkungen zum Protokoll machen.
    <?php endif; ?>
</div>

<!-- TOPS ANZEIGEN -->
<form method="POST" action="">
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
                TOP #<?php echo $item['top_number']; ?>: <?php echo htmlspecialchars($item['title']); ?>
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
        // SSO-kompatibel: Member-Namen über Adapter holen
        $live_comments = get_live_comments($pdo, $item['item_id']);
        
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
            <!-- Nachträgliche Kommentare für Protokollführer anzeigen (vor dem Protokollfeld) -->
            <?php
            // SSO-kompatibel: Member-Namen über Adapter holen
            $post_comments = get_post_comments($pdo, $item['item_id']);

            if (!empty($post_comments)):
            ?>
                <div style="margin-top: 15px; padding: 12px; background: #fff3e0; border: 2px solid #ff9800; border-radius: 6px;">
                    <h4 style="color: #e65100; margin-bottom: 8px;">💭 Nachträgliche Anmerkungen der Teilnehmer</h4>
                    <div style="background: white; padding: 10px; border-radius: 4px;">
                        <?php foreach ($post_comments as $pc): ?>
                            <div style="padding: 6px 0; border-bottom: 1px solid #eee; font-size: 13px;">
                                <strong style="color: #333;"><?php echo htmlspecialchars($pc['first_name'] . ' ' . $pc['last_name']); ?></strong>
                                <span style="color: #999; font-size: 11px;"><?php echo date('d.m.Y H:i', strtotime($pc['created_at'])); ?>:</span>
                                <span style="color: #555;"><?php echo htmlspecialchars($pc['comment_text']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

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
            </div>

            <!-- Nachträgliche Anmerkungen auch für Protokollant -->
            <div style="margin-top: 15px; padding: 12px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 6px;">
                <h4 style="color: #2e7d32; margin-bottom: 8px;">💭 Deine nachträgliche Anmerkung zum Protokoll</h4>

                <?php
                // Eigene nachträgliche Anmerkung des Protokollanten laden
                $stmt = $pdo->prepare("
                    SELECT comment_text, comment_id
                    FROM svagenda_post_comments
                    WHERE item_id = ? AND member_id = ?
                ");
                $stmt->execute([$item['item_id'], $current_user['member_id']]);
                $my_post_comment = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>

                <div class="form-group">
                    <label style="font-size: 13px; font-weight: 600; color: #2e7d32;">
                        Deine Anmerkung zu diesem TOP:
                    </label>
                    <textarea name="post_comment[<?php echo $item['item_id']; ?>]"
                              rows="3"
                              placeholder="Ihre nachträgliche Anmerkung..."
                              style="width: 100%; padding: 6px; border: 1px solid #4caf50; border-radius: 4px; font-size: 13px;"><?php echo htmlspecialchars($my_post_comment['comment_text'] ?? ''); ?></textarea>
                </div>

                <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.6); border-radius: 4px; font-size: 12px; color: #666; font-style: italic;">
                    ℹ️ Kommentare in diesem Feld bleiben bis zur Protokollgenehmigung sichtbar und werden dann verworfen
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
        
        <!-- NACHTRÄGLICHE KOMMENTARE (nur für Teilnehmer, nicht für Protokollant) -->
        <?php if (!$is_secretary): ?>
            <div style="margin-top: 15px; padding: 12px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 6px;">
                <h4 style="color: #2e7d32; margin-bottom: 8px;">💭 Nachträgliche Anmerkungen zum Protokollentwurf</h4>
                
                <!-- Bestehende nachträgliche Kommentare anzeigen -->
                <?php
                // SSO-kompatibel: Member-Namen über Adapter holen
                $post_comments = get_post_comments($pdo, $item['item_id']);
                
                if (!empty($post_comments)):
                ?>
                    <div style="background: white; border: 1px solid #4caf50; border-radius: 4px; padding: 8px; margin-bottom: 10px;">
                        <?php foreach ($post_comments as $pc): ?>
                            <?php render_comment_line($pc, 'full'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Eigenen nachträglichen Kommentar bearbeiten -->
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
                    <label style="font-size: 13px; font-weight: 600; color: #2e7d32;">
                        Deine Anmerkung zu diesem TOP:
                    </label>
                    <textarea name="post_comment[<?php echo $item['item_id']; ?>]"
                              rows="3"
                              placeholder="Ihre nachträgliche Anmerkung..."
                              style="width: 100%; padding: 6px; border: 1px solid #4caf50; border-radius: 4px; font-size: 13px;"><?php echo htmlspecialchars($my_post_comment['comment_text'] ?? ''); ?></textarea>
                </div>

                <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.6); border-radius: 4px; font-size: 12px; color: #666; font-style: italic;">
                    ℹ️ Kommentare in diesem Feld bleiben bis zur Protokollgenehmigung sichtbar und werden dann verworfen
                </div>
            </div>
        <?php endif; ?>
        
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
        <form method="POST" action="" onsubmit="return confirm('Protokoll wirklich freigeben? Du kannst danach noch Änderungen vornehmen.');">
            <input type="hidden" name="release_protocol" value="1">
            <button type="submit" style="background: #4caf50; color: white; padding: 10px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 4px; cursor: pointer;">
                ✅ Protokoll jetzt freigeben
            </button>
        </form>
    </div>
<?php endif; ?>
