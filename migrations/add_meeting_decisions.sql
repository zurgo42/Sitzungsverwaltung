-- Migration: Beschlüsse in Sitzungen ermöglichen
-- Datum: 2026-05-25

-- 1. Feld für "Beschlüsse erlauben" in Sitzungen
ALTER TABLE svmeetings
ADD COLUMN allow_decisions TINYINT(1) DEFAULT 1 COMMENT 'Beschlüsse in dieser Sitzung erlauben (1=ja, 0=nein)';

-- 2. Verknüpfung von TOP zu Antrag
ALTER TABLE svagenda_items
ADD COLUMN antrnr VARCHAR(15) NULL COMMENT 'Verknüpfung zu antraege.antrnr für Beschlussfassung',
ADD INDEX idx_antrnr (antrnr);

-- 3. Prüfen ob praesenz-Feld in antraege existiert (sollte bereits vorhanden sein)
-- Falls nicht: ALTER TABLE antraege ADD COLUMN praesenz TINYINT(1) DEFAULT 0 COMMENT 'Präsenzsitzung (1=ja, 0=nein)';

-- 4. Meeting-Referenz in antraege für Rückverknüpfung (optional, für Reports)
ALTER TABLE antraege
ADD COLUMN meeting_id INT NULL COMMENT 'Verknüpfung zur Sitzung falls Präsenzantrag',
ADD INDEX idx_meeting (meeting_id);
