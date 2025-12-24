<?php
/**
 * Namensschilder zum Drucken
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$reiseModel = new Reise($db);

$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('Location: ../reisen.php');
    exit;
}

$currentUser = $session->getUser();

// Berechtigung prüfen
$isAdmin = Session::isSuperuser();
if (!$isAdmin) {
    $admins = $reiseModel->getAdmins($reiseId);
    foreach ($admins as $admin) {
        if ($admin['user_id'] === $currentUser['user_id']) {
            $isAdmin = true;
            break;
        }
    }
}

if (!$isAdmin) {
    header('Location: ../dashboard.php');
    exit;
}

// Alle Teilnehmer laden
$teilnehmer = $db->fetchAll(
    "SELECT t.vorname, t.name, t.nickname
     FROM fan_anmeldungen a
     JOIN fan_teilnehmer t ON JSON_CONTAINS(a.teilnehmer_ids, CAST(t.teilnehmer_id AS CHAR))
     WHERE a.reise_id = ?
     ORDER BY t.vorname, t.name",
    [$reiseId]
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Namensschilder - <?= htmlspecialchars($reise['schiff']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
        }

        .no-print {
            padding: 20px;
            background: #f5f5f5;
            margin-bottom: 20px;
        }

        @media print {
            .no-print { display: none !important; }
        }

        .container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5mm;
            padding: 5mm;
        }

        .namensschild {
            width: 85mm;
            height: 55mm;
            border: 1px solid #000;
            border-radius: 5mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 5mm;
            page-break-inside: avoid;
            background: linear-gradient(135deg, #f5f5f5 0%, #fff 100%);
        }

        .namensschild .logo {
            font-size: 10pt;
            color: #0a1f6e;
            margin-bottom: 3mm;
            font-weight: bold;
        }

        .namensschild .vorname {
            font-size: 24pt;
            font-weight: bold;
            color: #0a1f6e;
            margin-bottom: 2mm;
        }

        .namensschild .name {
            font-size: 14pt;
            color: #333;
            margin-bottom: 2mm;
        }

        .namensschild .nickname {
            font-size: 12pt;
            font-style: italic;
            color: #666;
        }

        .namensschild .schiff {
            font-size: 8pt;
            color: #999;
            margin-top: 3mm;
        }
    </style>
</head>
<body>

<div class="no-print">
    <h2>Namensschilder - <?= htmlspecialchars($reise['schiff']) ?></h2>
    <p><?= count($teilnehmer) ?> Teilnehmer</p>
    <p>
        <button onclick="window.print()">Drucken</button>
        <a href="teilnehmerliste.php?id=<?= $reiseId ?>">Zurück zur Liste</a>
    </p>
</div>

<div class="container">
    <?php foreach ($teilnehmer as $t): ?>
        <div class="namensschild">
            <div class="logo">AIDA Fantreffen</div>
            <div class="vorname"><?= htmlspecialchars($t['vorname']) ?></div>
            <div class="name"><?= htmlspecialchars($t['name']) ?></div>
            <?php if ($t['nickname']): ?>
                <div class="nickname">"<?= htmlspecialchars($t['nickname']) ?>"</div>
            <?php endif; ?>
            <div class="schiff"><?= htmlspecialchars($reise['schiff']) ?> | <?= date('m/Y', strtotime($reise['anfang'])) ?></div>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
