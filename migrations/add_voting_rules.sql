-- Migration: Abstimmungsregeln für Anträge
-- Ermöglicht flexible Mehrheitsregeln pro Antrag

-- Neues Feld in antraege-Tabelle
ALTER TABLE antraege
ADD COLUMN IF NOT EXISTS abstimmregel VARCHAR(20) DEFAULT 'einfach'
COMMENT 'Abstimmungsregel: einfach, absolut, mehrheit_stimmber, zweidrittel, einstimmig';

-- Index für Performance
ALTER TABLE antraege ADD INDEX IF NOT EXISTS idx_abstimmregel (abstimmregel);

-- Globale Einstellungen in svconfig
INSERT INTO svconfig (config_key, config_value, config_type, description, category) VALUES

-- Standard-Abstimmungsregel
('voting_default_rule', 'einfach', 'text', 'Standard-Abstimmungsregel für neue Anträge', 'voting'),

-- Regel-Definitionen (für UI-Anzeige)
('voting_rule_einfach_label', 'Einfache Mehrheit', 'text', 'Label für einfache Mehrheit', 'voting'),
('voting_rule_einfach_desc', 'Mehr Ja als Nein (Enthaltungen zählen nicht)', 'text', 'Beschreibung einfache Mehrheit', 'voting'),

('voting_rule_absolut_label', 'Absolute Mehrheit', 'text', 'Label für absolute Mehrheit', 'voting'),
('voting_rule_absolut_desc', 'Mehr Ja als Nein+Enthaltung', 'text', 'Beschreibung absolute Mehrheit', 'voting'),

('voting_rule_mehrheit_stimmber_label', 'Mehrheit der Stimmberechtigten', 'text', 'Label für Mehrheit Stimmberechtigte', 'voting'),
('voting_rule_mehrheit_stimmber_desc', 'Mehr als 50% aller Stimmberechtigten stimmen Ja', 'text', 'Beschreibung Mehrheit Stimmberechtigte', 'voting'),

('voting_rule_zweidrittel_label', '2/3-Mehrheit', 'text', 'Label für 2/3-Mehrheit', 'voting'),
('voting_rule_zweidrittel_desc', 'Ja-Stimmen >= 2 × Nein-Stimmen', 'text', 'Beschreibung 2/3-Mehrheit', 'voting'),

('voting_rule_einstimmig_label', 'Einstimmigkeit', 'text', 'Label für Einstimmigkeit', 'voting'),
('voting_rule_einstimmig_desc', 'Alle Abstimmenden müssen Ja stimmen', 'text', 'Beschreibung Einstimmigkeit', 'voting'),

-- Regel-Aktivierung (welche Regeln zur Auswahl stehen)
('voting_enable_einfach', '1', 'boolean', 'Einfache Mehrheit aktiviert', 'voting'),
('voting_enable_absolut', '1', 'boolean', 'Absolute Mehrheit aktiviert', 'voting'),
('voting_enable_mehrheit_stimmber', '1', 'boolean', 'Mehrheit der Stimmberechtigten aktiviert', 'voting'),
('voting_enable_zweidrittel', '1', 'boolean', '2/3-Mehrheit aktiviert', 'voting'),
('voting_enable_einstimmig', '1', 'boolean', 'Einstimmigkeit aktiviert', 'voting')

ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);

-- Bestehende Anträge auf Standard setzen
UPDATE antraege SET abstimmregel = 'einfach' WHERE abstimmregel IS NULL OR abstimmregel = '';
