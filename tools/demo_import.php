<?php
/**
 * demo_import.php - Importiert Demo-Daten und setzt die Datenbank zurück
 *
 * WARNUNG: Dieses Skript löscht ALLE bestehenden Meeting-Daten!
 * Es sollte nur in Entwicklungs- oder Demo-Umgebungen verwendet werden.
 *
 * VERWENDUNG:
 * 1. Stelle sicher, dass demo_data.json im tools/-Verzeichnis existiert
 * 2. Rufe dieses Skript im Browser auf
 * 3. Bestätige den Vorgang
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
                Wenn du auf einem Demo-Server oder in einer Entwicklungsumgebung arbeitest und die Demo-Funktionen nutzen möchtest,
                setze in der <code>config.php</code>:
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

        <div class="info" style="margin-top: 20px;">
            <h3>✅ Checkliste vor dem Import</h3>
            <ol>
                <li>✓ <code>config.php</code> hat <code>DEMO_MODE_ENABLED = true</code> <?php echo DEMO_MODE_ENABLED ? '✅' : '❌'; ?></li>
                <li>✓ <code>init-db.php</code> wurde ausgeführt (Tabellen existieren)</li>
                <li>✓ <code>tools/demo_data.json</code> existiert <?php echo file_exists($demo_file) ? '✅' : '❌'; ?></li>
            </ol>
            <?php if (!DEMO_MODE_ENABLED): ?>
                <p style="color: #dc3545; font-weight: bold;">⚠️ DEMO_MODE_ENABLED ist auf false! Setze es auf true in config.php</p>
            <?php endif; ?>
            <?php if (!file_exists($demo_file)): ?>
                <p style="color: #dc3545; font-weight: bold;">⚠️ demo_data.json nicht gefunden! Erstelle sie zuerst mit demo_export.php auf dem Quellserver</p>
            <?php endif; ?>
        </div>
        <p><strong>Was wird gelöscht:</strong></p>
        <p>
            <strong>Alle Tabellen mit "sv"-Prefix werden komplett geleert!</strong> Dies umfasst:
        </p>
        <ul>
            <li>svmembers (Alle Mitglieder)</li>
            <li>svmeetings (Alle Meetings & Teilnehmer)</li>
            <li>svagenda_items (Alle Tagesordnungspunkte & Kommentare)</li>
            <li>svprotocols (Alle Protokolle & Änderungswünsche)</li>
            <li>svtodos (Alle TODOs & Historie)</li>
            <li>svpolls (Alle Terminabstimmungen & Antworten)</li>
            <li>svopinion_polls (Alle Meinungsbilder & Antworten)</li>
            <li>svdocuments (Alle Dokumente & Download-Historie)</li>
            <li>...und alle weiteren sv*-Tabellen</li>
        </ul>
        <p><strong>Was wird importiert:</strong></p>
        <ul>
            <li>Demo-Mitglieder</li>
            <li>Demo-Meetings in verschiedenen Stati</li>
            <li>Demo-Tagesordnungspunkte mit Kommentaren & Protokollen</li>
            <li>Demo-TODOs mit Historie</li>
            <li>Demo-Terminabstimmungen mit Antworten</li>
            <li>Demo-Meinungsbilder mit Antworten</li>
            <li>Demo-Dokumente mit Download-Statistiken</li>
        </ul>
    </div>

    <?php
    if (!file_exists($demo_file)) {
        echo '<div class="error">';
        echo '<h3>❌ Demo-Datei nicht gefunden</h3>';
        echo '<p>Die Datei <code>' . htmlspecialchars($demo_file) . '</code> existiert nicht.</p>';
        echo '<p>Bitte erstelle zuerst Demo-Daten mit <code>demo_export.php</code>.</p>';
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
    echo '<p><a href="demo_analyze.php" class="btn" style="background: #6c757d; color: white;">🔍 JSON-Datei analysieren (Zeigt was wirklich in der Datei steht)</a></p>';
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

            // 1. Alle Daten löschen
            echo '<div class="card">';
            echo '<h2>🗑️ Lösche bestehende Daten...</h2>';

            // Alle Tabellen mit "sv"-Prefix automatisch finden
            $stmt = $pdo->query("SHOW TABLES LIKE 'sv%'");
            $tables_to_clear = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Foreign Key Checks temporär deaktivieren für einfacheres Löschen
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

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

            // Foreign Key Checks bleiben während des Imports deaktiviert!
            // (werden am Ende wieder aktiviert)

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

            // Warnung bei zu wenig Daten
            $has_meetings = isset($demo_data['tables']['svmeetings']) && count($demo_data['tables']['svmeetings']) > 0;
            $has_agenda = isset($demo_data['tables']['svagenda_items']) && count($demo_data['tables']['svagenda_items']) > 0;

            if (!$has_meetings || !$has_agenda) {
                echo '<div class="warning" style="margin-top: 15px;">';
                echo '<h4>⚠️ WARNUNG: JSON-Datei enthält kaum Daten!</h4>';
                echo '<ul>';
                if (!$has_meetings) {
                    echo '<li>❌ Keine Meetings in der JSON-Datei (svmeetings ist leer)</li>';
                }
                if (!$has_agenda) {
                    echo '<li>❌ Keine Tagesordnungspunkte in der JSON-Datei (svagenda_items ist leer)</li>';
                }
                echo '</ul>';
                echo '<p><strong>Mögliche Ursache:</strong> Die demo_data.json wurde von einem Server exportiert, der selbst keine Daten hatte!</p>';
                echo '<p><strong>Lösung:</strong> Exportiere die Daten vom RICHTIGEN Quellserver (der mit den tatsächlichen Meeting-Daten).</p>';
                echo '</div>';
            }

            // Hinweis auf Schema-Probleme
            echo '<div class="info" style="margin-top: 15px;">';
            echo '<h4>💡 Häufiges Problem: Unterschiedliche Datenbank-Schemas</h4>';
            echo '<p>Wenn beim Import Fehler wie "Unknown column" oder "Column not found" auftreten, haben Quell- und Zieldatenbank unterschiedliche Schemas.</p>';
            echo '<p><strong>Lösung:</strong> <a href="migrate_schema.php" style="color: #0c5460; font-weight: bold;">🔧 Schema-Migration ausführen</a> (Fügt fehlende Spalten hinzu)</p>';
            echo '</div>';

            echo '</div>';

            // 3. Daten einfügen
            $import_stats = [];
            $import_errors = [];

            // Definierte Import-Reihenfolge nach Foreign Key Hierarchie
            // Parent-Tabellen müssen vor Child-Tabellen importiert werden
            $table_order = [
                // Level 1: Keine Abhängigkeiten
                'svmembers',
                'svopinion_answer_templates',

                // Level 2: Abhängig von Level 1
                'svmeetings',
                'svabsences',
                'svadmin_log',
                'svpolls',
                'svopinion_polls',
                'svdocuments',
                'svprotocols',  // Keine FK-Abhängigkeit mehr (meeting_id ist optional)
                'svnotifications',  // Benachrichtigungs-Center (2026-04-27)
                'svpush_subscriptions',  // Browser-Push-Abos (2026-04-27)

                // Level 3: Abhängig von Level 2
                'svmeeting_participants',
                'svagenda_items',
                'svtodos',
                'svpoll_dates',
                'svpoll_participants',
                'svopinion_poll_options',
                'svopinion_poll_participants',
                'svopinion_responses',
                'svdocument_downloads',
                'svmail_queue',
                'svexternal_participants',  // Externe Teilnehmer für Umfragen (2025-12-18)

                // Level 4: Abhängig von Level 3
                'svagenda_comments',
                'svagenda_live_comments',  // Live-Kommentare während Sitzung
                'svagenda_post_comments',  // Nachträgliche Anmerkungen in ended-Phase
                'svagenda_attachments',  // Dateianhänge an TOPs (2026-04-27)
                'svprotocol_change_requests',
                'svtodo_log',
                'svpoll_responses',
                'svopinion_response_options',
                'svexternal_access_log',  // Log für externe Zugriffe (2026-05-03)
            ];

            // Importiere Tabellen in der definierten Reihenfolge
            foreach ($table_order as $table) {
                // Überspringe Tabellen, die nicht in der JSON-Datei sind
                if (!isset($demo_data['tables'][$table])) {
                    continue;
                }

                $rows = $demo_data['tables'][$table];
                if (empty($rows)) {
                    echo "<p style='color: orange;'>⚠ Tabelle '$table': keine Daten zum Importieren</p>";
                    continue;
                }

                $count = 0;
                $error_count = 0;
                $first_error = null;

                foreach ($rows as $row_index => $row) {
                    try {
                        $columns = array_keys($row);
                        $placeholders = array_fill(0, count($columns), '?');

                        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_values($row));
                        $count++;
                    } catch (PDOException $e) {
                        $error_count++;
                        $error_msg = "Tabelle '$table', Zeile $row_index: " . $e->getMessage();
                        $import_errors[] = $error_msg;

                        // Ersten Fehler für Schnellübersicht merken
                        if ($first_error === null) {
                            $first_error = $e->getMessage();
                        }
                    }
                }

                $import_stats[$table] = $count;

                // Detaillierte Ausgabe
                if ($count > 0 && $error_count === 0) {
                    echo "<p style='color: green;'>✓ $table: <strong>$count</strong> Datensätze erfolgreich importiert</p>";
                } elseif ($count > 0 && $error_count > 0) {
                    echo "<p style='color: orange;'>⚠ $table: <strong>$count</strong> importiert, <strong style='color:red;'>$error_count Fehler</strong> - Erster Fehler: " . htmlspecialchars(substr($first_error, 0, 100)) . "...</p>";
                } else {
                    echo "<p style='color: red;'>✗ $table: <strong>0</strong> Datensätze importiert";
                    if ($error_count > 0) {
                        echo " (<strong>$error_count Fehler</strong>) - Erster Fehler: " . htmlspecialchars(substr($first_error, 0, 100)) . "...";
                    }
                    echo "</p>";
                }
            }

            // Importiere übrige Tabellen, die nicht in der definierten Reihenfolge sind
            // (Fallback für neue Tabellen)
            foreach ($demo_data['tables'] as $table => $rows) {
                // Überspringe Tabellen, die bereits importiert wurden
                if (in_array($table, $table_order)) {
                    continue;
                }

                if (empty($rows)) {
                    echo "<p style='color: orange;'>⚠ Tabelle '$table' (nicht in Reihenfolge): keine Daten zum Importieren</p>";
                    continue;
                }

                echo "<p style='color: blue;'>ℹ️ Tabelle '$table' (nicht in definierter Reihenfolge) wird importiert...</p>";

                $count = 0;
                $error_count = 0;
                $first_error = null;

                foreach ($rows as $row_index => $row) {
                    try {
                        $columns = array_keys($row);
                        $placeholders = array_fill(0, count($columns), '?');

                        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_values($row));
                        $count++;
                    } catch (PDOException $e) {
                        $error_count++;
                        $error_msg = "Tabelle '$table', Zeile $row_index: " . $e->getMessage();
                        $import_errors[] = $error_msg;

                        if ($first_error === null) {
                            $first_error = $e->getMessage();
                        }
                    }
                }

                $import_stats[$table] = $count;

                if ($count > 0 && $error_count === 0) {
                    echo "<p style='color: green;'>✓ $table: <strong>$count</strong> Datensätze erfolgreich importiert</p>";
                } elseif ($count > 0 && $error_count > 0) {
                    echo "<p style='color: orange;'>⚠ $table: <strong>$count</strong> importiert, <strong style='color:red;'>$error_count Fehler</strong> - Erster Fehler: " . htmlspecialchars(substr($first_error, 0, 100)) . "...</p>";
                } else {
                    echo "<p style='color: red;'>✗ $table: <strong>0</strong> Datensätze importiert";
                    if ($error_count > 0) {
                        echo " (<strong>$error_count Fehler</strong>) - Erster Fehler: " . htmlspecialchars(substr($first_error, 0, 100)) . "...";
                    }
                    echo "</p>";
                }
            }

            $pdo->commit();

            // Fehler anzeigen (falls vorhanden)
            if (!empty($import_errors)) {
                echo '<div class="error">';
                echo '<h3>⚠️ Import-Fehler (' . count($import_errors) . ' Fehler)</h3>';
                echo '<p><strong>Die folgenden Fehler sind beim Import aufgetreten:</strong></p>';

                // Gruppiere Fehler nach Tabelle für bessere Übersicht
                $errors_by_table = [];
                foreach ($import_errors as $error) {
                    if (preg_match('/Tabelle \'([^\']+)\'/', $error, $matches)) {
                        $table_name = $matches[1];
                        if (!isset($errors_by_table[$table_name])) {
                            $errors_by_table[$table_name] = [];
                        }
                        $errors_by_table[$table_name][] = $error;
                    } else {
                        $errors_by_table['Andere'][] = $error;
                    }
                }

                foreach ($errors_by_table as $table => $errors) {
                    echo '<details style="margin: 10px 0; border: 1px solid #dc3545; border-radius: 4px; padding: 10px;">';
                    echo '<summary style="cursor: pointer; font-weight: bold; color: #dc3545;">📋 ' . htmlspecialchars($table) . ' (' . count($errors) . ' Fehler)</summary>';
                    echo '<ul style="margin-top: 10px;">';
                    // Zeige maximal erste 10 Fehler pro Tabelle
                    $shown_errors = array_slice($errors, 0, 10);
                    foreach ($shown_errors as $error) {
                        echo '<li style="font-size: 12px; font-family: monospace;">' . htmlspecialchars($error) . '</li>';
                    }
                    if (count($errors) > 10) {
                        echo '<li style="color: #666; font-style: italic;">... und ' . (count($errors) - 10) . ' weitere Fehler</li>';
                    }
                    echo '</ul>';
                    echo '</details>';
                }
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

            // Foreign Key Checks wieder aktivieren (am Ende des Imports)
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        } catch (Exception $e) {
            // Im Fehlerfall auch Foreign Keys wieder aktivieren
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            // Nur rollBack wenn Transaktion noch aktiv
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

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
