<?php
/**
 * testfragen_standalone.php - Standalone IQ-Test-Aufgaben-Verwaltung
 * Erstellt: 19.11.2025
 *
 * VERWENDUNG:
 * ===========
 *
 * In der Sitzungsverwaltung:
 * - Automatisch über index.php?tab=testfragen integriert
 * - Nutzt functions.php für Datenbank-Zugriff
 *
 * In anderen Anwendungen:
 * - Per include einbinden:
 *   <?php
 *     require_once 'pfad/zu/testfragen_standalone.php';
 *   ?>
 * - Voraussetzungen:
 *   - $pdo: PDO-Datenbankverbindung
 *   - $MNr: Mitgliedsnummer des eingeloggten Users (für berechtigte-Tabelle)
 *   - Upload-Verzeichnis: uploads/testfragen/ (wird automatisch erstellt)
 *
 * DATENBANK-KOMPATIBILITÄT:
 * =========================
 * - Erkennt automatisch ob members oder berechtigte Tabelle verwendet wird
 * - Nutzt Adapter-System für Portabilität
 * - Benötigt Tabellen: testfragen, testkommentar (siehe schema_testfragen.sql)
 */

// ============================================
// UMGEBUNGS-ERKENNUNG
// ============================================

// Session starten falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prüfen ob wir in der Sitzungsverwaltung sind
$is_sitzungsverwaltung = file_exists(__DIR__ . '/functions.php');

if ($is_sitzungsverwaltung) {
    // In Sitzungsverwaltung: functions.php nutzen
    require_once __DIR__ . '/functions.php';
    require_login();

    $current_user = get_current_member();
    $currentMemberID = $_SESSION['member_id'] ?? 0;

} else {
    // In anderer Anwendung: Direkter Zugriff auf berechtigte-Tabelle

    // Hilfsfunktionen für berechtigte-Mapping (müssen VOR Verwendung definiert sein)
    if (!function_exists('determine_role_testfragen')) {
        function determine_role_testfragen($funktion, $aktiv) {
            if ($aktiv == 19) return 'vorstand';
            $roleMapping = [
                'GF' => 'gf',
                'SV' => 'assistenz',
                'RL' => 'fuehrungsteam',
                'AD' => 'Mitglied',
                'FP' => 'Mitglied'
            ];
            return $roleMapping[$funktion] ?? 'Mitglied';
        }
    }

    if (!function_exists('is_admin_user_testfragen')) {
        function is_admin_user_testfragen($funktion, $mnr) {
            return in_array($funktion, ['GF', 'SV']) || $mnr == '0495018';
        }
    }

    // Prüfen ob Voraussetzungen erfüllt sind
    if (!isset($pdo)) {
        die('FEHLER: $pdo nicht definiert. Bitte PDO-Verbindung vor dem Include erstellen.');
    }

    if (!isset($MNr)) {
        die('FEHLER: $MNr nicht definiert. Bitte Mitgliedsnummer setzen.');
    }

    // User aus berechtigte-Tabelle holen
    $stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE MNr = ?");
    $stmt->execute([$MNr]);
    $ber = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ber) {
        die('FEHLER: Benutzer nicht gefunden');
    }

    // In Standard-Format umwandeln
    $current_user = [
        'member_id' => $ber['ID'],
        'membership_number' => $ber['MNr'],
        'first_name' => $ber['Vorname'],
        'last_name' => $ber['Name'],
        'email' => $ber['eMail'],
        'role' => determine_role_testfragen($ber['Funktion'], $ber['aktiv']),
        'is_admin' => is_admin_user_testfragen($ber['Funktion'], $ber['MNr'])
    ];
    $currentMemberID = $current_user['member_id'];
}

// Berechtigungen
$isAdmin = ($current_user['membership_number'] == '0495018') || $current_user['is_admin']; // TODO: Auf Rollen-System umstellen
$isRedaktion = isset($_GET['redaktion']) && $isAdmin; // TODO: Redaktions-Rolle

