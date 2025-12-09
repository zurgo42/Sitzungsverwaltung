<?php

// Benachrichtigungsmodul laden
require_once 'module_notifications.php';
/**
 * tab_admin.php - Admin-Verwaltung (Präsentation)
 * Bereinigt: 29.10.2025 02:45 MEZ
 *
 * Zeigt Admin-Verwaltung an (nur für Admins)
 * Nur Darstellung - alle Verarbeitungen in process_admin.php
 */

// Logik einbinden
require_once 'process_admin.php';

// Dokumente-Funktionen einbinden
require_once __DIR__ . '/documents_functions.php';

// Alle Dokumente laden (Admin-Zugriff: Zugriffslevel 99)
$all_documents = get_documents($pdo, ['status' => 'active'], 99);
?>

<style>
/* Hellere, besser lesbare Überschriften */
.admin-section-header {
    color: #333 !important;
    background: #ffc107 !important;
    padding: 8px 15px !important;
    border-radius: 6px !important;
    margin-bottom: 10px !important;
    cursor: pointer !important;
    user-select: none !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    font-size: 15px !important;
    font-weight: 600 !important;
}

.admin-section-header:hover {
    background: #ffb300 !important;
}

.admin-section-header::after {
    content: '▼';
    transition: transform 0.3s;
    font-size: 12px;
}

.admin-section-header.collapsed::after {
    transform: rotate(-90deg);
}

.admin-section-content {
    max-height: 2000px;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    font-size: 13px;
}

.admin-section-content.collapsed {
    max-height: 0;
}

.admin-section-content table {
    font-size: 13px;
}

.admin-section-content h3,
.admin-section-content h4 {
    font-size: 16px;
}

/* Kompaktere Logfile-Darstellung */
.compact-log-table {
    font-size: 12px !important;
}

.compact-log-table td {
    padding: 6px 8px !important;
}
</style>

<!-- BENACHRICHTIGUNGEN -->
<?php render_user_notifications($pdo, $current_user['member_id']); ?>

<h2>⚙️ Admin-Verwaltung</h2>


<?php if ($success_message): ?>

    <div class="message"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>


<?php if ($error_message): ?>

    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>


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
    <h3 class="admin-section-header" onclick="toggleSection(this)">📅 Meeting-Verwaltung</h3>

    <div class="admin-section-content">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Meeting</th>
                <th>Datum</th>
                <th>Status</th>
                <th>Sichtbarkeit</th>
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
                    <td>
                        <?php
                        $visibility_labels = [
                            'public' => '🌐 Öffentlich',
                            'authenticated' => '🔓 Angemeldet',
                            'invited_only' => '🔒 Nur Eingeladene'
                        ];
                        echo $visibility_labels[$meeting['visibility_type'] ?? 'invited_only'] ?? $meeting['visibility_type'];
                        ?>
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
                    <label>Voraussichtliches Ende:</label>
                    <input type="datetime-local" name="expected_end_date" id="edit_expected_end_date">
                </div>
                <div class="form-group">
                    <label>Ort:</label>
                    <input type="text" name="location" id="edit_location">
                </div>
                <div class="form-group">
                    <label>Videokonferenz-Link:</label>
                    <input type="url" name="video_link" id="edit_video_link">
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
                <div class="form-group">
                    <label>Sichtbarkeit:</label>
                    <select name="visibility_type" id="edit_visibility_type" required>
                        <option value="invited_only">🔒 Nur Eingeladene</option>
                        <option value="authenticated">🔓 Alle Angemeldeten</option>
                        <option value="public">🌐 Öffentlich</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Eingeladen von:</label>
                    <select name="invited_by_member_id" id="edit_invited_by_member_id" required>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['member_id']; ?>">
                                <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
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
                <div class="form-group">
                    <label>Teilnehmer:</label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                        <?php foreach ($members as $m): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="participant_ids[]" value="<?php echo $m['member_id']; ?>" class="edit-participant-checkbox">
                                <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" name="edit_meeting" class="btn-primary">Speichern</button>
                <button type="button" onclick="closeEditMeetingModal()" class="btn-secondary">Abbrechen</button>
            </form>
        </div>
    </div>
    </div> <!-- End admin-section-content -->
