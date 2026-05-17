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

function getAbstimmungsberechtigte($pdo, $bart, $antrst) {
    // Für V und R: aktiv >= 14
    if ($bart === 'V' || $bart === 'R') {
        $members = $pdo->query("SELECT ID, KurzN, Funktion FROM berechtigte WHERE aktiv >= 14 ORDER BY Funktion DESC, KurzN ASC")->fetchAll();

        // Für R: zusätzlich FVo oder FVv
        if ($bart === 'R') {
            // Prüfen ob Antragsteller FVo ist
            $antrst_data = $pdo->prepare("SELECT Funktion FROM berechtigte WHERE ID = ?");
            $antrst_data->execute([$antrst]);
            $antrst_funktion = $antrst_data->fetchColumn();

            if ($antrst_funktion === 'FVo') {
                // FVv als zweiten Abstimmenden
                $zusatz = 'FVv';
            } else {
                // FVo als zweiten Abstimmenden
                $zusatz = 'FVo';
            }

            $vorstand = $pdo->prepare("SELECT ID, KurzN, Funktion FROM berechtigte WHERE Funktion = ?");
            $vorstand->execute([$zusatz]);
            $vorstand_member = $vorstand->fetch();

            if ($vorstand_member) {
                // FVo/FVv als erstes Element hinzufügen
                array_unshift($members, $vorstand_member);
            }
        }

        return $members;
    }

    // Für B: alle mit aktiv >= 18 (Vorstand)
    return $pdo->query("SELECT ID, KurzN, Funktion FROM berechtigte WHERE aktiv >= 18 ORDER BY Funktion DESC, KurzN ASC")->fetchAll();
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
    SELECT a.*, b.Vorname, b.Name, b.KurzN as AntragstellerKurz
    FROM antraege a
    LEFT JOIN berechtigte b ON a.antrst = b.ID
    WHERE a.antrnr = ?
");
$stmt->execute([$antrnr]);
$antrag = $stmt->fetch();
if (!$antrag) die("Antrag nicht gefunden.");

// Berechtigungsprüfung je nach Status
$prefix = substr($antrnr, 0, 1);
$ist_admin = ($user_aktiv >= 19 || ($user['is_admin'] ?? 0) == 1);

if ($prefix === 'A') {
    // A-Anträge: Antragsteller oder Vorstand darf bearbeiten
    $darf_bearbeiten = ($antrag['antrst'] == $user['ID'] || $user_aktiv >= 18);
} else {
    // B/VS/X/Z-Anträge: Nur Admins dürfen bearbeiten
    $darf_bearbeiten = $ist_admin;
}

if (!$darf_bearbeiten) {
    die("⚠️ Keine Berechtigung zur Bearbeitung.<br><br>" .
        "Dieser Antrag wurde bereits finalisiert und kann nur noch von Administratoren bearbeitet werden.<br>" .
        "Status: " . htmlspecialchars($prefix) . "-Antrag<br><br>" .
        "<a href='antragsliste.php'>← Zurück zur Liste</a> | " .
        "<a href='antrag_ansehen.php?antrnr=" . urlencode($antrnr) . "'>Antrag ansehen</a>");
}

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

                // Wartezeitverkürzung verarbeiten
                if (isset($_POST['wartezeit_verkuerzen']) && $_POST['wartezeit_verkuerzen'] == 1) {
                    if ($user['aktiv'] >= 18 && $antrag['antrst'] != $user['ID']) {
                        wartezeitVerkuerzung($pdo, $antrnr, $antrag, $user);
                    }
                }

                $saved = true;
                $message = "Antrag gespeichert.";
                if ($prefix === 'A' && !$ist_admin) {
                    $message .= " <strong style='color: var(--warning);'>⚠️ Hinweis:</strong> Nach dem 'Verbindlich einstellen' kann der Antrag nur noch von Administratoren bearbeitet werden!";
                }
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
                verwerfenAntrag($pdo, $antrnr, $antrag, $user);
                header('Location: antragsliste.php?msg=withdrawn');
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

    // Admin kann Antragsteller ändern
    $antrst = $antrag['antrst'];
    if ($user['aktiv'] >= 19 && isset($post['antrst']) && $post['antrst'] != $antrag['antrst']) {
        $antrst = $post['antrst'];
    }

    // Monatssummen-Prüfung für Verfügungen
    $monatssumme = berechneMonatssumme($pdo, $antrst, $antrnr);
    if ($fin < 600 && ($monatssumme + $fin) > 2000) {
        throw new Exception("Monatliche Verfügungsgrenze von 2000€ überschritten! Aktuelle Summe: " . number_format($monatssumme, 2) . "€");
    }

    // Wichtig-Flag Logik - NUR bei action=save ändern, nicht bei finalize
    $wichtig = $antrag['wichtig'] ?? 0; // Aktuellen Wert beibehalten

    // DEBUG
    error_log("DEBUG speichereAntrag START: action=" . ($post['action'] ?? 'none') . ", wichtig_escalate=" . (isset($post['wichtig_escalate']) ? 'JA' : 'NEIN') . ", wichtig_reset=" . (isset($post['wichtig_reset']) ? 'JA' : 'NEIN') . ", wichtig_alt=" . $wichtig);

    // Nur wenn wirklich gespeichert wird (nicht beim Finalisieren)
    if (isset($post['action']) && $post['action'] === 'save') {
        // Wenn wichtig_reset gesetzt ist (Antragsteller nimmt zurück)
        if (isset($post['wichtig_reset']) && $antrag['wichtig'] == $antrag['antrst']) {
            $wichtig = 0;
            error_log("DEBUG: wichtig auf 0 gesetzt (reset)");
        }
        // Wenn wichtig_escalate gesetzt ist (neu oder weiterhin)
        elseif (isset($post['wichtig_escalate']) && !isset($post['wichtig_reset'])) {
            // Auch Antragsteller (aktiv < 18) darf Vorstandsbeschluss anfordern
            $wichtig = $user['ID'];
            error_log("DEBUG: wichtig auf " . $user['ID'] . " gesetzt (escalate)");
        } elseif (!isset($post['wichtig_escalate']) && !isset($post['wichtig_reset'])) {
            // Checkbox nicht gesetzt und kein Reset -> wichtig löschen
            $wichtig = 0;
            error_log("DEBUG: wichtig auf 0 gesetzt (keine checkbox)");
        }
    } else {
        error_log("DEBUG: action ist nicht 'save', wichtig bleibt bei " . $wichtig);
    }

    // bart berechnen - aber wichtig hat Vorrang
    if ($wichtig > 0) {
        $bart = 'B'; // Immer Vorstandsbeschluss wenn wichtig gesetzt
        error_log("DEBUG: bart='B' (wichtig=" . $wichtig . ")");
    } else {
        $bart = ($monatssumme + $fin) > 600 || $fin >= 600 ? ($fin <= 3000 ? 'R' : 'B') : 'V';
        error_log("DEBUG: bart='" . $bart . "' (wichtig=0, fin=" . $fin . ", monatssumme=" . $monatssumme . ")");
    }

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
            antrst = ?, titel = ?, beschluss = ?, begr = ?, pers = ?, sach = ?,
            fintext = ?, fin = ?, bart = ?, verant = ?,
            ressort1 = ?, ressort2 = ?, wichtig = ?,
            int_ext = ?, verein = ?, hinweis = ?, thread = ?,
            file1 = ?, file2 = ?, file3 = ?, file4 = ?,
            filetext1 = ?, filetext2 = ?, filetext3 = ?, filetext4 = ?,
            sofort = ?, durch = ?, zufin = ?, zbem = ?,
            praesenz = ?, verf1 = ?, verf2 = ?, vorher = ?,
            lzugriff = NOW()
        WHERE antrnr = ?
    ");

    $update->execute([
        $antrst, $post['titel'], $post['beschluss'], $post['begr'] ?? null,
        $post['pers'] ?? null, $post['sach'] ?? null, $post['fintext'] ?? null,
        $fin, $bart, $post['verant'] ?? null,
        $post['ressort1'] ?? null, $post['ressort2'] ?? null, $wichtig,
        $post['int_ext'] ?? null, $post['verein'] ?? 'V', $hinweis, $post['thread'] ?? null,
        $post['file1'], $post['file2'], $post['file3'], $post['file4'],
        $post['filetext1'] ?? null, $post['filetext2'] ?? null,
        $post['filetext3'] ?? null, $post['filetext4'] ?? null,
        $sofort, $post['durch'] ?? null,
        isset($post['zufin']) ? 1 : 0, $post['zbem'] ?? null,
        $post['praesenz'] ?? null, $post['verf1'] ?? null, $post['verf2'] ?? null,
        isset($post['vorher']) ? 1 : 0,
        $antrnr
    ]);
}

