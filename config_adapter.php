<?php
/**
 * config_adapter.php - Konfiguration für Datenbank-Adapter
 *
 * Hier definieren Sie, welchen Adapter das System verwenden soll
 */

//<<<<<<< Updated upstream
// ============================================
// MITGLIEDER-DATENQUELLE
// ============================================

// Welche Tabelle soll für Mitgliederdaten verwendet werden?
// Optionen: 'members' (interne Tabelle) oder 'berechtigte' (externe Tabelle)
define('MEMBER_SOURCE', 'members');  // ÄNDERN auf 'berechtigte' für externe Tabelle
=======
// Welcher Adapter soll verwendet werden?
// Optionen: 'standard' (members Tabelle) oder 'berechtigte' (Ihre externe Tabelle)
define('MEMBER_ADAPTER_TYPE', 'berechtigte');  // ÄNDERN SIE DIES auf 'berechtigte' wenn Sie Ihre Tabelle nutzen
//>>>>>>> Stashed changes

// Alternative: Umgebungsvariable oder automatische Erkennung
// define('MEMBER_SOURCE', getenv('MEMBER_SOURCE') ?: 'members');


// ============================================
// LOGIN-MODUS KONFIGURATION
// ============================================

// Soll ein Login-Formular angezeigt werden?
// true  = Normaler Modus mit Login-Formular (Email/Passwort)
// false = SSO-Modus (Single Sign-On) - Benutzer ist bereits extern authentifiziert
define('REQUIRE_LOGIN', true);

// SSO-Modus: Woher kommt die Mitgliedsnummer?
// 'hardcoded' = Aus TEST_MEMBERSHIP_NUMBER (nur für Tests!)
// 'session'   = Aus $_SESSION['MNr'] (für echtes SSO-System)
// 'get'       = Aus $_GET['MNr'] (für URL-Parameter)
// 'post'      = Aus $_POST['MNr'] (für POST-Parameter)
define('SSO_SOURCE', 'hardcoded');

// TEST: Mitgliedsnummer für SSO-Modus
// Nur aktiv wenn REQUIRE_LOGIN = false UND SSO_SOURCE = 'hardcoded'
// WICHTIG: In Produktion auf null setzen und SSO_SOURCE auf 'session' ändern!
define('TEST_MEMBERSHIP_NUMBER', '0495018');  // Ihre Test-MNr


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
