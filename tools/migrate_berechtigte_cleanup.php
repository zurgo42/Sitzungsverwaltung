<?php
/**
 * migrate_berechtigte_cleanup.php
 *
 * Bereinigt die berechtigte-Tabelle:
 *   LÖSCHT:  verfuegt, sanzeigen, ressort, rzeigen, Z01–Z30 (ohne Z13/Z20)
 *   ERGÄNZT: sv_admin, sv_confidential
 *
 * Nur für Admins mit aktiv >= 18.
 * Einmalig ausführen – danach kann diese Datei gelöscht oder gesperrt werden.
 */

require_once __DIR__ . '/../session_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('SV_CONFIG_LOADED')) require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config_adapter.php';
require_once __DIR__ . '/../member_functions.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: ../login.php?redirect=' . urlencode('tools/migrate_berechtigte_cleanup.php'));
    exit;
}
$cu = get_member_by_id($pdo, $_SESSION['member_id']);
if ((int)($cu['aktiv'] ?? 0) < 18 && empty($cu['is_admin'])) {
    http_response_code(403);
    die('<p style="font-family:sans-serif;padding:2em;color:#c00">Nur für GF / Vorstand (aktiv ≥ 18).</p>');
}

// Zu löschende Spalten
$drop_cols = [
    'verfuegt', 'sanzeigen', 'ressort', 'rzeigen',
    'Z01','Z02','Z03','Z04','Z05','Z06','Z07','Z08','Z09','Z10',
    'Z11','Z12','Z14','Z15','Z16','Z17','Z18','Z19',
    'Z21','Z22','Z23','Z24','Z25','Z26','Z27','Z28','Z29','Z30',
];

// Zu ergänzende Spalten [name => SQL-Definition]
$add_cols = [
    'sv_admin'        => 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Admin-Zugang im Sitzungstool\'',
    'sv_confidential' => 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Zugriff auf vertrauliche TOPs/Anträge\'',
];

function col_exists($pdo, $table, $col) {
    return (bool)$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
}

$done = $skip = $err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'JA') {

    // Spalten löschen
    foreach ($drop_cols as $col) {
        if (!col_exists($pdo, 'berechtigte', $col)) {
            $skip[] = "DROP $col (existiert nicht)";
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE berechtigte DROP COLUMN `$col`");
            $done[] = "✓ Spalte <code>$col</code> gelöscht";
        } catch (PDOException $e) {
            $err[] = "✗ DROP $col: " . htmlspecialchars($e->getMessage());
        }
    }

    // Spalten ergänzen
    foreach ($add_cols as $col => $def) {
        if (col_exists($pdo, 'berechtigte', $col)) {
            $skip[] = "ADD $col (existiert bereits)";
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE berechtigte ADD COLUMN `$col` $def");
            $done[] = "✓ Spalte <code>$col</code> ergänzt";
        } catch (PDOException $e) {
            $err[] = "✗ ADD $col: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Aktuellen Stand prüfen
$existing = [];
$cols_stmt = $pdo->query("SHOW COLUMNS FROM berechtigte");
foreach ($cols_stmt->fetchAll() as $c) $existing[] = $c['Field'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Berechtigte-Migration</title>
<style>
body { font-family: system-ui, sans-serif; font-size: 14px; padding: 24px; max-width: 700px; color: #333; }
h1 { font-size: 20px; margin-bottom: 6px; }
h2 { font-size: 15px; margin: 20px 0 8px; }
.ok  { color: #2e7d32; } .err-c { color: #c62828; } .skip { color: #666; }
code { background: #f0f0f0; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
ul { padding-left: 20px; }
li { margin-bottom: 3px; }
.warn { background: #fff8e1; border: 1px solid #ffc107; padding: 12px; border-radius: 6px; margin: 16px 0; }
.form-row { display: flex; gap: 10px; align-items: center; margin-top: 16px; }
input[type=text] { padding: 6px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
button { padding: 8px 20px; background: #c62828; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; }
button:hover { background: #b71c1c; }
.tag { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
.tag-del { background: #ffeaea; color: #c62828; }
.tag-add { background: #e8f5e9; color: #2e7d32; }
.tag-ok  { background: #e8f5e9; color: #2e7d32; }
.tag-miss { background: #ffeaea; color: #c62828; }
a { color: #1565c0; }
</style>
</head>
<body>

<h1>🔧 Berechtigte-Tabellen-Migration</h1>
<p><a href="../berechtigte_editor.php">← Zum Editor</a></p>

<?php if ($done || $err): ?>
    <h2>Ergebnis</h2>
    <ul>
        <?php foreach ($done as $m): ?><li class="ok"><?= $m ?></li><?php endforeach; ?>
        <?php foreach ($err  as $m): ?><li class="err-c"><?= $m ?></li><?php endforeach; ?>
        <?php foreach ($skip as $m): ?><li class="skip"><?= $m ?></li><?php endforeach; ?>
    </ul>
    <?php if (!$err): ?>
        <p class="ok" style="margin-top:12px">✓ Migration abgeschlossen. <a href="../berechtigte_editor.php">Zum Editor</a></p>
    <?php endif; ?>
<?php endif; ?>

<h2>Was wird geändert?</h2>

<p><strong>Zu löschende Spalten (<?= count($drop_cols) ?>):</strong></p>
<p style="line-height:2">
<?php foreach ($drop_cols as $col):
    $ex = in_array($col, $existing);
?>
    <span class="tag <?= $ex ? 'tag-del' : 'tag-ok' ?>"><?= $col ?> <?= $ex ? '✗ löschen' : '✓ fehlt bereits' ?></span>
<?php endforeach; ?>
</p>

<p style="margin-top:12px"><strong>Zu ergänzende Spalten:</strong></p>
<ul>
<?php foreach ($add_cols as $col => $def):
    $ex = in_array($col, $existing);
?>
    <li><span class="tag <?= $ex ? 'tag-ok' : 'tag-add' ?>"><?= $col ?></span>
        – <?= $def ?> <?= $ex ? '<em>(bereits vorhanden)</em>' : '' ?>
    </li>
<?php endforeach; ?>
</ul>

<?php
$to_drop = array_filter($drop_cols, fn($c) => in_array($c, $existing));
$to_add  = array_filter(array_keys($add_cols), fn($c) => !in_array($c, $existing));
if (!$to_drop && !$to_add):
?>
    <p class="ok" style="margin-top:16px">✓ Alle Spalten sind bereits im Zielzustand – keine Aktion nötig.</p>
<?php else: ?>

<div class="warn">
    ⚠️ <strong>Achtung:</strong> Das Löschen von Datenbankspalten kann nicht rückgängig gemacht werden.
    Stelle sicher, dass kein anderes System diese Spalten noch schreibt, bevor du fortfährst.
    <br>Ein Datenbank-Backup wird empfohlen.
</div>

<form method="post">
    <div class="form-row">
        <label>Zur Bestätigung „<strong>JA</strong>" eingeben:</label>
        <input type="text" name="confirm" placeholder="JA" autocomplete="off">
        <button type="submit">Migration ausführen</button>
    </div>
</form>

<?php endif; ?>

</body>
</html>