// Upload-Verzeichnis
$uploadDir = __DIR__ . '/uploads/testfragen/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ============================================
// POST-HANDLER
// ============================================

$errors = [];
$messages = [];
$showForm = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ===== TESTFRAGE EINREICHEN =====
    if ($_POST['action'] === 'submit_question') {
        $isFigural = (int)($_POST['figural'] ?? 0);

        // Validierung
        $aufgabe = trim($_POST['aufgabe'] ?? '');
        $richtig = (int)($_POST['richtig'] ?? 0);
        $regel = trim($_POST['regel'] ?? '');
        $inhalt = (int)($_POST['inhalt'] ?? 0);
        $schwer = (int)($_POST['schwer'] ?? 0);

        // Pflichtfelder prüfen
        if (empty($aufgabe)) $errors[] = "Aufgabenstellung fehlt";
        if ($richtig < 1 || $richtig > 5) $errors[] = "Bitte gib an, welche Antwort richtig ist (1-5)";
        if (empty($regel)) $errors[] = "Beschreibung der Regel fehlt";
        if ($inhalt < 1 || $inhalt > 4) $errors[] = "Bitte wähle einen Inhaltsbereich";
        if ($schwer < 1 || $schwer > 5) $errors[] = "Bitte schätze die Schwierigkeit ein";

        // Antworten prüfen (bei Textaufgaben)
        if (!$isFigural) {
            for ($i = 1; $i <= 5; $i++) {
                $antwort = trim($_POST["antwort$i"] ?? '');
                if (empty($antwort)) {
                    $errors[] = "Antwort $i fehlt";
                }
            }
        }

        // Datei-Uploads prüfen (bei figuralen Aufgaben)
        if ($isFigural) {
            $hasCompleteSet = !empty($_FILES['files']['name'][0]); // Komplett-Bild
            $hasIndividual = true;
            for ($i = 1; $i <= 5; $i++) {
                if (empty($_FILES['files']['name'][$i])) {
                    $hasIndividual = false;
                }
            }

            if (!$hasCompleteSet && !$hasIndividual) {
                $errors[] = "Bei figuralen Aufgaben: Entweder Komplett-Bild ODER alle 5 Einzelbilder hochladen";
            }
        }

        // Wenn keine Fehler: Speichern
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Testfrage einfügen (ANONYM - ohne member_id)
                $stmt = $pdo->prepare("
                    INSERT INTO testfragen
                    (aufgabe, antwort1, antwort2, antwort3, antwort4, antwort5,
                     richtig, regel, inhalt, tinhalt, inhaltw, tinhaltw, schwer,
                     is_figural, datum)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $aufgabe,
                    $_POST['antwort1'] ?? '',
                    $_POST['antwort2'] ?? '',
                    $_POST['antwort3'] ?? '',
                    $_POST['antwort4'] ?? '',
                    $_POST['antwort5'] ?? '',
                    $richtig,
                    $regel,
                    $inhalt,
                    $_POST['tinhalt'] ?? '',
                    (int)($_POST['inhaltw'] ?? 0),
                    $_POST['tinhaltw'] ?? '',
                    $schwer,
                    $isFigural
                ]);

                $frageId = $pdo->lastInsertId();

                // Dateien hochladen
                if (!empty($_FILES['files']['name'])) {
                    for ($i = 0; $i <= 5; $i++) {
                        if (!empty($_FILES['files']['name'][$i]) &&
                            $_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {

                            $tmpName = $_FILES['files']['tmp_name'][$i];
                            $origName = $_FILES['files']['name'][$i];

                            // Validierung
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $tmpName);
                            finfo_close($finfo);

                            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            if (!in_array($mimeType, $allowedMimes)) {
                                throw new Exception("Nur Bilder erlaubt (JPG, PNG, GIF, WEBP)");
                            }

                            // Sicherer Dateiname
                            $ext = pathinfo($origName, PATHINFO_EXTENSION);
                            $newName = 'frage_' . $frageId . '_file' . $i . '_' . time() . '.' . $ext;

                            if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                                // In DB speichern
                                $updateStmt = $pdo->prepare("UPDATE testfragen SET file$i = ? WHERE id = ?");
                                $updateStmt->execute([$newName, $frageId]);
                            }
                        }
                    }
                }

                $pdo->commit();
                $messages[] = "Vielen Dank! Dein Vorschlag wurde gespeichert.";
                $showForm = false;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Fehler beim Speichern: " . ($isAdmin ? $e->getMessage() : 'Bitte Administrator kontaktieren');
                error_log("Testfragen Fehler: " . $e->getMessage());
            }
        }
    }

    // ===== KOMMENTAR ABSENDEN =====
    if ($_POST['action'] === 'submit_comment') {
        $kommentar = trim($_POST['kommentar'] ?? '');

        if (strlen($kommentar) > 5) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO testkommentar (member_id, kommentar, datum, todo)
                    VALUES (?, ?, NOW(), 'offen')
                ");
                $stmt->execute([$currentMemberID, $kommentar]);
                $messages[] = "Vielen Dank! Dein Kommentar wurde übermittelt.";
                $showForm = false;
            } catch (PDOException $e) {
                $errors[] = "Fehler beim Speichern des Kommentars";
                error_log("Kommentar Fehler: " . $e->getMessage());
            }
        } else {
            $errors[] = "Kommentar ist zu kurz";
        }
    }
}

