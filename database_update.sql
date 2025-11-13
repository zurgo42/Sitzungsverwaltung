-- ============================================
-- DATENBANK-UPDATE FÜR SITZUNGSVERWALTUNG
-- Version 2.0 - 04.11.2025
-- ============================================
--
-- Dieses Script fügt die neuen Felder für:
-- - Kategorien
-- - Antragstext
-- - Abstimmungsergebnisse
-- - Kommentar-Timestamps
-- hinzu
--
-- WICHTIG: Führen Sie dieses Script aus, BEVOR Sie die neuen PHP-Dateien hochladen!
-- ============================================

-- 1. Neue Felder für agenda_items-Tabelle hinzufügen
-- --------------------------------------------

-- Kategorie-Feld hinzufügen
ALTER TABLE agenda_items 
ADD COLUMN IF NOT EXISTS category ENUM('information', 'klaerung', 'aussprache', 'antrag_beschluss', 'sonstiges') 
DEFAULT 'information' 
AFTER description;

-- Antragstext-Feld hinzufügen
ALTER TABLE agenda_items 
ADD COLUMN IF NOT EXISTS proposal_text TEXT 
AFTER category;

-- Abstimmungsfelder hinzufügen
ALTER TABLE agenda_items 
ADD COLUMN IF NOT EXISTS vote_yes INT DEFAULT NULL 
AFTER proposal_text;

ALTER TABLE agenda_items 
ADD COLUMN IF NOT EXISTS vote_no INT DEFAULT NULL 
AFTER vote_yes;

ALTER TABLE agenda_items 
ADD COLUMN IF NOT EXISTS vote_abstain INT DEFAULT NULL 
AFTER vote_no;

ALTER TABLE agenda_items 
ADD COLUMN IF NOT EXISTS vote_result ENUM('einvernehmlich', 'einstimmig', 'angenommen', 'abgelehnt') 
DEFAULT NULL 
AFTER vote_abstain;

-- Index für Kategorie hinzufügen
ALTER TABLE agenda_items 
ADD INDEX IF NOT EXISTS idx_category (category);

-- 2. Neue Felder für agenda_comments-Tabelle hinzufügen
-- --------------------------------------------

-- Updated_at-Feld hinzufügen
ALTER TABLE agenda_comments 
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP 
DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
AFTER created_at;

-- 3. Bestehende Daten aktualisieren
-- --------------------------------------------

-- Alle TOPs mit top_number = 0 bekommen Kategorie 'antrag_beschluss'
UPDATE agenda_items 
SET category = 'antrag_beschluss' 
WHERE top_number = 0 AND category = 'information';

-- Alle TOPs mit top_number = 99 bekommen Kategorie 'sonstiges'
UPDATE agenda_items 
SET category = 'sonstiges' 
WHERE top_number = 99 AND category = 'information';

-- 4. Verifizierung
-- --------------------------------------------

-- Prüfen ob alle Felder vorhanden sind
SELECT 
    'agenda_items' as table_name,
    COUNT(CASE WHEN COLUMN_NAME = 'category' THEN 1 END) as has_category,
    COUNT(CASE WHEN COLUMN_NAME = 'proposal_text' THEN 1 END) as has_proposal_text,
    COUNT(CASE WHEN COLUMN_NAME = 'vote_yes' THEN 1 END) as has_vote_yes,
    COUNT(CASE WHEN COLUMN_NAME = 'vote_no' THEN 1 END) as has_vote_no,
    COUNT(CASE WHEN COLUMN_NAME = 'vote_abstain' THEN 1 END) as has_vote_abstain,
    COUNT(CASE WHEN COLUMN_NAME = 'vote_result' THEN 1 END) as has_vote_result
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'agenda_items';

SELECT 
    'agenda_comments' as table_name,
    COUNT(CASE WHEN COLUMN_NAME = 'updated_at' THEN 1 END) as has_updated_at
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'agenda_comments';

-- 5. Statistik
-- --------------------------------------------

-- Zeige Anzahl TOPs pro Kategorie
SELECT 
    category,
    COUNT(*) as count
FROM agenda_items
GROUP BY category
ORDER BY count DESC;

-- ============================================
-- ERFOLGSMELDUNG
-- ============================================

SELECT 'Datenbank erfolgreich aktualisiert!' as Status,
       NOW() as Timestamp;

-- ============================================
-- HINWEISE:
-- ============================================
-- 
-- Nach erfolgreicher Ausführung dieses Scripts:
-- 1. Laden Sie die neuen PHP-Dateien hoch
-- 2. Testen Sie die Anwendung gründlich
-- 3. Erstellen Sie ein neues Meeting zum Testen
-- 
-- Bei Problemen:
-- - Prüfen Sie die Tabelle-Struktur mit: DESCRIBE agenda_items;
-- - Prüfen Sie die Tabelle-Struktur mit: DESCRIBE agenda_comments;
-- - Kontrollieren Sie die Fehlermeldungen in den PHP-Logs
-- 
-- ============================================
