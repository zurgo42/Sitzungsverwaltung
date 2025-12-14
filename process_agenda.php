<?php
/**
 * process_agenda.php - Verarbeitung von Tagesordnungs-Aktionen
 * Bereinigt: 29.10.2025 00:30 MEZ
 * Erweitert: 07.11.2025 - v2.1
 * Bugfix: 12.11.2025 - Fehlende Funktionen und Handler hinzugefügt
 * Bugfix: 12.11.2025 14:00 - get_member_name() entfernt (existiert in module_helpers.php)
 *
 * Diese Datei verarbeitet alle POST-Anfragen aus tab_agenda.php
 * Trennung von Business-Logik und Präsentation (MVC-Prinzip)
 *
 * WICHTIG: Diese Datei wird in index.php NACH dem Laden von functions.php eingebunden
 * Voraussetzungen: $pdo, $current_user, recalculate_item_metrics(), get_next_top_number()
 */

// Hilfsfunktion: Vollständigen Link zur Sitzung generieren
function get_full_meeting_link($meeting_id) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    return $protocol . $host . $script . "?tab=agenda&meeting_id=" . $meeting_id;
}

// Berechtigungen ermitteln (falls Meeting geladen)
$is_secretary = false;
$is_chairman = false;
if (isset($meeting) && $meeting) {
    $is_secretary = ($meeting['secretary_member_id'] == $current_user['member_id']);
    $is_chairman = ($meeting['chairman_member_id'] == $current_user['member_id']);
}

// ============================================
// 1. TOP-VERWALTUNG
// ============================================

/**
 * Neuen TOP hinzufügen
 * 
 * POST-Parameter:
 * - add_agenda_item: 1
 * - title: String (required)
 * - description: String (optional)
 * - priority: Float (1-10, default: 5)
 * - duration: Int (Minuten, default: 15)
 * - is_confidential: Checkbox (1 oder nicht gesetzt)
 * 
 * Verwendet von: tab_agenda.php - "Neuen TOP hinzufügen" Formular
 * Redirect: ?tab=agenda&meeting_id=X#top-ITEM_ID
 */
