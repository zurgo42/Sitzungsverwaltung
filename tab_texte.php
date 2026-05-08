<?php
/**
 * tab_texte.php - Textbearbeitung
 * Erstellt: 02.12.2025
 *
 * Zwei Modi:
 * - MIT Meeting: Meeting-spezifische Texte für Sitzungsteilnehmer
 * - OHNE Meeting: Allgemeine Texte für Vorstand+GF+Assistenz
 */

require_once 'functions_collab_text.php';

// View-Parameter
$view = $_GET['view'] ?? 'overview';
$text_id = intval($_GET['text_id'] ?? 0);

// Meeting-ID ermitteln (aus SESSION oder GET)
$meeting_id = $_SESSION['current_meeting_id'] ?? intval($_GET['meeting_id'] ?? 0);

// Modus bestimmen
$is_meeting_mode = ($meeting_id > 0);

// Berechtigungsprüfung
$has_access = false;
$is_initiator_role = false;
$meeting = null;
$context_description = '';

if ($is_meeting_mode) {
    // MEETING-MODUS: Prüfen ob User Teilnehmer ist
    $stmt = $pdo->prepare("
        SELECT m.*,
               mp.member_id as is_participant
        FROM svmeetings m
        LEFT JOIN svmeeting_participants mp ON m.meeting_id = mp.meeting_id AND mp.member_id = ?
        WHERE m.meeting_id = ?
    ");
    $stmt->execute([$current_user['member_id'], $meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    // Secretary-Namen über Adapter laden
    if ($meeting && $meeting['secretary_member_id']) {
        $secretary = get_member_by_id($pdo, $meeting['secretary_member_id']);
        if ($secretary) {
            $meeting['secretary_first_name'] = $secretary['first_name'];
            $meeting['secretary_last_name'] = $secretary['last_name'];
        } else {
            $meeting['secretary_first_name'] = 'Unbekannt';
            $meeting['secretary_last_name'] = '';
        }
    }

    if ($meeting && $meeting['is_participant']) {
        $has_access = true;
        $is_initiator_role = ($meeting['secretary_member_id'] == $current_user['member_id']);
        $context_description = 'Sitzung: ' . htmlspecialchars($meeting['meeting_name']);
    }
} else {
    // ALLGEMEIN-MODUS: Nur Vorstand, GF, Assistenz, Führungsteam
    // Case-insensitive Rollenprüfung (funktioniert mit members und berechtigte)
    $user_role_lower = strtolower($current_user['role']);
    if (in_array($user_role_lower, ['vorstand', 'gf', 'geschäftsführung', 'assistenz', 'fuehrungsteam'])) {
        $has_access = true;
        $is_initiator_role = true; // Alle dürfen Texte erstellen
        $context_description = 'Allgemeine Texte (Vorstand/GF/Assistenz/Führungsteam)';
    }
}

// Zugriff verweigert
if (!$has_access) {
    echo '<div class="card">';
    echo '<h2>📝 Gemeinsame Texte</h2>';
    echo '<div class="alert alert-danger">';
    if ($is_meeting_mode) {
        echo '<p>Du bist kein Teilnehmer dieser Sitzung.</p>';
    } else {
        echo '<p>Diese Funktion steht nur Vorstand, Geschäftsführung, Assistenz und Führungsteam zur Verfügung.</p>';
    }
    echo '</div>';
    echo '</div>';
    return;
}

?>

<!-- CSS für kollaborative Texte -->
<style>
.collab-text-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.collab-text-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    transition: box-shadow 0.3s;
}

.collab-text-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.collab-text-card h3 {
    margin-top: 0;
    color: #333;
}

.collab-text-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: bold;
}

.status-active {
    background: #28a745;
    color: white;
}

.status-finalized {
    background: #6c757d;
    color: white;
}

.paragraph-container {
    margin-bottom: 20px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 0;
    background: white;
    position: relative;
    transition: border-color 0.3s;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.paragraph-container.editing {
    border-color: #007bff;
    background: #f8f9fa;
}

.paragraph-container.locked {
    border-color: #ffc107;
    background: #fff3cd;
}

.paragraph-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
    padding: 10px 10px 8px 10px;
    border-bottom: 1px solid #ddd;
}

.paragraph-content {
    width: 100% !important;
    max-width: 100% !important;
    min-height: 60px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
    padding: 10px;
    box-sizing: border-box !important;
}

.paragraph-edit-area {
    width: 100% !important;
    max-width: 100% !important;
    min-height: 300px;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-family: inherit;
    font-size: inherit;
    line-height: 1.6;
    resize: vertical;
    box-sizing: border-box !important;
}

.paragraph-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    padding: 10px;
}

