<?php
/**
 * tab_admin_init.php - Initialisierungs- und Grundkonfiguration
 * Hochsensibel: Nur für initiale Einrichtung und seltene Grundkonfiguration
 *
 * ZUGRIFF: Zusätzlich gesichert - erfordert Bestätigung
 */

// Prüfen ob direkter Zugriff (nicht erlaubt)
if (!isset($current_user) || !isset($pdo)) {
    die('Direkter Zugriff nicht erlaubt');
}

// Admin-Check
if (empty($current_user['is_admin'])) {
    echo '<div class="error-message">❌ Zugriff verweigert. Du hast keine Admin-Rechte.</div>';
    exit;
}

// Bestätigungs-Check für Initialisierung
$init_confirmed = isset($_SESSION['init_confirmed']) && $_SESSION['init_confirmed'] === true;

// Diese Verarbeitung erfolgt jetzt in process_admin.php VOR HTML-Output
// Hier nur noch Status prüfen

// Processing einbinden

?>

<style>
.init-warning-box {
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.init-warning-box h3 {
    color: #856404;
    margin-top: 0;
}

.init-confirm-box {
    background: white;
    border: 2px solid #dc3545;
    border-radius: 8px;
    padding: 30px;
    max-width: 600px;
    margin: 50px auto;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.init-confirm-box h2 {
    color: #dc3545;
    margin-top: 0;
}

.init-danger-list {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    padding: 15px;
    margin: 20px 0;
    font-size: 14px;
}

.init-danger-list ul {
    margin: 10px 0;
    padding-left: 20px;
}

.init-section-header {
    background: #dc3545 !important;
    color: white !important;
}

.init-section-header:hover {
    background: #c82333 !important;
}

body.dark-mode .init-warning-box {
    background: #3a2f1a;
    border-color: #ffc107;
    color: #ffc107;
}

body.dark-mode .init-confirm-box {
    background: #2d2d2d;
    border-color: #dc3545;
}

body.dark-mode .init-danger-list {
    background: #3a1f1f;
    border-color: #dc3545;
    color: #e0e0e0;
}
</style>

<h2>⚠️ Grundkonfiguration / Initialisierung</h2>

<?php if (!$init_confirmed): ?>
    <!-- Bestätigungs-Dialog -->
    <div class="init-confirm-box">
        <h2>⚠️ Sicherheitsbestätigung erforderlich</h2>

        <div class="init-danger-list">
            <strong>🚨 ACHTUNG:</strong> Dieser Bereich enthält kritische Grundeinstellungen!
            <ul>
                <li>Änderungen hier wirken sich auf das gesamte System aus</li>
                <li>Falsche Einstellungen können das System funktionsunfähig machen</li>
                <li>Löschen von Ressorts kann Anträge unbenutzbar machen</li>
                <li>Diese Einstellungen sollten nur bei Ersteinrichtung geändert werden</li>
            </ul>
        </div>

        <p style="margin: 20px 0; font-weight: 600; color: #666;">
            Bitte bestätige, dass du die kritischen Grundeinstellungen bearbeiten möchtest.
            Der Zugriff ist für 30 Minuten gültig.
        </p>

        <form method="POST" action="?tab=admin_init">
            <input type="hidden" name="confirm_init_access" value="1">
            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
                <button type="submit" class="btn-danger" style="padding: 12px 30px; font-size: 16px;">
                    ✓ Ich verstehe, Zugriff erlauben
                </button>
                <a href="?tab=admin" class="btn-secondary" style="padding: 12px 30px; font-size: 16px; display: inline-block; text-decoration: none;">
                    ← Zurück zum Admin-Bereich
                </a>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Initialisierungs-Bereich -->
    <div class="init-warning-box">
        <h3>⚠️ Grundkonfigurations-Modus aktiv</h3>
        <p style="margin: 10px 0 0 0;">
            Du kannst jetzt kritische Systemeinstellungen bearbeiten.
            Der Zugriff läuft automatisch nach 30 Minuten ab.
            <a href="?tab=admin_init&reset_init=1" style="color: #dc3545; font-weight: 600; margin-left: 20px;">
                Zugriff jetzt beenden →
            </a>
        </p>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'ressort_added'): ?>
            <div class="message">✅ Ressort erfolgreich hinzugefügt!</div>
        <?php elseif ($_GET['msg'] === 'ressort_updated'): ?>
            <div class="message">✅ Ressort erfolgreich aktualisiert!</div>
        <?php elseif ($_GET['msg'] === 'ressort_deleted'): ?>
            <div class="message">✅ Ressort erfolgreich gelöscht!</div>
        <?php elseif ($_GET['msg'] === 'terminology_saved'): ?>
            <div class="message">✅ Terminologie erfolgreich gespeichert!</div>
        <?php elseif ($_GET['msg'] === 'levels_saved'): ?>
            <div class="message">✅ aktiv-Level erfolgreich gespeichert!</div>
        <?php elseif ($_GET['msg'] === 'antragstypen_saved'): ?>
            <div class="message">✅ Antragstypen erfolgreich gespeichert!</div>
        <?php elseif ($_GET['msg'] === 'voting_rules_saved'): ?>
            <div class="message">✅ Abstimmungsregeln erfolgreich gespeichert!</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- 1. RESSORT-KONFIGURATION -->
    <div class="admin-section">
        <h3 class="admin-section-header init-section-header" onclick="toggleSection(this)">
            📁 Ressorts / Bereiche
        </h3>

        <div class="admin-section-content">
            <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                Ressorts/Bereiche für das Antragssystem definieren.
                Diese Zuordnung wird in Anträgen verwendet und sollte nach Einrichtung nicht mehr geändert werden.
            </p>

            <!-- Neue Ressort hinzufügen -->
            <details style="margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">
                <summary style="cursor: pointer; font-weight: 600; color: #2196f3;">➕ Neues Ressort hinzufügen</summary>
                <form method="POST" action="?tab=admin_init" style="margin-top: 15px;">
                    <input type="hidden" name="add_ressort" value="1">

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ressort-Name:</label>
                        <input type="text" name="ressort_name" required
                               placeholder="z.B. Finanzen, Marketing, IT"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Reihenfolge:</label>
                        <input type="number" name="reihenfolge" value="100" min="1" max="999"
                               style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Kleinere Zahlen erscheinen weiter oben
                        </small>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <input type="checkbox" name="aktiv" value="1" checked> Aktiv
                        </label>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Inaktive Ressorts werden nicht mehr angezeigt
                        </small>
                    </div>

                    <button type="submit" class="btn-primary">Ressort hinzufügen</button>
                </form>
            </details>

            <!-- Ressort-Liste -->
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 80px;">Reihenf.</th>
                        <th>Ressort-Name</th>
                        <th style="width: 100px;">Verwendung</th>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 150px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ressorts laden mit Verwendungszähler
                    $ressorts_stmt = $pdo->query("
                        SELECT r.*,
                               (SELECT COUNT(*) FROM antraege WHERE ressort1 = r.ID OR ressort2 = r.ID) as verwendung
                        FROM svressorts r
                        ORDER BY r.Reihenfolge, r.ID
                    ");
                    $all_ressorts = $ressorts_stmt->fetchAll();

                    if (empty($all_ressorts)):
                    ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                Noch keine Ressorts konfiguriert
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_ressorts as $ressort): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ressort['ID']); ?></td>
                                <td><?php echo htmlspecialchars($ressort['Reihenfolge']); ?></td>
                                <td style="font-weight: 600;">
                                    <?php echo htmlspecialchars($ressort['Ressort']); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($ressort['verwendung'] > 0): ?>
                                        <span style="color: #dc3545; font-weight: 600;">
                                            <?php echo $ressort['verwendung']; ?> ×
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($ressort['aktiv'] ?? 1) == 1): ?>
                                        <span style="color: #28a745; font-weight: 600;">✓ Aktiv</span>
                                    <?php else: ?>
                                        <span style="color: #999;">⊗ Inaktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button"
                                            onclick="openEditRessortModal(<?php echo htmlspecialchars(json_encode($ressort)); ?>)"
                                            class="btn-secondary"
                                            style="padding: 4px 10px; font-size: 12px; margin-right: 5px;">
                                        ✏️ Bearbeiten
                                    </button>
                                    <?php if ($ressort['verwendung'] == 0): ?>
                                        <form method="POST" action="?tab=admin_init" style="display: inline-block;"
                                              onsubmit="return confirm('Ressort wirklich löschen?');">
                                            <input type="hidden" name="delete_ressort" value="1">
                                            <input type="hidden" name="ressort_id" value="<?php echo $ressort['ID']; ?>">
                                            <button type="submit" class="btn-danger"
                                                    style="padding: 4px 10px; font-size: 12px; background: #dc3545;">
                                                🗑️ Löschen
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 11px;">
                                            (in Verwendung)
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bearbeitungs-Modal für Ressorts -->
    <div id="editRessortModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 8px; padding: 30px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <h3 style="margin-top: 0;">✏️ Ressort bearbeiten</h3>
            <form method="POST" action="?tab=admin_init" id="editRessortForm">
                <input type="hidden" name="edit_ressort" value="1">
                <input type="hidden" name="ressort_id" id="edit_ressort_id">

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ressort-Name:</label>
                    <input type="text" name="ressort_name" id="edit_ressort_name" required
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Reihenfolge:</label>
                    <input type="number" name="reihenfolge" id="edit_reihenfolge" min="1" max="999"
                           style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                        <input type="checkbox" name="aktiv" id="edit_aktiv" value="1"> Aktiv
                    </label>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary">Speichern</button>
                    <button type="button" onclick="closeEditRessortModal()" class="btn-secondary">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditRessortModal(ressort) {
        document.getElementById('edit_ressort_id').value = ressort.ID;
        document.getElementById('edit_ressort_name').value = ressort.Ressort;
        document.getElementById('edit_reihenfolge').value = ressort.Reihenfolge;
        document.getElementById('edit_aktiv').checked = (ressort.aktiv == 1);
        document.getElementById('editRessortModal').style.display = 'flex';
    }

    function closeEditRessortModal() {
        document.getElementById('editRessortModal').style.display = 'none';
    }
    </script>

    <!-- 2. TERMINOLOGIE-KONFIGURATION -->
    <div class="admin-section">
        <h3 class="admin-section-header init-section-header" onclick="toggleSection(this)">
            📝 Terminologie / Begriffe
        </h3>

        <div class="admin-section-content collapsed">
            <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                Passe die verwendeten Begriffe an deine Organisation an.
                Diese Begriffe erscheinen im gesamten Antragssystem.
            </p>

            <?php
            // Config laden
            $config_stmt = $pdo->query("SELECT * FROM svconfig WHERE category = 'terminology' ORDER BY config_key");
            $configs = $config_stmt->fetchAll();
            ?>

            <form method="POST" action="?tab=admin_init">
                <input type="hidden" name="save_terminology" value="1">

                <table style="width: 100%; max-width: 800px;">
                    <thead>
                        <tr>
                            <th style="text-align: left; width: 40%;">Begriff</th>
                            <th style="text-align: left; width: 40%;">Aktuelle Bezeichnung</th>
                            <th style="text-align: left; width: 20%;">Beschreibung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configs as $cfg): ?>
                            <tr>
                                <td style="padding: 10px 5px; font-weight: 600;">
                                    <?php
                                    $label = str_replace(['term_', '_'], ['', ' '], $cfg['config_key']);
                                    echo ucfirst($label);
                                    ?>
                                </td>
                                <td style="padding: 10px 5px;">
                                    <input type="text"
                                           name="config[<?php echo $cfg['config_key']; ?>]"
                                           value="<?php echo htmlspecialchars($cfg['config_value']); ?>"
                                           style="width: 100%; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px;">
                                </td>
                                <td style="padding: 10px 5px; font-size: 11px; color: #666;">
                                    <?php echo htmlspecialchars($cfg['description']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-primary">Terminologie speichern</button>
                    <span style="margin-left: 15px; color: #dc3545; font-size: 12px;">
                        ⚠️ Änderungen wirken sich auf das gesamte System aus
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. BERECHTIGUNGSSTUFEN / AKTIV-LEVEL -->
    <div class="admin-section">
        <h3 class="admin-section-header init-section-header" onclick="toggleSection(this)">
            🔐 Berechtigungsstufen (aktiv-Level)
        </h3>

        <div class="admin-section-content collapsed">
            <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                Definition der Berechtigungsstufen (aktiv = 0-19) für das Antragssystem.
                Diese Stufen steuern, wer welche Aktionen durchführen darf.
            </p>

            <?php
            // aktiv-Level Konfiguration laden
            $level_config_stmt = $pdo->query("
                SELECT * FROM svconfig
                WHERE category = 'aktiv_levels'
                ORDER BY config_key
            ");
            $level_configs = $level_config_stmt->fetchAll();

            // Level-Array erstellen (0-19)
            $levels = [];
            foreach ($level_configs as $cfg) {
                $level_num = (int)str_replace('aktiv_level_', '', $cfg['config_key']);
                $levels[$level_num] = $cfg['config_value'];
            }
            ?>

            <form method="POST" action="?tab=admin_init">
                <input type="hidden" name="save_aktiv_levels" value="1">

                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 14px;">Standard-Level (in Anlehnung an einen großen Verein):</h4>
                    <p style="margin: 0 0 15px 0; font-size: 12px; color: #666;">
                        'aktiv' steht für Antrags-, Freigabe- und Zugriffsrechte
                    </p>
                    <table style="width: 100%; font-size: 13px;">
                        <thead>
                            <tr>
                                <th style="text-align: left; width: 80px; padding: 5px;">Level</th>
                                <th style="text-align: left; padding: 5px;">Bezeichnung / Berechtigung</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i <= 19; $i++):
                                // Highlight-Klassen für wichtige Level
                                $bg_color = '';
                                if ($i == 10) $bg_color = 'background: #e8f5e9;';
                                elseif ($i >= 14 && $i <= 15) $bg_color = 'background: #fff3e0;';
                                elseif ($i == 18) $bg_color = 'background: #e3f2fd;';
                                elseif ($i == 19) $bg_color = 'background: #fce4ec;';
                            ?>
                                <tr style="<?php echo $bg_color; ?>">
                                    <td style="padding: 5px; font-weight: 600;"><?php echo $i; ?></td>
                                    <td style="padding: 5px;">
                                        <input type="text"
                                               name="level[<?php echo $i; ?>]"
                                               value="<?php echo htmlspecialchars($levels[$i] ?? ''); ?>"
                                               style="width: 100%; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                    <p style="margin: 15px 0 0 0; font-size: 12px; color: #666;">
                        💡 Diese Stufen können in svmembers bei jedem Mitglied individuell gesetzt werden.
                    </p>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-primary">aktiv-Level speichern</button>
                    <span style="margin-left: 15px; color: #dc3545; font-size: 12px;">
                        ⚠️ Änderungen wirken sich auf die Darstellung im gesamten System aus
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. SPEZIALFUNKTIONEN -->
    <div class="admin-section">
        <h3 class="admin-section-header init-section-header" onclick="toggleSection(this)">
            👥 Spezialfunktionen
        </h3>

        <div class="admin-section-content collapsed">
            <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                Spezielle Funktionen für die Antragssteuerung.
                Einige Kürzel sind fest vorgegeben, da sie interne Logik steuern.
            </p>

            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #856404;">Vordefinierte Funktionen (nicht änderbar):</h4>
                <table style="width: 100%; font-size: 13px;">
                    <tr>
                        <td style="padding: 5px; width: 80px; font-weight: 600; color: #856404;">VA</td>
                        <td style="padding: 5px; color: #856404;">Vorstandsassistenz (Rechte wie Vorstand, außer abstimmen)</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px; font-weight: 600; color: #856404;">Vo</td>
                        <td style="padding: 5px; color: #856404;">Vorstand - Vorstandsrechte, Abstimmung als Vorstand</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px; font-weight: 600; color: #856404;">FVo</td>
                        <td style="padding: 5px; color: #856404;">Finanzvorstand - wie Vo, hat spezielle Freigabe-Rechte</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px; font-weight: 600; color: #856404;">FVv</td>
                        <td style="padding: 5px; color: #856404;">Stellvertreter des Finanzvorstands - Rechte wie FVo</td>
                    </tr>
                </table>
            </div>

            <div style="background: #e8f5e9; border-left: 4px solid #28a745; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;">Weitere Funktionen (frei definierbar):</h4>
                <p style="margin: 0; font-size: 13px;">
                    Zusätzliche Funktionen können frei in svmembers eingetragen werden.
                    Beispiele: "RL" (Ressortleiter), "Ass" (Assistenz), "StV" (Stellvertreter), etc.
                </p>
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                    Diese haben (noch) keine automatische Steuerungsfunktion,
                    dienen aber der Dokumentation und können später für Rechteprüfungen verwendet werden.
                </p>
            </div>

            <p style="margin-top: 20px; font-size: 12px; color: #999;">
                Die Funktionen werden direkt in der Mitgliederverwaltung (svmembers) zugewiesen.
            </p>
        </div>
    </div>

    <!-- 5. ANTRAGSTYPEN / BESCHLUSSARTEN -->
    <div class="admin-section">
        <h3 class="admin-section-header init-section-header" onclick="toggleSection(this)">
            📋 Antragstypen / Beschlussarten
        </h3>

        <div class="admin-section-content collapsed">
            <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                Konfiguration der drei Antragstypen: <strong>V</strong> (Verfügung), <strong>R</strong> (Ressortbeschluss), <strong>B</strong> (Vorstandsbeschluss).<br>
                Nicht benötigte Typen können deaktiviert werden. Kleine Vereine können z.B. nur Vorstandsbeschlüsse nutzen.
            </p>

            <?php
            // Antragstypen-Konfiguration laden
            $bart_config_stmt = $pdo->query("
                SELECT * FROM svconfig
                WHERE category = 'antragstypen'
                ORDER BY config_key
            ");
            $bart_configs = $bart_config_stmt->fetchAll();

            // Configs in Array organisieren
            $bart = [];
            foreach ($bart_configs as $cfg) {
                $bart[$cfg['config_key']] = $cfg['config_value'];
            }
            ?>

            <form method="POST" action="?tab=admin_init">
                <input type="hidden" name="save_antragstypen" value="1">

                <?php
                $typen = [
                    'V' => ['name' => 'Verfügung', 'color' => '#e3f2fd', 'border' => '#2196f3'],
                    'R' => ['name' => 'Ressortbeschluss', 'color' => '#fff3e0', 'border' => '#ff9800'],
                    'B' => ['name' => 'Vorstandsbeschluss', 'color' => '#fce4ec', 'border' => '#e91e63']
                ];

                foreach ($typen as $typ => $typ_info):
                    $aktiv = ($bart["bart_{$typ}_aktiv"] ?? '1') == '1';
                ?>

                <!-- TYP <?php echo $typ; ?> -->
                <div style="background: <?php echo $typ_info['color']; ?>; border-left: 4px solid <?php echo $typ_info['border']; ?>; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-size: 16px;">
                            Typ <?php echo $typ; ?>: <?php echo $typ_info['name']; ?>
                        </h4>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
                            <input type="checkbox" name="bart[<?php echo $typ; ?>][aktiv]" value="1"
                                   <?php echo $aktiv ? 'checked' : ''; ?>
                                   onchange="toggleTypSection('<?php echo $typ; ?>', this.checked)">
                            <span style="color: <?php echo $aktiv ? '#28a745' : '#999'; ?>;">
                                <?php echo $aktiv ? '✓ Aktiviert' : '⊗ Deaktiviert'; ?>
                            </span>
                        </label>
                    </div>

                    <div id="typ_<?php echo $typ; ?>_details" style="<?php echo !$aktiv ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        <!-- Bezeichnung -->
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Bezeichnung:</label>
                            <input type="text" name="bart[<?php echo $typ; ?>][bezeichnung]"
                                   value="<?php echo htmlspecialchars($bart["bart_{$typ}_bezeichnung"] ?? $typ_info['name']); ?>"
                                   style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>

                        <!-- Beschreibung -->
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Beschreibung:</label>
                            <input type="text" name="bart[<?php echo $typ; ?>][beschreibung]"
                                   value="<?php echo htmlspecialchars($bart["bart_{$typ}_beschreibung"] ?? ''); ?>"
                                   placeholder="Kurze Erklärung dieses Typs"
                                   style="width: 100%; max-width: 600px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>

                        <!-- Betragsgrenze -->
                        <div style="background: white; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 10px;">
                                <input type="checkbox" name="bart[<?php echo $typ; ?>][betrag_aktiv]" value="1"
                                       <?php echo ($bart["bart_{$typ}_betrag_aktiv"] ?? '0') == '1' ? 'checked' : ''; ?>
                                       onchange="toggleSubOption('betrag_<?php echo $typ; ?>', this.checked)">
                                <strong>Betragsgrenze aktiv</strong>
                            </label>
                            <div id="betrag_<?php echo $typ; ?>_details" style="margin-left: 30px; <?php echo ($bart["bart_{$typ}_betrag_aktiv"] ?? '0') != '1' ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                                <label style="display: block; margin-bottom: 5px;">Maximaler Betrag (€):</label>
                                <input type="number" name="bart[<?php echo $typ; ?>][betrag_limit]"
                                       value="<?php echo htmlspecialchars($bart["bart_{$typ}_betrag_limit"] ?? '0'); ?>"
                                       min="0" step="1"
                                       style="width: 150px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                                <small style="display: block; margin-top: 5px; color: #666;">
                                    Anträge dieses Typs dürfen diesen Betrag nicht überschreiten (0 = keine Grenze)
                                </small>
                            </div>
                        </div>

                        <!-- Wartezeit -->
                        <div style="background: white; padding: 12px; border-radius: 4px; margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 10px;">
                                <input type="checkbox" name="bart[<?php echo $typ; ?>][wartezeit_aktiv]" value="1"
                                       <?php echo ($bart["bart_{$typ}_wartezeit_aktiv"] ?? '0') == '1' ? 'checked' : ''; ?>
                                       onchange="toggleSubOption('wartezeit_<?php echo $typ; ?>', this.checked)">
                                <strong>Wartezeit aktiv</strong>
                            </label>
                            <div id="wartezeit_<?php echo $typ; ?>_details" style="margin-left: 30px; <?php echo ($bart["bart_{$typ}_wartezeit_aktiv"] ?? '0') != '1' ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                                <label style="display: block; margin-bottom: 5px;">Wartezeit (Tage):</label>
                                <input type="number" name="bart[<?php echo $typ; ?>][wartezeit_tage]"
                                       value="<?php echo htmlspecialchars($bart["bart_{$typ}_wartezeit_tage"] ?? '0'); ?>"
                                       min="0" max="365" step="1"
                                       style="width: 150px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                                <small style="display: block; margin-top: 5px; color: #666;">
                                    Anträge müssen diese Zeit zur Prüfung ausliegen, bevor sie beschlossen werden können
                                </small>
                            </div>
                        </div>

                        <!-- Freigabe-Vereinfachung -->
                        <div style="background: white; padding: 12px; border-radius: 4px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="bart[<?php echo $typ; ?>][freigabe_vereinfacht]" value="1"
                                       <?php echo ($bart["bart_{$typ}_freigabe_vereinfacht"] ?? '0') == '1' ? 'checked' : ''; ?>>
                                <strong>Vereinfachte Freigabe</strong>
                            </label>
                            <small style="display: block; margin-top: 5px; margin-left: 30px; color: #666;">
                                Ermöglicht automatische oder vereinfachte Freigabe-Regeln für diesen Typ
                            </small>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

                <!-- Globale Einstellungen -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; font-size: 14px;">Globale Einstellungen</h4>

                    <div style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="bart[global][show_betrag_in_liste]" value="1"
                                   <?php echo ($bart['bart_show_betrag_in_liste'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <strong>Beträge in Antragsliste anzeigen</strong>
                        </label>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                            Betragsangabe ist Pflicht ab (€):
                        </label>
                        <input type="number" name="bart[global][pflicht_bei_betrag]"
                               value="<?php echo htmlspecialchars($bart['bart_pflicht_bei_betrag'] ?? '100'); ?>"
                               min="0" step="1"
                               style="width: 150px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="display: block; margin-top: 5px; color: #666;">
                            Ab diesem Betrag muss eine Betragsangabe im Antrag gemacht werden
                        </small>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                            Monatliches Verfügungslimit (€):
                        </label>
                        <input type="number" name="bart[global][verfuegung_monatslimit]"
                               value="<?php echo htmlspecialchars($bart['bart_verfuegung_monatslimit'] ?? '2000'); ?>"
                               min="0" step="1"
                               style="width: 150px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="display: block; margin-top: 5px; color: #666;">
                            Maximale Summe aller Verfügungen pro Monat. Bei Überschreitung ist ein Ressortbeschluss erforderlich.
                        </small>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-primary">Antragstypen speichern</button>
                    <span style="margin-left: 15px; color: #dc3545; font-size: 12px;">
                        ⚠️ Änderungen wirken sich auf das Antragsverhalten aus
                    </span>
                </div>
            </form>

            <script>
            function toggleTypSection(typ, enabled) {
                const details = document.getElementById('typ_' + typ + '_details');
                if (details) {
                    details.style.opacity = enabled ? '1' : '0.5';
                    details.style.pointerEvents = enabled ? 'auto' : 'none';
                }
            }

            function toggleSubOption(prefix, enabled) {
                const details = document.getElementById(prefix + '_details');
                if (details) {
                    details.style.opacity = enabled ? '1' : '0.5';
                    details.style.pointerEvents = enabled ? 'auto' : 'none';
                }
            }
            </script>
        </div>
    </div>

    <!-- 6. ABSTIMMUNGSREGELN -->
    <div class="admin-section">
        <h3 class="admin-section-header init-section-header" onclick="toggleSection(this)">
            🗳️ Abstimmungsregeln
        </h3>

        <div class="admin-section-content collapsed">
            <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                Konfiguration der verfügbaren Abstimmungsregeln für Anträge.<br>
                Jeder Antrag kann individuell eine Regel zugewiesen bekommen (nur durch Admins).
            </p>

            <?php
            // Voting-Config laden
            $voting_stmt = $pdo->query("
                SELECT * FROM svconfig
                WHERE category = 'voting'
                ORDER BY config_key
            ");
            $voting_cfgs = $voting_stmt->fetchAll();

            $voting_data = [];
            foreach ($voting_cfgs as $cfg) {
                $voting_data[$cfg['config_key']] = $cfg['config_value'];
            }
            ?>

            <form method="POST" action="?tab=admin_init">
                <input type="hidden" name="save_voting_rules" value="1">

                <div style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; font-size: 14px;">Standard-Regel</h4>
                    <select name="voting[default_rule]" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <?php
                        $current_default = $voting_data['voting_default_rule'] ?? 'einfach';
                        $rule_keys = ['einfach', 'absolut', 'mehrheit_stimmber', 'zweidrittel', 'einstimmig'];
                        foreach ($rule_keys as $key):
                            $label = $voting_data["voting_rule_{$key}_label"] ?? $key;
                        ?>
                            <option value="<?= $key ?>" <?= $current_default === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Diese Regel wird für neue Anträge vorausgewählt
                    </small>
                </div>

                <h4 style="margin: 20px 0 10px 0; font-size: 14px;">Verfügbare Regeln</h4>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                    Nur aktivierte Regeln stehen zur Auswahl. Bezeichnungen und Beschreibungen können angepasst werden.
                </p>

                <?php
                $rules = [
                    'einfach' => ['name' => 'Einfache Mehrheit', 'default_desc' => 'Mehr Ja als Nein (Enthaltungen zählen nicht)'],
                    'absolut' => ['name' => 'Absolute Mehrheit', 'default_desc' => 'Mehr Ja als Nein+Enthaltung'],
                    'mehrheit_stimmber' => ['name' => 'Mehrheit der Stimmberechtigten', 'default_desc' => 'Mehr als 50% aller Stimmberechtigten stimmen Ja'],
                    'zweidrittel' => ['name' => '2/3-Mehrheit', 'default_desc' => 'Ja-Stimmen >= 2 × Nein-Stimmen'],
                    'einstimmig' => ['name' => 'Einstimmigkeit', 'default_desc' => 'Alle Abstimmenden müssen Ja stimmen']
                ];

                foreach ($rules as $key => $rule):
                    $enabled = ($voting_data["voting_enable_{$key}"] ?? '1') == '1';
                    $label = $voting_data["voting_rule_{$key}_label"] ?? $rule['name'];
                    $desc = $voting_data["voting_rule_{$key}_desc"] ?? $rule['default_desc'];
                ?>
                    <div style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <h5 style="margin: 0; font-size: 14px;"><?= htmlspecialchars($rule['name']) ?></h5>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="voting[enable_<?= $key ?>]" value="1"
                                       <?= $enabled ? 'checked' : '' ?>>
                                <span style="font-weight: 600; color: <?= $enabled ? '#28a745' : '#999' ?>;">
                                    <?= $enabled ? '✓ Aktiviert' : '⊗ Deaktiviert' ?>
                                </span>
                            </label>
                        </div>

                        <div style="margin-bottom: 10px;">
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">Bezeichnung:</label>
                            <input type="text" name="voting[rule_<?= $key ?>_label]"
                                   value="<?= htmlspecialchars($label) ?>"
                                   style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600;">Beschreibung:</label>
                            <input type="text" name="voting[rule_<?= $key ?>_desc]"
                                   value="<?= htmlspecialchars($desc) ?>"
                                   style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-primary">Abstimmungsregeln speichern</button>
                    <span style="margin-left: 15px; color: #dc3545; font-size: 12px;">
                        ⚠️ Änderungen wirken sich auf zukünftige Abstimmungen aus
                    </span>
                </div>
            </form>

            <div style="background: #e8f5e9; border-left: 4px solid #28a745; padding: 15px; margin-top: 20px;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;">Hinweise</h4>
                <ul style="margin: 0; padding-left: 20px; font-size: 13px;">
                    <li>Die Abstimmungsregel wird pro Antrag festgelegt (nur durch Admins)</li>
                    <li>Bestehende Anträge behalten ihre Regel</li>
                    <li>Die Auswertung erfolgt automatisch nach der gewählten Regel</li>
                    <li>Bei alten Anträgen ohne Regel wird die Legacy-Logik verwendet</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- PLATZHALTER FÜR WEITERE BEREICHE -->
    <!-- Hier werden später weitere Konfigurationsbereiche hinzugefügt:
         - Workflow-Status (A, B, VS, X, Z) - NICHT konfigurierbar
         - etc.
    -->

<?php endif; ?>
