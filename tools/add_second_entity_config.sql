-- Migration: second_entity_name zu svconfig hinzufügen
-- Ermöglicht die Konfiguration einer zweiten Einheit neben "Verein" (z.B. "Stiftung")
-- Leer = keine Differenzierung in Antragsformularen

INSERT IGNORE INTO svconfig (config_key, config_value, config_type, description, category)
VALUES ('second_entity_name', '', 'text',
        'Name der zweiten Einheit neben "Verein" (z.B. "Stiftung", leer = keine Differenzierung)',
        'terminology');
