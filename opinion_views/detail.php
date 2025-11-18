<?php
/**
 * Meinungsbild Details
 */

$poll = get_opinion_poll_with_options($pdo, $poll_id);

if (!$poll) {
    echo "<p>Meinungsbild nicht gefunden.</p>";
    return;
}

$is_creator = $current_user && ($poll['creator_member_id'] == $current_user['member_id']);
$is_admin = $current_user && in_array($current_user['role'], ['assistenz', 'gf']);
$is_active = ($poll['status'] === 'active' && strtotime($poll['ends_at']) > time());

// Statistiken
$stats = get_opinion_results($pdo, $poll_id);
?>

<div style="margin-bottom: 20px;">
    <a href="?tab=opinion" style="text-decoration: none;">← Zurück zur Übersicht</a>
</div>

<div class="opinion-card">
    <div style="display: flex; justify-content: space-between; align-items: start;">
        <div style="flex: 1;">
            <h3 style="margin: 0 0 15px 0;"><?php echo htmlspecialchars($poll['title']); ?></h3>

            <div class="poll-meta">
                <span class="poll-status <?php echo $is_active ? 'status-active' : 'status-ended'; ?>">
                    <?php echo $is_active ? 'Aktiv' : 'Beendet'; ?>
                </span>

                <span style="margin-left: 10px;">
                    Zielgruppe:
                    <?php
                    if ($poll['target_type'] === 'individual') echo '🔗 Individuell (Link)';
                    elseif ($poll['target_type'] === 'list') echo '📋 Meeting-Teilnehmer';
                    elseif ($poll['target_type'] === 'public') echo '🌐 Öffentlich';
                    ?>
                </span>

                <span style="margin-left: 10px;">
                    <?php echo $poll['allow_multiple_answers'] ? '☑️ Mehrfachantworten' : '⚪ Einzelantwort'; ?>
                </span>

                <span style="margin-left: 10px;">
                    <?php echo $poll['is_anonymous'] ? '🕶️ Anonym' : '👤 Mit Namen'; ?>
                </span>
            </div>

            <div style="margin-top: 15px; font-size: 14px; color: #666;">
                <div>Erstellt von: <strong><?php echo htmlspecialchars($poll['first_name'] . ' ' . $poll['last_name']); ?></strong></div>
                <div>Erstellt am: <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?></div>
                <div>Läuft bis: <?php echo date('d.m.Y H:i', strtotime($poll['ends_at'])); ?></div>
                <div>📊 <?php echo $stats['total_responses']; ?> Antwort<?php echo $stats['total_responses'] != 1 ? 'en' : ''; ?></div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php if ($is_active): ?>
                <a href="?tab=opinion&view=participate&poll_id=<?php echo $poll_id; ?>" class="btn-primary" style="text-decoration: none;">
                    Teilnehmen / Antworten
                </a>
            <?php endif; ?>

            <a href="?tab=opinion&view=results&poll_id=<?php echo $poll_id; ?>" class="btn-secondary" style="text-decoration: none;">
                Ergebnisse anzeigen
            </a>

            <?php if ($is_creator || $is_admin): ?>
                <?php if ($is_active): ?>
                    <form method="POST" action="process_opinion.php" style="margin: 0;" onsubmit="return confirm('Umfrage jetzt beenden?')">
                        <input type="hidden" name="action" value="end_opinion">
                        <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">
                        <button type="submit" class="btn-secondary" style="width: 100%;">⏸️ Beenden</button>
                    </form>
                <?php endif; ?>

                <form method="POST" action="process_opinion.php" style="margin: 0;" onsubmit="return confirm('Umfrage wirklich löschen?')">
                    <input type="hidden" name="action" value="delete_opinion">
                    <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">
                    <button type="submit" class="btn-secondary" style="width: 100%; background: #f44336; color: white;">🗑️ Löschen</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($poll['target_type'] === 'individual' && ($is_creator || $is_admin)): ?>
    <div class="access-link-box">
        <h4 style="margin: 0 0 10px 0;">🔗 Zugangslink</h4>
        <p style="margin: 0 0 10px 0;">Teilen Sie diesen Link mit den gewünschten Teilnehmern:</p>
        <?php
        $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        $link = get_poll_access_link($poll, $host);
        ?>
        <input type="text" value="<?php echo htmlspecialchars($link); ?>" readonly onclick="this.select()">
        <button type="button" class="btn-copy" onclick="copyToClipboard('<?php echo htmlspecialchars($link); ?>')">
            📋 Link kopieren
        </button>
    </div>
<?php endif; ?>

<div class="opinion-card">
    <h4>Antwortmöglichkeiten</h4>
    <ul class="option-list">
        <?php foreach ($poll['options'] as $option): ?>
            <li class="option-item" style="cursor: default; border-color: #e0e0e0;">
                <?php echo htmlspecialchars($option['option_text']); ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
