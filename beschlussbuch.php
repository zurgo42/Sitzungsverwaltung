<?php
/**
 * beschlussbuch.php - Beschlusssammlung
 *
 * Zeigt alle fertigen Beschlüsse aus der antraege-Tabelle
 * (gefiltert nach fertig = '1')
 */

require_once 'session_config.php';
session_start();
require_once 'config.php';

// Prüfen ob eingeloggt (mit Adapter-Pattern)
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

// Datenbankverbindung
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// Filter-Parameter
$filter_year = $_GET['year'] ?? '';
$filter_wichtig = $_GET['wichtig'] ?? '';
$filter_ressort = $_GET['ressort'] ?? '';
$search = $_GET['search'] ?? '';

// SQL-Basis
$sql = "SELECT
    b.antrnr,
    b.titel,
    b.beschluss,
    b.begr,
    b.pers,
    b.sach,
    b.fintext,
    b.wichtig,
    b.ressort,
    b.int_ext,
    b.dafuer,
    b.dagegen,
    b.enthaltungen,
    b.anmerkungen,
    b.text as volltext,
    a.Betrag,
    a.ergebnis,
    a.verant,
    a.lzugriff
FROM beschluesse b
LEFT JOIN antraege a ON b.antrnr = a.antrnr
WHERE 1=1";

$params = [];

// Filter: Jahr
if ($filter_year) {
    $sql .= " AND antrnr LIKE ?";
    $params[] = "%$filter_year%";
}

// Filter: Wichtig
if ($filter_wichtig === '1') {
    $sql .= " AND wichtig = 1";
}

// Filter: Ressort
if ($filter_ressort) {
    $sql .= " AND b.ressort LIKE ?";
    $params[] = "%$filter_ressort%";
}

// Suche: Titel, Beschluss, Begründung
if ($search) {
    $sql .= " AND (
        b.titel LIKE ? OR
        b.beschluss LIKE ? OR
        b.begr LIKE ?
    )";
    $search_param = "%$search%";
    $params = array_merge($params, array_fill(0, 3, $search_param));
}

$sql .= " ORDER BY b.antrnr DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$beschluesse = $stmt->fetchAll();

// Jahres-Liste für Filter
$years_stmt = $pdo->query("
    SELECT DISTINCT SUBSTRING(antrnr, 3, 2) as jahr
    FROM beschluesse
    ORDER BY jahr DESC
");
$years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

// Ressort-Liste (aus beschluesse, enthält bereits aufgelöste Namen)
$ressorts_stmt = $pdo->query("
    SELECT DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(ressort, ',', numbers.n), ',', -1)) as ressort
    FROM beschluesse
    CROSS JOIN (
        SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    ) numbers
    WHERE ressort IS NOT NULL
    AND ressort != ''
    AND CHAR_LENGTH(ressort) - CHAR_LENGTH(REPLACE(ressort, ',', '')) >= numbers.n - 1
    ORDER BY ressort
