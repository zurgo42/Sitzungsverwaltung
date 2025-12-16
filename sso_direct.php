<?php
/**
 * sso_direct.php - Entry-Point für SSOdirekt-Modus
 *
 * Eigenständige Seite mit:
 * - SSO-Integration via Session
 * - Custom Styling (anpassbar an Vereins-Design)
 * - "Zurück zum VTool" statt "Abmelden"
 * - Konfigurierbarer Footer
 *
 * ARCHITEKTUR:
 * - Setzt nur DISPLAY_MODE_OVERRIDE und startet Session
 * - Delegiert ALLES andere an index.php
 * - index.php übernimmt: Configs, Auth, Members-Array, UI
 */

// Session starten
session_start();

// DISPLAY_MODE auf SSOdirekt setzen (MUSS VOR include index.php definiert werden)
// Wird von index.php beim Rendern der UI verwendet
define('DISPLAY_MODE_OVERRIDE', 'SSOdirekt');

// Das war's! index.php übernimmt jetzt:
// 1. Laden aller Configs (config.php, config_adapter.php, etc.)
// 2. Initialisierung des globalen Members-Arrays
// 3. SSO-Authentifizierung via get_sso_membership_number()
// 4. Laden des $current_user
// 5. HTML-Ausgabe mit SSOdirekt-Styling

include __DIR__ . '/index.php';
?>