// ============================================
// VIEW RENDERING
// ============================================

// Wenn in Sitzungsverwaltung integriert, nutze die bestehenden Tab-Dateien
if ($is_sitzungsverwaltung && file_exists(__DIR__ . '/tab_testfragen.php')) {
    include __DIR__ . '/tab_testfragen.php';
    return; // Beende hier
}

// ============================================
// STANDALONE-RENDERING
// ============================================

// CSS für Standalone-Modus
if (!$is_sitzungsverwaltung) {
    echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IQ-Test Aufgaben einreichen</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        .content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #333;
            margin-top: 0;
        }
        h2 { font-size: 1.75rem; margin-bottom: 20px; }
        h3 { font-size: 1.1rem; }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        /* Info Box */
        .info-box, .info-details {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-details summary {
            cursor: pointer;
            padding: 5px;
            user-select: none;
        }
        .details-content {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #b3d9ff;
        }

        /* Form Styles */
        .testfragen-form {
            max-width: 1000px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .form-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .form-group.flex-2 { flex: 2; }
        .form-group.flex-1 { flex: 1; }
        .form-group.full-width { flex-basis: 100%; }

        .form-group label {
            font-weight: 500;
            margin-bottom: 6px;
            color: #495057;
            font-size: 0.9375rem;
        }
        .form-control {
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.9375rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #5568d3;
            box-shadow: 0 0 0 3px rgba(85, 104, 211, 0.1);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        .help-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 6px;
            font-style: italic;
        }

        /* Radio Groups */
        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        .radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .radio-label:hover {
            border-color: #5568d3;
            background: #f8f9ff;
        }
        .radio-label input[type="radio"] {
            margin: 0;
        }
        .inline-input {
            padding: 6px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #5568d3;
            color: white;
        }
        .btn-primary:hover {
            background: #3d4fb8;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-large {
            padding: 14px 32px;
            font-size: 1.125rem;
        }
        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
        }

        /* Comment Form */
        .comment-form {
            margin-top: 20px;
        }

        /* Utilities */
        .mt-20 { margin-top: 20px; }
        .mt-40 { margin-top: 40px; }
        .mb-20 { margin-bottom: 20px; }
        .text-muted { color: #6c757d; }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .content {
                padding: 20px;
            }
            .form-row {
                flex-direction: column;
            }
            .radio-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="content">';
}

