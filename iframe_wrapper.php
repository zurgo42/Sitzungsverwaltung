<?php
/**
 * iframe_wrapper.php
 *
 * Spezieller Wrapper für iframe-Einbindung der Sitzungsverwaltung
 * - Übernimmt Session vom Hauptsystem
 * - Vermeidet Style-Konflikte durch Isolation
 * - Lädt config_adapter.php für SSO-Integration
 */

// Session starten (muss gleiche Session wie Hauptsystem nutzen!)
session_start();

// WICHTIG: config_adapter.php einbinden (enthält SSO-Integration)
require_once __DIR__ . '/config_adapter.php';

// Falls User nicht eingeloggt, Fehlermeldung
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
