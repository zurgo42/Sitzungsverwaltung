-- Migration: Force-Update Tracking für kollaboratives Protokoll
-- Datum: 2026-01-15
-- Beschreibung: Ermöglicht Tracking von Force-Updates, damit alle Clients diese sofort laden

-- Spalte für Force-Update Tracking
ALTER TABLE svagenda_items
ADD COLUMN force_update_at TIMESTAMP NULL DEFAULT NULL
COMMENT 'Timestamp des letzten Force-Updates (Prioritäts-Button)'
AFTER protocol_notes;

-- Index für schnellere Abfragen
CREATE INDEX idx_force_update ON svagenda_items(force_update_at);
