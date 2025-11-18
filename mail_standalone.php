<?php
/**
 * mail_standalone.php - Standalone Mail-System-Wrapper
 * Erstellt: 18.11.2025
 *
 * VERWENDUNG:
 * ===========
 *
 * In der Sitzungsverwaltung:
 * - Automatisch über mail_functions.php integriert
 * - Nutzt config.php für Konfiguration
 *
 * In anderen Anwendungen (Standalone):
 * - Als eigenständiges Script verwendbar:
 *   <?php
 *     require_once 'pfad/zu/mail_standalone.php';
 *
 *     // Minimale Konfiguration
 *     $mail_config = [
 *         'enabled' => true,
 *         'backend' => 'mail',  // 'mail', 'phpmailer', 'queue'
 *         'from_email' => 'noreply@example.com',
 *         'from_name' => 'Meeting-System'
 *     ];
 *
 *     // Mail senden
 *     $result = mail_standalone_send(
 *         'empfaenger@example.com',
 *         'Test-Betreff',
 *         'Text-Version der Nachricht',
 *         '<p>HTML-Version der Nachricht</p>',
 *         $mail_config
 *     );
 *   ?>
 *
 * - Via Web-Interface (Test-Oberfläche):
 *   https://example.com/mail_standalone.php
 *   Bietet Test-Interface für Mail-Versand und Queue-Verwaltung
 *
 * STANDALONE-KONFIGURATION:
 * =========================
 * Datei: mail_standalone_config.php (wird automatisch erstellt)
 * Enthält:
 * - Datenbank-Zugangsdaten (für Queue)
 * - Mail-Backend-Einstellungen
 * - SMTP-Konfiguration
 *
 * FEATURES:
 * =========
 * - Multi-Backend-Support (mail, PHPMailer, Queue)
 * - Web-Interface für Tests
 * - Queue-Verwaltung (wenn Queue-Backend aktiviert)
 * - Minimale Abhängigkeiten
 * - Portabel zwischen verschiedenen Servern
 */

// ============================================
// UMGEBUNGS-ERKENNUNG
// ============================================

// Session starten falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prüfen ob wir in der Sitzungsverwaltung sind
$is_sitzungsverwaltung = file_exists(__DIR__ . '/config.php') && file_exists(__DIR__ . '/mail_functions.php');

// ============================================
// KONFIGURATION LADEN
// ============================================

