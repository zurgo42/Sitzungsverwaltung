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
require_once __DIR__ . '/demo_common.php';

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
                <li>✓ <code>tools/init-db.php</code> wurde ausgeführt (Tabellen existieren)</li>
                <li>✓ <code>tools/demo_data.json</code> existiert <?php echo file_exists($demo_file) ? '✅' : '❌'; ?></li>
            </ol>
            <?php if (!DEMO_MODE_ENABLED): ?>
                <p style="color: #dc3545; font-weight: bold;">⚠️ DEMO_MODE_ENABLED ist auf false! Setze es auf true in config.php</p>
            <?php endif; ?>
            <?php if (!file_exists($demo_file)): ?>
                <p style="color: #dc3545; font-weight: bold;">⚠️ demo_data.json nicht gefunden! Erstelle sie zuerst mit demo_build.php (erfundene Daten) oder demo_export.php (Export einer Datenbank)</p>
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
            <li>svevents (Kalender), svnotifications, svcollab_texts, svconfig (Admin-Einstellungen), svressorts</li>
            <li>Anträge/Beschlüsse/Ressorts laut config (TABLE_ANTRAEGE usw.), Feedback und das Aktions-Protokoll</li>
            <li><strong>Datenschutz:</strong> svmail_queue, svmail_notifications, svpush_subscriptions, svexternal_access_log, svaudit_log, svadmin_log,
                svdocument_downloads, svvote_reminders, svcollab_text_locks und protokoll werden geleert und <em>nie</em> importiert</li>
            <li>...und alle weiteren sv*-Tabellen; die Tabelle berechtigte wird (nur Standalone) aus svmembers neu abgeleitet</li>
        </ul>
        <p><strong>Was wird importiert:</strong></p>
        <ul>
            <li>Demo-Mitglieder</li>
            <li>Demo-Meetings in verschiedenen Stati</li>
            <li>Demo-Tagesordnungspunkte mit Kommentaren & Protokollen</li>
            <li>Demo-TODOs mit Historie</li>
            <li>Demo-Terminabstimmungen mit Antworten</li>
            <li>Demo-Meinungsbilder mit Antworten</li>
            <li>Demo-Dokumente (nur Metadaten), Kalender, Anträge in allen Phasen, Admin-Einstellungen (svconfig, Ressorts)</li>
        </ul>
    </div>

    <?php
    if (!file_exists($demo_file)) {
        echo '<div class="error">';
        echo '<h3>❌ Demo-Datei nicht gefunden</h3>';
        echo '<p>Die Datei <code>' . htmlspecialchars($demo_file) . '</code> existiert nicht.</p>';
        echo '<p>Bitte erstelle zuerst Demo-Daten mit <code>demo_build.php</code> (erfundene Daten, <code>php tools/demo_build.php</code>) oder <code>demo_export.php</code>.</p>';
        echo '<a href="demo_build.php" class="btn">➡️ Zum Demo-Builder</a> <a href="demo_export.php" class="btn">➡️ Zum Export-Tool</a>';
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
        $import_stats = [];
        $import_errors = [];
        $import_notes = [];
        try {
            echo '<div class="card">';
            echo '<h2>🗑️ Lösche bestehende Daten...</h2>';

            // 0. JSON laden und prüfen
            $json = file_get_contents($demo_file);
            $demo_data = json_decode($json, true);
            if (!is_array($demo_data) || !isset($demo_data['tables']) || !is_array($demo_data['tables'])) {
                throw new Exception('Fehler beim Parsen der JSON-Datei');
            }
            $json_tables = $demo_data['tables'];

            // Datenschutz: ausgeschlossene Tabellen werden nie importiert; Fremd-Mailadressen nur melden
            $ignored_in_json = array_values(array_filter(array_keys($json_tables), 'demo_is_excluded'));
            if ($ignored_in_json) {
                echo '<p style="color:#856404;">⚠️ Folgende Tabellen stehen in der JSON, werden aber aus Datenschutzgründen ignoriert: <code>'
                    . htmlspecialchars(implode(', ', $ignored_in_json)) . '</code></p>';
            }
            $foreign_mails = demo_find_foreign_emails($json_tables, 5);
            if ($foreign_mails) {
                echo '<p style="color:#dc3545;">⚠️ Die JSON enthält Mailadressen außerhalb von example.com/.org/.net (z. B. <code>'
                    . htmlspecialchars($foreign_mails[0]['value']) . '</code> in ' . htmlspecialchars($foreign_mails[0]['table'])
                    . '). Für eine öffentliche Demo bitte durch Fake-Daten ersetzen (tools/demo_build.php).</p>';
            }

            // 1. Tabellen bestimmen
            $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $existing_lc = array_map('strtolower', $existing);
            $table_exists = function ($t) use ($existing_lc) { return in_array(strtolower($t), $existing_lc, true); };

            // Alle sv*-Tabellen (auch die ausgeschlossenen Log-/Queue-Tabellen: sie werden geleert, nie importiert)
            $tables_to_clear = array_values(array_filter($existing, function ($t) {
                return strncasecmp($t, 'sv', 2) === 0;
            }));

            // Anträge/Beschlüsse/Ressorts können laut config.php anders heißen (z. B. antraege, VTool-Variante).
            // Sie werden nur geleert, wenn die JSON sie enthält (alte Exporte würden sonst z. B. die Ressortliste leeren).
            $name_map = [];
            foreach (demo_table_constants() as $logical => $const) {
                $real = demo_real_table($logical);
                $name_map[$logical] = $real;
                if ($real !== $logical && (isset($json_tables[$logical]) || isset($json_tables[$real])) && $table_exists($real)) {
                    $tables_to_clear[] = $real;
                }
            }
            // Aktions-Protokoll, Feedback und alle ausgeschlossenen Tabellen (Datenschutz) leeren
            foreach (array_merge(['protokoll', 'feedback'], array_keys(DEMO_EXCLUDED_TABLES)) as $extra) {
                if ($table_exists($extra) && !in_array($extra, $tables_to_clear, true)) $tables_to_clear[] = $extra;
            }
            $tables_to_clear = array_values(array_unique($tables_to_clear));

            // Mitgliederquelle: berechtigte nur im Standalone-Betrieb ableiten/leeren (in der VTool-Variante echte Daten!)
            $derive_berechtigte = !demo_member_source_is_berechtigte();
            if (!$derive_berechtigte) {
                $import_notes[] = 'MEMBER_SOURCE = berechtigte: Die Tabelle berechtigte bleibt unverändert (echte VTool-Daten).';
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            foreach ($tables_to_clear as $table) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
                $stmt = $pdo->prepare("DELETE FROM `$table`");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                $suffix = array_key_exists($table, DEMO_EXCLUDED_TABLES) ? ' <small>(Datenschutz: wird nie importiert)</small>' : '';
                echo "<p>✓ $table: $deleted Datensätze gelöscht$suffix</p>";
            }
            if ($derive_berechtigte && $table_exists(DEMO_DERIVED_TABLE)) {
                $n = $pdo->exec("DELETE FROM " . DEMO_DERIVED_TABLE);
                echo "<p>✓ " . DEMO_DERIVED_TABLE . ": $n Datensätze gelöscht <small>(wird aus svmembers neu abgeleitet)</small></p>";
            }

            // Auto-Increment zurücksetzen (DDL: vor der Transaktion)
            foreach ($tables_to_clear as $table) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
                try { $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1"); } catch (PDOException $e) { /* z. B. View */ }
            }
            echo '</div>';

            echo '<div class="card">';
            echo '<h2>📥 Importiere Demo-Daten...</h2>';
            $pdo->beginTransaction();

            // Spalteninfo der Zieltabellen (Schema-Abweichungen werden toleriert: unbekannte Spalten werden verworfen)
            $col_info = [];
            $get_cols = function ($table) use ($pdo, &$col_info) {
                if (!isset($col_info[$table])) {
                    $col_info[$table] = [];
                    foreach ($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                        $col_info[$table][$c['Field']] = [
                            'required' => ($c['Null'] === 'NO' && $c['Default'] === null && stripos($c['Extra'], 'auto_increment') === false
                                           && stripos($c['Extra'], 'generated') === false),
                        ];
                    }
                }
                return $col_info[$table];
            };
            $stmt_cache = [];

            // Reihenfolge: definierte Reihenfolge (Eltern vor Kindern), dann übrige Tabellen der JSON
            $queue = [];
            foreach (demo_table_order() as $logical) {
                if (isset($name_map[$logical]) && isset($json_tables[$name_map[$logical]]) && !isset($json_tables[$logical])) {
                    $queue[$logical] = $name_map[$logical];   // JSON enthält bereits den konfigurierten Namen
                } elseif (isset($json_tables[$logical])) {
                    $queue[$logical] = $logical;
                }
            }
            foreach ($json_tables as $key => $rows) {
                if (!in_array($key, $queue, true) && !isset($queue[$key])) $queue[$key] = $key;
            }

            foreach ($queue as $logical => $json_key) {
                if (demo_is_excluded($logical) || demo_is_excluded($json_key)) continue;   // Datenschutz
                $rows = $json_tables[$json_key] ?? null;
                if (empty($rows) || !is_array($rows)) continue;

                $target = $name_map[$logical] ?? $logical;
                if (!preg_match('/^[A-Za-z0-9_]+$/', $target) || !$table_exists($target)) {
                    $import_notes[] = "Tabelle '$target' existiert nicht in der Datenbank – " . count($rows) . ' Zeilen übersprungen (init-db.php bzw. Migration ausführen).';
                    continue;
                }
                $cols_meta = $get_cols($target);
                $count = 0; $error_count = 0; $skipped = 0; $first_error = null; $dropped = [];

                foreach ($rows as $row_index => $row) {
                    if (!is_array($row)) continue;
                    if ($logical === 'svconfig' && in_array($row['config_key'] ?? '', DEMO_RUNTIME_CONFIG_KEYS, true)) continue;

                    $clean = [];
                    foreach ($row as $col => $val) {
                        if (isset($cols_meta[$col])) $clean[$col] = $val; else $dropped[$col] = true;
                    }
                    if (!$clean) continue;
                    // Pflichtspalte ohne Wert (z. B. NOT NULL durch fehlende Migration) -> Zeile überspringen
                    $missing = false;
                    foreach ($clean as $col => $val) {
                        if ($val === null && $cols_meta[$col]['required']) { $missing = true; break; }
                    }
                    if ($missing) { $skipped++; continue; }

                    try {
                        $columns = array_keys($clean);
                        $key = $target . '|' . implode(',', $columns);
                        if (!isset($stmt_cache[$key])) {
                            $stmt_cache[$key] = $pdo->prepare("INSERT INTO `$target` (`" . implode('`, `', $columns) . "`) VALUES ("
                                . implode(', ', array_fill(0, count($columns), '?')) . ")");
                        }
                        $stmt_cache[$key]->execute(array_values($clean));
                        $count++;
                    } catch (PDOException $e) {
                        $error_count++;
                        $import_errors[] = "Tabelle '$target', Zeile $row_index: " . $e->getMessage();
                        if ($first_error === null) $first_error = $e->getMessage();
                    }
                }

                $import_stats[$target] = $count;
                if ($dropped) {
                    $import_notes[] = "Tabelle '$target': Spalten nicht im Schema, verworfen: " . implode(', ', array_keys($dropped)) . ' (ggf. Migration aus tools/*.sql ausführen)';
                }
                if ($skipped) {
                    $import_notes[] = "Tabelle '$target': $skipped Zeilen übersprungen (Pflichtspalte leer, Schema ohne passende Migration).";
                }

                if ($count > 0 && $error_count === 0) {
                    echo "<p style='color: green;'>✓ $target: <strong>$count</strong> Datensätze erfolgreich importiert</p>";
                } elseif ($count > 0 && $error_count > 0) {
                    echo "<p style='color: orange;'>⚠ $target: <strong>$count</strong> importiert, <strong style='color:red;'>$error_count Fehler</strong> - Erster Fehler: " . htmlspecialchars(substr($first_error, 0, 100)) . "...</p>";
                } else {
                    echo "<p style='color: red;'>✗ $target: <strong>0</strong> Datensätze importiert";
                    if ($error_count > 0) {
                        echo " (<strong>$error_count Fehler</strong>) - Erster Fehler: " . htmlspecialchars(substr($first_error, 0, 100)) . "...";
                    }
                    echo "</p>";
                }
            }

            // Commit nur wenn Transaktion noch aktiv ist
            try {
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                } else {
                    echo '<div class="warning"><p>⚠️ Warnung: Keine aktive Transaktion zum Committen (möglicherweise durch Fehler beendet)</p></div>';
                }
            } catch (PDOException $e) {
                echo '<div class="error"><p>❌ Fehler beim Commit: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
            }

            // berechtigte aus svmembers ableiten (Anträge, Terminplanung und Meinungsbild lesen diese Tabelle direkt)
            if ($derive_berechtigte) {
                try {
                    $pdo->exec(demo_berechtigte_create_sql());
                    $members = $pdo->query("SELECT * FROM svmembers ORDER BY member_id")->fetchAll(PDO::FETCH_ASSOC);
                    $ins = null; $n = 0;
                    foreach (demo_derive_berechtigte($members) as $b) {
                        if (!$ins) {
                            $ins = $pdo->prepare("INSERT INTO berechtigte (`" . implode('`, `', array_keys($b)) . "`) VALUES (" . implode(', ', array_fill(0, count($b), '?')) . ")");
                        }
                        $ins->execute(array_values($b));
                        $n++;
                    }
                    $import_stats[DEMO_DERIVED_TABLE . ' (abgeleitet)'] = $n;
                    echo "<p style='color: green;'>✓ " . DEMO_DERIVED_TABLE . ": <strong>$n</strong> Zeilen aus svmembers abgeleitet</p>";
                } catch (PDOException $e) {
                    $import_notes[] = 'berechtigte konnte nicht abgeleitet werden: ' . $e->getMessage();
                }
            }

            // Foreign Key Checks wieder aktivieren (am Ende des Imports)
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            // Zeitabhängige Demo-Daten sofort auf heute ausrichten (wie bei jedem Seitenaufruf)
            require_once __DIR__ . '/update_demo_dates.php';
            if (function_exists('update_demo_meeting_dates')) {
                update_demo_meeting_dates($pdo);
                echo '<p style="color: green;">✓ Datumsangaben auf heute ausgerichtet</p>';
            }
            echo '</div>';

            // Fehler anzeigen (falls vorhanden)
            if (!empty($import_errors)) {
                echo '<div class="error">';
                echo '<h3>⚠️ Import-Fehler (' . count($import_errors) . ' Fehler)</h3>';
                echo '<p><strong>Die folgenden Fehler sind beim Import aufgetreten:</strong></p>';

                // Gruppiere Fehler nach Tabelle für bessere Übersicht
                $errors_by_table = [];
                foreach ($import_errors as $error) {
                    if (preg_match('/Tabelle \'([^\']+)\'/', $error, $matches)) {
                        $errors_by_table[$matches[1]][] = $error;
                    } else {
                        $errors_by_table['Andere'][] = $error;
                    }
                }

                foreach ($errors_by_table as $table => $errors) {
                    echo '<details style="margin: 10px 0; border: 1px solid #dc3545; border-radius: 4px; padding: 10px;">';
                    echo '<summary style="cursor: pointer; font-weight: bold; color: #dc3545;">📋 ' . htmlspecialchars($table) . ' (' . count($errors) . ' Fehler)</summary>';
                    echo '<ul style="margin-top: 10px;">';
                    // Zeige maximal erste 10 Fehler pro Tabelle
                    foreach (array_slice($errors, 0, 10) as $error) {
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

            if (!empty($import_notes)) {
                echo '<div class="warning"><h3>ℹ️ Hinweise</h3><ul>';
                foreach ($import_notes as $note) echo '<li>' . htmlspecialchars($note) . '</li>';
                echo '</ul></div>';
            }

            echo '<div class="success">';
            echo '<h3>✅ Import abgeschlossen!</h3>';
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
            echo '<p>Die Datenbank wurde auf den Demo-Stand zurückgesetzt. Alle Demo-Benutzer haben das Passwort <code>' . htmlspecialchars(DEMO_PASSWORD) . '</code> (Anmeldung per Klick auf der Demo-Startseite).</p>';
            echo '<p><a href="../index.php" class="btn">➡️ Zur Anwendung</a></p>';
            echo '</div>';

        } catch (Exception $e) {
            // Im Fehlerfall auch Foreign Keys wieder aktivieren
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Exception $e2) { /* ignorieren */ }

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