</div>

<!-- Mitgliederverwaltung -->
<div id="admin-members" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">👥 Mitgliederverwaltung</h3>

    <div class="admin-section-content">

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
            <div class="form-group">
                <label>Mitgliedsnummer (optional):</label>
                <input type="text" name="membership_number">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Rolle:</label>
                    <select name="role" required>
                        <option value="Mitglied">Mitglied</option>
                        <option value="fuehrungsteam">Führungsteam</option>
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
                <label class="checkbox-label">
                    <input type="checkbox" name="is_confidential" value="1">
                    <span>Darf vertrauliche TOPs sehen</span>
                </label>
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
                <th>Vertraulich</th>
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
                    <td><?php echo $member['is_confidential'] ? '✅' : '❌'; ?></td>
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
                <div class="form-group">
                    <label>Mitgliedsnummer (optional):</label>
                    <input type="text" name="membership_number" id="edit_membership_number">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Rolle:</label>
                        <select name="role" id="edit_role" required>
                            <option value="Mitglied">Mitglied</option>
                            <option value="fuehrungsteam">Führungsteam</option>
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
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_confidential" id="edit_is_confidential" value="1">
                        <span>Darf vertrauliche TOPs sehen</span>
                    </label>
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
    </div> <!-- End admin-section-content -->
</div>

<!-- Offene ToDos -->
<div id="admin-todos" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">📝 Offene ToDos</h3>

    <div class="admin-section-content">

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
                        <td class="action-buttons">
                            <button class="btn-view" onclick="editTodo(<?php echo $todo['todo_id']; ?>)">✏️</button>
                            <form method="POST" onsubmit="return confirm('ToDo als erledigt markieren?');" style="display: inline;">
                                <input type="hidden" name="todo_id" value="<?php echo $todo['todo_id']; ?>">
                                <button type="submit" name="close_todo" class="btn-primary">✓ Erledigt</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('ToDo wirklich löschen?');" style="display: inline;">
                                <input type="hidden" name="todo_id" value="<?php echo $todo['todo_id']; ?>">
                                <button type="submit" name="delete_todo" class="btn-delete">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div> <!-- End admin-section-content -->
</div>

