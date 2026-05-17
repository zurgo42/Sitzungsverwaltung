<?php
/**
 * beschlussbuch.php - Beschlossene Anträge
 *
 * Zeigt alle VS-Anträge (beschlossene Anträge) aus der antraege-Tabelle
 */

session_start();
require_once 'session_config.php';
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
$filter_ressort = $_POST['ressort'] ?? ($_GET['ressort'] ?? '');
$filter_sichtbarkeit = $_POST['sichtbarkeit'] ?? ($_GET['sichtbarkeit'] ?? '');
$search = $_POST['stichwort'] ?? ($_GET['search'] ?? '');
$sortierung = $_POST['sort'] ?? ($_GET['sort'] ?? 'desc'); // desc = neueste zuerst
$view_mode = $_POST['view_mode'] ?? ($_GET['view_mode'] ?? 'table');
$limit = (int)($_POST['limit'] ?? ($_GET['limit'] ?? 50));

// SQL für VS-Anträge mit allen Details
$sql = "SELECT
    a.antrnr,
    a.titel,
    a.beschluss,
    a.begr,
    a.antrst,
    a.ressort1,
    a.ressort2,
    a.wichtig,
    a.lzugriff,
    a.int_ext,
    a.warantrag,
    a.fin,
    a.fintext,
    a.sach,
    a.pers,
    a.anmerkungen,
    a.VName1, a.VName2, a.VName3, a.VName4, a.VName5, a.VName6,
    a.Votum1, a.Votum2, a.Votum3, a.Votum4, a.Votum5, a.Votum6,
    b.Vorname,
    b.Name,
    r1.Ressort as ressort1_name,
    r2.Ressort as ressort2_name
FROM antraege a
LEFT JOIN berechtigte b ON a.antrst = b.ID
LEFT JOIN ressortliste r1 ON a.ressort1 = r1.ID
LEFT JOIN ressortliste r2 ON a.ressort2 = r2.ID
WHERE a.antrnr LIKE 'VS%'";

// Sichtbarkeits-Filter für nicht-berechtigte User
if (!$kann_intern_sehen) {
    $sql .= " AND (a.int_ext IS NULL OR a.int_ext != 'i')";
}

// Weitere Filterung
if ($filter_sichtbarkeit) {
    if ($filter_sichtbarkeit === 'intern' && $kann_intern_sehen) {
        $sql .= " AND a.int_ext = 'i'";
    } elseif ($filter_sichtbarkeit === 'nicht_oeffentlich') {
        $sql .= " AND a.int_ext = 'n'";
    } elseif ($filter_sichtbarkeit === 'extern') {
        $sql .= " AND (a.int_ext = 'e' OR a.int_ext IS NULL)";
    }
}

$params = [];

// Zeitraum-Filter (aus Antragsnummer extrahieren: VSYYMMDD...)
if ($filter_von || $filter_bis) {
    $von_ymd = $filter_von ? date('ymd', strtotime($filter_von)) : '000000';
    $bis_ymd = $filter_bis ? date('ymd', strtotime($filter_bis)) : '991231';
    $sql .= " AND SUBSTRING(a.antrnr, 3, 6) BETWEEN ? AND ?";
    $params[] = $von_ymd;
    $params[] = $bis_ymd;
}

// Ressort-Filter
if ($filter_ressort) {
    $sql .= " AND (a.ressort1 = ? OR a.ressort2 = ?)";
    $params[] = $filter_ressort;
    $params[] = $filter_ressort;
}

// Erweiterte Suche in allen relevanten Feldern
if ($search) {
    $sql .= " AND (a.antrnr LIKE ? OR a.titel LIKE ? OR a.beschluss LIKE ? OR a.begr LIKE ? OR r1.Ressort LIKE ? OR r2.Ressort LIKE ? OR a.sach LIKE ? OR a.pers LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Sortierung
$sql .= $sortierung === 'asc' ? " ORDER BY a.antrnr ASC" : " ORDER BY a.antrnr DESC";

// Limit
$sql .= " LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$beschluesse = $stmt->fetchAll();

// Votum-Zusammenfassung berechnen
foreach ($beschluesse as &$b) {
    $ja = 0;
    $nein = 0;
    $enthaltung = 0;
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($b["Votum$i"])) {
            if ($b["Votum$i"] == 1) $ja++;
            elseif ($b["Votum$i"] == 2) $nein++;
            elseif ($b["Votum$i"] == 3) $enthaltung++;
        }
    }
    if ($ja > 0 || $nein > 0 || $enthaltung > 0) {
        $b['votum_text'] = "$ja Ja, $nein Nein, $enthaltung Enthaltung";
    } else {
        $b['votum_text'] = 'Einstimmig';
    }
}
unset($b);

// Ressorts für Filter
$ressorts_stmt = $pdo->query("SELECT ID, Ressort FROM ressortliste ORDER BY Ressort");
$ressorts = $ressorts_stmt->fetchAll();

