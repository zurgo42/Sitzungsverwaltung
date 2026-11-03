<?php
/**
 * module_priority_rating.php - Priorität & Dauer Bewertung
 * 
 * Ermöglicht allen Teilnehmern Priorität und geschätzte Dauer einzugeben
 * Mittelwerte werden in agenda_items gespeichert und für Sortierung verwendet
 */

/**
 * Zeigt Eingabefelder für Priorität und Dauer (nur in preparation)
 * 
 * @param array $item - Der TOP
 * @param array $user_comment - Vorhandener Kommentar des Users mit Ratings
 * @param string $meeting_status - Status der Sitzung
 */
function render_priority_rating_form($item, $user_comment, $meeting_status) {
    // Nur in preparation und nicht für TOP 0, 99, 999
    if ($meeting_status !== 'preparation' || 
        in_array($item['top_number'], [0, 99, 999])) {
        return;
    }
    
    $priority_value = $user_comment['priority_rating'] ?? '';
    $duration_value = $user_comment['duration_estimate'] ?? '';
    ?>
    
    <div style="margin-top: 15px; padding: 12px; background: #f0f7ff; border: 1px solid #2196f3; border-radius: 5px;">
        <strong style="color: #1976d2;">📊 Deine Einschätzung:</strong>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                    Priorität (0-9):
                </label>
                <input type="number" 
                       name="priority[<?php echo $item['item_id']; ?>]" 
                       min="0" max="9" step="0.1" 
                       value="<?php echo htmlspecialchars($priority_value); ?>"
                       placeholder="Optional"
                       style="width: 100%; padding: 6px; border: 1px solid #2196f3; border-radius: 4px;">
                <small style="display: block; color: #666; font-size: 11px; margin-top: 2px;">
                    1 = niedrig, 10 = sehr wichtig
                </small>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                    Geschätzte Dauer (Min.):
                </label>
                <input type="number" 
                       name="duration[<?php echo $item['item_id']; ?>]" 
                       min="1" 
                       value="<?php echo htmlspecialchars($duration_value); ?>"
                       placeholder="Optional"
                       style="width: 100%; padding: 6px; border: 1px solid #2196f3; border-radius: 4px;">
                <small style="display: block; color: #666; font-size: 11px; margin-top: 2px;">
                    Deine Schätzung in Minuten
                </small>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Zeigt Durchschnittswerte für Priorität und Dauer an
 * 
 * @param array $item - Der TOP mit avg_priority und avg_duration
 */
function render_priority_summary($item) {
    if ($item['top_number'] == 0 || $item['top_number'] == 99 || $item['top_number'] == 999) {
        return;
    }
    
    $avg_priority = $item['avg_priority'] ?? 0;
    $avg_duration = $item['avg_duration'] ?? 0;
    
    if ($avg_priority > 0 || $avg_duration > 0) {
        ?>
        <div style="font-size: 12px; color: #666; margin-top: 8px;">
            <?php if ($avg_priority > 0): ?>
                <span style="margin-right: 15px;">
                    <strong>Ø Priorität:</strong> <?php echo number_format($avg_priority, 1); ?>/10
                </span>
            <?php endif; ?>
            <?php if ($avg_duration > 0): ?>
                <span>
                    <strong>Ø Dauer:</strong> <?php echo $avg_duration; ?> Min.
                </span>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>
