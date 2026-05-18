<?php
/**
 * tab_mv_beschluesse.php - MV-Beschlussdatenbank
 * Integriert in index.php für konsistente Darstellung
 *
 * Basiert auf ilmvbeschluesse_neu.php 3.9.4
 * Alle Funktionen beibehalten, Layout und Rechte an System angepasst
 */

// Berechtigungen prüfen
$user_berecht_stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
$user_berecht_stmt->execute([$current_user['member_id']]);
$user_berecht = $user_berecht_stmt->fetch();
$user_aktiv = $user_berecht['aktiv'] ?? 0;

// Admin-Check: Wie im Original-Skript
$isAdmin = ($current_user['is_admin'] ?? 0) == 1 || $user_aktiv >= 19;

// Basis-URL für interne Verlinkung
$baseUrl = "index.php?tab=mv_beschluesse";

// --- PARAMETER-MANAGEMENT (GET/POST) ---
$stichwort = $_REQUEST['stichwort'] ?? '';
$gueltig = $_REQUEST['gueltig'] ?? 1;
$view_type = $_REQUEST['view_type'] ?? 'full';
$order_mode = $_REQUEST['order_mode'] ?? 'new';
$filterQuery = "&stichwort=" . urlencode($stichwort) . "&gueltig=$gueltig&view_type=$view_type&order_mode=$order_mode";

// Basis-URL für absolute Verlinkung (Direktlinks zum Kopieren)
$pureBaseUrl = "https://" . $_SERVER['HTTP_HOST'] . "/Sitzungsverwaltung/index.php?tab=mv_beschluesse";

// --- LÖSCH-LOGIK ---
if ($isAdmin && isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM mvbeschluesse WHERE antrnr = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: " . $baseUrl . $filterQuery);
    exit;
}

// --- SPEICHER-LOGIK ---
if ($isAdmin && isset($_POST['save_mv'])) {
    $old_antrnr = $_POST['old_antrnr'] ?? '';
    $new_antrnr = trim($_POST['antrnr_save']);

    if (!empty($old_antrnr) && $old_antrnr !== $new_antrnr) {
        $pdo->prepare("DELETE FROM mvbeschluesse WHERE antrnr = ?")->execute([$old_antrnr]);
    }

    $sql_save = "REPLACE INTO mvbeschluesse
                 SET antrnr = :antrnr, titel = :titel, text = :txt, begr = :begr,
                     ergebnis = :ergebnis, dafuer = :dafuer, dagegen = :dagegen,
                     enth = :enth, Relevant = :relevant, kategorie = :kat";

    $stmt_save = $pdo->prepare($sql_save);
    $stmt_save->execute([
        ':antrnr'   => $new_antrnr,
        ':titel'    => $_POST['titel_save'],
        ':txt'      => $_POST['text_save'],
        ':begr'     => $_POST['begr_save'],
        ':ergebnis' => $_POST['ergebnis_save'],
        ':dafuer'   => $_POST['dafuer_save'],
        ':dagegen'  => $_POST['dagegen_save'],
        ':enth'     => $_POST['enth_save'],
        ':relevant' => (int)$_POST['relevant_save'],
        ':kat'      => $_POST['kategorie_save']
    ]);

    header("Location: " . $baseUrl . $filterQuery);
    exit;
}