if (isset($_POST['add_agenda_item'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'information';
    $proposal_text = ($category === 'antrag_beschluss') ? trim($_POST['proposal_text'] ?? '') : '';
    $priority = floatval($_POST['priority'] ?? 5.0);
    $duration = intval($_POST['duration'] ?? 15);
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

    if ($current_meeting_id && $title) {
        try {
            // Transaktion starten für atomare Operation
            $pdo->beginTransaction();

            // TOP-Nummer automatisch vergeben
            $top_number = get_next_top_number($pdo, $current_meeting_id, $is_confidential);

            // TOP in Datenbank einfügen
            $stmt = $pdo->prepare("
                INSERT INTO svagenda_items
                (meeting_id, top_number, title, description, category, proposal_text, priority, estimated_duration, is_confidential, created_by_member_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $current_meeting_id,
                $top_number,
                $title,
                $description,
                $category,
                $proposal_text,
                $priority,
                $duration,
                $is_confidential,
                $current_user['member_id']
            ]);

            $new_item_id = $pdo->lastInsertId();

            // BUGFIX: Keinen initialen "-" Kommentar mehr erstellen
            // Stattdessen nur initiale Bewertung ohne sichtbaren Kommentar
            $stmt = $pdo->prepare("
                INSERT INTO svagenda_comments (item_id, member_id, comment_text, priority_rating, duration_estimate, created_at)
                VALUES (?, ?, '', ?, ?, NOW())
            ");
            $stmt->execute([$new_item_id, $current_user['member_id'], $priority, $duration]);

            // Transaktion abschließen
            $pdo->commit();

            // Durchschnittswerte berechnen (NACH commit, nicht in Transaktion)
            // Nicht nötig beim Erstellen, da nur ein Kommentar existiert und Werte schon korrekt sind
            // recalculate_item_metrics($pdo, $new_item_id);

            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$new_item_id");
            exit;

        } catch (PDOException $e) {
            // Rollback bei Fehler
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("FEHLER beim Hinzufügen des TOP: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
            $error = "Fehler beim Hinzufügen des TOP: " . $e->getMessage();
        }
    }
}

/**
 * Einzelnen TOP editieren (Ersteller in Vorbereitung)
 */
if (isset($_POST['edit_agenda_item'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'information';
    $proposal_text = ($category === 'antrag_beschluss') ? trim($_POST['proposal_text'] ?? '') : '';

    // Debug-Log
    error_log("EDIT TOP: item_id=$item_id, category=$category, title=$title");

    if ($item_id && $title) {
        try {
            // Prüfen ob User der Ersteller ist
            $stmt = $pdo->prepare("
                SELECT ai.created_by_member_id, ai.meeting_id, m.status, ai.category as old_category
                FROM svagenda_items ai
                JOIN svmeetings m ON ai.meeting_id = m.meeting_id
                WHERE ai.item_id = ?
            ");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();

            error_log("EDIT TOP Check: is_creator=" . ($item && $item['created_by_member_id'] == $current_user['member_id'] ? 'YES' : 'NO') .
                      ", status=" . ($item['status'] ?? 'NULL') .
                      ", old_category=" . ($item['old_category'] ?? 'NULL'));

            // Nur editierbar wenn Ersteller UND Meeting in Vorbereitung
            if ($item &&
                $item['created_by_member_id'] == $current_user['member_id'] &&
                $item['status'] === 'preparation') {

                $stmt = $pdo->prepare("
                    UPDATE svagenda_items
                    SET title = ?, description = ?, category = ?, proposal_text = ?
                    WHERE item_id = ?
                ");
                $stmt->execute([$title, $description, $category, $proposal_text, $item_id]);

                error_log("EDIT TOP Success: Updated category from {$item['old_category']} to $category");

                header("Location: ?tab=agenda&meeting_id={$item['meeting_id']}#top-$item_id");
                exit;
            } else {
                error_log("EDIT TOP FAILED: Berechtigung verweigert oder falscher Status");
            }
        } catch (PDOException $e) {
            error_log("Fehler beim Editieren des TOP: " . $e->getMessage());
        }
    } else {
        error_log("EDIT TOP FAILED: Missing item_id or title");
    }
}
/**
 * Alle TOP-Änderungen speichern
 * 
 * POST-Parameter:
 * - save_all_changes: 1
 * - edit_title[ITEM_ID]: String (optional, pro editierbarem TOP)
 * - edit_description[ITEM_ID]: String (optional, pro editierbarem TOP)
 * - priority[ITEM_ID]: Float (optional, für Bewertungen)
 * - duration[ITEM_ID]: Int (optional, für Bewertungen)
 * 
 * Verwendet von: tab_agenda.php - Großes Formular im Status "preparation"
 * Redirect: ?tab=agenda&meeting_id=X
 */
if (isset($_POST['save_all_changes']) || isset($_POST['save_all_preparation'])) {
    $edit_titles = $_POST['edit_title'] ?? [];
    $edit_descriptions = $_POST['edit_description'] ?? [];
    $priorities = $_POST['priority'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $comments = $_POST['comment_text'] ?? [];
    
    try {
        // 1. TOP-Änderungen speichern (nur für Ersteller)
        foreach ($edit_titles as $item_id => $title) {
            $item_id = intval($item_id);
            $title = trim($title);
            $description = trim($edit_descriptions[$item_id] ?? '');
            $category = $_POST['edit_category'][$item_id] ?? 'information';
            $proposal_text = ($category === 'antrag_beschluss') ? trim($_POST['edit_proposal'][$item_id] ?? '') : '';
            
            // Prüfen ob User der Ersteller ist
            $stmt = $pdo->prepare("
                SELECT created_by_member_id 
                FROM svagenda_items 
                WHERE item_id = ? AND meeting_id = ?
            ");
            $stmt->execute([$item_id, $current_meeting_id]);
            $item = $stmt->fetch();
            
            if ($item && $item['created_by_member_id'] == $current_user['member_id']) {
                $stmt = $pdo->prepare("
                    UPDATE svagenda_items 
                    SET title = ?, description = ?, category = ?, proposal_text = ?
                    WHERE item_id = ?
                ");
                $stmt->execute([$title, $description, $category, $proposal_text, $item_id]);
            }
        }
        
        // 2. Bewertungen speichern (Priorität/Dauer)
        foreach ($priorities as $item_id => $priority) {
            $item_id = intval($item_id);
            $priority = ($priority !== '') ? floatval($priority) : null;
            $duration = isset($durations[$item_id]) && $durations[$item_id] !== '' ? intval($durations[$item_id]) : null;
            
            // Nur speichern wenn mindestens ein Wert vorhanden
            if ($priority !== null || $duration !== null) {
                // Prüfen ob User bereits einen Kommentar hat
                $stmt = $pdo->prepare("
                    SELECT comment_id 
                    FROM svagenda_comments 
                    WHERE item_id = ? AND member_id = ?
                ");
                $stmt->execute([$item_id, $current_user['member_id']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Bestehenden Kommentar aktualisieren
                    $stmt = $pdo->prepare("
                        UPDATE svagenda_comments 
                        SET priority_rating = ?, duration_estimate = ?
                        WHERE comment_id = ?
                    ");
                    $stmt->execute([$priority, $duration, $existing['comment_id']]);
                } else {
                    // BUGFIX: Neuen Kommentar erstellen (ohne sichtbaren Text)
                    $stmt = $pdo->prepare("
                        INSERT INTO svagenda_comments (item_id, member_id, comment_text, priority_rating, duration_estimate, created_at)
                        VALUES (?, ?, '', ?, ?, NOW())
                    ");
                    $stmt->execute([$item_id, $current_user['member_id'], $priority, $duration]);
                }
                
                // Durchschnittswerte neu berechnen
                recalculate_item_metrics($pdo, $item_id);
            }
        }
        
        // 3. Kommentare speichern
        foreach ($comments as $item_id => $comment_text) {
            $item_id = intval($item_id);
            $comment_text = trim($comment_text);
            
            if (!empty($comment_text)) {
                $timestamp = date('d.m.Y H:i');
                
                // Prüfen ob User bereits Kommentar hat
                $stmt = $pdo->prepare("
                    SELECT comment_id, comment_text
                    FROM svagenda_comments 
                    WHERE item_id = ? AND member_id = ?
                ");
                $stmt->execute([$item_id, $current_user['member_id']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Anhängen
                    $old_text = trim($existing['comment_text']);
                    if ($old_text === '-') $old_text = ''; // Platzhalter entfernen
                    $new_text = empty($old_text) ? "[$timestamp]:\n" . $comment_text : $old_text . "\n\n[$timestamp]:\n" . $comment_text;
                    
                    $stmt = $pdo->prepare("
                        UPDATE svagenda_comments 
                        SET comment_text = ?, updated_at = NOW()
                        WHERE comment_id = ?
                    ");
                    $stmt->execute([$new_text, $existing['comment_id']]);
                } else {
                    // Neu erstellen
                    $new_text = "[$timestamp]:\n" . $comment_text;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO svagenda_comments (item_id, member_id, comment_text, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$item_id, $current_user['member_id'], $new_text]);
                }
            }
        }
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
        
    } catch (PDOException $e) {
        error_log("Fehler beim Speichern der Änderungen: " . $e->getMessage());
        $error = "Fehler beim Speichern der Änderungen";
    }
}

/**
 * Bewertungen aus Übersichtstabelle speichern
 * 
 * POST-Parameter:
 * - save_ratings_overview: 1
 * - priority_rating[item_id]: Float (1-10, optional)
 * - duration_estimate[item_id]: Int (Minuten, optional)
 * 
 * Besonderheit: Priorität und Dauer können unabhängig voneinander gespeichert werden
 * 
 * Verwendet von: module_agenda_overview.php - Übersichtstabelle
 * Redirect: ?tab=agenda&meeting_id=X
 */
if (isset($_POST['save_ratings_overview'])) {
    $priorities = $_POST['priority_rating'] ?? [];
    $durations = $_POST['duration_estimate'] ?? [];
    
    try {
        // Alle übermittelten Werte durchgehen
        $all_item_ids = array_unique(array_merge(array_keys($priorities), array_keys($durations)));
        
        foreach ($all_item_ids as $item_id) {
            $item_id = intval($item_id);
            $priority = isset($priorities[$item_id]) && $priorities[$item_id] !== '' ? floatval($priorities[$item_id]) : null;
            $duration = isset($durations[$item_id]) && $durations[$item_id] !== '' ? intval($durations[$item_id]) : null;
            
            // Nur speichern wenn mindestens ein Wert vorhanden
            if ($priority !== null || $duration !== null) {
                // Prüfen ob bereits Kommentar/Bewertung existiert
                $stmt = $pdo->prepare("
                    SELECT comment_id, priority_rating, duration_estimate 
                    FROM svagenda_comments 
                    WHERE item_id = ? AND member_id = ?
                ");
                $stmt->execute([$item_id, $current_user['member_id']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // UPDATE: Nur die Felder updaten die übergeben wurden
                    $updates = [];
                    $params = [];
                    
                    if ($priority !== null) {
                        $updates[] = "priority_rating = ?";
                        $params[] = $priority;
                    }
                    if ($duration !== null) {
                        $updates[] = "duration_estimate = ?";
                        $params[] = $duration;
                    }
                    
                    if (!empty($updates)) {
                        $sql = "UPDATE svagenda_comments SET " . implode(", ", $updates) . " WHERE comment_id = ?";
                        $params[] = $existing['comment_id'];
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                    }
                } else {
                    // INSERT: Neuen Eintrag mit leerem Kommentar aber Bewertungen
                    $stmt = $pdo->prepare("
                        INSERT INTO svagenda_comments (item_id, member_id, comment_text, priority_rating, duration_estimate, created_at)
                        VALUES (?, ?, '', ?, ?, NOW())
                    ");
                    $stmt->execute([$item_id, $current_user['member_id'], $priority, $duration]);
                }
                
                // Durchschnittswerte neu berechnen
                recalculate_item_metrics($pdo, $item_id);
            }
        }
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
        
    } catch (PDOException $e) {
        error_log("Fehler beim Speichern der Bewertungen: " . $e->getMessage());
        $error = "Fehler beim Speichern der Bewertungen";
    }
}

// ============================================
// 2. MEETING-STEUERUNG
// ============================================

/**
 * Sitzungsrollen aktualisieren (Vorsitzender, Sekretär)
 * 
 * POST-Parameter:
 * - update_meeting_roles: 1
 * - chairman_id: Int (required)
 * - secretary_id: Int (required)
 * 
 * Verwendet von: tab_agenda.php - Rollen-Box während aktiver Sitzung (nur Sekretär)
 * Redirect: ?tab=agenda&meeting_id=X
 */
if (isset($_POST['update_meeting_roles'])) {
    $chairman_id = intval($_POST['chairman_id'] ?? 0);
    $secretary_id = intval($_POST['secretary_id'] ?? 0);
    
    // Prüfen ob User Sekretär ist
    $stmt = $pdo->prepare("SELECT secretary_member_id FROM svmeetings WHERE meeting_id = ?");
    $stmt->execute([$current_meeting_id]);
    $meeting = $stmt->fetch();
    
    if ($meeting && $meeting['secretary_member_id'] == $current_user['member_id']) {
        if ($chairman_id && $secretary_id) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE svmeetings 
                    SET chairman_member_id = ?, secretary_member_id = ? 
                    WHERE meeting_id = ?
                ");
                $stmt->execute([$chairman_id, $secretary_id, $current_meeting_id]);
                
                header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
                exit;
                
            } catch (PDOException $e) {
                error_log("Fehler beim Aktualisieren der Rollen: " . $e->getMessage());
                $error = "Fehler beim Aktualisieren der Rollen";
            }
        }
    }
}

/**
 * Anwesenheitsliste speichern
 * 
 * POST-Parameter:
 * - save_attendance: 1
 * - attendance[MEMBER_ID]: 'present'|'partial'|'absent' (für jeden Teilnehmer)
 * 
 * Verwendet von: tab_agenda.php - Teilnehmerliste-Accordion (nur Sekretär)
 * Redirect: ?tab=agenda&meeting_id=X
 */
if (isset($_POST['save_attendance'])) {
    $attendance = $_POST['attendance'] ?? [];
    
    // Prüfen ob User Sekretär ist
    $stmt = $pdo->prepare("SELECT secretary_member_id FROM svmeetings WHERE meeting_id = ?");
    $stmt->execute([$current_meeting_id]);
    $meeting = $stmt->fetch();
    
    if ($meeting && $meeting['secretary_member_id'] == $current_user['member_id']) {
        try {
            foreach ($attendance as $member_id => $status) {
                $member_id = intval($member_id);
                $status = in_array($status, ['present', 'partial', 'absent']) ? $status : 'absent';
                
                $stmt = $pdo->prepare("
                    UPDATE svmeeting_participants 
                    SET attendance_status = ? 
                    WHERE meeting_id = ? AND member_id = ?
                ");
                $stmt->execute([$status, $current_meeting_id, $member_id]);
            }
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Speichern der Anwesenheit: " . $e->getMessage());
            $error = "Fehler beim Speichern der Anwesenheit";
        }
    }
}

/**
 * TOP als aktiv markieren
 * 
 * POST-Parameter:
 * - set_active_top: 1
 * - item_id: Int (required)
 * 
 * Verwendet von: tab_agenda.php - Button beim TOP (nur Sekretär während Sitzung)
 * Redirect: ?tab=agenda&meeting_id=X#top-ITEM_ID
 */
if (isset($_POST['set_active_top'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    
    // Prüfen ob User Sekretär ist und Meeting aktiv
    $stmt = $pdo->prepare("
        SELECT secretary_member_id, status 
        FROM svmeetings 
        WHERE meeting_id = ?
    ");
    $stmt->execute([$current_meeting_id]);
    $meeting = $stmt->fetch();
    
    if ($meeting && 
        $meeting['secretary_member_id'] == $current_user['member_id'] && 
        $meeting['status'] === 'active') {
        
        try {
            // Alle TOPs auf inaktiv setzen
            $stmt = $pdo->prepare("
                UPDATE svagenda_items 
                SET is_active = 0 
                WHERE meeting_id = ?
            ");
            $stmt->execute([$current_meeting_id]);
            
            // Gewählten TOP aktivieren
            $stmt = $pdo->prepare("
                UPDATE svagenda_items 
                SET is_active = 1 
                WHERE item_id = ? AND meeting_id = ?
            ");
            $stmt->execute([$item_id, $current_meeting_id]);
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Setzen des aktiven TOP: " . $e->getMessage());
            $error = "Fehler beim Setzen des aktiven TOP";
        }
    }
}

/**
 * Sitzung schließen
 * 
 * POST-Parameter:
 * - end_meeting: 1
 * 
 * Verwendet von: tab_agenda.php - Button "Sitzung schließen" (Sekretär/Vorsitzender)
 * Redirect: ?tab=agenda&meeting_id=X
 * 
 * Aktion:
 * 1. Meeting-Status auf 'ended' setzen
 * 2. ended_at Zeitstempel setzen
 * 3. TOP 999 (Sitzungsende) mit Zeitstempel erstellen
 * 4. ToDo für Sekretär erstellen (Protokoll fertigstellen)
 */
if (isset($_POST['end_meeting'])) {
    // Prüfen ob User Sekretär oder Vorsitzender ist
       // SELECT secretary_member_id, chairman_member_id, status, feedback_deadline_hours 

	$stmt = $pdo->prepare("
        SELECT secretary_member_id, chairman_member_id, status 
        FROM svmeetings 
        WHERE meeting_id = ?
    ");
    $stmt->execute([$current_meeting_id]);
    $meeting = $stmt->fetch();
    
    if ($meeting && 
        ($meeting['secretary_member_id'] == $current_user['member_id'] || 
         $meeting['chairman_member_id'] == $current_user['member_id']) &&
        $meeting['status'] === 'active') {
        
        try {
            // 1. Meeting beenden
            $stmt = $pdo->prepare("
                UPDATE svmeetings 
                SET status = 'ended', ended_at = NOW() 
                WHERE meeting_id = ?
            ");
            $stmt->execute([$current_meeting_id]);
            
            // 2. TOP 999 (Sitzungsende) erstellen falls nicht vorhanden
            $stmt = $pdo->prepare("
                SELECT item_id 
                FROM svagenda_items 
                WHERE meeting_id = ? AND top_number = 999
            ");
            $stmt->execute([$current_meeting_id]);
            
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO svagenda_items 
                    (meeting_id, top_number, title, description, is_confidential, created_by_member_id, protocol_notes)
                    VALUES (?, 999, 'Sitzungsende', 'Automatisch erstellt', 0, ?, '')
                ");
                $stmt->execute([$current_meeting_id, $current_user['member_id']]);
            }
            
            // 3. ToDo für Sekretär erstellen (Protokoll fertigstellen) mit Datum und Link
            $feedback_hours = 72;
            $due_date = date('Y-m-d', strtotime("+$feedback_hours hours +1 day"));

            // Meeting-Daten für ToDo holen
            $stmt_meeting = $pdo->prepare("SELECT meeting_name, meeting_date FROM svmeetings WHERE meeting_id = ?");
            $stmt_meeting->execute([$current_meeting_id]);
            $meeting_data = $stmt_meeting->fetch();

            $todo_title = "Protokoll fertigstellen: " . ($meeting_data['meeting_name'] ?? 'Sitzung') . " vom " . date('d.m.Y', strtotime($meeting_data['meeting_date']));
            $todo_description = "Bitte das Protokoll vervollständigen und zur Genehmigung freigeben.\n\nLink: " . get_full_meeting_link($current_meeting_id);

            $stmt = $pdo->prepare("
                INSERT INTO svtodos
                (meeting_id, assigned_to_member_id, title, description, status, due_date, created_by_member_id, entry_date)
                VALUES (?, ?, ?, ?, 'open', ?, ?, CURDATE())
            ");
            $stmt->execute([
                $current_meeting_id,
                $meeting['secretary_member_id'],
                $todo_title,
                $todo_description,
                $due_date,
                $current_user['member_id']
            ]);
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Beenden der Sitzung: " . $e->getMessage());
            $error = "Fehler beim Beenden der Sitzung";
        }
    }
}

/**
 * Protokoll genehmigen (durch Vorsitzenden)
 * 
 * POST-Parameter:
 * - approve_protocol: 1
 * 
 * Verwendet von: tab_agenda.php - Button wenn Protokoll zur Genehmigung vorliegt
 * Redirect: ?tab=agenda&meeting_id=X
 * 
 * Aktion:
 * 1. Meeting-Status auf 'archived' setzen
 * 2. ToDo des Vorsitzenden als erledigt markieren
 */
if (isset($_POST['approve_protocol'])) {
    // Prüfen ob User Vorsitzender ist
    $stmt = $pdo->prepare("
        SELECT chairman_member_id, status 
        FROM svmeetings 
        WHERE meeting_id = ?
    ");
    $stmt->execute([$current_meeting_id]);
    $meeting = $stmt->fetch();
    
    if ($meeting && 
        $meeting['chairman_member_id'] == $current_user['member_id'] &&
        ($meeting['status'] === 'ended' || $meeting['status'] === 'protocol_ready')) {
        
        try {
            // 1. Meeting archivieren
            $stmt = $pdo->prepare("
                UPDATE svmeetings 
                SET status = 'archived' 
                WHERE meeting_id = ?
            ");
            $stmt->execute([$current_meeting_id]);
            
            // 2. ToDo "Protokoll genehmigen" als erledigt markieren
            $stmt = $pdo->prepare("
                UPDATE svtodos 
                SET status = 'done', completed_at = NOW() 
                WHERE meeting_id = ? 
                AND assigned_to_member_id = ? 
                AND title LIKE '%Protokoll genehmigen%'
                AND status = 'open'
            ");
            $stmt->execute([$current_meeting_id, $current_user['member_id']]);
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Genehmigen des Protokolls: " . $e->getMessage());
            $error = "Fehler beim Genehmigen des Protokolls";
        }
    }
}

// ============================================
// 3. KOMMENTARE
// ============================================

/**
 * Einzelnen Kommentar hinzufügen (beim aktiven TOP)
 * 
 * POST-Parameter:
 * - save_single_comment: 1
 * - item_id: Int (required)
 * - comment_text: String (required)
 * 
 * Verwendet von: tab_agenda.php - Kommentar-Formular beim aktiven TOP
 * Redirect: ?tab=agenda&meeting_id=X#top-ITEM_ID
 */
if (isset($_POST['save_single_comment'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $comment_text = trim($_POST['comment_text'] ?? '');
    
    if ($item_id && $comment_text) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO svagenda_comments (item_id, member_id, comment_text, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$item_id, $current_user['member_id'], $comment_text]);
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Hinzufügen des Kommentars: " . $e->getMessage());
            $error = "Fehler beim Hinzufügen des Kommentars";
        }
    }
}

/**
 * Kommentar speichern oder aktualisieren (pro Teilnehmer ein editierbarer Kommentar pro TOP)
 * 
 * POST-Parameter:
 * - save_comment: 1
 * - item_id: Int (required)
 * - comment_text: String (required)
 * 
 * Verwendet von: tab_agenda.php - Editierbares Kommentarfeld in preparation
 * Redirect: ?tab=agenda&meeting_id=X#top-ITEM_ID
 */
if (isset($_POST['save_comment'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $comment_text = trim($_POST['comment_text'] ?? '');
    
    if ($item_id && $comment_text) {
        try {
            // Timestamp
            $timestamp = date('d.m.Y H:i');
            
            // Prüfen ob User bereits einen Kommentar hat
            $stmt = $pdo->prepare("
                SELECT comment_id, comment_text
                FROM svagenda_comments 
                WHERE item_id = ? AND member_id = ?
            ");
            $stmt->execute([$item_id, $current_user['member_id']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // ANHÄNGEN: Neuer Kommentar mit Timestamp an bestehenden
                $old_text = trim($existing['comment_text']);
                $new_text = $old_text . "\n\n[$timestamp]:\n" . $comment_text;
                
                $stmt = $pdo->prepare("
                    UPDATE svagenda_comments 
                    SET comment_text = ?, updated_at = NOW()
                    WHERE comment_id = ?
                ");
                $stmt->execute([$new_text, $existing['comment_id']]);
            } else {
                // NEU: Erster Kommentar mit Timestamp
                $new_text = "[$timestamp]:\n" . $comment_text;
                
                $stmt = $pdo->prepare("
                    INSERT INTO svagenda_comments (item_id, member_id, comment_text, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$item_id, $current_user['member_id'], $new_text]);
            }
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Speichern des Kommentars: " . $e->getMessage());
            $error = "Fehler beim Speichern des Kommentars";
        }
    }
}

/**
 * Kommentar löschen (nur eigener Kommentar, nur bis Sitzungsende)
 * 
 * POST-Parameter:
 * - delete_comment: 1
 * - comment_id: Int (required)
 * - item_id: Int (required, für Redirect)
 * 
 * Verwendet von: tab_agenda.php - Löschen-Button beim eigenen Kommentar
 * Redirect: ?tab=agenda&meeting_id=X#top-ITEM_ID
 */
if (isset($_POST['delete_comment'])) {
    $comment_id = intval($_POST['comment_id'] ?? 0);
    $item_id = intval($_POST['item_id'] ?? 0);
    
    if ($comment_id && $item_id) {
        try {
            // Meeting-Status prüfen (nur bis 'ended')
            $stmt = $pdo->prepare("SELECT status FROM svmeetings WHERE meeting_id = ?");
            $stmt->execute([$current_meeting_id]);
            $meeting_status = $stmt->fetchColumn();
            
            if (in_array($meeting_status, ['preparation', 'active', 'ended'])) {
                // Prüfen ob User der Kommentar-Autor ist
                $stmt = $pdo->prepare("
                    SELECT member_id 
                    FROM svagenda_comments 
                    WHERE comment_id = ? AND item_id IN (SELECT item_id FROM svagenda_items WHERE meeting_id = ?)
                ");
                $stmt->execute([$comment_id, $current_meeting_id]);
                $comment_owner = $stmt->fetchColumn();
                
                if ($comment_owner == $current_user['member_id']) {
                    // Kommentar löschen
                    $stmt = $pdo->prepare("DELETE FROM svagenda_comments WHERE comment_id = ?");
                    $stmt->execute([$comment_id]);
                    
                    // Durchschnittswerte neu berechnen
                    recalculate_item_metrics($pdo, $item_id);
                }
            }
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Löschen des Kommentars: " . $e->getMessage());
            $error = "Fehler beim Löschen des Kommentars";
        }
    }
}

/**
 * Kommentar hinzufügen (in preparation Phase)
 * 
 * POST-Parameter:
 * - add_comment_preparation: 1
 * - item_id: Int (required)
 * - comment: String (required)
 * 
 * Verwendet von: tab_agenda_display_preparation.php - Kommentar-Formular
 * Redirect: ?tab=agenda&meeting_id=X#top-ITEM_ID
 */
if (isset($_POST['add_comment_preparation'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $comment_text = trim($_POST['comment'] ?? '');
    
    if ($item_id && !empty($comment_text)) {
        try {
            // Meeting-Status prüfen (nur in preparation)
            $stmt = $pdo->prepare("SELECT status FROM svmeetings WHERE meeting_id = ?");
            $stmt->execute([$current_meeting_id]);
            $meeting_status = $stmt->fetchColumn();
            
            if ($meeting_status === 'preparation') {
                // Neuen Kommentar erstellen
                $stmt = $pdo->prepare("
                    INSERT INTO svagenda_comments (item_id, member_id, comment_text, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$item_id, $current_user['member_id'], $comment_text]);
            }
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Hinzufügen des Kommentars: " . $e->getMessage());
            $error = "Fehler beim Hinzufügen des Kommentars";
        }
    }
}

// ============================================
// 4. PROTOKOLL
// ============================================

/**
 * Protokoll speichern
 * 
 * POST-Parameter:
 * - save_protocol: 1
 * - item_id: Int (required)
 * - protocol_text: String
 * - todo_assigned_to[ITEM_ID]: Int (optional, Member-ID für ToDo)
 * - todo_description[ITEM_ID]: String (optional)
 * - todo_due_date[ITEM_ID]: Date (optional)
 * - todo_private[ITEM_ID]: 0|1 (optional)
 * 
 * Verwendet von: tab_agenda.php - Protokoll-Formular (nur Sekretär)
 * Redirect: ?tab=agenda&meeting_id=X#top-ITEM_ID
 */
if (isset($_POST['save_protocol'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $protocol_text = trim($_POST['protocol_text'] ?? '');
    
    // Prüfen ob User Sekretär ist
    if ($is_secretary && $meeting['status'] === 'active') {
        try {
            // 1. Abstimmungsergebnisse speichern (falls vorhanden)
            $vote_yes = isset($_POST['vote_yes']) && isset($_POST['vote_yes'][$item_id]) ? intval($_POST['vote_yes'][$item_id]) : null;
            $vote_no = isset($_POST['vote_no']) && isset($_POST['vote_no'][$item_id]) ? intval($_POST['vote_no'][$item_id]) : null;
            $vote_abstain = isset($_POST['vote_abstain']) && isset($_POST['vote_abstain'][$item_id]) ? intval($_POST['vote_abstain'][$item_id]) : null;
            $vote_result = isset($_POST['vote_result']) && isset($_POST['vote_result'][$item_id]) && !empty($_POST['vote_result'][$item_id]) ? $_POST['vote_result'][$item_id] : null;
            
            if ($vote_result) {
                $stmt = $pdo->prepare("
                    UPDATE svagenda_items 
                    SET vote_yes = ?, vote_no = ?, vote_abstain = ?, vote_result = ?
                    WHERE item_id = ?
                ");
                $stmt->execute([$vote_yes, $vote_no, $vote_abstain, $vote_result, $item_id]);
            }
            
            // 2. Protokollnotizen speichern
            $stmt = $pdo->prepare("
                UPDATE svagenda_items 
                SET protocol_notes = ? 
                WHERE item_id = ?
            ");
            $stmt->execute([$protocol_text, $item_id]);
            
            // 2. ToDo erstellen (falls gewünscht)
            $todo_arrays = [
                'assigned_to' => $_POST['todo_assigned_to'] ?? [],
                'description' => $_POST['todo_description'] ?? [],
                'due_date' => $_POST['todo_due_date'] ?? [],
                'private' => $_POST['todo_private'] ?? []
            ];
            
            if (isset($todo_arrays['assigned_to'][$item_id]) && 
                $todo_arrays['assigned_to'][$item_id] && 
                !empty($todo_arrays['description'][$item_id])) {
                
                $assigned_to = intval($todo_arrays['assigned_to'][$item_id]);
                $todo_desc = trim($todo_arrays['description'][$item_id]);
                $due_date = $todo_arrays['due_date'][$item_id] ?? null;
                $is_private = isset($todo_arrays['private'][$item_id]) && $todo_arrays['private'][$item_id] == 1 ? 1 : 0;
                
                // ToDo in Protokoll-Text einfügen
                // Mitglied über Wrapper-Funktion laden
                $member = get_member_by_id($pdo, $assigned_to);
                
                if ($member) {
                    $member_name = $member['first_name'] . ' ' . $member['last_name'];
                    $due_date_formatted = $due_date ? date('d.m.Y', strtotime($due_date)) : 'offen';
                    
                    $todo_text = "\n\nToDo für {$member_name}: {$todo_desc} bis {$due_date_formatted}";

                    // Protokoll mit ToDo aktualisieren
                    $updated_protocol = $protocol_text . $todo_text;
                    $stmt = $pdo->prepare("
                        UPDATE svagenda_items
                        SET protocol_notes = ?
                        WHERE item_id = ?
                    ");
                    $stmt->execute([$updated_protocol, $item_id]);

                    // ToDo-Beschreibung mit Meeting-Link erweitern
                    $todo_description_with_link = $todo_desc . "\n\nLink zur Sitzung: " . get_full_meeting_link($current_meeting_id);

                    // ToDo in Datenbank speichern
                    $stmt = $pdo->prepare("
                        INSERT INTO svtodos
                        (meeting_id, item_id, assigned_to_member_id, title, description, status, is_private, due_date, entry_date, created_by_member_id)
                        VALUES (?, ?, ?, ?, ?, 'open', ?, ?, CURDATE(), ?)
                    ");
                    $stmt->execute([
                        $current_meeting_id,
                        $item_id,
                        $assigned_to,
                        $todo_desc,
                        $todo_description_with_link,
                        $is_private,
                        $due_date,
                        $current_user['member_id']
                    ]);
                }
            }
            
            // 3. Wiedervorlage erstellen (falls gewünscht)
            $resubmit_arrays = $_POST['resubmit_meeting_id'] ?? [];
            $resubmit_confidential = $_POST['resubmit_confidential'] ?? [];
            
            if (isset($resubmit_arrays[$item_id]) && intval($resubmit_arrays[$item_id]) > 0) {
                $target_meeting_id = intval($resubmit_arrays[$item_id]);
                
                // Aktuellen TOP laden
                $stmt = $pdo->prepare("
                    SELECT ai.*, m.meeting_date, m.meeting_name
                    FROM svagenda_items ai
                    JOIN svmeetings m ON ai.meeting_id = m.meeting_id
                    WHERE ai.item_id = ?
                ");
                $stmt->execute([$item_id]);
                $current_item = $stmt->fetch();
                
                if ($current_item) {
                    // Prüfen ob Ziel-Meeting in Vorbereitung ist
                    $stmt = $pdo->prepare("SELECT status FROM svmeetings WHERE meeting_id = ?");
                    $stmt->execute([$target_meeting_id]);
                    $target_meeting = $stmt->fetch();
                    
                    if ($target_meeting && $target_meeting['status'] === 'preparation') {
                        // Vertraulichkeits-Status: Checkbox hat Vorrang, sonst Original übernehmen
                        $is_confidential = isset($resubmit_confidential[$item_id]) ? 1 : 0;
                        
                        // Neuen TOP-Nummer für Ziel-Meeting ermitteln
                        $new_top_number = get_next_top_number($pdo, $target_meeting_id, $is_confidential);
                        
                        // Neuen TOP mit "Wiedervorlage:" Präfix erstellen
                        $new_title = "Wiedervorlage: " . $current_item['title'];
                        $meeting_date_formatted = date('d.m.Y', strtotime($current_item['meeting_date']));
                        $resubmit_note = "Wiedervorlage aus Sitzung vom {$meeting_date_formatted}, TOP {$current_item['top_number']}";
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO svagenda_items 
                            (meeting_id, top_number, title, description, priority, estimated_duration, is_confidential, created_by_member_id, protocol_notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $target_meeting_id,
                            $new_top_number,
                            $new_title,
                            $current_item['description'],
                            $current_item['priority'],
                            $current_item['estimated_duration'],
                            $is_confidential,
                            $current_user['member_id'],
                            $resubmit_note
                        ]);
                        
                        // Erfolgs-Feedback mit Info über Vertraulichkeit
                        $confidential_text = $is_confidential ? ' als vertraulicher TOP' : '';
                        $_SESSION['resubmit_success'] = "TOP erfolgreich als Wiedervorlage{$confidential_text} angelegt!";
                    }
                }
            }
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
            
        } catch (PDOException $e) {
            error_log("Fehler beim Speichern des Protokolls: " . $e->getMessage());
            $error = "Fehler beim Speichern des Protokolls";
        }
    }
}

/**
 * Wiedervorlage einzeln speichern
 */
if (isset($_POST['save_resubmit']) && $is_secretary && $meeting['status'] === 'active') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $target_meeting_id = intval($_POST['resubmit_meeting_id'] ?? 0);
    
    if ($target_meeting_id) {
        try {
            // TOP-Daten laden
            $stmt = $pdo->prepare("
                SELECT ai.*, m.meeting_date 
                FROM svagenda_items ai
                JOIN svmeetings m ON ai.meeting_id = m.meeting_id
                WHERE ai.item_id = ?
            ");
            $stmt->execute([$item_id]);
            $current_item = $stmt->fetch();
            
            if ($current_item) {
                // Ziel-Meeting Status prüfen
                $stmt = $pdo->prepare("SELECT status FROM svmeetings WHERE meeting_id = ?");
                $stmt->execute([$target_meeting_id]);
                $target_meeting = $stmt->fetch();
                
                if ($target_meeting && $target_meeting['status'] === 'preparation') {
                    $is_confidential_resubmit = isset($_POST['resubmit_confidential']) ? 1 : 0;
                    $new_top_number = get_next_top_number($pdo, $target_meeting_id, $is_confidential_resubmit);
                    
                    $new_title = "Wiedervorlage: " . $current_item['title'];
                    $meeting_date_formatted = date('d.m.Y', strtotime($current_item['meeting_date']));
                    $resubmit_note = "Wiedervorlage aus Sitzung vom {$meeting_date_formatted}, TOP {$current_item['top_number']}";
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO svagenda_items 
                        (meeting_id, top_number, title, description, category, proposal_text, priority, estimated_duration, is_confidential, created_by_member_id, protocol_notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $target_meeting_id,
                        $new_top_number,
                        $new_title,
                        $current_item['description'],
                        $current_item['category'],
                        $current_item['proposal_text'],
                        $current_item['priority'],
                        $current_item['estimated_duration'],
                        $is_confidential_resubmit,
                        $current_user['member_id'],
                        $resubmit_note
                    ]);
                    
                    $_SESSION['resubmit_success'] = "Wiedervorlage erfolgreich angelegt!";
                }
            }
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
        } catch (PDOException $e) {
            error_log("Fehler bei Wiedervorlage: " . $e->getMessage());
        }
    } else {
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
        exit;
    }
}

// ============================================
// NEUE FEATURES - Status "active"
// ============================================

/**
 * Teilnehmerstatus aktualisieren (nur Sekretär während aktiver Sitzung)
 */
if (isset($_POST['update_attendance']) && $is_secretary && in_array($meeting['status'], ['active', 'ended', 'protocol_ready'])) {
    $attendance_data = $_POST['attendance'] ?? [];
    
    try {
        foreach ($attendance_data as $member_id => $status) {
            $stmt = $pdo->prepare("
                UPDATE svmeeting_participants 
                SET attendance_status = ? 
                WHERE meeting_id = ? AND member_id = ?
            ");
            $stmt->execute([$status, $current_meeting_id, intval($member_id)]);
        }
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Update der Teilnehmerliste: " . $e->getMessage());
    }
}

/**
 * Nicht eingeladene Teilnehmer hinzufügen
 * - Im active Status: Nur Secretary
 * - Im preparation Status: Nur Admins
 */
if (isset($_POST['add_uninvited_participant'])) {
    $new_participant_id = intval($_POST['new_participant_id'] ?? 0);

    // Berechtigung prüfen
    $is_allowed = false;
    if ($meeting['status'] === 'active' && $is_secretary) {
        $is_allowed = true;
    } elseif ($meeting['status'] === 'preparation' && $current_user['is_admin'] == 1) {
        $is_allowed = true;
    }

    if ($is_allowed && $new_participant_id > 0) {
        try {
            // Prüfen ob Teilnehmer bereits eingeladen ist
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM svmeeting_participants WHERE meeting_id = ? AND member_id = ?");
            $stmt->execute([$current_meeting_id, $new_participant_id]);
            $already_invited = $stmt->fetchColumn() > 0;

            if (!$already_invited) {
                // Teilnehmer hinzufügen mit Status invited und present
                $stmt = $pdo->prepare("
                    INSERT INTO svmeeting_participants (meeting_id, member_id, attendance_status)
                    VALUES (?, ?, 'present')
                ");
                $stmt->execute([$current_meeting_id, $new_participant_id]);
            }

            header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
            exit;
        } catch (PDOException $e) {
            error_log("Fehler beim Hinzufügen des Teilnehmers: " . $e->getMessage());
        }
    }
}

/**
 * TOP auf aktiv setzen
 */
if (isset($_POST['set_active_top']) && $is_secretary && $meeting['status'] === 'active') {
    $item_id = intval($_POST['item_id']);
    
    try {
        $stmt = $pdo->prepare("UPDATE svmeetings SET active_item_id = ? WHERE meeting_id = ?");
        $stmt->execute([$item_id, $current_meeting_id]);
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Aktivieren des TOP: " . $e->getMessage());
    }
}

/**
 * Aktiven TOP deaktivieren
 */
if (isset($_POST['unset_active_top']) && $is_secretary && $meeting['status'] === 'active') {
    try {
        $stmt = $pdo->prepare("UPDATE svmeetings SET active_item_id = NULL WHERE meeting_id = ?");
        $stmt->execute([$current_meeting_id]);
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Deaktivieren des TOP: " . $e->getMessage());
    }
}

/**
 * TOP zwischen öffentlich und vertraulich verschieben
 */
if (isset($_POST['toggle_confidential']) && $is_secretary && $meeting['status'] === 'active') {
    $item_id = intval($_POST['item_id']);
    
    try {
        // Aktuellen Status ermitteln
        $stmt = $pdo->prepare("SELECT is_confidential FROM svagenda_items WHERE item_id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if ($item) {
            $new_confidential = $item['is_confidential'] ? 0 : 1;
            
            // Neue TOP-Nummer vergeben
            $new_top_number = get_next_top_number($pdo, $current_meeting_id, $new_confidential);
            
            // TOP aktualisieren
            $stmt = $pdo->prepare("
                UPDATE svagenda_items 
                SET is_confidential = ?, top_number = ? 
                WHERE item_id = ?
            ");
            $stmt->execute([$new_confidential, $new_top_number, $item_id]);
        }
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Verschieben des TOP: " . $e->getMessage());
    }
}

/**
 * Priorität des aktiven TOP aktualisieren (nur Sekretär)
 */
if (isset($_POST['update_active_priority']) && $is_secretary && $meeting['status'] === 'active') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $priority = floatval($_POST['priority'] ?? 5.0);

    if ($item_id && $priority >= 1 && $priority <= 10) {
        try {
            $stmt = $pdo->prepare("
                UPDATE svagenda_items
                SET priority = ?
                WHERE item_id = ? AND meeting_id = ?
            ");
            $stmt->execute([$priority, $item_id, $current_meeting_id]);

            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
        } catch (PDOException $e) {
            error_log("Fehler beim Aktualisieren der Priorität: " . $e->getMessage());
        }
    }
}

/**
 * Live-Kommentar während aktiver Sitzung hinzufügen
 */
if (isset($_POST['add_live_comment']) && $meeting['status'] === 'active') {
    $item_id = intval($_POST['item_id']);
    $comment_text = trim($_POST['comment_text'] ?? '');
    
    if ($comment_text) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO svagenda_live_comments (item_id, member_id, comment_text, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$item_id, $current_user['member_id'], $comment_text]);
            
            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
        } catch (PDOException $e) {
            error_log("Fehler beim Speichern des Live-Kommentars: " . $e->getMessage());
        }
    }
}

/**
 * Neuen TOP während aktiver Sitzung hinzufügen (nur Sekretär)
 */
if (isset($_POST['add_agenda_item_active']) && $is_secretary && $meeting['status'] === 'active') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'information';
    $proposal_text = ($category === 'antrag_beschluss') ? trim($_POST['proposal_text'] ?? '') : '';
    $is_confidential = isset($_POST['is_confidential']) ? 1 : 0;

    if ($title) {
        try {
            // Transaktion starten für atomare Operation
            $pdo->beginTransaction();

            error_log("Adding TOP (active meeting): meeting_id=$current_meeting_id, confidential=$is_confidential, title=$title");

            $top_number = get_next_top_number($pdo, $current_meeting_id, $is_confidential);
            error_log("Assigned TOP number: $top_number");

            $stmt = $pdo->prepare("
                INSERT INTO svagenda_items
                (meeting_id, top_number, title, description, category, proposal_text, priority, estimated_duration, is_confidential, created_by_member_id)
                VALUES (?, ?, ?, ?, ?, ?, 5, 10, ?, ?)
            ");
            $stmt->execute([
                $current_meeting_id,
                $top_number,
                $title,
                $description,
                $category,
                $proposal_text,
                $is_confidential,
                $current_user['member_id']
            ]);

            $new_item_id = $pdo->lastInsertId();
            error_log("Inserted TOP with ID: $new_item_id");

            // Transaktion abschließen
            $pdo->commit();
            error_log("TOP successfully added and committed");

            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$new_item_id");
            exit;
        } catch (PDOException $e) {
            // Rollback bei Fehler
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("FEHLER beim Hinzufügen des TOP (active): " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
        }
    }
}

/**
 * Alle Protokolle während aktiver Sitzung speichern
 */
if (isset($_POST['save_all_protocols']) && $is_secretary && $meeting['status'] === 'active') {
    $protocol_texts = $_POST['protocol_text'] ?? [];
    $vote_yes = $_POST['vote_yes'] ?? [];
    $vote_no = $_POST['vote_no'] ?? [];
    $vote_abstain = $_POST['vote_abstain'] ?? [];
    $vote_result = $_POST['vote_result'] ?? [];
    $resubmit_meeting_ids = $_POST['resubmit_meeting_id'] ?? [];
    $resubmit_confidential = $_POST['resubmit_confidential'] ?? [];
    
    try {
        // Protokolle speichern
        foreach ($protocol_texts as $item_id => $text) {
            $item_id = intval($item_id);
            $text = trim($text);
            
            $stmt = $pdo->prepare("UPDATE svagenda_items SET protocol_notes = ? WHERE item_id = ?");
            $stmt->execute([$text, $item_id]);
            
            // Abstimmungsdaten speichern (falls vorhanden)
            if (isset($vote_result[$item_id]) && !empty($vote_result[$item_id])) {
                $stmt = $pdo->prepare("
                    UPDATE svagenda_items 
                    SET vote_yes = ?, vote_no = ?, vote_abstain = ?, vote_result = ?
                    WHERE item_id = ?
                ");
                $stmt->execute([
                    intval($vote_yes[$item_id] ?? 0),
                    intval($vote_no[$item_id] ?? 0),
                    intval($vote_abstain[$item_id] ?? 0),
                    $vote_result[$item_id],
                    $item_id
                ]);
            }
        }
        
        // Wiedervorlagen verarbeiten
        foreach ($resubmit_meeting_ids as $item_id => $target_meeting_id) {
            if (empty($target_meeting_id)) continue;
            
            $item_id = intval($item_id);
            $target_meeting_id = intval($target_meeting_id);
            
            // TOP-Daten laden
            $stmt = $pdo->prepare("
                SELECT ai.*, m.meeting_date 
                FROM svagenda_items ai
                JOIN svmeetings m ON ai.meeting_id = m.meeting_id
                WHERE ai.item_id = ?
            ");
            $stmt->execute([$item_id]);
            $current_item = $stmt->fetch();
            
            if ($current_item) {
                // Ziel-Meeting Status prüfen
                $stmt = $pdo->prepare("SELECT status FROM svmeetings WHERE meeting_id = ?");
                $stmt->execute([$target_meeting_id]);
                $target_meeting = $stmt->fetch();
                
                if ($target_meeting && $target_meeting['status'] === 'preparation') {
                    $is_confidential_resubmit = isset($resubmit_confidential[$item_id]) ? 1 : 0;
                    $new_top_number = get_next_top_number($pdo, $target_meeting_id, $is_confidential_resubmit);
                    
                    $new_title = "Wiedervorlage: " . $current_item['title'];
                    $meeting_date_formatted = date('d.m.Y', strtotime($current_item['meeting_date']));
                    $resubmit_note = "Wiedervorlage aus Sitzung vom {$meeting_date_formatted}, TOP {$current_item['top_number']}";
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO svagenda_items 
                        (meeting_id, top_number, title, description, category, proposal_text, priority, estimated_duration, is_confidential, created_by_member_id, protocol_notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $target_meeting_id,
                        $new_top_number,
                        $new_title,
                        $current_item['description'],
                        $current_item['category'],
                        $current_item['proposal_text'],
                        $current_item['priority'],
                        $current_item['estimated_duration'],
                        $is_confidential_resubmit,
                        $current_user['member_id'],
                        $resubmit_note
                    ]);
                    
                    $_SESSION['resubmit_success'] = "Wiedervorlage erfolgreich angelegt!";
                }
            }
        }
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Speichern der Protokolle: " . $e->getMessage());
    }
}

// ============================================
// STATUS-ÜBERGÄNGE
// ============================================

/**
 * Sitzung starten (preparation -> active)
 */
if (isset($_POST['start_meeting']) && ($is_secretary || $is_chairman) && $meeting['status'] === 'preparation') {
    try {
        // TOP 0 mit Voreinstellung befüllen
        $chairman_name = get_member_name($pdo, $meeting['chairman_member_id']);
        $secretary_name = get_member_name($pdo, $meeting['secretary_member_id']);
        
        $top0_protocol = "Sitzungsleitung: " . $chairman_name . "\n";
        $top0_protocol .= "Protokollführung: " . $secretary_name . "\n";
        $top0_protocol .= "Gäste: ";
        
        $stmt = $pdo->prepare("
            UPDATE svagenda_items 
            SET protocol_notes = ?
            WHERE meeting_id = ? AND top_number = 0
        ");
        $stmt->execute([$top0_protocol, $current_meeting_id]);
        
        // TOP 999 erstellen für Sitzungsende
        $stmt = $pdo->prepare("
            INSERT INTO svagenda_items 
            (meeting_id, top_number, title, description, priority, estimated_duration, is_confidential, created_by_member_id) 
            VALUES (?, 999, 'Sitzungsende', 'Automatisch erfasst', 1.00, 1, 0, ?)
        ");
        $stmt->execute([$current_meeting_id, $current_user['member_id']]);
        
        // Meeting-Status auf active setzen
        $stmt = $pdo->prepare("UPDATE svmeetings SET status = 'active' WHERE meeting_id = ?");
        $stmt->execute([$current_meeting_id]);
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Starten der Sitzung: " . $e->getMessage());
    }
}

/**
 * Sitzung beenden (active -> ended)
 */
if (isset($_POST['end_meeting']) && ($is_secretary || $is_chairman) && $meeting['status'] === 'active') {
    try {
        // TOP 999 updated_at setzen (= Endzeitpunkt)
        $stmt = $pdo->prepare("
            UPDATE svagenda_items 
            SET updated_at = NOW() 
            WHERE meeting_id = ? AND top_number = 999
        ");
        $stmt->execute([$current_meeting_id]);
        
        // Meeting Status und ended_at setzen
        $stmt = $pdo->prepare("
            UPDATE svmeetings 
            SET status = 'ended', ended_at = NOW(), active_item_id = NULL
            WHERE meeting_id = ?
        ");
        $stmt->execute([$current_meeting_id]);
        
        // TODO für Sekretär erstellen mit vollständigen Sitzungsdaten
        $due_date = date('Y-m-d H:i:s', strtotime('+72 hours'));
        
        // Start- und Endzeitpunkt
        $start_time = date('H:i', strtotime($meeting['meeting_date']));
        $end_time_query = $pdo->prepare("SELECT updated_at FROM svagenda_items WHERE meeting_id = ? AND top_number = 999");
        $end_time_query->execute([$current_meeting_id]);
        $end_timestamp = $end_time_query->fetchColumn();
        $end_time = $end_timestamp ? date('H:i', strtotime($end_timestamp)) : '?';
        
        $todo_title = "Protokoll fertigstellen: " . $meeting['meeting_name'] . " vom " . date('d.m.Y', strtotime($meeting['meeting_date']));
        $todo_description = "Sitzung vom " . date('d.m.Y', strtotime($meeting['meeting_date'])) .
                           " (" . $start_time . "-" . $end_time . " Uhr)\n" .
                           "Link: " . get_full_meeting_link($current_meeting_id);
        
        $stmt = $pdo->prepare("
            INSERT INTO svtodos (meeting_id, assigned_to_member_id, title, description, due_date, status, created_by_member_id)
            VALUES (?, ?, ?, ?, ?, 'open', ?)
        ");
        $stmt->execute([
            $current_meeting_id,
            $meeting['secretary_member_id'],
            $todo_title,
            $todo_description,
            $due_date,
            $current_user['member_id']
        ]);
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Beenden der Sitzung: " . $e->getMessage());
    }
}

/**
 * Änderungen im Status "ended" speichern
 */
if (isset($_POST['save_ended_changes']) && $meeting['status'] === 'ended') {
    try {
        // Protokoll speichern (nur Sekretär)
        if ($is_secretary) {
            $protocol_texts = $_POST['protocol_text'] ?? [];
            $vote_yes = $_POST['vote_yes'] ?? [];
            $vote_no = $_POST['vote_no'] ?? [];
            $vote_abstain = $_POST['vote_abstain'] ?? [];
            $vote_result = $_POST['vote_result'] ?? [];
            
            foreach ($protocol_texts as $item_id => $text) {
                $item_id = intval($item_id);
                $text = trim($text);
                
                $stmt = $pdo->prepare("UPDATE svagenda_items SET protocol_notes = ? WHERE item_id = ?");
                $stmt->execute([$text, $item_id]);
                
                if (isset($vote_result[$item_id]) && !empty($vote_result[$item_id])) {
                    $stmt = $pdo->prepare("
                        UPDATE svagenda_items 
                        SET vote_yes = ?, vote_no = ?, vote_abstain = ?, vote_result = ?
                        WHERE item_id = ?
                    ");
                    $stmt->execute([
                        intval($vote_yes[$item_id] ?? 0),
                        intval($vote_no[$item_id] ?? 0),
                        intval($vote_abstain[$item_id] ?? 0),
                        $vote_result[$item_id],
                        $item_id
                    ]);
                }
            }
        }
        
        // Nachträgliche Kommentare speichern (alle Teilnehmer)
        $post_comments = $_POST['post_comment'] ?? [];
        foreach ($post_comments as $item_id => $comment_text) {
            $item_id = intval($item_id);
            $comment_text = trim($comment_text);
            
            if (!empty($comment_text)) {
                // Prüfen ob schon Kommentar existiert
                $stmt = $pdo->prepare("
                    SELECT comment_id FROM svagenda_post_comments 
                    WHERE item_id = ? AND member_id = ?
                ");
                $stmt->execute([$item_id, $current_user['member_id']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE svagenda_post_comments 
                        SET comment_text = ?, updated_at = NOW()
                        WHERE comment_id = ?
                    ");
                    $stmt->execute([$comment_text, $existing['comment_id']]);
                } else {
                    // Insert
                    $stmt = $pdo->prepare("
                        INSERT INTO svagenda_post_comments (item_id, member_id, comment_text, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$item_id, $current_user['member_id'], $comment_text]);
                }
            } else {
                // Leerer Text = Kommentar löschen
                $stmt = $pdo->prepare("
                    DELETE FROM svagenda_post_comments 
                    WHERE item_id = ? AND member_id = ?
                ");
                $stmt->execute([$item_id, $current_user['member_id']]);
            }
        }
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Speichern: " . $e->getMessage());
    }
}

/**
 * Protokoll zur Genehmigung freigeben (ended -> protocol_ready)
 */
if (isset($_POST['release_protocol']) && $is_secretary && $meeting['status'] === 'ended') {
    try {
        // Protokoll generieren und speichern
        require_once 'module_protocol.php';
        require_once 'module_helpers.php';
        
        // Alle Daten laden (ohne JOINs!)
        $stmt = $pdo->prepare("
            SELECT ai.*
            FROM svagenda_items ai
            WHERE ai.meeting_id = ?
            ORDER BY ai.top_number
        ");
        $stmt->execute([$current_meeting_id]);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Creator-Namen über Adapter holen
        foreach ($all_items as &$item) {
            if ($item['created_by_member_id']) {
                $creator = get_member_by_id($pdo, $item['created_by_member_id']);
                $item['creator_first'] = $creator['first_name'] ?? null;
                $item['creator_last'] = $creator['last_name'] ?? null;
            }
        }
        unset($item);

        // Teilnehmer über Adapter laden
        $stmt = $pdo->prepare("
            SELECT member_id FROM svmeeting_participants WHERE meeting_id = ?
        ");
        $stmt->execute([$current_meeting_id]);
        $participant_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $all_participants = [];
        foreach ($participant_ids as $member_id) {
            $member = get_member_by_id($pdo, $member_id);
            if ($member) {
                $all_participants[] = $member;
            }
        }
        
        $protocols = generate_protocol($pdo, $meeting, $all_items, $all_participants);
        
        // Protokolle in DB speichern
        $stmt = $pdo->prepare("
            UPDATE svmeetings 
            SET protokoll = ?, prot_intern = ?, status = 'protocol_ready'
            WHERE meeting_id = ?
        ");
        $stmt->execute([
            $protocols['public'],
            $protocols['confidential'],
            $current_meeting_id
        ]);
        
        // TODO für Sekretär erledigen
        $stmt = $pdo->prepare("
            UPDATE svtodos 
            SET status = 'done', completed_at = NOW()
            WHERE meeting_id = ? AND assigned_to_member_id = ? AND title LIKE '%fertigstellen%'
        ");
        $stmt->execute([$current_meeting_id, $meeting['secretary_member_id']]);
        
        // TODO für Sitzungsleiter erstellen mit vollständigen Sitzungsdaten
        $start_time = date('H:i', strtotime($meeting['meeting_date']));
        $end_time_query = $pdo->prepare("SELECT updated_at FROM svagenda_items WHERE meeting_id = ? AND top_number = 999");
        $end_time_query->execute([$current_meeting_id]);
        $end_timestamp = $end_time_query->fetchColumn();
        $end_time = $end_timestamp ? date('H:i', strtotime($end_timestamp)) : '?';
        
        $todo_title = "Protokoll genehmigen: " . $meeting['meeting_name'] . " vom " . date('d.m.Y', strtotime($meeting['meeting_date']));
        $todo_description = "Sitzung vom " . date('d.m.Y', strtotime($meeting['meeting_date'])) .
                           " (" . $start_time . "-" . $end_time . " Uhr)\n" .
                           "Link: " . get_full_meeting_link($current_meeting_id);
        
        $stmt = $pdo->prepare("
            INSERT INTO svtodos (meeting_id, assigned_to_member_id, title, description, due_date, status, created_by_member_id)
            VALUES (?, ?, ?, ?, NOW(), 'open', ?)
        ");
        $stmt->execute([
            $current_meeting_id,
            $meeting['chairman_member_id'],
            $todo_title,
            $todo_description,
            $current_user['member_id']
        ]);
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Freigeben des Protokolls: " . $e->getMessage());
    }
}

/**
 * Änderungen im Status "protocol_ready" speichern (nur Sekretär)
 */
if (isset($_POST['save_protocol_ready_changes']) && $is_secretary && $meeting['status'] === 'protocol_ready') {
    try {
        $protocol_texts = $_POST['protocol_text'] ?? [];
        $vote_yes = $_POST['vote_yes'] ?? [];
        $vote_no = $_POST['vote_no'] ?? [];
        $vote_abstain = $_POST['vote_abstain'] ?? [];
        $vote_result = $_POST['vote_result'] ?? [];
        
        foreach ($protocol_texts as $item_id => $text) {
            $item_id = intval($item_id);
            $text = trim($text);
            
            $stmt = $pdo->prepare("UPDATE svagenda_items SET protocol_notes = ? WHERE item_id = ?");
            $stmt->execute([$text, $item_id]);
            
            if (isset($vote_result[$item_id]) && !empty($vote_result[$item_id])) {
                $stmt = $pdo->prepare("
                    UPDATE svagenda_items 
                    SET vote_yes = ?, vote_no = ?, vote_abstain = ?, vote_result = ?
                    WHERE item_id = ?
                ");
                $stmt->execute([
                    intval($vote_yes[$item_id] ?? 0),
                    intval($vote_no[$item_id] ?? 0),
                    intval($vote_abstain[$item_id] ?? 0),
                    $vote_result[$item_id],
                    $item_id
                ]);
            }
        }
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Speichern: " . $e->getMessage());
    }
}

/**
 * Protokoll genehmigen (protocol_ready -> archived)
 */
if (isset($_POST['approve_protocol']) && $is_chairman && $meeting['status'] === 'protocol_ready') {
    try {
        // Finale Protokolle nochmal generieren und speichern
        require_once 'module_protocol.php';
        require_once 'module_helpers.php';
        
        // Alle Daten laden (ohne JOINs!)
        $stmt = $pdo->prepare("
            SELECT ai.*
            FROM svagenda_items ai
            WHERE ai.meeting_id = ?
            ORDER BY ai.top_number
        ");
        $stmt->execute([$current_meeting_id]);
        $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Creator-Namen über Adapter holen
        foreach ($all_items as &$item) {
            if ($item['created_by_member_id']) {
                $creator = get_member_by_id($pdo, $item['created_by_member_id']);
                $item['creator_first'] = $creator['first_name'] ?? null;
                $item['creator_last'] = $creator['last_name'] ?? null;
            }
        }
        unset($item);

        // Teilnehmer über Adapter laden
        $stmt = $pdo->prepare("
            SELECT member_id FROM svmeeting_participants WHERE meeting_id = ?
        ");
        $stmt->execute([$current_meeting_id]);
        $participant_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $all_participants = [];
        foreach ($participant_ids as $member_id) {
            $member = get_member_by_id($pdo, $member_id);
            if ($member) {
                $all_participants[] = $member;
            }
        }

        $protocols = generate_protocol($pdo, $meeting, $all_items, $all_participants);
        
        // Meeting archivieren
        $stmt = $pdo->prepare("
            UPDATE svmeetings 
            SET protokoll = ?, prot_intern = ?, status = 'archived'
            WHERE meeting_id = ?
        ");
        $stmt->execute([
            $protocols['public'],
            $protocols['confidential'],
            $current_meeting_id
        ]);
        
        // TODO für Sitzungsleiter erledigen
        $stmt = $pdo->prepare("
            UPDATE svtodos 
            SET status = 'done', completed_at = NOW()
            WHERE meeting_id = ? AND assigned_to_member_id = ? AND title LIKE '%genehmigen%'
        ");
        $stmt->execute([$current_meeting_id, $meeting['chairman_member_id']]);
        
        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Genehmigen des Protokolls: " . $e->getMessage());
    }
}

/**
 * Protokolländerung anfordern (Sitzungsleiter)
 */
if (isset($_POST['request_protocol_revision']) && $is_chairman && $meeting['status'] === 'protocol_ready') {
    try {
        $todo_title = "Protokoll überarbeiten: " . $meeting['meeting_name'] . " vom " . date('d.m.Y', strtotime($meeting['meeting_date']));
        $todo_description = "Der Sitzungsleiter hat Änderungen am Protokoll angefordert. Bitte prüfen Sie Ihre Anmerkungen und überarbeiten Sie das Protokoll entsprechend.\n\nLink: " . get_full_meeting_link($current_meeting_id);

        $stmt = $pdo->prepare("
            INSERT INTO svtodos (meeting_id, assigned_to_member_id, title, description, due_date, status, created_by_member_id, entry_date)
            VALUES (?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'open', ?, CURDATE())
        ");
        $stmt->execute([
            $current_meeting_id,
            $meeting['secretary_member_id'],
            $todo_title,
            $todo_description,
            $current_user['member_id']
        ]);

        header("Location: ?tab=agenda&meeting_id=$current_meeting_id");
        exit;
    } catch (PDOException $e) {
        error_log("Fehler beim Anfordern der Protokolländerung: " . $e->getMessage());
    }
}

/**
 * Sitzungsleiter-Kommentar speichern (protocol_ready)
 */
if (isset($_POST['save_chairman_comment']) && $is_chairman && $meeting['status'] === 'protocol_ready') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $comment_text = trim($_POST['comment_text'] ?? '');

    if ($item_id) {
        try {
            // Prüfen ob bereits ein Kommentar existiert
            $stmt = $pdo->prepare("
                SELECT comment_id
                FROM svagenda_post_comments
                WHERE item_id = ? AND member_id = ?
            ");
            $stmt->execute([$item_id, $current_user['member_id']]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update
                if (!empty($comment_text)) {
                    $stmt = $pdo->prepare("
                        UPDATE svagenda_post_comments
                        SET comment_text = ?, created_at = NOW()
                        WHERE comment_id = ?
                    ");
                    $stmt->execute([$comment_text, $existing['comment_id']]);
                } else {
                    // Löschen wenn leer
                    $stmt = $pdo->prepare("DELETE FROM svagenda_post_comments WHERE comment_id = ?");
                    $stmt->execute([$existing['comment_id']]);
                }
            } elseif (!empty($comment_text)) {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO svagenda_post_comments (item_id, member_id, comment_text, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$item_id, $current_user['member_id'], $comment_text]);
            }

            header("Location: ?tab=agenda&meeting_id=$current_meeting_id#top-$item_id");
            exit;
        } catch (PDOException $e) {
            error_log("Fehler beim Speichern des Sitzungsleiter-Kommentars: " . $e->getMessage());
        }
    }
}

?>