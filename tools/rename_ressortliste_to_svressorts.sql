-- Migration: Ressortliste umbenennen zu svressorts
-- Trennt die Antragssystem-Ressorts vom Adapter-System

-- Schritt 1: Neue Tabelle svressorts erstellen (falls nicht existiert)
CREATE TABLE IF NOT EXISTS svressorts (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    Ressort VARCHAR(100) NOT NULL COMMENT 'Name des Ressorts',
    Reihenfolge INT DEFAULT 100 COMMENT 'Sortierreihenfolge',
    aktiv TINYINT(1) DEFAULT 1 COMMENT '1=Aktiv, 0=Inaktiv',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reihenfolge (Reihenfolge),
    INDEX idx_aktiv (aktiv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Ressorts für das Antragssystem (sv-intern)';

-- Schritt 2: Daten von ressortliste nach svressorts kopieren (falls Daten vorhanden)
-- Nur wenn svressorts leer ist und ressortliste existiert
INSERT INTO svressorts (ID, Ressort, Reihenfolge, aktiv, created_at)
SELECT
    ID,
    Ressort,
    Reihenfolge,
    COALESCE(aktiv, 1) as aktiv,
    COALESCE(created_at, NOW()) as created_at
FROM ressortliste
WHERE NOT EXISTS (SELECT 1 FROM svressorts LIMIT 1)
  AND EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'ressortliste');

-- Schritt 3: Foreign Key Constraints entfernen (falls vorhanden)
-- Dies muss vor dem Umbenennen der Referenzen geschehen
SET @drop_fk = (SELECT CONCAT('ALTER TABLE antraege DROP FOREIGN KEY ', constraint_name, ';')
                FROM information_schema.table_constraints
                WHERE table_name = 'antraege'
                AND constraint_name LIKE '%ressort%'
                LIMIT 1);

-- Hinweis: Die antraege-Tabelle verwendet jetzt svressorts
-- Die IDs bleiben gleich, nur der Tabellenname ändert sich
-- Queries müssen auf svressorts angepasst werden (siehe nächste Schritte)
