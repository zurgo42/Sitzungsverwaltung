-- Migration: Externe Teilnehmer für Umfragen
-- Erstellt: 2025-12-18
-- Beschreibung: Ermöglicht externen Nutzern (ohne Account) die Teilnahme an Umfragen via Link

-- Tabelle für externe Teilnehmer (mit sv-Präfix für Konsistenz)
CREATE TABLE IF NOT EXISTS svexternal_participants (
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

-- Prüfen ob svpoll_responses existiert, dann erweitern
-- HINWEIS: Falls die Tabelle noch ohne sv-Präfix existiert (polls statt svpolls),
-- muss der Tabellenname entsprechend angepasst werden

-- 1. Prüfen ob unique_vote Index existiert und ggf. löschen
SET @exist := (SELECT COUNT(*)
               FROM information_schema.statistics
               WHERE table_schema = DATABASE()
               AND table_name = 'svpoll_responses'
               AND index_name = 'unique_vote');
SET @sqlstmt := IF(@exist > 0,
    'ALTER TABLE svpoll_responses DROP INDEX unique_vote',
    'SELECT "Index unique_vote existiert nicht" AS Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Spalte external_participant_id hinzufügen (falls noch nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.columns
               WHERE table_schema = DATABASE()
               AND table_name = 'svpoll_responses'
               AND column_name = 'external_participant_id');
SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE svpoll_responses ADD COLUMN external_participant_id INT DEFAULT NULL AFTER member_id',
    'SELECT "Spalte external_participant_id existiert bereits" AS Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. member_id optional machen (war vorher NOT NULL)
ALTER TABLE svpoll_responses MODIFY COLUMN member_id INT DEFAULT NULL;

-- 4. Foreign Key hinzufügen (falls noch nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.table_constraints
               WHERE table_schema = DATABASE()
               AND table_name = 'svpoll_responses'
               AND constraint_name = 'fk_poll_external');
SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE svpoll_responses ADD CONSTRAINT fk_poll_external
     FOREIGN KEY (external_participant_id) REFERENCES svexternal_participants(external_id) ON DELETE CASCADE',
    'SELECT "Foreign Key fk_poll_external existiert bereits" AS Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Unique Constraints hinzufügen (falls noch nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.statistics
               WHERE table_schema = DATABASE()
               AND table_name = 'svpoll_responses'
               AND index_name = 'unique_vote_member');
SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE svpoll_responses ADD UNIQUE KEY unique_vote_member (poll_id, date_id, member_id)',
    'SELECT "Index unique_vote_member existiert bereits" AS Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*)
               FROM information_schema.statistics
               WHERE table_schema = DATABASE()
               AND table_name = 'svpoll_responses'
               AND index_name = 'unique_vote_external');
SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE svpoll_responses ADD UNIQUE KEY unique_vote_external (poll_id, date_id, external_participant_id)',
    'SELECT "Index unique_vote_external existiert bereits" AS Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- === OPINION RESPONSES ===

-- 1. Spalte external_participant_id hinzufügen zu opinion_responses (falls noch nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.columns
               WHERE table_schema = DATABASE()
               AND table_name = 'svopinion_responses'
               AND column_name = 'external_participant_id');
SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE svopinion_responses ADD COLUMN external_participant_id INT DEFAULT NULL AFTER member_id',
    'SELECT "Spalte external_participant_id in svopinion_responses existiert bereits" AS Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. member_id optional machen (falls noch NOT NULL)
ALTER TABLE svopinion_responses MODIFY COLUMN member_id INT DEFAULT NULL;

-- 3. Foreign Key hinzufügen (falls noch nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.table_constraints
               WHERE table_schema = DATABASE()
               AND table_name = 'svopinion_responses'
               AND constraint_name = 'fk_opinion_external');
SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE svopinion_responses ADD CONSTRAINT fk_opinion_external
     FOREIGN KEY (external_participant_id) REFERENCES svexternal_participants(external_id) ON DELETE CASCADE',
    'SELECT "Foreign Key fk_opinion_external existiert bereits" AS Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Index hinzufügen (falls noch nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.statistics
               WHERE table_schema = DATABASE()
               AND table_name = 'svopinion_responses'
               AND index_name = 'idx_external');
SET @sqlstmt := IF(@exist = 0,
    'ALTER TABLE svopinion_responses ADD INDEX idx_external (external_participant_id)',
    'SELECT "Index idx_external in svopinion_responses existiert bereits" AS Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

