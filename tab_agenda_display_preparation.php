<?php
/**
 * tab_agenda_display_preparation.php - Anzeige im Status "preparation"
 * Version 3.0 - 12.11.2025
 *
 * ÄNDERUNGEN:
 * - Priorität/Dauer wurden in die Übersicht verschoben
 * - Einzelne Speichern-Buttons pro TOP für Kommentare
 * - Kein globaler "Alle Eingaben speichern" Button mehr
 * - Übersicht nur noch einmal (am Anfang)
 *
 * GitHub-Übung: Lerne den Git-Workflow
 */

// Module laden
require_once 'module_agenda_overview.php';
require_once 'module_comments.php';

// Alle aktuellen und zukünftigen Abwesenheiten laden
// Nutzt Adapter-kompatible Funktion statt direktem JOIN auf svmembers
$all_absences = get_absences_with_names($pdo, "a.end_date >= CURDATE()");

// is_current Flag hinzufügen
foreach ($all_absences as &$abs) {
    $abs['is_current'] = (strtotime('today') >= strtotime($abs['start_date']) &&
                          strtotime('today') <= strtotime($abs['end_date'])) ? 1 : 0;
}
unset($abs); // Referenz löschen um Seiteneffekte bei späteren foreach zu vermeiden

// Abwesenheitsanzeige (nur wenn Abwesenheiten vorhanden)
if (!empty($all_absences)) {
    $absence_items = [];
    foreach ($all_absences as $abs) {
        $name = $abs['first_name'] . ' ' . $abs['last_name'];
        $dates = date('d.m.', strtotime($abs['start_date'])) . '-' . date('d.m.', strtotime($abs['end_date']));
        $vertr = $abs['sub_first_name'] ? ' Vertr.: ' . $abs['sub_first_name'] . ' ' . $abs['sub_last_name'] : '';

        // Aktuelle Abwesenheiten in rot
        $text = $name . ' (' . $dates . ')' . $vertr;
        if ($abs['is_current']) {
            $absence_items[] = '<span style="color: #d32f2f; font-weight: 600;">' . $text . '</span>';
        } else {
            $absence_items[] = $text;
        }
    }
    ?>
    <div style="background: #f9f9f9; padding: 8px 12px; margin-bottom: 15px; border-radius: 4px; font-size: 13px; color: #666;">
        <strong style="color: #333;">🏖️ Abwesenheiten:</strong>
        <?php echo implode(' • ', $absence_items); ?>
        <a href="?tab=vertretung" style="margin-left: 10px; color: #2196f3; text-decoration: none; font-size: 12px;">→ Details</a>
    </div>
    <?php
}

// Übersicht mit Bewertungs-Tabelle anzeigen (EINMALIG am Anfang)
render_agenda_overview($agenda_items, $current_user, $current_meeting_id, $pdo);

// Prüfen ob User Admin ist
$is_admin = $current_user['is_admin'] == 1;

// Abwesenheiten für alle Mitglieder laden (für Teilnehmerverwaltung)
// Nutzt Adapter-kompatible Funktion statt direktem JOIN auf svmembers
$all_absences_raw = get_absences_with_names($pdo, "a.end_date >= CURDATE()");

// Nach member_id gruppieren
$member_absences = [];
foreach ($all_absences_raw as $abs) {
    if (!isset($member_absences[$abs['member_id']])) {
        $member_absences[$abs['member_id']] = [];
    }
    $member_absences[$abs['member_id']][] = $abs;
}

// Prüfen ob Antragsschluss überschritten ist
$submission_deadline_passed = false;
$submission_deadline_date = null;
if (!empty($meeting['submission_deadline'])) {
    $submission_deadline_date = $meeting['submission_deadline'];
    $submission_deadline_passed = (strtotime($submission_deadline_date) < time());
}

// Berechtigung zum Hinzufügen neuer TOPs prüfen
// Vor Antragsschluss: Alle Teilnehmer
// Nach Antragsschluss: Nur Protokollant und Admins (während status=preparation)
// Während der Sitzung (status=active): Nur Protokollant
$can_add_tops = false;
if (!$submission_deadline_passed) {
    // Vor Antragsschluss: Alle Teilnehmer dürfen TOPs hinzufügen
    $can_add_tops = true;
} else {
    // Nach Antragsschluss: Nur Protokollant und Admins
    $can_add_tops = ($is_secretary || $is_admin);
}
?>

