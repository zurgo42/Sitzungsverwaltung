<?php
session_start();
require_once 'config.php';
require_once 'notifications_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['member_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$member_id = $_SESSION['member_id'];
$success = mark_all_notifications_read($pdo, $member_id);
echo json_encode(['success' => $success]);
