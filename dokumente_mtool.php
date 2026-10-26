<?php
/**
 * dokumente_mtool.php - Einbindung der Dokumentensammlung in Seiten, die nur die Mitgliedsnummer ($MNr) kennen (z. B. MTool)
 *
 * In mtool.php die bisherige include-Zeile für die alte Dokumentenseite ersetzen durch:
 *     include '../KONA/dokumente_mtool.php';   // Pfad zum Verzeichnis der Sitzungsverwaltung anpassen
 * $MNr muss vor dem include gesetzt sein (wie bisher). Das Skript lädt Konfiguration und Mitgliederfunktionen
 * (functions.php), ermittelt das Mitglied aus $MNr und bindet tab_dokumente_allgemein.php ein.
 * Parameter der aufrufenden Seite (z. B. ?steuer=…) bleiben bei Suche/Filter erhalten.
 * Über das MTool ist die Sammlung standardmäßig schreibgeschützt (auch für Admins); Bearbeiten weiterhin in der Sitzungsverwaltung.
 */
if (empty($MNr)) {
    echo '<p style="color:#c00;padding:1em;">Dokumentensammlung: Keine Mitgliedsnummer übergeben.</p>';
    return;
}

// functions.php lädt config.php, config_adapter.php usw. mit RELATIVEN Pfaden. Aus einem anderen Verzeichnis (MTool) würde PHP
// zuerst im aktuellen Verzeichnis suchen und dort ggf. die config.php des MTool statt der der Sitzungsverwaltung laden.
// Deshalb während des Einbindens das Verzeichnis der Sitzungsverwaltung an den Anfang des include_path setzen.
$__dok_include_path = get_include_path();
set_include_path(__DIR__ . PATH_SEPARATOR . $__dok_include_path);
// functions.php legt eine eigene $pdo-Verbindung an; die Variablen der aufrufenden Seite danach wiederherstellen
$__dok_had_pdo  = isset($pdo);
$__dok_pdo      = $__dok_had_pdo ? $pdo : null;
$__dok_had_user = isset($current_user);
$__dok_user     = $__dok_had_user ? $current_user : null;

try {
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
} finally {
    set_include_path($__dok_include_path);
    if ($__dok_had_pdo)  { $pdo = $__dok_pdo; }  else { unset($pdo); }
    if ($__dok_had_user) { $current_user = $__dok_user; } else { unset($current_user); }
}
