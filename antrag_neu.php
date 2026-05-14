<?php
/**
 * antrag_neu.php - Neuen Antrag erstellen
 *
 * Erzeugt einen neuen Antrag mit automatischer Nummernvergabe
 * und leitet zur Bearbeitung weiter.
 */

session_start();
require_once 'session_config.php';
require_once 'config.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['member_id'];

// Datenbankverbindung
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

/**
 * Generiert eine neue Folgenummer für Anträge
 * Basierend auf der VTool-Funktion folgenummer()
 *
 * Format: A + YYMMDD + laufende Nummer (01-99)
 * Beispiel: A26051401 (erster Antrag am 14.05.2026)
 *
 * @param PDO $pdo Datenbankverbindung
 * @param string $prefix Buchstabe (Standard: 'A' für Antrag)
 * @param string $date Optional: Datum im Format YYMMDD (Standard: heute)
 * @return string Die neue Antragsnummer
 */
function generiereAntragsnummer($pdo, $prefix = 'A', $date = '') {
    // Präfix erstellen (z.B. A260514)
    if ($date === '') {
        $date_part = date('ymd'); // YYMMDD Format
    } else {
        $date_part = $date;
    }

    $prefix_pattern = $prefix . $date_part;

    // Suche nach bestehenden Anträgen mit diesem Präfix
    $stmt = $pdo->prepare("
        SELECT antrnr
        FROM antraege
        WHERE antrnr LIKE ?
        ORDER BY antrnr DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix_pattern . '%']);
    $existing = $stmt->fetch();

    // Wenn kein Eintrag existiert, erste Nummer vergeben
    if (!$existing) {
        return $prefix_pattern . '01';
    }

    // Letzten 2 Stellen extrahieren und hochzählen
    $last_number = (int)substr($existing['antrnr'], -2);
    $new_number = $last_number + 1;

    // Prüfen ob Limit erreicht
    if ($new_number > 99) {
        throw new Exception("Tageslimit für Anträge erreicht (max. 99 pro Tag)");
    }

    // Neue Nummer mit führender Null formatieren
    return $prefix_pattern . str_pad($new_number, 2, '0', STR_PAD_LEFT);
}

try {
    // Neue Antragsnummer generieren
    $neue_antrnr = generiereAntragsnummer($pdo);

    // Neuen Antrag in Datenbank erstellen (Minimalversion im Status A = Editing)
    $stmt = $pdo->prepare("
        INSERT INTO antraege (
            antrnr,
            antrst,
            bart,
            titel,
            beschluss,
            fin,
            lzugriff
        ) VALUES (?, ?, 'A', '', '', '0', NOW())
    ");

    $stmt->execute([
        $neue_antrnr,
        $current_user_id
    ]);

    // Zur Bearbeitung weiterleiten
    header('Location: antrag_bearbeiten.php?antrnr=' . urlencode($neue_antrnr) . '&created=1');
    exit;

} catch (Exception $e) {
    // Fehlerbehandlung
    error_log("Fehler beim Erstellen eines neuen Antrags: " . $e->getMessage());

    // Zurück zur Antragsliste mit Fehlermeldung
    header('Location: antragsliste.php?error=' . urlencode('Fehler beim Erstellen: ' . $e->getMessage()));
    exit;
}
