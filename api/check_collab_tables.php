<?php
/**
 * Diagnostic: Check if all collaborative text tables exist
 */
session_start();
require_once('../config.php');
require_once('../functions.php');

header('Content-Type: application/json');

$required_tables = [
    'svcollab_texts',
    'svcollab_text_paragraphs',
    'svcollab_text_locks',
    'svcollab_text_participants',
    'svcollab_text_versions'
];

$result = [];
foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        $result[$table] = $exists ? 'EXISTS' : 'MISSING';

        if ($exists) {
            // Check column count
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $result[$table . '_columns'] = count($columns);
        }
    } catch (PDOException $e) {
        $result[$table] = 'ERROR: ' . $e->getMessage();
    }
}

// Also check if there are any users with the right roles
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM svmembers WHERE role IN ('vorstand', 'gf', 'assistenz')");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['users_with_access'] = $count['count'];
} catch (PDOException $e) {
    $result['users_with_access'] = 'ERROR: ' . $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
