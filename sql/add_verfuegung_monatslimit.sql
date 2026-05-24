-- SQL-Anweisung zum Hinzufügen des monatlichen Verfügungslimits zur Konfiguration
-- Dieses Limit definiert den maximalen Gesamtbetrag an Verfügungen pro Monat
-- Bei Überschreitung wird ein Ressortbeschluss erforderlich

INSERT INTO svconfig (config_key, config_value, config_type, description, category)
VALUES (
    'verfuegung_monatslimit',
    '2000',
    'number',
    'Maximale Summe aller Verfügungen pro Monat in Euro. Bei Überschreitung ist ein Ressortbeschluss erforderlich, auch wenn der einzelne Betrag unter der Verfügungsgrenze liegt.',
    'antragstypen'
)
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    description = VALUES(description);
