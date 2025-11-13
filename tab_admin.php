<?php
/**
 * tab_admin.php - Admin-Verwaltung (Präsentation)
 * Bereinigt: 29.10.2025 02:45 MEZ
 * 
 * Zeigt Admin-Verwaltung an (nur für Admins)
 * Nur Darstellung - alle Verarbeitungen in process_admin.php
 */

// Logik einbinden
require_once 'process_admin.php';
?>

<h2>⚙️ Admin-Verwaltung</h2>

<?php if ($success_message): ?>
    <div class="message"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="admin-warning">
    <strong>⚠️ Achtung:</strong> Diese Seite ist nur für Administratoren (Vorstand/GF) zugänglich.
</div>

<!-- Statistik-Übersicht -->
<div class="info-box" style="margin-bottom: 30px;">
    <strong>📊 Übersicht:</strong> 
    <?php echo $stats['total']; ?> Meetings gesamt 
    (<?php echo $stats['preparation']; ?> in Vorbereitung, 
    <?php echo $stats['active']; ?> aktiv, 
    <?php echo $stats['ended']; ?> beendet, 
    <?php echo $stats['archived']; ?> archiviert) • 
    <?php echo count($members); ?> Mitglieder • 
    <?php echo count($open_todos); ?> offene ToDos
</div>

<!-- Meeting-Verwaltung -->
<div id="admin-meetings" class="admin-section">
    <h3 class="admin-section-header">📅 Meeting-Verwaltung</h3>
    
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Meeting</th>
                <th>Datum</th>
                <th>Status</th>
                <th>Eingeladen von</th>
                <th>Teilnehmer</th>
                <th>TOPs</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($meetings as $meeting): ?>
                <tr>
                    <td><?php echo $meeting['meeting_id']; ?></td>
                    <td><?php echo htmlspecialchars($meeting['meeting_name'] ?? ''); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($meeting['meeting_date'])); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $meeting['status']; ?>">
                            <?php 
                            $status_labels = [
                                'preparation' => '📝 Vorbereitung',
                                'active' => '🟢 Läuft',
                                'ended' => '⏸️ Beendet',
                                'archived' => '📦 Archiviert'
                            ];
                            echo $status_labels[$meeting['status']] ?? $meeting['status'];
                            ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($meeting['inviter_first_name'] . ' ' . $meeting['inviter_last_name']); ?></td>
                    <td><?php echo $meeting['participant_count']; ?></td>
                    <td><?php echo $meeting['agenda_count']; ?></td>
                    <td class="action-buttons">
                        <button class="btn-view" onclick="editMeeting(<?php echo $meeting['meeting_id']; ?>)">✏️</button>
                        <form method="POST" onsubmit="return confirm('Meeting wirklich löschen? Alle TOPs und Kommentare gehen verloren!');">
                            <input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>">
                            <button type="submit" name="delete_meeting" class="btn-delete">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Edit Meeting Modal -->
    <div id="edit-meeting-modal" class="modal">
        <div class="modal-content">
            <h3>Meeting bearbeiten</h3>
            <form method="POST" id="edit-meeting-form">
                <input type="hidden" name="meeting_id" id="edit_meeting_id">
                <div class="form-group">
                    <label>Meeting-Name:</label>
                    <input type="text" name="meeting_name" id="edit_meeting_name" required>
                </div>
                <div class="form-group">
                    <label>Datum & Uhrzeit:</label>
                    <input type="datetime-local" name="meeting_date" id="edit_meeting_date" required>
                </div>
                <div class="form-group">
                    <label>Status:</label>
                    <select name="status" id="edit_status" required>
                        <option value="preparation">Vorbereitung</option>
                        <option value="active">Aktiv</option>
                        <option value="ended">Beendet</option>
                        <option value="archived">Archiviert</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Sitzungsleitung:</label>
                        <select name="chairman_id" id="edit_chairman_id">
                            <option value="">Noch nicht gewählt</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['member_id']; ?>">
                                    <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Protokollführung:</label>
                        <select name="secretary_id" id="edit_secretary_id">
                            <option value="">Noch nicht gewählt</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?php echo $m['member_id']; ?>">
                                    <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="edit_meeting" class="btn-primary">Speichern</button>
                <button type="button" onclick="closeEditMeetingModal()" class="btn-secondary">Abbrechen</button>
            </form>
        </div>
    </div>
</div>

