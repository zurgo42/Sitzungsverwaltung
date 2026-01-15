<?php
/**
 * API: protocol_process_queue.php - Verarbeitet Queue chronologisch
 * POST: item_id (optional - wenn nicht angegeben: alle Items)
 *
 * Kann aufgerufen werden von:
 * - JavaScript (alle 2 Sekunden von Protokollführung)
 * - Cronjob (regelmäßig für alle Items)
 *
 * Verarbeitet Queue-Einträge chronologisch (Last Write Wins)
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');

header('Content-Type: application/json');

// Session-Daten lesen (falls vorhanden) und sofort schließen
$member_id = isset($_SESSION['member_id']) ? $_SESSION['member_id'] : null;
session_write_close();

// Input validieren
$data = json_decode(file_get_contents('php://input'), true);
$item_id = isset($data['item_id']) ? (int)$data['item_id'] : 0;

try {
    // Prüfen ob Queue-Tabelle existiert
    $tables_check = $pdo->query("SHOW TABLES LIKE 'svprotocol_changes_queue'")->fetchAll();
    if (empty($tables_check)) {
        echo json_encode([
            'success' => false,
            'error' => 'Queue table not found',
            'details' => 'Please run: php run_queue_migration.php'
        ]);
        exit;
    }

    // Unverarbeitete Queue-Einträge holen (chronologisch)
    if ($item_id > 0) {
        // Nur für spezifisches Item
        $stmt = $pdo->prepare("
            SELECT change_id, item_id, member_id, protocol_text, submitted_at
            FROM svprotocol_changes_queue
            WHERE item_id = ? AND processed = 0
            ORDER BY submitted_at ASC, change_id ASC
        ");
        $stmt->execute([$item_id]);
    } else {
        // Für alle Items
        $stmt = $pdo->query("
            SELECT change_id, item_id, member_id, protocol_text, submitted_at
            FROM svprotocol_changes_queue
            WHERE processed = 0
            ORDER BY submitted_at ASC, change_id ASC
        ");
    }

    $queue_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($queue_entries)) {
        echo json_encode([
            'success' => true,
            'processed' => 0,
            'message' => 'Queue is empty'
        ]);
        exit;
    }

    // Beginne Transaktion für atomare Verarbeitung
    $pdo->beginTransaction();

    $processed_count = 0;
    $processed_items = [];

    // Gruppiere nach item_id für effiziente Verarbeitung
    $items_queue = [];
    foreach ($queue_entries as $entry) {
        $items_queue[$entry['item_id']][] = $entry;
    }

    foreach ($items_queue as $current_item_id => $entries) {
        // Letzter Eintrag gewinnt (chronologisch)
        $last_entry = end($entries);

        // Protokolltext aktualisieren
        $stmt = $pdo->prepare("
            UPDATE svagenda_items
            SET protocol_notes = ?, protocol_master_id = ?
            WHERE item_id = ?
        ");
        $stmt->execute([
            $last_entry['protocol_text'],
            $last_entry['member_id'],
            $current_item_id
        ]);

        // Alle Einträge für dieses Item als verarbeitet markieren
        $change_ids = array_column($entries, 'change_id');
        $placeholders = implode(',', array_fill(0, count($change_ids), '?'));

        $stmt = $pdo->prepare("
            UPDATE svprotocol_changes_queue
            SET processed = 1, processed_at = NOW()
            WHERE change_id IN ($placeholders)
        ");
        $stmt->execute($change_ids);

        // Version für History speichern (falls Tabelle existiert)
        $new_hash = md5($last_entry['protocol_text']);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO svprotocol_versions
                (item_id, protocol_text, modified_by, version_hash)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $current_item_id,
                $last_entry['protocol_text'],
                $last_entry['member_id'],
                $new_hash
            ]);
        } catch (PDOException $e) {
            // Tabelle existiert noch nicht - ignorieren
            error_log("svprotocol_versions insert failed: " . $e->getMessage());
        }

        $processed_count += count($entries);
        $processed_items[] = [
            'item_id' => $current_item_id,
            'entries_processed' => count($entries),
            'final_hash' => $new_hash
        ];
    }

    // Commit Transaktion
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'processed' => $processed_count,
        'items' => $processed_items,
        'message' => "$processed_count Einträge verarbeitet",
        'processed_at' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    // Rollback bei Fehler
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("protocol_process_queue Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
