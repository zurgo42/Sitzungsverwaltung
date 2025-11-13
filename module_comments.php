<?php
/**
 * module_comments.php - Kommentar-Funktionen
 * 
 * Dieses Modul enthält alle Funktionen für editierbare Kommentare
 * Einbindung: require_once 'module_comments.php';
 */

/**
 * Lädt alle Kommentare für einen TOP (sortiert nach Datum)
 */
function get_item_comments($pdo, $item_id) {
    $stmt = $pdo->prepare("
        SELECT ac.*, m.first_name, m.last_name
        FROM agenda_comments ac
        JOIN members m ON ac.member_id = m.member_id
        WHERE ac.item_id = ?
        ORDER BY ac.created_at ASC
    ");
    $stmt->execute([$item_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Lädt den eigenen Kommentar für einen TOP
 */
function get_my_comment($pdo, $item_id, $member_id) {
    $stmt = $pdo->prepare("
        SELECT comment_text, comment_id
        FROM agenda_comments 
        WHERE item_id = ? AND member_id = ?
    ");
    $stmt->execute([$item_id, $member_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Rendert die Kommentar-Liste für einen TOP
 * FORMAT: <b>Name</b> [Datum]: Text - sortiert nach Datum
 */
function render_comments_list($comments, $current_member_id, $meeting_status, $item_id) {
    // Nur Kommentare mit echtem Inhalt
    $valid_comments = array_filter($comments, function($comment) {
        return !empty(trim($comment['comment_text']));
    });
    
    if (empty($valid_comments)) {
        return; // Keine Box anzeigen
    }
    
    // Nach created_at sortieren
    usort($valid_comments, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    // Zeilen zählen für Höhe
    $total_lines = 0;
    foreach ($valid_comments as $comment) {
        $text = $comment['comment_text'];
        $lines = substr_count($text, "\n") + 1;
        $total_lines += $lines + 1;
    }
    $min_height = min($total_lines * 20 + 20, 300);
    ?>
    <div class="comments-box" style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 8px; margin: 10px 0; min-height: <?php echo $min_height; ?>px; max-height: 300px; overflow-y: auto;">
        <?php foreach ($valid_comments as $comment): ?>
            <div class="comment" style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee; font-size: 13px; line-height: 1.4;">
                <div style="color: #555;">
                    <b><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></b>
                    <span style="color: #999; font-size: 11px;">
                        [<?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>]:
                    </span>
                    <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                </div>
                
                <?php 
                // Löschen-Button nur für eigene Kommentare
                if ($comment['member_id'] == $current_member_id && 
                    in_array($meeting_status, ['preparation', 'active', 'ended'])):
                ?>
                    <form method="POST" action="" style="margin-top: 4px;" 
                          onsubmit="return confirm('Kommentar wirklich löschen?');">
                        <input type="hidden" name="delete_comment" value="1">
                        <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                        <button type="submit" style="background: #e74c3c; color: white; border: none; padding: 2px 8px; font-size: 11px; cursor: pointer; border-radius: 3px;">
                            🗑️
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Rendert das editierbare Kommentarfeld (nur in preparation Phase)
 * OHNE FORM-Tags - wird in großes Formular eingebettet
 */
function render_editable_comment_form($item_id, $my_comment, $meeting_status) {
    // Nur in preparation Phase
    if ($meeting_status !== 'preparation') {
        return;
    }
    
    // Alten Text NICHT im Formular anzeigen - nur anhängen
    ?>
    <div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 5px; border: 1px solid #e0e0e0;">
        <label style="font-size: 13px; font-weight: 600; color: #666; display: block; margin-bottom: 6px;">
            ✏️ Neuer Kommentar:
        </label>
        
        <textarea name="comment_text[<?php echo $item_id; ?>]" 
                  style="width: 100%; min-height: 60px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; font-size: 13px;" 
                  placeholder="Ihr neuer Kommentar zu diesem TOP..."></textarea>
        
        <small style="color: #999; font-size: 11px; display: block; margin-top: 4px;">
            💡 Kommentare werden mit Zeitstempel angehängt und können nicht bearbeitet werden
        </small>
    </div>
    <?php
}

/**
 * Rendert das komplette Kommentar-Modul für einen TOP
 */
function render_comment_module($pdo, $item_id, $current_member_id, $meeting_status) {
    // Alle Kommentare laden
    $comments = get_item_comments($pdo, $item_id);
    
    // Eigenen Kommentar laden
    $my_comment = get_my_comment($pdo, $item_id, $current_member_id);
    
    ?>
    <div class="comment-module" style="margin-top: 12px;">
        <h4 style="font-size: 14px; color: #666; margin-bottom: 8px;">💬 Diskussionsbeiträge</h4>
        
        <?php render_comments_list($comments, $current_member_id, $meeting_status, $item_id); ?>
        
        <?php render_editable_comment_form($item_id, $my_comment, $meeting_status); ?>
    </div>
    <?php
}
?>
