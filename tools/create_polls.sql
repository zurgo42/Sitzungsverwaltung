-- Migration: Terminplanung/Umfragen-System
-- Erstellt: 2025-11-18
-- Beschreibung: Tabellen für das Terminplanungs-Tool

-- Umfragen/Terminplanung-Tabelle
CREATE TABLE IF NOT EXISTS polls (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    finalized_at DATETIME DEFAULT NULL,
    FOREIGN KEY (created_by_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by_member_id),
    INDEX idx_meeting (meeting_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Terminvorschläge-Tabelle
CREATE TABLE IF NOT EXISTS poll_dates (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teilnehmer an Umfragen
CREATE TABLE IF NOT EXISTS poll_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    member_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (poll_id, member_id),
    INDEX idx_poll (poll_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abstimmungen zu Terminvorschlägen
CREATE TABLE IF NOT EXISTS poll_responses (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
