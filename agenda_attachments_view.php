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

/**
 * Für den Protokollanten: Anhänge eines TOP mit Knopf "Link ins Protokoll". Ob ein Anhang für das Protokoll relevant ist,
 * entscheidet er selbst - der Link wird nur auf Klick (mit absoluter Adresse) an die Protokollnotizen angehängt.
 * Muss innerhalb des Formulars mit dem Textfeld protocol_text bzw. protocol_text[<item_id>] stehen.
 */
function render_agenda_attachments_for_protocol($pdo, $item_id) {
    try {
        $st = $pdo->prepare("SELECT original_filename, filepath FROM svagenda_attachments WHERE item_id = ? ORDER BY uploaded_at");
        $st->execute([$item_id]);
        $files = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return;
    }
    if (!$files) return;

    // Absolute Adresse der Datei (im Protokoll muss der Link auch außerhalb der Anwendung funktionieren)
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $origin = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base   = function_exists('sv_base_path') ? sv_base_path() : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    static $script_done = false;
    ?>
    <div style="margin: 6px 0 10px; padding: 8px; background: #fff; border: 1px dashed #2196f3; border-radius: 4px; font-size: 12px;">
        <strong>📎 Dateien zu diesem TOP – Link ins Protokoll übernehmen?</strong>
        <?php foreach ($files as $f):
            $url = $origin . $base . '/' . ltrim($f['filepath'], '/');
        ?>
            <div style="margin-top: 4px;">
                <?php echo htmlspecialchars($f['original_filename']); ?>
                <button type="button" onclick="svAnhangInsProtokoll(this)"
                        data-text="<?php echo htmlspecialchars($f['original_filename'] . ': ' . $url); ?>"
                        style="margin-left: 6px; padding: 2px 8px; font-size: 11px; cursor: pointer; background: #2196f3; color: #fff; border: none; border-radius: 3px;">
                    ➕ Link ins Protokoll
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (!$script_done): $script_done = true; ?>
    <script>
    function svAnhangInsProtokoll(btn) {
        var form = btn.closest('.agenda-item') || btn.closest('form');
        if (!form) return;
        var ta = form.querySelector('textarea[name^="protocol_text"]');
        if (!ta) return;
        var zeile = btn.getAttribute('data-text');
        if (ta.value.indexOf(zeile) !== -1) { btn.textContent = '✔ bereits enthalten'; return; }
        ta.value = (ta.value.replace(/\s+$/, '') + (ta.value.trim() ? '\n' : '') + zeile);
        ta.dispatchEvent(new Event('input', {bubbles: true}));
        btn.textContent = '✔ eingefügt (noch speichern)';
    }
    </script>
    <?php endif;
}