<!-- Teilnehmer hinzufügen (nur für Admins) -->
<?php if ($is_admin): ?>
    <details style="margin: 20px 0; border: 2px solid #2196f3; border-radius: 8px; overflow: hidden;">
        <summary style="padding: 15px; background: #2196f3; color: white; font-size: 16px; font-weight: 600; cursor: pointer; list-style: none;">
            <span style="display: inline-block; width: 20px;">▶</span> 👥 Teilnehmerverwaltung (nur für Admins)
        </summary>

        <div style="padding: 15px; background: #f0f7ff;">
            <!-- Bestehende Teilnehmer -->
            <h4 style="margin: 0 0 10px 0; color: #1976d2;">Eingeladene Teilnehmer</h4>
            <div style="margin-bottom: 20px; padding: 10px; background: white; border-radius: 4px;">
                <?php
                // Teilnehmer aus DB laden (ohne JOIN svmembers)
                $stmt_participants = $pdo->prepare("
                    SELECT member_id
                    FROM svmeeting_participants
                    WHERE meeting_id = ?
                ");
                $stmt_participants->execute([$current_meeting_id]);
                $participant_ids = $stmt_participants->fetchAll(PDO::FETCH_COLUMN);

                // Member-Daten aus globalem Array holen und sortieren
                $current_participants = [];
                foreach ($participant_ids as $pid) {
                    // Verwende get_member_from_cache wenn verfügbar (index.php Kontext), sonst get_member_by_id
                    if (function_exists('get_member_from_cache')) {
                        $member = get_member_from_cache($pid);
                    } else {
                        $member = get_member_by_id($pdo, $pid);
                    }
                    if ($member) {
                        $current_participants[] = $member;
                    }
                }
                // Nach Nachname sortieren
                usort($current_participants, function($a, $b) {
                    return strcmp($a['last_name'], $b['last_name']);
                });
                ?>

                <?php if (count($current_participants) > 0): ?>
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($current_participants as $cp):
                            $has_absence = isset($member_absences[$cp['member_id']]);
                            $style = $has_absence ? 'background: #fff3cd; padding: 5px; border-left: 3px solid #ffc107; margin-bottom: 5px;' : '';
                        ?>
                            <li style="<?php echo $style; ?>">
                                <?php echo htmlspecialchars($cp['first_name'] . ' ' . $cp['last_name'] . ' (' . $cp['role'] . ')'); ?>
                                <?php if ($has_absence): ?>
                                    <br><small style="color: #856404;">
                                        <?php foreach ($member_absences[$cp['member_id']] as $abs): ?>
                                            🏖️ <?php echo date('d.m.', strtotime($abs['start_date'])); ?> - <?php echo date('d.m.', strtotime($abs['end_date'])); ?>
                                            <?php if ($abs['substitute_member_id']): ?>
                                                (Vertr.: <?php echo htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']); ?>)
                                            <?php endif; ?>
                                            <br>
                                        <?php endforeach; ?>
                                    </small>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="margin: 0; color: #666; font-style: italic;">Noch keine Teilnehmer eingeladen.</p>
                <?php endif; ?>
            </div>

            <!-- Nicht eingeladene Teilnehmer hinzufügen -->
            <h4 style="margin: 0 0 10px 0; color: #1976d2;">➕ Nicht eingeladene Teilnehmer hinzufügen</h4>
            <form method="POST" action="">
                <input type="hidden" name="add_uninvited_participant" value="1">

                <?php
                // Alle Members laden, die NICHT eingeladen sind
                // Eingeladene IDs holen
                $stmt_invited = $pdo->prepare("SELECT member_id FROM svmeeting_participants WHERE meeting_id = ?");
                $stmt_invited->execute([$current_meeting_id]);
                $invited_ids = $stmt_invited->fetchAll(PDO::FETCH_COLUMN);

                // Aus Members-Array filtern (verwendet $all_members aus tab_agenda.php)
                $uninvited_members = [];
                if (isset($all_members) && is_array($all_members)) {
                    foreach ($all_members as $member) {
                        if (!in_array($member['member_id'], $invited_ids) && $member['is_active']) {
                            $uninvited_members[] = $member;
                        }
                    }
                } else {
                    // Fallback: Direkt aus DB laden
                    $uninvited_members = get_all_members($pdo);
                    $uninvited_members = array_filter($uninvited_members, function($m) use ($invited_ids) {
                        return !in_array($m['member_id'], $invited_ids) && $m['is_active'];
                    });
                }
                // Nach Nachname sortieren
                usort($uninvited_members, function($a, $b) {
                    return strcmp($a['last_name'], $b['last_name']);
                });
                ?>

                <?php if (count($uninvited_members) > 0): ?>
                    <style>
                        .add-participant-container {
                            display: flex;
                            gap: 10px;
                            align-items: flex-end;
                        }
                        @media (max-width: 768px) {
                            .add-participant-container {
                                flex-direction: column;
                                align-items: stretch;
                                gap: 8px;
                            }
                            .add-participant-select {
                                width: 100% !important;
                            }
                            .add-participant-button {
                                width: 100%;
                                padding: 10px !important;
                            }
                        }
                    </style>
                    <div class="add-participant-container">
                        <div style="flex: 3;">
                            <select name="new_participant_id" required class="add-participant-select" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="">-- Teilnehmer auswählen --</option>
                                <?php foreach ($uninvited_members as $um):
                                    $has_absence_um = isset($member_absences[$um['member_id']]);
                                    $absence_info = '';
                                    if ($has_absence_um) {
                                        foreach ($member_absences[$um['member_id']] as $abs) {
                                            $absence_info .= ' [Abwesend: ' . date('d.m.', strtotime($abs['start_date'])) . '-' . date('d.m.', strtotime($abs['end_date'])) . ']';
                                        }
                                    }
                                    // Display-Name verwenden wenn vorhanden, sonst konvertieren
                                    $display_role = isset($um['role_display']) ? $um['role_display'] : get_role_display_name($um['role']);
                                ?>
                                    <option value="<?php echo $um['member_id']; ?>">
                                        <?php echo htmlspecialchars($um['first_name'] . ' ' . $um['last_name'] . ' (' . $display_role . ')' . $absence_info); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="add-participant-button" style="background: #4caf50; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
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
    </details>

    <style>
    details[open] summary span {
        transform: rotate(90deg);
        display: inline-block;
    }
    </style>
<?php endif; ?>

<!-- Formular zum Hinzufügen neuer TOPs -->
<?php if ($can_add_tops): ?>
    <details style="margin: 20px 0; border: 2px solid #4caf50; border-radius: 8px; overflow: hidden;">
        <summary style="padding: 15px; background: #4caf50; color: white; font-size: 16px; font-weight: 600; cursor: pointer; list-style: none;">
            <span style="display: inline-block; width: 20px;">▶</span> ➕ Neuen Tagesordnungspunkt anlegen
            <?php if ($submission_deadline_date): ?>
                (Antragsschluss: <?php echo date('d.m.Y H:i', strtotime($submission_deadline_date)); ?> Uhr)
            <?php endif; ?>
        </summary>
    
    <div style="padding: 15px; background: #f1f8e9;">
        <style>
            .top-form-group {
                margin-bottom: 15px;
            }
            .top-form-group label {
                display: block;
                margin-bottom: 5px;
            }
            .priority-duration-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            .top-submit-button {
                width: 100%;
                background: #4caf50;
                color: white;
                padding: 12px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 600;
                font-size: 16px;
            }
            @media (max-width: 768px) {
                .priority-duration-grid {
                    grid-template-columns: 1fr;
                    gap: 0;
                }
                .top-submit-button {
                    padding: 14px 20px;
                    font-size: 15px;
                }
            }
        </style>
        <form method="POST" action="">
            <input type="hidden" name="add_agenda_item" value="1">

            <div class="form-group top-form-group">
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
            // Priorität/Dauer nur für Führungsteam
            $is_leadership = in_array(strtolower($current_user['role'] ?? ''), ['vorstand', 'gf', 'assistenz', 'fuehrungsteam']);
            if ($is_leadership):
            ?>
            <div class="priority-duration-grid">
                <div class="form-group top-form-group">
                    <label style="font-weight: 600;">Priorität (1-10):</label>
                    <input type="number" name="priority" min="1" max="10" step="0.1" value="5" required>
                </div>

                <div class="form-group top-form-group">
                    <label style="font-weight: 600;">Geschätzte Dauer (Min.):</label>
                    <input type="number" name="duration" min="1" value="10" required>
                </div>
            </div>
            <?php else: ?>
            <!-- Hidden fields mit Standardwerten für nicht-Führungsteam -->
            <input type="hidden" name="priority" value="5">
            <input type="hidden" name="duration" value="10">
            <?php endif; ?>
            
            <div class="form-group top-form-group">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="is_confidential" value="1" style="margin-right: 8px;">
                    🔒 Vertraulich (nur für berechtigte Teilnehmer)
                </label>
            </div>

            <div class="form-group top-form-group">
                <button type="submit" class="top-submit-button">
                    ✅ TOP hinzufügen
                </button>
            </div>
        </form>
    </div>
</details>
<?php else: ?>
    <!-- Hinweis: Antragsschluss überschritten (dezent mit hellrosa Hintergrund) -->
    <p style="margin: 20px 0; padding: 10px 15px; background: #ffe8ec; border-radius: 4px; color: #666; font-size: 14px;">
        Antragsschluss war am <?php echo date('d.m.Y', strtotime($submission_deadline_date)); ?> um <?php echo date('H:i', strtotime($submission_deadline_date)); ?> Uhr
    </p>
<?php endif; ?>

<style>
details[open] summary span {
    transform: rotate(90deg);
    display: inline-block;
}
</style>

<!-- TOPs anzeigen -->
<?php 
// Berechtigung für vertrauliche TOPs prüfen
$can_see_confidential = (
    $current_user['is_admin'] == 1 ||
    $current_user['is_confidential'] == 1 ||
    in_array($current_user['role'], ['vorstand', 'gf']) ||
    $is_secretary ||
    $is_chairman
);

foreach ($agenda_items as $item):
    // Vertrauliche TOPs nur für berechtigte User
    if ($item['is_confidential'] && !$can_see_confidential) {
        continue;
    }

    // TOP 999 nicht anzeigen (nur Steuerungselement)
    if ($item['top_number'] == 999) {
        continue;
    }

    // TOP-Nummer formatieren
    $top_display = '';
    if ($item['top_number'] == 0) {
        $top_display = ''; // Leer lassen für TOP 0
    } elseif ($item['top_number'] == 99) {
        $top_display = ''; // Leer lassen für TOP 99
    } else {
        $top_display = 'TOP #' . $item['top_number'];
    }
    
    // Kategorie-Daten aus globaler Definition holen
    $cat_data = get_category_data($item['category']);
    $category_display = $cat_data['icon'] . ' ' . $cat_data['label'];
    
    // Eigener Kommentar des Users
    $stmt = $pdo->prepare("
        SELECT comment_text, priority_rating, duration_estimate, created_at
        FROM svagenda_comments 
        WHERE item_id = ? AND member_id = ?
    ");
    $stmt->execute([$item['item_id'], $current_user['member_id']]);
    $own_comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Alle Kommentare laden (ohne JOIN auf svmembers)
    $stmt = $pdo->prepare("
        SELECT ac.*
        FROM svagenda_comments ac
        WHERE ac.item_id = ? AND ac.comment_text != ''
        ORDER BY ac.created_at ASC
    ");
    $stmt->execute([$item['item_id']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Member-Namen aus Array hinzufügen
    foreach ($comments as &$comment) {
        $member = get_member_from_array($comment['member_id']);
        $comment['first_name'] = $member['first_name'] ?? 'Unbekannt';
        $comment['last_name'] = $member['last_name'] ?? '';
    }
    unset($comment);
    
    // Prüfen ob User der Ersteller ist
    $is_creator = ($item['created_by_member_id'] == $current_user['member_id']);
    ?>
    
    <div id="top-<?php echo $item['item_id']; ?>" style="margin: 20px 0; padding: 15px; border: 2px solid #2c5aa0; border-radius: 8px; background: white;">
        
        <!-- TOP Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; border-bottom: 2px solid #2c5aa0; padding-bottom: 10px;">
            <div style="flex: 1;">
                <h3 style="margin: 0 0 5px 0; color: #2c5aa0;">
                    <?php echo htmlspecialchars($top_display); ?>
                    <?php if ($item['is_confidential']): ?>
                        <span style="color: #d32f2f; font-size: 0.9em;">🔒 Vertraulich</span>
                    <?php endif; ?>
                </h3>
                <div style="color: #666; font-size: 0.9em;">
                    <?php echo $category_display; ?>
                    <?php if ($item['creator_first'] && $item['creator_last']): ?>
                        | Erstellt von: <?php echo htmlspecialchars($item['creator_first'] . ' ' . $item['creator_last']); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <!-- Durchschnittswerte -->
                <div style="text-align: center; background: <?php echo $item['priority'] ? '#e3f2fd' : '#d4edda'; ?>; padding: 8px 12px; border-radius: 5px;">
                    <div style="font-size: 0.8em; color: #666;">Ø Priorität</div>
                    <div style="font-weight: bold; color: #2c5aa0; font-size: 1.2em;">
                        <?php echo $item['priority'] ? number_format($item['priority'], 1) : '-'; ?>
                    </div>
                </div>
                <div style="text-align: center; background: <?php echo $item['estimated_duration'] ? '#e3f2fd' : '#d4edda'; ?>; padding: 8px 12px; border-radius: 5px;">
                    <div style="font-size: 0.8em; color: #666;">Ø Dauer</div>
                    <div style="font-weight: bold; color: #2c5aa0; font-size: 1.2em;">
                        <?php echo $item['estimated_duration'] ? $item['estimated_duration'] . ' min' : '-'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Titel -->
        <div style="margin-bottom: 15px;">
            <strong style="display: block; margin-bottom: 5px; color: #333;">Titel:</strong>
            <div style="padding: 8px; background: #f5f5f5; border-radius: 4px;">
                <?php echo nl2br(htmlspecialchars($item['title'])); ?>
            </div>
        </div>
        
        <!-- Beschreibung -->
        <?php if ($item['description']): ?>
        <div style="margin-bottom: 15px;">
            <strong style="display: block; margin-bottom: 5px; color: #333;">Beschreibung:</strong>
            <div style="padding: 8px; background: #f5f5f5; border-radius: 4px;">
                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Antragstext bei Kategorie "Antrag" -->
        <?php if ($item['category'] === 'antrag_beschluss' && $item['proposal_text']): ?>
        <div style="margin-bottom: 15px;">
            <strong style="display: block; margin-bottom: 5px; color: #333;">📜 Antragstext:</strong>
            <div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <?php echo nl2br(htmlspecialchars($item['proposal_text'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Bearbeiten-Button (nur für Ersteller) -->
        <?php if ($is_creator): ?>
        <details style="margin-bottom: 15px; border: 1px solid #ccc; border-radius: 5px; padding: 10px; background: #fafafa;">
            <summary style="cursor: pointer; font-weight: bold; color: #555;">
                ✏️ TOP bearbeiten
            </summary>
            <form method="POST" action="" style="margin-top: 10px;">
                <input type="hidden" name="edit_agenda_item" value="1">
                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                
                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Titel:</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" 
                           required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                
                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Beschreibung:</label>
                    <textarea name="description" rows="3" 
                              style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Kategorie:</label>
                    <?php render_category_select('category', 'edit_category_' . $item['item_id'], $item['category'], 'toggleProposalField("edit_' . $item['item_id'] . '")'); ?>
                </div>

                <div style="margin-bottom: 10px;" id="edit_<?php echo $item['item_id']; ?>_proposal" style="display: <?php echo $item['category'] === 'antrag_beschluss' ? 'block' : 'none'; ?>;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">📄 Antragstext:</label>
                    <textarea name="proposal_text" rows="3"
                              style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><?php echo htmlspecialchars($item['proposal_text'] ?? ''); ?></textarea>
                </div>
                
                <?php if ($item['is_confidential']): ?>
                <div style="margin-bottom: 10px; padding: 8px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                    🔒 <strong>Vertraulich</strong> - Dieser Status kann nach Erstellung nicht mehr geändert werden
                </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" style="background: #4CAF50; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;">
                        💾 Speichern
                    </button>
                    <button type="submit" name="delete_agenda_item" value="1" 
                            onclick="return confirm('TOP wirklich löschen?')"
                            style="background: #f44336; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;">
                        🗑️ Löschen
                    </button>
                </div>
            </form>
        </details>
        <?php endif; ?>

        <!-- Kommentare & Diskussion -->
        <div style="margin-top: 15px;">
            <strong style="display: block; margin-bottom: 10px; color: #333;">💬 Kommentare & Diskussion:</strong>

            <!-- Bestehende Kommentare anzeigen (nur wenn vorhanden) -->
            <?php if (!empty($comments)): ?>
            <div style="margin-bottom: 15px; background: white; border: 1px solid #ddd; border-radius: 5px; padding: 8px;">
                <?php foreach ($comments as $comment): ?>
                    <?php render_comment_line($comment, 'full'); ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Kommentar hinzufügen (immer anzeigen) -->
            <form method="POST" action="" style="margin-top: 10px;">
                <input type="hidden" name="add_comment_preparation" value="1">
                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">

                <textarea name="comment" rows="3" placeholder="Ihr Kommentar zu diesem TOP..."
                          style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px;"></textarea>

                <button type="submit" style="background: #2c5aa0; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;">
                    💬 Kommentar hinzufügen
                </button>
            </form>
        </div>
        
    </div>
<?php endforeach; ?>

<!-- Sitzung starten Button -->
<?php if ($can_edit_meeting): ?>
    <div style="margin: 30px 0; padding: 20px; background: #4CAF50; border-radius: 8px; text-align: center;">
        <form method="POST" action="" onsubmit="return confirm('Sitzung jetzt starten? Danach kann nur der Protokollführer neue TOPs hinzufügen.');">
            <input type="hidden" name="start_meeting" value="1">
            <button type="submit" style="background: white; color: #4CAF50; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.2em; font-weight: bold;">
                ▶️ Sitzung starten
            </button>
        </form>
    </div>
<?php endif; ?>
