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

// Voraussetzungen prüfen
if (!isset($pdo)) {
    die('FEHLER: $pdo nicht definiert. Bitte PDO-Verbindung vor dem Include erstellen.');
}

if (!isset($MNr) || empty($MNr)) {
    die('FEHLER: $MNr nicht definiert. Bitte Mitgliedsnummer übergeben.');
}

// Session-Context minimal aufsetzen (für tab_opinion.php)
$_SESSION['member_id'] = $MNr;

// Standalone-Modus aktivieren (versteckt vorgefertigte Gruppen)
$standalone_mode = true;

// User-Data-Helper laden (MNr → User-Daten)
if (!function_exists('get_user_data')) {
    require_once __DIR__ . '/user_data_helper.php';
}

// Member-Functions laden (wenn noch nicht geladen)
if (!function_exists('get_member_by_id')) {
    require_once __DIR__ . '/member_functions.php';
}

// User-Daten über MNr laden (aus berechtigte oder LDAP)
$user_data = get_user_data($pdo, $MNr);

if (!$user_data) {
    die('FEHLER: Konnte User mit MNr ' . htmlspecialchars($MNr) . ' nicht laden. Weder in berechtigte-Tabelle noch in LDAP gefunden.');
}

// Prüfen ob User in berechtigte-Tabelle existiert
$stmt = $pdo->prepare("SELECT MNr, Vorname, Name, eMail, rolle FROM berechtigte WHERE MNr = ?");
$stmt->execute([$MNr]);
$db_user = $stmt->fetch(PDO::FETCH_ASSOC);

// $current_user im erwarteten Format aufbauen
$current_user = [
    'member_id' => $MNr,  // MNr als ID verwenden
    'first_name' => $user_data['first_name'],
    'last_name' => $user_data['last_name'],
    'email' => $user_data['email'],
    'role' => $db_user['rolle'] ?? 'mitglied'  // Default: mitglied
];

// Tab mit allen Features laden (außer vorgefertigte Gruppen)
require_once __DIR__ . '/tab_opinion.php';
