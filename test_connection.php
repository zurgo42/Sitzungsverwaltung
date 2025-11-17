<?php
/**
 * test_connection.php - Diagnose-Script
 * Testet ob alle Komponenten funktionieren
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnose-Test</h1>";

// Test 1: Config laden
echo "<h2>1. Config laden...</h2>";
try {
    require_once 'config.php';
    echo "✅ config.php geladen<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "<br>";
    die();
}

// Test 2: PDO-Verbindung
echo "<h2>2. Datenbankverbindung...</h2>";
try {
    $test_pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ PDO-Verbindung erfolgreich<br>";
} catch (PDOException $e) {
    echo "❌ PDO-Fehler: " . $e->getMessage() . "<br>";
    die();
}

// Test 3: config_adapter.php laden
echo "<h2>3. config_adapter.php laden...</h2>";
try {
    require_once 'config_adapter.php';
    echo "✅ config_adapter.php geladen<br>";
    echo "MEMBER_SOURCE: " . MEMBER_SOURCE . "<br>";
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "<br>";
    die();
}

// Test 4: member_functions.php laden
echo "<h2>4. member_functions.php laden...</h2>";
try {
    require_once 'member_functions.php';
    echo "✅ member_functions.php geladen<br>";
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "<br>";
    die();
}

// Test 5: Mitglieder laden
echo "<h2>5. Mitglieder laden...</h2>";
try {
    $members = get_all_members($test_pdo);
    echo "✅ " . count($members) . " Mitglieder gefunden<br>";
    if (count($members) > 0) {
        echo "Erstes Mitglied: " . $members[0]['first_name'] . " " . $members[0]['last_name'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "<br>";
    die();
}

// Test 6: Test-Login
echo "<h2>6. Test-Authentifizierung...</h2>";
echo "Bitte geben Sie eine Test-Email ein (oder lassen Sie leer zum Überspringen):<br>";
echo '<form method="POST">
    Email: <input type="email" name="test_email"><br>
    Passwort: <input type="password" name="test_password"><br>
    <button type="submit">Testen</button>
</form>';

if (isset($_POST['test_email']) && !empty($_POST['test_email'])) {
    try {
        $result = authenticate_member($test_pdo, $_POST['test_email'], $_POST['test_password']);
        if ($result) {
            echo "✅ Login erfolgreich!<br>";
            echo "Member-ID: " . $result['member_id'] . "<br>";
            echo "Name: " . $result['first_name'] . " " . $result['last_name'] . "<br>";
            echo "Role: " . $result['role'] . "<br>";
        } else {
            echo "❌ Login fehlgeschlagen (falsche Credentials)<br>";
        }
    } catch (Exception $e) {
        echo "❌ Fehler: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>✅ Alle Tests bestanden!</h2>";
echo "<p><a href='index.php'>Zurück zu index.php</a></p>";
?>
