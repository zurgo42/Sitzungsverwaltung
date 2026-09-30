<?php
/**
 * tab_dokumente_allgemein.php – Allgemeine Dokumentensammlung
 *
 * Ersatz für das alte dokumente.php im neuen Look & Feel.
 * Kann als "PHP-Skript einbinden" im Admin-Steuerungsbereich konfiguriert werden.
 *
 * Benötigt: $pdo, $current_user (aus dem einbindenden index.php-Kontext)
 */

// Sicherstellen, dass Kontext vorhanden ist
if (!isset($pdo) || !isset($current_user)) {
    echo '<p style="color:#c00;padding:1em;">Fehler: Dieser Tab muss über index.php aufgerufen werden.</p>';
    return;
}

require_once __DIR__ . '/functions.php';

// ── Zugriffsebene des aktuellen Nutzers ──────────────────────────────────────
$user_aktiv   = (int)($current_user['aktiv'] ?? get_member_access_level($current_user));
$user_is_admin = is_admin_user($current_user) || $user_aktiv >= 18;

// Kategorien (identisch mit altem System)
$kat = ['', 'Satzung', 'Ordnungen', 'Richtlinien', 'Formulare',
        'MV-Unterlagen', 'Dokumentationen', 'Urteile etc.', 'Medien', 'Sonstige'];

// Tabelle auf Existenz prüfen
$table_ok = false;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'dokumente'");
    $table_ok = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {}

if (!$table_ok) {
    echo '<div class="info-box" style="background:#fff3cd;border-left:4px solid #ffc107;">';
    echo '⚠️ Die Tabelle <code>dokumente</code> existiert noch nicht. ';
    echo '<a href="tools/install_dokumente.php">→ Jetzt anlegen</a></div>';
    return;
}

// ── Upload-Verzeichnis (Server-Pfad) und Basis-URL aus svconfig ──────────────
$updir_stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'dokumente_upload_dir' LIMIT 1");
$upload_dir = $updir_stmt ? ($updir_stmt->fetchColumn() ?: '../docs/') : '../docs/';
if (substr($upload_dir, -1) !== '/') $upload_dir .= '/';

$baseurl_stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'dokumente_base_url' LIMIT 1");
$dok_base_url = $baseurl_stmt ? ($baseurl_stmt->fetchColumn() ?: '') : '';
if ($dok_base_url !== '' && substr($dok_base_url, -1) !== '/') $dok_base_url .= '/';

