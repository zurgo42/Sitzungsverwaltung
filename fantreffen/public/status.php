<?php
/**
 * Smartphone-optimierte Statusseite für Fantreffen
 * Zeigt Treffpunkt-Infos ohne Login an
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Reise.php';

$db = Database::getInstance();
$reiseModel = new Reise($db);

$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

// Wenn keine ID, zeige aktuelle Reisen
$showList = !$reise;
$aktiveReisen = [];

if ($showList) {
    $aktiveReisen = $reiseModel->getAktive();
}

$statusClass = [
    'geplant' => 'status-planned',
    'bestaetigt' => 'status-confirmed',
    'abgesagt' => 'status-cancelled'
];

$statusText = [
    'geplant' => 'Geplant',
    'bestaetigt' => 'Bestätigt',
    'abgesagt' => 'Abgesagt'
];

$statusEmoji = [
    'geplant' => '📅',
    'bestaetigt' => '✅',
    'abgesagt' => '❌'
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Fantreffen Status<?= $reise ? ' - ' . htmlspecialchars($reise['schiff']) : '' ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
            padding-bottom: env(safe-area-inset-bottom, 20px);
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 300;
            opacity: 0.8;
        }

        .ship-name {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .dates {
            font-size: 1rem;
            opacity: 0.7;
        }

        .status-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .status-badge {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-planned {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .status-confirmed {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }

        .status-cancelled {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
        }

        .meeting-info {
            margin-top: 20px;
        }

        .meeting-info h2 {
            font-size: 1.2rem;
            opacity: 0.8;
            margin-bottom: 10px;
        }

        .meeting-location {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .meeting-time {
            font-size: 1.3rem;
            opacity: 0.9;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            text-align: left;
            line-height: 1.6;
        }

        .refresh-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 20px;
        }

        .refresh-btn:active {
            background: rgba(255, 255, 255, 0.2);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
        }

        /* Liste der Reisen */
        .trip-list {
            list-style: none;
        }

        .trip-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            text-decoration: none;
            color: #fff;
            display: block;
        }

        .trip-item:active {
            background: rgba(255, 255, 255, 0.2);
        }

        .trip-item h3 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .trip-item .trip-dates {
            opacity: 0.7;
            font-size: 0.9rem;
        }

        .trip-item .trip-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 10px;
        }

        .no-trips {
            text-align: center;
            padding: 40px;
            opacity: 0.6;
        }

        .last-update {
            text-align: center;
            font-size: 0.8rem;
            opacity: 0.5;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($showList): ?>
            <!-- Liste aller aktiven Reisen -->
            <div class="header">
                <h1>AIDA Fantreffen</h1>
            </div>

            <?php if (empty($aktiveReisen)): ?>
                <div class="no-trips">
                    <p>Derzeit keine aktiven Fantreffen</p>
                </div>
            <?php else: ?>
                <ul class="trip-list">
                    <?php foreach ($aktiveReisen as $r): ?>
                        <a href="status.php?id=<?= $r['reise_id'] ?>" class="trip-item">
                            <h3><?= htmlspecialchars($r['schiff']) ?></h3>
                            <div class="trip-dates">
                                <?= date('d.m.', strtotime($r['anfang'])) ?> -
                                <?= date('d.m.Y', strtotime($r['ende'])) ?>
                            </div>
                            <span class="trip-status <?= $statusClass[$r['treffen_status']] ?>">
                                <?= $statusEmoji[$r['treffen_status']] ?>
                                <?= $statusText[$r['treffen_status']] ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php else: ?>
            <!-- Einzelne Reise -->
            <div class="header">
                <h1>AIDA Fantreffen</h1>
                <div class="ship-name"><?= htmlspecialchars($reise['schiff']) ?></div>
                <div class="dates">
                    <?= date('d.m.', strtotime($reise['anfang'])) ?> -
                    <?= date('d.m.Y', strtotime($reise['ende'])) ?>
                </div>
            </div>

            <div class="status-card">
                <div class="status-badge <?= $statusClass[$reise['treffen_status']] ?>">
                    <?= $statusEmoji[$reise['treffen_status']] ?>
                    Treffen <?= $statusText[$reise['treffen_status']] ?>
                </div>

                <?php if ($reise['treffen_ort'] || $reise['treffen_zeit']): ?>
                    <div class="meeting-info">
                        <h2>Treffpunkt</h2>
                        <?php if ($reise['treffen_ort']): ?>
                            <div class="meeting-location">
                                📍 <?= htmlspecialchars($reise['treffen_ort']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($reise['treffen_zeit']): ?>
                            <div class="meeting-time">
                                🕐 <?= date('d.m.Y', strtotime($reise['treffen_zeit'])) ?>
                                um <?= date('H:i', strtotime($reise['treffen_zeit'])) ?> Uhr
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($reise['treffen_info']): ?>
                    <div class="info-box">
                        <?= nl2br(htmlspecialchars($reise['treffen_info'])) ?>
                    </div>
                <?php endif; ?>

                <button class="refresh-btn" onclick="location.reload()">
                    🔄 Aktualisieren
                </button>
            </div>

            <a href="status.php" class="back-link">← Alle Reisen anzeigen</a>
        <?php endif; ?>

        <div class="last-update">
            Zuletzt aktualisiert: <?= date('H:i:s') ?>
        </div>
    </div>

    <script>
        // Auto-Refresh alle 60 Sekunden
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>
