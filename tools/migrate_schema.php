<?php
/**
 * migrate_schema.php - Fügt fehlende Spalten zur Datenbank hinzu
 *
 * Dieses Skript aktualisiert das Datenbank-Schema, damit es mit der
 * exportierten JSON-Datei kompatibel ist.
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

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schema-Migration</title>
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
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
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
    </style>
</head>
<body>
    <h1>🔧 Schema-Migration</h1>

    <div class="card">
        <h2>Über dieses Tool</h2>
        <p>
            Dieses Skript fügt fehlende Spalten zur Datenbank hinzu, damit der Import funktioniert.
        </p>
        <p>
            <strong>Problem:</strong> Die exportierte JSON-Datei enthält Spalten, die in der Ziel-Datenbank nicht existieren.
        </p>
    </div>

    <?php
    $migrations = [];
    $errors = [];
    $success_count = 0;
    $skip_count = 0;

    // Migration 1: svmeetings.active_item_id
    try {
        $pdo->exec("ALTER TABLE svmeetings ADD COLUMN active_item_id INT DEFAULT NULL COMMENT 'Aktuell aktiver TOP während der Sitzung' AFTER secretary_name");
        $migrations[] = "✓ svmeetings.active_item_id hinzugefügt";
        $success_count++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $migrations[] = "○ svmeetings.active_item_id existiert bereits";
            $skip_count++;
        } else {
            $errors[] = "svmeetings.active_item_id: " . $e->getMessage();
        }
    }

    // Migration 2: Foreign Key für active_item_id
    try {
        // Prüfe ob Foreign Key bereits existiert
        $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                            WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = 'svmeetings'
                            AND COLUMN_NAME = 'active_item_id'
                            AND REFERENCED_TABLE_NAME IS NOT NULL");
        $exists = $stmt->fetch();

        if (!$exists) {
            $pdo->exec("ALTER TABLE svmeetings ADD FOREIGN KEY (active_item_id) REFERENCES svagenda_items(item_id) ON DELETE SET NULL");
            $migrations[] = "✓ svmeetings.active_item_id Foreign Key hinzugefügt";
            $success_count++;
        } else {
            $migrations[] = "○ svmeetings.active_item_id Foreign Key existiert bereits";
            $skip_count++;
        }
    } catch (PDOException $e) {
        $errors[] = "svmeetings.active_item_id Foreign Key: " . $e->getMessage();
    }

    // Migration 3: svopinion_answer_templates.description
    // (Prüfen ob sie fehlt, sollte aber laut grep schon da sein)

    // Migration 4: svopinion_poll_options.created_at
    try {
        $pdo->exec("ALTER TABLE svopinion_poll_options ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER option_text");
        $migrations[] = "✓ svopinion_poll_options.created_at hinzugefügt";
        $success_count++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $migrations[] = "○ svopinion_poll_options.created_at existiert bereits";
            $skip_count++;
        } else {
            $errors[] = "svopinion_poll_options.created_at: " . $e->getMessage();
        }
    }

    // Migration 5: svopinion_poll_participants.invited_at
    try {
        $pdo->exec("ALTER TABLE svopinion_poll_participants ADD COLUMN invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER member_id");
        $migrations[] = "✓ svopinion_poll_participants.invited_at hinzugefügt";
        $success_count++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $migrations[] = "○ svopinion_poll_participants.invited_at existiert bereits";
            $skip_count++;
        } else {
            $errors[] = "svopinion_poll_participants.invited_at: " . $e->getMessage();
        }
    }

    // Migration 6: svopinion_responses.updated_at
    try {
        $pdo->exec("ALTER TABLE svopinion_responses ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        $migrations[] = "✓ svopinion_responses.updated_at hinzugefügt";
        $success_count++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $migrations[] = "○ svopinion_responses.updated_at existiert bereits";
            $skip_count++;
        } else {
            $errors[] = "svopinion_responses.updated_at: " . $e->getMessage();
        }
    }

    // Migration 7: svpoll_participants.invited_at
    try {
        $pdo->exec("ALTER TABLE svpoll_participants ADD COLUMN invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER member_id");
        $migrations[] = "✓ svpoll_participants.invited_at hinzugefügt";
        $success_count++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $migrations[] = "○ svpoll_participants.invited_at existiert bereits";
            $skip_count++;
        } else {
            $errors[] = "svpoll_participants.invited_at: " . $e->getMessage();
        }
    }

    // Migration 8: svpoll_responses.participant_name
    try {
        $pdo->exec("ALTER TABLE svpoll_responses ADD COLUMN participant_name VARCHAR(255) DEFAULT NULL AFTER member_id");
        $migrations[] = "✓ svpoll_responses.participant_name hinzugefügt";
        $success_count++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $migrations[] = "○ svpoll_responses.participant_name existiert bereits";
            $skip_count++;
        } else {
            $errors[] = "svpoll_responses.participant_name: " . $e->getMessage();
        }
    }

    // Ergebnisse anzeigen
    if (!empty($migrations) || !empty($errors)) {
        echo '<div class="card">';
        echo '<h2>📊 Migrations-Ergebnis</h2>';

        if (!empty($migrations)) {
            echo '<h3 style="color: #28a745;">Durchgeführte Änderungen:</h3>';
            echo '<ul>';
            foreach ($migrations as $migration) {
                echo '<li>' . htmlspecialchars($migration) . '</li>';
            }
            echo '</ul>';
        }

        echo '<div style="margin-top: 20px;">';
        echo '<p><strong>Erfolgreich:</strong> ' . $success_count . ' Spalten hinzugefügt</p>';
        echo '<p><strong>Übersprungen:</strong> ' . $skip_count . ' Spalten existierten bereits</p>';
        if (!empty($errors)) {
            echo '<p><strong style="color: #dc3545;">Fehler:</strong> ' . count($errors) . ' Fehler</p>';
        }
        echo '</div>';

        echo '</div>';

        if (!empty($errors)) {
            echo '<div class="error">';
            echo '<h3>❌ Fehler bei der Migration</h3>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if (empty($errors)) {
            echo '<div class="success">';
            echo '<h3>✅ Schema-Migration erfolgreich!</h3>';
            echo '<p>Die Datenbank ist jetzt bereit für den Import.</p>';
            echo '<p><strong>Nächste Schritte:</strong></p>';
            echo '<ol>';
            echo '<li>Gehen Sie zu <a href="demo_import.php">demo_import.php</a></li>';
            echo '<li>Führen Sie den Import erneut aus</li>';
            echo '<li>Alle Daten sollten jetzt erfolgreich importiert werden</li>';
            echo '</ol>';
            echo '<a href="demo_import.php" class="btn">➡️ Zum Import-Tool</a>';
            echo '</div>';
        }
    }
    ?>

</body>
</html>
