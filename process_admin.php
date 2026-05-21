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
    echo '<div class="error-message">❌ Zugriff verweigert. Du hast keine Admin-Rechte.</div>';
    exit;
}

// Nachrichten-Variablen
$success_message = '';
$error_message = '';

// ============================================
// INITIALISIERUNGS-BEREICH: Session-Handling
// ============================================

// Bestätigung setzen
if (isset($_POST['confirm_init_access'])) {
    $_SESSION['init_confirmed'] = true;
    $_SESSION['init_confirmed_at'] = time();
    header('Location: ?tab=admin_init');
    exit;
}

// Bestätigung zurücksetzen (nach 30 Minuten automatisch)
$init_confirmed = isset($_SESSION['init_confirmed']) && $_SESSION['init_confirmed'] === true;
if ($init_confirmed && (time() - ($_SESSION['init_confirmed_at'] ?? 0)) > 1800) {
    unset($_SESSION['init_confirmed']);
    unset($_SESSION['init_confirmed_at']);
    $init_confirmed = false;
}

// Manuelle Abmeldung
if (isset($_GET['reset_init'])) {
    unset($_SESSION['init_confirmed']);
    unset($_SESSION['init_confirmed_at']);
    header('Location: ?tab=admin');
    exit;
}

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
        INSERT INTO svadmin_log 
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
    $expected_end_date = !empty($_POST['expected_end_date']) ? $_POST['expected_end_date'] : null;
    $submission_deadline = !empty($_POST['submission_deadline']) ? $_POST['submission_deadline'] : null;
    $location = trim($_POST['location'] ?? '');
    $video_link = trim($_POST['video_link'] ?? '');
    $status = $_POST['status'] ?? '';
    $visibility_type = $_POST['visibility_type'] ?? 'invited_only';
    $invited_by_member_id = intval($_POST['invited_by_member_id'] ?? 0);
    $chairman_id = !empty($_POST['chairman_id']) ? intval($_POST['chairman_id']) : null;
    $secretary_id = !empty($_POST['secretary_id']) ? intval($_POST['secretary_id']) : null;
    $participant_ids = $_POST['participant_ids'] ?? [];

    // Datetime-Format konvertieren: 2026-05-01T17:00 -> 2026-05-01 17:00:00
    if (!empty($meeting_date)) {
        $meeting_date = str_replace('T', ' ', $meeting_date) . ':00';
    }
    if (!empty($expected_end_date)) {
        $expected_end_date = str_replace('T', ' ', $expected_end_date) . ':00';
    }
    if (!empty($submission_deadline)) {
        $submission_deadline = str_replace('T', ' ', $submission_deadline) . ':00';
    }


    // Validierung
    if (!$meeting_id || !$meeting_name || !$meeting_date || !$status || !$invited_by_member_id) {
        $error_message = "Pflichtfelder fehlen.";
    } else {
        try {
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM svmeetings WHERE meeting_id = ?");
            $stmt->execute([$meeting_id]);
            $old_meeting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_meeting) {
                $error_message = "Meeting nicht gefunden.";
            } else {
                // Meeting aktualisieren
                $stmt = $pdo->prepare("
                    UPDATE svmeetings
                    SET meeting_name = ?,
                        meeting_date = ?,
                        expected_end_date = ?,
                        submission_deadline = ?,
                        location = ?,
                        video_link = ?,
                        status = ?,
                        visibility_type = ?,
                        invited_by_member_id = ?,
                        chairman_member_id = ?,
                        secretary_member_id = ?
                    WHERE meeting_id = ?
                ");
                $stmt->execute([
                    $meeting_name,
                    $meeting_date,
                    $expected_end_date,
                    $submission_deadline,
                    $location,
                    $video_link,
                    $status,
                    $visibility_type,
                    $invited_by_member_id,
                    $chairman_id,
                    $secretary_id,
                    $meeting_id
                ]);

                $rows_affected = $stmt->rowCount();

                // Teilnehmer aktualisieren
                $stmt = $pdo->prepare("DELETE FROM svmeeting_participants WHERE meeting_id = ?");
                $stmt->execute([$meeting_id]);

                if (!empty($participant_ids)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO svmeeting_participants (meeting_id, member_id, attendance_status)
                        VALUES (?, ?, 'absent')
                    ");
                    foreach ($participant_ids as $member_id) {
                        $stmt->execute([$meeting_id, intval($member_id)]);
                    }
                }
                
                // Neue Daten für Log
                $new_meeting = [
                    'meeting_name' => $meeting_name,
                    'meeting_date' => $meeting_date,
                    'expected_end_date' => $expected_end_date,
                    'submission_deadline' => $submission_deadline,
                    'location' => $location,
                    'video_link' => $video_link,
                    'status' => $status,
                    'visibility_type' => $visibility_type,
                    'invited_by_member_id' => $invited_by_member_id,
                    'chairman_member_id' => $chairman_id,
                    'secretary_member_id' => $secretary_id,
                    'participant_count' => count($participant_ids)
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
 * Meeting manuell beenden (Admin-Funktion)
 *
 * POST-Parameter:
 * - admin_end_meeting: 1
 * - meeting_id: Int (required)
 * - end_time: DateTime (required)
 *
 * Aktion:
 * - Status auf 'ended' setzen
 * - end_time setzen
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['admin_end_meeting'])) {
    $meeting_id = intval($_POST['meeting_id'] ?? 0);
    $end_time = $_POST['end_time'] ?? '';

    if (!$meeting_id) {
        $error_message = "Ungültige Meeting-ID.";
    } elseif (empty($end_time)) {
        $error_message = "Endzeitpunkt muss angegeben werden.";
    } else {
        try {
            // Datetime-Format konvertieren
            if (!empty($end_time)) {
                $end_time = str_replace('T', ' ', $end_time) . ':00';
            }

            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT meeting_name, status, end_time FROM svmeetings WHERE meeting_id = ?");
            $stmt->execute([$meeting_id]);
            $old_meeting = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_meeting) {
                $error_message = "Meeting nicht gefunden.";
            } else {
                // Meeting beenden
                $stmt = $pdo->prepare("
                    UPDATE svmeetings
                    SET status = 'ended',
                        end_time = ?
                    WHERE meeting_id = ?
                ");
                $stmt->execute([$end_time, $meeting_id]);

                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'meeting_admin_end',
                    "Meeting manuell beendet: {$old_meeting['meeting_name']}",
                    'meeting',
                    $meeting_id,
                    [
                        'status' => $old_meeting['status'],
                        'end_time' => $old_meeting['end_time'] ?? 'NULL'
                    ],
                    [
                        'status' => 'ended',
                        'end_time' => $end_time
                    ]
                );

                $success_message = "✅ Sitzung wurde erfolgreich beendet.";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim manuellen Beenden der Sitzung: " . $e->getMessage());
            $error_message = "❌ Fehler beim Beenden: " . $e->getMessage();
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
            $stmt = $pdo->prepare("SELECT * FROM svmeetings WHERE meeting_id = ?");
            $stmt->execute([$meeting_id]);
            $old_meeting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_meeting) {
                $error_message = "Meeting nicht gefunden.";
                $pdo->rollBack();
            } else {
                // Reihenfolge wichtig wegen Foreign Keys!
                
                // 1. ToDos löschen
                $stmt = $pdo->prepare("DELETE FROM svtodos WHERE meeting_id = ?");
                $stmt->execute([$meeting_id]);
                
                // 2. Kommentare löschen (über Agenda Items)
                $stmt = $pdo->prepare("
                    DELETE FROM svagenda_comments
                    WHERE item_id IN (SELECT item_id FROM svagenda_items WHERE meeting_id = ?)
                ");
                $stmt->execute([$meeting_id]);

                // 2b. Post-Kommentare löschen (über Agenda Items)
                $stmt = $pdo->prepare("
                    DELETE FROM svagenda_post_comments
                    WHERE item_id IN (SELECT item_id FROM svagenda_items WHERE meeting_id = ?)
                ");
                $stmt->execute([$meeting_id]);

                // 3. Agenda Items löschen
                $stmt = $pdo->prepare("DELETE FROM svagenda_items WHERE meeting_id = ?");
                $stmt->execute([$meeting_id]);
                
                // 4. Teilnehmer löschen
                $stmt = $pdo->prepare("DELETE FROM svmeeting_participants WHERE meeting_id = ?");
                $stmt->execute([$meeting_id]);
                
                // 5. Meeting löschen
                $stmt = $pdo->prepare("DELETE FROM svmeetings WHERE meeting_id = ?");
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
    $membership_number = trim($_POST['membership_number'] ?? '');
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

            // Mitglied einfügen (über Wrapper-Funktion)
            $data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'role' => $role,
                'is_admin' => $is_admin,
                'is_confidential' => $is_confidential,
                'password_hash' => $password_hash
            ];
            if ($membership_number !== '') {
                $data['membership_number'] = $membership_number;
            }
            $new_member_id = create_member($pdo, $data);

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
    $membership_number = trim($_POST['membership_number'] ?? '');
    $role = $_POST['role'] ?? '';
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

    // Validierung
    if (!$member_id || !$first_name || !$last_name || !$email || !$role) {
        $error_message = "Pflichtfelder fehlen.";
    } else {
        try {
            // Alte Daten für Log abrufen (über Wrapper-Funktion)
            $old_member = get_member_by_id($pdo, $member_id);

            if (!$old_member) {
                $error_message = "Mitglied nicht gefunden.";
            } else {
                // Mitglied aktualisieren (über Wrapper-Funktion)
                $update_data = [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'membership_number' => $membership_number,
                    'role' => $role,
                    'is_admin' => $is_admin,
                    'is_confidential' => $is_confidential
                ];
                update_member($pdo, $member_id, $update_data);
                
                // Passwort ändern falls angegeben
                $password_changed = false;
                if (!empty($_POST['password'])) {
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE svmembers SET password_hash = ? WHERE member_id = ?");
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
            // Alte Daten für Log abrufen (über Wrapper-Funktion)
            $old_member = get_member_by_id($pdo, $member_id);

            if (!$old_member) {
                $error_message = "Mitglied nicht gefunden.";
            } else {
                // Mitglied löschen (über Wrapper-Funktion)
                delete_member($pdo, $member_id);
                
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
// 2A. RESSORT-VERWALTUNG
// ============================================

/**
 * Neues Ressort hinzufügen
 *
 * POST-Parameter:
 * - add_ressort: 1
 * - ressort_name: String (required)
 * - reihenfolge: Int
 * - aktiv: 1 oder nicht gesetzt
 */
if (isset($_POST['add_ressort'])) {
    $ressort_name = trim($_POST['ressort_name'] ?? '');
    $reihenfolge = intval($_POST['reihenfolge'] ?? 100);
    $aktiv = isset($_POST['aktiv']) ? 1 : 0;

    if (empty($ressort_name)) {
        $error_message = "Ressort-Name darf nicht leer sein.";
    } else {
        try {
            // Prüfen ob Ressort bereits existiert
            $check_stmt = $pdo->prepare("SELECT ID FROM svressorts WHERE Ressort = ?");
            $check_stmt->execute([$ressort_name]);

            if ($check_stmt->fetch()) {
                $error_message = "Ein Ressort mit diesem Namen existiert bereits.";
            } else {
                // Ressort hinzufügen
                $stmt = $pdo->prepare("
                    INSERT INTO svressorts (Ressort, Reihenfolge, aktiv, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$ressort_name, $reihenfolge, $aktiv]);
                $new_id = $pdo->lastInsertId();

                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'ressort_add',
                    "Ressort hinzugefügt: " . $ressort_name,
                    'ressort',
                    $new_id,
                    null,
                    ['Ressort' => $ressort_name, 'Reihenfolge' => $reihenfolge, 'aktiv' => $aktiv]
                );

                header('Location: ?tab=admin&msg=ressort_added');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Ressort-Hinzufügen: " . $e->getMessage());
            $error_message = "❌ Fehler beim Hinzufügen: " . $e->getMessage();
        }
    }
}

/**
 * Ressort bearbeiten
 *
 * POST-Parameter:
 * - edit_ressort: 1
 * - ressort_id: Int (required)
 * - ressort_name: String (required)
 * - reihenfolge: Int
 * - aktiv: 1 oder nicht gesetzt
 */
if (isset($_POST['edit_ressort'])) {
    $ressort_id = intval($_POST['ressort_id'] ?? 0);
    $ressort_name = trim($_POST['ressort_name'] ?? '');
    $reihenfolge = intval($_POST['reihenfolge'] ?? 100);
    $aktiv = isset($_POST['aktiv']) ? 1 : 0;

    if (!$ressort_id) {
        $error_message = "Ungültige Ressort-ID.";
    } elseif (empty($ressort_name)) {
        $error_message = "Ressort-Name darf nicht leer sein.";
    } else {
        try {
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM svressorts WHERE ID = ?");
            $stmt->execute([$ressort_id]);
            $old_ressort = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_ressort) {
                $error_message = "Ressort nicht gefunden.";
            } else {
                // Prüfen ob Name bereits von anderem Ressort verwendet wird
                $check_stmt = $pdo->prepare("SELECT ID FROM svressorts WHERE Ressort = ? AND ID != ?");
                $check_stmt->execute([$ressort_name, $ressort_id]);

                if ($check_stmt->fetch()) {
                    $error_message = "Ein anderes Ressort mit diesem Namen existiert bereits.";
                } else {
                    // Ressort aktualisieren
                    $stmt = $pdo->prepare("
                        UPDATE svressorts
                        SET Ressort = ?, Reihenfolge = ?, aktiv = ?, updated_at = NOW()
                        WHERE ID = ?
                    ");
                    $stmt->execute([$ressort_name, $reihenfolge, $aktiv, $ressort_id]);

                    // Admin-Log
                    log_admin_action(
                        $pdo,
                        $current_user['member_id'],
                        'ressort_edit',
                        "Ressort bearbeitet: " . $ressort_name,
                        'ressort',
                        $ressort_id,
                        ['Ressort' => $old_ressort['Ressort'], 'Reihenfolge' => $old_ressort['Reihenfolge'], 'aktiv' => $old_ressort['aktiv'] ?? 1],
                        ['Ressort' => $ressort_name, 'Reihenfolge' => $reihenfolge, 'aktiv' => $aktiv]
                    );

                    header('Location: ?tab=admin&msg=ressort_updated');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Ressort-Bearbeiten: " . $e->getMessage());
            $error_message = "❌ Fehler beim Bearbeiten: " . $e->getMessage();
        }
    }
}

/**
 * Ressort löschen
 *
 * POST-Parameter:
 * - delete_ressort: 1
 * - ressort_id: Int (required)
 *
 * WICHTIG: Löscht nur das Ressort, nicht die zugeordneten Anträge
 * Bestehende Anträge behalten ihre ressort1/ressort2 IDs
 */
if (isset($_POST['delete_ressort'])) {
    $ressort_id = intval($_POST['ressort_id'] ?? 0);

    if (!$ressort_id) {
        $error_message = "Ungültige Ressort-ID.";
    } else {
        try {
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM svressorts WHERE ID = ?");
            $stmt->execute([$ressort_id]);
            $old_ressort = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_ressort) {
                $error_message = "Ressort nicht gefunden.";
            } else {
                // Prüfen ob Ressort in Anträgen verwendet wird
                $usage_stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM antraege
                    WHERE ressort1 = ? OR ressort2 = ?
                ");
                $usage_stmt->execute([$ressort_id, $ressort_id]);
                $usage_count = $usage_stmt->fetchColumn();

                if ($usage_count > 0) {
                    $error_message = "Ressort wird noch in {$usage_count} Antrag/Anträgen verwendet und kann nicht gelöscht werden. Bitte setze es stattdessen auf 'Inaktiv'.";
                } else {
                    // Ressort löschen
                    $stmt = $pdo->prepare("DELETE FROM svressorts WHERE ID = ?");
                    $stmt->execute([$ressort_id]);

                    // Admin-Log
                    log_admin_action(
                        $pdo,
                        $current_user['member_id'],
                        'ressort_delete',
                        "Ressort gelöscht: " . $old_ressort['Ressort'],
                        'ressort',
                        $ressort_id,
                        ['Ressort' => $old_ressort['Ressort'], 'Reihenfolge' => $old_ressort['Reihenfolge']],
                        null
                    );

                    header('Location: ?tab=admin&msg=ressort_deleted');
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Ressort-Löschen: " . $e->getMessage());
            $error_message = "❌ Fehler beim Löschen: " . $e->getMessage();
        }
    }
}

// ============================================
// 2B. TERMINOLOGIE-KONFIGURATION
// ============================================

/**
 * Terminologie speichern
 *
 * POST-Parameter:
 * - save_terminology: 1
 * - config: Array mit config_key => config_value
 */
if (isset($_POST['save_terminology'])) {
    $configs = $_POST['config'] ?? [];

    if (empty($configs)) {
        $error_message = "Keine Konfigurationswerte übermittelt.";
    } else {
        try {
            $updated_count = 0;
            $old_values = [];
            $new_values = [];

            foreach ($configs as $key => $value) {
                // Alten Wert für Log abrufen
                $stmt = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
                $stmt->execute([$key]);
                $old_value = $stmt->fetchColumn();

                if ($old_value !== $value) {
                    // Wert aktualisieren
                    $update_stmt = $pdo->prepare("
                        UPDATE svconfig
                        SET config_value = ?, updated_by = ?, updated_at = NOW()
                        WHERE config_key = ?
                    ");
                    $update_stmt->execute([$value, $current_user['member_id'], $key]);

                    $old_values[$key] = $old_value;
                    $new_values[$key] = $value;
                    $updated_count++;
                }
            }

            if ($updated_count > 0) {
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'terminology_update',
                    "Terminologie aktualisiert: $updated_count Begriffe geändert",
                    'config',
                    null,
                    $old_values,
                    $new_values
                );

                header('Location: ?tab=admin_init&msg=terminology_saved');
                exit;
            } else {
                $success_message = "Keine Änderungen vorgenommen.";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Terminologie-Speichern: " . $e->getMessage());
            $error_message = "❌ Fehler beim Speichern: " . $e->getMessage();
        }
    }
}

/**
 * AKTIV-LEVEL SPEICHERN
 *
 * POST-Parameter:
 * - save_aktiv_levels: 1
 * - level: Array mit level_num => bezeichnung
 */
if (isset($_POST['save_aktiv_levels'])) {
    $levels = $_POST['level'] ?? [];

    if (empty($levels)) {
        $error_message = "Keine Level-Werte übermittelt.";
    } else {
        try {
            $updated_count = 0;
            $old_values = [];
            $new_values = [];

            foreach ($levels as $level_num => $bezeichnung) {
                $config_key = 'aktiv_level_' . (int)$level_num;

                // Alten Wert für Log abrufen
                $stmt = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
                $stmt->execute([$config_key]);
                $old_value = $stmt->fetchColumn();

                if ($old_value !== $bezeichnung) {
                    // Wert aktualisieren oder einfügen
                    $update_stmt = $pdo->prepare("
                        INSERT INTO svconfig (config_key, config_value, config_type, description, category, updated_by)
                        VALUES (?, ?, 'text', ?, 'aktiv_levels', ?)
                        ON DUPLICATE KEY UPDATE
                            config_value = VALUES(config_value),
                            updated_by = VALUES(updated_by),
                            updated_at = NOW()
                    ");
                    $update_stmt->execute([
                        $config_key,
                        $bezeichnung,
                        "Berechtigung für Level $level_num",
                        $current_user['member_id']
                    ]);

                    $old_values["Level $level_num"] = $old_value ?: '(leer)';
                    $new_values["Level $level_num"] = $bezeichnung;
                    $updated_count++;
                }
            }

            if ($updated_count > 0) {
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'aktiv_levels_update',
                    "aktiv-Level aktualisiert: $updated_count Level geändert",
                    'config',
                    null,
                    $old_values,
                    $new_values
                );

                header('Location: ?tab=admin_init&msg=levels_saved');
                exit;
            } else {
                $success_message = "Keine Änderungen vorgenommen.";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim aktiv-Level-Speichern: " . $e->getMessage());
            $error_message = "❌ Fehler beim Speichern: " . $e->getMessage();
        }
    }
}

/**
 * ANTRAGSTYPEN SPEICHERN
 *
 * POST-Parameter:
 * - save_antragstypen: 1
 * - bart: Array mit Typ-Konfigurationen
 */
if (isset($_POST['save_antragstypen'])) {
    $bart_data = $_POST['bart'] ?? [];

    if (empty($bart_data)) {
        $error_message = "Keine Antragstypen-Daten übermittelt.";
    } else {
        try {
            $updated_count = 0;
            $old_values = [];
            $new_values = [];

            // Die drei Typen durchgehen
            $typen = ['V', 'R', 'B'];
            foreach ($typen as $typ) {
                if (!isset($bart_data[$typ])) {
                    continue;
                }

                $typ_data = $bart_data[$typ];

                // Alle Felder für diesen Typ
                $fields = [
                    'aktiv' => isset($typ_data['aktiv']) ? '1' : '0',
                    'bezeichnung' => $typ_data['bezeichnung'] ?? '',
                    'beschreibung' => $typ_data['beschreibung'] ?? '',
                    'betrag_aktiv' => isset($typ_data['betrag_aktiv']) ? '1' : '0',
                    'betrag_limit' => intval($typ_data['betrag_limit'] ?? 0),
                    'wartezeit_aktiv' => isset($typ_data['wartezeit_aktiv']) ? '1' : '0',
                    'wartezeit_tage' => intval($typ_data['wartezeit_tage'] ?? 0),
                    'freigabe_vereinfacht' => isset($typ_data['freigabe_vereinfacht']) ? '1' : '0'
                ];

                foreach ($fields as $field => $value) {
                    $config_key = "bart_{$typ}_{$field}";
                    $config_type = is_numeric($value) ? 'number' : (in_array($value, ['0', '1']) ? 'boolean' : 'text');

                    // Alten Wert abrufen
                    $stmt = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
                    $stmt->execute([$config_key]);
                    $old_value = $stmt->fetchColumn();

                    if ($old_value != $value) {
                        // Wert aktualisieren oder einfügen
                        $update_stmt = $pdo->prepare("
                            INSERT INTO svconfig (config_key, config_value, config_type, description, category, updated_by)
                            VALUES (?, ?, ?, ?, 'antragstypen', ?)
                            ON DUPLICATE KEY UPDATE
                                config_value = VALUES(config_value),
                                updated_by = VALUES(updated_by),
                                updated_at = NOW()
                        ");
                        $update_stmt->execute([
                            $config_key,
                            $value,
                            $config_type,
                            "Typ $typ - $field",
                            $current_user['member_id']
                        ]);

                        $old_values[$config_key] = $old_value ?: '(leer)';
                        $new_values[$config_key] = $value;
                        $updated_count++;
                    }
                }
            }

            // Globale Einstellungen
            if (isset($bart_data['global'])) {
                $global_fields = [
                    'show_betrag_in_liste' => isset($bart_data['global']['show_betrag_in_liste']) ? '1' : '0',
                    'pflicht_bei_betrag' => intval($bart_data['global']['pflicht_bei_betrag'] ?? 100)
                ];

                foreach ($global_fields as $field => $value) {
                    $config_key = "bart_{$field}";
                    $config_type = is_numeric($value) && !in_array($value, ['0', '1']) ? 'number' : 'boolean';

                    $stmt = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
                    $stmt->execute([$config_key]);
                    $old_value = $stmt->fetchColumn();

                    if ($old_value != $value) {
                        $update_stmt = $pdo->prepare("
                            INSERT INTO svconfig (config_key, config_value, config_type, description, category, updated_by)
                            VALUES (?, ?, ?, ?, 'antragstypen', ?)
                            ON DUPLICATE KEY UPDATE
                                config_value = VALUES(config_value),
                                updated_by = VALUES(updated_by),
                                updated_at = NOW()
                        ");
                        $update_stmt->execute([
                            $config_key,
                            $value,
                            $config_type,
                            "Global - $field",
                            $current_user['member_id']
                        ]);

                        $old_values[$config_key] = $old_value ?: '(leer)';
                        $new_values[$config_key] = $value;
                        $updated_count++;
                    }
                }
            }

            if ($updated_count > 0) {
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'antragstypen_update',
                    "Antragstypen aktualisiert: $updated_count Einstellungen geändert",
                    'config',
                    null,
                    $old_values,
                    $new_values
                );

                header('Location: ?tab=admin_init&msg=antragstypen_saved');
                exit;
            } else {
                $success_message = "Keine Änderungen vorgenommen.";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Antragstypen-Speichern: " . $e->getMessage());
            $error_message = "❌ Fehler beim Speichern: " . $e->getMessage();
        }
    }
}

/**
 * ABSTIMMUNGSREGELN SPEICHERN
 *
 * POST-Parameter:
 * - save_voting_rules: 1
 * - voting: Array mit Regel-Konfigurationen
 */
if (isset($_POST['save_voting_rules'])) {
    $voting_data = $_POST['voting'] ?? [];

    if (empty($voting_data)) {
        $error_message = "Keine Voting-Daten übermittelt.";
    } else {
        try {
            $updated_count = 0;
            $old_values = [];
            $new_values = [];

            // Standard-Regel
            if (isset($voting_data['default_rule'])) {
                $config_key = 'voting_default_rule';
                $value = $voting_data['default_rule'];

                $stmt = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
                $stmt->execute([$config_key]);
                $old_value = $stmt->fetchColumn();

                if ($old_value != $value) {
                    $update_stmt = $pdo->prepare("
                        INSERT INTO svconfig (config_key, config_value, config_type, description, category, updated_by)
                        VALUES (?, ?, 'text', 'Standard-Abstimmungsregel', 'voting', ?)
                        ON DUPLICATE KEY UPDATE
                            config_value = VALUES(config_value),
                            updated_by = VALUES(updated_by),
                            updated_at = NOW()
                    ");
                    $update_stmt->execute([$config_key, $value, $current_user['member_id']]);

                    $old_values[$config_key] = $old_value ?: '(leer)';
                    $new_values[$config_key] = $value;
                    $updated_count++;
                }
            }

            // Regel-Aktivierungen und Labels/Beschreibungen
            $rule_keys = ['einfach', 'absolut', 'mehrheit_stimmber', 'zweidrittel', 'einstimmig'];
            foreach ($rule_keys as $key) {
                // Aktivierung
                $enable_key = "enable_{$key}";
                $config_key = "voting_{$enable_key}";
                $value = isset($voting_data[$enable_key]) ? '1' : '0';

                $stmt = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
                $stmt->execute([$config_key]);
                $old_value = $stmt->fetchColumn();

                if ($old_value != $value) {
                    $update_stmt = $pdo->prepare("
                        INSERT INTO svconfig (config_key, config_value, config_type, description, category, updated_by)
                        VALUES (?, ?, 'boolean', ?, 'voting', ?)
                        ON DUPLICATE KEY UPDATE
                            config_value = VALUES(config_value),
                            updated_by = VALUES(updated_by),
                            updated_at = NOW()
                    ");
                    $update_stmt->execute([
                        $config_key,
                        $value,
                        ucfirst($key) . " aktiviert",
                        $current_user['member_id']
                    ]);

                    $old_values[$config_key] = $old_value ?: '(leer)';
                    $new_values[$config_key] = $value;
                    $updated_count++;
                }

                // Label
                $label_key = "rule_{$key}_label";
                if (isset($voting_data[$label_key])) {
                    $config_key = "voting_{$label_key}";
                    $value = $voting_data[$label_key];

                    $stmt = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
                    $stmt->execute([$config_key]);
                    $old_value = $stmt->fetchColumn();

                    if ($old_value != $value) {
                        $update_stmt = $pdo->prepare("
                            INSERT INTO svconfig (config_key, config_value, config_type, description, category, updated_by)
                            VALUES (?, ?, 'text', ?, 'voting', ?)
                            ON DUPLICATE KEY UPDATE
                                config_value = VALUES(config_value),
                                updated_by = VALUES(updated_by),
                                updated_at = NOW()
                        ");
                        $update_stmt->execute([
                            $config_key,
                            $value,
                            "Label für {$key}",
                            $current_user['member_id']
                        ]);

                        $old_values[$config_key] = $old_value ?: '(leer)';
                        $new_values[$config_key] = $value;
                        $updated_count++;
                    }
                }

                // Beschreibung
                $desc_key = "rule_{$key}_desc";
                if (isset($voting_data[$desc_key])) {
                    $config_key = "voting_{$desc_key}";
                    $value = $voting_data[$desc_key];

                    $stmt = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
                    $stmt->execute([$config_key]);
                    $old_value = $stmt->fetchColumn();

                    if ($old_value != $value) {
                        $update_stmt = $pdo->prepare("
                            INSERT INTO svconfig (config_key, config_value, config_type, description, category, updated_by)
                            VALUES (?, ?, 'text', ?, 'voting', ?)
                            ON DUPLICATE KEY UPDATE
                                config_value = VALUES(config_value),
                                updated_by = VALUES(updated_by),
                                updated_at = NOW()
                        ");
                        $update_stmt->execute([
                            $config_key,
                            $value,
                            "Beschreibung für {$key}",
                            $current_user['member_id']
                        ]);

                        $old_values[$config_key] = $old_value ?: '(leer)';
                        $new_values[$config_key] = $value;
                        $updated_count++;
                    }
                }
            }

            if ($updated_count > 0) {
                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'voting_rules_update',
                    "Abstimmungsregeln aktualisiert: $updated_count Einstellungen geändert",
                    'config',
                    null,
                    $old_values,
                    $new_values
                );

                header('Location: ?tab=admin_init&msg=voting_rules_saved');
                exit;
            } else {
                $success_message = "Keine Änderungen vorgenommen.";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Voting-Rules-Speichern: " . $e->getMessage());
            $error_message = "❌ Fehler beim Speichern: " . $e->getMessage();
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
            $stmt = $pdo->prepare("SELECT * FROM svtodos WHERE todo_id = ?");
            $stmt->execute([$todo_id]);
            $old_todo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$old_todo) {
                $error_message = "ToDo nicht gefunden.";
            } else {
                // ToDo schließen
                $stmt = $pdo->prepare("
                    UPDATE svtodos 
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

/**
 * ToDo bearbeiten
 *
 * POST-Parameter:
 * - edit_todo: 1
 * - todo_id: Int (required)
 * - title: String
 * - description: String
 * - assigned_to_member_id: Int
 * - status: String
 * - entry_date: Date
 * - due_date: Date
 *
 * Aktion:
 * - ToDo aktualisieren
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['edit_todo'])) {
    $todo_id = intval($_POST['todo_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to_member_id = intval($_POST['assigned_to_member_id'] ?? 0);
    $status = $_POST['status'] ?? 'open';
    $entry_date = $_POST['entry_date'] ?? null;
    $due_date = $_POST['due_date'] ?? null;

    if (!$todo_id || !$title || !$description || !$assigned_to_member_id) {
        $error_message = "Pflichtfelder fehlen.";
    } else {
        try {
            // Alte Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM svtodos WHERE todo_id = ?");
            $stmt->execute([$todo_id]);
            $old_todo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_todo) {
                $error_message = "ToDo nicht gefunden.";
            } else {
                // ToDo aktualisieren
                $stmt = $pdo->prepare("
                    UPDATE svtodos
                    SET title = ?, description = ?, assigned_to_member_id = ?,
                        status = ?, entry_date = ?, due_date = ?
                    WHERE todo_id = ?
                ");
                $stmt->execute([
                    $title,
                    $description,
                    $assigned_to_member_id,
                    $status,
                    $entry_date ?: null,
                    $due_date ?: null,
                    $todo_id
                ]);

                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'todo_edit',
                    "ToDo bearbeitet: $title",
                    'todo',
                    $todo_id,
                    $old_todo,
                    [
                        'title' => $title,
                        'description' => $description,
                        'assigned_to_member_id' => $assigned_to_member_id,
                        'status' => $status,
                        'entry_date' => $entry_date,
                        'due_date' => $due_date
                    ]
                );

                $success_message = "✅ ToDo erfolgreich aktualisiert!";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim ToDo-Aktualisieren: " . $e->getMessage());
            $error_message = "❌ Fehler beim Aktualisieren: " . $e->getMessage();
        }
    }
}

/**
 * ToDo löschen
 *
 * POST-Parameter:
 * - delete_todo: 1
 * - todo_id: Int (required)
 *
 * Aktion:
 * - ToDo aus DB löschen
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['delete_todo'])) {
    $todo_id = intval($_POST['todo_id'] ?? 0);

    if (!$todo_id) {
        $error_message = "Ungültige ToDo-ID.";
    } else {
        try {
            // ToDo-Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM svtodos WHERE todo_id = ?");
            $stmt->execute([$todo_id]);
            $old_todo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_todo) {
                $error_message = "ToDo nicht gefunden.";
            } else {
                // ToDo löschen
                $stmt = $pdo->prepare("DELETE FROM svtodos WHERE todo_id = ?");
                $stmt->execute([$todo_id]);

                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'todo_delete',
                    "ToDo gelöscht: " . ($old_todo['description'] ?? 'Unbenannt'),
                    'todo',
                    $todo_id,
                    $old_todo,
                    null
                );

                $success_message = "✅ ToDo erfolgreich gelöscht!";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim ToDo-Löschen: " . $e->getMessage());
            $error_message = "❌ Fehler beim Löschen: " . $e->getMessage();
        }
    }
}

// ============================================
// TEXTBEARBEITUNG-VERWALTUNG
// ============================================

/**
 * Kollaborativen Text löschen (Admin)
 *
 * POST-Parameter:
 * - delete_collab_text: 1
 * - delete_collab_text_id: Int (required)
 *
 * Aktion:
 * - Text und alle zugehörigen Daten löschen (CASCADE)
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['delete_collab_text'])) {
    $text_id = intval($_POST['delete_collab_text_id'] ?? 0);

    if ($text_id > 0) {
        try {
            // Text-Infos holen (für Protokoll)
            $stmt = $pdo->prepare("
                SELECT t.title, t.meeting_id, t.initiator_member_id
                FROM svcollab_texts t
                WHERE t.text_id = ?
            ");
            $stmt->execute([$text_id]);
            $text_info = $stmt->fetch(PDO::FETCH_ASSOC);

            // Initiator-Daten über Adapter laden
            if ($text_info && $text_info['initiator_member_id']) {
                $initiator = get_member_by_id($pdo, $text_info['initiator_member_id']);
                if ($initiator) {
                    $text_info['initiator_first_name'] = $initiator['first_name'];
                    $text_info['initiator_last_name'] = $initiator['last_name'];
                }
            }

            if ($text_info) {
                // Text löschen (CASCADE löscht alle zugehörigen Daten)
                $stmt = $pdo->prepare("DELETE FROM svcollab_texts WHERE text_id = ?");
                $stmt->execute([$text_id]);

                // Admin-Aktion protokollieren
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'collab_text_delete',
                    "Kollaborativer Text gelöscht: {$text_info['title']} (ID: {$text_id})",
                    'collab_text',
                    $text_id,
                    $text_info,
                    null
                );

                $success_message = "✅ Text \"{$text_info['title']}\" erfolgreich gelöscht!";
            } else {
                $error_message = "❌ Text nicht gefunden!";
            }
        } catch (PDOException $e) {
            error_log("Admin: Fehler beim Text-Löschen: " . $e->getMessage());
            $error_message = "❌ Fehler beim Löschen: " . $e->getMessage();
        }
    }
}

// ============================================
// 3.5 TODO-VERWALTUNG (ADMIN)
// ============================================

/**
 * Admin: Neues ToDo erstellen
 */
if (isset($_POST['admin_create_todo'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = (int)($_POST['assigned_to_member_id'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;

    if (empty($title) || !$assigned_to) {
        $error_message = "Titel und Empfänger sind erforderlich.";
    } else {
        // Due date validieren
        if (!empty($due_date)) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $due_date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $due_date) {
                $error_message = "Ungültiges Datum.";
            }
        } else {
            $due_date = null;
        }

        if (!isset($error_message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO svtodos (
                        title, description, assigned_to_member_id, created_by_member_id,
                        due_date, status, is_private, entry_date
                    ) VALUES (?, ?, ?, ?, ?, 'open', ?, NOW())
                ");
                $stmt->execute([
                    $title,
                    $description,
                    $assigned_to,
                    $current_user['member_id'],
                    $due_date,
                    $is_private
                ]);

                $todo_id = $pdo->lastInsertId();

                // Logging
                $log = $pdo->prepare("INSERT INTO svtodo_log (todo_id, changed_by, change_type, old_value, new_value) VALUES (?, ?, 'admin-todo-erstellt', NULL, ?)");
                $log->execute([$todo_id, $current_user['member_id'], $title]);

                $success_message = '✅ ToDo erfolgreich erstellt.';
            } catch (PDOException $e) {
                error_log('Admin Todo Create Error: ' . $e->getMessage());
                $error_message = '❌ Fehler beim Erstellen: ' . $e->getMessage();
            }
        }
    }
}

/**
 * Admin: ToDo bearbeiten
 */
if (isset($_POST['admin_edit_todo'])) {
    $todo_id = (int)($_POST['todo_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = (int)($_POST['assigned_to_member_id'] ?? 0);
    $status = $_POST['status'] ?? 'open';
    $due_date = trim($_POST['due_date'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;

    $allowed_statuses = ['open', 'in progress', 'delayed', 'done'];

    if (!$todo_id || empty($title) || !$assigned_to || !in_array($status, $allowed_statuses)) {
        $error_message = "Ungültige Eingabe.";
    } else {
        // Due date validieren
        if (!empty($due_date)) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $due_date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $due_date) {
                $error_message = "Ungültiges Datum.";
            }
        } else {
            $due_date = null;
        }

        if (!isset($error_message)) {
            try {
                // Alte Daten für Log
                $stmt = $pdo->prepare("SELECT * FROM svtodos WHERE todo_id = ?");
                $stmt->execute([$todo_id]);
                $old_todo = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$old_todo) {
                    $error_message = "ToDo nicht gefunden.";
                } else {
                    // Update mit completed_at
                    if ($status === 'done' && $old_todo['status'] !== 'done') {
                        $stmt = $pdo->prepare("
                            UPDATE svtodos
                            SET title = ?, description = ?, assigned_to_member_id = ?,
                                status = ?, due_date = ?, is_private = ?, completed_at = NOW()
                            WHERE todo_id = ?
                        ");
                    } elseif ($status !== 'done' && $old_todo['status'] === 'done') {
                        $stmt = $pdo->prepare("
                            UPDATE svtodos
                            SET title = ?, description = ?, assigned_to_member_id = ?,
                                status = ?, due_date = ?, is_private = ?, completed_at = NULL
                            WHERE todo_id = ?
                        ");
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE svtodos
                            SET title = ?, description = ?, assigned_to_member_id = ?,
                                status = ?, due_date = ?, is_private = ?
                            WHERE todo_id = ?
                        ");
                    }

                    $params = [$title, $description, $assigned_to, $status, $due_date, $is_private, $todo_id];
                    $stmt->execute($params);

                    // Logging
                    $log = $pdo->prepare("INSERT INTO svtodo_log (todo_id, changed_by, change_type, old_value, new_value) VALUES (?, ?, 'admin-todo-bearbeitet', ?, ?)");
                    $log->execute([$todo_id, $current_user['member_id'], json_encode($old_todo), json_encode($params)]);

                    $success_message = '✅ ToDo erfolgreich aktualisiert.';
                }
            } catch (PDOException $e) {
                error_log('Admin Todo Edit Error: ' . $e->getMessage());
                $error_message = '❌ Fehler beim Aktualisieren: ' . $e->getMessage();
            }
        }
    }
}

/**
 * Admin: ToDo löschen
 */
if (isset($_POST['admin_delete_todo'])) {
    $todo_id = (int)($_POST['todo_id'] ?? 0);

    if (!$todo_id) {
        $error_message = "Ungültige ToDo-ID.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT title FROM svtodos WHERE todo_id = ?");
            $stmt->execute([$todo_id]);
            $todo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$todo) {
                $error_message = "ToDo nicht gefunden.";
            } else {
                // Logging (vor dem Löschen)
                $log = $pdo->prepare("INSERT INTO svtodo_log (todo_id, changed_by, change_type, old_value, new_value) VALUES (?, ?, 'admin-todo-geloescht', ?, NULL)");
                $log->execute([$todo_id, $current_user['member_id'], $todo['title']]);

                // Löschen
                $stmt = $pdo->prepare("DELETE FROM svtodos WHERE todo_id = ?");
                $stmt->execute([$todo_id]);

                $success_message = '✅ ToDo erfolgreich gelöscht.';
            }
        } catch (PDOException $e) {
            error_log('Admin Todo Delete Error: ' . $e->getMessage());
            $error_message = '❌ Fehler beim Löschen: ' . $e->getMessage();
        }
    }
}

/**
 * Terminabfrage löschen (Admin-Funktion)
 *
 * POST-Parameter:
 * - admin_delete_poll: 1
 * - poll_id: Int (required)
 *
 * Aktion:
 * - Alle Antworten löschen
 * - Alle Optionen löschen
 * - Umfrage löschen
 * - Admin-Aktion protokollieren
 */
if (isset($_POST['admin_delete_poll'])) {
    $poll_id = intval($_POST['poll_id'] ?? 0);

    if (!$poll_id) {
        $error_message = "Ungültige Umfrage-ID.";
    } else {
        try {
            $pdo->beginTransaction();

            // Umfrage-Daten für Log abrufen
            $stmt = $pdo->prepare("SELECT * FROM svpolls WHERE poll_id = ?");
            $stmt->execute([$poll_id]);
            $poll = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$poll) {
                $error_message = "Terminabfrage nicht gefunden.";
            } else {
                // 1. Antworten löschen
                $stmt = $pdo->prepare("DELETE FROM svpoll_responses WHERE poll_id = ?");
                $stmt->execute([$poll_id]);

                // 2. Terminvorschläge (dates) löschen
                $stmt = $pdo->prepare("DELETE FROM svpoll_dates WHERE poll_id = ?");
                $stmt->execute([$poll_id]);

                // 3. Teilnehmer löschen
                $stmt = $pdo->prepare("DELETE FROM svpoll_participants WHERE poll_id = ?");
                $stmt->execute([$poll_id]);

                // 4. Umfrage löschen
                $stmt = $pdo->prepare("DELETE FROM svpolls WHERE poll_id = ?");
                $stmt->execute([$poll_id]);

                $pdo->commit();

                // Admin-Log
                log_admin_action(
                    $pdo,
                    $current_user['member_id'],
                    'poll_delete',
                    "Terminabfrage gelöscht: {$poll['title']}",
                    'poll',
                    $poll_id,
                    $poll,
                    null
                );

                header('Location: ?tab=admin&msg=poll_deleted');
                exit;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Admin: Fehler beim Löschen der Terminabfrage: " . $e->getMessage());
            $error_message = "❌ Fehler beim Löschen: " . $e->getMessage();
        }
    }
}

// ============================================
// 4. DATEN LADEN
// ============================================

// Alle Meetings laden
$meetings = $pdo->query("
    SELECT m.*,
        (SELECT COUNT(*) FROM svmeeting_participants WHERE meeting_id = m.meeting_id) as participant_count,
        (SELECT COUNT(*) FROM svagenda_items WHERE meeting_id = m.meeting_id) as agenda_count
    FROM svmeetings m
    ORDER BY m.meeting_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Inviter-Daten über Adapter laden
foreach ($meetings as &$meeting) {
    if ($meeting['invited_by_member_id']) {
        $inviter = get_member_by_id($pdo, $meeting['invited_by_member_id']);
        if ($inviter) {
            $meeting['inviter_first_name'] = $inviter['first_name'];
            $meeting['inviter_last_name'] = $inviter['last_name'];
        }
    }
}

// Teilnehmer für jedes Meeting laden
foreach ($meetings as &$meeting) {
    $stmt = $pdo->prepare("SELECT member_id FROM svmeeting_participants WHERE meeting_id = ?");
    $stmt->execute([$meeting['meeting_id']]);
    $meeting['participant_ids'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
unset($meeting); // Wichtig: Referenz auflösen, um Duplikate zu vermeiden

// Alle Mitglieder laden (über Wrapper-Funktion)
// Funktioniert mit members ODER berechtigte Tabelle (siehe config_adapter.php)
$members = get_all_members($pdo);

// Alle Abwesenheiten laden (für Admin-Verwaltung)
$all_absences = get_absences_with_names($pdo);

// Offene ToDos laden
$open_todos = $pdo->query("
    SELECT t.*,
        ai.title as agenda_title,
        meet.meeting_name, meet.meeting_date
    FROM svtodos t
    LEFT JOIN svagenda_items ai ON t.item_id = ai.item_id
    LEFT JOIN svmeetings meet ON t.meeting_id = meet.meeting_id
    WHERE t.status = 'open'
    ORDER BY t.due_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Member-Daten über Adapter laden
foreach ($open_todos as &$todo) {
    if ($todo['assigned_to_member_id']) {
        $member = get_member_by_id($pdo, $todo['assigned_to_member_id']);
        if ($member) {
            $todo['first_name'] = $member['first_name'];
            $todo['last_name'] = $member['last_name'];
        }
    }
}
unset($todo);

// Admin-Log laden (letzte 50 Einträge)
$admin_logs = $pdo->query("
    SELECT al.*
    FROM svadmin_log al
    ORDER BY al.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Admin-Daten über Adapter laden
foreach ($admin_logs as &$log) {
    if ($log['admin_member_id']) {
        $admin = get_member_by_id($pdo, $log['admin_member_id']);
        if ($admin) {
            $log['first_name'] = $admin['first_name'];
            $log['last_name'] = $admin['last_name'];
        }
    }
}
unset($log);

// Externe Zugriffs-Logs laden (letzte 100 Einträge)
require_once __DIR__ . '/external_participants_functions.php';
$external_access_logs = get_external_access_logs($pdo, ['limit' => 100]);

// Statistiken für externe Zugriffe (letzte 30 Tage)
$external_access_stats = count_external_access_by_type($pdo, 30);

// Statistiken berechnen
$stats = [
    'total' => count($meetings),
    'preparation' => count(array_filter($meetings, fn($m) => $m['status'] === 'preparation')),
    'active' => count(array_filter($meetings, fn($m) => $m['status'] === 'active')),
    'ended' => count(array_filter($meetings, fn($m) => $m['status'] === 'ended')),
    'archived' => count(array_filter($meetings, fn($m) => $m['status'] === 'archived'))
];

// Alle kollaborativen Texte laden (Meeting-spezifisch UND allgemein)
$all_collab_texts = $pdo->query("
    SELECT t.*,
        mt.meeting_name
    FROM svcollab_texts t
    LEFT JOIN svmeetings mt ON t.meeting_id = mt.meeting_id
    ORDER BY t.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Initiator-Daten über Adapter laden
foreach ($all_collab_texts as &$text) {
    if ($text['initiator_member_id']) {
        $initiator = get_member_by_id($pdo, $text['initiator_member_id']);
        if ($initiator) {
            $text['initiator_first_name'] = $initiator['first_name'];
            $text['initiator_last_name'] = $initiator['last_name'];
        }
    }
}
unset($text);

?>