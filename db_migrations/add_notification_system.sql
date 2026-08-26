-- ============================================================
-- Migration: E-Mail-Benachrichtigungssystem
-- ============================================================

-- Individuelle Benachrichtigungseinstellungen je Mitglied
CREATE TABLE IF NOT EXISTS svnotification_prefs (
    member_id   INT         NOT NULL,
    event_type  VARCHAR(40) NOT NULL,
    email       TINYINT(1)  NOT NULL DEFAULT 0,
    PRIMARY KEY (member_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Staging-Tabelle für ausstehende Benachrichtigungen
-- is_digest=0: Sofort-Versand, is_digest=1: Tageszusammenfassung
CREATE TABLE IF NOT EXISTS svmail_notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT          NOT NULL,
    event_type  VARCHAR(40)  NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    body_text   TEXT,
    body_html   MEDIUMTEXT,
    is_digest   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at     DATETIME     DEFAULT NULL,
    INDEX idx_pending (is_digest, sent_at),
    INDEX idx_member  (member_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Konfigurationseintrag: Stunde für Digest-Versand (Standard: 18 Uhr)
INSERT INTO svconfig (config_key, config_value, config_type, description, category)
VALUES ('notification_digest_hour', '18', 'number', 'Stunde für den Digest-Versand (0–23)', 'notifications')
ON DUPLICATE KEY UPDATE config_key = config_key;
