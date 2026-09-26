<?php
/**
 * berechtigte_editor.php – Verwaltung der Berechtigten-Tabelle
 *
 * Zugriffsschutz: SSO-Login + aktiv >= 18 (GF / Vorstand)
 * Wird von VTool und Sitzungsverwaltung aus verlinkt.
 */

require_once 'session_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('SV_CONFIG_LOADED')) require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config_adapter.php';
require_once __DIR__ . '/member_functions.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['member_id'])) {
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: login.php?redirect=' . urlencode('berechtigte_editor.php' . $qs));
    exit;
}

$cu          = get_member_by_id($pdo, $_SESSION['member_id']);
$user_aktiv  = (int)($cu['aktiv'] ?? 0);
$user_admin  = !empty($cu['is_admin']) || !empty($cu['sv_admin']);

if ($user_aktiv < 18 && !$user_admin) {
    http_response_code(403);
    die('<p style="font-family:sans-serif;padding:2em;color:#c00">Zugriff verweigert – nur für GF und Vorstand (aktiv ≥ 18).</p>');
}

// ── Hilfsdaten ────────────────────────────────────────────────────────────────
$funktion_options = [
    ''     => '– bitte wählen –',
    'Vo'   => 'Vo – Vorstand',
    'FVo'  => 'FVo – Finanzvorstand',
    'FVv'  => 'FVv – Finanzvorstand-Stellvertreter',
    'GF'   => 'GF – Geschäftsführung',
    'VA'   => 'VA – Verwaltungsassistenz',
    'RL'   => 'RL – Ressortleitung',
    'PL'   => 'PL – Projektleitung',
    'JT'   => 'JT – Jahrestreffen-Organisation',
    'TM'   => 'TM – Teamleitung',
    'FP'   => 'FP – Finanzprüfung',
    'SV'   => 'SV – Sitzungsverwaltung',
    'MB'   => 'MB – Mitgliederbetreuung',
    'Ka'   => 'Ka – Kasse',
    'Orga' => 'Orga – Organisation',
    'AD'   => 'AD – Technischer Admin',
    'Rx'   => 'Rx – ehem. Ressortleitung',
    'Vx'   => 'Vx – ehem. Vorstand',
    'Xx'   => 'Xx – Sonstige (Aufbewahrungsfrist)',
];

$aktiv_labels = [
    0  => '0 – Inaktiv / Ausgeschieden',
    10 => '10 – Aktives Mitglied',
    15 => '15 – Ressortleitung',
    18 => '18 – GF / Verwaltung',
    19 => '19 – Vorstand / Admin',
];

// Ressortliste laden
$ressorts = [];
try {
    $ressorts = $pdo->query("SELECT * FROM `" . TABLE_RESSORTS . "` ORDER BY Reihenfolge")->fetchAll();
    // Nur R-Spalten berücksichtigen (VTool-Konvention)
    $ressorts = array_filter($ressorts, fn($r) => preg_match('/^R\d{2}$/', $r[TABLE_RESSORTS_KEY] ?? ''));
} catch (Exception $e) {}

// LDAP
if (defined('LDAP_ENABLED') && LDAP_ENABLED) {
    require_once __DIR__ . '/ldap_functions.php';
}

// sv_*-Spalten vorhanden?
$sv_cols = false;
try {
    $sv_cols = (bool)$pdo->query("SHOW COLUMNS FROM berechtigte LIKE 'sv_admin'")->fetch();
} catch (Exception $e) {}

// Alle Berechtigten (für Dropdown + Liste)
$all_rows = $pdo->query("SELECT ID, KurzN, Vorname, Name, aktiv FROM berechtigte ORDER BY aktiv DESC, KurzN ASC")->fetchAll();
$active_rows = array_filter($all_rows, fn($r) => (int)$r['aktiv'] >= 10);

