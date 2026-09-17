-- =============================================================================
-- Demo-Konfiguration: Vollständige Initialisierung aller Einstellungen
-- =============================================================================
-- Dieses Script erstellt:
-- 1. svconfig-Tabelle (falls nicht vorhanden)
-- 2. antraege-Tabelle (falls nicht vorhanden)
-- 3. svressorts-Tabelle (falls nicht vorhanden)
-- 4. Alle Konfigurationseinträge für:
--    - aktiv-Level Beschreibungen (0-19)
--    - Antragstypen (V, R, B) mit allen Einstellungen
--    - Abstimmungsregeln (5 Regeln)
--    - Terminologie
-- 5. Demo-Ressorts
-- =============================================================================

-- =============================================================================
-- 1. SVCONFIG TABELLE
-- =============================================================================

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

-- =============================================================================
-- 2. ANTRAEGE TABELLE
-- =============================================================================

CREATE TABLE IF NOT EXISTS antraege (
    antrnr VARCHAR(20) PRIMARY KEY COMMENT 'Antragsnummer (z.B. A26051401)',
    antrst INT NOT NULL COMMENT 'Antragsteller (member_id)',
    bart VARCHAR(1) NOT NULL DEFAULT 'B' COMMENT 'Beschlussart: V, R, B',
    titel VARCHAR(500) NOT NULL DEFAULT '',
    beschluss TEXT COMMENT 'Beschlusstext',
    ressort VARCHAR(100) DEFAULT NULL COMMENT 'Zuständiges Ressort',
    betrag DECIMAL(10,2) DEFAULT NULL COMMENT 'Finanzieller Betrag',
    fin CHAR(1) DEFAULT '0' COMMENT 'Finanzrelevanz: 0=Nein, 1=Ja',
    wichtig CHAR(1) DEFAULT '0' COMMENT 'Wichtig-Flag',
    status CHAR(1) DEFAULT 'A' COMMENT 'Status: A=Editing, E=Eingereicht, F=Freigegeben, G=Genehmigt, Z=Zurückgestellt, X=Abgelehnt',

    -- Abstimmungsregel (neu)
    abstimmregel VARCHAR(20) DEFAULT 'einfach' COMMENT 'Abstimmungsregel: einfach, absolut, mehrheit_stimmber, zweidrittel, einstimmig',

    -- Abstimmungsergebnisse
    ja INT DEFAULT NULL COMMENT 'Anzahl Ja-Stimmen',
    nein INT DEFAULT NULL COMMENT 'Anzahl Nein-Stimmen',
    enthaltung INT DEFAULT NULL COMMENT 'Anzahl Enthaltungen',
    abstimm_ergebnis VARCHAR(50) DEFAULT NULL COMMENT 'Ergebnis der Abstimmung',

    -- Datumsfelder
    antrdatum DATE DEFAULT NULL COMMENT 'Antragsdatum',
    frist DATE DEFAULT NULL COMMENT 'Wartefrist bis',
    freigabedatum DATE DEFAULT NULL COMMENT 'Datum der Freigabe',
    beschlussdatum DATE DEFAULT NULL COMMENT 'Datum des Beschlusses',

    -- Freigabe-Workflow
    freiggeber INT DEFAULT NULL COMMENT 'member_id des Freigebers',
    freigtext TEXT DEFAULT NULL COMMENT 'Freigabekommentar',

    -- Metadaten
    lzugriff DATETIME DEFAULT NULL COMMENT 'Letzter Zugriff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (antrst) REFERENCES svmembers(member_id) ON DELETE RESTRICT,
    FOREIGN KEY (freiggeber) REFERENCES svmembers(member_id) ON DELETE SET NULL,

    -- Indizes
    INDEX idx_antrst (antrst),
    INDEX idx_bart (bart),
    INDEX idx_status (status),
    INDEX idx_ressort (ressort),
    INDEX idx_antrdatum (antrdatum),
    INDEX idx_abstimmregel (abstimmregel),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Anträge/Beschlüsse mit konfigurierbaren Typen';

-- =============================================================================
-- 3. SVRESSORTS TABELLE
-- =============================================================================

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
COMMENT='Ressorts für das Antragssystem';

-- =============================================================================
-- 4. KONFIGURATIONSEINTRÄGE
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 4.1 AKTIV-LEVEL BESCHREIBUNGEN (0-19)
-- -----------------------------------------------------------------------------

INSERT INTO svconfig (config_key, config_value, config_type, description, category) VALUES
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

-- -----------------------------------------------------------------------------
-- 4.2 ANTRAGSTYPEN KONFIGURATION (V, R, B)
-- -----------------------------------------------------------------------------

INSERT INTO svconfig (config_key, config_value, config_type, description, category) VALUES
-- TYP V: VERFÜGUNG
('bart_V_aktiv', '1', 'boolean', 'Typ V (Verfügung) aktiviert', 'antragstypen'),
('bart_V_bezeichnung', 'Verfügung', 'text', 'Anzeigename für Typ V', 'antragstypen'),
('bart_V_beschreibung', 'Kleinere Ausgaben und operative Entscheidungen', 'text', 'Erklärung des Typs', 'antragstypen'),
('bart_V_betrag_aktiv', '1', 'boolean', 'Betragsgrenze für Typ V aktiv', 'antragstypen'),
('bart_V_betrag_limit', '500', 'number', 'Betragsgrenze für Typ V in Euro', 'antragstypen'),
('bart_V_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ V aktiv', 'antragstypen'),
('bart_V_wartezeit_tage', '3', 'number', 'Wartezeit für Typ V in Tagen', 'antragstypen'),
('bart_V_freigabe_vereinfacht', '1', 'boolean', 'Vereinfachte Freigabe für Typ V', 'antragstypen'),

-- TYP R: RESSORTBESCHLUSS
('bart_R_aktiv', '1', 'boolean', 'Typ R (Ressortbeschluss) aktiviert', 'antragstypen'),
('bart_R_bezeichnung', 'Ressortbeschluss', 'text', 'Anzeigename für Typ R', 'antragstypen'),
('bart_R_beschreibung', 'Entscheidungen innerhalb eines Ressorts/Bereichs', 'text', 'Erklärung des Typs', 'antragstypen'),
('bart_R_betrag_aktiv', '1', 'boolean', 'Betragsgrenze für Typ R aktiv', 'antragstypen'),
('bart_R_betrag_limit', '2000', 'number', 'Betragsgrenze für Typ R in Euro', 'antragstypen'),
('bart_R_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ R aktiv', 'antragstypen'),
('bart_R_wartezeit_tage', '7', 'number', 'Wartezeit für Typ R in Tagen', 'antragstypen'),
('bart_R_freigabe_vereinfacht', '1', 'boolean', 'Vereinfachte Freigabe für Typ R', 'antragstypen'),

-- TYP B: VORSTANDSBESCHLUSS
('bart_B_aktiv', '1', 'boolean', 'Typ B (Vorstandsbeschluss) aktiviert', 'antragstypen'),
('bart_B_bezeichnung', 'Vorstandsbeschluss', 'text', 'Anzeigename für Typ B', 'antragstypen'),
('bart_B_beschreibung', 'Entscheidungen des Vorstands', 'text', 'Erklärung des Typs', 'antragstypen'),
('bart_B_betrag_aktiv', '0', 'boolean', 'Betragsgrenze für Typ B aktiv', 'antragstypen'),
('bart_B_betrag_limit', '0', 'number', 'Betragsgrenze für Typ B in Euro (0 = keine Grenze)', 'antragstypen'),
('bart_B_wartezeit_aktiv', '1', 'boolean', 'Wartezeit für Typ B aktiv', 'antragstypen'),
('bart_B_wartezeit_tage', '14', 'number', 'Wartezeit für Typ B in Tagen', 'antragstypen'),
('bart_B_freigabe_vereinfacht', '0', 'boolean', 'Vereinfachte Freigabe für Typ B (meist nicht verwendet)', 'antragstypen'),

-- GLOBALE EINSTELLUNGEN
('bart_show_betrag_in_liste', '1', 'boolean', 'Beträge in Antragsliste anzeigen', 'antragstypen'),
('bart_pflicht_bei_betrag', '100', 'number', 'Ab welchem Betrag ist Betragsangabe Pflicht', 'antragstypen')
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);

-- -----------------------------------------------------------------------------
-- 4.3 ABSTIMMUNGSREGELN
-- -----------------------------------------------------------------------------

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

-- -----------------------------------------------------------------------------
-- 4.4 TERMINOLOGIE
-- -----------------------------------------------------------------------------

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

-- =============================================================================
-- 5. DEMO-RESSORTS
-- =============================================================================

INSERT INTO svressorts (Ressort, Reihenfolge, aktiv) VALUES
('Finanzen', 10, 1),
('Personal', 20, 1),
('IT & Digitalisierung', 30, 1),
('Marketing & Kommunikation', 40, 1),
('Veranstaltungen', 50, 1),
('Mitgliederverwaltung', 60, 1),
('Infrastruktur', 70, 1),
('Bildung & Weiterbildung', 80, 1)
ON DUPLICATE KEY UPDATE
    Reihenfolge = VALUES(Reihenfolge),
    aktiv = VALUES(aktiv);

-- =============================================================================
-- ENDE DER DEMO-KONFIGURATION
-- =============================================================================
