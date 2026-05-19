-- Migration: System-Konfiguration für Terminologie
-- Ermöglicht Anpassung von Begriffen an verschiedene Organisationen

CREATE TABLE IF NOT EXISTS svconfig (
    config_key VARCHAR(50) PRIMARY KEY,
    config_value TEXT,
    config_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description VARCHAR(255),
    category VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='System-Konfiguration für anpassbare Einstellungen';

-- Standard-Terminologie einfügen
INSERT INTO svconfig (config_key, config_value, config_type, description, category) VALUES
-- Terminologie für Antragssystem
('term_ressort_singular', 'Ressort', 'text', 'Singular-Form für Ressort/Abteilung/Bereich', 'terminology'),
('term_ressort_plural', 'Ressorts', 'text', 'Plural-Form für Ressort/Abteilung/Bereich', 'terminology'),
('term_antrag_singular', 'Antrag', 'text', 'Singular-Form für Antrag/Beschlussvorlage', 'terminology'),
('term_antrag_plural', 'Anträge', 'text', 'Plural-Form für Antrag/Beschlussvorlage', 'terminology'),
('term_beschluss_singular', 'Beschluss', 'text', 'Singular-Form für Beschluss', 'terminology'),
('term_beschluss_plural', 'Beschlüsse', 'text', 'Plural-Form für Beschluss', 'terminology'),

-- Rollen-Terminologie
('term_vorstand', 'Vorstand', 'text', 'Bezeichnung für Vorstand/Führungsgremium', 'terminology'),
('term_geschaeftsfuehrer', 'Geschäftsführer', 'text', 'Bezeichnung für Geschäftsführer', 'terminology'),
('term_ressortleiter', 'Ressortleiter', 'text', 'Bezeichnung für Ressortleiter/Abteilungsleiter', 'terminology')

ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);
