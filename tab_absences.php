<?php
/**
 * tab_absences.php - Abwesenheitsverwaltung für Führungsteam
 * Nur Vorstand, GF, Assistenz und Führungsteam können Abwesenheiten eintragen
 */

require_once 'functions.php';
require_login();

$current_user = get_current_member();
$currentMemberID = $_SESSION['member_id'] ?? 0;

// Berechtigung prüfen: Nur Führungsteam
$leadership_roles = ['vorstand', 'gf', 'assistenz', 'fuehrungsteam', 'Vorstand', 'Geschäftsführung', 'Assistenz', 'Führungsteam'];
$is_leadership = in_array($current_user['role'], $leadership_roles);

if (!$is_leadership) {
    die('<div class="error">Zugriff verweigert. Nur Führungsteam hat Zugriff auf diese Funktion.</div>');
}

// Alle Führungsteam-Mitglieder laden (für Vertretungs-Auswahl)
$all_members = get_all_members($pdo);
$leadership_members = array_filter($all_members, function($m) use ($leadership_roles) {
    return in_array($m['role'], $leadership_roles);
});

// Eigene Abwesenheiten laden
$stmt = $pdo->prepare("
    SELECT a.*,
           m1.first_name as member_first_name, m1.last_name as member_last_name,
           m2.first_name as substitute_first_name, m2.last_name as substitute_last_name
    FROM absences a
    LEFT JOIN members m1 ON a.member_id = m1.member_id
    LEFT JOIN members m2 ON a.substitute_member_id = m2.member_id
    WHERE a.member_id = ?
    ORDER BY a.start_date DESC
");
$stmt->execute([$currentMemberID]);
$my_absences = $stmt->fetchAll();

// Alle aktuellen/zukünftigen Abwesenheiten (für Übersicht)
$stmt = $pdo->query("
    SELECT a.*,
           m1.first_name as member_first_name, m1.last_name as member_last_name,
           m2.first_name as substitute_first_name, m2.last_name as substitute_last_name
    FROM absences a
    LEFT JOIN members m1 ON a.member_id = m1.member_id
    LEFT JOIN members m2 ON a.substitute_member_id = m2.member_id
    WHERE a.end_date >= CURDATE()
    ORDER BY a.start_date ASC
");
$current_absences = $stmt->fetchAll();
?>

<div class="content">
    <h2>🏖️ Abwesenheitsverwaltung</h2>

    <p>Hier können Sie Ihre Abwesenheitszeiten eintragen. Diese werden automatisch allen Mitgliedern angezeigt.</p>

    <!-- Neue Abwesenheit eintragen -->
    <div class="card">
        <h3>Neue Abwesenheit eintragen</h3>
        <form method="post" action="process_absences.php" class="absence-form">
            <input type="hidden" name="action" value="create">

            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Von *</label>
                    <input type="date" id="start_date" name="start_date" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="end_date">Bis *</label>
                    <input type="date" id="end_date" name="end_date" required class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label for="reason">Grund (optional)</label>
                <input type="text" id="reason" name="reason"
                       placeholder="z.B. Urlaub, Dienstreise, Konferenz..."
                       class="form-control">
            </div>

            <div class="form-group">
                <label for="substitute_member_id">Vertretung durch (optional)</label>
                <select id="substitute_member_id" name="substitute_member_id" class="form-control">
                    <option value="">-- Keine Vertretung --</option>
                    <?php foreach ($leadership_members as $member): ?>
                        <?php if ($member['member_id'] != $currentMemberID): ?>
                            <option value="<?= $member['member_id'] ?>">
                                <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Abwesenheit eintragen</button>
        </form>
    </div>

    <!-- Meine Abwesenheiten -->
    <h3 class="mt-30">Meine eingetragenen Abwesenheiten</h3>

    <?php if (empty($my_absences)): ?>
        <div class="info-box">Sie haben noch keine Abwesenheiten eingetragen.</div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Von</th>
                    <th>Bis</th>
                    <th>Dauer</th>
                    <th>Grund</th>
                    <th>Vertretung</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_absences as $absence): ?>
                    <?php
                    $start = new DateTime($absence['start_date']);
                    $end = new DateTime($absence['end_date']);
                    $is_past = $end < new DateTime();
                    $row_class = $is_past ? 'past-absence' : 'current-absence';

                    $interval = $start->diff($end);
                    $duration_days = $interval->days + 1;
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td><?= $start->format('d.m.Y') ?></td>
                        <td><?= $end->format('d.m.Y') ?></td>
                        <td><?= $duration_days ?> Tag<?= $duration_days > 1 ? 'e' : '' ?></td>
                        <td><?= $absence['reason'] ? htmlspecialchars($absence['reason']) : '<em>-</em>' ?></td>
                        <td>
                            <?php if ($absence['substitute_member_id']): ?>
                                <?= htmlspecialchars($absence['substitute_first_name'] . ' ' . $absence['substitute_last_name']) ?>
                            <?php else: ?>
                                <em>-</em>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if (!$is_past): ?>
                                <a href="?tab=absences&edit=<?= $absence['absence_id'] ?>"
                                   class="btn-small btn-secondary">✏️ Bearbeiten</a>
                                <form method="post" action="process_absences.php" style="display:inline;"
                                      onsubmit="return confirm('Wirklich löschen?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="absence_id" value="<?= $absence['absence_id'] ?>">
                                    <button type="submit" class="btn-small btn-danger">🗑️ Löschen</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Vergangen</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <!-- Bearbeiten-Formular (wenn ?edit= Parameter) -->
    <?php if (isset($_GET['edit'])): ?>
        <?php
        $edit_id = intval($_GET['edit']);
        $stmt = $pdo->prepare("SELECT * FROM absences WHERE absence_id = ? AND member_id = ?");
        $stmt->execute([$edit_id, $currentMemberID]);
        $edit_absence = $stmt->fetch();

        if ($edit_absence):
        ?>
            <div class="card edit-form">
                <h3>Abwesenheit bearbeiten</h3>
                <form method="post" action="process_absences.php" class="absence-form">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="absence_id" value="<?= $edit_absence['absence_id'] ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_start_date">Von *</label>
                            <input type="date" id="edit_start_date" name="start_date"
                                   value="<?= $edit_absence['start_date'] ?>" required class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="edit_end_date">Bis *</label>
                            <input type="date" id="edit_end_date" name="end_date"
                                   value="<?= $edit_absence['end_date'] ?>" required class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_reason">Grund (optional)</label>
                        <input type="text" id="edit_reason" name="reason"
                               value="<?= htmlspecialchars($edit_absence['reason'] ?? '') ?>"
                               class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="edit_substitute">Vertretung durch (optional)</label>
                        <select id="edit_substitute" name="substitute_member_id" class="form-control">
                            <option value="">-- Keine Vertretung --</option>
                            <?php foreach ($leadership_members as $member): ?>
                                <?php if ($member['member_id'] != $currentMemberID): ?>
                                    <option value="<?= $member['member_id'] ?>"
                                            <?= $edit_absence['substitute_member_id'] == $member['member_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                    <a href="?tab=absences" class="btn btn-secondary">Abbrechen</a>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Übersicht aller aktuellen Abwesenheiten -->
    <h3 class="mt-30">Aktuelle & zukünftige Abwesenheiten (alle)</h3>

    <?php if (empty($current_absences)): ?>
        <div class="info-box">Derzeit keine Abwesenheiten eingetragen.</div>
    <?php else: ?>
        <!-- Desktop-Tabelle -->
        <div class="table-responsive desktop-only">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Person</th>
                    <th>Von</th>
                    <th>Bis</th>
                    <th>Grund</th>
                    <th>Vertretung</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current_absences as $absence): ?>
                    <?php
                    $start = new DateTime($absence['start_date']);
                    $end = new DateTime($absence['end_date']);
                    $today = new DateTime();
                    $is_current = $start <= $today && $end >= $today;
                    ?>
                    <tr class="<?= $is_current ? 'highlight' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($absence['member_first_name'] . ' ' . $absence['member_last_name']) ?></strong>
                            <?php if ($is_current): ?>
                                <span class="badge badge-warning">Aktuell abwesend</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $start->format('d.m.Y') ?></td>
                        <td><?= $end->format('d.m.Y') ?></td>
                        <td><?= $absence['reason'] ? htmlspecialchars($absence['reason']) : '<em>-</em>' ?></td>
                        <td>
                            <?php if ($absence['substitute_member_id']): ?>
                                <?= htmlspecialchars($absence['substitute_first_name'] . ' ' . $absence['substitute_last_name']) ?>
                            <?php else: ?>
                                <em>-</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile-Ansicht: Kompakte Karten -->
        <div class="mobile-only">
            <?php foreach ($current_absences as $absence): ?>
                <?php
                $start = new DateTime($absence['start_date']);
                $end = new DateTime($absence['end_date']);
                $today = new DateTime();
                $is_current = $start <= $today && $end >= $today;
                ?>
                <div class="absence-card <?= $is_current ? 'highlight' : '' ?>">
                    <div class="absence-card-header">
                        <strong><?= htmlspecialchars($absence['member_first_name'] . ' ' . $absence['member_last_name']) ?></strong>
                        <?php if ($is_current): ?>
                            <span class="badge badge-warning">Aktuell</span>
                        <?php endif; ?>
                    </div>
                    <div class="absence-card-body">
                        <div class="absence-info">
                            <span class="label">Zeitraum:</span>
                            <?= $start->format('d.m.Y') ?> - <?= $end->format('d.m.Y') ?>
                        </div>
                        <?php if ($absence['reason']): ?>
                        <div class="absence-info">
                            <span class="label">Grund:</span>
                            <?= htmlspecialchars($absence['reason']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($absence['substitute_member_id']): ?>
                        <div class="absence-info">
                            <span class="label">Vertretung:</span>
                            <?= htmlspecialchars($absence['substitute_first_name'] . ' ' . $absence['substitute_last_name']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.absence-form {
    max-width: 600px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

/* Responsive Table Wrapper */
.table-responsive {
    width: 100%;
    max-width: calc(100vw - 40px);
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    display: block;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    min-width: 500px;
}

.data-table th,
.data-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.data-table th {
    background: #f5f5f5;
    font-weight: 600;
}

.data-table tr.past-absence {
    opacity: 0.6;
}

.data-table tr.highlight {
    background: #fff3cd;
}

.actions {
    white-space: nowrap;
}

.btn-small {
    padding: 5px 10px;
    font-size: 0.875rem;
    margin-right: 5px;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}

.badge-warning {
    background: #ff9800;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.edit-form {
    border: 2px solid #5568d3;
    margin-top: 20px;
}

.mt-30 {
    margin-top: 30px;
}

/* Desktop/Mobile Sichtbarkeit */
.mobile-only {
    display: none;
}

.desktop-only {
    display: block;
}

@media (max-width: 767px) {
    .mobile-only {
        display: block;
    }
    .desktop-only {
        display: none;
    }
}

/* Absence Cards für Mobile */
.absence-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
}

.absence-card.highlight {
    background: #fff3cd;
    border-color: #ffc107;
}

.absence-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.absence-card-body {
    font-size: 14px;
}

.absence-info {
    margin-bottom: 4px;
}

.absence-info .label {
    font-weight: 600;
    color: #666;
}
</style>

<script>
// Validierung: End-Date muss >= Start-Date sein
document.addEventListener('DOMContentLoaded', function() {
    const startInputs = document.querySelectorAll('input[name="start_date"]');
    const endInputs = document.querySelectorAll('input[name="end_date"]');

    startInputs.forEach((startInput, index) => {
        const endInput = endInputs[index];
        if (!endInput) return;

        startInput.addEventListener('change', function() {
            endInput.min = this.value;
            if (endInput.value && endInput.value < this.value) {
                endInput.value = this.value;
            }
        });

        // Initial setzen
        if (startInput.value) {
            endInput.min = startInput.value;
        }
    });
});
</script>
