<?php
/**
 * MinD-Referentenliste - Modernisierte Version
 * Hauptdatei für die Referentenverwaltung
 */

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includes
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/ReferentenModel.php';
require_once __DIR__ . '/includes/Security.php';

// Initialisierung
$model = new ReferentenModel();
$errors = [];
$messages = [];

// Benutzer-Authentifizierung (angepasst an bestehendes System)
$mNr = $_SERVER['REMOTE_USER'] ?? null;

// Für Tests kann MNr auch aus Session kommen
if (!$mNr && isset($_SESSION['test_mnr'])) {
    $mNr = $_SESSION['test_mnr'];
}

// Logging
if ($mNr) {
    Security::logAccess($mNr, 'referenten_access');
}

// Konstanten
$kategorien = [
    "", "Naturwissenschaft", "Psycho-/Soziologie", "Pädagogik/Erziehung",
    "Philosophisches", "Intelligenz, Hochbegabung", "Medizin, Gesundheit",
    "Berufsleben", "Recht", "Musik", "Kunst, Kreatives", "Hobby, Fertigkeiten",
    "Lebenshilfe, Praktisches", "Reisen, Bildung", "Sonstiges"
];

$wasOptionen = [
    "Vortrag", "Kurzvortrag mit Diskussion", "Moderierte Diskussion",
    "Workshop", "Seminar", "Performance", "Sonstiges - siehe Inhalt"
];

$regionOptionen = [
    "bundesweit", "eher im Norden", "eher im Westen", "eher im Osten",
    "in der Mitte Deutschlands", "eher im Süden",
    "in meinem LocSec-Gebiet und ggf. angrenzend",
    "nur in der Nähe zu meinem Wohnort", "auf Anfrage"
];

// Steuerung
$steuer = $_GET['steuer'] ?? $_POST['steuer'] ?? 0;
$steuer = (int)$steuer;

// AJAX-Request für Vortragsdetails
if ($steuer === 17) {
    $id = (int)($_GET['ID'] ?? 0);
    $requestMNr = $_GET['MNr'] ?? '';

    if ($id > 0) {
        $vortrag = $model->getVortrag($id);
        $person = $model->getPersonData($requestMNr);

        if ($vortrag && $person) {
            include __DIR__ . '/templates/vortrag_detail.php';
        }
    }
    exit;
}

// Anzahl aktiver Vorträge
$anzaktive = $model->countActiveVortraege();

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Schutz
    if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "Sicherheitsfehler. Bitte laden Sie die Seite neu.";
    } else {
        // Speichern von Daten
        if ($steuer === 11 || $steuer === 15) {
            try {
                // Persönliche Daten
                if (!empty($_POST['Name'])) {
                    $personData = [
                        'MNr' => $mNr,
                        'Vorname' => Security::cleanInput($_POST['Vorname'] ?? ''),
                        'Name' => Security::cleanInput($_POST['Name'] ?? ''),
                        'Titel' => Security::cleanInput($_POST['Titel'] ?? ''),
                        'PLZ' => Security::cleanInput($_POST['PLZ'] ?? ''),
                        'Ort' => Security::cleanInput($_POST['Ort'] ?? ''),
                        'Gebj' => Security::cleanInput($_POST['Gebj'] ?? ''),
                        'Beruf' => Security::cleanInput($_POST['Beruf'] ?? ''),
                        'Telefon' => Security::cleanInput($_POST['Telefon'] ?? ''),
                        'eMail' => Security::cleanInput($_POST['eMail'] ?? '')
                    ];

                    // Validierung
                    if (!empty($personData['eMail']) && !Security::isValidEmail($personData['eMail'])) {
                        $errors[] = "Ungültige E-Mail-Adresse";
                    }

                    if (empty($errors)) {
                        $model->savePersonData($personData);
                    }
                }

                // Vortragsdaten
                if (!empty($_POST['Thema']) && empty($errors)) {
                    $vortragData = [
                        'MNr' => $mNr,
                        'Was' => Security::cleanInput($_POST['Was'] ?? ''),
                        'Wo' => Security::cleanInput($_POST['Wo'] ?? ''),
                        'Entf' => (int)($_POST['Entf'] ?? 0),
                        'Thema' => Security::cleanInput($_POST['Thema'] ?? ''),
                        'Inhalt' => Security::cleanInput($_POST['Inhalt'] ?? ''),
                        'Kategorie' => Security::cleanInput($_POST['Kategorie'] ?? ''),
                        'Equipment' => Security::cleanInput($_POST['Equipment'] ?? ''),
                        'Dauer' => Security::cleanInput($_POST['Dauer'] ?? ''),
                        'Kompetenz' => Security::cleanInput($_POST['Kompetenz'] ?? ''),
                        'Bemerkung' => Security::cleanInput($_POST['Bemerkung'] ?? ''),
                        'aktiv' => isset($_POST['aktiv']) ? 1 : 0,
                        'IP' => Security::getClientIP()
                    ];

                    if ($steuer === 11) {
                        // Neuer Vortrag
                        if ($model->saveVortrag($vortragData)) {
                            $messages[] = "Vortrag wurde erfolgreich gespeichert.";
                        }
                    } elseif ($steuer === 15) {
                        // Vortrag aktualisieren
                        $id = (int)($_POST['ID'] ?? 0);
                        if ($id > 0 && $model->updateVortrag($id, $mNr, $vortragData)) {
                            $messages[] = "Vortrag wurde erfolgreich aktualisiert.";
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Fehler beim Speichern: " . $e->getMessage();
                error_log("Referenten Save Error: " . $e->getMessage());
            }
        }

        // Vortrag zum Bearbeiten laden
        if ($steuer === 8) {
            $editId = (int)($_POST['XThema'] ?? 0);
            if ($editId > 0) {
                $vortragToEdit = $model->getVortrag($editId, $mNr);
                $steuer = 15; // Zum Updaten vorbereiten
            }
        }
    }
}

// Daten für Formular vorbereiten
$personDaten = $model->getPersonData($mNr) ?: ['MNr' => $mNr];
$meineVortraege = $model->getVortraegeByMNr($mNr);

// View-Variablen
$csrfToken = Security::generateCSRFToken();
$pageTitle = "MinD-Referentenliste";

// Template einbinden
if ($steuer === 4) {
    // Liste anzeigen
    $sortBy = $_POST['weiter'] ?? 'PLZ';
    $vortraege = $model->getAllActiveVortraege($sortBy);
    $meinePLZ = $personDaten['PLZ'] ?? 0;

    include __DIR__ . '/templates/liste.php';
} else {
    // Formular anzeigen
    include __DIR__ . '/templates/formular.php';
}