<!-- Mitgliederverwaltung -->
<div id="admin-members" class="admin-section">
    <h3 class="admin-section-header">👥 Mitgliederverwaltung</h3>
    
    <button onclick="showAddMemberForm()" class="btn-primary">+ Neues Mitglied</button>
    
    <!-- Add Member Form -->
    <div id="add-member-form" class="form-box">
        <h4>Neues Mitglied hinzufügen</h4>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Vorname:</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Nachname:</label>
                    <input type="text" name="last_name" required>
                </div>
            </div>
            <div class="form-group">
                <label>E-Mail:</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Rolle:</label>
                    <select name="role" required>
                        <option value="mitglied">Mitglied</option>
                        <option value="assistenz">Assistenz</option>
                        <option value="gf">Geschäftsführung</option>
                        <option value="vorstand">Vorstand</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_admin">
                        <span>Admin-Rechte</span>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label>Passwort:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="add_member" class="btn-primary">Mitglied hinzufügen</button>
            <button type="button" onclick="hideAddMemberForm()" class="btn-secondary">Abbrechen</button>
        </form>
    </div>
    
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>E-Mail</th>
                <th>Rolle</th>
                <th>Admin</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $member): ?>
                <tr>
                    <td><?php echo $member['member_id']; ?></td>
                    <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                    <td><?php echo htmlspecialchars($member['role']); ?></td>
                    <td><?php echo $member['is_admin'] ? '✅' : '❌'; ?></td>
                    <td class="action-buttons">
                        <button class="btn-view" onclick="editMember(<?php echo $member['member_id']; ?>)">✏️</button>
                        <form method="POST" onsubmit="return confirm('Mitglied wirklich löschen?');">
                            <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                            <button type="submit" name="delete_member" class="btn-delete">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Edit Member Modal -->
    <div id="edit-member-modal" class="modal">
        <div class="modal-content">
            <h3>Mitglied bearbeiten</h3>
            <form method="POST" id="edit-member-form">
                <input type="hidden" name="member_id" id="edit_member_id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Vorname:</label>
                        <input type="text" name="first_name" id="edit_first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Nachname:</label>
                        <input type="text" name="last_name" id="edit_last_name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>E-Mail:</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Rolle:</label>
                        <select name="role" id="edit_role" required>
                            <option value="mitglied">Mitglied</option>
                            <option value="assistenz">Assistenz</option>
                            <option value="gf">Geschäftsführung</option>
                            <option value="vorstand">Vorstand</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_admin" id="edit_is_admin">
                            <span>Admin-Rechte</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Neues Passwort (leer lassen um nicht zu ändern):</label>
                    <input type="password" name="password" id="edit_password">
                </div>
                <button type="submit" name="edit_member" class="btn-primary">Speichern</button>
                <button type="button" onclick="closeEditMemberModal()" class="btn-secondary">Abbrechen</button>
            </form>
        </div>
    </div>
</div>

<!-- Offene ToDos -->
<div id="admin-todos" class="admin-section">
    <h3 class="admin-section-header">📝 Offene ToDos</h3>
    
    <?php if (empty($open_todos)): ?>
        <div class="info-box">Keine offenen ToDos vorhanden.</div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Meeting</th>
                    <th>TOP</th>
                    <th>Aufgabe</th>
                    <th>Zugewiesen an</th>
                    <th>Fällig am</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($open_todos as $todo): ?>
                    <tr>
                        <td>
                            <?php if ($todo['meeting_name']): ?>
                                <strong><?php echo htmlspecialchars($todo['meeting_name']); ?></strong><br>
                                <small><?php echo date('d.m.Y', strtotime($todo['meeting_date'])); ?></small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo $todo['agenda_title'] ? htmlspecialchars($todo['agenda_title']) : '-'; ?></td>
                        <td>
                            <?php if (!empty($todo['title'])): ?>
                                <strong><?php echo htmlspecialchars($todo['title']); ?></strong><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($todo['description']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($todo['first_name'] . ' ' . $todo['last_name']); ?></td>
                        <td><?php echo $todo['due_date'] ? date('d.m.Y', strtotime($todo['due_date'])) : '-'; ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('ToDo als erledigt markieren?');">
                                <input type="hidden" name="todo_id" value="<?php echo $todo['todo_id']; ?>">
                                <button type="submit" name="close_todo" class="btn-primary">✓ Erledigt</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Admin-Protokoll -->
<div id="admin-log" class="admin-section">
    <h3 class="admin-section-header">📋 Admin-Protokoll (letzte 50 Aktionen)</h3>
    
    <?php if (empty($admin_logs)): ?>
        <div class="info-box">Keine Admin-Aktionen protokolliert.</div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Admin</th>
                    <th>Aktion</th>
                    <th>Beschreibung</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admin_logs as $log): ?>
                    <tr>
                        <td><?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></td>
                        <td>
                            <span class="action-badge action-<?php echo str_replace('_', '-', $log['action_type']); ?>">
                                <?php 
                                $action_labels = [
                                    'meeting_create' => '➕ Meeting erstellt',
                                    'meeting_edit' => '✏️ Meeting bearbeitet',
                                    'meeting_delete' => '🗑️ Meeting gelöscht',
                                    'member_create' => '➕ Mitglied erstellt',
                                    'member_edit' => '✏️ Mitglied bearbeitet',
                                    'member_delete' => '🗑️ Mitglied gelöscht',
                                    'todo_close' => '✅ ToDo geschlossen'
                                ];
                                echo $action_labels[$log['action_type']] ?? $log['action_type'];
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                        <td>
                            <?php if ($log['old_values'] || $log['new_values']): ?>
                                <button class="btn-view" onclick="showLogDetails(<?php echo $log['log_id']; ?>)">
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
</div>

