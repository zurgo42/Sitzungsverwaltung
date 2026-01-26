<?php
/**
 * terminplanung_simple.php - Vereinfachte Terminplanung (ohne Meeting-Kontext)
 *
 * VERWENDUNG:
 * ===========
 * Aus anderer Anwendung aufrufen:
 *
 * <?php
 *   require_once 'pfad/zu/config.php';  // DB-Config
 *   $pdo = new PDO(...);                 // DB-Verbindung
 *   $MNr = '1234567';                    // Mitgliedsnummer des eingeloggten Users
 *   require_once 'pfad/zu/terminplanung_simple.php';
 * ?>
 *
 * FEATURES:
 * - Keine vorgefertigten Adressatengruppen (Vorstand, Führungsteam...)
 * - Nur manuelle Empfänger-Auswahl
 * - Kein Meeting erforderlich
 * - Nutzt die gleiche Funktionalität wie die volle Sitzungsverwaltung
 */

// Session starten falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error Reporting für Debug aktiviert
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!-- DEBUG: terminplanung_simple.php gestartet -->\n";

// Voraussetzungen prüfen
if (!isset($pdo)) {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; border: 2px solid #f5c6cb; margin: 20px; border-radius: 5px;">';
    echo '<h2>Fehler: Datenbankverbindung fehlt</h2>';
    echo '<p>Die Variable <code>$pdo</code> ist nicht definiert. Bitte stelle sicher, dass du eine PDO-Datenbankverbindung erstellst, bevor du dieses Skript includest.</p>';
    echo '</div>';
    return;
}

if (!isset($MNr) || empty($MNr)) {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; border: 2px solid #f5c6cb; margin: 20px; border-radius: 5px;">';
    echo '<h2>Fehler: Mitgliedsnummer fehlt</h2>';
    echo '<p>Die Variable <code>$MNr</code> ist nicht definiert oder leer. Bitte übergebe eine gültige Mitgliedsnummer.</p>';
    echo '</div>';
    return;
}

echo "<!-- DEBUG: Voraussetzungen OK, lade Module... -->\n";

// Session-Context minimal aufsetzen (für tab_termine.php)
$_SESSION['member_id'] = $MNr;

// Standalone-Modus aktivieren (versteckt vorgefertigte Gruppen)
$standalone_mode = true;

// Basis-Pfad für Formulare setzen (für externe Aufrufe)
// Standard: Relativ zum Sitzungsverwaltung-Verzeichnis
// Kann vom aufrufenden Script überschrieben werden, z.B.:
// $form_action_path = '../Sitzungsverwaltung/';
if (!isset($form_action_path)) {
    $form_action_path = '';  // Standard: gleicher Pfad wie Script
}

// Alle benötigten Module laden (in richtiger Reihenfolge)
$required_files = [
    'user_data_helper.php',
    'functions.php',               // Basis-Funktionen (get_visible_meetings, etc.)
    'member_functions.php',        // Member-Adapter-Funktionen
    'external_participants_functions.php',
    'module_notifications.php'
];

foreach ($required_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
        echo "<!-- DEBUG: Geladen: $file -->\n";
    } else {
        echo "<!-- DEBUG: NICHT GEFUNDEN: $file -->\n";
    }
}

echo "<!-- DEBUG: Alle Module geladen, lade User-Daten... -->\n";

// User-Daten über MNr laden (aus berechtigte oder LDAP)
$user_data = get_user_data($pdo, $MNr);

echo "<!-- DEBUG: get_user_data() abgeschlossen -->\n";

if (!$user_data) {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; border: 2px solid #f5c6cb; margin: 20px; border-radius: 5px;">';
    echo '<h2>Fehler: Benutzer nicht gefunden</h2>';
    echo '<p>Der Benutzer mit der Mitgliedsnummer <strong>' . htmlspecialchars($MNr) . '</strong> konnte nicht geladen werden.</p>';
    echo '<p>Weder in der berechtigte-Tabelle noch in LDAP wurde ein passender Eintrag gefunden.</p>';
    echo '<p><strong>Mögliche Ursachen:</strong></p>';
    echo '<ul>';
    echo '<li>Die Mitgliedsnummer existiert nicht in der Datenbank</li>';
    echo '<li>Der LDAP-Server ist nicht erreichbar</li>';
    echo '<li>Die berechtigte-Tabelle ist leer oder enthält keine passenden Einträge</li>';
    echo '</ul>';
    echo '</div>';
    return;
}

// $current_user im erwarteten Format aufbauen
$current_user = [
    'member_id' => $MNr,  // MNr als ID verwenden
    'first_name' => $user_data['first_name'],
    'last_name' => $user_data['last_name'],
    'email' => $user_data['email'],
    'role' => 'mitglied'  // Default für Standalone-Modus
];

echo "<!-- DEBUG: \$current_user aufgebaut: " . $current_user['first_name'] . " " . $current_user['last_name'] . " -->\n";
echo "<!-- DEBUG: Lade jetzt tab_termine.php... -->\n";

// WICHTIG: Für Standalone-Zugriff Flag setzen
$_SESSION['standalone_mode'] = true;
$_SESSION['standalone_user'] = $current_user;  // Komplett-Objekt für Process-Skripte

// Redirect-Basis für Process-Skripte setzen (wo soll nach erfolg zurück redirected werden?)
// Wenn nicht gesetzt, wird vom aufrufenden Script der aktuelle Pfad verwendet
if (!isset($_SESSION['standalone_redirect'])) {
    $_SESSION['standalone_redirect'] = $_SERVER['PHP_SELF'];
}

// Tab mit allen Features laden (außer vorgefertigte Gruppen)
require_once __DIR__ . '/tab_termine.php';

echo "<!-- DEBUG: tab_termine.php komplett geladen! -->\n";
