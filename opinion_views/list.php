<?php
/**
 * Liste aller Meinungsbilder
 */

if (!$current_user) {
    echo "<p>Bitte melde dich an, um Meinungsbilder zu sehen.</p>";
    return;
}

$all_polls = get_all_opinion_polls($pdo, $current_user['member_id']);

$visible_polls = array_filter($all_polls, fn($p) => !$p['is_hidden_for_user']);
$hidden_polls  = array_filter($all_polls, fn($p) =>  $p['is_hidden_for_user']);

$tab_public_url  = $_tab_public_url  ?? null;
$tab_process_url = $_tab_process_url ?? 'process_opinion.php';

// Hilfsfunktion: kleines POST-Formular für hide/unhide
function _opinion_action_form($action, $poll_id, $label, $tab_process_url, $confirm = '') {
    $confirm_attr = $confirm ? ' onsubmit="return confirm(' . htmlspecialchars(json_encode($confirm), ENT_QUOTES) . ');"' : '';
    echo '<form method="POST" action="' . htmlspecialchars($tab_process_url) . '" style="margin:0;"' . $confirm_attr . '>';
    echo '<input type="hidden" name="action"  value="' . htmlspecialchars($action) . '">';
    echo '<input type="hidden" name="poll_id" value="' . intval($poll_id) . '">';
    if (!empty($_SESSION['opinion_mtool_share_url'])) {
        echo '<input type="hidden" name="redirect_to" value="' . htmlspecialchars($_SESSION['opinion_mtool_share_url']) . '">';
    }
    if (!empty($OPINION_MTOOL_MODE) && !empty($MNr)) {
        echo '<input type="hidden" name="mtool_mnr" value="' . htmlspecialchars($MNr) . '">';
    }
    echo '<button type="submit" style="width:100%;padding:6px 12px;background:#f0f0f0;border:1px solid #ccc;border-radius:6px;cursor:pointer;font-size:13px;color:#555;white-space:nowrap;">'
        . $label . '</button>';
    echo '</form>';
}
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <h3>Meine Meinungsbilder</h3>
    <a href="<?php echo _opinion_url('create'); ?>" class="btn-primary">+ Neues Meinungsbild erstellen</a>
</div>

<?php if (empty($all_polls)): ?>
    <div class="opinion-card">
        <p>Noch keine Meinungsbilder vorhanden.</p>
        <p><a href="<?php echo _opinion_url('create'); ?>">Erstelle jetzt dein erstes Meinungsbild!</a></p>
    </div>
