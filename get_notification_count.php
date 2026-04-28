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
    echo json_encode(['count' => 0]);
    exit;
}

header('Content-Type: application/json');

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$member_id = $_SESSION['member_id'];
$count = count_unread_notifications($pdo, $member_id);
echo json_encode(['count' => $count]);
