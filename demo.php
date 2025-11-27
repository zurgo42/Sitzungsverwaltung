<?php
/**
 * demo.php - Demo-Daten Generator
 *
 * Erstellt automatisch ein vollständiges Demo-Szenario mit:
 * - 8 Test-Mitgliedern
 * - 3 Meetings (preparation, active, ended)
 * - Tagesordnungspunkten & Kommentaren
 * - TODOs
 * - Terminabstimmungen mit Antworten
 * - Meinungsbildern mit Antworten
 */

require_once 'config.php';

// Nur im Demo-Modus erlaubt
if (!defined('DEMO_MODE_ENABLED') || !DEMO_MODE_ENABLED) {
    die('<h1>Fehler</h1><p>Demo-Modus ist nicht aktiviert. Setzen Sie <code>DEMO_MODE_ENABLED = true</code> in config.php</p>');
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "<h1>Demo-Daten Generator</h1>";
    echo "<p>Erstelle vollständiges Demo-Szenario...</p>";

    // =========================================================
    // 1. DEMO-MITGLIEDER
    // =========================================================

    echo "<h2>1. Erstelle Mitglieder...</h2>";

    $password_hash = password_hash('demo123', PASSWORD_DEFAULT);

    $demo_members = [
        ['Max', 'Mustermann', 'vorstand', 'max.mustermann@example.com', 1, '0001'],
        ['Erika', 'Musterfrau', 'vorstand', 'erika.musterfrau@example.com', 1, '0002'],
        ['Hans', 'Schmidt', 'gf', 'hans.schmidt@example.com', 1, '0003'],
        ['Anna', 'Weber', 'assistenz', 'anna.weber@example.com', 1, '0004'],
        ['Peter', 'Müller', 'fuehrungsteam', 'peter.mueller@example.com', 0, '0005'],
        ['Maria', 'Fischer', 'fuehrungsteam', 'maria.fischer@example.com', 0, '0006'],
        ['Thomas', 'Wagner', 'Mitglied', 'thomas.wagner@example.com', 0, '0007'],
        ['Julia', 'Becker', 'Mitglied', 'julia.becker@example.com', 0, '0008'],
    ];

    $member_ids = [];
    $stmt = $pdo->prepare("
        INSERT INTO svmembers (first_name, last_name, role, email, password_hash, is_admin, is_active, membership_number)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)
    ");

    foreach ($demo_members as $member) {
        $stmt->execute([
            $member[0], // first_name
            $member[1], // last_name
            $member[2], // role
            $member[3], // email
            $password_hash,
            $member[4], // is_admin
            $member[5]  // membership_number
        ]);
        $member_ids[] = $pdo->lastInsertId();
        echo "✓ {$member[0]} {$member[1]} ({$member[2]})<br>";
    }

    // =========================================================
    // 2. DEMO-MEETINGS
    // =========================================================

    echo "<h2>2. Erstelle Meetings...</h2>";

    $meetings_data = [
        [
            'name' => 'Quartalsplanung Q4/2025',
            'date' => date('Y-m-d H:i:s', strtotime('+7 days 10:00')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days 12:00')),
            'location' => 'Konferenzraum A',
            'status' => 'preparation',
            'participants' => [$member_ids[0], $member_ids[1], $member_ids[2], $member_ids[3], $member_ids[4]]
        ],
        [
            'name' => 'Vorstandssitzung November',
            'date' => date('Y-m-d H:i:s', strtotime('-3 days 14:00')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-3 days 17:00')),
            'location' => 'Online (Teams)',
            'status' => 'ended',
            'participants' => [$member_ids[0], $member_ids[1], $member_ids[2]]
        ],
        [
            'name' => 'Projektbesprechung Website-Relaunch',
            'date' => date('Y-m-d H:i:s', strtotime('+2 days 15:00')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+2 days 16:30')),
            'location' => 'Konferenzraum B',
            'status' => 'preparation',
            'participants' => [$member_ids[2], $member_ids[3], $member_ids[4], $member_ids[5]]
        ],
    ];

    $meeting_ids = [];
    foreach ($meetings_data as $idx => $meeting) {
        $stmt = $pdo->prepare("
            INSERT INTO svmeetings (meeting_name, meeting_date, expected_end_date, location, invited_by_member_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $meeting['name'],
            $meeting['date'],
            $meeting['end_date'],
            $meeting['location'],
            $member_ids[0], // Ersteller
            $meeting['status']
        ]);
        $meeting_id = $pdo->lastInsertId();
        $meeting_ids[] = $meeting_id;

        // Teilnehmer hinzufügen
        $stmt = $pdo->prepare("
            INSERT INTO svmeeting_participants (meeting_id, member_id, status)
            VALUES (?, ?, 'invited')
        ");
        foreach ($meeting['participants'] as $participant_id) {
            $stmt->execute([$meeting_id, $participant_id]);
        }

        echo "✓ {$meeting['name']} ({$meeting['status']})<br>";
    }

    // =========================================================
    // 3. TAGESORDNUNGSPUNKTE & KOMMENTARE
    // =========================================================

    echo "<h2>3. Erstelle Tagesordnungspunkte...</h2>";

    // Für Meeting 1
    $tops_meeting1 = [
        ['Begrüßung und Genehmigung der Tagesordnung', 'information', null],
        ['Quartalszahlen Q3/2025', 'bericht', 'Präsentation der Umsatzzahlen und KPIs'],
        ['Budget-Planung Q4', 'diskussion', 'Diskussion über Budgetverteilung'],
        ['Antrag: Neue Marketing-Kampagne', 'antrag_beschluss', 'Antrag auf Budget von 50.000€'],
    ];

    foreach ($tops_meeting1 as $idx => $top) {
        $stmt = $pdo->prepare("
            INSERT INTO svagenda_items (meeting_id, top_number, title, category, description, created_by_member_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $meeting_ids[0],
            $idx + 1,
            $top[0],
            $top[1],
            $top[2],
            $member_ids[0]
        ]);
        echo "✓ TOP {$idx+1}: {$top[0]}<br>";
    }

    // =========================================================
    // 4. TODOS
    // =========================================================

    echo "<h2>4. Erstelle TODOs...</h2>";

    $todos = [
        ['Präsentation Q3-Zahlen vorbereiten', $member_ids[2], date('Y-m-d', strtotime('+5 days'))],
        ['Budget-Vorschlag ausarbeiten', $member_ids[1], date('Y-m-d', strtotime('+6 days'))],
        ['Marketing-Konzept erstellen', $member_ids[4], date('Y-m-d', strtotime('+14 days'))],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO svtodos (title, assigned_to_member_id, created_by_member_id, due_date, status, entry_date)
        VALUES (?, ?, ?, ?, 'open', CURDATE())
    ");

    foreach ($todos as $todo) {
        $stmt->execute([
            $todo[0],
            $todo[1],
            $member_ids[0],
            $todo[2]
        ]);
        echo "✓ {$todo[0]}<br>";
    }

    // =========================================================
    // 5. TERMINABSTIMMUNG (POLL)
    // =========================================================

    echo "<h2>5. Erstelle Terminabstimmung...</h2>";

    $stmt = $pdo->prepare("
        INSERT INTO svpolls (title, description, location, created_by_member_id, status)
        VALUES (?, ?, ?, ?, 'open')
    ");
    $stmt->execute([
        'Termin für Jahreshauptversammlung 2026',
        'Bitte wählen Sie einen passenden Termin',
        'Stadthalle',
        $member_ids[0]
    ]);
    $poll_id = $pdo->lastInsertId();
    echo "✓ Terminabstimmung erstellt<br>";

    // Terminvorschläge
    $poll_dates_data = [
        [date('Y-m-d 10:00:00', strtotime('+30 days')), date('Y-m-d 18:00:00', strtotime('+30 days'))],
        [date('Y-m-d 10:00:00', strtotime('+37 days')), date('Y-m-d 18:00:00', strtotime('+37 days'))],
        [date('Y-m-d 14:00:00', strtotime('+44 days')), date('Y-m-d 20:00:00', strtotime('+44 days'))],
    ];

    $poll_date_ids = [];
    $stmt = $pdo->prepare("
        INSERT INTO svpoll_dates (poll_id, suggested_date, suggested_end_date, sort_order)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($poll_dates_data as $idx => $dates) {
        $stmt->execute([$poll_id, $dates[0], $dates[1], $idx]);
        $poll_date_ids[] = $pdo->lastInsertId();
        echo "✓ Terminvorschlag " . ($idx+1) . "<br>";
    }

    // Teilnehmer & Antworten
    $stmt_participant = $pdo->prepare("INSERT INTO svpoll_participants (poll_id, member_id) VALUES (?, ?)");
    $stmt_response = $pdo->prepare("INSERT INTO svpoll_responses (poll_id, date_id, member_id, vote) VALUES (?, ?, ?, ?)");

    foreach ($member_ids as $idx => $member_id) {
        if ($idx < 5) { // Erste 5 Mitglieder
            $stmt_participant->execute([$poll_id, $member_id]);
            // Zufällige Antworten
            foreach ($poll_date_ids as $date_id) {
                $vote = [1, 0, -1][rand(0, 2)]; // Ja=1, Vielleicht=0, Nein=-1
                $stmt_response->execute([$poll_id, $date_id, $member_id, $vote]);
            }
        }
    }

    // =========================================================
    // 6. MEINUNGSBILDER
    // =========================================================

    echo "<h2>6. Erstelle Meinungsbilder...</h2>";

    $opinion_polls_data = [
        [
            'title' => 'Wie gefällt Ihnen das neue Bürokonzept?',
            'description' => 'Bitte bewerten Sie unser neues Open-Space-Konzept',
            'target_type' => 'public',
            'options' => ['gefällt mir sehr gut', 'gefällt mir gut', 'neutral', 'gefällt mir nicht', 'gefällt mir überhaupt nicht']
        ],
        [
            'title' => 'Soll die Weihnachtsfeier hybrid stattfinden?',
            'description' => null,
            'target_type' => 'list',
            'options' => ['Ja', 'Nein', 'Enthaltung']
        ],
    ];

    foreach ($opinion_polls_data as $opinion) {
        $access_token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("
            INSERT INTO svopinion_polls (title, description, creator_member_id, target_type, access_token, allow_multiple_answers, is_anonymous, duration_days, status)
            VALUES (?, ?, ?, ?, ?, 0, 0, 14, 'active')
        ");
        $stmt->execute([
            $opinion['title'],
            $opinion['description'],
            $member_ids[0],
            $opinion['target_type'],
            $access_token
        ]);
        $opinion_poll_id = $pdo->lastInsertId();

        // Optionen hinzufügen
        $stmt_option = $pdo->prepare("
            INSERT INTO svopinion_poll_options (poll_id, option_text, sort_order)
            VALUES (?, ?, ?)
        ");
        foreach ($opinion['options'] as $idx => $option_text) {
            $stmt_option->execute([$opinion_poll_id, $option_text, $idx]);
        }

        echo "✓ {$opinion['title']}<br>";
    }

    echo "<h2>✅ Demo-Daten erfolgreich erstellt!</h2>";
    echo "<p><strong>Zugangsdaten:</strong></p>";
    echo "<ul>";
    echo "<li>Vorstand: max.mustermann@example.com / demo123</li>";
    echo "<li>GF: hans.schmidt@example.com / demo123</li>";
    echo "<li>Weitere Accounts: demo123</li>";
    echo "</ul>";

    echo "<p><a href='tools/demo_export.php' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>→ Jetzt exportieren</a></p>";
    echo "<p><a href='index.php' style='padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>→ Zur Anwendung</a></p>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Fehler: " . htmlspecialchars($e->getMessage()) . "</h2>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
