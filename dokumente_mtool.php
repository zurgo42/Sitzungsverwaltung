<?php
/**
 * dokumente_mtool.php - Einbindung der Dokumentensammlung in Seiten, die nur die Mitgliedsnummer ($MNr) kennen (z. B. MTool)
 *
 * In mtool.php die bisherige include-Zeile für die alte Dokumentenseite ersetzen durch:
 *     include '../Sitzungsverwaltung/dokumente_mtool.php';   // Pfad zum Verzeichnis der Sitzungsverwaltung anpassen
 * $MNr muss vor dem include gesetzt sein (wie bisher). Das Skript lädt Konfiguration und Mitgliederfunktionen
 * (functions.php), ermittelt das Mitglied aus $MNr und bindet tab_dokumente_allgemein.php ein.
 * Parameter der aufrufenden Seite (z. B. ?steuer=…) bleiben bei Suche/Filter erhalten.
 * Über das MTool ist die Sammlung standardmäßig schreibgeschützt (auch für Admins); Bearbeiten weiterhin in der Sitzungsverwaltung.
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

// Standard: nur Ansicht (keine Bearbeitung, Uploads, Verzeichnis-Einstellungen), damit es keine Konflikte mit dem Upload-Verzeichnis
// gibt. Zum Bearbeiten über das MTool in mtool.php VOR dem include setzen:  $DOK_READONLY = false;
if (!isset($DOK_READONLY)) {
    $DOK_READONLY = true;
}

include __DIR__ . '/tab_dokumente_allgemein.php';
