<?php
/**
 * debug_mail.php - Mail-System Diagnose-Tool
 *
 * Zeigt alle relevanten Informationen zur Fehlersuche
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>📧 Mail-System Diagnose</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border:1px solid #ddd;'>";

echo "=== SYSTEM-INFORMATIONEN ===\n\n";

// PHP Version
echo "PHP Version: " . phpversion() . "\n";

// Mail-Funktion verfügbar?
echo "mail() Funktion: " . (function_exists('mail') ? '✓ Verfügbar' : '✗ NICHT verfügbar') . "\n\n";

// Config laden
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    echo "=== CONFIG.PHP EINSTELLUNGEN ===\n\n";
    echo "MAIL_ENABLED: " . (defined('MAIL_ENABLED') ? (MAIL_ENABLED ? 'true' : 'false') : 'nicht definiert') . "\n";
    echo "MAIL_BACKEND: " . (defined('MAIL_BACKEND') ? MAIL_BACKEND : 'nicht definiert') . "\n";
    echo "MAIL_FROM: " . (defined('MAIL_FROM') ? MAIL_FROM : 'nicht definiert') . "\n";
    echo "MAIL_FROM_NAME: " . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'nicht definiert') . "\n\n";

    if (defined('MAIL_BACKEND') && MAIL_BACKEND === 'phpmailer') {
        echo "SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : 'nicht definiert') . "\n";
        echo "SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : 'nicht definiert') . "\n";
        echo "SMTP_SECURE: " . (defined('SMTP_SECURE') ? SMTP_SECURE : 'nicht definiert') . "\n";
        echo "SMTP_AUTH: " . (defined('SMTP_AUTH') ? (SMTP_AUTH ? 'true' : 'false') : 'nicht definiert') . "\n";
        echo "SMTP_USER: " . (defined('SMTP_USER') && !empty(SMTP_USER) ? '***gesetzt***' : 'nicht definiert') . "\n\n";
    }
}

echo "=== PHP INI EINSTELLUNGEN ===\n\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "SMTP (Windows): " . ini_get('SMTP') . "\n";
echo "smtp_port (Windows): " . ini_get('smtp_port') . "\n";
echo "sendmail_from: " . ini_get('sendmail_from') . "\n\n";

echo "=== ERROR LOGS ===\n\n";
echo "error_log: " . ini_get('error_log') . "\n";
echo "log_errors: " . (ini_get('log_errors') ? 'aktiviert' : 'deaktiviert') . "\n\n";

echo "=== BETRIEBSSYSTEM ===\n\n";
echo "OS: " . PHP_OS . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unbekannt') . "\n\n";

// Test: Direkter mail() Aufruf
echo "=== DIREKTER MAIL-TEST ===\n\n";
echo "Versuche Test-Mail zu senden...\n";

$test_to = "test@example.com"; // Ändere das zu deiner E-Mail!
$test_subject = "Debug Test " . date('H:i:s');
$test_message = "Test-Mail von debug_mail.php um " . date('Y-m-d H:i:s');
$test_headers = "From: debug@localhost\r\n";

// Mail-Aufruf mit Error-Catching
$mail_result = @mail($test_to, $test_subject, $test_message, $test_headers);

echo "mail() Rückgabewert: " . ($mail_result ? '✓ TRUE (scheinbar erfolgreich)' : '✗ FALSE (fehlgeschlagen)') . "\n";
echo "Hinweis: TRUE bedeutet nur, dass PHP die Mail an das Mail-System übergeben hat!\n";
echo "         Die tatsächliche Zustellung hängt vom Server ab.\n\n";

// Letzter PHP-Fehler
$last_error = error_get_last();
if ($last_error) {
    echo "Letzter PHP-Fehler:\n";
    print_r($last_error);
    echo "\n";
}

echo "=== MÖGLICHE PROBLEME ===\n\n";

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    echo "⚠️ Windows erkannt!\n";
    echo "   Problem: PHP mail() benötigt auf Windows einen SMTP-Server.\n";
    echo "   Lösung 1: SMTP in php.ini konfigurieren\n";
    echo "   Lösung 2: PHPMailer Backend verwenden\n";
    echo "   Lösung 3: Externes Tool wie 'sendmail.exe' oder 'msmtp'\n\n";

    if (empty(ini_get('SMTP')) || ini_get('SMTP') === 'localhost') {
        echo "❌ KRITISCH: SMTP nicht konfiguriert in php.ini!\n";
        echo "   Öffne: C:\\xampp\\php\\php.ini\n";
        echo "   Suche nach [mail function]\n";
        echo "   Setze:\n";
        echo "   SMTP = smtp.gmail.com (oder dein SMTP-Server)\n";
        echo "   smtp_port = 587\n";
        echo "   sendmail_from = deine@email.com\n\n";
    }
}

// PHPMailer verfügbar?
echo "=== PHPMAILER VERFÜGBARKEIT ===\n\n";
$phpmailer_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($phpmailer_autoload)) {
    require_once $phpmailer_autoload;
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "✓ PHPMailer verfügbar\n";
        echo "  Empfehlung: Verwende MAIL_BACKEND='phpmailer' für zuverlässigen Versand\n\n";
    } else {
        echo "✗ PHPMailer nicht geladen\n\n";
    }
} else {
    echo "✗ Composer vendor/autoload.php nicht gefunden\n";
    echo "  Installation: composer require phpmailer/phpmailer\n\n";
}

echo "=== EMPFOHLENE MASSNAHMEN ===\n\n";
echo "1. Prüfe die PHP Error Logs:\n";
$log_file = ini_get('error_log');
if (!empty($log_file) && file_exists($log_file)) {
    echo "   Datei: $log_file\n";
    echo "   Letzte 20 Zeilen:\n";
    echo "   " . str_repeat('-', 70) . "\n";
    $lines = file($log_file);
    $last_lines = array_slice($lines, -20);
    foreach ($last_lines as $line) {
        if (stripos($line, 'mail') !== false) {
            echo "   " . $line;
        }
    }
    echo "   " . str_repeat('-', 70) . "\n\n";
} else {
    echo "   Nicht gefunden oder leer\n\n";
}

echo "2. Prüfe Spam-Ordner beim Empfänger\n\n";

echo "3. Für XAMPP (Windows):\n";
echo "   a) Installiere 'Fake Sendmail' (in XAMPP enthalten)\n";
echo "   b) Oder konfiguriere echten SMTP in php.ini\n";
echo "   c) Oder verwende PHPMailer Backend\n\n";

echo "4. Test mit externer Test-Mail-Adresse:\n";
echo "   z.B. https://www.mail-tester.com/\n";
echo "   Sende eine Mail dorthin und prüfe den Spam-Score\n\n";

echo "</pre>";

echo "<h2>🔧 Quick-Fix für XAMPP/Windows</h2>";
echo "<div style='background:#fff3cd; padding:15px; border-left:4px solid #ffc107; margin:20px;'>";
echo "<strong>Schnellste Lösung:</strong> PHPMailer mit Gmail verwenden<br><br>";
echo "1. In config.php ändern:<br>";
echo "<code style='background:#f5f5f5; padding:2px 6px; display:block; margin:10px 0;'>";
echo "define('MAIL_BACKEND', 'phpmailer');<br>";
echo "define('SMTP_HOST', 'smtp.gmail.com');<br>";
echo "define('SMTP_PORT', 587);<br>";
echo "define('SMTP_SECURE', 'tls');<br>";
echo "define('SMTP_AUTH', true);<br>";
echo "define('SMTP_USER', 'deine@gmail.com');<br>";
echo "define('SMTP_PASS', 'dein-app-passwort'); // Nicht normales Passwort!<br>";
echo "</code>";
echo "2. Gmail App-Passwort erstellen: https://myaccount.google.com/apppasswords<br>";
echo "3. Composer installieren: <code>composer require phpmailer/phpmailer</code>";
echo "</div>";

echo "<h2>🧪 Nächste Schritte</h2>";
echo "<ol>";
echo "<li>Ändere in diesem Script oben <code>\$test_to</code> auf deine echte E-Mail</li>";
echo "<li>Lade die Seite neu</li>";
echo "<li>Prüfe ob die Mail ankommt</li>";
echo "<li>Wenn nicht: Schau in die Error Logs (siehe oben)</li>";
echo "</ol>";

?>
