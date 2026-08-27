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
    // MTool-Kontext: $MNr gesetzt aber kein SV-Session → Mitglied per MNr laden
    if (!$current_user && isset($MNr) && $MNr) {
        $current_user = get_member_by_membership_number($pdo, $MNr);
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

                // Access-Token generieren (für Weitergabe-Link an externe Teilnehmer)
                $new_access_token = bin2hex(random_bytes(16));

                // Umfrage erstellen (immer individual – Weitergabe per Link)
                $stmt = $pdo->prepare("
                    INSERT INTO svpolls (title, description, location, created_by_member_id, target_type, status, access_token, created_at)
                    VALUES (?, ?, ?, ?, 'individual', 'open', ?, NOW())
                ");
                $stmt->execute([$title, $description, $location, $current_user['member_id'], $new_access_token]);
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
                    $error_message = 'Du musst dich registrieren um abzustimmen';
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

                $success_message = 'Deine Abstimmung wurde gespeichert!';
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

// MTool-Modus: Aufrufer hat $MNr gesetzt und kein Public-Wrapper-Modus.
// In diesem Fall nutzen wir tab_termine.php (vollständige Funktionalität),
// blenden dort aber MTool-unpassende Bereiche (Abwesenheiten, Zielgruppe,
// E-Mail-Optionen) per $TERMINPLANUNG_MTOOL_MODE aus.
// Szenario 1 (VTool/SV): index.php lädt tab_termine.php direkt – diese Datei nicht beteiligt.
// Szenario 3 (Öffentlich): $TERMINPLANUNG_PUBLIC_MODE=true → weiter unten, Standalone-Rendering.
if (isset($MNr) && empty($TERMINPLANUNG_PUBLIC_MODE) && $current_user && file_exists(__DIR__ . '/tab_termine.php')) {
    $TERMINPLANUNG_MTOOL_MODE = true;

    // Absolute URL zu SV's process_termine.php (für Form-Actions)
    $_mtp_proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_mtp_docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    $_mtp_proc    = realpath(__DIR__ . '/process_termine.php');
    $terminplanung_process_url = $_mtp_proto . '://' . $_SERVER['HTTP_HOST']
        . str_replace('\\', '/', substr($_mtp_proc, strlen($_mtp_docroot)));

    // URL des aufrufenden MTool-Skripts (für Rückleitungen nach POST)
    $_mtp_caller  = realpath($_SERVER['SCRIPT_FILENAME']);
    $terminplanung_share_url = $_mtp_proto . '://' . $_SERVER['HTTP_HOST']
        . str_replace('\\', '/', substr($_mtp_caller, strlen($_mtp_docroot)));

    if (file_exists(__DIR__ . '/functions.php')) {
        require_once __DIR__ . '/functions.php';
    }
    // Minimaler HTML-Wrapper – tab_termine.php liefert seinen eigenen <style>-Block
    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="de"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Terminplanung</title>';
    echo '</head><body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">';
    echo '<div style="max-width:1100px;margin:0 auto;padding:20px;">';
    include __DIR__ . '/tab_termine.php';
    echo '</div></body></html>';
    return;
}

// Kanonische URL zu terminplanung_standalone.php (funktioniert auch wenn per include eingebunden)
$_tp_proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_tp_docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$_tp_abspath = realpath(__FILE__);
$_tp_relpath = str_replace('\\', '/', substr($_tp_abspath, strlen($_tp_docroot)));
// Interne URL (zum Navigieren innerhalb der geschützten Umgebung)
$terminplanung_self_url = $_tp_proto . '://' . $_SERVER['HTTP_HOST'] . $_tp_relpath;
$terminplanung_self     = basename(__FILE__); // für relative Links (gleiche Directory)
// Öffentliche URL für Weitergabe-Links: vom Wrapper gesetzt ($TERMINPLANUNG_PUBLIC_URL),
// sonst intern (nur sinnvoll wenn die Umgebung selbst öffentlich ist)
$terminplanung_share_url = isset($TERMINPLANUNG_PUBLIC_URL) && $TERMINPLANUNG_PUBLIC_URL
    ? rtrim($TERMINPLANUNG_PUBLIC_URL, '/')
    : $terminplanung_self_url;

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

        .vote-matrix {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .vote-matrix th,
        .vote-matrix td {
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 13px;
        }

        .vote-matrix th {
            background: #f5f5f5;
            font-weight: bold;
        }

        .vote-matrix tr:hover {
            background: #fafafa;
        }

        .vote-buttons {
            display: flex;
            gap: 4px;
            justify-content: flex-start;
        }

        .vote-btn {
            border: 2px solid #ddd;
            background: white;
            padding: 4px 8px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .vote-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .vote-btn.selected {
            border-width: 3px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .vote-btn.vote-yes.selected {
            background: #4CAF50;
            border-color: #2E7D32;
            color: white;
        }

        .vote-btn.vote-maybe.selected {
            background: #FFC107;
            border-color: #F57C00;
            color: white;
        }

        .vote-btn.vote-no.selected {
            background: #f44336;
            border-color: #C62828;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .poll-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px 24px;
            margin: 16px 0;
            background: #fafafa;
        }

        .poll-card h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            color: #222;
        }

        .poll-card p {
            margin: 0 0 12px 0;
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }

        .poll-card p:last-child {
            margin-bottom: 0;
        }

        .poll-card.status-closed {
            opacity: 0.7;
            border-color: #ccc;
            background: #f5f5f5;
        }

        .dashboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .dashboard-header h2 {
            margin: 0;
            border-bottom: none;
            padding-bottom: 0;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 20px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
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

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #444;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76,175,80,0.15);
        }

        .date-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 8px;
        }

        .date-row .date-label {
            min-width: 60px;
            font-size: 13px;
            color: #666;
        }

        .date-row input[type="date"] {
            flex: 2;
            padding: 7px 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 13px;
        }

        .date-row input[type="time"] {
            flex: 1;
            padding: 7px 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 13px;
        }

        .date-row .date-sep {
            color: #888;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h2 {
                font-size: 22px;
            }

            .vote-btn {
                min-width: 65px;
                padding: 4px 6px;
                font-size: 12px;
            }

            .vote-buttons {
                gap: 3px;
            }

            .vote-matrix th,
            .vote-matrix td {
                padding: 6px 4px;
                font-size: 12px;
            }
        }
    </style>
    <script>
    function selectVote(button, dateId, voteValue) {
        // Alle Buttons für dieses Datum deaktivieren
        const row = button.closest("tr");
        row.querySelectorAll(".vote-btn").forEach(btn => btn.classList.remove("selected"));

        // Aktuellen Button aktivieren
        button.classList.add("selected");

        // Hidden Input setzen
        const input = document.getElementById("vote_" + dateId);
        if (input) {
            input.value = voteValue;
        }
    }
    </script>
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
    echo '<div class="dashboard-header">';
    echo '<h2>Terminplanung</h2>';
    if ($current_user) {
        echo '<a href="' . $terminplanung_share_url . '?view=create" class="btn-primary">+ Neue Umfrage erstellen</a>';
    }
    echo '</div>';

    // Umfragen: nur die des eingeloggten Users (Ersteller oder eingeladen)
    if ($current_user) {
        $stmt = $pdo->prepare("
            SELECT * FROM svpolls
            WHERE created_by_member_id = ?
               OR EXISTS (SELECT 1 FROM svpoll_participants WHERE poll_id = svpolls.poll_id AND member_id = ?)
               OR EXISTS (SELECT 1 FROM svpoll_responses WHERE poll_id = svpolls.poll_id AND member_id = ?)
            ORDER BY created_at DESC
        ");
        $stmt->execute([$current_user['member_id'], $current_user['member_id'], $current_user['member_id']]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM svpolls WHERE status = 'open' ORDER BY created_at DESC");
        $stmt->execute();
    }
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($polls)) {
        echo '<p style="color:#666;">Noch keine Terminumfragen vorhanden.</p>';
    }

    foreach ($polls as $poll) {
        $status_label = $poll['status'] === 'open' ? '🟢 Offen' : '🔒 Geschlossen';
        echo '<div class="poll-card status-' . htmlspecialchars($poll['status']) . '">';
        echo '<h3>' . htmlspecialchars($poll['title']) . ' <small style="font-size:12px;font-weight:normal;color:#888;">' . $status_label . '</small></h3>';
        if (!empty($poll['description'])) {
            echo '<p>' . nl2br(htmlspecialchars($poll['description'])) . '</p>';
        }
        echo '<a href="' . $terminplanung_share_url . '?view=poll&poll_id=' . $poll['poll_id'] . '" class="btn-secondary">Ansehen →</a>';
        echo '</div>';
    }

} elseif ($view === 'create') {
    echo '<div style="margin-bottom:16px;"><a href="' . $terminplanung_share_url . '" class="btn-secondary">← Übersicht</a></div>';
    echo '<h2>Neue Terminumfrage erstellen</h2>';
    echo '<form method="POST" action="' . htmlspecialchars($terminplanung_self_url) . '">';
    echo '<input type="hidden" name="terminplanung_action" value="create_poll">';
    echo '<input type="hidden" name="target_type" value="individual">';

    echo '<div class="form-group">';
    echo '<label for="tp-title">Titel der Umfrage *</label>';
    echo '<input type="text" id="tp-title" name="title" required placeholder="z.B. Vorstandssitzung April 2025">';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label for="tp-desc">Beschreibung</label>';
    echo '<textarea id="tp-desc" name="description" rows="3" placeholder="Optional: Weitere Informationen zur Umfrage"></textarea>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label for="tp-loc">Ort</label>';
    echo '<input type="text" id="tp-loc" name="location" placeholder="Optional: Ort der Veranstaltung">';
    echo '</div>';

    echo '<h3 style="margin: 24px 0 12px;">Terminvorschläge</h3>';
    echo '<p style="font-size:13px;color:#666;margin-bottom:14px;">Mindestens einen Termin angeben. Endzeit ist optional.</p>';
    for ($i = 1; $i <= 8; $i++) {
        echo '<div class="date-row">';
        echo '<span class="date-label">Termin ' . $i . '</span>';
        echo '<input type="date" name="date_' . $i . '">';
        echo '<input type="time" name="time_start_' . $i . '" placeholder="Von">';
        echo '<span class="date-sep">–</span>';
        echo '<input type="time" name="time_end_' . $i . '" placeholder="Bis">';
        echo '</div>';
    }

    echo '<div style="margin-top:24px;">';
    echo '<button type="submit" class="btn-primary">Umfrage erstellen</button>';
    echo '</div>';
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

        echo '<div style="margin-bottom:16px;"><a href="' . $terminplanung_share_url . '" class="btn-secondary">← Übersicht</a></div>';
        echo '<h2>' . htmlspecialchars($poll['title']) . '</h2>';

        if (!empty($poll['description'])) {
            echo '<div class="poll-description">' . nl2br(htmlspecialchars($poll['description'])) . '</div>';
        }

        if (!empty($poll['location'])) {
            echo '<div class="poll-meta">📍 Ort: ' . htmlspecialchars($poll['location']) . '</div>';
        }

        // Weitergabe-Link anzeigen (für Ersteller/Admin)
        if (!empty($poll['access_token']) && $current_user && can_edit_poll_standalone($poll, $current_user)) {
            $share_url = $terminplanung_share_url . '?token=' . $poll['access_token'];
            echo '<div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:16px;margin:16px 0;">';
            echo '<strong>🔗 Teilnahme-Link zum Weitergeben</strong>';
            echo '<p style="font-size:13px;color:#555;margin:6px 0 10px;">Diesen Link können Sie an alle Teilnehmer weitergeben — auch ohne Login.</p>';
            echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
            echo '<input type="text" id="tp-share-url" value="' . htmlspecialchars($share_url) . '" readonly '
                . 'style="flex:1;min-width:200px;padding:8px 10px;border:1px solid #ccc;border-radius:5px;font-size:13px;background:#fff;">';
            echo '<button onclick="var i=document.getElementById(\'tp-share-url\');i.select();navigator.clipboard.writeText(i.value).then(function(){this.textContent=\'✓ Kopiert\';}.bind(this));" '
                . 'style="padding:8px 16px;background:#4CAF50;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:13px;white-space:nowrap;">Kopieren</button>';
            echo '</div>';
            echo '</div>';
        }

        // Terminvorschläge und Abstimmung
        $stmt = $pdo->prepare("SELECT * FROM svpoll_dates WHERE poll_id = ? ORDER BY suggested_date");
        $stmt->execute([$poll_id]);
        $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Bestehende Votes des aktuellen Teilnehmers laden
        $user_votes = [];
        if (isset($current_participant_type) && isset($current_participant_id)) {
            if ($current_participant_type === 'member') {
                $stmt = $pdo->prepare("SELECT date_id, vote FROM svpoll_responses WHERE poll_id = ? AND member_id = ?");
                $stmt->execute([$poll_id, $current_participant_id]);
            } else if ($current_participant_type === 'external') {
                $stmt = $pdo->prepare("SELECT date_id, vote FROM svpoll_responses WHERE poll_id = ? AND external_participant_id = ?");
                $stmt->execute([$poll_id, $current_participant_id]);
            }
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $user_votes[$row['date_id']] = (int)$row['vote'];
            }
        }

        if ($poll['status'] === 'open') {
            echo '<p style="margin-bottom: 20px; font-size: 14px; color: #666;">';
            echo '<strong>✅ Passt</strong> – Der Termin passt mir gut<br>';
            echo '<strong>🟡 Muss</strong> – Wenn es sein muss, kann ich<br>';
            echo '<strong>❌ Passt nicht</strong> – Der Termin passt mir nicht';
            echo '</p>';

            echo '<form method="POST">';
            echo '<input type="hidden" name="terminplanung_action" value="submit_vote">';
            echo '<input type="hidden" name="poll_id" value="' . $poll_id . '">';

            echo '<table class="vote-matrix">';
            echo '<thead><tr>';
            echo '<th style="width: 180px;">Terminvorschlag</th>';
            echo '<th>Deine Wahl</th>';
            echo '</tr></thead>';
            echo '<tbody>';

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

                $user_vote = $user_votes[$date['date_id']] ?? null;

                echo '<tr>';
                echo '<td style="white-space: nowrap;">';
                echo '<strong style="font-size: 15px;">' . $weekday_de . ', ' . $date_str . '</strong><br>';
                echo '<span style="color: #666; font-size: 13px;">' . $time_str . $end_time . ' Uhr</span>';
                echo '</td>';
                echo '<td>';
                echo '<input type="hidden" name="vote_' . $date['date_id'] . '" id="vote_' . $date['date_id'] . '" value="' . ($user_vote !== null ? $user_vote : '') . '">';
                echo '<div class="vote-buttons">';

                echo '<button type="button" class="vote-btn vote-yes' . ($user_vote === 1 ? ' selected' : '') . '" onclick="selectVote(this, ' . $date['date_id'] . ', 1)">';
                echo '✅ Passt';
                echo '</button>';

                echo '<button type="button" class="vote-btn vote-maybe' . ($user_vote === 0 ? ' selected' : '') . '" onclick="selectVote(this, ' . $date['date_id'] . ', 0)">';
                echo '🟡 Muss';
                echo '</button>';

                echo '<button type="button" class="vote-btn vote-no' . ($user_vote === -1 ? ' selected' : '') . '" onclick="selectVote(this, ' . $date['date_id'] . ', -1)">';
                echo '❌ Passt nicht';
                echo '</button>';

                echo '</div>'; // vote-buttons
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

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
