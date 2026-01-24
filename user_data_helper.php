<?php
/**
 * user_data_helper.php - Vereinfachte User-Daten-Abfrage
 *
 * Holt Vorname, Name und E-Mail aus berechtigte-Tabelle oder LDAP
 */

/**
 * Hole User-Daten anhand der Mitgliedsnummer
 *
 * @param PDO $pdo Datenbankverbindung
 * @param string $MNr Mitgliedsnummer
 * @return array|null ['first_name' => ..., 'last_name' => ..., 'email' => ...]
 */
function get_user_data($pdo, $MNr) {
    global $testserver;

    // Erst in berechtigte-Tabelle suchen
    $stmt = $pdo->prepare("SELECT Vorname, Name, eMail FROM berechtigte WHERE MNr = ?");
    $stmt->execute([$MNr]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        return [
            'first_name' => $user['Vorname'],
            'last_name' => $user['Name'],
            'email' => $user['eMail'] ?? ''
        ];
    }

    // Nicht gefunden? Dann LDAP versuchen (falls verfügbar)
    if (isset($testserver) && $testserver) {
        // Testserver hat kein LDAP
        return null;
    }

    // LDAP-Suche (falls ldapsuche_neu() verfügbar ist)
    if (function_exists('ldapsuche_neu')) {
        global $data;

        // LDAP-Suche durchführen
        try {
            ldapsuche_neu($MNr, 1, "a");

            if (isset($data[0]['givenname'][0]) && isset($data[0]['sn'][0])) {
                return [
                    'first_name' => $data[0]['givenname'][0],
                    'last_name' => $data[0]['sn'][0],
                    'email' => $data[0]['mail'][0] ?? ''
                ];
            }
        } catch (Exception $e) {
            // LDAP-Fehler ignorieren, NULL zurückgeben
            error_log("LDAP-Fehler bei MNr $MNr: " . $e->getMessage());
        }
    }

    return null;
}

/**
 * Formatiere User-Name als "Vorname Nachname"
 *
 * @param array $user_data User-Daten Array
 * @return string
 */
function format_user_name($user_data) {
    if (!$user_data) {
        return '';
    }
    return trim($user_data['first_name'] . ' ' . $user_data['last_name']);
}
