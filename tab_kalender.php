<?php
/**
 * tab_kalender.php - Kalender-Modul UI
 * Erstellt: 2026-10-11
 *
 * $pdo, $current_user und $active_tab werden von index.php bereitgestellt.
 */

// ============================================================
// HILFSFUNKTIONEN
// ============================================================

/**
 * Prüft, ob ein Ereignis für das angegebene Mitglied sichtbar ist.
 *
 * @param array  $event        Datenbankzeile aus svevents
 * @param int    $member_id
 * @param string $member_role
 * @param PDO    $pdo
 * @return bool
 */
function isEventVisibleTo(array $event, int $member_id, string $member_role, $pdo): bool
{
    switch ($event['target_type']) {
        case 'all':
            return true;

        case 'roles':
            if (empty($event['target_roles'])) {
                return false;
            }
            $roles = array_map('trim', explode(',', $event['target_roles']));
            return in_array(strtolower($member_role), $roles, true);

        case 'list':
            $stmt = $pdo->prepare(
                "SELECT 1 FROM svevents_participants WHERE event_id = ? AND member_id = ? LIMIT 1"
            );
            $stmt->execute([(int)$event['event_id'], $member_id]);
            return (bool)$stmt->fetchColumn();

        default:
            return false;
    }
}

/**
 * Erzeugt aus einem Array von Ereignissen (inkl. recurrence-Einträgen) die
 * konkreten Vorkommnisse im Zeitraum [$from_ts, $to_ts].
 *
 * Für jedes Vorkommnis wird eine Kopie des Original-Arrays zurückgegeben,
 * mit angepasstem start_datetime (und ggf. end_datetime falls gesetzt).
 *
 * @param array[] $events   Datenbankzeilen aus svevents
 * @param int     $from_ts  Unix-Timestamp (Beginn des Zeitraums)
 * @param int     $to_ts    Unix-Timestamp (Ende des Zeitraums, inkl.)
 * @return array[]
 */
function expandEvents(array $events, int $from_ts, int $to_ts): array
{
    $result = [];

    foreach ($events as $event) {
        $start_ts = strtotime($event['start_datetime']);
        $duration = 0;
        if (!empty($event['end_datetime'])) {
            $duration = strtotime($event['end_datetime']) - $start_ts;
        }

        $rec_end_ts = !empty($event['recurrence_end_date'])
            ? strtotime($event['recurrence_end_date'] . ' 23:59:59')
            : $to_ts;

        if ($event['recurrence'] === 'none' || $event['recurrence'] === null) {
            // Einmaliger Termin
            if ($start_ts <= $to_ts && $start_ts >= $from_ts) {
                $result[] = $event;
            }
            continue;
        }

        // Wiederkehrender Termin: iteriere über alle Vorkommnisse im Zeitfenster
        $current_ts = $start_ts;
        $max_iterations = 500; // Sicherheitsgrenze
        $i = 0;

        while ($current_ts <= $to_ts && $current_ts <= $rec_end_ts && $i < $max_iterations) {
            $i++;
            if ($current_ts >= $from_ts) {
                $occurrence       = $event;
                $occurrence['start_datetime'] = date('Y-m-d H:i:s', $current_ts);
                if ($duration > 0) {
                    $occurrence['end_datetime'] = date('Y-m-d H:i:s', $current_ts + $duration);
                }
                $result[] = $occurrence;
            }

            // Nächstes Vorkommnis berechnen
            switch ($event['recurrence']) {
                case 'weekly':
                    $current_ts = strtotime('+1 week', $current_ts);
                    break;
                case 'biweekly':
                    $current_ts = strtotime('+2 weeks', $current_ts);
                    break;
                case 'monthly':
                    $current_ts = strtotime('+1 month', $current_ts);
                    break;
                case 'yearly':
                    $current_ts = strtotime('+1 year', $current_ts);
                    break;
                default:
                    break 2;
            }
        }
    }

    // Sortieren nach start_datetime
    usort($result, function ($a, $b) {
        return strcmp($a['start_datetime'], $b['start_datetime']);
    });

    return $result;
}

// ============================================================
// DATEN LADEN
// ============================================================

$is_admin = is_admin_user($current_user);
$member_id   = (int)$current_user['member_id'];
$member_role = strtolower($current_user['role'] ?? '');

// Zeitraum: heute bis +90 Tage
$from_ts = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
$to_ts   = $from_ts + (90 * 86400);
$from_dt = date('Y-m-d H:i:s', $from_ts);
$to_dt   = date('Y-m-d H:i:s', $to_ts);

