<?php
/**
 * tab_agenda_form_new_top.php - Formular für neue TOPs (als Details/Summary)
 */

if (!$can_edit_meeting) {
    return; // Nur für Berechtigte
}
?>

<details style="margin: 20px 0; border: 2px solid #4caf50; border-radius: 8px; overflow: hidden;">
    <summary style="padding: 15px; background: #4caf50; color: white; font-size: 16px; font-weight: 600; cursor: pointer; list-style: none;">
        <span style="display: inline-block; width: 20px;">▶</span> ➕ Neuen TOP hinzufügen
    </summary>
    
    <div style="padding: 15px; background: #f1f8e9;">
        <form method="POST" action="">
            <input type="hidden" name="add_agenda_item" value="1">
            
            <div class="form-group">
                <label style="font-weight: 600;">Titel:</label>
                <input type="text" name="title" required>
            </div>
            
            <div class="form-group">
                <label style="font-weight: 600;">Beschreibung:</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label style="font-weight: 600;">Kategorie:</label>
                <?php render_category_select('category', 'new_top_category', '', 'toggleProposalField(\'new_top\')'); ?>
            </div>
            
            <div class="form-group" id="new_top_proposal" style="display:none;">
                <label style="font-weight: 600;">📄 Antragstext:</label>
                <textarea name="proposal_text" rows="4" style="width: 100%; padding: 8px; border: 1px solid #4caf50; border-radius: 4px;"></textarea>
            </div>

            <?php
            // Antragsteller-Auswahl für Sekretär und Assistenz
            $is_assistenz_prep = in_array(strtolower($current_user['role'] ?? ''), ['assistenz']);
            if ($is_secretary || $is_assistenz_prep):
                // Alle registrierten Mitglieder nach Rolle sortiert laden
                $all_registered_prep = get_all_registered_members($pdo);
                $sorted_members_prep = sort_members_by_role_hierarchy($all_registered_prep);
            ?>
            <div class="form-group">
                <label style="font-weight: 600;">👤 Antragsteller:</label>
                <select name="created_by_member_id" style="width: 100%; padding: 8px; border: 1px solid #4caf50; border-radius: 4px;">
                    <option value="">-- Bitte wählen --</option>
                    <?php foreach ($sorted_members_prep as $member):
                        $display_role = $member['role_display'] ?? get_role_display_name($member['role']);
                    ?>
                        <option value="<?php echo $member['member_id']; ?>" <?php echo ($member['member_id'] == $current_user['member_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $display_role . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="display: block; margin-top: 4px; color: #666;">
                    Wähle die Person aus, die diesen TOP beantragt
                </small>
            </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label style="font-weight: 600;">Priorität (1-10):</label>
                    <input type="number" name="priority" min="1" max="10" step="0.1" value="5" required>
                </div>
                
                <div class="form-group">
                    <label style="font-weight: 600;">Geschätzte Dauer (Min.):</label>
                    <input type="number" name="duration" min="1" value="10" required>
                </div>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="is_confidential" value="1" style="margin-right: 8px;">
                    🔒 Vertraulich (nur für berechtigte Teilnehmer)
                </label>
            </div>
            
            <button type="submit" style="background: #4caf50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                ✅ TOP hinzufügen
            </button>
        </form>
    </div>
</details>

<style>
details[open] summary span {
    transform: rotate(90deg);
    display: inline-block;
}
</style>
