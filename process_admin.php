<?php
/**
 * process_admin.php - Admin-Verwaltung (Business Logic)
 * Bereinigt: 29.10.2025 02:30 MEZ
 * 
 * Verarbeitet alle Admin-Aktionen mit vollständiger Protokollierung
 * Nur für Benutzer mit is_admin = TRUE zugänglich
 * 
 * WICHTIG: Diese Datei wird in tab_admin.php eingebunden
 * Voraussetzungen: $pdo, $current_user
 */

// ============================================
// ZUGRIFFSKONTROLLE
// ============================================

if (empty($current_user['is_admin'])) {
    echo '<div class="error-message">❌ Zugriff verweigert. Sie haben keine Admin-Rechte.</div>';
    exit;
}

// Nachrichten-Variablen
$success_message = '';
$error_message = '';

// ============================================
// HILFSFUNKTIONEN
// ============================================

/**
 * Protokolliert eine Admin-Aktion in der Datenbank
 * 
 * @param PDO $pdo Datenbankverbindung
 * @param int $admin_id Member-ID des Admins
 * @param string $action_type Art der Aktion (z.B. 'meeting_delete')
 * @param string $description Beschreibung der Aktion
 * @param string|null $target_type Typ des Zielobjekts (z.B. 'meeting', 'member')
 * @param int|null $target_id ID des Zielobjekts
 * @param array|null $old_values Alte Werte (vor Änderung)
 * @param array|null $new_values Neue Werte (nach Änderung)
 */
function log_admin_action($pdo, $admin_id, $action_type, $description, $target_type = null, $target_id = null, $old_values = null, $new_values = null) {
    // IP-Adresse und User-Agent erfassen
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Arrays zu JSON konvertieren
    $old_json = $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null;
    $new_json = $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null;
    
    // In Datenbank speichern
    $stmt = $pdo->prepare("
        INSERT INTO admin_log 
        (admin_member_id, action_type, action_description, target_type, target_id, 
         old_values, new_values, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $admin_id,
        $action_type,
        $description,
        $target_type,
        $target_id,
        $old_json,
        $new_json,
        $ip_address,
        $user_agent
    ]);
}

// ============================================
// 1. MEETING-VERWALTUNG
// ============================================

