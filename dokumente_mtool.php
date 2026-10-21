<?php
/**
 * dokumente_mtool.php - Einbindung der Dokumentensammlung in Seiten, die nur die Mitgliedsnummer ($MNr) kennen (z. B. MTool)
 *
 * In mtool.php die bisherige include-Zeile für die alte Dokumentenseite ersetzen durch:
 *     include '../Sitzungsverwaltung/dokumente_mtool.php';   // Pfad zum Verzeichnis der Sitzungsverwaltung anpassen
 * $MNr muss vor dem include gesetzt sein (wie bisher). Das Skript lädt Konfiguration und Mitgliederfunktionen
 * (functions.php), ermittelt das Mitglied aus $MNr und bindet tab_dokumente_allgemein.php ein.
 * Parameter der aufrufenden Seite (z. B. ?steuer=…) bleiben bei Suche/Filter erhalten.
 */
if (empty($MNr)) {
    echo '<p style="color:#c00;padding:1em;">Dokumentensammlung: Keine Mitgliedsnummer übergeben.</p>';
    return;
}

require_once __DIR__ . '/functions.php';   // $pdo, Adapter, Mitgliederfunktionen

$current_user = get_member_by_membership_number($pdo, $MNr);
if (!$current_user) {
    echo '<p style="color:#c00;padding:1em;">Dokumentensammlung: Mitglied zu dieser Mitgliedsnummer nicht gefunden.</p>';
    return;
}

include __DIR__ . '/tab_dokumente_allgemein.php';
