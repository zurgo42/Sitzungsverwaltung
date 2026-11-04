<?php
/**
 * agenda_attachments_view.php - Schreibgeschützte Liste der Dateianhänge eines TOP
 *
 * Für die Ansichten "beendet", "Protokoll bereit" und "archiviert", in denen kein Upload mehr möglich ist,
 * die Dateien aber weiterhin abrufbar sein sollen (Upload/Löschen gibt es nur in Vorbereitung und laufender Sitzung).
 */
function render_agenda_attachments_readonly($pdo, $item_id) {
    try {
        $st = $pdo->prepare("SELECT attachment_id, original_filename, filepath, filesize FROM svagenda_attachments WHERE item_id = ? ORDER BY uploaded_at DESC");
        $st->execute([$item_id]);
        $files = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return;   // Tabelle fehlt o. ä.: nichts anzeigen
    }
    if (!$files) return;
    ?>
    <details style="margin: 10px 0;" open>
        <summary style="cursor: pointer; padding: 8px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
            📎 Dateianhänge (<?php echo count($files); ?>)
        </summary>
        <div style="margin-top: 8px;">
            <?php foreach ($files as $f): ?>
                <div style="padding: 6px; background: white; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 4px;">
                    📄 <a href="<?php echo htmlspecialchars($f['filepath']); ?>" download="<?php echo htmlspecialchars($f['original_filename']); ?>">
                        <strong><?php echo htmlspecialchars($f['original_filename']); ?></strong></a>
                    <small style="color: #666;">(<?php echo number_format($f['filesize'] / 1024, 1, ',', '.'); ?> KB)</small>
                </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
}
