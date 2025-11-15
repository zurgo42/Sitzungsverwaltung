<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Erweiterte Diagnose</h1>";

try {
    echo "<h2>1. Config laden</h2>";
    require_once 'config.php';
    echo "✅ config.php geladen<br>";
    echo "DB: " . DB_NAME . "@" . DB_HOST . "<br>";

    echo "<h2>2. config_adapter.php laden</h2>";
    require_once 'config_adapter.php';
    echo "✅ config_adapter.php geladen<br>";
    echo "MEMBER_SOURCE: <strong>" . MEMBER_SOURCE . "</strong><br>";
    echo "REQUIRE_LOGIN: <strong>" . (REQUIRE_LOGIN ? 'true' : 'false') . "</strong><br>";
    echo "SSO_SOURCE: <strong>" . SSO_SOURCE . "</strong><br>";
    echo "TEST_MEMBERSHIP_NUMBER: <strong>" . TEST_MEMBERSHIP_NUMBER . "</strong><br>";

    echo "<h2>3. PDO-Verbindung</h2>";
    $test_pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ PDO-Verbindung erfolgreich<br>";

    echo "<h2>4. Prüfe ob Tabelle 'berechtigte' existiert</h2>";
    $tables = $test_pdo->query("SHOW TABLES LIKE 'berechtigte'")->fetchAll();
    if (count($tables) > 0) {
        echo "✅ Tabelle 'berechtigte' existiert<br>";

        // Zeige Struktur
        $columns = $test_pdo->query("DESCRIBE berechtigte")->fetchAll();
        echo "<h3>Spalten in 'berechtigte':</h3>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><strong>" . $col['Field'] . "</strong> (" . $col['Type'] . ")</li>";
        }
        echo "</ul>";

        // Zähle Einträge
        $count = $test_pdo->query("SELECT COUNT(*) as cnt FROM berechtigte WHERE aktiv > 0")->fetch();
        echo "Anzahl aktiver Einträge: <strong>" . $count['cnt'] . "</strong><br>";

        // Prüfe ob Test-MNr existiert
        echo "<h3>Suche Test-Mitglied (MNr: " . TEST_MEMBERSHIP_NUMBER . ")</h3>";
        $stmt = $test_pdo->prepare("SELECT * FROM berechtigte WHERE MNr = ?");
        $stmt->execute([TEST_MEMBERSHIP_NUMBER]);
        $test_member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($test_member) {
            echo "✅ Test-Mitglied gefunden!<br>";
            echo "<pre>";
            print_r($test_member);
            echo "</pre>";
        } else {
            echo "❌ Test-Mitglied NICHT gefunden!<br>";
            echo "<strong>Problem:</strong> MNr '" . TEST_MEMBERSHIP_NUMBER . "' existiert nicht in der Tabelle.<br>";
            echo "<strong>Lösung:</strong> Ändern Sie TEST_MEMBERSHIP_NUMBER in config_adapter.php auf eine existierende MNr.<br>";
        }

    } else {
        echo "❌ Tabelle 'berechtigte' existiert NICHT!<br>";
        echo "<strong>Problem:</strong> Die Tabelle wurde noch nicht angelegt.<br>";
        echo "<strong>Lösung:</strong> Entweder:<br>";
        echo "1. Tabelle 'berechtigte' in der Datenbank anlegen<br>";
        echo "2. ODER in config_adapter.php zurück auf 'members' ändern:<br>";
        echo "<code>define('MEMBER_SOURCE', 'members');</code><br>";
    }

    echo "<h2>5. Lade Adapter</h2>";
    require_once 'adapters/MemberAdapter.php';
    echo "✅ MemberAdapter.php geladen<br>";

    $adapter = MemberAdapterFactory::create($test_pdo, MEMBER_SOURCE);
    echo "✅ Adapter erstellt: <strong>" . get_class($adapter) . "</strong><br>";

    echo "<h2>✅ DIAGNOSE ABGESCHLOSSEN</h2>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ FEHLER GEFUNDEN:</h2>";
    echo "<div style='background: #fee; padding: 20px; border: 2px solid red;'>";
    echo "<strong>Fehler:</strong> " . htmlspecialchars($e->getMessage()) . "<br><br>";
    echo "<strong>Datei:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Zeile:</strong> " . $e->getLine() . "<br><br>";
    echo "<strong>Stack Trace:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>