// --- HELPER FUNCTIONS ---
function h($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function linkify($text) {
    $urlPattern = '/(https?:\/\/[^\s<]+|www\.[^\s<]+)/i';
    return preg_replace_callback($urlPattern, function($matches) {
        $url = $matches[0];
        $url = rtrim($url, '.,;!?');
        $href = (strpos($url, 'http') === 0) ? $url : 'http://' . $url;
        return '<a href="' . h($href) . '" target="_blank" style="color:#007bff; text-decoration:underline;">' . h($url) . '</a>';
    }, $text);
}

function highlightWords2($text, $search) {
    if (!$search || trim($search) === '') return $text;
    return preg_replace('/(' . preg_quote($search, '/') . ')/iu', '<mark class="hl">$1</mark>', $text);
}

// --- DATENABFRAGE ---
$edit_mode = ($isAdmin && isset($_GET['edit'])) ? $_GET['edit'] : null;
$add_mode = ($isAdmin && isset($_GET['add']));

if ($edit_mode) {
    $stmt = $pdo->prepare("SELECT * FROM mvbeschluesse WHERE antrnr = :target");
    $stmt->execute([':target' => $edit_mode]);
} else {
    $queryParams = [':mnr' => $current_user['member_id']];
    $filter = ["1=1"];

    if ($gueltig == 1) {
        $filter[] = "Relevant >= 0";
    } elseif ($gueltig != 9) {
        $filter[] = "Relevant = :rel";
        $queryParams[':rel'] = (int)$gueltig;
    }

    if (!empty($stichwort)) {
        // Suche in Titeln/Texten ODER exakter Treffer bei Antragsnummer (für Direktlinks)
        $filter[] = "(antrnr = :stexact OR titel LIKE :st OR text LIKE :st OR begr LIKE :st OR kategorie LIKE :st)";
        $queryParams[':st'] = "%$stichwort%";
        $queryParams[':stexact'] = $stichwort;
    }

    $orderBy = ($order_mode == 'old') ? "antrnr ASC" : "antrnr DESC";
    $sql = "SELECT b.*,
            (SELECT COUNT(*) FROM mverhalten v WHERE v.antrnr = b.antrnr AND v.MNr = :mnr) as user_voted
            FROM mvbeschluesse b
            WHERE " . implode(" AND ", $filter) . "
            ORDER BY $orderBy";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
}
$results = $stmt->fetchAll();

// --- RENDERING-FUNKTION ---
function renderInhalt($res, $stichwort, $isAdmin, $baseUrl, $filterQuery, $pureBaseUrl) {
    // Erzeugt den Link für die Einzelansicht (Suche nach AntrNr + Filter auf "Alle")
    $singleLink = $pureBaseUrl . "&stichwort=" . urlencode($res['antrnr']) . "&gueltig=9";

    ob_start(); ?>
    <div class="mv-beschluss-content">
        <div class="mv-wortlaut">
            <?= nl2br(linkify(highlightWords2(h($res['text']), $stichwort))) ?>
        </div>
        <?php if (!empty($res['begr'])): ?>
            <div class="mv-begruendung">
                <strong>Begründung:</strong><br>
                <?= nl2br(linkify(highlightWords2(h($res['begr']), $stichwort))) ?>
            </div>
        <?php endif; ?>
        <div class="mv-footer-meta">
            <div class="mv-footer-left">
                <span>Ergebnis: <strong><?= h($res['ergebnis']) ?></strong>
                (<?= h($res['dafuer']) ?>/<?= h($res['dagegen']) ?>/<?= h($res['enth']) ?>)</span>
                <span class="mv-separator">|</span>
                <a href="<?= $singleLink ?>"
                   title="Rechtsklick: Link kopieren"
                   class="mv-link-copy">
                    🔗 Link zu diesem Beschluss
                </a>
            </div>
            <?php if ($isAdmin): ?>
                <a href="<?= $baseUrl . $filterQuery ?>&edit=<?= urlencode($res['antrnr']) ?>"
                   class="mv-edit-btn">
                    ✏️ Editieren
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>

<style>
    /* MV-Beschlüsse Styling - Angepasst an bestehendes System */
    .mv-filter-card {
        background: white;
        padding: 15px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .mv-filter-top {
        margin-bottom: 12px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
    }

    .mv-filter-top label {
        margin-right: 15px;
        font-size: 13px;
    }

    .mv-filter-controls {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        font-size: 13px;
    }

    .mv-filter-controls select,
    .mv-filter-controls input[type="text"] {
        padding: 6px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
    }

    .mv-filter-controls input[type="text"] {
        width: 280px;
    }

    .mv-filter-controls button {
        padding: 6px 15px;
        background: #0066cc;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
    }

    .mv-filter-controls button:hover {
        background: #0052a3;
    }

    .mv-add-btn {
        margin-left: auto;
        color: #28a745;
        font-weight: bold;
        text-decoration: none;
        border: 1px solid #28a745;
        padding: 5px 12px;
        border-radius: 4px;
        display: inline-block;
    }

    .mv-add-btn:hover {
        background: #28a745;
        color: white;
    }

    .mv-beschluss-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        margin-bottom: 15px;
        padding: 18px;
        border-left: 6px solid #2ecc71;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .mv-beschluss-card.status-wegfall {
        border-left-color: #95a5a6;
        background-color: #f9f9f9;
    }

    .mv-beschluss-card.status-nicht-beschlossen {
        border-left-color: #e74c3c;
        background-color: #fffafa;
    }

    .mv-card-header {
        font-size: 11px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 5px;
    }

    .mv-card-header strong {
        color: #0066cc;
        font-size: 12px;
    }

    .mv-card-kategorie {
        color: #666;
        font-weight: bold;
    }

    .mv-card-title {
        margin: 0 0 15px 0;
        font-size: 17px;
        font-weight: 600;
        color: #333;
    }

    .mv-wortlaut {
        font-size: 16px;
        line-height: 1.5;
        margin-bottom: 15px;
        color: #111;
    }

    .mv-begruendung {
        font-size: 12px;
        color: #555;
        border-top: 1px dotted #ccc;
        padding-top: 10px;
        margin-bottom: 12px;
        line-height: 1.4;
    }

    .mv-footer-meta {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
    }

    .mv-footer-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .mv-separator {
        color: #ccc;
    }

    .mv-link-copy {
        color: #007bff;
        text-decoration: none;
        font-size: 11px;
        font-weight: bold;
    }

    .mv-link-copy:hover {
        text-decoration: underline;
    }

    .mv-edit-btn {
        background: #f0f0f0;
        padding: 5px 12px;
        border-radius: 4px;
        color: #333;
        text-decoration: none;
        border: 1px solid #ccc;
        font-size: 12px;
    }

    .mv-edit-btn:hover {
        background: #e0e0e0;
    }

    .hl {
        background: #ffd700;
        padding: 0 2px;
    }

    /* Compact View */
    .mv-compact-summary {
        cursor: pointer;
        outline: none;
        font-size: 14px;
        padding: 5px 0;
    }

    .mv-compact-summary::-webkit-details-marker {
        display: none;
    }

    .mv-compact-details {
        padding-top: 12px;
    }

    /* Edit/Add Form */
    .mv-edit-form {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }

    .mv-form-row {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .mv-form-group {
        flex-grow: 1;
    }

    .mv-form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        font-size: 13px;
    }

    .mv-form-group input[type="text"],
    .mv-form-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }

    .mv-form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: Arial, sans-serif;
        box-sizing: border-box;
    }

    .mv-form-metadata {
        background: #eee;
        padding: 15px;
        border-radius: 4px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 15px;
    }

    .mv-form-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .mv-btn-save {
        background: #28a745;
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
    }

    .mv-btn-save:hover {
        background: #218838;
    }

    .mv-btn-delete {
        background: #dc3545;
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    .mv-btn-delete:hover {
        background: #c82333;
    }

    .mv-btn-cancel {
        color: #666;
        text-decoration: none;
        padding: 10px 20px;
    }

    .mv-btn-cancel:hover {
        text-decoration: underline;
    }

    .mv-reset-link {
        margin-top: 10px;
        font-size: 11px;
        display: inline-block;
    }

    .mv-reset-link a {
        color: #666;
        text-decoration: none;
    }

    .mv-reset-link a:hover {
        text-decoration: underline;
    }

    /* Dark Mode */
    body.dark-mode .mv-filter-card {
        background: #2d2d2d;
        border-color: #444;
    }

    body.dark-mode .mv-filter-top {
        border-color: #555;
    }

    body.dark-mode .mv-filter-top label {
        color: #e0e0e0;
    }

    body.dark-mode .mv-filter-controls {
        color: #e0e0e0;
    }

    body.dark-mode .mv-filter-controls select,
    body.dark-mode .mv-filter-controls input[type="text"] {
        background: #1a1a1a;
        color: #e0e0e0;
        border-color: #555;
    }

    body.dark-mode .mv-beschluss-card {
        background: #2d2d2d;
        border-color: #444;
    }

    body.dark-mode .mv-beschluss-card.status-wegfall {
        background-color: #252525;
    }

    body.dark-mode .mv-beschluss-card.status-nicht-beschlossen {
        background-color: #2a1f1f;
    }

    body.dark-mode .mv-card-header {
        border-color: #444;
    }

    body.dark-mode .mv-card-header strong {
        color: #64b5f6;
    }

    body.dark-mode .mv-card-kategorie {
        color: #aaa;
    }

    body.dark-mode .mv-card-title {
        color: #e0e0e0;
    }

    body.dark-mode .mv-wortlaut {
        color: #e0e0e0;
    }

    body.dark-mode .mv-begruendung {
        color: #bbb;
        border-color: #555;
    }

    body.dark-mode .mv-footer-meta {
        border-color: #444;
        color: #e0e0e0;
    }

    body.dark-mode .mv-edit-btn {
        background: #3a3a3a;
        color: #e0e0e0;
        border-color: #555;
    }

    body.dark-mode .mv-edit-btn:hover {
        background: #4a4a4a;
    }

    body.dark-mode .mv-edit-form {
        background: #2d2d2d;
        border-color: #444;
    }

    body.dark-mode .mv-form-group label {
        color: #e0e0e0;
    }

    body.dark-mode .mv-form-group input[type="text"],
    body.dark-mode .mv-form-group select,
    body.dark-mode .mv-form-group textarea {
        background: #1a1a1a;
        color: #e0e0e0;
        border-color: #555;
    }

    body.dark-mode .mv-form-metadata {
        background: #1a1a1a;
        color: #e0e0e0;
    }
</style>

<!-- BENACHRICHTIGUNGEN -->
<?php
require_once 'module_notifications.php';
render_user_notifications($pdo, $current_user['member_id']);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin: 0; color: #333;" class="mv-main-heading">📜 MV-Beschlüsse</h2>
</div>

<?php if ($add_mode || $edit_mode):
    $row = ($add_mode) ? [
        'antrnr'=>'','titel'=>'','text'=>'','begr'=>'','ergebnis'=>'',
        'dafuer'=>'','dagegen'=>'','enth'=>'','Relevant'=>1,'kategorie'=>''
    ] : ($results[0] ?? [
        'antrnr'=>'','titel'=>'','text'=>'','begr'=>'','ergebnis'=>'',
        'dafuer'=>'','dagegen'=>'','enth'=>'','Relevant'=>1,'kategorie'=>''
    ]);
?>
    <div class="mv-edit-form">
        <form method="post" action="<?= $baseUrl . $filterQuery ?>">
            <input type="hidden" name="old_antrnr" value="<?= h($row['antrnr']) ?>">

            <div class="mv-form-row">
                <div class="mv-form-group" style="flex: 0 0 120px;">
                    <label>Nummer:</label>
                    <input type="text" name="antrnr_save" value="<?= h($row['antrnr']) ?>" required>
                </div>
                <div class="mv-form-group" style="flex: 0 0 200px;">
                    <label>Kategorie:</label>
                    <input type="text" name="kategorie_save" value="<?= h($row['kategorie']) ?>">
                </div>
                <div class="mv-form-group">
                    <label>Titel:</label>
                    <input type="text" name="titel_save" value="<?= h($row['titel']) ?>">
                </div>
            </div>

            <div class="mv-form-group">
                <label>Wortlaut (16px):</label>
                <textarea name="text_save" rows="12" style="font-size:16px;"><?= h($row['text']) ?></textarea>
            </div>

            <div class="mv-form-group">
                <label>Begründung (12px):</label>
                <textarea name="begr_save" rows="5" style="font-size:12px;"><?= h($row['begr']) ?></textarea>
            </div>

            <div class="mv-form-metadata">
                <div>
                    <strong>Ergebnis:</strong><br>
                    <input type="text" name="ergebnis_save" value="<?= h($row['ergebnis']) ?>" style="width:150px;">
                </div>
                <div>
                    <strong>D/G/E:</strong><br>
                    <input type="text" name="dafuer_save" value="<?= h($row['dafuer']) ?>"
                           style="width:45px;" placeholder="Dafür"> /
                    <input type="text" name="dagegen_save" value="<?= h($row['dagegen']) ?>"
                           style="width:45px;" placeholder="Dagegen"> /
                    <input type="text" name="enth_save" value="<?= h($row['enth']) ?>"
                           style="width:45px;" placeholder="Enthalt.">
                </div>
                <div>
                    <strong>Status:</strong><br>
                    <select name="relevant_save">
                        <option value="1" <?= $row['Relevant']==1?'selected':'' ?>>Gültig</option>
                        <option value="0" <?= $row['Relevant']==0?'selected':'' ?>>Aufhebung</option>
                        <option value="-1" <?= $row['Relevant']==-1?'selected':'' ?>>Nicht beschlossen</option>
                    </select>
                </div>
            </div>

            <div class="mv-form-actions">
                <button type="submit" name="save_mv" class="mv-btn-save">💾 Speichern</button>
                <?php if ($edit_mode): ?>
                    <button type="button"
                            onclick="if(confirm('Beschluss wirklich löschen?')) window.location.href='<?= $baseUrl . $filterQuery ?>&delete=<?= urlencode($row['antrnr']) ?>';"
                            class="mv-btn-delete">
                        🗑️ Löschen
                    </button>
                <?php endif; ?>
                <a href="<?= $baseUrl . $filterQuery ?>" class="mv-btn-cancel">Abbrechen</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="mv-filter-card">
        <form method="post" action="<?= $baseUrl ?>">
            <div class="mv-filter-top">
                <label>
                    <input type="radio" name="gueltig" value="9" <?= $gueltig==9?'checked':'' ?>> Alle
                </label>
                <label>
                    <input type="radio" name="gueltig" value="1" <?= $gueltig==1?'checked':'' ?>> Gültige
                </label>
                <label>
                    <input type="radio" name="gueltig" value="0" <?= $gueltig==0?'checked':'' ?>> Aufhebung
                </label>
                <label>
                    <input type="radio" name="gueltig" value="-1" <?= $gueltig==-1?'checked':'' ?>> Nicht beschlossen
                </label>
            </div>
            <div class="mv-filter-controls">
                <span>Ansicht:</span>
                <select name="view_type">
                    <option value="full" <?= $view_type=='full'?'selected':'' ?>>Volltext</option>
                    <option value="compact" <?= $view_type=='compact'?'selected':'' ?>>Kompakt</option>
                </select>

                <span>Sortierung:</span>
                <select name="order_mode">
                    <option value="new" <?= $order_mode=='new'?'selected':'' ?>>Neueste zuerst</option>
                    <option value="old" <?= $order_mode=='old'?'selected':'' ?>>Älteste zuerst</option>
                </select>

                <input type="text" name="stichwort" value="<?= h($stichwort) ?>"
                       placeholder="Suche in Text, Titel, Kategorie...">

                <button type="submit">Filtern</button>

                <?php if ($isAdmin): ?>
                    <a href="<?= $baseUrl . $filterQuery ?>&add=1" class="mv-add-btn">+ Neu</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($stichwort) && count($results) > 0): ?>
                <div class="mv-reset-link">
                    <a href="<?= $baseUrl ?>&gueltig=1">« Alle Beschlüsse anzeigen</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div style="background: white; padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-weight: 600; color: #666;" class="mv-count">
        <?= count($results) ?> Beschlüsse gefunden
    </div>

    <?php if (empty($results)): ?>
        <div style="background: white; padding: 60px 20px; text-align: center; color: #999; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            Keine Beschlüsse gefunden.
        </div>
    <?php else: ?>
        <?php foreach ($results as $res):
            $statusClass = ($res['Relevant'] == 0) ? "status-wegfall" : (($res['Relevant'] == -1) ? "status-nicht-beschlossen" : "");
        ?>
            <div class="mv-beschluss-card <?= $statusClass ?>">
                <?php if ($view_type == 'compact'): ?>
                    <details>
                        <summary class="mv-compact-summary">
                            <strong><?= h($res['antrnr']) ?>:</strong>
                            <?= highlightWords2(h($res['titel']), $stichwort) ?>
                            <span style="float:right; color:#888; font-size:0.85em;">
                                <?= h($res['kategorie'] ?? '') ?>
                            </span>
                        </summary>
                        <div class="mv-compact-details">
                            <?= renderInhalt($res, $stichwort, $isAdmin, $baseUrl, $filterQuery, $pureBaseUrl) ?>
                        </div>
                    </details>
                <?php else: ?>
                    <div class="mv-card-header">
                        <strong><?= h($res['antrnr']) ?></strong>
                        <span class="mv-card-kategorie"><?= h($res['kategorie'] ?? '') ?></span>
                    </div>
                    <h3 class="mv-card-title">
                        <?= highlightWords2(h($res['titel']), $stichwort) ?>
                    </h3>
                    <?= renderInhalt($res, $stichwort, $isAdmin, $baseUrl, $filterQuery, $pureBaseUrl) ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<style>
    /* Zusätzliche Dark Mode Styles für Heading */
    body.dark-mode .mv-main-heading {
        color: #e0e0e0 !important;
    }
    body.dark-mode .mv-count {
        background: #2d2d2d !important;
        color: #e0e0e0 !important;
    }
</style>
