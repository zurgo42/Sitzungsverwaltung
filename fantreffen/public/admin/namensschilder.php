<?php
/**
 * Namensschilder zum Drucken
 * Format: Etiketten 105x48mm
 * Layout: Schiffsbild oben (Querformat), Text darunter
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
    header('Location: ../index.php');
    exit;
}

$currentUser = $session->getUser();

// Berechtigung prüfen
$isAdmin = Session::isSuperuser() || $reiseModel->isReiseAdmin($reiseId, $currentUser['user_id']);

if (!$isAdmin) {
    header('Location: ../index.php');
    exit;
}

// Alle Teilnehmer laden - sortiert nach Kabine, dann Nachname
$teilnehmer = $db->fetchAll(
    "SELECT t.vorname, t.name, t.nickname, a.kabine
     FROM fan_anmeldungen a
     JOIN fan_teilnehmer t ON t.teilnehmer_id IN (
         a.teilnehmer1_id, a.teilnehmer2_id, a.teilnehmer3_id, a.teilnehmer4_id
     )
     WHERE a.reise_id = ?
     ORDER BY CAST(a.kabine AS UNSIGNED), a.kabine, t.name, t.vorname",
    [$reiseId]
);

// Schiffsbild URL ermitteln
$schiffBild = $reiseModel->getSchiffBild($reise['schiff']);
$schiffBildAbsolut = '../' . $schiffBild;

// Reisedaten formatieren
$anfangDatum = date('d.m.Y', strtotime($reise['anfang']));
$endeDatum = date('d.m.Y', strtotime($reise['ende']));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Namensschilder - <?= htmlspecialchars($reise['schiff']) ?></title>
    <style>
        /* Seiten-Setup für A4 mit Etiketten 105x48mm (2 Spalten × 5 Reihen = 10 pro Seite) */
        @page {
            size: A4;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #fff;
        }

        /* Steuerleiste - nur am Bildschirm sichtbar */
        .no-print {
            background: linear-gradient(135deg, #0a1f6e 0%, #1a3fa0 100%);
            color: #fff;
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .no-print h2 {
            margin-bottom: 10px;
        }

        .no-print button, .no-print a {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 10px;
            background: #fff;
            color: #0a1f6e;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .no-print button:hover, .no-print a:hover {
            background: #e0e0e0;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding-top: 0 !important; }
        }

        @media screen {
            body { padding-top: 120px; }
        }

        /* Etiketten-Seite (A4: 210x297mm) */
        .etiketten-seite {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 8.5mm 0 8.5mm 0;
            background: #fff;
        }

        @media print {
            .etiketten-seite {
                page-break-after: always;
            }
            .etiketten-seite:last-child {
                page-break-after: avoid;
            }
        }

        /* Etiketten-Container: 2 Spalten */
        .etiketten-row {
            display: flex;
            justify-content: center;
            gap: 0;
            margin-bottom: 0;
        }

        /* Einzelnes Etikett: 105x48mm */
        .etikett {
            width: 105mm;
            height: 48mm;
            border: 1px dashed #ccc;
            display: flex;
            flex-direction: column;
            padding: 2mm 2mm 1mm 2mm;
            overflow: hidden;
        }

        @media print {
            .etikett {
                border: none;
            }
        }

        /* Schiffsbild oben - volle Breite, Höhe nach Bild */
        .etikett-bild {
            width: 100%;
            flex-shrink: 0;
            padding: 0 30px;
        }

        .etikett-bild img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Text-Bereich darunter */
        .etikett-text {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .etikett-info {
            font-size: 8pt;
            color: #666;
            margin-bottom: 1mm;
        }

        .etikett-vorname {
            font-size: 26pt;
            font-weight: bold;
            color: #0a1f6e;
            line-height: 1.0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
        }

        .etikett-nickname {
            font-size: 14pt;
            font-style: italic;
            color: #666;
            margin-top: 0.5mm;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Info am Bildschirm */
        .info-text {
            text-align: center;
            padding: 10px;
            color: #666;
            font-size: 12px;
        }

        @media print {
            .info-text { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <h2>Namensschilder - <?= htmlspecialchars($reise['schiff']) ?></h2>
    <p style="margin-bottom: 15px;">
        <?= count($teilnehmer) ?> Teilnehmer | Etiketten-Format: 105 × 48 mm (2×5 = 10 Stück pro A4-Seite)
    </p>
    <button onclick="window.print()">Drucken</button>
    <a href="teilnehmerliste.php?id=<?= $reiseId ?>">Zurück zur Teilnehmerliste</a>
    <a href="reise-bearbeiten.php?id=<?= $reiseId ?>">Zurück zur Reise</a>
</div>

<?php
// Etiketten in Seiten aufteilen (10 pro Seite = 2 Spalten × 5 Reihen)
$etikettenProSeite = 10;
$seiten = array_chunk($teilnehmer, $etikettenProSeite);

foreach ($seiten as $seiteNr => $seitenTeilnehmer):
?>
<div class="etiketten-seite">
    <?php
    // In Reihen aufteilen (2 pro Reihe)
    $reihen = array_chunk($seitenTeilnehmer, 2);
    foreach ($reihen as $reihe):
    ?>
    <div class="etiketten-row">
        <?php foreach ($reihe as $t): ?>
        <div class="etikett">
            <div class="etikett-bild">
                <img src="<?= htmlspecialchars($schiffBildAbsolut) ?>" alt="<?= htmlspecialchars($reise['schiff']) ?>">
            </div>
            <div class="etikett-text">
                <div class="etikett-info">Fantreffen <?= htmlspecialchars($reise['schiff']) ?> <?= $anfangDatum ?> - <?= $endeDatum ?></div>
                <div class="etikett-vorname"><?= htmlspecialchars($t['vorname']) ?></div>
                <?php if ($t['nickname']): ?>
                    <div class="etikett-nickname">(<?= htmlspecialchars($t['nickname']) ?>)</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php // Leere Etiketten auffüllen wenn Reihe nicht voll
        for ($i = count($reihe); $i < 2; $i++): ?>
        <div class="etikett" style="border-color: transparent;"></div>
        <?php endfor; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (empty($teilnehmer)): ?>
<div class="etiketten-seite">
    <p class="info-text" style="padding: 50px; text-align: center; color: #999;">
        Keine Teilnehmer für diese Reise angemeldet.
    </p>
</div>
<?php endif; ?>

<div class="info-text">
    Tipp: Verwende kompatible Etiketten 105 × 48 mm (z.B. Herma 4457 oder Avery 3425, 10 Stück pro A4-Seite)
</div>

</body>
</html>
