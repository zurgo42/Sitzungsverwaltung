<?php
/**
 * remove_foreign_keys.php - Entfernt alle Foreign Key Constraints
 *
 * WARUM: Nach dem Umbenennen der Tabellen (members → svmembers)
 * verweisen die Foreign Keys auf nicht-existierende Tabellen.
 *
 * VERWENDUNG: Dieses Skript im Browser aufrufen
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
$password_required = true;
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
            <p>Für das Entfernen von Foreign Keys wird das System-Admin-Passwort benötigt.</p>
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

$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foreign Keys entfernen</title>
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
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .btn:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>🔧 Foreign Key Constraints entfernen</h1>

    <?php if (!$confirmed): ?>

        <div class="warning">
            <h3>⚠️ WARNUNG</h3>
            <p><strong>Diese Aktion entfernt alle Foreign Key Constraints aus der Datenbank!</strong></p>
            <p>Foreign Keys können Probleme verursachen, wenn:</p>
            <ul>
                <li>Tabellen umbenannt wurden (z.B. members → svmembers)</li>
                <li>Demo-Daten mit anderen IDs importiert werden</li>
                <li>Referenzen auf nicht-existierende Tabellen zeigen</li>
            </ul>
        </div>

        <?php
        // Alle Foreign Keys in der Datenbank finden
        $stmt = $pdo->query("
            SELECT
                TABLE_NAME,
                CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME,
                COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, CONSTRAINT_NAME
        ");
        $foreign_keys = $stmt->fetchAll();
        ?>

        <div class="info">
            <h3>📊 Gefundene Foreign Keys: <?php echo count($foreign_keys); ?></h3>

            <?php if (empty($foreign_keys)): ?>
                <p>✓ Keine Foreign Keys gefunden! Die Datenbank ist bereits bereinigt.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Tabelle</th>
                        <th>Constraint Name</th>
                        <th>Spalte</th>
                        <th>Referenziert</th>
                    </tr>
                    <?php foreach ($foreign_keys as $fk): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fk['TABLE_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($fk['CONSTRAINT_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($fk['COLUMN_NAME']); ?></td>
                            <td><?php echo htmlspecialchars($fk['REFERENCED_TABLE_NAME']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($foreign_keys)): ?>
            <form method="POST">
                <input type="hidden" name="confirm" value="yes">
                <input type="hidden" name="admin_password" value="<?php echo htmlspecialchars(SYSTEM_ADMIN_PASSWORD); ?>">
                <button type="submit" class="btn">🗑️ Alle Foreign Keys entfernen</button>
                <a href="../index.php" class="btn btn-secondary">Abbrechen</a>
            </form>
        <?php else: ?>
            <a href="../index.php" class="btn btn-secondary">Zurück zur Anwendung</a>
        <?php endif; ?>

    <?php else: ?>

        <?php
        try {
            // Alle Foreign Keys finden
            $stmt = $pdo->query("
                SELECT
                    TABLE_NAME,
                    CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL
                GROUP BY TABLE_NAME, CONSTRAINT_NAME
            ");
            $foreign_keys = $stmt->fetchAll();

            $removed_count = 0;
            $errors = [];

            echo '<div class="card">';
            echo '<h2>🗑️ Entferne Foreign Keys...</h2>';

            foreach ($foreign_keys as $fk) {
                $table = $fk['TABLE_NAME'];
                $constraint = $fk['CONSTRAINT_NAME'];

                try {
                    $sql = "ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`";
                    $pdo->exec($sql);
                    echo "<p style='color: green;'>✓ Entfernt: $table.$constraint</p>";
                    $removed_count++;
                } catch (PDOException $e) {
                    $error_msg = "$table.$constraint: " . $e->getMessage();
                    $errors[] = $error_msg;
                    echo "<p style='color: red;'>✗ Fehler bei $table.$constraint: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }

            echo '</div>';

            if (!empty($errors)) {
                echo '<div class="error">';
                echo '<h3>⚠️ Fehler beim Entfernen (' . count($errors) . ' Fehler)</h3>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            echo '<div class="success">';
            echo '<h3>✅ Erfolgreich abgeschlossen!</h3>';
            echo '<p><strong>' . $removed_count . ' Foreign Keys</strong> wurden entfernt.</p>';
            echo '<p>Die Datenbank arbeitet nun ohne referenzielle Integrität auf DB-Ebene.</p>';
            echo '<p><strong>Hinweis:</strong> Die Anwendung stellt die Datenintegrität auf Anwendungsebene sicher.</p>';
            echo '</div>';

            echo '<div class="info">';
            echo '<h3>🎉 Fertig!</h3>';
            echo '<p><a href="../index.php" class="btn btn-secondary">➡️ Zur Anwendung</a></p>';
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Kritischer Fehler</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

    <?php endif; ?>

</div>

</body>
</html>