if ($is_sitzungsverwaltung) {
    // In Sitzungsverwaltung: Nutze config.php
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/mail_functions.php';

    // Falls $pdo noch nicht existiert, aus functions.php laden
    if (!isset($pdo) && file_exists(__DIR__ . '/functions.php')) {
        require_once __DIR__ . '/functions.php';
    }

} else {
    // Standalone-Modus: Eigene Konfiguration

    // Prüfen ob Konfig-Datei existiert, sonst erstellen
    $config_file = __DIR__ . '/mail_standalone_config.php';

    if (!file_exists($config_file)) {
        // Standard-Konfiguration erstellen
        $default_config = '<?php
/**
 * mail_standalone_config.php - Standalone Mail-System Konfiguration
 * Automatisch erstellt durch mail_standalone.php
 */

// ============= E-MAIL-EINSTELLUNGEN =============
define(\'MAIL_ENABLED\', true);  // E-Mail-Versand aktivieren/deaktivieren
define(\'MAIL_FROM\', \'noreply@example.com\');
define(\'MAIL_FROM_NAME\', \'Meeting-System\');

// Mail-Backend auswählen:
// - \'mail\':      Standard PHP mail() - funktioniert überall
// - \'phpmailer\': PHPMailer library - besser für SMTP
// - \'queue\':     Speichert Mails in Datenbank, Versand via Cronjob
define(\'MAIL_BACKEND\', \'mail\');

// ============= SMTP-EINSTELLUNGEN (nur für MAIL_BACKEND=\'phpmailer\') =============
define(\'SMTP_HOST\', \'\');           // z.B. \'smtp.example.com\'
define(\'SMTP_PORT\', 587);          // 587 (TLS) oder 465 (SSL)
define(\'SMTP_SECURE\', \'tls\');      // \'tls\', \'ssl\' oder \'\'
define(\'SMTP_AUTH\', false);        // true wenn SMTP-Auth erforderlich
define(\'SMTP_USER\', \'\');           // SMTP-Benutzername
define(\'SMTP_PASS\', \'\');           // SMTP-Passwort

// ============= DATENBANK-EINSTELLUNGEN (nur für MAIL_BACKEND=\'queue\') =============
define(\'DB_HOST\', \'localhost\');
define(\'DB_USER\', \'root\');
define(\'DB_PASS\', \'\');
define(\'DB_NAME\', \'mail_queue_db\');

// ============= QUEUE-EINSTELLUNGEN =============
define(\'MAIL_QUEUE_BATCH_SIZE\', 10);
define(\'MAIL_QUEUE_DELAY\', 1);
define(\'MAIL_QUEUE_MAX_ATTEMPTS\', 3);

// ============= ADMIN-ZUGANG (für Web-Interface) =============
define(\'MAIL_ADMIN_USER\', \'admin\');
define(\'MAIL_ADMIN_PASS\', \'changeme\');  // BITTE ÄNDERN!

?>';

        file_put_contents($config_file, $default_config);
        chmod($config_file, 0600); // Nur Owner kann lesen/schreiben
    }

    // Konfiguration laden
    require_once $config_file;

    // Mail-Funktionen einbinden (inline, da mail_functions.php config.php benötigt)
    // Wir definieren hier die Hauptfunktionen neu

    /**
     * Sendet eine HTML-E-Mail (multipart: text + HTML) - STANDALONE VERSION
     */
    function mail_standalone_send($to, $subject, $message_text, $message_html = '', $config = null) {
        // Config überschreibt defines
        $enabled = $config['enabled'] ?? (defined('MAIL_ENABLED') ? MAIL_ENABLED : true);
        $from_email = $config['from_email'] ?? (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com');
        $from_name = $config['from_name'] ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Mail-System');
        $backend = $config['backend'] ?? (defined('MAIL_BACKEND') ? MAIL_BACKEND : 'mail');

        if (!$enabled) {
            error_log("Mail-Versand deaktiviert: An $to - $subject");
            return true;
        }

        // HTML-Version generieren falls nicht vorhanden
        if (empty($message_html)) {
            $message_html = nl2br(htmlspecialchars($message_text));
        }

        // Backend-spezifischer Versand
        switch ($backend) {
            case 'phpmailer':
                return mail_standalone_send_phpmailer($to, $subject, $message_text, $message_html, $from_email, $from_name, $config);

            case 'queue':
                return mail_standalone_queue($to, $subject, $message_text, $message_html, $from_email, $from_name);

            case 'mail':
            default:
                return mail_standalone_send_native($to, $subject, $message_text, $message_html, $from_email, $from_name);
        }
    }

    /**
     * Sendet E-Mail via PHP mail() - Standalone Version
     */
    function mail_standalone_send_native($to, $subject, $message_text, $message_html, $from_email, $from_name) {
        $boundary = md5(uniqid(time()));

        $headers = [];
        $headers[] = "From: $from_name <$from_email>";
        $headers[] = "Reply-To: $from_email";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
        $headers[] = "X-Mailer: PHP/" . phpversion();

        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $message_text . "\r\n\r\n";

        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $message_html . "\r\n\r\n";

        $body .= "--$boundary--";

        $result = mail($to, $subject, $body, implode("\r\n", $headers));

        if (!$result) {
            error_log("Mail-Versand (mail) fehlgeschlagen: An $to - $subject");
        }

        return $result;
    }

    /**
     * Sendet E-Mail via PHPMailer - Standalone Version
     */
    function mail_standalone_send_phpmailer($to, $subject, $message_text, $message_html, $from_email, $from_name, $config = null) {
        // Prüfen ob PHPMailer verfügbar
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $phpmailer_path = __DIR__ . '/vendor/autoload.php';
            if (file_exists($phpmailer_path)) {
                require_once $phpmailer_path;
            }
        }

        // Fallback auf mail()
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log("PHPMailer nicht verfügbar - Fallback auf mail()");
            return mail_standalone_send_native($to, $subject, $message_text, $message_html, $from_email, $from_name);
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // SMTP-Konfiguration
            $smtp_host = $config['smtp_host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : '');
            if (!empty($smtp_host)) {
                $mail->isSMTP();
                $mail->Host = $smtp_host;
                $mail->Port = $config['smtp_port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 587);
                $mail->SMTPSecure = $config['smtp_secure'] ?? (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls');

                $smtp_auth = $config['smtp_auth'] ?? (defined('SMTP_AUTH') ? SMTP_AUTH : false);
                if ($smtp_auth) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $config['smtp_user'] ?? (defined('SMTP_USER') ? SMTP_USER : '');
                    $mail->Password = $config['smtp_pass'] ?? (defined('SMTP_PASS') ? SMTP_PASS : '');
                }
            }

            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->addReplyTo($from_email, $from_name);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $message_html;
            $mail->AltBody = $message_text;

            return $mail->send();

        } catch (Exception $e) {
            error_log("PHPMailer Exception: " . $e->getMessage());
            return mail_standalone_send_native($to, $subject, $message_text, $message_html, $from_email, $from_name);
        }
    }

    /**
     * Fügt E-Mail zur Queue hinzu - Standalone Version
     */
    function mail_standalone_queue($to, $subject, $message_text, $message_html, $from_email, $from_name) {
        global $pdo;

        // PDO Connection erstellen falls nicht vorhanden
        if (!isset($pdo) || !$pdo) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            } catch (PDOException $e) {
                error_log("Queue-Mail: PDO-Verbindung fehlgeschlagen - Fallback auf mail()");
                return mail_standalone_send_native($to, $subject, $message_text, $message_html, $from_email, $from_name);
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO mail_queue
                (recipient, subject, message_text, message_html, from_email, from_name, status, created_at, priority)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), 5)
            ");

            $result = $stmt->execute([
                $to,
                $subject,
                $message_text,
                $message_html,
                $from_email,
                $from_name
            ]);

            if (!$result) {
                error_log("Queue-Mail: INSERT fehlgeschlagen - Fallback auf mail()");
                return mail_standalone_send_native($to, $subject, $message_text, $message_html, $from_email, $from_name);
            }

            return true;

        } catch (Exception $e) {
            error_log("Queue-Mail Exception: " . $e->getMessage() . " - Fallback auf mail()");
            return mail_standalone_send_native($to, $subject, $message_text, $message_html, $from_email, $from_name);
        }
    }
}

// ============================================
// WEB-INTERFACE (nur wenn direkt aufgerufen)
// ============================================

// Nur Web-Interface anzeigen wenn direkt aufgerufen (nicht per include)
if (basename($_SERVER['PHP_SELF']) === 'mail_standalone.php' && !isset($GLOBALS['MAIL_STANDALONE_INCLUDED'])) {

    // Admin-Authentifizierung
    $is_authenticated = false;

    if (isset($_POST['login'])) {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        $admin_user = defined('MAIL_ADMIN_USER') ? MAIL_ADMIN_USER : 'admin';
        $admin_pass = defined('MAIL_ADMIN_PASS') ? MAIL_ADMIN_PASS : 'changeme';

        if ($user === $admin_user && $pass === $admin_pass) {
            $_SESSION['mail_admin_authenticated'] = true;
            $is_authenticated = true;
        } else {
            $login_error = "Ungültige Zugangsdaten";
        }
    }

    if (isset($_SESSION['mail_admin_authenticated']) && $_SESSION['mail_admin_authenticated'] === true) {
        $is_authenticated = true;
    }

    if (isset($_GET['logout'])) {
        unset($_SESSION['mail_admin_authenticated']);
        $is_authenticated = false;
    }

    // ============================================
    // HTML OUTPUT
    // ============================================

    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail-System Standalone</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 30px; }

        .card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2 {
            color: #495057;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea {
            min-height: 120px;
            font-family: inherit;
            resize: vertical;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }

        .message {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .message.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box strong { color: #1976D2; }

        .config-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .config-table th,
        .config-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .config-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .config-table tr:last-child td {
            border-bottom: none;
        }

        .login-box {
            max-width: 400px;
            margin: 50px auto;
        }

        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.enabled {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.disabled {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Mail-System Standalone</h1>
            <p>Test-Interface & Queue-Verwaltung</p>
        </div>

        <?php if (!$is_authenticated): ?>
            <!-- LOGIN FORM -->
            <div class="content">
                <div class="login-box card">
                    <h2>Anmeldung erforderlich</h2>

                    <?php if (isset($login_error)): ?>
                        <div class="message error">❌ <?= htmlspecialchars($login_error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>Benutzername</label>
                            <input type="text" name="username" required autofocus>
                        </div>
                        <div class="form-group">
                            <label>Passwort</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary">Anmelden</button>
                    </form>

                    <div class="info-box" style="margin-top: 20px;">
                        <strong>Hinweis:</strong> Standard-Zugangsdaten sind in <code>mail_standalone_config.php</code> definiert.
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- AUTHENTICATED CONTENT -->
            <div class="content">

                <?php
                // ============================================
                // POST HANDLING
                // ============================================

                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_mail'])) {
                    $to = $_POST['to'] ?? '';
                    $subject = $_POST['subject'] ?? 'Test-Mail vom Standalone Mail-System';
                    $message_text = $_POST['message_text'] ?? '';
                    $message_html = $_POST['message_html'] ?? '';

                    if (empty($to) || empty($message_text)) {
                        echo '<div class="message error">❌ Empfänger und Text-Nachricht sind Pflichtfelder</div>';
                    } else {
                        $config = [
                            'enabled' => true,
                            'backend' => $_POST['backend'] ?? MAIL_BACKEND,
                            'from_email' => $_POST['from_email'] ?? MAIL_FROM,
                            'from_name' => $_POST['from_name'] ?? MAIL_FROM_NAME
                        ];

                        if ($is_sitzungsverwaltung) {
                            // In Sitzungsverwaltung: multipartmail nutzen
                            $result = multipartmail($to, $subject, $message_text, $message_html, $config['from_email'], $config['from_name']);
                        } else {
                            // Standalone: mail_standalone_send nutzen
                            $result = mail_standalone_send($to, $subject, $message_text, $message_html, $config);
                        }

                        if ($result) {
                            echo '<div class="message success">✅ Test-Mail erfolgreich versendet an ' . htmlspecialchars($to) . '</div>';
                        } else {
                            echo '<div class="message error">❌ Fehler beim Versenden der Test-Mail. Prüfen Sie die Logs.</div>';
                        }
                    }
                }
                ?>

                <!-- SYSTEM STATUS -->
                <div class="card">
                    <h2>📊 System-Status</h2>

                    <table class="config-table">
                        <tr>
                            <th>Einstellung</th>
                            <th>Wert</th>
                        </tr>
                        <tr>
                            <td>Modus</td>
                            <td><strong><?= $is_sitzungsverwaltung ? 'Integriert (Sitzungsverwaltung)' : 'Standalone' ?></strong></td>
                        </tr>
                        <tr>
                            <td>Mail-Versand</td>
                            <td>
                                <?php if (defined('MAIL_ENABLED') && MAIL_ENABLED): ?>
                                    <span class="status-badge enabled">Aktiviert</span>
                                <?php else: ?>
                                    <span class="status-badge disabled">Deaktiviert</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Backend</td>
                            <td><code><?= defined('MAIL_BACKEND') ? MAIL_BACKEND : 'mail' ?></code></td>
                        </tr>
                        <tr>
                            <td>Absender-E-Mail</td>
                            <td><?= defined('MAIL_FROM') ? htmlspecialchars(MAIL_FROM) : 'nicht konfiguriert' ?></td>
                        </tr>
                        <tr>
                            <td>Absender-Name</td>
                            <td><?= defined('MAIL_FROM_NAME') ? htmlspecialchars(MAIL_FROM_NAME) : 'nicht konfiguriert' ?></td>
                        </tr>
                        <?php if (defined('MAIL_BACKEND') && MAIL_BACKEND === 'phpmailer'): ?>
                        <tr>
                            <td>SMTP-Host</td>
                            <td><?= defined('SMTP_HOST') && !empty(SMTP_HOST) ? htmlspecialchars(SMTP_HOST) : '<span style="color:#dc3545">nicht konfiguriert</span>' ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (defined('MAIL_BACKEND') && MAIL_BACKEND === 'queue'): ?>
                        <tr>
                            <td>Queue-Datenbank</td>
                            <td><?= defined('DB_NAME') ? htmlspecialchars(DB_NAME) : 'nicht konfiguriert' ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <?php if (!$is_sitzungsverwaltung): ?>
                    <div class="info-box">
                        <strong>📝 Konfiguration ändern:</strong><br>
                        Bearbeiten Sie <code>mail_standalone_config.php</code> im gleichen Verzeichnis.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- TEST MAIL FORM -->
                <div class="card">
                    <h2>🧪 Test-Mail versenden</h2>

                    <form method="POST">
                        <div class="form-group">
                            <label>Backend</label>
                            <select name="backend">
                                <option value="mail" <?= (defined('MAIL_BACKEND') && MAIL_BACKEND === 'mail') ? 'selected' : '' ?>>PHP mail()</option>
                                <option value="phpmailer" <?= (defined('MAIL_BACKEND') && MAIL_BACKEND === 'phpmailer') ? 'selected' : '' ?>>PHPMailer (SMTP)</option>
                                <option value="queue" <?= (defined('MAIL_BACKEND') && MAIL_BACKEND === 'queue') ? 'selected' : '' ?>>Queue (Datenbank)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Empfänger-E-Mail *</label>
                            <input type="email" name="to" required placeholder="empfaenger@example.com">
                        </div>

                        <div class="form-group">
                            <label>Absender-E-Mail</label>
                            <input type="email" name="from_email" value="<?= defined('MAIL_FROM') ? htmlspecialchars(MAIL_FROM) : '' ?>" placeholder="absender@example.com">
                        </div>

                        <div class="form-group">
                            <label>Absender-Name</label>
                            <input type="text" name="from_name" value="<?= defined('MAIL_FROM_NAME') ? htmlspecialchars(MAIL_FROM_NAME) : '' ?>" placeholder="Meeting-System">
                        </div>

                        <div class="form-group">
                            <label>Betreff</label>
                            <input type="text" name="subject" value="Test-Mail vom Standalone Mail-System" placeholder="Test-Mail">
                        </div>

                        <div class="form-group">
                            <label>Nachricht (Text) *</label>
                            <textarea name="message_text" required placeholder="Dies ist eine Test-Mail vom Standalone Mail-System.&#10;&#10;Zeitstempel: <?= date('Y-m-d H:i:s') ?>">Dies ist eine Test-Mail vom Standalone Mail-System.

Zeitstempel: <?= date('Y-m-d H:i:s') ?>

Versand-Backend: <?= defined('MAIL_BACKEND') ? MAIL_BACKEND : 'mail' ?>

---
Diese Nachricht wurde automatisch generiert.</textarea>
                        </div>

                        <div class="form-group">
                            <label>Nachricht (HTML) <small>(optional, wird aus Text generiert wenn leer)</small></label>
                            <textarea name="message_html" placeholder="<p>HTML-Version...</p>"></textarea>
                        </div>

                        <button type="submit" name="send_test_mail" class="btn btn-primary">📧 Test-Mail senden</button>
                    </form>
                </div>

                <?php if (defined('MAIL_BACKEND') && MAIL_BACKEND === 'queue'): ?>
                <!-- QUEUE MANAGEMENT -->
                <div class="card">
                    <h2>📬 Mail-Queue</h2>

                    <?php
                    // Queue-Status laden
                    if (isset($pdo) && $pdo) {
                        try {
                            $stmt = $pdo->query("
                                SELECT
                                    status,
                                    COUNT(*) as count
                                FROM mail_queue
                                GROUP BY status
                            ");
                            $queue_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                            $pending = $queue_stats['pending'] ?? 0;
                            $sent = $queue_stats['sent'] ?? 0;
                            $failed = $queue_stats['failed'] ?? 0;
                            $sending = $queue_stats['sending'] ?? 0;

                            echo '<table class="config-table">';
                            echo '<tr><th>Status</th><th>Anzahl</th></tr>';
                            echo '<tr><td>Ausstehend (pending)</td><td><strong>' . $pending . '</strong></td></tr>';
                            echo '<tr><td>Gesendet (sent)</td><td><strong style="color:#28a745">' . $sent . '</strong></td></tr>';
                            echo '<tr><td>Fehlgeschlagen (failed)</td><td><strong style="color:#dc3545">' . $failed . '</strong></td></tr>';
                            echo '<tr><td>In Bearbeitung (sending)</td><td><strong style="color:#ffc107">' . $sending . '</strong></td></tr>';
                            echo '</table>';

                            if ($pending > 0) {
                                echo '<div class="info-box" style="margin-top: 15px;">';
                                echo '<strong>ℹ️ Queue-Verarbeitung:</strong><br>';
                                echo 'Führen Sie <code>php process_mail_queue.php</code> aus oder richten Sie einen Cronjob ein.<br>';
                                echo 'Empfehlung: <code>*/5 * * * * /usr/bin/php ' . __DIR__ . '/process_mail_queue.php</code>';
                                echo '</div>';
                            }

                        } catch (Exception $e) {
                            echo '<div class="message error">Fehler beim Laden der Queue: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    } else {
                        echo '<div class="message error">Keine Datenbankverbindung für Queue verfügbar</div>';
                    }
                    ?>
                </div>
                <?php endif; ?>

                <!-- DOCUMENTATION -->
                <div class="card">
                    <h2>📚 Verwendung in PHP-Code</h2>

                    <p><strong>Beispiel:</strong> Mail direkt senden</p>
                    <pre style="background: #f8f9fa; padding: 15px; border-radius: 6px; overflow-x: auto; border: 1px solid #e9ecef;"><code><?= htmlspecialchars('<?php
require_once \'mail_standalone.php\';

$config = [
    \'enabled\' => true,
    \'backend\' => \'mail\',  // \'mail\', \'phpmailer\', \'queue\'
    \'from_email\' => \'noreply@example.com\',
    \'from_name\' => \'Mein System\'
];

$result = mail_standalone_send(
    \'empfaenger@example.com\',
    \'Betreff der Mail\',
    \'Text-Version der Nachricht\',
    \'<p>HTML-Version der Nachricht</p>\',
    $config
);

if ($result) {
    echo "Mail erfolgreich versendet!";
} else {
    echo "Fehler beim Versenden";
}
?>') ?></code></pre>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <a href="?logout" class="btn btn-danger">Abmelden</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="footer">
            Mail-System Standalone v1.0 &middot; Erstellt: 18.11.2025
        </div>
    </div>
</body>
</html>
    <?php

    exit; // Web-Interface beendet hier
}

// ============================================
// ENDE DES SCRIPTS
// ============================================

// Flag setzen dass mail_standalone eingebunden wurde
$GLOBALS['MAIL_STANDALONE_INCLUDED'] = true;

?>
