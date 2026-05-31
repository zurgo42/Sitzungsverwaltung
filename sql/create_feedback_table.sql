-- Feedback-System für Testphase
-- Tabelle für Benutzer-Feedback und Fehlermeldungen

CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    feedback_text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,

    INDEX idx_member_id (member_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kommentar
ALTER TABLE feedback COMMENT='Feedback und Fehlermeldungen für Testphase';
