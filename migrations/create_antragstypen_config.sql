-- Migration: Konfigurierbare Antragstypen (bart)
-- Ermöglicht organisationsspezifische Anpassung der Beschlussarten

-- Antragstypen: V = Verfügung, R = Ressortbeschluss, B = Vorstandsbeschluss
-- Jeder Typ kann individuell aktiviert/deaktiviert und konfiguriert werden

INSERT INTO svconfig (config_key, config_value, config_type, description, category) VALUES

-- ========================================
-- TYP V: VERFÜGUNG
-- ========================================
('bart_V_aktiv', '1', 'boolean', 'Typ V (Verfügung) aktiviert', 'antragstypen'),
('bart_V_bezeichnung', 'Verfügung', 'text', 'Anzeigename für Typ V', 'antragstypen'),
('bart_V_beschreibung', 'Kleinere Ausgaben und operative Entscheidungen', 'text', 'Erklärung des Typs', 'antragstypen'),

-- Betragsgrenze für Typ V
('bart_V_betrag_aktiv', '1', 'boolean', 'Betragsgrenze für Typ V aktiv', 'antragstypen'),
('bart_V_betrag_limit', '500', 'number', 'Betragsgrenze für Typ V in Euro', 'antragstypen'),

-- Wartezeit für Typ V
('bart_V_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ V aktiv', 'antragstypen'),
('bart_V_wartezeit_tage', '3', 'number', 'Wartezeit für Typ V in Tagen', 'antragstypen'),

-- Freigabe-Vereinfachung
('bart_V_freigabe_vereinfacht', '1', 'boolean', 'Vereinfachte Freigabe für Typ V', 'antragstypen'),

-- ========================================
-- TYP R: RESSORTBESCHLUSS
-- ========================================
('bart_R_aktiv', '1', 'boolean', 'Typ R (Ressortbeschluss) aktiviert', 'antragstypen'),
('bart_R_bezeichnung', 'Ressortbeschluss', 'text', 'Anzeigename für Typ R', 'antragstypen'),
('bart_R_beschreibung', 'Entscheidungen innerhalb eines Ressorts/Bereichs', 'text', 'Erklärung des Typs', 'antragstypen'),

-- Betragsgrenze für Typ R
('bart_R_betrag_aktiv', '1', 'boolean', 'Betragsgrenze für Typ R aktiv', 'antragstypen'),
('bart_R_betrag_limit', '2000', 'number', 'Betragsgrenze für Typ R in Euro', 'antragstypen'),

-- Wartezeit für Typ R
('bart_R_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ R aktiv', 'antragstypen'),
('bart_R_wartezeit_tage', '7', 'number', 'Wartezeit für Typ R in Tagen', 'antragstypen'),

-- Freigabe-Vereinfachung
('bart_R_freigabe_vereinfacht', '1', 'boolean', 'Vereinfachte Freigabe für Typ R', 'antragstypen'),

-- ========================================
-- TYP B: VORSTANDSBESCHLUSS
-- ========================================
('bart_B_aktiv', '1', 'boolean', 'Typ B (Vorstandsbeschluss) aktiviert', 'antragstypen'),
('bart_B_bezeichnung', 'Vorstandsbeschluss', 'text', 'Anzeigename für Typ B', 'antragstypen'),
('bart_B_beschreibung', 'Entscheidungen des Vorstands', 'text', 'Erklärung des Typs', 'antragstypen'),

-- Betragsgrenze für Typ B
('bart_B_betrag_aktiv', '0', 'boolean', 'Betragsgrenze für Typ B aktiv', 'antragstypen'),
('bart_B_betrag_limit', '0', 'number', 'Betragsgrenze für Typ B in Euro (0 = keine Grenze)', 'antragstypen'),

-- Wartezeit für Typ B
('bart_B_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ B aktiv', 'antragstypen'),
('bart_B_wartezeit_tage', '14', 'number', 'Wartezeit für Typ B in Tagen', 'antragstypen'),

-- Freigabe-Vereinfachung
('bart_B_freigabe_vereinfacht', '0', 'boolean', 'Vereinfachte Freigabe für Typ B (meist nicht verwendet)', 'antragstypen'),

-- ========================================
-- GLOBALE EINSTELLUNGEN
-- ========================================
('bart_show_betrag_in_liste', '1', 'boolean', 'Beträge in Antragsliste anzeigen', 'antragstypen'),
('bart_pflicht_bei_betrag', '100', 'number', 'Ab welchem Betrag ist Betragsangabe Pflicht', 'antragstypen')

ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);
