<?php
/**
 * tab_proposals.php - Antragsverwaltung Tab
 * Eingebunden in index.php für konsistente Darstellung
 */

// Berechtigte-Daten für aktiv-Wert laden
$user_berecht_stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
$user_berecht_stmt->execute([$current_user['member_id']]);
$user_berecht = $user_berecht_stmt->fetch();
$user_aktiv = $user_berecht['aktiv'] ?? 0;

// Berechtigungen prüfen
$kann_intern_sehen = ($user_aktiv > 17 || $user_berecht['Funktion'] === 'VA' || ($current_user['is_admin'] ?? 0) == 1);
$ist_admin = ($user_aktiv >= 19 || ($current_user['is_admin'] ?? 0) == 1);

// POST-Verarbeitung für permanentes Löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_permanent']) && $ist_admin) {
    $antrnr_to_delete = $_POST['antrnr'] ?? '';

    // Nur X und Z Anträge dürfen permanent gelöscht werden
    if ($antrnr_to_delete && (substr($antrnr_to_delete, 0, 1) === 'X' || substr($antrnr_to_delete, 0, 1) === 'Z')) {
        $delete_stmt = $pdo->prepare("DELETE FROM antraege WHERE antrnr = ?");
        $delete_stmt->execute([$antrnr_to_delete]);

        header('Location: index.php?tab=proposals&show_deleted=1&msg=deleted');
        exit;
    }
}

// GET-Parameter für Filterung
$filter_status = $_GET['status'] ?? 'all';
$filter_prefix = $_GET['prefix'] ?? 'all';
$filter_bart = $_GET['bart'] ?? 'all';
$search = $_GET['search'] ?? '';
$show_deleted = isset($_GET['show_deleted']) && $ist_admin;

// SQL-Abfrage für offene Anträge
$sql = "SELECT a.*, b.Vorname, b.Name, b.KurzN FROM antraege a
        LEFT JOIN berechtigte b ON a.antrst = b.ID
        WHERE 1=1";

$params = [];

// Präfix-Filter
if ($show_deleted) {
    $sql .= " AND (a.antrnr LIKE 'X%' OR a.antrnr LIKE 'Z%')";
} else {
    if ($filter_prefix === 'A') {
        $sql .= " AND a.antrnr LIKE 'A%'";
    } elseif ($filter_prefix === 'B') {
        $sql .= " AND a.antrnr LIKE 'B%'";
    } else {
        // Alle außer VS (beschlossene)
        $sql .= " AND a.antrnr NOT LIKE 'VS%' AND a.antrnr NOT LIKE 'X%' AND a.antrnr NOT LIKE 'Z%'";
    }
}

// Status-Filter (Antragsteller)
if ($filter_status !== 'all') {
    $sql .= " AND a.antrst = ?";
    $params[] = $filter_status;
}

// Bart-Filter
if ($filter_bart !== 'all') {
    $sql .= " AND a.bart = ?";
    $params[] = $filter_bart;
}

