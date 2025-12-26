-- =============================================================================
-- Fantreffen - Datenbankschema
-- =============================================================================
-- Dieses Skript erstellt alle benötigten Tabellen für die neue Version.
-- Ausführen mit: mysql -u username -p datenbankname < schema.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabelle: fan_users
-- Zentrale Benutzerverwaltung (nicht mehr reisebezogen)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fan_users` (
    `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `passwort_hash` VARCHAR(255) NOT NULL,
    `rolle` ENUM('user', 'admin', 'superuser') NOT NULL DEFAULT 'user',
    `reset_token` VARCHAR(64) DEFAULT NULL,
    `reset_expires` DATETIME DEFAULT NULL,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `letzter_login` DATETIME DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `email` (`email`),
    KEY `idx_rolle` (`rolle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: fan_teilnehmer
-- Bis zu 4 Teilnehmer pro User (wiederverwendbar für mehrere Reisen)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fan_teilnehmer` (
    `teilnehmer_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `vorname` VARCHAR(100) NOT NULL,
    `nickname` VARCHAR(50) DEFAULT NULL,
    `mobil` VARCHAR(30) DEFAULT NULL,
    `position` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`teilnehmer_id`),
    KEY `idx_user` (`user_id`),
    CONSTRAINT `fk_fan_teilnehmer_user` FOREIGN KEY (`user_id`)
        REFERENCES `fan_users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: fan_reisen
-- Alle Fantreffen-Reisen
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fan_reisen` (
    `reise_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schiff` VARCHAR(100) NOT NULL,
    `bahnhof` VARCHAR(100) DEFAULT NULL,
    `anfang` DATE NOT NULL,
    `ende` DATE NOT NULL,
    `treffen_ort` VARCHAR(200) DEFAULT NULL,
    `treffen_zeit` DATETIME DEFAULT NULL,
    `treffen_status` ENUM('geplant', 'bestaetigt', 'abgesagt') NOT NULL DEFAULT 'geplant',
    `treffen_info` TEXT DEFAULT NULL,
    `link_wasserurlaub` VARCHAR(500) DEFAULT NULL,
    `link_facebook` VARCHAR(500) DEFAULT NULL,
    `link_kids` VARCHAR(500) DEFAULT NULL,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`reise_id`),
    KEY `idx_anfang` (`anfang`),
    KEY `idx_status` (`treffen_status`),
    CONSTRAINT `fk_fan_reisen_ersteller` FOREIGN KEY (`erstellt_von`)
        REFERENCES `fan_users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: fan_reise_admins
-- Verknüpfung: Welche User sind Admin für welche Reise
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fan_reise_admins` (
    `reise_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`reise_id`, `user_id`),
    CONSTRAINT `fk_fan_reise_admins_reise` FOREIGN KEY (`reise_id`)
        REFERENCES `fan_reisen` (`reise_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fan_reise_admins_user` FOREIGN KEY (`user_id`)
        REFERENCES `fan_users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: fan_anmeldungen
-- Anmeldungen von Usern zu Reisen (max. 4 Teilnehmer pro Anmeldung)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fan_anmeldungen` (
    `anmeldung_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `reise_id` INT UNSIGNED NOT NULL,
    `kabine` VARCHAR(20) DEFAULT NULL,
    `teilnehmer1_id` INT UNSIGNED DEFAULT NULL,
    `teilnehmer2_id` INT UNSIGNED DEFAULT NULL,
    `teilnehmer3_id` INT UNSIGNED DEFAULT NULL,
    `teilnehmer4_id` INT UNSIGNED DEFAULT NULL,
    `bemerkung` TEXT DEFAULT NULL,
    `infomail_gesendet` DATETIME DEFAULT NULL,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`anmeldung_id`),
    UNIQUE KEY `user_reise` (`user_id`, `reise_id`),
    KEY `idx_reise` (`reise_id`),
    KEY `idx_teilnehmer1` (`teilnehmer1_id`),
    KEY `idx_teilnehmer2` (`teilnehmer2_id`),
    KEY `idx_teilnehmer3` (`teilnehmer3_id`),
    KEY `idx_teilnehmer4` (`teilnehmer4_id`),
    CONSTRAINT `fk_fan_anmeldungen_user` FOREIGN KEY (`user_id`)
        REFERENCES `fan_users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fan_anmeldungen_reise` FOREIGN KEY (`reise_id`)
        REFERENCES `fan_reisen` (`reise_id`) ON DELETE CASCADE
    -- Foreign Keys für teilnehmer*_id absichtlich weggelassen (verursachen Probleme bei Migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: fan_mail_queue
-- Warteschlange für zu versendende Mails
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fan_mail_queue` (
    `mail_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empfaenger` VARCHAR(255) NOT NULL,
    `betreff` VARCHAR(255) NOT NULL,
    `inhalt_html` TEXT NOT NULL,
    `inhalt_text` TEXT DEFAULT NULL,
    `anhang` VARCHAR(500) DEFAULT NULL,
    `prioritaet` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `versuche` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `letzter_fehler` TEXT DEFAULT NULL,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `gesendet` DATETIME DEFAULT NULL,
    PRIMARY KEY (`mail_id`),
    KEY `idx_gesendet` (`gesendet`),
    KEY `idx_prioritaet` (`prioritaet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Initialer Superuser (Passwort nach dem ersten Login ändern!)
-- Passwort: 'admin123' (bcrypt-Hash)
-- =============================================================================
INSERT INTO `fan_users` (`email`, `passwort_hash`, `rolle`, `erstellt`) VALUES
('admin@aidafantreffen.de', '$2y$12$tDTXfo2PrPNF6ItPacrdTuzzlFsaFt4yQy3893gE/iYQjXs/LkZiu', 'superuser', NOW());

-- Hinweis: Das Passwort 'admin123' sollte sofort nach dem ersten Login geändert werden!
