<?php
/**
 * tab_termine.php - Terminplanung/Umfragen (Präsentation)
 * Erstellt: 17.11.2025
 *
 * Zeigt Terminumfragen und Abstimmungen
 * Nur Darstellung - alle Verarbeitungen in process_termine.php
 */

// View-Parameter
$view = $_GET['view'] ?? 'dashboard';
$poll_id = intval($_GET['poll_id'] ?? 0);

// Alle Umfragen laden
$stmt = $pdo->prepare("
    SELECT p.*,
           m.first_name as creator_first_name,
           m.last_name as creator_last_name,
           COUNT(DISTINCT pd.date_id) as date_count,
           COUNT(DISTINCT pr.member_id) as response_count
    FROM polls p
    LEFT JOIN members m ON p.created_by_member_id = m.member_id
    LEFT JOIN poll_dates pd ON p.poll_id = pd.poll_id
    LEFT JOIN poll_responses pr ON p.poll_id = pr.poll_id
    GROUP BY p.poll_id
    ORDER BY p.created_at DESC
");
$stmt->execute();
$all_polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Meetings für Dropdown laden
$all_meetings = get_visible_meetings($pdo, $current_user['member_id']);
$all_members = get_all_members($pdo);
?>

<style>
/* Poll-spezifische Styles */
.poll-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
}

.poll-card.status-open {
    border-left: 4px solid #4CAF50;
}

.poll-card.status-closed {
    border-left: 4px solid #FF9800;
}

.poll-card.status-finalized {
    border-left: 4px solid #2196F3;
}

