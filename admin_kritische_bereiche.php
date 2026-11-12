<?php
/**
 * admin_kritische_bereiche.php - Kritische Systembereiche (Teil von Grundkonfiguration/Initialisierung)
 *
 * Wird nur aus tab_admin_init.php eingebunden, nachdem das System-Admin-Passwort eingegeben wurde:
 * Externe Zugriffs-Logs, System- & Demo-Funktionen, Produktions-System-Verwaltung, Datenbank-Wartung, Datenbank-Protokoll.
 * Voraussetzungen: $pdo, $external_access_logs, $external_access_stats (process_admin.php).
 */
if (!isset($pdo) || empty($current_user['is_admin']) || !function_exists('sv_init_passwort_ok') || !sv_init_passwort_ok()) {
    return;
}
?>
<script>
// Akkordeon für die Bereiche (eigenständig, da tab_admin.php nicht geladen ist)
if (typeof toggleSection === 'undefined') {
    function toggleSection(header) {
        header.classList.toggle('collapsed');
        var content = header.nextElementSibling;
        if (content) content.classList.toggle('collapsed');
    }
}
if (typeof openSection === 'undefined') {
    function openSection(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var header = el.querySelector('.admin-section-header');
        var content = el.querySelector('.admin-section-content');
        if (header && content && content.classList.contains('collapsed')) toggleSection(header);
    }
}
document.addEventListener('DOMContentLoaded', function () {
    // Bereiche eingeklappt starten; per Anker/Parameter gewünschten Bereich öffnen
    document.querySelectorAll('#sv-kritische-bereiche .admin-section-header').forEach(function (h) {
        var c = h.nextElementSibling;
        if (c && !c.classList.contains('collapsed')) { c.classList.add('collapsed'); h.classList.add('collapsed'); }
    });
    var anchor = (location.hash || '').replace('#', '');
    if (anchor) openSection(anchor);
    if (new URLSearchParams(location.search).get('section') === 'auditlog') openSection('admin-auditlog');
});
<?php /* showExternalLogDetails: Dialog mit eigenen ids (ext-log-*) */ ?>
// Externe Zugriffs-Log Details
function showExternalLogDetails(logId) {
    const logs = <?php echo json_encode($external_access_logs); ?>;
    const log = logs.find(l => l.log_id == logId);

    if (log) {
        let html = '<div class="log-details">';

        html += '<h4>Zugriffs-Details:</h4>';
        html += '<table style="width: 100%; border-collapse: collapse;">';

        if (log.access_type) {
            const types = {
                'member_login': '✅ Member-Login via Mitgliedsnummer',
                'invalid_mnr': '⚠️ Ungültiger Mitgliedsnummer-Versuch',
                'external_registration': '👤 Externe Registrierung',
                'registration_error': '❌ Registrierungsfehler'
            };
            html += '<tr><td style="padding: 5px; font-weight: bold;">Typ:</td><td style="padding: 5px;">' + (types[log.access_type] || log.access_type) + '</td></tr>';
        }

        if (log.poll_type) {
            html += '<tr><td style="padding: 5px; font-weight: bold;">Umfrage:</td><td style="padding: 5px;">' +
                    (log.poll_type === 'termine' ? '📅 Terminumfrage' : '📊 Meinungsbild') + ' #' + log.poll_id + '</td></tr>';
        }

        if (log.member_first_name) {
            html += '<tr><td style="padding: 5px; font-weight: bold;">Mitglied:</td><td style="padding: 5px;">' +
                    log.member_first_name + ' ' + log.member_last_name +
                    (log.membership_number ? ' (MNr: ' + log.membership_number + ')' : '') + '</td></tr>';
        }

        if (log.mnr) {
            html += '<tr><td style="padding: 5px; font-weight: bold;">Eingeg. MNr:</td><td style="padding: 5px;">' + log.mnr + '</td></tr>';
        }

        if (log.first_name || log.last_name) {
            html += '<tr><td style="padding: 5px; font-weight: bold;">Name:</td><td style="padding: 5px;">' +
                    (log.first_name || '') + ' ' + (log.last_name || '') + '</td></tr>';
        }

        if (log.email) {
            html += '<tr><td style="padding: 5px; font-weight: bold;">E-Mail:</td><td style="padding: 5px;">' + log.email + '</td></tr>';
        }

        if (log.ip_address) {
            html += '<tr><td style="padding: 5px; font-weight: bold;">IP-Adresse:</td><td style="padding: 5px; font-family: monospace;">' + log.ip_address + '</td></tr>';
        }

        if (log.user_agent) {
            html += '<tr><td style="padding: 5px; font-weight: bold;">Browser:</td><td style="padding: 5px; font-size: 0.9em; word-break: break-all;">' + log.user_agent + '</td></tr>';
        }

        html += '<tr><td style="padding: 5px; font-weight: bold;">Status:</td><td style="padding: 5px;">' +
                (log.success ? '<span style="color: #4caf50;">✓ Erfolgreich</span>' : '<span style="color: #f44336;">✗ Fehlgeschlagen</span>') + '</td></tr>';

        if (log.error_message) {
            html += '<tr><td style="padding: 5px; font-weight: bold; vertical-align: top;">Fehlermeldung:</td><td style="padding: 5px; color: #f44336;">' + log.error_message + '</td></tr>';
        }

        html += '<tr><td style="padding: 5px; font-weight: bold;">Zeitpunkt:</td><td style="padding: 5px;">' +
                new Date(log.created_at).toLocaleString('de-DE') + '</td></tr>';

        html += '</table>';
        html += '</div>';

        document.getElementById('ext-log-details-content').innerHTML = html;
        document.getElementById('ext-log-details-modal').classList.add('show');
    }
}

