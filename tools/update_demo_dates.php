<?php
/**
 * update_demo_dates.php - Dynamische Datumsanpassung für Demo-Daten
 *
 * Wird automatisch bei jedem Seitenaufruf aufgerufen (index.php), wenn DEMO_MODE_ENABLED aktiv ist,
 * und nach jedem Import (demo_import.php).
 *
 * Bei jedem Aufruf (billig): Vorbereitungs-Sitzungen 50/51/63/64/65 auf Stunden-/Tagesbasis.
 * Einmal pro Tag (Marker svconfig.demo_anchor_date, atomar per UPDATE ... WHERE alter Wert):
 *  - alle relativen Datumsangaben (Sitzungen, ToDos, Abwesenheiten, Benachrichtigungen, Kommentare ...)
 *    werden um die vergangenen Tage verschoben (feste Abstände zum Anker bleiben erhalten)
 *  - Anträge in Abstimmung: Erinnerungs-Antrag (DEMO_REMINDER_ANTRNR) steht immer auf
 *    b_date = heute - Abstimmungsdauer (letzter Abstimmungstag = heute, 24h-Erinnerung greift),
 *    seine offenen Stimmen (DEMO_REMINDER_OPEN_SLOTS) und die Versandmarker in svvote_reminders
 *    werden zurückgesetzt; alle übrigen B-Anträge behalten jüngere b_date-Werte (nie automatisch ausgewertet)
 *  - Kalender (svevents), Terminvorschläge offener Terminumfragen, Meinungsbilder bleiben aktuell
 */

if (!defined('DEMO_MODE_ENABLED') || !DEMO_MODE_ENABLED) {
    // Nur im Demo-Modus ausführen
    return;
}

require_once __DIR__ . '/demo_common.php';

// Adapter-Betrieb (echte VTool-Daten in berechtigte/antraege): nie Daten verändern
if (demo_member_source_is_berechtigte()) {
    return;
}

// Timezone setzen (falls noch nicht gesetzt)
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Europe/Berlin');
}

/**
 * Aktualisiert die Datumsangaben der Demo-Meetings
 *
 * @param PDO $pdo Datenbankverbindung
 * @param bool $force_daily Tagesaktualisierung auch dann ausführen, wenn der Marker bereits "heute" ist (Tests)
 * @return bool Erfolg
 */
