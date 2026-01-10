<?php
/**
 * module_comments.php - Kommentar-Funktionen
 * 
 * Dieses Modul enthält alle Funktionen für editierbare Kommentare
 * Einbindung: require_once 'module_comments.php';
 */

/**
 * Formatiert Dateigröße in menschenlesbare Form
 */
function format_filesize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

/**
 * Lädt alle Kommentare für einen TOP (sortiert nach Datum)
 * Verwendet den Adapter für Member-Namen
 */
function get_item_comments($pdo, $item_id) {
    $stmt = $pdo->prepare("
        SELECT ac.*
        FROM svagenda_comments ac
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
        FROM svagenda_comments 
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
    $text = linkify_text($comment['comment_text']);

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

    // Dateianhang anzeigen (falls vorhanden)
    $attachment_html = '';
    if (!empty($comment['attachment_filename'])) {
        $original_name = !empty($comment['attachment_original_name'])
            ? htmlspecialchars($comment['attachment_original_name'])
            : htmlspecialchars($comment['attachment_filename']);
        $file_size = !empty($comment['attachment_size'])
            ? ' (' . format_filesize($comment['attachment_size']) . ')'
            : '';
        $attachment_html = '<br><span style="margin-left: 20px;">📎 <a href="uploads/' . htmlspecialchars($comment['attachment_filename']) . '" target="_blank" style="color: #2196f3; text-decoration: underline;">' . $original_name . '</a>' . $file_size . '</span>';
    }

    ?>
    <div style="padding: 4px 0; border-bottom: 1px solid #eee; font-size: 13px; line-height: 1.5;">
        <strong style="color: #333;"><?php echo $name; ?></strong> <span style="color: #999; font-size: 11px;"><?php echo $timestamp; ?>:</span><?php echo $rating_text; ?> <span style="color: #555;"><?php echo $text; ?></span><?php echo $attachment_html; ?>
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

        <div style="margin-top: 8px;">
            <label style="font-size: 12px; font-weight: 600; color: #666; display: block; margin-bottom: 4px;">
                📎 Dateianhang (optional):
            </label>
            <input type="file"
                   name="comment_attachment[<?php echo $item_id; ?>]"
                   style="font-size: 12px; padding: 4px; border: 1px solid #ddd; border-radius: 4px; background: white; width: 100%;">
            <small style="color: #999; font-size: 10px; display: block; margin-top: 2px;">
                Max. 10 MB, erlaubte Formate: PDF, DOC(X), XLS(X), PPT(X), TXT, JPG, PNG, ZIP
            </small>
        </div>

        <small style="color: #999; font-size: 11px; display: block; margin-top: 8px;">
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
        FROM svagenda_live_comments alc
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
            FROM svagenda_post_comments apc
            WHERE apc.item_id = ? AND apc.member_id = ?
            ORDER BY apc.created_at DESC
        ");
        $stmt->execute([$item_id, $member_id]);
    } else {
        // Alle Post-Kommentare
        $stmt = $pdo->prepare("
            SELECT apc.*
            FROM svagenda_post_comments apc
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
