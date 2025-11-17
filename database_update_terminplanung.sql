-- ============================================
-- DATENBANK-UPDATE FÜR SITZUNGSVERWALTUNG
-- Version 4.0 - 17.11.2025
-- Feature: Terminplanung / Umfragen
-- ============================================
--
-- Dieses Script fügt die Datenbank-Tabellen für die
-- Terminplanung hinzu:
-- - polls (Umfragen)
-- - poll_dates (Terminvorschläge)
-- - poll_responses (Teilnehmer-Antworten)
--
-- WICHTIG: Führen Sie dieses Script aus, BEVOR Sie die neuen PHP-Dateien hochladen!
-- ============================================

-- 1. Tabelle für Umfragen/Terminplanungen
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS polls (
    poll_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by_member_id INT NOT NULL,
    meeting_id INT DEFAULT NULL,
    -- Globale Angaben für alle Terminvorschläge
    location VARCHAR(255) DEFAULT NULL,
    video_link VARCHAR(500) DEFAULT NULL,
    duration INT DEFAULT NULL,
    -- Status der Umfrage
    status ENUM('open', 'closed', 'finalized') DEFAULT 'open',
    -- Finaler gewählter Termin (falls ausgewählt)
    final_date_id INT DEFAULT NULL,
    -- Zeitstempel
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    finalized_at DATETIME DEFAULT NULL,

    -- Foreign Keys
    FOREIGN KEY (created_by_member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(meeting_id) ON DELETE SET NULL,

    -- Indizes
    INDEX idx_status (status),
    INDEX idx_created_by (created_by_member_id),
    INDEX idx_meeting (meeting_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabelle für Terminvorschläge
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS poll_dates (
    date_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    suggested_date DATETIME NOT NULL,
    suggested_end_date DATETIME DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    -- Sortierreihenfolge
    sort_order INT DEFAULT 0,
    -- Zeitstempel
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,

    -- Indizes
    INDEX idx_poll (poll_id),
    INDEX idx_suggested_date (suggested_date),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabelle für Teilnehmer-Antworten
-- --------------------------------------------

CREATE TABLE IF NOT EXISTS poll_responses (
    response_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    date_id INT NOT NULL,
    -- Teilnehmer (kann member_id sein ODER externe Person)
    member_id INT DEFAULT NULL,
    participant_name VARCHAR(255) DEFAULT NULL,
    participant_email VARCHAR(255) DEFAULT NULL,
    -- Abstimmung: 1 = Passt (✅), 0 = Muss (🟡), -1 = Passt nicht (❌)
    vote TINYINT NOT NULL DEFAULT 0,
    comment TEXT,
    -- Zeitstempel
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (date_id) REFERENCES poll_dates(date_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,

    -- Ein Teilnehmer kann pro Termin nur einmal abstimmen
    UNIQUE KEY unique_response (poll_id, date_id, member_id, participant_email),

    -- Indizes
    INDEX idx_poll (poll_id),
    INDEX idx_date (date_id),
    INDEX idx_member (member_id),
    INDEX idx_vote (vote)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabelle für Umfrage-Teilnehmer
-- --------------------------------------------
-- Definiert, wer die Umfrage sehen/bearbeiten darf

CREATE TABLE IF NOT EXISTS poll_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_id INT NOT NULL,
    member_id INT NOT NULL,
    -- Zeitstempel
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (poll_id) REFERENCES polls(poll_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,

    -- Ein Mitglied kann nur einmal zu einer Umfrage eingeladen werden
    UNIQUE KEY unique_poll_participant (poll_id, member_id),

    -- Indizes
    INDEX idx_poll (poll_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Foreign Key für final_date_id in polls Tabelle
-- --------------------------------------------
-- (Wird nach Erstellung der poll_dates Tabelle hinzugefügt)

ALTER TABLE polls
ADD CONSTRAINT fk_final_date
FOREIGN KEY (final_date_id) REFERENCES poll_dates(date_id) ON DELETE SET NULL;

-- 6. Verifizierung
-- --------------------------------------------

-- Prüfen ob alle Tabellen erstellt wurden
SELECT
    'Tabellenprüfung' as check_type,
    COUNT(CASE WHEN TABLE_NAME = 'polls' THEN 1 END) as has_polls,
    COUNT(CASE WHEN TABLE_NAME = 'poll_dates' THEN 1 END) as has_poll_dates,
    COUNT(CASE WHEN TABLE_NAME = 'poll_responses' THEN 1 END) as has_poll_responses,
    COUNT(CASE WHEN TABLE_NAME = 'poll_participants' THEN 1 END) as has_poll_participants
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('polls', 'poll_dates', 'poll_responses', 'poll_participants');

-- 6. Beispieldaten (optional, nur für Tests)
-- --------------------------------------------

-- Beispiel-Umfrage erstellen (auskommentiert)
-- INSERT INTO polls (title, description, created_by_member_id, status)
-- SELECT 'Test-Terminumfrage', 'Wann passt der nächste Termin?', member_id, 'open'
-- FROM members WHERE is_admin = 1 LIMIT 1;

-- ============================================
-- ERFOLGSMELDUNG
-- ============================================

SELECT 'Datenbank erfolgreich aktualisiert!' as Status,
       'Terminplanung-Tabellen erstellt' as Details,
       NOW() as Timestamp;

-- ============================================
-- HINWEISE:
-- ============================================
--
-- Nach erfolgreicher Ausführung dieses Scripts:
-- 1. Laden Sie die neuen PHP-Dateien hoch:
--    - tab_termine.php (Anzeige)
--    - process_termine.php (Logik)
--
-- 2. Integrieren Sie den Tab in index.php
--
-- 3. Testen Sie die Terminplanung-Funktion
--
-- Voting-System:
-- - vote = 1:  ✅ Passt (bevorzugter Termin)
-- - vote = 0:  🟡 Geht zur Not (wenn sein muss)
-- - vote = -1: ❌ Passt nicht (Termin unmöglich)
--
-- Auto-Cleanup:
-- - Alte Umfragen (>1 Monat nach letztem Datum) können
--   automatisch archiviert oder gelöscht werden
--
-- ============================================
