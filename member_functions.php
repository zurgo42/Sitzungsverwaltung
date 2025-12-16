<?php
/**
 * member_functions.php - Prozedurale Wrapper-Funktionen für flexible Mitgliederverwaltung
 *
 * ========================================
 * WAS MACHT DIESE DATEI?
 * ========================================
 *
 * Diese Datei stellt einfache Funktionen bereit, um Mitgliederdaten zu verwalten.
 * Der Trick: Die Daten können aus VERSCHIEDENEN Tabellen kommen, aber die
 * Funktionen funktionieren IMMER GLEICH.
 *
 * BEISPIEL:
 * --------
 * get_member_by_id(5)
 *
 * Gibt IMMER zurück:
 * [
 *   'member_id' => 5,
 *   'first_name' => 'Max',
 *   'last_name' => 'Mustermann',
 *   'email' => 'max@example.com',
 *   ...
 * ]
 *
 * Egal ob die Daten aus der "members" oder "berechtigte" Tabelle kommen!
 *
 * ========================================
 * WIE FUNKTIONIERT DAS?
 * ========================================
 *
 * In config_adapter.php definieren Sie, welche Tabelle verwendet werden soll:
 *
 *   define('MEMBER_SOURCE', 'members');      // Standard-Tabelle
 *   ODER
 *   define('MEMBER_SOURCE', 'berechtigte');  // Ihre externe Tabelle
 *
 * Die Funktionen unten schauen nach MEMBER_SOURCE und holen die Daten
 * aus der richtigen Tabelle - mit automatischer Feld-Umwandlung!
 *
 * ========================================
 * FÜR NACHFOLGER: WAS IST WICHTIG?
 * ========================================
 *
 * 1. NIEMALS direkt "SELECT * FROM svmembers" im Code schreiben
 *    → Stattdessen get_all_members() verwenden
 *
 * 2. NIEMALS direkt "INSERT INTO svmembers ..." im Code schreiben
 *    → Stattdessen create_member() verwenden
 *
 * 3. Die Funktionen geben IMMER das gleiche Format zurück:
 *    - member_id (nicht ID!)
 *    - first_name (nicht Vorname!)
 *    - last_name (nicht Name!)
 *    - email (nicht eMail!)
 *    etc.
 *
 * 4. Wenn Sie die Datenquelle ändern wollen:
 *    → Nur config_adapter.php anpassen
 *    → NICHT diese Datei ändern!
 *
 * ========================================
 */

// Adapter einbinden (nur einmal)
require_once __DIR__ . '/adapters/MemberAdapter.php';

/**
 * Holt den konfigurierten Adapter
 *
 * INTERN - Muss normalerweise nicht direkt aufgerufen werden
 */
function get_member_adapter($pdo) {
    static $adapter = null;

    if ($adapter === null) {
        // Welche Quelle nutzen? (aus Konfiguration)
        $source = defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'members';
        $adapter = MemberAdapterFactory::create($pdo, $source);
    }

    return $adapter;
}

// ============================================
// LESEN (READ)
// ============================================

/**
 * Holt ALLE Mitglieder
 *
 * @param PDO $pdo Datenbankverbindung
 * @return array Liste aller Mitglieder
 *
 * BEISPIEL:
 * $members = get_all_members($pdo);
 * foreach ($members as $m) {
 *     echo $m['first_name'] . ' ' . $m['last_name'];
 * }
 */
function get_all_members($pdo) {
    $adapter = get_member_adapter($pdo);
    return $adapter->getAllMembers();
}

/**
 * Holt EIN Mitglied nach ID
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $member_id ID des Mitglieds
 * @return array|null Mitgliedsdaten oder null wenn nicht gefunden
 *
 * BEISPIEL:
 * $member = get_member_by_id($pdo, 5);
 * if ($member) {
 *     echo $member['email'];
 * }
 */
function get_member_by_id($pdo, $member_id) {
    $adapter = get_member_adapter($pdo);
    return $adapter->getMemberById($member_id);
}