// Antrag duplizieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplizieren']) && $kann_duplizieren) {
    $alt_antrnr = $_POST['antrnr'] ?? '';

    // Original laden
    $orig_stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
    $orig_stmt->execute([$alt_antrnr]);
    $orig = $orig_stmt->fetch();

    if ($orig) {
        // Neue Antragsnummer generieren: A + YYMMDD + laufende Nummer
        $heute = date('ymd');
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM antraege WHERE antrnr LIKE ?");
        $count_stmt->execute(["A{$heute}%"]);
        $count = $count_stmt->fetchColumn();
        $neue_nr = 'A' . $heute . sprintf('%02d', $count + 1);

        // Duplikat erstellen
        $insert_sql = "INSERT INTO antraege (
            antrnr, titel, beschluss, begr, fin, fintext, pers, sach,
            ressort1, ressort2, verant, antrst, int_ext, verein,
            file1, file2, file3, file4, filetext1, filetext2, filetext3, filetext4,
            thread, lzugriff
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $pdo->prepare($insert_sql)->execute([
            $neue_nr,
            $orig['titel'],
            $orig['beschluss'],
            $orig['begr'],
            $orig['fin'],
            $orig['fintext'],
            $orig['pers'],
            $orig['sach'],
            $orig['ressort1'],
            $orig['ressort2'],
            $orig['verant'],
            $user['ID'], // Neuer Antragsteller = aktueller User
            $orig['int_ext'],
            $orig['verein'],
            $orig['file1'],
            $orig['file2'],
            $orig['file3'],
            $orig['file4'],
            $orig['filetext1'],
            $orig['filetext2'],
            $orig['filetext3'],
            $orig['filetext4'],
            $orig['thread']
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
    </style>
</head>
<body>
    <button onclick="toggleDarkMode()" class="btn btn-secondary" style="float: right; margin-bottom: 10px;">
        🌓 Dark Mode
    </button>

    <a href="antragsliste.php" class="back-link">← Zurück zu Anträgen</a>

    <div class="header">
        <h1>📚 Beschlussbuch</h1>
        <div class="actions">
            <a href="antragsliste.php" class="btn btn-secondary">📝 Anträge</a>
            <a href="abstimmungen.php" class="btn btn-secondary">🗳️ Abstimmungen</a>
        </div>
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
            <div class="filter-label">Ressort:</div>
            <select name="ressort">
                <option value="">Alle Ressorts</option>
                <?php foreach ($ressorts as $r): ?>
                    <option value="<?= h($r['ID']) ?>" <?= $filter_ressort == $r['ID'] ? 'selected' : '' ?>>
                        <?= h($r['Ressort']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <div class="filter-label">Sichtbarkeit:</div>
            <select name="sichtbarkeit">
                <option value="">Alle</option>
                <option value="extern" <?= $filter_sichtbarkeit === 'extern' ? 'selected' : '' ?>>Extern</option>
                <option value="nicht_oeffentlich" <?= $filter_sichtbarkeit === 'nicht_oeffentlich' ? 'selected' : '' ?>>Nicht öffentlich</option>
                <?php if ($kann_intern_sehen): ?>
                    <option value="intern" <?= $filter_sichtbarkeit === 'intern' ? 'selected' : '' ?>>Intern (Vorstand)</option>
                <?php endif; ?>
            </select>
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
                $ressort_namen = [];
                if (!empty($b['ressort1_name'])) $ressort_namen[] = $b['ressort1_name'];
                if (!empty($b['ressort2_name'])) $ressort_namen[] = $b['ressort2_name'];
                $ressort_text = !empty($ressort_namen) ? implode(', ', $ressort_namen) : 'Vorstand Gesamt';
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
            $ressort_namen = [];
            if (!empty($b['ressort1_name'])) $ressort_namen[] = $b['ressort1_name'];
            if (!empty($b['ressort2_name'])) $ressort_namen[] = $b['ressort2_name'];
            $ressort_text = !empty($ressort_namen) ? implode(', ', $ressort_namen) : 'Vorstand Gesamt';
        ?>
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div>
                    <span style="background: #e9ecef; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                        <a href="antrag_ansehen.php?antrnr=<?= h($b['antrnr']) ?>" style="color:inherit; text-decoration:none;"><?= h($b['antrnr']) ?></a>
                    </span>
                    <span style="background: #d1ecf1; color: #0c5460; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 5px;"><?= h($ressort_text) ?></span>
                    <?php if ($b['fin'] > 0): ?>
                        <span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 5px;">💰 <?= number_format($b['fin'], 0, ',', '.') ?> €</span>
                    <?php endif; ?>
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

            <?php if (!empty($b['fintext']) || !empty($b['sach']) || !empty($b['pers'])): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px;">
                <?php if ($b['fintext']): ?>
                <div style="font-size: 12px; background: #f8f9fa; padding: 8px; border-radius: 4px; border-left: 3px solid #007bff;">
                    <strong style="font-size: 10px; color: #6c757d; text-transform: uppercase; display: block;">Finanziell</strong>
                    <?= highlightWords2(h($b['fintext']), $search) ?>
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
                        | <strong>Protokollnotiz:</strong> <?= highlightWords2(h($b['anmerkungen']), $search) ?>
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
