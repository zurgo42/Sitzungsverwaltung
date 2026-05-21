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
    $tables[] = "CREATE TABLE IF NOT EXISTS svmembers (
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
    $tables[] = "CREATE TABLE IF NOT EXISTS svmeetings (
        meeting_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_name VARCHAR(255) DEFAULT NULL,
        meeting_date DATETIME NOT NULL,
        expected_end_date DATETIME DEFAULT NULL,
        submission_deadline DATETIME DEFAULT NULL COMMENT 'Antragsschluss für neue TOPs durch Teilnehmer',
        location VARCHAR(255) DEFAULT NULL,
        video_link VARCHAR(500) DEFAULT NULL,
        invited_by_member_id INT NOT NULL,
        chairman_member_id INT DEFAULT NULL,
        chairman_name VARCHAR(255) DEFAULT NULL,
        secretary_member_id INT DEFAULT NULL,
        secretary_name VARCHAR(255) DEFAULT NULL,
        active_item_id INT DEFAULT NULL COMMENT 'Aktuell aktiver TOP während der Sitzung',
        started_at DATETIME DEFAULT NULL,
        ended_at DATETIME DEFAULT NULL,
        status ENUM('preparation', 'active', 'ended', 'protocol_ready', 'archived') DEFAULT 'preparation',
        visibility_type ENUM('public', 'authenticated', 'invited_only') DEFAULT 'invited_only',
        protokoll TEXT DEFAULT NULL,
        prot_intern TEXT DEFAULT NULL,
        protocol_intern TEXT NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invited_by_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        FOREIGN KEY (chairman_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
        FOREIGN KEY (secretary_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
        INDEX idx_meeting_date (meeting_date),
        INDEX idx_status (status),
        INDEX idx_visibility (visibility_type),
        INDEX idx_protokoll (protokoll(768)),
        INDEX idx_submission_deadline (submission_deadline)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Meeting-Teilnehmer-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svmeeting_participants (
        participant_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT NOT NULL,
        member_id INT NOT NULL,
        status ENUM('invited', 'confirmed', 'present', 'absent') DEFAULT 'invited',
        attendance_status ENUM('present', 'partial', 'absent') DEFAULT 'absent',
        invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_participant (meeting_id, member_id),
        INDEX idx_meeting (meeting_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Tagesordnungspunkte-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svagenda_items (
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
        FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (created_by_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
        FOREIGN KEY (grouped_with_item_id) REFERENCES svagenda_items(item_id) ON DELETE SET NULL,
        INDEX idx_meeting (meeting_id),
        INDEX idx_top_number (top_number),
        INDEX idx_is_active (is_active),
        INDEX idx_created_by (created_by_member_id),
        INDEX idx_grouped_with (grouped_with_item_id),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Kommentare-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svagenda_comments (
        comment_id INT PRIMARY KEY AUTO_INCREMENT,
        item_id INT NOT NULL,
        member_id INT NOT NULL,
        comment_text TEXT,
        priority_rating DECIMAL(3,2) DEFAULT NULL,
        duration_estimate DECIMAL(5,2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        INDEX idx_item (item_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Live-Kommentare während aktiver Sitzung
    $tables[] = "CREATE TABLE IF NOT EXISTS svagenda_live_comments (
        comment_id INT PRIMARY KEY AUTO_INCREMENT,
        item_id INT NOT NULL,
        member_id INT NOT NULL,
        comment_text TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        INDEX idx_item (item_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Post-Meeting Kommentare (nach Sitzungsende)
    $tables[] = "CREATE TABLE IF NOT EXISTS svagenda_post_comments (
        comment_id INT PRIMARY KEY AUTO_INCREMENT,
        item_id INT NOT NULL,
        member_id INT NOT NULL,
        comment_text TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        INDEX idx_item (item_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Dateianhänge für Tagesordnungspunkte (2026-04-27)
    $tables[] = "CREATE TABLE IF NOT EXISTS svagenda_attachments (
        attachment_id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL COMMENT 'FK zu svagenda_items',
        filename VARCHAR(255) NOT NULL COMMENT 'Gespeicherter Dateiname (unique)',
        original_filename VARCHAR(255) NOT NULL COMMENT 'Original-Dateiname vom User',
        filepath VARCHAR(500) NOT NULL COMMENT 'Pfad zur Datei relativ zu uploads/',
        filesize INT NOT NULL COMMENT 'Dateigröße in Bytes',
        filetype VARCHAR(100) NOT NULL COMMENT 'MIME-Type',
        uploaded_by_member_id INT DEFAULT NULL COMMENT 'Wer hat hochgeladen (NULL wenn Member gelöscht)',
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item_id (item_id),
        INDEX idx_uploaded_by (uploaded_by_member_id),
        INDEX idx_uploaded_at (uploaded_at),
        FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // PROTOKOLL-TABELLEN
    // =========================================================

    // Protokolle-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svprotocols (
        protocol_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT DEFAULT NULL COMMENT 'Optional: Verknüpfung zum Meeting (kann auch ohne Meeting existieren)',
        protocol_type ENUM('public', 'confidential') DEFAULT 'public',
        content TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_meeting (meeting_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Protokolländerungs-Anfragen-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svprotocol_change_requests (
        request_id INT PRIMARY KEY AUTO_INCREMENT,
        protocol_id INT DEFAULT NULL,
        member_id INT DEFAULT NULL,
        item_id INT DEFAULT NULL,
        change_request TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (protocol_id) REFERENCES svprotocols(protocol_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
        INDEX idx_protocol (protocol_id),
        INDEX idx_member (member_id),
        INDEX idx_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // TODO-TABELLEN
    // =========================================================

    // ToDos-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svtodos (
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
        FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_to_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        INDEX idx_meeting (meeting_id),
        INDEX idx_item (item_id),
        INDEX idx_assigned_to (assigned_to_member_id),
        INDEX idx_due_date (due_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // ToDo-Log-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svtodo_log (
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
    $tables[] = "CREATE TABLE IF NOT EXISTS svadmin_log (
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
        FOREIGN KEY (admin_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        INDEX idx_admin (admin_member_id),
        INDEX idx_action_type (action_type),
        INDEX idx_target_type (target_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // SYSTEM-KONFIGURATION
    // =========================================================

    // System-Konfigurationstabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svconfig (
        config_key VARCHAR(50) PRIMARY KEY,
        config_value TEXT,
        config_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
        description VARCHAR(255),
        category VARCHAR(50) DEFAULT 'general',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT NULL,
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='System-Konfiguration für anpassbare Einstellungen'";

    // =========================================================
    // ANTRAGS-/BESCHLUSSSYSTEM
    // =========================================================

    // Ressorts-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svressorts (
        ID INT AUTO_INCREMENT PRIMARY KEY,
        Code VARCHAR(4) DEFAULT NULL COMMENT 'Ressort-Code (z.B. R15)',
        Ressort VARCHAR(100) NOT NULL COMMENT 'Name des Ressorts',
        Reihenfolge INT DEFAULT 100 COMMENT 'Sortierreihenfolge',
        aktiv TINYINT(1) DEFAULT 1 COMMENT '1=Aktiv, 0=Inaktiv',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (Code),
        INDEX idx_reihenfolge (Reihenfolge),
        INDEX idx_aktiv (aktiv)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Ressorts für das Antragssystem'";

    // Anträge-Tabelle (für Clean-Installationen ohne Adapter)
    // Bewährte VTool-Struktur + moderne Erweiterungen
    $tables[] = "CREATE TABLE IF NOT EXISTS svantraege (
        bart VARCHAR(1) DEFAULT NULL,
        praesenz VARCHAR(11) DEFAULT NULL,
        antrnr VARCHAR(10) NOT NULL PRIMARY KEY,
        ergebnis TEXT DEFAULT NULL,
        antrst VARCHAR(3) DEFAULT NULL COMMENT 'Antragsteller-Code',
        ressort1 VARCHAR(4) DEFAULT NULL COMMENT 'Hauptressort (Code)',
        ressort2 VARCHAR(4) DEFAULT NULL COMMENT 'Mitwirkendes Ressort (Code)',
        verein VARCHAR(1) DEFAULT NULL,
        int_ext VARCHAR(1) DEFAULT NULL,
        titel VARCHAR(255) DEFAULT NULL,
        beschluss TEXT DEFAULT NULL,
        fin VARCHAR(8) DEFAULT NULL,
        fintext TEXT DEFAULT NULL,
        budgetnr VARCHAR(16) DEFAULT NULL,
        budget VARCHAR(8) DEFAULT NULL,
        pers TEXT DEFAULT NULL,
        sach TEXT DEFAULT NULL,
        begr TEXT DEFAULT NULL,
        thread VARCHAR(64) DEFAULT NULL,
        hinweis TEXT DEFAULT NULL,
        verant TEXT DEFAULT NULL,
        file1 VARCHAR(128) DEFAULT NULL,
        filetext1 TEXT DEFAULT NULL,
        file2 VARCHAR(128) DEFAULT NULL,
        filetext2 TEXT DEFAULT NULL,
        file3 VARCHAR(128) DEFAULT NULL,
        filetext3 TEXT DEFAULT NULL,
        file4 VARCHAR(128) DEFAULT NULL,
        filetext4 TEXT DEFAULT NULL,
        wichtig VARCHAR(3) DEFAULT NULL,
        verf VARCHAR(64) DEFAULT NULL,
        vorher VARCHAR(1) DEFAULT NULL,
        sofort VARCHAR(1) DEFAULT NULL,
        durch VARCHAR(64) DEFAULT NULL,
        zufin VARCHAR(16) DEFAULT NULL,
        zbem TEXT DEFAULT NULL,
        verf1 VARCHAR(3) DEFAULT NULL,
        verf2 VARCHAR(3) DEFAULT NULL,
        verkb TEXT DEFAULT NULL,
        verk1 INT UNSIGNED DEFAULT NULL,
        verk2 INT UNSIGNED DEFAULT NULL,
        warantrag VARCHAR(9) DEFAULT NULL,
        lzugriff DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        VName1 VARCHAR(3) DEFAULT NULL,
        Votum1 VARCHAR(1) DEFAULT NULL,
        VBegr1 TEXT DEFAULT NULL,
        VProt1 TEXT DEFAULT NULL,
        VBedenk1 TEXT DEFAULT NULL,
        VDat1 VARCHAR(16) DEFAULT NULL,
        VName2 VARCHAR(3) DEFAULT NULL,
        Votum2 VARCHAR(1) DEFAULT NULL,
        VBegr2 TEXT DEFAULT NULL,
        VProt2 TEXT DEFAULT NULL,
        VBedenk2 TEXT DEFAULT NULL,
        VDat2 VARCHAR(16) DEFAULT NULL,
        VName3 VARCHAR(3) DEFAULT NULL,
        Votum3 VARCHAR(1) DEFAULT NULL,
        VBegr3 TEXT DEFAULT NULL,
        VProt3 TEXT DEFAULT NULL,
        VBedenk3 TEXT DEFAULT NULL,
        VDat3 VARCHAR(16) DEFAULT NULL,
        VName4 VARCHAR(3) DEFAULT NULL,
        Votum4 VARCHAR(1) DEFAULT NULL,
        VBegr4 TEXT DEFAULT NULL,
        VProt4 TEXT DEFAULT NULL,
        VBedenk4 TEXT DEFAULT NULL,
        VDat4 VARCHAR(16) DEFAULT NULL,
        VName5 VARCHAR(3) DEFAULT NULL,
        Votum5 VARCHAR(1) DEFAULT NULL,
        VBegr5 TEXT DEFAULT NULL,
        VProt5 TEXT DEFAULT NULL,
        VBedenk5 TEXT DEFAULT NULL,
        VDat5 VARCHAR(16) DEFAULT NULL,
        VName6 VARCHAR(3) DEFAULT NULL,
        Votum6 VARCHAR(1) DEFAULT NULL,
        VBegr6 TEXT DEFAULT NULL,
        VProt6 TEXT DEFAULT NULL,
        VBedenk6 TEXT DEFAULT NULL,
        VDat6 VARCHAR(16) DEFAULT NULL,
        Zeitablauf TEXT DEFAULT NULL,
        -- Neue Felder für moderne Funktionen
        status CHAR(1) DEFAULT 'A' COMMENT 'Status: A=Editing, E=Eingereicht, F=Freigegeben, G=Genehmigt, Z=Zurückgestellt, X=Abgelehnt',
        abstimmregel VARCHAR(20) DEFAULT 'einfach' COMMENT 'Abstimmungsregel: einfach, absolut, mehrheit_stimmber, zweidrittel, einstimmig',
        betrag DECIMAL(10,2) DEFAULT NULL COMMENT 'Finanzieller Betrag (strukturiert)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_bart (bart),
        INDEX idx_antrst (antrst),
        INDEX idx_ressort1 (ressort1),
        INDEX idx_ressort2 (ressort2),
        INDEX idx_status (status),
        INDEX idx_abstimmregel (abstimmregel),
        INDEX idx_wichtig (wichtig),
        INDEX idx_lzugriff (lzugriff)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Anträge - Bewährte VTool-Struktur mit modernen Erweiterungen'";

    // Beschlüsse-Tabelle (für Clean-Installationen ohne Adapter)
    $tables[] = "CREATE TABLE IF NOT EXISTS svbeschluesse (
        antrnr VARCHAR(12) NOT NULL PRIMARY KEY,
        fertig VARCHAR(1) DEFAULT NULL,
        wichtig VARCHAR(1) DEFAULT NULL,
        text TEXT DEFAULT NULL,
        ressort TEXT DEFAULT NULL,
        int_ext VARCHAR(8) DEFAULT NULL,
        titel TEXT DEFAULT NULL,
        beschluss TEXT DEFAULT NULL,
        fintext TEXT DEFAULT NULL,
        pers TEXT DEFAULT NULL,
        sach TEXT DEFAULT NULL,
        begr TEXT DEFAULT NULL,
        dafuer TEXT DEFAULT NULL,
        dagegen TEXT DEFAULT NULL,
        enthaltungen TEXT DEFAULT NULL,
        anmerkungen TEXT DEFAULT NULL,
        -- Neue Felder für moderne Funktionen
        status CHAR(1) DEFAULT 'F' COMMENT 'Status: F=Finalized',
        abstimmregel VARCHAR(20) DEFAULT 'einfach' COMMENT 'Abstimmungsregel',
        beschlussdatum DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ressort (ressort(100)),
        INDEX idx_status (status),
        INDEX idx_wichtig (wichtig),
        INDEX idx_fertig (fertig)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Beschlüsse - Bewährte VTool-Struktur mit modernen Erweiterungen'";

    // =========================================================
    // TERMINPLANUNG-TABELLEN
    // =========================================================

    // Umfragen/Terminplanung-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svpolls (
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
        FOREIGN KEY (created_by_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_created_by (created_by_member_id),
        INDEX idx_meeting (meeting_id),
        INDEX idx_created_at (created_at),
        INDEX idx_polls_reminder (reminder_enabled, reminder_sent, final_date_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Terminvorschläge-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svpoll_dates (
        date_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        suggested_date DATETIME NOT NULL,
        suggested_end_date DATETIME DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        notes TEXT,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES svpolls(poll_id) ON DELETE CASCADE,
        INDEX idx_poll (poll_id),
        INDEX idx_date (suggested_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Teilnehmer an Umfragen
    $tables[] = "CREATE TABLE IF NOT EXISTS svpoll_participants (
        participant_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        member_id INT NOT NULL,
        invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES svpolls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_participant (poll_id, member_id),
        INDEX idx_poll (poll_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Abstimmungen zu Terminvorschlägen
    $tables[] = "CREATE TABLE IF NOT EXISTS svpoll_responses (
        response_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        date_id INT NOT NULL,
        member_id INT NOT NULL,
        participant_name VARCHAR(255) DEFAULT NULL,
        participant_email VARCHAR(255) DEFAULT NULL,
        vote TINYINT NOT NULL COMMENT '-1=Nein, 0=Vielleicht, 1=Ja',
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES svpolls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (date_id) REFERENCES svpoll_dates(date_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_vote (poll_id, date_id, member_id),
        INDEX idx_poll (poll_id),
        INDEX idx_date (date_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // MEINUNGSBILD-TOOL-TABELLEN
    // =========================================================

    // Antwort-Templates für Meinungsbilder
    $tables[] = "CREATE TABLE IF NOT EXISTS svopinion_answer_templates (
        template_id INT PRIMARY KEY AUTO_INCREMENT,
        template_name VARCHAR(100) NOT NULL,
        description TEXT DEFAULT NULL,
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
    $tables[] = "CREATE TABLE IF NOT EXISTS svopinion_polls (
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
        FOREIGN KEY (creator_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        FOREIGN KEY (list_id) REFERENCES svmeetings(meeting_id) ON DELETE SET NULL,
        FOREIGN KEY (template_id) REFERENCES svopinion_answer_templates(template_id) ON DELETE SET NULL,
        UNIQUE KEY unique_access_token (access_token),
        INDEX idx_creator (creator_member_id),
        INDEX idx_status (status),
        INDEX idx_target_type (target_type),
        INDEX idx_list (list_id),
        INDEX idx_ends_at (ends_at),
        INDEX idx_delete_at (delete_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Antwortoptionen für Meinungsbilder
    $tables[] = "CREATE TABLE IF NOT EXISTS svopinion_poll_options (
        option_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        option_text VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES svopinion_polls(poll_id) ON DELETE CASCADE,
        INDEX idx_poll (poll_id),
        INDEX idx_sort_order (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Teilnehmer bei list-Typ Meinungsbildern
    $tables[] = "CREATE TABLE IF NOT EXISTS svopinion_poll_participants (
        participant_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        member_id INT NOT NULL,
        invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES svopinion_polls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_opinion_participant (poll_id, member_id),
        INDEX idx_poll (poll_id),
        INDEX idx_member (member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Antworten auf Meinungsbilder
    $tables[] = "CREATE TABLE IF NOT EXISTS svopinion_responses (
        response_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_id INT NOT NULL,
        member_id INT DEFAULT NULL COMMENT 'NULL bei anonymen public-Umfragen',
        session_token VARCHAR(100) DEFAULT NULL COMMENT 'Für anonyme Teilnahme (public)',
        free_text TEXT DEFAULT NULL COMMENT 'Optionaler Kommentar',
        force_anonymous TINYINT(1) DEFAULT 0 COMMENT 'User will anonym bleiben',
        responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (poll_id) REFERENCES svopinion_polls(poll_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        INDEX idx_poll (poll_id),
        INDEX idx_member (member_id),
        INDEX idx_session_token (session_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Gewählte Optionen (M:N zwischen responses und options)
    $tables[] = "CREATE TABLE IF NOT EXISTS svopinion_response_options (
        response_option_id INT PRIMARY KEY AUTO_INCREMENT,
        response_id INT NOT NULL,
        option_id INT NOT NULL,
        FOREIGN KEY (response_id) REFERENCES svopinion_responses(response_id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES svopinion_poll_options(option_id) ON DELETE CASCADE,
        UNIQUE KEY unique_response_option (response_id, option_id),
        INDEX idx_response (response_id),
        INDEX idx_option (option_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // E-MAIL-WARTESCHLANGE
    // =========================================================

    // E-Mail-Warteschlange (für Queue-basiertes Mail-System)
    $tables[] = "CREATE TABLE IF NOT EXISTS svmail_queue (
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
    $tables[] = "CREATE TABLE IF NOT EXISTS svdocuments (
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
        FOREIGN KEY (uploaded_by_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
        INDEX idx_category (category),
        INDEX idx_access_level (access_level),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_title (title),
        FULLTEXT INDEX idx_search (title, description, keywords)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Dokument-Downloads-Tabelle (Download-Tracking)
    $tables[] = "CREATE TABLE IF NOT EXISTS svdocument_downloads (
        download_id INT PRIMARY KEY AUTO_INCREMENT,
        document_id INT NOT NULL,
        member_id INT,
        downloaded_at DATETIME NOT NULL,
        ip_address VARCHAR(45),
        FOREIGN KEY (document_id) REFERENCES svdocuments(document_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
        INDEX idx_document (document_id),
        INDEX idx_member (member_id),
        INDEX idx_downloaded_at (downloaded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // ABWESENHEITS-VERWALTUNG
    // =========================================================

    // Abwesenheiten-Tabelle
    $tables[] = "CREATE TABLE IF NOT EXISTS svabsences (
        absence_id INT PRIMARY KEY AUTO_INCREMENT,
        member_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        substitute_member_id INT DEFAULT NULL COMMENT 'Vertretung durch dieses Mitglied',
        reason VARCHAR(255) DEFAULT NULL COMMENT 'Grund der Abwesenheit (optional)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        FOREIGN KEY (substitute_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
        INDEX idx_member (member_id),
        INDEX idx_dates (start_date, end_date),
        INDEX idx_substitute (substitute_member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // EXTERNE TEILNEHMER & ZUGRIFFSPROTOKOLLE
    // =========================================================

    // Externe Teilnehmer für Umfragen (2025-12-18)
    $tables[] = "CREATE TABLE IF NOT EXISTS svexternal_participants (
        external_id INT PRIMARY KEY AUTO_INCREMENT,
        poll_type ENUM('termine', 'meinungsbild') NOT NULL COMMENT 'Typ der Umfrage',
        poll_id INT NOT NULL COMMENT 'ID der Umfrage (svpolls oder svopinion_polls)',
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        mnr VARCHAR(50) DEFAULT NULL COMMENT 'Optional: Mitgliedsnummer falls bekannt',
        session_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Eindeutiger Token für anonyme Teilnahme',
        ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP-Adresse bei Registrierung',
        consent_given BOOLEAN DEFAULT TRUE COMMENT 'Einwilligung zur Datenspeicherung',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Für 6-Monats-Löschung',
        INDEX idx_poll_type_id (poll_type, poll_id),
        INDEX idx_session_token (session_token),
        INDEX idx_email (email),
        INDEX idx_last_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Externe Teilnehmer ohne Account - werden nach 6 Monaten gelöscht'";

    // Externes Zugriffs-Log (2026-05-03)
    $tables[] = "CREATE TABLE IF NOT EXISTS svexternal_access_log (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        access_type ENUM('member_login', 'invalid_mnr', 'external_registration', 'registration_error') NOT NULL COMMENT 'Art des Zugriffs',
        poll_type ENUM('termine', 'meinungsbild') NOT NULL COMMENT 'Art der Umfrage',
        poll_id INT NOT NULL COMMENT 'ID der Umfrage',
        member_id INT DEFAULT NULL COMMENT 'Member-ID bei erfolgreichem MNr-Login',
        external_participant_id INT DEFAULT NULL COMMENT 'Externe Teilnehmer-ID bei Registrierung',
        mnr VARCHAR(50) DEFAULT NULL COMMENT 'Eingegebene Mitgliedsnummer',
        email VARCHAR(255) DEFAULT NULL COMMENT 'Eingegebene E-Mail',
        first_name VARCHAR(100) DEFAULT NULL COMMENT 'Eingegebener Vorname',
        last_name VARCHAR(100) DEFAULT NULL COMMENT 'Eingegebener Nachname',
        ip_address VARCHAR(45) NOT NULL COMMENT 'IP-Adresse des Zugriffs',
        user_agent TEXT COMMENT 'Browser User-Agent',
        success BOOLEAN DEFAULT TRUE COMMENT 'Ob der Zugriff erfolgreich war',
        error_message TEXT DEFAULT NULL COMMENT 'Fehlermeldung bei Misserfolg',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
        FOREIGN KEY (external_participant_id) REFERENCES svexternal_participants(external_id) ON DELETE SET NULL,
        INDEX idx_access_type (access_type),
        INDEX idx_poll (poll_type, poll_id),
        INDEX idx_member (member_id),
        INDEX idx_external (external_participant_id),
        INDEX idx_ip (ip_address),
        INDEX idx_created (created_at),
        INDEX idx_success (success)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // BENACHRICHTIGUNGS-CENTER
    // =========================================================

    // Benachrichtigungen (2026-04-27)
    $tables[] = "CREATE TABLE IF NOT EXISTS svnotifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL COMMENT 'Empfänger',
        type ENUM('meeting', 'todo', 'comment', 'assignment', 'reminder', 'system') NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(500) DEFAULT NULL COMMENT 'URL zum relevanten Element',
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        related_meeting_id INT DEFAULT NULL,
        related_todo_id INT DEFAULT NULL,
        related_item_id INT DEFAULT NULL COMMENT 'agenda item_id',
        INDEX idx_member_read (member_id, is_read),
        INDEX idx_created_at (created_at),
        INDEX idx_type (type),
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        FOREIGN KEY (related_meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (related_todo_id) REFERENCES svtodos(todo_id) ON DELETE CASCADE,
        FOREIGN KEY (related_item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Push-Subscriptions für Browser-Push (2026-04-27)
    $tables[] = "CREATE TABLE IF NOT EXISTS svpush_subscriptions (
        subscription_id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh_key TEXT NOT NULL,
        auth_key TEXT NOT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME DEFAULT NULL,
        INDEX idx_member_id (member_id),
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // KOLLABORATIVE TEXTE
    // =========================================================

    // Kollaborative Texte (Haupttabelle)
    $tables[] = "CREATE TABLE IF NOT EXISTS svcollab_texts (
        text_id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT NULL COMMENT 'NULL = Allgemeiner Text (Vorstand+GF+Ass), sonst Meeting-spezifisch',
        title VARCHAR(255) NOT NULL,
        initiator_member_id INT NOT NULL COMMENT 'Ersteller des Textes',
        status ENUM('active', 'finalized') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finalized_at DATETIME NULL,
        final_name VARCHAR(255) DEFAULT NULL COMMENT 'Name für finalisierte Version',
        FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE CASCADE,
        FOREIGN KEY (initiator_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        INDEX idx_meeting (meeting_id),
        INDEX idx_status (status),
        INDEX idx_initiator (initiator_member_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Absätze (Paragraphs) für kollaborative Texte
    $tables[] = "CREATE TABLE IF NOT EXISTS svcollab_text_paragraphs (
        paragraph_id INT PRIMARY KEY AUTO_INCREMENT,
        text_id INT NOT NULL,
        paragraph_order INT NOT NULL COMMENT 'Reihenfolge der Absätze',
        content TEXT DEFAULT NULL,
        last_edited_by INT DEFAULT NULL,
        last_edited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (text_id) REFERENCES svcollab_texts(text_id) ON DELETE CASCADE,
        FOREIGN KEY (last_edited_by) REFERENCES svmembers(member_id) ON DELETE SET NULL,
        INDEX idx_text (text_id),
        INDEX idx_order (text_id, paragraph_order),
        INDEX idx_edited (last_edited_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Locks für Absätze (wer editiert gerade welchen Absatz?)
    $tables[] = "CREATE TABLE IF NOT EXISTS svcollab_text_locks (
        lock_id INT PRIMARY KEY AUTO_INCREMENT,
        paragraph_id INT NOT NULL,
        member_id INT NOT NULL,
        locked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (paragraph_id) REFERENCES svcollab_text_paragraphs(paragraph_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_paragraph_lock (paragraph_id),
        INDEX idx_paragraph (paragraph_id),
        INDEX idx_member (member_id),
        INDEX idx_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Teilnehmer & Online-Status für kollaborative Texte
    $tables[] = "CREATE TABLE IF NOT EXISTS svcollab_text_participants (
        participant_id INT PRIMARY KEY AUTO_INCREMENT,
        text_id INT NOT NULL,
        member_id INT NOT NULL,
        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (text_id) REFERENCES svcollab_texts(text_id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_text_participant (text_id, member_id),
        INDEX idx_text (text_id),
        INDEX idx_member (member_id),
        INDEX idx_last_seen (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Versionen (Snapshots) für kollaborative Texte
    $tables[] = "CREATE TABLE IF NOT EXISTS svcollab_text_versions (
        version_id INT PRIMARY KEY AUTO_INCREMENT,
        text_id INT NOT NULL,
        version_number INT NOT NULL,
        content TEXT NOT NULL COMMENT 'Kompletter Text-Inhalt (alle Absätze zusammengefügt)',
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        version_note VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (text_id) REFERENCES svcollab_texts(text_id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES svmembers(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_text_version (text_id, version_number),
        INDEX idx_text (text_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // =========================================================
    // PERSÖNLICHE NOTIZEN
    // =========================================================

    // Persönliche Notizen zu Agenda Items (Teilnehmer-Mitschriften)
    $tables[] = "CREATE TABLE IF NOT EXISTS svagenda_personal_notes (
        note_id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        member_id INT NOT NULL,
        note_text TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_item_member (item_id, member_id),
        INDEX idx_member (member_id),
        UNIQUE KEY unique_note_per_member_item (item_id, member_id),
        FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Persönliche Notizen von Teilnehmern zu Agenda Items'";


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
    $stmt = $pdo->query("SHOW COLUMNS FROM svmembers LIKE 'membership_number'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'membership_number' zu members hinzu...</p>";
        $pdo->exec("ALTER TABLE svmembers ADD COLUMN membership_number VARCHAR(50) DEFAULT NULL AFTER member_id");
        // Unique Key nur hinzufügen, wenn er nicht existiert
        try {
            $pdo->exec("ALTER TABLE svmembers ADD UNIQUE KEY membership_number (membership_number)");
        } catch (PDOException $e) {
            // Index existiert bereits - ignorieren
        }
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM svmembers LIKE 'is_admin'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'is_admin' zu members hinzu...</p>";
        $pdo->exec("ALTER TABLE svmembers ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER role");
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM svmembers LIKE 'is_active'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'is_active' zu members hinzu...</p>";
        $pdo->exec("ALTER TABLE svmembers ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER is_admin");
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM svmembers LIKE 'is_confidential'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'is_confidential' zu members hinzu...</p>";
        $pdo->exec("ALTER TABLE svmembers ADD COLUMN is_confidential TINYINT UNSIGNED DEFAULT NULL AFTER is_active");
        echo ".";
    }

    // Migration: location, video_link, duration zu polls hinzufügen (falls fehlend)
    $stmt = $pdo->query("SHOW COLUMNS FROM svpolls LIKE 'location'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'location' zu polls hinzu...</p>";
        $pdo->exec("ALTER TABLE svpolls ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER meeting_id");
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM svpolls LIKE 'video_link'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'video_link' zu polls hinzu...</p>";
        $pdo->exec("ALTER TABLE svpolls ADD COLUMN video_link VARCHAR(500) DEFAULT NULL AFTER location");
        echo ".";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM svpolls LIKE 'duration'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'duration' zu polls hinzu...</p>";
        $pdo->exec("ALTER TABLE svpolls ADD COLUMN duration INT DEFAULT NULL AFTER video_link");
        echo ".";
    }

    // Migration: submission_deadline zu meetings hinzufügen (falls fehlend)
    $stmt = $pdo->query("SHOW COLUMNS FROM svmeetings LIKE 'submission_deadline'");
    if (!$stmt->fetch()) {
        echo "<p>Füge Spalte 'submission_deadline' zu meetings hinzu...</p>";
        $pdo->exec("ALTER TABLE svmeetings ADD COLUMN submission_deadline DATETIME DEFAULT NULL COMMENT 'Antragsschluss für neue TOPs durch Teilnehmer' AFTER expected_end_date");
        // Index hinzufügen
        try {
            $pdo->exec("ALTER TABLE svmeetings ADD INDEX idx_submission_deadline (submission_deadline)");
        } catch (PDOException $e) {
            // Index existiert bereits - ignorieren
        }
        echo ".";
    }

    echo "<p style='color: green;'>✓ Migrations abgeschlossen!</p>";

    // =========================================================
    // TRIGGER & INITIALDATEN
    // =========================================================

    echo "<p>Erstelle Trigger und füge Initialdaten ein...</p>";

    // Trigger für automatische Datums-Berechnung bei svopinion_polls
    $pdo->exec("DROP TRIGGER IF EXISTS svopinion_polls_before_insert");
    $pdo->exec("
        CREATE TRIGGER svopinion_polls_before_insert
        BEFORE INSERT ON svopinion_polls
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
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM svopinion_answer_templates");
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
            INSERT INTO svopinion_answer_templates
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

    // System-Konfiguration einfügen (nur wenn leer)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM svconfig");
    if ($stmt->fetch()['count'] == 0) {
        echo "<p>Füge System-Konfiguration ein...</p>";

        $config_entries = [
            // Aktiv-Level 0-19
            ['aktiv_level_0', 'Beschlussdatenbanken-Zugriff', 'text', 'Berechtigung für Level 0', 'aktiv_levels'],
            ['aktiv_level_1', 'Spezielle Zugriffe (Wiederaufnahmen)', 'text', 'Berechtigung für Level 1', 'aktiv_levels'],
            ['aktiv_level_2', 'Kassenfunktionen', 'text', 'Berechtigung für Level 2', 'aktiv_levels'],
            ['aktiv_level_3', 'Finanzprüfer', 'text', 'Berechtigung für Level 3', 'aktiv_levels'],
            ['aktiv_level_4', 'NN (reserviert)', 'text', 'Berechtigung für Level 4', 'aktiv_levels'],
            ['aktiv_level_5', 'NN (reserviert)', 'text', 'Berechtigung für Level 5', 'aktiv_levels'],
            ['aktiv_level_6', 'NN (reserviert)', 'text', 'Berechtigung für Level 6', 'aktiv_levels'],
            ['aktiv_level_7', 'NN (reserviert)', 'text', 'Berechtigung für Level 7', 'aktiv_levels'],
            ['aktiv_level_8', 'Finanzverantwortliche in Teams', 'text', 'Berechtigung für Level 8', 'aktiv_levels'],
            ['aktiv_level_9', 'Finanzverantwortliche in Teams', 'text', 'Berechtigung für Level 9', 'aktiv_levels'],
            ['aktiv_level_10', 'Temporäre Aktive', 'text', 'Berechtigung für Level 10', 'aktiv_levels'],
            ['aktiv_level_11', 'Teamleiter', 'text', 'Berechtigung für Level 11', 'aktiv_levels'],
            ['aktiv_level_12', 'Projektleiter', 'text', 'Berechtigung für Level 12', 'aktiv_levels'],
            ['aktiv_level_13', 'Projektleitung mit Budget', 'text', 'Berechtigung für Level 13', 'aktiv_levels'],
            ['aktiv_level_14', 'Zweiter Ressortleiter', 'text', 'Berechtigung für Level 14', 'aktiv_levels'],
            ['aktiv_level_15', 'Ressortleitung', 'text', 'Berechtigung für Level 15', 'aktiv_levels'],
            ['aktiv_level_16', 'Sonderaufgaben', 'text', 'Berechtigung für Level 16', 'aktiv_levels'],
            ['aktiv_level_17', 'Ressortleitung Finanzen', 'text', 'Berechtigung für Level 17', 'aktiv_levels'],
            ['aktiv_level_18', 'Geschäftsführung/Admin', 'text', 'Berechtigung für Level 18', 'aktiv_levels'],
            ['aktiv_level_19', 'Vorstandsmitglied', 'text', 'Berechtigung für Level 19', 'aktiv_levels'],

            // Antragstypen
            ['bart_V_aktiv', '1', 'boolean', 'Typ V (Verfügung) aktiviert', 'antragstypen'],
            ['bart_V_bezeichnung', 'Verfügung', 'text', 'Anzeigename für Typ V', 'antragstypen'],
            ['bart_V_beschreibung', 'Kleinere Ausgaben und operative Entscheidungen', 'text', 'Erklärung des Typs', 'antragstypen'],
            ['bart_V_betrag_aktiv', '1', 'boolean', 'Betragsgrenze für Typ V aktiv', 'antragstypen'],
            ['bart_V_betrag_limit', '500', 'number', 'Betragsgrenze für Typ V in Euro', 'antragstypen'],
            ['bart_V_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ V aktiv', 'antragstypen'],
            ['bart_V_wartezeit_tage', '3', 'number', 'Wartezeit für Typ V in Tagen', 'antragstypen'],
            ['bart_V_freigabe_vereinfacht', '1', 'boolean', 'Vereinfachte Freigabe für Typ V', 'antragstypen'],

            ['bart_R_aktiv', '1', 'boolean', 'Typ R (Ressortbeschluss) aktiviert', 'antragstypen'],
            ['bart_R_bezeichnung', 'Ressortbeschluss', 'text', 'Anzeigename für Typ R', 'antragstypen'],
            ['bart_R_beschreibung', 'Entscheidungen innerhalb eines Ressorts/Bereichs', 'text', 'Erklärung des Typs', 'antragstypen'],
            ['bart_R_betrag_aktiv', '1', 'boolean', 'Betragsgrenze für Typ R aktiv', 'antragstypen'],
            ['bart_R_betrag_limit', '2000', 'number', 'Betragsgrenze für Typ R in Euro', 'antragstypen'],
            ['bart_R_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ R aktiv', 'antragstypen'],
            ['bart_R_wartezeit_tage', '7', 'number', 'Wartezeit für Typ R in Tagen', 'antragstypen'],
            ['bart_R_freigabe_vereinfacht', '1', 'boolean', 'Vereinfachte Freigabe für Typ R', 'antragstypen'],

            ['bart_B_aktiv', '1', 'boolean', 'Typ B (Vorstandsbeschluss) aktiviert', 'antragstypen'],
            ['bart_B_bezeichnung', 'Vorstandsbeschluss', 'text', 'Anzeigename für Typ B', 'antragstypen'],
            ['bart_B_beschreibung', 'Entscheidungen des Vorstands', 'text', 'Erklärung des Typs', 'antragstypen'],
            ['bart_B_betrag_aktiv', '0', 'boolean', 'Betragsgrenze für Typ B aktiv', 'antragstypen'],
            ['bart_B_betrag_limit', '0', 'number', 'Betragsgrenze für Typ B in Euro', 'antragstypen'],
            ['bart_B_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ B aktiv', 'antragstypen'],
            ['bart_B_wartezeit_tage', '14', 'number', 'Wartezeit für Typ B in Tagen', 'antragstypen'],
            ['bart_B_freigabe_vereinfacht', '0', 'boolean', 'Vereinfachte Freigabe für Typ B', 'antragstypen'],

            ['bart_show_betrag_in_liste', '1', 'boolean', 'Beträge in Antragsliste anzeigen', 'antragstypen'],
            ['bart_pflicht_bei_betrag', '100', 'number', 'Ab welchem Betrag ist Betragsangabe Pflicht', 'antragstypen'],

            // Abstimmungsregeln
            ['voting_default_rule', 'einfach', 'text', 'Standard-Abstimmungsregel für neue Anträge', 'voting'],
            ['voting_rule_einfach_label', 'Einfache Mehrheit', 'text', 'Label für einfache Mehrheit', 'voting'],
            ['voting_rule_einfach_desc', 'Mehr Ja als Nein (Enthaltungen zählen nicht)', 'text', 'Beschreibung einfache Mehrheit', 'voting'],
            ['voting_rule_absolut_label', 'Absolute Mehrheit', 'text', 'Label für absolute Mehrheit', 'voting'],
            ['voting_rule_absolut_desc', 'Mehr Ja als Nein+Enthaltung', 'text', 'Beschreibung absolute Mehrheit', 'voting'],
            ['voting_rule_mehrheit_stimmber_label', 'Mehrheit der Stimmberechtigten', 'text', 'Label für Mehrheit Stimmberechtigte', 'voting'],
            ['voting_rule_mehrheit_stimmber_desc', 'Mehr als 50% aller Stimmberechtigten stimmen Ja', 'text', 'Beschreibung Mehrheit Stimmberechtigte', 'voting'],
            ['voting_rule_zweidrittel_label', '2/3-Mehrheit', 'text', 'Label für 2/3-Mehrheit', 'voting'],
            ['voting_rule_zweidrittel_desc', 'Ja-Stimmen >= 2 × Nein-Stimmen', 'text', 'Beschreibung 2/3-Mehrheit', 'voting'],
            ['voting_rule_einstimmig_label', 'Einstimmigkeit', 'text', 'Label für Einstimmigkeit', 'voting'],
            ['voting_rule_einstimmig_desc', 'Alle Abstimmenden müssen Ja stimmen', 'text', 'Beschreibung Einstimmigkeit', 'voting'],
            ['voting_enable_einfach', '1', 'boolean', 'Einfache Mehrheit aktiviert', 'voting'],
            ['voting_enable_absolut', '1', 'boolean', 'Absolute Mehrheit aktiviert', 'voting'],
            ['voting_enable_mehrheit_stimmber', '1', 'boolean', 'Mehrheit der Stimmberechtigten aktiviert', 'voting'],
            ['voting_enable_zweidrittel', '1', 'boolean', '2/3-Mehrheit aktiviert', 'voting'],
            ['voting_enable_einstimmig', '1', 'boolean', 'Einstimmigkeit aktiviert', 'voting'],

            // Terminologie
            ['term_ressort_singular', 'Ressort', 'text', 'Singular-Form für Ressort/Abteilung/Bereich', 'terminology'],
            ['term_ressort_plural', 'Ressorts', 'text', 'Plural-Form für Ressort/Abteilung/Bereich', 'terminology'],
            ['term_antrag_singular', 'Antrag', 'text', 'Singular-Form für Antrag/Beschlussvorlage', 'terminology'],
            ['term_antrag_plural', 'Anträge', 'text', 'Plural-Form für Antrag/Beschlussvorlage', 'terminology'],
            ['term_beschluss_singular', 'Beschluss', 'text', 'Singular-Form für Beschluss', 'terminology'],
            ['term_beschluss_plural', 'Beschlüsse', 'text', 'Plural-Form für Beschluss', 'terminology'],
            ['term_vorstand', 'Vorstand', 'text', 'Bezeichnung für Vorstand/Führungsgremium', 'terminology'],
            ['term_geschaeftsfuehrer', 'Geschäftsführer', 'text', 'Bezeichnung für Geschäftsführer', 'terminology'],
            ['term_ressortleiter', 'Ressortleiter', 'text', 'Bezeichnung für Ressortleiter/Abteilungsleiter', 'terminology'],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO svconfig (config_key, config_value, config_type, description, category)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($config_entries as $entry) {
            $stmt->execute($entry);
            echo ".";
        }

        echo "<p style='color: green;'>✓ " . count($config_entries) . " Konfigurationseinträge eingefügt!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ System-Konfiguration existiert bereits - überspringe</p>";
    }

    // Demo-Ressorts einfügen (nur wenn leer)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM svressorts");
    if ($stmt->fetch()['count'] == 0) {
        echo "<p>Füge Demo-Ressorts ein...</p>";

        $ressorts = [
            ['Finanzen', 10],
            ['Personal', 20],
            ['IT & Digitalisierung', 30],
            ['Marketing & Kommunikation', 40],
            ['Veranstaltungen', 50],
            ['Mitgliederverwaltung', 60],
            ['Infrastruktur', 70],
            ['Bildung & Weiterbildung', 80],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO svressorts (Ressort, Reihenfolge, aktiv)
            VALUES (?, ?, 1)
        ");

        foreach ($ressorts as $ressort) {
            $stmt->execute($ressort);
            echo ".";
        }

        // Codes automatisch generieren (Rxx-Format)
        $pdo->exec("UPDATE svressorts SET Code = CONCAT('R', LPAD(ID, 2, '0')) WHERE Code IS NULL");

        echo "<p style='color: green;'>✓ " . count($ressorts) . " Demo-Ressorts eingefügt (mit Codes)!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Ressorts existieren bereits - überspringe</p>";
    }

    // Default-Admin anlegen (nur wenn members-Tabelle leer ist)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM svmembers");
    if ($stmt->fetch()['count'] == 0) {
        echo "<p>Erstelle Default-Admin-User...</p>";

        // Passwort: admin123 (BITTE NACH ERSTEM LOGIN ÄNDERN!)
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO svmembers (email, password_hash, first_name, last_name, role, is_admin, is_active, membership_number)
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
        echo "<p style='color: #856404;'><strong>WICHTIG:</strong> Bitte ändere das Passwort nach dem ersten Login!</p>";
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
