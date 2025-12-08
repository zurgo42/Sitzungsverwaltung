<?php
/**
 * iframe_wrapper.php
 *
 * Spezieller Wrapper für iframe-Einbindung der Sitzungsverwaltung
 * - Übernimmt Session vom Hauptsystem (inkl. $MNr)
 * - Vermeidet Style-Konflikte durch vollständige Isolation
 * - Nutzt BerechtigteAdapter für externe berechtigte-Tabelle
 */

// Session starten (nutzt gleiche Session wie Hauptsystem!)
session_start();

// Konfiguration laden
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';  // WICHTIG: Initialisiert $pdo
require_once __DIR__ . '/config_adapter.php';
require_once __DIR__ . '/member_functions.php';

// DISPLAY_MODE auf iframe setzen (entfernt Footer, da Parent-Seite eigenen Footer hat)
define('DISPLAY_MODE_OVERRIDE', 'iframe');

// SSO-Integration: Mitgliedsnummer aus Session holen
$MNr = get_sso_membership_number();

// User laden via MNr (überschreibt ggf. falsche member_id vom Hauptsystem)
if ($MNr) {
    // Prüfen ob bereits korrekt in Session (via sv_member_id, nicht member_id vom Hauptsystem!)
    if (!isset($_SESSION['sv_member_id']) || !isset($_SESSION['sv_current_user'])) {
        // User aus berechtigte-Tabelle laden
        $current_user = get_member_by_membership_number($pdo, $MNr);

        if ($current_user) {
            // In Session speichern (mit sv_ Prefix um Kollision mit Hauptsystem zu vermeiden)
            $_SESSION['sv_member_id'] = $current_user['member_id'];
            $_SESSION['sv_current_user'] = $current_user;
        }
    } else {
        // Bereits in Session, von dort laden
        $current_user = $_SESSION['sv_current_user'];
    }
} else {
    // Kein MNr vorhanden
    $current_user = null;
}

// Falls User nicht gefunden, Fehlermeldung anzeigen
if (!isset($current_user) || !$current_user) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sitzungsverwaltung - Nicht angemeldet</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                padding: 40px;
                text-align: center;
                background: #f5f5f5;
            }
            .error-box {
                background: white;
                border: 2px solid #e74c3c;
                border-radius: 8px;
                padding: 30px;
                max-width: 500px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2>⚠️ Nicht angemeldet</h2>
            <p>Sie müssen angemeldet sein, um die Sitzungsverwaltung zu nutzen.</p>
            <p><a href="javascript:parent.location.reload()">Seite neu laden</a></p>

            <?php if (isset($_GET['debug'])): ?>
            <hr>
            <h3>Debug-Info:</h3>
            <pre style="text-align: left; font-size: 11px;">
SSO_SOURCE: <?php echo defined('SSO_SOURCE') ? SSO_SOURCE : 'nicht definiert'; ?>

MEMBER_SOURCE: <?php echo defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'nicht definiert'; ?>

$_SESSION['MNr']: <?php echo $_SESSION['MNr'] ?? 'nicht gesetzt'; ?>

$MNr (via get_sso_membership_number()): <?php echo $MNr ?? 'null'; ?>

$_SESSION['sv_member_id']: <?php echo $_SESSION['sv_member_id'] ?? 'nicht gesetzt'; ?>

$_SESSION['member_id'] (vom Hauptsystem): <?php echo $_SESSION['member_id'] ?? 'nicht gesetzt'; ?>

User laden Versuch:
<?php
if ($MNr) {
    $test_user = get_member_by_membership_number($pdo, $MNr);
    if ($test_user) {
        echo "✓ User gefunden: {$test_user['first_name']} {$test_user['last_name']} (ID: {$test_user['member_id']})\n";
    } else {
        echo "❌ User mit MNr=$MNr nicht gefunden\n";
    }
} else {
    echo "❌ Kein MNr verfügbar\n";
}
?>

$_SESSION keys: <?php print_r(array_keys($_SESSION)); ?>
            </pre>
            <p style="font-size: 11px;">Für vollständiges Debug: /debug_iframe.php aufrufen</p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Sitzungsverwaltung laden
include __DIR__ . '/index.php';

?>

<!-- Automatische Höhenmeldung an Parent-Frame -->
<script>
// Höhe an Parent melden (falls möglich)
function notifyParentHeight() {
    try {
        var height = document.body.scrollHeight;
        parent.postMessage({type: 'resize', height: height}, '*');
    } catch(e) {
        // Ignorieren wenn nicht möglich
    }
}

// Initial
notifyParentHeight();

// Bei Änderungen
window.addEventListener('load', notifyParentHeight);
window.addEventListener('resize', notifyParentHeight);

// Beobachter für DOM-Änderungen
var observer = new MutationObserver(notifyParentHeight);
observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true
});

// Alle 500ms aktualisieren (für Akkordeons, Tabs, etc.)
setInterval(notifyParentHeight, 500);
</script>
