<?php
/**
 * tab_texte.php - Kollaborative Texte für Sitzungen
 * Erstellt: 02.12.2025
 *
 * Gemeinsames Arbeiten an Texten während Sitzungen
 * (z.B. Pressemeldungen, Briefe, etc.)
 */

require_once 'functions_collab_text.php';

// View-Parameter
$view = $_GET['view'] ?? 'overview';
$text_id = intval($_GET['text_id'] ?? 0);

// Prüfen ob eine Sitzung aktiv/ausgewählt ist
$meeting_id = $_SESSION['current_meeting_id'] ?? 0;

if ($meeting_id == 0) {
    echo '<div class="card">';
    echo '<h2>📝 Gemeinsame Texte</h2>';
    echo '<div class="alert alert-info">';
    echo '<p>Diese Funktion ist nur innerhalb einer Sitzung verfügbar.</p>';
    echo '<p>Bitte wählen Sie zuerst eine Sitzung aus dem Tab "Meetings" aus.</p>';
    echo '</div>';
    echo '</div>';
    return;
}

// Sitzungs-Daten laden
$stmt = $pdo->prepare("
    SELECT m.*,
           sec.first_name as secretary_first_name,
           sec.last_name as secretary_last_name
    FROM svmeetings m
    LEFT JOIN svmembers sec ON m.secretary_member_id = sec.member_id
    WHERE m.meeting_id = ?
");
$stmt->execute([$meeting_id]);
$meeting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
    echo '<div class="alert alert-danger">Sitzung nicht gefunden.</div>';
    return;
}

// Prüfen ob User Teilnehmer der Sitzung ist
$stmt = $pdo->prepare("
    SELECT COUNT(*) as is_participant
    FROM svmeeting_participants
    WHERE meeting_id = ? AND member_id = ?
");
$stmt->execute([$meeting_id, $current_user['member_id']]);
$is_participant = $stmt->fetch(PDO::FETCH_ASSOC)['is_participant'] > 0;

if (!$is_participant) {
    echo '<div class="alert alert-danger">Sie sind kein Teilnehmer dieser Sitzung.</div>';
    return;
}

// Ist aktueller User der Protokollant?
$is_secretary = ($meeting['secretary_member_id'] == $current_user['member_id']);

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
    padding: 15px;
    background: white;
    position: relative;
    transition: border-color 0.3s;
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
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #ddd;
}

