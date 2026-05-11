<?php
/**
 * session_config.php - Zentrale Session-Konfiguration
 *
 * MUSS VOR session_start() geladen werden!
 * Wird von allen Dateien included, die session_start() aufrufen.
 */

// Session-Konfiguration (identisch zu VTool für Cookie-Sharing!)
ini_set('session.cookie_path', '/');              // Cookie für gesamte Domain
ini_set('session.cookie_httponly', 1);            // Schutz vor XSS
ini_set('session.cookie_samesite', 'Lax');        // CSRF-Schutz
ini_set('session.use_only_cookies', 1);           // Nur Cookies, keine URL-Parameter

// HTTPS-Sicherheit (nur wenn HTTPS aktiv)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);          // Cookie nur über HTTPS
}

// Session-Laufzeit: 15 Tage (für Jour Fixe mit möglichem übersprungenen Termin)
ini_set('session.gc_maxlifetime', 15 * 24 * 60 * 60);    // 15 Tage Server-seitig
ini_set('session.cookie_lifetime', 15 * 24 * 60 * 60);   // 15 Tage Client-seitig
