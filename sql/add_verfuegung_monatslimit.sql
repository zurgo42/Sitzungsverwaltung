-- SQL-Anweisung zum Hinzufügen des monatlichen Verfügungslimits zur Konfiguration
-- Dieses Limit definiert den maximalen Gesamtbetrag an Verfügungen pro Monat
-- Bei Überschreitung wird ein Ressortbeschluss erforderlich

INSERT INTO svconfig (category, config_key, config_value, config_type, label, description, sort_order)
VALUES (
    'antragstypen',
    'verfuegung_monatslimit',
    '2000',
    'number',
    'Monatliches Verfügungslimit',
    'Maximale Summe aller Verfügungen pro Monat in Euro. Bei Überschreitung ist ein Ressortbeschluss erforderlich, auch wenn der einzelne Betrag unter der Verfügungsgrenze liegt.',
    100
)
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    label = VALUES(label),
    description = VALUES(description);
