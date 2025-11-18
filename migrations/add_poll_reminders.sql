-- Migration: Poll-Erinnerungsmail-Feature
-- Fügt Spalten zur polls-Tabelle hinzu für Erinnerungsmails
-- Erstellt: 2025-11-18

-- Spalten für Erinnerungsmail-Funktion hinzufügen
ALTER TABLE polls
ADD COLUMN IF NOT EXISTS reminder_enabled TINYINT(1) DEFAULT 0 COMMENT 'Ob Erinnerungsmail aktiviert ist',
ADD COLUMN IF NOT EXISTS reminder_days INT DEFAULT 1 COMMENT 'Anzahl Tage vor Termin für Erinnerung',
ADD COLUMN IF NOT EXISTS reminder_recipients VARCHAR(20) DEFAULT 'voters' COMMENT 'Empfänger: voters, all, none',
ADD COLUMN IF NOT EXISTS reminder_sent TINYINT(1) DEFAULT 0 COMMENT 'Ob Erinnerungsmail bereits versendet wurde';

-- Index für Performance (um fällige Erinnerungen schnell zu finden)
CREATE INDEX IF NOT EXISTS idx_polls_reminder
ON polls(reminder_enabled, reminder_sent, final_date_id);
