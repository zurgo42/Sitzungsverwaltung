<?php
/**
 * API: Lock für Mitschrift-Feld anfordern
 * Nur ein User kann gleichzeitig schreiben
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');

header('Content-Type: application/json');

// Authentifizierung prüfen
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Session-Daten gelesen → Session sofort schließen
$member_id = $_SESSION['member_id'];
session_write_close();

// Input validieren
$input = json_decode(file_get_contents('php://input'), true);
$item_id = isset($input['item_id']) ? intval($input['item_id']) : 0;

if (!$item_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing item_id']);
    exit;
}

try {
    // Timeout: 30 Sekunden
    $lock_timeout = 30;

    // Alte Locks aufräumen (älter als 30 Sekunden)
    $pdo->exec("
        DELETE FROM svprotocol_lock
        WHERE TIMESTAMPDIFF(SECOND, locked_at, NOW()) > $lock_timeout
    ");

    // Prüfen ob Lock existiert
    $stmt = $pdo->prepare("
        SELECT l.member_id, l.locked_at,
               m.first_name, m.last_name,
               TIMESTAMPDIFF(SECOND, l.locked_at, NOW()) as lock_age
        FROM svprotocol_lock l
        LEFT JOIN svmembers m ON l.member_id = m.member_id
        WHERE l.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $existing_lock = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_lock) {
        // Lock existiert
        if ($existing_lock['member_id'] == $member_id) {
            // Eigener Lock → refreshen
            $stmt = $pdo->prepare("
                UPDATE svprotocol_lock
                SET locked_at = NOW()
                WHERE item_id = ?
            ");
            $stmt->execute([$item_id]);

            echo json_encode([
                'success' => true,
                'locked' => true,
                'own_lock' => true,
                'message' => 'Lock refreshed'
            ]);
        } else {
            // Jemand anderes hat Lock
            $name = trim(($existing_lock['first_name'] ?? '') . ' ' . ($existing_lock['last_name'] ?? ''));
            if (empty($name)) {
                $name = "User #" . $existing_lock['member_id'];
            }

            echo json_encode([
                'success' => true,
                'locked' => true,
                'own_lock' => false,
                'locked_by_id' => $existing_lock['member_id'],
                'locked_by_name' => $name,
                'lock_age' => $existing_lock['lock_age']
            ]);
        }
    } else {
        // Kein Lock → erstellen
        $stmt = $pdo->prepare("
            INSERT INTO svprotocol_lock (item_id, member_id, locked_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$item_id, $member_id]);

        echo json_encode([
            'success' => true,
            'locked' => true,
            'own_lock' => true,
            'message' => 'Lock acquired'
        ]);
    }

} catch (PDOException $e) {
    error_log("Lock acquire error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