// Eigene und für den User sichtbare Ereignisse aus svevents laden
// (Sichtbarkeitsfilter wird weiter unten per PHP angewendet,
//  da target_type='list' einen DB-Lookup erfordert)
$stmt = $pdo->prepare(
    "SELECT * FROM svevents
     WHERE status = 'active'
       AND start_datetime <= :to_dt
       AND (recurrence != 'none' OR start_datetime >= :from_dt)
     ORDER BY start_datetime ASC"
);
$stmt->execute([':from_dt' => $from_dt, ':to_dt' => $to_dt]);
$raw_events = $stmt->fetchAll();

// Sichtbarkeitsfilter anwenden
$visible_events = [];
foreach ($raw_events as $ev) {
    if (isEventVisibleTo($ev, $member_id, $member_role, $pdo)) {
        $visible_events[] = $ev;
    }
}

// Wiederkehrende Termine expandieren
$expanded_events = expandEvents($visible_events, $from_ts, $to_ts);

// Sitzungen aus svmeetings laden, an denen der User teilnimmt
$stmt_meetings = $pdo->prepare(
    "SELECT m.meeting_id, m.meeting_name, m.meeting_date, m.expected_end_date, m.location, m.status
     FROM svmeetings m
     INNER JOIN svmeeting_participants mp ON mp.meeting_id = m.meeting_id AND mp.member_id = :mid
     WHERE m.meeting_date >= :from_dt AND m.meeting_date <= :to_dt
       AND m.status NOT IN ('archived')
     ORDER BY m.meeting_date ASC"
);
$stmt_meetings->execute([':mid' => $member_id, ':from_dt' => $from_dt, ':to_dt' => $to_dt]);
$meetings_as_events = [];
while ($row = $stmt_meetings->fetch()) {
    $meetings_as_events[] = [
        'event_id'            => 'meeting_' . $row['meeting_id'],
        'title'               => $row['meeting_name'] ?: 'Sitzung',
        'description'         => null,
        'location'            => $row['location'],
        'start_datetime'      => $row['meeting_date'],
        'end_datetime'        => $row['expected_end_date'],
        'all_day'             => 0,
        'event_type'          => 'sitzung',
        'recurrence'          => 'none',
        'recurrence_end_date' => null,
        'target_type'         => 'all',
        'target_roles'        => null,
        'created_by_member_id'=> 0,
        'meeting_id'          => $row['meeting_id'],
        'status'              => 'active',
        '_from_meetings'      => true,
    ];
}

// Alle Ereignisse zusammenführen und nochmals sortieren
$all_events = array_merge($expanded_events, $meetings_as_events);
usort($all_events, function ($a, $b) {
    return strcmp($a['start_datetime'], $b['start_datetime']);
});

// Nach Monat gruppieren
$grouped = [];
foreach ($all_events as $ev) {
    $month_key = date('Y-m', strtotime($ev['start_datetime']));
    $grouped[$month_key][] = $ev;
}

// Alle Mitglieder für Teilnehmerauswahl laden
$all_members = get_all_members($pdo);

// Badge-Farben / Labels für event_type
$type_styles = [
    'sitzung'     => ['label' => 'Sitzung',     'bg' => '#1a73e8', 'color' => '#fff'],
    'treffen'     => ['label' => 'Treffen',      'bg' => '#2e7d32', 'color' => '#fff'],
    'abwesenheit' => ['label' => 'Abwesenheit',  'bg' => '#e65100', 'color' => '#fff'],
    'frist'       => ['label' => 'Frist',        'bg' => '#c62828', 'color' => '#fff'],
    'sonstiges'   => ['label' => 'Sonstiges',    'bg' => '#616161', 'color' => '#fff'],
];

$month_names_de = [
    '01' => 'Januar', '02' => 'Februar', '03' => 'März',    '04' => 'April',
    '05' => 'Mai',    '06' => 'Juni',    '07' => 'Juli',     '08' => 'August',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
];

// Kann der aktuelle User Termine erstellen?
$can_create = ($member_role !== 'mitglied');
?>

<!-- ============================================================
     FLASH-MESSAGES
     ============================================================ -->
<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success" style="background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:12px 16px;border-radius:6px;margin-bottom:16px;">
        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger" style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:12px 16px;border-radius:6px;margin-bottom:16px;">
        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- ============================================================
     HEADER-ZEILE
     ============================================================ -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <h2 style="margin:0;font-size:1.4rem;">📅 Kalender – nächste 90 Tage</h2>
    <?php if ($can_create): ?>
        <button class="btn-primary" onclick="openCreateModal()"
                style="padding:8px 18px;font-size:0.95rem;cursor:pointer;">
            + Termin hinzufügen
        </button>
    <?php endif; ?>
