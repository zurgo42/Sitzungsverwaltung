<?php
/**
 * init-db.php - Datenbank initialisieren
 * Aktualisiert: 18.11.2025 - Vollständige DB-Struktur inkl. Terminplanung, Meinungsbild-Tool & Dokumentenverwaltung
 *
 * Dieses Skript erstellt die komplette Datenbankstruktur für die Sitzungsverwaltung.
 * Es enthält KEINE Demo-Daten - diese können separat über demo.php geladen werden.
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

    // =========================================================
    // CORE-TABELLEN
    // =========================================================

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

    // =========================================================
    // MEETING-TABELLEN
    // =========================================================

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

    // =========================================================
    // PROTOKOLL-TABELLEN
    // =========================================================

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

    // =========================================================
    // TODO-TABELLEN
    // =========================================================

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

    // =========================================================
    // ADMIN-LOG-TABELLE
    // =========================================================

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

    // =========================================================
    // TERMINPLANUNG-TABELLEN
    // =========================================================

    // Umfragen/Terminplanung-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS polls (
        poll_id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        created_by_member_id INT NOT NULL,
        meeting_id INT DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        video_link VARCHAR(500) DEFAULT NULL,
        duration INT DEFAULT NULL,
        status ENUM('open', 'closed', 'finalized') DEFAULT 'open',
        final_date_id INT DEFAULT NULL,
        reminder_enabled TINYINT(1) DEFAULT 0 COMMENT 'Ob Erinnerungsmail aktiviert ist',
        reminder_days INT DEFAULT 1 COMMENT 'Anzahl Tage vor Termin für Erinnerung',
        reminder_recipients VARCHAR(20) DEFAULT 'voters' COMMENT 'Empfänger: voters, all, none',
        reminder_sent TINYINT(1) DEFAULT 0 COMMENT 'Ob Erinnerungsmail bereits versendet wurde',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        finalized_at DATETIME DEFAULT NULL,
        FOREIGN KEY (created_by_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_created_by (created_by_member_id),
        INDEX idx_meeting (meeting_id),
        INDEX idx_created_at (created_at),
        INDEX idx_polls_reminder (reminder_enabled, reminder_sent, final_date_id)
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
        INDEX idx_date (suggested_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Teilnehmer an Umfragen
    $tables[] = "CREATE TABLE IF NOT EXISTS poll_participants (
        participant_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        member_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_participant (poll_id, member_id),
        INDEX idx_poll (poll_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Abstimmungen zu Terminvorschlägen
    $tables[] = "CREATE TABLE IF NOT EXISTS poll_responses (
        response_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        date_id INT NOT NULL,
        member_id INT NOT NULL,
        vote TINYINT NOT NULL COMMENT '-1=Nein, 0=Vielleicht, 1=Ja',
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (date_id) REFERENCES poll_dates(date_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_vote (poll_id, date_id, member_id),
        INDEX idx_poll (poll_id),
        INDEX idx_date (date_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // MEINUNGSBILD-TOOL-TABELLEN
    // =========================================================

    // Antwort-Templates für Meinungsbilder
    $tables[] = "CREATE TABLE IF NOT EXISTS opinion_answer_templates (
        template_id INT PRIMARY KEY AUTO_INCREMENT,
        template_name VARCHAR(100) NOT NULL,
        option_1 VARCHAR(100) DEFAULT NULL,
        option_2 VARCHAR(100) DEFAULT NULL,
        option_3 VARCHAR(100) DEFAULT NULL,
        option_4 VARCHAR(100) DEFAULT NULL,
        option_5 VARCHAR(100) DEFAULT NULL,
        option_6 VARCHAR(100) DEFAULT NULL,
        option_7 VARCHAR(100) DEFAULT NULL,
        option_8 VARCHAR(100) DEFAULT NULL,
        option_9 VARCHAR(100) DEFAULT NULL,
        option_10 VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_template_name (template_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Meinungsbilder/Umfragen
    $tables[] = "CREATE TABLE IF NOT EXISTS opinion_polls (
        poll_id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL COMMENT 'Die gestellte Frage',
        creator_member_id INT NOT NULL,
        target_type ENUM('individual', 'list', 'public') NOT NULL COMMENT 'individual=Link, list=Meeting-Teilnehmer, public=Alle',
        list_id INT DEFAULT NULL COMMENT 'Falls target_type=list: meeting_id',
        access_token VARCHAR(100) DEFAULT NULL COMMENT 'Eindeutiger Token für individual-Typ',
        template_id INT DEFAULT NULL,
        allow_multiple_answers TINYINT(1) DEFAULT 0,
        is_anonymous TINYINT(1) DEFAULT 0,
        duration_days INT DEFAULT 14,
        show_intermediate_after_days INT DEFAULT 7,
        delete_after_days INT DEFAULT 30,
        status ENUM('active', 'ended', 'deleted') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ends_at DATETIME DEFAULT NULL,
        delete_at DATETIME DEFAULT NULL,
        FOREIGN KEY (creator_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        FOREIGN KEY (list_id) REFERENCES meetings(meeting_id) ON DELETE SET NULL,
        FOREIGN KEY (template_id) REFERENCES opinion_answer_templates(template_id) ON DELETE SET NULL,
        UNIQUE KEY unique_access_token (access_token),
        INDEX idx_creator (creator_member_id),
        INDEX idx_status (status),
        INDEX idx_target_type (target_type),
        INDEX idx_list (list_id),
        INDEX idx_ends_at (ends_at),
        INDEX idx_delete_at (delete_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Antwortoptionen für Meinungsbilder
    $tables[] = "CREATE TABLE IF NOT EXISTS opinion_poll_options (
        option_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        option_text VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (poll_id) REFERENCES opinion_polls(poll_id) ON DELETE CASCADE,
        INDEX idx_poll (poll_id),
        INDEX idx_sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Teilnehmer bei list-Typ Meinungsbildern
    $tables[] = "CREATE TABLE IF NOT EXISTS opinion_poll_participants (
        participant_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        member_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES opinion_polls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_opinion_participant (poll_id, member_id),
        INDEX idx_poll (poll_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Antworten auf Meinungsbilder
    $tables[] = "CREATE TABLE IF NOT EXISTS opinion_responses (
        response_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        member_id INT DEFAULT NULL COMMENT 'NULL bei anonymen public-Umfragen',
        session_token VARCHAR(100) DEFAULT NULL COMMENT 'Für anonyme Teilnahme (public)',
        free_text TEXT DEFAULT NULL COMMENT 'Optionaler Kommentar',
        force_anonymous TINYINT(1) DEFAULT 0 COMMENT 'User will anonym bleiben',
        responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES opinion_polls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
        INDEX idx_poll (poll_id),
        INDEX idx_member (member_id),
        INDEX idx_session_token (session_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Gewählte Optionen (M:N zwischen responses und options)
    $tables[] = "CREATE TABLE IF NOT EXISTS opinion_response_options (
        response_option_id INT PRIMARY KEY AUTO_INCREMENT,
        response_id INT NOT NULL,
        option_id INT NOT NULL,
        FOREIGN KEY (response_id) REFERENCES opinion_responses(response_id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES opinion_poll_options(option_id) ON DELETE CASCADE,
        UNIQUE KEY unique_response_option (response_id, option_id),
        INDEX idx_response (response_id),
        INDEX idx_option (option_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // E-MAIL-WARTESCHLANGE
    // =========================================================

    // E-Mail-Warteschlange (für Queue-basiertes Mail-System)
    $tables[] = "CREATE TABLE IF NOT EXISTS mail_queue (
        queue_id INT PRIMARY KEY AUTO_INCREMENT,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(500) NOT NULL,
        message_text TEXT NOT NULL,
        message_html TEXT,
        from_email VARCHAR(255) NOT NULL,
        from_name VARCHAR(255) NOT NULL,
        status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
        priority INT DEFAULT 5,
        attempts INT DEFAULT 0,
        max_attempts INT DEFAULT 3,
        last_error TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        send_at TIMESTAMP NULL DEFAULT NULL,
        sent_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_send_at (send_at),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // DOKUMENTENVERWALTUNG-TABELLEN
    // =========================================================

    // Dokumente-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS documents (
        document_id INT PRIMARY KEY AUTO_INCREMENT,
        filename VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        filepath VARCHAR(500) NOT NULL,
        filesize INT NOT NULL DEFAULT 0,
        filetype VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        keywords TEXT,
        version VARCHAR(50),
        short_url VARCHAR(255),
        category ENUM('satzung', 'ordnungen', 'richtlinien', 'formulare', 'mv_unterlagen', 'dokumentationen', 'urteile', 'medien', 'sonstige') DEFAULT 'sonstige',
        access_level INT DEFAULT 0,
        status ENUM('active', 'archived', 'hidden', 'outdated') DEFAULT 'active',
        uploaded_by_member_id INT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME,
        admin_notes TEXT,
        FOREIGN KEY (uploaded_by_member_id) REFERENCES members(member_id) ON DELETE SET NULL,
        INDEX idx_category (category),
        INDEX idx_access_level (access_level),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_title (title),
        FULLTEXT INDEX idx_search (title, description, keywords)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Dokument-Downloads-Tabelle (Download-Tracking)
    $tables[] = "CREATE TABLE IF NOT EXISTS document_downloads (
        download_id INT PRIMARY KEY AUTO_INCREMENT,
        document_id INT NOT NULL,
        member_id INT,
        downloaded_at DATETIME NOT NULL,
        ip_address VARCHAR(45),
        FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL,
        INDEX idx_document (document_id),
        INDEX idx_member (member_id),
        INDEX idx_downloaded_at (downloaded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Tabellen erstellen
    echo "<p>Erstelle " . count($tables) . " Tabellen...</p>";
    foreach ($tables as $sql) {
        $pdo->exec($sql);
        echo ".";
    }

    echo "<p style='color: green;'>✓ Alle Tabellen erfolgreich erstellt!</p>";

    // =========================================================
    // MIGRATIONS: Fehlende Spalten hinzufügen
    // =========================================================

    echo "<p>Prüfe auf fehlende Spalten und führe Migrations aus...</p>";

    // Migration: Fehlende Spalten zur members-Tabelle hinzufügen (in korrekter Reihenfolge!)
    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'membership_number'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'membership_number' zu members hinzu...</p>";
        $pdo->exec("ALTER TABLE members ADD COLUMN membership_number VARCHAR(50) DEFAULT NULL AFTER member_id");
        // Unique Key nur hinzufügen, wenn er nicht existiert
        try {
            $pdo->exec("ALTER TABLE members ADD UNIQUE KEY membership_number (membership_number)");
        } catch (PDOException $e) {
            // Index existiert bereits - ignorieren
        }
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'is_admin'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'is_admin' zu members hinzu...</p>";
        $pdo->exec("ALTER TABLE members ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER role");
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'is_active'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'is_active' zu members hinzu...</p>";
        $pdo->exec("ALTER TABLE members ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER is_admin");
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'is_confidential'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'is_confidential' zu members hinzu...</p>";
        $pdo->exec("ALTER TABLE members ADD COLUMN is_confidential TINYINT UNSIGNED DEFAULT NULL AFTER is_active");
        echo ".";
    }

    // Migration: location, video_link, duration zu polls hinzufügen (falls fehlend)
    $stmt = $pdo->query("SHOW COLUMNS FROM polls LIKE 'location'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'location' zu polls hinzu...</p>";
        $pdo->exec("ALTER TABLE polls ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER meeting_id");
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM polls LIKE 'video_link'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'video_link' zu polls hinzu...</p>";
        $pdo->exec("ALTER TABLE polls ADD COLUMN video_link VARCHAR(500) DEFAULT NULL AFTER location");
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM polls LIKE 'duration'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'duration' zu polls hinzu...</p>";
        $pdo->exec("ALTER TABLE polls ADD COLUMN duration INT DEFAULT NULL AFTER video_link");
        echo ".";
    }

    echo "<p style='color: green;'>✓ Migrations abgeschlossen!</p>";

    // =========================================================
    // TRIGGER & INITIALDATEN
    // =========================================================

    echo "<p>Erstelle Trigger und füge Initialdaten ein...</p>";

    // Trigger für automatische Datums-Berechnung bei opinion_polls
    $pdo->exec("DROP TRIGGER IF EXISTS opinion_polls_before_insert");
    $pdo->exec("
        CREATE TRIGGER opinion_polls_before_insert
        BEFORE INSERT ON opinion_polls
        FOR EACH ROW
        BEGIN
            IF NEW.ends_at IS NULL THEN
                SET NEW.ends_at = DATE_ADD(NOW(), INTERVAL NEW.duration_days DAY);
            END IF;
            IF NEW.delete_at IS NULL THEN
                SET NEW.delete_at = DATE_ADD(NOW(), INTERVAL NEW.delete_after_days DAY);
            END IF;
        END
    ");
    echo ".";

    // Meinungsbild-Templates einfügen (nur wenn leer)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM opinion_answer_templates");
    if ($stmt->fetch()['count'] == 0) {
        echo "<p>Füge Meinungsbild-Templates ein...</p>";

        $templates = [
            ['Ja/Nein/Enthaltung', 'Ja', 'Nein', 'Enthaltung', null, null, null, null, null, null, null],
            ['Passt-Skala', 'passt sehr gut', 'passt gut', 'passt einigermaßen', 'passt schlecht', 'passt gar nicht', null, null, null, null, null],
            ['Dafür/Dagegen', 'unbedingt dafür', 'eher dafür', 'neutral', 'eher dagegen', 'unbedingt dagegen', null, null, null, null, null],
            ['Gefällt mir', 'gefällt mir sehr gut', 'gefällt mir gut', 'neutral', 'gefällt mir nicht', 'gefällt mir überhaupt nicht', null, null, null, null, null],
            ['Skala 1-9', '1', '2', '3', '4', '5', '6', '7', '8', '9', null],
            ['Dringlichkeit', 'Sofort!', 'sehr dringlich', 'dringlich', 'kann warten', 'nicht machen', null, null, null, null, null],
            ['Wichtigkeit', 'unabdingbar', 'sehr wichtig', 'eher wichtig', 'nicht wichtig', 'Auf keinen Fall', null, null, null, null, null],
            ['Wünsche', 'Sehr!', 'Würde mich freuen', 'ist mir egal', 'würde ich nicht wollen', 'Auf keinen Fall', null, null, null, null, null],
            ['Häufigkeit', 'immer', 'sehr oft', 'häufig', 'ab und zu', 'selten', 'fast nie', 'nie', null, null, null],
            ['Priorität', 'Absolutes Muss', 'sehr wichtig', 'wichtig', 'nice to have', 'unnötig', 'Auf keinen Fall', null, null, null, null],
            ['Frei', null, null, null, null, null, null, null, null, null, null],
            ['Nützlichkeit', 'sehr nützlich', 'etwas nützlich', 'überflüssig', null, null, null, null, null, null, null],
            ['Bewertung', 'langweilig', 'Zeitvertreib', 'spannend', null, null, null, null, null, null, null]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO opinion_answer_templates
            (template_name, option_1, option_2, option_3, option_4, option_5, option_6, option_7, option_8, option_9, option_10)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($templates as $template) {
            $stmt->execute($template);
            echo ".";
        }

        echo "<p style='color: green;'>✓ " . count($templates) . " Meinungsbild-Templates eingefügt!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Meinungsbild-Templates existieren bereits - überspringe</p>";
    }

    // Default-Admin anlegen (nur wenn members-Tabelle leer ist)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM members");
    if ($stmt->fetch()['count'] == 0) {
        echo "<p>Erstelle Default-Admin-User...</p>";

        // Passwort: admin123 (BITTE NACH ERSTEM LOGIN ÄNDERN!)
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO members (email, password_hash, first_name, last_name, role, is_admin, is_active, membership_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'admin@example.com',
            $password_hash,
            'System',
            'Administrator',
            'gf',
            1,
            1,
            'ADMIN001'
        ]);

        echo "<p style='color: green;'>✓ Default-Admin angelegt</p>";
        echo "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;'>";
        echo "<h4 style='margin-top: 0;'>⚠️ Default-Admin Zugangsdaten</h4>";
        echo "<p><strong>Email:</strong> admin@example.com</p>";
        echo "<p><strong>Passwort:</strong> admin123</p>";
        echo "<p style='color: #856404;'><strong>WICHTIG:</strong> Bitte ändern Sie das Passwort nach dem ersten Login!</p>";
        echo "</div>";
    } else {
        echo "<p style='color: orange;'>⚠ Members-Tabelle enthält bereits Einträge - überspringe Default-Admin</p>";
    }

    echo "<p style='color: green; font-weight: bold;'>✓✓✓ Datenbank-Initialisierung abgeschlossen!</p>";
    echo "<hr>";
    echo "<h3>Nächste Schritte:</h3>";
    echo "<ul>";
    echo "<li><a href='index.php'>Zum Login</a> - Anmelden mit den oben genannten Zugangsdaten</li>";
    echo "<li><a href='tools/demo_export.php'>Demo-Daten exportieren</a> - Nach dem Anlegen von Test-Daten</li>";
    echo "<li><a href='tools/demo_import.php'>Demo-Daten importieren</a> - Setzt DB auf Demo-Stand zurück</li>";
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Fehler: " . $e->getMessage() . "</p>";
    die();
}
?>
