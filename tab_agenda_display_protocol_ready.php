<?php
/**
 * tab_agenda_display_protocol_ready.php - Protokoll wartet auf Genehmigung
 * Nur Sitzungsleiter kann genehmigen, Protokollant kann noch editieren
 */

if (empty($agenda_items)) {
    echo '<div class="info-box">Keine Tagesordnungspunkte vorhanden.</div>';
    return;
}
?>

<h3 style="margin: 20px 0 15px 0;">📋 Sitzungsverlauf - Protokoll wartet auf Genehmigung</h3>

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
<?php else: ?>
    <?php render_readonly_participant_list($pdo, $current_meeting_id, $participants); ?>
<?php endif; ?>

<!-- Info-Box -->
<div style="margin: 15px 0; padding: 12px; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 4px;">
    <strong>ℹ️ Status:</strong> Das Protokoll wurde zur Genehmigung freigegeben.
    <?php if ($is_chairman): ?>
        Du kannst das Protokoll jetzt genehmigen.
    <?php elseif ($is_secretary): ?>
        Du kannst dein Protokoll noch bearbeiten, bis es genehmigt wird.
    <?php else: ?>
        Das Protokoll wartet auf Genehmigung durch den Sitzungsleiter.
    <?php endif; ?>
</div>

<!-- TOPS ANZEIGEN -->
<?php if ($is_secretary): ?>
<form method="POST" action="">
    <input type="hidden" name="save_protocol_ready_changes" value="1">
<?php endif; ?>

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
        
        <!-- Diskussionsbeiträge (zugeklappt, nur wenn vorhanden) -->
        <?php
        // Alle Kommentare laden
        $prep_comments = get_item_comments($pdo, $item['item_id']);

        $stmt = $pdo->prepare("
            SELECT alc.*, m.first_name, m.last_name
            FROM svagenda_live_comments alc
            JOIN svmembers m ON alc.member_id = m.member_id
            WHERE alc.item_id = ?
            ORDER BY alc.created_at ASC
        ");
        $stmt->execute([$item['item_id']]);
        $live_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT apc.*, m.first_name, m.last_name
            FROM svagenda_post_comments apc
            JOIN svmembers m ON apc.member_id = m.member_id
            WHERE apc.item_id = ?
            ORDER BY apc.created_at ASC
        ");
        $stmt->execute([$item['item_id']]);
        $post_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Nur anzeigen wenn mindestens eine Kommentarart vorhanden
        if (!empty($prep_comments) || !empty($live_comments) || !empty($post_comments)):
        ?>
            <details style="margin-top: 10px;">
                <summary style="cursor: pointer; color: #667eea; font-weight: 600; padding: 6px; background: #f9f9f9; border-radius: 4px; font-size: 13px;">
                    💬 Alle Diskussionsbeiträge anzeigen
                </summary>
                <div style="margin-top: 8px; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 4px;">
                    <?php if (!empty($prep_comments)): ?>
                        <h5 style="font-size: 12px; color: #667eea; margin: 8px 0 4px 0;">Aus Vorbereitung:</h5>
                        <?php foreach ($prep_comments as $comment): ?>
                            <?php render_comment_line($comment, 'full'); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($live_comments)): ?>
                        <h5 style="font-size: 12px; color: #f44336; margin: 12px 0 4px 0;">Während Sitzung:</h5>
                        <?php foreach ($live_comments as $lc): ?>
                            <?php render_comment_line($lc, 'time'); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($post_comments)): ?>
                        <h5 style="font-size: 12px; color: #4caf50; margin: 12px 0 4px 0;">Nachträgliche Anmerkungen:</h5>
                        <?php foreach ($post_comments as $pc): ?>
                            <?php
                            // Sitzungsleiter-Kommentare in rot
                            if ($pc['member_id'] == $meeting['chairman_member_id']) {
                                echo '<div style="padding: 4px 0; border-bottom: 1px solid #eee; font-size: 13px; line-height: 1.5;">';
                                echo '<strong style="color: #c62828;">' . htmlspecialchars($pc['first_name'] . ' ' . $pc['last_name']) . ' (Sitzungsleiter)</strong> ';
                                echo '<span style="color: #999; font-size: 11px;">' . date('d.m.Y H:i', strtotime($pc['created_at'])) . ':</span> ';
                                echo '<span style="color: #c62828;">' . htmlspecialchars($pc['comment_text']) . '</span>';
                                echo '</div>';
                            } else {
                                render_comment_line($pc, 'full');
                            }
                            ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>
        <?php endif; ?>
        
        <!-- PROTOKOLL -->
        <?php if ($is_secretary): ?>
            <!-- Protokollant kann weiter editieren -->
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
        <?php elseif (!empty($item['protocol_notes'])): ?>
            <!-- Alle anderen sehen Protokoll read-only -->
            <div style="margin-top: 15px; padding: 10px; background: #f0f7ff; border-left: 4px solid #2196f3; border-radius: 4px;">
                <strong style="color: #1976d2;">📝 Protokoll:</strong><br>
                <div style="margin-top: 6px; color: #333; font-size: 14px; line-height: 1.6;">
                    <?php echo nl2br(linkify_text($item['protocol_notes'])); ?>
                </div>
                <?php render_voting_result($item); ?>
            </div>
        <?php endif; ?>

        <!-- NACHTRÄGLICHE KOMMENTARE FÜR PROTOKOLLFÜHRER -->
        <?php if ($is_secretary): ?>
            <?php
            // Nachträgliche Kommentare laden
            $stmt = $pdo->prepare("
                SELECT apc.*, m.first_name, m.last_name
                FROM svagenda_post_comments apc
                JOIN svmembers m ON apc.member_id = m.member_id
                WHERE apc.item_id = ?
                ORDER BY apc.created_at ASC
            ");
            $stmt->execute([$item['item_id']]);
            $all_post_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($all_post_comments)):
            ?>
                <div style="margin-top: 15px; padding: 12px; background: #fff3e0; border: 2px solid #ff9800; border-radius: 6px;">
                    <h4 style="color: #e65100; margin-bottom: 8px;">💭 Nachträgliche Anmerkungen der Teilnehmer</h4>
                    <div style="background: white; padding: 10px; border-radius: 4px;">
                        <?php foreach ($all_post_comments as $pc): ?>
                            <div style="padding: 6px 0; border-bottom: 1px solid #eee; font-size: 13px;">
                                <strong style="color: #333;"><?php echo htmlspecialchars($pc['first_name'] . ' ' . $pc['last_name']); ?></strong>
                                <span style="color: #999; font-size: 11px;"><?php echo date('d.m.Y H:i', strtotime($pc['created_at'])); ?>:</span>
                                <span style="color: #555;"><?php echo htmlspecialchars($pc['comment_text']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Eigene nachträgliche Anmerkung des Protokollanten -->
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
        <?php endif; ?>

        <!-- KOMMENTARFELD FÜR SITZUNGSLEITER -->
        <?php if ($is_chairman): ?>
            <div style="margin-top: 15px; padding: 12px; background: #ffebee; border: 2px solid #f44336; border-radius: 6px;">
                <h4 style="color: #c62828; margin-bottom: 8px;">💭 Deine Anmerkungen als Sitzungsleiter</h4>

                <?php
                // Bestehende Kommentare des Sitzungsleiters laden
                $stmt = $pdo->prepare("
                    SELECT apc.*, m.first_name, m.last_name
                    FROM svagenda_post_comments apc
                    JOIN svmembers m ON apc.member_id = m.member_id
                    WHERE apc.item_id = ? AND apc.member_id = ?
                    ORDER BY apc.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$item['item_id'], $current_user['member_id']]);
                $my_chairman_comment = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>

                <form method="POST" action="" style="margin-top: 8px;">
                    <input type="hidden" name="save_chairman_comment" value="1">
                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                    <textarea name="comment_text" rows="3"
                              placeholder="Ihre Anmerkung zum Protokoll..."
                              style="width: 100%; padding: 6px; border: 1px solid #f44336; border-radius: 4px; font-size: 13px;"><?php echo htmlspecialchars($my_chairman_comment['comment_text'] ?? ''); ?></textarea>
                    <button type="submit" style="background: #f44336; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-top: 4px;">
                        💾 Speichern
                    </button>
                </form>

                <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.6); border-radius: 4px; font-size: 12px; color: #666; font-style: italic;">
                    ℹ️ Kommentare in diesem Feld bleiben bis zur Protokollgenehmigung sichtbar und werden dann verworfen
                </div>
            </div>
        <?php endif; ?>
        
    </div>