<?php else: ?>

    <?php if (empty($visible_polls)): ?>
        <div class="opinion-card" style="color:#666;">
            <p>Alle Meinungsbilder sind ausgeblendet.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($visible_polls as $poll):
        $is_creator  = ($poll['creator_member_id'] == $current_user['member_id']);
        $is_admin    = in_array($current_user['role'], ['assistenz', 'gf']);
        $is_active   = ($poll['status'] === 'active' && !empty($poll['ends_at']) && strtotime($poll['ends_at']) > time());
        $has_responded = has_responded($pdo, $poll['poll_id'], $current_user['member_id'], null);

        $delete_at = null;
        if (!empty($poll['delete_after_days']) && !empty($poll['created_at'])) {
            $delete_at = date('d.m.Y', strtotime($poll['created_at']) + $poll['delete_after_days'] * 86400);
        }
    ?>
        <div class="opinion-card">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                <div style="flex: 1; min-width: 0;">
                    <h4 style="margin: 0 0 10px 0;">
                        <a href="<?php echo _opinion_url('detail', $poll['poll_id']); ?>" style="text-decoration: none; color: #333;">
                            <?php echo htmlspecialchars($poll['title']); ?>
                        </a>
                    </h4>

                    <div class="poll-meta">
                        <?php if ($is_creator): ?>
                            <span style="background:#2196F3;color:white;padding:2px 8px;border-radius:10px;font-size:11px;margin-right:5px;">Ersteller</span>
                        <?php endif; ?>

                        <span class="poll-status <?php echo $is_active ? 'status-active' : 'status-ended'; ?>">
                            <?php echo $is_active ? 'Aktiv' : 'Beendet'; ?>
                        </span>

                        <span style="margin-left:10px;">
                            📊 <?php echo $poll['response_count']; ?> Antwort<?php echo $poll['response_count'] != 1 ? 'en' : ''; ?>
                            <?php if ($poll['target_type'] === 'list' && $poll['participant_count'] > 0): ?>
                                von <?php echo $poll['participant_count']; ?> Teilnehmer<?php echo $poll['participant_count'] != 1 ? 'n' : ''; ?>
                            <?php endif; ?>
                        </span>

                        <span style="margin-left:10px;">
                            👤 <?php echo htmlspecialchars(($poll['first_name'] ?? '') . ' ' . ($poll['last_name'] ?? '')); ?>
                        </span>

                        <span style="margin-left:10px;">
                            🕒 <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?>
                        </span>

                        <?php if ($is_active && $poll['ends_at']): ?>
                            <span style="margin-left:10px;">
                                ⏱ Läuft bis: <?php echo date('d.m.Y H:i', strtotime($poll['ends_at'])); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($delete_at): ?>
                            <span style="margin-left:10px;color:#999;">
                                🗑 Löschen am: <?php echo $delete_at; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($has_responded): ?>
                        <div style="margin-top:10px;color:#4CAF50;font-weight:bold;">
                            ✓ Du hast an dieser Umfrage teilgenommen
                        </div>
                    <?php endif; ?>

                    <?php
                    // Zugangslink (nur für Ersteller und Admins)
                    if ($is_creator || $is_admin):
                        if (!empty($tab_public_url)) {
                            $sep = strpos($tab_public_url, '?') !== false ? '&' : '?';
                            if (!empty($poll['access_token'])) {
                                $access_link = $tab_public_url . $sep . 'token=' . urlencode($poll['access_token']);
                            } elseif (($poll['target_type'] ?? '') === 'public') {
                                $access_link = $tab_public_url . $sep . 'poll_id=' . intval($poll['poll_id']);
                            } else {
                                $access_link = null;
                            }
                        } else {
                            $access_link = get_poll_access_link($poll);
                        }
                        if ($access_link):
                    ?>
                        <div style="margin-top:10px;padding:10px;background:#f0f8ff;border:1px solid #4CAF50;border-radius:4px;">
                            <strong>🔗 Zugangslink:</strong>
                            <div style="display:flex;gap:10px;align-items:center;margin-top:5px;">
                                <input type="text"
                                       value="<?php echo htmlspecialchars($access_link); ?>"
                                       readonly onclick="this.select()"
                                       style="flex:1;min-width:0;padding:5px;font-size:12px;font-family:monospace;border:1px solid #ccc;background:white;">
                                <button onclick="copyToClipboard('<?php echo htmlspecialchars($access_link, ENT_QUOTES); ?>')"
                                        class="btn-secondary"
                                        style="padding:5px 15px;white-space:nowrap;flex-shrink:0;">
                                    📋 Kopieren
                                </button>
                            </div>
                            <small style="color:#666;display:block;margin-top:5px;">
                                <?php
                                if ($poll['target_type'] === 'individual') {
                                    echo 'Dieser Link ist eindeutig für diese Umfrage. Teile ihn mit den gewünschten Teilnehmern.';
                                } elseif ($poll['target_type'] === 'public') {
                                    echo 'Dieser Link ist öffentlich. Jeder mit diesem Link kann teilnehmen.';
                                } else {
                                    echo 'Nur eingeladene Mitglieder können teilnehmen.';
                                }
                                ?>
                            </small>
                        </div>
                    <?php
                        endif;
                    endif;
                    ?>
                </div>

                <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;min-width:120px;">
                    <?php if ($is_active && !$has_responded): ?>
                        <a href="<?php echo _opinion_url('participate', $poll['poll_id']); ?>" class="btn-primary" style="text-decoration:none;text-align:center;">
                            Teilnehmen
                        </a>
                    <?php endif; ?>

                    <?php if ($has_responded || $is_creator || $is_admin): ?>
                        <a href="<?php echo _opinion_url('results', $poll['poll_id']); ?>" class="btn-secondary" style="text-decoration:none;text-align:center;">
                            Ergebnisse
                        </a>
                    <?php endif; ?>

                    <a href="<?php echo _opinion_url('detail', $poll['poll_id']); ?>" class="btn-secondary" style="text-decoration:none;text-align:center;">
                        Details
                    </a>

                    <?php _opinion_action_form('hide_poll', $poll['poll_id'], '👁 Für mich ausblenden', $tab_process_url); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($hidden_polls)): ?>
        <details style="margin-top:20px;">
            <summary style="cursor:pointer;font-weight:bold;color:#666;padding:12px 16px;background:#f5f5f5;border-radius:6px;border:1px solid #ddd;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>👁 Für mich ausgeblendet (<?php echo count($hidden_polls); ?>)</span>
                <span style="font-size:18px;line-height:1;">▾</span>
            </summary>
            <div style="margin-top:10px;">
                <?php foreach ($hidden_polls as $poll):
                    $is_creator  = ($poll['creator_member_id'] == $current_user['member_id']);
                    $is_admin    = in_array($current_user['role'], ['assistenz', 'gf']);
                    $has_responded = has_responded($pdo, $poll['poll_id'], $current_user['member_id'], null);
                ?>
                    <div class="opinion-card" style="opacity:0.7;">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                            <div style="flex:1;min-width:0;">
                                <span style="color:#555;"><?php echo htmlspecialchars($poll['title']); ?></span>
                                <span style="margin-left:10px;font-size:13px;color:#999;">
                                    📊 <?php echo $poll['response_count']; ?> Antwort<?php echo $poll['response_count'] != 1 ? 'en' : ''; ?>
                                </span>
                            </div>
                            <div style="display:flex;gap:8px;flex-shrink:0;">
                                <?php if ($has_responded || $is_creator || $is_admin): ?>
                                    <a href="<?php echo _opinion_url('results', $poll['poll_id']); ?>" class="btn-secondary" style="text-decoration:none;font-size:13px;padding:6px 12px;">
                                        Ergebnisse
                                    </a>
                                <?php endif; ?>
                                <?php _opinion_action_form('unhide_poll', $poll['poll_id'], '↩ Wieder einblenden', $tab_process_url); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

<?php endif; ?>
