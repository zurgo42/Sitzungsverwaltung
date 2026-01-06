<?php
/**
 * index.php - Hauptdatei der Sitzungsverwaltung
 * 
 * Diese Datei ist der zentrale Einstiegspunkt der Anwendung und koordiniert:
 * - Login/Logout-Verwaltung
 * - Session-Handling
 * - Routing zwischen verschiedenen Tabs
 * - Einbindung der Processing- und Presentation-Dateien
 * 
 * Letzte Aktualisierung: 28.10.2025 MEZ
 */

// Konfiguration und Hilfsfunktionen laden (MUSS vor Session-Konfiguration stehen)
require_once 'config.php';           // Datenbankverbindung und Konstanten

// ============================================
// SESSION-LIFETIME MANAGEMENT
// ============================================
// Trust-Device Cookie prüfen
$trust_device = isset($_COOKIE['trust_device']) && $_COOKIE['trust_device'] === '1';

// Session-Lifetime setzen basierend auf Trust-Device Status (MUSS vor session_start() stehen)
if ($trust_device) {
    // Vertrauenswürdiges Gerät: Session bleibt bis zum expliziten Logout
    // Cookie-Lifetime auf 0 = bis Browser geschlossen wird
    // gc_maxlifetime auf 30 Tage = Session-Daten bleiben 30 Tage erhalten
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 2592000); // 30 Tage
} else {
    // Normaler Modus: Session läuft nach SESSION_TIMEOUT ab
    ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
}

// Session starten (muss ganz am Anfang stehen, vor jeder Ausgabe)
// Prüfen ob Session bereits gestartet wurde (z.B. durch sso_direct.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ============================================
require_once 'config_adapter.php';   // Konfiguration für Mitgliederquelle
require_once 'member_functions.php'; // Prozedurale Wrapper-Funktionen für Mitglieder
require_once 'functions.php';        // Wiederverwendbare Funktionen

// ============================================
// GLOBALES MEMBERS-ARRAY (für SSO und Standard-Modus)
// ============================================
// Alle Mitglieder EINMAL laden und als assoziatives Array bereitstellen
// Verhindert wiederholte DB-Queries und ermöglicht schnellen Zugriff nach member_id
$GLOBALS['all_members'] = get_all_members($pdo);
$GLOBALS['members_by_id'] = [];
foreach ($GLOBALS['all_members'] as $member) {
    $GLOBALS['members_by_id'][$member['member_id']] = $member;
}

/**
 * Hilfsfunktion: Holt Member-Daten nach ID aus dem globalen Array
 * @param int $member_id
 * @return array|null Member-Daten oder null wenn nicht gefunden
 *
 * HINWEIS: Diese Funktion existiert nur in index.php Kontext!
 * In anderen Kontexten (process_*, module_*) wird get_member_name($pdo, $id) aus module_helpers.php verwendet.
 */
function get_member_from_cache($member_id) {
    if (!$member_id) return null;
    return $GLOBALS['members_by_id'][$member_id] ?? null;
}

// ============================================
// LOGOUT-VERARBEITUNG
// ============================================
// Wenn der Logout-Link geklickt wurde (?logout=1), Session beenden
if (isset($_GET['logout'])) {
    // Session zerstören
    session_destroy();

    // Trust-Device Cookie löschen
    if (isset($_COOKIE['trust_device'])) {
        setcookie('trust_device', '', time() - 3600, '/');
    }

    // Redirect to login or SSO entry point
    header('Location: index.php');
    exit;
}

// ============================================
// TRUST-DEVICE TOGGLE
// ============================================
// Trust-Device Cookie setzen/löschen
if (isset($_GET['toggle_trust_device'])) {
    if (isset($_COOKIE['trust_device']) && $_COOKIE['trust_device'] === '1') {
        // Trust-Device deaktivieren
        setcookie('trust_device', '0', time() - 3600, '/');
    } else {
        // Trust-Device aktivieren (Cookie für 365 Tage)
        setcookie('trust_device', '1', time() + 31536000, '/');
    }

    // Zur gleichen Seite zurück
    header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['tab']) ? '?tab=' . $_GET['tab'] : ''));
    exit;
}

// ============================================
// LOGIN-VERARBEITUNG
// ============================================
// Prüfen ob Login-Formular abgeschickt wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Eingaben bereinigen
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Authentifizierung über Wrapper-Funktion
    // Funktioniert mit members ODER berechtigte Tabelle (siehe config_adapter.php)
    $user = authenticate_member($pdo, $email, $password);

    // Bei erfolgreichem Login
    if ($user) {
        // Session-Variablen setzen
        $_SESSION['member_id'] = $user['member_id'];
        $_SESSION['role'] = $user['role'];

        // Zur Hauptseite weiterleiten
        header('Location: index.php');
        exit;
    } else {
        $login_error = "Ungültige Anmeldedaten";
    }
}

