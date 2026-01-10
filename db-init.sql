-- =========================================================
-- Datenbank-Initialisierungsskript für Sitzungsverwaltung
-- Erstellt: 2025-12-21
-- Version: 2.0
-- =========================================================
-- Dieses Skript erstellt alle benötigten Tabellen für die
-- Sitzungsverwaltung mit sv-Präfix für bessere Portabilität.
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- =========================================================
-- 1. MEMBERS (Mitglieder)
-- =========================================================

CREATE TABLE IF NOT EXISTS svmembers (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    membership_number VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    role ENUM('vorstand', 'gf', 'assistenz', 'fuehrungsteam', 'mitglied') DEFAULT 'mitglied',
    status ENUM('active', 'inactive', 'guest') DEFAULT 'active',
    password_hash VARCHAR(255) DEFAULT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_membership_number (membership_number),
    INDEX idx_status (status),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2. MEETINGS (Sitzungen)
-- =========================================================

CREATE TABLE IF NOT EXISTS svmeetings (
    meeting_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    meeting_date DATETIME NOT NULL,
    meeting_end DATETIME DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    video_link VARCHAR(500) DEFAULT NULL,
    meeting_type ENUM('regular', 'special', 'online', 'hybrid') DEFAULT 'regular',
    status ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
    created_by_member_id INT NOT NULL,
    protocol_intern TEXT DEFAULT NULL COMMENT 'Internes Protokoll (nicht öffentlich)',
    protocol_version INT DEFAULT 0,
    protocol_finalized_at DATETIME DEFAULT NULL,
    protocol_finalized_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_member_id) REFERENCES svmembers(member_id) ON DELETE RESTRICT,
    FOREIGN KEY (protocol_finalized_by) REFERENCES svmembers(member_id) ON DELETE SET NULL,
    INDEX idx_meeting_date (meeting_date),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3. MEETING PARTICIPANTS (Sitzungsteilnehmer)
-- =========================================================

CREATE TABLE IF NOT EXISTS svmeeting_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    member_id INT NOT NULL,
    participation_status ENUM('invited', 'accepted', 'declined', 'tentative', 'attended') DEFAULT 'invited',
    attended BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_participation (meeting_id, member_id),
    INDEX idx_meeting (meeting_id),
    INDEX idx_member (member_id),
    INDEX idx_status (participation_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 4. AGENDA ITEMS (Tagesordnungspunkte)
-- =========================================================

CREATE TABLE IF NOT EXISTS svagenda_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    item_order INT NOT NULL DEFAULT 0,
    duration_minutes INT DEFAULT NULL,
    responsible_member_id INT DEFAULT NULL,
    item_type ENUM('information', 'discussion', 'decision', 'other') DEFAULT 'discussion',
    status ENUM('pending', 'discussed', 'decided', 'postponed') DEFAULT 'pending',
    decision TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (responsible_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
    INDEX idx_meeting (meeting_id),
    INDEX idx_order (meeting_id, item_order),
    INDEX idx_responsible (responsible_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. AGENDA COMMENTS (Kommentare zu TOPs)
-- =========================================================

CREATE TABLE IF NOT EXISTS svagenda_comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    member_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    attachment_filename VARCHAR(500) DEFAULT NULL,
    attachment_original_name VARCHAR(255) DEFAULT NULL,
    attachment_size INT DEFAULT NULL,
    attachment_mime_type VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    INDEX idx_item (item_id),
    INDEX idx_member (member_id),
    INDEX idx_attachment (attachment_filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6. PROTOCOLS (Protokolle)
-- =========================================================

CREATE TABLE IF NOT EXISTS svprotocols (
    protocol_id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    version INT DEFAULT 1,
    status ENUM('draft', 'review', 'approved', 'published') DEFAULT 'draft',
    created_by_member_id INT NOT NULL,
    approved_by_member_id INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_member_id) REFERENCES svmembers(member_id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
    INDEX idx_meeting (meeting_id),
    INDEX idx_status (status),
    INDEX idx_version (meeting_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 7. PROTOCOL CHANGE REQUESTS (Änderungsanträge)
-- =========================================================

CREATE TABLE IF NOT EXISTS svprotocol_change_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    protocol_id INT NOT NULL,
    requested_by_member_id INT NOT NULL,
    section TEXT NOT NULL,
    suggested_change TEXT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    reviewed_by_member_id INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    reviewer_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (protocol_id) REFERENCES svprotocols(protocol_id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
    INDEX idx_protocol (protocol_id),
    INDEX idx_status (status),
    INDEX idx_requester (requested_by_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 8. TODOS (Aufgaben)
-- =========================================================

CREATE TABLE IF NOT EXISTS svtodos (
    todo_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    meeting_id INT DEFAULT NULL,
    agenda_item_id INT DEFAULT NULL,
    assigned_to_member_id INT NOT NULL,
    created_by_member_id INT NOT NULL,
    due_date DATE DEFAULT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE SET NULL,
    FOREIGN KEY (agenda_item_id) REFERENCES svagenda_items(item_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    INDEX idx_assigned (assigned_to_member_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_meeting (meeting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 9. TODO LOG (Änderungsprotokoll für TODOs)
-- =========================================================

CREATE TABLE IF NOT EXISTS svtodo_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    todo_id INT NOT NULL,
    changed_by_member_id INT NOT NULL,
    change_type ENUM('created', 'updated', 'status_changed', 'assigned', 'completed') NOT NULL,
    old_value TEXT,
    new_value TEXT,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (todo_id) REFERENCES svtodos(todo_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    INDEX idx_todo (todo_id),
    INDEX idx_member (changed_by_member_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 10. ADMIN LOG (Systemprotokoll)
-- =========================================================

CREATE TABLE IF NOT EXISTS svadmin_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
    INDEX idx_member (member_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 11. DOCUMENTS (Dokumentenverwaltung)
-- =========================================================

CREATE TABLE IF NOT EXISTS svdocuments (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) DEFAULT NULL COMMENT 'Dateiname (bei Upload) - NULL bei externen Links',
    original_filename VARCHAR(255) DEFAULT NULL COMMENT 'Original-Dateiname (bei Upload) - NULL bei externen Links',
    filepath VARCHAR(500) DEFAULT NULL COMMENT 'Pfad zur Datei (bei Upload) - NULL bei externen Links',
    external_url VARCHAR(1000) DEFAULT NULL COMMENT 'URL zu externer Datei (statt Upload)',
    filesize INT DEFAULT NULL COMMENT 'Dateigröße (bei Upload) - NULL bei externen Links',
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
    INDEX idx_category (category),
    INDEX idx_access_level (access_level),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_title (title),
    INDEX idx_external_url (external_url(255)),
    FULLTEXT INDEX idx_search (title, description, keywords),
    FOREIGN KEY (uploaded_by_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 12. DOCUMENT DOWNLOADS (Download-Tracking)
-- =========================================================

CREATE TABLE IF NOT EXISTS svdocument_downloads (
    download_id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    member_id INT DEFAULT NULL,
    ip_address VARCHAR(45),
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES svdocuments(document_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
    INDEX idx_document (document_id),
    INDEX idx_member (member_id),
    INDEX idx_downloaded (downloaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 13. MAIL QUEUE (E-Mail-Warteschlange)
-- =========================================================

CREATE TABLE IF NOT EXISTS svmail_queue (
    mail_id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_member_id INT DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    html_body TEXT,
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    priority INT DEFAULT 5,
    attempts INT DEFAULT 0,
    last_error TEXT,
    scheduled_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_priority (priority),
    INDEX idx_recipient (recipient_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 14. TERMINPLANUNG - POLLS (Terminumfragen)
-- =========================================================

CREATE TABLE IF NOT EXISTS svpolls (
    poll_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by_member_id INT NOT NULL,
    meeting_id INT DEFAULT NULL,
    target_type ENUM('individual', 'list', 'public') DEFAULT 'individual' COMMENT 'Link, Eingeladene, Öffentlich',
    access_token VARCHAR(64) UNIQUE DEFAULT NULL COMMENT 'Für individual/public Zugriff',
    location VARCHAR(255) DEFAULT NULL,
    video_link VARCHAR(500) DEFAULT NULL,
    duration INT DEFAULT NULL COMMENT 'Dauer in Minuten',
    status ENUM('open', 'closed', 'finalized') DEFAULT 'open',
    final_date_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ends_at DATETIME DEFAULT NULL COMMENT 'Umfrage läuft bis',
    finalized_at DATETIME DEFAULT NULL,
    FOREIGN KEY (created_by_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES svmeetings(meeting_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by_member_id),
    INDEX idx_meeting (meeting_id),
    INDEX idx_created_at (created_at),
    INDEX idx_target_type (target_type),
    INDEX idx_access_token (access_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 15. POLL DATES (Terminvorschläge)
-- =========================================================

CREATE TABLE IF NOT EXISTS svpoll_dates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 16. POLL PARTICIPANTS (Eingeladene Teilnehmer)
-- =========================================================

CREATE TABLE IF NOT EXISTS svpoll_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    member_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES svpolls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (poll_id, member_id),
    INDEX idx_poll (poll_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 17. POLL RESPONSES (Abstimmungen)
-- =========================================================

CREATE TABLE IF NOT EXISTS svpoll_responses (
    response_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    date_id INT NOT NULL,
    member_id INT DEFAULT NULL,
    external_participant_id INT DEFAULT NULL,
    vote TINYINT NOT NULL COMMENT '-1=Nein, 0=Vielleicht, 1=Ja',
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES svpolls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (date_id) REFERENCES svpoll_dates(date_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote_member (poll_id, date_id, member_id),
    UNIQUE KEY unique_vote_external (poll_id, date_id, external_participant_id),
    INDEX idx_poll (poll_id),
    INDEX idx_date (date_id),
    INDEX idx_member (member_id),
    INDEX idx_external (external_participant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 18. MEINUNGSBILD - ANSWER TEMPLATES (Antwortvorlagen)
-- =========================================================

CREATE TABLE IF NOT EXISTS svopinion_answer_templates (
    template_id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(100) NOT NULL,
    description TEXT,
    option_1 VARCHAR(255),
    option_2 VARCHAR(255),
    option_3 VARCHAR(255),
    option_4 VARCHAR(255),
    option_5 VARCHAR(255),
    option_6 VARCHAR(255),
    option_7 VARCHAR(255),
    option_8 VARCHAR(255),
    option_9 VARCHAR(255),
    option_10 VARCHAR(255),
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 19. OPINION POLLS (Meinungsumfragen)
-- =========================================================

CREATE TABLE IF NOT EXISTS svopinion_polls (
    poll_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    creator_member_id INT NOT NULL,
    target_type ENUM('individual', 'list', 'public') DEFAULT 'individual' COMMENT 'Link, Eingeladene, Öffentlich',
    list_id INT DEFAULT NULL COMMENT 'Optional: Meeting-ID für Teilnehmerliste',
    template_id INT DEFAULT NULL,
    access_token VARCHAR(64) UNIQUE DEFAULT NULL COMMENT 'Für individual/public Zugriff',
    allow_multiple_answers BOOLEAN DEFAULT FALSE,
    is_anonymous BOOLEAN DEFAULT FALSE,
    duration_days INT DEFAULT 14,
    show_intermediate_after_days INT DEFAULT 7,
    delete_after_days INT DEFAULT 30,
    status ENUM('active', 'ended', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME DEFAULT NULL,
    FOREIGN KEY (creator_member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES svopinion_answer_templates(template_id) ON DELETE SET NULL,
    INDEX idx_creator (creator_member_id),
    INDEX idx_status (status),
    INDEX idx_target_type (target_type),
    INDEX idx_access_token (access_token),
    INDEX idx_ends_at (ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 20. OPINION POLL OPTIONS (Antwortoptionen)
-- =========================================================

CREATE TABLE IF NOT EXISTS svopinion_poll_options (
    option_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    option_text VARCHAR(500) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES svopinion_polls(poll_id) ON DELETE CASCADE,
    INDEX idx_poll (poll_id),
    INDEX idx_order (poll_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 21. OPINION POLL PARTICIPANTS (Eingeladene)
-- =========================================================

CREATE TABLE IF NOT EXISTS svopinion_poll_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    member_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES svopinion_polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (poll_id, member_id),
    INDEX idx_poll (poll_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 22. OPINION RESPONSES (Antworten)
-- =========================================================

CREATE TABLE IF NOT EXISTS svopinion_responses (
    response_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    member_id INT DEFAULT NULL,
    external_participant_id INT DEFAULT NULL,
    session_token VARCHAR(64) DEFAULT NULL COMMENT 'Legacy: Für alte anonyme Teilnehmer',
    free_text TEXT,
    force_anonymous BOOLEAN DEFAULT FALSE COMMENT 'User will anonym bleiben',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES svopinion_polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE,
    INDEX idx_poll (poll_id),
    INDEX idx_member (member_id),
    INDEX idx_external (external_participant_id),
    INDEX idx_session (session_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 23. OPINION RESPONSE OPTIONS (Gewählte Optionen)
-- =========================================================

CREATE TABLE IF NOT EXISTS svopinion_response_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    response_id INT NOT NULL,
    option_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (response_id) REFERENCES svopinion_responses(response_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES svopinion_poll_options(option_id) ON DELETE CASCADE,
    UNIQUE KEY unique_response_option (response_id, option_id),
    INDEX idx_response (response_id),
    INDEX idx_option (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 24. EXTERNAL PARTICIPANTS (Externe Teilnehmer)
-- =========================================================

CREATE TABLE IF NOT EXISTS svexternal_participants (
    external_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_type ENUM('termine', 'meinungsbild') NOT NULL COMMENT 'Typ der Umfrage',
    poll_id INT NOT NULL COMMENT 'ID der Umfrage',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mnr VARCHAR(50) DEFAULT NULL COMMENT 'Optional: Mitgliedsnummer',
    session_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Eindeutiger Token',
    ip_address VARCHAR(45) DEFAULT NULL,
    consent_given BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_poll_type_id (poll_type, poll_id),
    INDEX idx_session_token (session_token),
    INDEX idx_email (email),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Externe Teilnehmer - werden nach 6 Monaten gelöscht';

-- =========================================================
-- FOREIGN KEYS für externe Teilnehmer nachrüsten
-- =========================================================

-- svpoll_responses: FK zu svexternal_participants
ALTER TABLE svpoll_responses
ADD CONSTRAINT fk_poll_external
FOREIGN KEY (external_participant_id) REFERENCES svexternal_participants(external_id) ON DELETE CASCADE;

-- svopinion_responses: FK zu svexternal_participants
ALTER TABLE svopinion_responses
ADD CONSTRAINT fk_opinion_external
FOREIGN KEY (external_participant_id) REFERENCES svexternal_participants(external_id) ON DELETE CASCADE;

-- =========================================================
-- STANDARD-DATEN EINFÜGEN
-- =========================================================

-- Antwortvorlagen für Meinungsbilder
INSERT INTO svopinion_answer_templates (template_name, description, option_1, option_2, option_3, option_4, option_5, is_system) VALUES
('Ja/Nein', 'Einfache Ja/Nein Abstimmung', 'Ja', 'Nein', NULL, NULL, NULL, TRUE),
('Ja/Nein/Enthaltung', 'Abstimmung mit Enthaltung', 'Ja', 'Nein', 'Enthaltung', NULL, NULL, TRUE),
('Zustimmung (5-stufig)', '5-stufige Zustimmungsskala', 'Stimme voll zu', 'Stimme zu', 'Neutral', 'Stimme nicht zu', 'Stimme überhaupt nicht zu', TRUE),
('Bewertung (5 Sterne)', '5-Sterne Bewertung', '⭐⭐⭐⭐⭐ Ausgezeichnet', '⭐⭐⭐⭐ Gut', '⭐⭐⭐ Mittel', '⭐⭐ Schlecht', '⭐ Sehr schlecht', TRUE),
('Priorität', 'Prioritätsbewertung', 'Sehr hoch', 'Hoch', 'Mittel', 'Niedrig', 'Sehr niedrig', TRUE),
('Häufigkeit', 'Häufigkeitsangabe', 'Immer', 'Oft', 'Manchmal', 'Selten', 'Nie', TRUE),
('Zufriedenheit', 'Zufriedenheitsskala', 'Sehr zufrieden', 'Zufrieden', 'Neutral', 'Unzufrieden', 'Sehr unzufrieden', TRUE),
('Passt/Passt nicht', 'Einfache Passt-Bewertung', 'Passt sehr gut', 'Passt', 'Geht so', 'Passt eher nicht', 'Passt gar nicht', TRUE),
('Erweiterte Passt-Bewertung', 'Detaillierte Bewertung mit neutralen Optionen', 'Passt sehr gut', 'Passt', 'Geht so', 'Passt eher nicht', 'Passt gar nicht', TRUE);

-- Letztes Template mit allen 10 Optionen
UPDATE svopinion_answer_templates
SET option_6 = 'Unentschieden',
    option_7 = 'Weiß nicht',
    option_8 = 'Ich möchte das nicht beantworten'
WHERE template_name = 'Erweiterte Passt-Bewertung';

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- =========================================================
-- ENDE DER INITIALISIERUNG
-- =========================================================
