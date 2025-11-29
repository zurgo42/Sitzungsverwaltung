<?php
/**
 * tab_vertretung.php - Abwesenheits- und Vertretungsverwaltung
 */

// Alle Mitglieder laden (falls noch nicht geladen)
if (!isset($all_members)) {
    $all_members = get_all_members($pdo);
}

// Aktuelle und zukünftige Abwesenheiten laden
$stmt_all_absences = $pdo->prepare("
    SELECT a.*,
           m.first_name, m.last_name, m.role,
           s.first_name AS sub_first_name, s.last_name AS sub_last_name, s.role AS sub_role
    FROM svabsences a
    JOIN svmembers m ON a.member_id = m.member_id
    LEFT JOIN svmembers s ON a.substitute_member_id = s.member_id
    WHERE a.end_date >= CURDATE()
    ORDER BY a.start_date ASC, m.last_name ASC
");
$stmt_all_absences->execute();
$absences = $stmt_all_absences->fetchAll();

// Eigene Abwesenheiten
$stmt_my_absences = $pdo->prepare("
    SELECT a.*,
           s.first_name AS sub_first_name, s.last_name AS sub_last_name
    FROM svabsences a
    LEFT JOIN svmembers s ON a.substitute_member_id = s.member_id
    WHERE a.member_id = ?
    ORDER BY a.start_date DESC
");
$stmt_my_absences->execute([$current_user['member_id']]);
$my_absences = $stmt_my_absences->fetchAll();
?>

<h2>🎨 Vertretungen & Abwesenheiten</h2>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'absence_added'): ?>
    <div class="message">✅ Abwesenheit erfolgreich eingetragen!</div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'absence_deleted'): ?>
    <div class="message">✅ Abwesenheit erfolgreich gelöscht!</div>
<?php endif; ?>

<!-- ÜBERSICHT ALLER AKTUELLEN UND ZUKÜNFTIGEN ABWESENHEITEN -->
<div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <h3>📅 Aktuelle und geplante Abwesenheiten</h3>

    <?php if (empty($absences)): ?>
        <p style="color: #666;">Keine Abwesenheiten eingetragen.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px; text-align: left;">Name</th>
                    <th style="padding: 10px; text-align: left;">Zeitraum</th>
                    <th style="padding: 10px; text-align: left;">Vertretung</th>
                    <th style="padding: 10px; text-align: left;">Grund</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($absences as $abs):
                    $is_current = (date('Y-m-d') >= $abs['start_date'] && date('Y-m-d') <= $abs['end_date']);
                    $row_bg = $is_current ? '#fffbf0' : 'white';
                ?>
                    <tr style="border-bottom: 1px solid #eee; background: <?php echo $row_bg; ?>;">
                        <td style="padding: 10px;">
                            <strong><?php echo htmlspecialchars($abs['first_name'] . ' ' . $abs['last_name']); ?></strong>
                            <?php if ($is_current): ?>
                                <span style="color: #ff9800; font-size: 11px; font-weight: 600;">● AKTUELL</span>
                            <?php endif; ?>
                            <br>
                            <small style="color: #666;"><?php echo htmlspecialchars($abs['role']); ?></small>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo date('d.m.Y', strtotime($abs['start_date'])); ?>
                            -
                            <?php echo date('d.m.Y', strtotime($abs['end_date'])); ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php if ($abs['substitute_member_id']): ?>
                                <strong><?php echo htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']); ?></strong>
                                <br>
                                <small style="color: #666;"><?php echo htmlspecialchars($abs['sub_role']); ?></small>
                            <?php else: ?>
                                <span style="color: #999;">–</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php if ($abs['reason']): ?>
                                <?php echo htmlspecialchars($abs['reason']); ?>
                            <?php else: ?>
                                <span style="color: #999;">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- MEINE ABWESENHEITEN -->
<div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <h3>🏖️ Meine Abwesenheiten</h3>

    <!-- FORMULAR ZUM EINTRAGEN -->
    <details style="margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">
        <summary style="cursor: pointer; font-weight: 600; color: #2196f3;">➕ Neue Abwesenheit eintragen</summary>
        <form method="POST" action="?tab=vertretung" style="margin-top: 15px;">
            <input type="hidden" name="add_absence" value="1">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Von:</label>
                    <input type="date" name="start_date" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Bis:</label>
                    <input type="date" name="end_date" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Vertretung (optional):</label>
                <select name="substitute_member_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">-- Keine Vertretung --</option>
                    <?php foreach ($all_members as $member):
                        if ($member['member_id'] == $current_user['member_id']) continue;
                    ?>
                        <option value="<?php echo $member['member_id']; ?>">
                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Grund (optional):</label>
                <input type="text" name="reason" placeholder="z.B. Urlaub, Dienstreise, Krankheit..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <button type="submit" style="background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                Abwesenheit eintragen
            </button>
        </form>
    </details>

    <!-- LISTE MEINER ABWESENHEITEN -->
    <?php if (empty($my_absences)): ?>
        <p style="color: #666;">Sie haben noch keine Abwesenheiten eingetragen.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px; text-align: left;">Zeitraum</th>
                    <th style="padding: 10px; text-align: left;">Vertretung</th>
                    <th style="padding: 10px; text-align: left;">Grund</th>
                    <th style="padding: 10px; text-align: left;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_absences as $abs):
                    $is_future = (strtotime($abs['start_date']) > time());
                    $is_current = (date('Y-m-d') >= $abs['start_date'] && date('Y-m-d') <= $abs['end_date']);
                    $is_past = (strtotime($abs['end_date']) < time());

                    if ($is_past) {
                        $row_bg = '#f5f5f5';
                        $text_color = '#999';
                    } elseif ($is_current) {
                        $row_bg = '#fffbf0';
                        $text_color = '#333';
                    } else {
                        $row_bg = 'white';
                        $text_color = '#333';
                    }
                ?>
                    <tr style="border-bottom: 1px solid #eee; background: <?php echo $row_bg; ?>; color: <?php echo $text_color; ?>;">
                        <td style="padding: 10px;">
                            <?php echo date('d.m.Y', strtotime($abs['start_date'])); ?>
                            -
                            <?php echo date('d.m.Y', strtotime($abs['end_date'])); ?>
                            <?php if ($is_current): ?>
                                <span style="color: #ff9800; font-size: 11px; font-weight: 600;">● AKTUELL</span>
                            <?php elseif ($is_past): ?>
                                <span style="color: #999; font-size: 11px;">(Vergangenheit)</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php if ($abs['substitute_member_id']): ?>
                                <?php echo htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']); ?>
                            <?php else: ?>
                                <span style="color: #999;">–</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo $abs['reason'] ? htmlspecialchars($abs['reason']) : '<span style="color: #999;">–</span>'; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php if (!$is_past): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Abwesenheit wirklich löschen?');">
                                    <input type="hidden" name="delete_absence" value="1">
                                    <input type="hidden" name="absence_id" value="<?php echo $abs['absence_id']; ?>">
                                    <button type="submit" style="background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;">
                                        🗑️ Löschen
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
