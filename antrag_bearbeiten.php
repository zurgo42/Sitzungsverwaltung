<?php
/**
 * antrag_bearbeiten.php - Vollständige Antragsverwaltung mit kompaktem Layout
 * Rechte: aktiv > 10
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

// Hilfsfunktionen
function getUserData($pdo, $member_id) {
    $stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
    $stmt->execute([$member_id]);
    return $stmt->fetch();
}

function getVerfuegungsberechtigte($pdo) {
    $stmt = $pdo->query("SELECT ID, KurzN, Funktion FROM berechtigte WHERE aktiv >= 18 ORDER BY KurzN");
    return $stmt->fetchAll();
}

function berechneWartezeit($antrnr) {
    if (strlen($antrnr) < 7) return null;
    $jahr = '20' . substr($antrnr, 1, 2);
    $monat = substr($antrnr, 3, 2);
    $tag = substr($antrnr, 5, 2);
    $antragsdatum = strtotime("$jahr-$monat-$tag");
    if (!$antragsdatum) return null;
    $wartezeit_bis = $antragsdatum + (7 * 24 * 60 * 60);
    return time() >= $wartezeit_bis ? 'erfüllt' : date('d.m.Y', $wartezeit_bis);
}

function berechneMonatssumme($pdo, $member_id, $antrnr) {
    $jahr_monat = substr($antrnr, 1, 4); // JJMM
    $stmt = $pdo->prepare("
        SELECT SUM(fin) as summe
        FROM antraege
        WHERE antrst = ?
        AND fin < 600
        AND SUBSTRING(antrnr, 2, 4) = ?
        AND antrnr != ?
    ");
    $stmt->execute([$member_id, $jahr_monat, $antrnr]);
    return (float)($stmt->fetchColumn() ?? 0);
}

// Benutzer-Daten
$user = getUserData($pdo, $_SESSION['member_id']);
$user_aktiv = (int)($user['aktiv'] ?? 0);

if ($user_aktiv <= 10) {
    die("Keine Berechtigung. Sie benötigen aktiv > 10 um Anträge zu bearbeiten.");
}

$antrnr = $_GET['antrnr'] ?? '';
if (!$antrnr) die("Keine Antragsnummer angegeben.");

// Antrag laden mit Antragsteller-Daten
$stmt = $pdo->prepare("
    SELECT a.*, b.Vorname, b.Nachname, b.KurzN as AntragstellerKurz
    FROM antraege a
    LEFT JOIN berechtigte b ON a.antrst = b.ID
    WHERE a.antrnr = ?
");
$stmt->execute([$antrnr]);
$antrag = $stmt->fetch();
if (!$antrag) die("Antrag nicht gefunden.");

$saved = false;
$error = null;
$message = null;

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save':
                speichereAntrag($pdo, $antrnr, $_POST, $antrag, $user);
                $saved = true;
                $message = "Antrag gespeichert.";
                break;

            case 'finalize':
                finalisiereAntrag($pdo, $antrnr, $_POST, $antrag, $user);
                $saved = true;
                $message = "Antrag wurde verbindlich eingestellt.";
                // Redirect to updated antrnr
                $neue_nr = $_POST['neue_antrnr'] ?? $antrnr;
                header("Location: antrag_bearbeiten.php?antrnr=" . urlencode($neue_nr) . "&msg=finalized");
                exit;

            case 'delete':
                loescheAntrag($pdo, $antrnr, $antrag, $user);
                header('Location: antragsliste.php?msg=deleted');
                exit;

            case 'verkuerzung':
                wartezeitVerkuerzung($pdo, $antrnr, $antrag, $user);
                $saved = true;
                $message = "Wartezeitverkürzung gespeichert.";
                break;
        }

        // Antrag neu laden
        $stmt->execute([$antrnr]);
        $antrag = $stmt->fetch();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Speichern-Funktion
function speichereAntrag($pdo, $antrnr, $post, $antrag, $user) {
    $fin = floatval($post['fin'] ?? 0);

    // Monatssummen-Prüfung für Verfügungen
    $monatssumme = berechneMonatssumme($pdo, $antrag['antrst'], $antrnr);
    if ($fin < 600 && ($monatssumme + $fin) > 2000) {
        throw new Exception("Monatliche Verfügungsgrenze von 2000€ überschritten! Aktuelle Summe: " . number_format($monatssumme, 2) . "€");
    }

    // bart berechnen
    $bart = ($monatssumme + $fin) > 600 || $fin >= 600 ? ($fin <= 3000 ? 'R' : 'B') : 'V';

    // Wichtig-Flag
    $wichtig = 0;
    if (isset($post['wichtig_escalate']) && $user['aktiv'] >= 18) {
        $wichtig = $user['ID'];
    }
    if ($wichtig > 0) $bart = 'B'; // Eskaliert zu Vorstandsbeschluss

    // sofort-Wert
    $sofort = 0;
    if (isset($post['sofort_1'])) $sofort = 1;
    elseif (isset($post['sofort_2'])) $sofort = 2;

    // Hinweis anhängen
    $hinweis = $antrag['hinweis'] ?? '';
    if (!empty($post['neuerhinweis'])) {
        if (!empty($hinweis)) $hinweis .= "\n---\n";
        $hinweis .= date('d.m.Y H:i') . ' (' . $user['KurzN'] . '): ' . $post['neuerhinweis'];
    }

    // File-Upload-Handling
    for ($i = 1; $i <= 4; $i++) {
        $file_field = "file$i";
        if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/antraege/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $filename = $antrnr . '_f' . $i . '_' . basename($_FILES[$file_field]['name']);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES[$file_field]['tmp_name'], $filepath)) {
                $post[$file_field] = 'uploads/antraege/' . $filename;
            }
        } else {
            $post[$file_field] = $antrag[$file_field] ?? null;
        }
    }

    $update = $pdo->prepare("
        UPDATE antraege SET
            titel = ?, beschluss = ?, begr = ?, pers = ?, sach = ?,
            fintext = ?, fin = ?, bart = ?, verant = ?,
            ressort1 = ?, ressort2 = ?, wichtig = ?,
            int_ext = ?, verein = ?, hinweis = ?, thread = ?,
            file1 = ?, file2 = ?, file3 = ?, file4 = ?,
            filetext1 = ?, filetext2 = ?, filetext3 = ?, filetext4 = ?,
            sofort = ?, durch = ?, zufin = ?, zbem = ?, praesenz = ?,
            lzugriff = NOW()
        WHERE antrnr = ?
    ");

    $update->execute([
        $post['titel'], $post['beschluss'], $post['begr'] ?? null,
        $post['pers'] ?? null, $post['sach'] ?? null, $post['fintext'] ?? null,
        $fin, $bart, $post['verant'] ?? null,
        $post['ressort1'] ?? null, $post['ressort2'] ?? null, $wichtig,
        $post['int_ext'] ?? null, $post['verein'] ?? 'V', $hinweis, $post['thread'] ?? null,
        $post['file1'], $post['file2'], $post['file3'], $post['file4'],
        $post['filetext1'] ?? null, $post['filetext2'] ?? null,
        $post['filetext3'] ?? null, $post['filetext4'] ?? null,
        $sofort, $post['durch'] ?? null,
        isset($post['zufin']) ? 1 : 0, $post['zbem'] ?? null,
        isset($post['praesenz']) ? 1 : 0, $antrnr
    ]);
}

// Finalisieren-Funktion
function finalisiereAntrag($pdo, $antrnr, $post, $antrag, $user) {
    speichereAntrag($pdo, $antrnr, $post, $antrag, $user);

    // Antrag neu laden
    $stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
    $antrag = $stmt->fetch();

    if (empty($antrag['ressort1'])) throw new Exception("Ressort muss angegeben werden.");
    if (empty($antrag['verant']) || strlen($antrag['verant']) < 3) throw new Exception("Verantwortlicher muss angegeben werden.");

    $prefix = substr($antrnr, 0, 1);
    if ($prefix !== 'A') throw new Exception("Nur Anträge im Editiermodus können finalisiert werden.");

    // Verf1/Verf2 automatisch setzen
    if ($antrag['bart'] === 'V') {
        // Verfügung: Antragsteller ist verf1
        $verf1 = $antrag['antrst'];
        $update = $pdo->prepare("UPDATE antraege SET verf1 = ? WHERE antrnr = ?");
        $update->execute([$verf1, $antrnr]);
    } elseif ($antrag['bart'] === 'R') {
        // Ressortbeschluss: Ressortvorstand + Finanzvorstand
        $verf1 = $antrag['antrst']; // Vereinfacht: Antragsteller
        // Finanzvorstand holen
        $stmt_fvo = $pdo->query("SELECT ID FROM berechtigte WHERE Funktion IN ('FVo', 'FVv') ORDER BY Funktion LIMIT 1");
        $fvo = $stmt_fvo->fetch();
        $verf2 = $fvo['ID'] ?? 0;

        $update = $pdo->prepare("UPDATE antraege SET verf1 = ?, verf2 = ? WHERE antrnr = ?");
        $update->execute([$verf1, $verf2, $antrnr]);
    }

    // Neue Nummer generieren
    $neue_nr = 'B' . substr($antrnr, 1);

    // Bei Verfügung durch Antragsteller: direkt VS
    if ($antrag['bart'] === 'V' && $verf1 == $antrag['antrst']) {
        $neue_nr = 'VS' . date('ymd') . substr($antrnr, 7);
    }

    $update = $pdo->prepare("UPDATE antraege SET antrnr = ? WHERE antrnr = ?");
    $update->execute([$neue_nr, $antrnr]);

    $_POST['neue_antrnr'] = $neue_nr;
}

// Löschen-Funktion
function loescheAntrag($pdo, $antrnr, $antrag, $user) {
    if ($antrag['antrst'] != $user['ID'] && $user['aktiv'] < 18) {
        throw new Exception("Keine Berechtigung zum Löschen.");
    }
    $pdo->prepare("DELETE FROM antraege WHERE antrnr = ?")->execute([$antrnr]);
}

// Wartezeitverkürzung
function wartezeitVerkuerzung($pdo, $antrnr, $antrag, $user) {
    if ($user['aktiv'] < 18 || $antrag['antrst'] == $user['ID']) {
        throw new Exception("Antragsteller darf nicht der Wartezeitverkürzung zustimmen.");
    }

    $verk1 = $antrag['verk1'] ?? 0;
    $verk2 = $antrag['verk2'] ?? 0;

    if (!$verk1) {
        $update = $pdo->prepare("UPDATE antraege SET verk1 = ? WHERE antrnr = ?");
        $update->execute([$user['ID'], $antrnr]);
    } elseif (!$verk2 && $verk1 != $user['ID']) {
        $update = $pdo->prepare("UPDATE antraege SET verk2 = ? WHERE antrnr = ?");
        $update->execute([$user['ID'], $antrnr]);
    } else {
        throw new Exception("Wartezeitverkürzung bereits vollständig.");
    }
}

// Daten für UI
$ressorts = $pdo->query("SELECT DISTINCT ressort, klartext FROM ressortliste ORDER BY ressort")->fetchAll();
$verfuegungsber = getVerfuegungsberechtigte($pdo);
$wartezeit = berechneWartezeit($antrnr);
$wartezeit_erfuellt = ($wartezeit === 'erfüllt' || ($antrag['verk1'] && $antrag['verk2']) || $antrag['bart'] === 'B');
$monatssumme = berechneMonatssumme($pdo, $antrag['antrst'], $antrnr);

$blockiert = false;
$blockierung_grund = [];
if (empty($antrag['ressort1'])) { $blockiert = true; $blockierung_grund[] = "Ressort fehlt"; }
if (empty($antrag['verant']) || strlen($antrag['verant']) < 3) { $blockiert = true; $blockierung_grund[] = "Verantwortlicher fehlt"; }

$kann_finalisieren = !$blockiert && $wartezeit_erfuellt && substr($antrnr, 0, 1) === 'A' &&
                     ($antrag['antrst'] == $user['ID'] || $user['aktiv'] >= 18);
$kann_loeschen = ($antrag['antrst'] == $user['ID'] || $user['aktiv'] >= 18);
$kann_verkuerzen = ($user['aktiv'] >= 18 && $antrag['antrst'] != $user['ID'] &&
                    (!$antrag['verk1'] || (!$antrag['verk2'] && $antrag['verk1'] != $user['ID'])));

// Verf-Namen holen
$verf1_name = $verf2_name = '';
if ($antrag['verf1']) {
    $stmt = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
    $stmt->execute([$antrag['verf1']]);
    $verf1_name = $stmt->fetchColumn() ?: '';
}
if ($antrag['verf2']) {
    $stmt = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
    $stmt->execute([$antrag['verf2']]);
    $verf2_name = $stmt->fetchColumn() ?: '';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrag: <?= htmlspecialchars($antrag['antrnr']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 15px; font-size: 14px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; padding: 12px 15px; margin-bottom: 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        h1 { font-size: 18px; color: #333; }
        .antrnr { color: #666; font-size: 13px; margin-left: 10px; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; margin-left: 8px; }
        .status-editing { background: #fff3cd; color: #856404; }
        .status-voting { background: #cce5ff; color: #004085; }
        .status-finalized { background: #d4edda; color: #155724; }
        .form-container { background: white; padding: 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 10px; }
        .form-row.two-col { grid-template-columns: 1fr 1fr; }
        .form-row.full { grid-template-columns: 1fr; }
        .form-group { margin-bottom: 10px; }
        label { display: block; font-weight: 600; margin-bottom: 3px; color: #333; font-size: 13px; }
        .required { color: #d32f2f; }
        input[type="text"], input[type="number"], textarea, select { width: 100%; padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: inherit; }
        textarea { min-height: 60px; resize: vertical; }
        textarea.large { min-height: 80px; }
        .read-only { background: #f8f9fa; color: #666; }
        .actions { display: flex; gap: 8px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; flex-wrap: wrap; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; }
        .btn-primary { background: #0066cc; color: white; }
        .btn-primary:hover { background: #0052a3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-warning:hover { background: #e0a800; }
        .btn-secondary { background: #666; color: white; }
        .btn-secondary:hover { background: #555; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .alert { padding: 10px 12px; border-radius: 4px; margin-bottom: 12px; font-size: 13px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info-box { background: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 3px solid #0066cc; margin-bottom: 12px; font-size: 13px; }
        .section-title { font-size: 14px; font-weight: 700; color: #0066cc; margin: 15px 0 8px 0; padding-bottom: 4px; border-bottom: 2px solid #0066cc; }
        .back-link { display: inline-block; margin-bottom: 12px; color: #0066cc; text-decoration: none; font-size: 13px; }
        .file-item { background: #f8f9fa; padding: 8px; border-radius: 4px; margin-bottom: 8px; }
        .checkbox-inline { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
        .checkbox-inline input[type="checkbox"] { width: auto; }
        .help-text { font-size: 11px; color: #666; margin-top: 2px; }
        .hint-box { background: #fffbea; padding: 8px; border-radius: 4px; margin-top: 8px; font-size: 12px; font-style: italic; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <a href="antragsliste.php" class="back-link">← Zurück zur Antragsliste</a>

        <div class="header">
            <div>
                <h1>Antrag bearbeiten</h1>
                <span class="antrnr">
                    <?= htmlspecialchars($antrag['antrnr']) ?>
                    <?php
                    $prefix = substr($antrnr, 0, 1);
                    if ($prefix === 'A') echo '<span class="status-badge status-editing">Editiermodus</span>';
                    elseif ($prefix === 'B') echo '<span class="status-badge status-voting">In Abstimmung</span>';
                    elseif (substr($antrnr, 0, 2) === 'VS') echo '<span class="status-badge status-finalized">Beschlossen</span>';
                    ?>
                </span>
            </div>
        </div>

        <?php if ($saved): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($message ?? 'Gespeichert') ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">✗ Fehler: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (substr($antrnr, 0, 1) === 'A'): ?>
            <div class="info-box">
                <strong>Editiermodus:</strong> Anträge können von allen im Führungsteam bearbeitet werden.
                <?php if ($wartezeit && $wartezeit !== 'erfüllt'): ?>
                    <strong>Wartezeit bis <?= htmlspecialchars($wartezeit) ?></strong>
                    <?php if (!empty($antrag['verkb'])): ?>
                        - Wartezeitverkürzung beantragt
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($blockiert): ?>
            <div class="alert alert-error">
                <strong>Unvollständig:</strong> <?= implode(', ', $blockierung_grund) ?>
            </div>
        <?php endif; ?>

        <?php if ($monatssumme > 0 && ($antrag['fin'] ?? 0) < 600): ?>
            <div class="alert alert-warning">
                <strong>Monatssumme Verfügungen:</strong> <?= number_format($monatssumme, 2) ?>€ + aktuell <?= number_format($antrag['fin'] ?? 0, 2) ?>€ = <?= number_format($monatssumme + ($antrag['fin'] ?? 0), 2) ?>€
                (Max. 2000€/Monat, sonst Ressortbeschluss)
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-container">
            <!-- Stammdaten -->
            <div class="form-row">
                <div class="form-group">
                    <label>Antragsnummer</label>
                    <input type="text" value="<?= htmlspecialchars($antrag['antrnr']) ?>" class="read-only" readonly>
                </div>
                <div class="form-group">
                    <label>Antragsteller</label>
                    <input type="text" value="<?= htmlspecialchars(($antrag['Vorname'] ?? '') . ' ' . ($antrag['Nachname'] ?? '')) ?>" class="read-only" readonly>
                </div>
                <div class="form-group">
                    <label>Beschlussart</label>
                    <input type="text" value="<?= $antrag['bart'] === 'V' ? 'Verfügung' : ($antrag['bart'] === 'R' ? 'Ressortbeschluss' : 'Vorstandsbeschluss') ?>" class="read-only" readonly>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="titel">Titel <span class="required">*</span></label>
                    <input type="text" id="titel" name="titel" value="<?= htmlspecialchars($antrag['titel']) ?>" required>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="beschluss">Beschlusstext <span class="required">*</span></label>
                    <textarea id="beschluss" name="beschluss" class="large" required><?= htmlspecialchars($antrag['beschluss']) ?></textarea>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="begr">Begründung</label>
                    <textarea id="begr" name="begr"><?= htmlspecialchars($antrag['begr'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pers">Personelle Auswirkungen</label>
                    <textarea id="pers" name="pers"><?= htmlspecialchars($antrag['pers'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="sach">Sachliche Auswirkungen</label>
                    <textarea id="sach" name="sach"><?= htmlspecialchars($antrag['sach'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="verant">Verantwortlich <span class="required">*</span></label>
                    <input type="text" id="verant" name="verant" value="<?= htmlspecialchars($antrag['verant'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fin">Betrag (€)</label>
                    <input type="number" id="fin" name="fin" step="0.01" min="0" value="<?= htmlspecialchars($antrag['fin'] ?? '0') ?>">
                    <div class="help-text">≤600€=Verfügung | 601-3000€=Ressort | >3000€=Vorstand</div>
                </div>
                <div class="form-group">
                    <label for="ressort1">Ressort <span class="required">*</span></label>
                    <select id="ressort1" name="ressort1" required>
                        <option value="">-- Bitte wählen --</option>
                        <?php foreach ($ressorts as $r): ?>
                            <option value="<?= htmlspecialchars($r['ressort']) ?>" <?= $antrag['ressort1'] === $r['ressort'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['klartext'] ?? $r['ressort']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ressort2">Mitwirkendes Ressort</label>
                    <select id="ressort2" name="ressort2">
                        <option value="">-- Kein weiteres --</option>
                        <?php foreach ($ressorts as $r): ?>
                            <option value="<?= htmlspecialchars($r['ressort']) ?>" <?= $antrag['ressort2'] === $r['ressort'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['klartext'] ?? $r['ressort']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="fintext">Finanzielle Auswirkungen (Beschreibung)</label>
                    <textarea id="fintext" name="fintext"><?= htmlspecialchars($antrag['fintext'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="verein">Verein/Stiftung</label>
                    <select id="verein" name="verein">
                        <option value="V" <?= ($antrag['verein'] ?? 'V') === 'V' ? 'selected' : '' ?>>Verein</option>
                        <option value="S" <?= ($antrag['verein'] ?? '') === 'S' ? 'selected' : '' ?>>Stiftung</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="int_ext">Sichtbarkeit</label>
                    <select id="int_ext" name="int_ext">
                        <option value="e" <?= ($antrag['int_ext'] ?? 'e') === 'e' ? 'selected' : '' ?>>Extern (alle Ms)</option>
                        <option value="n" <?= ($antrag['int_ext'] ?? '') === 'n' ? 'selected' : '' ?>>Nicht öffentlich (Führung)</option>
                        <option value="i" <?= ($antrag['int_ext'] ?? '') === 'i' ? 'selected' : '' ?>>Intern (nur Vorstand)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="thread">Forum-ID</label>
                    <input type="number" id="thread" name="thread" min="0" value="<?= htmlspecialchars($antrag['thread'] ?? '') ?>">
                    <?php if (($antrag['thread'] ?? 0) > 0): ?>
                        <a href="https://vorstand.mensa.de/forum/index.php?id=<?= $antrag['thread'] ?>" target="forum" style="font-size: 11px;">→ Forum</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($antrag['verf1'] || $antrag['verf2']): ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Verfügungsberechtigt 1</label>
                    <input type="text" value="<?= htmlspecialchars($verf1_name) ?>" class="read-only" readonly>
                </div>
                <?php if ($antrag['verf2']): ?>
                <div class="form-group">
                    <label>Verfügungsberechtigt 2</label>
                    <input type="text" value="<?= htmlspecialchars($verf2_name) ?>" class="read-only" readonly>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Dateien -->
            <div class="section-title">Angebote / Unterlagen</div>
            <div class="form-row two-col">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div class="file-item">
                        <label for="file<?= $i ?>">Datei <?= $i ?></label>
                        <?php if (!empty($antrag["file$i"])): ?>
                            <div style="font-size: 11px; color: #666; margin-bottom: 4px;">
                                Aktuell: <a href="<?= htmlspecialchars($antrag["file$i"]) ?>" target="_blank"><?= basename($antrag["file$i"]) ?></a>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="file<?= $i ?>" name="file<?= $i ?>" style="font-size: 12px; padding: 4px;">
                        <input type="text" name="filetext<?= $i ?>" placeholder="Beschreibung (z.B. 'Angebot')"
                               value="<?= htmlspecialchars($antrag["filetext$i"] ?? '') ?>" style="margin-top: 4px;">
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Vereinfachte Freigabe -->
            <div class="section-title">Vereinfachte Freigabe</div>
            <div class="checkbox-inline">
                <input type="checkbox" id="sofort_1" name="sofort_1" value="1" <?= ($antrag['sofort'] ?? 0) == 1 ? 'checked' : '' ?>
                       onchange="if(this.checked) document.getElementById('sofort_2').checked = false;">
                <label for="sofort_1" style="margin:0;">Rechnung = Angebot: sofort überweisen</label>
            </div>
            <div style="padding-left: 20px; margin-bottom: 8px;">
                <div class="checkbox-inline">
                    <input type="checkbox" id="sofort_2" name="sofort_2" value="2" <?= ($antrag['sofort'] ?? 0) == 2 ? 'checked' : '' ?>
                           onchange="if(this.checked) document.getElementById('sofort_1').checked = false;">
                    <label for="sofort_2" style="margin:0;">Nach Vorprüfung durch:</label>
                </div>
                <input type="text" name="durch" placeholder="Name der prüfenden Person" value="<?= htmlspecialchars($antrag['durch'] ?? '') ?>" style="margin-left: 20px; max-width: 300px;">
            </div>
            <div class="checkbox-inline">
                <input type="checkbox" id="zufin" name="zufin" value="1" <?= ($antrag['zufin'] ?? 0) ? 'checked' : '' ?>>
                <label for="zufin" style="margin:0;"><strong>Zustimmung Finanzvorstand</strong></label>
            </div>
            <div class="form-group">
                <label for="zbem">Bemerkung zum Zahlungsvorgang</label>
                <textarea id="zbem" name="zbem" style="min-height: 50px;"><?= htmlspecialchars($antrag['zbem'] ?? '') ?></textarea>
            </div>

            <!-- Präsenzsitzung -->
            <div class="checkbox-inline">
                <input type="checkbox" id="praesenz" name="praesenz" value="1" <?= ($antrag['praesenz'] ?? 0) ? 'checked' : '' ?>>
                <label for="praesenz" style="margin:0;"><strong>Präsenzsitzung/Telko</strong> (nicht online abstimmen)</label>
            </div>

            <!-- Wichtig-Eskalation für Vorstand -->
            <?php if ($user['aktiv'] >= 18 && ($antrag['fin'] ?? 0) <= 3000 && $antrag['bart'] !== 'B'): ?>
            <div class="checkbox-inline" style="margin-top: 12px; padding: 8px; background: #fff3cd; border-radius: 4px;">
                <input type="checkbox" id="wichtig_escalate" name="wichtig_escalate" value="1" <?= ($antrag['wichtig'] ?? 0) > 0 ? 'checked' : '' ?>>
                <label for="wichtig_escalate" style="margin:0;"><strong>Als VM: Vorstandsbeschluss erforderlich</strong> (unabhängig von Betrag)</label>
                <input type="hidden" name="wichtig_member_id" value="<?= $user['ID'] ?>">
            </div>
            <?php endif; ?>

            <!-- Wartezeitverkürzung -->
            <?php if (!empty($antrag['verkb']) && substr($antrnr, 0, 1) === 'A'): ?>
            <div class="section-title">Wartezeitverkürzung</div>
            <div class="info-box">
                <strong>Begründung:</strong> <?= nl2br(htmlspecialchars($antrag['verkb'])) ?>
                <div style="margin-top: 8px;">
                    <?php if ($antrag['verk1']): ?>
                        <div>✓ Zustimmung 1: <?php
                            $stmt = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
                            $stmt->execute([$antrag['verk1']]);
                            echo htmlspecialchars($stmt->fetchColumn());
                        ?></div>
                    <?php endif; ?>
                    <?php if ($antrag['verk2']): ?>
                        <div>✓ Zustimmung 2: <?php
                            $stmt = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
                            $stmt->execute([$antrag['verk2']]);
                            echo htmlspecialchars($stmt->fetchColumn());
                        ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($kann_verkuerzen): ?>
                    <button type="submit" name="action" value="verkuerzung" class="btn btn-warning" style="margin-top: 8px;">
                        Wartezeitverkürzung zustimmen
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Hinweise -->
            <?php if (!empty($antrag['hinweis'])): ?>
            <div class="section-title">Bisherige Hinweise</div>
            <div class="hint-box"><?= nl2br(htmlspecialchars($antrag['hinweis'])) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="neuerhinweis">Neuer Hinweis</label>
                <textarea id="neuerhinweis" name="neuerhinweis" placeholder="Wird mit Zeitstempel angehängt..." style="min-height: 50px;"></textarea>
            </div>

            <!-- Actions -->
            <div class="actions">
                <button type="submit" name="action" value="save" class="btn btn-primary">Speichern</button>

                <?php if ($kann_finalisieren): ?>
                    <button type="submit" name="action" value="finalize" class="btn btn-success"
                            onclick="return confirm('Antrag verbindlich einstellen? Nicht mehr änderbar!');">
                        Verbindlich einstellen
                    </button>
                <?php elseif (substr($antrnr, 0, 1) === 'A'): ?>
                    <button type="button" class="btn btn-success" disabled title="<?= implode(', ', $blockierung_grund) ?>">
                        Verbindlich einstellen <?= !$wartezeit_erfuellt ? '(Wartezeit)' : '' ?>
                    </button>
                <?php endif; ?>

                <?php if ($kann_loeschen && substr($antrnr, 0, 1) === 'A'): ?>
                    <button type="submit" name="action" value="delete" class="btn btn-danger"
                            onclick="return confirm('ACHTUNG: Unwiderruflich löschen?');">
                        Löschen
                    </button>
                <?php endif; ?>

                <a href="antragsliste.php" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</body>
</html>
