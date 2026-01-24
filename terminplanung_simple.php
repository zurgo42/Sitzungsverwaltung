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

// Voraussetzungen prüfen
if (!isset($pdo)) {
    die('FEHLER: $pdo nicht definiert. Bitte PDO-Verbindung vor dem Include erstellen.');
}

if (!isset($MNr) || empty($MNr)) {
    die('FEHLER: $MNr nicht definiert. Bitte Mitgliedsnummer übergeben.');
}

// Session-Context minimal aufsetzen (für tab_termine.php)
if (!isset($_SESSION['member_id'])) {
    $_SESSION['member_id'] = $MNr;
}

// Standalone-Modus aktivieren (versteckt vorgefertigte Gruppen)
$standalone_mode = true;

// Tab mit allen Features laden (außer vorgefertigte Gruppen)
require_once __DIR__ . '/tab_termine.php';