.online-users {
    background: #e7f3ff;
    border-left: 4px solid #007bff;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.online-users-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}

.online-user-badge {
    background: #007bff;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.9em;
}

.text-preview {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    line-height: 1.8;
    white-space: pre-wrap;
    word-wrap: break-word;
}

@media (max-width: 768px) {
    .collab-text-list {
        grid-template-columns: 1fr;
    }

    .paragraph-actions {
        flex-direction: column;
    }

    .paragraph-actions button {
        width: 100%;
    }
}

/* Print-Styles: Nur den finalen Text-Inhalt drucken */
@media print {
    /* Alles verstecken */
    body * {
        visibility: hidden;
    }

    /* Nur den Text-Inhalt anzeigen */
    #finalTextContent, #finalTextContent * {
        visibility: visible;
    }

    #finalTextContent {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: white;
        border: none;
        padding: 20px;
    }
}
</style>

<?php

//============================================================================
// OVERVIEW: Liste aller Texte
//============================================================================

if ($view === 'overview') {
    // Texte laden
    if ($is_meeting_mode) {
        $all_texts = getCollabTextsByMeeting($pdo, $meeting_id);
    } else {
        // Allgemeine Texte (meeting_id = NULL) - OHNE JOIN auf svmembers
        $stmt = $pdo->prepare("
            SELECT t.*,
                   COUNT(DISTINCT p.member_id) as participant_count
            FROM svcollab_texts t
            LEFT JOIN svcollab_text_participants p ON t.text_id = p.text_id
            WHERE t.meeting_id IS NULL
            GROUP BY t.text_id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        $all_texts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initiator-Namen über Adapter holen
        foreach ($all_texts as &$text) {
            if ($text['initiator_member_id']) {
                $initiator = get_member_by_id($pdo, $text['initiator_member_id']);
                $text['initiator_first_name'] = $initiator['first_name'] ?? null;
                $text['initiator_last_name'] = $initiator['last_name'] ?? null;
            }
        }
        unset($text);
    }
    ?>

    <h2>📝 Gemeinsame Texte</h2>

    <div class="alert alert-info">
        <strong>ℹ️ Info:</strong> Vorstand, GF, Assistenz und Führungsteam können hier gemeinsam an Texten arbeiten.
    </div>

    <?php if ($is_initiator_role): ?>
    <button onclick="showCreateTextDialog()" class="btn-primary" style="margin-bottom: 20px;">
        + Neuen Text erstellen
    </button>
    <?php endif; ?>

    <?php if (empty($all_texts)): ?>
        <p style="color: #666; font-style: italic;">
            Noch keine gemeinsamen Texte vorhanden.
            <?php if ($is_initiator_role): ?>
            Erstelle den ersten Text mit dem Button oben.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div class="collab-text-list">
            <?php foreach ($all_texts as $text): ?>
                <div class="collab-text-card">
                    <h3><?php echo htmlspecialchars($text['title']); ?></h3>

                    <p style="font-size: 0.9em; color: #666;">
                        Ersteller: <?php echo htmlspecialchars($text['initiator_first_name'] . ' ' . $text['initiator_last_name']); ?>
                    </p>

                    <p>
                        <span class="collab-text-status <?php echo $text['status'] === 'active' ? 'status-active' : 'status-finalized'; ?>">
                            <?php echo $text['status'] === 'active' ? '⏳ Aktiv' : '✅ Finalisiert'; ?>
                        </span>
                    </p>

                    <p style="font-size: 0.85em; color: #999;">
                        Erstellt: <?php echo date('d.m.Y H:i', strtotime($text['created_at'])); ?>
                    </p>

                    <?php if ($text['status'] === 'finalized'): ?>
                        <button onclick="window.location.href='?tab=texte&view=final&text_id=<?php echo $text['text_id']; ?>'"
                                class="btn-secondary" style="width: 100%; margin-bottom: 8px;">
                            📄 Ansehen
                        </button>
                    <?php else: ?>
                        <button onclick="window.location.href='?tab=texte&view=editor&text_id=<?php echo $text['text_id']; ?>'"
                                class="btn-primary" style="width: 100%; margin-bottom: 8px;">
                            ✏️ Bearbeiten
                        </button>
                    <?php endif; ?>

                    <?php
                    // Lösch-Button: Nur für Ersteller oder Admin
                    $can_delete = ($text['initiator_member_id'] == $current_user['member_id']) || $current_user['is_admin'];
                    if ($can_delete):
                    ?>
                        <button onclick="deleteText(<?php echo $text['text_id']; ?>, '<?php echo htmlspecialchars($text['title'], ENT_QUOTES); ?>')"
                                class="btn-danger" style="width: 100%; font-size: 0.9em;">
                            🗑️ Löschen
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Dialog: Neuen Text erstellen -->
    <div id="createTextDialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; padding: 20px 0;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 1024px; width: 90%; margin: 0 auto; min-height: fit-content;">
            <h3>Neuen Text erstellen</h3>

            <label>Titel:</label>
            <input type="text" id="newTextTitle" placeholder="z.B. Pressemeldung Vereinsheim"
                   style="width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">

            <label>Initial-Text (optional):</label>
            <textarea id="newTextContent" placeholder="Optional: Ersten Absatz bereits eingeben...

Tipp: Texte mit einer oder mehreren Leerzeilen werden automatisch in mehrere Absätze aufgeteilt."
                      style="width: 100%; min-height: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 14px; line-height: 1.6;"></textarea>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="createText()" class="btn-primary">Erstellen</button>
                <button onclick="hideCreateTextDialog()" class="btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>

    <script>
    function showCreateTextDialog() {
        document.getElementById('createTextDialog').style.display = 'flex';
    }

    function hideCreateTextDialog() {
        document.getElementById('createTextDialog').style.display = 'none';
        document.getElementById('newTextTitle').value = '';
        document.getElementById('newTextContent').value = '';
    }

    function createText() {
        const title = document.getElementById('newTextTitle').value.trim();
        const content = document.getElementById('newTextContent').value.trim();

        if (!title) {
            alert('Bitte gib einen Titel ein.');
            return;
        }

        fetch('api/collab_text_create.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                meeting_id: <?php echo $meeting_id ?: 'null'; ?>,
                title: title,
                initial_content: content
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = '?tab=texte&view=editor&text_id=' + data.text_id;
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Fehler beim Erstellen des Textes');
        });
    }

    function deleteText(textId, textTitle) {
        if (!confirm('Möchtest du den Text "' + textTitle + '" wirklich löschen?\n\nDieser Vorgang kann nicht rückgängig gemacht werden!')) {
            return;
        }

        fetch('api/collab_text_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({text_id: textId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Fehler beim Löschen');
        });
    }
    </script>

    <?php
    return;
}

