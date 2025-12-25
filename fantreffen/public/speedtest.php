<?php
/**
 * Speed-Test - Minimaler Test um Flaschenhals zu finden
 */
$t = ['start' => microtime(true)];

// Test 1: Nur PHP
$t['php_ready'] = microtime(true);

// Test 2: Session
session_start();
$t['session'] = microtime(true);

// Test 3: Datei-Include
require_once __DIR__ . '/../src/Database.php';
$t['require_db'] = microtime(true);

// Test 4: DB-Verbindung
try {
    require_once __DIR__ . '/../config/config.php';
    $t['config'] = microtime(true);

    $db = Database::getInstance();
    $t['db_connect'] = microtime(true);

    // Test 5: Einfache Query
    $db->fetchOne("SELECT 1");
    $t['db_query'] = microtime(true);
} catch (Exception $e) {
    $t['error'] = microtime(true);
    echo "DB-Fehler: " . $e->getMessage() . "\n";
}

$t['end'] = microtime(true);

// Ausgabe
header('Content-Type: text/plain');
echo "=== SPEEDTEST ===\n\n";

$prev = $t['start'];
foreach ($t as $name => $time) {
    if ($name === 'start') continue;
    $delta = ($time - $prev) * 1000;
    $total = ($time - $t['start']) * 1000;
    printf("%-15s %6.0f ms  (gesamt: %6.0f ms)\n", $name, $delta, $total);
    $prev = $time;
}

echo "\n";
printf("TOTAL: %.0f ms\n", ($t['end'] - $t['start']) * 1000);
