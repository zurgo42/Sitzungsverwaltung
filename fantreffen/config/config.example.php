<?php
/**
 * Konfigurationsdatei
 * Diese Datei als config.php kopieren und Zugangsdaten eintragen
 */

// Datenbank
define('DB_HOST', 'localhost');
define('DB_NAME', 'dein_datenbankname');
define('DB_USER', 'dein_username');
define('DB_PASS', 'dein_passwort');

// Mail-Server (SMTP)
define('MAIL_HOST', 'mail.example.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'info@example.de');
define('MAIL_PASS', 'dein_mail_passwort');
define('MAIL_FROM_ADDRESS', 'info@example.de');
define('MAIL_FROM_NAME', 'Aida Fantreffen');

// BCC-Kopie an Admin (leer = deaktiviert)
define('MAIL_BCC_ADMIN', '');  // z.B. 'admin@example.de'

// Bestätigungsmail nach Anmeldung senden (true/false)
// Bei false erhält der Teilnehmer nur die Rückmeldung auf der Webseite
define('MAIL_SEND_ANMELDEBESTAETIGUNG', true);

// Anwendung
define('APP_NAME', 'Aida Fantreffen');
define('APP_URL', 'https://aidafantreffen.de');
define('APP_DEBUG', false);

// Session
define('SESSION_LIFETIME', 3600 * 24 * 7); // 1 Woche
