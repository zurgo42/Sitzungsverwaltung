-- Migration: svressorts Tabelle erstellen
-- Einfache Version für schnelle Einrichtung

-- Tabelle erstellen
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

-- Daten von ressortliste kopieren (falls vorhanden und svressorts leer)
INSERT IGNORE INTO svressorts (ID, Ressort, Reihenfolge, aktiv, created_at)
SELECT
    ID,
    Ressort,
    COALESCE(Reihenfolge, 100) as Reihenfolge,
    COALESCE(aktiv, 1) as aktiv,
    COALESCE(created_at, NOW()) as created_at
FROM ressortliste
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ressortliste');