// Errors/Messages anzeigen
if (!empty($errors)) {
    echo '<div class="alert alert-error">';
    foreach ($errors as $error) {
        echo '<p>❌ ' . htmlspecialchars($error) . '</p>';
    }
    echo '</div>';
}

if (!empty($messages)) {
    echo '<div class="alert alert-success">';
    foreach ($messages as $message) {
        echo '<p>✅ ' . htmlspecialchars($message) . '</p>';
    }
    echo '</div>';
}

// Hauptinhalt
if (!$showForm) {
    // Erfolgs-Nachricht und Kommentar-Formular
    echo '<h2>Wie geht es nun weiter?</h2>';
    echo '<div class="info-box">';
    echo '<p>Als nächstes sichten wir die Aufgaben auf Vollständigkeit und Schlüssigkeit. Sobald genug Aufgaben vorliegen, werden alle Ms erneut eingeladen, um sich an ihrer Lösung zu versuchen!</p>';
    echo '<p>Die Antworten werden dann statistisch analysiert, sodass in die Endfassung nur diejenigen Aufgaben eingehen, die dabei am besten abschneiden.</p>';
    echo '</div>';

    echo '<h3>Allgemeine Anmerkungen</h3>';
    echo '<p>Wenn Du allgemeine Anmerkungen (nicht zu der von dir eingereichten Frage) für uns hast, kannst Du sie hier loswerden:</p>';

    echo '<form method="post" class="comment-form">';
    echo '<input type="hidden" name="action" value="submit_comment">';
    echo '<textarea name="kommentar" rows="5" placeholder="Deine Anmerkung..." class="form-control"></textarea>';
    echo '<p class="help-text">Datenschutz: Wird separat gespeichert, nur mit deiner MNr für Rückfragen</p>';
    echo '<button type="submit" class="btn btn-primary">Absenden</button>';
    echo '</form>';

    echo '<p class="mt-20">';
    echo '<a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" class="btn btn-secondary">Neue Aufgabe eingeben</a>';
    echo '</p>';

} else {
    // Hauptformular
    echo '<h2>IQ-Test entwickeln: Testfragen finden</h2>';

    echo '<details class="info-details" open>';
    echo '<summary><strong>Liebe MitMs,</strong> wir wollen einen neuen "Spieltest" entwickeln - Details hier:</summary>';
    echo '<div class="details-content">';
    echo '<p>Der Test soll Interessierten eine einigermaßen aussagekräftige Einschätzung geben, in welchem IQ-Bereich sie vermutlich "landen" würden.</p>';
    echo '<p>Aussagekräftige IQ-Tests zu entwickeln, ist eine aufwändige Sache. Deshalb würden wir uns gern die Möglichkeit offenhalten, die eingereichten Aufgaben für die Entwicklung eines "richtigen" Tests weiterzuverwenden.</p>';
    echo '<p>Wenn Deine Aufgabe es in die finale Version schafft, wirst Du natürlich im Testmanual namentlich genannt!</p>';
    echo '<p>Der bisherige Spieletest ist <a href="https://www.mensa.de/ueber-den-iq/online-tests-raetsel/online-iq-test/" target="_blank">hier</a> zu finden.</p>';
    echo '</div>';
    echo '</details>';

    echo '<p class="mt-20"><strong>Jetzt bist Du gefragt!</strong> Denk Dir Aufgaben aus, die logisch zu lösen sind. Das können sprachliche, rechnerische oder räumlich-bildhafte Inhalte sein.</p>';

    // Template einbinden
    include __DIR__ . '/templates/testfragen_form.php';
}

// Redaktionsansicht (wenn berechtigt)
if ($isRedaktion) {
    echo '<hr class="mt-40">';
    echo '<h2>Redaktionsansicht</h2>';
    include __DIR__ . '/templates/testfragen_redaktion.php';
}

// HTML schließen im Standalone-Modus
if (!$is_sitzungsverwaltung) {
    echo '</div>
</body>
</html>';
}
?>
