<?php
/**
 * config_adapter.EXAMPLE.php
 *
 * MINIMALES BEISPIEL für Integration mit bestehendem System
 *
 * ========================================
 * WAS MACHT DIESE DATEI?
 * ========================================
 *
 * Diese Datei verbindet Ihr bestehendes System (mit SSO und berechtigte-Tabelle)
 * mit der Sitzungsverwaltung. Sie ist SEHR EINFACH, weil alle Funktionen
 * bereits in member_functions.php und adapters/MemberAdapter.php vorhanden sind.
 *
 * ========================================
 * ANLEITUNG:
 * ========================================
 *
 * 1. Kopieren Sie diese Datei zu config_adapter.php
 * 2. Passen Sie die Pfade an (Zeile 30 und 33)
 * 3. Fertig! Keine weiteren Änderungen nötig.
 */

// Guard: verhindert Doppel-Ausführung wenn aus zwei verschiedenen Pfaden geladen
if (defined('SV_CONFIG_ADAPTER_LOADED')) return;
define('SV_CONFIG_ADAPTER_LOADED', true);

// ============================================
// 1. IHR BESTEHENDES SYSTEM EINBINDEN
// ============================================

// ANPASSEN: Pfad zu Ihrer bestehenden Config
// Diese Datei sollte die Datenbank-Verbindung ($pdo) und SSO-Variable ($MNr) bereitstellen
require_once __DIR__ . '/../ihre_bestehende_config.php';

// Sitzungsverwaltung Config einbinden (enthält $pdo falls nicht bereits vorhanden)
require_once __DIR__ . '/config.php';

// ============================================
// 2. ADAPTER AUF "berechtigte" UMSCHALTEN
// ============================================

// WICHTIG: Diese Zeile aktiviert den BerechtigteAdapter
// Alle member_functions.php Funktionen nutzen jetzt automatisch
// die berechtigte-Tabelle statt svmembers!
define('MEMBER_SOURCE', 'berechtigte');

// Member-Funktionen laden (nutzt jetzt automatisch BerechtigteAdapter!)
require_once __DIR__ . '/member_functions.php';

// ============================================
// 3. SSO INTEGRATION
// ============================================

// ANPASSEN: Wie wird die Member-ID in Ihrem System gesetzt?
// Falls Ihr System die Variable $MNr bereitstellt (Mitgliedsnummer):

if (isset($MNr) && !isset($_SESSION['member_id'])) {
    // Mitglied aus berechtigte-Tabelle holen (via BerechtigteAdapter)
    // Diese Funktion ist in member_functions.php und nutzt automatisch
    // den BerechtigteAdapter, weil wir MEMBER_SOURCE='berechtigte' gesetzt haben
    $current_user = get_member_by_membership_number($pdo, $MNr);

    if ($current_user) {
        // User gefunden - in Session speichern
        $_SESSION['member_id'] = $current_user['member_id'];
        $_SESSION['current_user'] = $current_user;
    } else {
        // User nicht gefunden - zurück zum Login
        // ANPASSEN: Pfad zu Ihrer Login-Seite
        header('Location: /ihre_login_seite.php');
        exit;
    }
}

// Alternative: Falls $MNr nicht direkt verfügbar ist
// if (isset($_SESSION['user_id']) && !isset($_SESSION['member_id'])) {
//     $current_user = get_member_by_id($pdo, $_SESSION['user_id']);
//     if ($current_user) {
//         $_SESSION['member_id'] = $current_user['member_id'];
//         $_SESSION['current_user'] = $current_user;
//     }
// }

// ============================================
// 4. CURRENT USER LADEN (falls nicht via SSO)
// ============================================

// Falls User bereits in Session, aber $current_user noch nicht geladen
if (!isset($current_user) && isset($_SESSION['member_id'])) {
    $current_user = get_member_by_id($pdo, $_SESSION['member_id']);

    if (!$current_user) {
        // User nicht mehr vorhanden - zurück zum Login
        // ANPASSEN: Pfad zu Ihrer Login-Seite
        header('Location: /ihre_login_seite.php');
        exit;
    }
}

// ============================================
// FERTIG! ✅
// ============================================
//
// Das war's! Alle Sitzungsverwaltungs-Skripte nutzen jetzt automatisch
// die berechtigte-Tabelle via BerechtigteAdapter.
//
// Der BerechtigteAdapter mappt automatisch:
// - ID → member_id
// - MNr → membership_number
// - Vorname → first_name
// - Name → last_name
// - eMail → email
// - Funktion + aktiv → role
//
// Keine weiteren Änderungen an anderen Dateien nötig!
//
// ============================================
// DEBUG (Optional - zum Testen)
// ============================================

// Temporär aktivieren zum Debuggen: ?debug=1
// if (isset($_GET['debug']) && !empty($current_user)) {
//     echo '<pre style="background: #f0f0f0; padding: 20px; margin: 20px; border: 2px solid #333;">';
//     echo "<h3>🔧 DEBUG MODE</h3>\n";
//     echo "SSO Variable \$MNr: " . ($MNr ?? 'nicht gesetzt') . "\n";
//     echo "Session member_id: " . ($_SESSION['member_id'] ?? 'nicht gesetzt') . "\n";
//     echo "MEMBER_SOURCE: " . (defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'nicht gesetzt') . "\n\n";
//     echo "Current User:\n";
//     print_r($current_user);
//     echo "\n\nAlle Members (erste 3):\n";
//     print_r(array_slice(get_all_members($pdo), 0, 3));
//     echo '</pre>';
//     exit;
// }

?>
