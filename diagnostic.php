<?php
session_start();
require_once 'config.php';

// Nur für eingeloggte Benutzer
if (!isset($_SESSION['member_id'])) {
    die("Nicht eingeloggt");
}

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

header('Content-Type: text/plain; charset=utf-8');

echo "=== ANTRAEGE TABLE COLUMNS ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM antraege");
foreach ($stmt->fetchAll() as $col) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== BERECHTIGTE TABLE COLUMNS ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM berechtigte");
foreach ($stmt->fetchAll() as $col) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== RESSORTLISTE TABLE COLUMNS ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM ressortliste");
foreach ($stmt->fetchAll() as $col) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== SAMPLE RESSORTLISTE DATA (first 3 rows) ===\n";
$stmt = $pdo->query("SELECT * FROM ressortliste LIMIT 3");
foreach ($stmt->fetchAll() as $row) {
    echo "  " . print_r($row, true) . "\n";
}
