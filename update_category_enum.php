<?php
/**
 * update_category_enum.php - Kategorie-ENUM in Datenbank aktualisieren
 * Erweitert die agenda_items.category um fehlende Werte
 */

require_once 'config.php';

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

    echo "<h2>Kategorie-ENUM aktualisieren</h2>";

    // Datenbanktabelle aktualisieren
    $sql = "ALTER TABLE agenda_items
    MODIFY COLUMN category ENUM('information', 'klaerung', 'diskussion', 'aussprache', 'antrag_beschluss', 'wahl', 'bericht', 'sonstiges') DEFAULT 'information'";

    $pdo->exec($sql);

    echo "<p style='color: green;'>✅ Datenbanktabelle agenda_items.category erfolgreich aktualisiert!</p>";
    echo "<p><strong>Jetzt verfügbar:</strong></p>";
    echo "<ul>";
    echo "<li>information</li>";
    echo "<li>klaerung</li>";
    echo "<li>diskussion</li>";
    echo "<li>aussprache</li>";
    echo "<li>antrag_beschluss</li>";
    echo "<li>wahl</li>";
    echo "<li>bericht</li>";
    echo "<li>sonstiges</li>";
    echo "</ul>";

    echo "<p><a href='index.php'>→ Zurück zur Anwendung</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
    die();
}
?>
