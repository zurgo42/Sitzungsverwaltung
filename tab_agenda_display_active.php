<?php
/**
 * tab_agenda_display_active.php - TOP-Anzeige während aktiver Sitzung
 * Version 2.0 - Mit allen Features
 */

// Module laden
require_once 'module_todos.php';
require_once 'module_agenda_overview.php';

// Kollaboratives Protokoll JavaScript einbinden (wenn aktiv)
if (!empty($meeting['collaborative_protocol']) && $meeting['collaborative_protocol'] == 1) {
    // Cache-Buster: Version 3.0 (Master-Slave Queue-System)
    echo '<script src="js/collab_protocol_queue.js?v=3.0"></script>';
}

if (empty($agenda_items)) {
    echo '<div class="info-box">Keine Tagesordnungspunkte vorhanden.</div>';
    return;
}

// Übersicht anzeigen (read-only während aktiver Sitzung)
render_simple_agenda_overview($agenda_items, $current_user, $current_meeting_id, $pdo);

// Aktiven TOP ermitteln
$stmt = $pdo->prepare("SELECT active_item_id FROM svmeetings WHERE meeting_id = ?");
$stmt->execute([$current_meeting_id]);
$active_item_id = $stmt->fetchColumn();
?>

<style>
/* Mobile-responsive live-comment form */
.live-comment-form {
    display: flex;
    gap: 8px;
    align-items: end;
}

@media (max-width: 768px) {
    .live-comment-form {
        flex-direction: column;
        align-items: stretch;
    }

    .live-comment-form button {
        width: 100%;
        margin-top: 4px;
    }
}
</style>

<h3 style="margin: 20px 0 15px 0;">🟢 Laufende Sitzung - Tagesordnungspunkte</h3>

