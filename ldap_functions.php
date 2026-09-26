<?php
/**
 * ldap_functions.php – LDAP-Abfragen an den Mitglieder-LDAP
 *
 * Verbindungslogik entspricht ldapsuche_neu() im VTool.
 * Konfiguration über LDAP_* Konstanten in config.php.
 */

/**
 * Sucht einen Eintrag per Mitgliedsnummer im LDAP.
 *
 * Mensa-Konvention: UIDs beginnen mit "049"; fehlt das Präfix, wird es ergänzt.
 * Sucht nur in der Gruppe MinD-MIGS (ANB-zugestimmt), analog zu mname() mit Funktion "a".
 *
 * @return array  ['vorname', 'name', 'email'] bei Erfolg,
 *                ['error' => '...']           bei Fehler oder nicht gefunden
 */
function ldap_lookup_by_mnr(string $mnr): array {
    if (!function_exists('ldap_connect')) {
        return ['error' => 'PHP-Extension php-ldap fehlt (sudo apt install php-ldap)'];
    }

    // MNr → UID: Mensa-Nummern beginnen mit "049"
    $uid = ($mnr !== '' && $mnr[0] !== '0') ? '049' . $mnr : $mnr;

    $ldap = @ldap_connect(LDAP_HOST, LDAP_PORT);
    if (!$ldap) {
        return ['error' => 'Keine Verbindung zu ' . LDAP_HOST];
    }
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

    if (!@ldap_bind($ldap, LDAP_BIND_DN, LDAP_BIND_PW)) {
        $err = ldap_error($ldap);
        ldap_close($ldap);
        return ['error' => 'LDAP-Bind fehlgeschlagen: ' . $err];
    }

    $dn = 'ou=members,dc=mensa,dc=de';
    // UID-Filter + Gruppenfilter "a" (MinD-MIGS mit ANB), wie in mname()
    $filter = '(&(uid=' . ldap_escape($uid, '', LDAP_ESCAPE_FILTER) . ')'
            . '(memberof=cn=MinD-MIGS,ou=login,ou=groups,dc=mensa,dc=de))';

    $attrs = ['givenname', 'sn', 'mail'];
    $sr    = @ldap_search($ldap, $dn, $filter, $attrs);

    if (!$sr || ldap_count_entries($ldap, $sr) === 0) {
        ldap_close($ldap);
        return ['error' => 'MNr ' . $mnr . ' nicht gefunden (Suche als UID ' . $uid . ')'];
    }

    $entries = ldap_get_entries($ldap, $sr);
    ldap_close($ldap);

    $row = $entries[0];
    return [
        'vorname' => $row['givenname'][0] ?? '',
        'name'    => $row['sn'][0]        ?? '',
        'email'   => $row['mail'][0]      ?? '',
    ];
}
