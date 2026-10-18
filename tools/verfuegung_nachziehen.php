<?php
/**
 * verfuegung_nachziehen.php - EINMALIGES Hilfsskript (danach löschen!)
 *
 * Verfügungen (Typ V) wurden früher wie eine Abstimmung behandelt. Nach der neuen Regel sind sie mit
 * "Verbindlich einstellen" sofort beschlossen. Dieses Skript beschließt Verfügungen, die noch als
 * B-Antrag "in Abstimmung" hängen, nachträglich: VS-Nummer, Beschlussbuch-Eintrag "Verfügung von <Name>"
 * (verf1, sonst Antragsteller). Die bisher abgegebenen Stimmen bleiben im Antrag stehen, werden aber
 * nicht gewertet.
 *
 * Ablauf: Passwort -> Liste der betroffenen Anträge -> je Antrag bestätigen. Verändert nur die gewählten Anträge.
 * Unten gibt es einen Knopf, der das Skript wieder löscht.
 */
chdir(__DIR__ . '/..');
require_once 'session_config.php';
session_start();
require_once 'functions.php';
require_once 'voting_helper.php';
if (file_exists('protokoll_helper.php')) require_once 'protokoll_helper.php';

function h($s) { return htmlspecialchars((string)$s); }

echo '<!DOCTYPE html><meta charset="utf-8"><title>Verfügungen nachziehen</title><body style="font-family:sans-serif;max-width:900px;margin:20px auto">';
echo '<h2>Verfügungen nachträglich beschließen (einmalig)</h2>';

if (isset($_POST['admin_password']) && $_POST['admin_password'] === SYSTEM_ADMIN_PASSWORD) {
    $_SESSION['system_admin_authenticated'] = true;
}
if (empty($_SESSION['system_admin_authenticated'])) {
    echo '<form method="post"><p>System-Admin-Passwort:</p><input type="password" name="admin_password"> <button>Weiter</button></form>';
    exit;
}

// Skript löschen
if (isset($_POST['selbst_loeschen'])) {
    echo @unlink(__FILE__) ? '<p style="color:green"><b>Das Skript wurde gelöscht.</b></p>' : '<p style="color:red">Löschen nicht möglich - bitte die Datei tools/verfuegung_nachziehen.php von Hand entfernen.</p>';
    exit;
}

// Einen Antrag beschließen
if (isset($_POST['antrnr'], $_POST['bestaetigt'])) {
    $nr = $_POST['antrnr'];
    $st = $pdo->prepare("SELECT * FROM " . TABLE_ANTRAEGE . " WHERE antrnr = ? AND antrnr LIKE 'B%' AND bart = 'V'");
    $st->execute([$nr]);
    $antrag = $st->fetch(PDO::FETCH_ASSOC);
    if (!$antrag) {
        echo '<p style="color:red">Antrag ' . h($nr) . ' nicht gefunden oder keine Verfügung in Abstimmung.</p>';
    } else {
        $wer_id = !empty($antrag['verf1']) ? $antrag['verf1'] : $antrag['antrst'];
        $m = get_member_by_id($pdo, $wer_id);
        $name = $m ? trim($m['first_name'] . ' ' . $m['last_name']) : ('ID' . $wer_id);
        $neu = beschluss_annehmen($pdo, $nr, $antrag, 'Verfügung von ' . $name);
        if (function_exists('protokoll')) {
            protokoll($pdo, '', 'Skript', 'Verfügung-Nachgezogen', $nr . ' → ' . $neu);
        }
        if (!empty($_POST['mail'])) {
            require_once 'notification_mailer.php';
            nm_event_antrag_beschlossen($pdo, $neu, $antrag['titel'] ?? '', true, ($antrag['int_ext'] ?? 'e') === 'i', true);
        }
        echo '<p style="color:green"><b>' . h($nr) . ' wurde beschlossen als ' . h($neu) . '</b> (Beschlussbuch: „Verfügung von ' . h($name) . '").</p>';
    }
}

// Liste der betroffenen Anträge
$rows = $pdo->query("SELECT * FROM " . TABLE_ANTRAEGE . " WHERE antrnr LIKE 'B%' AND bart = 'V' ORDER BY antrnr")->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo '<p>Keine Verfügungen mehr in Abstimmung - es ist nichts zu tun.</p>';
}
$votum_text = [0 => 'offen', 1 => 'Ja', 2 => 'Nein', 3 => 'Enthaltung', 4 => 'Rückverweis', 5 => 'Bedenkzeit', 6 => 'befangen'];
foreach ($rows as $a) {
    $stimmen = [];
    for ($i = 1; $i <= 6; $i++) {
        if (empty($a["VName$i"])) continue;
        $vm = get_member_by_id($pdo, $a["VName$i"]);
        $stimmen[] = h($vm ? $vm['first_name'] : 'ID' . $a["VName$i"]) . ': ' . ($votum_text[(int)($a["Votum$i"] ?? 0)] ?? '?');
    }
    $wer_id = !empty($a['verf1']) ? $a['verf1'] : $a['antrst'];
    $m = get_member_by_id($pdo, $wer_id);
    echo '<div style="border:1px solid #ccc;border-radius:6px;padding:10px;margin:10px 0">';
    echo '<b>' . h($a['antrnr']) . '</b> - ' . h($a['titel']) . '<br>';
    echo '<small>Abstimmungsbeginn: ' . h($a['b_date'] ?: 'unbekannt') . ' &middot; Stimmen: ' . (implode(', ', $stimmen) ?: 'keine') . '</small><br>';
    echo '<small>Wird beschlossen als: „Verfügung von ' . h($m ? $m['first_name'] . ' ' . $m['last_name'] : 'ID' . $wer_id) . '"</small>';
    echo '<form method="post" style="margin-top:8px" onsubmit="return confirm(\'Diese Verfügung jetzt endgültig beschließen?\')">';
    echo '<input type="hidden" name="antrnr" value="' . h($a['antrnr']) . '"><input type="hidden" name="bestaetigt" value="1">';
    echo '<label><input type="checkbox" name="mail" value="1"> Benachrichtigung „Verfügung beschlossen" an alle senden</label> ';
    echo '<button>Jetzt beschließen</button></form></div>';
}

echo '<hr><form method="post" onsubmit="return confirm(\'Skript jetzt löschen?\')"><button name="selbst_loeschen" value="1">Dieses Skript jetzt löschen</button></form>';
