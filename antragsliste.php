<?php
/**
 * antragsliste.php - Liste offener Anträge
 *
 * Zeigt alle Anträge ohne VS-Präfix (offene Anträge)
 */

session_start();
require_once 'session_config.php';
require_once 'config.php';

// Prüfen ob eingeloggt
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

// Filter
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// SQL für offene Anträge (ohne VS-Präfix) mit Antragsteller-Namen und Ressort-Namen
$sql = "SELECT
    a.antrnr,
    a.titel,
    a.bart,
    a.antrst,
    a.ressort1,
    a.ressort2,
    a.wichtig,
    a.lzugriff,
    a.verant,
    b.Vorname,
    b.Name,
    r1.Ressort as ressort1_name,
    r2.Ressort as ressort2_name
FROM antraege a
LEFT JOIN berechtigte b ON a.antrst = b.ID
LEFT JOIN ressortliste r1 ON a.ressort1 = r1.ID
LEFT JOIN ressortliste r2 ON a.ressort2 = r2.ID
WHERE a.antrnr NOT LIKE 'VS%'
  AND a.antrnr NOT LIKE 'X%'
  AND a.antrnr NOT LIKE 'Z%'";

$params = [];

// Filter nach Antragsteller
if ($filter_status !== 'all') {
    $sql .= " AND a.antrst = ?";
    $params[] = $filter_status;
}

// Suche
if ($search) {
    $sql .= " AND (a.antrnr LIKE ? OR a.titel LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY a.lzugriff DESC, a.antrnr DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$antraege = $stmt->fetchAll();

// Antragsteller für Filter ermitteln
$status_stmt = $pdo->query("
    SELECT DISTINCT a.antrst, b.Vorname, b.Name
    FROM antraege a
    LEFT JOIN berechtigte b ON a.antrst = b.ID
    WHERE a.antrnr NOT LIKE 'VS%'
      AND a.antrnr NOT LIKE 'X%'
      AND a.antrnr NOT LIKE 'Z%'
      AND a.antrst IS NOT NULL
    ORDER BY b.Name, b.Vorname
");
$antragsteller = $status_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offene Anträge</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        h1 {
            font-size: 24px;
            color: #333;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            background: #0066cc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn:hover {
            background: #0052a3;
        }
        .btn-secondary {
            background: #666;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .filters {
            background: white;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        tr:hover {
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
        .badge.wichtig {
            background: #fff3cd;
            color: #856404;
        }
        .badge.status {
            background: #e7f3ff;
            color: #0066cc;
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
    <a href="index.php" class="back-link">← Zurück zur Übersicht</a>

    <div class="header">
        <h1>Offene Anträge</h1>
        <div class="actions">
            <a href="beschlussbuch.php" class="btn btn-secondary">Beschlussbuch</a>
            <a href="antrag_neu.php" class="btn">+ Neuer Antrag</a>
        </div>
    </div>

    <form method="GET" class="filters">
        <select name="status">
            <option value="all">Alle Antragsteller</option>
            <?php foreach ($antragsteller as $ast): ?>
                <option value="<?= htmlspecialchars($ast['antrst']) ?>"
                        <?= $filter_status == $ast['antrst'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ast['Vorname'] . ' ' . $ast['Name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text"
               name="search"
               placeholder="Suche Nummer/Titel..."
               value="<?= htmlspecialchars($search) ?>">

        <button type="submit">Filtern</button>
        <a href="antragsliste.php" style="padding: 8px 16px; background: #666; color: white; text-decoration: none; border-radius: 4px;">Zurücksetzen</a>
    </form>

    <div class="count">
        <?= count($antraege) ?> offene Anträge
    </div>

    <div class="table-container">
        <?php if (empty($antraege)): ?>
            <div class="empty-state">
                Keine offenen Anträge gefunden.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Antragsnummer</th>
                        <th>Titel / Antragsteller</th>
                        <th>Ressort</th>
                        <th>Letzter Zugriff</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($antraege as $a): ?>
                        <tr>
                            <td>
                                <span style="color: #333; font-weight: 600;"><?= htmlspecialchars($a['antrnr']) ?></span>
                                <?php if ($a['wichtig']): ?>
                                    <span class="badge wichtig">Wichtig</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($a['titel']) ?></div>
                                <div style="font-size: 13px; color: #666;">
                                    <?= htmlspecialchars(($a['Vorname'] ?? '') . ' ' . ($a['Name'] ?? '')) ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $ressort_namen = [];
                                if (!empty($a['ressort1_name'])) {
                                    $ressort_namen[] = $a['ressort1_name'];
                                }
                                if (!empty($a['ressort2_name'])) {
                                    $ressort_namen[] = $a['ressort2_name'];
                                }
                                echo htmlspecialchars(implode(', ', $ressort_namen));
                                ?>
                            </td>
                            <td><?= $a['lzugriff'] ? date('d.m.Y H:i', strtotime($a['lzugriff'])) : '-' ?></td>
                            <td>
                                <a href="antrag_bearbeiten.php?antrnr=<?= urlencode($a['antrnr']) ?>"
                                   class="btn"
                                   style="padding: 6px 12px; font-size: 13px;">
                                    Bearbeiten
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
