<?php
/**
 * config_adapter.EXAMPLE.php
 *
 * TEMPLATE für Integration mit bestehendem System
 *
 * ANLEITUNG:
 * 1. Kopieren Sie diese Datei zu config_adapter.php
 * 2. Passen Sie die Pfade und Spaltennamen an Ihr System an
 * 3. Testen Sie die Integration
 */

// ============================================
// 1. BESTEHENDES SYSTEM EINBINDEN
// ============================================

// ANPASSEN: Pfad zu Ihrer bestehenden Config
require_once __DIR__ . '/../ihre_bestehende_config.php';

// Sitzungsverwaltung Config einbinden
require_once __DIR__ . '/config.php';

// ============================================
// 2. SSO INTEGRATION
// ============================================

// ANPASSEN: Wie wird die Member-ID in Ihrem System gesetzt?
// Option A: Direkt aus Variable
if (isset($MNr) && !isset($_SESSION['member_id'])) {
    $_SESSION['member_id'] = $MNr;
}

// Option B: Aus Ihrer Session
// if (isset($_SESSION['user_id']) && !isset($_SESSION['member_id'])) {
//     $_SESSION['member_id'] = $_SESSION['user_id'];
// }

// ============================================
// 3. DATENBANK-MAPPING
// ============================================

/**
 * Holt Mitglied aus Ihrer berechtigte-Tabelle
 *
 * ANPASSEN: Spaltennamen entsprechend Ihrer Tabellenstruktur
 */
function get_member_by_id($pdo, $member_id) {
    $stmt = $pdo->prepare("
        SELECT
            member_id,           -- ANPASSEN: Ihre ID-Spalte (z.B. MNr AS member_id)
            first_name,          -- ANPASSEN: (z.B. vorname AS first_name)
            last_name,           -- ANPASSEN: (z.B. nachname AS last_name)
            email,
            role,                -- ANPASSEN: (z.B. rolle AS role)
            phone,
            is_active            -- ANPASSEN: oder 1 AS is_active falls nicht vorhanden
        FROM berechtigte         -- ANPASSEN: Ihre Tabelle
        WHERE member_id = ?      -- ANPASSEN: Ihre ID-Spalte
    ");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    // Optional: Rolle mappen falls Ihre Rollennamen anders sind
    if ($member && isset($member['role'])) {
        $member['role'] = map_role($member['role']);
    }

    return $member;
}

/**
 * Holt alle aktiven Mitglieder
 *
 * ANPASSEN: Spaltennamen und Aktivitätsbedingung
 */
function get_all_members($pdo) {
    $stmt = $pdo->query("
        SELECT
            member_id,           -- ANPASSEN: Ihre ID-Spalte
            first_name,          -- ANPASSEN: vorname AS first_name
            last_name,           -- ANPASSEN: nachname AS last_name
            email,
            role,                -- ANPASSEN: rolle AS role
            phone
        FROM berechtigte         -- ANPASSEN: Ihre Tabelle
        WHERE is_active = 1      -- ANPASSEN: Ihre Aktivitätsbedingung
        ORDER BY last_name, first_name
    ");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Optional: Rollen mappen
    foreach ($members as &$member) {
        if (isset($member['role'])) {
            $member['role'] = map_role($member['role']);
        }
    }

    return $members;
}

/**
 * Mapped Ihre Rollennamen auf Sitzungsverwaltungs-Rollen
 *
 * ANPASSEN: Falls Ihre Rollennamen anders sind
 */
function map_role($original_role) {
    $role_mapping = [
        // Ihre Rolle => Sitzungsverwaltungs-Rolle
        'admin'       => 'vorstand',
        'manager'     => 'gf',
        'assistant'   => 'assistenz',
        'teamleader'  => 'führungsteam',
        'member'      => 'mitglied',

        // Falls bereits korrekt benannt, 1:1 Mapping
        'vorstand'    => 'vorstand',
        'gf'          => 'gf',
        'assistenz'   => 'assistenz',
        'führungsteam'=> 'führungsteam',
        'mitglied'    => 'mitglied',
    ];

    $role_lower = strtolower($original_role);
    return $role_mapping[$role_lower] ?? 'mitglied';  // Default: mitglied
}

/**
 * Prüft ob User Admin ist (Vorstand, GF, Assistenz)
 */
function is_admin($member) {
    if (!$member) return false;

    $admin_roles = ['vorstand', 'gf', 'assistenz'];
    return in_array(strtolower($member['role']), $admin_roles);
}

/**
 * Prüft ob User Leadership-Rolle hat
 */
function is_leadership($member) {
    if (!$member) return false;

    $leadership_roles = ['vorstand', 'gf', 'assistenz', 'führungsteam'];
    return in_array(strtolower($member['role']), $leadership_roles);
}

// ============================================
// 4. CURRENT USER LADEN
// ============================================

// Aktuellen User aus Session laden
if (isset($_SESSION['member_id'])) {
    $current_user = get_member_by_id($pdo, $_SESSION['member_id']);

    if ($current_user) {
        // Zusätzliche Flags setzen
        $current_user['is_admin'] = is_admin($current_user);
        $current_user['is_leadership'] = is_leadership($current_user);

        // Optional: In Session cachen
        $_SESSION['current_user'] = $current_user;
    } else {
        // User nicht gefunden
        // ANPASSEN: Pfad zu Ihrer Login-Seite
        header('Location: /ihre_login_seite.php');
        exit;
    }
} else {
    // Nicht eingeloggt
    // ANPASSEN: Pfad zu Ihrer Login-Seite
    header('Location: /ihre_login_seite.php');
    exit;
}

// ============================================
// 5. DEBUG (Optional - für Integration)
// ============================================

// Temporär aktivieren zum Debuggen:
// if (isset($_GET['debug']) && $current_user['is_admin']) {
//     echo '<pre style="background: #f0f0f0; padding: 20px; margin: 20px; border: 2px solid #333;">';
//     echo "<h3>🔧 DEBUG MODE</h3>\n";
//     echo "SSO Variable \$MNr: " . ($MNr ?? 'nicht gesetzt') . "\n";
//     echo "Session member_id: " . ($_SESSION['member_id'] ?? 'nicht gesetzt') . "\n\n";
//     echo "Current User:\n";
//     print_r($current_user);
//     echo "\n\nAll Members (erste 5):\n";
//     print_r(array_slice(get_all_members($pdo), 0, 5));
//     echo '</pre>';
//
//     // Nicht weitermachen im Debug-Modus
//     // exit;
// }

?>
