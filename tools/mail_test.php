<?php
/**
 * mail_test.php - Test-Skript für E-Mail-Versand
 *
 * Testet alle drei Mail-Backends:
 * - PHP mail() (Standard)
 * - PHPMailer (SMTP)
 * - Queue (Datenbank + Cronjob)
 *
 * VERWENDUNG:
 * 1. Mailadressen in das Formular eintragen (eine pro Zeile)
 * 2. Backend(s) auswählen
 * 3. Test starten
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mail_functions.php';

// PDO-Verbindung für Queue-Test
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

$test_started = isset($_POST['test_mails']);
$recipients = [];
$backends_to_test = [];
$test_results = [];

if ($test_started) {
    // Empfänger-Adressen einlesen (eine pro Zeile)
    $recipient_input = trim($_POST['recipients'] ?? '');
    $recipients = array_filter(array_map('trim', explode("\n", $recipient_input)));

    // Backends zum Testen
    $backends_to_test = $_POST['backends'] ?? [];
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail-Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .config-info {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 20px;
        }
        .config-info h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .config-item {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 10px;
            padding: 5px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        .config-item:last-child {
            border-bottom: none;
        }
        .config-label {
            font-weight: bold;
            color: #34495e;
        }
        .config-value {
            font-family: monospace;
            color: #27ae60;
        }
        .config-value.disabled {
            color: #e74c3c;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #bdc3c7;
            border-radius: 5px;
            font-size: 14px;
            font-family: monospace;
            box-sizing: border-box;
        }
        textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        .backend-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .backend-option {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .backend-option:hover {
            border-color: #3498db;
            background: #d5dbdb;
        }
        .backend-option input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.3);
        }
        .backend-option label {
            cursor: pointer;
            font-weight: normal !important;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-secondary {
            background: #95a5a6;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        .result {
            margin-top: 30px;
        }
        .result-item {
            background: white;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 5px solid #95a5a6;
        }
        .result-item.success {
            border-left-color: #27ae60;
            background: #d5f4e6;
        }
        .result-item.error {
            border-left-color: #e74c3c;
            background: #fadbd8;
        }
        .result-item h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .result-details {
            font-family: monospace;
            font-size: 13px;
            color: #34495e;
            white-space: pre-wrap;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }
        .queue-info {
            background: #fff3cd;
            padding: 10px;
            border-radius: 3px;
            margin-top: 10px;
            font-size: 14px;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <h1>📧 E-Mail-Test für Meeting-System</h1>

    <!-- Aktuelle Konfiguration -->
    <div class="card">
        <div class="config-info">
            <h3>🔧 Aktuelle Konfiguration (aus config.php)</h3>

            <div class="config-item">
                <div class="config-label">MAIL_ENABLED:</div>
                <div class="config-value <?php echo (!defined('MAIL_ENABLED') || !MAIL_ENABLED) ? 'disabled' : ''; ?>">
                    <?php echo defined('MAIL_ENABLED') && MAIL_ENABLED ? 'true ✓' : 'false ✗'; ?>
                </div>
            </div>

            <div class="config-item">
                <div class="config-label">MAIL_BACKEND:</div>
                <div class="config-value">
                    <?php echo defined('MAIL_BACKEND') ? MAIL_BACKEND : 'mail (Standard)'; ?>
                </div>
            </div>

            <div class="config-item">
                <div class="config-label">MAIL_FROM:</div>
                <div class="config-value">
                    <?php echo defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com (Standard)'; ?>
                </div>
            </div>

            <div class="config-item">
                <div class="config-label">MAIL_FROM_NAME:</div>
                <div class="config-value">
                    <?php echo defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Meeting-System (Standard)'; ?>
                </div>
            </div>

            <?php if (defined('SMTP_HOST')): ?>
            <div class="config-item">
                <div class="config-label">SMTP_HOST:</div>
                <div class="config-value"><?php echo SMTP_HOST; ?></div>
            </div>

            <div class="config-item">
                <div class="config-label">SMTP_PORT:</div>
                <div class="config-value"><?php echo defined('SMTP_PORT') ? SMTP_PORT : '587 (Standard)'; ?></div>
            </div>

            <div class="config-item">
                <div class="config-label">SMTP_SECURE:</div>
                <div class="config-value"><?php echo defined('SMTP_SECURE') ? SMTP_SECURE : 'tls (Standard)'; ?></div>
            </div>

            <div class="config-item">
                <div class="config-label">SMTP_AUTH:</div>
                <div class="config-value"><?php echo defined('SMTP_AUTH') && SMTP_AUTH ? 'true ✓' : 'false'; ?></div>
            </div>

            <div class="config-item">
                <div class="config-label">SMTP_USER:</div>
                <div class="config-value"><?php echo defined('SMTP_USER') ? (SMTP_USER ?: '(leer)') : '(nicht gesetzt)'; ?></div>
            </div>

            <div class="config-item">
                <div class="config-label">SMTP_PASS:</div>
                <div class="config-value"><?php echo defined('SMTP_PASS') && !empty(SMTP_PASS) ? '••••••••• (gesetzt)' : '(nicht gesetzt)'; ?></div>
            </div>
            <?php else: ?>
            <div class="warning">
                ℹ️ SMTP-Einstellungen sind nicht in config.php definiert. PHPMailer wird auf sendmail() zurückfallen.
            </div>
            <?php endif; ?>

            <?php
            // PHPMailer verfügbar?
            $phpmailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');
            if (!$phpmailer_available && file_exists(__DIR__ . '/../vendor/autoload.php')) {
                require_once __DIR__ . '/../vendor/autoload.php';
                $phpmailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');
            }
            ?>

            <div class="config-item">
                <div class="config-label">PHPMailer Library:</div>
                <div class="config-value <?php echo !$phpmailer_available ? 'disabled' : ''; ?>">
                    <?php echo $phpmailer_available ? 'Installiert ✓' : 'Nicht verfügbar ✗'; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$test_started): ?>
    <!-- Formular -->
    <div class="card">
        <h3>📝 Test-Parameter</h3>

        <form method="post">
            <div class="form-group">
                <label>Empfänger-E-Mail-Adressen (eine pro Zeile):</label>
                <textarea name="recipients" rows="6" placeholder="test@example.com&#10;admin@example.com&#10;user@example.com" required></textarea>
                <small style="color: #7f8c8d;">Tipp: Verwenden Sie Ihre eigenen E-Mail-Adressen für den Test</small>
            </div>

            <div class="form-group">
                <label>Zu testende Backends:</label>
                <div class="backend-options">
                    <div class="backend-option">
                        <input type="checkbox" name="backends[]" value="mail" id="backend_mail" checked>
                        <label for="backend_mail">
                            <strong>PHP mail()</strong><br>
                            <small>Standard-Backend</small>
                        </label>
                    </div>

                    <div class="backend-option">
                        <input type="checkbox" name="backends[]" value="phpmailer" id="backend_phpmailer" <?php echo $phpmailer_available ? 'checked' : ''; ?>>
                        <label for="backend_phpmailer">
                            <strong>PHPMailer (SMTP)</strong><br>
                            <small><?php echo $phpmailer_available ? 'Verfügbar' : 'Nicht verfügbar'; ?></small>
                        </label>
                    </div>

                    <div class="backend-option">
                        <input type="checkbox" name="backends[]" value="queue" id="backend_queue" checked>
                        <label for="backend_queue">
                            <strong>Queue (DB + Cronjob)</strong><br>
                            <small>Verzögerter Versand</small>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" name="test_mails" class="btn">🚀 Tests starten</button>
            <a href="../index.php" class="btn btn-secondary">← Zurück</a>
        </form>
    </div>

    <?php else: ?>
    <!-- Test-Ergebnisse -->
    <div class="card result">
        <h3>📊 Test-Ergebnisse</h3>

        <?php if (empty($recipients)): ?>
            <div class="error">
                <strong>Fehler:</strong> Keine Empfänger angegeben!
            </div>
        <?php elseif (empty($backends_to_test)): ?>
            <div class="error">
                <strong>Fehler:</strong> Kein Backend ausgewählt!
            </div>
        <?php else: ?>
            <p><strong>Empfänger:</strong> <?php echo count($recipients); ?> E-Mail-Adresse(n)</p>
            <p><strong>Backends:</strong> <?php echo implode(', ', $backends_to_test); ?></p>
            <hr>

            <?php
            // Temporär MAIL_ENABLED aktivieren für Tests
            $original_mail_enabled = defined('MAIL_ENABLED') ? MAIL_ENABLED : false;
            if (!defined('MAIL_ENABLED')) {
                define('MAIL_ENABLED', true);
            }

            // Jedes Backend testen
            foreach ($backends_to_test as $backend) {
                echo "<h4 style='margin-top: 30px; color: #2c3e50;'>Backend: <code>$backend</code></h4>";

                // Temporär Backend wechseln
                $original_backend = defined('MAIL_BACKEND') ? MAIL_BACKEND : 'mail';
                if (defined('MAIL_BACKEND')) {
                    // PHP doesn't allow redefining constants, so we need a workaround
                    // We'll modify the global state temporarily
                }

                foreach ($recipients as $idx => $recipient) {
                    $recipient = trim($recipient);
                    if (empty($recipient)) continue;

                    echo "<div class='result-item'>";
                    echo "<h4>Test #" . ($idx + 1) . ": $recipient</h4>";

                    $test_subject = "Test-Mail ($backend) - " . date('H:i:s');
                    $test_text = "Dies ist eine Test-E-Mail vom Meeting-System.\n\n";
                    $test_text .= "Backend: $backend\n";
                    $test_text .= "Zeitstempel: " . date('Y-m-d H:i:s') . "\n";
                    $test_text .= "Absender: " . (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com') . "\n";

                    $test_html = "<html><body style='font-family: Arial, sans-serif;'>";
                    $test_html .= "<h2 style='color: #3498db;'>Test-E-Mail</h2>";
                    $test_html .= "<p>Dies ist eine <strong>Test-E-Mail</strong> vom Meeting-System.</p>";
                    $test_html .= "<table style='border-collapse: collapse; margin-top: 20px;'>";
                    $test_html .= "<tr><td style='padding: 5px; font-weight: bold;'>Backend:</td><td style='padding: 5px;'>$backend</td></tr>";
                    $test_html .= "<tr><td style='padding: 5px; font-weight: bold;'>Zeitstempel:</td><td style='padding: 5px;'>" . date('Y-m-d H:i:s') . "</td></tr>";
                    $test_html .= "<tr><td style='padding: 5px; font-weight: bold;'>Absender:</td><td style='padding: 5px;'>" . (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com') . "</td></tr>";
                    $test_html .= "</table>";
                    $test_html .= "</body></html>";

                    // Backend-spezifischer Test
                    $success = false;
                    $error_msg = '';

                    try {
                        switch ($backend) {
                            case 'mail':
                                $success = send_via_mail($recipient, $test_subject, $test_text, $test_html,
                                    defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com',
                                    defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Meeting-System'
                                );
                                break;

                            case 'phpmailer':
                                $success = send_via_phpmailer($recipient, $test_subject, $test_text, $test_html,
                                    defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com',
                                    defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Meeting-System'
                                );
                                break;

                            case 'queue':
                                $success = queue_mail($recipient, $test_subject, $test_text, $test_html,
                                    defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com',
                                    defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Meeting-System'
                                );
                                break;
                        }
                    } catch (Exception $e) {
                        $error_msg = $e->getMessage();
                        $success = false;
                    }

                    if ($success) {
                        echo "<div class='success'>";
                        echo "✅ <strong>Erfolgreich!</strong> E-Mail wurde ";
                        if ($backend === 'queue') {
                            echo "zur Warteschlange hinzugefügt.";
                            echo "<div class='queue-info'>";
                            echo "ℹ️ Die E-Mail wurde in die <code>mail_queue</code> Tabelle eingetragen.<br>";
                            echo "Zum Versenden führen Sie aus: <code>php process_cronjob.php</code>";
                            echo "</div>";
                        } else {
                            echo "versendet.";
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='error'>";
                        echo "❌ <strong>Fehler!</strong> E-Mail konnte nicht versendet werden.";
                        if (!empty($error_msg)) {
                            echo "<pre>" . htmlspecialchars($error_msg) . "</pre>";
                        }
                        echo "</div>";
                    }

                    echo "<div class='result-details'>";
                    echo "Empfänger: $recipient\n";
                    echo "Betreff: $test_subject\n";
                    echo "Backend: $backend\n";
                    echo "Zeitstempel: " . date('Y-m-d H:i:s');
                    echo "</div>";

                    echo "</div>";
                }
            }

            // Queue-Status anzeigen
            if (in_array('queue', $backends_to_test)) {
                echo "<h4 style='margin-top: 30px; color: #2c3e50;'>📦 Warteschlangen-Status</h4>";

                $stmt = $pdo->query("
                    SELECT status, COUNT(*) as count
                    FROM mail_queue
                    GROUP BY status
                    ORDER BY status
                ");
                $queue_stats = $stmt->fetchAll();

                if (empty($queue_stats)) {
                    echo "<p>Die Warteschlange ist leer.</p>";
                } else {
                    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
                    echo "<tr style='background: #ecf0f1;'>";
                    echo "<th style='padding: 10px; text-align: left; border: 1px solid #bdc3c7;'>Status</th>";
                    echo "<th style='padding: 10px; text-align: left; border: 1px solid #bdc3c7;'>Anzahl</th>";
                    echo "</tr>";
                    foreach ($queue_stats as $stat) {
                        echo "<tr>";
                        echo "<td style='padding: 10px; border: 1px solid #bdc3c7;'><code>" . htmlspecialchars($stat['status']) . "</code></td>";
                        echo "<td style='padding: 10px; border: 1px solid #bdc3c7;'>" . $stat['count'] . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";

                    echo "<div class='warning' style='margin-top: 15px;'>";
                    echo "<strong>📌 Nächster Schritt:</strong><br>";
                    echo "Zum Versenden der Queue-Mails führen Sie aus:<br>";
                    echo "<code>php " . __DIR__ . "/../process_cronjob.php</code>";
                    echo "</div>";
                }
            }
            ?>

            <hr style="margin-top: 30px;">
            <a href="?" class="btn">🔄 Neuer Test</a>
            <a href="../index.php" class="btn btn-secondary">← Zurück zur Anwendung</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Hinweise -->
    <?php if (!$test_started): ?>
    <div class="card">
        <h3>💡 Hinweise</h3>
        <ul>
            <li><strong>PHP mail():</strong> Funktioniert auf den meisten Servern out-of-the-box. Nutzt sendmail oder die in php.ini konfigurierte Methode.</li>
            <li><strong>PHPMailer (SMTP):</strong> Benötigt SMTP-Zugangsdaten in config.php. Empfohlen für produktive Systeme.</li>
            <li><strong>Queue:</strong> Speichert E-Mails in der Datenbank. Versand erfolgt asynchron via Cronjob (process_cronjob.php).</li>
            <li>Prüfen Sie nach dem Test Ihren Posteingang (auch Spam-Ordner!).</li>
            <li>Bei Problemen mit mail(): Kontaktieren Sie Ihren Hosting-Provider.</li>
        </ul>
    </div>
    <?php endif; ?>
</body>
</html>
