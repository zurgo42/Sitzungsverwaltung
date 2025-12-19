<?php
/**
 * terminplanung_standalone.php - Standalone Terminplanung-Wrapper
 * Erstellt: 17.11.2025
 * Erweitert: 18.12.2025 - Externe Teilnehmer-Support
 *
 * VERWENDUNG:
 * ===========
 *
 * In der Sitzungsverwaltung:
 * - Automatisch über index.php?tab=termine integriert
 * - Nutzt member_functions.php für Datenbank-Zugriff
 *
 * In anderen Anwendungen:
 * - Per include einbinden:
 *   <?php
 *     require_once 'pfad/zu/terminplanung_standalone.php';
 *   ?>
 * - Voraussetzungen:
 *   - $pdo: PDO-Datenbankverbindung
 *   - $MNr: Mitgliedsnummer des eingeloggten Users (für berechtigte-Tabelle)
 *   - Optional: $HOST_URL_BASE für E-Mails
 *
 * Externer Zugriff (ohne Login):
 * - terminplanung_standalone.php?poll_id=XXX
 * - Zeigt Registrierungsformular für externe Teilnehmer an
 *
 * DATENBANK-KOMPATIBILITÄT:
 * =========================
 * - Erkennt automatisch ob members oder berechtigte Tabelle verwendet wird
 * - Nutzt Adapter-System für Portabilität
 * - Unterstützt externe Teilnehmer ohne Account
 */

// ============================================
// UMGEBUNGS-ERKENNUNG
// ============================================

// Session starten falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prüfen ob wir in der Sitzungsverwaltung sind (dann existiert member_functions.php)
$is_sitzungsverwaltung = file_exists(__DIR__ . '/member_functions.php');

if ($is_sitzungsverwaltung) {
    // In Sitzungsverwaltung: Adapter-System nutzen

    // Konfiguration und Datenbank laden
    if (!defined('DB_HOST')) {
        require_once __DIR__ . '/config.php';
    }

    // PDO-Verbindung initialisieren falls noch nicht vorhanden
    if (!isset($pdo)) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die('<div style="background:#f8d7da;padding:20px;border:1px solid #f5c6cb;color:#721c24;border-radius:5px;margin:20px;">
                ❌ Datenbankverbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage()) . '
            </div>');
        }
    }

    require_once __DIR__ . '/member_functions.php';
    require_once __DIR__ . '/external_participants_functions.php';

    // User aus Session holen (kann NULL sein bei externem Zugriff)
    $current_user = null;
    if (isset($_SESSION['member_id'])) {
        $current_user = get_member_by_id($pdo, $_SESSION['member_id']);
    }

} else {
    // In anderer Anwendung: Direkter Zugriff auf berechtigte-Tabelle
    require_once __DIR__ . '/external_participants_functions.php';

    // Prüfen ob Voraussetzungen erfüllt sind (außer bei externem Zugriff)
    if (!isset($pdo)) {
        die('FEHLER: $pdo nicht definiert. Bitte PDO-Verbindung vor dem Include erstellen.');
    }

    // User laden (kann NULL sein bei externem Zugriff)
    $current_user = null;
    if (isset($MNr) && $MNr) {
        // User aus berechtigte-Tabelle holen
        $stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE MNr = ?");
        $stmt->execute([$MNr]);
        $ber = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ber) {
            // In Standard-Format umwandeln
            $current_user = [
                'member_id' => $ber['ID'],
                'membership_number' => $ber['MNr'],
                'first_name' => $ber['Vorname'],
                'last_name' => $ber['Name'],
                'email' => $ber['eMail'],
                'role' => determine_role($ber['Funktion'], $ber['aktiv']),
                'is_admin' => is_admin_user($ber['Funktion'], $ber['MNr'])
            ];
        }
    }

    // Hilfsfunktionen für berechtigte-Mapping
    function determine_role($funktion, $aktiv) {
        if ($aktiv == 19) return 'vorstand';
        $roleMapping = [
            'GF' => 'gf',
            'SV' => 'assistenz',
            'RL' => 'fuehrungsteam',
            'AD' => 'Mitglied',
            'FP' => 'Mitglied'
        ];
        return $roleMapping[$funktion] ?? 'Mitglied';
    }

    function is_admin_user($funktion, $mnr) {
        return in_array($funktion, ['GF', 'SV']) || $mnr == '0495018';
    }

    // Alle Mitglieder laden
    function get_all_members_standalone($pdo) {
        $stmt = $pdo->query("
            SELECT ID as member_id, MNr as membership_number, Vorname as first_name,
                   Name as last_name, eMail as email, Funktion, aktiv
            FROM berechtigte
            WHERE aktiv > 17 OR Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')
            ORDER BY Name, Vorname
        ");
        $members = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $members[] = [
                'member_id' => $row['member_id'],
                'membership_number' => $row['membership_number'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'role' => determine_role($row['Funktion'], $row['aktiv']),
                'is_admin' => is_admin_user($row['Funktion'], $row['membership_number'])
            ];
        }
        return $members;
    }

    $all_members = get_all_members_standalone($pdo);
}

