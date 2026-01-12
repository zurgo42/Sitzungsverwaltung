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
        die("Datenbankverbindung fehlgeschlagen. Bitte kontaktiere den Administrator.");
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
 * Konvertiert URLs in Text zu klickbaren Links
 * - Öffnet Links in neuem Tab
 * - Zeigt Alerts für Bilder/PDFs auf Mobilgeräten
 * - Zeigt lange URLs (>40 Zeichen) als "🔗 Link" mit Tooltip
 * - Fügt Copy-Button (📋) für lange URLs hinzu
 *
 * @param string $text Der Text der URLs enthalten kann
 * @return string Der Text mit klickbaren Links
 */
function linkify_text($text) {
    // Text escapen für Sicherheit
    $text = htmlspecialchars($text);

    // URL-Pattern (http, https, www)
    $pattern = '#\b((https?://|www\.)[^\s<]+)#i';

    $text = preg_replace_callback($pattern, function($matches) {
        $url = $matches[1];

        // www. URLs mit http:// ergänzen
        if (substr($url, 0, 4) !== 'http') {
            $url = 'http://' . $url;
        }

        // Dateiendung prüfen
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
        $is_pdf = ($extension === 'pdf');

        // Link-Text: Bei langen URLs nur "Link" anzeigen
        $display_text = strlen($matches[1]) > 40 ? '🔗 Link' : htmlspecialchars($matches[1]);

        // Alert für Bilder/PDFs auf Mobile
        $onclick = '';
        if ($is_image || $is_pdf) {
            $type = $is_image ? 'Bild' : 'PDF';
            $onclick = " onclick=\"if(window.innerWidth <= 768) { alert('⚠️ {$type}-Datei wird in neuem Tab geöffnet'); }\"";
        }

        // Copy-Button (nur bei verkürzten Links)
        $copy_button = '';
        if (strlen($matches[1]) > 40) {
            $escaped_url = htmlspecialchars($url);
            $copy_button = ' <button onclick="navigator.clipboard.writeText(\'' . addslashes($escaped_url) . '\'); this.textContent=\'✓\'; setTimeout(()=>this.textContent=\'📋\',1000); return false;" style="border:none; background:transparent; cursor:pointer; font-size:14px; padding:0 4px; color:#2196f3;" title="Link kopieren">📋</button>';
        }

        return '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" title="' . htmlspecialchars($url) . '"' . $onclick . '>' . $display_text . '</a>' . $copy_button;
    }, $text);

    return $text;
}

/**
 * Lädt alle Meetings
 * Kompatibel mit members UND berechtigte Tabelle
 */