// Finalisieren-Funktion
function finalisiereAntrag($pdo, $antrnr, $post, $antrag, $user) {
    // Speichern mit action=save, damit wichtig-Flag korrekt verarbeitet wird
    $post_save = $post;
    $post_save['action'] = 'save';
    speichereAntrag($pdo, $antrnr, $post_save, $antrag, $user);

    // Antrag neu laden
    $stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
    $antrag = $stmt->fetch();

    if (empty($antrag['ressort1'])) throw new Exception("Ressort muss angegeben werden.");
    if (empty($antrag['verant']) || strlen($antrag['verant']) < 3) throw new Exception("Verantwortlicher muss angegeben werden.");

    $prefix = substr($antrnr, 0, 1);
    if ($prefix !== 'A') throw new Exception("Nur Anträge im Editiermodus können finalisiert werden.");

    // DEBUG: Ausgabe von bart
    error_log("DEBUG finalisiereAntrag: bart=" . $antrag['bart'] . ", wichtig=" . ($antrag['wichtig'] ?? '0') . ", fin=" . ($antrag['fin'] ?? '0') . ", verf1=" . ($antrag['verf1'] ?? 'leer') . ", verf2=" . ($antrag['verf2'] ?? 'leer'));

    // Abstimmende setzen je nach Beschlussart
    $vname_fields = [];

    if ($antrag['bart'] === 'V') {
        // Verfügung: verf1 muss abstimmen
        if (empty($antrag['verf1'])) {
            throw new Exception("Verfügungsberechtigter muss angegeben werden.");
        }
        $vname_fields = ['VName1' => $antrag['verf1']];

    } elseif ($antrag['bart'] === 'R') {
        // Ressortbeschluss: verf1 und verf2 müssen abstimmen
        if (empty($antrag['verf1']) || empty($antrag['verf2'])) {
            throw new Exception("Beide Verfügungsberechtigte müssen angegeben werden. (bart=" . $antrag['bart'] . ", verf1=" . ($antrag['verf1'] ?? 'leer') . ", verf2=" . ($antrag['verf2'] ?? 'leer') . ")");
        }
        if ($antrag['verf1'] == $antrag['verf2']) {
            throw new Exception("Die Verfügungsberechtigten dürfen nicht identisch sein.");
        }
        $vname_fields = [
            'VName1' => $antrag['verf1'],
            'VName2' => $antrag['verf2']
        ];

    } elseif ($antrag['bart'] === 'B') {
        // Vorstandsbeschluss: alle Vorstandsmitglieder (aktiv >= 18)
        $vorstand_stmt = $pdo->query("SELECT ID FROM berechtigte WHERE aktiv >= 18 ORDER BY ID LIMIT 6");
        $vorstand = $vorstand_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($vorstand)) {
            throw new Exception("Keine Vorstandsmitglieder gefunden.");
        }

        for ($i = 0; $i < count($vorstand); $i++) {
            $vname_fields['VName' . ($i + 1)] = $vorstand[$i];
        }
    }

    // Neue Nummer generieren: A → B (zur Abstimmung)
    $neue_nr = 'B' . substr($antrnr, 1);

    // VName-Felder in UPDATE-Statement aufbauen
    $update_parts = ['antrnr = ?'];
    $update_values = [$neue_nr];

    foreach ($vname_fields as $field => $value) {
        $update_parts[] = "$field = ?";
        $update_values[] = $value;
    }

    $update_values[] = $antrnr; // WHERE-Bedingung

    $sql = "UPDATE antraege SET " . implode(', ', $update_parts) . " WHERE antrnr = ?";
    $update = $pdo->prepare($sql);
    $update->execute($update_values);

    $_POST['neue_antrnr'] = $neue_nr;

    // TODO: Email-Benachrichtigung an Abstimmende (später)
}

