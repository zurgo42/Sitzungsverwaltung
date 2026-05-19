-- Migration: Ressortliste erweitern
-- Fügt fehlende Felder zur ressortliste-Tabelle hinzu für die Admin-Verwaltung

-- Aktiv-Status hinzufügen (falls nicht vorhanden)
ALTER TABLE ressortliste
ADD COLUMN IF NOT EXISTS aktiv TINYINT(1) DEFAULT 1 COMMENT '1=Aktiv, 0=Inaktiv';

-- Zeitstempel hinzufügen (falls nicht vorhanden)
ALTER TABLE ressortliste
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungsdatum';

ALTER TABLE ressortliste
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letzte Änderung';

-- Index für aktiv-Status (für schnellere Filterung)
ALTER TABLE ressortliste
ADD INDEX IF NOT EXISTS idx_aktiv (aktiv);

-- Standardwert für existierende Einträge setzen
UPDATE ressortliste SET aktiv = 1 WHERE aktiv IS NULL;
