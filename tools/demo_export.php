<?php
/**
 * demo_export.php - Exportiert den aktuellen Datenbankstand als Demo-Daten
 *
 * Dieses Skript exportiert alle relevanten Tabellen aus der "members"-basierten
 * Datenbank in eine JSON-Datei, die später als Demoversion eingespielt werden kann.
 *
 * VERWENDUNG:
 * 1. Erstelle in der Anwendung verschiedene Meetings mit unterschiedlichen Stati
 * 2. Füge Kommentare, TODOs, Protokolle etc. hinzu
 * 3. Rufe dieses Skript im Browser auf
 * 4. Die Datei demo_data.json wird im tools/-Verzeichnis erstellt
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
            <p>Für den Zugriff auf Import/Export-Funktionen wird das System-Admin-Passwort benötigt.</p>
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

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo-Daten Export</title>
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
            margin-top: 10px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>📦 Demo-Daten Export</h1>

    <div class="card">
        <h2>Über dieses Tool</h2>
        <p>
            Dieses Skript exportiert den aktuellen Stand deiner Datenbank (members-basiert)
            in eine JSON-Datei. Diese kann später als "Demoversion" eingespielt werden.
        </p>
        <p><strong>Exportierte Tabellen:</strong></p>
        <p>
            Alle Tabellen mit dem Prefix "sv" werden automatisch exportiert. Dies umfasst:
        </p>
        <ul>
            <li>svmembers (Mitglieder)</li>
            <li>svmeetings (Sitzungen)</li>
            <li>svmeeting_participants (Teilnehmer)</li>
            <li>svagenda_items (Tagesordnungspunkte)</li>
            <li>svagenda_comments (Kommentare)</li>
            <li>svprotocols (Protokolle)</li>
            <li>svtodos (Aufgaben)</li>
            <li>svpolls (Terminabstimmungen)</li>
            <li>svopinion_polls (Meinungsbilder)</li>
            <li>svdocuments (Dokumente)</li>
            <li>...und alle weiteren sv*-Tabellen</li>
        </ul>
    </div>

    <?php
    try {
        // Daten aus allen relevanten Tabellen holen
        $export_data = [
            'export_date' => date('Y-m-d H:i:s'),
            'export_version' => '1.0',
            'tables' => []
        ];

        // Alle Tabellen mit "sv"-Prefix automatisch finden
        $stmt = $pdo->query("SHOW TABLES LIKE 'sv%'");
        $tables_to_export = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables_to_export)) {
            throw new Exception('Keine Tabellen mit "sv"-Prefix gefunden. Bitte prüfe die Datenbank.');
        }

        $total_records = 0;

        foreach ($tables_to_export as $table) {
            $stmt = $pdo->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $export_data['tables'][$table] = $rows;
            $total_records += count($rows);
        }

        // JSON-Datei erstellen
        $json = json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = __DIR__ . '/demo_data.json';
        file_put_contents($filename, $json);

        // Warnung bei zu wenig Daten
        $has_meetings = isset($export_data['tables']['svmeetings']) && count($export_data['tables']['svmeetings']) > 0;
        $has_agenda = isset($export_data['tables']['svagenda_items']) && count($export_data['tables']['svagenda_items']) > 0;

        if (!$has_meetings || !$has_agenda) {
            echo '<div class="error">';
            echo '<h3>⚠️ WARNUNG: Kaum Daten exportiert!</h3>';
            echo '<p><strong>Problem:</strong> Die Datenbank enthält keine oder kaum Daten!</p>';
            echo '<ul>';
            if (!$has_meetings) {
                echo '<li>❌ Keine Meetings gefunden (svmeetings ist leer)</li>';
            }
            if (!$has_agenda) {
                echo '<li>❌ Keine Tagesordnungspunkte gefunden (svagenda_items ist leer)</li>';
            }
            echo '</ul>';
            echo '<p><strong>Lösung:</strong> Erstelle zuerst einige Demo-Meetings mit TOPs, Kommentaren etc. in der Anwendung, bevor du exportierst!</p>';
            echo '<p>Diese leere JSON-Datei kann zwar importiert werden, enthält aber keine sinnvollen Demo-Daten.</p>';
            echo '</div>';
        }

        echo '<div class="success">';
        echo '<h3>✅ Export erfolgreich!</h3>';
        echo '<p>Datei erstellt: <code>' . $filename . '</code></p>';
        echo '<p>Dateigröße: ' . number_format(filesize($filename) / 1024, 2) . ' KB</p>';
        echo '<p>Gesamt-Datensätze: ' . $total_records . '</p>';
        echo '</div>';

        // Statistik anzeigen
        echo '<div class="card">';
        echo '<h2>📊 Export-Statistik</h2>';
        echo '<table>';
        echo '<tr><th>Tabelle</th><th>Anzahl Datensätze</th></tr>';
        foreach ($export_data['tables'] as $table => $rows) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($table) . '</td>';
            echo '<td>' . count($rows) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';

        // Nächste Schritte
        echo '<div class="info">';
        echo '<h3>📋 Nächste Schritte - So überträgst du die Daten auf einen anderen Server</h3>';
        echo '<ol>';
        echo '<li><strong>Export erfolgreich!</strong> Die Datei <code>demo_data.json</code> wurde im <code>tools/</code>-Verzeichnis erstellt</li>';
        echo '<li><strong>Datei auf neuen Server kopieren:</strong>';
        echo '<ul>';
        echo '<li>Lade die Datei <code>tools/demo_data.json</code> herunter</li>';
        echo '<li>Lade sie auf dem Zielserver in das gleiche <code>tools/</code>-Verzeichnis hoch</li>';
        echo '</ul></li>';
        echo '<li><strong>Auf dem Zielserver:</strong>';
        echo '<ul>';
        echo '<li>Stelle sicher, dass <code>config.php</code> die Einstellung <code>DEMO_MODE_ENABLED = true</code> hat</li>';
        echo '<li>Führe zuerst <code>init-db.php</code> aus, um die Tabellen anzulegen</li>';
        echo '<li>Rufe dann <code>tools/demo_import.php</code> im Browser auf</li>';
        echo '</ul></li>';
        echo '<li><strong>Lokaler Test:</strong> Du kannst die Demo-Daten auch lokal testen mit <code>demo_import.php</code></li>';
        echo '</ol>';
        echo '<a href="demo_import.php" class="btn">➡️ Zum Import-Tool (lokaler Test)</a>';
        echo '</div>';

        // Download-Link für die JSON-Datei
        echo '<div class="card">';
        echo '<h3>💾 Datei herunterladen</h3>';
        echo '<p>Klicke hier, um die demo_data.json direkt herunterzuladen:</p>';
        echo '<a href="demo_data.json" download class="btn">⬇️ demo_data.json herunterladen</a>';
        echo ' ';
        echo '<a href="demo_analyze.php" class="btn" style="background: #6c757d;">🔍 JSON-Datei analysieren</a>';
        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<h3>❌ Fehler beim Export</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>

</body>
</html>
