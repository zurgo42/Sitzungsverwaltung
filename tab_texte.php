<?php
/**
 * tab_texte.php - Kollaborative Texte
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
               sec.first_name as secretary_first_name,
               sec.last_name as secretary_last_name,
               mp.member_id as is_participant
        FROM svmeetings m
        LEFT JOIN svmembers sec ON m.secretary_member_id = sec.member_id
        LEFT JOIN svmeeting_participants mp ON m.meeting_id = mp.meeting_id AND mp.member_id = ?
        WHERE m.meeting_id = ?
    ");
    $stmt->execute([$current_user['member_id'], $meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($meeting && $meeting['is_participant']) {
        $has_access = true;
        $is_initiator_role = ($meeting['secretary_member_id'] == $current_user['member_id']);
        $context_description = 'Sitzung: ' . htmlspecialchars($meeting['meeting_name']);
    }
} else {
    // ALLGEMEIN-MODUS: Nur Vorstand, GF, Assistenz
    if (in_array($current_user['role'], ['vorstand', 'gf', 'assistenz'])) {
        $has_access = true;
        $is_initiator_role = true; // Alle dürfen Texte erstellen
        $context_description = 'Allgemeine Texte (Vorstand/GF/Assistenz)';
    }
}

// Zugriff verweigert
if (!$has_access) {
    echo '<div class="card">';
    echo '<h2>📝 Gemeinsame Texte</h2>';
    echo '<div class="alert alert-danger">';
    if ($is_meeting_mode) {
        echo '<p>Sie sind kein Teilnehmer dieser Sitzung.</p>';
    } else {
        echo '<p>Diese Funktion steht nur Vorstand, Geschäftsführung und Assistenz zur Verfügung.</p>';
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
// OVERVIEW: Liste aller Texte
//============================================================================

if ($view === 'overview') {
    // Texte laden
    if ($is_meeting_mode) {
        $all_texts = getCollabTextsByMeeting($pdo, $meeting_id);
    } else {
        // Allgemeine Texte (meeting_id = NULL)
        $stmt = $pdo->prepare("
            SELECT t.*,
                   m.first_name as initiator_first_name,
                   m.last_name as initiator_last_name,
                   COUNT(DISTINCT p.member_id) as participant_count
            FROM svcollab_texts t
            JOIN svmembers m ON t.initiator_member_id = m.member_id
            LEFT JOIN svcollab_text_participants p ON t.text_id = p.text_id
            WHERE t.meeting_id IS NULL
            GROUP BY t.text_id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        $all_texts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    ?>

    <div class="card">
        <h2>📝 Gemeinsame Texte</h2>
        <p><strong>Kontext:</strong> <?php echo $context_description; ?></p>

        <div class="alert alert-info">
            <strong>ℹ️ Info:</strong> Hier können Sie gemeinsam an Texten arbeiten (z.B. Pressemeldungen, Briefe).
            <?php if ($is_meeting_mode): ?>
            Alle Sitzungsteilnehmer können gleichzeitig an verschiedenen Absätzen arbeiten.
            <?php else: ?>
            Alle Vorstandsmitglieder, GF und Assistenz können gemeinsam arbeiten.
            <?php endif; ?>
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
                Erstellen Sie den ersten Text mit dem Button oben.
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
            console.error(err);
            alert('Netzwerkfehler');
        });
    }
    </script>

    <?php
    return;
}

// TODO: Editor und Final-Views werden in der nächsten Version hinzugefügt
echo '<div class="alert alert-warning">Editor-Ansicht folgt in Kürze...</div>';
