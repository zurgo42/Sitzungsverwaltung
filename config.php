<?php
/**
 * config.php - Nutzerspezifische Konfiguration
 * Hier stehen alle individuellen Einstellungen
 */

// ============= DATENBANK-ZUGANGSDATEN =============
define('DB_HOST', '91.204.46.74');
define('DB_USER', 'k126904_hm');
define('DB_PASS', '1Pkigg!n');
define('DB_NAME', 'k126904_div');  // für Testphase

// ============= SYSTEM-EINSTELLUNGEN =============
define('TIMEZONE', 'Europe/Berlin');
define('SESSION_TIMEOUT', 3600);  // in Sekunden (1 Stunde)

// ============= VOREINSTELLUNGEN FÜR MEETINGS =============
define('DEFAULT_MEETING_NAME', 'Vorstandssitzung');
define('DEFAULT_LOCATION', 'Online per Jitsi');
define('DEFAULT_VIDEO_LINK', 'https://meet.zurgo.de/');

// Zeitspanne in Stunden, wie lange nach Sitzungsende Änderungswünsche möglich sind
define('PROTOCOL_FEEDBACK_HOURS', 48); // 48 Stunden = 2 Tage

// ============= E-MAIL-EINSTELLUNGEN (optional) =============
define('MAIL_ENABLED', false);
define('MAIL_FROM', 'meetings@example.com');
define('MAIL_FROM_NAME', 'Meeting-System');

// ============= WEITERE EINSTELLUNGEN =============
define('TOP_CONFIDENTIAL_START', 101);  // Ab welcher TOP-Nummer ist es vertraulich
define('DEBUG_MODE', false);  // Fehler-Ausgabe aktivieren (in Entwicklung auf true)

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

?>