/**
 * Meeting bearbeiten
 * 
 * POST-Parameter:
 * - edit_meeting: 1
 * - meeting_id: Int (required)
 * - meeting_name: String (required)
 * - meeting_date: DateTime (required)
 * - status: String (required)
 * - chairman_id: Int (optional)
 * - secretary_id: Int (optional)
 * 
 * Aktion:
 * - Meeting-Daten aktualisieren
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['edit_meeting'])) {
    $meeting_id = intval($_POST['meeting_id'] ?? 0);
    $meeting_name = trim($_POST['meeting_name'] ?? '');
    $meeting_date = $_POST['meeting_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $chairman_id = !empty($_POST['chairman_id']) ? intval($_POST['chairman_id']) : null;
    $secretary_id = !empty($_POST['secretary_id']) ? intval($_POST['secretary_id']) : null;
    
    // Validierung
    if (!$meeting_id || !$meeting_name || !$meeting_date || !$status) {
        $error_message = "Pflichtfelder fehlen.";
    } else {
        try {
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM meetings WHERE meeting_id = ?");
            $stmt->execute([$meeting_id]);
            $old_meeting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_meeting) {
                $error_message = "Meeting nicht gefunden.";
            } else {
                // Meeting aktualisieren
                $stmt = $pdo->prepare("
                    UPDATE meetings 
                    SET meeting_name = ?, 
                        meeting_date = ?, 
                        status = ?,
                        chairman_member_id = ?,
                        secretary_member_id = ?
                    WHERE meeting_id = ?
                ");
                $stmt->execute([
                    $meeting_name, 
                    $meeting_date, 
                    $status, 
                    $chairman_id, 
                    $secretary_id, 
                    $meeting_id
                ]);
                
                // Neue Daten für Log
                $new_meeting = [
                    'meeting_name' => $meeting_name,
                    'meeting_date' => $meeting_date,
                    'status' => $status,
                    'chairman_member_id' => $chairman_id,
                    'secretary_member_id' => $secretary_id
                ];
                
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'meeting_edit',
                    "Meeting bearbeitet: " . $meeting_name,
                    'meeting',
                    $meeting_id,
                    $old_meeting,
                    $new_meeting
                );
                
                $success_message = "✅ Meeting erfolgreich aktualisiert!";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Meeting-Aktualisieren: " . $e->getMessage());
            $error_message = "❌ Fehler beim Aktualisieren: " . $e->getMessage();
        }
    }
}

/**
 * Meeting löschen
 * 
 * POST-Parameter:
 * - delete_meeting: 1
 * - meeting_id: Int (required)
 * 
 * Aktion:
 * - Alle abhängigen Daten löschen (TODOs, Kommentare, Agenda Items, Teilnehmer)
 * - Meeting löschen
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['delete_meeting'])) {
    $meeting_id = intval($_POST['meeting_id'] ?? 0);
    
    if (!$meeting_id) {
        $error_message = "Ungültige Meeting-ID.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM meetings WHERE meeting_id = ?");
            $stmt->execute([$meeting_id]);
            $old_meeting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_meeting) {
                $error_message = "Meeting nicht gefunden.";
                $pdo->rollBack();
            } else {
                // Reihenfolge wichtig wegen Foreign Keys!
                
                // 1. ToDos löschen
                $stmt = $pdo->prepare("DELETE FROM todos WHERE meeting_id = ?");
                $stmt->execute([$meeting_id]);
                
                // 2. Kommentare löschen (über Agenda Items)
                $stmt = $pdo->prepare("
                    DELETE FROM agenda_comments 
                    WHERE item_id IN (SELECT item_id FROM agenda_items WHERE meeting_id = ?)
                ");
                $stmt->execute([$meeting_id]);
                
                // 3. Agenda Items löschen
                $stmt = $pdo->prepare("DELETE FROM agenda_items WHERE meeting_id = ?");
                $stmt->execute([$meeting_id]);
                
                // 4. Teilnehmer löschen
                $stmt = $pdo->prepare("DELETE FROM meeting_participants WHERE meeting_id = ?");
                $stmt->execute([$meeting_id]);
                
                // 5. Meeting löschen
                $stmt = $pdo->prepare("DELETE FROM meetings WHERE meeting_id = ?");
                $stmt->execute([$meeting_id]);
                
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'meeting_delete',
                    "Meeting gelöscht: " . ($old_meeting['meeting_name'] ?? 'Unbenannt'),
                    'meeting',
                    $meeting_id,
                    $old_meeting,
                    null
                );
                
                $pdo->commit();
                $success_message = "✅ Meeting erfolgreich gelöscht!";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Admin: Fehler beim Meeting-Löschen: " . $e->getMessage());
            $error_message = "❌ Fehler beim Löschen: " . $e->getMessage();
        }
    }
}

// ============================================
// 2. MITGLIEDERVERWALTUNG
// ============================================

/**
 * Mitglied hinzufügen
 * 
 * POST-Parameter:
 * - add_member: 1
 * - first_name: String (required)
 * - last_name: String (required)
 * - email: String (required)
 * - role: String (required)
 * - is_admin: Checkbox (0 oder 1)
 * - password: String (required)
 * 
 * Aktion:
 * - Mitglied in DB einfügen
 * - Passwort hashen
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['add_member'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    // Validierung
    if (!$first_name || !$last_name || !$email || !$role || !$password) {
        $error_message = "Pflichtfelder fehlen.";
    } else {
        try {
            // Passwort hashen
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Mitglied einfügen
            $stmt = $pdo->prepare("
                INSERT INTO members
                (first_name, last_name, email, role, is_admin, is_confidential, password_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $role,
                $is_admin,
                $is_confidential,
                $password_hash
            ]);

            $new_member_id = $pdo->lastInsertId();

            // Admin-Log
            log_admin_action(
                $pdo,
                $current_user['member_id'],
                'member_create',
                "Mitglied erstellt: $first_name $last_name ($email)",
                'member',
                $new_member_id,
                null,
                [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'role' => $role,
                    'is_admin' => $is_admin,
                    'is_confidential' => $is_confidential
                ]
            );
            
            $success_message = "✅ Mitglied erfolgreich hinzugefügt!";
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Mitglied-Hinzufügen: " . $e->getMessage());
            $error_message = "❌ Fehler beim Hinzufügen: " . $e->getMessage();
        }
    }
}

/**
 * Mitglied bearbeiten
 * 
 * POST-Parameter:
 * - edit_member: 1
 * - member_id: Int (required)
 * - first_name: String (required)
 * - last_name: String (required)
 * - email: String (required)
 * - role: String (required)
 * - is_admin: Checkbox (0 oder 1)
 * - password: String (optional, nur wenn geändert)
 * 
 * Aktion:
 * - Mitglied-Daten aktualisieren
 * - Passwort ändern falls angegeben
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['edit_member'])) {
    $member_id = intval($_POST['member_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

    // Validierung
    if (!$member_id || !$first_name || !$last_name || !$email || !$role) {
        $error_message = "Pflichtfelder fehlen.";
    } else {
        try {
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
            $stmt->execute([$member_id]);
            $old_member = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_member) {
                $error_message = "Mitglied nicht gefunden.";
            } else {
                // Mitglied aktualisieren
                $stmt = $pdo->prepare("
                    UPDATE members
                    SET first_name = ?, last_name = ?, email = ?, role = ?, is_admin = ?, is_confidential = ?
                    WHERE member_id = ?
                ");
                $stmt->execute([
                    $first_name,
                    $last_name,
                    $email,
                    $role,
                    $is_admin,
                    $is_confidential,
                    $member_id
                ]);
                
                // Passwort ändern falls angegeben
                $password_changed = false;
                if (!empty($_POST['password'])) {
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE members SET password_hash = ? WHERE member_id = ?");
                    $stmt->execute([$password_hash, $member_id]);
                    $password_changed = true;
                }
                
                // Neue Daten für Log
                $new_member = [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'role' => $role,
                    'is_admin' => $is_admin,
                    'is_confidential' => $is_confidential,
                    'password_changed' => $password_changed
                ];
                
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'member_edit',
                    "Mitglied bearbeitet: $first_name $last_name",
                    'member',
                    $member_id,
                    $old_member,
                    $new_member
                );
                
                $success_message = "✅ Mitglied erfolgreich aktualisiert!";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Mitglied-Aktualisieren: " . $e->getMessage());
            $error_message = "❌ Fehler beim Aktualisieren: " . $e->getMessage();
        }
    }
}

/**
 * Mitglied löschen
 * 
 * POST-Parameter:
 * - delete_member: 1
 * - member_id: Int (required)
 * 
 * Aktion:
 * - Mitglied aus DB löschen
 * - Admin-Aktion protokollieren
 * 
 * HINWEIS: Abhängige Daten (TODOs, Kommentare etc.) werden NICHT gelöscht
 *          sondern behalten die member_id (für historische Nachvollziehbarkeit)
 */
