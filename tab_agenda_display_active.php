<?php
/**
 * tab_agenda_display_active.php - TOP-Anzeige während aktiver Sitzung
 * Version 2.0 - Mit allen Features
 */

// Module laden
require_once 'module_todos.php';
require_once 'module_agenda_overview.php';

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
                    <div style="display: flex; align-items: center; gap: 15px; padding: 8px; background: white; border-radius: 4px;">
                        <span style="flex: 1; font-weight: 600;">
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
                // Alle Members laden, die NICHT eingeladen sind
                $stmt_uninvited = $pdo->prepare("
                    SELECT m.member_id, m.first_name, m.last_name, m.role
                    FROM svmembers m
                    WHERE m.member_id NOT IN (
                        SELECT member_id FROM svmeeting_participants WHERE meeting_id = ?
                    )
                    AND m.is_active = 1
                    ORDER BY m.last_name, m.first_name
                ");
                $stmt_uninvited->execute([$current_meeting_id]);
                $uninvited_members = $stmt_uninvited->fetchAll();
                ?>

                <?php if (count($uninvited_members) > 0): ?>
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <select name="new_participant_id" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="">-- Teilnehmer auswählen --</option>
                                <?php foreach ($uninvited_members as $um): ?>
                                    <option value="<?php echo $um['member_id']; ?>">
                                        <?php echo htmlspecialchars($um['first_name'] . ' ' . $um['last_name'] . ' (' . $um['role'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" style="background: #4caf50; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
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
<?php else: ?>
    <?php render_readonly_participant_list($pdo, $current_meeting_id, $participants); ?>
<?php endif; ?>

<!-- NEUEN TOP HINZUFÜGEN (nur Sekretär) -->
<?php if ($is_secretary): ?>
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
        
        <div style="display: flex; gap: 10px; align-items: center;">
            <label style="display: flex; align-items: center; gap: 5px;">
                <input type="checkbox" name="is_confidential" value="1" style="width: auto;">
                <span>🔒 Vertraulich</span>
            </label>
            
            <button type="submit" style="background: #4caf50; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                ➕ TOP hinzufügen
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- TOPS ANZEIGEN -->

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
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php if ($is_active): ?>
                    <span style="background: #f44336; color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">
                        🔴 AKTIV
                    </span>
                <?php endif; ?>
                <strong style="font-size: 16px; color: #333;">
                    TOP <?php echo $item['top_number']; ?>: <?php echo htmlspecialchars($item['title']); ?>
                </strong>
                <?php render_category_badge($item['category']); ?>
                <?php if ($item['is_confidential']): ?>
                    <span class="badge" style="background: #f39c12; color: white;">🔒 Vertraulich</span>
                <?php endif; ?>
            </div>
            
            <?php if ($is_secretary && $item['top_number'] != 0 && $item['top_number'] != 99 && $item['top_number'] != 999): ?>
                <div style="display: flex; gap: 5px;">
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
                            <?php echo $item['is_confidential'] ? '🔓 Öffentlich' : '🔒 Vertraulich'; ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <?php render_proposal_display($item['proposal_text']); ?>
        
        <!-- Beschreibung -->
        <?php if ($item['description']): ?>
            <div style="color: #666; margin: 8px 0; font-size: 14px;">
                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
            </div>
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
        
        <!-- LIVE-KOMMENTARE (dynamisch ein-/ausgeblendet je nach Aktiv-Status) -->
        <?php if ($item['top_number'] != 999): ?>
            <div id="live-comments-container-<?php echo $item['item_id']; ?>"
                 style="margin-top: 12px; padding: 12px; background: #ffebee; border: 2px solid #f44336; border-radius: 6px; <?php echo !$is_active ? 'display: none;' : ''; ?>">
                <h4 style="color: #c62828; margin-bottom: 8px;">💬 Live-Kommentare (während Sitzung)</h4>

                <!-- Live-Kommentare-Anzeige -->
                <div id="live-comments-<?php echo $item['item_id']; ?>" style="background: white; border: 1px solid #f44336; border-radius: 4px; padding: 8px; margin-bottom: 10px; max-height: 120px; overflow-y: auto;">
                    <?php
                    if ($is_active) {
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
                    } else {
                        echo '<div style="color: #999; font-size: 12px;">Noch keine Kommentare</div>';
                    }
                    ?>
                </div>

                <!-- Formular für neuen Live-Kommentar -->
                <form method="POST" action="" class="live-comment-form">
                    <input type="hidden" name="add_live_comment" value="1">
                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">

                    <div style="flex: 1;">
                        <textarea name="comment_text"
                                  rows="2"
                                  placeholder="Ihr Beitrag zur laufenden Diskussion..."
                                  style="width: 100%; padding: 6px; border: 1px solid #f44336; border-radius: 4px; font-size: 13px;"
                                  required></textarea>
                    </div>

                    <button type="submit" style="background: #f44336; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
                        💬 Senden
                    </button>
                </form>

                <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.6); border-radius: 4px; font-size: 12px; color: #666; font-style: italic;">
                    ℹ️ Kommentare in diesem Feld bleiben bis zur Protokollgenehmigung sichtbar und werden dann verworfen
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($item['top_number'] != 999): ?>
            <?php if ($is_secretary): ?>
                <!-- PROTOKOLL-FORMULAR (nur für Sekretär) -->
                <div style="margin-top: 15px; padding: 12px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 6px;">
                    <h4 style="color: #1976d2; margin-bottom: 10px;">📝 Protokoll</h4>

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
                    
                    <?php 
                    // Abstimmungsfelder nur bei Antrag/Beschluss
                    if ($item['category'] === 'antrag_beschluss') {
                        render_voting_fields($item['item_id'], $item);
                    }
                    
                    // ToDo-Vergabe (für Protokollant) - INNERHALB des Formulars!
                    render_todo_creation_form($pdo, $item, $current_meeting_id, $is_secretary, 'active', $participants);
                    ?>
                    
                    <button type="submit" style="background: #2196f3; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; margin-top: 10px;">
                        💾 Protokoll speichern
                    </button>
                </form>
                
                <?php 
                // Wiedervorlage-Feature
                if ($item['top_number'] != 0 && $item['top_number'] != 99):
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
            <!-- Protokoll-Anzeige für andere Teilnehmer (Live-Update) -->
            <div style="margin-top: 15px; padding: 10px; background: #f0f7ff; border-left: 4px solid #2196f3; border-radius: 4px;">
                <strong style="color: #1976d2;">📝 Protokoll:</strong><br>
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
            Wenn alle TOPs behandelt wurden, können Sie die Sitzung beenden und das Protokoll erstellen.
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
            location.reload();
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
            location.reload();
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

            // Live-Kommentare-Container ein-/ausblenden je nach Aktiv-Status
            const commentsContainer = document.getElementById(`live-comments-container-${itemId}`);
            if (commentsContainer) {
                commentsContainer.style.display = data.is_active ? 'block' : 'none';
            }

            // Live-Kommentare aktualisieren (nur wenn TOP aktiv ist)
            if (data.is_active) {
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

                        html += `<div style="padding: 4px 0; border-bottom: 1px solid #eee; font-size: 13px; line-height: 1.5;">
                            <strong style="color: #333;">${comment.first_name} ${comment.last_name}</strong> <span style="color: #999; font-size: 11px;">${time}:</span> <span style="color: #555;">${commentText}</span>
                        </div>`;
                    });
                    commentsDiv.innerHTML = html || '<div style="color: #999; font-size: 12px; padding: 4px;">Noch keine Kommentare</div>';

                    // Auto-Scroll zum Ende der Kommentare
                    commentsDiv.scrollTop = commentsDiv.scrollHeight;
                }
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

// Live-Updates starten (nur im Status "active")
<?php if ($meeting['status'] === 'active'): ?>
    // Warten bis DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Initial-Update
            updateAllProtocols();

            // Polling alle 5 Sekunden
            updateInterval = setInterval(updateAllProtocols, 5000);
        });
    } else {
        // DOM bereits geladen
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
