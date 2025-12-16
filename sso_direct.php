<?php
/**
 * sso_direct.php - Entry-Point für SSOdirekt-Modus
 *
 * Eigenständige Seite mit:
 * - SSO-Integration via Session
 * - Custom Styling (anpassbar an Vereins-Design)
 * - "Zurück zum VTool" statt "Abmelden"
 * - Konfigurierbarer Footer
 */

// Session starten
session_start();

// Basis-Konfiguration laden
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config_adapter.php';
require_once __DIR__ . '/member_functions.php';

// SSO-Modus: svmembers VIEW initialisieren (falls MEMBER_SOURCE = 'berechtigte')
// Ermöglicht, dass alle JOIN svmembers automatisch mit externer Tabelle funktionieren
ensure_svmembers_view($pdo);

// DISPLAY_MODE auf SSOdirekt setzen (überschreibt config_adapter.php für diese Seite)
define('DISPLAY_MODE_OVERRIDE', 'SSOdirekt');

// SSO-Integration: Mitgliedsnummer aus Session holen
$MNr = get_sso_membership_number();

// User laden via MNr
if ($MNr) {
    // Prüfen ob bereits korrekt in Session
    // WICHTIG: Verwende die gleichen Session-Keys wie index.php!
    if (!isset($_SESSION['member_id'])) {
        // User aus berechtigte-Tabelle laden
        $current_user = get_member_by_membership_number($pdo, $MNr);

        if ($current_user) {
            // In Session speichern (gleiche Keys wie index.php!)
            $_SESSION['member_id'] = $current_user['member_id'];
            $_SESSION['role'] = $current_user['role'];

            // Optional: Für spätere Verwendung
            $_SESSION['first_name'] = $current_user['first_name'];
            $_SESSION['last_name'] = $current_user['last_name'];
            $_SESSION['email'] = $current_user['email'];
        }
    } else {
        // Bereits in Session, User-Objekt aus DB neu laden
        $current_user = get_member_by_id($pdo, $_SESSION['member_id']);
    }
} else {
    // Kein MNr vorhanden
    $current_user = null;
}

// Falls User nicht gefunden, Fehlerseite anzeigen
if (!isset($current_user) || !$current_user) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $SSO_DIRECT_CONFIG['page_title']; ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
                background: #f5f5f5;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
            }
            .error-container {
                background: white;
                border: 2px solid #e74c3c;
                border-radius: 8px;
                padding: 40px;
                max-width: 500px;
                text-align: center;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 {
                color: #e74c3c;
                margin-top: 0;
            }
            .back-button {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 24px;
                background: <?php echo $SSO_DIRECT_CONFIG['primary_color']; ?>;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-weight: bold;
            }
            .back-button:hover {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>⚠️ Zugriff verweigert</h1>
            <p>Sie müssen angemeldet sein, um die Sitzungsverwaltung zu nutzen.</p>
            <p>Bitte melden Sie sich zunächst im Hauptsystem an.</p>
            <a href="<?php echo $SSO_DIRECT_CONFIG['back_button_url']; ?>" class="back-button">
                <?php echo $SSO_DIRECT_CONFIG['back_button_text']; ?>
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// User ist eingeloggt - Sitzungsverwaltung laden
// index.php wird die Custom Styles und den angepassten Header/Footer rendern
include __DIR__ . '/index.php';
?>
