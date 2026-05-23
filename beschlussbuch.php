<?php
/**
 * beschlussbuch.php - Beschlossene Anträge
 *
 * Zeigt alle VS-Beschlüsse aus der beschluesse-Tabelle
 */

require_once 'session_config.php';
session_start();
require_once 'config.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// User laden
$user_stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
$user_stmt->execute([$_SESSION['member_id']]);
$user = $user_stmt->fetch();

// Prüfen, ob der Aufruf über vtool.php erfolgt ist
$isVTool = (strpos($_SERVER['PHP_SELF'], 'vtool.php') !== false);

// Helper-Funktion für HTML-Escaping
function h($text) {
    $text = html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

// Helper-Funktion für Suchbegriff-Highlighting
function highlightWords2(string $text, ?string $search): string {
    if (!$search || trim($search) === '') return $text;
    $search = preg_quote($search, '/');
    return preg_replace('/(' . $search . ')/iu', '<mark style="background:#ffd700;color:#000;">$1</mark>', $text);
}

// Berechtigungen
$kann_intern_sehen = ($user['aktiv'] > 17 || $user['Funktion'] === 'VA' || ($user['is_admin'] ?? 0) == 1);
$kann_duplizieren = ($user['aktiv'] >= 9);

// Filter - POST statt GET für Kompatibilität mit altem System
$filter_von = $_POST['von'] ?? ($_GET['von'] ?? date('Y-01-01'));
$filter_bis = $_POST['bis'] ?? ($_GET['bis'] ?? date('Y-12-31'));
$search = $_POST['stichwort'] ?? ($_GET['search'] ?? '');
$sortierung = $_POST['sort'] ?? ($_GET['sort'] ?? 'desc'); // desc = neueste zuerst
$view_mode = $_POST['view_mode'] ?? ($_GET['view_mode'] ?? 'table');
$limit = (int)($_POST['limit'] ?? ($_GET['limit'] ?? 25));

// SQL für VS-Beschlüsse aus beschluesse-Tabelle
$sql = "SELECT b.* FROM beschluesse b WHERE b.antrnr LIKE 'VS%'";

// Sichtbarkeits-Filter für nicht-berechtigte User
if (!$kann_intern_sehen) {
    $sql .= " AND (b.int_ext IS NULL OR b.int_ext = '' OR b.int_ext != 'i')";
}

$params = [];

// Zeitraum-Filter (aus Antragsnummer extrahieren: VSYYMMDD...)
if ($filter_von || $filter_bis) {
    $von_ymd = $filter_von ? date('ymd', strtotime($filter_von)) : '000000';
    $bis_ymd = $filter_bis ? date('ymd', strtotime($filter_bis)) : '991231';
    $sql .= " AND SUBSTRING(b.antrnr, 3, 6) BETWEEN ? AND ?";
    $params[] = $von_ymd;
    $params[] = $bis_ymd;
}

// Erweiterte Suche in allen relevanten Feldern
if ($search) {
    $sql .= " AND (b.antrnr LIKE ? OR b.titel LIKE ? OR b.beschluss LIKE ? OR b.begr LIKE ? OR b.ressort LIKE ? OR b.sach LIKE ? OR b.pers LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Sortierung
$sql .= $sortierung === 'asc' ? " ORDER BY b.antrnr ASC" : " ORDER BY b.antrnr DESC";

// Limit
$sql .= " LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$beschluesse = $stmt->fetchAll();

// Warantrag für jeden Beschluss aus antraege-Tabelle holen
foreach ($beschluesse as &$b) {
    $warantrag_stmt = $pdo->prepare("SELECT warantrag FROM antraege WHERE antrnr = ?");
    $warantrag_stmt->execute([$b['antrnr']]);
    $b['warantrag'] = $warantrag_stmt->fetchColumn();

    // Finanzielle Auswirkungen: Betrag aus fintext extrahieren
    $b['fin'] = 0;
    if (!empty($b['fintext']) && preg_match('/(\d+)\s*Euro/', $b['fintext'], $matches)) {
        $b['fin'] = (int)$matches[1];
    }

    // Votum-Text aus dafuer, dagegen, enthaltungen zusammensetzen
    if (!empty($b['dafuer']) || !empty($b['dagegen']) || !empty($b['enthaltungen'])) {
        $b['votum_text'] = '';
        if (!empty($b['dafuer'])) $b['votum_text'] .= 'Ja: ' . $b['dafuer'];
        if (!empty($b['dagegen'])) {
            if ($b['votum_text']) $b['votum_text'] .= ' | ';
            $b['votum_text'] .= 'Nein: ' . $b['dagegen'];
        }
        if (!empty($b['enthaltungen'])) {
            if ($b['votum_text']) $b['votum_text'] .= ' | ';
            $b['votum_text'] .= 'Enthaltung: ' . $b['enthaltungen'];
        }
    } else {
        $b['votum_text'] = 'Einstimmig';
    }
}
unset($b);

// Antrag duplizieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplizieren']) && $kann_duplizieren) {
    $alt_antrnr = $_POST['antrnr'] ?? '';

    // Original aus beschluesse-Tabelle laden (dort stehen die redaktionell bearbeiteten Texte)
    $orig_stmt = $pdo->prepare("SELECT * FROM beschluesse WHERE antrnr = ?");
    $orig_stmt->execute([$alt_antrnr]);
    $orig_beschluss = $orig_stmt->fetch();

    // Zusätzlich aus antraege-Tabelle laden für Ressorts, Verantwortliche, wichtig/verf1/verf2
    $orig_antrag_stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
    $orig_antrag_stmt->execute([$alt_antrnr]);
    $orig_antrag = $orig_antrag_stmt->fetch();

    if ($orig_beschluss && $orig_antrag) {
        // Neue Antragsnummer generieren: A + YYMMDD + laufende Nummer
        $heute = date('ymd');
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM antraege WHERE antrnr LIKE ?");
        $count_stmt->execute(["A{$heute}%"]);
        $count = $count_stmt->fetchColumn();
        $neue_nr = 'A' . $heute . sprintf('%02d', $count + 1);

        // Finanzielle Auswirkungen: Betrag aus fintext extrahieren
        $fin = 0;
        if (!empty($orig_beschluss['fintext']) && preg_match('/(\d+)\s*Euro/', $orig_beschluss['fintext'], $matches)) {
            $fin = (int)$matches[1];
        }

        // Prüfen ob es ein Vorstandsbeschluss war (bart='B')
        $wichtig = 0;
        $verf1 = null;
        $verf2 = null;
        if ($orig_antrag['bart'] === 'B') {
            // Vorstandsbeschluss: wichtig übernehmen
            $wichtig = $orig_antrag['wichtig'] ?? 0;
        } else {
            // Verfügung oder Ressortbeschluss: verf1 und verf2 übernehmen
            $verf1 = $orig_antrag['verf1'] ?? null;
            $verf2 = $orig_antrag['verf2'] ?? null;
        }

        // Duplikat erstellen mit Daten aus beschluesse und antraege
        $insert_sql = "INSERT INTO antraege (
            antrnr, titel, beschluss, begr, fin, fintext, pers, sach,
            ressort1, ressort2, verant, antrst, int_ext, wichtig, verf1, verf2,
            lzugriff
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $pdo->prepare($insert_sql)->execute([
            $neue_nr,
            $orig_beschluss['titel'],
            $orig_beschluss['beschluss'],
            $orig_beschluss['begr'] ?? '',
            $fin,
            $orig_beschluss['fintext'] ?? '',
            $orig_beschluss['pers'] ?? '',
            $orig_beschluss['sach'] ?? '',
            $orig_antrag['ressort1'] ?? null,
            $orig_antrag['ressort2'] ?? null,
            $orig_antrag['verant'] ?? null,
            $user['ID'], // Neuer Antragsteller = aktueller User
            $orig_beschluss['int_ext'] ?? 'e',
            $wichtig,
            $verf1,
            $verf2
        ]);

        header("Location: antrag_bearbeiten.php?antrnr=" . urlencode($neue_nr) . "&msg=dupliziert");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beschlussbuch</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="antrag-styles.css">
    <script>
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        h1 { font-size: 24px; color: #333; }
        .actions { display: flex; gap: 10px; }
        .btn {
            padding: 10px 20px;
            background: #0066cc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: #0052a3; }
        .btn-secondary { background: #666; }
        .btn-secondary:hover { background: #555; }
        .filters {
            background: white;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            align-items: end;
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            width: 100%;
        }
        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 4px;
        }
        .count {
            background: white;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            font-weight: 600;
            color: #666;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        tr:hover { background: #f8f9fa; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 12px;
            font-weight: 500;
        }
        .badge-intern {
            background: rgba(211, 47, 47, 0.1);
            color: #d32f2f;
        }
        .badge-nicht-oeffentlich {
            background: rgba(250, 170, 0, 0.2);
            color: #f57c00;
        }
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #999;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0066cc;
            text-decoration: none;
        }

        /* Dark Mode */
        body.dark-mode {
            background: #1a1a1a;
            color: #e0e0e0;
        }
        body.dark-mode .header {
            background: #2d2d2d;
            border-color: #444;
        }
        body.dark-mode h1 {
            color: #e0e0e0;
        }
        body.dark-mode .filters {
            background: #2d2d2d;
            border-color: #444;
        }
        body.dark-mode .filters input,
        body.dark-mode .filters select {
            background: #1a1a1a;
            color: #e0e0e0;
            border-color: #444;
        }
        body.dark-mode .filter-label {
            color: #e0e0e0;
        }
        body.dark-mode .count {
            background: #2d2d2d;
            color: #e0e0e0;
        }
        body.dark-mode .table-container {
            background: #2d2d2d;
        }
        body.dark-mode th {
            background: #1a1a1a;
            color: #e0e0e0;
            border-color: #444;
        }
        body.dark-mode td {
            border-color: #444;
            color: #e0e0e0;
        }
        body.dark-mode tr:hover {
            background: #333;
        }
        body.dark-mode .back-link {
            color: #64b5f6;
        }
    </style>
</head>
<body>
    <!-- Dark Mode wird automatisch von index.php übernommen -->
    <script>
        // Dark Mode Status von index.php übernehmen
        if (document.cookie.includes('darkMode=enabled')) {
            document.body.classList.add('dark-mode');
        }
    </script>

    <?php
    // Dynamischer Zurück-Link zur aufrufenden Seite
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php?tab=proposals';
    // Sicherstellen, dass der Referer zur gleichen Domain gehört
    if (parse_url($referer, PHP_URL_HOST) !== $_SERVER['HTTP_HOST']) {
        $referer = 'index.php?tab=proposals';
    }
    ?>
    <a href="<?= h($referer) ?>" class="back-link">← Zurück</a>

    <div class="header">
        <h1>📚 Beschlussbuch</h1>
    </div>

    <form method="POST" class="filters">
        <div>
            <div class="filter-label">Von Datum:</div>
            <input type="date" name="von" value="<?= h($filter_von) ?>">
        </div>
        <div>
            <div class="filter-label">Bis Datum:</div>
            <input type="date" name="bis" value="<?= h($filter_bis) ?>">
        </div>
        <div>
            <div class="filter-label">Sortierung:</div>
            <select name="sort">
                <option value="desc" <?= $sortierung === 'desc' ? 'selected' : '' ?>>Neueste zuerst</option>
                <option value="asc" <?= $sortierung === 'asc' ? 'selected' : '' ?>>Älteste zuerst</option>
            </select>
        </div>
        <div>
            <div class="filter-label">Limit:</div>
            <input type="number" name="limit" value="<?= h($limit) ?>" min="1" max="500" style="width: 80px;">
        </div>
        <div>
            <div class="filter-label">Suche:</div>
            <input type="text" name="stichwort" placeholder="Stichwort..." value="<?= h($search) ?>">
        </div>
        <?php if ($user['aktiv'] > 9): ?>
        <div>
            <div class="filter-label">Darstellung:</div>
            <select name="view_mode">
                <option value="table" <?= $view_mode === 'table' ? 'selected' : '' ?>>Tabelle</option>
                <option value="news" <?= $view_mode === 'news' ? 'selected' : '' ?>>Liste (MensaNews)</option>
            </select>
        </div>
        <?php endif; ?>
        <div style="display: flex; gap: 10px; align-items: end;">
            <button type="submit" class="btn">Filtern</button>
            <a href="beschlussbuch.php" class="btn btn-secondary">Zurücksetzen</a>
        </div>
    </form>

    <div class="count">
        <?= count($beschluesse) ?> Beschlüsse gefunden
    </div>

    <?php if (empty($beschluesse)): ?>
        <div class="table-container">
            <div class="empty-state">
                Keine Beschlüsse im gewählten Zeitraum gefunden.
            </div>
        </div>
    <?php elseif ($view_mode === 'news' && $user['aktiv'] > 9): ?>
        <!-- MensaNews-Liste Format -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <?php foreach ($beschluesse as $b):
                // Datum aus Antragsnummer
                if (preg_match('/^VS(\d{6})/', $b['antrnr'], $matches)) {
                    $datum_str = $matches[1];
                    $datum = '20' . substr($datum_str, 0, 2) . '-' . substr($datum_str, 2, 2) . '-' . substr($datum_str, 4, 2);
                    $datum_anzeige = date('d.m.Y', strtotime($datum));
                } else {
                    $datum_anzeige = '-';
                }
                $ressort_text = !empty($b['ressort']) ? $b['ressort'] : 'Vorstand Gesamt';
            ?>
            <p style="text-align: left; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <b><a href="antrag_ansehen.php?antrnr=<?= h($b['antrnr']) ?>" style="color:black;text-decoration:none;"><?= h($b['antrnr']) ?></a></b> (<?= $datum_anzeige ?>)<br>
                Ressort: <?= highlightWords2(h($ressort_text), $search) ?><br>
                <b>Beschlusstitel: <?= highlightWords2(h($b['titel']), $search) ?></b><br>
                Beschluss: <?= highlightWords2(h($b['beschluss']), $search) ?><br>
                <?php if (!empty($b['fintext'])): ?>
                    Finanzielle Auswirkungen: <?= highlightWords2(h($b['fintext']), $search) ?><br>
                <?php else: ?>
                    Finanzielle Auswirkungen: Keine<br>
                <?php endif; ?>
                <?php if (!empty($b['pers'])): ?>
                    Personelle Auswirkungen: <?= highlightWords2(h($b['pers']), $search) ?><br>
                <?php else: ?>
                    Personelle Auswirkungen: Keine<br>
                <?php endif; ?>
                <?php if (!empty($b['sach'])): ?>
                    Sachliche Auswirkungen: <?= highlightWords2(h($b['sach']), $search) ?><br>
                <?php else: ?>
                    Sachliche Auswirkungen: Keine<br>
                <?php endif; ?>
                <?php if (!empty($b['begr'])): ?>
                    Begründung: <?= highlightWords2(h($b['begr']), $search) ?><br>
                <?php endif; ?>
                Abstimmung: <?= h($b['votum_text']) ?><br>
                <?php if (!empty($b['anmerkungen'])): ?>
                    Protokollnotiz/Anmerkung: <?= highlightWords2(h($b['anmerkungen']), $search) ?><br>
                <?php endif; ?>
            </p>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Detaillierte Karten-Ansicht (wie alte Tabelle) -->
        <?php foreach ($beschluesse as $b):
            // Datum aus Antragsnummer
            if (preg_match('/^VS(\d{6})/', $b['antrnr'], $matches)) {
                $datum_str = $matches[1];
                $datum = '20' . substr($datum_str, 0, 2) . '-' . substr($datum_str, 2, 2) . '-' . substr($datum_str, 4, 2);
                $datum_anzeige = date('d.m.Y', strtotime($datum));
            } else {
                $datum_anzeige = '-';
            }
            $ressort_text = !empty($b['ressort']) ? $b['ressort'] : 'Vorstand Gesamt';
        ?>
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div>
                    <span style="background: #e9ecef; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                        <a href="antrag_ansehen.php?antrnr=<?= h($b['antrnr']) ?>" style="color:inherit; text-decoration:none;"><?= h($b['antrnr']) ?></a>
                    </span>
                    <span style="background: #d1ecf1; color: #0c5460; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 5px;"><?= h($ressort_text) ?></span>
                </div>
                <div style="text-align: right">
                    <small style="color: #6c757d;"><?= $datum_anzeige ?></small>
                    <?php if ($b['int_ext'] === 'n' || $b['int_ext'] === 'i'): ?>
                        <div style="color:#d9534f; font-size:10px; font-weight:bold;">
                            <?= $b['int_ext'] === 'i' ? '🔒 VORSTANDSINTERN' : '👥 NICHT ÖFFENTLICH' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #2c3e50; background: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #eee;">
                <?= highlightWords2(h($b['titel']), $search) ?>
            </h3>

            <div style="background: #f1f3f5; padding: 12px; border-radius: 6px; margin: 10px 0; font-size: 14px;">
                <strong style="font-size: 11px; text-transform: uppercase; color: #495057; display: block; margin-bottom: 4px;">Beschlusswortlaut:</strong>
                <?= highlightWords2(nl2br(h($b['beschluss'])), $search) ?>
            </div>

            <?php if (!empty($b['fintext']) || $b['fin'] > 0 || !empty($b['sach']) || !empty($b['pers'])): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px;">
                <?php if ($b['fintext'] || $b['fin'] > 0): ?>
                <div style="font-size: 12px; background: #f8f9fa; padding: 8px; border-radius: 4px; border-left: 3px solid #007bff;">
                    <strong style="font-size: 10px; color: #6c757d; text-transform: uppercase; display: block;">Finanziell</strong>
                    <?php if ($b['fin'] > 0): ?>
                        <div style="font-weight: 600; color: #856404; margin-bottom: 4px;">💰 <?= number_format($b['fin'], 0, ',', '.') ?> €</div>
                    <?php endif; ?>
                    <?php if ($b['fintext']): ?>
                        <?= highlightWords2(h($b['fintext']), $search) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($b['sach']): ?>
                <div style="font-size: 12px; background: #f8f9fa; padding: 8px; border-radius: 4px; border-left: 3px solid #28a745;">
                    <strong style="font-size: 10px; color: #6c757d; text-transform: uppercase; display: block;">Sachlich / Rechtlich</strong>
                    <?= highlightWords2(h($b['sach']), $search) ?>
                </div>
                <?php endif; ?>
                <?php if ($b['pers']): ?>
                <div style="font-size: 12px; background: #f8f9fa; padding: 8px; border-radius: 4px; border-left: 3px solid #e67e22;">
                    <strong style="font-size: 10px; color: #6c757d; text-transform: uppercase; display: block;">Personell</strong>
                    <?= highlightWords2(h($b['pers']), $search) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($b['begr'])): ?>
            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee;">
                <strong style="font-size: 11px; text-transform: uppercase; color: #495057; display: block; margin-bottom: 4px;">Begründung:</strong>
                <div style="font-size: 13px;"><?= highlightWords2(nl2br(h($b['begr'])), $search) ?></div>
            </div>
            <?php endif; ?>

            <div style="margin-top: 10px; font-size: 12px; color: #495057; font-style: italic; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong>Abstimmung:</strong> <?= h($b['votum_text']) ?>
                    <?php if (!empty($b['anmerkungen'])): ?>
                        <br><strong>Protokollnotiz:</strong> <?= highlightWords2(h($b['anmerkungen']), $search) ?>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                    <?php if (($user['aktiv'] == 3 || $user['aktiv'] > 9) && $isVTool): ?>
                        <a href="vtool.php?steuer=22&sta=1&antrnr=<?= h($b['antrnr']) ?>"
                           style="font-size: 10px; background: #f8f9fa; color: #666; text-decoration: none; padding: 2px 6px; border-radius: 3px; border: 1px solid #ddd;">
                            Antrag zeigen: <?= h($b['antrnr']) ?>
                        </a>
                        <?php if ($user['aktiv'] > 9): ?>
                        <a href="vtool.php?steuer=31&antrnr=<?= h($b['antrnr']) ?>"
                           style="font-size: 10px; background: #f8f9fa; color: #666; text-decoration: none; padding: 2px 6px; border-radius: 3px; border: 1px solid #ddd;">
                            Antrag duplizieren: <?= h($b['antrnr']) ?>
                        </a>
                        <?php endif; ?>
                    <?php elseif (!$isVTool && $kann_duplizieren): ?>
                        <?php if ($b['warantrag']): ?>
                        <a href="antrag_ansehen.php?antrnr=<?= urlencode($b['warantrag']) ?>"
                           style="font-size: 10px; background: #4caf50; color: white; text-decoration: none; padding: 4px 8px; border-radius: 3px;">
                            📄 Ursprungsantrag
                        </a>
                        <?php endif; ?>
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="antrnr" value="<?= h($b['antrnr']) ?>">
                            <button type="submit" name="duplizieren" value="1"
                                    style="font-size: 10px; background: #ff9800; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer;"
                                    onclick="return confirm('Beschluss als neuen Antrag duplizieren?');">
                                📋 Duplizieren
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
