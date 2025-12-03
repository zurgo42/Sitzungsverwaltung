<?php
/**
 * Performance-Diagnose: Misst Ausführungszeiten
 */
$timings = [];
$start_total = microtime(true);

// Test 1: Session Start
$t1 = microtime(true);
session_start();
$timings['session_start'] = round((microtime(true) - $t1) * 1000, 2);

// Test 2: Config laden
$t2 = microtime(true);
require_once('../config.php');
$timings['config_load'] = round((microtime(true) - $t2) * 1000, 2);

// Test 3: Functions laden
$t3 = microtime(true);
require_once('../functions.php');
$timings['functions_load'] = round((microtime(true) - $t3) * 1000, 2);

// Test 4: Collab Functions laden
$t4 = microtime(true);
require_once('../functions_collab_text.php');
$timings['collab_functions_load'] = round((microtime(true) - $t4) * 1000, 2);

// Test 5: Einfache DB-Query
$t5 = microtime(true);
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM svmembers");
$result = $stmt->fetch();
$timings['simple_query'] = round((microtime(true) - $t5) * 1000, 2);

// Test 6: JOIN Query (wie getCollabText)
$t6 = microtime(true);
$stmt = $pdo->query("
    SELECT t.*, m.first_name, m.last_name
    FROM svcollab_texts t
    JOIN svmembers m ON t.initiator_member_id = m.member_id
    LIMIT 1
");
$result = $stmt->fetch();
$timings['join_query'] = round((microtime(true) - $t6) * 1000, 2);

// Test 7: getCollabText (falls Text existiert)
if ($result) {
    $t7 = microtime(true);
    $text = getCollabText($pdo, 1);
    $timings['getCollabText'] = round((microtime(true) - $t7) * 1000, 2);
}

$timings['total'] = round((microtime(true) - $start_total) * 1000, 2);

// PHP-Konfiguration
$php_info = [
    'php_version' => PHP_VERSION,
    'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
    'xdebug_enabled' => extension_loaded('xdebug'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
];

header('Content-Type: application/json');
echo json_encode([
    'timings_ms' => $timings,
    'php_config' => $php_info,
    'warnings' => [
        'opcache' => !$php_info['opcache_enabled'] ? 'OPcache ist DEAKTIVIERT - das verlangsamt PHP erheblich!' : null,
        'xdebug' => $php_info['xdebug_enabled'] ? 'Xdebug ist AKTIVIERT - das verlangsamt PHP um Faktor 5-10!' : null,
    ]
], JSON_PRETTY_PRINT);
