-- ======================================================
-- Testfragen-Verwaltung - Datenbank-Schema
-- IQ-Test-Aufgaben-Sammlung für Mensa
-- ======================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ======================================================
-- Tabelle: testfragen
-- Speichert eingereichte Testaufgaben
-- ======================================================

CREATE TABLE IF NOT EXISTS `testfragen` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Eindeutige ID der Testfrage',
  `member_id` INT(11) NOT NULL COMMENT 'ID des einreichenden Mitglieds',
  `aufgabe` TEXT NOT NULL COMMENT 'Aufgabenstellung als Text',
  `antwort1` TEXT DEFAULT NULL COMMENT 'Antwortmöglichkeit 1',
  `antwort2` TEXT DEFAULT NULL COMMENT 'Antwortmöglichkeit 2',
  `antwort3` TEXT DEFAULT NULL COMMENT 'Antwortmöglichkeit 3',
  `antwort4` TEXT DEFAULT NULL COMMENT 'Antwortmöglichkeit 4',
  `antwort5` TEXT DEFAULT NULL COMMENT 'Antwortmöglichkeit 5',
  `richtig` TINYINT(1) NOT NULL COMMENT 'Nummer der richtigen Antwort (1-5)',
  `regel` TEXT NOT NULL COMMENT 'Beschreibung der zugrundeliegenden Regel',
  `inhalt` TINYINT(1) NOT NULL COMMENT 'Hauptinhaltsbereich: 1=verbal, 2=numerisch, 3=figural, 4=anderes',
  `tinhalt` VARCHAR(100) DEFAULT NULL COMMENT 'Freitext-Beschreibung wenn inhalt=4',
  `inhaltw` TINYINT(1) DEFAULT 0 COMMENT 'Sekundärer Inhaltsbereich (0=keiner)',
  `tinhaltw` VARCHAR(100) DEFAULT NULL COMMENT 'Freitext-Beschreibung wenn inhaltw=4',
  `schwer` TINYINT(1) NOT NULL COMMENT 'Schwierigkeitseinschätzung (1=sehr niedrig bis 5=sehr hoch)',
  `is_figural` TINYINT(1) DEFAULT 0 COMMENT '1=Figurale Aufgabe mit Bildern, 0=Textaufgabe',
  `file0` VARCHAR(255) DEFAULT NULL COMMENT 'Dateiname für Komplett-Bild der Aufgabe',
  `file1` VARCHAR(255) DEFAULT NULL COMMENT 'Dateiname für Bild zu Antwort 1',
  `file2` VARCHAR(255) DEFAULT NULL COMMENT 'Dateiname für Bild zu Antwort 2',
  `file3` VARCHAR(255) DEFAULT NULL COMMENT 'Dateiname für Bild zu Antwort 3',
  `file4` VARCHAR(255) DEFAULT NULL COMMENT 'Dateiname für Bild zu Antwort 4',
  `file5` VARCHAR(255) DEFAULT NULL COMMENT 'Dateiname für Bild zu Antwort 5',
  `datum` DATETIME NOT NULL COMMENT 'Zeitstempel der Einreichung',
  `reviewed` TINYINT(1) DEFAULT 0 COMMENT '1=von Redaktion gesichtet',
  `approved` TINYINT(1) DEFAULT NULL COMMENT '1=genehmigt, 0=abgelehnt, NULL=noch nicht entschieden',
  `review_notes` TEXT DEFAULT NULL COMMENT 'Notizen der Redaktion',
  PRIMARY KEY (`id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_reviewed` (`reviewed`),
  KEY `idx_approved` (`approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Eingereichte IQ-Test-Aufgaben';

-- ======================================================
-- Tabelle: testkommentar
-- Speichert allgemeine Kommentare zur Aktion
-- ======================================================

CREATE TABLE IF NOT EXISTS `testkommentar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Eindeutige ID des Kommentars',
  `member_id` INT(11) NOT NULL COMMENT 'ID des kommentierenden Mitglieds',
  `kommentar` TEXT NOT NULL COMMENT 'Kommentartext',
  `datum` DATETIME NOT NULL COMMENT 'Zeitstempel des Kommentars',
  `todo` ENUM('offen', 'bearbeitet', 'erledigt') DEFAULT 'offen' COMMENT 'Status der Bearbeitung',
  PRIMARY KEY (`id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_todo` (`todo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Allgemeine Kommentare zur Testfragen-Aktion';

-- ======================================================
-- Beispiel-Daten (Optional - zum Testen)
-- ======================================================

-- Kommentiere die folgenden Zeilen aus, wenn du Testdaten einfügen möchtest:

/*
INSERT INTO `testfragen`
(`member_id`, `aufgabe`, `antwort1`, `antwort2`, `antwort3`, `antwort4`, `antwort5`,
 `richtig`, `regel`, `inhalt`, `schwer`, `is_figural`, `datum`)
VALUES
(1, 'Welche Zahl kommt als nächstes? 2, 4, 8, 16, ...', '20', '24', '28', '32', '64',
 5, 'Jede Zahl wird verdoppelt', 2, 2, 0, NOW()),

(1, 'Hund verhält sich zu Welpe wie Katze zu ...', 'Kätzchen', 'Maus', 'Tiger', 'Löwe', 'Fisch',
 1, 'Analogie: Erwachsenes Tier zu Jungtier', 1, 3, 0, NOW());
*/

-- ======================================================
-- Migration von alter Struktur (falls vorhanden)
-- ======================================================

/*
Falls du vom alten Schema migrierst:

-- Spalte für member_id hinzufügen (falls noch nicht vorhanden)
ALTER TABLE testfragen ADD COLUMN member_id INT(11) AFTER id;

-- Fehlende Spalten hinzufügen
ALTER TABLE testfragen ADD COLUMN is_figural TINYINT(1) DEFAULT 0 AFTER schwer;
ALTER TABLE testfragen ADD COLUMN reviewed TINYINT(1) DEFAULT 0 AFTER file5;
ALTER TABLE testfragen ADD COLUMN approved TINYINT(1) DEFAULT NULL AFTER reviewed;
ALTER TABLE testfragen ADD COLUMN review_notes TEXT DEFAULT NULL AFTER approved;

-- Charset auf utf8mb4 ändern
ALTER TABLE testfragen CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE testkommentar CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Indizes hinzufügen
ALTER TABLE testfragen ADD INDEX idx_member (member_id);
ALTER TABLE testfragen ADD INDEX idx_datum (datum);
*/

-- ======================================================
-- Berechtigungen (Beispiel)
-- ======================================================

/*
Erstelle einen Benutzer für die Anwendung (optional):

GRANT SELECT, INSERT, UPDATE ON datenbank_name.testfragen TO 'app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON datenbank_name.testkommentar TO 'app_user'@'localhost';
FLUSH PRIVILEGES;
*/
