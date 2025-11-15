<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test 1: Start<br>";

echo "Test 2: Lade config.php<br>";
require_once 'config.php';
echo "- DB_HOST: " . DB_HOST . "<br>";

echo "Test 3: Lade config_adapter.php<br>";
require_once 'config_adapter.php';
echo "- MEMBER_SOURCE: " . MEMBER_SOURCE . "<br>";
echo "- REQUIRE_LOGIN: " . (REQUIRE_LOGIN ? 'true' : 'false') . "<br>";

echo "Test 4: Lade adapters/MemberAdapter.php<br>";
require_once 'adapters/MemberAdapter.php';
echo "- Adapter geladen<br>";

echo "Test 5: Erstelle Adapter<br>";
$test_pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS
);
$adapter = MemberAdapterFactory::create($test_pdo, MEMBER_SOURCE);
echo "- Adapter erstellt: " . get_class($adapter) . "<br>";

echo "<br><strong>✅ ALLE TESTS BESTANDEN!</strong><br>";
echo "<br>Wenn dies funktioniert, liegt das Problem in index.php oder functions.php";
?>
