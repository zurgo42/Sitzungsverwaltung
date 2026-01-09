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

// Konfiguration für Adapter-Auswahl einbinden (nur einmal)
// WICHTIG: Muss VOR dem Adapter geladen werden, damit MEMBER_SOURCE definiert ist
if (!defined('MEMBER_SOURCE')) {
    require_once __DIR__ . '/config_adapter.php';
}

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
 * Holt ALLE registrierten Mitglieder ohne Filterung
 *
 * Diese Funktion gibt ALLE Mitglieder zurück, auch wenn sie nicht aktiv sind.
 * Nützlich für Dropdown-Listen wo alle Personen zur Auswahl stehen sollen.
 *
 * @param PDO $pdo Datenbankverbindung
 * @return array Liste aller registrierten Mitglieder
 *
 * BEISPIEL:
 * $all_registered = get_all_registered_members($pdo);
 * // Zeigt auch inaktive Mitglieder
 */
function get_all_registered_members($pdo) {
    $source = defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'members';

    if ($source === 'berechtigte') {
        // Direkt aus berechtigte-Tabelle OHNE WHERE-Filterung
        try {
            $sql = "
            SELECT
                ID AS member_id,
                MNr AS membership_number,
                Vorname AS first_name,
                Name AS last_name,
                eMail AS email,
                '' AS password_hash,
                CASE
                    WHEN aktiv = 19 THEN 'vorstand'
                    WHEN Funktion = 'GF' THEN 'gf'
                    WHEN Funktion = 'SV' THEN 'assistenz'
                    WHEN Funktion = 'RL' THEN 'fuehrungsteam'
                    ELSE 'mitglied'
                END AS role,
                CASE
                    WHEN Funktion IN ('GF', 'SV') OR MNr = '0495018' THEN 1
                    ELSE 0
                END AS is_admin,
                CASE
                    WHEN aktiv = 19 OR Funktion IN ('GF', 'SV') THEN 1
                    ELSE 0
                END AS is_confidential,
                CASE
                    WHEN aktiv > 17 OR Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF') THEN 1
                    ELSE 0
                END AS is_active,
                angelegt AS created_at,
                angelegt AS updated_at
            FROM berechtigte
            ";

            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // Role_display hinzufügen
            foreach ($rows as &$row) {
                $displayNames = [
                    'vorstand' => 'Vorstand',
                    'gf' => 'Geschäftsführung',
                    'assistenz' => 'Assistenz',
                    'fuehrungsteam' => 'Führungsteam',
                    'mitglied' => 'Mitglied'
                ];
                $row['role_display'] = $displayNames[$row['role']] ?? 'Mitglied';
            }
            unset($row);

            return $rows;
        } catch (PDOException $e) {
            error_log("Fehler in get_all_registered_members: " . $e->getMessage());
            return [];
        }
    } else {
        // Standard: Aus svmembers (alle, auch inaktive)
        $adapter = get_member_adapter($pdo);
        return $adapter->getAllMembers();
    }
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

// ============================================
// DISPLAY-FUNKTIONEN
// ============================================

/**
 * Wandelt role-Code in lesbaren Display-Namen um
 *
 * @param string $role_code Der interne role-Code (lowercase, z.B. 'gf', 'vorstand')
 * @return string Der Display-Name für die UI
 *
 * BEISPIEL:
 * echo get_role_display_name('gf');  // Ausgabe: "Geschäftsführung"
 */
function get_role_display_name($role_code) {
    $displayNames = [
        'vorstand' => 'Vorstand',
        'gf' => 'Geschäftsführung',
        'assistenz' => 'Assistenz',
        'fuehrungsteam' => 'Führungsteam',
        'mitglied' => 'Mitglied'
    ];

    return $displayNames[strtolower($role_code)] ?? 'Mitglied';
}

/**
 * Rendert eine standardisierte Teilnehmerauswahl mit Checkboxen
 *
 * @param array $members Liste aller verfügbaren Mitglieder
 * @param array $selected_ids Optional: IDs der bereits ausgewählten Mitglieder
 * @param array $member_absences Optional: Array mit Abwesenheiten (member_id => [absences])
 * @param string $checkbox_class CSS-Klasse für die Checkboxen (default: 'participant-checkbox')
 * @param string $checkbox_name Name-Attribut für die Checkboxen (default: 'participant_ids[]')
 *
 * BEISPIEL:
 * render_participant_selector($all_members, [5, 12], $absences);
 *
 * Generiert:
 * - Buttons: Alle auswählen, Alle abwählen, Führungsrollen, Vorstand+GF+Ass
 * - Checkboxen mit data-role Attributen für JavaScript
 * - Anzeige von Abwesenheiten falls vorhanden
 */
function render_participant_selector($members, $selected_ids = [], $member_absences = [], $checkbox_class = 'participant-checkbox', $checkbox_name = 'participant_ids[]') {
    ?>
    <div class="participant-buttons" style="margin: 10px 0;">
        <button type="button" onclick="toggleAllParticipants_<?php echo md5($checkbox_class); ?>(true)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✓ Alle auswählen</button>
        <button type="button" onclick="toggleAllParticipants_<?php echo md5($checkbox_class); ?>(false)" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">✗ Alle abwählen</button>
        <button type="button" onclick="toggleLeadershipRoles_<?php echo md5($checkbox_class); ?>()" class="btn-secondary" style="padding: 5px 10px; margin-right: 5px;">👔 Führungsrollen</button>
        <button type="button" onclick="toggleTopManagement_<?php echo md5($checkbox_class); ?>()" class="btn-secondary" style="padding: 5px 10px;">⭐ Vorstand+GF+Ass</button>
    </div>
    <div class="participants-selector" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
        <?php foreach ($members as $member):
            $is_selected = in_array($member['member_id'], $selected_ids);
            $has_absence = isset($member_absences[$member['member_id']]);
            $display_role = isset($member['role_display']) ? $member['role_display'] : get_role_display_name($member['role']);
        ?>
            <label class="participant-label" style="display: block; margin: 5px 0; <?php echo $has_absence ? 'background: #fff3cd; border-left: 3px solid #ffc107; padding-left: 8px;' : ''; ?>">
                <input type="checkbox"
                       name="<?php echo htmlspecialchars($checkbox_name); ?>"
                       value="<?php echo $member['member_id']; ?>"
                       class="<?php echo htmlspecialchars($checkbox_class); ?>"
                       data-role="<?php echo htmlspecialchars($member['role']); ?>"
                       <?php echo $is_selected ? 'checked' : ''; ?>>
                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $display_role . ')'); ?>
                <?php if ($has_absence): ?>
                    <br><small style="color: #856404;">
                        <?php foreach ($member_absences[$member['member_id']] as $abs): ?>
                            🏖️ <?php echo date('d.m.', strtotime($abs['start_date'])); ?> - <?php echo date('d.m.', strtotime($abs['end_date'])); ?>
                            <?php if ($abs['substitute_member_id']): ?>
                                (Vertr.: <?php echo htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']); ?>)
                            <?php endif; ?>
                            <br>
                        <?php endforeach; ?>
                    </small>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>

    <script>
    // JavaScript-Funktionen für Teilnehmerauswahl - eindeutig per Klasse
    function toggleAllParticipants_<?php echo md5($checkbox_class); ?>(select) {
        document.querySelectorAll('.<?php echo $checkbox_class; ?>').forEach(cb => cb.checked = select);
    }

    function toggleLeadershipRoles_<?php echo md5($checkbox_class); ?>() {
        document.querySelectorAll('.<?php echo $checkbox_class; ?>').forEach(cb => {
            const role = cb.getAttribute('data-role')?.toLowerCase();
            if (role === 'vorstand' || role === 'gf' || role === 'assistenz' || role === 'fuehrungsteam') {
                cb.checked = !cb.checked;
            }
        });
    }

    function toggleTopManagement_<?php echo md5($checkbox_class); ?>() {
        document.querySelectorAll('.<?php echo $checkbox_class; ?>').forEach(cb => {
            const role = cb.getAttribute('data-role')?.toLowerCase();
            if (role === 'vorstand' || role === 'gf' || role === 'assistenz') {
                cb.checked = !cb.checked;
            }
        });
    }
    </script>
    <?php
}

?>
