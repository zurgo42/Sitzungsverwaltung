<?php
/**
 * Fix NULL-Werte nach Migration
 *
 * Setzt collaborative_protocol = 0 für alle Meetings die vor der Migration erstellt wurden
 *
 * Aufruf: php fix_null_values.php
 * Oder im Browser: http://domain.de/Sitzungsverwaltung/fix_null_values.php
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

    // Prüfen wie viele Meetings NULL-Werte haben
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM svmeetings WHERE collaborative_protocol IS NULL");
    $result = $stmt->fetch();
    $null_count = $result['count'];

    if ($null_count == 0) {
        echo "✅ Alle Meetings haben bereits Werte - kein Fix nötig!\n";
    } else {
        echo "📝 Gefunden: $null_count Meetings mit NULL-Werten\n";
        echo "🔧 Setze collaborative_protocol = 0 (Standard-Modus)...\n\n";

        // NULL-Werte auf 0 setzen
        $stmt = $pdo->exec("UPDATE svmeetings SET collaborative_protocol = 0 WHERE collaborative_protocol IS NULL");

        echo "✅ $null_count Meetings aktualisiert!\n\n";
        echo "ℹ️  Diese Meetings verwenden jetzt den Standard-Modus (nur Protokollführung schreibt)\n";
        echo "💡 Du kannst den Modus pro Meeting in 'Meeting bearbeiten' ändern\n";
    }

} catch (Exception $e) {
    echo "\n❌ Fehler:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

if (!$is_cli) {
    echo '</pre>';
    echo '<a href="index.php?tab=meetings" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #4caf50; color: white; text-decoration: none; border-radius: 4px;">Zurück zu Meetings</a>';
}
