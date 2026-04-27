<?php
session_start();
require_once 'config.php';
require_once 'notifications_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$member_id = $_SESSION['member_id'];
$count = count_unread_notifications($pdo, $member_id);
echo json_encode(['count' => $count]);
