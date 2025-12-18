-- Migration: Externe Teilnehmer für Umfragen
-- Erstellt: 2025-12-18
-- Beschreibung: Ermöglicht externen Nutzern (ohne Account) die Teilnahme an Umfragen via Link

-- Tabelle für externe Teilnehmer (werden zu svexternal_participants)
CREATE TABLE IF NOT EXISTS external_participants (
    external_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_type ENUM('termine', 'meinungsbild') NOT NULL COMMENT 'Typ der Umfrage',
    poll_id INT NOT NULL COMMENT 'ID der Umfrage (svpolls oder svopinion_polls)',

    -- Persönliche Daten
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mnr VARCHAR(50) DEFAULT NULL COMMENT 'Optional: Mitgliedsnummer falls bekannt',

    -- Identifikation & Tracking
    session_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Eindeutiger Token für anonyme Teilnahme',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP-Adresse bei Registrierung',

    -- Datenschutz & Löschung
    consent_given BOOLEAN DEFAULT TRUE COMMENT 'Einwilligung zur Datenspeicherung',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Für 6-Monats-Löschung',

    -- Indizes für Performance
    INDEX idx_poll_type_id (poll_type, poll_id),
    INDEX idx_session_token (session_token),
    INDEX idx_email (email),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Externe Teilnehmer ohne Account - werden nach 6 Monaten gelöscht';

-- Erweitere poll_responses um externe Teilnehmer
-- WICHTIG: Diese ALTER TABLE Statements müssen nach dem Umbenennen mit sv-Präfix ausgeführt werden
-- oder die Tabellennamen entsprechend angepasst werden

-- Option 1: Wenn Tabellen bereits sv-Präfix haben
ALTER TABLE svpoll_responses
    ADD COLUMN external_participant_id INT DEFAULT NULL AFTER member_id,
    ADD FOREIGN KEY fk_poll_external (external_participant_id)
        REFERENCES svexternal_participants(external_id) ON DELETE CASCADE,
    DROP INDEX unique_vote,
    ADD UNIQUE KEY unique_vote_member (poll_id, date_id, member_id),
    ADD UNIQUE KEY unique_vote_external (poll_id, date_id, external_participant_id);

-- Option 2: Wenn Tabellen noch KEIN sv-Präfix haben (auskommentiert)
-- ALTER TABLE poll_responses
--     ADD COLUMN external_participant_id INT DEFAULT NULL AFTER member_id,
--     ADD FOREIGN KEY fk_poll_external (external_participant_id)
--         REFERENCES external_participants(external_id) ON DELETE CASCADE,
--     DROP INDEX unique_vote,
--     ADD UNIQUE KEY unique_vote_member (poll_id, date_id, member_id),
--     ADD UNIQUE KEY unique_vote_external (poll_id, date_id, external_participant_id);

-- Erweitere opinion_responses um externe Teilnehmer
ALTER TABLE svopinion_responses
    ADD COLUMN external_participant_id INT DEFAULT NULL AFTER member_id,
    ADD FOREIGN KEY fk_opinion_external (external_participant_id)
        REFERENCES svexternal_participants(external_id) ON DELETE CASCADE,
    ADD INDEX idx_external (external_participant_id);

-- Mache member_id in poll_responses optional (war vorher NOT NULL)
ALTER TABLE svpoll_responses
    MODIFY COLUMN member_id INT DEFAULT NULL;

-- Mache member_id in opinion_responses optional (falls noch NOT NULL)
ALTER TABLE svopinion_responses
    MODIFY COLUMN member_id INT DEFAULT NULL;

-- Check Constraint würde sicherstellen, dass entweder member_id ODER external_participant_id gesetzt ist
-- MySQL 8.0.16+ unterstützt CHECK constraints, aber für Kompatibilität verzichten wir darauf
-- und prüfen dies in der Application Logic
