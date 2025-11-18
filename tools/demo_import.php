<?php
/**
 * demo_import.php - Importiert Demo-Daten und setzt die Datenbank zurück
 *
 * WARNUNG: Dieses Skript löscht ALLE bestehenden Meeting-Daten!
 * Es sollte nur in Entwicklungs- oder Demo-Umgebungen verwendet werden.
 *
 * VERWENDUNG:
 * 1. Stellen Sie sicher, dass demo_data.json im tools/-Verzeichnis existiert
 * 2. Rufen Sie dieses Skript im Browser auf
 * 3. Bestätigen Sie den Vorgang
 * 4. Alle Daten werden gelöscht und durch die Demo-Daten ersetzt
 */

require_once __DIR__ . '/../config.php';

// Sicherstellen, dass wir mit der members-Tabelle arbeiten
define('FORCE_MEMBER_SOURCE', 'members');

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

$demo_file = __DIR__ . '/demo_data.json';
$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo-Daten Import</title>
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
            background-color: #f0f0f0;
            font-weight: bold;
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
        .environment-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .env-local {
            background-color: #28a745;
            color: white;
        }
        .env-production {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <h1>♻️ Demo-Daten Import &amp; Reset</h1>

    <div class="environment-badge <?php echo IS_LOCAL ? 'env-local' : 'env-production'; ?>">
        <?php echo IS_LOCAL ? '🏠 LOKALE UMGEBUNG' : '🌐 REMOTE SERVER'; ?>
    </div>

    <div class="environment-badge" style="background-color: #ffc107; color: #000; margin-left: 10px;">
        🎭 DEMO-MODUS AKTIV
    </div>

    <?php if (!DEMO_MODE_ENABLED): ?>
        <div class="error">
            <h3>🚫 Demo-Modus deaktiviert!</h3>
            <p>
                Dieses Skript ist nur verfügbar, wenn <code>DEMO_MODE_ENABLED = true</code> in der <code>config.php</code> gesetzt ist.<br>
                Dies ist eine Sicherheitsmaßnahme, um versehentliches Löschen von Produktivdaten zu verhindern.
            </p>
            <p>
                <strong>Für den echten Produktivbetrieb sollte DEMO_MODE_ENABLED = false sein!</strong>
            </p>
            <p>
                Wenn Sie auf einem Demo-Server oder in einer Entwicklungsumgebung arbeiten und die Demo-Funktionen nutzen möchten,
                setzen Sie in der <code>config.php</code>:
            </p>
            <pre>define('DEMO_MODE_ENABLED', true);</pre>
        </div>
        <?php exit; ?>
    <?php endif; ?>

    <div class="card">
        <h2>Über dieses Tool</h2>
        <p>
            Dieses Skript setzt die Datenbank auf einen sauberen Demo-Stand zurück.
        </p>
        <p><strong>Was wird gelöscht:</strong></p>
        <ul>
            <li>Alle Meetings & Teilnehmer</li>
            <li>Alle Tagesordnungspunkte & Kommentare</li>
            <li>Alle Protokolle & Änderungswünsche</li>
            <li>Alle TODOs & Historie</li>
            <li>Alle Terminabstimmungen & Antworten</li>
            <li>Alle Meinungsbilder & Antworten</li>
            <li>Alle Mitglieder (members-Tabelle)</li>
        </ul>
        <p><strong>Was wird importiert:</strong></p>
        <ul>
            <li>Demo-Mitglieder</li>
            <li>Demo-Meetings in verschiedenen Stati</li>
            <li>Demo-Tagesordnungspunkte mit Kommentaren & Protokollen</li>
            <li>Demo-TODOs mit Historie</li>
            <li>Demo-Terminabstimmungen mit Antworten</li>
            <li>Demo-Meinungsbilder mit Antworten</li>
        </ul>
    </div>

    <?php
    if (!file_exists($demo_file)) {
        echo '<div class="error">';
        echo '<h3>❌ Demo-Datei nicht gefunden</h3>';
        echo '<p>Die Datei <code>' . htmlspecialchars($demo_file) . '</code> existiert nicht.</p>';
        echo '<p>Bitte erstellen Sie zuerst Demo-Daten mit <code>demo_export.php</code>.</p>';
        echo '<a href="demo_export.php" class="btn">➡️ Zum Export-Tool</a>';
        echo '</div>';
        exit;
    }

    // Datei-Info anzeigen
    $file_info = stat($demo_file);
    echo '<div class="info">';
    echo '<h3>📄 Demo-Datei gefunden</h3>';
    echo '<p><strong>Datei:</strong> <code>' . basename($demo_file) . '</code></p>';
    echo '<p><strong>Größe:</strong> ' . number_format($file_info['size'] / 1024, 2) . ' KB</p>';
    echo '<p><strong>Geändert:</strong> ' . date('d.m.Y H:i:s', $file_info['mtime']) . '</p>';
    echo '</div>';

    if (!$confirmed) {
        // Bestätigungs-Formular anzeigen
        echo '<div class="warning">';
        echo '<h3>⚠️ WARNUNG</h3>';
        echo '<p><strong>Dieser Vorgang kann nicht rückgängig gemacht werden!</strong></p>';
        echo '<p>Alle bestehenden Meeting-Daten werden unwiderruflich gelöscht.</p>';
        echo '<form method="post">';
        echo '<input type="hidden" name="confirm" value="yes">';
        echo '<button type="submit" class="btn btn-danger">🗑️ Ja, alle Daten löschen und Demo-Daten importieren</button>';
        echo '</form>';
        echo '</div>';
    } else {
        // Import durchführen
        try {
            $pdo->beginTransaction();

            // 1. Alle Daten löschen (in der richtigen Reihenfolge wegen Foreign Keys)
            echo '<div class="card">';
            echo '<h2>🗑️ Lösche bestehende Daten...</h2>';

            $tables_to_clear = [
                // Meinungsbild-Daten (in korrekter Reihenfolge wegen FK)
                'opinion_response_options',
                'opinion_responses',
                'opinion_poll_participants',
                'opinion_poll_options',
                'opinion_polls',
                // Terminabstimmungs-Daten
                'poll_responses',
                'poll_participants',
                'poll_dates',
                'polls',
                // TODO-Daten
                'todo_log',
                'todos',
                // Protokoll-Daten
                'protocol_change_requests',
                'protocols',
                // Meeting-Daten
                'agenda_comments',
                'agenda_items',
                'meeting_participants',
                'meetings',
                // Mitglieder
                'members'
            ];

            foreach ($tables_to_clear as $table) {
                $stmt = $pdo->prepare("DELETE FROM $table");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                echo '<p>✓ ' . $table . ': ' . $deleted . ' Datensätze gelöscht</p>';
            }

            // Auto-Increment zurücksetzen
            foreach ($tables_to_clear as $table) {
                $pdo->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
            }

            echo '</div>';

            // 2. Demo-Daten laden
            $json = file_get_contents($demo_file);
            $demo_data = json_decode($json, true);

            if (!$demo_data) {
                throw new Exception('Fehler beim Parsen der JSON-Datei');
            }

            echo '<div class="card">';
            echo '<h2>📥 Importiere Demo-Daten...</h2>';
            echo '<p><strong>Export-Datum:</strong> ' . $demo_data['export_date'] . '</p>';
            echo '<p><strong>Version:</strong> ' . $demo_data['export_version'] . '</p>';
            echo '</div>';

            // 3. Daten einfügen
            $import_stats = [];
            $import_errors = [];

            foreach ($demo_data['tables'] as $table => $rows) {
                if (empty($rows)) {
                    echo "<p style='color: orange;'>⚠ Tabelle '$table': keine Daten zum Importieren</p>";
                    continue;
                }

                $count = 0;
                foreach ($rows as $row) {
                    try {
                        $columns = array_keys($row);
                        $placeholders = array_fill(0, count($columns), '?');

                        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_values($row));
                        $count++;
                    } catch (PDOException $e) {
                        $import_errors[] = "Fehler bei Tabelle '$table': " . $e->getMessage();
                        // Weiter mit nächstem Datensatz
                    }
                }

                $import_stats[$table] = $count;
                echo "<p>✓ $table: $count Datensätze importiert</p>";
            }

            $pdo->commit();

            // Fehler anzeigen (falls vorhanden)
            if (!empty($import_errors)) {
                echo '<div class="error">';
                echo '<h3>⚠️ Import-Fehler</h3>';
                echo '<ul>';
                foreach ($import_errors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            echo '<div class="success">';
            echo '<h3>✅ Import erfolgreich abgeschlossen!</h3>';
            echo '<table>';
            echo '<tr><th>Tabelle</th><th>Importierte Datensätze</th></tr>';
            foreach ($import_stats as $table => $count) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($table) . '</td>';
                echo '<td>' . $count . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';

            echo '<div class="info">';
            echo '<h3>🎉 Fertig!</h3>';
            echo '<p>Die Datenbank wurde erfolgreich auf den Demo-Stand zurückgesetzt.</p>';
            echo '<p><a href="../index.php" class="btn">➡️ Zur Anwendung</a></p>';
            echo '</div>';

        } catch (Exception $e) {
            $pdo->rollBack();

            echo '<div class="error">';
            echo '<h3>❌ Fehler beim Import</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
    }
    ?>

</body>
</html>
