#!/usr/bin/env php
<?php
/**
 * Cronjob: Mail-Queue verarbeiten
 *
 * Versendet E-Mails aus der Warteschlange mit Provider-Limits.
 * Empfohlen: Jede Stunde ausführen
 *
 * Crontab:
 * 0 * * * * /usr/bin/php /pfad/zu/fantreffen/cron/process_mail_queue.php >> /var/log/fantreffen_mail.log 2>&1
 */

// CLI-Modus erzwingen
if (PHP_SAPI !== 'cli') {
    die("Dieses Skript kann nur via CLI ausgeführt werden.\n");
}

set_time_limit(0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MailService.php';

echo "[" . date('Y-m-d H:i:s') . "] Mail-Queue Verarbeitung gestartet\n";

try {
    $db = Database::getInstance();
    $mailService = new MailService($db);

    // Provider-Limits (anpassbar)
    $limits = [
        'gmx' => 10,
        'web' => 10,
        't-online' => 10,
        'yahoo' => 10,
        'default' => 20
    ];

    $stats = $mailService->processQueue($limits);

    echo "Verarbeitet: {$stats['processed']} | Gesendet: {$stats['sent']} | Fehlgeschlagen: {$stats['failed']}\n";

} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Mail-Queue Verarbeitung beendet\n\n";
exit(0);