<!-- Log-Details Modal -->
<div id="log-details-modal" class="modal">
    <div class="modal-content">
        <h3>Admin-Aktion Details</h3>
        <div id="log-details-content"></div>
        <button type="button" onclick="closeLogDetailsModal()" class="btn-secondary">Schließen</button>
    </div>
</div>

<script>
// Meeting bearbeiten
function editMeeting(meetingId) {
    const meetings = <?php echo json_encode($meetings); ?>;
    const meeting = meetings.find(m => m.meeting_id == meetingId);
    
    if (meeting) {
        document.getElementById('edit_meeting_id').value = meeting.meeting_id;
        document.getElementById('edit_meeting_name').value = meeting.meeting_name;
        document.getElementById('edit_meeting_date').value = meeting.meeting_date.replace(' ', 'T').substring(0, 16);
        document.getElementById('edit_status').value = meeting.status;
        document.getElementById('edit_chairman_id').value = meeting.chairman_member_id || '';
        document.getElementById('edit_secretary_id').value = meeting.secretary_member_id || '';
        document.getElementById('edit-meeting-modal').classList.add('show');
    }
}

function closeEditMeetingModal() {
    document.getElementById('edit-meeting-modal').classList.remove('show');
}

// Mitglied bearbeiten
function editMember(memberId) {
    const members = <?php echo json_encode($members); ?>;
    const member = members.find(m => m.member_id == memberId);
    
    if (member) {
        document.getElementById('edit_member_id').value = member.member_id;
        document.getElementById('edit_first_name').value = member.first_name;
        document.getElementById('edit_last_name').value = member.last_name;
        document.getElementById('edit_email').value = member.email;
        document.getElementById('edit_role').value = member.role;
        document.getElementById('edit_is_admin').checked = member.is_admin == 1;
        document.getElementById('edit_password').value = '';
        document.getElementById('edit-member-modal').classList.add('show');
    }
}

function closeEditMemberModal() {
    document.getElementById('edit-member-modal').classList.remove('show');
}

// Mitglied hinzufügen
function showAddMemberForm() {
    document.getElementById('add-member-form').classList.add('show');
}

function hideAddMemberForm() {
    document.getElementById('add-member-form').classList.remove('show');
}

// Log-Details
function showLogDetails(logId) {
    const logs = <?php echo json_encode($admin_logs); ?>;
    const log = logs.find(l => l.log_id == logId);
    
    if (log) {
        let html = '<div class="log-details">';
        
        if (log.old_values) {
            html += '<h4>Vorher:</h4>';
            html += '<pre>' + JSON.stringify(JSON.parse(log.old_values), null, 2) + '</pre>';
        }
        
        if (log.new_values) {
            html += '<h4>Nachher:</h4>';
            html += '<pre>' + JSON.stringify(JSON.parse(log.new_values), null, 2) + '</pre>';
        }
        
        if (log.ip_address) {
            html += '<p><strong>IP-Adresse:</strong> ' + log.ip_address + '</p>';
        }
        if (log.user_agent) {
            html += '<p><strong>Browser:</strong> ' + log.user_agent + '</p>';
        }
        
        html += '</div>';
        document.getElementById('log-details-content').innerHTML = html;
        document.getElementById('log-details-modal').classList.add('show');
    }
}

function closeLogDetailsModal() {
    document.getElementById('log-details-modal').classList.remove('show');
}
</script>