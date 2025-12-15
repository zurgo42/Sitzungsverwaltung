<?php
/**
 * test_mail_system.php - Test-Script für Mail-System
 *
 * VERWENDUNG:
 * 1. Via Browser: http://localhost/Sitzungsverwaltung/test_mail_system.php
 * 2. Via CLI: php test_mail_system.php
 *
 * Testet alle Mail-Backends und zeigt Ergebnisse an.
 */

// Session für Fehlermeldungen
session_start();

// Config laden (falls in Sitzungsverwaltung)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Mail-Funktionen laden
if (file_exists(__DIR__ . '/mail_functions.php')) {
    require_once __DIR__ . '/mail_functions.php';
    $use_integrated = true;
} else {
    require_once __DIR__ . '/mail_standalone.php';
    $use_integrated = false;
}

// CLI oder Browser?
$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail-System Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #2196F3; padding-bottom: 10px; }
        .test-result { margin: 20px 0; padding: 15px; border-radius: 6px; border-left: 4px solid #ddd; }
        .test-result.success { background: #d4edda; border-color: #28a745; color: #155724; }
        .test-result.error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .test-result.info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
        .config-box { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .config-box strong { color: #495057; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .btn { display: inline-block; padding: 12px 24px; background: #2196F3; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #1976D2; }
        form { margin: 20px 0; }
        label { display: block; margin: 15px 0 5px 0; font-weight: bold; color: #495057; }
        input[type="email"], select { width: 100%; padding: 10px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 14px; }
        input[type="email"]:focus, select:focus { outline: none; border-color: #2196F3; }
    </style>
</head>
<body>
    <div class="card">
        <h1>📧 Mail-System Test</h1>

        <?php
}

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mail'])) {
    $test_email = $_POST['test_email'] ?? '';
    $test_backend = $_POST['test_backend'] ?? 'mail';

    if (empty($test_email)) {
        $error = "Bitte E-Mail-Adresse eingeben!";
    } else {
        // Test-Mail senden
        $subject = "Test-Mail vom Mail-System - " . date('d.m.Y H:i:s');

        $text = "Hallo,\n\n";
        $text .= "dies ist eine Test-Mail vom Mail-System.\n\n";
        $text .= "Zeitstempel: " . date('d.m.Y H:i:s') . "\n";
        $text .= "Backend: $test_backend\n";
        $text .= "Modus: " . ($use_integrated ? 'Integriert (Sitzungsverwaltung)' : 'Standalone') . "\n\n";
        $text .= "Wenn du diese Mail erhältst, funktioniert das Mail-System! ✓\n\n";
        $text .= "---\n";
        $text .= "Diese Mail wurde automatisch generiert.";

        $html = "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";
        $html .= "<div style='max-width: 600px; margin: 0 auto; padding: 20px;'>";
        $html .= "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0;'>";
        $html .= "<h2 style='margin: 0;'>📧 Test-Mail vom Mail-System</h2>";
        $html .= "</div>";
        $html .= "<div style='background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;'>";
        $html .= "<p>Hallo,</p>";
        $html .= "<p>dies ist eine <strong>Test-Mail</strong> vom Mail-System.</p>";
        $html .= "<div style='background: white; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;'>";
        $html .= "<strong>📊 Test-Details:</strong><br>";
        $html .= "🕐 Zeitstempel: " . date('d.m.Y H:i:s') . "<br>";
        $html .= "⚙️ Backend: <code>$test_backend</code><br>";
        $html .= "🔧 Modus: " . ($use_integrated ? 'Integriert (Sitzungsverwaltung)' : 'Standalone') . "<br>";
        $html .= "</div>";
        $html .= "<p style='background: #d4edda; padding: 15px; border-radius: 6px; border: 1px solid #c3e6cb; color: #155724;'>";
        $html .= "✅ <strong>Wenn du diese Mail erhältst, funktioniert das Mail-System!</strong>";
        $html .= "</p>";
        $html .= "<p style='font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; margin-top: 20px;'>";
        $html .= "Diese Mail wurde automatisch generiert.";
        $html .= "</p>";
        $html .= "</div></div></body></html>";

        // Senden
        if ($use_integrated) {
            // In Sitzungsverwaltung
            $result = multipartmail($test_email, $subject, $text, $html);
        } else {
            // Standalone
            $config = ['enabled' => true, 'backend' => $test_backend];
            $result = mail_standalone_send($test_email, $subject, $text, $html, $config);
        }

        if ($result) {
            $success_msg = "✅ Test-Mail erfolgreich versendet an: $test_email";
            if ($test_backend === 'queue') {
                $success_msg .= " (zur Queue hinzugefügt - Versand erfolgt via Cronjob)";
            }
        } else {
            $error = "❌ Fehler beim Versenden der Test-Mail. Prüfe die Logs.";
        }
    }
}

if (!$is_cli) {
    // Browser-Ausgabe

    // Erfolgsmeldung
    if (isset($success_msg)) {
        echo '<div class="test-result success">' . htmlspecialchars($success_msg) . '</div>';
    }

    // Fehlermeldung
    if (isset($error)) {
        echo '<div class="test-result error">' . htmlspecialchars($error) . '</div>';
    }

    // System-Konfiguration anzeigen
    echo '<div class="config-box">';
    echo '<h2>📊 Aktuelle Konfiguration</h2>';
    echo '<strong>Modus:</strong> ' . ($use_integrated ? 'Integriert (Sitzungsverwaltung)' : 'Standalone') . '<br>';
    echo '<strong>Mail-Versand:</strong> ' . (defined('MAIL_ENABLED') && MAIL_ENABLED ? '<span style="color:#28a745">✓ Aktiviert</span>' : '<span style="color:#dc3545">✗ Deaktiviert</span>') . '<br>';
    echo '<strong>Backend:</strong> <code>' . (defined('MAIL_BACKEND') ? MAIL_BACKEND : 'mail') . '</code><br>';
    echo '<strong>Absender:</strong> ' . (defined('MAIL_FROM') ? htmlspecialchars(MAIL_FROM) : 'nicht konfiguriert') . '<br>';

    if (defined('MAIL_BACKEND') && MAIL_BACKEND === 'phpmailer' && defined('SMTP_HOST')) {
        echo '<strong>SMTP-Host:</strong> ' . htmlspecialchars(SMTP_HOST) . '<br>';
    }

    if (defined('MAIL_BACKEND') && MAIL_BACKEND === 'queue' && defined('DB_NAME')) {
        echo '<strong>Queue-DB:</strong> ' . htmlspecialchars(DB_NAME) . '<br>';
    }
    echo '</div>';

    // Test-Formular
    echo '<h2>🧪 Test-Mail senden</h2>';
    echo '<form method="POST">';
    echo '<label for="test_email">Empfänger-E-Mail *</label>';
    echo '<input type="email" id="test_email" name="test_email" required placeholder="ihre.email@example.com">';

    echo '<label for="test_backend">Backend wählen</label>';
    echo '<select id="test_backend" name="test_backend">';
    echo '<option value="mail"' . (defined('MAIL_BACKEND') && MAIL_BACKEND === 'mail' ? ' selected' : '') . '>PHP mail()</option>';
    echo '<option value="phpmailer"' . (defined('MAIL_BACKEND') && MAIL_BACKEND === 'phpmailer' ? ' selected' : '') . '>PHPMailer (SMTP)</option>';
    echo '<option value="queue"' . (defined('MAIL_BACKEND') && MAIL_BACKEND === 'queue' ? ' selected' : '') . '>Queue (Datenbank)</option>';
    echo '</select>';

    echo '<div style="margin-top: 20px;">';
    echo '<button type="submit" name="test_mail" class="btn">📧 Test-Mail senden</button>';
    echo '</div>';
    echo '</form>';

    // Hinweise
    echo '<div class="test-result info">';
    echo '<strong>ℹ️ Hinweise:</strong><br>';
    echo '• Die Test-Mail wird an die angegebene Adresse versendet<br>';
    echo '• Prüfe ggf. den Spam-Ordner<br>';
    echo '• Bei Queue-Backend: Cronjob muss laufen (php process_mail_queue.php)<br>';
    echo '• Logs prüfen: PHP Error Log oder /var/log/mail.log<br>';
    echo '</div>';

    echo '</div></body></html>';

} else {
    // CLI-Ausgabe
    echo "\n";
    echo "====================================\n";
    echo "   📧 Mail-System Test (CLI)\n";
    echo "====================================\n\n";

    echo "Modus: " . ($use_integrated ? 'Integriert' : 'Standalone') . "\n";
    echo "Backend: " . (defined('MAIL_BACKEND') ? MAIL_BACKEND : 'mail') . "\n";
    echo "Aktiviert: " . (defined('MAIL_ENABLED') && MAIL_ENABLED ? 'Ja' : 'Nein') . "\n\n";

    echo "Verwendung:\n";
    echo "  Via Browser: http://localhost/Sitzungsverwaltung/test_mail_system.php\n\n";

    echo "Code-Beispiel:\n";
    echo "  <?php\n";
    if ($use_integrated) {
        echo "  require_once 'mail_functions.php';\n";
        echo "  multipartmail('test@example.com', 'Betreff', 'Text', '<p>HTML</p>');\n";
    } else {
        echo "  require_once 'mail_standalone.php';\n";
        echo "  mail_standalone_send('test@example.com', 'Betreff', 'Text', '<p>HTML</p>');\n";
    }
    echo "  ?>\n\n";
}

?>
