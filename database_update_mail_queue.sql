-- ========================================
-- Database Update: Mail Queue System
-- Erstellt: 17.11.2025
-- ========================================
--
-- Dieses Script fügt die mail_queue Tabelle hinzu für Queue-basiertes
-- E-Mail-System. Kann sicher auf bestehenden Installationen ausgeführt werden.
--
-- VERWENDUNG:
-- 1. Via phpMyAdmin: SQL-Tab -> Paste -> Ausführen
-- 2. Via MySQL CLI: mysql -u username -p databasename < database_update_mail_queue.sql
--

-- E-Mail-Warteschlange für Queue-basiertes Mail-System
CREATE TABLE IF NOT EXISTS mail_queue (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update erfolgreich!
SELECT 'Mail-Queue-Tabelle wurde erfolgreich erstellt/aktualisiert!' as Status;
