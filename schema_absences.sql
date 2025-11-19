-- ======================================================
-- Abwesenheitsverwaltung - Datenbank-Schema
-- Führungsteam kann Abwesenheiten eintragen
-- ======================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ======================================================
-- Tabelle: absences
-- Speichert Abwesenheitszeiten des Führungsteams
-- ======================================================

CREATE TABLE IF NOT EXISTS `absences` (
  `absence_id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Eindeutige ID der Abwesenheit',
  `member_id` INT(11) NOT NULL COMMENT 'Wer ist abwesend',
  `start_date` DATE NOT NULL COMMENT 'Beginn der Abwesenheit',
  `end_date` DATE NOT NULL COMMENT 'Ende der Abwesenheit',
  `reason` TEXT DEFAULT NULL COMMENT 'Grund der Abwesenheit (optional)',
  `substitute_member_id` INT(11) DEFAULT NULL COMMENT 'Wer vertritt (optional)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Zeitpunkt der Erstellung',
  `created_by_member_id` INT(11) NOT NULL COMMENT 'Wer hat eingetragen',
  PRIMARY KEY (`absence_id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_dates` (`start_date`, `end_date`),
  KEY `idx_substitute` (`substitute_member_id`),
  KEY `idx_created_by` (`created_by_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Abwesenheiten des Führungsteams';

-- ======================================================
-- Beispiel-Daten (Optional - zum Testen)
-- ======================================================

/*
INSERT INTO `absences`
(`member_id`, `start_date`, `end_date`, `reason`, `substitute_member_id`, `created_by_member_id`)
VALUES
(1, '2025-12-20', '2025-12-27', 'Urlaub', 2, 1),
(3, '2025-11-25', '2025-11-25', 'Konferenz', NULL, 3);
*/

-- ======================================================
-- Berechtigungen (Beispiel)
-- ======================================================

/*
GRANT SELECT, INSERT, UPDATE, DELETE ON datenbank_name.absences TO 'app_user'@'localhost';
FLUSH PRIVILEGES;
*/