");
$ressorts = $ressorts_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beschlussbuch</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
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
        }
        .header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
        }
        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters select,
        .filters input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .filters button {
            padding: 8px 16px;
            background: #0066cc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .filters button:hover {
            background: #0052a3;
        }
        .filters .reset-btn {
            background: #666;
        }
        .filters .reset-btn:hover {
            background: #555;
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
        .beschluss-card {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .beschluss-card.wichtig {
            border-left: 4px solid #ff6b00;
        }
        .beschluss-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .beschluss-number {
            font-size: 14px;
            font-weight: 700;
            color: #0066cc;
        }
        .beschluss-badges {
            display: flex;
            gap: 8px;
        }
        .badge {
            padding: 4px 10px;
            font-size: 12px;
            border-radius: 12px;
            font-weight: 500;
        }
        .badge.wichtig {
            background: #fff3cd;
            color: #856404;
        }
        .badge.ressort {
            background: #e7f3ff;
            color: #0066cc;
        }
        .badge.intern {
            background: #f0f0f0;
            color: #666;
        }
        .badge.extern {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .beschluss-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
        }
        .beschluss-content {
            color: #555;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .beschluss-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        .meta-label {
            font-weight: 600;
            margin-bottom: 4px;
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
        }
        .meta-value {
            color: #333;
        }
        .empty-state {
            background: white;
            padding: 60px 20px;
            text-align: center;
            border-radius: 8px;
            color: #999;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0066cc;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">← Zurück zur Übersicht</a>

    <div class="header">
        <h1>Beschlussbuch</h1>

        <form method="GET" class="filters">
            <select name="year">
                <option value="">Alle Jahre</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>"
                            <?= $filter_year === $year ? 'selected' : '' ?>>
                        20<?= htmlspecialchars($year) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="wichtig">
                <option value="">Alle Prioritäten</option>
                <option value="1" <?= $filter_wichtig === '1' ? 'selected' : '' ?>>
                    Nur wichtige
                </option>
            </select>

            <select name="ressort">
                <option value="">Alle Ressorts</option>
                <?php foreach ($ressorts as $ressort): ?>
                    <option value="<?= htmlspecialchars($ressort) ?>"
                            <?= $filter_ressort === $ressort ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ressort) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text"
                   name="search"
                   placeholder="Suche..."
                   value="<?= htmlspecialchars($search) ?>">

            <button type="submit">Filtern</button>
            <a href="beschlussbuch.php" class="reset-btn" style="padding: 8px 16px; background: #666; color: white; text-decoration: none; border-radius: 4px;">Zurücksetzen</a>
        </form>
    </div>

    <div class="count">
        <?= count($beschluesse) ?> Beschlüsse gefunden
    </div>

    <?php if (empty($beschluesse)): ?>
        <div class="empty-state">
            <p>Keine Beschlüsse gefunden.</p>
            <?php if ($filter_year || $filter_wichtig || $filter_ressort || $search): ?>
                <p style="margin-top: 10px;">
                    <a href="beschlussbuch.php">Filter zurücksetzen</a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($beschluesse as $b): ?>
            <div class="beschluss-card <?= $b['wichtig'] ? 'wichtig' : '' ?>">
                <div class="beschluss-header">
                    <div class="beschluss-number"><?= htmlspecialchars($b['antrnr']) ?></div>
                    <div class="beschluss-badges">
                        <?php if ($b['wichtig']): ?>
                            <span class="badge wichtig">Wichtig</span>
                        <?php endif; ?>

                        <?php if ($b['ressort']): ?>
                            <?php
                            // Ressort kann kommasepariert sein
                            $ressorts_arr = array_map('trim', explode(',', $b['ressort']));
                            foreach ($ressorts_arr as $r):
                                if ($r):
                            ?>
                                <span class="badge ressort"><?= htmlspecialchars($r) ?></span>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        <?php endif; ?>

                        <?php if ($b['int_ext'] === 'i'): ?>
                            <span class="badge intern">Intern</span>
                        <?php elseif ($b['int_ext'] === 'e'): ?>
                            <span class="badge extern">Extern</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="beschluss-title">
                    <?= htmlspecialchars($b['titel']) ?>
                </div>

                <div class="beschluss-content">
                    <?= nl2br(htmlspecialchars($b['beschluss'])) ?>
                </div>

                <?php if ($b['begr']): ?>
                    <div class="beschluss-content" style="margin-top: 12px; font-style: italic; color: #666;">
                        <strong>Begründung:</strong><br>
                        <?= nl2br(htmlspecialchars($b['begr'])) ?>
                    </div>
                <?php endif; ?>

                <div class="beschluss-meta">
                    <?php if ($b['Betrag'] && $b['Betrag'] != 0): ?>
                        <div class="meta-item">
                            <span class="meta-label">Betrag</span>
                            <span class="meta-value"><?= number_format($b['Betrag'], 2, ',', '.') ?> €</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['fintext']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Finanzielle Auswirkungen</span>
                            <span class="meta-value"><?= htmlspecialchars($b['fintext']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['pers']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Personelle Auswirkungen</span>
                            <span class="meta-value"><?= htmlspecialchars($b['pers']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['sach']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Sachliche Auswirkungen</span>
                            <span class="meta-value"><?= htmlspecialchars($b['sach']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['verant']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Verantwortlich</span>
                            <span class="meta-value"><?= htmlspecialchars($b['verant']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['ergebnis']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Abstimmungsergebnis</span>
                            <span class="meta-value"><?= htmlspecialchars($b['ergebnis']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['dafuer']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Dafür</span>
                            <span class="meta-value"><?= htmlspecialchars($b['dafuer']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['dagegen']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Dagegen</span>
                            <span class="meta-value"><?= htmlspecialchars($b['dagegen']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['enthaltungen']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Enthaltungen</span>
                            <span class="meta-value"><?= htmlspecialchars($b['enthaltungen']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($b['anmerkungen']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Anmerkungen</span>
                            <span class="meta-value"><?= nl2br(htmlspecialchars($b['anmerkungen'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