//============================================================================
// EDITOR: Absatz-basiertes Editieren
//============================================================================

if ($view === 'editor') {
    // Text laden
    $text = getCollabText($pdo, $text_id);

    if (!$text) {
        echo '<div class="alert alert-danger">Text nicht gefunden.</div>';
        return;
    }

    // Zugriffsprüfung
    if (!hasCollabTextAccess($pdo, $text_id, $current_user['member_id'])) {
        echo '<div class="alert alert-danger">Sie haben keinen Zugriff auf diesen Text.</div>';
        return;
    }

    // Prüfen ob finalisiert
    if ($text['status'] === 'finalized') {
        echo '<div class="alert alert-info">Dieser Text wurde finalisiert und kann nicht mehr bearbeitet werden.
              <a href="?tab=texte&view=final&text_id=' . $text_id . '">Zur Ansicht</a></div>';
        return;
    }

    $is_initiator = ($text['initiator_member_id'] == $current_user['member_id']);
    ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2>✏️ <?php echo htmlspecialchars($text['title']); ?></h2>
            <p style="color: #666; font-size: 0.9em; margin: 5px 0 0 0;">
                Erstellt von <?php echo htmlspecialchars($text['initiator_first_name'] . ' ' . $text['initiator_last_name']); ?>
            </p>
        </div>
        <button onclick="window.location.href='?tab=texte&view=overview'" class="btn-secondary back-to-overview-btn">
            ← Zurück zur Übersicht
        </button>
    </div>

    <!-- Online-Benutzer -->
    <div id="onlineUsersBox" class="online-users">
        <strong>🟢 Online:</strong>
        <div id="onlineUsersList" class="online-users-list">
            <span style="color: #999;">Lade...</span>
        </div>
    </div>

    <!-- Buttons -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
        <button onclick="addParagraph()" class="btn-primary">+ Absatz hinzufügen</button>
        <button onclick="showPreview()" class="btn-secondary">👁️ Text zeigen und ggf. kopieren</button>
        <?php if ($is_initiator): ?>
            <button onclick="finalizeText()" class="btn-danger" style="margin-left: auto;">
                ✅ Text finalisieren
            </button>
        <?php endif; ?>
    </div>

    <!-- Absätze -->
    <div id="paragraphsContainer">
        <?php
        $total_paragraphs = count($text['paragraphs']);
        foreach ($text['paragraphs'] as $index => $para):
            renderParagraph($para, $current_user['member_id'], $index + 1, $total_paragraphs);
        endforeach;
        ?>
    </div>

    <?php if (empty($text['paragraphs'])): ?>
        <p style="color: #999; font-style: italic;">
            Noch keine Absätze vorhanden. Klicke auf "+ Absatz hinzufügen" um zu starten.
        </p>
    <?php endif; ?>

    <!-- Vorschau-Dialog -->
    <div id="previewDialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 1024px; width: 90%; margin: 20px; max-height: 80vh; overflow-y: auto;">
            <h3>Text: <?php echo htmlspecialchars($text['title']); ?></h3>
            <div id="previewContent" class="text-preview">Lade...</div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="copyPreviewToClipboard()" class="btn-primary">📋 In Zwischenablage kopieren</button>
                <button onclick="hidePreview()" class="btn-secondary">Schließen</button>
            </div>
        </div>
    </div>

    <!-- Finalisieren-Dialog -->
    <div id="finalizeDialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <h3>⚠️ Text finalisieren</h3>
            <p>Der Text wird nach Finalisierung schreibgeschützt. Alle Benutzer verlieren die Bearbeitungsrechte.</p>

            <label>Name für finale Version:</label>
            <input type="text" id="finalNameInput" placeholder="z.B. Pressemeldung Final v1.0"
                   value="<?php echo htmlspecialchars($text['title']); ?> (Final)"
                   style="width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="confirmFinalize()" class="btn-danger">Ja, finalisieren</button>
                <button onclick="hideFinalizeDialog()" class="btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>

    <script>
    const TEXT_ID = <?php echo $text_id; ?>;
    const CURRENT_USER_ID = <?php echo $current_user['member_id']; ?>;
    let lastUpdate = new Date().toISOString();
    let pollingInterval = null;
    let heartbeatInterval = null;
    let editingParagraphId = null;
    let lockWarningTimeout = null;
    let lockTimerInterval = null;
    let lockTimeRemaining = 0;
    let lockHeartbeatInterval = null;

    // Initialisierung
    document.addEventListener('DOMContentLoaded', function() {
        startPolling();
        startHeartbeat();

        // Page Visibility API: Polling pausieren wenn Tab nicht sichtbar (Performance-Optimierung)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Tab nicht sichtbar - Polling stoppen
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                }
            } else {
                // Tab wieder sichtbar - Polling neu starten
                if (!pollingInterval) {
                    startPolling();
                    fetchUpdates(); // Sofort Updates holen
                }
            }
        });
    });

    // Polling für Echtzeit-Updates
    function startPolling() {
        // Polling alle 10 Sekunden für bessere Performance (XAMPP-optimiert)
        pollingInterval = setInterval(fetchUpdates, 10000);
    }

    function fetchUpdates() {
        fetch('api/collab_text_get_updates.php?text_id=' + TEXT_ID + '&since=' + encodeURIComponent(lastUpdate))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Online-Benutzer aktualisieren
                    updateOnlineUsers(data.online_users);

                    // Absätze aktualisieren (wenn nicht gerade editiert)
                    if (data.paragraphs && data.paragraphs.length > 0) {
                        updateParagraphs(data.paragraphs);
                    }

                    // Status prüfen
                    if (data.text_status === 'finalized') {
                        alert('Der Text wurde finalisiert und kann nicht mehr bearbeitet werden.');
                        window.location.href = '?tab=texte&view=final&text_id=' + TEXT_ID;
                    }

                    lastUpdate = data.server_time;
                }
            })
            .catch(err => console.error('Polling error:', err));
    }

    // Heartbeat alle 15 Sekunden
    function startHeartbeat() {
        heartbeatInterval = setInterval(sendHeartbeat, 15000);
        sendHeartbeat(); // Sofort einmal senden
    }

    function sendHeartbeat() {
        fetch('api/collab_text_heartbeat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({text_id: TEXT_ID})
        })
        .then(r => {
            if (!r.ok) {
                console.error('Heartbeat failed:', r.status);
            }
        })
        .catch(err => console.error('Heartbeat error:', err));
    }

    // Online-Benutzer aktualisieren
    function updateOnlineUsers(users) {
        const container = document.getElementById('onlineUsersList');
        if (!users || users.length === 0) {
            container.innerHTML = '<span style="color: #999;">Keine anderen Benutzer online</span>';
            return;
        }

        container.innerHTML = users.map(u =>
            '<span class="online-user-badge">' +
            u.first_name + ' ' + u.last_name +
            '</span>'
        ).join('');
    }

    // Absätze aktualisieren
    function updateParagraphs(paragraphs) {
        paragraphs.forEach(para => {
            // Nicht aktualisieren wenn gerade von diesem User editiert wird
            if (editingParagraphId == para.paragraph_id) {
                return;
            }

            const paraDiv = document.querySelector('[data-paragraph-id="' + para.paragraph_id + '"]');
            if (!paraDiv) {
                // Neuer Absatz → Seite neu laden
                location.reload();
                return;
            }

            // Content aktualisieren
            const contentDiv = paraDiv.querySelector('.paragraph-content');
            if (contentDiv && contentDiv.textContent !== para.content) {
                contentDiv.textContent = para.content;
            }

            // Lock-Status aktualisieren
            const lockInfo = paraDiv.querySelector('.paragraph-lock-info');
            if (para.locked_by_member_id && para.locked_by_member_id != CURRENT_USER_ID) {
                paraDiv.classList.add('locked');
                if (lockInfo) {
                    const lockerName = (para.locked_by_first_name && para.locked_by_last_name)
                        ? para.locked_by_first_name + ' ' + para.locked_by_last_name
                        : 'einem anderen Benutzer';
                    lockInfo.innerHTML = '🔒 Wird bearbeitet von: ' + lockerName;
                }
            } else {
                paraDiv.classList.remove('locked');
                if (lockInfo) {
                    lockInfo.innerHTML = '';
                }
            }
        });
    }

    // Absatz bearbeiten
    function editParagraph(paragraphId) {
        // Prüfen ob bereits ein anderer Absatz bearbeitet wird
        if (editingParagraphId && editingParagraphId !== paragraphId) {
            alert('⚠️ Du bearbeitest bereits einen anderen Absatz.\n\nBitte speichere oder breche die aktuelle Bearbeitung ab, bevor du einen weiteren Absatz öffnest.\n\nDadurch wird verhindert, dass Arbeit verloren geht.');
            return;
        }

        // Lock erwerben
        fetch('api/collab_text_lock_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({paragraph_id: paragraphId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showEditMode(paragraphId);
                editingParagraphId = paragraphId;
            } else {
                alert('Dieser Absatz wird gerade von ' + data.locked_by + ' bearbeitet.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Fehler beim Sperren des Absatzes');
        });
    }

    function showEditMode(paragraphId) {
        const paraDiv = document.querySelector('[data-paragraph-id="' + paragraphId + '"]');
        const contentDiv = paraDiv.querySelector('.paragraph-content');
        const currentContent = contentDiv.textContent;

        contentDiv.innerHTML = '<textarea class="paragraph-edit-area" id="editArea_' + paragraphId + '">' +
            currentContent + '</textarea>';

        paraDiv.classList.add('editing');

        // Buttons ändern
        const actions = paraDiv.querySelector('.paragraph-actions');
        actions.innerHTML = `
            <button onclick="saveParagraph(${paragraphId})" class="btn-primary">💾 Speichern</button>
            <button onclick="cancelEdit(${paragraphId})" class="btn-secondary">❌ Abbrechen</button>
            <span id="lockTimer_${paragraphId}" style="margin-left: 15px; font-weight: bold; color: #2196f3;">⏱️ 5:00</span>
        `;

        // Timer und Heartbeat starten
        startLockTimer(paragraphId);
        startLockHeartbeat(paragraphId);
    }

    function startLockHeartbeat(paragraphId) {
        // Stoppe alten Heartbeat falls vorhanden
        if (lockHeartbeatInterval) {
            clearInterval(lockHeartbeatInterval);
        }

        // Alle 60 Sekunden Lock-Activity aktualisieren
        lockHeartbeatInterval = setInterval(function() {
            fetch('api/collab_text_lock_paragraph.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    paragraph_id: paragraphId,
                    action: 'lock'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    console.warn('Lock-Heartbeat fehlgeschlagen:', data.message);
                }
            })
            .catch(err => console.error('Lock-Heartbeat Fehler:', err));
        }, 60000); // Alle 60 Sekunden
    }

    function stopLockHeartbeat() {
        if (lockHeartbeatInterval) {
            clearInterval(lockHeartbeatInterval);
            lockHeartbeatInterval = null;
        }
    }

    function startLockTimer(paragraphId) {
        // Timer stoppen falls vorhanden
        if (lockTimerInterval) {
            clearInterval(lockTimerInterval);
        }

        // 5 Minuten in Sekunden
        lockTimeRemaining = 300;

        // Timer-Update jede Sekunde
        lockTimerInterval = setInterval(function() {
            lockTimeRemaining--;

            const timerEl = document.getElementById('lockTimer_' + paragraphId);
            if (timerEl) {
                const minutes = Math.floor(lockTimeRemaining / 60);
                const seconds = lockTimeRemaining % 60;
                const timeString = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

                // Farbe ändern bei weniger als 1 Minute
                if (lockTimeRemaining < 60) {
                    timerEl.style.color = '#f44336'; // Rot
                } else if (lockTimeRemaining < 120) {
                    timerEl.style.color = '#ff9800'; // Orange
                }

                timerEl.textContent = '⏱️ ' + timeString;
            }

            // Bei 30 Sekunden: Auto-Speichern (großer Puffer bevor Lock abläuft)
            if (lockTimeRemaining === 30) {
                console.log('Auto-Save triggert bei 30 Sekunden verbleibend...');
                clearInterval(lockTimerInterval);
                lockTimerInterval = null;

                // Auto-Speichern und Lock freigeben
                autoSaveParagraph(paragraphId);
            }

            // Sicherheits-Check: Bei 5 Sekunden Notfall-Speichern falls 30-Sekunden-Trigger verpasst wurde
            if (lockTimeRemaining === 5) {
                console.log('Notfall Auto-Save bei 5 Sekunden...');
                clearInterval(lockTimerInterval);
                lockTimerInterval = null;
                autoSaveParagraph(paragraphId);
            }
        }, 1000);
    }

    function stopLockTimer() {
        if (lockTimerInterval) {
            clearInterval(lockTimerInterval);
            lockTimerInterval = null;
        }
        lockTimeRemaining = 0;

        // Heartbeat auch stoppen
        stopLockHeartbeat();
    }

    function autoSaveParagraph(paragraphId) {
        const textarea = document.getElementById('editArea_' + paragraphId);
        if (!textarea) return;

        const content = textarea.value;

        // Speichern
        fetch('api/collab_text_save_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                paragraph_id: paragraphId,
                content: content,
                text_id: TEXT_ID
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Edit-Mode verlassen
                editingParagraphId = null;
                exitEditMode(paragraphId, content, '<?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?>');

                // WICHTIG: Updates abrufen damit andere Benutzer sehen dass Lock weg ist
                fetchUpdates();

                // Hinweis anzeigen (nicht blockierend - Seite lädt ohnehin neu)
                // alert('⏰ Deine Änderungen wurden automatisch gespeichert.');
            } else {
                alert('Auto-Speichern fehlgeschlagen: ' + (data.error || 'Unbekannter Fehler'));
                // Bei Fehler trotzdem Lock freigeben
                unlockParagraphAndRefresh(paragraphId);
            }
        })
        .catch(err => {
            console.error('Auto-Save Error:', err);
            alert('Netzwerkfehler beim Auto-Speichern');
            // Bei Fehler trotzdem Lock freigeben
            unlockParagraphAndRefresh(paragraphId);
        });
    }

    function unlockParagraph(paragraphId) {
        fetch('api/collab_text_lock_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                paragraph_id: paragraphId,
                action: 'unlock'
            })
        });
    }

    function unlockParagraphAndRefresh(paragraphId) {
        fetch('api/collab_text_lock_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                paragraph_id: paragraphId,
                action: 'unlock'
            })
        })
        .then(() => {
            // Updates abrufen damit andere sehen dass Lock weg ist
            fetchUpdates();
        });
    }

    function saveParagraph(paragraphId) {
        const textarea = document.getElementById('editArea_' + paragraphId);
        const content = textarea.value;

        // Timer stoppen
        stopLockTimer();

        fetch('api/collab_text_save_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                paragraph_id: paragraphId,
                content: content
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                exitEditMode(paragraphId, content, data.editor_name);
                editingParagraphId = null;
            } else {
                alert('Fehler beim Speichern: ' + (data.error || 'Unbekannt'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
        });
    }

    function cancelEdit(paragraphId) {
        // Timer stoppen
        stopLockTimer();

        // Lock freigeben
        fetch('api/collab_text_lock_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                paragraph_id: paragraphId,
                action: 'unlock'
            })
        });

        location.reload(); // Einfach neu laden um alten Zustand wiederherzustellen
    }

    function exitEditMode(paragraphId, newContent, editorName) {
        const paraDiv = document.querySelector('[data-paragraph-id="' + paragraphId + '"]');
        const contentDiv = paraDiv.querySelector('.paragraph-content');
        contentDiv.textContent = newContent;

        paraDiv.classList.remove('editing');

        // Editor-Namen im Header aktualisieren
        if (editorName) {
            const headerText = paraDiv.querySelector('.paragraph-header span');
            if (headerText) {
                headerText.innerHTML = 'Absatz #' + paraDiv.dataset.paragraphOrder +
                    ' | Zuletzt bearbeitet von ' + editorName;
            }
        }

        // Seite neu laden um aktuelle Reihenfolge und alle Buttons korrekt anzuzeigen
        location.reload();
    }

    // Neuen Absatz hinzufügen
    function addParagraph() {
        fetch('api/collab_text_add_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({text_id: TEXT_ID})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Neu laden um neuen Absatz anzuzeigen
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannt'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
        });
    }

    // Absatz löschen
    function deleteParagraph(paragraphId) {
        if (!confirm('Diesen Absatz wirklich löschen?')) {
            return;
        }

        fetch('api/collab_text_delete_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({paragraph_id: paragraphId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannt'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
        });
    }

    function swapParagraph(paragraphId, direction) {
        // Aktuellen Absatz finden
        const currentDiv = document.querySelector('[data-paragraph-id="' + paragraphId + '"]');
        if (!currentDiv) return;

        const currentOrder = parseInt(currentDiv.dataset.paragraphOrder);

        // Ziel-Absatz finden
        let targetDiv;
        if (direction === 'up') {
            // Vorherigen Absatz finden
            targetDiv = document.querySelector('[data-paragraph-order="' + (currentOrder - 1) + '"]');
        } else {
            // Nächsten Absatz finden
            targetDiv = document.querySelector('[data-paragraph-order="' + (currentOrder + 1) + '"]');
        }

        if (!targetDiv) {
            alert('Absatz kann nicht weiter ' + (direction === 'up' ? 'nach oben' : 'nach unten') + ' verschoben werden.');
            return;
        }

        const targetParagraphId = parseInt(targetDiv.dataset.paragraphId);

        // An Server senden
        fetch('api/collab_text_swap_paragraphs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                text_id: TEXT_ID,
                paragraph1_id: paragraphId,
                paragraph2_id: targetParagraphId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Seite neu laden um die neue Reihenfolge anzuzeigen
                location.reload();
            } else {
                alert('Fehler: ' + (data.error || 'Absätze konnten nicht vertauscht werden'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
        });
    }

    // Vorschau anzeigen
    function showPreview() {
        // Alle Absätze sammeln
        const paragraphs = Array.from(document.querySelectorAll('.paragraph-content'))
            .map(p => p.textContent.trim())
            .filter(t => t.length > 0);

        const previewContent = document.getElementById('previewContent');
        previewContent.textContent = paragraphs.join('\n\n');

        document.getElementById('previewDialog').style.display = 'flex';
    }

    function hidePreview() {
        document.getElementById('previewDialog').style.display = 'none';
    }

    // Text aus Vorschau in Zwischenablage kopieren
    function copyPreviewToClipboard() {
        const previewContent = document.getElementById('previewContent');
        const text = previewContent.textContent;

        navigator.clipboard.writeText(text).then(() => {
            alert('✅ Text wurde in die Zwischenablage kopiert!');
        }).catch(err => {
            console.error('Fehler beim Kopieren:', err);
            alert('❌ Fehler beim Kopieren in die Zwischenablage.');
        });
    }

    // Finalisieren
    function finalizeText() {
        document.getElementById('finalizeDialog').style.display = 'flex';
    }

    function hideFinalizeDialog() {
        document.getElementById('finalizeDialog').style.display = 'none';
    }

    function confirmFinalize() {
        const finalName = document.getElementById('finalNameInput').value.trim();

        if (!finalName) {
            alert('Bitte gib einen Namen für die finale Version ein.');
            return;
        }

        // Polling und Heartbeat stoppen (verhindert Endlosschleife)
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }

        fetch('api/collab_text_finalize.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                text_id: TEXT_ID,
                final_name: finalName
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Text erfolgreich finalisiert!');
                window.location.href = '?tab=texte&view=final&text_id=' + TEXT_ID;
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannt'));
                // Bei Fehler: Polling und Heartbeat wieder starten
                startPolling();
                startHeartbeat();
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
            // Bei Fehler: Polling und Heartbeat wieder starten
            startPolling();
            startHeartbeat();
        });
    }

    // Cleanup bei Seitenverlassen
    window.addEventListener('beforeunload', function() {
        if (pollingInterval) clearInterval(pollingInterval);
        if (heartbeatInterval) clearInterval(heartbeatInterval);
    });
    </script>

    <?php
    return;
}

