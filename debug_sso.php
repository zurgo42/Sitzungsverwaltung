<?php
/**
 * debug_sso.php - SSO-Modus Debug
 *
 * WICHTIG: Nach Debug SOFORT löschen!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h1>SSO Debug</h1>";
echo "<pre>";

echo "=== DISPLAY MODE ===\n";
echo "DISPLAY_MODE_OVERRIDE definiert: " . (defined('DISPLAY_MODE_OVERRIDE') ? 'JA' : 'NEIN') . "\n";
if (defined('DISPLAY_MODE_OVERRIDE')) {
    echo "DISPLAY_MODE_OVERRIDE Wert: " . DISPLAY_MODE_OVERRIDE . "\n";
}
echo "\n";

echo "=== SESSION ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session MNr: " . ($_SESSION['MNr'] ?? 'NICHT GESETZT') . "\n";
echo "Session member_id: " . ($_SESSION['member_id'] ?? 'NICHT GESETZT') . "\n";
echo "\n";

echo "=== CONFIG ADAPTER ===\n";
require_once 'config_adapter.php';
echo "REQUIRE_LOGIN: " . (defined('REQUIRE_LOGIN') ? (REQUIRE_LOGIN ? 'TRUE' : 'FALSE') : 'nicht definiert') . "\n";
echo "MEMBER_SOURCE: " . (defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'nicht definiert') . "\n";
echo "SSO_SOURCE: " . (defined('SSO_SOURCE') ? SSO_SOURCE : 'nicht definiert') . "\n";
echo "DISPLAY_MODE (config): " . (defined('DISPLAY_MODE') ? DISPLAY_MODE : 'nicht definiert') . "\n";
echo "\n";

echo "=== SSO CONFIG ===\n";
if (isset($SSO_DIRECT_CONFIG)) {
    echo "SSO_DIRECT_CONFIG gesetzt: JA\n";
    echo "back_button_url: " . ($SSO_DIRECT_CONFIG['back_button_url'] ?? 'nicht gesetzt') . "\n";
    echo "back_button_text: " . ($SSO_DIRECT_CONFIG['back_button_text'] ?? 'nicht gesetzt') . "\n";
} else {
    echo "SSO_DIRECT_CONFIG gesetzt: NEIN\n";
}
echo "\n";

// Index.php laden um $display_mode zu sehen
echo "=== LADE INDEX.PHP ===\n";
ob_start();
define('DISPLAY_MODE_OVERRIDE', 'SSOdirekt');
require_once 'index.php';
$output = ob_get_clean();

echo "\nHinweis: index.php wurde geladen (Output unterdrückt)\n";
echo "Wenn du den vollen Output sehen willst, kommentiere die ob_* Zeilen aus.\n";

echo "</pre>";

echo "<h2 style='color: red;'>⚠️ Diese Datei jetzt löschen!</h2>";
?>
