<?php
/**
 * Test: Session-Informationen anzeigen
 * Hilft beim Debuggen von Cookie-Sharing-Problemen
 */

require_once 'session_config.php';
session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug Info</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .box { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .label { font-weight: bold; color: #333; }
        .value { color: #0066cc; }
    </style>
</head>
<body>
    <h1>🔍 Session Debug Info</h1>

    <div class="box">
        <div class="label">Session Name:</div>
        <div class="value"><?php echo session_name(); ?></div>
        <div style="font-size: 12px; color: #666; margin-top: 5px;">
            Dies ist der Cookie-Name. VTool muss den gleichen Namen verwenden!
        </div>
    </div>

    <div class="box">
        <div class="label">Session ID:</div>
        <div class="value"><?php echo session_id(); ?></div>
    </div>

    <div class="box">
        <div class="label">$_SESSION['MNr']:</div>
        <div class="value"><?php echo isset($_SESSION['MNr']) ? $_SESSION['MNr'] : '❌ NICHT GESETZT'; ?></div>
        <div style="font-size: 12px; color: #666; margin-top: 5px;">
            Wenn leer: Cookie-Sharing funktioniert nicht oder Session ist abgelaufen
        </div>
    </div>

    <div class="box">
        <div class="label">Session Cookie Parameters:</div>
        <pre><?php print_r(session_get_cookie_params()); ?></pre>
    </div>

    <div class="box">
        <div class="label">$_SESSION Inhalt:</div>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

    <div class="box">
        <div class="label">Alle Cookies:</div>
        <pre><?php print_r($_COOKIE); ?></pre>
    </div>

    <h2>📋 Vergleich mit VTool:</h2>
    <ol>
        <li>Öffnen Sie VTool und loggen Sie sich ein</li>
        <li>Öffnen Sie dort Developer Tools (F12) → Application → Cookies</li>
        <li>Notieren Sie den Cookie-Namen (z.B. "PHPSESSID" oder anders)</li>
        <li>Vergleichen Sie mit dem "Session Name" oben</li>
        <li><strong>Wenn unterschiedlich:</strong> Session-Name muss in session_config.php gesetzt werden!</li>
    </ol>
</body>
</html>
