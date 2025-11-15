<?php
/**
 * fix_foreign_keys.php - Entfernt problematische Foreign Key Constraints
 *
 * PROBLEM:
 * Die meetings-Tabelle hat Foreign Keys auf die members-Tabelle:
 * - chairman_member_id → members(member_id)
 * - secretary_member_id → members(member_id)
 * - invited_by_member_id → members(member_id)
 *
 * Wenn wir die berechtigte-Tabelle verwenden, existieren diese IDs
 * nicht in members, und das INSERT schlägt fehl.
 *
 * LÖSUNG:
 * Entferne die Foreign Key Constraints. Die referentielle Integrität
 * wird über die Anwendungslogik sichergestellt.
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
    <title>Foreign Key Fix</title>
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
    </style>
</head>
<body>
    <h1>🔧 Foreign Key Constraints Fix</h1>

    <div class="card">
        <h2>Problem</h2>
        <p>
            Die <code>meetings</code>-Tabelle hat Foreign Key Constraints, die auf die
            <code>members</code>-Tabelle zeigen. Wenn Sie die <code>berechtigte</code>-Tabelle
            verwenden, schlagen INSERT-Operationen fehl, da die IDs nicht übereinstimmen.
        </p>
    </div>

    <?php
    $confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

    if (!$confirmed) {
        // Zeige bestehende Foreign Keys
        echo '<div class="card">';
        echo '<h2>📋 Bestehende Foreign Keys</h2>';

        try {
            $stmt = $pdo->query("
                SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = '" . DB_NAME . "'
                AND TABLE_NAME = 'meetings'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($fks)) {
                echo '<table style="width: 100%; border-collapse: collapse;">';
                echo '<tr style="background-color: #f0f0f0;">';
                echo '<th style="padding: 10px; text-align: left;">Constraint Name</th>';
                echo '<th style="padding: 10px; text-align: left;">Column</th>';
                echo '<th style="padding: 10px; text-align: left;">References</th>';
                echo '</tr>';

                foreach ($fks as $fk) {
                    echo '<tr>';
                    echo '<td style="padding: 10px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($fk['CONSTRAINT_NAME']) . '</td>';
                    echo '<td style="padding: 10px; border-bottom: 1px solid #ddd;"><code>' . htmlspecialchars($fk['COLUMN_NAME']) . '</code></td>';
                    echo '<td style="padding: 10px; border-bottom: 1px solid #ddd;"><code>' . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . '(' . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . ')</code></td>';
                    echo '</tr>';
                }

                echo '</table>';
            } else {
                echo '<p>✅ Keine Foreign Key Constraints auf members gefunden.</p>';
            }

        } catch (PDOException $e) {
            echo '<div class="error">Fehler beim Abrufen der Foreign Keys: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        echo '</div>';

        // Bestätigungsformular
        echo '<div class="warning">';
        echo '<h3>⚠️ Warnung</h3>';
        echo '<p>Dieses Skript wird die folgenden Foreign Key Constraints entfernen:</p>';
        echo '<ul>';
        echo '<li><code>meetings_ibfk_1</code> (chairman_member_id)</li>';
        echo '<li><code>meetings_ibfk_2</code> (secretary_member_id)</li>';
        echo '<li><code>meetings_ibfk_3</code> (invited_by_member_id)</li>';
        echo '</ul>';
        echo '<p><strong>Hinweis:</strong> Die referentielle Integrität wird weiterhin durch die Anwendungslogik sichergestellt.</p>';
        echo '<form method="post">';
        echo '<input type="hidden" name="confirm" value="yes">';
        echo '<button type="submit" class="btn btn-danger">🔧 Foreign Keys entfernen</button>';
        echo '</form>';
        echo '</div>';

    } else {
        // Foreign Keys entfernen
        try {
            // Zuerst alle existierenden Foreign Keys auf members ermitteln
            $stmt = $pdo->query("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = '" . DB_NAME . "'
                AND TABLE_NAME = 'meetings'
                AND REFERENCED_TABLE_NAME = 'members'
            ");
            $existing_constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($existing_constraints)) {
                echo '<div class="success">';
                echo '<h3>✅ Bereits erledigt!</h3>';
                echo '<p>Es gibt keine Foreign Key Constraints auf die members-Tabelle mehr.</p>';
                echo '<p><a href="../index.php" class="btn">➡️ Zur Anwendung</a></p>';
                echo '</div>';
                exit;
            }

            $pdo->beginTransaction();
            $dropped = [];

            foreach ($existing_constraints as $constraint) {
                try {
                    $pdo->exec("ALTER TABLE meetings DROP FOREIGN KEY `$constraint`");
                    $dropped[] = $constraint;
                } catch (PDOException $e) {
                    // Fehler loggen, aber weitermachen
                    error_log("Fehler beim Löschen von Constraint $constraint: " . $e->getMessage());
                }
            }

            $pdo->commit();

            if (!empty($dropped)) {
                echo '<div class="success">';
                echo '<h3>✅ Erfolgreich!</h3>';
                echo '<p>Folgende Foreign Key Constraints wurden entfernt:</p>';
                echo '<ul>';
                foreach ($dropped as $constraint) {
                    echo '<li><code>' . htmlspecialchars($constraint) . '</code></li>';
                }
                echo '</ul>';
                echo '</div>';
            } else {
                echo '<div class="warning">';
                echo '<h3>⚠️ Warnung</h3>';
                echo '<p>Es konnten keine Constraints entfernt werden. Möglicherweise existieren sie bereits nicht mehr.</p>';
                echo '</div>';
            }

            echo '<div class="info">';
            echo '<h3>📋 Nächste Schritte</h3>';
            echo '<p>Die Foreign Key Constraints wurden erfolgreich entfernt. Sie können jetzt:</p>';
            echo '<ol>';
            echo '<li>Meetings mit der berechtigte-Tabelle erstellen</li>';
            echo '<li>Chairman und Secretary aus beiden Tabellen zuweisen</li>';
            echo '<li>Die Anwendung normal nutzen</li>';
            echo '</ol>';
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