</script>
<div id="ext-log-details-modal" class="modal">
    <div class="modal-content">
        <h3>Zugriffs-Details</h3>
        <div id="ext-log-details-content"></div>
        <button type="button" onclick="document.getElementById('ext-log-details-modal').classList.remove('show')" class="btn-secondary">Schließen</button>
    </div>
</div>

<h3 style="margin: 30px 0 10px; color:#dc3545;">🔒 Kritische Systembereiche</h3>
<div id="sv-kritische-bereiche">
<!-- Externe Zugriffs-Logs -->
<div id="external-access-log" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">🔐 Externe Zugriffs-Logs (Terminumfragen & Meinungsbilder)</h3>

    <div class="admin-section-content">

    <?php if (!empty($external_access_stats)): ?>
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <?php
            $access_type_labels = [
                'member_login' => ['icon' => '✅', 'label' => 'Member-Logins', 'color' => '#4caf50'],
                'invalid_mnr' => ['icon' => '⚠️', 'label' => 'Ungültige MNr', 'color' => '#ff9800'],
                'external_registration' => ['icon' => '👤', 'label' => 'Externe Registrierungen', 'color' => '#2196f3'],
                'registration_error' => ['icon' => '❌', 'label' => 'Fehler', 'color' => '#f44336']
            ];

            foreach ($external_access_stats as $stat):
                $info = $access_type_labels[$stat['access_type']] ?? ['icon' => '📊', 'label' => $stat['access_type'], 'color' => '#999'];
            ?>
                <div style="background: white; padding: 15px; border-radius: 8px; border-left: 4px solid <?php echo $info['color']; ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="font-size: 24px; margin-bottom: 5px;"><?php echo $info['icon']; ?></div>
                    <div style="font-size: 28px; font-weight: bold; color: <?php echo $info['color']; ?>; margin-bottom: 5px;">
                        <?php echo $stat['count']; ?>
                    </div>
                    <div style="font-size: 14px; color: #666;">
                        <?php echo $info['label']; ?>
                        <span style="font-size: 12px; color: #999;">(30 Tage)</span>
                    </div>
                    <?php if ($stat['failure_count'] > 0): ?>
                        <div style="font-size: 12px; color: #f44336; margin-top: 5px;">
                            ⚠️ <?php echo $stat['failure_count']; ?> fehlgeschlagen
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($external_access_logs)): ?>
        <div class="info-box">Keine externen Zugriffe protokolliert.</div>
    <?php else: ?>
        <table class="admin-table compact-log-table">
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Typ</th>
                    <th>Umfrage</th>
                    <th>Person</th>
                    <th>IP-Adresse</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($external_access_logs as $log):
                    $access_badges = [
                        'member_login' => ['label' => '✅ Member-Login', 'class' => 'success'],
                        'invalid_mnr' => ['label' => '⚠️ Ungültige MNr', 'class' => 'warning'],
                        'external_registration' => ['label' => '👤 Externe Reg.', 'class' => 'info'],
                        'registration_error' => ['label' => '❌ Fehler', 'class' => 'error']
                    ];
                    $badge = $access_badges[$log['access_type']] ?? ['label' => $log['access_type'], 'class' => 'default'];

                    // Person identifizieren
                    $person = '';
                    if ($log['member_first_name']) {
                        $person = htmlspecialchars($log['member_first_name'] . ' ' . $log['member_last_name']);
                        if ($log['membership_number']) {
                            $person .= ' <span style="color: #666; font-size: 0.9em;">(MNr: ' . htmlspecialchars($log['membership_number']) . ')</span>';
                        }
                    } elseif ($log['first_name'] && $log['last_name']) {
                        $person = htmlspecialchars($log['first_name'] . ' ' . $log['last_name']);
                    } elseif ($log['email']) {
                        $person = htmlspecialchars($log['email']);
                    } elseif ($log['mnr']) {
                        $person = '<span style="color: #999;">MNr: ' . htmlspecialchars($log['mnr']) . '</span>';
                    } else {
                        $person = '<span style="color: #999;">-</span>';
                    }
                ?>
                    <tr style="<?php echo !$log['success'] ? 'background: #fff3cd;' : ''; ?>">
                        <td style="white-space: nowrap;"><?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?></td>
                        <td>
                            <span class="action-badge action-<?php echo $badge['class']; ?>">
                                <?php echo $badge['label']; ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo $log['poll_type'] === 'termine' ? '📅 Termin' : '📊 Meinungsbild'; ?></strong>
                            <span style="color: #666; font-size: 0.9em;">#<?php echo $log['poll_id']; ?></span>
                        </td>
                        <td><?php echo $person; ?></td>
                        <td style="font-family: monospace; font-size: 0.9em;"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        <td>
                            <?php if ($log['success']): ?>
                                <span style="color: #4caf50;">✓ Erfolg</span>
                            <?php else: ?>
                                <span style="color: #f44336;">✗ Fehler</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['error_message'] || $log['user_agent']): ?>
                                <button class="btn-view" onclick="showExternalLogDetails(<?php echo $log['log_id']; ?>)">
                                    🔍 Details
                                </button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div> <!-- End admin-section-content -->
