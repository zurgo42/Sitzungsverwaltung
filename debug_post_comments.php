<?php
/**
 * Debug: Nachträgliche Kommentare prüfen
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
}

echo "<h1>🔍 Debug: Nachträgliche Kommentare</h1>";

// 1. Tabelle svagenda_post_comments prüfen
echo "<h2>1. Tabelle svagenda_post_comments</h2>";
$stmt = $pdo->query("SELECT * FROM svagenda_post_comments ORDER BY created_at DESC LIMIT 20");
$comments = $stmt->fetchAll();

if (empty($comments)) {
    echo "<p style='color: red;'>❌ Keine Einträge in svagenda_post_comments gefunden!</p>";
} else {
    echo "<p style='color: green;'>✅ " . count($comments) . " Einträge gefunden</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>comment_id</th><th>item_id</th><th>member_id</th><th>comment_text</th><th>created_at</th></tr>";
    foreach ($comments as $c) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($c['comment_id']) . "</td>";
        echo "<td>" . htmlspecialchars($c['item_id']) . "</td>";
        echo "<td>" . htmlspecialchars($c['member_id']) . "</td>";
        echo "<td>" . htmlspecialchars($c['comment_text']) . "</td>";
        echo "<td>" . htmlspecialchars($c['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. Tabelle svmembers prüfen
echo "<h2>2. Tabelle svmembers</h2>";
$stmt = $pdo->query("SELECT member_id, first_name, last_name FROM svmembers LIMIT 10");
$members = $stmt->fetchAll();

if (empty($members)) {
    echo "<p style='color: red;'>❌ Keine Einträge in svmembers gefunden!</p>";
} else {
    echo "<p style='color: green;'>✅ " . count($members) . " Einträge gefunden</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>member_id</th><th>first_name</th><th>last_name</th></tr>";
    foreach ($members as $m) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($m['member_id']) . "</td>";
        echo "<td>" . htmlspecialchars($m['first_name']) . "</td>";
        echo "<td>" . htmlspecialchars($m['last_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. JOIN-Query testen
if (!empty($comments)) {
    $test_item_id = $comments[0]['item_id'];

    echo "<h2>3. JOIN-Query Test (item_id = $test_item_id)</h2>";

    $stmt = $pdo->prepare("
        SELECT apc.*, m.first_name, m.last_name
        FROM svagenda_post_comments apc
        JOIN svmembers m ON apc.member_id = m.member_id
        WHERE apc.item_id = ?
        ORDER BY apc.created_at ASC
    ");
    $stmt->execute([$test_item_id]);
    $joined_comments = $stmt->fetchAll();

    if (empty($joined_comments)) {
        echo "<p style='color: red;'>❌ JOIN liefert keine Ergebnisse!</p>";
        echo "<p><strong>Problem:</strong> member_id in svagenda_post_comments stimmt nicht mit member_id in svmembers überein</p>";

        // Prüfen welche member_ids in post_comments existieren
        $stmt = $pdo->prepare("SELECT DISTINCT member_id FROM svagenda_post_comments WHERE item_id = ?");
        $stmt->execute([$test_item_id]);
        $comment_member_ids = $stmt->fetchAll();

        echo "<p>Member IDs in svagenda_post_comments (item_id=$test_item_id): ";
        echo implode(', ', array_column($comment_member_ids, 'member_id'));
        echo "</p>";

        // Prüfen welche member_ids in svmembers existieren
        $stmt = $pdo->query("SELECT member_id FROM svmembers");
        $member_ids = $stmt->fetchAll();

        echo "<p>Member IDs in svmembers: ";
        echo implode(', ', array_column($member_ids, 'member_id'));
        echo "</p>";

    } else {
        echo "<p style='color: green;'>✅ " . count($joined_comments) . " Kommentare mit JOIN geladen</p>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>comment_id</th><th>first_name</th><th>last_name</th><th>comment_text</th><th>created_at</th></tr>";
        foreach ($joined_comments as $c) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($c['comment_id']) . "</td>";
            echo "<td>" . htmlspecialchars($c['first_name']) . "</td>";
            echo "<td>" . htmlspecialchars($c['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($c['comment_text']) . "</td>";
            echo "<td>" . htmlspecialchars($c['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// 4. Agenda Items prüfen
echo "<h2>4. Aktuelle Agenda Items</h2>";
$stmt = $pdo->query("
    SELECT ai.item_id, ai.title, ai.top_number, m.meeting_name, m.status
    FROM svagenda_items ai
    JOIN svmeetings m ON ai.meeting_id = m.meeting_id
    ORDER BY m.start_date DESC, ai.top_number ASC
    LIMIT 20
");
$items = $stmt->fetchAll();

if (empty($items)) {
    echo "<p style='color: red;'>❌ Keine Agenda Items gefunden!</p>";
} else {
    echo "<p style='color: green;'>✅ " . count($items) . " Agenda Items gefunden</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>item_id</th><th>Meeting</th><th>Status</th><th>TOP</th><th>Titel</th></tr>";
    foreach ($items as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['item_id']) . "</td>";
        echo "<td>" . htmlspecialchars($item['meeting_name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['status']) . "</td>";
        echo "<td>" . htmlspecialchars($item['top_number']) . "</td>";
        echo "<td>" . htmlspecialchars($item['title']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