.poll-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.poll-title {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.poll-status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.poll-status-badge.status-open {
    background: #4CAF50;
    color: white;
}

.poll-status-badge.status-closed {
    background: #FF9800;
    color: white;
}

.poll-status-badge.status-finalized {
    background: #2196F3;
    color: white;
}

.poll-meta {
    color: #666;
    font-size: 14px;
    margin-bottom: 10px;
}

.poll-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.date-suggestions-grid {
    display: grid;
    grid-template-columns: 150px 80px 80px 200px auto 60px;
    gap: 10px;
    align-items: center;
    margin-bottom: 10px;
}

.date-suggestions-grid.header {
    font-weight: bold;
    border-bottom: 2px solid #ddd;
    padding-bottom: 5px;
}

.vote-matrix {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

.vote-matrix th,
.vote-matrix td {
    padding: 10px;
    text-align: left;
    border: 1px solid #ddd;
}

.vote-matrix th {
    background: #f5f5f5;
    font-weight: bold;
}

.vote-matrix tr:hover {
    background: #fafafa;
}

.vote-buttons {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.vote-btn {
    border: 2px solid #ddd;
    background: white;
    padding: 8px 12px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 16px;
    transition: all 0.2s;
}

.vote-btn:hover {
    transform: scale(1.1);
}

.vote-btn.selected {
    border-width: 3px;
}

.vote-btn.vote-yes.selected {
    background: #4CAF50;
    border-color: #2E7D32;
    color: white;
}

.vote-btn.vote-maybe.selected {
    background: #FFC107;
    border-color: #F57C00;
    color: white;
}

.vote-btn.vote-no.selected {
    background: #f44336;
    border-color: #C62828;
    color: white;
}

.vote-summary {
    font-size: 12px;
    color: #666;
}

.vote-summary .count-yes {
    color: #4CAF50;
    font-weight: bold;
}

.vote-summary .count-maybe {
    color: #FF9800;
    font-weight: bold;
}

.vote-summary .count-no {
    color: #f44336;
    font-weight: bold;
}

.final-date-highlight {
    background: #E3F2FD !important;
    border: 2px solid #2196F3 !important;
}

.accordion-button {
    width: 100%;
    text-align: left;
    padding: 12px 15px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    border-radius: 5px;
    transition: background 0.3s;
}

.accordion-button:hover {
    background: #e0e0e0;
}

.accordion-content {
    display: none;
    padding: 20px;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 5px 5px;
    background: white;
}

.accordion-content.active {
    display: block;
}
</style>

<script>
// Accordion Toggle
function toggleAccordion(button) {
    const content = button.nextElementSibling;
    const isActive = content.classList.contains('active');

    // Alle Accordions schließen
    document.querySelectorAll('.accordion-content').forEach(c => c.classList.remove('active'));

    // Aktuelles öffnen/schließen
    if (!isActive) {
        content.classList.add('active');
    }
}

// Vote Button Selection
function selectVote(button, dateId, voteValue) {
    // Alle Buttons für dieses Datum deaktivieren
    const row = button.closest('tr');
    row.querySelectorAll('.vote-btn').forEach(btn => btn.classList.remove('selected'));

    // Aktuellen Button aktivieren
    button.classList.add('selected');

    // Hidden Input setzen
    const input = document.getElementById('vote_' + dateId);
    if (input) {
        input.value = voteValue;
    }
}

// Add More Date Suggestions
let dateCount = 5; // Initial anzuzeigende Felder
function addMoreDates() {
    const container = document.getElementById('date-suggestions-container');
    const maxDates = 20;

    if (dateCount >= maxDates) {
        alert('Maximal ' + maxDates + ' Terminvorschläge möglich');
        return;
    }

    dateCount++;
    const newRow = document.createElement('div');
    newRow.className = 'date-suggestions-grid';
    newRow.innerHTML = `
        <input type="date" name="date_${dateCount}" placeholder="Datum">
        <input type="time" name="time_start_${dateCount}" placeholder="Beginn">
        <input type="time" name="time_end_${dateCount}" placeholder="Ende">
        <input type="text" name="location_${dateCount}" placeholder="Ort (optional)">
        <input type="text" name="notes_${dateCount}" placeholder="Notiz (optional)">
        <button type="button" onclick="this.parentElement.remove(); dateCount--;" style="background: #f44336; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px;">×</button>
    `;
    container.appendChild(newRow);
}

// Auto-set date field on change
function updateDateSuggestions() {
    // Optional: Auto-fill logic
}
</script>

<h2>📅 Terminplanung & Umfragen</h2>

<?php
// Success/Error Messages
if (isset($_SESSION['success'])) {
    echo '<div class="message">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="error-message">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>

<?php if ($view === 'dashboard'): ?>
    <!-- DASHBOARD VIEW -->

    <!-- Neue Umfrage erstellen -->
    <div style="margin-bottom: 30px;">
        <button class="accordion-button" onclick="toggleAccordion(this)">➕ Neue Terminumfrage erstellen</button>
        <div class="accordion-content">
            <form method="POST" action="process_termine.php">
                <input type="hidden" name="action" value="create_poll">

                <div class="form-group">
                    <label>Titel der Umfrage:*</label>
                    <input type="text" name="title" required placeholder="z.B. Vorstandssitzung April 2025">
                </div>

                <div class="form-group">
                    <label>Beschreibung:</label>
                    <textarea name="description" rows="3" placeholder="Optional: Weitere Informationen zur Umfrage"></textarea>
                </div>

                <div class="form-group">
                    <label>Mit Meeting verknüpfen (optional):</label>
                    <select name="meeting_id">
                        <option value="">- Kein Meeting -</option>
                        <?php foreach ($all_meetings as $meeting): ?>
                            <option value="<?php echo $meeting['meeting_id']; ?>">
                                <?php echo htmlspecialchars($meeting['meeting_name'] . ' - ' . date('d.m.Y H:i', strtotime($meeting['meeting_date']))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Optional: Wenn Sie ein bestehendes Meeting auswählen, wird die Umfrage damit verknüpft
                    </small>
                </div>

                <div class="form-group">
                    <label><strong>Terminvorschläge:</strong></label>
                    <div class="date-suggestions-grid header">
                        <div>Datum</div>
                        <div>Beginn</div>
                        <div>Ende</div>
                        <div>Ort</div>
                        <div>Notiz</div>
                        <div></div>
                    </div>

                    <div id="date-suggestions-container">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="date-suggestions-grid">
                            <input type="date" name="date_<?php echo $i; ?>" placeholder="Datum">
                            <input type="time" name="time_start_<?php echo $i; ?>" placeholder="Beginn">
                            <input type="time" name="time_end_<?php echo $i; ?>" placeholder="Ende">
                            <input type="text" name="location_<?php echo $i; ?>" placeholder="Ort (optional)">
                            <input type="text" name="notes_<?php echo $i; ?>" placeholder="Notiz (optional)">
                            <span></span>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <button type="button" onclick="addMoreDates()" class="btn-secondary" style="margin-top: 10px;">+ Weiteren Termin hinzufügen</button>
                    <small style="display: block; margin-top: 10px; color: #666;">
                        Sie können bis zu 20 Terminvorschläge hinzufügen
                    </small>
                </div>

                <button type="submit">Umfrage erstellen</button>
            </form>
        </div>
    </div>

    <!-- Umfragen-Liste -->
    <h3>Bestehende Umfragen</h3>

    <?php if (empty($all_polls)): ?>
        <div class="info-box">Noch keine Umfragen vorhanden.</div>
    <?php else: ?>
        <?php foreach ($all_polls as $poll):
            $is_creator = ($poll['created_by_member_id'] == $current_user['member_id']);
            $is_admin = in_array($current_user['role'], ['assistenz', 'gf']);
            $can_edit = $is_creator || $is_admin;
        ?>
            <div class="poll-card status-<?php echo $poll['status']; ?>">
                <div class="poll-header">
                    <h3 class="poll-title"><?php echo htmlspecialchars($poll['title']); ?></h3>
                    <span class="poll-status-badge status-<?php echo $poll['status']; ?>">
                        <?php
                        switch ($poll['status']) {
                            case 'open': echo '🟢 Offen'; break;
                            case 'closed': echo '🟠 Geschlossen'; break;
                            case 'finalized': echo '🔵 Abgeschlossen'; break;
                        }
                        ?>
                    </span>
                </div>

                <div class="poll-meta">
                    <?php if (!empty($poll['description'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($poll['description'])); ?></p>
                    <?php endif; ?>

                    <p>
                        📊 <strong><?php echo $poll['date_count']; ?></strong> Terminvorschläge ·
                        👥 <strong><?php echo $poll['response_count']; ?></strong> Teilnehmer abgestimmt ·
                        👤 Erstellt von <strong><?php echo htmlspecialchars($poll['creator_first_name'] . ' ' . $poll['creator_last_name']); ?></strong> ·
                        📅 <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?>
                    </p>

                    <?php if ($poll['status'] === 'finalized' && !empty($poll['finalized_at'])): ?>
                        <p style="color: #2196F3; font-weight: bold;">
                            ✓ Finaler Termin festgelegt am <?php echo date('d.m.Y H:i', strtotime($poll['finalized_at'])); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="poll-actions">
                    <a href="?tab=termine&view=poll&poll_id=<?php echo $poll['poll_id']; ?>" class="btn-primary">
                        <?php echo $poll['status'] === 'open' ? '📝 Abstimmen' : '📊 Ergebnisse ansehen'; ?>
                    </a>

                    <?php if ($can_edit): ?>
                        <?php if ($poll['status'] === 'open'): ?>
                            <form method="POST" action="process_termine.php" style="display: inline;">
                                <input type="hidden" name="action" value="close_poll">
                                <input type="hidden" name="poll_id" value="<?php echo $poll['poll_id']; ?>">
                                <button type="submit" class="btn-secondary" onclick="return confirm('Umfrage schließen?')">🔒 Schließen</button>
                            </form>
                        <?php elseif ($poll['status'] === 'closed'): ?>
                            <form method="POST" action="process_termine.php" style="display: inline;">
                                <input type="hidden" name="action" value="reopen_poll">
                                <input type="hidden" name="poll_id" value="<?php echo $poll['poll_id']; ?>">
                                <button type="submit" class="btn-secondary">🔓 Wieder öffnen</button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" action="process_termine.php" style="display: inline;">
                            <input type="hidden" name="action" value="delete_poll">
                            <input type="hidden" name="poll_id" value="<?php echo $poll['poll_id']; ?>">
                            <button type="submit" class="btn-danger" onclick="return confirm('Umfrage wirklich löschen? Alle Abstimmungen gehen verloren!')">🗑️ Löschen</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php elseif ($view === 'poll' && $poll_id > 0): ?>
    <!-- POLL DETAIL VIEW -->

    <?php
    // Poll-Daten laden
    $stmt = $pdo->prepare("
        SELECT p.*,
               m.first_name as creator_first_name,
               m.last_name as creator_last_name
        FROM polls p
        LEFT JOIN members m ON p.created_by_member_id = m.member_id
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        echo '<div class="error-message">Umfrage nicht gefunden</div>';
        echo '<a href="?tab=termine" class="btn-secondary">← Zurück zur Übersicht</a>';
    } else {
        // Terminvorschläge laden
        $stmt = $pdo->prepare("
            SELECT * FROM poll_dates
            WHERE poll_id = ?
            ORDER BY sort_order, suggested_date
        ");
        $stmt->execute([$poll_id]);
        $poll_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Alle Antworten laden
        $stmt = $pdo->prepare("
            SELECT pr.*, m.first_name, m.last_name
            FROM poll_responses pr
            LEFT JOIN members m ON pr.member_id = m.member_id
            WHERE pr.poll_id = ?
        ");
        $stmt->execute([$poll_id]);
        $all_responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // User's aktuelle Antworten laden
        $stmt = $pdo->prepare("
            SELECT date_id, vote
            FROM poll_responses
            WHERE poll_id = ? AND member_id = ?
        ");
        $stmt->execute([$poll_id, $current_user['member_id']]);
        $user_votes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $user_votes[$row['date_id']] = $row['vote'];
        }

        // Berechtigungen
        $is_creator = ($poll['created_by_member_id'] == $current_user['member_id']);
        $is_admin = in_array($current_user['role'], ['assistenz', 'gf']);
        $can_edit = $is_creator || $is_admin;
        $can_vote = $poll['status'] === 'open';
    ?>

        <a href="?tab=termine" class="btn-secondary" style="margin-bottom: 20px;">← Zurück zur Übersicht</a>

        <div class="poll-card status-<?php echo $poll['status']; ?>" style="margin-bottom: 20px;">
            <div class="poll-header">
                <h2 class="poll-title"><?php echo htmlspecialchars($poll['title']); ?></h2>
                <span class="poll-status-badge status-<?php echo $poll['status']; ?>">
                    <?php
                    switch ($poll['status']) {
                        case 'open': echo '🟢 Offen'; break;
                        case 'closed': echo '🟠 Geschlossen'; break;
                        case 'finalized': echo '🔵 Abgeschlossen'; break;
                    }
                    ?>
                </span>
            </div>

            <div class="poll-meta">
                <?php if (!empty($poll['description'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($poll['description'])); ?></p>
                <?php endif; ?>

                <p>
                    👤 Erstellt von <strong><?php echo htmlspecialchars($poll['creator_first_name'] . ' ' . $poll['creator_last_name']); ?></strong> ·
                    📅 <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?>
                </p>

                <?php if ($poll['status'] === 'finalized' && !empty($poll['finalized_at'])): ?>
                    <p style="color: #2196F3; font-weight: bold;">
                        ✓ Finaler Termin festgelegt am <?php echo date('d.m.Y H:i', strtotime($poll['finalized_at'])); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Abstimmungs-Formular -->
        <?php if ($can_vote): ?>
            <h3>📝 Ihre Abstimmung</h3>
            <p>Bitte geben Sie für jeden Terminvorschlag an, ob der Termin für Sie passt:</p>
            <ul style="margin-bottom: 20px;">
                <li><strong>✅ Passt:</strong> Dieser Termin ist ideal für mich</li>
                <li><strong>🟡 Geht zur Not:</strong> Ich könnte, wenn es unbedingt sein muss</li>
                <li><strong>❌ Passt nicht:</strong> Dieser Termin ist für mich unmöglich</li>
            </ul>

            <form method="POST" action="process_termine.php">
                <input type="hidden" name="action" value="submit_vote">
                <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">

                <table class="vote-matrix">
                    <thead>
                        <tr>
                            <th>Terminvorschlag</th>
                            <th>Ort</th>
                            <th style="text-align: center; width: 300px;">Ihre Wahl</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poll_dates as $date):
                            $user_vote = $user_votes[$date['date_id']] ?? null;
                            $date_str = date('D, d.m.Y', strtotime($date['suggested_date']));
                            $time_str = date('H:i', strtotime($date['suggested_date']));
                            if (!empty($date['suggested_end_date'])) {
                                $time_str .= ' - ' . date('H:i', strtotime($date['suggested_end_date']));
                            }
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo $date_str; ?></strong><br>
                                    <span style="color: #666;"><?php echo $time_str; ?></span>
                                    <?php if (!empty($date['notes'])): ?>
                                        <br><small style="color: #999;">ℹ️ <?php echo htmlspecialchars($date['notes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($date['location'] ?? '-'); ?></td>
                                <td>
                                    <input type="hidden" name="vote_<?php echo $date['date_id']; ?>" id="vote_<?php echo $date['date_id']; ?>" value="<?php echo $user_vote ?? 0; ?>">
                                    <div class="vote-buttons">
                                        <button type="button"
                                                class="vote-btn vote-yes <?php echo $user_vote === 1 ? 'selected' : ''; ?>"
                                                onclick="selectVote(this, <?php echo $date['date_id']; ?>, 1)">
                                            ✅ Passt
                                        </button>
                                        <button type="button"
                                                class="vote-btn vote-maybe <?php echo $user_vote === 0 ? 'selected' : ''; ?>"
                                                onclick="selectVote(this, <?php echo $date['date_id']; ?>, 0)">
                                            🟡 Muss
                                        </button>
                                        <button type="button"
                                                class="vote-btn vote-no <?php echo $user_vote === -1 ? 'selected' : ''; ?>"
                                                onclick="selectVote(this, <?php echo $date['date_id']; ?>, -1)">
                                            ❌ Passt nicht
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="submit" class="btn-primary" style="font-size: 16px; padding: 12px 24px;">💾 Abstimmung speichern</button>
            </form>
        <?php else: ?>
            <div class="info-box">Diese Umfrage ist geschlossen. Sie können nicht mehr abstimmen.</div>
        <?php endif; ?>

        <!-- Ergebnisse anzeigen -->
        <h3 style="margin-top: 40px;">📊 Abstimmungsergebnisse</h3>

        <table class="vote-matrix">
            <thead>
                <tr>
                    <th>Terminvorschlag</th>
                    <th>Ort</th>
                    <th style="text-align: center;">Zusammenfassung</th>
                    <?php
                    // Alle Teilnehmer die abgestimmt haben
                    $participants = [];
                    foreach ($all_responses as $resp) {
                        if (!isset($participants[$resp['member_id']])) {
                            $participants[$resp['member_id']] = $resp['first_name'] . ' ' . substr($resp['last_name'], 0, 1) . '.';
                        }
                    }
                    foreach ($participants as $name): ?>
                        <th style="text-align: center; font-size: 12px;"><?php echo htmlspecialchars($name); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($poll_dates as $date):
                    $is_final = ($poll['final_date_id'] == $date['date_id']);
                    $date_str = date('D, d.m.Y', strtotime($date['suggested_date']));
                    $time_str = date('H:i', strtotime($date['suggested_date']));
                    if (!empty($date['suggested_end_date'])) {
                        $time_str .= ' - ' . date('H:i', strtotime($date['suggested_end_date']));
                    }

                    // Votes zählen für diesen Termin
                    $count_yes = 0;
                    $count_maybe = 0;
                    $count_no = 0;
                    $votes_by_member = [];

                    foreach ($all_responses as $resp) {
                        if ($resp['date_id'] == $date['date_id']) {
                            $votes_by_member[$resp['member_id']] = $resp['vote'];
                            if ($resp['vote'] == 1) $count_yes++;
                            elseif ($resp['vote'] == 0) $count_maybe++;
                            elseif ($resp['vote'] == -1) $count_no++;
                        }
                    }
                ?>
                    <tr class="<?php echo $is_final ? 'final-date-highlight' : ''; ?>">
                        <td>
                            <?php if ($is_final): ?>
                                <span style="color: #2196F3; font-weight: bold;">⭐ GEWÄHLT</span><br>
                            <?php endif; ?>
                            <strong><?php echo $date_str; ?></strong><br>
                            <span style="color: #666;"><?php echo $time_str; ?></span>
                            <?php if (!empty($date['notes'])): ?>
                                <br><small style="color: #999;">ℹ️ <?php echo htmlspecialchars($date['notes']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($date['location'] ?? '-'); ?></td>
                        <td style="text-align: center;">
                            <div class="vote-summary">
                                <span class="count-yes">✅ <?php echo $count_yes; ?></span> ·
                                <span class="count-maybe">🟡 <?php echo $count_maybe; ?></span> ·
                                <span class="count-no">❌ <?php echo $count_no; ?></span>
                            </div>
                        </td>
                        <?php foreach (array_keys($participants) as $member_id):
                            $vote = $votes_by_member[$member_id] ?? null;
                            $vote_icon = '';
                            $vote_color = '#ddd';
                            if ($vote === 1) {
                                $vote_icon = '✅';
                                $vote_color = '#4CAF50';
                            } elseif ($vote === 0) {
                                $vote_icon = '🟡';
                                $vote_color = '#FF9800';
                            } elseif ($vote === -1) {
                                $vote_icon = '❌';
                                $vote_color = '#f44336';
                            }
                        ?>
                            <td style="text-align: center; background-color: <?php echo $vote_color; ?>20;">
                                <?php echo $vote_icon; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Finalisierungs-Optionen für Ersteller/Admin -->
        <?php if ($can_edit && $poll['status'] !== 'finalized'): ?>
            <h3 style="margin-top: 40px;">🔒 Finalisierung</h3>
            <form method="POST" action="process_termine.php">
                <input type="hidden" name="action" value="finalize_poll">
                <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">

                <div class="form-group">
                    <label>Finalen Termin auswählen:</label>
                    <select name="final_date_id" required>
                        <option value="">- Termin auswählen -</option>
                        <?php foreach ($poll_dates as $date):
                            $date_str = date('D, d.m.Y H:i', strtotime($date['suggested_date']));
                        ?>
                            <option value="<?php echo $date['date_id']; ?>">
                                <?php echo $date_str; ?>
                                <?php if (!empty($date['location'])): ?>
                                    (<?php echo htmlspecialchars($date['location']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary" onclick="return confirm('Finalen Termin festlegen? Die Umfrage wird damit abgeschlossen.')">
                    ✓ Finalen Termin festlegen
                </button>
            </form>
        <?php endif; ?>

    <?php } // end if poll exists ?>

<?php endif; ?>