</div>

<!-- System & Demo-Funktionen -->
<?php if (DEMO_MODE_ENABLED): ?>

<div id="admin-demo" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">🎭 System &amp; Demo-Funktionen</h3>

    <div class="admin-section-content">
        <div class="warning" style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
            <h4 style="margin-top: 0;">⚠️ Demo-Modus aktiv</h4>
            <p>
                Der Demo-Modus ist aktiviert. Diese Funktionen erlauben es, die Datenbank auf einen
                definierten Demo-Stand zurückzusetzen - ideal für Präsentationen und Tests.
            </p>
            <p style="margin-bottom: 0;">
                <strong>WICHTIG:</strong> Für den echten Produktivbetrieb sollte in der <code>config.php</code>
                die Einstellung <code>DEMO_MODE_ENABLED = false</code> gesetzt werden!
            </p>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4>🔄 Demo-Daten-Verwaltung</h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Demo Export -->
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h5 style="margin-top: 0; color: #007bff;">📦 Aktuellen Stand als Demo-Version speichern</h5>
                    <p style="font-size: 14px; color: #666;">
                        Speichert den aktuellen Datenbankstand als Demo-Version „aktuell". Die beiden vorgegebenen Versionen
                        (Vollausstattung, Einfachausstattung) bleiben unverändert.
                    </p>
                    <a href="tools/demo_export.php" class="btn" style="background-color: #007bff; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-top: 10px;" target="_blank">
                        📦 Als „aktuell" speichern
                    </a>
                </div>

                <!-- Demo-Version wählen (überschreibt den aktuellen Stand) -->
                <div style="border: 1px solid #dc3545; padding: 15px; border-radius: 5px; background-color: #fff5f5;">
                    <h5 style="margin-top: 0; color: #dc3545;">♻️ Demo-Version laden</h5>
                    <p style="font-size: 14px; color: #666;">
                        <strong>ACHTUNG:</strong> Die gewählte Version überschreibt den aktuellen Stand und löscht alle aktuellen Daten.
                        Dieser Vorgang kann nicht rückgängig gemacht werden!
                    </p>
                    <?php $demo_aktuell_da = file_exists(__DIR__ . '/tools/demo_data_aktuell.json'); ?>
                    <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 10px;">
                        <a href="tools/demo_import.php?profil=voll" class="btn btn-danger" style="background-color: #dc3545; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block;" target="_blank" onclick="return confirm('WARNUNG: Dies löscht ALLE aktuellen Daten!\n\nMöchtest du wirklich fortfahren?');">
                            ♻️ Vollausstattung (vorgegeben)
                        </a>
                        <a href="tools/demo_import.php?profil=einfach" class="btn btn-danger" style="background-color: #dc3545; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block;" target="_blank" onclick="return confirm('WARNUNG: Dies löscht ALLE aktuellen Daten!\n\nMöchtest du wirklich fortfahren?');">
                            ♻️ Einfachausstattung (vorgegeben)
                        </a>
                        <?php if ($demo_aktuell_da): ?>
                        <a href="tools/demo_import.php?profil=aktuell" class="btn btn-danger" style="background-color: #6c757d; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block;" target="_blank" onclick="return confirm('WARNUNG: Dies löscht ALLE aktuellen Daten!\n\nMöchtest du wirklich fortfahren?');">
                            ♻️ Aktuelle Version (gespeichert <?= date('d.m.Y H:i', filemtime(__DIR__ . '/tools/demo_data_aktuell.json')) ?>)
                        </a>
                        <?php else: ?>
                        <span style="font-size: 13px; color: #888;">Aktuelle Version: noch nicht gespeichert.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; padding: 15px; background-color: #e7f3ff; border-left: 4px solid #0c5460; border-radius: 5px;">
                <h5 style="margin-top: 0;">💡 Workflow</h5>
                <ol style="font-size: 14px; margin: 0;">
                    <li>Erstelle in der Anwendung verschiedene Meetings, TODOs, Kommentare etc.</li>
                    <li>Speichere diesen Stand als Demo-Version „aktuell" (erzeugt <code>demo_data_aktuell.json</code>)</li>
                    <li>Zum Wiederherstellen oder Wechseln lädst du eine der drei Versionen: Vollausstattung, Einfachausstattung oder „aktuell"</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Produktions-System-Funktionen (immer sichtbar) -->
