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

// Übersicht mit Bewertungs-Tabelle anzeigen (EINMALIG am Anfang)
require_once 'module_agenda_overview.php';
render_agenda_overview($agenda_items, $current_user, $current_meeting_id, $pdo);
?>

<!-- Formular zum Hinzufügen neuer TOPs -->
<?php if ($can_edit_meeting): ?>
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
<?php endif; ?>

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
        $top_display = 'TOP 0: Eröffnung / Organisatorisches';
    } elseif ($item['top_number'] == 99) {
        $top_display = 'Sonstiges';
    } else {
        $top_display = 'TOP ' . $item['top_number'];
    }
    
    // Kategorie-Daten aus globaler Definition holen
    $cat_data = get_category_data($item['category']);
    $category_display = $cat_data['icon'] . ' ' . $cat_data['label'];
    
    // Eigener Kommentar des Users
    $stmt = $pdo->prepare("
        SELECT comment_text, priority_rating, duration_estimate, created_at
        FROM agenda_comments 
        WHERE item_id = ? AND member_id = ?
    ");
    $stmt->execute([$item['item_id'], $current_user['member_id']]);
    $own_comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Alle Kommentare laden
    $stmt = $pdo->prepare("
        SELECT ac.*, m.first_name, m.last_name, m.member_id
        FROM agenda_comments ac
        JOIN members m ON ac.member_id = m.member_id
        WHERE ac.item_id = ? AND ac.comment_text != ''
        ORDER BY ac.created_at ASC
    ");
    $stmt->execute([$item['item_id']]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <div style="text-align: center; background: #e3f2fd; padding: 8px 12px; border-radius: 5px;">
                    <div style="font-size: 0.8em; color: #666;">Ø Priorität</div>
                    <div style="font-weight: bold; color: #2c5aa0; font-size: 1.2em;">
                        <?php echo $item['priority'] ? number_format($item['priority'], 1) : '-'; ?>
                    </div>
                </div>
                <div style="text-align: center; background: #e3f2fd; padding: 8px 12px; border-radius: 5px;">
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
                    <select name="category" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="information" <?php echo $item['category'] === 'information' ? 'selected' : ''; ?>>📢 Information</option>
                        <option value="diskussion" <?php echo $item['category'] === 'diskussion' ? 'selected' : ''; ?>>💬 Diskussion</option>
                        <option value="antrag_beschluss" <?php echo $item['category'] === 'antrag_beschluss' ? 'selected' : ''; ?>>📜 Antrag / Beschluss</option>
                        <option value="bericht" <?php echo $item['category'] === 'bericht' ? 'selected' : ''; ?>>📊 Bericht</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Antragstext (nur bei Antrag/Beschluss):</label>
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
        
        <!-- Kommentare -->
        <div style="margin-top: 15px;">
            <strong style="display: block; margin-bottom: 10px; color: #333;">💬 Kommentare & Diskussion:</strong>
            
            <!-- Bestehende Kommentare anzeigen -->
            <?php if (!empty($comments)): ?>
                <div style="margin-bottom: 15px; background: #f9f9f9; padding: 10px; border-radius: 5px; border-left: 4px solid #2c5aa0;">
                    <?php foreach ($comments as $comment): ?>
                        <div style="margin-bottom: 10px; padding: 8px; background: white; border-radius: 4px; border: 1px solid #e0e0e0;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px;">
                                <strong style="color: #2c5aa0;">
                                    <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                </strong>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <span style="font-size: 0.85em; color: #666;">
                                        <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>
                                    </span>
                                    <?php if ($comment['member_id'] == $current_user['member_id']): ?>
                                        <form method="POST" style="display: inline; margin: 0;">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <button type="submit" name="delete_comment" value="1" 
                                                    onclick="return confirm('Kommentar wirklich löschen?')"
                                                    style="background: #f44336; color: white; padding: 3px 8px; border: none; border-radius: 3px; cursor: pointer; font-size: 0.85em;">
                                                🗑️
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="color: #333;">
                                <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #999; font-style: italic; margin-bottom: 15px;">Noch keine Kommentare vorhanden.</p>
            <?php endif; ?>
            
            <!-- Kommentar hinzufügen -->
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
        <form method="POST" action="" onsubmit="return confirm('Sitzung jetzt starten? Danach können keine TOPs mehr hinzugefügt werden.');">
            <input type="hidden" name="start_meeting" value="1">
            <button type="submit" style="background: white; color: #4CAF50; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.2em; font-weight: bold;">
                ▶️ Sitzung starten
            </button>
        </form>
    </div>
<?php endif; ?>
