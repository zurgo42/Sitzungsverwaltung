<?php
/**
 * config.php - Nutzerspezifische Konfiguration
 * Hier stehen alle individuellen Einstellungen
 */

// ============= UMGEBUNGS-ERKENNUNG =============
/**
 * Erkennt automatisch, ob die Anwendung lokal (XAMPP) oder auf dem Produktivserver läuft
 *
 * @return bool true wenn lokal (XAMPP), false wenn Produktivserver
 */
function is_local_environment() {
    // ZUERST: Produktivserver explizit ausschließen
    $production_paths = [
        '/srv/www/vhosts',  // Typischer Produktivserver-Pfad (Plesk, etc.)
        '/var/www/vhosts',  // Alternative Produktivserver-Pfade
        '/home/www',
        '/usr/share/nginx',
    ];

    foreach ($production_paths as $prod_path) {
        if (stripos(__FILE__, $prod_path) !== false) {
            error_log("=== IS_LOCAL_ENVIRONMENT: PRODUCTION (Pfad enthält " . $prod_path . ") ===");
            return false;  // Definitiv Produktivserver
        }
    }

    // DANN: Prüfe Indikatoren für lokale Entwicklung
    $indicators = [];

    // Prüfe Server-Name (localhost, 127.0.0.1, ::1)
    $indicators['server_name'] = isset($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1']);

    // Prüfe HTTP-Host
    $indicators['http_host'] = isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;

    // Prüfe Server-Adresse
    $indicators['server_addr'] = isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']);

    // Prüfe ob im XAMPP-Pfad (Windows oder Linux XAMPP)
    $indicators['xampp'] = stripos(__FILE__, 'xampp') !== false;

    // Prüfe ob im LAMPP-Pfad (Linux alternative)
    $indicators['lampp'] = stripos(__FILE__, '/opt/lampp') !== false;

    // Prüfe ob im typischen lokalen htdocs-Pfad (nach Produktivserver-Check)
    $indicators['htdocs'] = stripos(__FILE__, 'htdocs') !== false;

    $is_local = in_array(true, $indicators, true);

    // Debug-Logging
    error_log("=== IS_LOCAL_ENVIRONMENT DEBUG ===");
    error_log("File: " . __FILE__);
    error_log("SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'N/A'));
    error_log("HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A'));
    error_log("SERVER_ADDR: " . ($_SERVER['SERVER_ADDR'] ?? 'N/A'));
    error_log("Indicators: " . json_encode($indicators));
    error_log("Result: " . ($is_local ? 'LOCAL' : 'PRODUCTION'));
    error_log("===================================");

    return $is_local;
}

// Umgebung setzen
define('IS_LOCAL', is_local_environment());

// ============= DATENBANK-ZUGANGSDATEN =============
// Primäre Konstanten: MYSQL_*
if (IS_LOCAL) {
    // XAMPP / Lokale Entwicklungsumgebung
    define('MYSQL_HOST', 'localhost');
    define('MYSQL_USER', 'root');
    define('MYSQL_PASS', '');  // XAMPP Standard: kein Passwort
    define('MYSQL_DATABASE', 'k126904_div');  // Lokale Datenbank
} else {
    // Produktivserver
    define('MYSQL_HOST', '...');
    define('MYSQL_USER', '...');
    define('MYSQL_PASS', '...');
    define('MYSQL_DATABASE', '...');
}

// Aliases für Abwärtskompatibilität: DB_*
define('DB_HOST', MYSQL_HOST);
define('DB_USER', MYSQL_USER);
define('DB_PASS', MYSQL_PASS);
define('DB_NAME', MYSQL_DATABASE);

// ============= SYSTEM-EINSTELLUNGEN =============
define('TIMEZONE', 'Europe/Berlin');
define('SESSION_TIMEOUT', 18000);  // in Sekunden (5 Stunden)

// Tabs aktivieren/deaktivieren
define('ENABLE_DOCUMENTS_TAB', true);  // Dokumenten-Verwaltung aktivieren/deaktivieren

// ============= VOREINSTELLUNGEN FÜR MEETINGS =============
define('DEFAULT_MEETING_NAME', 'Vorstandssitzung');
define('DEFAULT_LOCATION', 'Online per Jitsi');
define('DEFAULT_VIDEO_LINK', 'https://meet.zurgo.de/');

// Zeitspanne in Stunden, wie lange nach Sitzungsende Änderungswünsche möglich sind
define('PROTOCOL_FEEDBACK_HOURS', 48); // 48 Stunden = 2 Tage

// ============= E-MAIL-EINSTELLUNGEN (optional) =============
define('MAIL_ENABLED', false);  // E-Mail-Versand aktivieren/deaktivieren
define('MAIL_FROM', 'meetings@example.com');
define('MAIL_FROM_NAME', 'Meeting-System');

// Mail-Backend auswählen:
// - 'mail':      Standard PHP mail() - funktioniert überall (empfohlen als Fallback)
// - 'phpmailer': PHPMailer library - besser für SMTP (siehe SMTP-Einstellungen unten)
// - 'queue':     Speichert Mails in Datenbank, Versand via Cronjob (siehe Queue-Einstellungen)
define('MAIL_BACKEND', 'mail');

// ============= SMTP-EINSTELLUNGEN (nur für MAIL_BACKEND='phpmailer') =============
// Nur relevant wenn PHPMailer installiert ist (composer require phpmailer/phpmailer)
define('SMTP_HOST', '');           // z.B. 'smtp.example.com'
define('SMTP_PORT', 587);          // 587 (TLS) oder 465 (SSL) oder 25
define('SMTP_SECURE', 'tls');      // 'tls', 'ssl' oder '' (kein SSL)
define('SMTP_AUTH', false);        // true wenn SMTP-Authentifizierung erforderlich
define('SMTP_USER', '');           // SMTP-Benutzername
define('SMTP_PASS', '');           // SMTP-Passwort

// ============= QUEUE-EINSTELLUNGEN (nur für MAIL_BACKEND='queue') =============
// Mails werden in mail_queue Tabelle gespeichert und via Cronjob versendet
// Cronjob Setup: */5 * * * * /usr/bin/php /pfad/zu/process_mail_queue.php >> /var/log/mail_queue.log 2>&1
define('MAIL_QUEUE_BATCH_SIZE', 10);    // Anzahl Mails pro Cronjob-Durchlauf
define('MAIL_QUEUE_DELAY', 1);          // Sekunden Pause zwischen einzelnen Mails
define('MAIL_QUEUE_MAX_ATTEMPTS', 3);   // Max. Zustellversuche pro Mail

// ============= WEITERE EINSTELLUNGEN =============
define('TOP_CONFIDENTIAL_START', 101);  // Ab welcher TOP-Nummer ist es vertraulich
define('DEBUG_MODE', true);  // TEMPORÄR: Immer aktiviert für Debugging (normalerweise: IS_LOCAL)

// ============= STANDALONE-SKRIPTE =============
// Pfad zu standalone-Skripten (für externe Teilnehmer ohne Login)
// Kann sein: '/' (root), '/Sitzungsverwaltung', '/public' etc.
// Wichtig: Dieses Verzeichnis darf NICHT durch .htaccess passwortgeschützt sein!
define('STANDALONE_PATH', '/Sitzungsverwaltung');

// ============= DEMO-MODUS =============
// WICHTIG: Auf dem echten Produktivserver auf false setzen!
// true  = Demo-Funktionen sind verfügbar (Datenbank-Reset-Button, Demo-Daten-Import)
// false = Produktivbetrieb, keine Demo-Funktionen
define('DEMO_MODE_ENABLED', true);  // ÄNDERN SIE DIES AUF false FÜR PRODUKTIVBETRIEB!

// ============= SYSTEM-ADMIN-PASSWORT =============
// Passwort für sensible Systemfunktionen (Import/Export/Backup/Restore)
// WICHTIG: Ändern Sie dieses Passwort für Produktivbetrieb!
define('SYSTEM_ADMIN_PASSWORD', 'admin2024');  // ÄNDERN SIE DIESES PASSWORT!

// Rollen-Definitionen
define('ROLES_CONFIDENTIAL_ACCESS', ['vorstand', 'gf', 'assistenz']); // Rollen mit Zugriff auf vertrauliche TOPs

// ============= ZEITZONE SETZEN =============
date_default_timezone_set(TIMEZONE);

// ============= DEBUG MODE =============
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============= SESSION-KONFIGURATION =============
// WICHTIG: Für Standalone-Nutzung (externe Aufrufe) müssen diese
// Einstellungen identisch sein mit dem aufrufenden Script!

// Session-Einstellungen nur setzen wenn Session noch nicht aktiv ist
// (verhindert Warnings wenn externes Script bereits session_start() aufgerufen hat)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');           // Cookie für gesamte Domain
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', 1);

    // Falls HTTPS:
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
}

// ============= FOOTER-KONFIGURATION =============
define('FOOTER_COPYRIGHT', '&copy; Dr. Hermann Meier, Horstmannsmühle 1a, 42781 Haan Tel. 02129 379 2870 eMail meier@zurgo.de');
define('FOOTER_IMPRESSUM_URL', 'https://geschäftsordnung.com/?page_id=53');
define('FOOTER_DATENSCHUTZ_URL', 'https://geschäftsordnung.com/?page_id=54');


?>
