<?php
require_once('config.php');
require_once('db_connection.php');

echo "=== svmembers Table Schema ===\n";
$result = $pdo->query("DESCRIBE svmembers");
$columns = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']} - {$col['Default']}\n";
}

echo "\n=== Sample Member Data (first 2 rows) ===\n";
$members = $pdo->query("SELECT * FROM svmembers LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
foreach ($members as $member) {
    print_r($member);
}
?>
