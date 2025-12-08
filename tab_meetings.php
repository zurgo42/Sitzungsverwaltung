<?php
/**
 * tab_meetings.php - Sitzungs-Verwaltung (Präsentation)
 * Bereinigt: 29.10.2025 02:00 MEZ
 * 
 * Zeigt Sitzungs-Liste und Erstellungs-Formular
 * Nur Darstellung - alle Verarbeitungen in process_meetings.php
 */

// Nur sichtbare Meetings laden (basierend auf Sichtbarkeitstyp)
$all_meetings = get_visible_meetings($pdo, $current_user['member_id']);
$all_members = get_all_members($pdo);

// Abwesenheiten für alle Mitglieder laden (zukünftig und aktuell)
$stmt_member_absences = $pdo->query("
    SELECT a.*, s.first_name AS sub_first_name, s.last_name AS sub_last_name
    FROM svabsences a
    LEFT JOIN svmembers s ON a.substitute_member_id = s.member_id
    WHERE a.end_date >= CURDATE()
");
$all_absences_raw = $stmt_member_absences->fetchAll();

// Abwesenheiten nach member_id gruppieren
$member_absences = [];
foreach ($all_absences_raw as $abs) {
    if (!isset($member_absences[$abs['member_id']])) {
        $member_absences[$abs['member_id']] = [];
    }
    $member_absences[$abs['member_id']][] = $abs;
}

// Benachrichtigungsmodul laden
require_once 'module_notifications.php';
?>

<style>
/* Kompaktere Sitzungs-Cards */
.meeting-card {
    padding: 12px !important;
    margin-bottom: 15px !important;
}
.meeting-card-header {
    padding: 0 !important;
}
.meeting-card-content {
    padding: 0 !important;
    margin-bottom: 8px !important;
}
.agenda-title {
    margin-bottom: 8px !important;
}
.agenda-meta {
    margin-top: 4px !important;
    line-height: 1.4 !important;
}
.meeting-card-actions {
    margin-top: 10px !important;
    gap: 8px !important;
}
.meeting-edit-section,
.meeting-start-section {
    padding: 12px !important;
    margin-top: 12px !important;
}
</style>

<h2>🤝 Sitzungen verwalten</h2>

<!-- BENACHRICHTIGUNGEN -->
<?php render_user_notifications($pdo, $current_user['member_id']); ?>

<?php if (isset($_GET['success'])): ?>
    <div class="message">
        <?php 
        switch($_GET['success']) {
            case 'created': echo '✅ Sitzung erfolgreich erstellt!'; break;
            case 'deleted': echo '✅ Sitzung erfolgreich gelöscht!'; break;
            case 'updated': echo '✅ Sitzung erfolgreich aktualisiert!'; break;
            default: echo '✅ Aktion erfolgreich durchgeführt!';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="error-message">
        <?php 
        switch($_GET['error']) {
            case 'permission': echo '❌ Keine Berechtigung für diese Aktion.'; break;
            case 'delete_failed': echo '❌ Fehler beim Löschen der Sitzung.'; break;
            case 'update_failed': echo '❌ Fehler beim Aktualisieren der Sitzung.'; break;
            case 'start_failed': echo '❌ Fehler beim Starten der Sitzung.'; break;
            case 'create_failed': echo '❌ Fehler beim Erstellen der Sitzung.'; break;
            case 'missing_data': echo '❌ Pflichtfelder fehlen.'; break;
            case 'invalid_id': echo '❌ Ungültige Sitzungs-ID.'; break;
            default: echo '❌ Ein Fehler ist aufgetreten.';
        }
        ?>
    </div>
<?php endif; ?>

<!-- Neue Sitzung erstellen (nicht für Mitglied-Rolle) -->
<?php if (strtolower($current_user['role']) !== 'mitglied'): ?>
<div style="margin-bottom: 30px;">
    <button class="accordion-button" onclick="toggleAccordion(this)">➕ Neue Sitzung erstellen</button>
    <div class="accordion-content">
        <form method="POST" action="process_meetings.php">
            <input type="hidden" name="create_meeting" value="1">
            
            <div class="form-group">
                <label>Sitzungs-Name:</label>
                <input type="text" name="meeting_name" value="<?php echo htmlspecialchars(DEFAULT_MEETING_NAME); ?>" required>
            </div>
            
            <div class="meeting-form-grid-2">
                <div class="form-group">
                    <label>Datum:</label>
                    <input type="date" name="meeting_date_only" id="meeting_date_only" required onchange="combineDateTime()">
                </div>
                <div class="form-group">
                    <label>Uhrzeit:</label>
                    <input type="time" name="meeting_time_only" id="meeting_time_only" required onchange="combineDateTime()">
                </div>
            </div>
            <input type="hidden" name="meeting_date" id="meeting_date">
            
            <div class="form-group">
                <label>Voraussichtliches Ende:</label>
                <input type="datetime-local" name="expected_end_date" id="expected_end_date">
            </div>

            <div class="form-group">
                <label>Antragsschluss:</label>
                <input type="datetime-local" name="submission_deadline" id="submission_deadline">
                <small style="display: block; margin-top: 5px; color: #666;">
                    Bis zu diesem Zeitpunkt dürfen Teilnehmer neue Tagesordnungspunkte hinzufügen.<br>
                    Standard: 24 Stunden vor Sitzungsbeginn. Danach können nur noch Protokollant und Admins TOPs hinzufügen.
                </small>
            </div>

            <div class="form-group">
                <label>Ort:</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars(DEFAULT_LOCATION); ?>">
            </div>
            
            <div class="form-group">
                <label>Videokonferenz-Link:</label>
                <input type="url" name="video_link" value="<?php echo htmlspecialchars(DEFAULT_VIDEO_LINK); ?>">
            </div>
            
            <div class="meeting-form-grid-equal">
                <div class="form-group">
                    <label>Vorläufiger Vorschlag Sitzungsleitung:</label>
                    <select name="chairman_member_id">
                        <option value="">- Optional -</option>
                        <?php foreach ($all_members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Vorläufiger Vorschlag Protokollführung:</label>
                    <select name="secretary_member_id">
                        <option value="">- Optional -</option>
                        <?php foreach ($all_members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Sichtbarkeit:</label>
                <select name="visibility_type">
                    <option value="invited_only">Nur Eingeladene</option>
                    <option value="authenticated">Alle angemeldeten Mitglieder</option>
                    <option value="public">Öffentlich</option>
                </select>
                <small style="display: block; margin-top: 5px; color: #666;">
                    • <strong>Nur Eingeladene:</strong> Nur ausgewählte Teilnehmer sehen diese Sitzung<br>
                    • <strong>Alle angemeldeten:</strong> Alle eingeloggten Mitglieder sehen diese Sitzung (read-only, Teilnehmer haben volle Rechte)<br>
                    • <strong>Öffentlich:</strong> Auch der Spezial-User "Mitglied alle" kann diese Sitzung sehen (read-only, Teilnehmer haben volle Rechte)
                </small>
            </div>

            <div class="form-group">
                <label>Teilnehmer auswählen:</label>
                <div class="participant-buttons">
                    <button type="button" onclick="toggleAllParticipants(true)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✓ Alle auswählen</button>
                    <button type="button" onclick="toggleAllParticipants(false)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✗ Alle abwählen</button>
                    <button type="button" onclick="toggleLeadershipRoles()" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">👔 Führungsrollen</button>
                    <button type="button" onclick="toggleTopManagement()" class="btn-secondary" style="padding: 5px 10px;">⭐ Vorstand+GF+Ass</button>
                </div>
                <div class="participants-selector">
                    <?php foreach ($all_members as $member):
                        $has_absence = isset($member_absences[$member['member_id']]);
                    ?>
                        <label class="participant-label" style="<?php echo $has_absence ? 'background: #fff3cd; border-left: 3px solid #ffc107;' : ''; ?>">
                            <input type="checkbox"
                                   name="participant_ids[]"
                                   value="<?php echo $member['member_id']; ?>"
                                   class="participant-checkbox"
                                   data-role="<?php echo htmlspecialchars($member['role']); ?>">
                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                            <?php if ($has_absence): ?>
                                <br><small style="color: #856404;">
                                    <?php foreach ($member_absences[$member['member_id']] as $abs): ?>
                                        🏖️ <?php echo date('d.m.', strtotime($abs['start_date'])); ?> - <?php echo date('d.m.', strtotime($abs['end_date'])); ?>
                                        <?php if ($abs['substitute_member_id']): ?>
                                            (Vertr.: <?php echo htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']); ?>)
                                        <?php endif; ?>
                                        <br>
                                    <?php endforeach; ?>
                                </small>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit">Meeting erstellen</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Sitzungs-Liste -->
<h3>Bestehende Sitzungen</h3>
<?php if (empty($all_meetings)): ?>
    <div class="info-box">Noch keine Sitzungen vorhanden.</div>
<?php else: ?>
    <?php
    // TOP-Counts für alle Meetings laden
    $meeting_ids = array_column($all_meetings, 'meeting_id');
    $top_counts = [];
    if (!empty($meeting_ids)) {
        $placeholders = str_repeat('?,', count($meeting_ids) - 1) . '?';
        $stmt_tops = $pdo->prepare("
            SELECT meeting_id, COUNT(*) as top_count
            FROM svagenda_items
            WHERE meeting_id IN ($placeholders)
            GROUP BY meeting_id
        ");
        $stmt_tops->execute($meeting_ids);
        foreach ($stmt_tops->fetchAll() as $row) {
            $top_counts[$row['meeting_id']] = $row['top_count'];
        }
    }
    ?>
    <?php foreach ($all_meetings as $m):
        $status_class = 'meeting-card status-' . $m['status'];
        $is_creator = ($m['invited_by_member_id'] == $current_user['member_id']);
        $is_admin = in_array($current_user['role'], ['assistenz', 'gf']);
        $can_edit = ($is_creator || $is_admin) && $m['status'] === 'preparation';

        // Rote Umrandung für ausstehende Aufgaben
        $is_secretary = ($m['secretary_member_id'] == $current_user['member_id']);
        $is_chairman = ($m['chairman_member_id'] == $current_user['member_id']);
        $needs_protocol_completion = ($m['status'] === 'ended' && $is_secretary);
        $needs_protocol_approval = ($m['status'] === 'protocol_ready' && $is_chairman);
        $red_border_style = ($needs_protocol_completion || $needs_protocol_approval) ? ' style="border: 3px solid #f44336;"' : '';
    ?>

        <div class="<?php echo $status_class; ?>"<?php echo $red_border_style; ?>>
            <div class="meeting-card-header">
                <div class="meeting-card-content">
                    <div class="agenda-title">
                        <?php if (!empty($m['meeting_name'])): ?>
                            <strong><?php echo htmlspecialchars($m['meeting_name']); ?></strong><br>
                        <?php endif; ?>
                        Termin am <?php echo date('d.m.Y H:i', strtotime($m['meeting_date'])); ?>
                        <?php if (in_array($m['status'], ['ended', 'protocol_ready', 'archived']) && !empty($m['meeting_end_date'])): ?>
                            <br><small>Ende der Sitzung: <?php echo date('d.m.Y H:i', strtotime($m['meeting_end_date'])); ?></small>
                        <?php endif; ?>
                        <?php if ($m['status'] === 'preparation' && !empty($m['submission_deadline'])): ?>
                            <?php
                            $deadline_passed = (strtotime($m['submission_deadline']) < time());
                            if ($deadline_passed) {
                                // Antragsschluss überschritten - kleinerer, dezenterer Text
                                echo '<br><small style="color: #999; font-size: 0.85em;">(Antragsschluss war ' . date('d.m.Y H:i', strtotime($m['submission_deadline'])) . ' Uhr)</small>';
                            } else {
                                // Antragsschluss noch offen - prominent
                                echo '<br><small style="color: #2196f3; font-weight: 600;">⏰ Antragsschluss: ' . date('d.m.Y H:i', strtotime($m['submission_deadline'])) . ' Uhr</small>';
                            }
                            ?>
                        <?php endif; ?>
                    </div>
                    <div class="agenda-meta">
                        <?php
                        if (!empty($m['location'])) {
                            echo '📍 Ort: ' . htmlspecialchars($m['location']);
                        }
                        if (!empty($m['video_link']) && in_array($m['status'], ['preparation', 'active'])) {
                            echo '<br>🎥 <a href="' . htmlspecialchars($m['video_link']) . '" target="_blank" class="video-link">' . htmlspecialchars($m['video_link']) . '</a>';
                        }
                        // TOP-Anzahl anzeigen
                        $top_count = $top_counts[$m['meeting_id']] ?? 0;
                        echo '<br>📋 TOPs: ' . $top_count;

                        if (in_array($m['status'], ['preparation', 'active'])) {
                            echo '<br>Eingeladen von: ' . htmlspecialchars($m['first_name'] . ' ' . $m['last_name']);
                        }
                        ?>
                        <br><strong>Status:</strong>
                        <span class="meeting-status-badge <?php echo $m['status']; ?>">
                            <?php
                            switch($m['status']) {
                                case 'preparation': echo '📝 In Vorbereitung'; break;
                                case 'active': echo '🟢 Sitzung läuft'; break;
                                case 'ended': echo '✅ Sitzung geschlossen'; break;
                                case 'protocol_ready': echo '⏳ Protokoll wartet auf Genehmigung'; break;
                                case 'archived': echo '📦 Archiviert'; break;
                                default: echo htmlspecialchars($m['status']);
                            }
                            ?>
                        </span>
                        <?php if ($needs_protocol_completion): ?>
                            <br><strong style="color: #f44336;">⚠️ Fertigstellung des Protokolls steht aus</strong>
                        <?php elseif ($needs_protocol_approval): ?>
                            <br><strong style="color: #f44336;">⚠️ Genehmigung des Protokolls steht aus</strong>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="meeting-card-actions">
                    <?php if ($can_edit): ?>
                        <button type="button" onclick="toggleEditMeeting(<?php echo $m['meeting_id']; ?>)" class="btn-view">✏️ Bearbeiten</button>
                        <button type="button" onclick="if(confirm('Meeting wirklich löschen?')) deleteMeeting(<?php echo $m['meeting_id']; ?>)" class="btn-delete">🗑️ Löschen</button>
                        <button type="button" onclick="toggleStartMeeting(<?php echo $m['meeting_id']; ?>)" style="background: #4caf50; color: white;">▶️ Starten</button>
                    <?php endif; ?>
                    <a href="?tab=agenda&meeting_id=<?php echo $m['meeting_id']; ?>">
                        <button>
                            <?php echo in_array($m['status'], ['active', 'preparation']) ? 'Tagesordnung öffnen →' : 'Zum Sitzungsverlauf →'; ?>
                        </button>
                    </a>
                </div>
            </div>
            
            <?php if ($can_edit): ?>
                <!-- Meeting bearbeiten -->
                <div id="edit-meeting-<?php echo $m['meeting_id']; ?>" class="meeting-edit-section">
                    <h4>✏️ Meeting bearbeiten</h4>
                    <form method="POST" action="process_meetings.php">
                        <input type="hidden" name="edit_meeting" value="1">
                        <input type="hidden" name="meeting_id" value="<?php echo $m['meeting_id']; ?>">
                        
                        <div class="form-group">
                            <label>Sitzungs-Name:</label>
                            <input type="text" name="meeting_name" value="<?php echo htmlspecialchars($m['meeting_name']); ?>" required>
                        </div>
                        
                        <div class="meeting-form-grid-2">
                            <div class="form-group">
                                <label>Datum:</label>
                                <input type="date" name="meeting_date_only" id="meeting_date_only_<?php echo $m['meeting_id']; ?>" value="<?php echo date('Y-m-d', strtotime($m['meeting_date'])); ?>" required onchange="combineDateTimeEdit(<?php echo $m['meeting_id']; ?>)">
                            </div>
                            <div class="form-group">
                                <label>Uhrzeit:</label>
                                <input type="time" name="meeting_time_only" id="meeting_time_only_<?php echo $m['meeting_id']; ?>" value="<?php echo date('H:i', strtotime($m['meeting_date'])); ?>" required onchange="combineDateTimeEdit(<?php echo $m['meeting_id']; ?>)">
                            </div>
                        </div>
                        <input type="hidden" name="meeting_date" id="meeting_date_<?php echo $m['meeting_id']; ?>" value="<?php echo date('Y-m-d\TH:i', strtotime($m['meeting_date'])); ?>">
                        
                        <div class="form-group">
                            <label>Voraussichtliches Ende:</label>
                            <input type="datetime-local" name="expected_end_date" value="<?php echo $m['expected_end_date'] ? date('Y-m-d\TH:i', strtotime($m['expected_end_date'])) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label>Antragsschluss:</label>
                            <input type="datetime-local" name="submission_deadline" value="<?php echo $m['submission_deadline'] ? date('Y-m-d\TH:i', strtotime($m['submission_deadline'])) : ''; ?>">
                            <small style="display: block; margin-top: 5px; color: #666;">
                                Bis zu diesem Zeitpunkt dürfen Teilnehmer neue Tagesordnungspunkte hinzufügen.<br>
                                Standard: 24 Stunden vor Sitzungsbeginn. Danach können nur noch Protokollant und Admins TOPs hinzufügen.
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Ort:</label>
                            <input type="text" name="location" value="<?php echo htmlspecialchars($m['location']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Videokonferenz-Link:</label>
                            <input type="url" name="video_link" value="<?php echo htmlspecialchars($m['video_link']); ?>">
                        </div>
                        
                        <div class="meeting-form-grid-equal">
                            <div class="form-group">
                                <label>Vorschlag Sitzungsleitung:</label>
                                <select name="chairman_member_id">
                                    <option value="">- Optional -</option>
                                    <?php foreach ($all_members as $member): ?>
                                        <option value="<?php echo $member['member_id']; ?>" <?php echo ($m['chairman_member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Vorschlag Protokollführung:</label>
                                <select name="secretary_member_id">
                                    <option value="">- Optional -</option>
                                    <?php foreach ($all_members as $member): ?>
                                        <option value="<?php echo $member['member_id']; ?>" <?php echo ($m['secretary_member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Sichtbarkeit:</label>
                            <select name="visibility_type">
                                <option value="invited_only" <?php echo ($m['visibility_type'] ?? 'invited_only') === 'invited_only' ? 'selected' : ''; ?>>Nur Eingeladene</option>
                                <option value="authenticated" <?php echo ($m['visibility_type'] ?? 'invited_only') === 'authenticated' ? 'selected' : ''; ?>>Alle angemeldeten Mitglieder</option>
                                <option value="public" <?php echo ($m['visibility_type'] ?? 'invited_only') === 'public' ? 'selected' : ''; ?>>Öffentlich (nur für User "Mitglied alle")</option>
                            </select>
                            <small style="display: block; margin-top: 5px; color: #666;">
                                • <strong>Nur Eingeladene:</strong> Nur ausgewählte Teilnehmer sehen diese Sitzung<br>
                                • <strong>Alle angemeldeten:</strong> Alle Members sehen diese Sitzung<br>
                                • <strong>Öffentlich:</strong> Nur der Spezial-User "Mitglied alle" sieht diese Sitzung (read-only)
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Teilnehmer auswählen:</label>
                            <div class="participant-buttons">
                                <button type="button" onclick="toggleAllParticipantsEdit(<?php echo $m['meeting_id']; ?>, true)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✓ Alle auswählen</button>
                                <button type="button" onclick="toggleAllParticipantsEdit(<?php echo $m['meeting_id']; ?>, false)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✗ Alle abwählen</button>
                                <button type="button" onclick="toggleLeadershipRolesEdit(<?php echo $m['meeting_id']; ?>)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">👔 Führungsrollen</button>
                                <button type="button" onclick="toggleTopManagementEdit(<?php echo $m['meeting_id']; ?>)" class="btn-secondary" style="padding: 5px 10px;">⭐ Vorstand+GF+Ass</button>
                            </div>
                            <div class="participants-selector">
                                <?php
                                $stmt_current_participants = $pdo->prepare("SELECT member_id FROM svmeeting_participants WHERE meeting_id = ?");
                                $stmt_current_participants->execute([$m['meeting_id']]);
                                $current_participant_ids = $stmt_current_participants->fetchAll(PDO::FETCH_COLUMN);

                                foreach ($all_members as $member):
                                    $is_participant = in_array($member['member_id'], $current_participant_ids);
                                    $has_absence = isset($member_absences[$member['member_id']]);
                                ?>
                                    <label class="participant-label" style="<?php echo $has_absence ? 'background: #fff3cd; border-left: 3px solid #ffc107;' : ''; ?>">
                                        <input type="checkbox"
                                               name="participant_ids[]"
                                               value="<?php echo $member['member_id']; ?>"
                                               class="participant-checkbox-<?php echo $m['meeting_id']; ?>"
                                               data-role="<?php echo htmlspecialchars($member['role']); ?>"
                                               <?php echo $is_participant ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                                        <?php if ($has_absence): ?>
                                            <br><small style="color: #856404;">
                                                <?php foreach ($member_absences[$member['member_id']] as $abs): ?>
                                                    🏖️ <?php echo date('d.m.', strtotime($abs['start_date'])); ?> - <?php echo date('d.m.', strtotime($abs['end_date'])); ?>
                                                    <?php if ($abs['substitute_member_id']): ?>
                                                        (Vertr.: <?php echo htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']); ?>)
                                                    <?php endif; ?>
                                                    <br>
                                                <?php endforeach; ?>
                                            </small>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit">Änderungen speichern</button>
                        <button type="button" onclick="toggleEditMeeting(<?php echo $m['meeting_id']; ?>)" class="btn-secondary" style="margin-left: 10px;">Abbrechen</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <?php if ($can_edit): ?>
                <!-- Sitzung starten -->
                <div id="start-meeting-<?php echo $m['meeting_id']; ?>" class="meeting-start-section">
                    <h4>🚀 Sitzung starten</h4>
                    <form method="POST" action="process_meetings.php">
                        <input type="hidden" name="start_meeting" value="1">
                        <input type="hidden" name="meeting_id" value="<?php echo $m['meeting_id']; ?>">
                        
                        <?php
                        $stmt_participants = $pdo->prepare("
                            SELECT m.member_id, m.first_name, m.last_name 
                            FROM svmeeting_participants mp
                            JOIN svmembers m ON mp.member_id = m.member_id
                            WHERE mp.meeting_id = ?
                            ORDER BY m.last_name, m.first_name
                        ");
                        $stmt_participants->execute([$m['meeting_id']]);
                        $participants = $stmt_participants->fetchAll();
                        ?>
                        
                        <div class="meeting-form-grid-equal">
                            <div class="form-group">
                                <label>Sitzungsleitung:</label>
                                <select name="chairman_member_id" required>
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($participants as $p): ?>
                                        <option value="<?php echo $p['member_id']; ?>" <?php echo ($m['chairman_member_id'] == $p['member_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Protokollführung:</label>
                                <select name="secretary_member_id" required>
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($participants as $p): ?>
                                        <option value="<?php echo $p['member_id']; ?>" <?php echo ($m['secretary_member_id'] == $p['member_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-start-meeting">▶️ Sitzung jetzt starten</button>
                        <button type="button" onclick="toggleStartMeeting(<?php echo $m['meeting_id']; ?>)" class="btn-secondary" style="width: 100%; margin-top: 10px;">Abbrechen</button>
                    </form>
                </div>
            <?php endif; ?>
            
        </div><!-- Ende meeting-card -->
        
    <?php endforeach; ?>
<?php endif; ?>

<script>
function toggleAllParticipants(checked) {
    const checkboxes = document.querySelectorAll('.participant-checkbox');
    checkboxes.forEach(cb => cb.checked = checked);
}

function toggleAllParticipantsEdit(meetingId, checked) {
    const checkboxes = document.querySelectorAll('.participant-checkbox-' + meetingId);
    checkboxes.forEach(cb => cb.checked = checked);
}

// Wählt nur Führungsrollen aus (alle außer "Mitglied")
function toggleLeadershipRoles() {
    const checkboxes = document.querySelectorAll('.participant-checkbox');
    checkboxes.forEach(cb => {
        const role = cb.getAttribute('data-role');
        cb.checked = (role !== 'Mitglied');
    });
}

function toggleLeadershipRolesEdit(meetingId) {
    const checkboxes = document.querySelectorAll('.participant-checkbox-' + meetingId);
    checkboxes.forEach(cb => {
        const role = cb.getAttribute('data-role');
        cb.checked = (role !== 'Mitglied');
    });
}

// Wählt nur Vorstand, Geschäftsführung und Assistenz aus
function toggleTopManagement() {
    const checkboxes = document.querySelectorAll('.participant-checkbox');
    const topRoles = ['Vorstand', 'Geschäftsführung', 'Assistenz'];
    checkboxes.forEach(cb => {
        const role = cb.getAttribute('data-role');
        cb.checked = topRoles.includes(role);
    });
}

function toggleTopManagementEdit(meetingId) {
    const checkboxes = document.querySelectorAll('.participant-checkbox-' + meetingId);
    const topRoles = ['Vorstand', 'Geschäftsführung', 'Assistenz'];
    checkboxes.forEach(cb => {
        const role = cb.getAttribute('data-role');
        cb.checked = topRoles.includes(role);
    });
}

function combineDateTime() {
    const dateInput = document.getElementById('meeting_date_only');
    const timeInput = document.getElementById('meeting_time_only');
    const hiddenInput = document.getElementById('meeting_date');
    
    if (dateInput.value && timeInput.value) {
        hiddenInput.value = dateInput.value + 'T' + timeInput.value;
        updateEndDateTime();
    }
}

function combineDateTimeEdit(meetingId) {
    const dateInput = document.getElementById('meeting_date_only_' + meetingId);
    const timeInput = document.getElementById('meeting_time_only_' + meetingId);
    const hiddenInput = document.getElementById('meeting_date_' + meetingId);
    
    if (dateInput.value && timeInput.value) {
        hiddenInput.value = dateInput.value + 'T' + timeInput.value;
    }
}

function updateEndDateTime() {
    const hiddenInput = document.getElementById('meeting_date');
    const endInput = document.getElementById('expected_end_date');
    const submissionInput = document.getElementById('submission_deadline');

    if (hiddenInput.value) {
        const startDate = new Date(hiddenInput.value);

        // Voraussichtliches Ende: +2 Stunden
        const endDate = new Date(startDate);
        endDate.setHours(endDate.getHours() + 2);

        const year = endDate.getFullYear();
        const month = String(endDate.getMonth() + 1).padStart(2, '0');
        const day = String(endDate.getDate()).padStart(2, '0');
        const hours = String(endDate.getHours()).padStart(2, '0');
        const minutes = String(endDate.getMinutes()).padStart(2, '0');

        endInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;

        // Antragsschluss: -24 Stunden vor Start
        const submissionDate = new Date(startDate);
        submissionDate.setHours(submissionDate.getHours() - 24);

        const subYear = submissionDate.getFullYear();
        const subMonth = String(submissionDate.getMonth() + 1).padStart(2, '0');
        const subDay = String(submissionDate.getDate()).padStart(2, '0');
        const subHours = String(submissionDate.getHours()).padStart(2, '0');
        const subMinutes = String(submissionDate.getMinutes()).padStart(2, '0');

        submissionInput.value = `${subYear}-${subMonth}-${subDay}T${subHours}:${subMinutes}`;
    }
}

function deleteMeeting(meetingId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'process_meetings.php';
    
    const input1 = document.createElement('input');
    input1.type = 'hidden';
    input1.name = 'delete_meeting';
    input1.value = '1';
    
    const input2 = document.createElement('input');
    input2.type = 'hidden';
    input2.name = 'meeting_id';
    input2.value = meetingId;
    
    form.appendChild(input1);
    form.appendChild(input2);
    document.body.appendChild(form);
    form.submit();
}

function toggleEditMeeting(meetingId) {
    const editDiv = document.getElementById('edit-meeting-' + meetingId);
    const startDiv = document.getElementById('start-meeting-' + meetingId);
    
    if (startDiv && startDiv.style.display === 'block') {
        startDiv.style.display = 'none';
    }
    
    if (editDiv.style.display === 'none' || editDiv.style.display === '') {
        editDiv.style.display = 'block';
    } else {
        editDiv.style.display = 'none';
    }
}

function toggleStartMeeting(meetingId) {
    const editDiv = document.getElementById('edit-meeting-' + meetingId);
    const startDiv = document.getElementById('start-meeting-' + meetingId);
    
    if (editDiv && editDiv.style.display === 'block') {
        editDiv.style.display = 'none';
    }
    
    if (startDiv.style.display === 'none' || startDiv.style.display === '') {
        startDiv.style.display = 'block';
    } else {
        startDiv.style.display = 'none';
    }
}
</script>