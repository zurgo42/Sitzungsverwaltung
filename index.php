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

// Session starten (muss ganz am Anfang stehen, vor jeder Ausgabe)
session_start();

// Konfiguration und Hilfsfunktionen laden
require_once 'config.php';           // Datenbankverbindung und Konstanten
require_once 'config_adapter.php';   // Konfiguration für Mitgliederquelle
require_once 'member_functions.php'; // Prozedurale Wrapper-Funktionen für Mitglieder
require_once 'functions.php';        // Wiederverwendbare Funktionen

// ============================================
// LOGOUT-VERARBEITUNG
// ============================================
// Wenn der Logout-Link geklickt wurde (?logout=1), Session beenden
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
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

            // Zur Hauptseite weiterleiten
            header('Location: index.php');
            exit;
        } else {
            // Mitglied nicht gefunden
            die('<h1>Zugriff verweigert</h1><p>Ihre Mitgliedsnummer wurde nicht gefunden oder ist nicht aktiv.</p><p>MNr: ' . htmlspecialchars($sso_mnr) . '</p>');
        }
    } else {
        // Keine Mitgliedsnummer übergeben
        die('<h1>Zugriff verweigert</h1><p>Keine Mitgliedsnummer übergeben. SSO-Konfiguration prüfen!</p>');
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
// HTML-AUSGABE BEGINNT HIER
// ============================================
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitzungsverwaltung</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- HEADER mit Benutzerinfo -->
    <div class="header">
        <h1>🏛️ Sitzungsverwaltung</h1>
        <div class="user-info">
            <!-- Benutzername anzeigen -->
            <span><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>
            
            <!-- Rollen-Badge anzeigen -->
            <span class="role-badge role-<?php echo $current_user['role']; ?>">
                <?php echo ucfirst($current_user['role']); ?>
            </span>
            
            <!-- Logout-Button -->
            <a href="?logout=1" class="logout-btn">Abmelden</a>
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

        <!-- Textbearbeitung-Tab (nur für Vorstand/GF/Assistenz oder aktive Sitzungsteilnehmer) -->
        <?php
        // Prüfen ob User Zugriff hat
        $has_texte_access = in_array($current_user['role'], ['vorstand', 'gf', 'assistenz']);

        // Oder ist User Teilnehmer einer aktiven Sitzung?
        if (!$has_texte_access) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM svmeeting_participants mp
                JOIN svmeetings m ON mp.meeting_id = m.meeting_id
                WHERE mp.member_id = ? AND m.status = 'active'
            ");
            $stmt->execute([$current_user['member_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $has_texte_access = ($result['count'] > 0);
        }

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

        <!-- Vertretungen-Tab (immer sichtbar) -->
        <a href="?tab=vertretung" class="<?php echo $active_tab === 'vertretung' ? 'active' : ''; ?>">
            🏖️ Vertretungen
        </a>

        <!-- Meinungsbild-Tab (immer sichtbar) -->
        <a href="?tab=opinion" class="<?php echo $active_tab === 'opinion' ? 'active' : ''; ?>">
            📊 Meinungsbild
        </a>

        <!-- Dokumente-Tab (immer sichtbar) -->
        <a href="?tab=documents" class="<?php echo $active_tab === 'documents' ? 'active' : ''; ?>">
            📁 Dokumente
        </a>

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
                    // Fehlermeldung bei unberechtigtem Zugriff
                    echo '<div class="error-message">Zugriff verweigert.</div>';
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
    </script>

    <!-- FOOTER -->
    <footer class="page-footer">
        <?php echo FOOTER_COPYRIGHT; ?> |
        <a href="<?php echo FOOTER_IMPRESSUM_URL; ?>" target="_blank">Impressum</a> |
        <a href="<?php echo FOOTER_DATENSCHUTZ_URL; ?>" target="_blank">Datenschutz</a>
    </footer>
</body>
</html>