/**
 * Holt EIN Mitglied nach E-Mail
 *
 * @param PDO $pdo Datenbankverbindung
 * @param string $email E-Mail-Adresse
 * @return array|null Mitgliedsdaten oder null wenn nicht gefunden
 *
 * BEISPIEL:
 * $member = get_member_by_email($pdo, 'max@example.com');
 */
function get_member_by_email($pdo, $email) {
    $adapter = get_member_adapter($pdo);
    return $adapter->getMemberByEmail($email);
}

/**
 * Holt EIN Mitglied nach Mitgliedsnummer
 *
 * @param PDO $pdo Datenbankverbindung
 * @param string $membership_number Mitgliedsnummer (z.B. '0495018')
 * @return array|null Mitgliedsdaten oder null wenn nicht gefunden
 *
 * BEISPIEL:
 * $member = get_member_by_membership_number($pdo, '0495018');
 * if ($member) {
 *     echo $member['first_name'] . ' ' . $member['last_name'];
 * }
 *
 * VERWENDUNG für SSO:
 * Wenn Benutzer bereits extern authentifiziert ist, holen Sie das
 * Mitglied über die Mitgliedsnummer statt Email/Passwort.
 */
function get_member_by_membership_number($pdo, $membership_number) {
    $adapter = get_member_adapter($pdo);
    return $adapter->getMemberByMembershipNumber($membership_number);
}

// ============================================
// ERSTELLEN (CREATE)
// ============================================

/**
 * Erstellt ein NEUES Mitglied
 *
 * @param PDO $pdo Datenbankverbindung
 * @param array $data Mitgliedsdaten
 * @return int ID des neu erstellten Mitglieds
 *
 * BEISPIEL:
 * $new_id = create_member($pdo, [
 *     'first_name' => 'Max',
 *     'last_name' => 'Mustermann',
 *     'email' => 'max@example.com',
 *     'role' => 'Mitglied',
 *     'is_admin' => 0,
 *     'is_confidential' => 0,
 *     'password_hash' => password_hash('test123', PASSWORD_DEFAULT)
 * ]);
 *
 * HINWEIS: Egal ob members oder berechtigte - verwenden Sie diese Feldnamen!
 * Die Funktion übersetzt automatisch (z.B. first_name → Vorname bei berechtigte)
 */
function create_member($pdo, $data) {
    $adapter = get_member_adapter($pdo);
    return $adapter->createMember($data);
}

// ============================================
// ÄNDERN (UPDATE)
// ============================================

/**
 * Ändert ein bestehendes Mitglied
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $member_id ID des zu ändernden Mitglieds
 * @param array $data Neue Daten (nur die zu ändernden Felder)
 * @return bool true bei Erfolg
 *
 * BEISPIEL:
 * update_member($pdo, 5, [
 *     'first_name' => 'Maxine',
 *     'last_name' => 'Musterfrau',
 *     'role' => 'vorstand'
 * ]);
 *
 * HINWEIS: Die Änderung wird in der konfigurierten Tabelle gespeichert!
 * Bei MEMBER_SOURCE='berechtigte' wird die berechtigte-Tabelle geändert.
 */
function update_member($pdo, $member_id, $data) {
    $adapter = get_member_adapter($pdo);
    return $adapter->updateMember($member_id, $data);
}

// ============================================
// LÖSCHEN (DELETE)
// ============================================

/**
 * Löscht ein Mitglied
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $member_id ID des zu löschenden Mitglieds
 * @return bool true bei Erfolg
 *
 * BEISPIEL:
 * delete_member($pdo, 5);
 *
 * HINWEIS:
 * - Bei members-Tabelle: Echtes DELETE
 * - Bei berechtigte-Tabelle: Soft Delete (aktiv = 0)
 */
function delete_member($pdo, $member_id) {
    $adapter = get_member_adapter($pdo);
    return $adapter->deleteMember($member_id);
}

// ============================================
// AUTHENTIFIZIERUNG
// ============================================

/**
 * Authentifiziert ein Mitglied (Login)
 *
 * @param PDO $pdo Datenbankverbindung
 * @param string $email E-Mail-Adresse
 * @param string $password Passwort (Klartext)
 * @return array|false Mitgliedsdaten bei Erfolg, false bei Fehler
 *
 * BEISPIEL:
 * $member = authenticate_member($pdo, 'max@example.com', 'test123');
 * if ($member) {
 *     $_SESSION['member_id'] = $member['member_id'];
 *     // Login erfolgreich
 * } else {
 *     // Login fehlgeschlagen
 * }
 */
