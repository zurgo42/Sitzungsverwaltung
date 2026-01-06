<?php
/**
 * sso_direct.php - Entry-Point für SSOdirekt-Modus
 *
 * Eigenständige Seite mit:
 * - SSO-Integration via Session
 * - Custom Styling (anpassbar an Vereins-Design)
 * - Logout und "Zurück zum VTool" Buttons
 * - Konfigurierbarer Footer
 * - Trust-Device Unterstützung
 *
 * ARCHITEKTUR:
 * - Setzt nur DISPLAY_MODE_OVERRIDE
 * - Delegiert ALLES andere an index.php (inkl. Session-Start mit Lifetime-Management)
 * - index.php übernimmt: Configs, Session-Start, Auth, Members-Array, UI
 */

// DISPLAY_MODE auf SSOdirekt setzen (MUSS VOR include index.php definiert werden)
// Wird von index.php beim Rendern der UI verwendet
define('DISPLAY_MODE_OVERRIDE', 'SSOdirekt');

// WICHTIG: Keine session_start() hier!
// index.php übernimmt Session-Management mit korrekter Lifetime-Konfiguration

// Das war's! index.php übernimmt jetzt:
// 1. Laden aller Configs (config.php, config_adapter.php, etc.)
// 2. Session-Lifetime-Management (basierend auf Trust-Device)
// 3. Session-Start
// 4. Initialisierung des globalen Members-Arrays
// 5. SSO-Authentifizierung via get_sso_membership_number()
// 6. Laden des $current_user
// 7. HTML-Ausgabe mit SSOdirekt-Styling

include __DIR__ . '/index.php';
?>
