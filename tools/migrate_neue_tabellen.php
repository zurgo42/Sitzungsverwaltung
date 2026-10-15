<?php
/**
 * migrate_neue_tabellen.php - Legt NUR die neuen Tabellen an (Audit-Log, Kalender, Abstimmungs-Erinnerung)
 *
 * Gedacht für laufende Installationen (z. B. Adapter-Version mit berechtigte/antraege), bei denen
 * tools/init-db.php NICHT ausgeführt werden soll. Das Skript
 *   - führt ausschließlich CREATE TABLE IF NOT EXISTS aus,
 *   - ändert keine bestehende Tabelle, legt keine Benutzer an und schreibt keine Konfiguration,
 *   - ist beliebig oft wiederholbar.
 * Mit ?dry=1 werden die Anweisungen nur angezeigt.
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

$statements = [
    <<<'SQL'
CREATE TABLE IF NOT EXISTS svaudit_log (
        log_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        logged_at     DATETIME(3)      NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
        member_id     INT              NULL     COMMENT 'Eingeloggtes Mitglied (NULL = Cron/extern)',
        ip_address    VARCHAR(45)      NULL,
        script        VARCHAR(200)     NULL     COMMENT 'Aufrufendes PHP-Skript',
        action        VARCHAR(10)      NOT NULL COMMENT 'INSERT | UPDATE | DELETE | REPLACE',
        table_name    VARCHAR(100)     NULL     COMMENT 'Betroffene Tabelle',
        affected_rows SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        query         TEXT             NULL     COMMENT 'SQL-Abfrage mit interpolierten Parameterwerten (max. 2000 Zeichen)',
        INDEX idx_logged_at  (logged_at),
        INDEX idx_member_id  (member_id),
        INDEX idx_table_name (table_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Generelles Audit-Log aller DB-Schreibzugriffe'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS svevents (
        event_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title           VARCHAR(200) NOT NULL,
        description     TEXT NULL,
        location        VARCHAR(200) NULL,
        start_datetime  DATETIME NOT NULL,
        end_datetime    DATETIME NULL,
        all_day         TINYINT(1) DEFAULT 0,
        event_type      ENUM('sitzung','treffen','abwesenheit','frist','sonstiges') DEFAULT 'sonstiges',
        recurrence      ENUM('none','weekly','biweekly','monthly','yearly') DEFAULT 'none',
        recurrence_end_date DATE NULL,
        target_type     ENUM('all','roles','list') DEFAULT 'all',
        target_roles    VARCHAR(200) NULL COMMENT 'Komma-getrennte Rollen, z.B. vorstand,gf',
        created_by_member_id INT NOT NULL,
        meeting_id      INT NULL COMMENT 'Verknüpfung zu svmeetings',
        status          ENUM('active','cancelled','deleted') DEFAULT 'active',
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_start (start_datetime),
        INDEX idx_type (event_type),
        INDEX idx_status (status),
        INDEX idx_created_by (created_by_member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Kalender-Ereignisse für alle Rollen'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS svevents_participants (
        event_id  INT NOT NULL,
        member_id INT NOT NULL,
        PRIMARY KEY (event_id, member_id),
        INDEX idx_event (event_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Teilnehmer-Liste für Kalender-Ereignisse mit target_type=list'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS svvote_reminders (
    antrnr VARCHAR(50) NOT NULL,
    member_id INT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (antrnr, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
];

$dry = isset($_GET['dry']);
echo '<pre>';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach ($statements as $sql) {
        preg_match('/EXISTS (\w+)/', $sql, $m);
        $table = $m[1];
        $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn() !== false;
        if ($exists) {
            echo "= {$table}: existiert bereits\n";
        } elseif ($dry) {
            echo "? {$table}: würde angelegt\n";
        } else {
            $pdo->exec($sql);
            echo "+ {$table}: angelegt\n";
        }
    }
    echo $dry ? "\nTrockenlauf - nichts geändert.\n" : "\nFertig.\n";
} catch (PDOException $e) {
    echo "FEHLER: " . htmlspecialchars($e->getMessage()) . "\n";
}
echo '</pre>';