</div>

<!-- ============================================================
     EREIGNISLISTE (gruppiert nach Monat)
     ============================================================ -->
<?php if (empty($all_events)): ?>
    <p style="color:#666;padding:20px 0;">Keine Termine in den nächsten 90 Tagen gefunden.</p>
<?php else: ?>
    <?php foreach ($grouped as $month_key => $month_events): ?>
        <?php
        [$year, $month_num] = explode('-', $month_key);
        $month_label = ($month_names_de[$month_num] ?? $month_num) . ' ' . $year;
        ?>
        <h3 style="margin:24px 0 10px;font-size:1.1rem;color:#333;border-bottom:2px solid #e0e0e0;padding-bottom:6px;">
            <?php echo htmlspecialchars($month_label); ?>
        </h3>

        <?php foreach ($month_events as $ev): ?>
            <?php
            $type   = $ev['event_type'] ?? 'sonstiges';
            $style  = $type_styles[$type] ?? $type_styles['sonstiges'];
            $start  = strtotime($ev['start_datetime']);
            $is_creator = ((int)$ev['created_by_member_id'] === $member_id);
            $can_edit   = ($is_creator || $is_admin) && empty($ev['_from_meetings']);
            $can_delete = $can_edit;
            ?>
            <div class="opinion-card" style="display:flex;gap:14px;align-items:flex-start;padding:12px 16px;margin-bottom:8px;border-radius:8px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                <!-- Datum-Block -->
                <div style="min-width:52px;text-align:center;padding-top:2px;">
                    <div style="font-size:1.5rem;font-weight:700;line-height:1;color:#333;">
                        <?php echo date('d', $start); ?>
                    </div>
                    <div style="font-size:0.75rem;color:#888;text-transform:uppercase;">
                        <?php echo $month_names_de[date('m', $start)] ?? date('M', $start); ?>
                    </div>
                </div>

                <!-- Inhalt -->
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:4px;">
                        <!-- Typ-Badge -->
                        <span style="background:<?php echo $style['bg']; ?>;color:<?php echo $style['color']; ?>;padding:2px 8px;border-radius:20px;font-size:0.78rem;font-weight:600;white-space:nowrap;">
                            <?php echo htmlspecialchars($style['label']); ?>
                        </span>
                        <!-- Titel -->
                        <strong style="font-size:1rem;color:#222;">
                            <?php echo htmlspecialchars($ev['title']); ?>
                        </strong>
                    </div>

                    <!-- Zeit & Ort -->
                    <div style="font-size:0.88rem;color:#666;">
                        <?php if ($ev['all_day']): ?>
                            Ganztägig
                        <?php else: ?>
                            <?php echo date('H:i', $start); ?>
                            <?php if (!empty($ev['end_datetime'])): ?>
                                – <?php echo date('H:i', strtotime($ev['end_datetime'])); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($ev['location'])): ?>
                            &nbsp;·&nbsp; 📍 <?php echo htmlspecialchars($ev['location']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Aktions-Buttons -->
                <?php if ($can_edit || $can_delete): ?>
                    <div style="display:flex;gap:6px;flex-shrink:0;align-items:center;">
                        <?php if ($can_edit): ?>
                            <button class="btn-secondary"
                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($ev), ENT_QUOTES); ?>)"
                                    style="padding:5px 12px;font-size:0.83rem;cursor:pointer;">
                                ✏️ Bearbeiten
                            </button>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                            <form method="post" action="process_kalender.php"
                                  onsubmit="return confirm('Termin wirklich löschen?');"
                                  style="margin:0;">
                                <input type="hidden" name="action"   value="delete_event">
                                <input type="hidden" name="event_id" value="<?php echo (int)$ev['event_id']; ?>">
                                <button type="submit"
                                        style="padding:5px 12px;font-size:0.83rem;cursor:pointer;background:#c62828;color:#fff;border:none;border-radius:4px;">
                                    🗑
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>


<!-- ============================================================
     MODAL: TERMIN ERSTELLEN / BEARBEITEN
     ============================================================ -->
