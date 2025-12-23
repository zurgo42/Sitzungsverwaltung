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
    // Prüfe verschiedene Indikatoren für lokale Entwicklung
    $local_indicators = [
        // Prüfe Server-Name (localhost, 127.0.0.1, ::1)
        isset($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1']),

        // Prüfe HTTP-Host
        isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false,

        // Prüfe Server-Adresse
        isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']),

        // Prüfe ob im XAMPP-Pfad
        stripos(__FILE__, 'xampp') !== false,

        // Prüfe ob im htdocs-Pfad (typisch für XAMPP)
        stripos(__FILE__, 'htdocs') !== false
    ];

    return in_array(true, $local_indicators, true);
}

// Umgebung setzen
define('IS_LOCAL', is_local_environment());

// ============= DATENBANK-ZUGANGSDATEN =============
if (IS_LOCAL) {
    // XAMPP / Lokale Entwicklungsumgebung
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');  // XAMPP Standard: kein Passwort
    define('DB_NAME', 'k126904_div');  // Lokale Datenbank
} else {
    // Produktivserver
    define('DB_HOST', '91.204.46.74');
    define('DB_USER', 'k126904_hm');
    define('DB_PASS', '1Pkigg!n');
    define('DB_NAME', 'k126904_div');
}

// ============= SYSTEM-EINSTELLUNGEN =============
define('TIMEZONE', 'Europe/Berlin');
define('SESSION_TIMEOUT', 3600);  // in Sekunden (1 Stunde)

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
define('DEBUG_MODE', IS_LOCAL);  // Automatisch aktiviert in lokaler Umgebung

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

// ============= SESSION-SICHERHEIT =============
//ini_set('session.cookie_httponly', 1);
//ini_set('session.use_only_cookies', 1);
// ini_set('session.cookie_secure', 1); // Nur bei HTTPS aktivieren!

// ============= FOOTER-KONFIGURATION =============
define('FOOTER_COPYRIGHT', '&copy; Dr. Hermann Meier, Horstmannsmühle 1a, 42781 Haan Tel. 02129 379 2870 eMail meier@zurgo.de');
define('FOOTER_IMPRESSUM_URL', 'https://geschäftsordnung.com/?page_id=53');
define('FOOTER_DATENSCHUTZ_URL', 'https://geschäftsordnung.com/?page_id=54');

?>