<?php
/**
 * db_backup.php - Datenbank Backup & Restore
 *
 * Ermöglicht das Sichern und Wiederherstellen der Datenbank
 * ohne IT-Kenntnisse. Geschützt durch System-Admin-Passwort.
 *
 * VERWENDUNG:
 * 1. Rufe dieses Skript im Browser auf
 * 2. Gib das System-Admin-Passwort ein
 * 3. Erstelle Backups oder stelle welche wieder her
 */

require_once __DIR__ . '/../config.php';

// PDO-Verbindung erstellen
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// ============================================
// PASSWORT-SCHUTZ
// ============================================
session_start();
$password_correct = false;

// Passwort prüfen
if (isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === SYSTEM_ADMIN_PASSWORD) {
        $_SESSION['system_admin_authenticated'] = true;
        $password_correct = true;
    } else {
        $password_error = "Falsches Passwort!";
    }
}

// Prüfen ob bereits authentifiziert
if (isset($_SESSION['system_admin_authenticated']) && $_SESSION['system_admin_authenticated'] === true) {
    $password_correct = true;
}

// Wenn nicht authentifiziert, Passwort-Formular anzeigen
if (!$password_correct) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>System-Admin Authentifizierung</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 400px; margin: 100px auto; padding: 20px; }
            .login-box { background: #f9f9f9; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h2 { margin-top: 0; color: #333; }
            input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
            button { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            button:hover { background: #45a049; }
            .error { color: #d32f2f; margin-bottom: 10px; padding: 10px; background: #ffebee; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔒 System-Admin Zugang</h2>
            <p>Für den Zugriff auf Backup/Restore-Funktionen wird das System-Admin-Passwort benötigt.</p>
            <?php if (isset($password_error)): ?>
                <div class="error"><?php echo htmlspecialchars($password_error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="admin_password" placeholder="System-Admin-Passwort" required autofocus>
                <button type="submit">Anmelden</button>
            </form>
            <p style="font-size: 12px; color: #666; margin-top: 20px;">
                Das Passwort kann in der config.php unter SYSTEM_ADMIN_PASSWORD geändert werden.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// BACKUP/RESTORE LOGIK
// ============================================

$backup_dir = __DIR__ . '/backups';
$message = '';
$message_type = '';

// Backup-Verzeichnis erstellen falls nicht vorhanden
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Aktion: Backup erstellen
if (isset($_POST['create_backup'])) {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_{$timestamp}.sql";
    $filepath = $backup_dir . '/' . $filename;

    try {
        // Alle Tabellen mit sv-Präfix holen
        $tables = [];
        $result = $pdo->query("SHOW TABLES LIKE 'sv%'");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        if (empty($tables)) {
            throw new Exception("Keine Tabellen zum Sichern gefunden!");
        }

        $sql_dump = "-- Datenbank Backup: " . DB_NAME . "\n";
        $sql_dump .= "-- Erstellt am: " . date('Y-m-d H:i:s') . "\n\n";
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // Jede Tabelle exportieren
        foreach ($tables as $table) {
            // DROP TABLE Statement
            $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n\n";

            // CREATE TABLE Statement
            $create_result = $pdo->query("SHOW CREATE TABLE `$table`");
            $create_row = $create_result->fetch();
            $sql_dump .= $create_row['Create Table'] . ";\n\n";

            // INSERT Statements
            $rows = $pdo->query("SELECT * FROM `$table`");
            $row_count = 0;

            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                if ($row_count == 0) {
                    $sql_dump .= "INSERT INTO `$table` (" . implode(', ', array_map(function($col) {
                        return "`$col`";
                    }, array_keys($row))) . ") VALUES\n";
                }

                $values = array_map(function($val) use ($pdo) {
                    return $val === null ? 'NULL' : $pdo->quote($val);
                }, array_values($row));

                $sql_dump .= "(" . implode(', ', $values) . ")";
                $row_count++;

                // Kommata zwischen Zeilen, Semikolon am Ende
                $more_rows = $rows->fetch(PDO::FETCH_ASSOC);
                if ($more_rows) {
                    $rows = $pdo->query("SELECT * FROM `$table`"); // Reset für nächsten Durchlauf
                    $sql_dump .= ",\n";
                    // Zurücksetzen für korrekte Fortsetzung
                    for ($i = 0; $i < $row_count; $i++) {
                        $rows->fetch();
                    }
                } else {
                    $sql_dump .= ";\n\n";
                }
            }
        }

        $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Backup speichern
        if (file_put_contents($filepath, $sql_dump)) {
            $filesize = filesize($filepath);
            $filesize_mb = round($filesize / 1024 / 1024, 2);
            $message = "✅ Backup erfolgreich erstellt: $filename (" . count($tables) . " Tabellen, {$filesize_mb} MB)";
            $message_type = 'success';
        } else {
            throw new Exception("Fehler beim Speichern der Backup-Datei!");
        }

    } catch (Exception $e) {
        $message = "❌ Fehler beim Erstellen des Backups: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Aktion: Backup wiederherstellen
if (isset($_POST['restore_backup']) && isset($_POST['backup_file'])) {
    $filename = basename($_POST['backup_file']);
    $filepath = $backup_dir . '/' . $filename;

    if (!file_exists($filepath)) {
        $message = "❌ Backup-Datei nicht gefunden!";
        $message_type = 'error';
    } elseif (!isset($_POST['confirm_restore'])) {
        $message = "❌ Bitte bestätige die Wiederherstellung!";
        $message_type = 'error';
    } else {
        try {
            $sql_content = file_get_contents($filepath);

            if ($sql_content === false) {
                throw new Exception("Fehler beim Lesen der Backup-Datei!");
            }

            // SQL in einzelne Statements aufteilen und ausführen
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

            // Multi-Query Execution
            $statements = explode(";\n", $sql_content);
            $executed = 0;

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && substr($statement, 0, 2) !== '--') {
                    $pdo->exec($statement);
                    $executed++;
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            $message = "✅ Backup erfolgreich wiederhergestellt: $filename ($executed Statements ausgeführt)";
            $message_type = 'success';

        } catch (Exception $e) {
            $message = "❌ Fehler beim Wiederherstellen: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Aktion: Backup löschen
if (isset($_POST['delete_backup']) && isset($_POST['backup_file'])) {
    $filename = basename($_POST['backup_file']);
    $filepath = $backup_dir . '/' . $filename;

    if (file_exists($filepath) && unlink($filepath)) {
        $message = "✅ Backup gelöscht: $filename";
        $message_type = 'success';
    } else {
        $message = "❌ Fehler beim Löschen der Backup-Datei!";
        $message_type = 'error';
    }
}

// Liste aller Backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (substr($file, -4) === '.sql') {
            $filepath = $backup_dir . '/' . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filepath),
                'date' => filemtime($filepath)
            ];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank Backup & Restore</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 { color: #333; margin-top: 0; }
        h2 { color: #555; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-primary { background: #4CAF50; color: white; }
        .btn-primary:hover { background: #45a049; }
        .btn-warning { background: #ff9800; color: white; }
        .btn-warning:hover { background: #e68900; }
        .btn-danger { background: #f44336; color: white; }
        .btn-danger:hover { background: #da190b; }
        .backup-list {
            list-style: none;
            padding: 0;
        }
        .backup-item {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            border-left: 4px solid #2196F3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .backup-info {
            flex: 1;
        }
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        .backup-actions form {
            display: inline;
        }
        label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            margin: 10px 0;
        }
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <h1>💾 Datenbank Backup & Restore</h1>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Backup erstellen -->
    <div class="card">
        <h2>Neues Backup erstellen</h2>
        <p>Erstellt eine vollständige Sicherung aller Datenbanktabellen (sv*).</p>
        <form method="POST">
            <button type="submit" name="create_backup" class="btn-primary" onclick="return confirm('Backup jetzt erstellen?')">
                💾 Backup jetzt erstellen
            </button>
        </form>
    </div>

    <!-- Vorhandene Backups -->
    <div class="card">
        <h2>Vorhandene Backups (<?php echo count($backups); ?>)</h2>

        <?php if (empty($backups)): ?>
            <p style="color: #666;">Noch keine Backups vorhanden.</p>
        <?php else: ?>
            <ul class="backup-list">
                <?php foreach ($backups as $backup): ?>
                    <li class="backup-item">
                        <div class="backup-info">
                            <strong><?php echo htmlspecialchars($backup['filename']); ?></strong><br>
                            <small style="color: #666;">
                                <?php echo date('d.m.Y H:i:s', $backup['date']); ?> •
                                <?php echo round($backup['size'] / 1024 / 1024, 2); ?> MB
                            </small>
                        </div>
                        <div class="backup-actions">
                            <!-- Wiederherstellen -->
                            <form method="POST" onsubmit="return confirm('⚠️ WARNUNG: Dies überschreibt ALLE aktuellen Daten!\n\nMöchtest du wirklich dieses Backup wiederherstellen?');">
                                <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                <label style="margin-bottom: 5px;">
                                    <input type="checkbox" name="confirm_restore" required>
                                    <small>Bestätigen</small>
                                </label>
                                <button type="submit" name="restore_backup" class="btn-warning">
                                    🔄 Wiederherstellen
                                </button>
                            </form>

                            <!-- Löschen -->
                            <form method="POST" onsubmit="return confirm('Backup wirklich löschen?');">
                                <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                <button type="submit" name="delete_backup" class="btn-danger">
                                    🗑️ Löschen
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Hinweise -->
    <div class="card">
        <h2>ℹ️ Wichtige Hinweise</h2>
        <ul>
            <li><strong>Backups werden gespeichert in:</strong> <code>tools/backups/</code></li>
            <li><strong>Vor Wiederherstellung:</strong> Immer zuerst ein neues Backup erstellen!</li>
            <li><strong>Backup-Dateien:</strong> Können auch manuell heruntergeladen werden (FTP/cPanel)</li>
            <li><strong>Regelmäßige Backups:</strong> Empfohlen vor wichtigen Änderungen</li>
            <li><strong>Passwort:</strong> Kann in <code>config.php</code> unter <code>SYSTEM_ADMIN_PASSWORD</code> geändert werden</li>
        </ul>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <a href="../index.php" style="color: #2196F3; text-decoration: none;">← Zurück zur Anwendung</a>
    </div>
</body>
</html>