// ── POST: Aktionen ────────────────────────────────────────────────────────────
$flash        = '';
$ldap_prefill = null;   // vorausgefüllte Werte nach LDAP-Lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'ldap_lookup') {
        $mnr = trim($_POST['new_mnr'] ?? '');
        if ($mnr === '') {
            $flash = 'err:Bitte zuerst eine MNr eingeben.';
        } elseif (!defined('LDAP_ENABLED') || !LDAP_ENABLED) {
            $flash = 'err:LDAP ist nicht aktiviert (LDAP_ENABLED in config.php).';
        } else {
            $ldap_prefill = ldap_lookup_by_mnr($mnr);
            if (isset($ldap_prefill['error'])) {
                $flash = 'err:LDAP: ' . $ldap_prefill['error'];
                $ldap_prefill = null;
            } else {
                $flash = 'ok:LDAP-Daten geladen – bitte prüfen und ggf. anpassen.';
            }
        }
        // Kein redirect – Seite direkt rendern, Formular bleibt ausgefüllt
    }

    if ($action === 'create') {
        $new_id  = (int)($_POST['new_id']  ?? 0);
        $new_mnr = trim($_POST['new_mnr']  ?? '');

        if ($new_id <= 0) {
            $flash = 'err:Bitte eine gültige ID (> 0) eingeben.';
        } else {
            $chk = $pdo->prepare("SELECT 1 FROM berechtigte WHERE ID = ?");
            $chk->execute([$new_id]);
            if ($chk->fetch()) {
                $flash = 'err:ID ' . $new_id . ' existiert bereits.';
            } elseif ($new_mnr !== '') {
                $dup = $pdo->prepare("SELECT ID, KurzN, Vorname, Name FROM berechtigte WHERE MNr = ?");
                $dup->execute([$new_mnr]);
                $dup_row = $dup->fetch();
                if ($dup_row) {
                    $dn = $dup_row['KurzN'] ?: trim($dup_row['Vorname'] . ' ' . $dup_row['Name']);
                    $flash = 'err:MNr ' . $new_mnr . ' ist bereits vergeben (ID ' . $dup_row['ID'] . ' – ' . $dn . ').';
                }
            }

            if (!$flash) {
                $pdo->prepare("INSERT INTO berechtigte
                    (ID, MNr, Vorname, Name, KurzN, eMail, Funktion, aktiv, angelegt)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([
                        $new_id,
                        $new_mnr,
                        trim($_POST['new_vorname'] ?? ''),
                        trim($_POST['new_name']    ?? ''),
                        trim($_POST['new_kurzn']   ?? ''),
                        trim($_POST['new_email']   ?? ''),
                        trim($_POST['new_funktion']?? ''),
                        (int)($_POST['new_aktiv']  ?? 10),
                        date('Y-m-d H:i:s'),
                    ]);
                header('Location: berechtigte_editor.php?id=' . $new_id . '&msg=created');
                exit;
            }
        }
    }

    if ($action === 'save') {
        $sid = (int)($_POST['save_id'] ?? 0);
        if ($sid > 0) {
            $f = [
                'MNr'                   => trim($_POST['MNr'] ?? ''),
                'Vorname'               => trim($_POST['Vorname'] ?? ''),
                'Name'                  => trim($_POST['Name'] ?? ''),
                'KurzN'                 => trim($_POST['KurzN'] ?? ''),
                'Forumname'             => trim($_POST['Forumname'] ?? ''),
                'eMail'                 => trim($_POST['eMail'] ?? ''),
                'aktiv'                 => (int)($_POST['aktiv'] ?? 0),
                'Funktion'              => trim($_POST['Funktion'] ?? ''),
                'funktionsbeschreibung' => trim($_POST['funktionsbeschreibung'] ?? ''),
                'aktivbis'              => trim($_POST['aktivbis'] ?? ''),
                'vertretung'            => ($_POST['vertretung'] ?? '') !== '' ? (int)$_POST['vertretung'] : null,
                'FN'                    => trim($_POST['FN'] ?? ''),
                'ORG'                   => trim($_POST['ORG'] ?? ''),
                'TITLE'                 => trim($_POST['TITLE'] ?? ''),
                'TELW'                  => trim($_POST['TELW'] ?? ''),
                'TELH'                  => trim($_POST['TELH'] ?? ''),
                'ADRW'                  => trim($_POST['ADRW'] ?? ''),
                'ADRH'                  => trim($_POST['ADRH'] ?? ''),
            ];
            if ($sv_cols) {
                $f['sv_admin']        = isset($_POST['sv_admin']) ? 1 : 0;
                $f['sv_confidential'] = isset($_POST['sv_confidential']) ? 1 : 0;
            }
            foreach ($ressorts as $r) {
                $col = $r[TABLE_RESSORTS_KEY];
                $f[$col] = isset($_POST['r_' . $col]) ? 1 : 0;
            }

            $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($f)));
            $vals = [...array_values($f), $sid];
            $pdo->prepare("UPDATE berechtigte SET $sets WHERE ID = ?")->execute($vals);
            header('Location: berechtigte_editor.php?id=' . $sid . '&msg=saved');
            exit;
        }
    }
}

