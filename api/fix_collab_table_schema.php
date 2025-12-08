<?php
/**
 * Fix: Allow NULL values in meeting_id column for svcollab_texts table
 */
session_start();
require_once('../config.php');
require_once('../functions.php');

header('Content-Type: application/json');

try {
    // First, check current structure
    $stmt = $pdo->query("DESCRIBE svcollab_texts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $meeting_id_info = null;
    foreach ($columns as $col) {
        if ($col['Field'] === 'meeting_id') {
            $meeting_id_info = $col;
            break;
        }
    }

    $result = [
        'before' => $meeting_id_info,
        'action' => 'none'
    ];

    // Check if meeting_id allows NULL
    if ($meeting_id_info && $meeting_id_info['Null'] === 'NO') {
        // Need to alter the table
        $pdo->exec("ALTER TABLE svcollab_texts MODIFY COLUMN meeting_id INT NULL COMMENT 'NULL = Allgemeiner Text (Vorstand+GF+Ass), sonst Meeting-spezifisch'");

        // Check after alteration
        $stmt = $pdo->query("DESCRIBE svcollab_texts");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $col) {
            if ($col['Field'] === 'meeting_id') {
                $result['after'] = $col;
                break;
            }
        }

        $result['action'] = 'altered';
        $result['success'] = true;
        $result['message'] = 'Table structure updated - meeting_id now allows NULL values';
    } else {
        $result['success'] = true;
        $result['message'] = 'Table structure is already correct - meeting_id allows NULL';
    }

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
