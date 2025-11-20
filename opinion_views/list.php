<?php
/**
 * Liste aller Meinungsbilder
 */

// Nur für eingeloggte User
if (!$current_user) {
    echo "<p>Bitte melden Sie sich an, um Meinungsbilder zu sehen.</p>";
    return;
}

$all_polls = get_all_opinion_polls($pdo, $current_user['member_id']);
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <h3>Meine Meinungsbilder</h3>
    <a href="?tab=opinion&view=create" class="btn-primary">+ Neues Meinungsbild erstellen</a>
</div>

<?php if (empty($all_polls)): ?>
    <div class="opinion-card">
        <p>Noch keine Meinungsbilder vorhanden.</p>
        <p><a href="?tab=opinion&view=create">Erstellen Sie jetzt Ihr erstes Meinungsbild!</a></p>
    </div>
<?php else: ?>
    <?php foreach ($all_polls as $poll):
        $is_creator = ($poll['creator_member_id'] == $current_user['member_id']);
        $is_admin = in_array($current_user['role'], ['assistenz', 'gf']);
        $is_active = ($poll['status'] === 'active' && strtotime($poll['ends_at']) > time());
        $has_responded = has_responded($pdo, $poll['poll_id'], $current_user['member_id'], null);
    ?>
        <div class="opinion-card">
            <div class="opinion-card-layout">
                <div class="opinion-card-content">
                    <h4 style="margin: 0 0 10px 0;">
                        <a href="?tab=opinion&view=detail&poll_id=<?php echo $poll['poll_id']; ?>" style="text-decoration: none; color: #333;">
                            <?php echo htmlspecialchars($poll['title']); ?>
                        </a>
                    </h4>

                    <div class="poll-meta">
                        <?php if ($is_creator): ?>
                            <span style="background: #2196F3; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-right: 5px;">Ersteller</span>
                        <?php endif; ?>

                        <span class="poll-status <?php echo $is_active ? 'status-active' : 'status-ended'; ?>">
                            <?php echo $is_active ? 'Aktiv' : 'Beendet'; ?>
                        </span>

                        <span style="margin-left: 10px;">
                            📊 <?php echo $poll['response_count']; ?> Antwort<?php echo $poll['response_count'] != 1 ? 'en' : ''; ?>
                        </span>

                        <span style="margin-left: 10px;">
                            👤 <?php echo htmlspecialchars($poll['first_name'] . ' ' . $poll['last_name']); ?>
                        </span>

                        <span style="margin-left: 10px;">
                            🕒 <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?>
                        </span>

                        <?php if ($is_active): ?>
                            <span style="margin-left: 10px;">
                                ⏱ Läuft bis: <?php echo date('d.m.Y H:i', strtotime($poll['ends_at'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($has_responded): ?>
                        <div style="margin-top: 10px; color: #4CAF50; font-weight: bold;">
                            ✓ Sie haben an dieser Umfrage teilgenommen
                        </div>
                    <?php endif; ?>

                    <?php
                    // Zugangslink anzeigen
                    $access_link = get_poll_access_link($poll, defined('BASE_URL') ? BASE_URL : 'http://' . $_SERVER['HTTP_HOST']);
                    if ($access_link):
                    ?>
                        <div style="margin-top: 10px; padding: 10px; background: #f0f8ff; border: 1px solid #4CAF50; border-radius: 4px;">
                            <strong>🔗 Zugangslink:</strong>
                            <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                                <input type="text"
                                       value="<?php echo htmlspecialchars($access_link); ?>"
                                       readonly
                                       onclick="this.select()"
                                       style="flex: 1; padding: 5px; font-size: 12px; font-family: monospace; border: 1px solid #ccc; background: white;">
                                <button onclick="copyToClipboard('<?php echo htmlspecialchars($access_link, ENT_QUOTES); ?>')"
                                        class="btn-secondary"
                                        style="padding: 5px 15px; white-space: nowrap;">
                                    📋 Kopieren
                                </button>
                            </div>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                <?php
                                if ($poll['target_type'] === 'individual') {
                                    echo 'Dieser Link ist eindeutig für diese Umfrage. Teilen Sie ihn mit den gewünschten Teilnehmern.';
                                } elseif ($poll['target_type'] === 'public') {
                                    echo 'Dieser Link ist öffentlich. Jeder mit diesem Link kann teilnehmen.';
                                } else {
                                    echo 'Nur eingeladene Mitglieder können teilnehmen.';
                                }
                                ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="opinion-card-actions">
                    <?php if ($is_active && !$has_responded): ?>
                        <a href="?tab=opinion&view=participate&poll_id=<?php echo $poll['poll_id']; ?>" class="btn-primary" style="text-decoration: none;">
                            Teilnehmen
                        </a>
                    <?php endif; ?>

                    <?php if ($has_responded || $is_creator || $is_admin): ?>
                        <a href="?tab=opinion&view=results&poll_id=<?php echo $poll['poll_id']; ?>" class="btn-secondary" style="text-decoration: none;">
                            Ergebnisse
                        </a>
                    <?php endif; ?>

                    <a href="?tab=opinion&view=detail&poll_id=<?php echo $poll['poll_id']; ?>" class="btn-secondary" style="text-decoration: none;">
                        Details
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