// ── GET: Daten laden ──────────────────────────────────────────────────────────
$edit_id  = (int)($_GET['id'] ?? 0);
$edit_row = null;
if ($edit_id) {
    $es = $pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
    $es->execute([$edit_id]);
    $edit_row = $es->fetch();
    if (!$edit_row) { $edit_id = 0; $flash = 'err:Datensatz nicht gefunden.'; }
}

$sort_map = [
    'ID'      => 'ID ASC',
    'Funktion'=> 'Funktion ASC, KurzN ASC',
    'MNr'     => 'MNr ASC',
    'KurzN'   => 'KurzN ASC',
];
$sort_key = $_GET['sort'] ?? '';
$order = $sort_map[$sort_key] ?? 'aktiv DESC, Funktion ASC, KurzN ASC';
$list = $pdo->query("SELECT * FROM berechtigte ORDER BY $order")->fetchAll();

if (!$flash && isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved')   $flash = 'ok:Gespeichert.';
    if ($_GET['msg'] === 'created') $flash = 'ok:Neuer Berechtigter angelegt – bitte Daten ausfüllen.';
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$next_id = max(array_column($all_rows, 'ID') ?: [0]) + 1;

$js_taken_ids = json_encode(array_map('intval', array_column($all_rows, 'ID')));
$js_mnr_list  = json_encode(array_values(array_map(fn($r) => [
    'id'   => (int)$r['ID'],
    'mnr'  => (string)$r['MNr'],
    'name' => $r['KurzN'] ?: trim($r['Vorname'] . ' ' . $r['Name']),
], array_filter($all_rows, fn($r) => $r['MNr'] !== '' && $r['MNr'] !== null))));
$ldap_js = (defined('LDAP_ENABLED') && LDAP_ENABLED) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Berechtigte verwalten</title>
<style>
:root {
    --bg: #f5f5f5; --card: #fff; --border: #ddd; --text: #333;
    --label: #666; --accent: #1565c0; --accent-hover: #0d47a1;
    --ok-bg: #e8f5e9; --ok-border: #4caf50; --ok-text: #2e7d32;
    --err-bg: #ffeaea; --err-border: #f44336; --err-text: #c62828;
    --row-hover: #f0f4ff; --th-bg: #e8edf5;
}
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
        --bg: #1a1a2e; --card: #16213e; --border: #374151; --text: #e2e8f0;
        --label: #94a3b8; --accent: #60a5fa; --accent-hover: #93c5fd;
        --ok-bg: #1a2e1a; --ok-border: #4caf50; --ok-text: #86efac;
        --err-bg: #2e1a1a; --err-border: #f44336; --err-text: #fca5a5;
        --row-hover: #1e2a3a; --th-bg: #1e293b;
    }
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; font-size: 14px; background: var(--bg); color: var(--text); padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.page-header h1 { font-size: 20px; font-weight: 600; }
.back-link { color: var(--accent); text-decoration: none; font-size: 13px; }
.back-link:hover { text-decoration: underline; }
.flash { padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
.flash.ok  { background: var(--ok-bg);  border: 1px solid var(--ok-border);  color: var(--ok-text); }
.flash.err { background: var(--err-bg); border: 1px solid var(--err-border); color: var(--err-text); }
/* ── Tabelle ── */
.card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
table.list { width: 100%; border-collapse: collapse; }
table.list th { background: var(--th-bg); padding: 8px 10px; text-align: left; font-weight: 600; font-size: 12px; border-bottom: 1px solid var(--border); white-space: nowrap; }
table.list th a { color: var(--accent); text-decoration: none; }
table.list th a:hover { text-decoration: underline; }
table.list td { padding: 7px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
table.list tr:last-child td { border-bottom: none; }
table.list tr:hover td { background: var(--row-hover); }
table.list td a { color: var(--accent); text-decoration: none; font-weight: 500; }
table.list td a:hover { text-decoration: underline; }
.badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; background: var(--th-bg); border: 1px solid var(--border); }
/* ── Formular ── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 20px; }
@media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } }
.form-section { margin-bottom: 20px; }
.form-section h3 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--label); border-bottom: 1px solid var(--border); padding-bottom: 6px; margin-bottom: 12px; }
.field-row { display: flex; flex-direction: column; gap: 3px; margin-bottom: 10px; }
.field-row label { font-size: 12px; color: var(--label); font-weight: 500; }
.field-row input, .field-row select, .field-row textarea {
    padding: 6px 8px; border: 1px solid var(--border); border-radius: 5px;
    background: var(--bg); color: var(--text); font-size: 13px; width: 100%;
}
.field-row input:focus, .field-row select:focus { outline: 2px solid var(--accent); border-color: transparent; }
.field-row input[readonly] { opacity: .6; cursor: not-allowed; }
.ressort-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; }
.ressort-item { display: flex; align-items: center; gap: 6px; font-size: 13px; padding: 3px 0; }
.ressort-item input[type=checkbox] { width: 15px; height: 15px; flex-shrink: 0; accent-color: var(--accent); }
.sv-flags { display: flex; flex-direction: column; gap: 10px; }
.sv-flag { display: flex; align-items: flex-start; gap: 8px; padding: 10px; background: var(--th-bg); border-radius: 6px; border: 1px solid var(--border); }
.sv-flag input[type=checkbox] { width: 16px; height: 16px; margin-top: 1px; flex-shrink: 0; accent-color: var(--accent); }
.sv-flag-text strong { font-size: 13px; }
.sv-flag-text small { display: block; color: var(--label); font-size: 12px; margin-top: 2px; }
.btn { display: inline-block; padding: 9px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: var(--accent-hover); }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.new-form { padding: 16px 20px; border-top: 1px solid var(--border); }
.new-form h3 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--label); margin-bottom: 12px; }
.new-form-row1 { display: grid; grid-template-columns: minmax(160px,1.5fr) 80px minmax(130px,1fr) auto; gap: 8px; align-items: end; margin-bottom: 8px; }
.new-form-row2 { display: grid; grid-template-columns: 1fr 1fr 100px 1fr; gap: 8px; align-items: end; }
@media (max-width: 820px) {
    .new-form-row1, .new-form-row2 { grid-template-columns: 1fr 1fr; }
    .ldap-btn-wrap { grid-column: 1 / -1; }
}
.new-form-row1 .field-row, .new-form-row2 .field-row { margin-bottom: 0; }
.mnr-wrap { position: relative; }
.mnr-status { font-size: 11px; margin-top: 3px; min-height: 15px; }
.mnr-status.dup  { color: var(--err-text); }
.mnr-status.free { color: var(--ok-text); }
.ldap-btn-wrap { display: flex; align-items: flex-end; padding-bottom: 0; }
.btn-ldap { padding: 6px 10px; background: var(--th-bg); border: 1px solid var(--border); border-radius: 5px; cursor: pointer; font-size: 12px; white-space: nowrap; color: var(--text); }
.btn-ldap:hover { border-color: var(--accent); color: var(--accent); }
.new-form-actions { display: flex; gap: 10px; align-items: center; margin-top: 12px; flex-wrap: wrap; }
.hint { font-size: 11px; color: var(--label); }
.form-actions { padding: 16px 20px; border-top: 1px solid var(--border); display: flex; gap: 10px; align-items: center; }
.explanation { padding: 16px; background: var(--th-bg); border-radius: 6px; font-size: 12px; line-height: 1.6; color: var(--label); border: 1px solid var(--border); }
.explanation h4 { font-size: 12px; font-weight: 700; margin-bottom: 8px; color: var(--text); }
.explanation dt { font-weight: 600; color: var(--text); }
.explanation dd { margin: 0 0 6px 0; }
</style>
</head>
<body>

<?php // ── Flash-Meldung ─────────────────────────────────────────────────────
if ($flash) {
    [$type, $text] = explode(':', $flash, 2);
    echo '<div class="flash ' . h($type) . '">' . h($text) . '</div>';
}
?>

<?php if ($edit_row): ?>
<!-- ═══════════════════════════════ EDIT-ANSICHT ══════════════════════════════ -->
<div class="page-header">
    <h1>✏️ Berechtigter: <?= h($edit_row['KurzN'] ?: $edit_row['Vorname'] . ' ' . $edit_row['Name']) ?></h1>
    <a href="berechtigte_editor.php" class="back-link">← Zurück zur Liste</a>
</div>

<form method="post" action="berechtigte_editor.php">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="save_id" value="<?= (int)$edit_row['ID'] ?>">

    <div class="card">
        <div class="form-grid">
            <!-- Linke Spalte: Identität + Berechtigung -->
            <div>
                <div class="form-section">
                    <h3>Identität</h3>
                    <div class="field-row">
                        <label>ID (unveränderlich)</label>
                        <input type="text" value="<?= h($edit_row['ID']) ?>" readonly>
                    </div>
                    <div class="field-row">
                        <label>MNr – Mitgliedsnummer</label>
                        <input type="text" name="MNr" value="<?= h($edit_row['MNr']) ?>" size="15">
                    </div>
                    <div class="field-row">
                        <label>Vorname</label>
                        <input type="text" name="Vorname" value="<?= h($edit_row['Vorname']) ?>">
                    </div>
                    <div class="field-row">
                        <label>Name (Nachname)</label>
                        <input type="text" name="Name" value="<?= h($edit_row['Name']) ?>">
                    </div>
                    <div class="field-row">
                        <label>KurzN – Kurzname (wird in Listen angezeigt)</label>
                        <input type="text" name="KurzN" value="<?= h($edit_row['KurzN']) ?>" size="15">
                    </div>
                    <div class="field-row">
                        <label>Forumname</label>
                        <input type="text" name="Forumname" value="<?= h($edit_row['Forumname']) ?>">
                    </div>
                    <div class="field-row">
                        <label>E-Mail</label>
                        <input type="email" name="eMail" value="<?= h($edit_row['eMail']) ?>">
                    </div>
                    <div class="field-row">
                        <label>Angelegt</label>
                        <input type="text" value="<?= h($edit_row['angelegt']) ?>" readonly>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Berechtigung</h3>
                    <div class="field-row">
                        <label>aktiv – Berechtigungslevel</label>
                        <select name="aktiv">
                            <?php foreach ($aktiv_labels as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= (int)$edit_row['aktiv'] === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                            <?php endforeach; ?>
                            <?php if (!array_key_exists((int)$edit_row['aktiv'], $aktiv_labels)): ?>
                                <option value="<?= (int)$edit_row['aktiv'] ?>" selected><?= (int)$edit_row['aktiv'] ?> – (anderer Wert)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="field-row">
                        <label>Funktion</label>
                        <select name="Funktion">
                            <?php foreach ($funktion_options as $val => $lbl): ?>
                                <option value="<?= h($val) ?>" <?= $edit_row['Funktion'] === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-row">
                        <label>Funktionsbeschreibung (Freitext, z.&nbsp;B. „Ko Organisation")</label>
                        <input type="text" name="funktionsbeschreibung" value="<?= h($edit_row['funktionsbeschreibung']) ?>">
                    </div>
                    <div class="field-row">
                        <label>aktiv bis (Datum, leer = unbefristet)</label>
                        <input type="date" name="aktivbis" value="<?= h($edit_row['aktivbis']) ?>">
                    </div>
                    <div class="field-row">
                        <label>Standard-Stellvertretung</label>
                        <select name="vertretung">
                            <option value="">– bitte auswählen –</option>
                            <?php foreach ($active_rows as $m): ?>
                                <option value="<?= (int)$m['ID'] ?>" <?= (int)$edit_row['vertretung'] === (int)$m['ID'] ? 'selected' : '' ?>>
                                    <?= h($m['KurzN'] ?: $m['Vorname'] . ' ' . $m['Name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ($sv_cols): ?>
                <div class="form-section">
                    <h3>Sitzungsverwaltung</h3>
                    <div class="sv-flags">
                        <label class="sv-flag">
                            <input type="checkbox" name="sv_admin" <?= !empty($edit_row['sv_admin']) ? 'checked' : '' ?>>
                            <div class="sv-flag-text">
                                <strong>sv_admin – Admin-Zugang</strong>
                                <small>Vollzugriff auf Verwaltungsfunktionen im Sitzungstool</small>
                            </div>
                        </label>
                        <label class="sv-flag">
                            <input type="checkbox" name="sv_confidential" <?= !empty($edit_row['sv_confidential']) ? 'checked' : '' ?>>
                            <div class="sv-flag-text">
                                <strong>sv_confidential – Vertrauliche Inhalte</strong>
                                <small>Darf vertrauliche TOPs und interne Anträge sehen</small>
                            </div>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Rechte Spalte: Kontakt + Ressorts + Erläuterung -->
            <div>
                <div class="form-section">
                    <h3>Kontaktdaten</h3>
                    <div class="field-row"><label>FN – vollständiger Name (vCard)</label><input type="text" name="FN" value="<?= h($edit_row['FN'] ?? '') ?>"></div>
                    <div class="field-row"><label>ORG – Organisation / Abteilung</label><input type="text" name="ORG" value="<?= h($edit_row['ORG'] ?? '') ?>"></div>
                    <div class="field-row"><label>TITLE – Titel / Position</label><input type="text" name="TITLE" value="<?= h($edit_row['TITLE'] ?? '') ?>"></div>
                    <div class="field-row"><label>TELW – Telefon dienstlich</label><input type="tel" name="TELW" value="<?= h($edit_row['TELW'] ?? '') ?>"></div>
                    <div class="field-row"><label>TELH – Telefon privat / mobil</label><input type="tel" name="TELH" value="<?= h($edit_row['TELH'] ?? '') ?>"></div>
                    <div class="field-row"><label>ADRW – Adresse dienstlich</label><textarea name="ADRW" rows="2"><?= h($edit_row['ADRW'] ?? '') ?></textarea></div>
                    <div class="field-row"><label>ADRH – Adresse privat</label><textarea name="ADRH" rows="2"><?= h($edit_row['ADRH'] ?? '') ?></textarea></div>
                </div>

                <?php if ($ressorts): ?>
                <div class="form-section">
                    <h3>Ressort-Zugehörigkeit</h3>
                    <div class="ressort-grid">
                        <?php foreach ($ressorts as $r):
                            $col   = $r[TABLE_RESSORTS_KEY];
                            $name  = $r['Ressort'] ?? $col;
                            $checked = !empty($edit_row[$col]) && $edit_row[$col] != '0' && $edit_row[$col] !== '' ? 'checked' : '';
                        ?>
                            <label class="ressort-item">
                                <input type="checkbox" name="r_<?= h($col) ?>" <?= $checked ?>>
                                <?= h($name) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="explanation">
                    <h4>Erläuterung aktiv-Level</h4>
                    <dl>
                        <dt>0</dt><dd>Inaktiv / ausgeschieden – kein Zugriff (Daten 10 Jahre aufbewahren)</dd>
                        <dt>10</dt><dd>Aktives Mitglied – Standard-Zugriff</dd>
                        <dt>15</dt><dd>Ressortleitung – erweiterte Rechte (Antragsfreigabe etc.)</dd>
                        <dt>18</dt><dd>GF / Verwaltung – Admin-Zugang, kann Anträge aller sehen</dd>
                        <dt>19</dt><dd>Vorstand – höchste Berechtigung, vertrauliche Inhalte sichtbar</dd>
                    </dl>
                    <br>
                    <strong>Löschen ist nicht vorgesehen</strong> – steuerliche Aufbewahrungsfrist 10 Jahre. Ausgeschiedene Mitglieder auf aktiv=0 setzen.
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Daten speichern</button>
            <a href="berechtigte_editor.php" class="back-link">Abbrechen</a>
        </div>
    </div>
</form>

<?php else: ?>
<!-- ══════════════════════════════ LISTEN-ANSICHT ═════════════════════════════ -->
<div class="page-header">
    <h1>👥 Berechtigte verwalten</h1>
    <span class="hint"><?= count($list) ?> Einträge</span>
</div>

<div class="card">
    <table class="list">
        <thead>
            <tr>
                <th><a href="?sort=ID">ID</a></th>
                <th><a href="?sort=Funktion">Funktion</a></th>
                <th><a href="?sort=MNr">MNr</a></th>
                <th><a href="?sort=KurzN">KurzN / Name</a></th>
                <th>Funktionsbeschreibung</th>
                <th><a href="?sort=aktiv">aktiv</a></th>
                <th>E-Mail</th>
                <?php if ($sv_cols): ?><th>SV</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($list as $row):
            $aktiv_val = (int)$row['aktiv'];
            $badge_color = match(true) {
                $aktiv_val >= 19 => 'background:#1565c0;color:#fff',
                $aktiv_val >= 18 => 'background:#2e7d32;color:#fff',
                $aktiv_val >= 15 => 'background:#e65100;color:#fff',
                $aktiv_val >= 10 => 'background:#546e7a;color:#fff',
                default          => 'background:#ccc;color:#333',
            };
        ?>
        <tr>
            <td><?= h($row['ID']) ?></td>
            <td><span class="badge"><?= h($row['Funktion']) ?></span></td>
            <td><a href="berechtigte_editor.php?id=<?= (int)$row['ID'] ?>"><?= h($row['MNr']) ?></a></td>
            <td><?= h($row['KurzN'] ?: $row['Vorname'] . ' ' . $row['Name']) ?></td>
            <td><?= h($row['funktionsbeschreibung']) ?></td>
            <td><span class="badge" style="<?= $badge_color ?>"><?= $aktiv_val ?></span></td>
            <td><?= h($row['eMail']) ?></td>
            <?php if ($sv_cols): ?>
            <td>
                <?= !empty($row['sv_admin']) ? '🔑' : '' ?>
                <?= !empty($row['sv_confidential']) ? '🔒' : '' ?>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php
    // Formularwerte: nach LDAP-Lookup aus $ldap_prefill, sonst aus $_POST (Retry), sonst leer
    $cv = fn(string $key, string $default = '') =>
        h($_POST[$key] ?? $default);
    $sel = fn(string $key, $val) =>
        (string)($val) === (string)($_POST[$key] ?? '') ? 'selected' : '';
    ?>
    <form method="post" action="berechtigte_editor.php" class="new-form">
        <input type="hidden" name="action" value="create">
        <h3>Neuen Berechtigten anlegen</h3>

        <!-- Zeile 1: Funktion → ID-Vorschlag, MNr + optionaler LDAP-Button -->
        <div class="new-form-row1">
            <div class="field-row">
                <label>Funktion</label>
                <select name="new_funktion" id="new_funktion" onchange="suggestId()">
                    <?php foreach ($funktion_options as $val => $lbl): ?>
                        <option value="<?= h($val) ?>" <?= $sel('new_funktion', $val) ?>><?= h($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-row">
                <label>ID *</label>
                <input type="number" name="new_id" id="new_id_field"
                       value="<?= h($_POST['new_id'] ?? $next_id) ?>" min="1" required>
            </div>
            <div class="field-row mnr-wrap">
                <label>MNr</label>
                <input type="text" name="new_mnr" id="new_mnr_field"
                       value="<?= $cv('new_mnr') ?>"
                       placeholder="z.&nbsp;B. 12345"
                       oninput="checkMnrDuplicate()" onblur="checkMnrDuplicate()">
                <div class="mnr-status" id="mnr-status"></div>
            </div>
            <?php if (defined('LDAP_ENABLED') && LDAP_ENABLED): ?>
            <div class="ldap-btn-wrap">
                <button type="submit" name="action" value="ldap_lookup" class="btn-ldap">
                    🔍 Aus Mitgliederdatenbank laden
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Zeile 2: Stammdaten (ggf. aus LDAP vorausgefüllt) -->
        <div class="new-form-row2">
            <div class="field-row">
                <label>Vorname</label>
                <input type="text" name="new_vorname"
                       value="<?= h($ldap_prefill['vorname'] ?? $_POST['new_vorname'] ?? '') ?>"
                       placeholder="Vorname">
            </div>
            <div class="field-row">
                <label>Name (Nachname)</label>
                <input type="text" name="new_name"
                       value="<?= h($ldap_prefill['name'] ?? $_POST['new_name'] ?? '') ?>"
                       placeholder="Nachname">
            </div>
            <div class="field-row">
                <label>KurzN</label>
                <input type="text" name="new_kurzn" value="<?= $cv('new_kurzn') ?>"
                       placeholder="z.&nbsp;B. MMax">
            </div>
            <div class="field-row">
                <label>E-Mail</label>
                <input type="email" name="new_email"
                       value="<?= h($ldap_prefill['email'] ?? $_POST['new_email'] ?? '') ?>"
                       placeholder="name@example.org">
            </div>
        </div>

        <div class="new-form-actions">
            <select name="new_aktiv" style="padding:6px 8px;border:1px solid var(--border);border-radius:5px;background:var(--bg);color:var(--text);font-size:13px">
                <?php foreach ($aktiv_labels as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $sel('new_aktiv', $val) ?: ($val === 10 ? 'selected' : '') ?>><?= h($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="create" class="btn btn-primary btn-sm">+ Anlegen</button>
            <span class="hint">ID: Vo/FVo/FVv/GF/VA → &lt;100 · Sonstige Aktive → &lt;900 · AD/Rx/Vx/Xx → ≥900</span>
        </div>
    </form>

<script>
const takenIds = <?= $js_taken_ids ?>;
const mnrList  = <?= $js_mnr_list ?>;

const FUNKTION_RANGES = {
    'Vo': [1,99], 'FVo': [1,99], 'FVv': [1,99], 'GF': [1,99], 'VA': [1,99],
    'AD': [900,9999], 'Rx': [900,9999], 'Vx': [900,9999], 'Xx': [900,9999],
};

function nextFreeId(min, max) {
    const taken = new Set(takenIds);
    for (let i = min; i <= max; i++) if (!taken.has(i)) return i;
    return max + 1;
}

function suggestId() {
    const fn = document.getElementById('new_funktion').value;
    const [min, max] = FUNKTION_RANGES[fn] ?? [100, 899];
    document.getElementById('new_id_field').value = nextFreeId(min, max);
}

function checkMnrDuplicate() {
    const mnr = document.getElementById('new_mnr_field').value.trim();
    const el  = document.getElementById('mnr-status');
    if (!mnr) { el.textContent = ''; el.className = 'mnr-status'; return; }
    const dup = mnrList.find(r => r.mnr === mnr);
    if (dup) {
        el.textContent = '⚠ bereits vergeben: ID ' + dup.id + ' (' + dup.name + ')';
        el.className = 'mnr-status dup';
    } else {
        el.textContent = '✓ frei';
        el.className = 'mnr-status free';
    }
}
</script>
</div>

<?php if (!$sv_cols): ?>
<div class="flash err" style="font-size:12px">
    ⚠️ Die Spalten <code>sv_admin</code> und <code>sv_confidential</code> fehlen noch in der Datenbank.
    Bitte zuerst <a href="tools/migrate_berechtigte_cleanup.php">tools/migrate_berechtigte_cleanup.php</a> ausführen.
</div>
<?php endif; ?>

<?php endif; ?>

</body>
</html>
