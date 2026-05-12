-- ============================================
-- ALTER SCRIPT: antraege-Tabelle erweitern
-- ============================================
-- Fügt Felder aus beschluesse-Tabelle zu antraege hinzu
-- Damit haben wir alle Daten in einer Tabelle

USE aktive;

-- ============================================
-- Beschluss-Phase: Felder aus beschluesse
-- ============================================

ALTER TABLE antraege
-- Beschluss-Status
ADD COLUMN fertig VARCHAR(1) NULL COMMENT 'Beschluss abgeschlossen (1=ja)',

-- Finale Texte (können vom Antrag abweichen!)
ADD COLUMN besch_text TEXT NULL COMMENT 'Beschluss-Volltext',
ADD COLUMN besch_titel TEXT NULL COMMENT 'Finaler Beschluss-Titel',
ADD COLUMN besch_beschluss TEXT NULL COMMENT 'Finaler Beschluss-Wortlaut',
ADD COLUMN besch_fintext TEXT NULL COMMENT 'Finale Finanz-Begründung',
ADD COLUMN besch_pers TEXT NULL COMMENT 'Finale Personal-Auswirkungen',
ADD COLUMN besch_sach TEXT NULL COMMENT 'Finale Sach-Auswirkungen',
ADD COLUMN besch_begr TEXT NULL COMMENT 'Finale Begründung',
ADD COLUMN besch_ressort TEXT NULL COMMENT 'Finale Ressort-Zuordnung',
ADD COLUMN besch_int_ext VARCHAR(8) NULL COMMENT 'Finale int_ext',

-- Abstimmungsergebnis (Namenslisten)
ADD COLUMN besch_dafuer TEXT NULL COMMENT 'Namen: Dafür gestimmt',
ADD COLUMN besch_dagegen TEXT NULL COMMENT 'Namen: Dagegen gestimmt',
ADD COLUMN besch_enthaltungen TEXT NULL COMMENT 'Namen: Enthalten',
ADD COLUMN besch_anmerkungen TEXT NULL COMMENT 'Anmerkungen zum Beschluss',

-- Index für schnelle Filterung nach Beschlüssen
ADD INDEX idx_fertig (fertig);

-- ============================================
-- FERTIG!
-- ============================================
-- Die antraege-Tabelle kann jetzt auch fertige Beschlüsse aufnehmen
-- Filter: WHERE fertig = '1' → nur Beschlüsse
-- Filter: WHERE fertig IS NULL OR fertig != '1' → nur Anträge