<div id="kalender-modal-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;overflow-y:auto;padding:20px 16px;">
    <div style="background:#fff;border-radius:10px;max-width:640px;margin:0 auto;padding:28px 24px;position:relative;">
        <h3 id="kalender-modal-title" style="margin:0 0 20px;font-size:1.2rem;">Termin erstellen</h3>

        <form id="kalender-form" method="post" action="process_kalender.php">
            <input type="hidden" name="action"   id="form-action"   value="create_event">
            <input type="hidden" name="event_id" id="form-event-id" value="">

            <!-- Titel -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Titel *</label>
                <input type="text" name="title" id="form-title" required maxlength="200"
                       style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;">
            </div>

            <!-- Beschreibung -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Beschreibung</label>
                <textarea name="description" id="form-description" rows="3"
                          style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;resize:vertical;"></textarea>
            </div>

            <!-- Ort -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Ort</label>
                <input type="text" name="location" id="form-location" maxlength="200"
                       style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;">
            </div>

            <!-- Typ -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Typ</label>
                <select name="event_type" id="form-event-type"
                        style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                    <option value="sitzung">Sitzung</option>
                    <option value="treffen">Treffen</option>
                    <option value="abwesenheit">Abwesenheit</option>
                    <option value="frist">Frist</option>
                    <option value="sonstiges" selected>Sonstiges</option>
                </select>
            </div>

            <!-- Ganztags -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                    <input type="checkbox" name="all_day" id="form-all-day" value="1"
                           onchange="toggleAllDay(this)">
                    Ganztägig
                </label>
            </div>

            <!-- Datum von / bis -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div class="form-group">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Beginn *</label>
                    <input type="datetime-local" name="start_datetime" id="form-start" required
                           style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;">
                </div>
                <div class="form-group" id="form-end-wrapper">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Ende</label>
                    <input type="datetime-local" name="end_datetime" id="form-end"
                           style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;">
                </div>
                <div class="form-group" id="form-allday-date-wrapper" style="display:none;">
                    <label style="display:block;font-weight:600;margin-bottom:4px;">Datum *</label>
                    <input type="date" name="start_date_only" id="form-start-date"
                           style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;">
                </div>
            </div>

            <!-- Wiederholung -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Wiederholung</label>
                <select name="recurrence" id="form-recurrence"
                        style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;"
                        onchange="toggleRecurrenceEnd(this)">
                    <option value="none" selected>Keine</option>
                    <option value="weekly">Wöchentlich</option>
                    <option value="biweekly">Zweiwöchentlich</option>
                    <option value="monthly">Monatlich</option>
                    <option value="yearly">Jährlich</option>
                </select>
            </div>
            <div class="form-group" id="form-rec-end-wrapper" style="display:none;margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Wiederholung bis</label>
                <input type="date" name="recurrence_end_date" id="form-rec-end"
                       style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;">
            </div>

            <!-- Zielgruppe -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Zielgruppe</label>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="radio" name="target_type" value="all" checked
                               onchange="toggleTargetType(this)"> Alle
                    </label>
                    <?php if ($is_admin): ?>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="radio" name="target_type" value="roles"
                               onchange="toggleTargetType(this)"> Nur bestimmte Rollen
                    </label>
                    <?php endif; ?>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="radio" name="target_type" value="list"
                               onchange="toggleTargetType(this)"> Ausgewählte Mitglieder
                    </label>
                </div>
            </div>

            <!-- Rollen-Auswahl (nur für Admins) -->
            <?php if ($is_admin): ?>
            <div id="form-roles-wrapper" style="display:none;margin-bottom:14px;padding:12px;background:#f5f5f5;border-radius:6px;">
                <label style="display:block;font-weight:600;margin-bottom:8px;">Rollen auswählen:</label>
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    <?php foreach (['vorstand','gf','assistenz'] as $role_opt): ?>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" name="target_roles[]" value="<?php echo $role_opt; ?>">
                            <?php echo htmlspecialchars(ucfirst($role_opt)); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Mitglieder-Auswahl -->
            <div id="form-list-wrapper" style="display:none;margin-bottom:14px;padding:12px;background:#f5f5f5;border-radius:6px;">
                <label style="display:block;font-weight:600;margin-bottom:8px;">Mitglieder auswählen:</label>
                <select name="participant_ids[]" id="form-participants" multiple
                        style="width:100%;min-height:120px;border:1px solid #ccc;border-radius:4px;padding:4px;">
                    <?php foreach ($all_members as $m): ?>
                        <?php if ((int)$m['member_id'] !== $member_id): ?>
                            <option value="<?php echo (int)$m['member_id']; ?>">
                                <?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <small style="color:#666;display:block;margin-top:4px;">
                    Mehrfachauswahl: Strg+Klick (Windows) oder Cmd+Klick (Mac)
                </small>
            </div>

            <!-- Aktions-Buttons -->
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="btn-secondary"
                        onclick="closeKalenderModal()"
                        style="padding:9px 20px;cursor:pointer;">
                    Abbrechen
                </button>
                <button type="submit" class="btn-primary"
                        style="padding:9px 20px;cursor:pointer;">
                    Speichern
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
function openCreateModal() {
    document.getElementById('kalender-modal-title').textContent = 'Termin erstellen';
    document.getElementById('form-action').value   = 'create_event';
    document.getElementById('form-event-id').value = '';
    document.getElementById('kalender-form').reset();
    // Sektionen zurücksetzen
    document.getElementById('form-rec-end-wrapper').style.display = 'none';
    <?php if ($is_admin): ?>
    document.getElementById('form-roles-wrapper').style.display = 'none';
    <?php endif; ?>
    document.getElementById('form-list-wrapper').style.display   = 'none';
    // Ganztags-Felder zurücksetzen
    document.getElementById('form-end-wrapper').style.display = '';
    document.getElementById('form-allday-date-wrapper').style.display = 'none';

    document.getElementById('kalender-modal-overlay').style.display = 'block';
}

