<?php
/**
 * tab_testfragen.php - IQ-Test-Aufgaben-Verwaltung
 * Modernisierte Version mit sicherem Code
 *
 * Mitglieder können Testfragen einreichen (textlich oder figural)
 * Redaktion kann alle Einreichungen sichten
 */

require_once 'functions.php';
require_login();

$currentMember = get_current_member();
$currentMemberID = $_SESSION['member_id'] ?? 0;

// Berechtigungen
$isAdmin = ($currentMemberID == '0495018'); // TODO: Auf Rollen-System umstellen
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

                // Testfrage einfügen
                $stmt = $pdo->prepare("
                    INSERT INTO testfragen
                    (member_id, aufgabe, antwort1, antwort2, antwort3, antwort4, antwort5,
                     richtig, regel, inhalt, tinhalt, inhaltw, tinhaltw, schwer,
                     is_figural, datum)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $currentMemberID,
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
// HTML OUTPUT
// ============================================
?>

<div class="content">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p>❌ <?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <div class="alert alert-success">
            <?php foreach ($messages as $message): ?>
                <p>✅ <?= htmlspecialchars($message) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$showForm): ?>
        <!-- Erfolgs-Nachricht und Kommentar-Formular -->
        <h2>Wie geht es nun weiter?</h2>
        <div class="info-box">
            <p>Als nächstes sichten wir die Aufgaben auf Vollständigkeit und Schlüssigkeit. Sobald genug Aufgaben vorliegen, werden alle Ms erneut eingeladen, um sich an ihrer Lösung zu versuchen!</p>
            <p>Die Antworten werden dann statistisch analysiert, sodass in die Endfassung nur diejenigen Aufgaben eingehen, die dabei am besten abschneiden.</p>
        </div>

        <h3>Allgemeine Anmerkungen</h3>
        <p>Wenn Du allgemeine Anmerkungen (nicht zu der von dir eingereichten Frage) für uns hast, kannst Du sie hier loswerden:</p>

        <form method="post" class="comment-form">
            <input type="hidden" name="action" value="submit_comment">
            <textarea name="kommentar" rows="5" placeholder="Deine Anmerkung..." class="form-control"></textarea>
            <p class="help-text">Datenschutz: Wird separat gespeichert, nur mit deiner MNr für Rückfragen</p>
            <button type="submit" class="btn btn-primary">Absenden</button>
        </form>

        <p class="mt-20">
            <a href="index.php?tab=testfragen" class="btn btn-secondary">Neue Aufgabe eingeben</a>
        </p>

    <?php elseif ($showForm): ?>
        <!-- Hauptformular -->
        <h2>IQ-Test entwickeln: Testfragen finden</h2>

        <details class="info-details" open>
            <summary><strong>Liebe MitMs,</strong> wir wollen einen neuen "Spieltest" entwickeln - Details hier:</summary>
            <div class="details-content">
                <p>Der Test soll Interessierten eine einigermaßen aussagekräftige Einschätzung geben, in welchem IQ-Bereich sie vermutlich "landen" würden.</p>
                <p>Aussagekräftige IQ-Tests zu entwickeln, ist eine aufwändige Sache. Deshalb würden wir uns gern die Möglichkeit offenhalten, die eingereichten Aufgaben für die Entwicklung eines "richtigen" Tests weiterzuverwenden.</p>
                <p>Wenn Deine Aufgabe es in die finale Version schafft, wirst Du natürlich im Testmanual namentlich genannt!</p>
                <p>Der bisherige Spieletest ist <a href="https://www.mensa.de/ueber-den-iq/online-tests-raetsel/online-iq-test/" target="_blank">hier</a> zu finden.</p>
            </div>
        </details>

        <p class="mt-20"><strong>Jetzt bist Du gefragt!</strong> Denk Dir Aufgaben aus, die logisch zu lösen sind. Das können sprachliche, rechnerische oder räumlich-bildhafte Inhalte sein.</p>

        <?php include __DIR__ . '/templates/testfragen_form.php'; ?>

    <?php endif; ?>

    <?php if ($isRedaktion): ?>
        <!-- Redaktionsansicht -->
        <hr class="mt-40">
        <h2>Redaktionsansicht</h2>
        <?php include __DIR__ . '/templates/testfragen_redaktion.php'; ?>
    <?php endif; ?>

</div>
