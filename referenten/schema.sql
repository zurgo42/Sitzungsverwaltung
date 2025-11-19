-- ======================================================
-- MinD-Referentenliste - Datenbank-Schema
-- Modernisierte Version mit UTF8MB4 und InnoDB
-- ======================================================

-- Setze Charset
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ======================================================
-- Tabelle: Refname
-- Speichert persönliche Daten der Referenten
-- ======================================================

CREATE TABLE IF NOT EXISTS `Refname` (
  `MNr` VARCHAR(10) NOT NULL COMMENT 'Mensa-Mitgliedsnummer (Primary Key)',
  `Vorname` VARCHAR(50) DEFAULT NULL COMMENT 'Vorname des Referenten',
  `Name` VARCHAR(50) DEFAULT NULL COMMENT 'Nachname des Referenten',
  `Titel` VARCHAR(20) DEFAULT NULL COMMENT 'Akademischer Titel',
  `PLZ` VARCHAR(5) DEFAULT NULL COMMENT 'Postleitzahl',
  `Ort` VARCHAR(50) DEFAULT NULL COMMENT 'Wohnort',
  `Gebj` VARCHAR(4) DEFAULT NULL COMMENT 'Geburtsjahr',
  `Beruf` VARCHAR(100) DEFAULT NULL COMMENT 'Beruf/Tätigkeit',
  `Telefon` VARCHAR(30) DEFAULT NULL COMMENT 'Telefonnummer',
  `eMail` VARCHAR(100) DEFAULT NULL COMMENT 'E-Mail-Adresse',
  `datum` DATETIME DEFAULT NULL COMMENT 'Zeitstempel der letzten Änderung',
  PRIMARY KEY (`MNr`),
  KEY `idx_plz` (`PLZ`),
  KEY `idx_name` (`Name`, `Vorname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Persönliche Daten der Referenten';

-- ======================================================
-- Tabelle: Refpool
-- Speichert die Vortragsangebote
-- ======================================================

CREATE TABLE IF NOT EXISTS `Refpool` (
  `ID` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Eindeutige ID des Vortrags',
  `MNr` VARCHAR(10) NOT NULL COMMENT 'Mensa-Mitgliedsnummer (Foreign Key)',
  `Was` VARCHAR(50) DEFAULT NULL COMMENT 'Art des Angebots (Vortrag, Workshop, etc.)',
  `Wo` VARCHAR(100) DEFAULT NULL COMMENT 'Region/Verfügbarkeit',
  `Entf` INT(11) DEFAULT 0 COMMENT 'Maximale Entfernung in km',
  `Thema` VARCHAR(100) DEFAULT NULL COMMENT 'Titel/Überschrift des Vortrags',
  `Inhalt` TEXT DEFAULT NULL COMMENT 'Ausführliche Beschreibung',
  `Kategorie` VARCHAR(50) DEFAULT NULL COMMENT 'Themenkategorie',
  `Equipment` VARCHAR(100) DEFAULT NULL COMMENT 'Benötigte Technik',
  `Dauer` VARCHAR(10) DEFAULT NULL COMMENT 'Dauer in Minuten',
  `Kompetenz` TEXT DEFAULT NULL COMMENT 'Kompetenz/Qualifikation',
  `Bemerkung` TEXT DEFAULT NULL COMMENT 'Zusätzliche Bemerkungen',
  `aktiv` TINYINT(1) DEFAULT 1 COMMENT '1 = aktiv, 0 = deaktiviert',
  `IP` VARCHAR(45) DEFAULT NULL COMMENT 'IP-Adresse der letzten Änderung',
  `datum` DATETIME DEFAULT NULL COMMENT 'Zeitstempel der letzten Änderung',
  PRIMARY KEY (`ID`),
  KEY `idx_mnr` (`MNr`),
  KEY `idx_aktiv` (`aktiv`),
  KEY `idx_kategorie` (`Kategorie`),
  KEY `idx_thema` (`Thema`),
  CONSTRAINT `fk_refpool_mnr` FOREIGN KEY (`MNr`) REFERENCES `Refname` (`MNr`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vortragsangebote der Referenten';

-- ======================================================
-- Tabelle: PLZ (Optional - für Entfernungsberechnung)
-- Deutsche Postleitzahlen mit Koordinaten
-- ======================================================

CREATE TABLE IF NOT EXISTS `PLZ` (
  `plz` VARCHAR(5) NOT NULL COMMENT 'Postleitzahl',
  `Ort` VARCHAR(100) DEFAULT NULL COMMENT 'Ortsname',
  `lon` DECIMAL(10, 7) DEFAULT NULL COMMENT 'Längengrad (Longitude)',
  `lat` DECIMAL(10, 7) DEFAULT NULL COMMENT 'Breitengrad (Latitude)',
  PRIMARY KEY (`plz`),
  KEY `idx_ort` (`Ort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Deutsche PLZ mit Geo-Koordinaten für Entfernungsberechnung';

-- ======================================================
-- Beispiel-Daten (Optional - zum Testen)
-- ======================================================

-- Kommentiere die folgenden Zeilen aus, wenn du Testdaten einfügen möchtest:

/*
INSERT INTO `Refname` (`MNr`, `Vorname`, `Name`, `Titel`, `PLZ`, `Ort`, `Gebj`, `Beruf`, `Telefon`, `eMail`, `datum`) VALUES
('049000001', 'Max', 'Mustermann', 'Dr.', '10115', 'Berlin', '1975', 'Physiker', '030-12345678', 'max.mustermann@example.de', NOW()),
('049000002', 'Erika', 'Musterfrau', 'Prof. Dr.', '80331', 'München', '1980', 'Psychologin', '089-87654321', 'erika.musterfrau@example.de', NOW());

INSERT INTO `Refpool` (`MNr`, `Was`, `Wo`, `Entf`, `Thema`, `Inhalt`, `Kategorie`, `Equipment`, `Dauer`, `Kompetenz`, `Bemerkung`, `aktiv`, `IP`, `datum`) VALUES
('049000001', 'Vortrag', 'bundesweit', 500, 'Quantenphysik für Einsteiger', 'Eine Einführung in die faszinierende Welt der Quantenmechanik mit anschaulichen Beispielen.', 'Naturwissenschaft', 'Beamer, Leinwand', '60', 'Promovierter Physiker mit 15 Jahren Forschungserfahrung', 'Kann auch als Workshop angeboten werden', 1, '127.0.0.1', NOW()),
('049000002', 'Workshop', 'eher im Süden', 200, 'Hochbegabung im Erwachsenenalter', 'Ein interaktiver Workshop über die Besonderheiten hochbegabter Erwachsener.', 'Intelligenz, Hochbegabung', 'Flipchart, Moderationsmaterial', '180', 'Psychologin mit Schwerpunkt Hochbegabung, langjährige Erfahrung in der Beratung', NULL, 1, '127.0.0.1', NOW());
*/

-- ======================================================
-- Hinweise zur PLZ-Tabelle
-- ======================================================

/*
Die PLZ-Tabelle muss separat mit den deutschen Postleitzahlen gefüllt werden.
Datenquellen:
- OpenGeoDB: https://www.suche-postleitzahl.org/downloads
- GeoNames: https://www.geonames.org/

Beispiel für einige wenige PLZ-Einträge:

INSERT INTO `PLZ` (`plz`, `Ort`, `lon`, `lat`) VALUES
('10115', 'Berlin', 13.3888599, 52.5234051),
('20095', 'Hamburg', 10.0001488, 53.5510846),
('80331', 'München', 11.5819806, 48.1351253),
('50667', 'Köln', 6.9602786, 50.9375127),
('60311', 'Frankfurt am Main', 8.6821267, 50.1109221);

Für eine vollständige Funktionalität der Entfernungsberechnung
sollten alle deutschen PLZ importiert werden.
*/

-- ======================================================
-- Berechtigungen (Beispiel)
-- ======================================================

/*
Erstelle einen Benutzer für die Anwendung:

CREATE USER 'referenten_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
GRANT SELECT, INSERT, UPDATE, DELETE ON datenbank_name.Refname TO 'referenten_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON datenbank_name.Refpool TO 'referenten_user'@'localhost';
GRANT SELECT ON datenbank_name.PLZ TO 'referenten_user'@'localhost';
FLUSH PRIVILEGES;
*/

-- ======================================================
-- Migration vom alten Schema (falls vorhanden)
-- ======================================================

/*
Falls du vom alten Schema migrierst, führe folgende Anpassungen durch:

-- IP-Feld erweitern für IPv6
ALTER TABLE Refpool MODIFY COLUMN IP VARCHAR(45);

-- E-Mail-Feld erweitern
ALTER TABLE Refname MODIFY COLUMN eMail VARCHAR(100);

-- Charset auf utf8mb4 ändern
ALTER TABLE Refname CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE Refpool CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE PLZ CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Foreign Key hinzufügen (falls nicht vorhanden)
ALTER TABLE Refpool ADD CONSTRAINT fk_refpool_mnr
  FOREIGN KEY (MNr) REFERENCES Refname(MNr)
  ON DELETE CASCADE ON UPDATE CASCADE;
*/

-- ======================================================
-- Wartung
-- ======================================================

/*
Empfohlene regelmäßige Wartungsaufgaben:

-- Tabellen optimieren
OPTIMIZE TABLE Refname, Refpool, PLZ;

-- Indizes analysieren
ANALYZE TABLE Refname, Refpool, PLZ;

-- Inaktive Einträge bereinigen (optional)
-- DELETE FROM Refpool WHERE aktiv = 0 AND datum < DATE_SUB(NOW(), INTERVAL 2 YEAR);
*/