// ── POST-Verarbeitung ─────────────────────────────────────────────────────────
$flash_ok  = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dok_action'])) {
    if (!$user_is_admin) {
        $flash_err = 'Keine Berechtigung.';
    } else {
        $action = $_POST['dok_action'];

        if ($action === 'save_meta') {
            // Metadaten eines vorhandenen Eintrags speichern
            $id = (int)($_POST['dok_id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE dokumente SET
                    titel=?, beschreibung=?, stichworte=?, version=?,
                    kurzurl=?, zugriff=?, k1=?, adminbem=?, datum=?
                    WHERE id=?");
                $stmt->execute([
                    trim($_POST['titel']     ?? ''),
                    trim($_POST['beschreibung'] ?? ''),
                    trim($_POST['stichworte'] ?? ''),
                    trim($_POST['version']   ?? ''),
                    trim($_POST['kurzurl']   ?? ''),
                    (int)($_POST['zugriff']  ?? 0),
                    (int)($_POST['k1']       ?? 0),
                    trim($_POST['adminbem']  ?? ''),
                    trim($_POST['datum']     ?? date('Y-m-d')),
                    $id,
                ]);
                $flash_ok = 'Eintrag gespeichert.';
            }

        } elseif ($action === 'new_link') {
            // Neuer Eintrag als externer Link
            $titel = trim($_POST['titel'] ?? '');
            $link  = trim($_POST['linkneu'] ?? '');
            if ($titel && $link) {
                $stmt = $pdo->prepare("INSERT INTO dokumente
                    (name, verz, datum, groesse, titel, kurzurl, version, zugriff, beschreibung, stichworte, k1, adminbem)
                    VALUES (?, '', ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $link,
                    date('Y-m-d'),
                    $titel,
                    trim($_POST['kurzurl'] ?? ''),
                    trim($_POST['version'] ?? ''),
                    (int)($_POST['zugriff'] ?? 0),
                    trim($_POST['beschreibung'] ?? ''),
                    trim($_POST['stichworte']   ?? ''),
                    (int)($_POST['k1'] ?? 0),
                    trim($_POST['adminbem'] ?? ''),
                ]);
                $flash_ok = 'Link gespeichert.';
            } else {
                $flash_err = 'Titel und Link sind Pflichtfelder.';
            }

        } elseif ($action === 'upload') {
            // Datei hochladen
            $titel = trim($_POST['titel'] ?? '');
            if (!$titel) {
                $flash_err = 'Bitte einen Titel eingeben.';
            } elseif (empty($_FILES['datei']['name'])) {
                $flash_err = 'Keine Datei ausgewählt.';
            } else {
                $filename = basename($_FILES['datei']['name']);
                $target   = $upload_dir . $filename;
                if (file_exists($target)) {
                    $flash_err = "Datei \"{$filename}\" existiert bereits im Upload-Verzeichnis.";
                } elseif (!is_dir($upload_dir)) {
                    $flash_err = "Upload-Verzeichnis \"{$upload_dir}\" nicht gefunden.";
                } elseif (move_uploaded_file($_FILES['datei']['tmp_name'], $target)) {
                    $size = filesize($target) ?: 0;
                    $stmt = $pdo->prepare("INSERT INTO dokumente
                        (name, verz, datum, groesse, titel, kurzurl, version, zugriff, beschreibung, stichworte, k1, adminbem)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $filename,
                        $upload_dir,
                        date('Y-m-d'),
                        $size,
                        $titel,
                        trim($_POST['kurzurl']     ?? ''),
                        trim($_POST['version']     ?? ''),
                        (int)($_POST['zugriff']    ?? 0),
                        trim($_POST['beschreibung'] ?? ''),
                        trim($_POST['stichworte']   ?? ''),
                        (int)($_POST['k1']          ?? 0),
                        trim($_POST['adminbem']     ?? ''),
                    ]);
                    $flash_ok = "Datei \"{$filename}\" hochgeladen.";
                } else {
                    $flash_err = 'Hochladen fehlgeschlagen (Serverrechte prüfen).';
                }
            }

        } elseif ($action === 'hide') {
            $id = (int)($_POST['dok_id'] ?? 0);
            if ($id > 0) {
                // Datei umbenennen: Präfix xxx
                $row = $pdo->query("SELECT verz, name FROM dokumente WHERE id=$id")->fetch();
                if ($row && $row['verz'] && $row['name'] && substr($row['name'], 0, 3) !== 'xxx') {
                    $old = $row['verz'] . $row['name'];
                    $new = $row['verz'] . 'xxx' . $row['name'];
                    if (file_exists($old)) rename($old, $new);
                    $pdo->prepare("UPDATE dokumente SET name=?, zugriff=99 WHERE id=?")
                        ->execute(['xxx' . $row['name'], $id]);
                } else {
                    $pdo->prepare("UPDATE dokumente SET zugriff=99 WHERE id=?")->execute([$id]);
                }
                $flash_ok = 'Eintrag verborgen.';
            }

        } elseif ($action === 'mark_old') {
            $id = (int)($_POST['dok_id'] ?? 0);
            if ($id > 0) {
                $row = $pdo->query("SELECT verz, name FROM dokumente WHERE id=$id")->fetch();
                if ($row && $row['verz'] && $row['name']
                    && substr($row['name'], 0, 3) !== 'vvv' && substr($row['name'], 0, 3) !== 'xxx') {
                    $old = $row['verz'] . $row['name'];
                    $new = $row['verz'] . 'vvv' . $row['name'];
                    if (file_exists($old)) rename($old, $new);
                    $pdo->prepare("UPDATE dokumente SET name=? WHERE id=?")->execute(['vvv' . $row['name'], $id]);
                }
                $flash_ok = 'Als überholt markiert.';
            }

        } elseif ($action === 'delete') {
            $id = (int)($_POST['dok_id'] ?? 0);
            if ($id > 0) {
                $row = $pdo->query("SELECT verz, name FROM dokumente WHERE id=$id")->fetch();
                if ($row && $row['verz'] && $row['name'] && file_exists($row['verz'] . $row['name'])) {
                    unlink($row['verz'] . $row['name']);
                }
                $pdo->prepare("DELETE FROM dokumente WHERE id=?")->execute([$id]);
                $flash_ok = 'Eintrag gelöscht.';
            }

        } elseif ($action === 'save_sammler') {
            // Link-Sammlung speichern
            $sid   = (int)($_POST['sid'] ?? 0);
            $titel = trim($_POST['s_titel'] ?? '');
            $descr = trim($_POST['s_beschreibung'] ?? '');
            $url   = trim($_POST['s_url'] ?? '');
            if ($titel && $url) {
                $_tbl = @$pdo->query("SHOW TABLES LIKE 'doksammler'");
                if ($_tbl && $_tbl->rowCount() > 0) {
                    if ($sid > 0) {
                        $pdo->prepare("UPDATE doksammler SET titel=?, beschreibung=?, url=? WHERE id=?")
                            ->execute([$titel, $descr, $url, $sid]);
                    } else {
                        $pdo->prepare("INSERT INTO doksammler (titel, beschreibung, url) VALUES (?,?,?)")
                            ->execute([$titel, $descr, $url]);
                    }
                    $flash_ok = 'Link-Sammlung gespeichert.';
                }
            }

        } elseif ($action === 'delete_sammler') {
            $sid = (int)($_POST['sid'] ?? 0);
            if ($sid > 0) {
                $pdo->prepare("DELETE FROM doksammler WHERE id=?")->execute([$sid]);
                $flash_ok = 'Link-Sammlung gelöscht.';
            }
        }
    }
}

