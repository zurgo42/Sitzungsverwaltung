<?php
/**
 * migrate_auto_abstimmung.php - Fügt der Anträge-Tabelle die Spalte auto_abstimmung hinzu
 *
 * Für laufende Installationen (auch Adapter-Version mit Tabelle antraege), bei denen tools/init-db.php
 * NICHT ausgeführt werden soll. Das Skript führt genau eine Änderung durch:
 *   ALTER TABLE <TABLE_ANTRAEGE> ADD COLUMN auto_abstimmung TINYINT(1) NOT NULL DEFAULT 0
 * Bestehende Zeilen erhalten 0; bestehende Programme (z. B. das VTool) sind davon nicht betroffen.
 * Mit ?dry=1 wird nur angezeigt, was passieren würde. Beliebig oft wiederholbar.
 */
require_once __DIR__ . '/../config.php';
session_start();

if (isset($_POST['admin_password']) && $_POST['admin_password'] === SYSTEM_ADMIN_PASSWORD) {
    $_SESSION['system_admin_authenticated'] = true;
}
if (empty($_SESSION['system_admin_authenticated'])) {
    echo '<form method="post"><p>System-Admin-Passwort:</p><input type="password" name="admin_password"> <button>Weiter</button></form>';
    exit;
}

$dry = isset($_GET['dry']);
echo '<pre>';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $table = defined('TABLE_ANTRAEGE') ? TABLE_ANTRAEGE : 'antraege';
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'auto_abstimmung'");
    $q->execute([$table]);
    if ((int)$q->fetchColumn() > 0) {
        echo "= {$table}.auto_abstimmung: existiert bereits\n";
    } elseif ($dry) {
        echo "? {$table}.auto_abstimmung: würde angelegt (TINYINT(1) NOT NULL DEFAULT 0)\n\nTrockenlauf - nichts geändert.\n";
    } else {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN auto_abstimmung TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Nach Wartezeit automatisch zur Abstimmung stellen'");
        echo "+ {$table}.auto_abstimmung: angelegt\n\nFertig.\n";
    }
} catch (PDOException $e) {
    echo "FEHLER: " . htmlspecialchars($e->getMessage()) . "\n";
}
echo '</pre>';
