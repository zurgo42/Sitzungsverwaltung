-- =========================================================
-- EXTERNAL ACCESS LOG
-- Logging aller externen Zugriffe auf Terminumfragen und Meinungsbilder
-- =========================================================

CREATE TABLE IF NOT EXISTS svexternal_access_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    access_type ENUM('member_login', 'invalid_mnr', 'external_registration', 'registration_error') NOT NULL COMMENT 'Art des Zugriffs',
    poll_type ENUM('termine', 'meinungsbild') NOT NULL COMMENT 'Art der Umfrage',
    poll_id INT NOT NULL COMMENT 'ID der Umfrage',

    -- Erfolgreicher Member-Login
    member_id INT DEFAULT NULL COMMENT 'Member-ID bei erfolgreichem MNr-Login',

    -- Externe Registrierung
    external_participant_id INT DEFAULT NULL COMMENT 'Externe Teilnehmer-ID bei Registrierung',

    -- Eingegebene Daten
    mnr VARCHAR(50) DEFAULT NULL COMMENT 'Eingegebene Mitgliedsnummer',
    email VARCHAR(255) DEFAULT NULL COMMENT 'Eingegebene E-Mail',
    first_name VARCHAR(100) DEFAULT NULL COMMENT 'Eingegebener Vorname',
    last_name VARCHAR(100) DEFAULT NULL COMMENT 'Eingegebener Nachname',

    -- Technische Daten
    ip_address VARCHAR(45) NOT NULL COMMENT 'IP-Adresse des Zugriffs',
    user_agent TEXT COMMENT 'Browser User-Agent',

    -- Status
    success BOOLEAN DEFAULT TRUE COMMENT 'Ob der Zugriff erfolgreich war',
    error_message TEXT DEFAULT NULL COMMENT 'Fehlermeldung bei Misserfolg',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL,
    FOREIGN KEY (external_participant_id) REFERENCES svexternal_participants(external_id) ON DELETE SET NULL,

    -- Indizes für schnelle Suche
    INDEX idx_access_type (access_type),
    INDEX idx_poll (poll_type, poll_id),
    INDEX idx_member (member_id),
    INDEX idx_external (external_participant_id),
    INDEX idx_ip (ip_address),
    INDEX idx_created (created_at),
    INDEX idx_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
