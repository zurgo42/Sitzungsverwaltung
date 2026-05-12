-- ============================================
-- ROLLBACK: besch_* Felder aus antraege entfernen
-- ============================================
-- Führe dieses Skript aus, um die besch_* Felder wieder zu entfernen

USE aktive;

ALTER TABLE antraege
DROP COLUMN IF EXISTS fertig,
DROP COLUMN IF EXISTS besch_text,
DROP COLUMN IF EXISTS besch_titel,
DROP COLUMN IF EXISTS besch_beschluss,
DROP COLUMN IF EXISTS besch_fintext,
DROP COLUMN IF EXISTS besch_pers,
DROP COLUMN IF EXISTS besch_sach,
DROP COLUMN IF EXISTS besch_begr,
DROP COLUMN IF EXISTS besch_ressort,
DROP COLUMN IF EXISTS besch_int_ext,
DROP COLUMN IF EXISTS besch_dafuer,
DROP COLUMN IF EXISTS besch_dagegen,
DROP COLUMN IF EXISTS besch_enthaltungen,
DROP COLUMN IF EXISTS besch_anmerkungen,
DROP INDEX IF EXISTS idx_fertig;

-- ============================================
-- FERTIG!
-- ============================================
