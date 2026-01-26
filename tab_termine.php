<?php

// Benachrichtigungsmodul laden (wenn noch nicht geladen)
if (!function_exists('render_user_notifications')) {
    require_once 'module_notifications.php';
}
// Member-Functions mit Adapter-Support laden (wenn noch nicht geladen)
if (!function_exists('get_member_by_id')) {
    require_once 'member_functions.php';
}
// Externe Teilnehmer-Functions laden (wenn noch nicht geladen)
if (!function_exists('get_external_participant')) {
    require_once 'external_participants_functions.php';
}

/**
 * tab_termine.php - Terminplanung/Umfragen (Präsentation)
 * Erstellt: 17.11.2025
 *
 * Zeigt Terminumfragen und Abstimmungen
 * Nur Darstellung - alle Verarbeitungen in process_termine.php
 *
 * STANDALONE-MODUS:
 * Kann auch außerhalb der Sitzungsverwaltung verwendet werden.
 * Dazu vor dem Include setzen: $standalone_mode = true;
 */

// Standalone-Modus erkennen (Standard: false = volle Features)
if (!isset($standalone_mode)) {
    $standalone_mode = false;
}

// Aktuellen User holen (nur wenn noch nicht gesetzt, z.B. von Simple-Script)
if (!isset($current_user)) {
    $current_user = null;
    if (isset($_SESSION['member_id'])) {
        $current_user = get_member_by_id($pdo, $_SESSION['member_id']);
    }
}

// View-Parameter
$view = $_GET['view'] ?? 'dashboard';
$poll_id = intval($_GET['poll_id'] ?? 0);

// Umfragen laden (nur die, bei denen User Teilnehmer oder Ersteller ist)
$is_admin = $current_user ? in_array($current_user['role'], ['assistenz', 'gf']) : false;

if ($current_user) {
    if ($is_admin) {
        // Admins sehen alle Umfragen
        $stmt = $pdo->prepare("
            SELECT p.*,
                   COUNT(DISTINCT pd.date_id) as date_count,
                   COUNT(DISTINCT pr.member_id) as response_count,
                   final_pd.suggested_date as final_date,
                   final_pd.suggested_end_date as final_end_date
            FROM svpolls p
            LEFT JOIN svpoll_dates pd ON p.poll_id = pd.poll_id
            LEFT JOIN svpoll_responses pr ON p.poll_id = pr.poll_id
            LEFT JOIN svpoll_dates final_pd ON p.final_date_id = final_pd.date_id
            GROUP BY p.poll_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
    } else {
        // Normale User sehen:
        // 1. Umfragen, bei denen sie Teilnehmer sind
        // 2. Umfragen, die sie selbst erstellt haben
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.*,
                   COUNT(DISTINCT pd.date_id) as date_count,
                   COUNT(DISTINCT pr.member_id) as response_count,
                   final_pd.suggested_date as final_date,
                   final_pd.suggested_end_date as final_end_date
            FROM svpolls p
            LEFT JOIN svpoll_dates pd ON p.poll_id = pd.poll_id
            LEFT JOIN svpoll_responses pr ON p.poll_id = pr.poll_id
            LEFT JOIN svpoll_dates final_pd ON p.final_date_id = final_pd.date_id
            LEFT JOIN svpoll_participants pp ON p.poll_id = pp.poll_id AND pp.member_id = ?
            WHERE pp.member_id = ? OR p.created_by_member_id = ?
            GROUP BY p.poll_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$current_user['member_id'], $current_user['member_id'], $current_user['member_id']]);
    }
    $all_polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $all_polls = [];
}

// Creator-Namen über Adapter nachladen
foreach ($all_polls as &$poll) {
    if ($poll['created_by_member_id']) {
        $creator = get_member_by_id($pdo, $poll['created_by_member_id']);
        if ($creator) {
            $poll['creator_first_name'] = $creator['first_name'];
            $poll['creator_last_name'] = $creator['last_name'];
        }
    }
}

// Meetings für Dropdown laden (nur wenn User eingeloggt)
if ($current_user) {
    $all_meetings = get_visible_meetings($pdo, $current_user['member_id']);
    $all_members = get_all_members($pdo);
} else {
    $all_meetings = [];
    $all_members = [];
}

// Hilfsfunktion: Deutsche Wochentage (kurz)
function get_german_weekday($date_string) {
    $days = [
        'Mon' => 'Mo',
        'Tue' => 'Di',
        'Wed' => 'Mi',
        'Thu' => 'Do',
        'Fri' => 'Fr',
        'Sat' => 'Sa',
        'Sun' => 'So'
    ];
    $eng_day = date('D', strtotime($date_string));
    return $days[$eng_day] ?? $eng_day;
}

// Hilfsfunktion: Deutsche Wochentage (lang)
function get_german_weekday_long($date_string) {
    $days = [
        'Monday' => 'Montag',
        'Tuesday' => 'Dienstag',
        'Wednesday' => 'Mittwoch',
        'Thursday' => 'Donnerstag',
        'Friday' => 'Freitag',
        'Saturday' => 'Samstag',
        'Sunday' => 'Sonntag'
    ];
    $eng_day = date('l', strtotime($date_string));
    return $days[$eng_day] ?? $eng_day;
}
?>