// Suchfilter
if ($search) {
    $sql .= " AND (a.antrnr LIKE ? OR a.titel LIKE ? OR a.beschluss LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY a.antrnr DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$antraege = $stmt->fetchAll();

// Liste der Antragsteller für Filter
$antragsteller_stmt = $pdo->query("SELECT DISTINCT a.antrst, b.Vorname, b.Name, b.KurzN
                                   FROM antraege a
                                   JOIN berechtigte b ON a.antrst = b.ID
                                   WHERE a.antrnr NOT LIKE 'VS%'
                                   ORDER BY b.Name");
$antragsteller = $antragsteller_stmt->fetchAll();

// Prüfe offene Abstimmungen für aktuellen User
$pending_votes = [];
$pending_stmt = $pdo->query("SELECT * FROM antraege WHERE antrnr LIKE 'B%'");
$b_antraege = $pending_stmt->fetchAll();
foreach ($b_antraege as $a) {
    for ($i = 1; $i <= 6; $i++) {
        if ($a["VName$i"] == $current_user['member_id'] && empty($a["Votum$i"])) {
            $pending_votes[] = $a;
            break;
        }
    }
}
?>

<style>
    .proposals-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .proposals-actions {
        display: flex;
        gap: 10px;
    }
    .proposals-actions .btn {
        padding: 10px 20px;
        background: #0066cc;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 14px;
        display: inline-block;
    }
    .proposals-actions .btn:hover {
        background: #0052a3;
    }
    .proposals-actions .btn-secondary {
        background: #666;
    }
    .proposals-actions .btn-secondary:hover {
        background: #555;
    }
    .proposals-filters {
        background: white;
        padding: 15px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        align-items: end;
    }
    .proposals-filters select,
    .proposals-filters input[type="text"] {
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
        width: 100%;
    }
    .proposals-filters button,
    .proposals-filters a {
        padding: 8px 12px;
        background: #0066cc;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        text-align: center;
        white-space: nowrap;
    }
    .proposals-filters .filter-actions {
        display: flex;
        gap: 8px;
        grid-column: span 2;
    }
    .proposals-count {
        background: white;
        padding: 15px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        font-weight: 600;
        color: #666;
    }
    .proposals-table-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .proposals-table-container table {
        width: 100%;
        border-collapse: collapse;
    }
    .proposals-table-container th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #dee2e6;
    }
    .proposals-table-container td {
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
    }
    .proposals-table-container tr:hover {
        background: #f8f9fa;
    }
    .antrnr {
        font-weight: 600;
        color: #0066cc;
    }
    .badge {
        display: inline-block;
        padding: 4px 8px;
        font-size: 11px;
        border-radius: 12px;
        font-weight: 500;
    }
    .badge.status {
        background: #e7f3ff;
        color: #0066cc;
    }
    .proposals-empty-state {
        padding: 60px 20px;
        text-align: center;
        color: #999;
    }
    .visibility-hint {
        font-size: 11px;
        margin-top: 4px;
    }
</style>

<div class="proposals-header">
    <h2>Offene Anträge</h2>
    <div class="proposals-actions">
        <a href="abstimmungen.php" class="btn btn-secondary">🗳️ Abstimmungen<?php if (!empty($pending_votes)): ?> <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 5px;"><?= count($pending_votes) ?></span><?php endif; ?></a>
        <a href="beschlussbuch.php" class="btn btn-secondary">📚 Beschlussbuch</a>
        <a href="antrag_neu.php" class="btn">+ Neuer Antrag</a>
    </div>
</div>

<!-- Infokasten -->
<div class="info-box" style="background: #fff3cd; border-left-color: var(--warning); color: #856404;">
    <?php if (!empty($pending_votes)): ?>
        <div style="display: flex; align-items: start; gap: 12px;">
            <div style="font-size: 24px;">⚠️</div>
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 16px; margin-bottom: 8px;">
                    Sie haben <?= count($pending_votes) ?> offene Abstimmung<?= count($pending_votes) > 1 ? 'en' : '' ?>!
                </div>
                <div style="font-size: 14px; margin-bottom: 10px;">
                    Bitte stimmen Sie über folgende Anträge ab:
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($pending_votes as $pv): ?>
                        <a href="abstimmungen.php?antrnr=<?= urlencode($pv['antrnr']) ?>"
                           style="display: inline-block; background: var(--warning); color: #000; padding: 8px 12px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 13px;">
                            → <?= htmlspecialchars($pv['antrnr']) ?>: <?= htmlspecialchars(mb_substr($pv['titel'], 0, 50)) ?><?= mb_strlen($pv['titel']) > 50 ? '...' : '' ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        ℹ️ Keine ausstehenden Abstimmungen. Hier finden Sie alle offenen Anträge.
    <?php endif; ?>
</div>

<form method="GET" class="proposals-filters">
    <input type="hidden" name="tab" value="proposals">

    <select name="status">
        <option value="all">Alle Antragsteller</option>
        <?php foreach ($antragsteller as $ast): ?>
            <option value="<?= htmlspecialchars($ast['antrst']) ?>" <?= $filter_status == $ast['antrst'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ast['KurzN']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="prefix">
        <option value="all" <?= $filter_prefix === 'all' ? 'selected' : '' ?>>Alle Status</option>
        <option value="A" <?= $filter_prefix === 'A' ? 'selected' : '' ?>>A - In Bearbeitung</option>
        <option value="B" <?= $filter_prefix === 'B' ? 'selected' : '' ?>>B - Zur Abstimmung</option>
    </select>

    <select name="bart">
        <option value="all" <?= $filter_bart === 'all' ? 'selected' : '' ?>>Alle Typen</option>
        <option value="V" <?= $filter_bart === 'V' ? 'selected' : '' ?>>V - Verfügung</option>
        <option value="R" <?= $filter_bart === 'R' ? 'selected' : '' ?>>R - Ressortbeschluss</option>
        <option value="B" <?= $filter_bart === 'B' ? 'selected' : '' ?>>B - Vorstandsbeschluss</option>
    </select>

    <input type="text" name="search" placeholder="Suche..." value="<?= htmlspecialchars($search) ?>">

    <div class="filter-actions">
        <button type="submit">Filtern</button>
        <a href="?tab=proposals" style="padding: 8px 12px; background: #666; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">Zurücksetzen</a>
        <?php if ($ist_admin): ?>
            <a href="?tab=proposals&show_deleted=1" style="padding: 8px 12px; background: <?= $show_deleted ? '#dc3545' : '#666' ?>; color: white; text-decoration: none; border-radius: 4px; font-weight: <?= $show_deleted ? '600' : 'normal' ?>; display: inline-block;">
                <?= $show_deleted ? '✓ ' : '' ?>Gelöschte
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="proposals-count">
    <?= count($antraege) ?> Anträge gefunden
</div>

<div class="proposals-table-container">
    <?php if (empty($antraege)): ?>
        <div class="proposals-empty-state">
            Keine Anträge gefunden.
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nummer</th>
                    <th>Titel</th>
                    <th>Antragsteller</th>
                    <th>Typ</th>
                    <th>Zuletzt geändert</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($antraege as $a):
                    $prefix_a = substr($a['antrnr'], 0, 1);
                    $bart_text = ['V' => 'Verfügung', 'R' => 'Ressort', 'B' => 'Vorstand'];
                ?>
                    <tr>
                        <td>
                            <span class="antrnr"><?= htmlspecialchars($a['antrnr']) ?></span>
                            <?php if ($a['int_ext'] === 'i'): ?>
                                <div class="visibility-hint" style="color: #d32f2f;">🔒 Vorstandsintern</div>
                            <?php elseif ($a['int_ext'] === 'n'): ?>
                                <div class="visibility-hint" style="color: #f57c00;">👥 Nicht öffentlich</div>
                            <?php endif; ?>
                            <?php
                            if ($prefix_a === 'B' || $prefix_a === 'V') {
                                if (preg_match('/^[BV](\d{6})/', $a['antrnr'], $matches)) {
                                    $datum_str = $matches[1];
                                    $datum = '20' . substr($datum_str, 0, 2) . '-' . substr($datum_str, 2, 2) . '-' . substr($datum_str, 4, 2);
                                    echo '<div class="visibility-hint" style="color: #0066cc;">📅 Finalisiert: ' . date('d.m.Y', strtotime($datum)) . '</div>';
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($a['titel']) ?></strong>
                            <?php if ($a['fin'] > 0): ?>
                                <div style="font-size: 12px; color: #856404; margin-top: 4px;">
                                    💰 <?= number_format($a['fin'], 0, ',', '.') ?> €
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($a['KurzN']) ?></td>
                        <td>
                            <?php if ($a['bart']): ?>
                                <span class="badge status"><?= htmlspecialchars($bart_text[$a['bart']] ?? $a['bart']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $a['lzugriff'] ? date('d.m.Y H:i', strtotime($a['lzugriff'])) : '-' ?></td>
                        <td style="white-space: nowrap;">
                            <a href="antrag_ansehen.php?antrnr=<?= urlencode($a['antrnr']) ?>"
                               class="btn btn-secondary"
                               style="padding: 6px 12px; font-size: 13px; display: inline-block;">
                                👁️ Ansehen
                            </a>
                            <a href="antrag_bearbeiten.php?antrnr=<?= urlencode($a['antrnr']) ?>"
                               class="btn"
                               style="padding: 6px 12px; font-size: 13px; margin-left: 10px; display: inline-block;">
                                ✏️ Bearbeiten
                            </a>
                            <?php if ($show_deleted && ($prefix_a === 'X' || $prefix_a === 'Z')): ?>
                                <form method="POST" style="display: inline-block; margin-left: 10px;">
                                    <input type="hidden" name="antrnr" value="<?= htmlspecialchars($a['antrnr']) ?>">
                                    <button type="submit" name="delete_permanent" value="1"
                                            class="btn"
                                            style="padding: 6px 12px; font-size: 13px; background: #dc3545;"
                                            onclick="return confirm('Antrag PERMANENT löschen? Dies kann nicht rückgängig gemacht werden!');">
                                        🗑️ Endgültig löschen
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
