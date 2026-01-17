<?php
/**
 * Migration Runner: KOMPLETT - Alle Kollaboratives Protokoll Migrationen
 *
 * Führt alle 3 Migrationen auf einmal aus
 *
 * Aufruf: php run_complete_migration.php
 * Oder im Browser: http://domain.de/Sitzungsverwaltung/run_complete_migration.php
 */

require_once('config.php');

// CLI oder Browser?
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    // Im Browser: Login-Prüfung
    session_start();
    if (!isset($_SESSION['member_id'])) {
        die('❌ Nicht eingeloggt. Bitte erst einloggen.');
    }
    echo '<pre>';
}

try {
    // Datenbankverbindung
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "🔌 Datenbankverbindung erfolgreich\n\n";

    // Migration laden
    $migration_file = __DIR__ . '/migrations/COMPLETE_collab_protocol.sql';

    if (!file_exists($migration_file)) {
        die("❌ Migration nicht gefunden: $migration_file\n");
    }

    $sql = file_get_contents($migration_file);

    // Kommentare entfernen (-- Zeilen)
    $lines = explode("\n", $sql);
    $cleaned_lines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Überspringe Kommentar-Zeilen und leere Zeilen
        if (empty($trimmed) || strpos($trimmed, '--') === 0) {
            continue;
        }
        $cleaned_lines[] = $line;
    }
    $sql_cleaned = implode("\n", $cleaned_lines);

    // SQL in einzelne Statements aufteilen (am Semikolon)
    $statements = array_filter(
        array_map('trim', explode(';', $sql_cleaned)),
        function($stmt) {
            return !empty($stmt);
        }
    );

    echo "📋 Migration: COMPLETE Kollaboratives Protokoll\n";
    echo "➡️  Führe " . count($statements) . " SQL-Statements aus...\n\n";

    $success_count = 0;
    $skip_count = 0;
    $error_count = 0;

    foreach ($statements as $i => $statement) {
        try {
            $pdo->exec($statement);
            $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 80);
            echo "✅ [" . ($i + 1) . "] " . $preview . "...\n";
            $success_count++;
        } catch (PDOException $e) {
            // Bereits existiert? Dann OK
            if (strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "⚠️  [" . ($i + 1) . "] Bereits vorhanden (übersprungen)\n";
                $skip_count++;
            } else {
                echo "❌ [" . ($i + 1) . "] Fehler: " . $e->getMessage() . "\n";
                $error_count++;
                // Bei kritischen Fehlern abbrechen
                if (strpos($e->getMessage(), 'foreign key') === false) {
                    throw $e;
                }
            }
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Migration abgeschlossen!\n\n";
    echo "📊 Statistik:\n";
    echo "   ✓ Erfolgreich: $success_count\n";
    echo "   ⚠ Übersprungen: $skip_count\n";
    echo "   ✗ Fehler: $error_count\n\n";

    echo "🎉 Kollaboratives Protokoll ist jetzt vollständig installiert!\n\n";
    echo "📝 Alle 3 Migrations-Stufen:\n";
    echo "   1️⃣  Basis-System (v2.0)\n";
    echo "   2️⃣  Force-Update Tracking (v2.x)\n";
    echo "   3️⃣  Master-Slave Queue-System (v3.0)\n\n";

    echo "ℹ️  Nächste Schritte:\n";
    echo "   1. Hard-Refresh im Browser (Ctrl+F5)\n";
    echo "   2. Meeting öffnen und Kollaborativ-Modus testen\n";
    echo "   3. Console checken: Sollte 'v3.0' anzeigen\n\n";

    if (!$is_cli) {
        echo '<a href="index.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #4caf50; color: white; text-decoration: none; border-radius: 4px;">Zurück zur Anwendung</a>';
    }

} catch (Exception $e) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "❌ FEHLER bei Migration:\n";
    echo $e->getMessage() . "\n\n";

    if ($e instanceof PDOException) {
        echo "💡 Tipps:\n";
        echo "   - Ist MySQL gestartet?\n";
        echo "   - Sind die Zugangsdaten in config.php korrekt?\n";
        echo "   - Hat der DB-User ausreichend Rechte?\n";
    }

    exit(1);
}

if (!$is_cli) {
    echo '</pre>';
}
