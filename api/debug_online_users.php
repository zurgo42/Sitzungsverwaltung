<?php
/**
 * Debug: Warum zeigt getOnlineParticipants keine User?
 */
session_start();
require_once('../config.php');
require_once('db_connection.php');

header('Content-Type: application/json');

$text_id = 1; // Erster Text

// 1. Aktuelle Server-Zeit
$stmt = $pdo->query("SELECT NOW() as server_now");
$server_now = $stmt->fetch()['server_now'];

// 2. Alle Participants mit last_seen
$stmt = $pdo->prepare("
    SELECT p.member_id, p.last_seen, p.text_id,
           m.first_name, m.last_name,
           TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) as seconds_ago
    FROM svcollab_text_participants p
    JOIN svmembers m ON p.member_id = m.member_id
    WHERE p.text_id = ?
    ORDER BY p.last_seen DESC
");
$stmt->execute([$text_id]);
$all_participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Query wie getOnlineParticipants es macht
$stmt = $pdo->prepare("
    SELECT p.member_id, p.last_seen,
           m.first_name, m.last_name,
           TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) as seconds_ago,
           (p.last_seen > DATE_SUB(NOW(), INTERVAL 30 SECOND)) as should_be_online
    FROM svcollab_text_participants p
    JOIN svmembers m ON p.member_id = m.member_id
    WHERE p.text_id = ?
    ORDER BY p.last_seen DESC
");
$stmt->execute([$text_id]);
$query_test = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Nur die "online" laut Query
$stmt = $pdo->prepare("
    SELECT p.member_id, p.last_seen,
           m.first_name, m.last_name
    FROM svcollab_text_participants p
    JOIN svmembers m ON p.member_id = m.member_id
    WHERE p.text_id = ?
      AND p.last_seen > DATE_SUB(NOW(), INTERVAL 30 SECOND)
");
$stmt->execute([$text_id]);
$online_by_query = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'server_now' => $server_now,
    'text_id' => $text_id,
    'all_participants' => $all_participants,
    'query_test_with_should_be_online' => $query_test,
    'online_by_30sec_query' => $online_by_query,
    'diagnosis' => [
        'total_participants' => count($all_participants),
        'participants_passing_30sec_filter' => count($online_by_query),
        'issue' => count($online_by_query) === 0 ? 'KEINE User als online erkannt trotz Heartbeat!' : 'OK'
    ]
], JSON_PRETTY_PRINT);
