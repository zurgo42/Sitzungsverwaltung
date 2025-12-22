<?php
/**
 * production_reset.php - Produktions-Datenbank-Reset
 *
 * KRITISCHE WARNUNG: Dieses Skript löscht ALLE Daten aus der Produktionsdatenbank!
 * Es sollte nur zur initialen Einrichtung oder für komplette Resets verwendet werden.
 *
 * VERWENDUNG:
 * 1. Nur für Produktionsbetrieb gedacht (leert Tabellen, behält Struktur)
 * 2. Benötigt doppelte Bestätigung
 * 3. Benötigt System-Admin-Passwort
 * 4. Protokolliert den Reset im Admin-Log
 *
 * Erstellt: 2025-12-21
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
    die("❌ Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

// ============================================
// SICHERHEITS-CHECKS
// ============================================

// 1. Passwort-Schutz
session_start();
$password_correct = false;

if (isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === SYSTEM_ADMIN_PASSWORD) {
        $_SESSION['production_reset_auth'] = true;
        // Redirect um Session zu speichern
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $password_error = "❌ Falsches Passwort!";
    }
}

if (isset($_SESSION['production_reset_auth']) && $_SESSION['production_reset_auth'] === true) {
    $password_correct = true;
}

// Wenn nicht authentifiziert, Passwort-Formular anzeigen
if (!$password_correct) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🔒 Produktions-Reset - Authentifizierung</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 20px;
            }
            .login-box {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                max-width: 450px;
                width: 100%;
            }
            h2 {
                margin-top: 0;
                color: #d32f2f;
                font-size: 24px;
            }
            .warning {
                background: #fff3cd;
                border: 2px solid #ffc107;
                color: #856404;
                padding: 15px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
            }
            input[type="password"] {
                width: 100%;
                padding: 12px;
                margin: 10px 0;
                border: 2px solid #ddd;
                border-radius: 6px;
                box-sizing: border-box;
                font-size: 15px;
            }
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
            }
            button {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 16px;
                font-weight: 600;
                transition: transform 0.2s;
            }
            button:hover {
                transform: translateY(-2px);
            }
            .error {
                color: #d32f2f;
                background: #ffebee;
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 15px;
                border-left: 4px solid #d32f2f;
            }
            .info {
                font-size: 13px;
                color: #666;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔒 Produktions-Reset Zugang</h2>

            <div class="warning">
                <strong>⚠️ KRITISCHE FUNKTION</strong><br>
                Dieser Bereich erlaubt das vollständige Zurücksetzen der Produktionsdatenbank.
                <strong>ALLE Daten werden unwiderruflich gelöscht!</strong>
            </div>

            <?php if (isset($password_error)): ?>
                <div class="error"><?php echo htmlspecialchars($password_error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <label for="admin_password" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    System-Admin-Passwort:
                </label>
                <input type="password"
                       name="admin_password"
                       id="admin_password"
                       placeholder="Passwort eingeben"
                       required
                       autofocus>
                <button type="submit">🔓 Anmelden</button>
            </form>

            <div class="info">
                <strong>ℹ️ Hinweis:</strong> Das Passwort ist in der config.php unter
                <code>SYSTEM_ADMIN_PASSWORD</code> definiert.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// RESET-PROZESS
// ============================================

// Beide Checkboxen müssen bestätigt sein
$backup_confirmed = isset($_POST['backup_confirmation']) && $_POST['backup_confirmation'] === 'yes';
$final_confirmed = isset($_POST['final_confirmation']) && $_POST['final_confirmation'] === 'yes';
$confirmed = $backup_confirmed && $final_confirmed;

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>♻️ Produktions-Datenbank Reset</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #d32f2f;
            margin-top: 0;
            font-size: 28px;
        }
        .danger-box {
            background: #ffebee;
            border: 3px solid #d32f2f;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .danger-box h3 {
            margin-top: 0;
            color: #d32f2f;
        }
        .warning-list {
            list-style: none;
            padding: 0;
        }
        .warning-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f5c6cb;
        }
        .warning-list li:last-child {
            border-bottom: none;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .table-list {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        .table-list h4 {
            margin-top: 0;
        }
        .table-list ul {
            column-count: 2;
            column-gap: 20px;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 16px;
            transition: all 0.2s;
        }
        .btn-danger {
            background: #d32f2f;
            color: white;
        }
        .btn-danger:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .confirmation-input {
            padding: 12px;
            font-size: 16px;
            border: 2px solid #d32f2f;
            border-radius: 6px;
            width: 300px;
            font-family: monospace;
        }
        .checkbox-confirm {
            margin: 20px 0;
        }
        .checkbox-confirm label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            cursor: pointer;
        }
        .checkbox-confirm input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .progress {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
        }
        .progress-item {
            padding: 5px 0;
        }
        .progress-item.success::before {
            content: '✅ ';
        }
        .progress-item.error::before {
            content: '❌ ';
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            color: #d32f2f;
        }
        .logout-link {
            display: inline-block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }
        .logout-link:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$confirmed): ?>
            <!-- FINALE BESTÄTIGUNG -->
            <h1>⚠️ Produktions-Datenbank Reset</h1>

            <div class="danger-box">
                <h3>⚠️ KRITISCHE WARNUNG</h3>
                <p><strong>Diese Funktion löscht ALLE Daten aus der Produktionsdatenbank!</strong></p>
                <ul class="warning-list">
                    <li>❌ Alle Sitzungen und Protokolle werden gelöscht</li>
                    <li>❌ Alle Mitgliederdaten werden entfernt</li>
                    <li>❌ Alle TODOs und Aufgaben gehen verloren</li>
                    <li>❌ Alle Umfragen (Termine & Meinungsbilder) werden gelöscht</li>
                    <li>❌ Alle Dokumente-Metadaten werden entfernt</li>
                    <li>❌ Alle Admin-Logs werden gelöscht</li>
                </ul>
                <p style="margin-top: 20px; font-weight: 600; color: #d32f2f;">
                    ⚠️ DIESER VORGANG KANN NICHT RÜCKGÄNGIG GEMACHT WERDEN!
                </p>
            </div>

            <div class="table-list">
                <h4>📋 Folgende 23 Tabellen werden geleert:</h4>
                <ul>
                    <li>svmembers (Mitglieder)</li>
                    <li>svmeetings (Sitzungen)</li>
                    <li>svmeeting_participants</li>
                    <li>svagenda_items, svagenda_comments</li>
                    <li>svprotocols, svprotocol_change_requests</li>
                    <li>svtodos, svtodo_log</li>
                    <li>svadmin_log</li>
                    <li>svpolls, svpoll_dates, svpoll_participants, svpoll_responses</li>
                    <li>svopinion_polls, svopinion_poll_options, svopinion_poll_participants</li>
                    <li>svopinion_responses, svopinion_response_options</li>
                    <li>svexternal_participants</li>
                    <li>svdocuments, svdocument_downloads</li>
                    <li>svmail_queue</li>
                </ul>
            </div>

            <div class="info-box">
                <strong>ℹ️ Was bleibt erhalten:</strong>
                <ul>
                    <li>✅ Die Tabellenstruktur bleibt erhalten</li>
                    <li>✅ Antwortvorlagen (svopinion_answer_templates) bleiben erhalten</li>
                    <li>✅ Dokumentdateien auf dem Server bleiben physisch erhalten</li>
                </ul>
            </div>

            <div class="info-box">
                <strong>💡 Empfehlung vor dem Reset:</strong>
                <ol>
                    <li>Erstelle ein Backup der Datenbank (z.B. mit phpMyAdmin oder mysqldump)</li>
                    <li>Stelle sicher, dass alle wichtigen Daten exportiert wurden</li>
                    <li>Informiere alle Benutzer über die bevorstehende Löschung</li>
                </ol>
            </div>

            <form method="POST">

                <div class="checkbox-confirm">
                    <label>
                        <input type="checkbox" name="backup_confirmation" value="yes" required>
                        Ich habe ein Backup erstellt
                    </label>
                </div>

                <div class="checkbox-confirm">
                    <label>
                        <input type="checkbox" name="final_confirmation" value="yes" required>
                        <strong style="color: #d32f2f;">
                            JA, LÖSCHE JETZT ALLE DATEN UNWIDERRUFLICH!
                        </strong>
                    </label>
                </div>

                <br>
                <button type="submit" class="btn btn-danger" style="font-size: 18px; padding: 16px 32px;">
                    🗑️ DATENBANK JETZT ZURÜCKSETZEN
                </button>
                <a href="../index.php?tab=admin" class="btn btn-secondary" style="margin-left: 10px;">
                    ← Abbrechen
                </a>
            </form>

        <?php else: ?>
            <!-- SCHRITT 3: Reset durchführen -->
            <h1>♻️ Reset wird durchgeführt...</h1>

            <div class="progress">
                <?php
                $errors = [];
                $success_count = 0;

                // Tabellen in der richtigen Reihenfolge leeren (wegen Foreign Keys)
                $tables_to_truncate = [
                    // Abhängige Tabellen zuerst
                    'svopinion_response_options',
                    'svopinion_responses',
                    'svopinion_poll_participants',
                    'svopinion_poll_options',
                    'svopinion_polls',
                    'svpoll_responses',
                    'svpoll_participants',
                    'svpoll_dates',
                    'svpolls',
                    'svexternal_participants',
                    'svdocument_downloads',
                    'svdocuments',
                    'svtodo_log',
                    'svtodos',
                    'svprotocol_change_requests',
                    'svprotocols',
                    'svagenda_comments',
                    'svagenda_items',
                    'svmeeting_participants',
                    'svmeetings',
                    'svmail_queue',
                    'svadmin_log',
                    'svmembers'
                ];

                // Foreign Key Checks deaktivieren
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    echo '<div class="progress-item success">Foreign Key Checks deaktiviert</div>';
                } catch (Exception $e) {
                    echo '<div class="progress-item error">Fehler beim Deaktivieren der Foreign Key Checks: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $errors[] = $e->getMessage();
                }

                // Tabellen leeren
                foreach ($tables_to_truncate as $table) {
                    try {
                        $pdo->exec("TRUNCATE TABLE `$table`");
                        echo '<div class="progress-item success">Tabelle ' . htmlspecialchars($table) . ' geleert</div>';
                        $success_count++;
                    } catch (Exception $e) {
                        echo '<div class="progress-item error">Fehler bei ' . htmlspecialchars($table) . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                        $errors[] = "Tabelle $table: " . $e->getMessage();
                    }
                }

                // Foreign Key Checks wieder aktivieren
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    echo '<div class="progress-item success">Foreign Key Checks wieder aktiviert</div>';
                } catch (Exception $e) {
                    echo '<div class="progress-item error">Fehler beim Aktivieren der Foreign Key Checks: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $errors[] = $e->getMessage();
                }

                // Log-Eintrag (nur wenn svadmin_log existiert und leer ist)
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO svadmin_log (member_id, action, details, ip_address)
                        VALUES (NULL, 'database_reset', 'Produktionsdatenbank wurde komplett zurückgesetzt', ?)
                    ");
                    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                    echo '<div class="progress-item success">Reset im Admin-Log protokolliert</div>';
                } catch (Exception $e) {
                    // Ignorieren, da Admin-Log gerade geleert wurde
                    echo '<div class="progress-item">Admin-Log konnte nicht aktualisiert werden (Tabelle wurde geleert)</div>';
                }

                // Zusammenfassung
                echo '<br>';
                if (empty($errors)) {
                    echo '<div class="progress-item success" style="font-weight: 600; font-size: 16px; padding: 10px 0;">';
                    echo 'Alle ' . $success_count . ' Tabellen erfolgreich geleert! ✅';
                    echo '</div>';
                } else {
                    echo '<div class="progress-item error" style="font-weight: 600; font-size: 16px; padding: 10px 0;">';
                    echo count($errors) . ' Fehler aufgetreten! ❌';
                    echo '</div>';
                }
                ?>
            </div>

            <?php if (empty($errors)): ?>
                <div class="success-box">
                    <h3 style="margin-top: 0;">✅ Reset erfolgreich abgeschlossen!</h3>
                    <p><strong>Die Datenbank wurde vollständig zurückgesetzt.</strong></p>
                    <p>Nächste Schritte:</p>
                    <ol>
                        <li>Erstelle einen ersten Admin-Benutzer</li>
                        <li>Konfiguriere die Grundeinstellungen</li>
                        <li>Importiere ggf. Mitgliederdaten</li>
                        <li>Teste alle Funktionen</li>
                    </ol>
                </div>
            <?php else: ?>
                <div class="danger-box">
                    <h3>⚠️ Es sind Fehler aufgetreten</h3>
                    <p>Folgende Probleme wurden festgestellt:</p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p>Bitte prüfe die Datenbankstruktur und versuche es erneut.</p>
                </div>
            <?php endif; ?>

            <a href="../index.php" class="btn btn-secondary">
                🏠 Zur Startseite
            </a>

            <?php
            // Session löschen
            unset($_SESSION['production_reset_auth']);
            ?>
        <?php endif; ?>

        <?php if (!$final_confirmed): ?>
            <a href="?logout=1" class="logout-link" onclick="<?php unset($_SESSION['production_reset_auth']); ?>">
                🔒 Abmelden
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
// Logout-Funktionalität
if (isset($_GET['logout'])) {
    unset($_SESSION['production_reset_auth']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
