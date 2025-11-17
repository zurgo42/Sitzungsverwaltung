<?php
/**
 * fix_all_foreign_keys.php - Entfernt ALLE Foreign Key Constraints aus der Datenbank
 *
 * PROBLEM:
 * Foreign Key Constraints können bei der Verwendung von mehreren Datenquellen
 * (members + berechtigte) zu Problemen führen. Auch beim Löschen von Meetings
 * können FK-Constraints Fehler verursachen.
 *
 * LÖSUNG:
 * Entfernt ALLE Foreign Key Constraints aus ALLEN Tabellen der Datenbank.
 * Die referentielle Integrität wird über die Anwendungslogik sichergestellt.
 *
 * WARNUNG: Dieses Tool entfernt ALLE Foreign Keys aus der gesamten Datenbank!
 */

require_once __DIR__ . '/../config.php';

// Datenbankverbindung erstellen
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alle Foreign Keys entfernen</title>
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
        h1 {
            color: #333;
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
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            margin: 20px 0;
        }
        pre {
            background-color: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🔧 Alle Foreign Key Constraints entfernen</h1>

    <div class="card">
        <h2>Über dieses Tool</h2>
        <p>
            Dieses Skript entfernt <strong>ALLE</strong> Foreign Key Constraints aus <strong>ALLEN</strong> Tabellen
            Ihrer Datenbank <code><?php echo htmlspecialchars(DB_NAME); ?></code>.
        </p>
        <p>
            <strong>Warum ist das notwendig?</strong>
        </p>
        <ul>
            <li>Foreign Keys blockieren Operationen mit der berechtigte-Tabelle</li>
            <li>Foreign Keys können Löschen von Meetings verhindern</li>
            <li>Die referentielle Integrität wird über die Anwendungslogik sichergestellt</li>
        </ul>
    </div>

    <?php
    $confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

    if (!$confirmed) {
        // Zeige alle bestehenden Foreign Keys
        echo '<div class="card">';
        echo '<h2>📋 Alle bestehenden Foreign Keys</h2>';

        try {
            // ALLE Foreign Keys aus der Datenbank holen
            $stmt = $pdo->query("
                SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = '" . DB_NAME . "'
                AND REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY TABLE_NAME, CONSTRAINT_NAME
            ");
            $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($fks)) {
                echo '<p>Gefunden: <strong>' . count($fks) . ' Foreign Key Constraints</strong></p>';
                echo '<table>';
                echo '<tr>';
                echo '<th>Tabelle</th>';
                echo '<th>Constraint Name</th>';
                echo '<th>Spalte</th>';
                echo '<th>Referenz</th>';
                echo '</tr>';

                foreach ($fks as $fk) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($fk['TABLE_NAME']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($fk['CONSTRAINT_NAME']) . '</td>';
                    echo '<td><code>' . htmlspecialchars($fk['COLUMN_NAME']) . '</code></td>';
                    echo '<td><code>' . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . '(' . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . ')</code></td>';
                    echo '</tr>';
                }

                echo '</table>';
            } else {
                echo '<p>✅ Keine Foreign Key Constraints gefunden.</p>';
            }

        } catch (PDOException $e) {
            echo '<div class="error">Fehler beim Abrufen der Foreign Keys: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        echo '</div>';

        // Bestätigungsformular
        if (!empty($fks)) {
            echo '<div class="warning">';
            echo '<h3>⚠️ WARNUNG</h3>';
            echo '<p><strong>Dieses Skript wird ALLE Foreign Key Constraints aus der gesamten Datenbank entfernen!</strong></p>';
            echo '<p>Betroffene Tabellen:</p>';
            echo '<ul>';
            $tables = array_unique(array_column($fks, 'TABLE_NAME'));
            foreach ($tables as $table) {
                echo '<li><code>' . htmlspecialchars($table) . '</code></li>';
            }
            echo '</ul>';
            echo '<p><strong>Hinweis:</strong> Die referentielle Integrität wird weiterhin durch die Anwendungslogik sichergestellt.</p>';
            echo '<form method="post">';
            echo '<input type="hidden" name="confirm" value="yes">';
            echo '<button type="submit" class="btn btn-danger">🔧 Alle ' . count($fks) . ' Foreign Keys entfernen</button>';
            echo '</form>';
            echo '</div>';
        }

    } else {
        // Foreign Keys entfernen
        try {
            // ALLE Foreign Keys aus ALLEN Tabellen ermitteln
            $stmt = $pdo->query("
                SELECT TABLE_NAME, CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = '" . DB_NAME . "'
                AND REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY TABLE_NAME, CONSTRAINT_NAME
            ");
            $existing_constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($existing_constraints)) {
                echo '<div class="success">';
                echo '<h3>✅ Bereits erledigt!</h3>';
                echo '<p>Es gibt keine Foreign Key Constraints mehr in der Datenbank.</p>';
                echo '<p><a href="../index.php" class="btn">➡️ Zur Anwendung</a></p>';
                echo '</div>';
                exit;
            }

            $pdo->beginTransaction();
            $dropped = [];
            $errors = [];

            foreach ($existing_constraints as $fk) {
                $table = $fk['TABLE_NAME'];
                $constraint = $fk['CONSTRAINT_NAME'];

                try {
                    $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
                    $dropped[] = "$table.$constraint";
                } catch (PDOException $e) {
                    // Fehler loggen, aber weitermachen
                    $errors[] = "$table.$constraint: " . $e->getMessage();
                    error_log("Fehler beim Löschen von Constraint $table.$constraint: " . $e->getMessage());
                }
            }

            $pdo->commit();

            if (!empty($dropped)) {
                echo '<div class="success">';
                echo '<h3>✅ Erfolgreich!</h3>';
                echo '<p>Folgende <strong>' . count($dropped) . ' Foreign Key Constraints</strong> wurden entfernt:</p>';
                echo '<ul>';
                foreach ($dropped as $constraint) {
                    echo '<li><code>' . htmlspecialchars($constraint) . '</code></li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            if (!empty($errors)) {
                echo '<div class="warning">';
                echo '<h3>⚠️ Fehler bei einigen Constraints</h3>';
                echo '<p>Folgende Constraints konnten nicht entfernt werden:</p>';
                echo '<ul>';
                foreach ($errors as $error) {
                    echo '<li><code>' . htmlspecialchars($error) . '</code></li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            if (empty($dropped) && empty($errors)) {
                echo '<div class="warning">';
                echo '<h3>⚠️ Warnung</h3>';
                echo '<p>Es konnten keine Constraints entfernt werden. Möglicherweise existieren sie bereits nicht mehr.</p>';
                echo '</div>';
            }

            echo '<div class="info">';
            echo '<h3>📋 Fertig!</h3>';
            echo '<p>Die Foreign Key Constraints wurden entfernt. Die Anwendung kann jetzt:</p>';
            echo '<ul>';
            echo '<li>✅ Meetings mit berechtigte-Tabelle erstellen</li>';
            echo '<li>✅ Meetings ohne Fehler löschen</li>';
            echo '<li>✅ Alle Operationen ohne FK-Einschränkungen ausführen</li>';
            echo '</ul>';
            echo '<p><a href="../index.php" class="btn">➡️ Zur Anwendung</a></p>';
            echo '</div>';

        } catch (PDOException $e) {
            // Nur rollBack wenn Transaktion aktiv ist
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            echo '<div class="error">';
            echo '<h3>❌ Fehler</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    ?>

</body>
</html>
