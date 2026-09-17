-- Migration: Benachrichtigungs-Center
-- Erstellt am: 2026-04-27
-- Beschreibung: Zentrale Benachrichtigungen mit Browser-Push

-- Tabelle für Notifications
CREATE TABLE IF NOT EXISTS svnotifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL COMMENT 'Empfänger',
    type ENUM('meeting', 'todo', 'comment', 'assignment', 'reminder', 'system') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) DEFAULT NULL COMMENT 'URL zum relevanten Element',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Optional: Referenzen zu verknüpften Objekten
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für Push-Subscriptions (Browser-Push)
CREATE TABLE IF NOT EXISTS svpush_subscriptions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
