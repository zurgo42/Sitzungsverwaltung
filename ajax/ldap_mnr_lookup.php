<?php
/**
 * ajax/ldap_mnr_lookup.php
 *
 * Sucht einen Berechtigten per MNr im konfigurierten LDAP-Verzeichnis.
 * GET-Parameter: mnr=<MNr>
 * Antwort: JSON { vorname, name, email } oder { error: "..." }
 *
 * Nur für eingeloggte Nutzer mit aktiv >= 18.
 */

require_once __DIR__ . '/../session_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('SV_CONFIG_LOADED')) require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../member_functions.php';

header('Content-Type: application/json; charset=UTF-8');

// Auth
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}
$cu = get_member_by_id($pdo, $_SESSION['member_id']);
if ((int)($cu['aktiv'] ?? 0) < 18 && empty($cu['is_admin'])) {
    echo json_encode(['error' => 'Zugriff verweigert']);
    exit;
}

// LDAP aktiviert?
if (!defined('LDAP_ENABLED') || !LDAP_ENABLED) {
    echo json_encode(['error' => 'LDAP nicht konfiguriert (LDAP_ENABLED = false in config.php)']);
    exit;
}

$mnr = trim($_GET['mnr'] ?? '');
if ($mnr === '') {
    echo json_encode(['error' => 'Keine MNr angegeben']);
    exit;
}

if (!function_exists('ldap_connect')) {
    echo json_encode(['error' => 'PHP-LDAP-Erweiterung nicht installiert (php-ldap)']);
    exit;
}

$ldap = @ldap_connect(LDAP_HOST, LDAP_PORT);
if (!$ldap) {
    echo json_encode(['error' => 'LDAP-Verbindung zu ' . LDAP_HOST . ':' . LDAP_PORT . ' fehlgeschlagen']);
    exit;
}

ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

$bind_ok = LDAP_BIND_DN
    ? @ldap_bind($ldap, LDAP_BIND_DN, LDAP_BIND_PW)
    : @ldap_bind($ldap);

if (!$bind_ok) {
    echo json_encode(['error' => 'LDAP-Bind fehlgeschlagen: ' . ldap_error($ldap)]);
    ldap_close($ldap);
    exit;
}

$filter = '(' . LDAP_MNR_ATTR . '=' . ldap_escape($mnr, '', LDAP_ESCAPE_FILTER) . ')';
$attrs  = [LDAP_VORNAME_ATTR, LDAP_NAME_ATTR, LDAP_EMAIL_ATTR];
$result = @ldap_search($ldap, LDAP_BASE_DN, $filter, $attrs);

if (!$result || ldap_count_entries($ldap, $result) === 0) {
    echo json_encode(['error' => 'Kein Eintrag für MNr ' . htmlspecialchars($mnr) . ' im LDAP gefunden']);
    ldap_close($ldap);
    exit;
}

$entry = ldap_first_entry($ldap, $result);
$data  = ldap_get_attributes($ldap, $entry);

$get = fn($attr) => $data[$attr][0] ?? '';

echo json_encode([
    'vorname' => $get(LDAP_VORNAME_ATTR),
    'name'    => $get(LDAP_NAME_ATTR),
    'email'   => $get(LDAP_EMAIL_ATTR),
]);

ldap_close($ldap);