//============================================================================
// FINAL: Anzeige finalisierter Texte
//============================================================================

if ($view === 'final') {
    // Text laden
    $stmt = $pdo->prepare("
        SELECT t.*,
               mt.meeting_name
        FROM svcollab_texts t
        LEFT JOIN svmeetings mt ON t.meeting_id = mt.meeting_id
        WHERE t.text_id = ?
    ");
    $stmt->execute([$text_id]);
    $text = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$text) {
        echo '<div class="alert alert-danger">Text nicht gefunden.</div>';
        return;
    }

    // Initiator-Namen über Adapter laden
    if ($text['initiator_member_id']) {
        $initiator = get_member_by_id($pdo, $text['initiator_member_id']);
        if ($initiator) {
            $text['initiator_first_name'] = $initiator['first_name'];
            $text['initiator_last_name'] = $initiator['last_name'];
        } else {
            $text['initiator_first_name'] = 'Unbekannt';
            $text['initiator_last_name'] = '';
        }
    } else {
        $text['initiator_first_name'] = '';
        $text['initiator_last_name'] = '';
    }

    // Zugriffsprüfung
    if ($text['meeting_id']) {
        if (!hasCollabTextAccess($pdo, $text_id, $current_user['member_id'])) {
            echo '<div class="alert alert-danger">Du hast keinen Zugriff auf diesen Text.</div>';
            return;
        }
    } else {
        // Allgemeiner Text: Nur Vorstand, GF, Assistenz, Führungsteam
        if (!in_array(strtolower($current_user['role']), ['vorstand', 'gf', 'assistenz', 'fuehrungsteam'])) {
            echo '<div class="alert alert-danger">Du hast keinen Zugriff auf diesen Text.</div>';
            return;
        }
    }

    if ($text['status'] !== 'finalized') {
        echo '<div class="alert alert-warning">Dieser Text ist noch nicht finalisiert.
              <a href="?tab=texte&view=editor&text_id=' . $text_id . '">Zum Editor</a></div>';
        return;
    }

    // Alle Absätze laden
    $stmt = $pdo->prepare("
        SELECT content
        FROM svcollab_text_paragraphs
        WHERE text_id = ?
        ORDER BY paragraph_order ASC
    ");
    $stmt->execute([$text_id]);
    $paragraphs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Jeden Absatz trimmen (Whitespace/Einrückung entfernen) und dann verbinden
    $contents = array_map('trim', array_column($paragraphs, 'content'));
    $full_text = implode("\n\n", $contents);
    ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2>📄 <?php echo htmlspecialchars($text['final_name'] ?: $text['title']); ?></h2>
            <p style="color: #666; font-size: 0.9em; margin: 5px 0 0 0;">
                <span class="collab-text-status status-finalized">✅ Finalisiert</span>
            </p>
        </div>
        <button onclick="window.location.href='?tab=texte&view=overview'" class="btn-secondary back-to-overview-btn">
            ← Zurück zur Übersicht
        </button>
    </div>

    <div style="background: #f8f9fa; border-left: 4px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0;">
            <strong>Erstellt von:</strong> <?php echo htmlspecialchars($text['initiator_first_name'] . ' ' . $text['initiator_last_name']); ?><br>
            <?php if ($text['meeting_name']): ?>
            <strong>Sitzung:</strong> <?php echo htmlspecialchars($text['meeting_name']); ?><br>
            <?php endif; ?>
            <strong>Finalisiert am:</strong> <?php echo date('d.m.Y H:i', strtotime($text['finalized_at'])); ?>
        </p>
    </div>

    <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
        <button onclick="copyToClipboard()" class="btn-primary">📋 In Zwischenablage kopieren</button>
        <button onclick="printText()" class="btn-secondary">🖨️ Drucken</button>
        <?php
        // Lösch-Button: Nur für Ersteller oder Admin
        $can_delete = ($text['initiator_member_id'] == $current_user['member_id']) || $current_user['is_admin'];
        if ($can_delete):
        ?>
            <button onclick="deleteTextFinal()" class="btn-danger">🗑️ Text löschen</button>
        <?php endif; ?>
    </div>

    <!-- Finaler Text -->
    <div id="finalTextContent" class="text-preview" style="background: white; border: 2px solid #28a745; white-space: pre-wrap;">
        <?php echo htmlspecialchars($full_text); ?>
    </div>

    <script>
    function copyToClipboard() {
        const text = document.getElementById('finalTextContent').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('Text wurde in die Zwischenablage kopiert!');
        }).catch(err => {
            console.error(err);
            alert('Fehler beim Kopieren');
        });
    }

    function printText() {
        window.print();
    }

    function deleteTextFinal() {
        const textId = <?php echo $text_id; ?>;
        const textTitle = '<?php echo htmlspecialchars($text['title'], ENT_QUOTES); ?>';

        if (!confirm('Möchtest du den Text "' + textTitle + '" wirklich löschen?\n\nDieser Vorgang kann nicht rückgängig gemacht werden!')) {
            return;
        }

        fetch('api/collab_text_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({text_id: textId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = '?tab=texte&view=overview';
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Fehler beim Löschen');
        });
    }
    </script>

    <?php
    return;
}