// ── Filter-Parameter aus GET/POST ────────────────────────────────────────────
$sort       = (int)($_GET['sort']      ?? $_POST['sort']      ?? 0);
$stichwort  = trim($_GET['stichwort'] ?? $_POST['stichwort'] ?? '');
$nuroeff    = (int)($_GET['nuroeff']  ?? $_POST['nuroeff']  ?? 0);
$vorver     = (int)($_GET['vorver']   ?? $_POST['vorver']   ?? 0);
// Kategorie-Auswahl
$kauswahl = [];
$q_kat = 0;
for ($i = 1; $i < count($kat); $i++) {
    $kauswahl[$i] = (int)($_GET["kaus$i"] ?? $_POST["kaus$i"] ?? 0);
    if ($kauswahl[$i]) $q_kat++;
}
if ($q_kat === 0) {
    // Standard: erste 4 Kategorien
    for ($i = 1; $i <= 4; $i++) $kauswahl[$i] = 1;
}
if (isset($_GET['kalle']) || isset($_POST['kalle'])) {
    for ($i = 1; $i < count($kat); $i++) $kauswahl[$i] = 1;
}

// ── Dokumente abfragen ────────────────────────────────────────────────────────
$where_parts = [];
$params_q    = [];

// Zugriffsfilter
if ($user_is_admin) {
    // Admin sieht alles inkl. xxx-Dateien
} else {
    $where_parts[] = 'zugriff <= ?';
    $params_q[]    = $user_aktiv;
    $where_parts[] = 'name NOT LIKE "xxx%"';
}

// Vorversionen
if (!$vorver) $where_parts[] = 'name NOT LIKE "vvv%"';

// Nur öffentlich (zugriff < 2) wenn nicht aktiver Funktionsträger
if ($user_aktiv < 2 && !$user_is_admin && !$nuroeff) {
    // nichts – durch Zugriffsfilter oben bereits erledigt
}

// Kategorie-Filter
$kat_or = [];
for ($i = 1; $i < count($kat); $i++) {
    if ($kauswahl[$i]) { $kat_or[] = "k1 = ?"; $params_q[] = $i; }
}
if ($kat_or) $where_parts[] = '(' . implode(' OR ', $kat_or) . ')';

