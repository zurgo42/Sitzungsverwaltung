<?php
/**
 * Debug: Nachträgliche Kommentare prüfen
 */

require_once 'config.php';
require_once 'config_adapter.php';
require_once 'member_functions.php';

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

// 0. Konfiguration und VIEW prüfen
echo "<h2>0. Konfiguration</h2>";
echo "<p><strong>MEMBER_SOURCE:</strong> " . (defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'nicht definiert') . "</p>";

// VIEW svmembers prüfen
echo "<h3>VIEW svmembers prüfen</h3>";
try {
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $views = $stmt->fetchAll();

    $has_svmembers_view = false;
    foreach ($views as $view) {
        if ($view['Tables_in_' . DB_NAME] === 'svmembers') {
            $has_svmembers_view = true;
            break;
        }
    }

    if ($has_svmembers_view) {
        echo "<p style='color: green;'>✅ VIEW svmembers existiert</p>";

        // Testen ob VIEW funktioniert
        $stmt = $pdo->query("SELECT member_id, first_name, last_name FROM svmembers LIMIT 5");
        $view_data = $stmt->fetchAll();

        if (!empty($view_data)) {
            echo "<p style='color: green;'>✅ VIEW svmembers liefert Daten (" . count($view_data) . " Einträge)</p>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>member_id</th><th>first_name</th><th>last_name</th></tr>";
            foreach ($view_data as $row) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['member_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ VIEW svmembers ist leer</p>";
        }

    } else {
        echo "<p style='color: red;'>❌ VIEW svmembers existiert NICHT!</p>";
        echo "<p><strong>LÖSUNG:</strong> ensure_svmembers_view(\$pdo) muss aufgerufen werden!</p>";

        // Versuchen, VIEW zu erstellen
        echo "<p>Versuche VIEW zu erstellen...</p>";
        if (ensure_svmembers_view($pdo)) {
            echo "<p style='color: green;'>✅ VIEW erfolgreich erstellt! Lade Seite neu.</p>";
        } else {
            echo "<p style='color: red;'>❌ VIEW konnte nicht erstellt werden. Prüfe Error-Log.</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Fehler: " . $e->getMessage() . "</p>";
}

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

    // Alternative Tabellen prüfen
    echo "<h3>Alternative: Tabelle 'berechtigte' prüfen</h3>";
    try {
        $stmt = $pdo->query("SELECT MNr, Vorname, Nachname FROM berechtigte LIMIT 10");
        $berechtigte = $stmt->fetchAll();

        if (!empty($berechtigte)) {
            echo "<p style='color: green;'>✅ " . count($berechtigte) . " Einträge in 'berechtigte' gefunden</p>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>MNr</th><th>Vorname</th><th>Nachname</th></tr>";
            foreach ($berechtigte as $b) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($b['MNr']) . "</td>";
                echo "<td>" . htmlspecialchars($b['Vorname']) . "</td>";
                echo "<td>" . htmlspecialchars($b['Nachname']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠️ Tabelle 'berechtigte' nicht gefunden: " . $e->getMessage() . "</p>";
    }

    echo "<h3>Alternative: Tabelle 'members' prüfen</h3>";
    try {
        $stmt = $pdo->query("SELECT member_id, first_name, last_name FROM members LIMIT 10");
        $plain_members = $stmt->fetchAll();

        if (!empty($plain_members)) {
            echo "<p style='color: green;'>✅ " . count($plain_members) . " Einträge in 'members' gefunden</p>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>member_id</th><th>first_name</th><th>last_name</th></tr>";
            foreach ($plain_members as $pm) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($pm['member_id']) . "</td>";
                echo "<td>" . htmlspecialchars($pm['first_name']) . "</td>";
                echo "<td>" . htmlspecialchars($pm['last_name']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠️ Tabelle 'members' nicht gefunden: " . $e->getMessage() . "</p>";
    }
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

    echo "<h2>3. Query mit Adapter-Funktionen (item_id = $test_item_id)</h2>";

    echo "<h3>A) Kommentare über Adapter laden</h3>";
    $stmt = $pdo->prepare("
        SELECT apc.*
        FROM svagenda_post_comments apc
        WHERE apc.item_id = ?
        ORDER BY apc.created_at ASC
    ");
    $stmt->execute([$test_item_id]);
    $joined_comments = $stmt->fetchAll();

    // Member-Namen über Adapter hinzufügen
    foreach ($joined_comments as &$comment) {
        $member = get_member_by_id($pdo, $comment['member_id']);
        $comment['first_name'] = $member ? $member['first_name'] : 'Unbekannt';
        $comment['last_name'] = $member ? $member['last_name'] : '';
    }
    unset($comment);

    if (empty($joined_comments)) {
        echo "<p style='color: red;'>❌ Keine Kommentare gefunden!</p>";

        // Prüfen welche member_ids in post_comments existieren
        $stmt = $pdo->prepare("SELECT DISTINCT member_id FROM svagenda_post_comments WHERE item_id = ?");
        $stmt->execute([$test_item_id]);
        $comment_member_ids = $stmt->fetchAll();

        echo "<p>Member IDs in svagenda_post_comments (item_id=$test_item_id): ";
        echo implode(', ', array_column($comment_member_ids, 'member_id'));
        echo "</p>";

        // Test mit berechtigte Tabelle
        echo "<h3>B) JOIN mit berechtigte (MNr)</h3>";
        try {
            $stmt = $pdo->prepare("
                SELECT apc.*, b.Vorname, b.Nachname
                FROM svagenda_post_comments apc
                JOIN berechtigte b ON apc.member_id = b.MNr
                WHERE apc.item_id = ?
                ORDER BY apc.created_at ASC
            ");
            $stmt->execute([$test_item_id]);
            $joined_berechtigte = $stmt->fetchAll();

            if (!empty($joined_berechtigte)) {
                echo "<p style='color: green;'>✅ " . count($joined_berechtigte) . " Kommentare mit JOIN auf berechtigte geladen!</p>";
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                echo "<tr><th>comment_id</th><th>MNr</th><th>Vorname</th><th>Nachname</th><th>comment_text</th></tr>";
                foreach ($joined_berechtigte as $c) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($c['comment_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($c['member_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($c['Vorname']) . "</td>";
                    echo "<td>" . htmlspecialchars($c['Nachname']) . "</td>";
                    echo "<td>" . htmlspecialchars($c['comment_text']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "<p style='background: #ffffcc; padding: 10px; border: 2px solid #ff9800;'><strong>💡 LÖSUNG:</strong> Die Kommentare müssen mit der 'berechtigte' Tabelle gejoined werden, nicht mit 'svmembers'!</p>";
            } else {
                echo "<p style='color: red;'>❌ Auch JOIN mit berechtigte liefert keine Ergebnisse</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ JOIN mit berechtigte fehlgeschlagen: " . $e->getMessage() . "</p>";
        }

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
    ORDER BY ai.item_id DESC
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
