<?php
/**
 * module_comments.php - Kommentar-Funktionen
 * 
 * Dieses Modul enthält alle Funktionen für editierbare Kommentare
 * Einbindung: require_once 'module_comments.php';
 */

/**
 * Lädt alle Kommentare für einen TOP (sortiert nach Datum)
 * Verwendet den Adapter für Member-Namen
 */
function get_item_comments($pdo, $item_id) {
    $stmt = $pdo->prepare("
        SELECT ac.*
        FROM agenda_comments ac
        WHERE ac.item_id = ?
        ORDER BY ac.created_at ASC
    ");
    $stmt->execute([$item_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Member-Namen über Adapter holen
    foreach ($comments as &$comment) {
        $member = get_member_by_id($pdo, $comment['member_id']);
        $comment['first_name'] = $member['first_name'] ?? 'Unbekannt';
        $comment['last_name'] = $member['last_name'] ?? '';
    }
    unset($comment);

    return $comments;
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
/**
 * Konvertiert URLs in Text zu klickbaren Links
 * Muss NACH htmlspecialchars() aufgerufen werden
 */
function make_links_clickable($text) {
    // URL-Pattern für bereits escaped Text (htmlspecialchars wandelt & zu &amp;)
    $pattern = '/(https?:\/\/[^\s<>"\']+)/i';
    return preg_replace_callback($pattern, function($matches) {
        $url = $matches[1];
        // URL für href dekodieren und wieder encodieren für Sicherheit
        $display_url = strlen($url) > 50 ? substr($url, 0, 47) . '...' : $url;
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" style="color: #007bff; text-decoration: underline;">' . $display_url . '</a>';
    }, $text);
}

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
    $original_text = $text; // Volltext für Alert behalten

    // URLs klickbar machen
    $text = make_links_clickable($text);

    // Lange Kommentare kürzen (nur auf Desktop, Limit konfigurierbar)
    $max_length = defined('COMMENT_MAX_LENGTH') ? COMMENT_MAX_LENGTH : 500;
    $is_truncated = false;
    $truncated_text = $text;

    if (strlen($original_text) > $max_length) {
        $is_truncated = true;
        // Kürzen auf Limit, dann bis zum letzten Leerzeichen
        $truncated = substr($original_text, 0, $max_length);
        $last_space = strrpos($truncated, ' ');
        if ($last_space > $max_length * 0.8) {
            $truncated = substr($truncated, 0, $last_space);
        }
        $truncated_text = make_links_clickable(htmlspecialchars($truncated));
    }

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
        <strong style="color: #333;"><?php echo $name; ?></strong> <span style="color: #999; font-size: 11px;"><?php echo $timestamp; ?>:</span><?php echo $rating_text; ?>
        <?php if ($is_truncated): ?>
            <!-- Gekürzte Version nur auf Desktop -->
            <span style="color: #555;" class="comment-truncated">
                <?php echo $truncated_text; ?>...
                <a href="#" onclick="showCommentModal('<?php echo addslashes(str_replace(["\r\n", "\n", "\r"], "<br>", $text)); ?>'); return false;"
                   style="color: #007bff; font-style: italic; cursor: pointer;">[Vollbild]</a>
            </span>
            <!-- Voller Text auf Mobile -->
            <span style="color: #555;" class="comment-full"><?php echo $text; ?></span>
        <?php else: ?>
            <span style="color: #555;"><?php echo $text; ?></span>
        <?php endif; ?>
    </div>
    <?php
}

// CSS und Modal für Kommentar-Kürzung (wird einmal ausgegeben)
if (!defined('COMMENT_TRUNCATION_CSS_LOADED')) {
    define('COMMENT_TRUNCATION_CSS_LOADED', true);
    ?>
    <style>
    /* Desktop: Gekürzt anzeigen, voll verstecken */
    .comment-truncated { display: inline; }
    .comment-full { display: none; }

    /* Mobile: Voll anzeigen, gekürzt verstecken */
    @media (max-width: 768px) {
        .comment-truncated { display: none; }
        .comment-full { display: inline; }
    }

    /* Kommentar-Modal */
    .comment-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 10000;
        justify-content: center;
        align-items: center;
    }
    .comment-modal-overlay.show {
        display: flex;
    }
    .comment-modal {
        background: white;
        width: 80%;
        max-width: 900px;
        height: 70%;
        max-height: 600px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
    }
    .comment-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f5f5f5;
        border-radius: 8px 8px 0 0;
    }
    .comment-modal-header h3 {
        margin: 0;
        font-size: 16px;
        color: #333;
    }
    .comment-modal-close {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
    }
    .comment-modal-close:hover {
        background: #c0392b;
    }
    .comment-modal-content {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
        font-size: 14px;
        line-height: 1.6;
        color: #333;
    }

    /* Dark Mode Support */
    body.dark-mode .comment-modal {
        background: #2d2d2d;
    }
    body.dark-mode .comment-modal-header {
        background: #3d3d3d;
        border-bottom-color: #555;
    }
    body.dark-mode .comment-modal-header h3 {
        color: #e0e0e0;
    }
    body.dark-mode .comment-modal-content {
        color: #e0e0e0;
    }
    </style>

    <!-- Modal Container -->
    <div id="commentModal" class="comment-modal-overlay" onclick="if(event.target===this)closeCommentModal()">
        <div class="comment-modal">
            <div class="comment-modal-header">
                <h3>📝 Vollständiger Kommentar</h3>
                <button class="comment-modal-close" onclick="closeCommentModal()">✕ Schließen</button>
            </div>
            <div class="comment-modal-content" id="commentModalContent"></div>
        </div>
    </div>

    <script>
    function showCommentModal(text) {
        document.getElementById('commentModalContent').innerHTML = text;
        document.getElementById('commentModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeCommentModal() {
        document.getElementById('commentModal').classList.remove('show');
        document.body.style.overflow = '';
    }
    // ESC-Taste schließt Modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCommentModal();
    });
    </script>
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

/**
 * Lädt Live-Kommentare für einen TOP (während der Sitzung)
 * Verwendet den Adapter für Member-Namen
 */
function get_live_comments($pdo, $item_id) {
    $stmt = $pdo->prepare("
        SELECT alc.*
        FROM agenda_live_comments alc
        WHERE alc.item_id = ?
        ORDER BY alc.created_at ASC
    ");
    $stmt->execute([$item_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Member-Namen über Adapter holen
    foreach ($comments as &$comment) {
        $member = get_member_by_id($pdo, $comment['member_id']);
        $comment['first_name'] = $member['first_name'] ?? 'Unbekannt';
        $comment['last_name'] = $member['last_name'] ?? '';
    }
    unset($comment);

    return $comments;
}

/**
 * Lädt Post-Kommentare für einen TOP (nach der Sitzung)
 * Verwendet den Adapter für Member-Namen
 */
function get_post_comments($pdo, $item_id, $member_id = null) {
    if ($member_id !== null) {
        // Nur eigene Post-Kommentare
        $stmt = $pdo->prepare("
            SELECT apc.*
            FROM agenda_post_comments apc
            WHERE apc.item_id = ? AND apc.member_id = ?
            ORDER BY apc.created_at DESC
        ");
        $stmt->execute([$item_id, $member_id]);
    } else {
        // Alle Post-Kommentare
        $stmt = $pdo->prepare("
            SELECT apc.*
            FROM agenda_post_comments apc
            WHERE apc.item_id = ?
            ORDER BY apc.created_at ASC
        ");
        $stmt->execute([$item_id]);
    }

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Member-Namen über Adapter holen
    foreach ($comments as &$comment) {
        $member = get_member_by_id($pdo, $comment['member_id']);
        $comment['first_name'] = $member['first_name'] ?? 'Unbekannt';
        $comment['last_name'] = $member['last_name'] ?? '';
    }
    unset($comment);

    return $comments;
}
?>
