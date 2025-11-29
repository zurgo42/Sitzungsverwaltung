-- ========================================
-- QUICK-FIX: Fehlende Spalten in polls-Tabelle hinzufügen
-- Erstellt: 17.11.2025
-- ========================================
--
-- Dieses Script fügt die fehlenden Spalten location, video_link und duration
-- zur polls-Tabelle hinzu, falls sie noch nicht existieren.
--
-- VERWENDUNG:
-- 1. Via phpMyAdmin: SQL-Tab -> Paste -> Ausführen
-- 2. Via MySQL CLI: mysql -u username -p databasename < database_patch_polls_columns.sql
--
-- HINWEIS: Wenn Sie die vollständige database_update_terminplanung.sql noch nicht
-- ausgeführt haben, führen Sie diese stattdessen aus!
-- ========================================

-- Prüfen ob Spalten bereits existieren und ggf. hinzufügen
SET @dbname = DATABASE();
SET @tablename = 'polls';
SET @columnname = 'location';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 'Spalte location existiert bereits'",
  "ALTER TABLE polls ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER meeting_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'video_link';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 'Spalte video_link existiert bereits'",
  "ALTER TABLE polls ADD COLUMN video_link VARCHAR(500) DEFAULT NULL AFTER location"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'duration';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 'Spalte duration existiert bereits'",
  "ALTER TABLE polls ADD COLUMN duration INT DEFAULT NULL AFTER video_link"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Erfolgsmeldung
SELECT 'Spalten erfolgreich hinzugefügt oder existieren bereits!' as Status;
