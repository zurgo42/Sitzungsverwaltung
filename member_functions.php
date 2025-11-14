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
 * 1. NIEMALS direkt "SELECT * FROM members" im Code schreiben
 *    → Stattdessen get_all_members() verwenden
 *
 * 2. NIEMALS direkt "INSERT INTO members ..." im Code schreiben
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
        return $pdo->query("SELECT * FROM members ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
