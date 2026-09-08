<?php
/**
 * opinion_standalone.php - Standalone Meinungsbild-Tool-Wrapper
 * Erstellt: 18.11.2025
 * Erweitert: 18.12.2025 - Externe Teilnehmer-Support
 *
 * VERWENDUNG:
 * ===========
 *
 * In der Sitzungsverwaltung:
 * - Automatisch über index.php?tab=opinion integriert
 * - Nutzt member_functions.php für Datenbank-Zugriff
 *
 * In anderen Anwendungen:
 * - Per include einbinden:
 *   <?php
 *     require_once 'pfad/zu/opinion_standalone.php';
 *   ?>
 * - Voraussetzungen:
 *   - $pdo: PDO-Datenbankverbindung
 *   - $MNr: Mitgliedsnummer des eingeloggten Users (für berechtigte-Tabelle)
 *   - Optional: $HOST_URL_BASE für E-Mails und Access-Links
 *
 * ZUGRIFF VIA TOKEN:
 * ==================
 * - Für target_type='individual': opinion_standalone.php?token=XXXXXXXX
 * - Für public: opinion_standalone.php?poll_id=XX
 * - Für list/authenticated: Regulärer Login erforderlich
 *
 * Externer Zugriff (ohne Login):
 * - opinion_standalone.php?poll_id=XXX
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
    // session_config.php laden damit Cookie-Einstellungen mit index.php übereinstimmen
    if (file_exists(__DIR__ . '/session_config.php')) {
        require_once __DIR__ . '/session_config.php';
    }
    session_start();
}

// Prüfen ob wir in der Sitzungsverwaltung sind
$is_sitzungsverwaltung = file_exists(__DIR__ . '/member_functions.php');

// Prüfen ob via Access-Token zugegriffen wird
$access_token = $_GET['token'] ?? null;
$poll_id_param = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : null;

// Bei POST-Requests: poll_id auch aus POST-Daten lesen (wichtig für Formular-Submits!)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$poll_id_param && isset($_POST['poll_id'])) {
    $poll_id_param = intval($_POST['poll_id']);
}

if ($is_sitzungsverwaltung) {
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
            die('❌ Datenbankverbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage()));
        }
    }

    // In Sitzungsverwaltung: Adapter-System nutzen
    require_once __DIR__ . '/member_functions.php';
    require_once __DIR__ . '/opinion_functions.php';
    require_once __DIR__ . '/external_participants_functions.php';

    // config_adapter laden (enthält REQUIRE_LOGIN und get_sso_membership_number)
    if (!defined('REQUIRE_LOGIN') && file_exists(__DIR__ . '/config_adapter.php')) {
        require_once __DIR__ . '/config_adapter.php';
    }

    // SSO Auto-Login: wenn SSO-Modus aktiv und noch keine Session
    if (!isset($_SESSION['member_id'])
        && defined('REQUIRE_LOGIN') && !REQUIRE_LOGIN
        && function_exists('get_sso_membership_number'))
    {
        $sso_mnr = get_sso_membership_number();
        if ($sso_mnr) {
            $sso_user = get_member_by_membership_number($pdo, $sso_mnr);
            if ($sso_user) {
                $_SESSION['member_id'] = $sso_user['member_id'];
                $_SESSION['role']      = $sso_user['role'] ?? '';
                $_SESSION['MNr']       = $sso_mnr;
            }
        }
    }

    // User aus Session holen (kann NULL sein bei public/token/externem Zugriff)
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

    require_once __DIR__ . '/opinion_functions.php';
    require_once __DIR__ . '/external_participants_functions.php';

    // Prüfen ob Voraussetzungen erfüllt sind (außer bei Token/externem Zugriff)
    if (!$access_token && !isset($pdo)) {
        die('FEHLER: $pdo nicht definiert. Bitte PDO-Verbindung vor dem Include erstellen.');
    }

    // User laden (kann NULL sein bei public/token/externem Zugriff)
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
                'role' => determine_role_opinion($ber['Funktion'], $ber['aktiv']),
                'is_admin' => is_admin_user_opinion($ber['Funktion'], $ber['MNr'])
            ];
        }
    }

    // Hilfsfunktionen für berechtigte-Mapping
    function determine_role_opinion($funktion, $aktiv) {
        if ($aktiv == 19) return 'vorstand';
        if ($aktiv == 18) return 'gf';
        $roleMapping = [
            'Vo'   => 'vorstand',
            'FVo'  => 'vorstand',
            'FVv'  => 'vorstand',
            'GF'   => 'gf',
            'VA'   => 'assistenz',
            'SV'   => 'assistenz',
            'MB'   => 'assistenz',
            'Ka'   => 'assistenz',
            'Orga' => 'assistenz',
            'RL'   => 'fuehrungsteam',
            'PL'   => 'fuehrungsteam',
            'JT'   => 'fuehrungsteam',
            'TM'   => 'fuehrungsteam',
            'AD'   => 'mitglied',
            'FP'   => 'mitglied',
            'Rx'   => 'mitglied',
            'Vx'   => 'mitglied',
            'Xx'   => 'mitglied',
        ];
        return $roleMapping[$funktion] ?? 'mitglied';
    }

    function is_admin_user_opinion($funktion, $mnr) {
        return in_array($funktion, ['GF', 'SV', 'VA']) || $mnr == '0495018';
    }

    // Alle Mitglieder laden
    function get_all_members_opinion_standalone($pdo) {
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
                'role' => determine_role_opinion($row['Funktion'], $row['aktiv']),
                'is_admin' => is_admin_user_opinion($row['Funktion'], $row['membership_number'])
            ];
        }
        return $members;
    }
}

// ============================================
// TOKEN-BASIERTER ZUGRIFF
// ============================================

// Falls Access-Token übergeben: Poll laden
if ($access_token) {
    $poll = get_poll_by_token($pdo, $access_token);

    if (!$poll) {
        die('<div style="background:#f8d7da;padding:20px;border:1px solid #f5c6cb;color:#721c24;border-radius:5px;margin:20px;">
            ❌ Ungültiger oder abgelaufener Zugangs-Link. Bitte prüfe den Link oder kontaktiere den Ersteller.
        </div>');
    }

    // Poll-ID für weitere Verarbeitung
    $poll_id_param = $poll['poll_id'];

    // Automatisch zur Teilnahme-Ansicht
    if (!isset($_GET['view'])) {
        $_GET['view'] = 'participate';
    }
}

// ============================================
// ÖFFENTLICHE UMFRAGEN-LISTE
// ============================================

// Wenn KEINE Token UND KEINE Poll-ID UND kein MTool-Kontext: Liste öffentlicher Umfragen anzeigen
if (!$access_token && !$poll_id_param && !isset($MNr)) {
    $stmt = $pdo->prepare("
        SELECT poll_id, title, created_at, ends_at, status
        FROM svopinion_polls
        WHERE target_type = 'public' AND status = 'active'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $public_polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Öffentliche Umfragen</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                max-width: 800px;
                margin: 40px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            h1 {
                color: #333;
                border-bottom: 3px solid #4CAF50;
                padding-bottom: 10px;
            }
            .poll-card {
                background: white;
                padding: 20px;
                margin: 15px 0;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                transition: transform 0.2s;
            }
            .poll-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            }
            .poll-title {
                font-size: 18px;
                font-weight: bold;
                color: #333;
                margin-bottom: 10px;
            }
            .poll-meta {
                font-size: 14px;
                color: #666;
                margin-bottom: 15px;
            }
            .btn-primary {
                background: #4CAF50;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                display: inline-block;
                transition: background 0.2s;
            }
            .btn-primary:hover {
                background: #45a049;
            }
            .status-active {
                color: #4CAF50;
                font-weight: bold;
            }
            .no-polls {
                text-align: center;
                padding: 40px;
                color: #999;
            }
        </style>
    </head>
    <body>
        <h1>🌐 Öffentliche Umfragen</h1>
        <p style="color: #666; margin-bottom: 30px;">Hier findest du alle öffentlichen Umfragen, an denen du teilnehmen kannst.</p>

        <?php if (empty($public_polls)): ?>
            <div class="no-polls">
                <p>📭 Aktuell gibt es keine aktiven öffentlichen Umfragen.</p>
            </div>
        <?php else: ?>
            <?php foreach ($public_polls as $poll): ?>
                <div class="poll-card">
                    <div class="poll-title"><?php echo htmlspecialchars($poll['title']); ?></div>
                    <div class="poll-meta">
                        <span class="status-active">● Aktiv</span> •
                        Läuft bis: <?php echo date('d.m.Y H:i', strtotime($poll['ends_at'])); ?>
                    </div>
                    <a href="?poll_id=<?php echo $poll['poll_id']; ?>" class="btn-primary">
                        📝 Jetzt teilnehmen
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit; // Beende Skript nach Anzeige der Liste
}

// ============================================
// EXTERNE TEILNEHMER-PRÜFUNG
// ============================================

// Wenn Poll-ID vorhanden: Prüfen ob Teilnehmer identifiziert ist
if ($poll_id_param > 0) {
    // Poll laden um Titel etc. zu haben (falls nicht schon via Token geladen)
    if (!isset($poll) || !$poll) {
        $poll = get_opinion_poll_with_options($pdo, $poll_id_param);
    }

    if ($poll) {
        // Aktuellen Teilnehmer ermitteln (Member oder Extern)
        $participant = get_current_participant($current_user, $pdo, 'meinungsbild', $poll_id_param);

        // Wenn niemand identifiziert: Registrierungsformular anzeigen
        if ($participant['type'] === 'none') {
            // Registrierungsformular einbinden
            $poll_type = 'meinungsbild';
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
// POST REQUEST HANDLING
// ============================================

// Falls process_opinion.php existiert, nutze das (in Sitzungsverwaltung)
if ($is_sitzungsverwaltung && file_exists(__DIR__ . '/process_opinion.php') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Beim POST muss auch poll_id aus POST-Daten gelesen werden
    if (!isset($poll_id_param) && isset($_POST['poll_id'])) {
        $poll_id_param = intval($_POST['poll_id']);
    }

    // Für externe Teilnehmer: Sicherstellen, dass die Session korrekt erkannt wird
    if (!$current_user && $poll_id_param) {
        $participant = get_current_participant($current_user, $pdo, 'meinungsbild', $poll_id_param);
        $current_participant_type = $participant['type'];
        $current_participant_id = $participant['id'];
        $current_participant_data = $participant['data'];
    }

    // Leite an process_opinion.php weiter
    include __DIR__ . '/process_opinion.php';
    // Nach POST-Verarbeitung wird redirected, daher Exit hier nicht nötig
}

// ============================================
// VIEW RENDERING
// ============================================

// MTool-Modus: $MNr gesetzt, kein Public-Wrapper → tab_opinion.php mit HTML-Wrapper laden
// Szenario 2 (MTool/normales Mitglied): MTool-unpassende Bereiche werden per $OPINION_MTOOL_MODE ausgeblendet
if (isset($MNr) && empty($OPINION_PUBLIC_MODE) && $current_user && file_exists(__DIR__ . '/tab_opinion.php')) {
    $OPINION_MTOOL_MODE = true;

    $_omtp_proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_omtp_docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

    // Absolute URL zu process_opinion.php (für Form-Actions aus beliebigem Verzeichnis)
    $_omtp_proc = realpath(__DIR__ . '/process_opinion.php');
    $opinion_process_url = $_omtp_proto . '://' . $_SERVER['HTTP_HOST']
        . str_replace('\\', '/', substr($_omtp_proc, strlen($_omtp_docroot)));

    // URL des aufrufenden MTool-Skripts — MTool-Routing-Parameter (z.B. steuer=221) erhalten
    if (!isset($opinion_share_url)) {
        $_omtp_caller = realpath($_SERVER['SCRIPT_FILENAME']);
        $_omtp_relpath = str_replace('\\', '/', substr($_omtp_caller, strlen($_omtp_docroot)));
        // $_GET direkt verwenden (zuverlässiger als QUERY_STRING bei manchen Server-Konfigurationen)
        $_omtp_qparams = $_GET;
        unset($_omtp_qparams['view'], $_omtp_qparams['poll_id'], $_omtp_qparams['token'], $_omtp_qparams['tab']);
        $_omtp_base_qs = http_build_query($_omtp_qparams);
        $opinion_share_url = $_omtp_proto . '://' . $_SERVER['HTTP_HOST']
            . $_omtp_relpath
            . ($_omtp_base_qs ? '?' . $_omtp_base_qs : '');
    }
    // MTool-URL in Session speichern → process_opinion.php kann sie als Fallback nutzen
    $_SESSION['opinion_mtool_share_url'] = $opinion_share_url;

    // $OPINION_PUBLIC_URL nur setzen, wenn der MTool-Aufrufer sie nicht bereits gesetzt hat.
    // Der Aufrufer kann z.B. 'https://aktive.mensa.de/opinion_standalone.php' setzen (korrekte
    // öffentliche URL für externe Teilnehmer). Hier nur als Fallback die MTool-URL verwenden.
    if (!isset($OPINION_PUBLIC_URL)) {
        $OPINION_PUBLIC_URL = $opinion_share_url;
    }
    // Explizit in $GLOBALS schreiben: generate_external_access_link() nutzt 'global $OPINION_PUBLIC_URL'.
    // Wenn MTool dieses Script aus einem Funktions-Scope per require_once einbindet, landet
    // $OPINION_PUBLIC_URL nur im lokalen Scope – global findet es dann nicht. $GLOBALS ist
    // immer der echte globale Scope, unabhängig davon, wo der Aufruf stattfand.
    $GLOBALS['OPINION_PUBLIC_URL'] = $OPINION_PUBLIC_URL;

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Meinungsbild</title></head>';
    echo '<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">';
    echo '<div style="max-width:1100px;margin:0 auto;padding:20px;">';
    include __DIR__ . '/tab_opinion.php';
    echo '</div></body></html>';
    return;
}

// Absolute URL zu process_opinion.php setzen (damit Form-Actions auch funktionieren,
// wenn das Script aus einem anderen Verzeichnis eingebunden wird, z.B. public wrapper)
if (!isset($opinion_process_url) && $is_sitzungsverwaltung) {
    $_svop_proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_svop_docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    $_svop_proc    = realpath(__DIR__ . '/process_opinion.php');
    if ($_svop_proc && strpos($_svop_proc, $_svop_docroot) === 0) {
        $opinion_process_url = $_svop_proto . '://' . $_SERVER['HTTP_HOST']
            . str_replace('\\', '/', substr($_svop_proc, strlen($_svop_docroot)));
    }
}

// tab_opinion.php für SV-eingeloggte Benutzer laden
// (externe Teilnehmer und Public-Wrapper benötigen das Standalone-Rendering weiter unten)
if ($is_sitzungsverwaltung && $current_user && empty($OPINION_PUBLIC_MODE) && file_exists(__DIR__ . '/tab_opinion.php')) {
    include __DIR__ . '/tab_opinion.php';
    return; // Beende hier
}

// ============================================
// STANDALONE-RENDERING
// ============================================

// _opinion_url() für standalone-Kontext (externe Teilnehmer, kein tab_opinion.php-Include)
if (!function_exists('_opinion_url')) {
    function _opinion_url($view = null, $poll_id = null) {
        $url = basename($_SERVER['SCRIPT_NAME']);
        $sep = '?';
        if ($view)    { $url .= $sep . 'view=' . $view;            $sep = '&'; }
        if ($poll_id) { $url .= $sep . 'poll_id=' . intval($poll_id); }
        return $url;
    }
}

// View bestimmen: Wenn poll_id vorhanden und kein User eingeloggt -> participate
$view = $_GET['view'] ?? (($poll_id_param && !$current_user) ? 'participate' : 'list');
$poll_id = $poll_id_param ?? (isset($_GET['poll_id']) ? intval($_GET['poll_id']) : null);

// CSS für Standalone-Modus (immer ausgeben, da tab_opinion.php bereits return ausgeführt hat)
echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meinungsbild-Tool</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        h1, h2, h3 { color: #333; }
        .card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-primary { background: #2196F3; color: white; border: none; padding: 12px 24px; cursor: pointer; border-radius: 6px; text-decoration: none; display: inline-block; }
        .btn-primary:hover { background: #1976D2; }
        .btn-secondary { background: #6c757d; color: white; border: none; padding: 12px 24px; cursor: pointer; border-radius: 6px; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #4CAF50; color: white; border: none; padding: 12px 24px; cursor: pointer; border-radius: 6px; text-decoration: none; display: inline-block; }
        .message { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .poll-option { padding: 10px; margin: 5px 0; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; }
        .poll-option input[type="checkbox"], .poll-option input[type="radio"] { margin-right: 10px; }
        .progress-bar { background: #e9ecef; border-radius: 4px; height: 30px; margin: 10px 0; position: relative; overflow: hidden; }
        .progress-fill { background: #2196F3; height: 100%; transition: width 0.3s; display: flex; align-items: center; padding-left: 10px; color: white; font-weight: bold; }
        textarea { width: 100%; min-height: 80px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        label { display: block; margin: 15px 0 5px 0; font-weight: bold; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .form-group { margin-bottom: 20px; }
    </style>
</head>
<body>';

// Success/Error Messages aus Session
if (isset($_SESSION['success'])) {
    echo '<div class="message">✅ ' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="error">❌ ' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

// Views rendern
if ($view === 'list') {
    // Einfache Liste aller Meinungsbilder
    echo '<div class="card">';
    echo '<h1>📊 Meinungsbild-Tool</h1>';

    if ($current_user) {
        echo '<p><a href="?view=create" class="btn-primary">+ Neues Meinungsbild erstellen</a></p>';

        $polls = get_all_opinion_polls($pdo, $current_user['member_id'], true);

        if (empty($polls)) {
            echo '<p>Noch keine Meinungsbilder vorhanden.</p>';
        } else {
            foreach ($polls as $poll) {
                echo '<div class="card">';
                echo '<h3>' . htmlspecialchars($poll['title']) . '</h3>';
                echo '<p><small>Erstellt: ' . date('d.m.Y H:i', strtotime($poll['created_at'])) . '</small></p>';
                echo '<p>Status: <strong>' . htmlspecialchars($poll['status']) . '</strong> | ';
                echo 'Antworten: ' . $poll['response_count'] . '</p>';
                echo '<p>';
                echo '<a href="?view=detail&poll_id=' . $poll['poll_id'] . '" class="btn-secondary">Details</a> ';
                echo '<a href="?view=participate&poll_id=' . $poll['poll_id'] . '" class="btn-primary">Teilnehmen</a> ';
                echo '<a href="?view=results&poll_id=' . $poll['poll_id'] . '" class="btn-success">Ergebnisse</a>';
                echo '</p>';
                echo '</div>';
            }
        }
    } else {
        echo '<p class="error">Bitte melde dich an, um Meinungsbilder zu sehen.</p>';
    }

    echo '</div>';

} elseif ($view === 'participate' && $poll_id) {
    // Teilnahme-Formular
    include __DIR__ . '/opinion_views/participate.php';

} elseif ($view === 'results' && $poll_id) {
    // Ergebnisse anzeigen
    include __DIR__ . '/opinion_views/results.php';

} elseif ($view === 'detail' && $poll_id) {
    // Detail-Ansicht
    include __DIR__ . '/opinion_views/detail.php';

} elseif ($view === 'create' && $current_user) {
    // Erstellungs-Formular
    include __DIR__ . '/opinion_views/create.php';

} else {
    echo '<div class="error">Ansicht nicht verfügbar oder keine Berechtigung.</div>';
}

// HTML schließen (tab_opinion.php hat bereits return ausgeführt für eingeloggte User)
echo '</body></html>';
?>