// Stichwort-Filter
if (strlen($stichwort) >= 2) {
    $where_parts[] = '(titel LIKE ? OR beschreibung LIKE ? OR stichworte LIKE ?)';
    $sw = '%' . $stichwort . '%';
    array_push($params_q, $sw, $sw, $sw);
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$order_sql = match($sort) {
    1 => 'ORDER BY datum DESC, k1 ASC',
    2 => 'ORDER BY titel ASC',
    4 => 'ORDER BY zugriff DESC, datum DESC',
    default => 'ORDER BY k1 ASC, zugriff ASC, datum DESC',
};

$stmt_docs = $pdo->prepare("SELECT id, verz, name, datum, groesse, kurzurl, titel, zugriff, k1, version, beschreibung, stichworte, adminbem FROM dokumente $where_sql $order_sql");
$stmt_docs->execute($params_q);
$docs = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

// ── Sammler laden ─────────────────────────────────────────────────────────────
$sammler = [];
$sammler_tbl = @$pdo->query("SHOW TABLES LIKE 'doksammler'");
$has_sammler_tbl = $sammler_tbl && $sammler_tbl->rowCount() > 0;
if ($has_sammler_tbl) {
    $sammler = $pdo->query("SELECT * FROM doksammler ORDER BY sort_nr ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────
function dok_ext(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return $ext ?: '–';
}
function dok_size(int $bytes): string {
    if ($bytes <= 0) return '';
    if ($bytes < 1024) return $bytes . ' B';
    return round($bytes / 1024) . ' kB';
}
function dok_is_link(array $doc): bool {
    return $doc['verz'] === '' && strpos($doc['name'], '://') !== false;
}
function dok_href(array $doc): string {
    global $dok_base_url;
    if (dok_is_link($doc)) return htmlspecialchars($doc['name']);
    if ($doc['kurzurl'])   return htmlspecialchars($doc['kurzurl']);
    // Basis-URL konfiguriert → immer verwenden
    if ($dok_base_url !== '') return htmlspecialchars($dok_base_url . basename($doc['name']));
    // Fallback: Pfad aus verz+name (funktioniert nur wenn verz eine gültige URL ist)
    return htmlspecialchars($doc['verz'] . $doc['name']);
}

?>
<style>
/* ── Dokumentensammlung ─────────────────────────────────────── */
.dok-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:18px; }
.dok-toolbar input[type=text],
.dok-toolbar select { padding:6px 10px; border:1px solid #ddd; border-radius:5px; font-size:13px; }
.dok-kat-pills { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
.dok-kat-pill {
    display:inline-flex; align-items:center; gap:5px; cursor:pointer;
    padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;
    border:2px solid transparent; user-select:none; transition:.15s;
}
.dok-kat-pill input { display:none; }
.dok-kat-pill.on  { background:#667eea; color:#fff; border-color:#667eea; }
.dok-kat-pill.off { background:#f0f0f0; color:#555; border-color:#ddd; }
.dok-kat-pill.off:hover { border-color:#999; }
.dok-table { width:100%; border-collapse:collapse; font-size:13px; }
.dok-table th { background:#667eea; color:#fff; padding:8px 10px; text-align:left; font-weight:600; }
.dok-table td { padding:8px 10px; border-bottom:1px solid #eee; vertical-align:top; }
.dok-table tr:hover td { background:#f8f8ff; }
.dok-cat-head { background:#f0f0f8; font-weight:700; font-size:13px; color:#555; }
.dok-title-link { font-weight:600; color:#333; text-decoration:none; }
.dok-title-link:hover { color:#667eea; text-decoration:underline; }
.dok-badge-ext { display:inline-block; padding:1px 6px; border-radius:3px; font-size:11px; font-weight:700; background:#e8eaf6; color:#3949ab; margin-right:4px; text-transform:uppercase; }
.dok-badge-intern { background:#fff3e0; color:#e65100; font-size:11px; padding:1px 5px; border-radius:3px; }
.dok-badge-old { background:#f5f5f5; color:#999; font-size:11px; padding:1px 5px; border-radius:3px; }
.dok-badge-hidden { background:#fce4ec; color:#c62828; font-size:11px; padding:1px 5px; border-radius:3px; }
.dok-desc { color:#555; font-size:12px; margin-top:2px; }
.dok-edit-row td { background:#fffde7; border-bottom:2px solid #fff9c4; padding:12px 10px; }
.dok-edit-form { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:8px; }
.dok-edit-form label { font-size:11px; font-weight:600; color:#666; display:block; margin-bottom:2px; }
.dok-edit-form input,.dok-edit-form textarea,.dok-edit-form select {
    width:100%; padding:5px 8px; border:1px solid #ccc; border-radius:4px; font-size:12px; box-sizing:border-box; }
.dok-edit-form textarea { resize:vertical; min-height:48px; }
.dok-act-btn { font-size:11px; padding:3px 8px; border:none; border-radius:4px; cursor:pointer; }
.dok-act-save   { background:#4caf50; color:#fff; }
.dok-act-hide   { background:#ff9800; color:#fff; }
.dok-act-old    { background:#9e9e9e; color:#fff; }
.dok-act-delete { background:#f44336; color:#fff; }
.dok-act-btn:hover { opacity:.85; }
.dok-section-head { font-size:16px; font-weight:700; margin:24px 0 10px; color:#333; border-left:4px solid #667eea; padding-left:10px; }
.sammler-table { width:100%; border-collapse:collapse; font-size:13px; }
.sammler-table td { padding:8px 10px; border-bottom:1px solid #eee; vertical-align:top; }
.sammler-table th { background:#667eea; color:#fff; padding:8px 10px; text-align:left; }
.flash-ok  { background:#e8f5e9; border-left:4px solid #4caf50; padding:10px 14px; border-radius:4px; margin-bottom:14px; color:#2e7d32; }
.flash-err { background:#fce4ec; border-left:4px solid #f44336; padding:10px 14px; border-radius:4px; margin-bottom:14px; color:#c62828; }
@media(max-width:640px) {
    .dok-table,.dok-table tbody,.dok-table tr,.dok-table td { display:block; }
    .dok-table th { display:none; }
    .dok-table td { border-bottom:none; padding:4px 8px; }
    .dok-table tr { border:1px solid #e0e0e0; border-radius:6px; margin-bottom:8px; padding:6px; }
}
</style>

<h2>📁 Dokumentensammlung</h2>

<?php if ($flash_ok):  ?><div class="flash-ok">✅ <?= htmlspecialchars($flash_ok) ?></div><?php endif; ?>
<?php if ($flash_err): ?><div class="flash-err">❌ <?= htmlspecialchars($flash_err) ?></div><?php endif; ?>

<p style="color:#555;font-size:13px;margin-bottom:16px;">
    Hier findest du zentrale Dokumente für das Vereinsleben – in der jeweils aktuellen Version.
</p>

<!-- ── Admin: Neues Dokument / Link hinzufügen ── -->
<?php if ($user_is_admin): ?>
<button class="accordion-button" onclick="toggleAccordion(this)" style="margin-bottom:12px;">
    ➕ Neues Dokument / Link hinzufügen
</button>
<div class="accordion-content" style="padding:16px;border:1px solid #ddd;border-radius:0 0 6px 6px;margin-bottom:18px;">
    <div style="display:flex;gap:30px;flex-wrap:wrap;">

        <!-- Datei hochladen -->
        <form method="POST" enctype="multipart/form-data" style="flex:1;min-width:280px;">
            <input type="hidden" name="dok_action" value="upload">
            <h4 style="margin:0 0 10px;">Datei hochladen</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div style="grid-column:span 2;">
                    <label style="font-size:12px;font-weight:600;">Datei</label>
                    <input type="file" name="datei" style="font-size:13px;">
                </div>
                <div style="grid-column:span 2;">
                    <label style="font-size:12px;font-weight:600;">Titel *</label>
                    <input type="text" name="titel" placeholder="Kurzer, einprägsamer Titel" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;">Version</label>
                    <input type="text" name="version" placeholder="z.B. 2024">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;">Kategorie</label>
                    <select name="k1">
                        <?php for ($i=1;$i<count($kat);$i++) echo "<option value=\"$i\">{$kat[$i]}</option>"; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;">Zugriff (0–19)</label>
                    <input type="number" name="zugriff" value="0" min="0" max="19">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;">Kurz-URL</label>
                    <input type="text" name="kurzurl" placeholder="link.example.de/xyz">
                </div>
                <div style="grid-column:span 2;">
                    <label style="font-size:12px;font-weight:600;">Beschreibung</label>
                    <input type="text" name="beschreibung" placeholder="Was ist dieses Dokument?">
                </div>
                <div style="grid-column:span 2;">
                    <label style="font-size:12px;font-weight:600;">Stichworte</label>
                    <input type="text" name="stichworte" placeholder="z.B. Satzung, Satzungsänderung, 2023">
                </div>
            </div>
            <small style="color:#888;">Upload-Verzeichnis: <code><?= htmlspecialchars($upload_dir) ?></code></small><br>
            <button type="submit" class="btn-primary" style="margin-top:8px;">⬆️ Hochladen</button>
        </form>

        <div style="width:1px;background:#ddd;"></div>

        <!-- Externen Link eintragen -->
        <form method="POST" style="flex:1;min-width:280px;">
            <input type="hidden" name="dok_action" value="new_link">
            <h4 style="margin:0 0 10px;">Externen Link eintragen</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div style="grid-column:span 2;">
                    <label style="font-size:12px;font-weight:600;">URL *</label>
                    <input type="text" name="linkneu" placeholder="https://..." required>
                </div>
                <div style="grid-column:span 2;">
                    <label style="font-size:12px;font-weight:600;">Titel *</label>
                    <input type="text" name="titel" placeholder="Kurzer, einprägsamer Titel" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;">Version</label>
                    <input type="text" name="version" placeholder="z.B. 2024">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;">Kategorie</label>
                    <select name="k1">
                        <?php for ($i=1;$i<count($kat);$i++) echo "<option value=\"$i\">{$kat[$i]}</option>"; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;">Zugriff (0–19)</label>
                    <input type="number" name="zugriff" value="0" min="0" max="19">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;">Kurz-URL</label>
                    <input type="text" name="kurzurl" placeholder="Alias/Shortlink">
                </div>
                <div style="grid-column:span 2;">
                    <label style="font-size:12px;font-weight:600;">Beschreibung</label>
                    <input type="text" name="beschreibung" placeholder="Worum geht es?">
                </div>
                <div style="grid-column:span 2;">
                    <label style="font-size:12px;font-weight:600;">Stichworte</label>
                    <input type="text" name="stichworte">
                </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top:8px;">🔗 Link speichern</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── Filter-Toolbar ── -->
<form method="GET" action="" id="dok-filter-form" style="margin-bottom:12px;">
    <input type="hidden" name="tab" value="documents">
    <div class="dok-toolbar">
        <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Suche</label>
            <input type="text" name="stichwort" value="<?= htmlspecialchars($stichwort) ?>" placeholder="Stichwort …" style="width:180px;">
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Sortierung</label>
            <select name="sort">
                <option value="0" <?= $sort===0?'selected':'' ?>>Nach Kategorie</option>
                <option value="1" <?= $sort===1?'selected':'' ?>>Aktuellste zuerst</option>
                <option value="2" <?= $sort===2?'selected':'' ?>>Alphabetisch</option>
                <?php if ($user_is_admin): ?>
                <option value="4" <?= $sort===4?'selected':'' ?>>Nach Zugriffsziffer</option>
                <?php endif; ?>
            </select>
        </div>
        <?php if ($user_aktiv >= 2 || $user_is_admin): ?>
        <div style="align-self:flex-end;">
            <label style="font-size:12px;cursor:pointer;">
                <input type="checkbox" name="nuroeff" value="1" <?= $nuroeff?'checked':'' ?> onchange="this.form.submit()">
                Interne Dokumente zeigen
            </label>
        </div>
        <div style="align-self:flex-end;">
            <label style="font-size:12px;cursor:pointer;">
                <input type="checkbox" name="vorver" value="1" <?= $vorver?'checked':'' ?> onchange="this.form.submit()">
                Vorversionen zeigen
            </label>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn-primary" style="padding:7px 18px;align-self:flex-end;">🔍 Filtern</button>
        <a href="?tab=documents" class="btn-primary" style="padding:7px 14px;align-self:flex-end;background:#999;display:inline-block;border-radius:4px;color:#fff;text-decoration:none;font-size:13px;">↺ Reset</a>
    </div>

    <!-- Kategorie-Pills -->
    <div class="dok-kat-pills">
        <span style="font-size:12px;font-weight:600;align-self:center;margin-right:4px;">Kategorien:</span>
        <?php for ($i = 1; $i < count($kat); $i++): ?>
        <label class="dok-kat-pill <?= $kauswahl[$i] ? 'on' : 'off' ?>" id="pill-<?= $i ?>">
            <input type="checkbox" name="kaus<?= $i ?>" value="1" <?= $kauswahl[$i]?'checked':'' ?>
                   onchange="updatePill(this,<?= $i ?>)">
            <?= htmlspecialchars($kat[$i]) ?>
        </label>
        <?php endfor; ?>
        <a href="?tab=documents&kalle=1<?= $sort?"&sort=$sort":'' ?>" style="font-size:12px;align-self:center;color:#667eea;text-decoration:none;">alle</a>
    </div>
</form>

<!-- ── Dokumentenliste ── -->
<?php if (empty($docs)): ?>
<div class="info-box">Keine Dokumente für die gewählten Filter gefunden.</div>
<?php else: ?>
<table class="dok-table">
    <thead>
        <tr>
            <th style="width:35%;">Dokument</th>
            <th style="width:35%;">Beschreibung</th>
            <th style="width:10%;">Typ / Größe</th>
            <th style="width:10%;">Datum</th>
            <?php if ($user_is_admin): ?><th style="width:10%;">Admin</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php
    $last_kat = -1;
    foreach ($docs as $doc):
        $is_link   = dok_is_link($doc);
        $is_hidden = substr($doc['name'], 0, 3) === 'xxx';
        $is_old    = substr($doc['name'], 0, 3) === 'vvv';
        $href      = dok_href($doc);
        $ext       = $is_link ? 'URL' : dok_ext($doc['name']);
        $size_str  = dok_size((int)$doc['groesse']);

        // Kategorietrennzeile
        if ($sort === 0 && $doc['k1'] !== $last_kat):
            $last_kat = $doc['k1'];
    ?>
    <tr><td colspan="<?= $user_is_admin ? 5 : 4 ?>" class="dok-cat-head">
        📂 <?= htmlspecialchars($kat[$doc['k1']] ?? 'Sonstige') ?>
    </td></tr>
    <?php endif; ?>
    <tr <?= $is_hidden ? 'style="opacity:.5;"' : '' ?>>
        <td>
            <a href="<?= $href ?>" target="doku" class="dok-title-link" rel="noopener">
                <?= htmlspecialchars($doc['titel'] ?: $doc['name']) ?>
            </a>
            <?php if ($doc['version']): ?>
                <span class="dok-badge-ext"><?= htmlspecialchars($doc['version']) ?></span>
            <?php endif; ?>
            <?php if ($is_hidden): ?><span class="dok-badge-hidden">verborgen</span><?php endif; ?>
            <?php if ($is_old):    ?><span class="dok-badge-old">Vorversion</span><?php endif; ?>
            <?php if ($doc['zugriff'] > 1 && !$user_is_admin): ?>
                <span class="dok-badge-intern">intern</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($doc['beschreibung']): ?>
            <details>
                <summary class="dok-desc" style="cursor:pointer;"><?= htmlspecialchars(mb_strimwidth($doc['beschreibung'], 0, 100, '…')) ?></summary>
                <p style="font-size:12px;margin:4px 0;"><?= nl2br(htmlspecialchars($doc['beschreibung'])) ?></p>
                <?php if ($doc['stichworte']): ?>
                    <small style="color:#999;">🏷 <?= htmlspecialchars($doc['stichworte']) ?></small>
                <?php endif; ?>
                <?php if ($doc['verz'] && !$is_link): ?>
                    <br><small><embed src="<?= htmlspecialchars($doc['verz'].$doc['name']) ?>" width="100%" height="180px" type="application/pdf"></small>
                <?php endif; ?>
            </details>
            <?php else: ?>
                <span class="dok-desc">–</span>
            <?php endif; ?>
        </td>
        <td>
            <span class="dok-badge-ext"><?= $ext ?></span>
            <?php if ($size_str): ?><br><small style="color:#999;"><?= $size_str ?></small><?php endif; ?>
        </td>
        <td style="font-size:12px;color:#666;">
            <?= $doc['datum'] ? date('d.m.Y', strtotime($doc['datum'])) : '–' ?>
        </td>
        <?php if ($user_is_admin): ?>
        <td>
            <button class="dok-act-btn" onclick="toggleDokEdit(<?= $doc['id'] ?>)" style="background:#667eea;color:#fff;">✏️ Edit</button>
        </td>
        <?php endif; ?>
    </tr>
    <?php if ($user_is_admin): ?>
    <tr id="dok-edit-<?= $doc['id'] ?>" class="dok-edit-row" style="display:none;">
        <td colspan="5">
            <form method="POST">
                <input type="hidden" name="dok_action" value="save_meta">
                <input type="hidden" name="dok_id" value="<?= $doc['id'] ?>">
                <div class="dok-edit-form">
                    <div>
                        <label>Titel</label>
                        <input type="text" name="titel" value="<?= htmlspecialchars($doc['titel']) ?>">
                    </div>
                    <div>
                        <label>Kurz-URL</label>
                        <input type="text" name="kurzurl" value="<?= htmlspecialchars($doc['kurzurl']) ?>">
                    </div>
                    <div>
                        <label>Version</label>
                        <input type="text" name="version" value="<?= htmlspecialchars($doc['version']) ?>">
                    </div>
                    <div>
                        <label>Datum</label>
                        <input type="date" name="datum" value="<?= htmlspecialchars($doc['datum']) ?>">
                    </div>
                    <div>
                        <label>Zugriff (0–19)</label>
                        <input type="number" name="zugriff" value="<?= (int)$doc['zugriff'] ?>" min="0" max="99">
                    </div>
                    <div>
                        <label>Kategorie</label>
                        <select name="k1">
                            <?php for ($i=0;$i<count($kat);$i++) echo "<option value=\"$i\"".($doc['k1']==$i?' selected':'').">".htmlspecialchars($kat[$i]?:'–keine–')."</option>"; ?>
                        </select>
                    </div>
                    <div style="grid-column:span 2;">
                        <label>Beschreibung</label>
                        <textarea name="beschreibung" rows="2"><?= htmlspecialchars($doc['beschreibung']) ?></textarea>
                    </div>
                    <div style="grid-column:span 2;">
                        <label>Stichworte</label>
                        <input type="text" name="stichworte" value="<?= htmlspecialchars($doc['stichworte']) ?>">
                    </div>
                    <div style="grid-column:span 2;">
                        <label>Admin-Bemerkung</label>
                        <input type="text" name="adminbem" value="<?= htmlspecialchars($doc['adminbem']) ?>">
                    </div>
                </div>
                <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" class="dok-act-btn dok-act-save">💾 Speichern</button>
                </div>
            </form>
            <!-- Separate Aktionsformulare (Statusänderungen) -->
            <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
                <?php if (!$is_hidden): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Eintrag verbergen?');">
                    <input type="hidden" name="dok_action" value="hide">
                    <input type="hidden" name="dok_id" value="<?= $doc['id'] ?>">
                    <button type="submit" class="dok-act-btn dok-act-hide">🙈 Verbergen</button>
                </form>
                <?php endif; ?>
                <?php if (!$is_old && !$is_hidden): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="dok_action" value="mark_old">
                    <input type="hidden" name="dok_id" value="<?= $doc['id'] ?>">
                    <button type="submit" class="dok-act-btn dok-act-old">🗂 Als überholt</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Eintrag endgültig löschen?');">
                    <input type="hidden" name="dok_action" value="delete">
                    <input type="hidden" name="dok_id" value="<?= $doc['id'] ?>">
                    <button type="submit" class="dok-act-btn dok-act-delete">🗑 Löschen</button>
                </form>
                <small style="color:#999;font-size:11px;align-self:center;">
                    <?= htmlspecialchars($doc['verz'] . $doc['name']) ?>
                    <?php if ($user_is_admin && $doc['adminbem']): ?>
                        | <em><?= htmlspecialchars($doc['adminbem']) ?></em>
                    <?php endif; ?>
                </small>
            </div>
        </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- ── Sammler (Link-Kollektionen) ── -->
<div class="dok-section-head">🗂 Sammler – externe Dokumentenkollektionen</div>

<?php if (empty($sammler) && !$user_is_admin): ?>
<p style="color:#999;font-size:13px;">Noch keine Link-Kollektionen gepflegt.</p>
<?php else: ?>
<table class="sammler-table">
    <thead><tr>
        <th style="width:25%;">Titel</th>
        <th>Beschreibung / Link</th>
        <?php if ($user_is_admin): ?><th style="width:15%;">Admin</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($sammler as $sl): ?>
    <tr>
        <td><strong><?= htmlspecialchars($sl['titel']) ?></strong></td>
        <td>
            <?= htmlspecialchars($sl['beschreibung']) ?>
            <?php if ($sl['url']): ?>
            <br><a href="<?= htmlspecialchars($sl['url']) ?>" target="docs" rel="noopener" style="color:#667eea;font-size:12px;"><?= htmlspecialchars($sl['url']) ?></a>
            <?php endif; ?>
        </td>
        <?php if ($user_is_admin): ?>
        <td>
            <button class="dok-act-btn" onclick="toggleSammlerEdit(<?= $sl['id'] ?>)" style="background:#667eea;color:#fff;">✏️</button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Sammler löschen?');">
                <input type="hidden" name="dok_action" value="delete_sammler">
                <input type="hidden" name="sid" value="<?= $sl['id'] ?>">
                <button type="submit" class="dok-act-btn dok-act-delete">🗑</button>
            </form>
        </td>
        <?php endif; ?>
    </tr>
    <?php if ($user_is_admin): ?>
    <tr id="sammler-edit-<?= $sl['id'] ?>" style="display:none;background:#fffde7;">
        <td colspan="3">
            <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                <input type="hidden" name="dok_action" value="save_sammler">
                <input type="hidden" name="sid" value="<?= $sl['id'] ?>">
                <div><label style="font-size:11px;font-weight:600;">Titel</label>
                    <input type="text" name="s_titel" value="<?= htmlspecialchars($sl['titel']) ?>" style="padding:5px;border:1px solid #ccc;border-radius:4px;"></div>
                <div style="flex:1;min-width:200px;"><label style="font-size:11px;font-weight:600;">Beschreibung</label>
                    <input type="text" name="s_beschreibung" value="<?= htmlspecialchars($sl['beschreibung']) ?>" style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;"></div>
                <div style="flex:1;min-width:200px;"><label style="font-size:11px;font-weight:600;">URL</label>
                    <input type="text" name="s_url" value="<?= htmlspecialchars($sl['url']) ?>" style="width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;"></div>
                <button type="submit" class="dok-act-btn dok-act-save">💾</button>
            </form>
        </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Admin: Neuer Sammler -->
<?php if ($user_is_admin): ?>
<button class="accordion-button" onclick="toggleAccordion(this)" style="margin-top:14px;">
    ➕ Neue Link-Kollektion hinzufügen
</button>
<div class="accordion-content" style="padding:14px;border:1px solid #ddd;border-radius:0 0 6px 6px;margin-bottom:18px;">
    <?php if (!$has_sammler_tbl): ?>
    <p style="color:#c00;">Tabelle <code>doksammler</code> fehlt. <a href="tools/install_dokumente.php">→ Anlegen</a></p>
    <?php else: ?>
    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="dok_action" value="save_sammler">
        <input type="hidden" name="sid" value="0">
        <div><label style="font-size:12px;font-weight:600;display:block;">Titel *</label>
            <input type="text" name="s_titel" required style="padding:7px;border:1px solid #ccc;border-radius:4px;"></div>
        <div style="flex:1;min-width:220px;"><label style="font-size:12px;font-weight:600;display:block;">Beschreibung</label>
            <input type="text" name="s_beschreibung" style="width:100%;padding:7px;border:1px solid #ccc;border-radius:4px;"></div>
        <div style="flex:1;min-width:220px;"><label style="font-size:12px;font-weight:600;display:block;">URL *</label>
            <input type="text" name="s_url" required placeholder="https://…" style="width:100%;padding:7px;border:1px solid #ccc;border-radius:4px;"></div>
        <button type="submit" class="btn-primary">Speichern</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Admin: Upload-Verzeichnis konfigurieren -->
<?php if ($user_is_admin): ?>
<button class="accordion-button" onclick="toggleAccordion(this)" style="margin-top:8px;background:#555;">
    ⚙️ Verzeichnis &amp; URL konfigurieren
</button>
<div class="accordion-content" style="padding:14px;border:1px solid #ddd;border-radius:0 0 6px 6px;margin-bottom:18px;color:inherit;">
    <form method="POST" action="?tab=admin_init" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:780px;">
        <input type="hidden" name="save_notifications" value="1">
        <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Server-Pfad (Upload-Verzeichnis)</label>
            <input type="text" name="config[dokumente_upload_dir]" value="<?= htmlspecialchars($upload_dir) ?>"
                   placeholder="../docs/" style="width:100%;padding:7px;border:1px solid #ccc;border-radius:4px;font-family:monospace;box-sizing:border-box;color:inherit;background:inherit;">
            <small style="color:#888;">Wo Dateien gespeichert werden (relativer oder absoluter Serverpfad).<br>Beispiel: <code>../docs/</code></small>
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Basis-URL (Browser-Zugriff)</label>
            <input type="text" name="config[dokumente_base_url]" value="<?= htmlspecialchars($dok_base_url) ?>"
                   placeholder="https://aktive.mensa.de/docs/" style="width:100%;padding:7px;border:1px solid #ccc;border-radius:4px;font-family:monospace;box-sizing:border-box;color:inherit;background:inherit;">
            <small style="color:#888;">URL, unter der die Dateien im Browser erreichbar sind.<br>Beispiel: <code>https://aktive.mensa.de/docs/</code></small>
        </div>
        <div style="grid-column:span 2;">
            <button type="submit" class="btn-primary">Speichern</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
function toggleDokEdit(id) {
    const row = document.getElementById('dok-edit-' + id);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}
function toggleSammlerEdit(id) {
    const row = document.getElementById('sammler-edit-' + id);
    if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}
function updatePill(cb, i) {
    const pill = document.getElementById('pill-' + i);
    if (pill) pill.className = 'dok-kat-pill ' + (cb.checked ? 'on' : 'off');
}
</script>