.paragraph-content {
    min-height: 60px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.paragraph-edit-area {
    width: 100%;
    min-height: 150px;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-family: inherit;
    font-size: inherit;
    line-height: 1.6;
    resize: vertical;
}

.paragraph-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
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
</style>

<?php

//============================================================================
// OVERVIEW: Liste aller Texte der Sitzung
//============================================================================

if ($view === 'overview') {
    $all_texts = getCollabTextsByMeeting($pdo, $meeting_id);
    ?>

    <div class="card">
        <h2>📝 Gemeinsame Texte</h2>
        <p><strong>Sitzung:</strong> <?php echo htmlspecialchars($meeting['meeting_name']); ?></p>

        <div class="alert alert-info">
            <strong>ℹ️ Info:</strong> Hier können Sie gemeinsam an Texten arbeiten (z.B. Pressemeldungen, Briefe).
            Alle Sitzungsteilnehmer können gleichzeitig an verschiedenen Absätzen arbeiten.
        </div>

        <?php if ($is_secretary): ?>
        <button onclick="showCreateTextDialog()" class="btn-primary" style="margin-bottom: 20px;">
            + Neuen Text erstellen
        </button>
        <?php endif; ?>

        <?php if (empty($all_texts)): ?>
            <p style="color: #666; font-style: italic;">
                Noch keine gemeinsamen Texte für diese Sitzung.
                <?php if ($is_secretary): ?>
                Erstellen Sie den ersten Text mit dem Button oben.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="collab-text-list">
                <?php foreach ($all_texts as $text): ?>
                    <div class="collab-text-card">
                        <h3><?php echo htmlspecialchars($text['title']); ?></h3>

                        <p style="font-size: 0.9em; color: #666;">
                            Initiator: <?php echo htmlspecialchars($text['initiator_first_name'] . ' ' . $text['initiator_last_name']); ?>
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
                            <a href="?tab=texte&view=final&text_id=<?php echo $text['text_id']; ?>"
                               class="btn-secondary" style="width: 100%; text-align: center;">
                                📄 Ansehen
                            </a>
                        <?php else: ?>
                            <a href="?tab=texte&view=editor&text_id=<?php echo $text['text_id']; ?>"
                               class="btn-primary" style="width: 100%; text-align: center;">
                                ✏️ Bearbeiten
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Dialog: Neuen Text erstellen -->
    <div id="createTextDialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <h3>Neuen Text erstellen</h3>

            <label>Titel:</label>
            <input type="text" id="newTextTitle" placeholder="z.B. Pressemeldung Vereinsheim"
                   style="width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">

            <label>Initial-Text (optional):</label>
            <textarea id="newTextContent" placeholder="Optional: Ersten Absatz bereits eingeben..."
                      style="width: 100%; min-height: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>

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
            alert('Bitte geben Sie einen Titel ein.');
            return;
        }

        fetch('/api/collab_text_create.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                meeting_id: <?php echo $meeting_id; ?>,
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
            console.error(err);
            alert('Netzwerkfehler');
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
    if ($text_id <= 0) {
        echo '<div class="alert alert-danger">Ungültige Text-ID</div>';
        return;
    }

    $text = getCollabText($pdo, $text_id);

    if (!$text || $text['meeting_id'] != $meeting_id) {
        echo '<div class="alert alert-danger">Text nicht gefunden oder gehört nicht zu dieser Sitzung.</div>';
        return;
    }

    if ($text['status'] === 'finalized') {
        echo '<div class="alert alert-warning">Dieser Text ist bereits finalisiert und kann nicht mehr bearbeitet werden.</div>';
        echo '<a href="?tab=texte&view=final&text_id=' . $text_id . '" class="btn-primary">Finalen Text ansehen</a>';
        return;
    }

    $is_initiator = ($text['initiator_member_id'] == $current_user['member_id']);
    ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2>📝 <?php echo htmlspecialchars($text['title']); ?></h2>
                <p style="color: #666; margin: 5px 0;">
                    Initiator: <?php echo htmlspecialchars($text['initiator_first_name'] . ' ' . $text['initiator_last_name']); ?>
                </p>
            </div>
            <a href="?tab=texte" class="btn-secondary">← Zurück zur Übersicht</a>
        </div>

        <!-- Online-User-Anzeige -->
        <div class="online-users" id="onlineUsers">
            <strong>👥 Online:</strong>
            <div class="online-users-list" id="onlineUsersList">
                <span class="online-user-badge">Lädt...</span>
            </div>
        </div>

        <!-- Toolbar -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="addParagraph()" class="btn-secondary">+ Neuer Absatz</button>
            <button onclick="showPreview()" class="btn-secondary">👁️ Vorschau</button>
            <button onclick="createVersion()" class="btn-secondary">💾 Version speichern</button>
            <?php if ($is_initiator): ?>
            <button onclick="showFinalizeDialog()" class="btn-primary">✅ Arbeit beenden</button>
            <?php endif; ?>
        </div>

        <!-- Absätze -->
        <div id="paragraphsContainer">
            <?php foreach ($text['paragraphs'] as $para): ?>
                <div class="paragraph-container" id="para-<?php echo $para['paragraph_id']; ?>"
                     data-para-id="<?php echo $para['paragraph_id']; ?>"
                     data-order="<?php echo $para['paragraph_order']; ?>">

                    <div class="paragraph-header">
                        <span style="font-weight: bold; color: #666;">Absatz <?php echo $para['paragraph_order']; ?></span>
                        <span class="para-lock-status" id="lock-status-<?php echo $para['paragraph_id']; ?>">
                            <?php if ($para['locked_by_member_id']): ?>
                                🔒 <?php echo htmlspecialchars($para['locked_by_first_name']); ?> bearbeitet
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="paragraph-view" id="view-<?php echo $para['paragraph_id']; ?>">
                        <div class="paragraph-content">
                            <?php echo nl2br(htmlspecialchars($para['content'])); ?>
                        </div>
                        <div class="paragraph-actions" style="margin-top: 10px;">
                            <button onclick="editParagraph(<?php echo $para['paragraph_id']; ?>)" class="btn-primary">
                                ✏️ Bearbeiten
                            </button>
                            <?php if (count($text['paragraphs']) > 1): ?>
                            <button onclick="deleteParagraph(<?php echo $para['paragraph_id']; ?>)" class="btn-danger">
                                🗑️ Löschen
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($para['last_edited_by']): ?>
                        <p style="font-size: 0.8em; color: #999; margin-top: 8px;">
                            Zuletzt bearbeitet: <?php echo htmlspecialchars($para['editor_first_name']); ?>
                            am <?php echo date('d.m.Y H:i', strtotime($para['last_edited_at'])); ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <div class="paragraph-edit" id="edit-<?php echo $para['paragraph_id']; ?>" style="display: none;">
                        <textarea class="paragraph-edit-area" id="content-<?php echo $para['paragraph_id']; ?>">
<?php echo htmlspecialchars($para['content']); ?>
                        </textarea>
                        <div class="paragraph-actions" style="margin-top: 10px;">
                            <button onclick="saveParagraph(<?php echo $para['paragraph_id']; ?>)" class="btn-success">
                                💾 Speichern
                            </button>
                            <button onclick="cancelEdit(<?php echo $para['paragraph_id']; ?>)" class="btn-secondary">
                                ❌ Abbrechen
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Vorschau-Dialog -->
    <div id="previewDialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto; margin: 20px;">
            <h3>👁️ Vorschau: <?php echo htmlspecialchars($text['title']); ?></h3>
            <div id="previewContent" class="text-preview"></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="copyToClipboard()" class="btn-secondary">📋 Text kopieren</button>
                <button onclick="hidePreview()" class="btn-secondary">Schließen</button>
            </div>
        </div>
    </div>

    <!-- Finalisieren-Dialog -->
    <div id="finalizeDialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <h3>Arbeit beenden?</h3>
            <p>Der Text wird finalisiert und kann danach nicht mehr bearbeitet werden.</p>
            <p>Alle Teilnehmer können den finalen Text ansehen und kopieren.</p>

            <label>Name für Archiv:</label>
            <input type="text" id="finalName"
                   value="<?php echo htmlspecialchars($text['title'] . ' - ' . date('d.m.Y')); ?>"
                   style="width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="finalizeText()" class="btn-primary">✅ Beenden</button>
                <button onclick="hideFinalizeDialog()" class="btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>

    <script>
    const textId = <?php echo $text_id; ?>;
    let lastUpdate = new Date().toISOString();
    let currentlyEditingPara = null;

    // Polling für Updates (alle 1500ms)
    setInterval(pollUpdates, 1500);

    // Heartbeat (alle 10 Sekunden)
    setInterval(sendHeartbeat, 10000);

    // Initial
    pollUpdates();
    sendHeartbeat();

    function pollUpdates() {
        fetch(`/api/collab_text_get_updates.php?text_id=${textId}&since=${encodeURIComponent(lastUpdate)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Absätze aktualisieren (nur die, die NICHT gerade editiert werden)
                    if (data.paragraphs && data.paragraphs.length > 0) {
                        data.paragraphs.forEach(updateParagraphInDOM);
                    }

                    // Online-User aktualisieren
                    updateOnlineUsers(data.online_users);

                    // Server-Zeit als neue lastUpdate
                    lastUpdate = data.server_time;

                    // Wenn Text finalized wurde
                    if (data.text_status === 'finalized') {
                        window.location.href = '?tab=texte&view=final&text_id=' + textId;
                    }
                }
            })
            .catch(err => console.error('Polling error:', err));
    }

    function updateParagraphInDOM(para) {
        // Wenn dieser Absatz gerade von uns editiert wird → überspringen
        if (currentlyEditingPara == para.paragraph_id) {
            return;
        }

        const paraDiv = document.getElementById('para-' + para.paragraph_id);
        if (!paraDiv) return; // Neuer Absatz → Seite neu laden

        // Content aktualisieren
        const viewDiv = document.getElementById('view-' + para.paragraph_id);
        const contentDiv = viewDiv.querySelector('.paragraph-content');
        contentDiv.innerHTML = para.content.replace(/\n/g, '<br>');

        // Lock-Status aktualisieren
        const lockStatus = document.getElementById('lock-status-' + para.paragraph_id);
        if (para.locked_by_member_id) {
            lockStatus.textContent = '🔒 ' + para.locked_by_first_name + ' bearbeitet';
            paraDiv.classList.add('locked');
        } else {
            lockStatus.textContent = '';
            paraDiv.classList.remove('locked');
        }
    }

    function updateOnlineUsers(users) {
        const listDiv = document.getElementById('onlineUsersList');
        if (!users || users.length === 0) {
            listDiv.innerHTML = '<span style="color: #999;">Niemand online</span>';
            return;
        }

        listDiv.innerHTML = users.map(u =>
            `<span class="online-user-badge">${u.first_name} ${u.last_name}</span>`
        ).join('');
    }

    function sendHeartbeat() {
        fetch('/api/collab_text_heartbeat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ text_id: textId })
        }).catch(err => console.error('Heartbeat error:', err));
    }

    function editParagraph(paraId) {
        // Lock versuchen
        fetch('/api/collab_text_lock_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ paragraph_id: paraId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Editier-Modus aktivieren
                document.getElementById('view-' + paraId).style.display = 'none';
                document.getElementById('edit-' + paraId).style.display = 'block';
                document.getElementById('para-' + paraId).classList.add('editing');
                currentlyEditingPara = paraId;

                // Textarea fokussieren
                document.getElementById('content-' + paraId).focus();
            } else {
                alert('Absatz wird gerade von ' + (data.locked_by || 'jemand anderem') + ' bearbeitet.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Fehler beim Sperren des Absatzes');
        });
    }

    function saveParagraph(paraId) {
        const content = document.getElementById('content-' + paraId).value;

        fetch('/api/collab_text_save_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                paragraph_id: paraId,
                content: content
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Editier-Modus beenden
                document.getElementById('view-' + paraId).style.display = 'block';
                document.getElementById('edit-' + paraId).style.display = 'none';
                document.getElementById('para-' + paraId).classList.remove('editing');
                currentlyEditingPara = null;

                // Sofort Update holen
                lastUpdate = new Date(0).toISOString(); // Force full reload
                pollUpdates();
            } else {
                alert('Fehler beim Speichern: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler beim Speichern');
        });
    }

    function cancelEdit(paraId) {
        // Lock freigeben (durch erneutes Polling passiert automatisch nach 2 Min, aber wir können auch manuell freigeben)
        document.getElementById('view-' + paraId).style.display = 'block';
        document.getElementById('edit-' + paraId).style.display = 'none';
        document.getElementById('para-' + paraId).classList.remove('editing');
        currentlyEditingPara = null;
    }

    function addParagraph() {
        fetch('/api/collab_text_add_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ text_id: textId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Seite neu laden um neuen Absatz anzuzeigen
                window.location.reload();
            } else {
                alert('Fehler beim Hinzufügen des Absatzes');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
        });
    }

    function deleteParagraph(paraId) {
        if (!confirm('Absatz wirklich löschen?')) return;

        fetch('/api/collab_text_delete_paragraph.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ paragraph_id: paraId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
        });
    }

    function showPreview() {
        // Alle Absätze zusammensammeln
        const paras = Array.from(document.querySelectorAll('.paragraph-content'))
            .map(div => div.textContent.trim())
            .filter(text => text.length > 0);

        document.getElementById('previewContent').textContent = paras.join('\n\n');
        document.getElementById('previewDialog').style.display = 'flex';
    }

    function hidePreview() {
        document.getElementById('previewDialog').style.display = 'none';
    }

    function copyToClipboard() {
        const text = document.getElementById('previewContent').textContent;
        navigator.clipboard.writeText(text).then(() => {
            alert('Text in Zwischenablage kopiert!');
        }).catch(err => {
            console.error(err);
            alert('Fehler beim Kopieren');
        });
    }

    function showFinalizeDialog() {
        document.getElementById('finalizeDialog').style.display = 'flex';
    }

    function hideFinalizeDialog() {
        document.getElementById('finalizeDialog').style.display = 'none';
    }

    function finalizeText() {
        const finalName = document.getElementById('finalName').value.trim();

        if (!finalName) {
            alert('Bitte geben Sie einen Namen ein.');
            return;
        }

        if (!confirm('Text wirklich finalisieren? Dies kann nicht rückgängig gemacht werden.')) {
            return;
        }

        fetch('/api/collab_text_finalize.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                text_id: textId,
                final_name: finalName
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Text wurde finalisiert!');
                window.location.href = '?tab=texte&view=final&text_id=' + textId;
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
        });
    }

    function createVersion() {
        const note = prompt('Optionale Notiz für diese Version:', 'Zwischenspeicherung');
        if (note === null) return; // Cancel gedrückt

        fetch('/api/collab_text_create_version.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                text_id: textId,
                note: note
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Version ' + data.version_number + ' gespeichert!');
            } else {
                alert('Fehler beim Speichern der Version');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Netzwerkfehler');
        });
    }
    </script>

    <?php
    return;
}

//============================================================================
// FINAL: Ansicht des finalisierten Textes
//============================================================================

if ($view === 'final') {
    if ($text_id <= 0) {
        echo '<div class="alert alert-danger">Ungültige Text-ID</div>';
        return;
    }

    $text = getCollabText($pdo, $text_id);

    if (!$text || $text['meeting_id'] != $meeting_id) {
        echo '<div class="alert alert-danger">Text nicht gefunden.</div>';
        return;
    }

    if ($text['status'] !== 'finalized') {
        echo '<div class="alert alert-warning">Text ist noch nicht finalisiert.</div>';
        echo '<a href="?tab=texte&view=editor&text_id=' . $text_id . '" class="btn-primary">Zum Editor</a>';
        return;
    }

    // Alle Absätze zu einem Text zusammenfügen
    $full_content = implode("\n\n", array_column($text['paragraphs'], 'content'));
    ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2>📄 <?php echo htmlspecialchars($text['final_name'] ?? $text['title']); ?></h2>
                <p style="color: #666;">
                    <span class="collab-text-status status-finalized">✅ Finalisiert</span>
                    am <?php echo date('d.m.Y H:i', strtotime($text['finalized_at'])); ?>
                </p>
            </div>
            <a href="?tab=texte" class="btn-secondary">← Zurück zur Übersicht</a>
        </div>

        <div class="text-preview" id="finalText">
            <?php echo nl2br(htmlspecialchars($full_content)); ?>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button onclick="copyFinalText()" class="btn-primary">📋 Text kopieren</button>
        </div>
    </div>

    <script>
    function copyFinalText() {
        const text = document.getElementById('finalText').textContent;
        navigator.clipboard.writeText(text).then(() => {
            alert('Text in Zwischenablage kopiert!');
        }).catch(err => {
            console.error(err);
            alert('Fehler beim Kopieren');
        });
    }
    </script>

    <?php
    return;
}
