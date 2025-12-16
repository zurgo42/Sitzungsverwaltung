<?php
/**
 * config_adapter.php - Konfiguration für Datenbank-Adapter
 *
 * Hier definieren Sie, welchen Adapter das System verwenden soll
 */

// ============================================
// MITGLIEDER-DATENQUELLE
// ============================================

// Welche Tabelle soll für Mitgliederdaten verwendet werden?
// Optionen: 'members' (interne Tabelle) oder 'berechtigte' (externe Tabelle)
define('MEMBER_SOURCE', 'members');  // Standard: interne Tabelle verwenden

// Alternative: Umgebungsvariable oder automatische Erkennung
// define('MEMBER_SOURCE', getenv('MEMBER_SOURCE') ?: 'members');


// ============================================
// LOGIN-MODUS KONFIGURATION
// ============================================

// Soll ein Login-Formular angezeigt werden?
// true  = Normaler Modus mit Login-Formular (Email/Passwort)
// false = SSO-Modus (Single Sign-On) - Benutzer ist bereits extern authentifiziert
define('REQUIRE_LOGIN', true);  // Standard: Normaler Login-Modus

// SSO-Modus: Woher kommt die Mitgliedsnummer?
// 'hardcoded' = Aus TEST_MEMBERSHIP_NUMBER (nur für Tests!)
// 'session'   = Aus $_SESSION['MNr'] (für echtes SSO-System)
// 'get'       = Aus $_GET['MNr'] (für URL-Parameter)
// 'post'      = Aus $_POST['MNr'] (für POST-Parameter)
define('SSO_SOURCE', 'session');  // GEÄNDERT: Aus Session lesen

// TEST: Mitgliedsnummer für SSO-Modus
// Nur aktiv wenn REQUIRE_LOGIN = false UND SSO_SOURCE = 'hardcoded'
// WICHTIG: In Produktion auf null setzen und SSO_SOURCE auf 'session' ändern!
define('TEST_MEMBERSHIP_NUMBER', '0495018');  // Ihre Test-MNr


// ============================================
// DISPLAY-MODUS KONFIGURATION
// ============================================

// Wie soll die Sitzungsverwaltung angezeigt werden?
// 'standalone' = Normale Standalone-Seite mit Login
// 'iframe'     = Im iframe eingebettet (ohne Footer)
// 'SSOdirekt'  = Eigenständige Seite mit SSO und Custom Styling
define('DISPLAY_MODE', 'standalone');  // Standard-Modus

// ============================================
// SSOdirekt KONFIGURATION
// ============================================

/**
 * Konfiguration für SSOdirekt-Modus (Version 2.0)
 * Wird nur verwendet wenn DISPLAY_MODE = 'SSOdirekt'
 *
 * NEU: Separate Farben für Light und Dark Mode!
 * - Header und Footer können unabhängig gestyled werden
 * - Jedes Element hat background, text und border Farben
 * - Back-Button hat eigene Farben
 */
$SSO_DIRECT_CONFIG = [
    // === STYLING - LIGHT MODE ===
    'light' => [
        'header' => [
            'background' => '#1976d2',  // Header Hintergrundfarbe
            'text' => '#ffffff',        // Header Textfarbe
            'border' => '#0d47a1'       // Header Border-Farbe (unten)
        ],
        'footer' => [
            'background' => '#1976d2',  // Footer Hintergrundfarbe
            'text' => '#ffffff',        // Footer Textfarbe
            'border' => '#0d47a1'       // Footer Border-Farbe (oben)
        ],
        'back_button' => [
            'background' => '#ffffff',  // Button Hintergrundfarbe
            'text' => '#1976d2'         // Button Textfarbe
        ]
    ],

    // === STYLING - DARK MODE ===
    'dark' => [
        'header' => [
            'background' => '#1e1e1e',  // Dunkler Header
            'text' => '#e0e0e0',        // Heller Text
            'border' => '#2d2d2d'       // Subtile Border
        ],
        'footer' => [
            'background' => '#1e1e1e',  // Dunkler Footer
            'text' => '#e0e0e0',        // Heller Text
            'border' => '#2d2d2d'       // Subtile Border
        ],
        'back_button' => [
            'background' => '#2d2d2d',  // Dunkler Button
            'text' => '#e0e0e0'         // Heller Text
        ]
    ],

    // === GEMEINSAME EINSTELLUNGEN ===
    'logo_path' => '/img/logo.png',        // Pfad zum Logo (relativ oder absolut)
    'logo_height' => '40px',               // Logo-Höhe

    // === NAVIGATION ===
    'back_button_text' => 'Zurück zum VTool',  // Text für Zurück-Button
    'back_button_url' => 'https://aktive.mensa.de/vtool.php',  // URL für Zurück-Button

    // === FOOTER ===
    'footer_html' => '<p style="margin: 0;">© 2025 Mensa in Deutschland e.V. | <a href="/impressum" style="color: inherit;">Impressum</a> | <a href="/datenschutz" style="color: inherit;">Datenschutz</a></p>',

    // === SEITEN-TITEL ===
    'page_title' => 'Sitzungsverwaltung - Mensa in Deutschland e.V.',

    // ============================================
    // RÜCKWÄRTSKOMPATIBILITÄT (wird ignoriert wenn light/dark vorhanden)
    // ============================================
    // Falls alte Konfiguration noch verwendet wird, werden diese Werte genutzt:
    'primary_color' => '#1976d2',          // Wird für Light Mode verwendet
    'border_color' => '#0d47a1',           // Wird für Light Mode verwendet
    'header_text_color' => '#ffffff',      // Wird für Light Mode verwendet
    'footer_text_color' => '#ffffff',      // Wird für Light Mode verwendet
];


// ============================================
// ADAPTER-SPEZIFISCHE KONFIGURATIONEN
// ============================================

/**
 * Konfigurationen für verschiedene Adapter
 */
$ADAPTER_CONFIG = [
    'berechtigte' => [
        // Falls separate Datenbank
        'db_host' => DB_HOST,  // oder anderer Host
        'db_name' => DB_NAME,  // oder andere DB
        'db_user' => DB_USER,
        'db_pass' => DB_PASS,

        // Spezielle Mappings können hier definiert werden
        'role_mapping' => [
            'Vorstand' => 'vorstand',
            'Geschäftsführung' => 'gf',
            'Assistenz' => 'assistenz',
            'Führungsteam' => 'fuehrungsteam',
            'Mitglied' => 'Mitglied'
        ],

        // Logik für aktiv-Werte
        'confidential_threshold' => 18  // aktiv >= 18 = vertraulich
    ]
];


// ============================================
// HILFSFUNKTION: Mitgliedsnummer im SSO-Modus holen
// ============================================

/**
 * Holt die Mitgliedsnummer je nach SSO-Konfiguration
 *
 * @return string|null Die Mitgliedsnummer oder null
 */
function get_sso_membership_number() {
    if (REQUIRE_LOGIN) {
        return null; // SSO nicht aktiv
    }

    switch (SSO_SOURCE) {
        case 'hardcoded':
            return TEST_MEMBERSHIP_NUMBER;

        case 'session':
            return $_SESSION['MNr'] ?? null;

        case 'get':
            return $_GET['MNr'] ?? null;

        case 'post':
            return $_POST['MNr'] ?? null;

        default:
            return null;
    }
}
?>
