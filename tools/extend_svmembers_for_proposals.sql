-- Migration: svmembers erweitern für Antragssystem-Rechte
-- Übernimmt Rechte-Konzept aus berechtigte-Tabelle

-- Aktiv-Level hinzufügen (0-19)
ALTER TABLE svmembers
ADD COLUMN IF NOT EXISTS aktiv TINYINT DEFAULT 0
COMMENT '0-19: Berechtigungslevel (0=inaktiv, 10=Mitglied, 14+=Ressortleiter, 18+=Vorstand, 19=Admin)';

-- Funktion hinzufügen (z.B. FVo, FVv, VA)
ALTER TABLE svmembers
ADD COLUMN IF NOT EXISTS Funktion VARCHAR(10) DEFAULT NULL
COMMENT 'Spezielle Funktion (FVo=Finanzvorstand, FVv=Stellv., VA=Vorsitzende/r, Vo=Vorstand)';

-- Kurzname hinzufügen (für kompakte Darstellung)
ALTER TABLE svmembers
ADD COLUMN IF NOT EXISTS KurzN VARCHAR(20) DEFAULT NULL
COMMENT 'Kurzname für kompakte Darstellung';

-- Name/Vorname für Kompatibilität mit berechtigte
ALTER TABLE svmembers
ADD COLUMN IF NOT EXISTS Name VARCHAR(50) DEFAULT NULL
COMMENT 'Nachname (Alias für last_name)';

ALTER TABLE svmembers
ADD COLUMN IF NOT EXISTS Vorname VARCHAR(50) DEFAULT NULL
COMMENT 'Vorname (Alias für first_name)';

-- Trigger erstellen um Name/Vorname mit first_name/last_name zu synchronisieren
DELIMITER //

DROP TRIGGER IF EXISTS svmembers_before_insert//
CREATE TRIGGER svmembers_before_insert BEFORE INSERT ON svmembers
FOR EACH ROW
BEGIN
    -- Wenn Name/Vorname gesetzt sind, first_name/last_name aktualisieren
    IF NEW.Name IS NOT NULL AND NEW.first_name IS NULL THEN
        SET NEW.first_name = NEW.Vorname;
        SET NEW.last_name = NEW.Name;
    END IF;

    -- Umgekehrt: first_name/last_name → Name/Vorname
    IF NEW.first_name IS NOT NULL AND NEW.Name IS NULL THEN
        SET NEW.Vorname = NEW.first_name;
        SET NEW.Name = NEW.last_name;
    END IF;

    -- KurzN generieren falls leer (Initiale + Nachname)
    IF NEW.KurzN IS NULL AND NEW.first_name IS NOT NULL THEN
        SET NEW.KurzN = CONCAT(LEFT(NEW.first_name, 1), '. ', NEW.last_name);
    END IF;
END//

DROP TRIGGER IF EXISTS svmembers_before_update//
CREATE TRIGGER svmembers_before_update BEFORE UPDATE ON svmembers
FOR EACH ROW
BEGIN
    -- Synchronisation bei Update
    IF NEW.Name != OLD.Name OR NEW.Vorname != OLD.Vorname THEN
        SET NEW.first_name = NEW.Vorname;
        SET NEW.last_name = NEW.Name;
    END IF;

    IF NEW.first_name != OLD.first_name OR NEW.last_name != OLD.last_name THEN
        SET NEW.Vorname = NEW.first_name;
        SET NEW.Name = NEW.last_name;
    END IF;
END//

DELIMITER ;

-- Index für Performance
ALTER TABLE svmembers ADD INDEX IF NOT EXISTS idx_aktiv (aktiv);
ALTER TABLE svmembers ADD INDEX IF NOT EXISTS idx_funktion (Funktion);

-- Bestehende Mitglieder: Standard-Werte setzen basierend auf role
UPDATE svmembers SET
    aktiv = CASE
        WHEN role = 'gf' THEN 19  -- Geschäftsführung = Admin
        WHEN role = 'vorstand' THEN 18  -- Vorstand
        WHEN role = 'assistenz' THEN 17  -- Assistenz
        WHEN role = 'fuehrungsteam' THEN 16  -- Führungsteam
        WHEN role = 'mitglied' THEN 10  -- Mitglied
        ELSE 0  -- Inaktiv
    END,
    Funktion = CASE
        WHEN role = 'vorstand' THEN 'Vo'
        ELSE NULL
    END,
    Name = last_name,
    Vorname = first_name,
    KurzN = CONCAT(LEFT(first_name, 1), '. ', last_name)
WHERE aktiv IS NULL OR aktiv = 0;

-- Kommentar zur Tabelle aktualisieren
ALTER TABLE svmembers COMMENT='Mitglieder mit erweiterten Rechten für Antragssystem';