function openEditModal(event) {
    document.getElementById('kalender-modal-title').textContent = 'Termin bearbeiten';
    document.getElementById('form-action').value   = 'update_event';
    document.getElementById('form-event-id').value = event.event_id;

    document.getElementById('form-title').value       = event.title        || '';
    document.getElementById('form-description').value = event.description  || '';
    document.getElementById('form-location').value    = event.location     || '';
    document.getElementById('form-event-type').value  = event.event_type   || 'sonstiges';
    document.getElementById('form-recurrence').value  = event.recurrence   || 'none';

    // Ganztags
    var allDay = event.all_day == 1;
    document.getElementById('form-all-day').checked = allDay;
    if (allDay) {
        document.getElementById('form-end-wrapper').style.display         = 'none';
        document.getElementById('form-allday-date-wrapper').style.display = '';
        if (event.start_datetime) {
            document.getElementById('form-start-date').value = event.start_datetime.substring(0,10);
        }
        document.getElementById('form-start').removeAttribute('required');
    } else {
        document.getElementById('form-end-wrapper').style.display         = '';
        document.getElementById('form-allday-date-wrapper').style.display = 'none';
        document.getElementById('form-start').setAttribute('required','required');
        if (event.start_datetime) {
            document.getElementById('form-start').value = event.start_datetime.replace(' ','T').substring(0,16);
        }
        if (event.end_datetime) {
            document.getElementById('form-end').value = event.end_datetime.replace(' ','T').substring(0,16);
        }
    }

    // Wiederholung bis
    if (event.recurrence && event.recurrence !== 'none') {
        document.getElementById('form-rec-end-wrapper').style.display = '';
        document.getElementById('form-rec-end').value = event.recurrence_end_date || '';
    } else {
        document.getElementById('form-rec-end-wrapper').style.display = 'none';
    }

    // Zielgruppe setzen
    var targetType = event.target_type || 'all';
    var radios = document.querySelectorAll('input[name="target_type"]');
    radios.forEach(function(r) { r.checked = (r.value === targetType); });

    <?php if ($is_admin): ?>
    document.getElementById('form-roles-wrapper').style.display =
        (targetType === 'roles') ? '' : 'none';
    if (targetType === 'roles' && event.target_roles) {
        var selRoles = event.target_roles.split(',').map(function(s){ return s.trim(); });
        document.querySelectorAll('input[name="target_roles[]"]').forEach(function(cb){
            cb.checked = selRoles.includes(cb.value);
        });
    }
    <?php endif; ?>
    document.getElementById('form-list-wrapper').style.display =
        (targetType === 'list') ? '' : 'none';

    document.getElementById('kalender-modal-overlay').style.display = 'block';
}

function closeKalenderModal() {
    document.getElementById('kalender-modal-overlay').style.display = 'none';
}

// Klick auf Overlay schließt Modal
document.getElementById('kalender-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeKalenderModal();
});

function toggleAllDay(cb) {
    if (cb.checked) {
        document.getElementById('form-end-wrapper').style.display         = 'none';
        document.getElementById('form-allday-date-wrapper').style.display = '';
        document.getElementById('form-start').removeAttribute('required');
    } else {
        document.getElementById('form-end-wrapper').style.display         = '';
        document.getElementById('form-allday-date-wrapper').style.display = 'none';
        document.getElementById('form-start').setAttribute('required','required');
    }
}

function toggleRecurrenceEnd(sel) {
    document.getElementById('form-rec-end-wrapper').style.display =
        (sel.value !== 'none') ? '' : 'none';
}

function toggleTargetType(radio) {
    <?php if ($is_admin): ?>
    document.getElementById('form-roles-wrapper').style.display =
        (radio.value === 'roles') ? '' : 'none';
    <?php endif; ?>
    document.getElementById('form-list-wrapper').style.display =
        (radio.value === 'list') ? '' : 'none';
}
</script>
