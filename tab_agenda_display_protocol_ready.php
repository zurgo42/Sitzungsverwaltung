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

<!-- Teilnehmerliste -->
<?php render_readonly_participant_list($pdo, $current_meeting_id, $participants); ?>

<!-- Info-Box -->
<div style="margin: 15px 0; padding: 12px; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 4px;">
    <strong>ℹ️ Status:</strong> Das Protokoll wurde zur Genehmigung freigegeben.
    <?php if ($is_chairman): ?>
        Sie können das Protokoll jetzt genehmigen.
    <?php elseif ($is_secretary): ?>
        Sie können Ihr Protokoll noch bearbeiten, bis es genehmigt wird.
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
        
        <!-- Diskussionsbeiträge (zugeklappt) -->
        <details style="margin-top: 10px;">
            <summary style="cursor: pointer; color: #667eea; font-weight: 600; padding: 6px; background: #f9f9f9; border-radius: 4px; font-size: 13px;">
                💬 Alle Diskussionsbeiträge anzeigen
            </summary>
            <div style="margin-top: 8px; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 4px;">
                <?php 
                $prep_comments = get_item_comments($pdo, $item['item_id']);
                if (!empty($prep_comments)):
                ?>
                    <h5 style="font-size: 12px; color: #667eea; margin: 8px 0 4px 0;">Aus Vorbereitung:</h5>
                    <?php foreach ($prep_comments as $comment): ?>
                        <div style="padding: 4px; border-bottom: 1px solid #eee; font-size: 12px;">
                            <strong style="color: #667eea;">
                                <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>:
                            </strong>
                            <span style="color: #555;">
                                <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php
                // Live-Kommentare
                $stmt = $pdo->prepare("
                    SELECT alc.*, m.first_name, m.last_name
                    FROM agenda_live_comments alc
                    JOIN members m ON alc.member_id = m.member_id
                    WHERE alc.item_id = ?
                    ORDER BY alc.created_at ASC
                ");
                $stmt->execute([$item['item_id']]);
                $live_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($live_comments)):
                ?>
                    <h5 style="font-size: 12px; color: #f44336; margin: 12px 0 4px 0;">Während Sitzung:</h5>
                    <?php foreach ($live_comments as $lc): ?>
                        <div style="padding: 4px; border-bottom: 1px solid #ffcdd2; font-size: 12px;">
                            <strong style="color: #c62828;">
                                <?php echo htmlspecialchars($lc['first_name'] . ' ' . $lc['last_name']); ?>:
                            </strong>
                            <span style="color: #555;">
                                <?php echo nl2br(htmlspecialchars($lc['comment_text'])); ?>
                            </span>
                            <small style="color: #999; margin-left: 8px;">
                                <?php echo date('H:i', strtotime($lc['created_at'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php
                // Nachträgliche Kommentare
                $stmt = $pdo->prepare("
                    SELECT apc.*, m.first_name, m.last_name
                    FROM agenda_post_comments apc
                    JOIN members m ON apc.member_id = m.member_id
                    WHERE apc.item_id = ?
                    ORDER BY apc.created_at ASC
                ");
                $stmt->execute([$item['item_id']]);
                $post_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($post_comments)):
                ?>
                    <h5 style="font-size: 12px; color: #4caf50; margin: 12px 0 4px 0;">Nachträgliche Anmerkungen:</h5>
                    <?php foreach ($post_comments as $pc): ?>
                        <div style="padding: 4px; border-bottom: 1px solid #c8e6c9; font-size: 12px;">
                            <strong style="color: #2e7d32;">
                                <?php echo htmlspecialchars($pc['first_name'] . ' ' . $pc['last_name']); ?>:
                            </strong>
                            <span style="color: #555;">
                                <?php echo nl2br(htmlspecialchars($pc['comment_text'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (empty($prep_comments) && empty($live_comments) && empty($post_comments)): ?>
                    <div style="color: #999; font-size: 12px;">Keine Kommentare</div>
                <?php endif; ?>
            </div>
        </details>
        
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
                    <?php echo nl2br(htmlspecialchars($item['protocol_notes'])); ?>
                </div>
                <?php render_voting_result($item); ?>
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
    
    <!-- GENEHMIGEN -->
    <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">✅ Protokoll genehmigen</h4>
        <p style="color: #666; margin-bottom: 10px;">
            Als Sitzungsleiter können Sie das Protokoll jetzt genehmigen und archivieren.
        </p>
        <form method="POST" action="" onsubmit="return confirm('Protokoll wirklich genehmigen? Das Meeting wird dann archiviert.');">
            <input type="hidden" name="approve_protocol" value="1">
            <button type="submit" style="background: #4caf50; color: white; padding: 10px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 4px; cursor: pointer;">
                ✅ Protokoll jetzt genehmigen
            </button>
        </form>
    </div>
<?php endif; ?>
