<?php
/**
 * Umfassender Diagnose-Test für Kollaborative Texte
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');
require_once('../functions_collab_text.php');

header('Content-Type: application/json');

$results = [];
$start_total = microtime(true);

// Test 1: Gibt es überhaupt Texte?
$t1 = microtime(true);
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM svcollab_texts");
$text_count = $stmt->fetch()['cnt'];
$results['texts_exist'] = [
    'count' => $text_count,
    'time_ms' => round((microtime(true) - $t1) * 1000, 2)
];

if ($text_count > 0) {
    // Test 2: Ersten Text laden
    $t2 = microtime(true);
    $stmt = $pdo->query("SELECT text_id FROM svcollab_texts LIMIT 1");
    $text_id = $stmt->fetch()['text_id'];
    $results['first_text_id'] = $text_id;
    $results['query_first_text_ms'] = round((microtime(true) - $t2) * 1000, 2);

    // Test 3: getCollabText Performance
    $t3 = microtime(true);
    $text = getCollabText($pdo, $text_id);
    $results['getCollabText'] = [
        'time_ms' => round((microtime(true) - $t3) * 1000, 2),
        'has_paragraphs' => !empty($text['paragraphs']),
        'paragraph_count' => count($text['paragraphs'] ?? [])
    ];

    // Test 4: Participants vorhanden?
    $t4 = microtime(true);
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM svcollab_text_participants WHERE text_id = ?");
    $stmt->execute([$text_id]);
    $participant_count = $stmt->fetch()['cnt'];
    $results['participants'] = [
        'count' => $participant_count,
        'time_ms' => round((microtime(true) - $t4) * 1000, 2)
    ];

    // Test 5: Online Participants (wie im API verwendet)
    $t5 = microtime(true);
    $online = getOnlineParticipants($pdo, $text_id);
    $results['online_participants'] = [
        'count' => count($online),
        'users' => $online,
        'time_ms' => round((microtime(true) - $t5) * 1000, 2)
    ];

    // Test 6: Locks vorhanden?
    $t6 = microtime(true);
    $stmt = $pdo->prepare("
        SELECT l.*, m.first_name, m.last_name
        FROM svcollab_text_locks l
        LEFT JOIN svmembers m ON l.member_id = m.member_id
        WHERE l.paragraph_id IN (
            SELECT paragraph_id FROM svcollab_text_paragraphs WHERE text_id = ?
        )
    ");
    $stmt->execute([$text_id]);
    $locks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['locks'] = [
        'count' => count($locks),
        'locks' => $locks,
        'time_ms' => round((microtime(true) - $t6) * 1000, 2)
    ];

    // Test 7: Heartbeat API simulieren
    if (isset($_SESSION['member_id'])) {
        $t7 = microtime(true);
        $member_id = $_SESSION['member_id'];

        // Update last_seen
        $stmt = $pdo->prepare("
            UPDATE svcollab_text_participants
            SET last_seen = NOW()
            WHERE text_id = ? AND member_id = ?
        ");
        $stmt->execute([$text_id, $member_id]);
        $affected = $stmt->rowCount();

        $results['heartbeat_simulation'] = [
            'member_id' => $member_id,
            'rows_affected' => $affected,
            'time_ms' => round((microtime(true) - $t7) * 1000, 2)
        ];
    }

    // Test 8: Get Updates API simulieren
    $t8 = microtime(true);
    $since = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $stmt = $pdo->prepare("
        SELECT p.paragraph_id, p.content,
               l.member_id as locked_by_member_id,
               lm.first_name as locked_by_first_name,
               lm.last_name as locked_by_last_name
        FROM svcollab_text_paragraphs p
        LEFT JOIN svcollab_text_locks l ON p.paragraph_id = l.paragraph_id
        LEFT JOIN svmembers lm ON l.member_id = lm.member_id
        WHERE p.text_id = ?
    ");
    $stmt->execute([$text_id]);
    $paragraphs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['get_updates_simulation'] = [
        'paragraphs' => $paragraphs,
        'time_ms' => round((microtime(true) - $t8) * 1000, 2)
    ];
}

// Test 9: Session Performance
$t9 = microtime(true);
$_SESSION['test_timestamp'] = time();
$session_write_time = round((microtime(true) - $t9) * 1000, 2);

$t10 = microtime(true);
session_write_close();
$session_close_time = round((microtime(true) - $t10) * 1000, 2);

$results['session_performance'] = [
    'write_ms' => $session_write_time,
    'close_ms' => $session_close_time
];

$results['total_time_ms'] = round((microtime(true) - $start_total) * 1000, 2);
$results['current_user'] = $_SESSION['member_id'] ?? 'not logged in';

echo json_encode($results, JSON_PRETTY_PRINT);