<!-- Textbearbeitung-Verwaltung -->
<div id="admin-texts" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">✍️ Textbearbeitung-Verwaltung</h3>

    <div class="admin-section-content">

    <?php if (empty($all_collab_texts)): ?>
        <div class="info-box">Keine kollaborativen Texte vorhanden.</div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titel</th>
                    <th>Ersteller</th>
                    <th>Kontext</th>
                    <th>Status</th>
                    <th>Erstellt am</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_collab_texts as $text): ?>
                    <tr>
                        <td><?php echo $text['text_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($text['title']); ?></strong>
                            <?php if ($text['final_name']): ?>
                                <br><small style="color: #666;">Final: <?php echo htmlspecialchars($text['final_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($text['initiator_first_name'] . ' ' . $text['initiator_last_name']); ?></td>
                        <td>
                            <?php if ($text['meeting_id']): ?>
                                <span style="color: #007bff;">📅 <?php echo htmlspecialchars($text['meeting_name'] ?? 'Meeting #' . $text['meeting_id']); ?></span>
                            <?php else: ?>
                                <span style="color: #28a745;">📝 Allgemein</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($text['status'] === 'finalized'): ?>
                                <span style="color: #28a745; font-weight: bold;">✅ Finalisiert</span>
                            <?php else: ?>
                                <span style="color: #ffc107; font-weight: bold;">⏳ Aktiv</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($text['created_at'])); ?></td>
                        <td>
                            <?php if ($text['status'] === 'finalized'): ?>
                                <a href="?tab=texte&view=final&text_id=<?php echo $text['text_id']; ?>" class="btn-view" target="_blank">👁️ Ansehen</a>
                            <?php else: ?>
                                <a href="?tab=texte&view=editor&text_id=<?php echo $text['text_id']; ?>" class="btn-view" target="_blank">✏️ Editor</a>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Text &quot;<?php echo htmlspecialchars($text['title'], ENT_QUOTES); ?>&quot; wirklich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden!');">
                                <input type="hidden" name="delete_collab_text_id" value="<?php echo $text['text_id']; ?>">
                                <button type="submit" name="delete_collab_text" class="btn-delete">🗑️ Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="info-box" style="margin-top: 15px;">
            <strong>ℹ️ Hinweis:</strong> Insgesamt <?php echo count($all_collab_texts); ?> Texte
            (<?php echo count(array_filter($all_collab_texts, fn($t) => $t['status'] === 'active')); ?> aktiv,
            <?php echo count(array_filter($all_collab_texts, fn($t) => $t['status'] === 'finalized')); ?> finalisiert)
        </div>
    <?php endif; ?>
    </div> <!-- End admin-section-content -->
</div>

<!-- Admin-Protokoll -->
<div id="admin-log" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">📋 Admin-Protokoll (letzte 50 Aktionen)</h3>

    <div class="admin-section-content">

    <?php if (empty($admin_logs)): ?>
        <div class="info-box">Keine Admin-Aktionen protokolliert.</div>
    <?php else: ?>
        <table class="admin-table compact-log-table">
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
                    <h5 style="margin-top: 0; color: #007bff;">📦 Demo-Daten exportieren</h5>
                    <p style="font-size: 14px; color: #666;">
                        Exportiert den aktuellen Datenbankstand als Demo-Daten-Datei.
                        Nützlich wenn Sie einen neuen Demo-Stand erstellen möchten.
                    </p>
                    <a href="tools/demo_export.php" class="btn" style="background-color: #007bff; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-top: 10px;" target="_blank">
                        📦 Export starten
                    </a>
                </div>

                <!-- Demo Import/Reset -->
                <div style="border: 1px solid #dc3545; padding: 15px; border-radius: 5px; background-color: #fff5f5;">
                    <h5 style="margin-top: 0; color: #dc3545;">♻️ Datenbank auf Demo-Stand zurücksetzen</h5>
                    <p style="font-size: 14px; color: #666;">
                        <strong>ACHTUNG:</strong> Löscht ALLE aktuellen Daten und lädt Demo-Daten ein.
                        Dieser Vorgang kann nicht rückgängig gemacht werden!
                    </p>
                    <a href="tools/demo_import.php" class="btn btn-danger" style="background-color: #dc3545; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-top: 10px;" target="_blank" onclick="return confirm('WARNUNG: Dies löscht ALLE aktuellen Daten!\n\nMöchten Sie wirklich fortfahren?');">
                        ♻️ Demo-Reset durchführen
                    </a>
                </div>
            </div>

            <div style="margin-top: 20px; padding: 15px; background-color: #e7f3ff; border-left: 4px solid #0c5460; border-radius: 5px;">
                <h5 style="margin-top: 0;">💡 Workflow</h5>
                <ol style="font-size: 14px; margin: 0;">
                    <li>Erstellen Sie in der Anwendung verschiedene Meetings, TODOs, Kommentare etc.</li>
                    <li>Exportieren Sie diese als Demo-Daten (erzeugt <code>demo_data.json</code>)</li>
                    <li>Wenn Sie den Demo-Stand wiederherstellen möchten, nutzen Sie "Demo-Reset"</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Dokumentenverwaltung -->
<div id="admin-documents" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">📁 Dokumente in der Dokumentensammlung verwalten</h3>

    <div class="admin-section-content">
        <!-- Dokument hochladen -->
        <details style="margin-bottom: 20px;">
            <summary style="padding: 10px 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 14px;">
                ➕ Neues Dokument hochladen
            </summary>
            <div style="border: 1px solid #ddd; border-top: none; padding: 15px; border-radius: 0 0 5px 5px; background: white;">
                <form method="POST" action="process_documents.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="redirect_to" value="admin">

                    <div class="form-group">
                        <label>Datei auswählen *</label>
                        <input type="file" name="document_file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.rtf,.txt,.jpg,.jpeg,.png" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="display: block; margin-top: 5px; color: #666;">
                            Erlaubte Dateitypen: PDF, DOC, DOCX, XLS, XLSX, RTF, TXT, JPG, PNG
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Titel *</label>
                        <input type="text" name="title" required placeholder="Aussagekräftiger Titel" style="width: 100%;">
                    </div>

                    <div class="form-group">
                        <label>Kategorie *</label>
                        <select name="category" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <?php foreach (get_document_categories() as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Beschreibung</label>
                        <textarea name="description" rows="3" placeholder="Ausführliche Beschreibung des Dokuments" style="width: 100%;"></textarea>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Version</label>
                            <input type="text" name="version" placeholder="z.B. 2025, v1.2" style="width: 100%;">
                        </div>

                        <div class="form-group">
                            <label>Zugriffslevel</label>
                            <select name="access_level" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="0">Alle Mitglieder</option>
                                <option value="12">Ab Projektleitung</option>
                                <option value="15">Ab Ressortleitung</option>
                                <option value="18">Ab Assistenz</option>
                                <option value="19">Nur Vorstand</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Stichworte</label>
                        <input type="text" name="keywords" placeholder="Komma-getrennte Stichworte für die Suche" style="width: 100%;">
                    </div>

                    <div class="form-group">
                        <label>Kurz-URL</label>
                        <input type="text" name="short_url" placeholder="https://link.mensa.de/xyz" style="width: 100%;">
                        <small style="display: block; margin-top: 5px; color: #666;">
                            Optional: Eine kurze, einprägsame URL für dieses Dokument
                        </small>
                    </div>

                    <button type="submit" class="btn-primary">📤 Hochladen</button>
                </form>
            </div>
        </details>

        <!-- Dokumentenliste -->
        <h4 style="margin-top: 25px; margin-bottom: 15px; font-size: 16px;">Vorhandene Dokumente</h4>

        <?php if (empty($all_documents)): ?>
            <div class="info-box">Keine Dokumente vorhanden.</div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Kategorie</th>
                        <th>Version</th>
                        <th>Dateityp</th>
                        <th>Größe</th>
                        <th>Hochgeladen</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_documents as $doc):
                        $categories = get_document_categories();
                        $cat_label = $categories[$doc['category']] ?? $doc['category'];
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($doc['title']) ?></strong>
                                <?php if ($doc['description']): ?>
                                    <br><small style="color: #666;"><?= htmlspecialchars(substr($doc['description'], 0, 100)) ?><?= strlen($doc['description']) > 100 ? '...' : '' ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($cat_label) ?></td>
                            <td><?= $doc['version'] ? htmlspecialchars($doc['version']) : '-' ?></td>
                            <td><?= strtoupper($doc['filetype']) ?></td>
                            <td><?= format_filesize($doc['filesize']) ?></td>
                            <td><?= date('d.m.Y', strtotime($doc['created_at'])) ?></td>
                            <td class="action-buttons">
                                <a href="?tab=documents&view=edit&id=<?= $doc['document_id'] ?>" class="btn-view">✏️</a>
                                <form method="POST" action="process_documents.php" style="display: inline;" onsubmit="return confirm('Dokument wirklich löschen?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="document_id" value="<?= $doc['document_id'] ?>">
                                    <input type="hidden" name="redirect_to" value="admin">
                                    <button type="submit" class="btn-delete">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="info-box" style="margin-top: 15px;">
                <strong>ℹ️ Hinweis:</strong> Insgesamt <?= count($all_documents) ?> Dokumente in der Sammlung
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Datenbank-Wartung -->
<div id="admin-database" class="admin-section">
    <h3 class="admin-section-header" onclick="toggleSection(this)">🔧 Datenbank-Wartung</h3>

    <div class="admin-section-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">

            <!-- Backup & Restore -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #4CAF50;">
                <h4 style="margin-top: 0; color: #4CAF50;">💾 Backup & Restore</h4>
                <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                    Erstellen Sie regelmäßig Sicherungen der Datenbank.
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
                <li><strong>Backup:</strong> Erstellen Sie regelmäßig Backups vor wichtigen Änderungen</li>
                <li><strong>Restore:</strong> Beim Wiederherstellen werden ALLE aktuellen Daten überschrieben</li>
                <li><strong>Passwort:</strong> System-Admin-Passwort in config.php konfigurieren</li>
            </ul>
        </div>
    </div>
</div>

<!-- Edit ToDo Modal -->
<div id="edit-todo-modal" class="modal">
    <div class="modal-content">
        <h3>ToDo bearbeiten</h3>
        <form method="POST" id="edit-todo-form">
            <input type="hidden" name="todo_id" id="edit_todo_id">
            <div class="form-group">
                <label>Titel:</label>
                <input type="text" name="title" id="edit_todo_title" required>
            </div>
            <div class="form-group">
                <label>Beschreibung:</label>
                <textarea name="description" id="edit_todo_description" rows="4" required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Zugewiesen an:</label>
                    <select name="assigned_to_member_id" id="edit_todo_assigned_to" required>
                        <option value="">Bitte wählen...</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['member_id']; ?>">
                                <?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status:</label>
                    <select name="status" id="edit_todo_status" required>
                        <option value="open">Offen</option>
                        <option value="done">Erledigt</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Eintrittsdatum:</label>
                    <input type="date" name="entry_date" id="edit_todo_entry_date">
                </div>
                <div class="form-group">
                    <label>Fälligkeitsdatum:</label>
                    <input type="date" name="due_date" id="edit_todo_due_date">
                </div>
            </div>
            <button type="submit" name="edit_todo" class="btn-primary">Speichern</button>
            <button type="button" onclick="closeEditTodoModal()" class="btn-secondary">Abbrechen</button>
        </form>
    </div>
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
        document.getElementById('edit_expected_end_date').value = meeting.expected_end_date ? meeting.expected_end_date.replace(' ', 'T').substring(0, 16) : '';
        document.getElementById('edit_location').value = meeting.location || '';
        document.getElementById('edit_video_link').value = meeting.video_link || '';
        document.getElementById('edit_status').value = meeting.status;
        document.getElementById('edit_visibility_type').value = meeting.visibility_type || 'invited_only';
        document.getElementById('edit_invited_by_member_id').value = meeting.invited_by_member_id || '';
        document.getElementById('edit_chairman_id').value = meeting.chairman_member_id || '';
        document.getElementById('edit_secretary_id').value = meeting.secretary_member_id || '';

        // Alle Checkboxen zurücksetzen
        document.querySelectorAll('.edit-participant-checkbox').forEach(cb => cb.checked = false);

        // Teilnehmer markieren
        if (meeting.participant_ids && Array.isArray(meeting.participant_ids)) {
            meeting.participant_ids.forEach(memberId => {
                const checkbox = document.querySelector(`.edit-participant-checkbox[value="${memberId}"]`);
                if (checkbox) checkbox.checked = true;
            });
        }

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
        document.getElementById('edit_membership_number').value = member.membership_number || '';
        document.getElementById('edit_role').value = member.role;
        document.getElementById('edit_is_admin').checked = member.is_admin == 1;
        document.getElementById('edit_is_confidential').checked = member.is_confidential == 1;
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

// Akkordion-Funktionalität
function toggleSection(header) {
    header.classList.toggle('collapsed');
    const content = header.nextElementSibling;
    content.classList.toggle('collapsed');
}

// ToDo bearbeiten
function editTodo(todoId) {
    const todos = <?php echo json_encode($open_todos); ?>;
    const todo = todos.find(t => t.todo_id == todoId);

    if (todo) {
        document.getElementById('edit_todo_id').value = todo.todo_id;
        document.getElementById('edit_todo_title').value = todo.title || '';
        document.getElementById('edit_todo_description').value = todo.description || '';
        document.getElementById('edit_todo_assigned_to').value = todo.assigned_to_member_id || '';
        document.getElementById('edit_todo_status').value = todo.status || 'open';
        document.getElementById('edit_todo_entry_date').value = todo.entry_date || '';
        document.getElementById('edit_todo_due_date').value = todo.due_date || '';
        document.getElementById('edit-todo-modal').classList.add('show');
    }
}

function closeEditTodoModal() {
    document.getElementById('edit-todo-modal').classList.remove('show');
}

// Initialize: Start with all sections collapsed
document.addEventListener('DOMContentLoaded', function() {
    // Alle Sektionen initial eingeklappt
    const allHeaders = document.querySelectorAll('.admin-section-header');
    allHeaders.forEach(header => {
        toggleSection(header);
    });
});
</script>
