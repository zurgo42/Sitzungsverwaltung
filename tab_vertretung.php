<?php
/**
 * tab_vertretung.php - Abwesenheits- und Vertretungsverwaltung
 */

// Benachrichtigungsmodul laden
require_once 'module_notifications.php';

// Alle Mitglieder laden (falls noch nicht geladen)
if (!isset($all_members)) {
    $all_members = get_all_members($pdo);
}

// Aktuelle und zukünftige Abwesenheiten laden
// Nutzt Adapter-kompatible Funktion statt direktem JOIN auf svmembers
$absences = get_absences_with_names($pdo, "a.end_date >= CURDATE()");

// Eigene Abwesenheiten
// Nutzt Adapter-kompatible Funktion statt direktem JOIN auf svmembers
$my_absences = get_absences_with_names($pdo, "a.member_id = ?", [$current_user['member_id']]);
?>

<h2>🏖️ Vertretungen & Abwesenheiten</h2>

<!-- BENACHRICHTIGUNGEN -->
<?php render_user_notifications($pdo, $current_user['member_id']); ?>

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
        <!-- Desktop: Tabelle -->
        <table class="absence-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px; text-align: left;">Name</th>
                    <th style="padding: 10px; text-align: left;">Zeitraum</th>
                    <th style="padding: 10px; text-align: left;">Vertretungen</th>
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

        <!-- Mobile: Cards -->
        <div class="absence-cards" style="display: none;">
            <?php foreach ($absences as $abs):
                $is_current = (date('Y-m-d') >= $abs['start_date'] && date('Y-m-d') <= $abs['end_date']);
                $card_bg = $is_current ? '#fffbf0' : '#f8f9fa';
            ?>
                <div style="background: <?php echo $card_bg; ?>; padding: 15px; border-radius: 6px; margin-bottom: 12px; border-left: 4px solid <?php echo $is_current ? '#ff9800' : '#ddd'; ?>;">
                    <div style="margin-bottom: 10px;">
                        <strong style="font-size: 1.1em;"><?php echo htmlspecialchars($abs['first_name'] . ' ' . $abs['last_name']); ?></strong>
                        <?php if ($is_current): ?>
                            <span style="color: #ff9800; font-size: 11px; font-weight: 600; margin-left: 8px;">● AKTUELL</span>
                        <?php endif; ?>
                        <br>
                        <small style="color: #666;"><?php echo htmlspecialchars($abs['role']); ?></small>
                    </div>

                    <div style="margin-bottom: 8px;">
                        <strong style="color: #555;">📅 Zeitraum:</strong>
                        <?php echo date('d.m.Y', strtotime($abs['start_date'])); ?> - <?php echo date('d.m.Y', strtotime($abs['end_date'])); ?>
                    </div>

                    <?php if ($abs['substitute_member_id']): ?>
                        <div style="margin-bottom: 8px;">
                            <strong style="color: #555;">👤 Vertretung:</strong>
                            <?php echo htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']); ?>
                            <small style="color: #666;">(<?php echo htmlspecialchars($abs['sub_role']); ?>)</small>
                        </div>
                    <?php endif; ?>

                    <?php if ($abs['reason']): ?>
                        <div>
                            <strong style="color: #555;">📝 Grund:</strong>
                            <?php echo htmlspecialchars($abs['reason']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            /* Mobile: Cards statt Tabelle */
            @media (max-width: 768px) {
                .absence-table {
                    display: none !important;
                }
                .absence-cards {
                    display: block !important;
                }
            }
        </style>
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
        <!-- Desktop: Tabelle -->
        <table class="my-absence-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #ddd;">
                    <th style="padding: 10px; text-align: left;">Zeitraum</th>
                    <th style="padding: 10px; text-align: left;">Vertretungen</th>
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
                                <button onclick="editAbsence(<?php echo $abs['absence_id']; ?>, '<?php echo $abs['start_date']; ?>', '<?php echo $abs['end_date']; ?>', <?php echo $abs['substitute_member_id'] ?: 'null'; ?>, '<?php echo addslashes($abs['reason'] ?? ''); ?>')"
                                        style="background: #2196f3; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px; margin-right: 5px;">
                                    ✏️ Bearbeiten
                                </button>
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

        <!-- Mobile: Cards -->
        <div class="my-absence-cards" style="display: none;">
            <?php foreach ($my_absences as $abs):
                $is_future = (strtotime($abs['start_date']) > time());
                $is_current = (date('Y-m-d') >= $abs['start_date'] && date('Y-m-d') <= $abs['end_date']);
                $is_past = (strtotime($abs['end_date']) < time());

                if ($is_past) {
                    $card_bg = '#f5f5f5';
                    $text_color = '#999';
                    $border_color = '#ddd';
                } elseif ($is_current) {
                    $card_bg = '#fffbf0';
                    $text_color = '#333';
                    $border_color = '#ff9800';
                } else {
                    $card_bg = 'white';
                    $text_color = '#333';
                    $border_color = '#ddd';
                }
            ?>
                <div style="background: <?php echo $card_bg; ?>; padding: 15px; border-radius: 6px; margin-bottom: 12px; border-left: 4px solid <?php echo $border_color; ?>; color: <?php echo $text_color; ?>;">
                    <div style="margin-bottom: 10px; font-weight: bold;">
                        📅 <?php echo date('d.m.Y', strtotime($abs['start_date'])); ?> - <?php echo date('d.m.Y', strtotime($abs['end_date'])); ?>
                        <?php if ($is_current): ?>
                            <span style="color: #ff9800; font-size: 11px; font-weight: 600; margin-left: 8px;">● AKTUELL</span>
                        <?php elseif ($is_past): ?>
                            <span style="color: #999; font-size: 11px;">(Vergangenheit)</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($abs['substitute_member_id']): ?>
                        <div style="margin-bottom: 8px;">
                            <strong style="color: #555;">👤 Vertretung:</strong>
                            <?php echo htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($abs['reason']): ?>
                        <div style="margin-bottom: 8px;">
                            <strong style="color: #555;">📝 Grund:</strong>
                            <?php echo htmlspecialchars($abs['reason']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$is_past): ?>
                        <div style="margin-top: 12px;">
                            <form method="POST" onsubmit="return confirm('Abwesenheit wirklich löschen?');">
                                <input type="hidden" name="delete_absence" value="1">
                                <input type="hidden" name="absence_id" value="<?php echo $abs['absence_id']; ?>">
                                <button type="submit" style="background: #f44336; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; width: 100%;">
                                    🗑️ Abwesenheit löschen
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            /* Mobile: Cards statt Tabelle */
            @media (max-width: 768px) {
                .my-absence-table {
                    display: none !important;
                }
                .my-absence-cards {
                    display: block !important;
                }
            }
        </style>
    <?php endif; ?>
</div>

<!-- Edit Modal -->
<div id="editAbsenceModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
        <h3 style="margin-top: 0;">✏️ Abwesenheit bearbeiten</h3>
        <form method="POST" action="?tab=vertretung">
            <input type="hidden" name="edit_absence" value="1">
            <input type="hidden" name="absence_id" id="edit_absence_id">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Von:</label>
                    <input type="date" name="start_date" id="edit_start_date" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Bis:</label>
                    <input type="date" name="end_date" id="edit_end_date" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Vertretung:</label>
                <select name="substitute_member_id" id="edit_substitute" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">– Keine Vertretung –</option>
                    <?php foreach ($available_members as $mem): ?>
                        <option value="<?php echo $mem['member_id']; ?>">
                            <?php echo htmlspecialchars($mem['first_name'] . ' ' . $mem['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Grund:</label>
                <input type="text" name="reason" id="edit_reason" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEditModal()" style="background: #999; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                    Abbrechen
                </button>
                <button type="submit" style="background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editAbsence(absenceId, startDate, endDate, substituteId, reason) {
    document.getElementById('edit_absence_id').value = absenceId;
    document.getElementById('edit_start_date').value = startDate;
    document.getElementById('edit_end_date').value = endDate;
    document.getElementById('edit_substitute').value = substituteId || '';
    document.getElementById('edit_reason').value = reason || '';
    document.getElementById('editAbsenceModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editAbsenceModal').style.display = 'none';
}

// Close modal on background click
document.getElementById('editAbsenceModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>
