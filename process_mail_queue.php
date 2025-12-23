#!/usr/bin/env php
<?php
/**
 * process_mail_queue.php - Cron-Job für Queue-basiertes Mail-System
 * Erstellt: 17.11.2025
 *
 * ZWECK:
 * Verarbeitet E-Mails aus der mail_queue Tabelle und versendet sie in Batches
 * Verhindert Provider-Blockierungen durch kontrollierte Versandgeschwindigkeit
 *
 * VERWENDUNG:
 * 1. Als Cron-Job (empfohlen alle 5 Minuten):
 *    */5 * * * * /usr/bin/php /pfad/zu/process_mail_queue.php >> /var/log/mail_queue.log 2>&1
 *
 * 2. Manuell via CLI:
 *    php process_mail_queue.php
 *
 * 3. Via Browser (nur für Tests):
 *    https://example.com/process_mail_queue.php
 *
 * KONFIGURATION:
 * - MAIL_QUEUE_BATCH_SIZE: Anzahl Mails pro Durchlauf (Standard: 10)
 * - MAIL_QUEUE_DELAY: Pause zwischen Mails in Sekunden (Standard: 1)
 * - MAIL_QUEUE_MAX_ATTEMPTS: Max. Zustellversuche (Standard: 3)
 */

// CLI-Mode aktivieren (keine Timeouts)
if (PHP_SAPI === 'cli') {
    set_time_limit(0);
} else {
    // Wenn via Browser aufgerufen: Auth prüfen (nur für Admins)
    session_start();
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';

    // Basis-Requirements laden (für get_member_by_id)
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/member_functions.php';

    if (!isset($_SESSION['member_id'])) {
        die('Nicht autorisiert');
    }

    // User-Rolle über Adapter holen
    $user = get_member_by_id($pdo, $_SESSION['member_id']);

    if (!$user || !in_array($user['role'], ['assistenz', 'gf'])) {
        die('Nur für Admins');
    }
}

// Basis-Requirements (wenn nicht via Browser aufgerufen)
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/member_functions.php';
}
require_once __DIR__ . '/mail_functions.php';

// Konfiguration
$batch_size = defined('MAIL_QUEUE_BATCH_SIZE') ? MAIL_QUEUE_BATCH_SIZE : 10;
$delay = defined('MAIL_QUEUE_DELAY') ? MAIL_QUEUE_DELAY : 1;
$max_attempts = defined('MAIL_QUEUE_MAX_ATTEMPTS') ? MAIL_QUEUE_MAX_ATTEMPTS : 3;

// Start-Log
$start_time = microtime(true);
log_message("=== Mail Queue Processor gestartet ===");
log_message("Batch-Size: $batch_size | Delay: {$delay}s | Max-Attempts: $max_attempts");

// Statistik
$stats = [
    'processed' => 0,
    'sent' => 0,
    'failed' => 0,
    'skipped' => 0
];

try {
    // Pending Mails holen (sortiert nach Priorität und Erstellungsdatum)
    $stmt = $pdo->prepare("
        SELECT * FROM svmail_queue
        WHERE status = 'pending'
        AND attempts < max_attempts
        AND (send_at IS NULL OR send_at <= NOW())
        ORDER BY priority DESC, created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$batch_size]);
    $mails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    log_message("Gefunden: " . count($mails) . " zu versendende E-Mails");

    if (empty($mails)) {
        log_message("Keine E-Mails zum Versenden vorhanden");
        log_message("=== Beendet (0.00s) ===\n");
        exit(0);
    }

    // Mails verarbeiten
    foreach ($mails as $mail) {
        $queue_id = $mail['queue_id'];
        $stats['processed']++;

        log_message("[$stats[processed]/$batch_size] Verarbeite Mail #{$queue_id} an {$mail['recipient']}...");

        // Status auf 'sending' setzen
        $update_stmt = $pdo->prepare("
            UPDATE svmail_queue
            SET status = 'sending', attempts = attempts + 1
            WHERE queue_id = ?
        ");
        $update_stmt->execute([$queue_id]);

        // Mail versenden (via send_via_mail - direkter Versand)
        try {
            $result = send_via_mail(
                $mail['recipient'],
                $mail['subject'],
                $mail['message_text'],
                $mail['message_html'],
                $mail['from_email'],
                $mail['from_name']
            );

            if ($result) {
                // Erfolgreich versendet
                $update_stmt = $pdo->prepare("
                    UPDATE svmail_queue
                    SET status = 'sent', sent_at = NOW(), last_error = NULL
                    WHERE queue_id = ?
                ");
                $update_stmt->execute([$queue_id]);
                $stats['sent']++;
                log_message("  ✓ Erfolgreich versendet");

            } else {
                // Versand fehlgeschlagen
                $error = "Mail-Funktion gab false zurück";

                // Max attempts erreicht?
                if ($mail['attempts'] + 1 >= $max_attempts) {
                    $update_stmt = $pdo->prepare("
                        UPDATE svmail_queue
                        SET status = 'failed', last_error = ?
                        WHERE queue_id = ?
                    ");
                    $update_stmt->execute([$error, $queue_id]);
                    $stats['failed']++;
                    log_message("  ✗ Fehlgeschlagen (max attempts erreicht)");
                } else {
                    // Zurück auf pending für nächsten Versuch
                    $update_stmt = $pdo->prepare("
                        UPDATE svmail_queue
                        SET status = 'pending', last_error = ?
                        WHERE queue_id = ?
                    ");
                    $update_stmt->execute([$error, $queue_id]);
                    $stats['skipped']++;
                    log_message("  ⟳ Wird erneut versucht (Attempt " . ($mail['attempts'] + 1) . "/$max_attempts)");
                }
            }

        } catch (Exception $e) {
            // Exception beim Versand
            $error = $e->getMessage();

            if ($mail['attempts'] + 1 >= $max_attempts) {
                $update_stmt = $pdo->prepare("
                    UPDATE svmail_queue
                    SET status = 'failed', last_error = ?
                    WHERE queue_id = ?
                ");
                $update_stmt->execute([$error, $queue_id]);
                $stats['failed']++;
                log_message("  ✗ Exception: $error");
            } else {
                $update_stmt = $pdo->prepare("
                    UPDATE svmail_queue
                    SET status = 'pending', last_error = ?
                    WHERE queue_id = ?
                ");
                $update_stmt->execute([$error, $queue_id]);
                $stats['skipped']++;
                log_message("  ⟳ Exception - wird erneut versucht: $error");
            }
        }

        // Pause zwischen Mails
        if ($stats['processed'] < count($mails) && $delay > 0) {
            usleep($delay * 1000000);
        }
    }

} catch (Exception $e) {
    log_message("FEHLER: " . $e->getMessage());
    exit(1);
}

// Ende-Log
$duration = round(microtime(true) - $start_time, 2);
log_message("=== Statistik ===");
log_message("Verarbeitet: {$stats['processed']} | Versendet: {$stats['sent']} | Fehlgeschlagen: {$stats['failed']} | Wiederholung: {$stats['skipped']}");
log_message("=== Beendet ({$duration}s) ===\n");

exit(0);

/**
 * Log-Helper-Funktion
 */
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
}
?>