<div id="admin-production" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">⚙️ Produktions-System-Verwaltung</h3>

    <div class="admin-section-content">
        <div class="warning" style="background-color: #ffebee; border-left: 4px solid #d32f2f; padding: 15px; margin-bottom: 20px;">
            <h4 style="margin-top: 0; color: #d32f2f;">⚠️ KRITISCHE SYSTEM-FUNKTIONEN</h4>
            <p>
                Diese Funktionen sind für die Einrichtung und Verwaltung des Produktivsystems gedacht.
                Verwende sie mit äußerster Vorsicht!
            </p>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4>🗄️ Datenbank-Verwaltung</h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- db-init.sql Download -->
                <div style="border: 1px solid #2196F3; padding: 15px; border-radius: 5px; background-color: #e3f2fd;">
                    <h5 style="margin-top: 0; color: #1976D2;">📄 Datenbank-Schema herunterladen</h5>
                    <p style="font-size: 14px; color: #666;">
                        Lädt die vollständige Datenbank-Initialisierungsdatei <code>db-init.sql</code> herunter.
                        Enthält alle Tabellendefinitionen mit sv-Präfix.
                    </p>
                    <p style="font-size: 13px; color: #555;">
                        <strong>Verwendung:</strong> Für Neuinstallationen oder Migrations auf anderen Systemen.
                    </p>
                    <a href="db-init.sql" class="btn" style="background-color: #2196F3; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-top: 10px;" download>
                        📥 db-init.sql herunterladen
                    </a>
                </div>

                <!-- Produktions-Reset -->
                <div style="border: 2px solid #d32f2f; padding: 15px; border-radius: 5px; background-color: #ffebee;">
                    <h5 style="margin-top: 0; color: #d32f2f;">♻️ Produktionsdatenbank zurücksetzen</h5>
                    <p style="font-size: 14px; color: #666;">
                        <strong>KRITISCH:</strong> Löscht ALLE Daten aus der Produktionsdatenbank!
                        Nur für initiale Einrichtung verwenden.
                    </p>
                    <p style="font-size: 13px; color: #555;">
                        <strong>Was wird geleert:</strong> Alle Mitglieder, Sitzungen, TODOs, Umfragen, Dokumente-Metadaten
                    </p>
                    <p style="font-size: 13px; color: #555;">
                        <strong>Was bleibt:</strong> Tabellenstruktur, Antwortvorlagen
                    </p>
                    <a href="tools/production_reset.php" class="btn btn-danger" style="background-color: #d32f2f; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-top: 10px; font-weight: 600;" target="_blank" onclick="return confirm('⚠️ ACHTUNG!\n\nDies ist eine KRITISCHE Funktion!\n\nAlle Daten werden unwiderruflich gelöscht.\n\nMöchtest du wirklich fortfahren?');">
                        🗑️ Produktions-Reset
                    </a>
                </div>
            </div>

            <div style="margin-top: 30px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                <h4 style="margin-top: 0;">📋 Produktions-Checkliste</h4>
                <ol style="font-size: 14px; line-height: 1.8;">
                    <li>✅ Datenbank mit <code>db-init.sql</code> initialisiert</li>
                    <li>✅ <code>config.php</code> auf Produktionseinstellungen geprüft (DB-Credentials, DEMO_MODE_ENABLED = false)</li>
                    <li>✅ Ersten Admin-Benutzer angelegt</li>
                    <li>✅ Mitgliederdaten importiert (falls vorhanden)</li>
                    <li>✅ E-Mail-Konfiguration getestet</li>
                    <li>✅ Backup-Strategie eingerichtet</li>
                    <li>✅ SSL/HTTPS aktiviert</li>
                    <li>✅ Alle Funktionen getestet</li>
                </ol>
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px;">
                <strong>💡 Tipp:</strong> Erstelle vor jeder größeren Änderung ein Backup der Datenbank!
                Verwende dazu phpMyAdmin oder den Befehl: <code>mysqldump -u user -p dbname > backup.sql</code>
            </div>
        </div>
    </div>
