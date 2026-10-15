<?php
/**
 * update_demo_dates.php - Dynamische Datumsanpassung für Demo-Meetings
 *
 * Wird automatisch aufgerufen, wenn DEMO_MODE_ENABLED aktiv ist.
 * Passt die Datumsangaben der Demo-Meetings an, damit sie immer aktuell sind.
 */

if (!defined('DEMO_MODE_ENABLED') || !DEMO_MODE_ENABLED) {
    // Nur im Demo-Modus ausführen
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
 * @return bool Erfolg
 */
function update_demo_meeting_dates($pdo) {
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

        // Weitere zeitabhängige Demo-Daten (Anträge in Abstimmung, Kalender, Umfragen)
        update_demo_dynamic_dates($pdo);

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

/**
 * Hält zeitabhängige Demo-Daten aktuell. Jeder Block ist unabhängig (fehlende Tabellen/Spalten
 * in älteren Installationen brechen die anderen Blöcke nicht ab).
 *
 * 1. Anträge in Abstimmung (B...): b_date wird so zurückgesetzt, dass die Abstimmung offen
 *    bleibt (sonst würde pseudo_cron.php sie automatisch auswerten, wenn
 *    b_date < heute - Abstimmungsdauer). VDat1-6 / VBedenk1-6 werden um denselben Betrag verschoben.
 * 2. svevents: vergangene Ereignisse werden in 28-Tage-Schritten (Wochentag bleibt) in die Zukunft
 *    geschoben, bei Serien wird recurrence_end_date verlängert.
 * 3. svpoll_dates: Terminvorschläge offener Terminumfragen bleiben in der Zukunft.
 * 4. svopinion_polls: laufende Meinungsbilder laufen nicht ab / werden nicht gelöscht.
 */
function update_demo_dynamic_dates($pdo) {
    $today = date('Y-m-d');

    // ---- 1. Anträge in Abstimmung ----
    try {
        $antraege = (defined('TABLE_ANTRAEGE') && preg_match('/^[A-Za-z0-9_]+$/', TABLE_ANTRAEGE)) ? TABLE_ANTRAEGE : 'svantraege';

        $dauer = 7;
        $cfg = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'bart_B_abstimmung_tage' LIMIT 1");
        if ($cfg) {
            $dauer = (int)($cfg->fetchColumn() ?: 7);
        }
        if ($dauer < 1) $dauer = 7;

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
              AND b_date IS NOT NULL AND b_date <= ?
        ");
        $stmt->execute([$grenz]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            // Stabiler, je Antrag unterschiedlicher "Abstimmungsalter" in Tagen: 0 .. Dauer-2
            $new_age = (int)(crc32($row['antrnr']) % max($dauer - 1, 1));
            $new_b = date('Y-m-d', strtotime("-{$new_age} days"));
            $delta = (int)(new DateTime($row['b_date']))->diff(new DateTime($new_b))->format('%r%a');
            if ($delta <= 0) continue;

            $set = ['b_date = ?'];
            $params = [$new_b];
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
            $params[] = $row['antrnr'];
            $pdo->prepare("UPDATE `$antraege` SET " . implode(', ', $set) . " WHERE antrnr = ?")->execute($params);
        }
    } catch (Exception $e) {
        error_log("Demo date update (Abstimmungen) failed: " . $e->getMessage());
    }

    // ---- 2. Kalender-Ereignisse ----
    try {
        $stmt = $pdo->prepare("
            SELECT event_id, start_datetime, end_datetime, recurrence, recurrence_end_date
            FROM svevents
            WHERE status != 'deleted'
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

        if (update_demo_meeting_dates($pdo)) {
            echo "✅ Demo-Meeting-Datumsangaben erfolgreich aktualisiert!\n";
        } else {
            echo "❌ Fehler beim Aktualisieren der Demo-Datumsangaben.\n";
        }
    } catch (PDOException $e) {
        die("Datenbankfehler: " . $e->getMessage());
    }
}
?>
