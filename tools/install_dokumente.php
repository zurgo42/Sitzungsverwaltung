<?php
/**
 * install_dokumente.php – Tabellen für die Dokumentensammlung anlegen
 *
 * Legt an:
 *   dokumente   – Haupttabelle (kompatibel mit altem MTool-Schema)
 *   doksammler  – Externe Link-Kollektionen
 *
 * Nur für Admins (aktiv >= 18).
 * Kann mehrfach aufgerufen werden (IF NOT EXISTS).
 */

require_once __DIR__ . '/../session_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('SV_CONFIG_LOADED')) require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config_adapter.php';
require_once __DIR__ . '/../member_functions.php';
require_once __DIR__ . '/../functions.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: ../login.php?redirect=' . urlencode('tools/install_dokumente.php'));
    exit;
}
$cu = get_member_by_id($pdo, $_SESSION['member_id']);
if (!is_admin_user($cu) && (int)($cu['aktiv'] ?? 0) < 18) {
    http_response_code(403);
    die('<p style="font-family:sans-serif;padding:2em;color:#c00">Nur für Admins (aktiv ≥ 18).</p>');
}

$done = [];
$err  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'JA') {
    // 1. dokumente-Tabelle
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `dokumente` (
            `id`          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name`        VARCHAR(255) NOT NULL DEFAULT '',
            `verz`        VARCHAR(255) NOT NULL DEFAULT '',
            `datum`       DATE             NULL,
            `groesse`     INT          NOT NULL DEFAULT 0,
            `kurzurl`     VARCHAR(255) NOT NULL DEFAULT '',
            `titel`       VARCHAR(255) NOT NULL DEFAULT '',
            `version`     VARCHAR(50)  NOT NULL DEFAULT '',
            `beschreibung` TEXT             NULL,
            `stichworte`  TEXT             NULL,
            `adminbem`    VARCHAR(255) NOT NULL DEFAULT '',
            `zugriff`     TINYINT      NOT NULL DEFAULT 0,
            `k1`          TINYINT      NOT NULL DEFAULT 0,
            INDEX idx_k1 (k1),
            INDEX idx_zugriff (zugriff)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done[] = '✓ Tabelle <code>dokumente</code> angelegt (oder bereits vorhanden)';
    } catch (PDOException $e) {
        $err[] = '✗ dokumente: ' . htmlspecialchars($e->getMessage());
    }

    // 2. doksammler-Tabelle
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `doksammler` (
            `id`          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `titel`       VARCHAR(255) NOT NULL DEFAULT '',
            `beschreibung` TEXT             NULL,
            `url`         VARCHAR(512) NOT NULL DEFAULT '',
            `sort_nr`     INT          NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done[] = '✓ Tabelle <code>doksammler</code> angelegt (oder bereits vorhanden)';
    } catch (PDOException $e) {
        $err[] = '✗ doksammler: ' . htmlspecialchars($e->getMessage());
    }

    // 3. svconfig: dokumente_upload_dir Default setzen
    try {
        $pdo->prepare("INSERT INTO svconfig (config_key, config_value, config_type, description, category)
            VALUES ('dokumente_upload_dir', '../docs/', 'text', 'Upload-Verzeichnis für Dokumentensammlung', 'system')
            ON DUPLICATE KEY UPDATE config_key = config_key")
            ->execute();
        $done[] = '✓ <code>svconfig.dokumente_upload_dir</code> gesetzt (Default: <code>../docs/</code>)';
    } catch (PDOException $e) {
        $err[] = '✗ svconfig: ' . htmlspecialchars($e->getMessage());
    }
}

// Aktuellen Status prüfen
$dok_exists  = (bool)$pdo->query("SHOW TABLES LIKE 'dokumente'")->rowCount();
$samm_exists = (bool)$pdo->query("SHOW TABLES LIKE 'doksammler'")->rowCount();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Dokumentensammlung – Tabellen anlegen</title>
<style>
body { font-family:system-ui,sans-serif; font-size:14px; padding:24px; max-width:640px; color:#333; }
h1   { font-size:20px; margin-bottom:6px; }
h2   { font-size:15px; margin:20px 0 8px; }
.ok  { color:#2e7d32; } .err-c { color:#c62828; }
code { background:#f0f0f0; padding:1px 4px; border-radius:3px; font-size:12px; }
ul   { padding-left:20px; } li { margin-bottom:3px; }
.warn { background:#fff8e1; border:1px solid #ffc107; padding:12px; border-radius:6px; margin:16px 0; }
.form-row { display:flex; gap:10px; align-items:center; margin-top:16px; }
input[type=text] { padding:6px 10px; border:1px solid #ccc; border-radius:5px; font-size:14px; }
button { padding:8px 20px; background:#2e7d32; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:14px; font-weight:600; }
button:hover { background:#1b5e20; }
.tag { display:inline-block; padding:2px 6px; border-radius:3px; font-size:11px; }
.tag-ok  { background:#e8f5e9; color:#2e7d32; }
.tag-miss { background:#ffeaea; color:#c62828; }
a { color:#1565c0; }
</style>
</head>
<body>
<h1>🗄 Dokumentensammlung – Tabellen anlegen</h1>
<p><a href="../?tab=documents">← Zum Dokumenten-Tab</a></p>

<?php if ($done || $err): ?>
<h2>Ergebnis</h2>
<ul>
    <?php foreach ($done as $m): ?><li class="ok"><?= $m ?></li><?php endforeach; ?>
    <?php foreach ($err  as $m): ?><li class="err-c"><?= $m ?></li><?php endforeach; ?>
</ul>
<?php if (!$err): ?>
<p class="ok" style="margin-top:12px">✓ Installation abgeschlossen. <a href="../?tab=documents">Zum Dokumenten-Tab</a></p>
<?php endif; ?>
<?php endif; ?>

<h2>Tabellenstatus</h2>
<ul>
    <li><span class="tag <?= $dok_exists  ? 'tag-ok' : 'tag-miss' ?>"><?= $dok_exists  ? '✓ vorhanden' : '✗ fehlt' ?></span> <code>dokumente</code></li>
    <li><span class="tag <?= $samm_exists ? 'tag-ok' : 'tag-miss' ?>"><?= $samm_exists ? '✓ vorhanden' : '✗ fehlt' ?></span> <code>doksammler</code></li>
</ul>

<?php if (!$dok_exists || !$samm_exists): ?>
<div class="warn">
    ⚠️ Fehlende Tabellen werden neu angelegt. Vorhandene Daten werden <strong>nicht</strong> verändert (<code>IF NOT EXISTS</code>).
</div>
<form method="post">
    <div class="form-row">
        <label>Zur Bestätigung „<strong>JA</strong>" eingeben:</label>
        <input type="text" name="confirm" placeholder="JA" autocomplete="off">
        <button type="submit">Tabellen anlegen</button>
    </div>
</form>
<?php else: ?>
<p class="ok">✓ Alle Tabellen sind vorhanden – keine Aktion nötig.</p>
<?php endif; ?>
</body>
</html>