function authenticate_member($pdo, $email, $password) {
    $adapter = get_member_adapter($pdo);
    return $adapter->authenticate($email, $password);
}

// ============================================
// SSO-MODUS: VIEW-VERWALTUNG
// ============================================

/**
 * Erstellt oder aktualisiert die svmembers VIEW für SSO-Modus
 *
 * Im SSO-Modus (MEMBER_SOURCE = 'berechtigte') erstellt diese Funktion
 * automatisch eine VIEW "svmembers", die auf die externe Tabelle zeigt.
 * Dadurch funktionieren alle bestehenden SQL-Queries mit JOIN svmembers
 * automatisch mit der externen Datenquelle.
 *
 * WICHTIG: Diese Funktion sollte beim Start der Anwendung aufgerufen werden!
 *
 * @param PDO $pdo Datenbankverbindung
 * @return bool True bei Erfolg, False bei Fehler
 */
function ensure_svmembers_view($pdo) {
    // Nur im SSO-Modus mit berechtigte-Tabelle
    $source = defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'members';

    if ($source !== 'berechtigte') {
        // Im Standard-Modus nichts tun - svmembers ist eine echte Tabelle
        return true;
    }

    try {
        // Prüfen ob berechtigte-Tabelle existiert
        $stmt = $pdo->query("SHOW TABLES LIKE 'berechtigte'");
        if (!$stmt->fetch()) {
            error_log("WARNUNG: berechtigte-Tabelle existiert nicht, kann keine VIEW erstellen");
            return false;
        }

        // VIEW erstellen oder ersetzen
        // Mapping entsprechend BerechtigteAdapter
        $sql = "
        CREATE OR REPLACE VIEW svmembers AS
        SELECT
            ID AS member_id,
            MNr AS membership_number,
            Vorname AS first_name,
            Name AS last_name,
            eMail AS email,
            '' AS password_hash,
            CASE
                WHEN aktiv = 19 THEN 'Vorstand'
                WHEN Funktion = 'GF' THEN 'Geschäftsführung'
                WHEN Funktion = 'SV' THEN 'Assistenz'
                WHEN Funktion = 'RL' THEN 'Führungsteam'
                WHEN Funktion IN ('AD', 'FP') THEN 'Mitglied'
                ELSE 'Mitglied'
            END AS role,
            CASE
                WHEN Funktion IN ('GF', 'SV') OR MNr = '0495018' THEN 1
                ELSE 0
            END AS is_admin,
            CASE
                WHEN aktiv = 19 OR Funktion IN ('GF', 'SV') THEN 1
                ELSE 0
            END AS is_confidential,
            1 AS is_active,
            angelegt AS created_at,
            angelegt AS updated_at
        FROM berechtigte
        WHERE
            (aktiv > 17)
            OR Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')
            OR MNr = '0495018'
        ";

        $pdo->exec($sql);

        // Log-Eintrag für Debugging
        error_log("INFO: svmembers VIEW erfolgreich erstellt/aktualisiert für SSO-Modus");

        return true;

    } catch (PDOException $e) {
        error_log("FEHLER beim Erstellen der svmembers VIEW: " . $e->getMessage());
        return false;
    }
}

// ============================================
// KOMPATIBILITÄT
// ============================================

/**
 * Alte Funktionsnamen - für Rückwärtskompatibilität
 *
 * HINWEIS: Diese werden schrittweise durch die neuen Funktionen ersetzt
 * Nutzen Sie in neuem Code die Funktionen oben!
 */

// Diese Funktion existiert vermutlich schon in functions.php
// Wir überschreiben sie NICHT, sondern bieten eine Alternative:
if (!function_exists('get_all_members_OLD')) {
    function get_all_members_OLD($pdo) {
        // Alte Implementierung bleibt unverändert
        return $pdo->query("SELECT * FROM svmembers ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
