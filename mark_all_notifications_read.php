<?php
// Error Reporting aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Alle vorherigen Ausgaben löschen (wichtig für sauberes JSON)
if (ob_get_level()) ob_end_clean();

session_start();

try {
    require_once 'config.php';
} catch (Exception $e) {
    die(json_encode(['success' => false, 'error' => 'Config load failed: ' . $e->getMessage()]));
}

try {
    require_once 'notifications_functions.php';
} catch (Exception $e) {
    die(json_encode(['success' => false, 'error' => 'Functions load failed: ' . $e->getMessage()]));
}

header('Content-Type: application/json');

// Debug logging
error_log("mark_all_notifications_read.php called");

if (!isset($_SESSION['member_id'])) {
    error_log("No member_id in session");
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Not POST method");
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $member_id = $_SESSION['member_id'];
    error_log("Marking all read for member_id: $member_id");

    $success = mark_all_notifications_read($pdo, $member_id);

    error_log("Result: " . ($success ? 'true' : 'false'));
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Error in mark_all_notifications_read.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

