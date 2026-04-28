<?php
header('Content-Type: application/json');

// Test 1: Einfaches JSON
if (!isset($_GET['step'])) {
    echo json_encode(['success' => true, 'message' => 'Basic JSON works', 'step' => 0]);
    exit;
}

// Test 2: Session
if ($_GET['step'] == 1) {
    session_start();
    echo json_encode(['success' => true, 'message' => 'Session works', 'step' => 1, 'has_member_id' => isset($_SESSION['member_id'])]);
    exit;
}

// Test 3: Config
if ($_GET['step'] == 2) {
    session_start();
    require_once 'config.php';
    echo json_encode(['success' => true, 'message' => 'Config loaded', 'step' => 2, 'has_pdo' => isset($pdo)]);
    exit;
}

// Test 4: Functions
if ($_GET['step'] == 3) {
    session_start();
    require_once 'config.php';
    require_once 'notifications_functions.php';
    echo json_encode(['success' => true, 'message' => 'Functions loaded', 'step' => 3, 'function_exists' => function_exists('mark_all_notifications_read')]);
    exit;
}

// Normal execution
session_start();
require_once 'config.php';
require_once 'notifications_functions.php';

if (!isset($_SESSION['member_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Not authenticated or wrong method']);
    exit;
}

try {
    $member_id = $_SESSION['member_id'];
    $success = mark_all_notifications_read($pdo, $member_id);
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