</div>

<!-- Dokumentenverwaltung wurde in den Dokumente-Tab verschoben -->
<!-- Datenbank-Wartung -->
<div id="admin-database" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">🔧 Datenbank-Wartung</h3>

    <div class="admin-section-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">

            <!-- Backup & Restore -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #4CAF50;">
                <h4 style="margin-top: 0; color: #4CAF50;">💾 Backup & Restore</h4>
                <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                    Erstelle regelmäßig Sicherungen der Datenbank.
                    Backups können jederzeit wiederhergestellt werden.
                </p>
                <p style="font-size: 13px; color: #999; margin-bottom: 15px;">
                    🔒 Geschützt durch System-Admin-Passwort
                </p>
                <a href="tools/db_backup.php" class="btn" style="background-color: #4CAF50; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; font-weight: 600;" target="_blank">
                    💾 Backup/Restore verwalten
                </a>
            </div>

            <!-- Demo-Daten Analyse -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #2196F3;">
                <h4 style="margin-top: 0; color: #2196F3;">🔍 Demo-Daten Analyse</h4>
                <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                    Analysiert die demo_data.json Datei und zeigt Statistiken
                    über die enthaltenen Datensätze.
                </p>
                <p style="font-size: 13px; color: #999; margin-bottom: 15px;">
                    Nur für Entwicklung und Testing
                </p>
                <a href="tools/demo_analyze.php" class="btn" style="background-color: #2196F3; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; font-weight: 600;" target="_blank">
                    🔍 Demo-Daten analysieren
                </a>
            </div>

            <!-- Tabellen-Migration -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #FF9800;">
                <h4 style="margin-top: 0; color: #FF9800;">🔄 Tabellen-Migration</h4>
                <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                    Migriert Kommentar-Tabellen auf das sv-Präfix Schema.
                    Nur einmalig nach Update ausführen.
                </p>
                <p style="font-size: 13px; color: #999; margin-bottom: 15px;">
                    Wird automatisch geprüft
                </p>
                <a href="tools/migrate_comment_tables.php" class="btn" style="background-color: #FF9800; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; font-weight: 600;" target="_blank">
                    🔄 Migration prüfen
                </a>
            </div>

        </div>

        <div style="margin-top: 20px; padding: 15px; background-color: #e3f2fd; border-left: 4px solid #2196F3; border-radius: 5px;">
            <h5 style="margin-top: 0;">💡 Wichtige Hinweise</h5>
            <ul style="font-size: 14px; margin: 0;">
                <li><strong>Backup:</strong> Erstelle regelmäßig Backups vor wichtigen Änderungen</li>
                <li><strong>Restore:</strong> Beim Wiederherstellen werden ALLE aktuellen Daten überschrieben</li>
                <li><strong>Passwort:</strong> System-Admin-Passwort in config.php konfigurieren</li>
            </ul>
        </div>
    </div>
