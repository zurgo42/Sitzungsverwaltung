<?php
/**
 * Debug: Zeigt Admin-Status des aktuellen Users
 */

session_start();
require_once __DIR__ . '/functions.php';

echo "<h1>User Admin Status Debug</h1>";

if (!isset($_SESSION['member_id'])) {
    echo "<p>❌ Nicht eingeloggt</p>";
    exit;
}

echo "<h2>Session Info:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

$current_user = get_member_by_id($pdo, $_SESSION['member_id']);

echo "<h2>User Daten:</h2>";
echo "<pre>";
print_r($current_user);
echo "</pre>";

echo "<h2>Admin Check:</h2>";
$is_admin = is_admin_user($current_user);
echo "<p><strong>is_admin_user():</strong> " . ($is_admin ? '✅ JA' : '❌ NEIN') . "</p>";

if (isset($current_user['is_admin'])) {
    echo "<p><strong>is_admin Feld:</strong> " . ($current_user['is_admin'] ? '1 (JA)' : '0 (NEIN)') . "</p>";
} else {
    echo "<p><strong>is_admin Feld:</strong> ❌ Nicht vorhanden in Tabelle!</p>";
}

if (isset($current_user['role'])) {
    echo "<p><strong>role:</strong> " . htmlspecialchars($current_user['role']) . "</p>";
    $admin_roles = ['vorstand', 'gf', 'assistenz'];
    echo "<p><strong>Ist Admin-Rolle?:</strong> " . (in_array($current_user['role'], $admin_roles) ? '✅ JA' : '❌ NEIN') . "</p>";
} else {
    echo "<p><strong>role:</strong> ❌ Nicht gesetzt</p>";
}

$member_access_level = get_member_access_level($current_user);
echo "<p><strong>Access Level:</strong> $member_access_level</p>";
?>