function get_all_meetings($pdo) {
    try {
        // Nur Meeting-Daten holen, OHNE JOIN auf members
        $stmt = $pdo->query("SELECT m.*
                FROM svmeetings m
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
        $stmt = $pdo->prepare("SELECT m.* FROM svmeetings m WHERE m.meeting_id = ?");
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
        
        $stmt = $pdo->prepare("SELECT ai.*
                FROM svagenda_items ai
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

        // Creator-Namen über Adapter holen
        foreach ($agenda as &$item) {
            if ($item['created_by_member_id']) {
                $creator = get_member_by_id($pdo, $item['created_by_member_id']);
                $item['creator_first_name'] = $creator['first_name'] ?? null;
                $item['creator_last_name'] = $creator['last_name'] ?? null;
                $item['creator_member_id'] = $item['created_by_member_id'];
            } else {
                $item['creator_first_name'] = null;
                $item['creator_last_name'] = null;
                $item['creator_member_id'] = null;
            }
        }
        unset($item);
        
        // Kommentare für jeden TOP laden (ohne JOIN!)
        foreach ($agenda as &$item) {
            $stmt = $pdo->prepare("SELECT ac.*
                    FROM svagenda_comments ac
                    WHERE ac.item_id = ?
                    ORDER BY ac.created_at ASC");
            $stmt->execute([$item['item_id']]);
            $comments = $stmt->fetchAll();

            // Kommentator-Namen über Adapter holen
            foreach ($comments as &$comment) {
                $commenter = get_member_by_id($pdo, $comment['member_id']);
                $comment['first_name'] = $commenter['first_name'] ?? 'Unbekannt';
                $comment['last_name'] = $commenter['last_name'] ?? '';
            }
            unset($comment);

            $item['comments'] = $comments;
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
            $stmt = $pdo->prepare("SELECT MAX(top_number) as max_top FROM svagenda_items 
                    WHERE meeting_id = ? AND is_confidential = 1");
            $stmt->execute([$meeting_id]);
            $result = $stmt->fetch();
            // Wenn noch keine vertraulichen TOPs existieren, starte bei 101
            // Sonst nimm die höchste Nummer + 1
            return ($result['max_top'] ?? 100) + 1;
        } else {
            // Öffentliche TOPs: 1-98
            $stmt = $pdo->prepare("SELECT MAX(top_number) as max_top FROM svagenda_items 
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
        $stmt = $pdo->prepare("INSERT INTO svagenda_items 
                (meeting_id, top_number, title, description, category, priority, estimated_duration, is_confidential, created_by_member_id) 
                VALUES (?, 0, 'Wahl von Sitzungsleitung und Protokoll', 'Formale Wahl zu Beginn der Sitzung', 'antrag_beschluss', 10.00, 5, 0, ?)");
        $stmt->execute([$meeting_id, $creator_id]);
        
        // TOP 99 - Sonstiges
        $stmt = $pdo->prepare("INSERT INTO svagenda_items 
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
            FROM svagenda_comments
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
            $sql = "UPDATE svagenda_items SET " . implode(", ", $updates) . " WHERE item_id = ?";
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

/**
 * Prüft ob ein User Zugriff auf ein Meeting hat basierend auf Sichtbarkeitstyp
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $meeting_id Meeting-ID
 * @param int $member_id User-ID
 * @return bool true wenn Zugriff erlaubt, sonst false
 */
function can_user_access_meeting($pdo, $meeting_id, $member_id) {
    try {
        // Meeting-Details laden
        $stmt = $pdo->prepare("SELECT visibility_type FROM svmeetings WHERE meeting_id = ?");
        $stmt->execute([$meeting_id]);
        $meeting = $stmt->fetch();

        if (!$meeting) {
            return false;
        }

        // User-Details laden (mit Rolle) - über Adapter!
        $user = get_member_by_id($pdo, $member_id);

        if (!$user) {
            return false;
        }

        $visibility = $meeting['visibility_type'] ?? 'invited_only';

        // Öffentliche Meetings: Alle eingeloggten User können sehen
        if ($visibility === 'public') {
            return true;
        }

        // Angemeldete: Nur Führungsteam (Vorstand, GF, Assistenz)
        if ($visibility === 'authenticated') {
            $leadership_roles = ['vorstand', 'gf', 'assistenz', 'fuehrungsteam'];
            return in_array(strtolower($user['role']), $leadership_roles);
        }

        // Eingeladene: Nur invited participants
        if ($visibility === 'invited_only') {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM svmeeting_participants WHERE meeting_id = ? AND member_id = ?");
            $stmt->execute([$meeting_id, $member_id]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        }

        return false;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            error_log("Fehler in can_user_access_meeting(): " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Lädt alle Meetings die für den User sichtbar sind
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $member_id User-ID
 * @return array Array mit sichtbaren Meetings
 */
function get_visible_meetings($pdo, $member_id) {
    try {
        // User-Details laden (mit Rolle) - über Adapter!
        $user = get_member_by_id($pdo, $member_id);

        if (!$user) {
            return [];
        }

        $user_role = strtolower($user['role']);
        $is_top_management = in_array($user_role, ['vorstand', 'gf', 'assistenz']);
        $is_fuehrungsteam = ($user_role === 'fuehrungsteam');
        $is_mitglied = ($user_role === 'mitglied');

        // Meetings laden basierend auf Visibility-Regeln:
        // - public: Alle eingeloggten User sehen
        // - authenticated: Nur Führungsteam (Vorstand, GF, Assistenz, Führungsteam)
        // - invited_only: Nur Teilnehmer
        //
        // Protokoll-Filterung (für ended, protocol_ready, archived):
        // - Vorstand+GF+Assistenz: sehen alle Protokolle
        // - Führungsteam: sehen Protokolle von Meetings, an denen sie teilnahmen + authenticated + public
        // - Mitglied: sehen nur Protokolle von public Meetings

        if ($is_top_management) {
            // Vorstand, GF, Assistenz sehen ALLES
            $stmt = $pdo->prepare("
                SELECT DISTINCT m.*
                FROM svmeetings m
                ORDER BY FIELD(m.status, 'active', 'protocol_ready', 'ended', 'preparation', 'archived'), m.meeting_date ASC
            ");
            $stmt->execute();
            $meetings = $stmt->fetchAll();
        } elseif ($is_fuehrungsteam) {
            // Führungsteam sieht: public + authenticated + invited_only (wenn Teilnehmer)
            // Für Protokolle (ended/protocol_ready/archived): public + authenticated + nur eigene invited_only
            $stmt = $pdo->prepare("
                SELECT DISTINCT m.*
                FROM svmeetings m
                LEFT JOIN svmeeting_participants mp ON m.meeting_id = mp.meeting_id AND mp.member_id = ?
                WHERE m.visibility_type IN ('public', 'authenticated')
                   OR (m.visibility_type = 'invited_only' AND mp.member_id IS NOT NULL)
                ORDER BY FIELD(m.status, 'active', 'protocol_ready', 'ended', 'preparation', 'archived'), m.meeting_date ASC
            ");
            $stmt->execute([$member_id]);
            $meetings = $stmt->fetchAll();
        } else {
            // Mitglieder sehen:
            // - Bei preparation/active: invited_only (wenn Teilnehmer)
            // - Bei ended/protocol_ready/archived (Protokolle): nur public
            $stmt = $pdo->prepare("
                SELECT DISTINCT m.*
                FROM svmeetings m
                LEFT JOIN svmeeting_participants mp ON m.meeting_id = mp.meeting_id AND mp.member_id = ?
                WHERE
                    -- Immer public Meetings
                    m.visibility_type = 'public'
                    -- invited_only nur wenn Teilnehmer UND nicht archiviert
                    OR (m.visibility_type = 'invited_only'
                        AND mp.member_id IS NOT NULL
                        AND m.status IN ('preparation', 'active'))
                ORDER BY FIELD(m.status, 'active', 'protocol_ready', 'ended', 'preparation', 'archived'), m.meeting_date ASC
            ");
            $stmt->execute([$member_id]);
            $meetings = $stmt->fetchAll();
        }

        // Namen aus dem globalen members-Array hinzufügen
        foreach ($meetings as &$meeting) {
            if ($meeting['invited_by_member_id']) {
                // Verwende get_member_from_cache wenn verfügbar (index.php Kontext), sonst get_member_by_id
                if (function_exists('get_member_from_cache')) {
                    $inviter = get_member_from_cache($meeting['invited_by_member_id']);
                } else {
                    $inviter = get_member_by_id($pdo, $meeting['invited_by_member_id']);
                }
                $meeting['first_name'] = $inviter['first_name'] ?? null;
                $meeting['last_name'] = $inviter['last_name'] ?? null;
            } else {
                $meeting['first_name'] = null;
                $meeting['last_name'] = null;
            }
        }
        unset($meeting); // Referenz aufheben

        return $meetings;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Fehler in get_visible_meetings(): " . $e->getMessage());
        }
        return [];
    }
}

/**
 * Prüft ob User Read-Only Zugriff auf ein Meeting hat
 * (Kann Meeting sehen, ist aber kein Teilnehmer)
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $meeting_id Meeting-ID
 * @param int $member_id User-ID
 * @return bool true wenn read-only
 */
function is_readonly_user($pdo, $meeting_id, $member_id) {
    try {
        // Prüfen ob User das Meeting sehen kann
        if (!can_user_access_meeting($pdo, $meeting_id, $member_id)) {
            return false;
        }

        // Prüfen ob User Teilnehmer ist
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM svmeeting_participants WHERE meeting_id = ? AND member_id = ?");
        $stmt->execute([$meeting_id, $member_id]);
        $result = $stmt->fetch();
        $is_participant = ($result['count'] > 0);

        // Read-only wenn sichtbar ABER nicht Teilnehmer
        return !$is_participant;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Prüft ob User Admin-Rechte hat
 *
 * @param array $member Member-Array mit Daten
 * @return bool true wenn Admin
 */
function is_admin_user($member) {
    if (!$member) {
        return false;
    }

    // Prüfe NUR is_admin Flag in der Datenbank
    return isset($member['is_admin']) && $member['is_admin'] == 1;
}

/**
 * Ermittelt Access-Level eines Mitglieds für Zugriffskontrolle
 *
 * @param array $member Member-Array mit Daten
 * @return int Access-Level (0-19)
 */
function get_member_access_level($member) {
    if (!$member) {
        return 0;
    }

    // Mapping von Rollen zu Access-Levels
    $role_levels = [
        'gf' => 19,
        'assistenz' => 18,
        'vorstand' => 19,
        'fuehrungsteam' => 15,
        'projektleitung' => 12,
        'mitglied' => 0
    ];

    $role = $member['role'] ?? 'mitglied';
    return $role_levels[strtolower($role)] ?? 0;
}

/**
 * Lädt Abwesenheiten und löst Namen über den Adapter auf
 * Verhindert direkte JOINs auf svmembers (für Adapter-Kompatibilität)
 *
 * @param PDO $pdo Datenbankverbindung
 * @param string $where_clause WHERE-Bedingung (z.B. "a.end_date >= CURDATE()")
 * @param array $params Parameter für prepared statement
 * @return array Abwesenheiten mit aufgelösten Namen
 */
function get_absences_with_names($pdo, $where_clause = "1=1", $params = []) {
    // Abwesenheiten ohne JOIN laden
    $sql = "SELECT * FROM svabsences a WHERE $where_clause ORDER BY a.start_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $absences = $stmt->fetchAll();

    // Namen über Adapter auflösen
    foreach ($absences as &$absence) {
        // Hauptperson
        $member = get_member_by_id($pdo, $absence['member_id']);
        if ($member) {
            $absence['first_name'] = $member['first_name'];
            $absence['last_name'] = $member['last_name'];
            $absence['role'] = $member['role'];
        } else {
            $absence['first_name'] = '???';
            $absence['last_name'] = '(ID: ' . $absence['member_id'] . ')';
            $absence['role'] = 'mitglied';
        }

        // Vertretung (optional)
        if ($absence['substitute_member_id']) {
            $substitute = get_member_by_id($pdo, $absence['substitute_member_id']);
            if ($substitute) {
                $absence['sub_first_name'] = $substitute['first_name'];
                $absence['sub_last_name'] = $substitute['last_name'];
                $absence['sub_role'] = $substitute['role'];
            } else {
                $absence['sub_first_name'] = '???';
                $absence['sub_last_name'] = '(ID: ' . $absence['substitute_member_id'] . ')';
                $absence['sub_role'] = 'mitglied';
            }
        } else {
            $absence['sub_first_name'] = null;
            $absence['sub_last_name'] = null;
            $absence['sub_role'] = null;
        }
    }
    unset($absence); // Referenz löschen um Seiteneffekte zu vermeiden

    return $absences;
}

/**
 * Sortiert Mitglieder-Array nach Rollen-Hierarchie
 * Reihenfolge: Vorstand -> GF/Assistenz -> Führungsteam -> Mitglieder
 * Innerhalb der Gruppen alphabetisch nach Nachnamen
 *
 * @param array $members Array mit Mitgliedern
 * @return array Sortiertes Array
 */
function sort_members_by_role_hierarchy($members) {
    // Rollen-Prioritäten definieren (niedrigere Zahl = höhere Priorität)
    $role_priority = [
        'vorstand' => 1,
        'gf' => 2,
        'assistenz' => 2,  // GF und Assistenz gleichwertig
        'fuehrungsteam' => 3,
        'mitglied' => 4,
        'member' => 4  // Fallback für englische Bezeichnung
    ];

    usort($members, function($a, $b) use ($role_priority) {
        // Rollen ermitteln
        $role_a = strtolower($a['role'] ?? 'mitglied');
        $role_b = strtolower($b['role'] ?? 'mitglied');

        // Prioritäten holen (default 99 für unbekannte Rollen)
        $prio_a = $role_priority[$role_a] ?? 99;
        $prio_b = $role_priority[$role_b] ?? 99;

        // Erst nach Priorität sortieren
        if ($prio_a !== $prio_b) {
            return $prio_a - $prio_b;
        }

        // Bei gleicher Priorität alphabetisch nach Nachname
        return strcmp($a['last_name'], $b['last_name']);
    });

    return $members;
}
?>