<?php
/**
 * tab_agenda_display_readonly.php - Nur-Lese-Ansicht nach Sitzungsende
 * Wird in Status "ended", "protocol_ready", "archived" eingebunden
 */

// Module laden
require_once 'module_comments.php';

if (empty($agenda_items)) {
    echo '<div class="info-box">Keine Tagesordnungspunkte vorhanden.</div>';
    return;
}
?>

<h3 style="margin: 20px 0 15px 0;">📋 Protokoll - Tagesordnungspunkte</h3>

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
    // Vertrauliche TOPs nur für berechtigte User
    if ($item['is_confidential'] && !$can_see_confidential) {
        continue;
    }

    // TOP 999 nicht anzeigen (nur Steuerungselement)
    if ($item['top_number'] == 999) {
        continue;
    }
?>
    <div class="agenda-item" id="top-<?php echo $item['item_id']; ?>" 
         style="background: white; padding: 12px; margin-bottom: 10px; border-left: 4px solid #999; border-radius: 5px; border: 1px solid #e0e0e0;">
        
        <!-- TOP-Header mit Kategorie -->
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
        
        <!-- Diskussionsbeiträge (zugeklappt, nur wenn vorhanden) -->
        <?php
        $comments = get_item_comments($pdo, $item['item_id']);
        if (!empty($comments)):
        ?>
            <details style="margin-top: 10px;">
                <summary style="cursor: pointer; color: #667eea; font-weight: 600; padding: 6px; background: #f9f9f9; border-radius: 4px;">
                    💬 Diskussionsbeiträge anzeigen
                </summary>
                <div style="margin-top: 8px; padding: 8px; background: white; border: 1px solid #ddd; border-radius: 4px;">
                    <?php
                    foreach ($comments as $comment):
                        render_comment_line($comment, 'full');
                    endforeach;
                    ?>
                </div>
            </details>
        <?php endif; ?>
        
        <!-- Protokoll -->
        <?php if (!empty($item['protocol_notes'])): ?>
            <div style="margin-top: 12px; padding: 12px; background: #f0f7ff; border-left: 4px solid #2196f3; border-radius: 4px;">
                <strong style="color: #1976d2; display: block; margin-bottom: 8px;">📝 Protokoll:</strong>
                <div style="color: #333; line-height: 1.6;">
                    <?php echo nl2br(linkify_text($item['protocol_notes'])); ?>
                </div>
                
                <?php 
                // Abstimmungsergebnis anzeigen (aus module_proposals.php)
                render_voting_result($item); 
                ?>
            </div>
        <?php endif; ?>
        
    </div>
<?php endforeach; ?>

<?php if ($meeting['status'] === 'protocol_ready' && $is_chairman): ?>
    <!-- Protokoll genehmigen (nur Vorsitzender) -->
    <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">✅ Protokoll genehmigen</h4>
        <p style="color: #666; margin-bottom: 10px;">
            Als Vorsitzender kannst du das Protokoll jetzt genehmigen.
        </p>
        <form method="POST" action="" onsubmit="return confirm('Protokoll jetzt genehmigen?');">
            <input type="hidden" name="approve_protocol" value="1">
            <button type="submit" style="background: #4caf50; color: white; padding: 10px 20px; font-size: 16px; font-weight: 600;">
                ✅ Protokoll genehmigen
            </button>
        </form>
    </div>
<?php endif; ?>

<?php if ($meeting['status'] === 'archived'): ?>
    <div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border: 2px solid #999; border-radius: 8px; text-align: center;">
        <strong style="color: #666;">📁 Dieses Meeting ist archiviert</strong>
    </div>
<?php endif; ?>
