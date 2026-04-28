<?php
session_start();
require_once 'config.php';
require_once 'notifications_functions.php';

// PDO-Verbindung erstellen
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
    exit;
}

header('Content-Type: application/json');

if (!isset($_SESSION['member_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$notification_id = intval($_POST['notification_id'] ?? 0);
$member_id = $_SESSION['member_id'];

$success = mark_notification_read($pdo, $notification_id, $member_id);
echo json_encode(['success' => $success]);
