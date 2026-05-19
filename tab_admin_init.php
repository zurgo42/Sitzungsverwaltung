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

    <!-- PLATZHALTER FÜR WEITERE INITIALISIERUNGS-BEREICHE -->
    <!-- Hier werden später weitere Konfigurationsbereiche hinzugefügt:
         - Antragstypen (V, R, B)
         - Workflow-Status (A, B, VS, X, Z)
         - etc.
    -->

<?php endif; ?>
