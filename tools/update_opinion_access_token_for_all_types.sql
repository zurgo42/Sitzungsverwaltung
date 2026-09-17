-- Migration: Access-Token für alle Zielgruppen-Typen generieren
-- Erstellt: 2025-12-20
-- Zweck: Links sollen bei allen Varianten (individual, list, public) angezeigt werden

-- Prüfen ob Trigger existiert und löschen
DROP TRIGGER IF EXISTS set_opinion_poll_dates;

-- Neuen Trigger erstellen: Access-Token für ALLE target_types
DELIMITER $$

CREATE TRIGGER set_opinion_poll_dates
BEFORE INSERT ON svopinion_polls
FOR EACH ROW
BEGIN
    -- Enddatum setzen
    IF NEW.ends_at IS NULL THEN
        SET NEW.ends_at = DATE_ADD(NEW.created_at, INTERVAL NEW.duration_days DAY);
    END IF;

    -- Löschdatum setzen
    IF NEW.delete_at IS NULL THEN
        SET NEW.delete_at = DATE_ADD(NEW.created_at, INTERVAL NEW.delete_after_days DAY);
    END IF;

    -- Access-Token für ALLE Typen generieren (nicht nur 'individual')
    IF NEW.access_token IS NULL THEN
        SET NEW.access_token = SHA2(CONCAT(UUID(), RAND()), 256);
    END IF;
END$$

DELIMITER ;

-- Bestehende Umfragen ohne access_token aktualisieren
UPDATE svopinion_polls
SET access_token = SHA2(CONCAT(UUID(), RAND(), poll_id), 256)
WHERE access_token IS NULL OR access_token = '';