// ============================================
// HILFSFUNKTION: Zugriffsverweigerungs-Seite
// ============================================
/**
 * Zeigt eine dezente Fehlerseite mit Zurück-Link an
 *
 * @param string $title Hauptüberschrift
 * @param string $message Beschreibung des Problems
 * @param string $details Optionale technische Details
 */
function show_access_denied_page($title, $message, $details = '') {
    global $SSO_DIRECT_CONFIG;

    // Zurück-URL aus Konfiguration holen
    $back_url = $SSO_DIRECT_CONFIG['back_button_url'] ?? 'https://aktive.mensa.de/vorstand/vtool.php';
    $back_text = $SSO_DIRECT_CONFIG['back_button_text'] ?? 'Zurück zum VTool';

    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zugriff verweigert - Sitzungsverwaltung</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .error-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 500px;
                width: 100%;
                padding: 40px;
                text-align: center;
            }
            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                font-size: 24px;
                margin-bottom: 16px;
                font-weight: 600;
            }
            .error-message {
                color: #666;
                font-size: 16px;
                line-height: 1.6;
                margin-bottom: 24px;
            }
            .error-details {
                background: #f5f5f5;
                border-left: 4px solid #ffc107;
                padding: 12px 16px;
                margin-bottom: 32px;
                border-radius: 4px;
                font-size: 13px;
                color: #666;
                text-align: left;
            }
            .back-button {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 14px 32px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                font-size: 16px;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }
            .back-button:hover {
                background: #5568d3;
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            }
            .footer-note {
                margin-top: 24px;
                font-size: 13px;
                color: #999;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">🔒</div>
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <div class="error-message">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php if ($details): ?>
                <div class="error-details">
                    ℹ️ <?php echo htmlspecialchars($details); ?>
                </div>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="back-button">
                ← <?php echo htmlspecialchars($back_text); ?>
            </a>
            <div class="footer-note">
                Bei Problemen wende dich bitte an den Support
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// SSO-MODUS (Single Sign-On) - Automatischer Login
// ============================================
// Wenn SSO aktiv ist (REQUIRE_LOGIN = false) und noch keine Session existiert
if (!REQUIRE_LOGIN && !isset($_SESSION['member_id'])) {
    // Mitgliedsnummer aus konfigurierter Quelle holen
    $sso_mnr = get_sso_membership_number();

    if ($sso_mnr) {
        // Mitglied über Mitgliedsnummer laden
        $sso_user = get_member_by_membership_number($pdo, $sso_mnr);

        if ($sso_user) {
            // Automatisch einloggen
            $_SESSION['member_id'] = $sso_user['member_id'];
            $_SESSION['role'] = $sso_user['role'];
            $_SESSION['MNr'] = $sso_mnr;  // Für config_adapter.php - damit API-Calls den richtigen Adapter verwenden

            // Zur Hauptseite weiterleiten
            header('Location: index.php');
            exit;
        } else {
            // Mitglied nicht gefunden - Prüfen ob DB leer ist (nach Reset)
            $stmt = $pdo->query("SELECT COUNT(*) FROM svmembers");
            $member_count = $stmt->fetchColumn();

            if ($member_count == 0) {
                // Datenbank ist leer - Ersten User als Admin anlegen
                // Versuche Daten vom Adapter zu holen
                $member_data = get_member_data_from_adapter($sso_mnr);

                if ($member_data) {
                    // Mit Adapter-Daten anlegen
                    $stmt = $pdo->prepare("
                        INSERT INTO svmembers (
                            membership_number, first_name, last_name, email, phone,
                            role, status, joined_date, created_at
                        ) VALUES (?, ?, ?, ?, ?, 'gf', 'active', NOW(), NOW())
                    ");
                    $stmt->execute([
                        $sso_mnr,
                        $member_data['first_name'] ?? 'Admin',
                        $member_data['last_name'] ?? 'User',
                        $member_data['email'] ?? '',
                        $member_data['phone'] ?? ''
                    ]);
                } else {
                    // Ohne Adapter-Daten - Platzhalter anlegen
                    $stmt = $pdo->prepare("
                        INSERT INTO svmembers (
                            membership_number, first_name, last_name, email,
                            role, status, joined_date, created_at
                        ) VALUES (?, 'Admin', 'User', '', 'gf', 'active', NOW(), NOW())
                    ");
                    $stmt->execute([$sso_mnr]);
                }

                // Neu angelegten User laden und einloggen
                $sso_user = get_member_by_membership_number($pdo, $sso_mnr);
                $_SESSION['member_id'] = $sso_user['member_id'];
                $_SESSION['role'] = $sso_user['role'];
                $_SESSION['MNr'] = $sso_mnr;

                // Zur Hauptseite weiterleiten mit Hinweis
                $_SESSION['success'] = 'Erste Anmeldung nach DB-Reset: Admin-Account wurde automatisch angelegt.';
                header('Location: index.php');
                exit;
            } else {
                // DB ist nicht leer, aber User nicht gefunden
                show_access_denied_page(
                    'Mitgliedsnummer nicht gefunden',
                    'Deine Mitgliedsnummer wurde nicht in der Datenbank gefunden oder ist nicht aktiv.',
                    'MNr: ' . htmlspecialchars($sso_mnr)
                );
            }
        }
    } else {
        // Keine Mitgliedsnummer übergeben
        show_access_denied_page(
            'Systemsicherheit',
            'Deine Mitgliedsnummer fehlt noch. Bitte rufe das VTool auf und klicke dort dann auf "Sitzungen". Dann bist du zuverlässig eingeloggt.',
            'Technischer Hinweis: SSO_SOURCE ist auf "' . SSO_SOURCE . '" konfiguriert'
        );
    }
}

// ============================================
// LOGIN-FORMULAR ANZEIGEN (falls nicht eingeloggt)
// ============================================
// Nur wenn normaler Login-Modus aktiv ist
if (REQUIRE_LOGIN && !isset($_SESSION['member_id'])) {
    // Im Demo-Modus: Zur Welcome-Seite umleiten (außer bei Login-Versuchen)
    if (defined('DEMO_MODE_ENABLED') && DEMO_MODE_ENABLED && !isset($login_error)) {
        header('Location: welcome.php');
        exit;
    }

    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Sitzungsverwaltung</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="login-container">
            <div class="login-box">
                <h1>🏛️ Sitzungsverwaltung</h1>

                <?php if (isset($login_error)): ?>
                    <div class="error-message"><?php echo $login_error; ?></div>
                    <p style="text-align: center; margin-top: 15px;">
                        <a href="welcome.php" style="color: #667eea; text-decoration: none;">← Zurück zur Startseite</a>
                    </p>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>E-Mail:</label>
                        <input type="email" name="email" required autofocus>
                    </div>

                    <div class="form-group">
                        <label>Passwort:</label>
                        <input type="password" name="password" required>
                    </div>

                    <button type="submit" name="login">Anmelden</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit; // Skript hier beenden - nichts weiteres ausführen
}

// ============================================
// AB HIER: NUR NOCH FÜR EINGELOGGTE BENUTZER
// ============================================

// Aktuellen Benutzer aus Datenbank laden
// Nutzt Wrapper-Funktion (funktioniert mit members ODER berechtigte)
$current_user = get_member_by_id($pdo, $_SESSION['member_id']);

// Sicherheitscheck: Wenn User nicht gefunden wurde, Session beenden
if (!$current_user) {
    // Session ist ungültig (z.B. nach DB-Reset oder gelöschter User)
    session_destroy();

    // Wenn SSO-Modus, neu versuchen
    if (!REQUIRE_LOGIN) {
        header('Location: index.php');
        exit;
    }

    // Sonst zum Login
    session_start();
    $_SESSION['error'] = 'Deine Session ist abgelaufen. Bitte melde dich erneut an.';
    header('Location: login.php');
    exit;
}

// Aktiven Tab aus URL ermitteln (Standard: 'meetings')
$active_tab = $_GET['tab'] ?? 'meetings';

// Meeting-ID aus URL holen (falls vorhanden)
$current_meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : null;

// ============================================
// DEMO-MODUS: DYNAMISCHE DATUMSANPASSUNG
// ============================================
if (defined('DEMO_MODE_ENABLED') && DEMO_MODE_ENABLED) {
    require_once 'tools/update_demo_dates.php';
    update_demo_meeting_dates($pdo);
}

// ============================================
// PROCESS-DATEIEN EINBINDEN
// Diese Dateien verarbeiten POST-Requests und führen Datenbankoperationen aus
// Sie werden VOR der HTML-Ausgabe eingebunden
// ============================================

// PROCESS SITZUNGEN
// Wird nur bei POST-Requests auf dem Sitzungen-Tab ausgeführt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_tab === 'meetings') {
    require_once 'process_meetings.php';
}

// PROCESS ABSENCES
// Wird bei POST-Requests auf dem Sitzungen-Tab oder Vertretung-Tab für Abwesenheiten ausgeführt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($active_tab === 'meetings' || $active_tab === 'vertretung')) {
    require_once 'process_absences.php';
}

// PROCESS AGENDA
// Wird geladen wenn eine Meeting-ID vorhanden ist und der Agenda-Tab aktiv ist
// WICHTIG: Wird auch bei GET-Requests geladen, da es Status-Updates verarbeitet
if ($current_meeting_id && isset($_GET['tab']) && $_GET['tab'] === 'agenda') {
    
    // Zuerst Meeting-Details laden
    $meeting = get_meeting_details($pdo, $current_meeting_id);
    
    // Debug-Logging (kann später entfernt werden)
    error_log("=== LOADING process_agenda.php ===");
    error_log("Meeting loaded: " . ($meeting ? 'YES' : 'NO'));
    error_log("Meeting Status: " . ($meeting['status'] ?? 'NULL'));
    error_log("POST data: " . print_r($_POST, true));
    
    // process_agenda.php einbinden (verarbeitet Formular-Aktionen)
    require_once 'module_helpers.php'; 
	require_once 'process_agenda.php';
    
    // WICHTIG: Meeting nach process_agenda NEU laden
    // Der Status könnte durch process_agenda.php geändert worden sein
    $meeting = get_meeting_details($pdo, $current_meeting_id);
    error_log("Meeting Status nach process: " . ($meeting['status'] ?? 'NULL'));
}

// PROCESS ADMIN
// Wird bei POST-Requests auf dem Admin-Tab eingebunden
// Dies geschieht bereits in der Presentation-Datei (tab_admin.php)

// ============================================
// DISPLAY-MODUS ERKENNUNG
// ============================================
// Welcher Display-Modus ist aktiv?
$display_mode = defined('DISPLAY_MODE_OVERRIDE') ? DISPLAY_MODE_OVERRIDE : (defined('DISPLAY_MODE') ? DISPLAY_MODE : 'standalone');

// SSOdirekt-Config laden falls benötigt
if ($display_mode === 'SSOdirekt' && isset($SSO_DIRECT_CONFIG)) {
    $sso_config = $SSO_DIRECT_CONFIG;
} else {
    $sso_config = null;
}

// ============================================
// DARK MODE: Serverseitige Erkennung (verhindert Flash)
// ============================================
// Dark Mode Cookie auslesen - Cookie wird von JavaScript gesetzt
// FALLBACK: Wenn kein Cookie existiert, aus localStorage lesen (client-side)
$dark_mode_enabled = isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'enabled';

// Wenn kein Cookie, aber localStorage vorhanden sein könnte: Client-seitiges Script
$check_localstorage = !isset($_COOKIE['darkMode']);

// ============================================
// HTML-AUSGABE BEGINNT HIER
// ============================================
?>
<!DOCTYPE html>
<html lang="de" <?php echo $dark_mode_enabled ? 'class="dark-mode"' : ''; ?> id="root-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $sso_config ? $sso_config['page_title'] : 'Sitzungsverwaltung'; ?></title>

    <!-- KRITISCH: Dark Mode Flash Prevention - MUSS VOR allem anderen kommen! -->

    <!-- Schritt 1: Sofortiges inline CSS (greift bevor irgendwas geladen wird) -->
    <script>
        // SEHR WICHTIG: Vor CSS! Setzt dark-mode Klasse auf body wenn nötig
        (function() {
            const hasCookie = document.cookie.split(';').some(c => c.trim().startsWith('darkMode='));
            if (!hasCookie) {
                const savedDarkMode = localStorage.getItem('darkMode');
                if (savedDarkMode === 'enabled') {
                    // Setze auf <body> weil style.css body.dark-mode verwendet!
                    document.documentElement.className = 'dark-mode';
                    document.body.className = 'dark-mode';
                    // Cookie für nächstes Mal
                    document.cookie = 'darkMode=enabled;path=/;max-age=31536000';
                }
            } else if (document.cookie.includes('darkMode=enabled')) {
                // Cookie vorhanden, Klasse auch auf body setzen
                document.body.className = 'dark-mode';
            }
        })();
    </script>
    <style id="dark-mode-flash-prevention">
        /* Inline CSS für SOFORTIGE Dark Mode Anwendung - verhindert Flash */
        html.dark-mode,
        html.dark-mode body,
        body.dark-mode {
            background-color: #1a1a1a !important;
            color: #e0e0e0 !important;
        }

        /* Navigation und Tabs sofort dunkel machen - SPEZIFISCH */
        html.dark-mode nav,
        html.dark-mode .navigation,
        html.dark-mode .navigation a,
        html.dark-mode .nav-tabs,
        html.dark-mode .nav-link,
        html.dark-mode .tab-content,
        body.dark-mode nav,
        body.dark-mode .navigation,
        body.dark-mode .navigation a,
        body.dark-mode .nav-tabs,
        body.dark-mode .nav-link,
        body.dark-mode .tab-content {
            background-color: #1a1a1a !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }

        /* Navigation Links aktiv/hover States */
        html.dark-mode .navigation a.active,
        html.dark-mode .navigation a:hover,
        body.dark-mode .navigation a.active,
        body.dark-mode .navigation a:hover {
            background-color: #2d2d2d !important;
            color: #ffffff !important;
        }

        /* Buttons sofort dunkel machen - ALLE Varianten */
        html.dark-mode button,
        html.dark-mode .btn,
        html.dark-mode .btn-primary,
        html.dark-mode .btn-secondary,
        html.dark-mode .btn-danger,
        html.dark-mode input[type="button"],
        html.dark-mode input[type="submit"],
        html.dark-mode .accordion-button,
        body.dark-mode button,
        body.dark-mode .btn,
        body.dark-mode .btn-primary,
        body.dark-mode .btn-secondary,
        body.dark-mode .btn-danger,
        body.dark-mode input[type="button"],
        body.dark-mode input[type="submit"],
        body.dark-mode .accordion-button {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }

        /* Container, Cards und Forms sofort dunkel machen */
        html.dark-mode .container,
        html.dark-mode .card,
        html.dark-mode .form-control,
        html.dark-mode .form-section,
        html.dark-mode .form-group,
        html.dark-mode .accordion-content,
        html.dark-mode select,
        html.dark-mode textarea,
        html.dark-mode input[type="text"],
        html.dark-mode input[type="date"],
        html.dark-mode input[type="time"],
        html.dark-mode input[type="email"],
        body.dark-mode .container,
        body.dark-mode .card,
        body.dark-mode .form-control,
        body.dark-mode .form-section,
        body.dark-mode .form-group,
        body.dark-mode .accordion-content,
        body.dark-mode select,
        body.dark-mode textarea,
        body.dark-mode input[type="text"],
        body.dark-mode input[type="date"],
        body.dark-mode input[type="time"],
        body.dark-mode input[type="email"] {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }
    </style>

    <link rel="stylesheet" href="style.css">

    <?php if ($sso_config): ?>
    <!-- Custom Styling für SSOdirekt-Modus (Version 2.0 - Light/Dark Mode Support) -->
    <style>
        <?php
        // Prüfen ob neues Format (light/dark) vorhanden ist
        $has_new_format = isset($sso_config['light']) && isset($sso_config['dark']);

        if ($has_new_format):
            // Neues Format: Separate Farben für Light und Dark Mode
            $light = $sso_config['light'];
            $dark = $sso_config['dark'];
        ?>
        /* Light Mode Farben (Standard) */
        :root {
            --sso-header-bg: <?php echo $light['header']['background']; ?>;
            --sso-header-text: <?php echo $light['header']['text']; ?>;
            --sso-header-border: <?php echo $light['header']['border']; ?>;
            --sso-footer-bg: <?php echo $light['footer']['background']; ?>;
            --sso-footer-text: <?php echo $light['footer']['text']; ?>;
            --sso-footer-border: <?php echo $light['footer']['border']; ?>;
            --sso-back-btn-bg: <?php echo $light['back_button']['background']; ?>;
            --sso-back-btn-text: <?php echo $light['back_button']['text']; ?>;
        }

        /* Dark Mode Farben - sowohl html.dark-mode als auch body.dark-mode */
        html.dark-mode,
        body.dark-mode {
            --sso-header-bg: <?php echo $dark['header']['background']; ?>;
            --sso-header-text: <?php echo $dark['header']['text']; ?>;
            --sso-header-border: <?php echo $dark['header']['border']; ?>;
            --sso-footer-bg: <?php echo $dark['footer']['background']; ?>;
            --sso-footer-text: <?php echo $dark['footer']['text']; ?>;
            --sso-footer-border: <?php echo $dark['footer']['border']; ?>;
            --sso-back-btn-bg: <?php echo $dark['back_button']['background']; ?>;
            --sso-back-btn-text: <?php echo $dark['back_button']['text']; ?>;
        }

        /* Header Styling */
        .header {
            background: var(--sso-header-bg) !important;
            border-bottom: 3px solid var(--sso-header-border) !important;
        }
        .header h1,
        .header .user-info,
        .header .user-info span,
        .header .user-info .logout-btn,
        .header .header-left a {
            color: var(--sso-header-text) !important;
        }

        /* Footer Styling */
        .page-footer {
            background: var(--sso-footer-bg) !important;
            border-top: 3px solid var(--sso-footer-border) !important;
            color: var(--sso-footer-text) !important;
        }
        .page-footer a {
            color: var(--sso-footer-text) !important;
        }

        /* Back Button Styling - Höhere Spezifität für Überschreibung */
        .header .user-info .sso-back-button,
        .header .user-info a.sso-back-button {
            background: var(--sso-back-btn-bg) !important;
            color: var(--sso-back-btn-text) !important;
            border: 1px solid var(--sso-back-btn-text) !important;
            /* Überschreibe alle logout-btn Styles */
            background-color: var(--sso-back-btn-bg) !important;
        }
        .header .user-info .sso-back-button:hover,
        .header .user-info a.sso-back-button:hover {
            opacity: 0.85;
            background: var(--sso-back-btn-bg) !important;
            color: var(--sso-back-btn-text) !important;
        }

        <?php else: ?>
        /* Altes Format: Rückwärtskompatibilität */
        :root {
            --primary: <?php echo $sso_config['primary_color'] ?? '#1976d2'; ?>;
            --primary-dark: <?php echo $sso_config['border_color'] ?? '#0d47a1'; ?>;
            --header-text: <?php echo $sso_config['header_text_color'] ?? '#ffffff'; ?>;
            --footer-text: <?php echo $sso_config['footer_text_color'] ?? '#ffffff'; ?>;
        }
        .header {
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
        }
        .header h1,
        .header .user-info,
        .header .user-info span,
        .header .user-info .logout-btn {
            color: var(--header-text);
        }
        .page-footer {
            background: var(--primary);
            color: var(--footer-text);
        }
        .page-footer a {
            color: var(--footer-text);
        }
        <?php endif; ?>
    </style>
    <?php endif; ?>
</head>
<body>
    <!-- HEADER mit Benutzerinfo -->
    <div class="header">
        <div class="header-inner">
            <?php if ($sso_config && !empty($sso_config['logo_path'])): ?>
            <!-- Logo für SSOdirekt-Modus -->
            <div style="display: flex; align-items: center; gap: 15px;">
                <img src="<?php echo $sso_config['logo_path']; ?>"
                     alt="Logo"
                     style="height: <?php echo $sso_config['logo_height']; ?>;">
                <h1 style="margin: 0;">Sitzungsverwaltung</h1>
            </div>
            <?php else: ?>
            <h1>🏛️ Sitzungsverwaltung</h1>
            <?php endif; ?>

            <div class="user-info">
                <!-- Benutzername anzeigen -->
                <span><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>

                <!-- Rollen-Badge anzeigen (nur auf PC) -->
                <span class="role-badge desktop-only role-<?php echo $current_user['role']; ?>">
                    <?php echo ucfirst($current_user['role']); ?>
                </span>

                <!-- Dark Mode Toggle -->
                <button class="dark-mode-toggle" id="darkModeToggle" title="Dunkelmodus umschalten">
                    <span class="icon">🌙</span>
                </button>

                <!-- Trust-Device Toggle -->
                <a href="?toggle_trust_device=1"
                   class="trust-device-toggle"
                   title="<?php echo $trust_device ? 'Vertrauenswürdiges Gerät deaktivieren' : 'Gerät als vertrauenswürdig markieren'; ?>">
                    <?php echo $trust_device ? '🔓' : '🔒'; ?>
                </a>

                <?php if ($display_mode === 'SSOdirekt' && $sso_config): ?>
                    <!-- Logout-Button für SSOdirekt-Modus -->
                    <a href="?logout=1" class="logout-btn" title="Abmelden">Abmelden</a>

                    <!-- Zurück-Button für SSOdirekt-Modus -->
                    <a href="<?php echo $sso_config['back_button_url']; ?>" class="logout-btn sso-back-button">
                        <?php echo $sso_config['back_button_text']; ?>
                    </a>
                <?php else: ?>
                    <!-- Normaler Logout-Button -->
                    <a href="?logout=1" class="logout-btn">Abmelden</a>
                <?php endif; ?>

                <!-- Hamburger-Menü (nur auf Mobile) -->
                <div class="hamburger-menu mobile-only" id="hamburger-menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </div>
    
    <!--Es wird gerade am Skript gearbeitet - also bitte nicht wundern, wenn was nicht funktioniert!!! -->
    
    <!-- NAVIGATION / TABS -->
    <div class="navigation">
        <!-- Termine-Tab (immer sichtbar) -->
        <a href="?tab=termine" class="<?php echo $active_tab === 'termine' ? 'active' : ''; ?>">
            📆 Termine
        </a>

        <!-- Sitzungen-Tab (immer sichtbar) -->
        <a href="?tab=meetings" class="<?php echo $active_tab === 'meetings' ? 'active' : ''; ?>">
            🤝 Sitzungen
        </a>

        <!-- Agenda-Tab (nur sichtbar wenn eine Sitzung ausgewählt ist) -->
        <?php if ($current_meeting_id): ?>
            <a href="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>"
               class="<?php echo $active_tab === 'agenda' ? 'active' : ''; ?>">
                📋 Tagesordnung
            </a>
        <?php endif; ?>

        <!-- Textbearbeitung-Tab (nur für Vorstand/GF/Assistenz/Führungsteam, NICHT für Mitglied) -->
        <?php
        // Prüfen ob User Zugriff hat (Mitglieder niemals)
        $user_role = strtolower($current_user['role'] ?? '');
        $has_texte_access = in_array($user_role, ['vorstand', 'gf', 'assistenz', 'fuehrungsteam']) && $user_role !== 'mitglied';

        if ($has_texte_access):
        ?>
        <a href="?tab=texte"
           class="<?php echo $active_tab === 'texte' ? 'active' : ''; ?>">
            ✍️ Textbearbeitung
        </a>
        <?php endif; ?>

        <!-- Protokolle-Tab (immer sichtbar) -->
        <a href="?tab=protokolle" class="<?php echo $active_tab === 'protokolle' ? 'active' : ''; ?>">
            📋 Protokolle
        </a>

        <!-- Erledigen-Tab (immer sichtbar) -->
        <a href="?tab=todos" class="<?php echo $active_tab === 'todos' ? 'active' : ''; ?>">
            ✅ Erledigen
        </a>

        <!-- Abwesenheiten-Tab (nur für Leadership) -->
        <?php if (in_array(strtolower($current_user['role']), ['vorstand', 'gf', 'assistenz', 'führungsteam'])): ?>
        <a href="?tab=vertretung" class="<?php echo $active_tab === 'vertretung' ? 'active' : ''; ?>">
            🏖️ Abwesenheiten
        </a>
        <?php endif; ?>

        <!-- Meinungsbild-Tab (immer sichtbar) -->
        <a href="?tab=opinion" class="<?php echo $active_tab === 'opinion' ? 'active' : ''; ?>">
            📊 Meinungsbild
        </a>

        <!-- Dokumente-Tab (optional, siehe config.php) -->
        <?php if (defined('ENABLE_DOCUMENTS_TAB') && ENABLE_DOCUMENTS_TAB): ?>
        <a href="?tab=documents" class="<?php echo $active_tab === 'documents' ? 'active' : ''; ?>">
            📁 Dokumente
        </a>
        <?php endif; ?>

        <!-- Admin-Tab (nur für Vorstand und GF sichtbar) -->
        <?php //if (in_array($current_user['role'], ['vorstand', 'gf'])):
		if ($current_user['is_admin']):
		?>
            <a href="?tab=admin" class="<?php echo $active_tab === 'admin' ? 'active' : ''; ?>">
                ⚙️ Admin
            </a>
        <?php endif; ?>
    </div>
    
    <!-- HAUPTINHALT / CONTENT -->
    <div class="container">
        <?php
        /**
         * TAB-ROUTING
         * Je nach aktivem Tab wird die entsprechende Presentation-Datei geladen
         * Diese Dateien enthalten nur die Anzeige-Logik (HTML + PHP für Ausgabe)
         */
        switch ($active_tab) {
            case 'meetings':
                // Sitzungsübersicht anzeigen
                include 'tab_meetings.php';
                break;

            case 'agenda':
                // Tagesordnung einer Sitzung anzeigen
                include 'tab_agenda.php';
                break;
            
            case 'termine':
                // Terminplanung/Umfragen anzeigen
                include 'tab_termine.php';
                break;

            case 'opinion':
                // Meinungsbild-Tool anzeigen
                include 'tab_opinion.php';
                break;

            case 'todos':
                // ToDo-Liste anzeigen
                include 'tab_todos.php';
                break;

            case 'protokolle':
                // Protokoll-Sammlung anzeigen
                include 'tab_protokolle.php';
                break;

            case 'documents':
                // Dokumentenverwaltung anzeigen
                include 'tab_documents.php';
                break;

            case 'vertretung':
                // Vertretungen & Abwesenheiten anzeigen
                include 'tab_vertretung.php';
                break;

            case 'texte':
                // Textbearbeitung für Sitzungen anzeigen
                include 'tab_texte.php';
                break;

            case 'admin':
                // Admin-Panel anzeigen (nur für berechtigte Benutzer)
                if ($current_user['is_admin']) {
                    // process_admin.php verarbeitet Admin-Aktionen
                    include 'process_admin.php';
                    // tab_admin.php zeigt das Admin-Panel an
                    include 'tab_admin.php';
                } else {
                    // Dezente Fehlermeldung bei unberechtigtem Zugriff
                    echo '<div style="max-width: 600px; margin: 40px auto; padding: 30px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
                    echo '<h3 style="color: #856404; margin: 0 0 12px 0; font-size: 18px;">🔒 Zugriff nicht möglich</h3>';
                    echo '<p style="color: #856404; margin: 0; line-height: 1.6;">Dieser Bereich ist nur für Administratoren zugänglich. Falls du Zugriff benötigst, wende dich bitte an einen Administrator.</p>';
                    echo '</div>';
                }
                break;
            
            default:
                // Fallback: Bei unbekanntem Tab wird Sitzungen angezeigt
                include 'tab_meetings.php';
        }
        ?>
    </div>
    
    <!-- JAVASCRIPT für Client-seitige Funktionen -->
    <script>
    /**
     * Accordion-Funktion
     * Öffnet/Schließt Accordion-Bereiche (z.B. für TOP-Details)
     *
     * @param {HTMLElement} button - Der geklickte Accordion-Button
     */
    function toggleAccordion(button) {
        // Nächstes Element nach dem Button ist der Content
        const content = button.nextElementSibling;
        const isOpen = content.style.display === 'block';

        // Alle Accordions schließen (nur eines kann gleichzeitig offen sein)
        document.querySelectorAll('.accordion-content').forEach(item => {
            item.style.display = 'none';
        });

        // Aktuelles Accordion öffnen/schließen (Toggle)
        if (!isOpen) {
            content.style.display = 'block';
        }
    }

    /**
     * Auto-Resize für Textareas
     * Passt die Höhe einer Textarea automatisch an den Inhalt an
     *
     * @param {HTMLTextAreaElement} textarea - Das Textarea-Element
     */
    function autoResize(textarea) {
        // Höhe zurücksetzen um Schrumpfen zu ermöglichen
        textarea.style.height = 'auto';
        // Neue Höhe basierend auf scrollHeight setzen
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    /**
     * Initialisiert Auto-Resize für alle Textareas auf der Seite
     */
    function initAutoResize() {
        // Alle Textareas finden
        const textareas = document.querySelectorAll('textarea');

        textareas.forEach(textarea => {
            // Initial-Resize beim Laden
            autoResize(textarea);

            // Event-Listener für Eingaben
            textarea.addEventListener('input', function() {
                autoResize(this);
            });

            // Event-Listener für Paste-Events
            textarea.addEventListener('paste', function() {
                setTimeout(() => autoResize(this), 10);
            });
        });
    }

    // Auto-Resize initialisieren wenn DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutoResize);
    } else {
        // DOM bereits geladen
        initAutoResize();
    }

    /**
     * Hilfsfunktion zum Setzen von Cookies
     */
    function setCookie(name, value, days = 365) {
        const expires = new Date();
        expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
    }

    /**
     * Dark Mode Toggle
     * Schaltet zwischen hellem und dunklem Modus um
     * Speichert Präferenz in Cookie UND localStorage (Cookie für PHP, localStorage als Fallback)
     */
    function initDarkMode() {
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;
        const html = document.documentElement;
        const icon = darkModeToggle?.querySelector('.icon');

        // HTML hat bereits die dark-mode Klasse vom Server (Cookie)
        // Wir müssen nur noch body synchronisieren und Icon setzen
        const isDarkMode = html.classList.contains('dark-mode');

        if (isDarkMode) {
            body.classList.add('dark-mode');
            if (icon) icon.textContent = '☀️';
        }

        // Toggle-Funktion
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function() {
                // Toggle auf beiden Elementen
                body.classList.toggle('dark-mode');
                html.classList.toggle('dark-mode');

                // Icon wechseln und Präferenz speichern
                if (body.classList.contains('dark-mode')) {
                    if (icon) icon.textContent = '☀️';
                    setCookie('darkMode', 'enabled');
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    if (icon) icon.textContent = '🌙';
                    setCookie('darkMode', 'disabled');
                    localStorage.setItem('darkMode', 'disabled');
                }
            });
        }
    }

    // Dark Mode initialisieren
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDarkMode);
    } else {
        initDarkMode();
    }
    </script>

    <!-- Externes JavaScript für Hamburger-Menü -->
    <script src="script.js"></script>

    <!-- FOOTER (abhängig vom Display-Modus) -->
    <?php if ($display_mode !== 'iframe'): ?>
    <footer class="page-footer">
        <?php if ($display_mode === 'SSOdirekt' && $sso_config): ?>
            <!-- Custom Footer für SSOdirekt-Modus -->
            <?php echo $sso_config['footer_html']; ?>
        <?php else: ?>
            <!-- Standard Footer -->
            <?php echo FOOTER_COPYRIGHT; ?> |
            <a href="<?php echo FOOTER_IMPRESSUM_URL; ?>" target="_blank">Impressum</a> |
            <a href="<?php echo FOOTER_DATENSCHUTZ_URL; ?>" target="_blank">Datenschutz</a>
        <?php endif; ?>
    </footer>
    <?php endif; ?>
    <!-- Footer wird im iframe-Modus nicht angezeigt -->
</body>
</html>