</div>

<!-- Audit-Log -->
<div id="admin-auditlog" class="admin-section">
    <h3 class="admin-section-header collapsed" onclick="toggleSection(this)">📋 Datenbank-Protokoll (Audit-Log)</h3>
    <div class="admin-section-content collapsed">
<?php
$audit_table_exists = false;
$audit_check = @$pdo->query("SHOW TABLES LIKE 'svaudit_log'");
if ($audit_check && $audit_check->rowCount() > 0) {
    $audit_table_exists = true;
}

if (!$audit_table_exists): ?>
        <div style="padding:15px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;color:#856404;">
            Die Tabelle <code>svaudit_log</code> existiert noch nicht.
            Bitte <a href="tools/migrate_neue_tabellen.php" target="_blank">tools/migrate_neue_tabellen.php</a> ausführen (legt nur die neuen Tabellen an).
        </div>
<?php else:
    // Filter aus GET
    $af_member   = isset($_GET['af_member'])  ? intval($_GET['af_member'])         : 0;
    $af_table    = isset($_GET['af_table'])   ? trim($_GET['af_table'])              : '';
    $af_action   = isset($_GET['af_action'])  ? strtoupper(trim($_GET['af_action'])) : '';
    $af_date_from= isset($_GET['af_from'])    ? trim($_GET['af_from'])               : '';
    $af_date_to  = isset($_GET['af_to'])      ? trim($_GET['af_to'])                 : '';
    $af_limit_raw = isset($_GET['af_limit']) ? (int)$_GET['af_limit'] : 200;
    $af_limit     = in_array($af_limit_raw, [50, 100, 200, 500], true) ? $af_limit_raw : 200;

    // Distinct Tabellennamen für Filterdropdown
    $distinct_tables = $pdo->query("SELECT DISTINCT table_name FROM svaudit_log WHERE table_name IS NOT NULL ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);

    // Mitglieder für Filterdropdown
    $all_members_for_filter = get_all_members($pdo);

    // Abfrage aufbauen
    $where  = ['1=1'];
    $params = [];
    if ($af_member === -1)   { $where[] = 'a.member_id IS NULL'; }
    elseif ($af_member > 0)  { $where[] = 'a.member_id = ?'; $params[] = $af_member; }
    if ($af_table)     { $where[] = 'a.table_name = ?';       $params[] = $af_table; }
    if ($af_action)    { $where[] = 'a.action = ?';           $params[] = $af_action; }
    if ($af_date_from) { $where[] = 'a.logged_at >= ?';       $params[] = $af_date_from . ' 00:00:00'; }
    if ($af_date_to)   { $where[] = 'a.logged_at <= ?';       $params[] = $af_date_to   . ' 23:59:59'; }

    // LIMIT direkt einbetten – MariaDB akzeptiert keinen gebundenen Parameter in LIMIT
    $sql = "SELECT a.* FROM svaudit_log a WHERE " . implode(' AND ', $where)
         . " ORDER BY a.logged_at DESC LIMIT " . $af_limit;
    $audit_stmt = $pdo->prepare($sql);
    $audit_stmt->execute($params);
    $audit_rows = $audit_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mitglied-ID → Name Lookup
    $member_names = [];
    foreach ($all_members_for_filter as $m) {
        $member_names[$m['member_id']] = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
    }

    $total_count_stmt = $pdo->query("SELECT COUNT(*) FROM svaudit_log");
    $total_count = $total_count_stmt ? (int)$total_count_stmt->fetchColumn() : 0;
?>
        <p style="color:#555;margin:0 0 12px 0;">Gesamt: <strong><?php echo number_format($total_count, 0, ',', '.'); ?></strong> Einträge</p>

        <!-- Filterzeile -->
        <form method="GET" action="" style="background:#f5f5f5;padding:12px;border-radius:6px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <input type="hidden" name="tab" value="admin_init">
            <input type="hidden" name="section" value="auditlog">
            <div>
                <label style="font-size:12px;display:block;margin-bottom:3px;">Mitglied</label>
                <select name="af_member" style="padding:5px;font-size:13px;">
                    <option value="0">Alle</option>
                    <?php foreach ($all_members_for_filter as $m): ?>
                        <option value="<?php echo $m['member_id']; ?>" <?php echo $af_member == $m['member_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(trim($m['first_name'] . ' ' . $m['last_name'])); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="-1" <?php echo $af_member === -1 ? 'selected' : ''; ?>>— Cron / extern —</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px;display:block;margin-bottom:3px;">Tabelle</label>
                <select name="af_table" style="padding:5px;font-size:13px;">
                    <option value="">Alle</option>
                    <?php foreach ($distinct_tables as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $af_table === $t ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;display:block;margin-bottom:3px;">Aktion</label>
                <select name="af_action" style="padding:5px;font-size:13px;">
                    <option value="">Alle</option>
                    <?php foreach (['INSERT','UPDATE','DELETE','REPLACE'] as $v): ?>
                        <option value="<?php echo $v; ?>" <?php echo $af_action === $v ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;display:block;margin-bottom:3px;">Von</label>
                <input type="date" name="af_from" value="<?php echo htmlspecialchars($af_date_from); ?>" style="padding:5px;font-size:13px;">
            </div>
            <div>
                <label style="font-size:12px;display:block;margin-bottom:3px;">Bis</label>
                <input type="date" name="af_to" value="<?php echo htmlspecialchars($af_date_to); ?>" style="padding:5px;font-size:13px;">
            </div>
            <div>
                <label style="font-size:12px;display:block;margin-bottom:3px;">Zeilen</label>
                <select name="af_limit" style="padding:5px;font-size:13px;">
                    <?php foreach ([50,100,200,500] as $l): ?>
                        <option value="<?php echo $l; ?>" <?php echo $af_limit === $l ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" style="padding:6px 16px;background:#2196F3;color:white;border:none;border-radius:4px;cursor:pointer;font-size:13px;">Filtern</button>
                <a href="?tab=admin_init#admin-auditlog" style="margin-left:6px;font-size:13px;color:#666;">Zurücksetzen</a>
            </div>
        </form>

        <?php if (empty($audit_rows)): ?>
            <p style="color:#999;">Keine Einträge gefunden.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed;">
            <colgroup>
                <col style="width:145px;"><!-- Zeitpunkt -->
                <col style="width:130px;"><!-- Mitglied -->
                <col style="width:70px;"> <!-- Aktion -->
                <col style="width:160px;"><!-- Tabelle -->
                <col style="width:50px;"> <!-- Zeilen -->
                <col style="width:100px;"><!-- Skript -->
                <col style="width:80px;"> <!-- IP -->
                <col style="width:50px;"> <!-- SQL -->
            </colgroup>
            <thead>
                <tr style="background:#eee;text-align:left;">
                    <th style="padding:7px 8px;white-space:nowrap;">Zeitpunkt</th>
                    <th style="padding:7px 8px;">Mitglied</th>
                    <th style="padding:7px 8px;">Aktion</th>
                    <th style="padding:7px 8px;">Tabelle</th>
                    <th style="padding:7px 8px;text-align:center;">↕</th>
                    <th style="padding:7px 8px;">Skript</th>
                    <th style="padding:7px 8px;">IP</th>
                    <th style="padding:7px 8px;text-align:center;">SQL</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($audit_rows as $row):
                $action_colors = ['DELETE' => '#dc3545', 'INSERT' => '#28a745', 'UPDATE' => '#fd7e14', 'REPLACE' => '#6f42c1'];
                $action_color = $action_colors[$row['action']] ?? '#555';
                $member_label = $row['member_id']
                    ? (isset($member_names[$row['member_id']]) ? htmlspecialchars($member_names[$row['member_id']]) : 'ID ' . $row['member_id'])
                    : '<span style="color:#aaa;">—</span>';
                $script_short = $row['script'] ? basename($row['script']) : '—';
                $td = 'style="padding:5px 8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"';
            ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td <?php echo $td; ?> style="padding:5px 8px;font-family:monospace;font-size:12px;white-space:nowrap;"><?php echo htmlspecialchars(substr($row['logged_at'], 0, 19)); ?></td>
                    <td <?php echo $td; ?>><?php echo $member_label; ?></td>
                    <td style="padding:5px 8px;font-weight:bold;color:<?php echo $action_color; ?>;white-space:nowrap;"><?php echo htmlspecialchars($row['action']); ?></td>
                    <td <?php echo $td; ?> style="padding:5px 8px;font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($row['table_name'] ?? ''); ?>"><?php echo htmlspecialchars($row['table_name'] ?? '—'); ?></td>
                    <td style="padding:5px 8px;text-align:center;"><?php echo (int)$row['affected_rows']; ?></td>
                    <td <?php echo $td; ?> style="padding:5px 8px;font-size:12px;color:#555;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($row['script'] ?? ''); ?>"><?php echo htmlspecialchars($script_short); ?></td>
                    <td <?php echo $td; ?> style="padding:5px 8px;font-family:monospace;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($row['ip_address'] ?? ''); ?>"><?php echo htmlspecialchars($row['ip_address'] ?? '—'); ?></td>
                    <td style="padding:5px 8px;text-align:center;">
                        <?php if ($row['query']): ?>
                            <button onclick="showAuditSQL(<?php echo $row['log_id']; ?>, this)"
                                    data-sql="<?php echo htmlspecialchars($row['query'], ENT_QUOTES); ?>"
                                    style="padding:2px 7px;font-size:11px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;">SQL</button>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="font-size:12px;color:#999;margin-top:8px;">Zeigt <?php echo count($audit_rows); ?> Einträge (neueste zuerst).</p>

        <!-- SQL-Popup -->
        <div id="audit-sql-overlay" onclick="closeAuditSQL()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9998;"></div>
        <div id="audit-sql-popup" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;background:#fff;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.3);width:min(700px,90vw);max-height:80vh;overflow:auto;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <strong style="font-size:14px;">SQL-Abfrage</strong>
                <button onclick="closeAuditSQL()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#666;line-height:1;">×</button>
            </div>
            <pre id="audit-sql-content" style="margin:0;padding:12px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;font-size:12px;white-space:pre-wrap;word-break:break-all;"></pre>
        </div>
        <script>
        function showAuditSQL(id, btn) {
            document.getElementById('audit-sql-content').textContent = btn.dataset.sql;
            document.getElementById('audit-sql-overlay').style.display = 'block';
            document.getElementById('audit-sql-popup').style.display  = 'block';
        }
        function closeAuditSQL() {
            document.getElementById('audit-sql-overlay').style.display = 'none';
            document.getElementById('audit-sql-popup').style.display   = 'none';
        }
        document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeAuditSQL(); });
        </script>
        <?php endif; ?>
<?php endif; ?>
    </div>
</div>
</div>