<style>
/* Poll-spezifische Styles */
.poll-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
    position: relative;
    clear: both;
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
    flex: 1;
}

.poll-status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    flex-shrink: 0;
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
    line-height: 1.6;
}

.poll-meta p {
    margin: 8px 0;
    word-wrap: break-word;
}

.poll-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.vote-matrix {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.vote-matrix th,
.vote-matrix td {
    padding: 6px 8px;
    text-align: left;
    border: 1px solid #ddd;
    font-size: 13px;
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
    gap: 4px;
    justify-content: flex-start;
}

.vote-btn {
    border: 2px solid #ddd;
    background: white;
    padding: 4px 8px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 12px;
    transition: all 0.2s;
    min-width: 75px;
    text-align: center;
}

.vote-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.vote-btn.selected {
    border-width: 3px;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
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
    color: #333;
    border-radius: 5px;
    transition: background 0.3s;
}

.accordion-button:hover {
    background: #e0e0e0;
    color: #000;
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

/* Button Styles */
.btn-primary {
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.btn-secondary:hover {
    background: #545b62;
}

.btn-danger {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.btn-danger:hover {
    background: #c82333;
}

/* Responsive: Kleinere Buttons auf Smartphones */
@media (max-width: 767px) {
    .vote-btn {
        min-width: 65px;
        padding: 4px 6px;
        font-size: 12px;
    }
    .vote-buttons {
        gap: 3px;
    }

    /* Terminplanung: Flexible Spalten auf Smartphones */
    .date-suggestion-row,
    .date-suggestion-header {
        grid-template-columns: 1fr 80px 80px !important;
        gap: 5px !important;
    }

    /* Date/Time Inputs auf Smartphones kleinere Schrift */
    .date-suggestion-row input[type="date"],
    .date-suggestion-row input[type="time"] {
        font-size: 13px;
        padding: 6px 4px;
    }

    /* Kopieren-Button kompakter auf Mobile */
    .poll-link-container {
        flex-direction: column !important;
        gap: 8px !important;
    }

    .poll-link-container .btn-secondary {
        padding: 8px 12px !important;
        font-size: 13px !important;
        width: 100%;
    }

    /* Vote Matrix: Kleinere Schrift, kompaktere Darstellung */
    .vote-matrix th,
    .vote-matrix td {
        padding: 6px 4px;
        font-size: 12px;
    }

    .vote-matrix th:first-child,
    .vote-matrix td:first-child {
        font-size: 11px;
    }

    /* Ergebnistabelle: Teilnehmernamen kürzen */
    .vote-matrix thead th {
        font-size: 10px;
        padding: 4px 2px;
    }
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

// Teilnehmer-Auswahl für Umfragen
function toggleAllPollParticipants(checked) {
    const checkboxes = document.querySelectorAll('.poll-participant-checkbox');
    checkboxes.forEach(cb => cb.checked = checked);
}

function togglePollLeadershipRoles() {
    const checkboxes = document.querySelectorAll('.poll-participant-checkbox');
    const leadershipRoles = ['vorstand', 'gf', 'assistenz', 'fuehrungsteam'];
    checkboxes.forEach(cb => {
        const role = cb.getAttribute('data-role')?.toLowerCase();
        if (leadershipRoles.includes(role)) {
            cb.checked = !cb.checked;
        }
    });
}

function updatePollTargetOptions() {
    const targetType = document.querySelector('input[name="target_type"]:checked').value;
    const participantList = document.getElementById('poll-participant-list-selection');

    if (targetType === 'list') {
        participantList.style.display = 'block';
    } else {
        participantList.style.display = 'none';
    }
}

function togglePollTopManagement() {
    const checkboxes = document.querySelectorAll('.poll-participant-checkbox');
    const topRoles = ['vorstand', 'gf', 'assistenz'];
    checkboxes.forEach(cb => {
        const role = cb.getAttribute('data-role')?.toLowerCase();
        if (topRoles.includes(role)) {
            cb.checked = !cb.checked;
        }
    });
}

// Add More Date Suggestions
let pollDateCount = 5;
function addMorePollDates() {
    const container = document.getElementById('date-suggestions-container');
    const maxDates = 20;

    if (pollDateCount >= maxDates) {
        alert('Maximal ' + maxDates + ' Terminvorschläge möglich');
        return;
    }

    pollDateCount++;
    const newRow = document.createElement('div');
    newRow.className = 'date-suggestion-row';
    newRow.style.cssText = 'display: grid; grid-template-columns: 150px 100px 100px; gap: 10px; align-items: center; margin-bottom: 8px;';
    newRow.innerHTML = `
        <input type="date" name="date_${pollDateCount}" id="poll_date_${pollDateCount}" onfocus="autoFillOnFocus(${pollDateCount})" style="width: 100%;">
        <input type="time" name="time_start_${pollDateCount}" id="poll_time_start_${pollDateCount}" onfocus="autoFillOnFocus(${pollDateCount})" onchange="calculateEndTime(${pollDateCount})" style="width: 100%;">
        <input type="time" name="time_end_${pollDateCount}" id="poll_time_end_${pollDateCount}" onfocus="autoFillOnFocus(${pollDateCount})" style="width: 100%;">
    `;
    container.appendChild(newRow);
}

// Automatische Berechnung der Ende-Zeit basierend auf Dauer
function calculateEndTime(index) {
    const durationInput = document.getElementById('poll_duration');
    const startTimeInput = document.getElementById('poll_time_start_' + index);
    const endTimeInput = document.getElementById('poll_time_end_' + index);

    if (!durationInput || !durationInput.value || !startTimeInput || !startTimeInput.value) {
        return;
    }

    const duration = parseInt(durationInput.value);
    const [hours, minutes] = startTimeInput.value.split(':');
    const startTime = new Date();
    startTime.setHours(parseInt(hours), parseInt(minutes), 0);
    startTime.setMinutes(startTime.getMinutes() + duration);

    const endHours = String(startTime.getHours()).padStart(2, '0');
    const endMinutes = String(startTime.getMinutes()).padStart(2, '0');
    endTimeInput.value = `${endHours}:${endMinutes}`;
}

// Auto-Vorschlag: Folgetag mit gleicher Uhrzeit (wird beim Focus ins nächste Feld getriggert)
function autoFillOnFocus(currentIndex) {
    const currentDateInput = document.getElementById('poll_date_' + currentIndex);
    const currentTimeStartInput = document.getElementById('poll_time_start_' + currentIndex);
    const currentTimeEndInput = document.getElementById('poll_time_end_' + currentIndex);
    const prevDateInput = document.getElementById('poll_date_' + (currentIndex - 1));
    const prevTimeStartInput = document.getElementById('poll_time_start_' + (currentIndex - 1));
    const prevTimeEndInput = document.getElementById('poll_time_end_' + (currentIndex - 1));

    // Nur vorausfüllen, wenn das aktuelle Feld leer ist und das vorherige ausgefüllt ist
    if (currentDateInput && !currentDateInput.value && prevDateInput && prevDateInput.value) {
        // Folgetag berechnen
        const prevDate = new Date(prevDateInput.value);
        prevDate.setDate(prevDate.getDate() + 1);
        const year = prevDate.getFullYear();
        const month = String(prevDate.getMonth() + 1).padStart(2, '0');
        const day = String(prevDate.getDate()).padStart(2, '0');
        currentDateInput.value = `${year}-${month}-${day}`;
    }

    // Uhrzeit übernehmen wenn vorhanden
    if (currentTimeStartInput && !currentTimeStartInput.value && prevTimeStartInput && prevTimeStartInput.value) {
        currentTimeStartInput.value = prevTimeStartInput.value;
    }
    if (currentTimeEndInput && !currentTimeEndInput.value && prevTimeEndInput && prevTimeEndInput.value) {
        currentTimeEndInput.value = prevTimeEndInput.value;
    }
}

// Link in Zwischenablage kopieren
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Link wurde in die Zwischenablage kopiert!');
    });
}
</script>

<!-- BENACHRICHTIGUNGEN (nur im normalen Modus) -->
<?php if ($current_user && !$standalone_mode): render_user_notifications($pdo, $current_user['member_id']); endif; ?>

<h2>📆 Terminplanung & Umfragen</h2>

<?php
// Login-Prüfung
if (!$current_user) {
    echo '<div class="error-message">Bitte melde dich an, um Terminplanungen zu sehen.</div>';
    return;
}
?>

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
        <button class="accordion-button create-poll-button" onclick="toggleAccordion(this)">➕ Neue Terminumfrage erstellen</button>
        <div class="accordion-content">
            <form method="POST" action="<?php echo (isset($form_action_path) ? $form_action_path : '') . 'process_termine.php'; ?>" id="poll-create-form">
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
                    <label>Ort:</label>
                    <input type="text" name="location" placeholder="Optional: Ort der Veranstaltung (z.B. Konferenzraum A)">
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Wird beim Finalisieren in das Meeting übernommen
                    </small>
                </div>

                <!-- Zielgruppe wählen -->
                <div class="form-group" style="margin-top: 25px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                    <h4 style="margin: 0 0 15px 0;">Zielgruppe wählen</h4>
                    <?php if ($standalone_mode): ?>
                        <!-- Standalone: Nur individueller Link -->
                        <input type="hidden" name="target_type" value="individual">
                        <p style="margin: 0;">
                            <strong>🔗 Individueller Link</strong> - Du erhältst einen Link, den du weitergeben kannst
                        </p>
                    <?php else: ?>
                        <!-- Normal: Alle Optionen -->
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="radio" name="target_type" value="individual" onchange="updatePollTargetOptions()">
                            <strong>Individuell</strong> - Link, den du weitergeben kannst
                        </label>
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="radio" name="target_type" value="list" checked onchange="updatePollTargetOptions()">
                            <strong>Ausgewählte registrierte Teilnehmer</strong>
                        </label>
                    <?php endif; ?>
                </div>

                <!-- Teilnehmer auswählen (nur bei target_type='list' UND nicht im Standalone-Modus) -->
                <?php if (!$standalone_mode): ?>
                <div class="form-group" id="poll-participant-list-selection">
                    <label>Teilnehmer auswählen (nur diese sehen die Umfrage):*</label>
                    <div class="participant-buttons">
                        <button type="button" onclick="toggleAllPollParticipants(true)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✓ Alle auswählen</button>
                        <button type="button" onclick="toggleAllPollParticipants(false)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✗ Alle abwählen</button>
                        <button type="button" onclick="togglePollLeadershipRoles()" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">👔 Führungsrollen</button>
                        <button type="button" onclick="togglePollTopManagement()" class="btn-secondary" style="padding: 5px 10px;">⭐ Vorstand+GF+Ass</button>
                    </div>
                    <div class="participants-selector">
                        <?php foreach ($all_members as $member): ?>
                            <label class="participant-label">
                                <input type="checkbox"
                                       name="participant_ids[]"
                                       value="<?php echo $member['member_id']; ?>"
                                       class="poll-participant-checkbox"
                                       data-role="<?php echo htmlspecialchars($member['role']); ?>">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- E-Mail-Option direkt unter Teilnehmerliste -->
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <label style="display: block;">
                            <input type="checkbox" name="send_invitation_mail" value="1">
                            <strong>Einladungsmail an ausgewählte Teilnehmer senden</strong>
                        </label>
                        <small style="display: block; margin-left: 24px; margin-top: 5px; color: #666;">
                            Wenn aktiviert, werden alle ausgewählten Teilnehmer per E-Mail über die neue Umfrage benachrichtigt
                        </small>
                    </div>
                </div>
                <?php endif; // Ende if (!$standalone_mode) ?>

                <!-- Terminvorschläge -->
                <h3 style="margin-top: 25px; margin-bottom: 15px;">Terminvorschläge</h3>

                <div class="form-group">
                    <label>Voraussichtliche Dauer (Minuten):</label>
                    <input type="number" name="poll_duration" id="poll_duration" placeholder="z.B. 120" min="15" step="15" style="width: 150px;">
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Optional: Wenn angegeben, wird die Ende-Zeit automatisch berechnet
                    </small>
                </div>

                <div class="form-group">
                    <div class="date-suggestion-header" style="display: grid; grid-template-columns: 150px 100px 100px; gap: 10px; align-items: center; margin-bottom: 10px; font-weight: bold; border-bottom: 2px solid #ddd; padding-bottom: 5px;">
                        <div>Datum</div>
                        <div>Beginn</div>
                        <div>Ende</div>
                    </div>

                    <div id="date-suggestions-container">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="date-suggestion-row" style="display: grid; grid-template-columns: 150px 100px 100px auto; gap: 10px; align-items: center; margin-bottom: 8px;">
                            <input type="date" name="date_<?php echo $i; ?>" id="poll_date_<?php echo $i; ?>" onfocus="autoFillOnFocus(<?php echo $i; ?>)" style="width: 100%;">
                            <input type="time" name="time_start_<?php echo $i; ?>" id="poll_time_start_<?php echo $i; ?>" onfocus="autoFillOnFocus(<?php echo $i; ?>)" onchange="calculateEndTime(<?php echo $i; ?>)" style="width: 100%;">
                            <input type="time" name="time_end_<?php echo $i; ?>" id="poll_time_end_<?php echo $i; ?>" onfocus="autoFillOnFocus(<?php echo $i; ?>)" style="width: 100%;">
                            <?php if ($i === 5): ?>
                            <button type="button" onclick="addMorePollDates()" class="btn-secondary" style="padding: 4px 8px; font-size: 12px; white-space: nowrap;">+ Termin</button>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <small style="display: block; margin-top: 10px; color: #666;">
                        Du kannst bis zu 20 Terminvorschläge hinzufügen. Wenn du ins nächste Datumsfeld klickst, wird automatisch der Folgetag mit gleicher Uhrzeit vorgeschlagen.
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
            <div class="poll-card status-<?php echo $poll['status']; ?>" style="display: block; overflow: visible; position: static;">
                <div class="poll-header" style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                    <h3 class="poll-title" style="flex: 1; margin: 0;"><?php echo htmlspecialchars($poll['title']); ?></h3>
                    <span class="poll-status-badge status-<?php echo $poll['status']; ?>" style="flex-shrink: 0;">
                        <?php
                        switch ($poll['status']) {
                            case 'open': echo '🟢 Offen'; break;
                            case 'closed': echo '🟠 Geschlossen'; break;
                            case 'finalized': echo '🔵 Abgeschlossen'; break;
                        }
                        ?>
                    </span>
                </div>

                <div class="poll-meta" style="display: block; clear: both; margin-bottom: 15px;">
                    <?php if (!empty($poll['description'])): ?>
                        <p style="margin: 8px 0;"><?php echo nl2br(htmlspecialchars($poll['description'])); ?></p>
                    <?php endif; ?>

                    <p style="margin: 8px 0;">
                        📊 <strong><?php echo $poll['date_count']; ?></strong> Terminvorschläge ·
                        👥 <strong><?php echo $poll['response_count']; ?></strong> Teilnehmer abgestimmt ·
                        👤 Erstellt von <strong><?php echo htmlspecialchars($poll['creator_first_name'] . ' ' . $poll['creator_last_name']); ?></strong> ·
                        Zielgruppe: <?php
                            if ($poll['target_type'] === 'individual') echo '🔗 Individuell (Link)';
                            elseif ($poll['target_type'] === 'list') echo '📋 Ausgewählte Teilnehmer';
                            elseif ($poll['target_type'] === 'public') echo '🌐 Öffentlich';
                        ?> ·
                        📅 <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?>
                    </p>

                    <?php if ($poll['status'] === 'finalized' && !empty($poll['final_date'])): ?>
                        <p style="color: #2196F3; font-weight: bold; margin: 8px 0;">
                            ✓ Finaler Termin festgelegt auf <?php
                                echo get_german_weekday_long($poll['final_date']) . ', den ' . date('d.m.Y', strtotime($poll['final_date']));
                                if (!empty($poll['final_end_date'])) {
                                    echo ' ' . date('H:i', strtotime($poll['final_date'])) . ' - ' . date('H:i', strtotime($poll['final_end_date']));
                                } else {
                                    echo ' ' . date('H:i', strtotime($poll['final_date'])) . ' Uhr';
                                }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="poll-actions" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; clear: both;">
                    <a href="?tab=termine&view=poll&poll_id=<?php echo $poll['poll_id']; ?>" class="btn-primary" style="position: static; float: none;">
                        <?php echo $poll['status'] === 'open' ? '📝 Abstimmen' : '📊 Ergebnisse ansehen'; ?>
                    </a>

                    <?php if ($can_edit): ?>
                        <?php if ($poll['status'] === 'open'): ?>
                            <form method="POST" action="<?php echo (isset($form_action_path) ? $form_action_path : '') . 'process_termine.php'; ?>" style="display: inline;">
                                <input type="hidden" name="action" value="close_poll">
                                <input type="hidden" name="poll_id" value="<?php echo $poll['poll_id']; ?>">
                                <button type="submit" class="btn-secondary" onclick="return confirm('Umfrage schließen?')">🔒 Schließen</button>
                            </form>
                        <?php elseif ($poll['status'] === 'closed'): ?>
                            <form method="POST" action="<?php echo (isset($form_action_path) ? $form_action_path : '') . 'process_termine.php'; ?>" style="display: inline;">
                                <input type="hidden" name="action" value="reopen_poll">
                                <input type="hidden" name="poll_id" value="<?php echo $poll['poll_id']; ?>">
                                <button type="submit" class="btn-secondary">🔓 Wieder öffnen</button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo (isset($form_action_path) ? $form_action_path : '') . 'process_termine.php'; ?>" style="display: inline;">
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
        SELECT p.*
        FROM svpolls p
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        echo '<div class="error-message">Umfrage nicht gefunden</div>';
        echo '<a href="?tab=termine" class="btn-secondary">← Zurück zur Übersicht</a>';
    } else {
        // Creator-Namen über Adapter laden
        if ($poll['created_by_member_id']) {
            $creator = get_member_by_id($pdo, $poll['created_by_member_id']);
            if ($creator) {
                $poll['creator_first_name'] = $creator['first_name'];
                $poll['creator_last_name'] = $creator['last_name'];
            }
        }
        // Terminvorschläge laden
        $stmt = $pdo->prepare("
            SELECT * FROM svpoll_dates
            WHERE poll_id = ?
            ORDER BY sort_order, suggested_date
        ");
        $stmt->execute([$poll_id]);
        $poll_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Alle Antworten laden
        $stmt = $pdo->prepare("
            SELECT pr.*
            FROM svpoll_responses pr
            WHERE pr.poll_id = ?
        ");
        $stmt->execute([$poll_id]);
        $all_responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Member- und Externe-Teilnehmer-Namen nachladen
        foreach ($all_responses as &$response) {
            if ($response['member_id']) {
                $member = get_member_by_id($pdo, $response['member_id']);
                if ($member) {
                    $response['first_name'] = $member['first_name'];
                    $response['last_name'] = $member['last_name'];
                    $response['participant_key'] = 'member_' . $response['member_id'];
                }
            } elseif ($response['external_participant_id']) {
                // Externen Teilnehmer laden
                $ext_stmt = $pdo->prepare("SELECT first_name, last_name FROM svexternal_participants WHERE external_id = ?");
                $ext_stmt->execute([$response['external_participant_id']]);
                $ext = $ext_stmt->fetch(PDO::FETCH_ASSOC);
                if ($ext) {
                    $response['first_name'] = $ext['first_name'];
                    $response['last_name'] = $ext['last_name'];
                    $response['participant_key'] = 'external_' . $response['external_participant_id'];
                }
            }
        }

        // Alle eingeladenen Teilnehmer laden
        $stmt = $pdo->prepare("
            SELECT pp.member_id
            FROM svpoll_participants pp
            WHERE pp.poll_id = ?
        ");
        $stmt->execute([$poll_id]);
        $poll_participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Member-Namen über Adapter nachladen und sortieren
        foreach ($poll_participants as &$participant) {
            if ($participant['member_id']) {
                $member = get_member_by_id($pdo, $participant['member_id']);
                if ($member) {
                    $participant['first_name'] = $member['first_name'];
                    $participant['last_name'] = $member['last_name'];
                }
            }
        }
        // Nach Nachname sortieren
        usort($poll_participants, function($a, $b) {
            return strcmp($a['last_name'] ?? '', $b['last_name'] ?? '');
        });

        // User's aktuelle Antworten laden
        $stmt = $pdo->prepare("
            SELECT date_id, vote
            FROM svpoll_responses
            WHERE poll_id = ? AND member_id = ?
        ");
        $stmt->execute([$poll_id, $current_user['member_id']]);
        $user_votes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $user_votes[$row['date_id']] = (int)$row['vote'];
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
                    Zielgruppe: <?php
                        if ($poll['target_type'] === 'individual') echo '🔗 Individuell (Link)';
                        elseif ($poll['target_type'] === 'list') echo '📋 Ausgewählte Teilnehmer';
                        elseif ($poll['target_type'] === 'public') echo '🌐 Öffentlich';
                    ?> ·
                    📅 <?php echo date('d.m.Y H:i', strtotime($poll['created_at'])); ?>
                </p>

                <?php if ($poll['status'] === 'finalized' && !empty($poll['final_date_id'])):
                    // Finales Datum laden
                    $final_date_stmt = $pdo->prepare("SELECT suggested_date, suggested_end_date FROM svpoll_dates WHERE date_id = ?");
                    $final_date_stmt->execute([$poll['final_date_id']]);
                    $final_date_info = $final_date_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($final_date_info):
                ?>
                    <p style="color: #2196F3; font-weight: bold;">
                        ✓ Finaler Termin festgelegt auf <?php
                            echo get_german_weekday_long($final_date_info['suggested_date']) . ', den ' . date('d.m.Y', strtotime($final_date_info['suggested_date']));
                            if (!empty($final_date_info['suggested_end_date'])) {
                                echo ' ' . date('H:i', strtotime($final_date_info['suggested_date'])) . ' - ' . date('H:i', strtotime($final_date_info['suggested_end_date']));
                            } else {
                                echo ' ' . date('H:i', strtotime($final_date_info['suggested_date'])) . ' Uhr';
                            }
                        ?>
                    </p>
                    <?php if (!empty($poll['meeting_id'])): ?>
                    <p style="margin-top: 10px;">
                        <a href="?tab=meetings" class="btn-primary" style="text-decoration: none; display: inline-block; padding: 8px 16px;">
                            📅 Zur erstellten Sitzung
                        </a>
                    </p>
                    <?php endif; ?>
                <?php endif; endif; ?>
            </div>
        </div>

        <!-- Link zur Umfrage (für Ersteller und Admin) -->
        <?php if (($is_creator || $is_admin) && $poll['status'] !== 'finalized'): ?>
        <?php
        // Zentrale Link-Generierung verwenden
        $use_token = ($poll['target_type'] ?? 'list') === 'individual' && !empty($poll['access_token']);
        $poll_link = generate_external_access_link(
            'termine',
            $use_token ? $poll['access_token'] : $poll_id,
            $use_token
        );
        ?>
        <div class="poll-card" style="background: #f0f8ff; border: 2px solid #4CAF50; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0;">🔗 Link zu dieser Umfrage</h4>
            <p style="margin: 0 0 10px 0; color: #666;">
                <?php
                if (($poll['target_type'] ?? 'list') === 'individual') {
                    echo 'Teile diesen eindeutigen Link mit den gewünschten Teilnehmern:';
                } elseif (($poll['target_type'] ?? 'list') === 'list') {
                    echo 'Dieser Link kann an die eingeladenen Teilnehmer weitergegeben werden (zusätzlich zur Benachrichtigung):';
                } else {
                    echo 'Teile diesen Link mit den Teilnehmern:';
                }
                ?>
            </p>
            <div class="poll-link-container" style="display: flex; gap: 10px; align-items: center;">
                <input type="text"
                       id="poll_link_<?php echo $poll_id; ?>"
                       value="<?php echo htmlspecialchars($poll_link); ?>"
                       readonly
                       onclick="this.select()"
                       style="flex: 1; padding: 10px; font-size: 13px; font-family: monospace; border: 1px solid #ccc; background: white; border-radius: 4px;">
                <button type="button"
                        class="btn-secondary"
                        onclick="copyToClipboard('<?php echo htmlspecialchars($poll_link, ENT_QUOTES); ?>')"
                        style="padding: 10px 20px; white-space: nowrap;">
                    📋 Kopieren
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Abstimmungs-Formular -->
        <?php if ($can_vote): ?>
            <h3>📝 Deine Abstimmung</h3>
            <p style="margin-bottom: 15px; color: #666;">
                Bitte gib für jeden Terminvorschlag an, ob der Termin für dich passt:<br>
                <strong>✅ Passt</strong> – Der Termin passt mir gut<br>
                <strong>🟡 Muss</strong> – Wenn es sein muss, kann ich<br>
                <strong>❌ Passt nicht</strong> – Der Termin passt mir nicht
            </p>

            <form method="POST" action="<?php echo (isset($form_action_path) ? $form_action_path : '') . 'process_termine.php'; ?>">
                <input type="hidden" name="action" value="submit_vote">
                <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">

                <table class="vote-matrix">
                    <thead>
                        <tr>
                            <th style="width: 180px;">Terminvorschlag</th>
                            <th>Deine Wahl</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poll_dates as $date):
                            $user_vote = $user_votes[$date['date_id']] ?? null;
                            $date_str = get_german_weekday($date['suggested_date']) . ', ' . date('d.m.Y', strtotime($date['suggested_date']));
                            $time_str = date('H:i', strtotime($date['suggested_date']));
                            if (!empty($date['suggested_end_date'])) {
                                $time_str .= ' - ' . date('H:i', strtotime($date['suggested_end_date']));
                            }
                        ?>
                            <tr>
                                <td style="white-space: nowrap;">
                                    <strong style="font-size: 15px;"><?php echo $date_str; ?></strong><br>
                                    <span style="color: #666; font-size: 13px;"><?php echo $time_str; ?></span>
                                </td>
                                <td>
                                    <input type="hidden" name="vote_<?php echo $date['date_id']; ?>" id="vote_<?php echo $date['date_id']; ?>" value="<?php echo $user_vote !== null ? $user_vote : ''; ?>">
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
            <div class="info-box">Diese Umfrage ist geschlossen. Du kannst nicht mehr abstimmen.</div>
        <?php endif; ?>

        <!-- Ergebnisse anzeigen -->
        <h3 style="margin-top: 40px;">📊 Abstimmungsergebnisse</h3>

        <table class="vote-matrix">
            <thead>
                <tr>
                    <th style="width: 220px;">Terminvorschlag & Zusammenfassung</th>
                    <?php
                    // Alle Teilnehmer sammeln (Members + Externe)
                    $participants = [];

                    // Eingeladene Members
                    foreach ($poll_participants as $pp) {
                        $key = 'member_' . $pp['member_id'];
                        $participants[$key] = $pp['first_name'] . ' ' . substr($pp['last_name'], 0, 1) . '.';
                    }

                    // Alle Teilnehmer, die bereits geantwortet haben (Members + Externe)
                    foreach ($all_responses as $resp) {
                        if (isset($resp['participant_key'])) {
                            if (!isset($participants[$resp['participant_key']])) {
                                $name_display = $resp['first_name'] . ' ' . substr($resp['last_name'], 0, 1) . '.';
                                // Externe mit Icon markieren
                                if (strpos($resp['participant_key'], 'external_') === 0) {
                                    $name_display .= ' 👤';
                                }
                                $participants[$resp['participant_key']] = $name_display;
                            }
                        }
                    }

                    foreach ($participants as $name): ?>
                        <th style="text-align: center; font-size: 11px; padding: 6px 4px;"><?php echo htmlspecialchars($name); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($poll_dates as $date):
                    $is_final = ($poll['final_date_id'] == $date['date_id']);
                    $date_str = get_german_weekday($date['suggested_date']) . ', ' . date('d.m.Y', strtotime($date['suggested_date']));
                    $time_str = date('H:i', strtotime($date['suggested_date']));
                    if (!empty($date['suggested_end_date'])) {
                        $time_str .= ' - ' . date('H:i', strtotime($date['suggested_end_date']));
                    }

                    // Votes zählen für diesen Termin
                    $count_yes = 0;
                    $count_maybe = 0;
                    $count_no = 0;
                    $votes_by_participant = [];

                    foreach ($all_responses as $resp) {
                        if ($resp['date_id'] == $date['date_id']) {
                            if (isset($resp['participant_key'])) {
                                $votes_by_participant[$resp['participant_key']] = (int)$resp['vote'];
                            }
                            if ($resp['vote'] == 1) $count_yes++;
                            elseif ($resp['vote'] == 0) $count_maybe++;
                            elseif ($resp['vote'] == -1) $count_no++;
                        }
                    }
                ?>
                    <tr class="<?php echo $is_final ? 'final-date-highlight' : ''; ?>">
                        <td style="white-space: nowrap;">
                            <?php if ($is_final): ?>
                                <span style="color: #2196F3; font-weight: bold; font-size: 12px;">⭐ GEWÄHLT</span><br>
                            <?php endif; ?>
                            <strong style="font-size: 15px;"><?php echo $date_str; ?></strong><br>
                            <span style="color: #666; font-size: 13px;"><?php echo $time_str; ?></span><br>
                            <div class="vote-summary" style="font-size: 12px; margin-top: 4px; padding-top: 4px; border-top: 1px solid #eee;">
                                <span class="count-yes">✅<?php echo $count_yes; ?></span> ·
                                <span class="count-maybe">🟡<?php echo $count_maybe; ?></span> ·
                                <span class="count-no">❌<?php echo $count_no; ?></span>
                            </div>
                        </td>
                        <?php foreach (array_keys($participants) as $participant_key):
                            $vote = $votes_by_participant[$participant_key] ?? null;
                            $vote_icon = '–';  // Strich für keine Abstimmung
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

        <!-- ICS-Export für finalisierte Umfragen -->
        <?php if ($poll['status'] === 'finalized' && !empty($poll['final_date_id'])): ?>
            <?php
            // Finalen Termin laden
            $final_stmt = $pdo->prepare("SELECT * FROM svpoll_dates WHERE date_id = ?");
            $final_stmt->execute([$poll['final_date_id']]);
            $final_date = $final_stmt->fetch(PDO::FETCH_ASSOC);

            if ($final_date):
                $final_date_str = get_german_weekday_long($final_date['suggested_date']) . ', ' . date('d.m.Y', strtotime($final_date['suggested_date']));
                $final_time_str = date('H:i', strtotime($final_date['suggested_date']));
                if (!empty($final_date['suggested_end_date'])) {
                    $final_time_str .= ' - ' . date('H:i', strtotime($final_date['suggested_end_date']));
                }
            ?>
                <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px;">
                    <h3 style="margin: 0 0 15px 0; color: white;">📅 Finaler Termin - Kalender-Export</h3>

                    <div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <div style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">
                            <?php echo $final_date_str; ?>
                        </div>
                        <div style="font-size: 16px; opacity: 0.95;">
                            🕐 <?php echo $final_time_str; ?>
                        </div>
                        <?php if (!empty($poll['location'])): ?>
                            <div style="font-size: 14px; margin-top: 8px; opacity: 0.9;">
                                📍 <?php echo htmlspecialchars($poll['location']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                        <div class="desktop-only" style="background: white; padding: 10px; border-radius: 5px;">
                            <img src="qr.php?type=poll&id=<?php echo $poll_id; ?>" alt="QR-Code für Kalender-Import" style="display: block;">
                            <div style="text-align: center; font-size: 11px; color: #333; margin-top: 5px;">
                                QR-Code scannen<br>zum Importieren
                            </div>
                        </div>

                        <div style="flex: 1; min-width: 200px;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.95;">
                                Füge den Termin zu deinem Kalender hinzu:
                            </p>
                            <a href="poll_ics.php?id=<?php echo $poll_id; ?>"
                               class="btn-primary"
                               style="display: inline-block; background: white; color: #667eea; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                📥 .ics-Datei herunterladen
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Finalisierungs-Optionen für Ersteller/Admin -->
        <?php if ($can_edit && $poll['status'] !== 'finalized'): ?>
            <div style="margin-top: 40px;">
                <button class="accordion-button" onclick="toggleAccordion(this)">
                    🔒 Finalisierung
                </button>
                <div class="accordion-content <?php echo $is_creator ? 'active' : ''; ?>">
            <form method="POST" action="<?php echo (isset($form_action_path) ? $form_action_path : '') . 'process_termine.php'; ?>">
                <input type="hidden" name="action" value="finalize_poll">
                <input type="hidden" name="poll_id" value="<?php echo $poll_id; ?>">

                <div class="form-group">
                    <label>Finalen Termin auswählen:</label>
                    <select name="final_date_id" required>
                        <option value="">- Termin auswählen -</option>
                        <?php foreach ($poll_dates as $date):
                            $date_str = get_german_weekday($date['suggested_date']) . ', ' . date('d.m.Y H:i', strtotime($date['suggested_date']));
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

                <!-- Meeting-Option -->
                <?php if (!$standalone_mode): ?>
                <div class="form-group" style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px;">
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" name="create_meeting" value="1" checked>
                        <strong>📅 Automatisch Meeting für diesen Termin anlegen</strong>
                    </label>
                    <small style="display: block; margin-left: 24px; color: #666;">
                        Wenn aktiviert, wird automatisch ein Meeting mit dem finalen Termin, Titel und Teilnehmern erstellt
                    </small>
                </div>
                <?php else: ?>
                <!-- Hidden field im Standalone-Modus: kein automatisches Meeting -->
                <input type="hidden" name="create_meeting" value="0">
                <?php endif; ?>

                <!-- E-Mail-Optionen -->
                <div class="form-group" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 10px;">📧 E-Mail-Benachrichtigungen:</label>

                    <div style="margin-bottom: 15px;">
                        <label style="font-weight: normal; display: block; margin-bottom: 8px;">Bestätigungsmail senden an:</label>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="notification_recipients" value="voters" checked>
                            Nur Teilnehmer, die abgestimmt haben
                        </label>
                        <?php if (!$standalone_mode): ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="radio" name="notification_recipients" value="all">
                            Alle ausgewählten Teilnehmer (auch ohne Abstimmung)
                        </label>
                        <?php endif; ?>
                        <label style="display: block;">
                            <input type="radio" name="notification_recipients" value="none">
                            Keine E-Mail senden
                        </label>
                    </div>

                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="checkbox" name="send_reminder" value="1" id="send_reminder_checkbox" onchange="document.getElementById('reminder_days_input').disabled = !this.checked">
                            <strong>Erinnerungsmail vor dem Termin senden</strong>
                        </label>
                        <div style="margin-left: 24px;">
                            <label style="display: block; margin-bottom: 5px;">Erinnerung senden (Tage vorher):</label>
                            <input type="number" name="reminder_days" id="reminder_days_input" value="1" min="1" max="30" style="width: 80px;" disabled>
                            <small style="display: block; margin-top: 5px; color: #666;">
                                Die Erinnerungsmail wird automatisch X Tage vor dem Termin an die gleichen Empfänger versendet
                            </small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" onclick="return confirm('Finalen Termin festlegen? Die Umfrage wird damit abgeschlossen.')">
                    ✓ Finalen Termin festlegen
                </button>
            </form>
                </div>
            </div>
        <?php endif; ?>

    <?php } // end if poll exists ?>

<?php endif; ?>

