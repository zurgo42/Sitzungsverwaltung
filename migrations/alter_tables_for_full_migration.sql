-- ============================================
-- ALTER SCRIPT: Bestehende Tabellen erweitern
-- ============================================
-- Führe dieses Skript aus BEVOR du die Migration startest!
-- Es erweitert svbproposals und svbproposal_votes mit den neuen Feldern

USE aktive;

-- ============================================
-- 1. svbproposals erweitern
-- ============================================

ALTER TABLE svbproposals
-- VTool Legacy-Felder
ADD COLUMN vtool_bart VARCHAR(1) NULL COMMENT 'VTool: Art (V=Verfügung, R=Ressort, B=Vorstand)',
ADD COLUMN vtool_praesenz VARCHAR(11) NULL COMMENT 'VTool: Präsenzsitzung=1 oder Online',
ADD COLUMN vtool_antrst VARCHAR(3) NULL COMMENT 'VTool: Antragsteller-Code',
ADD COLUMN vtool_verein VARCHAR(1) NULL COMMENT 'VTool: Verein/Stiftung',
ADD COLUMN vtool_creator_code VARCHAR(64) NULL COMMENT 'VTool: Ersteller-Kürzel (verf)',

-- Workflow & Kategorisierung
ADD COLUMN internal_external VARCHAR(1) NULL COMMENT 'i=intern, e=extern',
ADD COLUMN presence_voting TINYINT(1) DEFAULT 0 COMMENT '1=Abstimmung in Präsenzsitzung',
ADD COLUMN prior_review_required TINYINT(1) DEFAULT 0 COMMENT 'Fachliche Vorprüfung erforderlich',
ADD COLUMN immediate_payment TINYINT(1) DEFAULT 0 COMMENT 'Sofortzahlung nach Prüfung',
ADD COLUMN prior_reviewer VARCHAR(64) NULL COMMENT 'Fachliche Vorprüfung durch',

-- Budget & Finanzen
ADD COLUMN budget_number VARCHAR(16) NULL COMMENT 'Budget-Nummer',
ADD COLUMN budget_code VARCHAR(8) NULL COMMENT 'Budget-Code',
ADD COLUMN finance_code VARCHAR(8) NULL COMMENT 'Finanzielle Auswirkungen (Code)',
ADD COLUMN forwarded_to_finance VARCHAR(16) NULL COMMENT 'An Finanzen weitergeleitet',
ADD COLUMN finance_remarks TEXT NULL COMMENT 'Bemerkungen für Finanzen',

-- Hinweise & Notizen
ADD COLUMN notes TEXT NULL COMMENT 'Allgemeine Hinweise',
ADD COLUMN hint_to_submitter TEXT NULL COMMENT 'Hinweis an Antragsteller',

-- Verknüpfungen & Historie
ADD COLUMN original_proposal_number VARCHAR(9) NULL COMMENT 'Urspr. Antragsnummer (warantrag)',
ADD COLUMN linked_proposal_1 INT NULL COMMENT 'Verknüpfter Antrag 1',
ADD COLUMN linked_proposal_2 INT NULL COMMENT 'Verknüpfter Antrag 2',
ADD COLUMN link_remarks TEXT NULL COMMENT 'Verknüpfungs-Bemerkungen',

-- Zeitablauf
ADD COLUMN timeline TEXT NULL COMMENT 'Zeitlicher Ablauf',
ADD COLUMN last_accessed_at DATETIME NULL COMMENT 'Letzter Zugriff (VTool)',

-- BESCHLUSS-PHASE (finale Werte aus beschluesse-Tabelle)
ADD COLUMN decision_finalized TINYINT(1) DEFAULT 0 COMMENT 'Beschluss abgeschlossen',
ADD COLUMN decision_important TINYINT(1) DEFAULT 0 COMMENT 'Beschluss wichtig',
ADD COLUMN decision_text TEXT NULL COMMENT 'Beschluss-Volltext',
ADD COLUMN decision_title TEXT NULL COMMENT 'Finaler Beschluss-Titel',
ADD COLUMN decision_content TEXT NULL COMMENT 'Finaler Beschluss-Wortlaut',
ADD COLUMN decision_finance_text TEXT NULL COMMENT 'Finale Finanz-Begründung',
ADD COLUMN decision_personnel TEXT NULL COMMENT 'Finale Personal-Auswirkungen',
ADD COLUMN decision_material TEXT NULL COMMENT 'Finale Sach-Auswirkungen',
ADD COLUMN decision_justification TEXT NULL COMMENT 'Finale Begründung',
ADD COLUMN decision_departments TEXT NULL COMMENT 'Finale Ressort-Zuordnung',
ADD COLUMN decision_internal_external VARCHAR(8) NULL COMMENT 'Finale int_ext',
ADD COLUMN decision_notes TEXT NULL COMMENT 'Anmerkungen zum Beschluss',

-- Abstimmungsergebnis (Namenslisten)
ADD COLUMN votes_for_list TEXT NULL COMMENT 'Namen: Dafür gestimmt',
ADD COLUMN votes_against_list TEXT NULL COMMENT 'Namen: Dagegen gestimmt',
ADD COLUMN votes_abstain_list TEXT NULL COMMENT 'Namen: Enthalten',

-- Indizes
ADD INDEX idx_vtool_bart (vtool_bart),
ADD INDEX idx_presence_voting (presence_voting);

-- ============================================
-- 2. svbproposal_votes erweitern
-- ============================================

ALTER TABLE svbproposal_votes
ADD COLUMN concerns TEXT NULL COMMENT 'Bedenken (VBedenk)',
MODIFY COLUMN vote_type ENUM('yes', 'no', 'abstain', 'refer_back', 'request_time', 'no_vote') NOT NULL COMMENT '1=yes, 2=no, 3=abstain, 4=refer_back, 5=request_time, 6=no_vote';

-- ============================================
-- FERTIG!
-- ============================================
-- Die Tabellen sind jetzt bereit für die vollständige Migration
