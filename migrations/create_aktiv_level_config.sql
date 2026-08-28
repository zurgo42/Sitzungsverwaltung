-- Migration: Konfigurierbare aktiv-Level Beschreibungen
-- Ermöglicht organisationsspezifische Anpassung der Berechtigungsstufen

INSERT INTO svconfig (config_key, config_value, config_type, description, category) VALUES
-- aktiv-Level 0-19
('aktiv_level_0', 'Beschlussdatenbanken-Zugriff', 'text', 'Berechtigung für Level 0', 'aktiv_levels'),
('aktiv_level_1', 'Spezielle Zugriffe (Wiederaufnahmen)', 'text', 'Berechtigung für Level 1', 'aktiv_levels'),
('aktiv_level_2', 'Kassenfunktionen', 'text', 'Berechtigung für Level 2', 'aktiv_levels'),
('aktiv_level_3', 'Finanzprüfer', 'text', 'Berechtigung für Level 3', 'aktiv_levels'),
('aktiv_level_4', 'NN (reserviert)', 'text', 'Berechtigung für Level 4', 'aktiv_levels'),
('aktiv_level_5', 'NN (reserviert)', 'text', 'Berechtigung für Level 5', 'aktiv_levels'),
('aktiv_level_6', 'NN (reserviert)', 'text', 'Berechtigung für Level 6', 'aktiv_levels'),
('aktiv_level_7', 'NN (reserviert)', 'text', 'Berechtigung für Level 7', 'aktiv_levels'),
('aktiv_level_8', 'Finanzverantwortliche in Teams', 'text', 'Berechtigung für Level 8', 'aktiv_levels'),
('aktiv_level_9', 'Finanzverantwortliche in Teams', 'text', 'Berechtigung für Level 9', 'aktiv_levels'),
('aktiv_level_10', 'Temporäre Aktive', 'text', 'Berechtigung für Level 10', 'aktiv_levels'),
('aktiv_level_11', 'Teamleiter', 'text', 'Berechtigung für Level 11', 'aktiv_levels'),
('aktiv_level_12', 'Projektleiter', 'text', 'Berechtigung für Level 12', 'aktiv_levels'),
('aktiv_level_13', 'Projektleitung mit Budget', 'text', 'Berechtigung für Level 13', 'aktiv_levels'),
('aktiv_level_14', 'Zweiter Ressortleiter', 'text', 'Berechtigung für Level 14', 'aktiv_levels'),
('aktiv_level_15', 'Ressortleitung', 'text', 'Berechtigung für Level 15', 'aktiv_levels'),
('aktiv_level_16', 'Sonderaufgaben', 'text', 'Berechtigung für Level 16', 'aktiv_levels'),
('aktiv_level_17', 'Ressortleitung Finanzen', 'text', 'Berechtigung für Level 17', 'aktiv_levels'),
('aktiv_level_18', 'Geschäftsführung/Admin', 'text', 'Berechtigung für Level 18', 'aktiv_levels'),
('aktiv_level_19', 'Vorstandsmitglied', 'text', 'Berechtigung für Level 19', 'aktiv_levels')

ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);
