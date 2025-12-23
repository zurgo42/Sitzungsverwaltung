-- =============================================================================
-- Fantreffen - Datenbankschema
-- =============================================================================
-- Dieses Skript erstellt alle benötigten Tabellen für die neue Version.
-- Ausführen mit: mysql -u username -p datenbankname < schema.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabelle: users
-- Zentrale Benutzerverwaltung (nicht mehr reisebezogen)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
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
-- Tabelle: teilnehmer
-- Bis zu 4 Teilnehmer pro User (wiederverwendbar für mehrere Reisen)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teilnehmer` (
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
    CONSTRAINT `fk_teilnehmer_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: reisen
-- Alle Fantreffen-Reisen
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reisen` (
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
    CONSTRAINT `fk_reisen_ersteller` FOREIGN KEY (`erstellt_von`)
        REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: reise_admins
-- Verknüpfung: Welche User sind Admin für welche Reise
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reise_admins` (
    `reise_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`reise_id`, `user_id`),
    CONSTRAINT `fk_reise_admins_reise` FOREIGN KEY (`reise_id`)
        REFERENCES `reisen` (`reise_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reise_admins_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: anmeldungen
-- Anmeldungen von Usern zu Reisen
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `anmeldungen` (
    `anmeldung_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `reise_id` INT UNSIGNED NOT NULL,
    `kabine` VARCHAR(20) DEFAULT NULL,
    `bemerkung` TEXT DEFAULT NULL,
    `teilnehmer_ids` JSON DEFAULT NULL,
    `infomail_gesendet` DATETIME DEFAULT NULL,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`anmeldung_id`),
    UNIQUE KEY `user_reise` (`user_id`, `reise_id`),
    KEY `idx_reise` (`reise_id`),
    CONSTRAINT `fk_anmeldungen_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_anmeldungen_reise` FOREIGN KEY (`reise_id`)
        REFERENCES `reisen` (`reise_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabelle: mail_queue
-- Warteschlange für zu versendende Mails
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mail_queue` (
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
INSERT INTO `users` (`email`, `passwort_hash`, `rolle`, `erstellt`) VALUES
('admin@aidafantreffen.de', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superuser', NOW());

-- Hinweis: Das Passwort 'admin123' sollte sofort nach dem ersten Login geändert werden!
