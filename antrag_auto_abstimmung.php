<?php
/**
 * antrag_auto_abstimmung.php - Antrag zur Abstimmung stellen (A -> B) und automatische Einstellung
 *
 * - antrag_zur_abstimmung_stellen(): gemeinsame Logik für "Verbindlich einstellen" (antrag_bearbeiten.php)
 *   und die automatische Einstellung per Cron.
 * - auto_abstimmung_pruefen(): stellt Anträge mit gesetztem Haken "automatisch zur Abstimmung stellen"
 *   nach Ablauf der Wartezeit zur Abstimmung (Pseudo-Cron / cron_notifications.php).
 *
 * Die Option benötigt die Spalte auto_abstimmung in der Anträge-Tabelle (tools/migrate_auto_abstimmung.php).
 * Fehlt die Spalte, ist das Feature inaktiv und nichts ändert sich.
 */

function antrag_auto_column_exists($pdo) {
    static $exists = null;
    if ($exists === null) {
        try {
            $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'auto_abstimmung'");
            $q->execute([TABLE_ANTRAEGE]);
            $exists = ((int)$q->fetchColumn() > 0);
        } catch (\Throwable $e) {
            $exists = false;
        }
    }
    return $exists;
}

/**
 * Stellt einen A-Antrag zur Abstimmung (A -> B): Abstimmende setzen, b_date = heute, Mail.
 * Wirft eine Exception, wenn Voraussetzungen fehlen. Liefert die neue Antragsnummer
 * (null, wenn der Antrag parallel bereits eingestellt wurde).
 */
