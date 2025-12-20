-- Migration: Access-Token für alle Zielgruppen-Typen bei Terminen generieren
-- Erstellt: 2025-12-20
-- Zweck: Links sollen bei allen Varianten (individual, list) angezeigt werden

-- Trigger für svpolls droppen
DROP TRIGGER IF EXISTS set_poll_access_token;

-- Neuen Trigger erstellen: Access-Token für ALLE target_types
DELIMITER $$

CREATE TRIGGER set_poll_access_token
BEFORE INSERT ON svpolls
FOR EACH ROW
BEGIN
    -- Access-Token für ALLE Typen generieren (nicht nur 'individual')
    IF NEW.access_token IS NULL THEN
        SET NEW.access_token = SHA2(CONCAT(UUID(), RAND()), 256);
    END IF;
END$$

DELIMITER ;

-- Bestehende Umfragen ohne access_token aktualisieren
UPDATE svpolls
SET access_token = SHA2(CONCAT(UUID(), RAND(), poll_id), 256)
WHERE access_token IS NULL OR access_token = '';

SELECT 'Migration erfolgreich: svpolls Trigger aktualisiert' AS Status;