if (isset($_POST['delete_member'])) {
    $member_id = intval($_POST['member_id'] ?? 0);
    
    if (!$member_id) {
        $error_message = "Ungültige Mitglieds-ID.";
    } else {
        try {
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
            $stmt->execute([$member_id]);
            $old_member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_member) {
                $error_message = "Mitglied nicht gefunden.";
            } else {
                // Mitglied löschen
                $stmt = $pdo->prepare("DELETE FROM members WHERE member_id = ?");
                $stmt->execute([$member_id]);
                
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'member_delete',
                    "Mitglied gelöscht: " . $old_member['first_name'] . " " . $old_member['last_name'],
                    'member',
                    $member_id,
                    $old_member,
                    null
                );
                
                $success_message = "✅ Mitglied erfolgreich gelöscht!";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Mitglied-Löschen: " . $e->getMessage());
            $error_message = "❌ Fehler beim Löschen: " . $e->getMessage();
        }
    }
}

// ============================================
// 3. TODO-VERWALTUNG
// ============================================

/**
 * ToDo als erledigt markieren
 * 
 * POST-Parameter:
 * - close_todo: 1
 * - todo_id: Int (required)
 * 
 * Aktion:
 * - ToDo-Status auf 'done' setzen
 * - completed_at Zeitstempel setzen
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['close_todo'])) {
    $todo_id = intval($_POST['todo_id'] ?? 0);
    
    if (!$todo_id) {
        $error_message = "Ungültige ToDo-ID.";
    } else {
        try {
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM todos WHERE todo_id = ?");
            $stmt->execute([$todo_id]);
            $old_todo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_todo) {
                $error_message = "ToDo nicht gefunden.";
            } else {
                // ToDo schließen
                $stmt = $pdo->prepare("
                    UPDATE todos 
                    SET status = 'done', completed_at = NOW() 
                    WHERE todo_id = ?
                ");
                $stmt->execute([$todo_id]);
                
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'todo_close',
                    "ToDo geschlossen: " . ($old_todo['description'] ?? 'Unbenannt'),
                    'todo',
                    $todo_id,
                    ['status' => 'open'],
                    ['status' => 'done']
                );
                
                $success_message = "✅ ToDo erfolgreich geschlossen!";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim ToDo-Schließen: " . $e->getMessage());
            $error_message = "❌ Fehler beim Schließen: " . $e->getMessage();
        }
    }
}

// ============================================
// 4. DATEN LADEN
// ============================================

// Alle Meetings laden
$meetings = $pdo->query("
    SELECT m.*, 
        mem_inv.first_name as inviter_first_name, 
        mem_inv.last_name as inviter_last_name,
        (SELECT COUNT(*) FROM meeting_participants WHERE meeting_id = m.meeting_id) as participant_count,
        (SELECT COUNT(*) FROM agenda_items WHERE meeting_id = m.meeting_id) as agenda_count
    FROM meetings m
    LEFT JOIN members mem_inv ON m.invited_by_member_id = mem_inv.member_id
    ORDER BY m.meeting_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Alle Mitglieder laden
$members = $pdo->query("
    SELECT * FROM members 
    ORDER BY last_name, first_name
")->fetchAll(PDO::FETCH_ASSOC);

// Offene ToDos laden
$open_todos = $pdo->query("
    SELECT t.*, 
        m.first_name, m.last_name,
        ai.title as agenda_title,
        meet.meeting_name, meet.meeting_date
    FROM todos t
    LEFT JOIN members m ON t.assigned_to_member_id = m.member_id
    LEFT JOIN agenda_items ai ON t.item_id = ai.item_id
    LEFT JOIN meetings meet ON t.meeting_id = meet.meeting_id
    WHERE t.status = 'open'
    ORDER BY t.due_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Admin-Log laden (letzte 50 Einträge)
$admin_logs = $pdo->query("
    SELECT al.*, 
        m.first_name, m.last_name
    FROM admin_log al
    LEFT JOIN members m ON al.admin_member_id = m.member_id
    ORDER BY al.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Statistiken berechnen
$stats = [
    'total' => count($meetings),
    'preparation' => count(array_filter($meetings, fn($m) => $m['status'] === 'preparation')),
    'active' => count(array_filter($meetings, fn($m) => $m['status'] === 'active')),
    'ended' => count(array_filter($meetings, fn($m) => $m['status'] === 'ended')),
    'archived' => count(array_filter($meetings, fn($m) => $m['status'] === 'archived'))
];

?>