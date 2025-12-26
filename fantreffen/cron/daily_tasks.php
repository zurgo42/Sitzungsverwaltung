#!/usr/bin/env php
<?php
/**
 * Cronjob: Tägliche Aufgaben
 *
 * 1. Tag vor Anreise: Erinnerung an Fantreffen senden
 * 2. Tag nach Abreise: Daten bereinigen (Anmeldungen, Mails, Reise löschen)
 *
 * Crontab (täglich um 10:00 Uhr):
 * 0 10 * * * /usr/bin/php /pfad/zu/fantreffen/cron/daily_tasks.php >> /var/log/fantreffen_daily.log 2>&1
 */

// CLI-Modus erzwingen
if (PHP_SAPI !== 'cli') {
    die("Dieses Skript kann nur via CLI ausgeführt werden.\n");
}

set_time_limit(0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MailService.php';
require_once __DIR__ . '/../src/Reise.php';

echo "=== " . date('Y-m-d H:i:s') . " - Tägliche Aufgaben ===\n\n";

try {
    $db = Database::getInstance();
    $mailService = new MailService($db);
    $reiseModel = new Reise($db);

    $heute = date('Y-m-d');
    $morgen = date('Y-m-d', strtotime('+1 day'));
    $gestern = date('Y-m-d', strtotime('-1 day'));

    // =========================================================================
    // 1. ERINNERUNGEN: Reisen die morgen beginnen
    // =========================================================================
    echo "--- Erinnerungen (Anreise morgen) ---\n";

    $reisenMorgen = $db->fetchAll(
        "SELECT * FROM fan_reisen
         WHERE anfang = ?
         AND treffen_status = 'bestaetigt'
         AND infomail_gesendet IS NULL",
        [$morgen]
    );

    foreach ($reisenMorgen as $reise) {
        echo "Reise: {$reise['schiff']} (Start: {$reise['anfang']})\n";

        // Alle Teilnehmer der Reise
        $anmeldungen = $db->fetchAll(
            "SELECT a.*, u.email, t.vorname, t.name
             FROM fan_anmeldungen a
             JOIN fan_users u ON a.user_id = u.user_id
             LEFT JOIN fan_teilnehmer t ON t.teilnehmer_id = a.teilnehmer1_id
             WHERE a.reise_id = ?",
            [$reise['reise_id']]
        );

        $count = 0;
        foreach ($anmeldungen as $a) {
            $mailService->sendFromVorlage('erinnerung_anreise', $a['email'], [
                'vorname' => $a['vorname'] ?? 'Hallo',
                'name' => $a['name'] ?? '',
                'schiff' => $reise['schiff'],
                'treffen_ort' => $reise['treffen_ort'] ?? 'wird noch bekannt gegeben',
                'treffen_zeit' => $reise['treffen_zeit']
                    ? date('d.m.Y H:i', strtotime($reise['treffen_zeit'])) . ' Uhr'
                    : 'wird noch bekannt gegeben'
            ], $reise['reise_id'], 9);
            $count++;
        }

        echo "  -> $count Erinnerungen in Queue gestellt\n";

        // Markieren dass Infomail gesendet wurde
        $db->update('fan_anmeldungen', [
            'infomail_gesendet' => date('Y-m-d H:i:s')
        ], 'reise_id = ?', [$reise['reise_id']]);
    }

    if (empty($reisenMorgen)) {
        echo "Keine Reisen mit Start morgen gefunden.\n";
    }

    // =========================================================================
    // 2. BEREINIGUNG: Reisen die gestern geendet haben
    // =========================================================================
    echo "\n--- Bereinigung (Abreise gestern) ---\n";

    $reisenGestern = $db->fetchAll(
        "SELECT * FROM fan_reisen WHERE ende = ?",
        [$gestern]
    );

    foreach ($reisenGestern as $reise) {
        echo "Reise: {$reise['schiff']} (Ende: {$reise['ende']})\n";

        $reiseId = $reise['reise_id'];

        // 1. Anmeldungen zählen
        $anmeldungCount = $db->fetchColumn(
            "SELECT COUNT(*) FROM fan_anmeldungen WHERE reise_id = ?",
            [$reiseId]
        );
        echo "  -> $anmeldungCount Anmeldungen werden gelöscht\n";

        // 2. Mails aus Queue löschen
        $mailCount = $mailService->deleteMailsForReise($reiseId);
        echo "  -> $mailCount Mails aus Queue gelöscht\n";

        // 3. Anmeldungen löschen (Teilnehmer bleiben erhalten für zukünftige Reisen)
        $db->delete('fan_anmeldungen', 'reise_id = ?', [$reiseId]);

        // 4. Reise-Admins löschen
        $db->delete('fan_reise_admins', 'reise_id = ?', [$reiseId]);

        // 5. Reise selbst löschen
        $db->delete('fan_reisen', 'reise_id = ?', [$reiseId]);

        echo "  -> Reise gelöscht\n";
    }

    if (empty($reisenGestern)) {
        echo "Keine Reisen mit Ende gestern gefunden.\n";
    }

    // =========================================================================
    // 3. STATISTIK
    // =========================================================================
    echo "\n--- Statistik ---\n";

    $pendingMails = $db->fetchColumn("SELECT COUNT(*) FROM fan_mail_queue WHERE gesendet IS NULL");
    $activeReisen = $db->fetchColumn("SELECT COUNT(*) FROM fan_reisen WHERE ende >= ?", [$heute]);
    $totalAnmeldungen = $db->fetchColumn("SELECT COUNT(*) FROM fan_anmeldungen");

    echo "Ausstehende Mails: $pendingMails\n";
    echo "Aktive Reisen: $activeReisen\n";
    echo "Gesamt-Anmeldungen: $totalAnmeldungen\n";

} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Beendet ===\n\n";
exit(0);
