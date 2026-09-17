<?php
/**
 * antragstypen_helper.php
 * Helper-Funktionen für Antragstypen-Konfiguration
 *
 * Lädt die Konfiguration aus svconfig und stellt
 * Hilfsfunktionen bereit.
 */

/**
 * Lädt die Antragstypen-Konfiguration aus der Datenbank
 *
 * @param PDO $pdo Datenbankverbindung
 * @return array Assoziatives Array mit config_key => config_value
 */
function lade_antragstypen_config($pdo) {
    static $config = null;

    // Cache: nur einmal pro Request laden
    if ($config !== null) {
        return $config;
    }

    try {
        $stmt = $pdo->query("
            SELECT config_key, config_value, config_type
            FROM svconfig
            WHERE category = 'antragstypen'
        ");

        $config = [];
        while ($row = $stmt->fetch()) {
            $value = $row['config_value'];

            // Typ-Konvertierung
            if ($row['config_type'] === 'boolean') {
                $value = ($value === '1' || $value === 'true');
            } elseif ($row['config_type'] === 'number') {
                $value = is_numeric($value) ? (float)$value : 0;
            }

            $config[$row['config_key']] = $value;
        }

        return $config;

    } catch (PDOException $e) {
        // Tabelle existiert nicht (z.B. altes Login-System ohne svconfig)
        // Rückgabe leere Config - System arbeitet mit Defaults
        error_log("antragstypen_helper: svconfig Tabelle nicht gefunden - verwende Defaults");
        return [];
    }
}

/**
 * Prüft ob ein Antragstyp aktiviert ist
 *
 * @param string $typ V, R oder B
 * @param array $config Antragstypen-Config
 * @return bool
 */
function ist_typ_aktiv($typ, $config) {
    return isset($config["bart_{$typ}_aktiv"]) && $config["bart_{$typ}_aktiv"] === true;
}

/**
 * Gibt die Bezeichnung eines Typs zurück
 *
 * @param string $typ V, R oder B
 * @param array $config Antragstypen-Config
 * @return string
 */
function get_typ_bezeichnung($typ, $config) {
    return $config["bart_{$typ}_bezeichnung"] ?? $typ;
}

/**
 * Gibt die Beschreibung eines Typs zurück
 *
 * @param string $typ V, R oder B
 * @param array $config Antragstypen-Config
 * @return string
 */
function get_typ_beschreibung($typ, $config) {
    return $config["bart_{$typ}_beschreibung"] ?? '';
}

/**
 * Gibt alle aktiven Typen zurück
 *
 * @param array $config Antragstypen-Config
 * @return array Array mit typ => bezeichnung
 */
function get_aktive_typen($config) {
    $typen = [];
    foreach (['V', 'R', 'B'] as $typ) {
        if (ist_typ_aktiv($typ, $config)) {
            $typen[$typ] = get_typ_bezeichnung($typ, $config);
        }
    }
    return $typen;
}

/**
 * Prüft ob ein Betrag für einen Typ zulässig ist
 *
 * @param string $typ V, R oder B
 * @param float $betrag Betrag in Euro
 * @param array $config Antragstypen-Config
 * @return array ['valid' => bool, 'message' => string]
 */
function validiere_betrag($typ, $betrag, $config) {
    // Betragsgrenze aktiv?
    if (!isset($config["bart_{$typ}_betrag_aktiv"]) || !$config["bart_{$typ}_betrag_aktiv"]) {
        return ['valid' => true, 'message' => ''];
    }

    $limit = $config["bart_{$typ}_betrag_limit"] ?? 0;

    // Kein Limit gesetzt
    if ($limit <= 0) {
        return ['valid' => true, 'message' => ''];
    }

    // Betrag prüfen
    if ($betrag > $limit) {
        $typ_bez = get_typ_bezeichnung($typ, $config);
        return [
            'valid' => false,
            'message' => "Der Betrag überschreitet das Limit für {$typ_bez} ({$limit}€). Bitte wähle einen anderen Typ oder reduziere den Betrag."
        ];
    }

    return ['valid' => true, 'message' => ''];
}

/**
 * Berechnet die Wartezeit für einen Antrag
 *
 * @param string $typ V, R oder B
 * @param string $antragsdatum Datum im Format Y-m-d oder Timestamp
 * @param array $config Antragstypen-Config
 * @return array ['aktiv' => bool, 'tage' => int, 'ablauf' => timestamp, 'erfuellt' => bool]
 */
function berechne_wartezeit($typ, $antragsdatum, $config) {
    // Wartezeit aktiv?
    if (!isset($config["bart_{$typ}_wartezeit_aktiv"]) || !$config["bart_{$typ}_wartezeit_aktiv"]) {
        return ['aktiv' => false, 'tage' => 0, 'ablauf' => null, 'erfuellt' => true];
    }

    $tage = $config["bart_{$typ}_wartezeit_tage"] ?? 0;

    // Keine Wartezeit konfiguriert
    if ($tage <= 0) {
        return ['aktiv' => false, 'tage' => 0, 'ablauf' => null, 'erfuellt' => true];
    }

    // Datum konvertieren
    if (is_numeric($antragsdatum)) {
        $datum_ts = $antragsdatum;
    } else {
        $datum_ts = strtotime($antragsdatum);
    }

    if (!$datum_ts) {
        return ['aktiv' => true, 'tage' => $tage, 'ablauf' => null, 'erfuellt' => false];
    }

    // Ablaufdatum berechnen
    $ablauf_ts = $datum_ts + ($tage * 24 * 60 * 60);
    $erfuellt = time() >= $ablauf_ts;

    return [
        'aktiv' => true,
        'tage' => $tage,
        'ablauf' => $ablauf_ts,
        'erfuellt' => $erfuellt
    ];
}

/**
 * Gibt eine formatierte Wartezeit-Info zurück
 *
 * @param string $typ V, R oder B
 * @param string $antragsdatum Datum
 * @param array $config Antragstypen-Config
 * @return string HTML-formatierte Info oder leerer String
 */
function get_wartezeit_info($typ, $antragsdatum, $config) {
    $wz = berechne_wartezeit($typ, $antragsdatum, $config);

    if (!$wz['aktiv']) {
        return '';
    }

    if ($wz['erfuellt']) {
        return '<span style="color: #28a745;">✓ Wartezeit erfüllt</span>';
    }

    $ablauf_datum = date('d.m.Y', $wz['ablauf']);
    return '<span style="color: #ff9800;">⏱ Wartezeit bis ' . $ablauf_datum . ' (' . $wz['tage'] . ' Tage)</span>';
}

/**
 * Prüft ob vereinfachte Freigabe für einen Typ aktiv ist
 *
 * @param string $typ V, R oder B
 * @param array $config Antragstypen-Config
 * @return bool
 */
function ist_freigabe_vereinfacht($typ, $config) {
    return isset($config["bart_{$typ}_freigabe_vereinfacht"]) &&
           $config["bart_{$typ}_freigabe_vereinfacht"] === true;
}

/**
 * Gibt das Betrags-Pflicht-Limit zurück
 *
 * @param array $config Antragstypen-Config
 * @return float
 */
function get_betrag_pflicht_limit($config) {
    return $config['bart_pflicht_bei_betrag'] ?? 100;
}

/**
 * Prüft ob Beträge in der Liste angezeigt werden sollen
 *
 * @param array $config Antragstypen-Config
 * @return bool
 */
function zeige_betraege_in_liste($config) {
    return isset($config['bart_show_betrag_in_liste']) &&
           $config['bart_show_betrag_in_liste'] === true;
}
