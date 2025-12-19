<?php
/**
 * external_participants_functions.php - Funktionen für externe Teilnehmer
 * Erstellt: 2025-12-18
 *
 * Ermöglicht Nutzern ohne Account die Teilnahme an Umfragen via Link
 */

/**
 * Generiert einen sicheren Session-Token für externe Teilnehmer
 *
 * @return string 64-stelliger Token
 */
function generate_external_token() {
    return bin2hex(random_bytes(32));
}

/**
 * Erstellt oder gibt bestehenden Opinion-Session-Token zurück
 * Wird für externe Teilnehmer bei Meinungsbildern verwendet
 *
 * @return string Session-Token
 */
function get_or_create_session_token() {
    if (!isset($_SESSION['opinion_session_token'])) {
        $_SESSION['opinion_session_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['opinion_session_token'];
}

/**
 * Speichert externen Teilnehmer in Cookie für 30 Tage
 * Ermöglicht automatisches Wiedererkennen bei zukünftigen Umfragen
 *
 * @param string $first_name
 * @param string $last_name
 * @param string $email
 * @return bool
 */
function save_external_participant_cookie($first_name, $last_name, $email) {
    $cookie_data = json_encode([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email
    ]);

    // Cookie für 30 Tage speichern
    $expires = time() + (30 * 24 * 60 * 60);
    return setcookie('sv_external_participant', $cookie_data, $expires, '/', '', false, true);
}

/**
 * Lädt externe Teilnehmer-Daten aus Cookie
 *
 * @return array|null ['first_name', 'last_name', 'email'] oder NULL
 */
function get_external_participant_from_cookie() {
    if (!isset($_COOKIE['sv_external_participant'])) {
        return null;
    }

    $data = json_decode($_COOKIE['sv_external_participant'], true);

    // Validierung
    if (!$data || !isset($data['first_name'], $data['last_name'], $data['email'])) {
        return null;
    }

    return $data;
}

/**
 * Erstellt oder aktualisiert einen externen Teilnehmer
 *
 * @param PDO $pdo
 * @param string $poll_type 'termine' oder 'meinungsbild'
 * @param int $poll_id
 * @param string $first_name
 * @param string $last_name
 * @param string $email
 * @param string|null $mnr Optional: Mitgliedsnummer
 * @param string|null $ip_address Optional: IP-Adresse
 * @return array ['external_id' => int, 'session_token' => string]
 */
function create_external_participant($pdo, $poll_type, $poll_id, $first_name, $last_name, $email, $mnr = null, $ip_address = null) {
    // Prüfen ob dieser externe Teilnehmer bereits existiert (gleiche Email für diese Umfrage)
    $stmt = $pdo->prepare("
        SELECT external_id, session_token
        FROM svexternal_participants
        WHERE poll_type = ? AND poll_id = ? AND email = ?
    ");
    $stmt->execute([$poll_type, $poll_id, $email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Teilnehmer existiert bereits - last_activity aktualisieren
        $stmt = $pdo->prepare("
            UPDATE svexternal_participants
            SET last_activity = NOW(),
                first_name = ?,
                last_name = ?,
                mnr = ?
            WHERE external_id = ?
        ");
        $stmt->execute([$first_name, $last_name, $mnr, $existing['external_id']]);

        return [
            'external_id' => $existing['external_id'],
            'session_token' => $existing['session_token']
        ];
    }

    // Neuen Teilnehmer erstellen
    $session_token = generate_external_token();

    $stmt = $pdo->prepare("
        INSERT INTO svexternal_participants
        (poll_type, poll_id, first_name, last_name, email, mnr, session_token, ip_address, created_at, last_activity)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$poll_type, $poll_id, $first_name, $last_name, $email, $mnr, $session_token, $ip_address]);

    return [
        'external_id' => $pdo->lastInsertId(),
        'session_token' => $session_token
    ];
}

/**
 * Lädt einen externen Teilnehmer anhand des Session-Tokens
 *
 * @param PDO $pdo
 * @param string $session_token
 * @return array|null Teilnehmer-Daten oder NULL
 */
function get_external_participant_by_token($pdo, $session_token) {
    $stmt = $pdo->prepare("
        SELECT * FROM svexternal_participants
        WHERE session_token = ?
    ");
    $stmt->execute([$session_token]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($participant) {
        // last_activity aktualisieren
        $stmt = $pdo->prepare("
            UPDATE svexternal_participants
            SET last_activity = NOW()
            WHERE external_id = ?
        ");
        $stmt->execute([$participant['external_id']]);
    }

    return $participant;
}

/**
 * Lädt alle externen Teilnehmer für eine Umfrage
 *
 * @param PDO $pdo
 * @param string $poll_type 'termine' oder 'meinungsbild'
 * @param int $poll_id
 * @return array Array von Teilnehmer-Daten
 */
function get_external_participants_for_poll($pdo, $poll_type, $poll_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM svexternal_participants
        WHERE poll_type = ? AND poll_id = ?
        ORDER BY last_name, first_name
    ");
    $stmt->execute([$poll_type, $poll_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Prüft ob ein externer Teilnehmer für eine Umfrage registriert ist
 *
 * @param PDO $pdo
 * @param string $session_token
 * @param string $poll_type
 * @param int $poll_id
 * @return bool
 */
function is_external_participant_registered($pdo, $session_token, $poll_type, $poll_id) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM svexternal_participants
        WHERE session_token = ? AND poll_type = ? AND poll_id = ?
    ");
    $stmt->execute([$session_token, $poll_type, $poll_id]);
    return $stmt->fetch() !== false;
}

/**
 * Löscht externe Teilnehmer, die älter als 6 Monate inaktiv sind
 *
 * @param PDO $pdo
 * @return int Anzahl gelöschter Teilnehmer
 */
function cleanup_old_external_participants($pdo) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM svexternal_participants
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Cleanup externe Teilnehmer fehlgeschlagen: " . $e->getMessage());
        return 0;
    }
}

/**
 * Speichert die Umfrage-Teilnahme eines externen Teilnehmers in der Session
 *
 * @param string $session_token
 * @param string $poll_type
 * @param int $poll_id
 * @param int $external_id
 */
function set_external_participant_session($session_token, $poll_type, $poll_id, $external_id) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['external_participant'] = [
        'session_token' => $session_token,
        'poll_type' => $poll_type,
        'poll_id' => $poll_id,
        'external_id' => $external_id,
        'timestamp' => time()
    ];
}

/**
 * Holt die externe Teilnehmer-Info aus der Session
 *
 * @return array|null
 */
function get_external_participant_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return $_SESSION['external_participant'] ?? null;
}

/**
 * Löscht die externe Teilnehmer-Info aus der Session
 */
function clear_external_participant_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    unset($_SESSION['external_participant']);
}

/**
 * Validiert die Email-Adresse
 *
 * @param string $email
 * @return bool
 */
function validate_external_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generiert einen Access-Link für externe Teilnehmer
 * ZENTRALE Funktion - verwendet STANDALONE_PATH aus config.php
 *
 * @param string $poll_type 'termine' oder 'meinungsbild'
 * @param int|string $poll_id_or_token Poll-ID oder Access-Token
 * @param bool $use_token Wenn true, wird $poll_id_or_token als Token behandelt
 * @return string
 */
function generate_external_access_link($poll_type, $poll_id_or_token, $use_token = false) {
    // Base URL ermitteln
    if (defined('BASE_URL')) {
        $base = BASE_URL;
    } else {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $base = $protocol . '://' . $_SERVER['HTTP_HOST'];
    }

    // Standalone-Pfad aus Konfiguration
    $path = defined('STANDALONE_PATH') ? STANDALONE_PATH : '';

    // Dateinamen und Parameter bestimmen
    if ($poll_type === 'termine') {
        $file = 'terminplanung_standalone.php';
    } else {
        $file = 'opinion_standalone.php';
    }

    $param = $use_token ? "token=$poll_id_or_token" : "poll_id=$poll_id_or_token";

    // Link zusammenbauen
    return rtrim($base, '/') . rtrim($path, '/') . '/' . $file . '?' . $param;
}

/**
 * Prüft ob ein Nutzer entweder eingeloggt oder als externer Teilnehmer registriert ist
 *
 * @param array|null $current_user Eingeloggter User oder NULL
 * @param PDO $pdo
 * @param string $poll_type
 * @param int $poll_id
 * @return array ['type' => 'member'|'external'|'none', 'id' => int|null, 'data' => array|null]
 */
function get_current_participant($current_user, $pdo, $poll_type, $poll_id) {
    // Zuerst: Ist jemand eingeloggt?
    if ($current_user && isset($current_user['member_id'])) {
        return [
            'type' => 'member',
            'id' => $current_user['member_id'],
            'data' => $current_user
        ];
    }

    // Zweitens: Externe Teilnehmer-Session prüfen
    $external_session = get_external_participant_session();
    if ($external_session
        && $external_session['poll_type'] === $poll_type
        && $external_session['poll_id'] == $poll_id) {

        // Teilnehmer aus DB laden
        $participant = get_external_participant_by_token($pdo, $external_session['session_token']);
        if ($participant) {
            return [
                'type' => 'external',
                'id' => $participant['external_id'],
                'data' => $participant
            ];
        }
    }

    // Niemand identifiziert
    return [
        'type' => 'none',
        'id' => null,
        'data' => null
    ];
}
?>