function antrag_zur_abstimmung_stellen($pdo, $antrnr) {
    // Antrag neu laden
    $stmt = $pdo->prepare("SELECT * FROM " . TABLE_ANTRAEGE . " WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
    $antrag = $stmt->fetch();

    if (empty($antrag['ressort1'])) throw new Exception("Ressort muss angegeben werden.");
    if (empty($antrag['verant']) || strlen($antrag['verant']) < 3) throw new Exception("Verantwortlicher muss angegeben werden.");

    $prefix = substr($antrnr, 0, 1);
    if ($prefix !== 'A') throw new Exception("Nur Anträge im Editiermodus können finalisiert werden.");

    // DEBUG: Ausgabe von bart
    error_log("DEBUG finalisiereAntrag: bart=" . $antrag['bart'] . ", wichtig=" . ($antrag['wichtig'] ?? '0') . ", fin=" . ($antrag['fin'] ?? '0') . ", verf1=" . ($antrag['verf1'] ?? 'leer') . ", verf2=" . ($antrag['verf2'] ?? 'leer'));

    // Abstimmende setzen je nach Beschlussart
    $vname_fields = [];

    if ($antrag['bart'] === 'V') {
        // Verfügung: Keine Abstimmung. Mit "Verbindlich einstellen" ist die Verfügung beschlossen und wird
        // direkt ins Beschlussbuch eingetragen (VS-Nummer, Beschluss-Eintrag).
        if (empty($antrag['verf1'])) throw new Exception("Verfügungsberechtigte/r muss angegeben werden.");
        require_once __DIR__ . '/voting_helper.php';
        $verf = get_member_by_id($pdo, $antrag['verf1']);
        $verf_name = $verf ? trim($verf['first_name'] . ' ' . $verf['last_name']) : ('ID' . $antrag['verf1']);
        $vs_nr = beschluss_annehmen($pdo, $antrnr, $antrag, 'Verfügung von ' . $verf_name);

        if (!function_exists('nm_event_antrag_beschlossen') && file_exists(__DIR__ . '/notification_mailer.php')) {
            require_once __DIR__ . '/notification_mailer.php';
        }
        if (function_exists('nm_event_antrag_beschlossen')) {
            nm_event_antrag_beschlossen($pdo, $vs_nr, $antrag['titel'] ?? '', true, ($antrag['int_ext'] ?? 'e') === 'i', true);
        }
        return $vs_nr;

    } elseif ($antrag['bart'] === 'R') {
        // Ressortbeschluss: Abstimmung durch die beiden im Antrag angegebenen Personen (verf1 und verf2)
        if (empty($antrag['verf1']) || empty($antrag['verf2'])) {
            throw new Exception("Beide Verfügungsberechtigte müssen angegeben sein.");
        }
        if ($antrag['verf1'] == $antrag['verf2']) {
            throw new Exception("Die Verfügungsberechtigten dürfen nicht identisch sein.");
        }
        $vname_fields['VName1'] = $antrag['verf1'];
        $vname_fields['VName2'] = $antrag['verf2'];

    } elseif ($antrag['bart'] === 'B') {
        // Vorstandsbeschluss: Nur Vorstände (aktiv = 19) über Adapter
        $all_members = get_all_members($pdo);
        $vorstand_members = array_filter($all_members, function($m) {
            return isset($m['aktiv']) && (int)$m['aktiv'] === 19;
        });
        usort($vorstand_members, function($a, $b) {
            $diff = ($b['aktiv'] ?? 0) - ($a['aktiv'] ?? 0);
            return $diff != 0 ? $diff : strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
        });
        $vorstand = array_slice(array_column($vorstand_members, 'member_id'), 0, 6);

        if (empty($vorstand)) {
            throw new Exception("Keine Vorstandsmitglieder gefunden.");
        }

        for ($i = 0; $i < count($vorstand); $i++) {
            $vname_fields['VName' . ($i + 1)] = $vorstand[$i];
        }
    }

    // Neue Nummer generieren: A → B (zur Abstimmung)
    $neue_nr = 'B' . substr($antrnr, 1);

    // VName-Felder in UPDATE-Statement aufbauen
    $update_parts = ['antrnr = ?', 'b_date = CURDATE()'];
    $update_values = [$neue_nr];

    foreach ($vname_fields as $field => $value) {
        $update_parts[] = "$field = ?";
        $update_values[] = $value;
    }

    $update_values[] = $antrnr; // WHERE-Bedingung

    $sql = "UPDATE " . TABLE_ANTRAEGE . " SET " . implode(', ', $update_parts) . " WHERE antrnr = ?";
    $update = $pdo->prepare($sql);
    $update->execute($update_values);
    if ($update->rowCount() === 0) {
        return null;   // parallel bereits eingestellt (z. B. Cron und Benutzer gleichzeitig): keine zweite Mail
    }

    // E-Mail-Benachrichtigung: Antrag zur Abstimmung eingestellt
    if (!function_exists('nm_event_antrag_abstimmung') && file_exists(__DIR__ . '/notification_mailer.php')) {
        require_once __DIR__ . '/notification_mailer.php';
    }
    if (function_exists('nm_event_antrag_abstimmung')) {
        // Letzter Abstimmungstag = heute (b_date) + Abstimmungsdauer (Auswertung um 0 Uhr danach)
        require_once __DIR__ . '/voting_reminder.php';
        $letzter_tag = date('Y-m-d', strtotime('+' . voting_duration_days($pdo) . ' days'));
        nm_event_antrag_abstimmung($pdo, $antrnr, $neue_nr, $antrag['titel'] ?? '', $letzter_tag, ($antrag['int_ext'] ?? 'e') === 'i');
    }

    return $neue_nr;
}

/**
 * Anträge mit gesetztem Haken "automatisch zur Abstimmung stellen" einstellen, sobald die (für den Antragstyp
 * konfigurierte) Wartezeit abgelaufen ist (oder per Wartezeitverkürzung aufgehoben wurde) und alle Pflichtangaben vorliegen.
 * Gibt die Anzahl eingestellter Anträge zurück. Wirft nie eine Exception.
 */
function auto_abstimmung_pruefen($pdo) {
    try {
        if (!defined('TABLE_ANTRAEGE') || !antrag_auto_column_exists($pdo)) return 0;
        if (!function_exists('get_all_members')) return 0;

        $stmt = $pdo->query("SELECT * FROM " . TABLE_ANTRAEGE . " WHERE antrnr LIKE 'A%' AND auto_abstimmung = 1");
        $kandidaten = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (empty($kandidaten)) return 0;

        require_once __DIR__ . '/antragstypen_helper.php';
        if (file_exists(__DIR__ . '/protokoll_helper.php')) require_once __DIR__ . '/protokoll_helper.php';
        $config = lade_antragstypen_config($pdo);

        $eingestellt = 0;
        foreach ($kandidaten as $a) {
            $antrnr = $a['antrnr'];

            // Wartezeit (Antragsdatum steckt in der Antragsnummer A + JJMMTT + lfd. Nr.)
            if (strlen($antrnr) < 7) continue;
            $ts = strtotime('20' . substr($antrnr, 1, 2) . '-' . substr($antrnr, 3, 2) . '-' . substr($antrnr, 5, 2));
            if (!$ts) continue;
            $wz = berechne_wartezeit($a['bart'], $ts, $config);
            if (!$wz['aktiv']) continue;   // Typ ohne konfigurierte Wartezeit: keine automatische Einstellung
            $wartezeit_erfuellt = $wz['erfuellt'] || (!empty($a['verk1']) && !empty($a['verk2']));
            if (!$wartezeit_erfuellt) continue;

            // Pflichtangaben wie bei "Verbindlich einstellen"; fehlt etwas, bleibt der Antrag unverändert liegen
            if (empty($a['ressort1']) || empty($a['verant']) || strlen($a['verant']) < 3) continue;
            if ($a['bart'] === 'V' && empty($a['verf1'])) continue;
            if ($a['bart'] === 'R' && (empty($a['verf1']) || empty($a['verf2']) || $a['verf1'] == $a['verf2'])) continue;

            try {
                $neue_nr = antrag_zur_abstimmung_stellen($pdo, $antrnr);
                if ($neue_nr === null) continue;
                $eingestellt++;
                if (function_exists('protokoll')) {
                    protokoll($pdo, '', 'Auto', 'Antrag-Auto-Finalisieren', $antrnr . ' → ' . $neue_nr);
                }
            } catch (\Throwable $e) {
                // Option abschalten, damit nicht jede Minute erneut versucht wird; der Haken ist im Antrag sichtbar entfernt
                error_log('Automatische Einstellung von ' . $antrnr . ' fehlgeschlagen (Option deaktiviert): ' . $e->getMessage());
                try {
                    $pdo->prepare("UPDATE " . TABLE_ANTRAEGE . " SET auto_abstimmung = 0 WHERE antrnr = ?")->execute([$antrnr]);
                } catch (\Throwable $e2) {
                }
            }
        }
        return $eingestellt;
    } catch (\Throwable $e) {
        error_log('auto_abstimmung_pruefen: ' . $e->getMessage());
        return 0;
    }
}
