-- Migration: Meinungsbild-Tool
-- Erstellt: 2025-11-18
-- Beschreibung: Vollständiges Meinungsbild/Umfrage-System

-- Tabelle für Antwort-Templates (vorgefertigte Antwortsets)
CREATE TABLE IF NOT EXISTS opinion_answer_templates (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für Meinungsbilder/Umfragen
CREATE TABLE IF NOT EXISTS opinion_polls (
    poll_id INT PRIMARY KEY AUTO_INCREMENT,
    title TEXT NOT NULL COMMENT 'Die gestellte Frage',
    creator_member_id INT NOT NULL,
    target_type ENUM('individual', 'list', 'public') NOT NULL DEFAULT 'individual',
    list_id INT DEFAULT NULL COMMENT 'Bezieht sich auf meeting_id falls target_type=list',
    access_token VARCHAR(64) DEFAULT NULL COMMENT 'Eindeutiger Token für individual-Links',
    template_id INT DEFAULT NULL COMMENT 'Gewähltes Antwort-Template',
    allow_multiple_answers TINYINT(1) DEFAULT 0,
    is_anonymous TINYINT(1) DEFAULT 0 COMMENT 'Ob Namen gezeigt werden',
    duration_days INT DEFAULT 14 COMMENT 'Laufzeit in Tagen',
    show_intermediate_after_days INT DEFAULT 7 COMMENT 'Ab wann Zwischenergebnisse',
    delete_after_days INT DEFAULT 30 COMMENT 'Löschung nach X Tagen',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME DEFAULT NULL,
    delete_at DATETIME DEFAULT NULL,
    status ENUM('active', 'ended', 'deleted') DEFAULT 'active',
    FOREIGN KEY (creator_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_access_token (access_token),
    INDEX idx_status (status),
    INDEX idx_ends_at (ends_at),
    INDEX idx_target_type (target_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für individuelle Antwortoptionen pro Umfrage
CREATE TABLE IF NOT EXISTS opinion_poll_options (
    option_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES opinion_polls(poll_id) ON DELETE CASCADE,
    INDEX idx_poll (poll_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für Teilnehmer (bei list-Typ)
CREATE TABLE IF NOT EXISTS opinion_poll_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    member_id INT NOT NULL,
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES opinion_polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (poll_id, member_id),
    INDEX idx_poll (poll_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für Antworten
CREATE TABLE IF NOT EXISTS opinion_responses (
    response_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    member_id INT DEFAULT NULL COMMENT 'NULL bei public/anonymous',
    session_token VARCHAR(64) DEFAULT NULL COMMENT 'Für public ohne Login',
    free_text TEXT,
    force_anonymous TINYINT(1) DEFAULT 0 COMMENT 'Teilnehmer will anonym bleiben',
    responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES opinion_polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL,
    INDEX idx_poll (poll_id),
    INDEX idx_member (member_id),
    INDEX idx_session (session_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für gewählte Antwortoptionen (unterstützt Mehrfachantworten)
CREATE TABLE IF NOT EXISTS opinion_response_options (
    response_option_id INT PRIMARY KEY AUTO_INCREMENT,
    response_id INT NOT NULL,
    option_id INT NOT NULL,
    FOREIGN KEY (response_id) REFERENCES opinion_responses(response_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES opinion_poll_options(option_id) ON DELETE CASCADE,
    UNIQUE KEY unique_response_option (response_id, option_id),
    INDEX idx_response (response_id),
    INDEX idx_option (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger: Automatisch ends_at und delete_at berechnen
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS set_opinion_poll_dates
BEFORE INSERT ON opinion_polls
FOR EACH ROW
BEGIN
    IF NEW.ends_at IS NULL THEN
        SET NEW.ends_at = DATE_ADD(NEW.created_at, INTERVAL NEW.duration_days DAY);
    END IF;
    IF NEW.delete_at IS NULL THEN
        SET NEW.delete_at = DATE_ADD(NEW.created_at, INTERVAL NEW.delete_after_days DAY);
    END IF;
    IF NEW.access_token IS NULL AND NEW.target_type = 'individual' THEN
        SET NEW.access_token = SHA2(CONCAT(UUID(), RAND()), 256);
    END IF;
END$$

DELIMITER ;
