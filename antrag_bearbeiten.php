<?php
/**
 * antrag_bearbeiten.php - Antrag bearbeiten mit vollständiger Workflow-Logik
 *
 * Bearbeitung eines bestehenden Antrags mit Statusübergängen:
 * - A (Editieren) → B (Abstimmung) → VS (Beschluss)
 * - Rechte: aktiv > 10
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

// Hilfsfunktion: aktiv-Level des aktuellen Users holen
function getUserAktivLevel($pdo, $member_id) {
    $stmt = $pdo->prepare("SELECT aktiv FROM berechtigte WHERE ID = ?");
    $stmt->execute([$member_id]);
    $result = $stmt->fetch();
    return $result ? (int)$result['aktiv'] : 0;
}

// Hilfsfunktion: Berechtigte mit Funktion holen
function getBerechtigteByFunktion($pdo, $funktion) {
    $stmt = $pdo->prepare("SELECT ID, KurzN FROM berechtigte WHERE Funktion = ? ORDER BY KurzN");
    $stmt->execute([$funktion]);
    return $stmt->fetchAll();
}

// Hilfsfunktion: Alle aktiven Vorstandsmitglieder holen
function getVorstandsmitglieder($pdo) {
    $stmt = $pdo->query("SELECT ID, KurzN FROM berechtigte WHERE aktiv >= 18 ORDER BY KurzN");
    return $stmt->fetchAll();
}

// Hilfsfunktion: Wartezeit berechnen (7 Tage ab Antragsdatum)
function berechneWartezeit($antrnr) {
    if (strlen($antrnr) < 7) return null;

    // Extrahiere Datum aus Antragsnummer (Format: AJJMMTT...)
    $jahr = '20' . substr($antrnr, 1, 2);
    $monat = substr($antrnr, 3, 2);
    $tag = substr($antrnr, 5, 2);

    $antragsdatum = strtotime("$jahr-$monat-$tag");
    if (!$antragsdatum) return null;

    $wartezeit_bis = $antragsdatum + (7 * 24 * 60 * 60); // +7 Tage

    if (time() >= $wartezeit_bis) {
        return 'erfüllt';
    } else {
        return date('d.m.Y', $wartezeit_bis);
    }
}

// Berechtigungen prüfen
$user_aktiv = getUserAktivLevel($pdo, $_SESSION['member_id']);

if ($user_aktiv <= 10) {
    die("Keine Berechtigung. Sie benötigen aktiv > 10 um Anträge zu bearbeiten.");
}

// Antragsnummer aus URL
$antrnr = $_GET['antrnr'] ?? '';

if (!$antrnr) {
    die("Keine Antragsnummer angegeben.");
}

// Antrag laden
$stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
$stmt->execute([$antrnr]);
$antrag = $stmt->fetch();

if (!$antrag) {
    die("Antrag nicht gefunden.");
}

// Status-Meldungen
$saved = false;
$error = null;
$message = null;

// Bei POST: Verarbeitung basierend auf Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save':
                // Einfaches Speichern ohne Statusänderung
                speichereAntrag($pdo, $antrnr, $_POST, $antrag);
                $saved = true;
                $message = "Antrag gespeichert.";
                break;

            case 'finalize':
                // Antrag verbindlich einstellen (A → B oder A → VS)
                finalisiereAntrag($pdo, $antrnr, $_POST, $antrag, $user_aktiv, $_SESSION['member_id']);
                $saved = true;
                $message = "Antrag wurde verbindlich eingestellt und zur Abstimmung freigegeben.";
                break;

            case 'delete':
                // Antrag unwiderruflich löschen
                loescheAntrag($pdo, $antrnr, $antrag, $_SESSION['member_id'], $user_aktiv);
                header('Location: antragsliste.php?msg=deleted');
                exit;

            default:
                $error = "Unbekannte Aktion.";
        }

        // Antrag neu laden nach Änderungen
        $stmt->execute([$antrnr]);
        $antrag = $stmt->fetch();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Speichern-Funktion
function speichereAntrag($pdo, $antrnr, $post, $antrag) {
    // Automatik: bart basierend auf fin berechnen
    $fin = floatval($post['fin'] ?? 0);
    $bart = 'B'; // Default: Vorstandsbeschluss

    if ($fin <= 600) {
        $bart = 'V'; // Verfügung
    } elseif ($fin <= 3000) {
        $bart = 'R'; // Ressortbeschluss
    }

    // Board-Member kann eskalieren (wichtig-Flag)
    $wichtig = 0;
    if (isset($post['wichtig_escalate'])) {
        $wichtig = (int)$post['wichtig_member_id'];
    }

    // sofort-Wert ermitteln (kann 0, 1 oder 2 sein)
    $sofort = 0;
    if (isset($post['sofort_1'])) {
        $sofort = 1;
    } elseif (isset($post['sofort_2'])) {
        $sofort = 2;
    }

    // Hinweis anhängen falls vorhanden
    $hinweis = $antrag['hinweis'] ?? '';
    if (!empty($post['neuerhinweis'])) {
        if (!empty($hinweis)) {
            $hinweis .= "\n---\n";
        }
        $hinweis .= date('d.m.Y H:i') . ': ' . $post['neuerhinweis'];
    }

    $update = $pdo->prepare("
        UPDATE antraege SET
            titel = ?,
            beschluss = ?,
            begr = ?,
            pers = ?,
            sach = ?,
            fintext = ?,
            fin = ?,
            bart = ?,
            verant = ?,
            ressort1 = ?,
            ressort2 = ?,
            wichtig = ?,
            int_ext = ?,
            verein = ?,
            hinweis = ?,
            thread = ?,
            filetext1 = ?,
            filetext2 = ?,
            filetext3 = ?,
            filetext4 = ?,
            sofort = ?,
            durch = ?,
            zufin = ?,
            zbem = ?,
            praesenz = ?,
            lzugriff = NOW()
        WHERE antrnr = ?
    ");

    $update->execute([
        $post['titel'],
        $post['beschluss'],
        $post['begr'] ?? null,
        $post['pers'] ?? null,
        $post['sach'] ?? null,
        $post['fintext'] ?? null,
        $fin,
        $bart,
        $post['verant'] ?? null,
        $post['ressort1'] ?? null,
        $post['ressort2'] ?? null,
        $wichtig,
        $post['int_ext'] ?? null,
        $post['verein'] ?? 'V',
        $hinweis,
        $post['thread'] ?? null,
        $post['filetext1'] ?? null,
        $post['filetext2'] ?? null,
        $post['filetext3'] ?? null,
        $post['filetext4'] ?? null,
        $sofort,
        $post['durch'] ?? null,
        isset($post['zufin']) ? 1 : 0,
        $post['zbem'] ?? null,
        isset($post['praesenz']) ? 1 : 0,
        $antrnr
    ]);
}

// Finalisieren-Funktion (A → B oder A → VS)
function finalisiereAntrag($pdo, $antrnr, $post, $antrag, $user_aktiv, $member_id) {
    // Erst speichern
    speichereAntrag($pdo, $antrnr, $post, $antrag);

    // Antrag neu laden um aktuelle Werte zu haben
    $stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
    $antrag = $stmt->fetch();

    // Validierung
    if (empty($antrag['ressort1'])) {
        throw new Exception("Ressort muss angegeben werden.");
    }
    if (empty($antrag['verant'])) {
        throw new Exception("Verantwortlicher muss angegeben werden.");
    }

    // Neue Antragsnummer generieren
    $prefix = substr($antrnr, 0, 1);
    if ($prefix === 'A') {
        // Von A nach B (Abstimmung) oder direkt nach VS (Verfügung mit Selbstfreigabe)
        $neue_nr = 'B' . substr($antrnr, 1);

        // Bei Verfügung durch Antragsteller selbst: direkt VS
        if ($antrag['bart'] === 'V' && $antrag['verf1'] == $antrag['antrst']) {
            $datumstr = date('ymd');
            $neue_nr = 'VS' . $datumstr . substr($antrnr, 7);
        }

        // Update Antragsnummer
        $update = $pdo->prepare("UPDATE antraege SET antrnr = ? WHERE antrnr = ?");
        $update->execute([$neue_nr, $antrnr]);
    } else {
        throw new Exception("Antrag kann nur im Editiermodus (A) finalisiert werden.");
    }
}

// Löschen-Funktion
function loescheAntrag($pdo, $antrnr, $antrag, $member_id, $user_aktiv) {
    // Berechtigung prüfen: Nur Antragsteller, Vorstand oder GF
    if ($antrag['antrst'] != $member_id && $user_aktiv < 18) {
        throw new Exception("Keine Berechtigung zum Löschen. Nur Antragsteller, Vorstand und Geschäftsführung dürfen löschen.");
    }

    $stmt = $pdo->prepare("DELETE FROM antraege WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
}

// Ressort-Liste für Dropdown
$ressorts_stmt = $pdo->query("SELECT DISTINCT ressort FROM ressortliste ORDER BY ressort");
$ressorts = $ressorts_stmt->fetchAll(PDO::FETCH_COLUMN);

// Vorstandsmitglieder für Dropdown
$vorstand = getVorstandsmitglieder($pdo);

// Wartezeit berechnen
$wartezeit = berechneWartezeit($antrnr);
$wartezeit_erfuellt = ($wartezeit === 'erfüllt' ||
                       ($antrag['verk1'] && $antrag['verk2']) ||
                       $antrag['bart'] === 'B');

// Blockierung prüfen
$blockiert = false;
$blockierung_grund = [];
if (empty($antrag['ressort1'])) {
    $blockiert = true;
    $blockierung_grund[] = "Ressort fehlt";
}
if (empty($antrag['verant']) || strlen($antrag['verant']) < 3) {
    $blockiert = true;
    $blockierung_grund[] = "Verantwortlicher fehlt";
}

// Kann finalisiert werden?
$kann_finalisieren = !$blockiert && $wartezeit_erfuellt &&
                     substr($antrnr, 0, 1) === 'A' &&
                     ($antrag['antrst'] == $_SESSION['member_id'] || $user_aktiv >= 18);

// Kann löschen?
$kann_loeschen = ($antrag['antrst'] == $_SESSION['member_id'] || $user_aktiv >= 18);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrag bearbeiten: <?= htmlspecialchars($antrag['antrnr']) ?></title>
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
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        .antrnr {
            color: #0066cc;
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-editing {
            background: #fff3cd;
            color: #856404;
        }
        .status-voting {
            background: #cce5ff;
            color: #004085;
        }
        .status-finalized {
            background: #d4edda;
            color: #155724;
        }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        .required {
            color: #d32f2f;
        }
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }
        .btn-primary {
            background: #0066cc;
            color: white;
        }
        .btn-primary:hover {
            background: #0052a3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #666;
            color: white;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0066cc;
            text-decoration: none;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .bart-display {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 12px;
            margin-left: 10px;
        }
        .bart-v {
            background: #d4edda;
            color: #155724;
        }
        .bart-r {
            background: #fff3cd;
            color: #856404;
        }
        .bart-b {
            background: #cce5ff;
            color: #004085;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #0066cc;
            margin-bottom: 20px;
        }
        .warning-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
            margin-bottom: 20px;
        }
        .section-divider {
            border-top: 2px solid #0066cc;
            padding-top: 20px;
            margin-top: 20px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const finInput = document.getElementById('fin');
            const bartInfo = document.getElementById('bart-info');

            function updateBartDisplay() {
                const fin = parseFloat(finInput.value) || 0;
                let bart, bartLabel, bartClass;

                if (fin <= 600) {
                    bart = 'V';
                    bartLabel = 'Verfügung';
                    bartClass = 'bart-v';
                } else if (fin <= 3000) {
                    bart = 'R';
                    bartLabel = 'Ressortbeschluss';
                    bartClass = 'bart-r';
                } else {
                    bart = 'B';
                    bartLabel = 'Vorstandsbeschluss';
                    bartClass = 'bart-b';
                }

                bartInfo.innerHTML = `
                    <strong>Automatische Zuordnung:</strong>
                    <span class="bart-display ${bartClass}">${bartLabel} (${bart})</span>
                    <br><small>≤600€ = Verfügung | 601-3000€ = Ressortbeschluss | >3000€ = Vorstandsbeschluss</small>
                `;
            }

            finInput.addEventListener('input', updateBartDisplay);
            updateBartDisplay(); // Initial

            // Bestätigung für Löschen
            const deleteBtn = document.getElementById('delete-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function(e) {
                    if (!confirm('ACHTUNG: Dieser Antrag wird unwiderruflich gelöscht! Fortfahren?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <a href="antragsliste.php" class="back-link">← Zurück zur Antragsliste</a>

        <div class="header">
            <h1>Antrag bearbeiten</h1>
            <div class="antrnr">
                <?= htmlspecialchars($antrag['antrnr']) ?>
                <?php
                $status_prefix = substr($antrnr, 0, 1);
                if ($status_prefix === 'A') {
                    echo '<span class="status-badge status-editing">Editiermodus</span>';
                } elseif ($status_prefix === 'B') {
                    echo '<span class="status-badge status-voting">In Abstimmung</span>';
                } elseif (substr($antrnr, 0, 2) === 'VS') {
                    echo '<span class="status-badge status-finalized">Beschlossen</span>';
                }
                ?>
            </div>
        </div>

        <?php if ($saved): ?>
            <div class="alert alert-success">
                ✓ <?= htmlspecialchars($message ?? 'Änderungen gespeichert') ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ✗ Fehler: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (substr($antrnr, 0, 1) === 'A'): ?>
            <div class="info-box">
                <strong>Editiermodus:</strong> Anträge werden zunächst im Editiermodus eingestellt und können von allen im Führungsteam bearbeitet werden.
                Der Antragsteller entscheidet, wann er (nach der Wartezeit) den Antrag endgültig abschickt.
            </div>
        <?php endif; ?>

        <?php if ($wartezeit && $wartezeit !== 'erfüllt' && substr($antrnr, 0, 1) === 'A'): ?>
            <div class="warning-box">
                <strong>Wartezeit:</strong> Dieser Antrag kann erst ab dem <?= htmlspecialchars($wartezeit) ?> zur Abstimmung gestellt werden (7 Tage Wartezeit gemäß Verfahrensordnung).
                <?php if ($antrag['bart'] !== 'B'): ?>
                    Jedes Vorstandsmitglied kann in dieser Frist verlangen, dass der Antrag im Gesamtvorstand behandelt wird.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($blockiert): ?>
            <div class="alert alert-error">
                <strong>Antrag unvollständig:</strong> <?= implode(', ', $blockierung_grund) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-container">
            <div class="form-group">
                <label for="titel">Titel <span class="required">*</span></label>
                <input type="text" id="titel" name="titel"
                       value="<?= htmlspecialchars($antrag['titel']) ?>" required>
            </div>

            <div class="form-group">
                <label for="beschluss">Beschlusstext <span class="required">*</span></label>
                <textarea id="beschluss" name="beschluss" required><?= htmlspecialchars($antrag['beschluss']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="begr">Begründung</label>
                <textarea id="begr" name="begr"><?= htmlspecialchars($antrag['begr'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="pers">Personelle Auswirkungen</label>
                    <textarea id="pers" name="pers"><?= htmlspecialchars($antrag['pers'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="sach">Sachliche Auswirkungen</label>
                    <textarea id="sach" name="sach"><?= htmlspecialchars($antrag['sach'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="fin">Betrag (€)</label>
                <input type="number" id="fin" name="fin" step="0.01" min="0"
                       value="<?= htmlspecialchars($antrag['fin'] ?? '0') ?>">
                <div class="help-text" id="bart-info">
                    Automatik:
                    ≤600€ = Verfügung |
                    601-3000€ = Ressortbeschluss |
                    >3000€ = Vorstandsbeschluss
                </div>
            </div>

            <div class="form-group">
                <label for="fintext">Finanzielle Auswirkungen (Beschreibung)</label>
                <textarea id="fintext" name="fintext"><?= htmlspecialchars($antrag['fintext'] ?? '') ?></textarea>
                <div class="help-text">Textliche Beschreibung der finanziellen Auswirkungen</div>
            </div>

            <div class="form-group">
                <label for="verant">Verantwortlich für die Umsetzung <span class="required">*</span></label>
                <input type="text" id="verant" name="verant"
                       value="<?= htmlspecialchars($antrag['verant'] ?? '') ?>" required>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="ressort1">Ressort <span class="required">*</span></label>
                    <select id="ressort1" name="ressort1" required>
                        <option value="">-- Bitte wählen --</option>
                        <?php foreach ($ressorts as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"
                                    <?= $antrag['ressort1'] === $r ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ressort2">Mitwirkendes Ressort</label>
                    <select id="ressort2" name="ressort2">
                        <option value="">-- Kein weiteres Ressort --</option>
                        <?php foreach ($ressorts as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"
                                    <?= $antrag['ressort2'] === $r ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="verein">Verein/Stiftung</label>
                    <select id="verein" name="verein">
                        <option value="V" <?= ($antrag['verein'] ?? 'V') === 'V' ? 'selected' : '' ?>>Verein</option>
                        <option value="S" <?= ($antrag['verein'] ?? '') === 'S' ? 'selected' : '' ?>>Stiftung</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="int_ext">Intern/Extern</label>
                    <select id="int_ext" name="int_ext">
                        <option value="e" <?= ($antrag['int_ext'] ?? 'e') === 'e' ? 'selected' : '' ?>>Extern (alle Ms)</option>
                        <option value="n" <?= ($antrag['int_ext'] ?? '') === 'n' ? 'selected' : '' ?>>Nicht öffentlich (Führungskreis)</option>
                        <option value="i" <?= ($antrag['int_ext'] ?? '') === 'i' ? 'selected' : '' ?>>Intern (nur Vorstand)</option>
                    </select>
                    <div class="help-text">Regelt, wer später den Beschluss sehen kann</div>
                </div>
            </div>

            <div class="form-group">
                <label for="thread">ID im Forum</label>
                <input type="number" id="thread" name="thread" min="0"
                       value="<?= htmlspecialchars($antrag['thread'] ?? '') ?>">
                <?php if (($antrag['thread'] ?? 0) > 0): ?>
                    <a href="https://vorstand.mensa.de/forum/index.php?id=<?= $antrag['thread'] ?>"
                       target="forum" style="margin-left: 10px; color: #0066cc;">→ Link zum Forum</a>
                <?php endif; ?>
                <div class="help-text">Die ID wird im Forum in der Adresszeile angezeigt</div>
            </div>

            <div class="form-group section-divider">
                <label style="font-size: 16px; color: #0066cc;">Angebote, erläuternde Unterlagen</label>
                <div class="help-text" style="margin-bottom: 15px;">Beschreibung der hochgeladenen Dateien oder Links</div>

                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div style="margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                        <label for="filetext<?= $i ?>" style="font-weight: 600; margin-bottom: 5px;">Datei <?= $i ?></label>
                        <?php if (!empty($antrag["file$i"])): ?>
                            <div style="margin-bottom: 8px; font-size: 13px; color: #666;">
                                Vorhandene Datei:
                                <a href="<?= htmlspecialchars($antrag["file$i"]) ?>" target="datei" style="color: #0066cc;">
                                    <?= htmlspecialchars(basename($antrag["file$i"])) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <input type="text" id="filetext<?= $i ?>" name="filetext<?= $i ?>"
                               placeholder="Beschreibung der Datei (z.B. 'Angebot')"
                               value="<?= htmlspecialchars($antrag["filetext$i"] ?? '') ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                <?php endfor; ?>
            </div>

            <div class="form-group section-divider">
                <label style="font-size: 16px; color: #0066cc;">Vereinfachte Freigabe</label>
                <div class="help-text" style="margin-bottom: 15px;">Ist mit dem Beschluss/der Verfügung zu genehmigen</div>

                <div style="margin-bottom: 12px;">
                    <label style="display: flex; align-items: start; gap: 10px;">
                        <input type="checkbox" id="sofort_1" name="sofort_1" value="1"
                               <?= ($antrag['sofort'] ?? 0) == 1 ? 'checked' : '' ?>
                               onchange="if(this.checked) document.getElementById('sofort_2').checked = false;">
                        <span>Wenn der in Rechnung gestellte Betrag dem Angebot entspricht, kann sofort überwiesen werden.</span>
                    </label>
                </div>

                <div style="margin-bottom: 12px; padding-left: 10px; border-left: 3px solid #ddd;">
                    <label style="display: flex; align-items: start; gap: 10px; margin-bottom: 8px;">
                        <input type="checkbox" id="sofort_2" name="sofort_2" value="2"
                               <?= ($antrag['sofort'] ?? 0) == 2 ? 'checked' : '' ?>
                               onchange="if(this.checked) document.getElementById('sofort_1').checked = false;">
                        <span>Alternativ: Nach fachlicher Vorprüfung durch:</span>
                    </label>
                    <input type="text" id="durch" name="durch"
                           placeholder="Name der prüfenden Person"
                           value="<?= htmlspecialchars($antrag['durch'] ?? '') ?>"
                           style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-left: 32px;">
                    <div class="help-text" style="margin-left: 32px;">kann der Rechnungsbetrag ohne weitere Freigabe überwiesen werden.</div>
                </div>

                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <input type="checkbox" id="zufin" name="zufin" value="1"
                               <?= ($antrag['zufin'] ?? 0) ? 'checked' : '' ?>>
                        <span style="font-weight: 600;">Zustimmung Finanzvorstand</span>
                    </label>

                    <label for="zbem" style="display: block; margin-bottom: 5px; font-weight: 600;">Bemerkungsfeld zum späteren Zahlungsvorgang:</label>
                    <textarea id="zbem" name="zbem"
                              placeholder="Hinweise für den Zahlungsvorgang..."
                              style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"><?= htmlspecialchars($antrag['zbem'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-group section-divider">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="praesenz" name="praesenz" value="1"
                           <?= ($antrag['praesenz'] ?? 0) ? 'checked' : '' ?>>
                    <span style="font-weight: 600;">Dies ist ein Antrag zu einer Präsenzsitzung oder -Telko</span>
                </label>
                <div class="help-text">Wenn markiert, wird dieser Antrag nicht online abgestimmt.</div>
            </div>

            <?php if ($user_aktiv >= 18 && ($antrag['fin'] ?? 0) <= 3000 && $antrag['bart'] !== 'B'): ?>
            <div class="form-group section-divider">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="wichtig_escalate" name="wichtig_escalate" value="1"
                           <?= ($antrag['wichtig'] ?? 0) > 0 ? 'checked' : '' ?>>
                    <span style="font-weight: 600;">Als Vorstandsmitglied melde ich an: Die Angelegenheit ist unabhängig von den finanziellen Auswirkungen als Vorstandsbeschluss zu behandeln.</span>
                </label>
                <input type="hidden" name="wichtig_member_id" value="<?= $_SESSION['member_id'] ?>">
            </div>
            <?php endif; ?>

            <div class="form-group section-divider">
                <label for="neuerhinweis">Hinweise für den Antragsteller / Anmerkungen</label>
                <?php if (!empty($antrag['hinweis'])): ?>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 10px; font-style: italic;">
                        <?= nl2br(htmlspecialchars($antrag['hinweis'])) ?>
                    </div>
                <?php endif; ?>
                <textarea id="neuerhinweis" name="neuerhinweis" placeholder="Neuer Hinweis..."
                          style="width: 100%; min-height: 60px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
                <div class="help-text">Wird mit Zeitstempel an die bestehenden Hinweise angehängt</div>
            </div>

            <div class="actions">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    Speichern
                </button>

                <?php if ($kann_finalisieren): ?>
                    <button type="submit" name="action" value="finalize" class="btn btn-success"
                            onclick="return confirm('Antrag verbindlich einstellen? Der Antrag wird zur Abstimmung freigegeben und kann dann nicht mehr geändert werden.');">
                        Verbindlich einstellen
                    </button>
                <?php elseif (substr($antrnr, 0, 1) === 'A'): ?>
                    <button type="button" class="btn btn-success" disabled title="<?= implode(', ', $blockierung_grund) ?>">
                        Verbindlich einstellen <?= !$wartezeit_erfuellt ? '(Wartezeit)' : '' ?>
                    </button>
                <?php endif; ?>

                <?php if ($kann_loeschen && substr($antrnr, 0, 1) === 'A'): ?>
                    <button type="submit" name="action" value="delete" id="delete-btn" class="btn btn-danger">
                        Unwiderruflich löschen
                    </button>
                <?php endif; ?>

                <a href="antragsliste.php" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</body>
</html>
