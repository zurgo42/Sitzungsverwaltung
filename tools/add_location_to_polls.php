<?php
/**
 * Migration: location-Spalte zur polls-Tabelle hinzufügen
 *
 * Diese Migration fügt die location-Spalte zur polls-Tabelle hinzu,
 * falls sie noch nicht existiert.
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "<h2>Migration: location-Spalte zur polls-Tabelle hinzufügen</h2>";

    // Prüfen, ob die Spalte bereits existiert
    $stmt = $pdo->query("SHOW COLUMNS FROM polls LIKE 'location'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "<p style='color: orange;'>⚠ Spalte 'location' existiert bereits - Migration wird übersprungen</p>";
    } else {
        // Spalte hinzufügen
        $pdo->exec("
            ALTER TABLE polls
            ADD COLUMN location VARCHAR(255) DEFAULT NULL
            AFTER meeting_id
        ");

        echo "<p style='color: green;'>✓ Spalte 'location' wurde erfolgreich zur polls-Tabelle hinzugefügt</p>";
    }

    // Prüfen, ob video_link existiert
    $stmt = $pdo->query("SHOW COLUMNS FROM polls LIKE 'video_link'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "<p style='color: orange;'>⚠ Spalte 'video_link' existiert bereits - Migration wird übersprungen</p>";
    } else {
        // Spalte hinzufügen
        $pdo->exec("
            ALTER TABLE polls
            ADD COLUMN video_link VARCHAR(500) DEFAULT NULL
            AFTER location
        ");

        echo "<p style='color: green;'>✓ Spalte 'video_link' wurde erfolgreich zur polls-Tabelle hinzugefügt</p>";
    }

    // Prüfen, ob duration existiert
    $stmt = $pdo->query("SHOW COLUMNS FROM polls LIKE 'duration'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "<p style='color: orange;'>⚠ Spalte 'duration' existiert bereits - Migration wird übersprungen</p>";
    } else {
        // Spalte hinzufügen
        $pdo->exec("
            ALTER TABLE polls
            ADD COLUMN duration INT DEFAULT NULL
            AFTER video_link
        ");

        echo "<p style='color: green;'>✓ Spalte 'duration' wurde erfolgreich zur polls-Tabelle hinzugefügt</p>";
    }

    echo "<p style='color: green; font-weight: bold;'>✓✓✓ Migration erfolgreich abgeschlossen!</p>";
    echo "<p><a href='../index.php'>→ Zurück zur Anwendung</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Fehler: " . $e->getMessage() . "</p>";
    die();
}
?>
