<?php
/**
 * functions.php - Gemeinsame Hilfsfunktionen
 */

// Datenbankverbindung erstellen
require_once("config.php");
require_once("config_adapter.php");   // Konfiguration für Mitgliederquelle
require_once("member_functions.php"); // Prozedurale Wrapper-Funktionen
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
    } else {
        die("Datenbankverbindung fehlgeschlagen. Bitte kontaktieren Sie den Administrator.");
    }
}

/**
 * Prüft ob User eingeloggt ist, sonst Redirect zu login.php
 */
function require_login() {
    if (!isset($_SESSION['member_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Gibt aktuellen eingeloggten User zurück
 * Nutzt Wrapper-Funktion (funktioniert mit members ODER berechtigte)
 */
function get_current_member() {
    global $pdo;
    if (!isset($_SESSION['member_id'])) {
        return null;
    }
    return get_member_by_id($pdo, $_SESSION['member_id']);
}

/**
 * Lädt alle Meetings
 * Kompatibel mit members UND berechtigte Tabelle
 */
function get_all_meetings($pdo) {
    try {
        // Nur Meeting-Daten holen, OHNE JOIN auf members
        $stmt = $pdo->query("SELECT m.*
                FROM meetings m
                ORDER BY FIELD(status, 'active', 'preparation', 'ended', 'protocol_ready', 'archived'), meeting_date ASC");
        $meetings = $stmt->fetchAll();

        // Mitglieder-Namen über Adapter ergänzen (funktioniert mit members ODER berechtigte)
        foreach ($meetings as &$meeting) {
            if ($meeting['invited_by_member_id']) {
                $inviter = get_member_by_id($pdo, $meeting['invited_by_member_id']);
                if ($inviter) {
                    $meeting['first_name'] = $inviter['first_name'];
                    $meeting['last_name'] = $inviter['last_name'];
                } else {
                    $meeting['first_name'] = 'Unbekannt';
                    $meeting['last_name'] = '';
                }
            } else {
                $meeting['first_name'] = 'Unbekannt';
                $meeting['last_name'] = '';
            }
        }

        return $meetings;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Fehler in get_all_meetings(): " . $e->getMessage());
        }
        return [];
    }
}

/**
 * Lädt alle Mitglieder
 * HINWEIS: Diese Funktion ist jetzt in member_functions.php definiert
 * und wird automatisch von dort geladen. Sie funktioniert mit members
 * ODER berechtigte Tabelle (siehe config_adapter.php).
 */
// function get_all_members() wurde nach member_functions.php verschoben

/**
 * Lädt Meeting-Details
 * Kompatibel mit members UND berechtigte Tabelle
 */
function get_meeting_details($pdo, $meeting_id) {
    try {
        // Nur Meeting-Daten holen, OHNE JOIN auf members
        $stmt = $pdo->prepare("SELECT m.* FROM meetings m WHERE m.meeting_id = ?");
        $stmt->execute([$meeting_id]);
        $meeting = $stmt->fetch();

        if (!$meeting) {
            return null;
        }

        // Mitglieder-Namen über Adapter ergänzen
        if ($meeting['invited_by_member_id']) {
            $inviter = get_member_by_id($pdo, $meeting['invited_by_member_id']);
            if ($inviter) {
                $meeting['inviter_first_name'] = $inviter['first_name'];
                $meeting['inviter_last_name'] = $inviter['last_name'];
            }
        }

        if ($meeting['chairman_member_id']) {
            $chairman = get_member_by_id($pdo, $meeting['chairman_member_id']);
            if ($chairman) {
                $meeting['chairman_first_name'] = $chairman['first_name'];
                $meeting['chairman_last_name'] = $chairman['last_name'];
            }
        }

        if ($meeting['secretary_member_id']) {
            $secretary = get_member_by_id($pdo, $meeting['secretary_member_id']);
            if ($secretary) {
                $meeting['secretary_first_name'] = $secretary['first_name'];
                $meeting['secretary_last_name'] = $secretary['last_name'];
            }
        }

        return $meeting;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Fehler in get_meeting_details(): " . $e->getMessage());
        }
        return null;
    }
}

/**
 * Lädt Tagesordnung mit Kommentaren
 */
function get_agenda_with_comments($pdo, $meeting_id, $user_role) {
    try {
        $confidential_filter = "";
        if (!in_array($user_role, ['board', 'gf', 'assistant'])) {
            $confidential_filter = "AND ai.is_confidential = 0";
        }
        
        $stmt = $pdo->prepare("SELECT ai.*, 
                mem.first_name as creator_first_name, 
                mem.last_name as creator_last_name,
                mem.member_id as creator_member_id
                FROM agenda_items ai
                LEFT JOIN members mem ON ai.created_by_member_id = mem.member_id
                WHERE ai.meeting_id = ? $confidential_filter
                ORDER BY 
                    CASE 
                        WHEN ai.top_number = 0 THEN 0
                        WHEN ai.top_number = 99 THEN 999
                        WHEN ai.is_confidential = 1 THEN ai.top_number + 1000
                        ELSE ai.priority * -100 + ai.top_number
                    END");
        $stmt->execute([$meeting_id]);
        $agenda = $stmt->fetchAll();
        
        // Kommentare für jeden TOP laden
        foreach ($agenda as &$item) {
            $stmt = $pdo->prepare("SELECT ac.*, m.first_name, m.last_name
                    FROM agenda_comments ac
                    JOIN members m ON ac.member_id = m.member_id
                    WHERE ac.item_id = ?
                    ORDER BY ac.created_at ASC");
            $stmt->execute([$item['item_id']]);
            $item['comments'] = $stmt->fetchAll();
        }
        
        return $agenda;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Fehler in get_agenda_with_comments(): " . $e->getMessage());
        }
        return [];
    }
}

/**
 * Berechnet nächste TOP-Nummer
 */
function get_next_top_number($pdo, $meeting_id, $is_confidential) {
    try {
        if ($is_confidential) {
            // Vertrauliche TOPs beginnen bei 101
            $stmt = $pdo->prepare("SELECT MAX(top_number) as max_top FROM agenda_items 
                    WHERE meeting_id = ? AND is_confidential = 1");
            $stmt->execute([$meeting_id]);
            $result = $stmt->fetch();
            // Wenn noch keine vertraulichen TOPs existieren, starte bei 101
            // Sonst nimm die höchste Nummer + 1
            return ($result['max_top'] ?? 100) + 1;
        } else {
            // Öffentliche TOPs: 1-98
            $stmt = $pdo->prepare("SELECT MAX(top_number) as max_top FROM agenda_items 
                    WHERE meeting_id = ? AND top_number BETWEEN 1 AND 98 AND is_confidential = 0");
            $stmt->execute([$meeting_id]);
            $result = $stmt->fetch();
            return ($result['max_top'] ?? 0) + 1;
        }
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Fehler in get_next_top_number(): " . $e->getMessage());
        }
        // Fallback bei Fehler: 101 für vertraulich, 1 für öffentlich
        return $is_confidential ? 101 : 1;
    }
}

/**
 * Erstellt Standard-TOPs (0 und 99)
 */
function create_standard_tops($pdo, $meeting_id, $creator_id) {
    try {
        // TOP 0 - Antrag/Beschluss
        $stmt = $pdo->prepare("INSERT INTO agenda_items 
                (meeting_id, top_number, title, description, category, priority, estimated_duration, is_confidential, created_by_member_id) 
                VALUES (?, 0, 'Wahl von Sitzungsleitung und Protokoll', 'Formale Wahl zu Beginn der Sitzung', 'antrag_beschluss', 10.00, 5, 0, ?)");
        $stmt->execute([$meeting_id, $creator_id]);
        
        // TOP 99 - Sonstiges
        $stmt = $pdo->prepare("INSERT INTO agenda_items 
                (meeting_id, top_number, title, description, category, priority, estimated_duration, is_confidential, created_by_member_id) 
                VALUES (?, 99, 'Verschiedenes', 'Sonstige Punkte und Anmerkungen', 'sonstiges', 1.00, 5, 0, ?)");
        $stmt->execute([$meeting_id, $creator_id]);
        
        return true;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Fehler in create_standard_tops(): " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Berechnet Priorität und Dauer neu (Mittelwerte aus Kommentaren)
 */
function recalculate_item_metrics($pdo, $item_id) {
    try {
        // Priorität und Dauer SEPARAT berechnen (nicht nur wo beide gesetzt sind!)
        $stmt = $pdo->prepare("
            SELECT
                AVG(priority_rating) as avg_priority,
                AVG(duration_estimate) as avg_duration
            FROM agenda_comments
            WHERE item_id = ?
        ");
        $stmt->execute([$item_id]);
        $metrics = $stmt->fetch();

        // Update-Statement vorbereiten
        $updates = [];
        $params = [];

        if ($metrics['avg_priority'] !== null) {
            $updates[] = "priority = ?";
            $params[] = round($metrics['avg_priority'], 2);
        }

        if ($metrics['avg_duration'] !== null) {
            $updates[] = "estimated_duration = ?";
            $params[] = round($metrics['avg_duration']);
        }

        // Nur updaten wenn mindestens ein Wert vorhanden
        if (!empty($updates)) {
            $sql = "UPDATE agenda_items SET " . implode(", ", $updates) . " WHERE item_id = ?";
            $params[] = $item_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        return true;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Fehler in recalculate_item_metrics(): " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Rendert einen einzelnen TOP (für Wiederverwendung)
 */
function render_agenda_item($item, $meeting, $current_user, $can_edit = false, $inside_form = false) {
    $is_creator = ($item['creator_member_id'] == $current_user['member_id']);
    $can_edit_now = ($is_creator && $meeting['status'] === 'preparation' && $item['top_number'] != 0 && $item['top_number'] != 99);
    ?>
    <div class="agenda-item">
        <?php if ($can_edit_now): ?>
            <!-- Bearbeitbare Version für Ersteller -->
            <form method="POST" action="" style="border: 2px solid #ffc107; padding: 15px; border-radius: 5px; background: #fffbf0;">
                <input type="hidden" name="edit_agenda_item" value="1">
                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                
                <div style="margin-bottom: 10px;">
                    <label style="font-weight: 600; color: #856404;">TOP <?php echo $item['top_number']; ?> - Titel:</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" required style="width: 100%; padding: 8px; border: 1px solid #ffc107;">
                </div>
                
                <div style="margin-bottom: 10px;">
                    <label style="font-weight: 600; color: #856404;">Beschreibung:</label>
                    <textarea name="description" style="width: 100%; padding: 8px; border: 1px solid #ffc107; min-height: 80px;"><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                
                <div style="margin-bottom: 10px; font-size: 12px; color: #856404;">
                    Erstellt von: <?php echo htmlspecialchars($item['creator_first_name'] . ' ' . $item['creator_last_name']); ?> | 
                    Priorität: <?php echo $item['priority']; ?> | 
                    Dauer: <?php echo $item['estimated_duration']; ?> Min.
                </div>
                
                <button type="submit" style="background: #ffc107; color: #856404; padding: 8px 16px;">💾 Änderungen speichern</button>
            </form>
        <?php else: ?>
            <!-- Normal-Ansicht für alle anderen -->
            <div class="agenda-title">
                TOP <?php echo $item['top_number']; ?>: <?php echo htmlspecialchars($item['title']); ?>
                <?php if ($item['is_confidential']): ?>
                    <span class="badge" style="background: #f39c12; color: white;">🔒 Vertraulich</span>
                <?php endif; ?>
            </div>
            
            <div class="agenda-meta">
                Eingetragen von: <?php echo htmlspecialchars($item['creator_first_name'] . ' ' . $item['creator_last_name']); ?> |
                Priorität: <?php echo $item['priority']; ?> |
                Dauer: <?php echo $item['estimated_duration']; ?> Min.
            </div>
            
            <?php if ($item['description']): ?>
                <p style="margin: 10px 0;"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Kommentare anzeigen -->
        <h4 style="margin-top: 15px; font-size: 14px; color: #666;">💬 Diskussionsbeiträge</h4>
        <div class="comments-box">
            <?php if (empty($item['comments'])): ?>
                <div style="color: #999; font-size: 13px; padding: 10px;">Noch keine Kommentare vorhanden</div>
            <?php else: ?>
                <?php foreach ($item['comments'] as $comment): ?>
                    <div class="comment">
                        <span class="comment-author"><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>:</span>
                        <span class="comment-text"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Kommentar-Formular (nur in Vorbereitung und NUR wenn inside_form = true) -->
        <?php if ($inside_form && $meeting['status'] === 'preparation'): ?>
            <div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 5px;" class="comment-form-item">
                <input type="hidden" name="item_ids[]" value="<?php echo $item['item_id']; ?>">
                
                <div class="form-group">
                    <textarea name="comment_texts[<?php echo $item['item_id']; ?>]" placeholder="Ihr Kommentar (optional)..." style="min-height: 60px;"></textarea>
                </div>
            </div>
        <?php endif; ?>

        <!-- Protokollnotizen (nur aktiv) -->
        <?php if ($meeting['status'] === 'active' && $meeting['secretary_member_id'] == $current_user['member_id']): ?>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                <h4 style="font-size: 14px; color: #666;">📝 Protokoll</h4>
                <form method="POST" action="">
                    <input type="hidden" name="add_protocol" value="1">
                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                    
                    <div class="form-group">
                        <textarea name="notes" style="min-height: 80px;"><?php echo htmlspecialchars($item['protocol_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit">Protokoll speichern</button>
                </form>
            </div>
        <?php elseif ($item['protocol_notes']): ?>
            <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 5px;">
                <strong>📝 Protokoll:</strong><br>
                <?php echo nl2br(htmlspecialchars($item['protocol_notes'])); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Zeigt Fehler- oder Erfolgs-Nachricht an
 */
function show_message($message, $type = 'success') {
    if (empty($message)) return;
    
    $class = $type === 'error' ? 'error-message' : 'message';
    echo '<div class="' . $class . '">' . htmlspecialchars($message) . '</div>';
}
?>