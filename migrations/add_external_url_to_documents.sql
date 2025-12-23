-- Migration: Externe Links für Dokumente
-- Erstellt: 2025-12-23
-- Beschreibung: Fügt external_url Feld hinzu, um auf externe Dateien zu verlinken

-- Spalte hinzufügen
ALTER TABLE svdocuments
ADD COLUMN external_url VARCHAR(1000) DEFAULT NULL COMMENT 'URL zu externer Datei (statt Upload)'
AFTER file_path;

-- filepath und file_name dürfen nun NULL sein (bei externen Links)
ALTER TABLE svdocuments
MODIFY COLUMN file_path VARCHAR(500) NULL,
MODIFY COLUMN file_name VARCHAR(255) NULL,
MODIFY COLUMN file_size INT NULL;

-- Index für schnellere Abfragen
CREATE INDEX idx_external_url ON svdocuments(external_url(255));