// Löschen-Funktion
function verwerfenAntrag($pdo, $antrnr, $antrag, $user) {
    // Nur Antragsteller, Vorstand oder GF dürfen verwerfen
    $ist_antragsteller = ($antrag['antrst'] == $user['ID']);
    $ist_vorstand_gf = ($user['aktiv'] >= 19 || $user['Funktion'] === 'GF');

    if (!$ist_antragsteller && !$ist_vorstand_gf) {
        throw new Exception("Nur Antragsteller, Vorstand und GF dürfen Anträge verwerfen.");
    }

    // Antragsnummer zu X ändern (zurückgezogen)
    $neue_antrnr = 'X' . substr($antrnr, 1);
    $pdo->prepare("UPDATE antraege SET antrnr = ? WHERE antrnr = ?")->execute([$neue_antrnr, $antrnr]);
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
$ressorts = $pdo->query("SELECT ID as ressort, Ressort as klartext FROM ressortliste ORDER BY Reihenfolge, ID")->fetchAll();
$verfuegungsber = getVerfuegungsberechtigte($pdo);
$abstimmende = getAbstimmungsberechtigte($pdo, $antrag['bart'], $antrag['antrst']);
$wartezeit = berechneWartezeit($antrnr);
$wartezeit_erfuellt = ($wartezeit === 'erfüllt' || ($antrag['verk1'] && $antrag['verk2']) || $antrag['bart'] === 'B');
$monatssumme = berechneMonatssumme($pdo, $antrag['antrst'], $antrnr);

$blockiert = false;
$blockierung_grund = [];
if (empty($antrag['ressort1'])) { $blockiert = true; $blockierung_grund[] = "Ressort fehlt"; }
if (empty($antrag['verant']) || strlen($antrag['verant']) < 3) { $blockiert = true; $blockierung_grund[] = "Verantwortlicher fehlt"; }

// Prüfung auf fehlende Verfügungsberechtigte je nach Beschlussart
if ($antrag['bart'] === 'V' && empty($antrag['verf1'])) {
    $blockiert = true;
    $blockierung_grund[] = "Verfügungsberechtigter fehlt";
}
if ($antrag['bart'] === 'R') {
    if (empty($antrag['verf1']) || empty($antrag['verf2'])) {
        $blockiert = true;
        $blockierung_grund[] = "Beide Verfügungsberechtigte müssen angegeben sein";
    } elseif ($antrag['verf1'] == $antrag['verf2']) {
        $blockiert = true;
        $blockierung_grund[] = "Verfügungsberechtigte dürfen nicht identisch sein";
    }
}

$kann_finalisieren = !$blockiert && $wartezeit_erfuellt && substr($antrnr, 0, 1) === 'A' &&
                     ($antrag['antrst'] == $user['ID'] || $user['aktiv'] >= 18);
$kann_verwerfen = ($antrag['antrst'] == $user['ID'] || $user['aktiv'] >= 19 || $user['Funktion'] === 'GF');
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

// Verk-Namen holen (Wartezeitverkürzung)
$verk1_name = $verk2_name = '';
if ($antrag['verk1']) {
    $stmt = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
    $stmt->execute([$antrag['verk1']]);
    $verk1_name = $stmt->fetchColumn() ?: '';
}
if ($antrag['verk2']) {
    $stmt = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
    $stmt->execute([$antrag['verk2']]);
    $verk2_name = $stmt->fetchColumn() ?: '';
}

// Alle User mit aktiv>=9 für Admin-Antragsteller-Auswahl
$alle_antragsteller = [];
if ($user['aktiv'] >= 19) {
    $alle_antragsteller = $pdo->query("SELECT ID, KurzN, Vorname, Name FROM berechtigte WHERE aktiv >= 9 ORDER BY KurzN")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrag: <?= htmlspecialchars($antrag['antrnr']) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="antrag-styles.css">
    <script>
        // Dark Mode Toggle
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }

        // Dark Mode beim Laden wiederherstellen
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <a href="antragsliste.php" class="back-link">← Zurück zur Antragsliste</a>

        <!-- Dark Mode Toggle -->
        <button onclick="toggleDarkMode()" class="btn btn-secondary" style="float: right; margin-bottom: 10px;">
            🌓 Dark Mode
        </button>

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
                <strong>Editiermodus:</strong> Anträge werden erst mal im Editiermodus eingestellt und können von allen im Führungsteam bearbeitet werden.
                Bei Verfügungen und Ressortbeschlüssen entscheidet der Antragsteller dann, wann er (nach der in der Verfahrensordnung geregelten Wartezeit) den Antrag endgültig abschickt.
                <?php if ($wartezeit && $wartezeit !== 'erfüllt'): ?>
                <br><strong>Wartezeit bis <?= htmlspecialchars($wartezeit) ?></strong>
                    <?php if ($antrag['verk1'] && $antrag['verk2']): ?>
                        - Wartezeit verkürzt
                    <?php elseif ($antrag['verk1'] || $antrag['verk2']): ?>
                        - Wartezeitverkürzung teilweise bestätigt
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($blockiert): ?>
            <div class="alert alert-error">
                <strong>Der Antrag kann nicht zur Abstimmung gestellt werden:</strong><br>
                Wesentliche Angaben fehlen - kontrolliere das Ressort, den/die Freigabeberechtigten und die Angabe der für die Umsetzung Verantwortlichen.
                <br>Fehlende Felder: <?= implode(', ', $blockierung_grund) ?>
            </div>
        <?php endif; ?>

        <?php if ($monatssumme > 0 && ($antrag['fin'] ?? 0) < 600): ?>
            <div class="alert alert-warning">
                <strong>Monatssumme Verfügungen:</strong> <?= number_format($monatssumme, 2) ?>€ + aktuell <?= number_format($antrag['fin'] ?? 0, 2) ?>€ = <?= number_format($monatssumme + ($antrag['fin'] ?? 0), 2) ?>€
                (Max. 2000€/Monat, sonst Ressortbeschluss)
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- ========== SEKTION 1: DATEN ZUM ANTRAG ========== -->
            <div class="form-section section-gray-1">
                <div class="section-header">Daten zum Antrag:
                    Antragsnummer <?= htmlspecialchars($antrag['antrnr']) ?>
                    <?php if ($user['aktiv'] >= 19): ?>
                        - Antragsteller:
                        <select name="antrst" style="display: inline-block; width: auto; padding: 2px 6px; font-size: 13px; margin-left: 5px;">
                            <?php foreach ($alle_antragsteller as $ast): ?>
                                <option value="<?= $ast['ID'] ?>" <?= $antrag['antrst'] == $ast['ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ast['KurzN']) ?> (<?= htmlspecialchars($ast['Vorname'] . ' ' . $ast['Name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        - Antragsteller: <?= htmlspecialchars(($antrag['Vorname'] ?? '') . ' ' . ($antrag['Name'] ?? '')) ?>
                    <?php endif; ?>
                </div>

                <!-- Zeile 1: Beschlussart - Abstimmung 1 - Abstimmung 2 -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Beschlussart</label>
                        <input type="text" value="<?= $antrag['bart'] === 'V' ? 'Verfügung' : ($antrag['bart'] === 'R' ? 'Ressortbeschluss' : 'Vorstandsbeschluss') ?>" class="read-only" readonly>
                    </div>

                    <?php if ($antrag['bart'] === 'V' || $antrag['bart'] === 'R'): ?>
                        <div class="form-group">
                            <label for="verf1" class="<?= empty($antrag['verf1']) ? 'warning' : '' ?>">
                                Abstimmung durch (1.) <?= empty($antrag['verf1']) ? '<span class="required">*</span>' : '' ?>
                            </label>
                            <select id="verf1" name="verf1" class="<?= empty($antrag['verf1']) ? 'warning-border' : '' ?>">
                                <option value="">-- Bitte wählen --</option>
                                <?php foreach ($abstimmende as $m): ?>
                                    <?php if (!str_starts_with($m['KurzN'], 'ASt ')): ?>
                                        <option value="<?= $m['ID'] ?>" <?= ($antrag['verf1'] ?? '') == $m['ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['KurzN']) ?><?= $m['Funktion'] ? ' (' . $m['Funktion'] . ')' : '' ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($antrag['verf1'])): ?>
                                <div class="field-warning">
                                    ⚠️ Erforderlich für "Verbindlich einstellen"
                                </div>
                            <?php endif; ?>

                            <?php
                            // Prüfen wer wichtig gesetzt hat
                            $wichtig_von_antragsteller = (!empty($antrag['wichtig']) && $antrag['wichtig'] == $antrag['antrst']);
                            $wichtig_von_vorstand = (!empty($antrag['wichtig']) && $antrag['wichtig'] != $antrag['antrst']);
                            ?>

                            <!-- Checkbox unter Abstimmung -->
                            <div style="margin-top: 8px;">
                                <div class="checkbox-inline">
                                    <input type="checkbox" id="wichtig_escalate" name="wichtig_escalate" value="1" <?= !empty($antrag['wichtig']) ? 'checked' : '' ?>>
                                    <label for="wichtig_escalate" style="margin: 0; font-size: 12px;">Alternativ: Als Abstimmung im Gesamtvorstand einstellen</label>
                                </div>

                                <?php if ($wichtig_von_antragsteller): ?>
                                <div class="checkbox-inline" style="margin-top: 4px;">
                                    <input type="checkbox" id="wichtig_reset" name="wichtig_reset" value="1">
                                    <label for="wichtig_reset" style="margin: 0; font-size: 11px; color: #666;">Beschlussart entsprechend Betrag</label>
                                </div>
                                <?php endif; ?>

                                <?php if ($wichtig_von_vorstand): ?>
                                <div style="font-size: 11px; color: #666; margin-top: 2px;">
                                    (Von Vorstand festgelegt)
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($antrag['bart'] === 'R'): ?>
                        <div class="form-group">
                            <label for="verf2" class="<?= empty($antrag['verf2']) ? 'warning' : '' ?>">
                                Abstimmung durch (2. - Vorstand) <?= empty($antrag['verf2']) ? '<span class="required">*</span>' : '' ?>
                            </label>
                            <select id="verf2" name="verf2" class="<?= empty($antrag['verf2']) ? 'warning-border' : '' ?>">
                                <option value="">-- FVo/FVv --</option>
                                <?php foreach ($verfuegungsber as $m): ?>
                                    <?php if (($m['Funktion'] === 'FVo' || $m['Funktion'] === 'FVv') && !str_starts_with($m['KurzN'], 'ASt ')): ?>
                                        <option value="<?= $m['ID'] ?>" <?= ($antrag['verf2'] ?? '') == $m['ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['KurzN']) ?> (<?= $m['Funktion'] ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($antrag['verf2'])): ?>
                                <div class="field-warning">
                                    ⚠️ Erforderlich für "Verbindlich einstellen"
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($antrag['verf1']) && !empty($antrag['verf2']) && $antrag['verf1'] == $antrag['verf2']): ?>
                                <div class="field-warning">
                                    ⚠️ Die Verfügungsberechtigten dürfen nicht identisch sein!
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="form-group">
                            <!-- Bei V: Leer -->
                        </div>
                        <?php endif; ?>

                    <?php elseif ($antrag['bart'] === 'B'): ?>
                        <!-- Hidden input um wichtig-Flag zu erhalten -->
                        <?php if (!empty($antrag['wichtig'])): ?>
                            <input type="hidden" name="wichtig_escalate" value="1">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="praesenz">Abstimmung</label>
                            <select id="praesenz" name="praesenz">
                                <option value="0" <?= ($antrag['praesenz'] ?? 0) == 0 ? 'selected' : '' ?>>Online</option>
                                <option value="1" <?= ($antrag['praesenz'] ?? 0) == 1 ? 'selected' : '' ?>>Präsenzsitzung</option>
                            </select>
                            <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                Vorstandsbeschluss
                                <?php if (!empty($antrag['wichtig']) && $antrag['wichtig'] != $antrag['antrst']): ?>
                                    (von Vorstand festgelegt)
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <!-- Leer -->
                        </div>
                    <?php else: ?>
                        <div class="form-group"></div>
                        <div class="form-group"></div>
                    <?php endif; ?>
                </div>

                <!-- Zeile 4: Ressort - Mitwirkendes Ressort - Verantwortlich -->
                <div class="form-row" style="margin-top: 12px;">
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
                    <div class="form-group">
                        <label for="verant">Verantwortlich für die Umsetzung <span class="required">*</span></label>
                        <input type="text" id="verant" name="verant" value="<?= htmlspecialchars($antrag['verant'] ?? '') ?>" required>
                    </div>
                </div>

                <!-- Zeile 5: Verein/Stiftung - Sichtbarkeit - Forum-ID -->
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
            </div>

            <!-- ========== SEKTION 2: ANTRAG ========== -->
            <div class="form-section section-gray-2">
                <div class="section-header">Antrag</div>

                <!-- Titel -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="titel">Titel <span class="required">*</span></label>
                        <input type="text" id="titel" name="titel" value="<?= htmlspecialchars($antrag['titel']) ?>" required>
                    </div>
                </div>

                <!-- Beschlusstext (nur 2 Zeilen) -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="beschluss">Beschlusstext <span class="required">*</span></label>
                        <textarea id="beschluss" name="beschluss" required style="min-height: 50px; max-height: 50px;"><?= htmlspecialchars($antrag['beschluss']) ?></textarea>
                    </div>
                </div>

                <!-- Begründung -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="begr">Begründung</label>
                        <textarea id="begr" name="begr"><?= htmlspecialchars($antrag['begr'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Betrag -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="fin">Betrag (volle Euro)</label>
                        <input type="number" id="fin" name="fin" step="1" min="0" value="<?= htmlspecialchars($antrag['fin'] ?? '0') ?>" style="max-width: 150px;">
                        <div class="help-text">≤600€=Verfügung | 601-3000€=Ressort | >3000€=Vorstand</div>
                    </div>
                </div>

                <!-- Finanzielle - Personelle - Sachliche Auswirkungen -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="fintext">Finanzielle Auswirkungen</label>
                        <textarea id="fintext" name="fintext"><?= htmlspecialchars($antrag['fintext'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="pers">Personelle Auswirkungen</label>
                        <textarea id="pers" name="pers"><?= htmlspecialchars($antrag['pers'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="sach">Sachliche Auswirkungen</label>
                        <textarea id="sach" name="sach"><?= htmlspecialchars($antrag['sach'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ========== SEKTION 3: ANGEBOTE/UNTERLAGEN ========== -->
            <div class="form-section section-gray-3">
                <div class="section-header">Angebote / Unterlagen</div>

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
            </div>

            <!-- ========== SEKTION 4: VEREINFACHTE FREIGABE (KOMPAKT) ========== -->
            <div class="form-section section-gray-4 compact-box">
                <div class="section-header" style="font-size: 13px; margin-bottom: 8px;">Vereinfachte Freigabe</div>

                <!-- Eine Zeile mit beiden Optionen -->
                <div class="checkbox-inline">
                    <input type="checkbox" id="sofort_1" name="sofort_1" value="1" <?= ($antrag['sofort'] ?? 0) == 1 ? 'checked' : '' ?>
                           onchange="toggleFinanzFreigabe()">
                    <label for="sofort_1" style="margin:0; font-size: 12px;">Wenn Rechnungsbetrag = Angebot, sofort überweisen</label>

                    <span style="margin-left: 20px;">ODER</span>

                    <input type="checkbox" id="sofort_2" name="sofort_2" value="2" <?= ($antrag['sofort'] ?? 0) == 2 ? 'checked' : '' ?>
                           onchange="toggleFinanzFreigabe()" style="margin-left: 20px;">
                    <label for="sofort_2" style="margin:0; font-size: 12px;">Nach Vorprüfung durch:</label>
                    <input type="text" name="durch" placeholder="Name" value="<?= htmlspecialchars($antrag['durch'] ?? '') ?>" style="width: 120px; margin-left: 8px; font-size: 12px;">
                    <span style="margin-left: 4px; font-size: 12px;">überweisen</span>
                </div>

                <?php
                // Prüfen ob User FVo oder FVv ist
                $ist_finanzvorstand = ($user['Funktion'] === 'FVo' || $user['Funktion'] === 'FVv');
                $sofort_aktiv = (($antrag['sofort'] ?? 0) > 0);
                $vorher_erteilt = (($antrag['vorher'] ?? 0) == 1);
                ?>

                <!-- Finanzvorstand-Freigabe (nur sichtbar wenn sofort aktiv) -->
                <div id="finanz_freigabe_box" style="<?= !$sofort_aktiv ? 'display: none;' : '' ?> margin-top: 8px; padding: 8px; background: #fff8dc; border-left: 3px solid #ffa500; border-radius: 4px;">

                    <?php if (!$vorher_erteilt): ?>
                        <div style="font-size: 12px; color: var(--warning); font-weight: 600; margin-bottom: 6px;">
                            ⚠️ Zustimmung durch Finanzvorstand (FVo bzw. FVv) erforderlich
                        </div>
                    <?php endif; ?>

                    <?php if ($ist_finanzvorstand): ?>
                        <div class="checkbox-inline">
                            <input type="checkbox" id="vorher" name="vorher" value="1" <?= $vorher_erteilt ? 'checked' : '' ?>>
                            <label for="vorher" style="margin:0; font-size: 12px; font-weight: 600;">Zustimmung Finanzvorstand</label>
                        </div>
                        <div style="font-size: 10px; color: #666; margin-top: 2px; padding-left: 20px;">
                            (Nur für FVo und FVv)
                        </div>
                    <?php endif; ?>

                    <?php if ($vorher_erteilt): ?>
                        <div style="margin-top: 4px; font-size: 11px; color: #2e7d32; font-weight: 600;">
                            ✓ Zustimmung Finanzvorstand bzw. Vertreter liegt vor
                        </div>
                    <?php endif; ?>
                </div>

                <div class="checkbox-inline" style="margin-top: 8px;">
                    <input type="checkbox" id="zufin" name="zufin" value="1" <?= ($antrag['zufin'] ?? 0) ? 'checked' : '' ?>>
                    <label for="zufin" style="margin:0; font-size: 12px;">Freigabe durch GF zusätzlich erforderlich</label>
                </div>

                <?php if (!empty($antrag['zbem'])): ?>
                <div style="margin-top: 6px; font-size: 11px; color: #666;">
                    <strong>Bemerkung Finanzreferat:</strong> <?= htmlspecialchars($antrag['zbem']) ?>
                </div>
                <?php endif; ?>
            </div>

            <script>
            function toggleFinanzFreigabe() {
                const box = document.getElementById('finanz_freigabe_box');
                const s1 = document.getElementById('sofort_1');
                const s2 = document.getElementById('sofort_2');

                if (s1.checked || s2.checked) {
                    box.style.display = 'block';
                    // Gegenseitig ausschließen
                    if (s1.checked) s2.checked = false;
                    if (s2.checked) s1.checked = false;
                } else {
                    box.style.display = 'none';
                }
            }
            </script>

            <!-- ========== SEKTION 5: BEMERKUNGEN/HINWEISE ========== -->
            <div class="form-section section-gray-5">
                <div class="section-header">Bemerkungen / Hinweise</div>

                <?php if (!empty($antrag['hinweis'])): ?>
                <div class="hint-box" style="margin-bottom: 10px;">
                    <strong>Bisherige Hinweise:</strong><br>
                    <?= nl2br(htmlspecialchars($antrag['hinweis'])) ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="neuerhinweis">Neuer Hinweis (wird mit Zeitstempel angehängt)</label>
                    <textarea id="neuerhinweis" name="neuerhinweis" placeholder="Wird mit Zeitstempel angehängt..." style="min-height: 50px;"></textarea>
                </div>
            </div>

            <!-- ========== SEKTION 6: NÄCHSTE SCHRITTE ========== -->
            <div class="form-section section-gray-6">
                <div class="section-header">Nächste Schritte</div>

                <?php if ($kann_verwerfen && substr($antrnr, 0, 1) === 'A'): ?>
                    <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107; margin-bottom: 12px;">
                        <strong>Nur für Vorstand, GF und Antragsteller:</strong>
                        Wenn der Antrag obsolet ist und nicht mehr benötigt wird, kannst du ihn löschen.
                    </div>
                <?php endif; ?>

                <?php if ($kann_verkuerzen && substr($antrnr, 0, 1) === 'A' && $wartezeit !== 'erfüllt'): ?>
                    <div style="background: #fff8dc; padding: 10px; margin-bottom: 12px; border-radius: 4px; border-left: 3px solid #ffa500;">
                        <div class="checkbox-inline">
                            <input type="checkbox" id="wartezeit_verkuerzen" name="wartezeit_verkuerzen" value="1"
                                   <?= ($antrag['verk1'] == $user['ID'] || $antrag['verk2'] == $user['ID']) ? 'checked' : '' ?>>
                            <label for="wartezeit_verkuerzen" style="margin: 0; font-size: 12px; font-weight: 600;">Wartezeit aufheben</label>
                        </div>
                        <div style="font-size: 11px; color: #666; margin-top: 4px;">
                            (Vorstandsmitglieder können der Verkürzung zustimmen)
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (($antrag['verk1'] || $antrag['verk2']) && $wartezeit !== 'erfüllt'): ?>
                    <div style="background: #e8f5e9; padding: 10px; margin-bottom: 12px; border-radius: 4px; border-left: 3px solid #4caf50;">
                        <div style="font-size: 12px; color: #2e7d32;">
                            <?php if ($antrag['verk1'] && $antrag['verk2']): ?>
                                ✓ Zustimmung zur Verkürzung der Wartezeit liegt vor von <?= htmlspecialchars($verk1_name) ?> und <?= htmlspecialchars($verk2_name) ?>
                            <?php elseif ($antrag['verk1']): ?>
                                ✓ Zustimmung zur Verkürzung der Wartezeit liegt vor von <?= htmlspecialchars($verk1_name) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($wartezeit === 'erfüllt' && ($antrag['bart'] === 'V' || $antrag['bart'] === 'R')): ?>
                    <div class="status-box">
                        Die Wartezeit ist erfüllt; du kannst den Antrag zur Abstimmung stellen.
                        <?php if (($antrag['verf1'] ?? '') == $antrag['antrst']): ?>
                            <span style="color: var(--warning); font-weight: 600;">⚠️ Bitte nicht vergessen, dort dann abzustimmen!</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="actions" style="border-top: none; padding-top: 0; margin-top: 8px;">
                    <button type="submit" name="action" value="save" class="btn btn-primary">💾 Speichern</button>

                    <?php if ($kann_finalisieren): ?>
                        <button type="submit" name="action" value="finalize" class="btn btn-success"
                                onclick="return confirm('Antrag verbindlich einstellen? Nicht mehr änderbar!');">
                            ✅ Verbindlich einstellen
                        </button>
                    <?php elseif (substr($antrnr, 0, 1) === 'A'): ?>
                        <button type="button" class="btn btn-success" disabled title="<?= implode(', ', $blockierung_grund) ?>">
                            Verbindlich einstellen <?= !$wartezeit_erfuellt ? '(Wartezeit)' : '' ?>
                        </button>
                    <?php endif; ?>

                    <?php if ($kann_verwerfen && substr($antrnr, 0, 1) === 'A'): ?>
                        <button type="submit" name="action" value="delete" class="btn btn-danger"
                                onclick="return confirm('Antrag löschen? Der Antrag wird als zurückgezogen markiert (X-Präfix).');">
                            🗑️ Löschen
                        </button>
                    <?php endif; ?>

                    <a href="antragsliste.php" class="btn btn-secondary">❌ Abbrechen</a>
                </div>
            </div>

        </form>
    </div>

<script>
// Drag & Drop File Upload
document.addEventListener('DOMContentLoaded', function() {
    const fileItems = document.querySelectorAll('.file-item');

    fileItems.forEach((item, index) => {
        const fileInput = item.querySelector('input[type="file"]');
        const fileNumber = index + 1;

        // Styling für Drop Zone
        item.style.position = 'relative';
        item.style.border = '2px dashed #ddd';
        item.style.borderRadius = '8px';
        item.style.padding = '15px';
        item.style.transition = 'all 0.3s ease';

        // Drag Events
        ['dragenter', 'dragover'].forEach(eventName => {
            item.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                item.style.borderColor = '#2196f3';
                item.style.backgroundColor = '#f0f7ff';
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            item.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                item.style.borderColor = '#ddd';
                item.style.backgroundColor = 'transparent';
            });
        });

        item.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;

                // Zeige Dateinamen an
                const fileName = files[0].name;
                let feedback = item.querySelector('.drop-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'drop-feedback';
                    feedback.style.marginTop = '8px';
                    feedback.style.fontSize = '12px';
                    feedback.style.color = '#2196f3';
                    feedback.style.fontWeight = '600';
                    item.appendChild(feedback);
                }
                feedback.textContent = '✓ Datei ausgewählt: ' + fileName;
            }
        });

        // Auch bei normalem File-Input Feedback zeigen
        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length > 0) {
                const fileName = fileInput.files[0].name;
                let feedback = item.querySelector('.drop-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'drop-feedback';
                    feedback.style.marginTop = '8px';
                    feedback.style.fontSize = '12px';
                    feedback.style.color = '#2196f3';
                    feedback.style.fontWeight = '600';
                    item.appendChild(feedback);
                }
                feedback.textContent = '✓ Datei ausgewählt: ' + fileName;
            }
        });
    });
});
</script>
</body>
</html>
