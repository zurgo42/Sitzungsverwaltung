<?php
/**
 * module_proposals.php - Antrags- und Abstimmungsfunktionen
 * 
 * Dieses Modul enthält alle Funktionen für Anträge und Abstimmungen
 * Einbindung: require_once 'module_proposals.php';
 */

/**
 * Rendert das Antragstext-Feld (nur bei Kategorie "antrag_beschluss")
 */
function render_proposal_field($item_id, $proposal_text = '', $is_new = false) {
    $prefix = $is_new ? 'new_top' : 'edit_' . $item_id . '_top';
    $name = $is_new ? 'proposal_text' : 'edit_proposal[' . $item_id . ']';
    
    // Sichtbarkeit: nur bei antrag_beschluss
    $display = $is_new ? 'display:none;' : ''; // Bei new immer hidden, wird per JS gesteuert
    
    ?>
    <div class="form-group" id="<?php echo $prefix; ?>_proposal" style="<?php echo $display; ?>">
        <label style="font-weight: 600; color: #856404;">📄 Antragstext:</label>
        <textarea name="<?php echo $name; ?>" 
                  rows="4" 
                  placeholder="Formulierung des Antrags..." 
                  style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;"><?php echo htmlspecialchars($proposal_text); ?></textarea>
        <small style="color: #666; display: block; margin-top: 4px;">
            Der Antragstext wird prominent vor der Beschreibung angezeigt.
        </small>
    </div>
    <?php
}

/**
 * Zeigt den Antragstext an (falls vorhanden)
 */
function render_proposal_display($proposal_text) {
    if (empty($proposal_text)) return;
    ?>
    <div style="margin: 10px 0; padding: 12px; background: #fffbf0; border-left: 4px solid #ffc107; border-radius: 4px;">
        <strong style="color: #856404; display: block; margin-bottom: 6px;">📄 Antragstext:</strong>
        <div style="color: #333; line-height: 1.6;">
            <?php echo nl2br(htmlspecialchars($proposal_text)); ?>
        </div>
    </div>
    <?php
}

/**
 * Rendert Abstimmungsfelder im Protokoll (nur für Sekretär)
 */
function render_voting_fields($item_id, $item) {
    ?>
    <style>
        .voting-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .voting-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .voting-grid > div {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .voting-grid label {
                flex: 0 0 auto;
                margin-bottom: 0 !important;
            }
            .voting-grid input[type="number"] {
                flex: 0 0 80px;
                max-width: 80px;
                padding: 4px !important;
            }
        }
    </style>
    <div style="margin-top: 15px; padding: 12px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 6px;">
        <h5 style="margin: 0 0 10px 0; color: #1976d2; font-size: 14px;">🗳️ Abstimmung erfassen</h5>

        <div class="voting-grid">
            <div>
                <label style="font-size: 12px; font-weight: 600; color: #666; display: block; margin-bottom: 4px;">
                    ✅ Ja-Stimmen:
                </label>
                <input type="number" 
                       name="vote_yes[<?php echo $item_id; ?>]" 
                       value="<?php echo $item['vote_yes'] ?? ''; ?>" 
                       min="0" 
                       style="width: 100%; padding: 6px; border: 1px solid #2196f3; border-radius: 4px;">
            </div>
            <div>
                <label style="font-size: 12px; font-weight: 600; color: #666; display: block; margin-bottom: 4px;">
                    ❌ Nein-Stimmen:
                </label>
                <input type="number" 
                       name="vote_no[<?php echo $item_id; ?>]" 
                       value="<?php echo $item['vote_no'] ?? ''; ?>" 
                       min="0" 
                       style="width: 100%; padding: 6px; border: 1px solid #2196f3; border-radius: 4px;">
            </div>
            <div>
                <label style="font-size: 12px; font-weight: 600; color: #666; display: block; margin-bottom: 4px;">
                    🤷 Enthaltungen:
                </label>
                <input type="number" 
                       name="vote_abstain[<?php echo $item_id; ?>]" 
                       value="<?php echo $item['vote_abstain'] ?? ''; ?>" 
                       min="0" 
                       style="width: 100%; padding: 6px; border: 1px solid #2196f3; border-radius: 4px;">
            </div>
        </div>
        
        <div>
            <label style="font-size: 12px; font-weight: 600; color: #666; display: block; margin-bottom: 4px;">
                📊 Ergebnis:
            </label>
            <select name="vote_result[<?php echo $item_id; ?>]" 
                    style="width: 100%; padding: 6px; border: 1px solid #2196f3; border-radius: 4px;">
                <option value="">-- Bitte wählen --</option>
                <option value="einvernehmlich" <?php echo ($item['vote_result'] ?? '') === 'einvernehmlich' ? 'selected' : ''; ?>>
                    🤝 Einvernehmlich
                </option>
                <option value="einstimmig" <?php echo ($item['vote_result'] ?? '') === 'einstimmig' ? 'selected' : ''; ?>>
                    💯 Einstimmig
                </option>
                <option value="angenommen" <?php echo ($item['vote_result'] ?? '') === 'angenommen' ? 'selected' : ''; ?>>
                    ✅ Angenommen
                </option>
                <option value="abgelehnt" <?php echo ($item['vote_result'] ?? '') === 'abgelehnt' ? 'selected' : ''; ?>>
                    ❌ Abgelehnt
                </option>
            </select>
        </div>
        
        <small style="display: block; margin-top: 8px; color: #666; font-size: 11px;">
            💡 Das Abstimmungsergebnis wird automatisch formatiert ans Protokoll angehängt
        </small>
    </div>
    <?php
}

/**
 * Zeigt gespeichertes Abstimmungsergebnis an
 */
function render_voting_result($item) {
    // Nur anzeigen wenn Daten vorhanden
    if (empty($item['vote_result']) && empty($item['vote_yes'])) {
        return;
    }
    
    ?>
    <div style="margin-top: 10px; padding: 10px; background: #e8f5e9; border: 2px solid #4caf50; border-radius: 4px;">
        <strong style="color: #2e7d32; display: block; margin-bottom: 6px;">🗳️ Abstimmungsergebnis:</strong>
        
        <?php if (in_array($item['vote_result'], ['einvernehmlich', 'einstimmig'])): ?>
            <div style="color: #2e7d32; font-weight: 600;">
                <?php echo ucfirst($item['vote_result']); ?>
            </div>
        <?php else: ?>
            <div style="display: flex; gap: 15px; margin-bottom: 6px;">
                <span>✅ Ja: <strong><?php echo $item['vote_yes'] ?? 0; ?></strong></span>
                <span>❌ Nein: <strong><?php echo $item['vote_no'] ?? 0; ?></strong></span>
                <span>🤷 Enthaltung: <strong><?php echo $item['vote_abstain'] ?? 0; ?></strong></span>
            </div>
            <div style="color: <?php echo $item['vote_result'] === 'angenommen' ? '#2e7d32' : '#c62828'; ?>; font-weight: 600;">
                <?php echo ucfirst($item['vote_result'] ?? 'Noch nicht abgestimmt'); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