<?php endforeach; ?>

<?php if ($is_secretary): ?>
    <button type="submit" style="background: #2196f3; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 16px; margin: 20px 0;">
        💾 Protokoll-Änderungen speichern
    </button>
</form>
<?php endif; ?>

<?php if ($is_secretary): ?>
    <!-- KURZPROTOKOLL FÜR PROTOKOLLFÜHRER -->
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
<?php endif; ?>

<?php if ($is_chairman): ?>
    <!-- KURZPROTOKOLL FÜR SITZUNGSLEITER -->
    <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 2px solid #2196f3; border-radius: 8px;">
        <h3 style="color: #1976d2; margin-bottom: 15px;">📋 Kurzprotokoll zur Genehmigung</h3>
        
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

    <!-- PROTOKOLLÄNDERUNG ANFORDERN -->
    <div style="margin-top: 20px; padding: 15px; background: #fff3e0; border: 2px solid #ff9800; border-radius: 8px;">
        <h4 style="color: #e65100; margin-bottom: 10px;">📝 Protokolländerung anfordern</h4>
        <p style="color: #666; margin-bottom: 10px;">
            Falls du Änderungen am Protokoll wünschst, kannst du dem Protokollanten eine Überarbeitungsanfrage senden.
        </p>
        <form method="POST" action="" onsubmit="return confirm('Überarbeitungsanfrage wirklich senden? Der Protokollant erhält ein entsprechendes ToDo.');">
            <input type="hidden" name="request_protocol_revision" value="1">
            <button type="submit" style="background: #ff9800; color: white; padding: 10px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 4px; cursor: pointer;">
                📝 Protokolländerung anfordern
            </button>
        </form>
    </div>

    <!-- GENEHMIGEN -->
    <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">✅ Protokoll genehmigen</h4>
        <p style="color: #666; margin-bottom: 10px;">
            Als Sitzungsleiter kannst du das Protokoll jetzt genehmigen und archivieren.
        </p>
        <form method="POST" action="" onsubmit="return confirm('Protokoll wirklich genehmigen? Das Meeting wird dann archiviert.');">
            <input type="hidden" name="approve_protocol" value="1">
            <button type="submit" style="background: #4caf50; color: white; padding: 10px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 4px; cursor: pointer;">
                ✅ Protokoll jetzt genehmigen
            </button>
        </form>
    </div>
<?php endif; ?>
