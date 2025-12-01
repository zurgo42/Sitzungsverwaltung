<?php
/**
 * migrate_comment_tables.php - Migration Script
 * Benennt Kommentar-Tabellen um, um sv-Präfix hinzuzufügen
 *
 * WICHTIG: Dieses Script nur EINMAL ausführen!
 *
 * Ausführung: php tools/migrate_comment_tables.php
 */

require_once __DIR__ . '/../config.php';

echo "Migration: Kommentar-Tabellen umbenennen\n";
echo "==========================================\n\n";

// PDO-Verbindung erstellen
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
} catch (PDOException $e) {
    die("✗ Datenbankverbindung fehlgeschlagen: " . $e->getMessage() . "\n");
}

try {
    // Prüfen ob alte Tabellen existieren
    $stmt = $pdo->query("SHOW TABLES LIKE 'agenda_live_comments'");
    $has_live = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW TABLES LIKE 'agenda_post_comments'");
    $has_post = $stmt->rowCount() > 0;

    if (!$has_live && !$has_post) {
        echo "✓ Keine alten Tabellen gefunden - Migration nicht nötig.\n";
        echo "  Die Tabellen sind bereits korrekt benannt.\n";
        exit(0);
    }

    // Migration starten
    $pdo->beginTransaction();

    // agenda_live_comments umbenennen
    if ($has_live) {
        echo "→ Benenne 'agenda_live_comments' um...\n";

        // Prüfen ob neue Tabelle schon existiert
        $stmt = $pdo->query("SHOW TABLES LIKE 'svagenda_live_comments'");
        if ($stmt->rowCount() > 0) {
            echo "  ⚠ 'svagenda_live_comments' existiert bereits!\n";
            echo "  → Kopiere Daten aus alter Tabelle...\n";

            $pdo->exec("
                INSERT IGNORE INTO svagenda_live_comments
                (comment_id, item_id, member_id, comment_text, created_at)
                SELECT comment_id, item_id, member_id, comment_text, created_at
                FROM agenda_live_comments
            ");

            $pdo->exec("DROP TABLE agenda_live_comments");
            echo "  ✓ Daten kopiert und alte Tabelle gelöscht\n";
        } else {
            $pdo->exec("RENAME TABLE agenda_live_comments TO svagenda_live_comments");
            echo "  ✓ Erfolgreich umbenannt\n";
        }
    }

    // agenda_post_comments umbenennen
    if ($has_post) {
        echo "→ Benenne 'agenda_post_comments' um...\n";

        // Prüfen ob neue Tabelle schon existiert
        $stmt = $pdo->query("SHOW TABLES LIKE 'svagenda_post_comments'");
        if ($stmt->rowCount() > 0) {
            echo "  ⚠ 'svagenda_post_comments' existiert bereits!\n";
            echo "  → Kopiere Daten aus alter Tabelle...\n";

            $pdo->exec("
                INSERT IGNORE INTO svagenda_post_comments
                (comment_id, item_id, member_id, comment_text, created_at)
                SELECT comment_id, item_id, member_id, comment_text, created_at
                FROM agenda_post_comments
            ");

            $pdo->exec("DROP TABLE agenda_post_comments");
            echo "  ✓ Daten kopiert und alte Tabelle gelöscht\n";
        } else {
            $pdo->exec("RENAME TABLE agenda_post_comments TO svagenda_post_comments");
            echo "  ✓ Erfolgreich umbenannt\n";
        }
    }

    $pdo->commit();

    echo "\n✓ Migration erfolgreich abgeschlossen!\n";
    echo "  Alle Kommentar-Tabellen haben jetzt das 'sv'-Präfix.\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ FEHLER bei Migration:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\nBitte prüfen Sie die Datenbank manuell!\n";
    exit(1);
}
?>
