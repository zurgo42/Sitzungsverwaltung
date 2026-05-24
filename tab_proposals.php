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
?>

<style>
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
    .proposals-main-heading {
        color: #333;
    }
    .proposals-sub-heading {
        color: #333;
    }
    .in-voting-row {
        background: rgba(250, 170, 0, 0.15) !important;
    }
    .in-voting-row:hover {
        background: rgba(250, 170, 0, 0.25) !important;
    }
    .deleted-row {
        background: #ffe0e0 !important;
        opacity: 0.7;
    }

    /* Dark Mode Anpassungen */
    body.dark-mode .proposals-filters {
        background: #2d2d2d;
        border-color: #444;
    }
    body.dark-mode .proposals-filters select,
    body.dark-mode .proposals-filters input[type="text"] {
        background: #1a1a1a;
        color: #e0e0e0;
        border-color: #444;
    }
    body.dark-mode .proposals-filters button,
    body.dark-mode .proposals-filters a {
        background: #0066cc !important;
        color: white !important;
        border: 1px solid #0066cc !important;
    }
    body.dark-mode .proposals-filters button:hover,
    body.dark-mode .proposals-filters a:hover {
        background: #0052a3 !important;
    }
    body.dark-mode .proposals-count {
        background: #2d2d2d;
        color: #e0e0e0;
    }
    body.dark-mode .proposals-table-container {
        background: #2d2d2d;
    }
    body.dark-mode .proposals-table-container th {
        background: #1a1a1a;
        color: #e0e0e0;
        border-color: #444;
    }
    body.dark-mode .proposals-table-container td {
        border-color: #444;
        color: #e0e0e0;
    }
    body.dark-mode .proposals-table-container tr:hover {
        background: #333;
    }
    body.dark-mode .antrnr {
        color: #64b5f6 !important;
    }
    body.dark-mode .visibility-hint {
        color: #e0e0e0 !important;
    }
    body.dark-mode .proposals-main-heading,
    body.dark-mode .proposals-sub-heading {
        color: #e0e0e0 !important;
    }

    /* Mobile Optimierung */
    @media (max-width: 768px) {
        /* Benachrichtigungs-Box (gelber Kasten) */
        div[style*="background: #f9f9f9"] {
            padding: 8px 10px !important;
            margin-left: -10px !important;
            margin-right: -10px !important;
            border-radius: 0 !important;
            overflow-x: auto;
            word-wrap: break-word;
        }
        div[style*="background: #f9f9f9"] span {
            font-size: 12px !important;
            line-height: 1.6;
        }
        div[style*="background: #f9f9f9"] a[style*="background: #ffc107"],
        div[style*="background: var(--warning)"] {
            display: block !important;
            margin: 5px 0 !important;
            margin-left: 0 !important;
            padding: 6px 8px !important;
            font-size: 11px !important;
            text-align: center;
            white-space: normal !important;
            word-break: break-word;
        }
        .kommende-termine-break::after {
            content: "\A";
            white-space: pre;
        }

        /* Proposals Filter */
        .proposals-filters {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .proposals-filters .filter-actions {
            grid-column: span 1;
            flex-direction: column;
        }
        .proposals-filters button,
        .proposals-filters a {
            width: 100%;
        }
    }
</style>

<!-- BENACHRICHTIGUNGEN -->
<?php
require_once 'module_notifications.php';
render_user_notifications($pdo, $current_user['member_id']);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h2 style="margin: 0;" class="proposals-main-heading">📋 Anträge/Beschlüsse verwalten</h2>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="abstimmungen.php" style="padding: 6px 12px; background: #0066cc; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; display: inline-block; white-space: nowrap;">🗳️ Abstimmungen</a>
        <a href="beschlussbuch.php" style="padding: 6px 12px; background: #0066cc; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; display: inline-block; white-space: nowrap;">📚 Beschlussbuch</a>
        <a href="antrag_neu.php" style="padding: 6px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; display: inline-block; white-space: nowrap;">+ Neuer Antrag</a>
    </div>
</div>

<h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px;" class="proposals-sub-heading">Offene Anträge</h3>

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
        <?php
        $bart_config = $GLOBALS['bart_config'] ?? lade_antragstypen_config($pdo);
        $aktive_typen = get_aktive_typen($bart_config);
        foreach ($aktive_typen as $typ => $bezeichnung):
        ?>
            <option value="<?= $typ ?>" <?= $filter_bart === $typ ? 'selected' : '' ?>><?= htmlspecialchars($typ . ' - ' . $bezeichnung) ?></option>
        <?php endforeach; ?>
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

<?php if (empty($antraege)): ?>
    <div class="proposals-table-container">
        <div class="proposals-empty-state">
            Keine Anträge gefunden.
        </div>
    </div>
<?php else: ?>
    <!-- Card-Layout (responsive, funktioniert auf Desktop und Mobile) -->
    <?php
    // Typ-Bezeichnungen aus Config
    $bart_config = $GLOBALS['bart_config'] ?? lade_antragstypen_config($pdo);
    $bart_bezeichnungen = [];
    foreach (['V', 'R', 'B'] as $typ) {
        $bart_bezeichnungen[$typ] = get_typ_bezeichnung($typ, $bart_config);
    }

    foreach ($antraege as $a):
        $prefix_a = substr($a['antrnr'], 0, 1);
        $is_deleted = ($prefix_a === 'X' || $prefix_a === 'Z');
        $in_abstimmung = ($prefix_a === 'B');

        // Hintergrundfarbe basierend auf Status
        if ($is_deleted) {
            $card_bg = '#ffe0e0';
            $card_border = '#ffcccc';
        } elseif ($in_abstimmung) {
            $card_bg = 'rgba(250, 170, 0, 0.08)';
            $card_border = '#FAAA00';
        } else {
            $card_bg = 'white';
            $card_border = '#ddd';
        }
    ?>
    <div style="background: <?= $card_bg ?>; border: 1px solid <?= $card_border ?>; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <!-- Header mit Nummer, Typ und Datum -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                <span class="antrnr" style="background: #e9ecef; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: bold;">
                    <?= htmlspecialchars($a['antrnr']) ?>
                </span>
                <?php if ($a['bart']): ?>
                    <span style="background: #d1ecf1; color: #0c5460; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                        <?= htmlspecialchars($bart_bezeichnungen[$a['bart']] ?? $a['bart']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($a['int_ext'] === 'i'): ?>
                    <span style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold;">
                        🔒 Vorstandsintern
                    </span>
                <?php elseif ($a['int_ext'] === 'n'): ?>
                    <span style="background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold;">
                        👥 Nicht öffentlich
                    </span>
                <?php endif; ?>
            </div>
            <div style="text-align: right; font-size: 12px; color: #666;">
                <div><?= htmlspecialchars($a['KurzN']) ?></div>
                <?php if ($a['lzugriff']): ?>
                    <div style="font-size: 11px; color: #999;"><?= date('d.m.Y H:i', strtotime($a['lzugriff'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status-Anzeige -->
        <?php
        if ($prefix_a === 'B') {
            if (preg_match('/^B(\d{6})/', $a['antrnr'], $matches)) {
                $datum_str = $matches[1];
                $datum = '20' . substr($datum_str, 0, 2) . '-' . substr($datum_str, 2, 2) . '-' . substr($datum_str, 4, 2);
                echo '<div style="background: rgba(250, 170, 0, 0.2); padding: 6px 10px; border-radius: 4px; margin-bottom: 10px; font-size: 12px; color: #000; font-weight: 600;">';
                echo '🗳️ In Abstimmung seit ' . date('d.m.Y', strtotime($datum));
                echo '</div>';
            }
        } elseif ($prefix_a === 'V') {
            if (preg_match('/^V(\d{6})/', $a['antrnr'], $matches)) {
                $datum_str = $matches[1];
                $datum = '20' . substr($datum_str, 0, 2) . '-' . substr($datum_str, 2, 2) . '-' . substr($datum_str, 4, 2);
                echo '<div style="background: rgba(76, 175, 80, 0.15); padding: 6px 10px; border-radius: 4px; margin-bottom: 10px; font-size: 12px; color: #2e7d32; font-weight: 600;">';
                echo '✓ Beschlossen am ' . date('d.m.Y', strtotime($datum));
                echo '</div>';
            }
        }
        ?>

        <!-- Titel -->
        <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #2c3e50;">
            <?= htmlspecialchars($a['titel']) ?>
        </h3>

        <!-- Finanzbetrag falls vorhanden -->
        <?php if ($a['fin'] > 0): ?>
            <div style="background: #fff3cd; padding: 8px 10px; border-radius: 4px; margin-bottom: 10px; font-size: 13px; color: #856404; font-weight: 600;">
                💰 <?= number_format($a['fin'], 0, ',', '.') ?> €
            </div>
        <?php endif; ?>

        <!-- Aktionen -->
        <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="antrag_ansehen.php?antrnr=<?= urlencode($a['antrnr']) ?>"
               class="btn btn-secondary"
               style="padding: 8px 16px; font-size: 13px; text-decoration: none; display: inline-block; flex: 1; min-width: 120px; text-align: center;">
                👁️ Ansehen
            </a>
            <a href="antrag_bearbeiten.php?antrnr=<?= urlencode($a['antrnr']) ?>"
               class="btn btn-primary"
               style="padding: 8px 16px; font-size: 13px; text-decoration: none; display: inline-block; flex: 1; min-width: 120px; text-align: center;">
                ✏️ Bearbeiten
            </a>
        </div>

        <!-- Admin-Hinweis bei B-Anträgen -->
        <?php if ($in_abstimmung && !$ist_admin): ?>
            <div style="margin-top: 8px; font-size: 11px; color: #856404; background: rgba(250, 170, 0, 0.1); padding: 6px 8px; border-radius: 4px;">
                ⚠️ Nur Administratoren können während der Abstimmung bearbeiten
            </div>
        <?php endif; ?>

        <!-- Endgültig löschen für Admins bei X/Z -->
        <?php if ($show_deleted && ($prefix_a === 'X' || $prefix_a === 'Z') && $ist_admin): ?>
            <form method="POST" style="margin-top: 10px;">
                <input type="hidden" name="antrnr" value="<?= htmlspecialchars($a['antrnr']) ?>">
                <button type="submit" name="delete_permanent" value="1"
                        style="width: 100%; padding: 8px; font-size: 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;"
                        onclick="return confirm('Antrag PERMANENT löschen? Dies kann nicht rückgängig gemacht werden!');">
                    🗑️ Endgültig löschen
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
