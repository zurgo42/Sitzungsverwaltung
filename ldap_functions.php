<?php
/**
 * ldap_functions.php – LDAP-Abfragen an den Mitglieder-LDAP
 *
 * Verbindungslogik entspricht ldapsuche_neu() im VTool.
 * Konfiguration über LDAP_* Konstanten in config.php.
 */

/** Verbindung aufbauen und binden. Gibt LDAP-Handle zurück oder ['error' => '...']. */
function _ldap_open(): mixed {
    if (!function_exists('ldap_connect')) {
        return ['error' => 'PHP-Extension php-ldap fehlt (apt install php-ldap)'];
    }
    $ldap = @ldap_connect(LDAP_HOST, LDAP_PORT);
    if (!$ldap) {
        return ['error' => 'Keine Verbindung zu ' . LDAP_HOST];
    }
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    if (!@ldap_bind($ldap, LDAP_BIND_DN, LDAP_BIND_PW)) {
        $err  = ldap_error($ldap);
        $code = ldap_errno($ldap);
        ldap_close($ldap);
        // DN für Diagnose teilweise anzeigen (kein Passwort)
        $dn_hint = strlen(LDAP_BIND_DN) > 4
            ? substr(LDAP_BIND_DN, 0, 6) . '…' . substr(LDAP_BIND_DN, -10)
            : '(leer)';
        $hint = match($code) {
            34 => ' – LDAP_BIND_DN hat ungültiges Format (aktuell: „' . $dn_hint . '"). Prüfen in config.php.',
            49 => ' – Falsches Passwort (LDAP_BIND_PW).',
            default => ' (Code ' . $code . ')',
        };
        return ['error' => 'LDAP-Bind: ' . $err . $hint];
    }
    return $ldap;
}

const LDAP_DN      = 'ou=members,dc=mensa,dc=de';
const LDAP_GROUP_A = '(memberof=cn=MinD-MIGS,ou=login,ou=groups,dc=mensa,dc=de)';
const LDAP_ATTRS   = ['uid', 'givenname', 'sn', 'mail'];

/**
 * Sucht per Mitgliedsnummer (exakt).
 * Mensa-Konvention: UIDs beginnen mit "049"; fehlt das Präfix, wird es ergänzt.
 *
 * @return array  ['vorname', 'name', 'email'] oder ['error' => '...']
 */
function ldap_lookup_by_mnr(string $mnr): array {
    $ldap = _ldap_open();
    if (is_array($ldap)) return $ldap;

    $uid    = ($mnr !== '' && $mnr[0] !== '0') ? '049' . $mnr : $mnr;
    $filter = '(&(uid=' . ldap_escape($uid, '', LDAP_ESCAPE_FILTER) . ')' . LDAP_GROUP_A . ')';
    $sr     = @ldap_search($ldap, LDAP_DN, $filter, LDAP_ATTRS);

    if (!$sr || ldap_count_entries($ldap, $sr) === 0) {
        ldap_close($ldap);
        return ['error' => 'MNr ' . $mnr . ' nicht gefunden (UID ' . $uid . ')'];
    }

    $row = ldap_get_entries($ldap, $sr)[0];
    ldap_close($ldap);
    return [
        'vorname' => $row['givenname'][0] ?? '',
        'name'    => $row['sn'][0]        ?? '',
        'email'   => $row['mail'][0]      ?? '',
    ];
}

/**
 * Sucht per Name (Vor- oder Nachname, unscharf).
 * Gibt bis zu $limit Treffer zurück.
 *
 * @return array  Liste von ['mnr', 'vorname', 'name', 'email']
 *                oder ['error' => '...'] bei Verbindungsfehler
 */
function ldap_search_by_name(string $query, int $limit = 30): array {
    if (strlen($query) < 2) {
        return ['error' => 'Bitte mindestens 2 Zeichen eingeben.'];
    }

    $ldap = _ldap_open();
    if (is_array($ldap)) return $ldap;

    // Teilstring-Suche (Groß-/Klein egal, kein Soundex) – findet Namen, die $query enthalten
    $q      = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
    $filter = '(&(|(sn=*' . $q . '*)(givenname=*' . $q . '*))' . LDAP_GROUP_A . ')';
    $sr     = @ldap_search($ldap, LDAP_DN, $filter, LDAP_ATTRS, 0, $limit);

    if (!$sr) {
        ldap_close($ldap);
        return ['error' => 'LDAP-Suche fehlgeschlagen: ' . ldap_error($ldap)];
    }

    $entries = ldap_get_entries($ldap, $sr);
    ldap_close($ldap);

    $results = [];
    for ($i = 0; $i < $entries['count']; $i++) {
        $e = $entries[$i];
        $results[] = [
            'mnr'     => $e['uid'][0] ?? '',   // voller UID inkl. "049"-Präfix
            'vorname' => $e['givenname'][0] ?? '',
            'name'    => $e['sn'][0]        ?? '',
            'email'   => $e['mail'][0]      ?? '',
        ];
    }

    // Nach Nachname sortieren
    usort($results, fn($a, $b) => strcmp($a['name'] . $a['vorname'], $b['name'] . $b['vorname']));

    return $results;
}
