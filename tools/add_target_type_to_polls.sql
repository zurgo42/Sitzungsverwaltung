-- Migration: Target Type für Terminplanung
-- Erstellt: 2025-12-19
-- Beschreibung: Erweitert svpolls um target_type und access_token (analog zu svopinion_polls)
--              Ermöglicht: individual (Link) oder list (ausgewählte Teilnehmer)

-- Prüfen ob svpolls existiert, sonst polls verwenden
SET @table_name := (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'svpolls'),
        'svpolls',
        'polls'
    )
);

-- 1. Spalte target_type hinzufügen (falls nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.columns
               WHERE table_schema = DATABASE()
               AND table_name = @table_name
               AND column_name = 'target_type');

SET @sqlstmt := IF(@exist = 0,
    CONCAT('ALTER TABLE ', @table_name, ' ADD COLUMN target_type ENUM(''individual'', ''list'') DEFAULT ''list'' AFTER meeting_id'),
    'SELECT "Spalte target_type existiert bereits" AS Info');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Spalte access_token hinzufügen (falls nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.columns
               WHERE table_schema = DATABASE()
               AND table_name = @table_name
               AND column_name = 'access_token');

SET @sqlstmt := IF(@exist = 0,
    CONCAT('ALTER TABLE ', @table_name, ' ADD COLUMN access_token VARCHAR(64) DEFAULT NULL AFTER target_type'),
    'SELECT "Spalte access_token existiert bereits" AS Info');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Unique Index für access_token hinzufügen (falls nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.statistics
               WHERE table_schema = DATABASE()
               AND table_name = @table_name
               AND index_name = 'unique_access_token');

SET @sqlstmt := IF(@exist = 0,
    CONCAT('ALTER TABLE ', @table_name, ' ADD UNIQUE KEY unique_access_token (access_token)'),
    'SELECT "Index unique_access_token existiert bereits" AS Info');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Index für target_type hinzufügen (falls nicht vorhanden)
SET @exist := (SELECT COUNT(*)
               FROM information_schema.statistics
               WHERE table_schema = DATABASE()
               AND table_name = @table_name
               AND index_name = 'idx_target_type');

SET @sqlstmt := IF(@exist = 0,
    CONCAT('ALTER TABLE ', @table_name, ' ADD INDEX idx_target_type (target_type)'),
    'SELECT "Index idx_target_type existiert bereits" AS Info');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Alle existierenden Polls auf target_type='list' setzen (falls noch NULL)
SET @sqlstmt := CONCAT('UPDATE ', @table_name, ' SET target_type = ''list'' WHERE target_type IS NULL');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Trigger für automatische Token-Generierung erstellen
-- Zuerst prüfen ob Trigger existiert
SET @trigger_exists := (SELECT COUNT(*)
                        FROM information_schema.triggers
                        WHERE trigger_schema = DATABASE()
                        AND trigger_name = 'set_poll_access_token');

-- Trigger nur erstellen wenn nicht vorhanden
SET @sqlstmt := IF(@trigger_exists = 0,
    CONCAT('CREATE TRIGGER set_poll_access_token
    BEFORE INSERT ON ', @table_name, '
    FOR EACH ROW
    BEGIN
        IF NEW.access_token IS NULL AND NEW.target_type = ''individual'' THEN
            SET NEW.access_token = SHA2(CONCAT(UUID(), RAND()), 256);
        END IF;
    END'),
    'SELECT "Trigger set_poll_access_token existiert bereits" AS Info');

-- Für Trigger brauchen wir DELIMITER, daher direkt ausführen wenn nicht existiert
-- Da wir in prepared statements sind, machen wir es anders:

-- Erst Trigger droppen falls existiert (sicherstellen dass er aktuell ist)
DROP TRIGGER IF EXISTS set_poll_access_token;

-- Dann neu erstellen
DELIMITER $$

CREATE TRIGGER set_poll_access_token
BEFORE INSERT ON svpolls
FOR EACH ROW
BEGIN
    IF NEW.access_token IS NULL AND NEW.target_type = 'individual' THEN
        SET NEW.access_token = SHA2(CONCAT(UUID(), RAND()), 256);
    END IF;
END$$

DELIMITER ;

-- Erfolgsmeldung
SELECT 'Migration erfolgreich: svpolls erweitert um target_type und access_token' AS Status;