<!-- TEILNEHMERLISTE -->
<?php if ($is_secretary): ?>
    <style>
        .participant-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 8px;
            background: white;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .participant-row {
                display: block;
                padding: 8px;
                font-size: 12px;
            }
            .participant-row .participant-name {
                display: block;
                font-size: 13px;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
            }
            .participant-row label {
                display: inline-flex !important; /* Override inline style to keep buttons horizontal */
                align-items: center;
                font-size: 11px;
                margin-right: 8px;
                gap: 4px;
            }
            .participant-row label span {
                font-size: 11px;
            }
            .participant-row input[type="radio"] {
                transform: scale(0.9);
            }
        }
    </style>
    <details open style="margin: 20px 0; padding: 15px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 8px;">
        <summary style="cursor: pointer; font-weight: 600; color: #1976d2; font-size: 16px; margin-bottom: 10px;">
            👥 Teilnehmerverwaltung (klicken zum Auf-/Zuklappen)
        </summary>

        <form method="POST" action="">
            <input type="hidden" name="update_attendance" value="1">

            <div style="margin-bottom: 15px;">
                <button type="button" onclick="setAllPresent()" style="background: #4caf50; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    ✅ Alle auf "Anwesend" setzen
                </button>
            </div>

            <div style="display: grid; gap: 10px;">
                <?php foreach ($participants as $p):
                    $stmt = $pdo->prepare("SELECT attendance_status FROM svmeeting_participants WHERE meeting_id = ? AND member_id = ?");
                    $stmt->execute([$current_meeting_id, $p['member_id']]);
                    $attendance = $stmt->fetch();
                    $status = $attendance['attendance_status'] ?? 'absent';
                ?>
                    <div class="participant-row">
                        <span class="participant-name" style="flex: 1; font-weight: 600;">
                            <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                        </span>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="present"
                                   class="attendance-radio"
                                   data-member="<?php echo $p['member_id']; ?>"
                                   <?php echo $status === 'present' ? 'checked' : ''; ?>>
                            <span>✅ Anwesend</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="partial"
                                   <?php echo $status === 'partial' ? 'checked' : ''; ?>>
                            <span>⏱️ Zeitweise</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="absent"
                                   <?php echo $status === 'absent' ? 'checked' : ''; ?>>
                            <span>❌ Abwesend</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="submit" style="margin-top: 15px; background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                💾 Teilnehmerliste speichern
            </button>
        </form>

        <!-- Nicht eingeladene Teilnehmer hinzufügen -->
        <div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #2196f3;">
            <h4 style="margin: 0 0 10px 0; color: #1976d2;">➕ Nicht eingeladene Teilnehmer hinzufügen</h4>
            <form method="POST" action="">
                <input type="hidden" name="add_uninvited_participant" value="1">

                <?php
                // ALLE registrierten Mitglieder für Auswahl laden
                $all_registered = get_all_registered_members($pdo);
                // Nach Rollen-Hierarchie sortieren
                $uninvited_members = sort_members_by_role_hierarchy($all_registered);
                ?>

                <?php if (count($uninvited_members) > 0): ?>
                    <style>
                        .add-participant-container-active {
                            display: flex;
                            gap: 10px;
                            align-items: flex-end;
                        }
                        @media (max-width: 768px) {
                            .add-participant-container-active {
                                flex-direction: column;
                                align-items: stretch;
                                gap: 8px;
                            }
                            .add-participant-select-active {
                                width: 100% !important;
                            }
                            .add-participant-button-active {
                                width: 100%;
                                padding: 10px !important;
                            }
                        }
                    </style>
                    <div class="add-participant-container-active">
                        <div style="flex: 3;">
                            <select name="new_participant_id" required class="add-participant-select-active" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="">-- Teilnehmer auswählen --</option>
                                <?php foreach ($uninvited_members as $um):
                                    // Display-Name verwenden wenn vorhanden, sonst konvertieren
                                    $display_role = isset($um['role_display']) ? $um['role_display'] : get_role_display_name($um['role']);
                                ?>
                                    <option value="<?php echo $um['member_id']; ?>">
                                        <?php echo htmlspecialchars($um['first_name'] . ' ' . $um['last_name'] . ' (' . $display_role . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="add-participant-button-active" style="background: #4caf50; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
                            ➕ Hinzufügen
                        </button>
                    </div>
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Hinzugefügte Teilnehmer erhalten automatisch den Status "invited" und "present".
                    </small>
                <?php else: ?>
                    <p style="margin: 0; color: #666; font-style: italic;">Alle Mitglieder sind bereits eingeladen.</p>
                <?php endif; ?>
            </form>
        </div>

        <script>
        function setAllPresent() {
            document.querySelectorAll('.attendance-radio').forEach(radio => {
                if (radio.value === 'present') {
                    radio.checked = true;
                }
            });
        }
        </script>
    </details>

    <!-- Vorsitz und Protokollführung -->
    <?php
    global $members_by_id;
    $chairman_name = 'Nicht zugewiesen';
    $secretary_name = 'Nicht zugewiesen';

    if (!empty($meeting['chairman_member_id']) && isset($members_by_id[$meeting['chairman_member_id']])) {
        $chairman = $members_by_id[$meeting['chairman_member_id']];
        $chairman_name = htmlspecialchars($chairman['first_name'] . ' ' . $chairman['last_name']);
    }

    if (!empty($meeting['secretary_member_id']) && isset($members_by_id[$meeting['secretary_member_id']])) {
        $secretary = $members_by_id[$meeting['secretary_member_id']];
        $secretary_name = htmlspecialchars($secretary['first_name'] . ' ' . $secretary['last_name']);
    }
    ?>
    <div style="margin-top: 10px; padding: 10px; background: #e8f4f8; border: 1px solid #90caf9; border-radius: 4px; font-size: 13px;">
        <strong>Vorsitz:</strong> <?php echo $chairman_name; ?> &nbsp;|&nbsp;
        <strong>Protokollführung:</strong> <?php echo $secretary_name; ?>
    </div>
<?php else: ?>
    <?php render_readonly_participant_list($pdo, $current_meeting_id, $participants); ?>

    <!-- Vorsitz und Protokollführung -->
    <?php
    global $members_by_id;
    $chairman_name = 'Nicht zugewiesen';
    $secretary_name = 'Nicht zugewiesen';

    if (!empty($meeting['chairman_member_id']) && isset($members_by_id[$meeting['chairman_member_id']])) {
        $chairman = $members_by_id[$meeting['chairman_member_id']];
        $chairman_name = htmlspecialchars($chairman['first_name'] . ' ' . $chairman['last_name']);
    }

    if (!empty($meeting['secretary_member_id']) && isset($members_by_id[$meeting['secretary_member_id']])) {
        $secretary = $members_by_id[$meeting['secretary_member_id']];
        $secretary_name = htmlspecialchars($secretary['first_name'] . ' ' . $secretary['last_name']);
    }
    ?>
    <div style="margin-top: 10px; padding: 10px; background: #e8f4f8; border: 1px solid #90caf9; border-radius: 4px; font-size: 13px;">
        <strong>Vorsitz:</strong> <?php echo $chairman_name; ?> &nbsp;|&nbsp;
        <strong>Protokollführung:</strong> <?php echo $secretary_name; ?>
    </div>
<?php endif; ?>

<!-- NEUEN TOP HINZUFÜGEN (nur Sekretär) -->
<?php if ($is_secretary): ?>
<style>
    .top-add-form-footer {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 15px;
    }
    .top-add-submit-btn {
        background: #4caf50;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        white-space: nowrap;
    }
    @media (max-width: 768px) {
        .top-add-form-footer {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        .top-add-form-footer label {
            margin-bottom: 0;
        }
        .top-add-submit-btn {
            width: 100%;
            padding: 14px 20px;
            font-size: 15px;
        }
    }
</style>
<div class="form-section" style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 20px 0; border: 2px solid #4caf50;">
    <h3 style="color: #2e7d32; margin-bottom: 15px;">➕ Neuen TOP hinzufügen (während Sitzung)</h3>

    <form method="POST" action="">
        <input type="hidden" name="add_agenda_item_active" value="1">

        <div class="form-group">
            <label>Titel:</label>
            <input type="text" name="title" required placeholder="TOP-Titel...">
        </div>

        <div class="form-group">
            <label>Beschreibung:</label>
            <textarea name="description" rows="2" placeholder="Kurze Beschreibung..."></textarea>
        </div>

        <div class="form-group">
            <label>Kategorie:</label>
            <?php render_category_select('category', 'active_new_category', 'information', 'toggleProposalField(\'active_new\')'); ?>
        </div>

        <div class="form-group" id="active_new_proposal" style="display:none;">
            <label style="font-weight: 600; color: #856404;">📄 Antragstext:</label>
            <textarea name="proposal_text"
                      rows="4"
                      placeholder="Formulierung des Antrags..."
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
        </div>

        <?php
        // Antragsteller-Auswahl nur für Sekretär und Assistenz
        $is_assistenz_active = in_array(strtolower($current_user['role'] ?? ''), ['assistenz']);
        if ($is_secretary || $is_assistenz_active):
            // Alle registrierten Mitglieder nach Rolle sortiert laden
            $all_registered_active = get_all_registered_members($pdo);
            $sorted_members_active = sort_members_by_role_hierarchy($all_registered_active);
        ?>
        <div class="form-group">
            <label>👤 Antragsteller:</label>
            <select name="created_by_member_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="">-- Bitte wählen --</option>
                <?php foreach ($sorted_members_active as $member):
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

        <div class="top-add-form-footer">
            <label style="display: flex; align-items: center; gap: 5px;">
                <input type="checkbox" name="is_confidential" value="1" style="width: auto;">
                <span>🔒 Vertraulich</span>
            </label>

            <button type="submit" class="top-add-submit-btn">
                ➕ TOP hinzufügen
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- TOPS ANZEIGEN -->

<style>
    /* Globale Box-Sizing für alle Elemente */
    .agenda-item *,
    .agenda-item *::before,
    .agenda-item *::after {
        box-sizing: border-box;
    }

    .top-header-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .top-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .top-header-right {
        display: flex;
        gap: 5px;
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        /* TOP-Header Smartphone-Optimierung */
        .top-header-container {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .top-header-left {
            gap: 6px;
            font-size: 14px;
        }
        .top-header-left strong {
            font-size: 14px !important;
            flex: 1 1 100%;
        }
        .top-header-right {
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 6px;
        }
        .top-header-right button {
            font-size: 10px !important;
            padding: 3px 8px !important;
        }

        /* Alle Eingabefelder auf Smartphones */
        .agenda-item textarea,
        .agenda-item input[type="text"],
        .agenda-item input[type="number"],
        .agenda-item select {
            max-width: 100%;
            font-size: 14px !important;
        }

        /* Padding für bessere Lesbarkeit */
        .agenda-item {
            padding: 10px !important;
        }
    }
</style>

<?php 
// Berechtigung für vertrauliche TOPs prüfen
$can_see_confidential = (
    $current_user['is_admin'] == 1 ||
    $current_user['is_confidential'] == 1 ||
    in_array($current_user['role'], ['vorstand', 'gf']) ||
    $is_secretary ||
    $is_chairman
);

$item_index = 0;
foreach ($agenda_items as $item): 
    $item_index++;
    
    // TOP 999 ausblenden
    if ($item['top_number'] == 999) {
        continue;
    }
    
    // Vertrauliche TOPs nur für berechtigte User
    if ($item['is_confidential'] && !$can_see_confidential) {
        continue;
    }
    
    $is_active = ($item['item_id'] == $active_item_id);
    $border_color = $is_active ? '#f44336' : '#667eea';
    $border_width = $is_active ? '4px' : '3px';
?>
    <div class="agenda-item" id="top-<?php echo $item['item_id']; ?>"
         style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border: <?php echo $border_width; ?> solid <?php echo $border_color; ?>; border-radius: 8px; <?php echo $is_active ? 'box-shadow: 0 0 15px rgba(244,67,54,0.4);' : ''; ?>">
        
        <!-- TOP-Header -->
        <div class="top-header-container">
            <div class="top-header-left">
                <?php if ($is_active): ?>
                    <span style="background: #f44336; color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">
                        🔴 AKTIV
                    </span>
                <?php endif; ?>
                <strong style="font-size: 16px; color: #333;">
                    TOP #<?php echo $item['top_number']; ?>: <?php echo htmlspecialchars($item['title']); ?>
                </strong>
                <?php render_category_badge($item['category']); ?>
                <?php if ($item['is_confidential']): ?>
                    <span class="badge" style="background: #f39c12; color: white;">🔒 Vertraulich</span>
                <?php endif; ?>
            </div>

            <?php if ($can_edit_meeting && $item['top_number'] != 999): ?>
                <div class="top-header-right">
                    <!-- Aktiv schalten via AJAX -->
                    <?php if (!$is_active): ?>
                        <button onclick="setActiveTop(<?php echo $item['item_id']; ?>, <?php echo $current_meeting_id; ?>)"
                                style="background: #f44336; color: white; padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                            🔴 Aktivieren
                        </button>
                    <?php else: ?>
                        <button onclick="unsetActiveTop(<?php echo $current_meeting_id; ?>)"
                                style="background: #999; color: white; padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                            ⚫ Deaktivieren
                        </button>
                    <?php endif; ?>

                    <!-- Verschieben öffentlich/vertraulich -->
                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('TOP wirklich verschieben?');">
                        <input type="hidden" name="toggle_confidential" value="1">
                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                        <button type="submit" style="background: #2196f3; color: white; padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                            <span class="desktop-only"><?php echo $item['is_confidential'] ? '🔓 Öffentlich' : '🔒 Vertraulich'; ?></span>
                            <span class="mobile-only"><?php echo $item['is_confidential'] ? 'Ändern in öffentlich' : 'Ändern in vertraulich'; ?></span>
                        </button>
                    </form>

                    <!-- Löschen (Admin oder Protokollführung) -->
                    <?php
                    $is_admin_active = ($current_user['role'] === 'admin');
                    if ($is_admin_active || $is_secretary):
                    ?>
                    <script>
                    function confirmDeleteTopActive<?php echo $item['item_id']; ?>() {
                        // Erste Bestätigung
                        if (!confirm('⚠️ WARNUNG: TOP #<?php echo $item['top_number']; ?> "<?php echo addslashes(htmlspecialchars($item['title'])); ?>" wirklich löschen?\n\nAlle Kommentare, Protokoll-Einträge und Anhänge werden ebenfalls gelöscht!')) {
                            return false;
                        }

                        // Zweite Bestätigung (zusätzliche Sicherheit)
                        if (!confirm('🛑 LETZTE WARNUNG!\n\nDieser Vorgang kann NICHT rückgängig gemacht werden!\n\nTOP #<?php echo $item['top_number']; ?> wirklich unwiderruflich löschen?')) {
                            return false;
                        }

                        return true;
                    }
                    </script>
                    <form method="POST" action="" style="display: inline;"
                          onsubmit="return confirmDeleteTopActive<?php echo $item['item_id']; ?>();">
                        <input type="hidden" name="delete_agenda_item" value="1">
                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                        <button type="submit" style="background: #f44336; color: white; padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                            🗑️ Löschen
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php render_proposal_display($item['proposal_text']); ?>
        
        <!-- Beschreibung -->
        <?php if ($item['description']): ?>
            <div style="color: #666; margin: 8px 0; font-size: 14px;">
                <?php echo nl2br(linkify_text($item['description'])); ?>
            </div>
        <?php endif; ?>

        <!-- TOP 0: Sitzungsleitung und Protokollführung bearbeiten (nur für Protokollführung) -->
        <?php if ($item['top_number'] == 0 && $is_secretary): ?>
            <details style="margin: 15px 0; border: 2px solid #ff9800; border-radius: 5px; padding: 15px; background: #fff3e0;">
                <summary style="cursor: pointer; font-weight: bold; color: #e65100; font-size: 15px;">
                    🔄 Sitzungsleitung und Protokollführung ändern
                </summary>
                <form method="POST" action="" style="margin-top: 15px;">
                    <input type="hidden" name="update_meeting_roles" value="1">

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">
                            👤 Sitzungsleitung (Vorsitz):
                        </label>
                        <?php
                        // Alle Teilnehmer für Auswahl laden
                        $all_members_for_roles = get_all_registered_members($pdo);
                        $sorted_members_for_roles = sort_members_by_role_hierarchy($all_members_for_roles);
                        ?>
                        <select name="chairman_id" required style="width: 100%; padding: 10px; border: 2px solid #ff9800; border-radius: 4px; font-size: 14px;">
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($sorted_members_for_roles as $member):
                                $display_role = $member['role_display'] ?? get_role_display_name($member['role']);
                                $is_selected = ($meeting['chairman_member_id'] == $member['member_id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $member['member_id']; ?>" <?php echo $is_selected; ?>>
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $display_role . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">
                            📝 Protokollführung:
                        </label>
                        <select name="secretary_id" required style="width: 100%; padding: 10px; border: 2px solid #ff9800; border-radius: 4px; font-size: 14px;">
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($sorted_members_for_roles as $member):
                                $display_role = $member['role_display'] ?? get_role_display_name($member['role']);
                                $is_selected = ($meeting['secretary_member_id'] == $member['member_id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $member['member_id']; ?>" <?php echo $is_selected; ?>>
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $display_role . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" style="background: #ff9800; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; width: 100%;">
                        💾 Rollen speichern
                    </button>

                    <div style="margin-top: 10px; padding: 8px; background: #fff9c4; border-left: 4px solid #fbc02d; font-size: 12px; color: #333;">
                        <strong>⚠️ Hinweis:</strong> Diese Änderung gilt für die gesamte Sitzung. Die neue Protokollführung übernimmt alle Rechte und Funktionen.
                    </div>
                </form>
            </details>
        <?php endif; ?>

        <!-- Meta-Info (nicht bei TOP 999) -->
        <?php if ($item['top_number'] != 999): ?>
            <div style="font-size: 12px; color: #999; margin: 8px 0;">
                Eingetragen von: <?php echo htmlspecialchars($item['creator_first'] . ' ' . $item['creator_last']); ?> |

                <!-- Priorität (editierbar für Sekretär bei aktivem TOP) -->
                <?php if ($is_active && $is_secretary): ?>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="update_active_priority" value="1">
                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                        <label>Priorität:</label>
                        <input type="number" name="priority" value="<?php echo htmlspecialchars($item['priority']); ?>"
                               min="1" max="10" step="0.1"
                               style="width: 50px; padding: 2px; border: 1px solid #2196f3; border-radius: 3px;">
                        <button type="submit" style="background: #2196f3; color: white; padding: 2px 8px; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">
                            💾
                        </button>
                    </form>
                <?php else: ?>
                    Priorität: <?php echo $item['priority']; ?>
                <?php endif; ?>
                |
                Dauer: <?php echo $item['estimated_duration']; ?> Min.
                
                <?php
                // Zeitbedarfsberechnung
                $remaining = calculate_remaining_time($pdo, $agenda_items, $item_index);
                if ($remaining['regular'] > 0 || $remaining['confidential'] > 0):
                ?>
                    <br><span style="background: #fff3cd; padding: 4px 8px; border-radius: 4px; display: inline-block;"><strong>Geschätzter Zeitbedarf ab hier:</strong>
                    <?php if ($remaining['regular'] > 0): ?>
                        <strong>Öffentlich: ~<?php echo $remaining['regular']; ?> Min.</strong>
                    <?php endif; ?>
                    <?php if ($remaining['confidential'] > 0): ?>
                        <strong>| Vertraulich: ~<?php echo $remaining['confidential']; ?> Min.</strong>
                    <?php endif; ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Bearbeiten-Button (für Protokollführung und Assistenz) -->
        <?php
        $is_assistenz_active = in_array(strtolower($current_user['role'] ?? ''), ['assistenz']);
        $can_edit_active = ($is_secretary || $is_assistenz_active || $current_user['role'] === 'admin');
        if ($can_edit_active && $item['top_number'] != 999):
        ?>
        <details style="margin: 15px 0; border: 1px solid #2196f3; border-radius: 5px; padding: 10px; background: #f0f7ff;">
            <summary style="cursor: pointer; font-weight: bold; color: #1976d2;">
                ✏️ TOP bearbeiten (Protokollführung/Assistenz)
            </summary>
            <form method="POST" action="" style="margin-top: 10px;">
                <input type="hidden" name="edit_agenda_item" value="1">
                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">

                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Titel:</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($item['title']); ?>"
                           required style="width: 100%; padding: 8px; border: 1px solid #2196f3; border-radius: 4px;">
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Beschreibung:</label>
                    <textarea name="description" rows="3"
                              style="width: 100%; padding: 8px; border: 1px solid #2196f3; border-radius: 4px;"><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Kategorie:</label>
                    <?php render_category_select('category', 'edit_category_active_' . $item['item_id'], $item['category'], 'toggleProposalField("edit_active_' . $item['item_id'] . '")'); ?>
                </div>

                <div style="margin-bottom: 10px;" id="edit_active_<?php echo $item['item_id']; ?>_proposal" style="display: <?php echo $item['category'] === 'antrag_beschluss' ? 'block' : 'none'; ?>;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">📄 Antragstext:</label>
                    <textarea name="proposal_text" rows="3"
                              style="width: 100%; padding: 8px; border: 1px solid #2196f3; border-radius: 4px;"><?php echo htmlspecialchars($item['proposal_text'] ?? ''); ?></textarea>
                </div>

                <?php
                // Ersteller-Feld für Protokollführung, Assistenz und Admin
                $is_admin_edit_active = ($current_user['role'] === 'admin');
                if ($is_secretary || $is_assistenz_active || $is_admin_edit_active):
                    $all_registered_edit_active = get_all_registered_members($pdo);
                    $sorted_members_edit_active = sort_members_by_role_hierarchy($all_registered_edit_active);
                ?>
                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">👤 Antragsteller/Ersteller:</label>
                    <select name="created_by_member_id" style="width: 100%; padding: 8px; border: 1px solid #2196f3; border-radius: 4px;">
                        <option value="">-- Nicht ändern --</option>
                        <?php foreach ($sorted_members_edit_active as $member):
                            $display_role = $member['role_display'] ?? get_role_display_name($member['role']);
                            $selected = ($member['member_id'] == $item['created_by_member_id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $member['member_id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $display_role . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 4px; color: #666;">
                        Aktuell: <?php echo htmlspecialchars($item['creator_first'] . ' ' . $item['creator_last']); ?>
                    </small>
                </div>
                <?php endif; ?>

                <button type="submit" style="background: #2196f3; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    💾 Speichern
                </button>
            </form>
        </details>
        <?php endif; ?>

        <!-- Diskussionsbeiträge aus Vorbereitung -->
        <div style="margin-top: 12px;">
            <h4 style="font-size: 14px; color: #666; margin-bottom: 8px;">💬 Diskussionsbeiträge (Vorbereitung)</h4>
            <?php
            $prep_comments = get_item_comments($pdo, $item['item_id']);
            if (!empty($prep_comments)):
            ?>
                <div style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 8px; max-height: 150px; overflow-y: auto;">
                    <?php foreach ($prep_comments as $comment): ?>
                        <?php render_comment_line($comment, 'full'); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="color: #999; font-size: 12px;">Keine Kommentare aus Vorbereitung</div>
            <?php endif; ?>
        </div>
        
        <!-- LIVE-KOMMENTARE (immer sichtbar, aber Formular nur bei aktivem TOP) -->
        <?php if ($item['top_number'] != 999): ?>
            <div id="live-comments-container-<?php echo $item['item_id']; ?>"
                 style="margin-top: 12px; padding: 12px; background: #ffebee; border: 2px solid #f44336; border-radius: 6px;">
                <h4 style="color: #c62828; margin-bottom: 8px;">💬 Live-Kommentare (während Sitzung)</h4>

                <!-- Live-Kommentare-Anzeige -->
                <div id="live-comments-<?php echo $item['item_id']; ?>" style="background: white; border: 1px solid #f44336; border-radius: 4px; padding: 8px; margin-bottom: 10px; max-height: 120px; overflow-y: auto;">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT alc.*, m.first_name, m.last_name
                        FROM svagenda_live_comments alc
                        JOIN svmembers m ON alc.member_id = m.member_id
                        WHERE alc.item_id = ?
                        ORDER BY alc.created_at ASC
                    ");
                    $stmt->execute([$item['item_id']]);
                    $live_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($live_comments)) {
                        foreach ($live_comments as $lc) {
                            render_comment_line($lc, 'time');
                        }
                    } else {
                        echo '<div style="color: #999; font-size: 12px;">Noch keine Kommentare</div>';
                    }
                    ?>
                </div>

                <!-- Formular für neuen Live-Kommentar (nur bei aktivem TOP) -->
                <?php if ($is_active): ?>
                <form method="POST" action="" enctype="multipart/form-data" class="live-comment-form">
                    <input type="hidden" name="add_live_comment" value="1">
                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">

                    <div style="flex: 1;">
                        <textarea name="comment_text"
                                  rows="2"
                                  placeholder="Ihr Beitrag zur laufenden Diskussion..."
                                  style="width: 100%; padding: 6px; border: 1px solid #f44336; border-radius: 4px; font-size: 13px;"
                                  required></textarea>
                    </div>

                    <!-- Dateianhang Upload (Akkordion) -->
                    <div style="margin: 8px 0;">
                        <div onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';"
                             style="cursor: pointer; padding: 5px 8px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 3px; font-size: 10px; color: #6c757d; user-select: none;">
                            📎 Dateianhang (optional) – klicken zum Öffnen
                        </div>
                        <div style="display: none; margin-top: 6px; padding: 8px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 3px;">
                            <input type="file" name="attachment"
                                   style="width: 100%; padding: 4px; border: 1px solid #ccc; border-radius: 3px; background: white; font-size: 11px; margin-bottom: 4px;">
                            <small style="display: block; margin-bottom: 6px; font-size: 10px; color: #999;">
                                Max. 20 MB, erlaubte Formate: PDF, DOC(X), XLS(X), PPT(X), TXT, JPG, PNG, ZIP
                            </small>

                            <!-- Lösch-Optionen -->
                            <div style="padding: 6px; background: white; border: 1px solid #dee2e6; border-radius: 3px;">
                                <label style="display: block; font-size: 10px; color: #666; font-weight: 600; margin-bottom: 4px;">Datei behandeln:</label>
                                <div style="display: flex; flex-direction: column; gap: 3px;">
                                    <label style="font-size: 10px; cursor: pointer;">
                                        <input type="radio" name="attachment_deletion_option" value="after_meeting" checked style="margin-right: 3px;">
                                        Nach der Sitzung löschen (Standard)
                                    </label>
                                    <label style="font-size: 10px; cursor: pointer;">
                                        <input type="radio" name="attachment_deletion_option" value="after_approval" style="margin-right: 3px;">
                                        Wenn Protokoll genehmigt löschen
                                    </label>
                                    <label style="font-size: 10px; cursor: pointer;">
                                        <input type="radio" name="attachment_deletion_option" value="manual" style="margin-right: 3px;">
                                        Durch Admin löschen
                                    </label>
                                    <label style="font-size: 10px; cursor: pointer;">
                                        <input type="radio" name="attachment_deletion_option" value="include_in_protocol" style="margin-right: 3px;">
                                        Ins Protokoll aufnehmen <span style="color: #999; font-size: 9px;">(keine pers. Daten)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" style="background: #f44336; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
                        💬 Senden
                    </button>
                </form>
                <?php endif; ?>

                <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.6); border-radius: 4px; font-size: 12px; color: #666; font-style: italic;">
                    ℹ️ Kommentare in diesem Feld bleiben bis zur Protokollgenehmigung sichtbar und werden dann verworfen
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($item['top_number'] != 999): ?>
            <?php
            // Prüfen ob kollaborativer Protokoll-Modus aktiv ist
            $is_collaborative = ($meeting['collaborative_protocol'] == 1);

            // WICHTIG: Im kollaborativen Modus NUR beim aktiven TOP schreiben!
            // Das verhindert Konflikte und fokussiert die Diskussion
            if ($is_collaborative) {
                $can_edit_protocol = $is_active; // Nur aktiver TOP
            } else {
                $can_edit_protocol = $is_secretary; // Klassisch: nur Sekretär
            }
            ?>

            <?php if ($can_edit_protocol): ?>
                <!-- PROTOKOLL-FORMULAR (klassisch: nur Sekretär | kollaborativ: alle Teilnehmer) -->
                <div style="margin-top: 15px; padding: 12px; background: <?php echo $is_collaborative ? '#e8f5e9' : '#f0f7ff'; ?>; border: 2px solid <?php echo $is_collaborative ? '#4caf50' : '#2196f3'; ?>; border-radius: 6px;">
                    <h4 style="color: <?php echo $is_collaborative ? '#2e7d32' : '#1976d2'; ?>; margin-bottom: 10px;">
                        📝 Protokoll
                        <?php if ($is_collaborative): ?>
                            <span style="font-size: 12px; color: #666; font-weight: normal;">🤝 Kollaborativ-Modus</span>
                        <?php endif; ?>
                    </h4>

                    <?php if ($is_collaborative): ?>
                        <!-- KOLLABORATIVER MODUS: Master-Slave Queue-System -->

                        <?php if ($is_secretary): ?>
                            <!-- PROTOKOLLFÜHRUNG: 2 Felder (Hauptsystem + Fortsetzung) -->
                            <div class="form-group" style="background: #e8f5e9; padding: 12px; border-radius: 8px; border: 2px solid #4caf50;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <label style="font-weight: 600; color: #2e7d32;">
                                        📝 Protokoll (Hauptsystem)
                                    </label>
                                    <div id="queue-display-<?php echo $item['item_id']; ?>" style="font-size: 11px; padding: 4px 8px; background: #fff3cd; border-radius: 4px; display: none;">
                                        <!-- Queue-Anzeige wird per JavaScript gefüllt -->
                                    </div>
                                </div>

                                <textarea id="protocol-main-<?php echo $item['item_id']; ?>"
                                          class="collab-protocol-main"
                                          data-item-id="<?php echo $item['item_id']; ?>"
                                          data-is-secretary="1"
                                          rows="6"
                                          placeholder="Aktueller Protokollstand (alle sehen diesen Text)"
                                          style="width: 100%; padding: 8px; border: 2px solid #4caf50; border-radius: 4px; background: white;"><?php echo htmlspecialchars($item['protocol_notes'] ?? ''); ?></textarea>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px; font-size: 11px; color: #666;">
                                    <span id="collab-status-<?php echo $item['item_id']; ?>">● Bereit</span>
                                    <span id="collab-last-saved-<?php echo $item['item_id']; ?>"></span>
                                </div>
                            </div>

                            <!-- FORTSETZUNGSFELD (nur Protokollführung) -->
                            <div class="form-group" style="background: #e3f2fd; padding: 12px; border-radius: 8px; border: 2px solid #2196f3; margin-top: 10px;">
                                <label style="font-weight: 600; color: #1976d2;">
                                    ✍️ Fortsetzungsfeld (priorisiert)
                                </label>
                                <small style="display: block; margin-bottom: 8px; color: #666; font-size: 11px;">
                                    Text hier wird direkt ans Hauptsystem angehängt (nach 2s Pause). Feld wird dann geleert.
                                </small>

                                <textarea id="protocol-append-<?php echo $item['item_id']; ?>"
                                          class="collab-protocol-append"
                                          data-item-id="<?php echo $item['item_id']; ?>"
                                          rows="3"
                                          placeholder="Neuen Text hier eingeben..."
                                          style="width: 100%; padding: 8px; border: 2px solid #2196f3; border-radius: 4px; background: white;"></textarea>

                                <div style="margin-top: 5px; font-size: 11px; color: #666;">
                                    <span id="append-status-<?php echo $item['item_id']; ?>"></span>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- NORMALE USER: 1 Feld (über Queue) -->
                            <div class="form-group" style="background: #fff8e1; padding: 12px; border-radius: 8px; border: 2px solid #ffc107;">
                                <label style="font-weight: 600; color: #f57c00;">
                                    📝 Protokoll (schreibt an Protokollführung)
                                </label>
                                <small style="display: block; margin-bottom: 8px; color: #666; font-size: 11px;">
                                    Änderungen werden in Queue eingereiht und chronologisch verarbeitet
                                </small>

                                <textarea id="protocol-main-<?php echo $item['item_id']; ?>"
                                          class="collab-protocol-main"
                                          data-item-id="<?php echo $item['item_id']; ?>"
                                          data-is-secretary="0"
                                          rows="6"
                                          placeholder="Protokollbeiträge... (werden automatisch gespeichert)"
                                          style="width: 100%; padding: 8px; border: 2px solid #ffc107; border-radius: 4px; background: white;"><?php echo htmlspecialchars($item['protocol_notes'] ?? ''); ?></textarea>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px; font-size: 11px; color: #666;">
                                    <span id="collab-status-<?php echo $item['item_id']; ?>">● Bereit</span>
                                    <span id="collab-last-saved-<?php echo $item['item_id']; ?>"></span>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- KLASSISCHER MODUS: Nur Sekretär, manuelles Speichern -->
                        <form method="POST" action="">
                            <input type="hidden" name="save_protocol" value="1">
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">

                            <div class="form-group">
                                <label style="font-weight: 600;">Protokollnotizen:</label>
                                <textarea name="protocol_text"
                                          rows="5"
                                          placeholder="Notizen zu diesem TOP..."
                                          style="width: 100%; padding: 8px; border: 1px solid #2196f3; border-radius: 4px;"><?php echo htmlspecialchars($item['protocol_notes'] ?? ''); ?></textarea>
                            </div>

                            <?php if ($is_secretary): ?>
                            <!-- Abstimmungsfelder und ToDo nur für Sekretär im klassischen Modus -->
                            <?php
                            // Abstimmungsfelder nur bei Antrag/Beschluss
                            if ($item['category'] === 'antrag_beschluss') {
                                render_voting_fields($item['item_id'], $item);
                            }

                            // ToDo-Vergabe (für Protokollant) - INNERHALB des Formulars!
                            render_todo_creation_form($pdo, $item, $current_meeting_id, $is_secretary, 'active', $participants);
                            ?>
                            <?php endif; ?>

                            <button type="submit" style="background: #2196f3; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; margin-top: 10px;">
                                💾 Protokoll speichern
                            </button>
                        </form>
                    <?php endif; // Ende klassischer Modus ?>

                <?php
                // Wiedervorlage-Feature (nur für Sekretär)
                if ($is_secretary && $item['top_number'] != 0 && $item['top_number'] != 99):
                    // Zukünftige Meetings laden
                    $stmt_future = $pdo->prepare("
                        SELECT meeting_id, meeting_name, meeting_date, location
                        FROM svmeetings
                        WHERE meeting_id != ? 
                        AND (status = 'preparation' OR meeting_date > ?)
                        ORDER BY meeting_date ASC
                        LIMIT 20
                    ");
                    $stmt_future->execute([$current_meeting_id, $meeting['meeting_date']]);
                    $future_meetings = $stmt_future->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($future_meetings)):
                ?>
                    <form method="POST" action="" style="margin-top: 15px; padding: 12px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 5px;">
                        <input type="hidden" name="save_resubmit" value="1">
                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                        
                        <div style="font-weight: 600; color: #1976d2; margin-bottom: 10px;">
                            🔄 Wiedervorlage für spätere Sitzung
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="font-size: 13px;">Sitzung auswählen:</label>
                            <select name="resubmit_meeting_id" 
                                    style="width: 100%; padding: 6px; border: 1px solid #90caf9; border-radius: 4px; font-size: 13px;">
                                <option value="">-- keine --</option>
                                <?php foreach ($future_meetings as $fm): ?>
                                    <option value="<?php echo $fm['meeting_id']; ?>">
                                        <?php 
                                        echo date('d.m.Y', strtotime($fm['meeting_date'])) . ' - ';
                                        echo htmlspecialchars($fm['meeting_name']); 
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px;">
                                <input type="checkbox" 
                                       name="resubmit_confidential" 
                                       value="1"
                                       <?php echo $item['is_confidential'] ? 'checked' : ''; ?>
                                       style="width: auto;">
                                <span>🔒 Als vertraulichen TOP anlegen</span>
                            </label>
                        </div>
                        
                        <button type="submit" style="background: #2196f3; color: white; padding: 6px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">
                            🔄 Wiedervorlage speichern
                        </button>
                        
                        <div style="font-size: 11px; color: #666; margin-top: 8px;">
                            ℹ️ Der TOP wird mit "Wiedervorlage: [Titel]" in der gewählten Sitzung angelegt
                        </div>
                    </form>
                <?php 
                    endif;
                endif;
                ?>
                
            </div>
        <?php else: ?>
            <!-- Protokoll-Anzeige für andere Teilnehmer oder nicht-aktive TOPs (Read-only) -->
            <div style="margin-top: 15px; padding: 10px; background: <?php echo $is_collaborative ? '#f1f8e9' : '#f0f7ff'; ?>; border-left: 4px solid <?php echo $is_collaborative ? '#8bc34a' : '#2196f3'; ?>; border-radius: 4px;">
                <strong style="color: <?php echo $is_collaborative ? '#558b2f' : '#1976d2'; ?>;">📝 Protokoll:</strong>
                <?php if ($is_collaborative && !$is_active): ?>
                    <span style="font-size: 11px; color: #666; font-style: italic;">
                        (Nur bei aktivem TOP editierbar)
                    </span>
                <?php endif; ?>
                <br>
                <div id="protocol-display-<?php echo $item['item_id']; ?>" style="margin-top: 6px; color: #333; font-size: 14px;">
                    <?php echo nl2br(linkify_text($item['protocol_notes'] ?? 'Noch kein Protokolleintrag...')); ?>
                </div>
                <div id="vote-display-<?php echo $item['item_id']; ?>" style="margin-top: 8px;">
                    <?php render_voting_result($item); ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        
    </div>
<?php endforeach; ?>

<?php if ($can_edit_meeting): ?>
    <!-- Sitzung beenden Button -->
    <div style="margin-top: 20px; padding: 15px; background: #fff3e0; border: 2px solid #ff9800; border-radius: 8px;">
        <h4 style="color: #e65100; margin-bottom: 10px;">⏸️ Sitzung beenden</h4>
        <p style="color: #666; margin-bottom: 10px;">
            Wenn alle TOPs behandelt wurden, kannst du die Sitzung beenden und das Protokoll erstellen.
        </p>
        <form method="POST" action="" onsubmit="return confirm('Sitzung jetzt beenden?');">
            <input type="hidden" name="end_meeting" value="1">
            <button type="submit" style="background: #ff9800; color: white; padding: 10px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 4px; cursor: pointer;">
                ⏸️ Sitzung jetzt beenden
            </button>
        </form>
    </div>
<?php endif; ?>

<script>
// AJAX: TOP aktivieren
function setActiveTop(itemId, meetingId) {
    fetch('api/meeting_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=set_active_top&item_id=${itemId}&meeting_id=${meetingId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Zum aktivierten TOP redirecten (wie beim Speichern des Protokolls)
            window.location.href = `?tab=agenda&meeting_id=${meetingId}#top-${itemId}`;
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        alert('AJAX-Fehler: ' + error);
    });
}

// AJAX: TOP deaktivieren
function unsetActiveTop(meetingId) {
    fetch('api/meeting_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=unset_active_top&meeting_id=${meetingId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Zur Agenda redirecten (bleibt an aktueller Scroll-Position wenn möglich)
            window.location.href = `?tab=agenda&meeting_id=${meetingId}`;
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        alert('AJAX-Fehler: ' + error);
    });
}

// ============================================
// LIVE-UPDATES für alle Teilnehmer
// ============================================

// Polling-Intervall: Alle 5 Sekunden Updates holen
let updateInterval = null;
const isSecretary = <?php echo $is_secretary ? 'true' : 'false'; ?>;

// Funktion: Protokoll-Updates für einen TOP holen
function updateProtocol(itemId) {
    fetch(`api/meeting_get_updates.php?item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error(`Live-Update Fehler TOP ${itemId}:`, data.error);
                return;
            }

            // Protokoll-Anzeige aktualisieren
            const protocolDiv = document.getElementById(`protocol-display-${itemId}`);
            if (protocolDiv && data.protocol_notes) {
                // Linkify direkt im JavaScript (einfache URL-Erkennung)
                let text = data.protocol_notes
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\n/g, '<br>');

                // URLs zu Links konvertieren
                text = text.replace(/\b((https?:\/\/|www\.)[^\s<]+)/gi, function(url) {
                    let href = url.startsWith('http') ? url : 'http://' + url;
                    let ext = url.split('.').pop().toLowerCase();
                    let isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
                    let isPdf = (ext === 'pdf');
                    let onclick = '';
                    if (isImage || isPdf) {
                        let type = isImage ? 'Bild' : 'PDF';
                        onclick = ` onclick="if(window.innerWidth <= 768) { alert('⚠️ ${type}-Datei wird in neuem Tab geöffnet'); }"`;
                    }
                    return `<a href="${href}" target="_blank" rel="noopener noreferrer"${onclick}>${url}</a>`;
                });

                protocolDiv.innerHTML = text;
            }

            // Aktiv-Status aktualisieren (roter Rand)
            const itemDiv = document.getElementById(`top-${itemId}`);
            if (itemDiv) {
                if (data.is_active) {
                    itemDiv.style.border = '4px solid #f44336';
                    itemDiv.style.boxShadow = '0 0 15px rgba(244,67,54,0.4)';
                } else {
                    itemDiv.style.border = '3px solid #667eea';
                    itemDiv.style.boxShadow = '';
                }
            }

            // Live-Kommentare aktualisieren (immer, unabhängig vom Aktiv-Status)
            const commentsDiv = document.getElementById(`live-comments-${itemId}`);
            if (commentsDiv && data.live_comments) {
                let html = '';
                data.live_comments.forEach(comment => {
                    const time = new Date(comment.created_at).toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});

                    // Linkify comment text
                    let commentText = comment.comment_text
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');

                    commentText = commentText.replace(/\b((https?:\/\/|www\.)[^\s<]+)/gi, function(url) {
                        let href = url.startsWith('http') ? url : 'http://' + url;
                        let ext = url.split('.').pop().toLowerCase();
                        let isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
                        let isPdf = (ext === 'pdf');
                        let onclick = '';
                        if (isImage || isPdf) {
                            let type = isImage ? 'Bild' : 'PDF';
                            onclick = ` onclick="if(window.innerWidth <= 768) { alert('⚠️ ${type}-Datei wird in neuem Tab geöffnet'); }"`;
                        }
                        return `<a href="${href}" target="_blank" rel="noopener noreferrer"${onclick}>${url}</a>`;
                    });

                    // Dateianhang anzeigen (falls vorhanden)
                    let attachmentHtml = '';
                    if (comment.attachment_filename) {
                        const originalName = comment.attachment_original_name || comment.attachment_filename;
                        const fileSize = comment.attachment_size ? ` (${(comment.attachment_size / 1024 / 1024).toFixed(2)} MB)` : '';
                        attachmentHtml = `<br><span style="margin-left: 20px;">📎 <a href="uploads/${comment.attachment_filename}" target="_blank" style="color: #2196f3; text-decoration: underline;">${originalName}</a>${fileSize}</span>`;
                    }

                    html += `<div style="padding: 4px 0; border-bottom: 1px solid #eee; font-size: 13px; line-height: 1.5;">
                        <strong style="color: #333;">${comment.first_name} ${comment.last_name}</strong> <span style="color: #999; font-size: 11px;">${time}:</span> <span style="color: #555;">${commentText}</span>${attachmentHtml}
                    </div>`;
                });
                commentsDiv.innerHTML = html || '<div style="color: #999; font-size: 12px; padding: 4px;">Noch keine Kommentare</div>';

                // Auto-Scroll zum Ende der Kommentare
                commentsDiv.scrollTop = commentsDiv.scrollHeight;
            }

            // Abstimmungsergebnis aktualisieren
            if (data.vote_result) {
                const voteDiv = document.getElementById(`vote-display-${itemId}`);
                if (voteDiv) {
                    try {
                        let voteHtml = `<strong>Abstimmung:</strong> `;
                        if (data.vote_result === 'einvernehmlich' || data.vote_result === 'einstimmig') {
                            voteHtml += data.vote_result;
                        } else {
                            voteHtml += `${data.vote_yes || 0} Ja, ${data.vote_no || 0} Nein, ${data.vote_abstain || 0} Enthaltung - ${data.vote_result}`;
                        }
                        voteDiv.innerHTML = voteHtml;
                    } catch (e) {
                        console.debug(`Konnte Abstimmung für TOP ${itemId} nicht aktualisieren:`, e);
                    }
                }
            }
        })
        .catch(error => {
            console.error(`Live-Update Netzwerk-Fehler TOP ${itemId}:`, error);
        });
}

// Funktion: Alle TOPs updaten
function updateAllProtocols() {
    const items = document.querySelectorAll('[id^="top-"]');
    items.forEach(item => {
        const match = item.id.match(/top-(\d+)/);
        if (match) {
            const itemId = match[1];
            updateProtocol(itemId);
        }
    });
}

// Auto-Scroll zum aktiven TOP
function scrollToActiveTop() {
    <?php if ($active_item_id): ?>
        const activeTop = document.getElementById('top-<?php echo $active_item_id; ?>');
        if (activeTop) {
            // Warten bis Layout berechnet ist
            setTimeout(function() {
                activeTop.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    <?php endif; ?>
}

// Live-Updates starten (nur im Status "active")
<?php if ($meeting['status'] === 'active'): ?>
    // Warten bis DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-Scroll zum aktiven TOP
            scrollToActiveTop();

            // Initial-Update
            updateAllProtocols();

            // Polling alle 5 Sekunden
            updateInterval = setInterval(updateAllProtocols, 5000);
        });
    } else {
        // DOM bereits geladen
        scrollToActiveTop();
        updateAllProtocols();
        updateInterval = setInterval(updateAllProtocols, 5000);
    }

    // Beim Verlassen der Seite stoppen
    window.addEventListener('beforeunload', function() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    });
<?php endif; ?>
</script>
