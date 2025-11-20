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
// PROCESS-DATEIEN EINBINDEN
// Diese Dateien verarbeiten POST-Requests und führen Datenbankoperationen aus
// Sie werden VOR der HTML-Ausgabe eingebunden
// ============================================

// PROCESS MEETINGS
// Wird nur bei POST-Requests auf dem Meetings-Tab ausgeführt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_tab === 'meetings') {
    require_once 'process_meetings.php';
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
    <link rel="stylesheet" href="style-dark.css">
    <script>
    // Theme sofort laden um Flackern zu vermeiden
    (function() {
        const theme = localStorage.getItem('theme') || 'auto';
        if (theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark-mode-loading');
        }
    })();
    </script>
    <style>
    /* Verhindere Flackern beim Laden */
    html.dark-mode-loading body { background: #1a1a1a; }

    /* Theme Toggle Button */
    .theme-toggle {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        border: 1px solid rgba(255,255,255,0.3);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .theme-toggle:hover {
        background: rgba(255,255,255,0.25);
    }
    .theme-toggle select {
        background: transparent;
        border: none;
        color: white;
        font-size: 12px;
        cursor: pointer;
        outline: none;
    }
    .theme-toggle select option {
        background: #333;
        color: white;
    }
    </style>
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

            <!-- Theme Toggle -->
            <div class="theme-toggle">
                <span>🌓</span>
                <select id="theme-select" onchange="setTheme(this.value)">
                    <option value="auto">Auto</option>
                    <option value="light">Hell</option>
                    <option value="dark">Dunkel</option>
                </select>
            </div>

            <!-- Logout-Button -->
            <a href="?logout=1" class="logout-btn">Abmelden</a>
        </div>
    </div>
    
    <!--Es wird gerade am Skript gearbeitet - also bitte nicht wundern, wenn was nicht funktioniert!!! -->
    
    <!-- NAVIGATION / TABS -->
    <div class="navigation">
        <!-- Meetings-Tab (immer sichtbar) -->
        <a href="?tab=meetings" class="<?php echo $active_tab === 'meetings' ? 'active' : ''; ?>">
            📅 Meetings
        </a>
        
        <!-- Agenda-Tab (nur sichtbar wenn ein Meeting ausgewählt ist) -->
        <?php if ($current_meeting_id): ?>
            <a href="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>" 
               class="<?php echo $active_tab === 'agenda' ? 'active' : ''; ?>">
                📋 Tagesordnung
            </a>
        <?php endif; ?>
        
        <!-- Termine-Tab (immer sichtbar) -->
        <a href="?tab=termine" class="<?php echo $active_tab === 'termine' ? 'active' : ''; ?>">
            📅 Termine
        </a>

        <!-- Meinungsbild-Tab (immer sichtbar) -->
        <a href="?tab=opinion" class="<?php echo $active_tab === 'opinion' ? 'active' : ''; ?>">
            📊 Meinungsbild
        </a>

        <!-- ToDos-Tab (immer sichtbar) -->
        <a href="?tab=todos" class="<?php echo $active_tab === 'todos' ? 'active' : ''; ?>">
            ✅ Meine ToDos
        </a>

        <!-- Protokolle-Tab (immer sichtbar) -->
        <a href="?tab=protokolle" class="<?php echo $active_tab === 'protokolle' ? 'active' : ''; ?>">
            📋 Protokolle
        </a>

        <!-- Dokumente-Tab (immer sichtbar) -->
        <a href="?tab=documents" class="<?php echo $active_tab === 'documents' ? 'active' : ''; ?>">
            📁 Dokumente
        </a>

        <!-- Vertretung-Tab (nur für Führungsteam sichtbar) -->
        <?php
        $leadership_roles = ['vorstand', 'gf', 'assistenz', 'fuehrungsteam', 'Vorstand', 'Geschäftsführung', 'Assistenz', 'Führungsteam'];
        if (in_array($current_user['role'], $leadership_roles)):
        ?>
            <a href="?tab=absences" class="<?php echo $active_tab === 'absences' ? 'active' : ''; ?>">
                🏖️ Vertretung
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

    <!-- ABWESENHEITEN-WIDGET (unterhalb der Tabs) -->
    <?php include 'widget_absences.php'; ?>

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
                // Meeting-Übersicht anzeigen
                include 'tab_meetings.php';
                break;
            
            case 'agenda':
                // Tagesordnung eines Meetings anzeigen
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

            case 'absences':
                // Abwesenheitsverwaltung anzeigen (nur für Führungsteam)
                include 'tab_absences.php';
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
                // Fallback: Bei unbekanntem Tab wird Meetings angezeigt
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
     * Theme Management
     * Hell/Dunkel/Auto Umschaltung mit localStorage Speicherung
     */
    function setTheme(theme) {
        localStorage.setItem('theme', theme);
        applyTheme(theme);
    }

    function applyTheme(theme) {
        const body = document.body;
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (theme === 'dark' || (theme === 'auto' && prefersDark)) {
            body.classList.add('dark-mode');
        } else {
            body.classList.remove('dark-mode');
        }

        // Entferne die Loading-Klasse
        document.documentElement.classList.remove('dark-mode-loading');
    }

    // Theme beim Laden initialisieren
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme') || 'auto';
        const themeSelect = document.getElementById('theme-select');
        if (themeSelect) {
            themeSelect.value = savedTheme;
        }
        applyTheme(savedTheme);

        // Auf System-Änderungen reagieren (wenn Auto gewählt)
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
            const currentTheme = localStorage.getItem('theme') || 'auto';
            if (currentTheme === 'auto') {
                applyTheme('auto');
            }
        });
    });

    /**
     * Smartphone-Menü: Klickbare Navigation mit Toggle
     */
    document.addEventListener('DOMContentLoaded', function() {
        const navigation = document.querySelector('.navigation');
        if (!navigation) return;

        // Prüfen ob mobil
        function isMobile() {
            return window.innerWidth <= 768;
        }

        // Initial collapsed auf Mobile
        if (isMobile()) {
            navigation.classList.add('collapsed');
        }

        // Bei Resize anpassen
        window.addEventListener('resize', function() {
            if (!isMobile()) {
                navigation.classList.remove('collapsed');
            } else if (!navigation.classList.contains('collapsed')) {
                navigation.classList.add('collapsed');
            }
        });

        // Klick auf aktiven Tab togglet Menü
        navigation.addEventListener('click', function(e) {
            if (!isMobile()) return;

            const clickedLink = e.target.closest('a');
            if (!clickedLink) return;

            // Wenn collapsed und auf aktiven Tab geklickt -> öffnen
            if (navigation.classList.contains('collapsed') && clickedLink.classList.contains('active')) {
                e.preventDefault();
                navigation.classList.remove('collapsed');
            }
            // Wenn offen und auf Link geklickt -> schließen nach Navigation
            else if (!navigation.classList.contains('collapsed')) {
                // Navigation erfolgt normal, Menü schließt sich
                setTimeout(() => {
                    navigation.classList.add('collapsed');
                }, 50);
            }
        });
    });
    </script>
</body>
</html>