// ============================================
// TOKEN-BASIERTER ZUGRIFF
// ============================================

// Prüfen ob via Access-Token zugegriffen wird
$access_token = $_GET['token'] ?? null;

// Falls Access-Token übergeben: Poll laden
if ($access_token) {
    // Poll per Token laden
    $stmt = $pdo->prepare("SELECT * FROM svpolls WHERE access_token = ? AND status = 'open'");
    $stmt->execute([$access_token]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        die('<div style="background:#f8d7da;padding:20px;border:1px solid #f5c6cb;color:#721c24;border-radius:5px;margin:20px;">
            ❌ Ungültiger oder abgelaufener Zugangs-Link. Bitte prüfe den Link oder kontaktiere den Ersteller.
        </div>');
    }

    // Poll-ID für weitere Verarbeitung
    $poll_id_param = $poll['poll_id'];
}

// ============================================
// EXTERNE TEILNEHMER-PRÜFUNG
// ============================================

// Poll-ID aus URL holen (falls nicht schon via Token gesetzt)
if (!isset($poll_id_param)) {
    $poll_id_param = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : null;
}

// Wenn Poll-ID vorhanden: Prüfen ob Teilnehmer identifiziert ist
if ($poll_id_param > 0) {
    // Poll laden um Titel etc. zu haben (falls nicht schon via Token geladen)
    if (!isset($poll) || !$poll) {
        $stmt = $pdo->prepare("SELECT * FROM svpolls WHERE poll_id = ?");
        $stmt->execute([$poll_id_param]);
        $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($poll) {
        // Aktuellen Teilnehmer ermitteln (Member oder Extern)
        $participant = get_current_participant($current_user, $pdo, 'termine', $poll_id_param);

        // Wenn niemand identifiziert: Registrierungsformular anzeigen
        if ($participant['type'] === 'none') {
            // Registrierungsformular einbinden
            $poll_type = 'termine';
            $poll_id = $poll_id_param;

            // Aktuelles Skript für Redirect übergeben
            $redirect_script = basename($_SERVER['SCRIPT_NAME']);

            require __DIR__ . '/external_participant_register.php';
            exit; // Beende Skript hier
        }

        // Teilnehmer ist identifiziert - in Variablen speichern für spätere Verwendung
        $current_participant_type = $participant['type']; // 'member' oder 'external'
        $current_participant_id = $participant['id'];
        $current_participant_data = $participant['data'];
    }
}

// ============================================
// COMMON FUNCTIONS
// ============================================

/**
 * Prüft ob User berechtigt ist, eine Umfrage zu bearbeiten/löschen
 */
function can_edit_poll_standalone($poll, $current_user) {
    if (!$poll) return false;
    $is_creator = ($poll['created_by_member_id'] == $current_user['member_id']);
    $is_admin = in_array($current_user['role'], ['assistenz', 'gf']);
    return $is_creator || $is_admin;
}

/**
 * Holt eine Umfrage mit ID
 */
function get_poll_by_id_standalone($pdo, $poll_id) {
    $stmt = $pdo->prepare("SELECT * FROM svpolls WHERE poll_id = ?");
    $stmt->execute([$poll_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================
// POST REQUEST HANDLING
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terminplanung_action'])) {
    $action = $_POST['terminplanung_action'];

    try {
        switch ($action) {
            case 'create_poll':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $location = trim($_POST['location'] ?? '');

                if (empty($title)) {
                    $error_message = 'Bitte gib einen Titel ein';
                    break;
                }

                // Umfrage erstellen
                $stmt = $pdo->prepare("
                    INSERT INTO svpolls (title, description, location, created_by_member_id, status, created_at)
                    VALUES (?, ?, ?, ?, 'open', NOW())
                ");
                $stmt->execute([$title, $description, $location, $current_user['member_id']]);
                $poll_id = $pdo->lastInsertId();

                // Terminvorschläge hinzufügen
                for ($i = 1; $i <= 20; $i++) {
                    $date = $_POST["date_$i"] ?? '';
                    $time_start = $_POST["time_start_$i"] ?? '';
                    $time_end = $_POST["time_end_$i"] ?? '';
                    $location = trim($_POST["location_$i"] ?? '');
                    $notes = trim($_POST["notes_$i"] ?? '');

                    if (!empty($date) && !empty($time_start)) {
                        $suggested_datetime = $date . ' ' . $time_start;
                        $suggested_end = !empty($time_end) ? $date . ' ' . $time_end : null;

                        $stmt = $pdo->prepare("
                            INSERT INTO svpoll_dates (poll_id, suggested_date, suggested_end_date, location, notes, sort_order)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$poll_id, $suggested_datetime, $suggested_end, $location, $notes, $i]);
                    }
                }

                $success_message = 'Terminumfrage erfolgreich erstellt!';
                $_GET['view'] = 'poll';
                $_GET['poll_id'] = $poll_id;
                break;

            case 'submit_vote':
                $poll_id = intval($_POST['poll_id'] ?? 0);
                $poll = get_poll_by_id_standalone($pdo, $poll_id);

                if (!$poll || $poll['status'] !== 'open') {
                    $error_message = 'Umfrage nicht verfügbar';
                    break;
                }

                // Aktuellen Teilnehmer ermitteln
                $participant = get_current_participant($current_user, $pdo, 'termine', $poll_id);

                if ($participant['type'] === 'none') {
                    $error_message = 'Sie müssen sich registrieren um abzustimmen';
                    break;
                }

                // IDs je nach Teilnehmer-Typ setzen
                $member_id = ($participant['type'] === 'member') ? $participant['id'] : null;
                $external_id = ($participant['type'] === 'external') ? $participant['id'] : null;

                // Bestehende Antworten löschen
                if ($member_id) {
                    $stmt = $pdo->prepare("DELETE FROM svpoll_responses WHERE poll_id = ? AND member_id = ?");
                    $stmt->execute([$poll_id, $member_id]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM svpoll_responses WHERE poll_id = ? AND external_participant_id = ?");
                    $stmt->execute([$poll_id, $external_id]);
                }

                // Neue Antworten speichern
                $stmt = $pdo->prepare("
                    INSERT INTO svpoll_responses (poll_id, date_id, member_id, external_participant_id, vote, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'vote_') === 0) {
                        $date_id = intval(str_replace('vote_', '', $key));
                        $vote = intval($value);
                        if (in_array($vote, [-1, 0, 1])) {
                            $stmt->execute([$poll_id, $date_id, $member_id, $external_id, $vote]);
                        }
                    }
                }

                $success_message = 'Ihre Abstimmung wurde gespeichert!';
                break;

            case 'finalize_poll':
                $poll_id = intval($_POST['poll_id'] ?? 0);
                $final_date_id = intval($_POST['final_date_id'] ?? 0);
                $poll = get_poll_by_id_standalone($pdo, $poll_id);

                if (!$poll || !can_edit_poll_standalone($poll, $current_user)) {
                    $error_message = 'Keine Berechtigung';
                    break;
                }

                $stmt = $pdo->prepare("
                    UPDATE svpolls SET status = 'finalized', final_date_id = ?, finalized_at = NOW()
                    WHERE poll_id = ?
                ");
                $stmt->execute([$final_date_id, $poll_id]);

                $success_message = 'Finaler Termin wurde festgelegt!';
                break;

            case 'delete_poll':
                $poll_id = intval($_POST['poll_id'] ?? 0);
                $poll = get_poll_by_id_standalone($pdo, $poll_id);

                if (!$poll || !can_edit_poll_standalone($poll, $current_user)) {
                    $error_message = 'Keine Berechtigung';
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM svpolls WHERE poll_id = ?");
                $stmt->execute([$poll_id]);

                $success_message = 'Umfrage wurde gelöscht';
                $_GET['view'] = 'dashboard';
                break;
        }

    } catch (Exception $e) {
        $error_message = 'Fehler: ' . $e->getMessage();
    }
}

// ============================================
// VIEW RENDERING
// ============================================

// Wenn in Sitzungsverwaltung integriert UND User eingeloggt, nutze die bestehenden Tab-Dateien
// Externe Teilnehmer (ohne Login) brauchen die komplette Tab-Ansicht nicht
if ($is_sitzungsverwaltung && $current_user && file_exists(__DIR__ . '/tab_termine.php')) {
    // functions.php laden für get_visible_meetings() etc.
    if (file_exists(__DIR__ . '/functions.php')) {
        require_once __DIR__ . '/functions.php';
    }

    include __DIR__ . '/tab_termine.php';
    return; // Beende hier
}

// Ansonsten: Standalone-Rendering
// Wenn poll_id vorhanden ist, automatisch poll-View wählen (für externe Teilnehmer)
$poll_id = intval($_GET['poll_id'] ?? 0);
if ($poll_id > 0 && !isset($_GET['view'])) {
    $view = 'poll';
} else {
    $view = $_GET['view'] ?? 'dashboard';
}

// Standalone-Rendering benötigt immer das CSS
// (tab_termine.php hat bereits return; ausgeführt wenn es geladen wurde)
// Poll-Titel für Page-Title laden falls poll_id vorhanden
$page_title = 'Terminplanung';
if ($poll_id > 0) {
    $stmt = $pdo->prepare("SELECT title FROM svpolls WHERE poll_id = ?");
    $stmt->execute([$poll_id]);
    $poll_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($poll_data) {
        $page_title = htmlspecialchars($poll_data['title']);
    }
}

echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $page_title . '</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }

        h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }

        .poll-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .poll-description {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            color: #555;
            line-height: 1.6;
        }

        .date-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .date-card:hover {
            border-color: #4CAF50;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.1);
        }

        .date-header {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-header .weekday {
            color: #4CAF50;
            font-weight: bold;
        }

        .vote-options {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .vote-option {
            flex: 1;
            min-width: 120px;
        }

        .vote-option input[type="radio"] {
            display: none;
        }

        .vote-option label {
            display: block;
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            font-weight: 500;
            transition: all 0.2s ease;
            background: white;
            font-size: 14px;
        }

        .vote-option label:hover {
            border-color: #bbb;
            background: #f8f9fa;
        }

        .vote-option input[type="radio"]:checked + label {
            font-weight: bold;
            transform: scale(1.02);
        }

        .vote-option.yes input[type="radio"]:checked + label {
            background: #4CAF50;
            border-color: #4CAF50;
            color: white;
        }

        .vote-option.maybe input[type="radio"]:checked + label {
            background: #FFC107;
            border-color: #FFC107;
            color: white;
        }

        .vote-option.no input[type="radio"]:checked + label {
            background: #f44336;
            border-color: #f44336;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            transition: all 0.3s ease;
            margin-top: 30px;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h2 {
                font-size: 22px;
            }

            .vote-options {
                flex-direction: column;
            }

            .vote-option {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="container">';

// Success/Error Messages
if (isset($success_message)) {
    echo '<div class="message">' . htmlspecialchars($success_message) . '</div>';
}
if (isset($error_message)) {
    echo '<div class="error-message">' . htmlspecialchars($error_message) . '</div>';
}

// Einfache Dashboard-Ansicht für Standalone-Modus
if ($view === 'dashboard') {
    echo '<h2>Terminplanung</h2>';
    echo '<p><a href="?view=create" class="btn-primary">+ Neue Umfrage erstellen</a></p>';

    // Umfragen auflisten
    $stmt = $pdo->query("SELECT * FROM svpolls ORDER BY created_at DESC");
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($polls as $poll) {
        echo '<div class="poll-card status-' . $poll['status'] . '">';
        echo '<h3>' . htmlspecialchars($poll['title']) . '</h3>';
        echo '<p>' . nl2br(htmlspecialchars($poll['description'])) . '</p>';
        echo '<p><a href="?view=poll&poll_id=' . $poll['poll_id'] . '" class="btn-primary">Ansehen</a></p>';
        echo '</div>';
    }

} elseif ($view === 'create') {
    // Einfaches Formular zum Erstellen
    echo '<h2>Neue Terminumfrage erstellen</h2>';
    echo '<form method="POST">';
    echo '<input type="hidden" name="terminplanung_action" value="create_poll">';
    echo '<p><label>Titel: <input type="text" name="title" required style="width: 100%; padding: 8px;"></label></p>';
    echo '<p><label>Beschreibung: <textarea name="description" rows="3" style="width: 100%; padding: 8px;"></textarea></label></p>';
    echo '<p><label>Ort (optional): <input type="text" name="location" placeholder="Ort der Veranstaltung" style="width: 100%; padding: 8px;"></label></p>';
    echo '<h3>Terminvorschläge</h3>';
    for ($i = 1; $i <= 5; $i++) {
        echo '<p>Termin ' . $i . ': ';
        echo '<input type="date" name="date_' . $i . '">';
        echo ' <input type="time" name="time_start_' . $i . '">';
        echo ' - <input type="time" name="time_end_' . $i . '">';
        echo '</p>';
    }
    echo '<p><button type="submit" class="btn-primary">Umfrage erstellen</button></p>';
    echo '</form>';

} elseif ($view === 'poll' && $poll_id > 0) {
    // Detailansicht mit Abstimmung
    $poll = get_poll_by_id_standalone($pdo, $poll_id);

    if (!$poll) {
        echo '<p class="error-message">Umfrage nicht gefunden</p>';
    } else {
        // Deutsche Wochentage
        $weekdays = [
            'Monday' => 'Montag',
            'Tuesday' => 'Dienstag',
            'Wednesday' => 'Mittwoch',
            'Thursday' => 'Donnerstag',
            'Friday' => 'Freitag',
            'Saturday' => 'Samstag',
            'Sunday' => 'Sonntag'
        ];

        echo '<h2>' . htmlspecialchars($poll['title']) . '</h2>';

        if (!empty($poll['description'])) {
            echo '<div class="poll-description">' . nl2br(htmlspecialchars($poll['description'])) . '</div>';
        }

        if (!empty($poll['location'])) {
            echo '<div class="poll-meta">📍 Ort: ' . htmlspecialchars($poll['location']) . '</div>';
        }

        // Terminvorschläge und Abstimmung
        $stmt = $pdo->prepare("SELECT * FROM svpoll_dates WHERE poll_id = ? ORDER BY suggested_date");
        $stmt->execute([$poll_id]);
        $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($poll['status'] === 'open') {
            echo '<form method="POST">';
            echo '<input type="hidden" name="terminplanung_action" value="submit_vote">';
            echo '<input type="hidden" name="poll_id" value="' . $poll_id . '">';

            foreach ($dates as $date) {
                $datetime = new DateTime($date['suggested_date']);
                $weekday_en = $datetime->format('l');
                $weekday_de = $weekdays[$weekday_en] ?? $weekday_en;
                $date_str = $datetime->format('d.m.Y');
                $time_str = $datetime->format('H:i');

                $end_time = '';
                if (!empty($date['suggested_end_date'])) {
                    $end_datetime = new DateTime($date['suggested_end_date']);
                    $end_time = ' - ' . $end_datetime->format('H:i');
                }

                echo '<div class="date-card">';
                echo '<div class="date-header">';
                echo '<span class="weekday">' . $weekday_de . '</span>';
                echo '<span>' . $date_str . ', ' . $time_str . $end_time . ' Uhr</span>';
                echo '</div>';
                echo '<div class="vote-options">';

                // Radio-Buttons für Ja / Vielleicht / Nein
                echo '<div class="vote-option yes">';
                echo '<input type="radio" id="vote_' . $date['date_id'] . '_yes" name="vote_' . $date['date_id'] . '" value="1">';
                echo '<label for="vote_' . $date['date_id'] . '_yes">✅ Passt gut</label>';
                echo '</div>';

                echo '<div class="vote-option maybe">';
                echo '<input type="radio" id="vote_' . $date['date_id'] . '_maybe" name="vote_' . $date['date_id'] . '" value="0" checked>';
                echo '<label for="vote_' . $date['date_id'] . '_maybe">🟡 Geht zur Not</label>';
                echo '</div>';

                echo '<div class="vote-option no">';
                echo '<input type="radio" id="vote_' . $date['date_id'] . '_no" name="vote_' . $date['date_id'] . '" value="-1">';
                echo '<label for="vote_' . $date['date_id'] . '_no">❌ Passt nicht</label>';
                echo '</div>';

                echo '</div>'; // vote-options
                echo '</div>'; // date-card
            }

            echo '<button type="submit" class="btn-primary">Abstimmung speichern</button>';
            echo '</form>';
        } else {
            echo '<div class="error-message">Diese Umfrage ist bereits geschlossen.</div>';
        }
    }
}

// Schließende Tags für Standalone-Rendering
// (tab_termine.php hat bereits return; ausgeführt wenn es geladen wurde)
echo '</div></body></html>';
?>