// Fallback
echo '<div class="alert alert-warning">Unbekannte Ansicht.</div>';

/**
 * Hilfsfunktion: Rendert einen einzelnen Absatz
 */
function renderParagraph($para, $current_member_id, $current_position = 1, $total_count = 1) {
    $is_locked = ($para['locked_by_member_id'] && $para['locked_by_member_id'] != $current_member_id);
    $is_own_lock = ($para['locked_by_member_id'] == $current_member_id);
    $is_first = ($current_position === 1);
    $is_last = ($current_position === $total_count);
    ?>
    <div class="paragraph-container <?php echo $is_locked ? 'locked' : ''; ?>"
         data-paragraph-id="<?php echo $para['paragraph_id']; ?>"
         data-paragraph-order="<?php echo $para['paragraph_order']; ?>">

        <div class="paragraph-header">
            <span style="color: #999; font-size: 0.85em;">
                Absatz #<?php echo $para['paragraph_order']; ?>
                <?php if ($para['last_edited_by']): ?>
                    | Zuletzt bearbeitet von <?php echo htmlspecialchars($para['editor_first_name'] . ' ' . $para['editor_last_name']); ?>
                <?php endif; ?>
            </span>
            <span class="paragraph-lock-info" style="color: #856404; font-size: 0.85em;">
                <?php if ($is_locked): ?>
                    <?php
                    $locker_name = ($para['locked_by_first_name'] && $para['locked_by_last_name'])
                        ? htmlspecialchars($para['locked_by_first_name'] . ' ' . $para['locked_by_last_name'])
                        : 'einem anderen Benutzer';
                    ?>
                    🔒 Wird bearbeitet von: <?php echo $locker_name; ?>
                <?php elseif ($is_own_lock): ?>
                    ✏️ Du bearbeitest gerade
                <?php endif; ?>
            </span>
        </div>

        <div class="paragraph-content"><?php echo htmlspecialchars($para['content']); ?></div>

        <div class="paragraph-actions">
            <?php if (!$is_locked): ?>
                <button onclick="editParagraph(<?php echo $para['paragraph_id']; ?>)" class="btn-primary">
                    ✏️ Bearbeiten
                </button>
                <button onclick="swapParagraph(<?php echo $para['paragraph_id']; ?>, 'up')" class="btn-secondary" title="Nach oben" <?php echo $is_first ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                    ↑
                </button>
                <button onclick="swapParagraph(<?php echo $para['paragraph_id']; ?>, 'down')" class="btn-secondary" title="Nach unten" <?php echo $is_last ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                    ↓
                </button>
                <button onclick="deleteParagraph(<?php echo $para['paragraph_id']; ?>)" class="btn-danger" style="margin-left: 10px;">
                    🗑️ Diesen Absatz löschen
                </button>
            <?php else: ?>
                <button disabled class="btn-secondary" style="opacity: 0.5;">
                    🔒 Gesperrt
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
