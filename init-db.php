<?php
/**
 * init-db.php - Datenbank initialisieren
 * Aktualisiert: 17.11.2025 - Terminplanung + visibility_type + is_active
 * Vollständige Datenbankstruktur für Portabilität
 */

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Datenbank erstellen falls nicht vorhanden
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    echo "<h2>Datenbank-Initialisierung</h2>";
    echo "<p>Erstelle Tabellen...</p>";
    
    $tables = [];
    
    // Mitglieder-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS members (
        member_id INT PRIMARY KEY AUTO_INCREMENT,
        membership_number VARCHAR(50) DEFAULT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        role ENUM('vorstand', 'gf', 'assistenz', 'fuehrungsteam', 'Mitglied') NOT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        is_confidential TINYINT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY membership_number (membership_number),
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Meetings-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS meetings (
        meeting_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_name VARCHAR(255) DEFAULT NULL,
        meeting_date DATETIME NOT NULL,
        expected_end_date DATETIME DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        video_link VARCHAR(500) DEFAULT NULL,
        invited_by_member_id INT NOT NULL,
        chairman_member_id INT DEFAULT NULL,
        chairman_name VARCHAR(255) DEFAULT NULL,
        secretary_member_id INT DEFAULT NULL,
        secretary_name VARCHAR(255) DEFAULT NULL,
        started_at DATETIME DEFAULT NULL,
        ended_at DATETIME DEFAULT NULL,
        status ENUM('preparation', 'active', 'ended', 'protocol_ready', 'archived') DEFAULT 'preparation',
        visibility_type ENUM('public', 'authenticated', 'invited_only') DEFAULT 'invited_only',
        protokoll TEXT DEFAULT NULL,
        prot_intern TEXT DEFAULT NULL,
        protocol_intern TEXT NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invited_by_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        FOREIGN KEY (chairman_member_id) REFERENCES members(member_id) ON DELETE SET NULL,
        FOREIGN KEY (secretary_member_id) REFERENCES members(member_id) ON DELETE SET NULL,
        INDEX idx_meeting_date (meeting_date),
        INDEX idx_status (status),
        INDEX idx_visibility (visibility_type),
        INDEX idx_protokoll (protokoll(768))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Meeting-Teilnehmer-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS meeting_participants (
        participant_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT NOT NULL,
        member_id INT NOT NULL,
        status ENUM('invited', 'confirmed', 'present', 'absent') DEFAULT 'invited',
        attendance_status ENUM('present', 'partial', 'absent') DEFAULT 'absent',
        invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_participant (meeting_id, member_id),
        INDEX idx_meeting (meeting_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Tagesordnungspunkte-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS agenda_items (
        item_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT NOT NULL,
        top_number INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        category ENUM('information', 'klaerung', 'diskussion', 'aussprache', 'antrag_beschluss', 'wahl', 'bericht', 'sonstiges') DEFAULT 'information',
        proposal_text TEXT,
        vote_yes INT DEFAULT NULL,
        vote_no INT DEFAULT NULL,
        vote_abstain INT DEFAULT NULL,
        vote_result ENUM('einvernehmlich', 'einstimmig', 'angenommen', 'abgelehnt') DEFAULT NULL,
        priority DECIMAL(3,2) DEFAULT 5.00,
        estimated_duration INT DEFAULT 15,
        protocol_notes TEXT,
        is_confidential TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 0,
        created_by_member_id INT DEFAULT NULL,
        grouped_with_item_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (created_by_member_id) REFERENCES members(member_id) ON DELETE SET NULL,
        FOREIGN KEY (grouped_with_item_id) REFERENCES agenda_items(item_id) ON DELETE SET NULL,
        INDEX idx_meeting (meeting_id),
        INDEX idx_top_number (top_number),
        INDEX idx_is_active (is_active),
        INDEX idx_created_by (created_by_member_id),
        INDEX idx_grouped_with (grouped_with_item_id),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Kommentare-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS agenda_comments (
        comment_id INT PRIMARY KEY AUTO_INCREMENT,
        item_id INT NOT NULL,
        member_id INT NOT NULL,
        comment_text TEXT,
        priority_rating DECIMAL(3,2) DEFAULT NULL,
        duration_estimate DECIMAL(5,2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES agenda_items(item_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        INDEX idx_item (item_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Protokolle-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS protocols (
        protocol_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT DEFAULT NULL,
        protocol_type ENUM('public', 'confidential') DEFAULT 'public',
        content TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE CASCADE,
        INDEX idx_meeting (meeting_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Protokolländerungs-Anfragen-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS protocol_change_requests (
        request_id INT PRIMARY KEY AUTO_INCREMENT,
        protocol_id INT DEFAULT NULL,
        member_id INT DEFAULT NULL,
        item_id INT DEFAULT NULL,
        change_request TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (protocol_id) REFERENCES protocols(protocol_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES agenda_items(item_id) ON DELETE CASCADE,
        INDEX idx_protocol (protocol_id),
        INDEX idx_member (member_id),
        INDEX idx_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // ToDos-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS todos (
        todo_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT DEFAULT NULL,
        item_id INT DEFAULT NULL,
        assigned_to_member_id INT DEFAULT NULL,
        created_by_member_id INT NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'open',
        is_private TINYINT(1) DEFAULT 0,
        entry_date DATE DEFAULT NULL,
        due_date DATE DEFAULT NULL,
        completed_at DATETIME DEFAULT NULL,
        protocol_link VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES agenda_items(item_id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        INDEX idx_meeting (meeting_id),
        INDEX idx_item (item_id),
        INDEX idx_assigned_to (assigned_to_member_id),
        INDEX idx_due_date (due_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // ToDo-Log-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS todo_log (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        todo_id INT NOT NULL,
        changed_by INT NOT NULL,
        change_type VARCHAR(50) NOT NULL,
        old_value VARCHAR(255) DEFAULT NULL,
        new_value VARCHAR(255) DEFAULT NULL,
        change_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_todo (todo_id),
        INDEX idx_changed_by (changed_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Admin-Log-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS admin_log (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        admin_member_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        action_description TEXT NOT NULL,
        target_type VARCHAR(50) DEFAULT NULL,
        target_id INT DEFAULT NULL,
        old_values JSON DEFAULT NULL,
        new_values JSON DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        INDEX idx_admin (admin_member_id),
        INDEX idx_action_type (action_type),
        INDEX idx_target_type (target_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Umfragen/Terminplanung-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS polls (
        poll_id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        created_by_member_id INT NOT NULL,
        meeting_id INT DEFAULT NULL,
        status ENUM('open', 'closed', 'finalized') DEFAULT 'open',
        final_date_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        finalized_at DATETIME DEFAULT NULL,
        FOREIGN KEY (created_by_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_created_by (created_by_member_id),
        INDEX idx_meeting (meeting_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Terminvorschläge-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS poll_dates (
        date_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        suggested_date DATETIME NOT NULL,
        suggested_end_date DATETIME DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        notes TEXT,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
        INDEX idx_poll (poll_id),
        INDEX idx_suggested_date (suggested_date),
        INDEX idx_sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Teilnehmer-Antworten-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS poll_responses (
        response_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        date_id INT NOT NULL,
        member_id INT DEFAULT NULL,
        participant_name VARCHAR(255) DEFAULT NULL,
        participant_email VARCHAR(255) DEFAULT NULL,
        vote TINYINT NOT NULL DEFAULT 0,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (date_id) REFERENCES poll_dates(date_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_response (poll_id, date_id, member_id, participant_email),
        INDEX idx_poll (poll_id),
        INDEX idx_date (date_id),
        INDEX idx_member (member_id),
        INDEX idx_vote (vote)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Tabellen erstellen
    foreach ($tables as $sql) {
        $pdo->exec($sql);
        echo ".";
    }
    
    echo "<p style='color: green;'>✓ Alle Tabellen erfolgreich erstellt!</p>";
    
    // Test-Daten erstellen (optional)
    echo "<h3>Test-Daten erstellen?</h3>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='create_testdata' value='1'>Test-Daten erstellen</button>";
    echo "</form>";
    
    if (isset($_POST['create_testdata'])) {
        echo "<p>Erstelle Test-Daten...</p>";
        
        // Prüfen ob bereits Mitglieder existieren
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM members");
        $count = $stmt->fetch()['count'];
        
        if ($count == 0) {
            // Test-Mitglieder erstellen
            $password = password_hash('test123', PASSWORD_DEFAULT);
            
            $members = [
                ['V001', 'test@example.com', $password, 'Max', 'Mustermann', 'vorstand', 1, 1],
                ['V002', 'vorstand2@example.com', $password, 'Maria', 'Schmidt', 'vorstand', 1, 1],
                ['GF001', 'gf@example.com', $password, 'Erika', 'Musterfrau', 'gf', 1, 1],
                ['A001', 'assistenz@example.com', $password, 'Anna', 'Schmidt', 'assistenz', 1, 0],
                ['FT001', 'ft1@example.com', $password, 'Peter', 'Meyer', 'fuehrungsteam', 0, 1],
                ['FT002', 'ft2@example.com', $password, 'Lisa', 'Weber', 'fuehrungsteam', 0, 1],
                ['M001', 'mitglied1@example.com', $password, 'Klaus', 'Fischer', 'Mitglied', 0, 0],
                ['M002', 'mitglied2@example.com', $password, 'Sandra', 'Becker', 'Mitglied', 0, 0]
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO members (membership_number, email, password_hash, first_name, last_name, role, is_admin, is_confidential) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($members as $member) {
                $stmt->execute($member);
            }
            
            echo "<p style='color: green;'>✓ Test-Mitglieder erstellt!</p>";
            echo "<p><strong>Login-Daten:</strong></p>";
            echo "<ul>";
            echo "<li>Vorstand: test@example.com / test123</li>";
            echo "<li>Vorstand 2: vorstand2@example.com / test123</li>";
            echo "<li>GF: gf@example.com / test123</li>";
            echo "<li>Assistenz: assistenz@example.com / test123</li>";
            echo "<li>Führungsteam 1: ft1@example.com / test123</li>";
            echo "<li>Führungsteam 2: ft2@example.com / test123</li>";
            echo "<li>Mitglied 1: mitglied1@example.com / test123</li>";
            echo "<li>Mitglied 2: mitglied2@example.com / test123</li>";
            echo "</ul>";
            
            // Test-Meeting erstellen
            $meeting_date = date('Y-m-d H:i:s', strtotime('+7 days 14:00'));
            $expected_end = date('Y-m-d H:i:s', strtotime('+7 days 17:00'));
            $stmt = $pdo->prepare("
                INSERT INTO meetings (meeting_name, meeting_date, expected_end_date, location, video_link, invited_by_member_id, status, protocol_intern) 
                VALUES (?, ?, ?, ?, ?, 1, 'preparation', '')
            ");
            $stmt->execute([
                'Vorstandssitzung Q4/2025', 
                $meeting_date, 
                $expected_end,
                'Konferenzraum A',
                'https://meet.example.com/vorstand-q4-2025'
            ]);
            $meeting_id = $pdo->lastInsertId();
            
            // Teilnehmer einladen
            $stmt = $pdo->prepare("
                INSERT INTO meeting_participants (meeting_id, member_id, status) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$meeting_id, 1, 'confirmed']);
            $stmt->execute([$meeting_id, 2, 'confirmed']);
            $stmt->execute([$meeting_id, 3, 'confirmed']);
            $stmt->execute([$meeting_id, 4, 'invited']);
            $stmt->execute([$meeting_id, 5, 'invited']);
            
            // TOP 0 erstellen
            $stmt = $pdo->prepare("
                INSERT INTO agenda_items (meeting_id, top_number, title, description, category, priority, estimated_duration, created_by_member_id) 
                VALUES (?, 0, ?, '', 'antrag_beschluss', 10.00, 5, 1)
            ");
            $stmt->execute([$meeting_id, 'Wahl der Sitzungsleitung und Protokollführung']);
            
            // Test-TOPs erstellen
            $tops = [
                ['Genehmigung des letzten Protokolls', 'Protokoll der Sitzung vom 15.09.2025', 8.00, 10, 0],
                ['Finanzbericht Q3/2025', 'Präsentation der Quartalszahlen', 9.00, 30, 1],
                ['Personalplanung 2026', 'Diskussion der Stellenbesetzungen', 7.00, 45, 1]
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO agenda_items (meeting_id, top_number, title, description, priority, estimated_duration, is_confidential, created_by_member_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $top_num = 1;
            foreach ($tops as $top) {
                $stmt->execute([$meeting_id, $top_num, $top[0], $top[1], $top[2], $top[3], $top[4]]);
                $top_num++;
            }
            
            // TOP 99 erstellen
            $stmt = $pdo->prepare("
                INSERT INTO agenda_items (meeting_id, top_number, title, description, category, priority, estimated_duration, created_by_member_id) 
                VALUES (?, 99, 'Verschiedenes', '', 'sonstiges', 5.00, 15, 1)
            ");
            $stmt->execute([$meeting_id]);
            
            // Beispiel-Kommentare
            $item_ids = $pdo->query("SELECT item_id FROM agenda_items WHERE meeting_id = $meeting_id AND top_number IN (2,3)")->fetchAll(PDO::FETCH_COLUMN);
            if (count($item_ids) >= 2) {
                $stmt = $pdo->prepare("
                    INSERT INTO agenda_comments (item_id, member_id, comment_text, priority_rating, duration_estimate) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$item_ids[0], 2, 'Bitte auch die Kostenentwicklung im Vergleich zum Vorjahr darstellen.', 8.50, 35.00]);
                $stmt->execute([$item_ids[1], 5, 'Wir sollten auch über die Nachfolgeplanung sprechen.', 7.50, 50.00]);
            }
            
            // Beispiel-ToDo
            $stmt = $pdo->prepare("
                INSERT INTO todos (meeting_id, assigned_to_member_id, title, description, status, entry_date, due_date, created_by_member_id) 
                VALUES (?, ?, ?, ?, 'open', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1)
            ");
            $stmt->execute([
                $meeting_id, 
                4, 
                'Einladungen verschicken',
                'Einladungen für die Vorstandssitzung an alle Teilnehmer versenden'
            ]);
            
            echo "<p style='color: green;'>✓ Test-Meeting mit TOPs, Kommentaren und ToDo erstellt!</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Mitglieder existieren bereits - überspringe Test-Daten</p>";
        }
    }
    
    echo "<p><a href='index.php'>→ Zum Login</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Fehler: " . $e->getMessage() . "</p>";
    die();
}
?>