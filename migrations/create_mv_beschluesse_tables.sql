-- Migration: MV-Beschlüsse Tabellen
-- Erstellt die benötigten Tabellen für die MV-Beschlussdatenbank

-- Tabelle für MV-Beschlüsse
CREATE TABLE IF NOT EXISTS `mvbeschluesse` (
  `antrnr` varchar(20) NOT NULL,
  `titel` varchar(255) DEFAULT NULL,
  `text` text,
  `begr` text COMMENT 'Begründung',
  `ergebnis` varchar(50) DEFAULT NULL COMMENT 'z.B. Angenommen, Abgelehnt',
  `dafuer` int(11) DEFAULT NULL COMMENT 'Stimmen dafür',
  `dagegen` int(11) DEFAULT NULL COMMENT 'Stimmen dagegen',
  `enth` int(11) DEFAULT NULL COMMENT 'Enthaltungen',
  `Relevant` tinyint(4) DEFAULT '1' COMMENT '1=Gültig, 0=Aufhebung, -1=Nicht beschlossen',
  `kategorie` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`antrnr`),
  KEY `idx_relevant` (`Relevant`),
  KEY `idx_kategorie` (`kategorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='MV-Beschlüsse der Mitgliederversammlungen';

-- Tabelle für Abstimmungsverhalten (optional, falls benötigt)
CREATE TABLE IF NOT EXISTS `mverhalten` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `antrnr` varchar(20) NOT NULL,
  `MNr` varchar(20) NOT NULL COMMENT 'Mitgliedsnummer oder Member-ID',
  `votum` enum('dafuer','dagegen','enthaltung') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`antrnr`,`MNr`),
  KEY `idx_antrnr` (`antrnr`),
  KEY `idx_mnr` (`MNr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Abstimmungsverhalten einzelner Mitglieder bei MV-Beschlüssen';
