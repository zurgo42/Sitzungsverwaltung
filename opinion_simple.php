<?php
/**
 * opinion_simple.php - Vereinfachtes Meinungsbild (ohne Meeting-Kontext)
 *
 * VERWENDUNG:
 * ===========
 * Aus anderer Anwendung aufrufen:
 *
 * <?php
 *   require_once 'pfad/zu/config.php';  // DB-Config
 *   $pdo = new PDO(...);                 // DB-Verbindung
 *   $MNr = '1234567';                    // Mitgliedsnummer des eingeloggten Users
 *   require_once 'pfad/zu/opinion_simple.php';
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

echo "<!-- DEBUG: opinion_simple.php gestartet -->\n";

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

// Session-Context minimal aufsetzen (für tab_opinion.php)
$_SESSION['member_id'] = $MNr;

// Standalone-Modus aktivieren (versteckt vorgefertigte Gruppen)
$standalone_mode = true;

// Im Standalone-Modus direkt zur Create-View
if (!isset($_GET['view'])) {
    $_GET['view'] = 'create';
}

// Alle benötigten Module laden (in richtiger Reihenfolge)
$required_files = [
    'user_data_helper.php',
    'functions.php',               // Basis-Funktionen
    'member_functions.php',        // Member-Adapter-Funktionen
    'external_participants_functions.php',
    'opinion_functions.php'
];

foreach ($required_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

// User-Daten über MNr laden (aus berechtigte oder LDAP)
$user_data = get_user_data($pdo, $MNr);

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

// Tab mit allen Features laden (außer vorgefertigte Gruppen)
require_once __DIR__ . '/tab_opinion.php';
