<?php
/**
 * debug_iframe.php - Diagnose-Skript für iframe-Integration
 *
 * Rufen Sie diese Datei direkt auf, um zu sehen, wo der Fehler liegt
 */

// Alle Fehler anzeigen
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 iframe-Integration Debug</h1>";
echo "<pre style='background: #f0f0f0; padding: 20px; border: 2px solid #333;'>\n";

// Session starten
echo "1. Session starten... ";
session_start();
echo "✓ OK\n";

// config.php laden
echo "2. config.php laden... ";
try {
    require_once __DIR__ . '/config.php';
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
    exit;
}

// config_adapter.php laden
echo "3. config_adapter.php laden... ";
try {
    require_once __DIR__ . '/config_adapter.php';
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
    exit;
}

// member_functions.php laden
echo "4. member_functions.php laden... ";
try {
    require_once __DIR__ . '/member_functions.php';
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
    exit;
}

// Konstanten prüfen
echo "\n=== KONFIGURATION ===\n";
echo "MEMBER_SOURCE: " . (defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'NICHT DEFINIERT') . "\n";
echo "REQUIRE_LOGIN: " . (defined('REQUIRE_LOGIN') ? (REQUIRE_LOGIN ? 'true' : 'false') : 'NICHT DEFINIERT') . "\n";
echo "SSO_SOURCE: " . (defined('SSO_SOURCE') ? SSO_SOURCE : 'NICHT DEFINIERT') . "\n";

// Datenbankverbindung prüfen
echo "\n=== DATENBANK ===\n";
echo "PDO existiert: " . (isset($pdo) ? '✓ Ja' : '❌ Nein') . "\n";

if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM berechtigte");
        $count = $stmt->fetchColumn();
        echo "berechtigte-Tabelle: ✓ $count Einträge\n";
    } catch (Exception $e) {
        echo "berechtigte-Tabelle: ❌ FEHLER: " . $e->getMessage() . "\n";
    }
}

// Funktion prüfen
echo "\n=== FUNKTIONEN ===\n";
echo "get_sso_membership_number existiert: " . (function_exists('get_sso_membership_number') ? '✓ Ja' : '❌ Nein') . "\n";
echo "get_member_by_membership_number existiert: " . (function_exists('get_member_by_membership_number') ? '✓ Ja' : '❌ Nein') . "\n";

// MNr holen
echo "\n=== SSO-INTEGRATION ===\n";

if (function_exists('get_sso_membership_number')) {
    $MNr = get_sso_membership_number();
    echo "get_sso_membership_number(): " . ($MNr ?? 'null') . "\n";
} else {
    echo "❌ Funktion get_sso_membership_number() existiert nicht!\n";
    $MNr = null;
}

echo "\$_SESSION['MNr']: " . ($_SESSION['MNr'] ?? 'nicht gesetzt') . "\n";

// Test: MNr manuell setzen
echo "\n=== TEST: MNr manuell setzen ===\n";
$_SESSION['MNr'] = '0495018';
echo "Setze \$_SESSION['MNr'] = '0495018'\n";

$MNr = get_sso_membership_number();
echo "get_sso_membership_number() nach setzen: " . ($MNr ?? 'null') . "\n";

// User laden
echo "\n=== USER LADEN ===\n";

if ($MNr && function_exists('get_member_by_membership_number')) {
    try {
        $current_user = get_member_by_membership_number($pdo, $MNr);

        if ($current_user) {
            echo "✓ User gefunden:\n";
            print_r($current_user);
        } else {
            echo "❌ User mit MNr=$MNr nicht gefunden!\n";

            // Prüfen ob User überhaupt existiert
            $stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE MNr = ?");
            $stmt->execute([$MNr]);
            $raw = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($raw) {
                echo "\nUser existiert in DB (roh):\n";
                print_r($raw);
                echo "\nAber BerechtigteAdapter filtert ihn raus (shouldInclude = false)!\n";
                echo "Prüfen Sie: aktiv > 17 ODER Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')\n";
            } else {
                echo "\nUser existiert nicht in berechtigte-Tabelle!\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ FEHLER beim User laden: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
} else {
    echo "❌ Kann User nicht laden: MNr=" . ($MNr ?? 'null') . "\n";
}

// Alle Members testen
echo "\n=== ALLE MEMBERS ===\n";
if (function_exists('get_all_members')) {
    try {
        $all_members = get_all_members($pdo);
        echo "get_all_members() gibt " . count($all_members) . " Mitglieder zurück\n";

        if (count($all_members) > 0) {
            echo "\nErste 3 Mitglieder:\n";
            print_r(array_slice($all_members, 0, 3));
        }
    } catch (Exception $e) {
        echo "❌ FEHLER: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
?>
