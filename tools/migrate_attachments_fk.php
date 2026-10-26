<?php
/**
 * migrate_attachments_fk.php - Entfernt den Fremdschlüssel svagenda_attachments.uploaded_by_member_id -> svmembers
 *
 * Im Adapter-Betrieb (MEMBER_SOURCE = berechtigte) stammen die Mitglieds-IDs aus berechtigte, nicht aus svmembers;
 * der Fremdschlüssel verhindert dort das Hochladen von TOP-Anhängen (Fehler 1452). Ändert nur diese eine Tabelle,
 * keine Daten. Beliebig oft wiederholbar. Danach kann das Skript gelöscht werden.
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

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:800px;margin:20px auto"><h2>Anhänge: Fremdschlüssel entfernen</h2>';

$fks = $pdo->query("
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'svagenda_attachments'
      AND COLUMN_NAME = 'uploaded_by_member_id' AND REFERENCED_TABLE_NAME = 'svmembers'
")->fetchAll(PDO::FETCH_COLUMN);

if (!$fks) {
    echo '<p>Kein Fremdschlüssel vorhanden - nichts zu tun.</p>';
    exit;
}
foreach ($fks as $fk) {
    $pdo->exec("ALTER TABLE svagenda_attachments DROP FOREIGN KEY `" . str_replace('`', '', $fk) . "`");
    echo '<p style="color:green">Fremdschlüssel <b>' . htmlspecialchars($fk) . '</b> entfernt.</p>';
}