function update_demo_meeting_dates($pdo, $force_daily = false) {
    try {
        $now = new DateTime();

        // Meeting 50: Mindestens 30 min vor voller Stunde bis +2h, kein Deadline
        $meeting50_start = clone $now;
        $current_minute = (int)$meeting50_start->format('i');

        // Zurück auf volle Stunde, dann mindestens 30 Minuten zurück
        if ($current_minute >= 30) {
            // Wenn aktuelle Minute >= 30, gehe zur aktuellen vollen Stunde zurück
            $meeting50_start->setTime((int)$meeting50_start->format('H'), 0, 0);
        } else {
            // Wenn aktuelle Minute < 30, gehe zur vorherigen vollen Stunde zurück
            $meeting50_start->modify('-1 hour')->setTime((int)$meeting50_start->format('H'), 0, 0);
        }

        $meeting50_end = clone $meeting50_start;
        $meeting50_end->modify('+2 hours');

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = NULL
            WHERE meeting_id = 50
            AND status = 'preparation'
            AND meeting_date < NOW()
        ");
        $stmt->execute([
            $meeting50_start->format('Y-m-d H:i:s'),
            $meeting50_end->format('Y-m-d H:i:s')
        ]);

        // Meeting 51: Heute+7 Tage 17:00-19:00, Deadline heute+6 23:59
        $meeting51_start = clone $now;
        $meeting51_start->modify('+7 days')->setTime(17, 0, 0);
        $meeting51_end = clone $meeting51_start;
        $meeting51_end->setTime(19, 0, 0);
        $meeting51_deadline = clone $now;
        $meeting51_deadline->modify('+6 days')->setTime(23, 59, 0);

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = ?
            WHERE meeting_id = 51
            AND status = 'preparation'
            AND meeting_date < NOW()
        ");
        $stmt->execute([
            $meeting51_start->format('Y-m-d H:i:s'),
            $meeting51_end->format('Y-m-d H:i:s'),
            $meeting51_deadline->format('Y-m-d H:i:s')
        ]);

        // Meeting 63: Heute+4 Tage 19:00-20:30, kein Deadline
        $meeting63_start = clone $now;
        $meeting63_start->modify('+4 days')->setTime(19, 0, 0);
        $meeting63_end = clone $meeting63_start;
        $meeting63_end->setTime(20, 30, 0);

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = NULL
            WHERE meeting_id = 63
            AND status = 'preparation'
            AND meeting_date < NOW()
        ");
        $stmt->execute([
            $meeting63_start->format('Y-m-d H:i:s'),
            $meeting63_end->format('Y-m-d H:i:s')
        ]);

        // Meeting 64: Heute+2 Tage 19:00-20:30, Deadline heute+2 12:00
        $meeting64_start = clone $now;
        $meeting64_start->modify('+2 days')->setTime(19, 0, 0);
        $meeting64_end = clone $meeting64_start;
        $meeting64_end->setTime(20, 30, 0);
        $meeting64_deadline = clone $now;
        $meeting64_deadline->modify('+2 days')->setTime(12, 0, 0);

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = ?
            WHERE meeting_id = 64
            AND status = 'preparation'
            AND meeting_date < NOW()
        ");
        $stmt->execute([
            $meeting64_start->format('Y-m-d H:i:s'),
            $meeting64_end->format('Y-m-d H:i:s'),
            $meeting64_deadline->format('Y-m-d H:i:s')
        ]);

        // Meeting 65: Heute+1 Tag 19:00-20:30, Deadline heute 00:01
        // NUR aktualisieren wenn Meeting beendet/archiviert ist (nicht bei aktiver Bearbeitung)
        $meeting65_start = clone $now;
        $meeting65_start->modify('+1 day')->setTime(19, 0, 0);
        $meeting65_end = clone $meeting65_start;
        $meeting65_end->setTime(20, 30, 0);
        $meeting65_deadline = clone $now;
        $meeting65_deadline->setTime(0, 1, 0);

        $stmt = $pdo->prepare("
            UPDATE svmeetings
            SET meeting_date = ?,
                expected_end_date = ?,
                submission_deadline = ?
            WHERE meeting_id = 65
            AND status = 'preparation'
            AND meeting_date < NOW()
        ");
        $stmt->execute([
            $meeting65_start->format('Y-m-d H:i:s'),
            $meeting65_end->format('Y-m-d H:i:s'),
            $meeting65_deadline->format('Y-m-d H:i:s')
        ]);

        // Weitere zeitabhängige Demo-Daten (ToDos, Abwesenheiten, Anträge in Abstimmung, Kalender, Umfragen):
        // nur einmal pro Tag (bzw. nach einem Import mit älterem Anker)
        demo_daily_refresh($pdo, $force_daily);

        return true;
    } catch (Exception $e) {
        error_log("Demo date update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Anzahl Tage, um die ein Datum in Schritten von $step Tagen vorgeschoben werden muss,
 * damit es mindestens $target (Y-m-d) erreicht. 0, wenn es schon >= $target ist.
 * Schrittweite 7/28 erhält den Wochentag.
 */
function demo_shift_days($from_date, $target, $step) {
    $from = new DateTime(substr($from_date, 0, 10));
    $to = new DateTime($target);
    if ($from >= $to) return 0;
    $diff = (int)$from->diff($to)->days;
    return (int)(ceil($diff / $step) * $step);
}

/** Datums-/Datetime-String um Tage verschieben, Format (Länge) bleibt erhalten. */
function demo_add_days($value, $days) {
    if ($value === null || $value === '' || $days === 0) return $value;
    $ts = strtotime($value);
    if ($ts === false) return $value;
    $len = strlen($value);
    $fmt = $len <= 10 ? 'Y-m-d' : ($len <= 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s');
    return date($fmt, strtotime(($days >= 0 ? '+' : '') . $days . ' days', $ts));
}

/** Tabelle über Konstante (Anträge/Beschlüsse), sonst Standalone-Name */
function demo_antraege_table() {
    return (defined('TABLE_ANTRAEGE') && preg_match('/^[A-Za-z0-9_]+$/', TABLE_ANTRAEGE)) ? TABLE_ANTRAEGE : 'svantraege';
}

/** Abstimmungsdauer in Tagen (svconfig.bart_B_abstimmung_tage, Standard 7) */
function demo_voting_days($pdo) {
    $dauer = 7;
    $cfg = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'bart_B_abstimmung_tage' LIMIT 1");
    if ($cfg) {
        $dauer = (int)($cfg->fetchColumn() ?: 7);
    }
    return $dauer < 1 ? 7 : $dauer;
}

/**
 * Tagesmarker svconfig.demo_anchor_date atomar auf heute setzen.
 * Rückgabe: null = heute schon erledigt (oder nicht möglich), sonst der bisherige Anker
 * ('' = Marker war nicht vorhanden, z. B. JSON-Export ohne Anker).
 */
function demo_claim_today($pdo, $force = false) {
    $today = date('Y-m-d');
    try {
        $st = $pdo->prepare("SELECT config_value FROM svconfig WHERE config_key = ?");
        $st->execute([DEMO_ANCHOR_KEY]);
        $prev = $st->fetchColumn();
        if ($prev === $today && !$force) return null;
        if ($prev === false) {
            $ins = $pdo->prepare("INSERT IGNORE INTO svconfig (config_key, config_value, config_type, description, category)
                                  VALUES (?, ?, 'text', 'Bezugstag der relativen Demo-Datumsangaben (wird von update_demo_dates.php täglich fortgeschrieben)', 'system')");
            $ins->execute([DEMO_ANCHOR_KEY, $today]);
            return $ins->rowCount() === 1 ? '' : null;
        }
        if ($prev === $today) return $today;   // force
        $upd = $pdo->prepare("UPDATE svconfig SET config_value = ? WHERE config_key = ? AND config_value = ?");
        $upd->execute([$today, DEMO_ANCHOR_KEY, $prev]);
        return $upd->rowCount() === 1 ? $prev : null;   // anderer Request war schneller
    } catch (Exception $e) {
        error_log("Demo date update (Marker) failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Verschiebt Datums-/Zeitspalten einer Tabelle um $delta Tage. Fehlende Tabellen/Spalten (ältere
 * Installationen) werden spaltenweise übersprungen.
 */
function demo_shift_table($pdo, $table, array $cols, $delta, $where = '1=1') {
    $sets = [];
    foreach ($cols as $c) $sets[] = "`$c` = DATE_ADD(`$c`, INTERVAL " . (int)$delta . " DAY)";
    try {
        $pdo->exec("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $where");
        return;
    } catch (Exception $e) {
        // Fallback: Spalte für Spalte
    }
    foreach ($cols as $c) {
        try {
            $pdo->exec("UPDATE `$table` SET `$c` = DATE_ADD(`$c`, INTERVAL " . (int)$delta . " DAY) WHERE $where");
        } catch (Exception $e) {
            // Spalte/Tabelle fehlt - ignorieren
        }
    }
}

/**
 * Alle Demo-Daten mit festem Abstand zum Anker um $delta Tage verschieben: Sitzungen (außer den
 * Vorbereitungs-Sitzungen mit Formel in update_demo_meeting_dates), ToDos, Abwesenheiten (inkl.
 * zugehörigem Kalendereintrag), Benachrichtigungen, Kommentare, Protokolle usw.
 */
function demo_shift_timeline($pdo, $delta) {
    if ($delta === 0) return;
    $formel = '50, 51, 63, 64, 65';   // Sitzungen mit eigener Tages-/Stundenformel
    demo_shift_table($pdo, 'svmeetings', ['meeting_date', 'expected_end_date', 'submission_deadline', 'started_at', 'ended_at', 'created_at'], $delta, "meeting_id NOT IN ($formel)");
    demo_shift_table($pdo, 'svmeeting_participants', ['invited_at'], $delta);
    demo_shift_table($pdo, 'svtodos', ['entry_date', 'due_date', 'completed_at', 'created_at'], $delta);
    demo_shift_table($pdo, 'svtodo_log', ['change_time'], $delta);
    demo_shift_table($pdo, 'svabsences', ['start_date', 'end_date', 'created_at'], $delta);
    demo_shift_table($pdo, 'svevents', ['start_datetime', 'end_datetime', 'recurrence_end_date'], $delta, "event_type = 'abwesenheit' AND (recurrence IS NULL OR recurrence = 'none')");
    demo_shift_table($pdo, 'svnotifications', ['created_at'], $delta);
    // updated_at explizit mitverschieben (TOP 999 = Sitzungsende; ON UPDATE würde sonst "jetzt" eintragen)
    demo_shift_table($pdo, 'svagenda_items', ['created_at', 'updated_at'], $delta);
    foreach (['svagenda_comments', 'svagenda_live_comments', 'svagenda_post_comments', 'svagenda_personal_notes', 'svprotocols', 'svprotocol_change_requests', 'svvotes', 'feedback'] as $t) {
        demo_shift_table($pdo, $t, ['created_at'], $delta);
    }
    demo_shift_table($pdo, 'svagenda_attachments', ['uploaded_at'], $delta);
    demo_shift_table($pdo, 'svvotings', ['created_at', 'closed_at'], $delta);
    demo_shift_table($pdo, 'svpolls', ['created_at', 'finalized_at'], $delta);
    demo_shift_table($pdo, 'svpoll_responses', ['created_at'], $delta);
    demo_shift_table($pdo, 'svpoll_dates', ['suggested_date', 'suggested_end_date'], $delta,
        "poll_id IN (SELECT poll_id FROM (SELECT poll_id FROM svpolls WHERE status <> 'open') x)");
    demo_shift_table($pdo, 'svopinion_polls', ['created_at'], $delta);
    demo_shift_table($pdo, 'svopinion_responses', ['responded_at', 'created_at'], $delta);
    demo_shift_table($pdo, 'svdocuments', ['created_at', 'updated_at'], $delta);
    demo_shift_table($pdo, 'svcollab_texts', ['created_at', 'finalized_at'], $delta);
    demo_shift_table($pdo, 'svcollab_text_paragraphs', ['last_edited_at', 'created_at'], $delta);
    demo_shift_table($pdo, 'svcollab_text_versions', ['created_at'], $delta);
    demo_shift_table($pdo, 'svcollab_text_participants', ['last_seen', 'joined_at'], $delta);
}

/**
 * Tagesaktualisierung: Marker setzen, relative Daten verschieben, dann Anträge/Kalender/Umfragen pflegen.
 */
function demo_daily_refresh($pdo, $force = false) {
    $prev = demo_claim_today($pdo, $force);
    if ($prev === null) return;   // heute schon erledigt
    $today = date('Y-m-d');
    if ($prev !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $prev)) {
        $delta = (int)(new DateTime($prev))->diff(new DateTime($today))->format('%r%a');
        try {
            demo_shift_timeline($pdo, $delta);
        } catch (Exception $e) {
            error_log("Demo date update (Zeitleiste) failed: " . $e->getMessage());
        }
    }
    update_demo_dynamic_dates($pdo);
    demo_regenerate_protocols($pdo);
}

/**
 * Kurzprotokolle der abgeschlossenen Demo-Sitzungen mit dem echten Generator neu erzeugen, damit Datum und Zeiten
 * im Protokolltext zu den (verschobenen) Sitzungsdaten passen.
 */
function demo_regenerate_protocols($pdo) {
    try {
        if (!function_exists('generate_protocol')) {
            if (!function_exists('get_member_by_id')) return;   // außerhalb der Anwendung (z. B. direkt nach Import)
            require_once __DIR__ . '/../module_helpers.php';
            require_once __DIR__ . '/../module_protocol.php';
        }
        $meetings = $pdo->query("SELECT * FROM svmeetings WHERE meeting_id IN (10, 11, 20, 30) AND status IN ('ended', 'protocol_ready', 'archived')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($meetings as $meeting) {
            $mid = (int)$meeting['meeting_id'];
            $items = $pdo->prepare("SELECT * FROM svagenda_items WHERE meeting_id = ? ORDER BY top_number");
            $items->execute([$mid]);
            $pids = $pdo->prepare("SELECT member_id FROM svmeeting_participants WHERE meeting_id = ?");
            $pids->execute([$mid]);
            $participants = [];
            foreach ($pids->fetchAll(PDO::FETCH_COLUMN) as $member_id) {
                $m = get_member_by_id($pdo, $member_id);
                if ($m) $participants[] = $m;
            }
            $prot = generate_protocol($pdo, $meeting, $items->fetchAll(PDO::FETCH_ASSOC), $participants);
            $pdo->prepare("UPDATE svmeetings SET protokoll = ?, prot_intern = ? WHERE meeting_id = ?")
                ->execute([$prot['public'], $prot['confidential'], $mid]);
            $upd = $pdo->prepare("UPDATE svprotocols SET content = ? WHERE meeting_id = ? AND protocol_type = ?");
            $upd->execute([$prot['public'], $mid, 'public']);
            $upd->execute([$prot['confidential'], $mid, 'confidential']);
        }
    } catch (Throwable $e) {
        error_log('Demo-Protokolle neu erzeugen fehlgeschlagen: ' . $e->getMessage());
    }
}

/** Erinnerungs-Antrag nach versehentlicher Auswertung (VS-/X-Nummer) wieder als B-Antrag herstellen */
function demo_restore_reminder_antrag($pdo, $antraege) {
    $st = $pdo->prepare("SELECT 1 FROM `$antraege` WHERE antrnr = ?");
    $st->execute([DEMO_REMINDER_ANTRNR]);
    if ($st->fetchColumn()) return;
    // angenommen: VS-Nummer mit warantrag; abgelehnt: X-Nummer
    $st = $pdo->prepare("SELECT antrnr FROM `$antraege` WHERE warantrag = ? LIMIT 1");
    $st->execute([DEMO_REMINDER_ANTRNR]);
    $vs = $st->fetchColumn();
    if ($vs) {
        $pdo->prepare("UPDATE `$antraege` SET antrnr = ?, warantrag = NULL WHERE antrnr = ?")->execute([DEMO_REMINDER_ANTRNR, $vs]);
        if (defined('TABLE_BESCHLUESSE') && preg_match('/^[A-Za-z0-9_]+$/', TABLE_BESCHLUESSE)) {
            $pdo->prepare("DELETE FROM `" . TABLE_BESCHLUESSE . "` WHERE antrnr = ?")->execute([$vs]);
        }
        return;
    }
    $x = 'X' . substr(DEMO_REMINDER_ANTRNR, 1);
    $pdo->prepare("UPDATE `$antraege` SET antrnr = ? WHERE antrnr = ?")->execute([DEMO_REMINDER_ANTRNR, $x]);
}

/**
 * Beispiel-Antrag "automatisch zur Abstimmung stellen" (laufende Nummer DEMO_AUTO_ANTRNR_SUFFIX) im Kreislauf halten:
 *  - A (wartet): Haken bleibt gesetzt; der Cron stellt ihn nach Ablauf der Wartezeit ein (A -> B).
 *  - B (automatisch eingestellt): nach 2 Tagen zurück auf A mit neuer Nummer, deren Wartezeit in 2 Tagen endet.
 * Nur wirksam, wenn für Typ B eine Wartezeit konfiguriert ist und die Spalte auto_abstimmung existiert.
 */
function demo_cycle_auto_antrag($pdo, $antraege) {
    $cfg = $pdo->query("SELECT config_key, config_value FROM svconfig WHERE config_key IN ('bart_B_wartezeit_aktiv', 'bart_B_wartezeit_tage')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $tage = (int)($cfg['bart_B_wartezeit_tage'] ?? 0);
    if (empty($cfg['bart_B_wartezeit_aktiv']) || $tage < 2) return;

    $col = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'auto_abstimmung'");
    $col->execute([$antraege]);
    if ((int)$col->fetchColumn() === 0) return;

    $st = $pdo->prepare("SELECT antrnr, b_date FROM `$antraege` WHERE antrnr REGEXP ? LIMIT 1");
    $st->execute(['^[AB][0-9]{6}' . DEMO_AUTO_ANTRNR_SUFFIX . '$']);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;

    if ($row['antrnr'][0] === 'A') {
        $pdo->prepare("UPDATE `$antraege` SET auto_abstimmung = 1 WHERE antrnr = ?")->execute([$row['antrnr']]);
        return;
    }
    if (empty($row['b_date']) || $row['b_date'] > date('Y-m-d', strtotime('-2 days'))) return;   // erst nach 2 Tagen zurücksetzen

    $neu = 'A' . date('ymd', strtotime('-' . ($tage - 2) . ' days')) . DEMO_AUTO_ANTRNR_SUFFIX;
    $sets = ['antrnr = ?', 'b_date = NULL', 'auto_abstimmung = 1'];
    for ($i = 1; $i <= 6; $i++) {
        array_push($sets, "VName$i = NULL", "Votum$i = NULL", "VBegr$i = NULL", "VProt$i = NULL", "VBedenk$i = NULL", "VDat$i = NULL");
    }
    $pdo->prepare("UPDATE `$antraege` SET " . implode(', ', $sets) . " WHERE antrnr = ?")->execute([$neu, $row['antrnr']]);
    try {
        $pdo->prepare("DELETE FROM svvote_reminders WHERE antrnr = ?")->execute([$row['antrnr']]);
    } catch (Exception $e) {
    }
}

/**
 * Hält zeitabhängige Demo-Daten aktuell. Jeder Block ist unabhängig (fehlende Tabellen/Spalten
 * in älteren Installationen brechen die anderen Blöcke nicht ab).
 *
 * 1. Anträge in Abstimmung (B...): Der Erinnerungs-Antrag DEMO_REMINDER_ANTRNR bekommt b_date =
 *    heute - Dauer (letzter Abstimmungstag ist heute; ausgewertet wird erst morgen um 0 Uhr), seine
 *    offenen Stimmen werden zurückgesetzt und svvote_reminders geleert. Alle anderen B-Anträge werden,
 *    sobald b_date <= heute - (Dauer-1) wäre, auf ein jüngeres b_date (Alter 0 .. Dauer-2) zurückgesetzt
 *    (sonst würde pseudo_cron.php sie auswerten). VDat1-6 / VBedenk1-6 werden um denselben Betrag verschoben.
 * 2. svevents: vergangene Ereignisse werden in 28-Tage-Schritten (Wochentag bleibt) in die Zukunft
 *    geschoben, bei Serien wird recurrence_end_date verlängert (Abwesenheits-Einträge wandern mit svabsences).
 * 3. svpoll_dates: Terminvorschläge offener Terminumfragen bleiben in der Zukunft.
 * 4. svopinion_polls: laufende Meinungsbilder laufen nicht ab / werden nicht gelöscht.
 */
function update_demo_dynamic_dates($pdo) {
    $today = date('Y-m-d');

    // ---- 1. Anträge in Abstimmung ----
    try {
        $antraege = demo_antraege_table();
        $dauer = demo_voting_days($pdo);

        try {
            demo_cycle_auto_antrag($pdo, $antraege);
        } catch (Exception $e) {
            error_log("Demo date update (Auto-Abstimmungs-Beispiel) failed: " . $e->getMessage());
        }

        try {
            demo_restore_reminder_antrag($pdo, $antraege);
        } catch (Exception $e) {
            error_log("Demo date update (Erinnerungs-Antrag) failed: " . $e->getMessage());
        }

        $cols = '';
        for ($i = 1; $i <= 6; $i++) {
            $cols .= ", VDat$i, VBedenk$i";
        }
        // Reset schon einen Tag vor dem Auswertungs-Stichtag (b_date < heute - Dauer), damit
        // kein Cron-Lauf zwischen zwei Seitenaufrufen die Demo-Abstimmung schließt.
        $grenz = date('Y-m-d', strtotime('-' . max($dauer - 1, 0) . ' days'));
        $stmt = $pdo->prepare("
            SELECT antrnr, b_date $cols
            FROM `$antraege`
            WHERE antrnr LIKE 'B%' AND LENGTH(antrnr) >= 8
              AND b_date IS NOT NULL AND (b_date <= ? OR antrnr = ?)
        ");
        $stmt->execute([$grenz, DEMO_REMINDER_ANTRNR]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $reminder = ($row['antrnr'] === DEMO_REMINDER_ANTRNR);
            if ($reminder) {
                $new_b = date('Y-m-d', strtotime("-{$dauer} days"));   // letzter Abstimmungstag = heute
            } else {
                // Stabiles, je Antrag unterschiedliches "Abstimmungsalter" in Tagen: 0 .. Dauer-2
                $new_age = (int)(crc32($row['antrnr']) % max($dauer - 1, 1));
                $new_b = date('Y-m-d', strtotime("-{$new_age} days"));
            }
            $delta = (int)(new DateTime($row['b_date']))->diff(new DateTime($new_b))->format('%r%a');

            $set = [];
            $params = [];
            if ($delta !== 0 && ($reminder || $delta > 0)) {
                $set[] = 'b_date = ?';
                $params[] = $new_b;
                $now_ts = time();
                for ($i = 1; $i <= 6; $i++) {
                    if (!empty($row["VDat$i"])) {
                        $nv = demo_add_days($row["VDat$i"], $delta);
                        if (strtotime($nv) > $now_ts) {
                            $nv = date('Y-m-d H:i:s', $now_ts - 60);
                            if (strlen($row["VDat$i"]) <= 16) $nv = substr($nv, 0, 16);
                        }
                        $set[] = "VDat$i = ?";
                        $params[] = $nv;
                    }
                    if (!empty($row["VBedenk$i"])) {
                        $set[] = "VBedenk$i = ?";
                        $params[] = demo_add_days($row["VBedenk$i"], $delta);
                    }
                }
            }
            if ($reminder) {
                // Offene Stimmen zurücksetzen, damit immer mindestens eine Stimme fehlt
                foreach (DEMO_REMINDER_OPEN_SLOTS as $slot) {
                    $slot = (int)$slot;
                    if ($slot < 1 || $slot > 6) continue;
                    $set[] = "Votum$slot = '0'";
                    $set[] = "VDat$slot = NULL";
                    $set[] = "VBegr$slot = NULL";
                    $set[] = "VProt$slot = NULL";
                    $set[] = "VBedenk$slot = NULL";
                }
            }
            if ($set) {
                $params[] = $row['antrnr'];
                $pdo->prepare("UPDATE `$antraege` SET " . implode(', ', $set) . " WHERE antrnr = ?")->execute($params);
            }
        }

        // Erinnerungs-Versandmarker löschen, damit die 24h-Erinnerung täglich neu auslösbar ist
        try {
            $pdo->prepare("DELETE FROM svvote_reminders WHERE antrnr = ?")->execute([DEMO_REMINDER_ANTRNR]);
        } catch (Exception $e) {
            // Tabelle wird erst beim ersten Versand angelegt
        }
    } catch (Exception $e) {
        error_log("Demo date update (Abstimmungen) failed: " . $e->getMessage());
    }

    // ---- 2. Kalender-Ereignisse ----
    try {
        $stmt = $pdo->prepare("
            SELECT event_id, start_datetime, end_datetime, recurrence, recurrence_end_date
            FROM svevents
            WHERE status != 'deleted' AND event_type != 'abwesenheit'
              AND ( ((recurrence IS NULL OR recurrence = 'none') AND DATE(start_datetime) < ?)
                 OR (recurrence IS NOT NULL AND recurrence != 'none'
                     AND recurrence_end_date IS NOT NULL AND recurrence_end_date < ?) )
        ");
        $stmt->execute([date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('+30 days'))]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
            if (empty($ev['recurrence']) || $ev['recurrence'] === 'none') {
                $days = demo_shift_days($ev['start_datetime'], date('Y-m-d', strtotime('-7 days')), 28);
                if ($days <= 0) continue;
                $upd = $pdo->prepare("UPDATE svevents SET start_datetime = ?, end_datetime = ?, recurrence_end_date = ? WHERE event_id = ?");
                $upd->execute([
                    demo_add_days($ev['start_datetime'], $days),
                    demo_add_days($ev['end_datetime'], $days),
                    demo_add_days($ev['recurrence_end_date'], $days),
                    $ev['event_id']
                ]);
            } else {
                // Serie: Ende der Wiederholung in die Zukunft verlängern
                $days = demo_shift_days($ev['recurrence_end_date'], date('Y-m-d', strtotime('+60 days')), 28);
                if ($days <= 0) continue;
                $pdo->prepare("UPDATE svevents SET recurrence_end_date = ? WHERE event_id = ?")
                    ->execute([demo_add_days($ev['recurrence_end_date'], $days), $ev['event_id']]);
            }
        }
    } catch (Exception $e) {
        error_log("Demo date update (svevents) failed: " . $e->getMessage());
    }

    // ---- 3. Terminvorschläge offener Terminumfragen ----
    try {
        $stmt = $pdo->prepare("
            SELECT d.poll_id, MIN(d.suggested_date) AS first_date
            FROM svpoll_dates d
            JOIN svpolls p ON p.poll_id = d.poll_id
            WHERE p.status = 'open'
            GROUP BY d.poll_id
            HAVING DATE(MIN(d.suggested_date)) < ?
        ");
        $stmt->execute([date('Y-m-d', strtotime('+2 days'))]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $days = demo_shift_days($p['first_date'], date('Y-m-d', strtotime('+7 days')), 7);
            if ($days <= 0) continue;
            $pdo->prepare("
                UPDATE svpoll_dates
                SET suggested_date = DATE_ADD(suggested_date, INTERVAL ? DAY),
                    suggested_end_date = DATE_ADD(suggested_end_date, INTERVAL ? DAY)
                WHERE poll_id = ?
            ")->execute([$days, $days, $p['poll_id']]);
        }
    } catch (Exception $e) {
        error_log("Demo date update (svpoll_dates) failed: " . $e->getMessage());
    }

    // ---- 4. Meinungsbilder (Ablauf / automatische Löschung) ----
    try {
        $stmt = $pdo->prepare("
            SELECT poll_id, ends_at, delete_at, status FROM svopinion_polls
            WHERE (status = 'active' AND ends_at IS NOT NULL AND ends_at < ?)
               OR (status = 'ended' AND delete_at IS NOT NULL AND delete_at < ?)
        ");
        $stmt->execute([date('Y-m-d H:i:s', strtotime('+1 day')), date('Y-m-d H:i:s', strtotime('+1 day'))]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            if ($o['status'] === 'active') {
                $days = demo_shift_days($o['ends_at'], date('Y-m-d', strtotime('+7 days')), 14);
            } else {
                $days = demo_shift_days($o['delete_at'], date('Y-m-d', strtotime('+10 days')), 30);
            }
            if ($days <= 0) continue;
            $pdo->prepare("
                UPDATE svopinion_polls
                SET ends_at = DATE_ADD(ends_at, INTERVAL ? DAY),
                    delete_at = DATE_ADD(delete_at, INTERVAL ? DAY)
                WHERE poll_id = ?
            ")->execute([$o['status'] === 'active' ? $days : 0, $days, $o['poll_id']]);
        }
    } catch (Exception $e) {
        error_log("Demo date update (svopinion_polls) failed: " . $e->getMessage());
    }
}

// Wenn als Standalone-Skript aufgerufen
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once dirname(__DIR__) . '/config.php';

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        if (update_demo_meeting_dates($pdo, in_array('--force', $argv ?? [], true))) {
            echo "✅ Demo-Meeting-Datumsangaben erfolgreich aktualisiert!\n";
        } else {
            echo "❌ Fehler beim Aktualisieren der Demo-Datumsangaben.\n";
        }
    } catch (PDOException $e) {
        die("Datenbankfehler: " . $e->getMessage());
    }
}
?>
