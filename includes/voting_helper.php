<?php
/**
 * voting_helper.php
 * Helper-Funktionen für Abstimmungsregeln
 */

/**
 * Lädt die Voting-Konfiguration aus der Datenbank
 *
 * @param PDO $pdo Datenbankverbindung
 * @return array Assoziatives Array mit config_key => config_value
 */
function lade_voting_config($pdo) {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $stmt = $pdo->query("
        SELECT config_key, config_value, config_type
        FROM svconfig
        WHERE category = 'voting'
    ");

    $config = [];
    while ($row = $stmt->fetch()) {
        $value = $row['config_value'];

        if ($row['config_type'] === 'boolean') {
            $value = ($value === '1' || $value === 'true');
        } elseif ($row['config_type'] === 'number') {
            $value = is_numeric($value) ? (float)$value : 0;
        }

        $config[$row['config_key']] = $value;
    }

    return $config;
}

/**
 * Gibt alle verfügbaren Abstimmungsregeln zurück
 *
 * @param array $config Voting-Config
 * @return array Array mit regel_key => ['label' => ..., 'desc' => ..., 'enabled' => ...]
 */
function get_voting_rules($config) {
    $rules = [
        'einfach' => [
            'label' => $config['voting_rule_einfach_label'] ?? 'Einfache Mehrheit',
            'desc' => $config['voting_rule_einfach_desc'] ?? 'Mehr Ja als Nein',
            'enabled' => $config['voting_enable_einfach'] ?? true
        ],
        'absolut' => [
            'label' => $config['voting_rule_absolut_label'] ?? 'Absolute Mehrheit',
            'desc' => $config['voting_rule_absolut_desc'] ?? 'Mehr Ja als Nein+Enthaltung',
            'enabled' => $config['voting_enable_absolut'] ?? true
        ],
        'mehrheit_stimmber' => [
            'label' => $config['voting_rule_mehrheit_stimmber_label'] ?? 'Mehrheit der Stimmberechtigten',
            'desc' => $config['voting_rule_mehrheit_stimmber_desc'] ?? 'Mehr als 50% aller Stimmberechtigten',
            'enabled' => $config['voting_enable_mehrheit_stimmber'] ?? true
        ],
        'zweidrittel' => [
            'label' => $config['voting_rule_zweidrittel_label'] ?? '2/3-Mehrheit',
            'desc' => $config['voting_rule_zweidrittel_desc'] ?? 'Ja >= 2 × Nein',
            'enabled' => $config['voting_enable_zweidrittel'] ?? true
        ],
        'einstimmig' => [
            'label' => $config['voting_rule_einstimmig_label'] ?? 'Einstimmigkeit',
            'desc' => $config['voting_rule_einstimmig_desc'] ?? 'Alle müssen Ja stimmen',
            'enabled' => $config['voting_enable_einstimmig'] ?? true
        ]
    ];

    return $rules;
}

/**
 * Gibt nur die aktivierten Abstimmungsregeln zurück
 *
 * @param array $config Voting-Config
 * @return array Array mit regel_key => ['label' => ..., 'desc' => ...]
 */
function get_enabled_voting_rules($config) {
    $all_rules = get_voting_rules($config);
    $enabled = [];

    foreach ($all_rules as $key => $rule) {
        if ($rule['enabled']) {
            $enabled[$key] = [
                'label' => $rule['label'],
                'desc' => $rule['desc']
            ];
        }
    }

    return $enabled;
}

/**
 * Gibt die Standard-Abstimmungsregel zurück
 *
 * @param array $config Voting-Config
 * @return string
 */
function get_default_voting_rule($config) {
    return $config['voting_default_rule'] ?? 'einfach';
}

/**
 * Prüft ob eine Abstimmung gemäß der Regel erfolgreich ist
 *
 * @param string $regel Regel-Key (einfach, absolut, etc.)
 * @param int $ja Anzahl Ja-Stimmen
 * @param int $nein Anzahl Nein-Stimmen
 * @param int $enthaltung Anzahl Enthaltungen
 * @param int $stimmberechtigte Anzahl aller Stimmberechtigten (für mehrheit_stimmber)
 * @return array ['erfolg' => bool, 'message' => string, 'details' => string]
 */
function pruefe_abstimmungsergebnis($regel, $ja, $nein, $enthaltung, $stimmberechtigte = 0) {
    $gesamt_abgegeben = $ja + $nein + $enthaltung;

    switch ($regel) {
        case 'einfach':
            // Einfache Mehrheit: Ja > Nein (Enthaltungen zählen nicht)
            $erfolg = $ja > $nein;
            $message = $erfolg ? 'Angenommen' : 'Abgelehnt';
            $details = "Einfache Mehrheit: {$ja} Ja > {$nein} Nein";
            break;

        case 'absolut':
            // Absolute Mehrheit: Ja > (Nein + Enthaltung)
            $erfolg = $ja > ($nein + $enthaltung);
            $message = $erfolg ? 'Angenommen' : 'Abgelehnt';
            $details = "Absolute Mehrheit: {$ja} Ja > " . ($nein + $enthaltung) . " (Nein+Enthaltung)";
            break;

        case 'mehrheit_stimmber':
            // Mehrheit der Stimmberechtigten: Ja > 50% aller Stimmberechtigten
            if ($stimmberechtigte == 0) {
                return [
                    'erfolg' => false,
                    'message' => 'Fehler: Anzahl Stimmberechtigte nicht bekannt',
                    'details' => ''
                ];
            }
            $erforderlich = ceil($stimmberechtigte / 2);
            $erfolg = $ja > ($stimmberechtigte / 2);
            $message = $erfolg ? 'Angenommen' : 'Abgelehnt';
            $details = "Mehrheit Stimmberechtigte: {$ja} Ja > {$erforderlich} (50% von {$stimmberechtigte})";
            break;

        case 'zweidrittel':
            // 2/3-Mehrheit: Ja >= 2 × Nein
            $erfolg = $ja >= (2 * $nein);
            $message = $erfolg ? 'Angenommen' : 'Abgelehnt';
            $erforderlich = 2 * $nein;
            $details = "2/3-Mehrheit: {$ja} Ja >= " . $erforderlich . " (2 × {$nein} Nein)";
            break;

        case 'einstimmig':
            // Einstimmigkeit: Alle Ja, keine Nein-Stimmen
            $erfolg = ($ja > 0 && $nein == 0 && $ja == $gesamt_abgegeben);
            $message = $erfolg ? 'Einstimmig angenommen' : 'Nicht einstimmig';
            $details = "Einstimmigkeit: {$ja} Ja, {$nein} Nein, {$enthaltung} Enthaltung";
            break;

        default:
            // Fallback: einfache Mehrheit
            $erfolg = $ja > $nein;
            $message = $erfolg ? 'Angenommen (Standardregel)' : 'Abgelehnt (Standardregel)';
            $details = "Unbekannte Regel '{$regel}', Fallback einfache Mehrheit";
    }

    return [
        'erfolg' => $erfolg,
        'message' => $message,
        'details' => $details
    ];
}

/**
 * Gibt eine formatierte Beschreibung der Abstimmungsregel zurück
 *
 * @param string $regel Regel-Key
 * @param array $config Voting-Config
 * @return string HTML-formatierte Beschreibung
 */
function get_voting_rule_description($regel, $config) {
    $rules = get_voting_rules($config);

    if (!isset($rules[$regel])) {
        return '<span style="color: #999;">Unbekannte Regel</span>';
    }

    $rule = $rules[$regel];
    return '<strong>' . htmlspecialchars($rule['label']) . '</strong>: ' . htmlspecialchars($rule['desc']);
}

/**
 * Berechnet das Abstimmungsergebnis mit Details
 *
 * @param array $antrag Antrags-Daten mit abstimmregel
 * @param array $votes Array mit Stimmen ['ja' => ..., 'nein' => ..., 'enthaltung' => ...]
 * @param int $stimmberechtigte Anzahl Stimmberechtigte
 * @return array Detailliertes Ergebnis
 */
function berechne_voting_ergebnis($antrag, $votes, $stimmberechtigte = 0) {
    $regel = $antrag['abstimmregel'] ?? 'einfach';
    $ja = $votes['ja'] ?? 0;
    $nein = $votes['nein'] ?? 0;
    $enthaltung = $votes['enthaltung'] ?? 0;

    $ergebnis = pruefe_abstimmungsergebnis($regel, $ja, $nein, $enthaltung, $stimmberechtigte);

    return array_merge($ergebnis, [
        'ja' => $ja,
        'nein' => $nein,
        'enthaltung' => $enthaltung,
        'gesamt' => $ja + $nein + $enthaltung,
        'stimmberechtigte' => $stimmberechtigte,
        'regel' => $regel
    ]);
}

/**
 * Wertet eine Abstimmung aus und ruft beschluss_annehmen/ablehnen auf.
 *
 * @param PDO    $pdo
 * @param string $antrnr
 * @param bool   $force  true = Fristablauf, kein Votum/Bedenkzeit zählen nicht mit
 */
function auswerten_abstimmung($pdo, $antrnr, $force = false) {
    $stmt = $pdo->prepare("SELECT * FROM " . TABLE_ANTRAEGE . " WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
    $antrag = $stmt->fetch();

    if (!$antrag) return;

    $abstimmende = 0;
    $abgestimmt  = 0;
    $ja = $nein = $enthaltung = $rueckverweis = $bedenkzeit = $befangen = 0;

    for ($i = 1; $i <= 6; $i++) {
        if (empty($antrag["VName$i"])) continue;

        $votum = (int)($antrag["Votum$i"] ?? 0);

        // Befangen und "kein Votum" (0, 5 bei Fristablauf): nicht zählen
        if ($votum === 6) continue;
        if ($force && ($votum === 5 || $votum === 0)) continue; // kein Votum

        $abstimmende++;
        switch ($votum) {
            case 1: $ja++;           $abgestimmt++; break;
            case 2: $nein++;         $abgestimmt++; break;
            case 3: $enthaltung++;   $abgestimmt++; break;
            case 4: $rueckverweis++; $nein++; $abgestimmt++; break;
            case 5: $bedenkzeit++;   break; // blockiert nur wenn !$force
        }
    }

    if (!$force && $abgestimmt < $abstimmende) {
        return; // Noch nicht alle abgestimmt
    }

    $regel = $antrag['abstimmregel'] ?? 'einfach';

    if ($antrag['bart'] === 'V' && (!isset($antrag['abstimmregel']) || $regel === 'einfach')) {
        if ($ja > 0 && $nein == 0) beschluss_annehmen($pdo, $antrnr, $antrag);
        else                         beschluss_ablehnen($pdo, $antrnr);
    } elseif ($antrag['bart'] === 'R' && (!isset($antrag['abstimmregel']) || $regel === 'einfach')) {
        if ($ja == 2 && $nein == 0) beschluss_annehmen($pdo, $antrnr, $antrag);
        else                         beschluss_ablehnen($pdo, $antrnr);
    } else {
        $ergebnis = pruefe_abstimmungsergebnis($regel, $ja, $nein, $enthaltung, $abstimmende);
        if ($ergebnis['erfolg']) beschluss_annehmen($pdo, $antrnr, $antrag);
        else                      beschluss_ablehnen($pdo, $antrnr);
        error_log("Abstimmung {$antrnr}: Regel={$regel}, Ja={$ja}, Nein={$nein}, Enthaltung={$enthaltung}, Ergebnis=" . ($ergebnis['erfolg'] ? 'ANGENOMMEN' : 'ABGELEHNT') . ($force ? ' [FRISTABLAUF]' : ''));
    }
}

/** Beschluss annehmen: antrnr B→VS, Eintrag in TABLE_BESCHLUESSE */
function beschluss_annehmen($pdo, $antrnr, $antrag) {
    $neue_nr = 'VS' . date('ymd') . substr($antrnr, 7);
    $pdo->prepare("UPDATE " . TABLE_ANTRAEGE . " SET antrnr = ?, warantrag = ? WHERE antrnr = ?")->execute([$neue_nr, $antrnr, $antrnr]);

    $dafuer = $dagegen = $enthaltungen = $kein_votum = [];
    for ($i = 1; $i <= 6; $i++) {
        if (empty($antrag["VName$i"])) continue;
        $member = get_member_by_id($pdo, $antrag["VName$i"]);
        $name   = $member ? (substr($member['first_name'], 0, 1) . '. ' . $member['last_name']) : ('ID ' . $antrag["VName$i"]);
        $votum  = (int)($antrag["Votum$i"] ?? 0);
        if ($votum === 1)                     $dafuer[]       = $name;
        elseif ($votum === 2 || $votum === 4) $dagegen[]      = $name;
        elseif ($votum === 3)                 $enthaltungen[] = $name;
        else                                  $kein_votum[]   = $name; // 0, 5, 6
    }

    $pdo->prepare("
        INSERT INTO " . TABLE_BESCHLUESSE . "
            (antrnr, fertig, titel, beschluss, begr, fintext, pers, sach, ressort, int_ext,
             dafuer, dagegen, enthaltungen, kein_votum, abstimmregel)
        VALUES (?, 'F', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            dafuer       = VALUES(dafuer),
            dagegen      = VALUES(dagegen),
            enthaltungen = VALUES(enthaltungen),
            kein_votum   = VALUES(kein_votum)
    ")->execute([
        $neue_nr,
        $antrag['titel'], $antrag['beschluss'], $antrag['begr'],
        $antrag['fintext'], $antrag['pers'], $antrag['sach'],
        $antrag['ressort1'], $antrag['int_ext'],
        implode(', ', $dafuer), implode(', ', $dagegen), implode(', ', $enthaltungen),
        implode(', ', $kein_votum) ?: null,
        $antrag['abstimmregel'] ?? 'einfach',
    ]);
}

/** Beschluss ablehnen: antrnr B→X */
function beschluss_ablehnen($pdo, $antrnr) {
    $neue_nr = 'X' . substr($antrnr, 1);
    $pdo->prepare("UPDATE " . TABLE_ANTRAEGE . " SET antrnr = ? WHERE antrnr = ?")->execute([$neue_nr, $antrnr]);
}
