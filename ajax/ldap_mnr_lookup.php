<?php
/**
 * ajax/ldap_mnr_lookup.php
 *
 * Sucht einen Berechtigten per MNr im LDAP-Verzeichnis.
 * GET-Parameter: mnr=<MNr>
 * Antwort: JSON { vorname, name, email } oder { error: "..." }
 *
 * Verbindungslogik angelehnt an ldapsuche_neu() aus VTool.
 * Nur für eingeloggte Nutzer mit aktiv >= 18.
 */

require_once __DIR__ . '/../session_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('SV_CONFIG_LOADED')) require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../member_functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['error' => 'Nicht eingeloggt']); exit;
}
$cu = get_member_by_id($pdo, $_SESSION['member_id']);
if ((int)($cu['aktiv'] ?? 0) < 18 && empty($cu['is_admin'])) {
    echo json_encode(['error' => 'Zugriff verweigert']); exit;
}
if (!defined('LDAP_ENABLED') || !LDAP_ENABLED) {
    echo json_encode(['error' => 'LDAP nicht konfiguriert']); exit;
}
if (!function_exists('ldap_connect')) {
    echo json_encode(['error' => 'PHP-Extension php-ldap fehlt']); exit;
}

$mnr = trim($_GET['mnr'] ?? '');
if ($mnr === '') {
    echo json_encode(['error' => 'Keine MNr angegeben']); exit;
}

// Verbindung (analog zu ldapsuche_neu im VTool)
$ldap = @ldap_connect(LDAP_HOST, LDAP_PORT);
if (!$ldap) {
    echo json_encode(['error' => 'Verbindung zu ' . LDAP_HOST . ' fehlgeschlagen']); exit;
}
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

if (!@ldap_bind($ldap, LDAP_BIND_DN, LDAP_BIND_PW)) {
    echo json_encode(['error' => 'Bind fehlgeschlagen: ' . ldap_error($ldap)]);
    ldap_close($ldap); exit;
}

// Suche: Attribut für MNr, Ergebnis per ldap_get_entries (gibt Attributnamen lowercase zurück)
$filter = '(' . LDAP_MNR_ATTR . '=' . ldap_escape($mnr, '', LDAP_ESCAPE_FILTER) . ')';
$sr     = @ldap_search($ldap, LDAP_BASE_DN, $filter, [LDAP_VORNAME_ATTR, LDAP_NAME_ATTR, LDAP_EMAIL_ATTR]);

if (!$sr || ldap_count_entries($ldap, $sr) === 0) {
    echo json_encode(['error' => 'MNr ' . htmlspecialchars($mnr) . ' nicht im LDAP gefunden']);
    ldap_close($ldap); exit;
}

// ldap_get_entries liefert Attributnamen normalisiert auf lowercase (wie VTool es erwartet)
$entries = ldap_get_entries($ldap, $sr);
$row     = $entries[0];

$get = fn($attr) => $row[strtolower($attr)][0] ?? '';

echo json_encode([
    'vorname' => $get(LDAP_VORNAME_ATTR),
    'name'    => $get(LDAP_NAME_ATTR),
    'email'   => $get(LDAP_EMAIL_ATTR),
]);

ldap_close($ldap);
