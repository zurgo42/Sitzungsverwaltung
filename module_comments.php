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
 * Rendert eine einzelne Kommentarzeile einheitlich
 * FORMAT: Vorname Name timestamp: Text (alles in einer Zeile)
 *
 * @param array $comment Kommentar mit first_name, last_name, created_at, comment_text
 * @param string $date_format Format für Timestamp ('full' = d.m.Y H:i, 'time' = H:i)
 */
function render_comment_line($comment, $date_format = 'full') {
    // Leere Kommentare oder solche mit nur '-' nicht anzeigen
    $text_trimmed = trim($comment['comment_text'] ?? '');
    if ($text_trimmed === '' || $text_trimmed === '-') {
        return;
    }

    $name = htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']);
    $timestamp = $date_format === 'time'
        ? date('H:i', strtotime($comment['created_at']))
        : date('d.m.Y H:i', strtotime($comment['created_at']));
    $text = htmlspecialchars($comment['comment_text']);

    // Bewertungen anzeigen (falls vorhanden)
    $rating_text = '';
    if (!empty($comment['priority_rating']) || !empty($comment['duration_estimate'])) {
        $rating_parts = [];
        if (!empty($comment['priority_rating'])) {
            $rating_parts[] = 'Prio: ' . htmlspecialchars($comment['priority_rating']);
        }
        if (!empty($comment['duration_estimate'])) {
            $rating_parts[] = 'Dauer: ' . htmlspecialchars($comment['duration_estimate']) . ' Min';
        }
        $rating_text = ' <span style="color: #2196f3; font-size: 11px;">[' . implode(', ', $rating_parts) . ']</span>';
    }

    ?>
    <div style="padding: 4px 0; border-bottom: 1px solid #eee; font-size: 13px; line-height: 1.5;">
        <strong style="color: #333;"><?php echo $name; ?></strong> <span style="color: #999; font-size: 11px;"><?php echo $timestamp; ?>:</span><?php echo $rating_text; ?> <span style="color: #555;"><?php echo $text; ?></span>
    </div>
    <?php
}

/**
 * Rendert die Kommentar-Liste für einen TOP
 * FORMAT: Vorname Name timestamp: Text - sortiert nach Datum
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
    ?>
    <div class="comments-box" style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 8px; margin: 10px 0; max-height: 300px; overflow-y: auto;">
        <?php foreach ($valid_comments as $comment): ?>
            <?php render_comment_line($comment, 'full'); ?>
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
