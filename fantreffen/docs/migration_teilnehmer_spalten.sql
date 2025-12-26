-- =============================================================================
-- Migration: teilnehmer_ids JSON -> teilnehmer1_id bis teilnehmer4_id
-- =============================================================================
-- Ersetzt die JSON-Spalte teilnehmer_ids durch 4 einzelne Fremdschlüssel-Spalten
--
-- ACHTUNG: Vor der Ausführung Backup der Datenbank erstellen!
-- Ausführen mit: mysql -u username -p datenbankname < migration_teilnehmer_spalten.sql
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Schritt 1: Neue Spalten hinzufügen
-- -----------------------------------------------------------------------------
ALTER TABLE `fan_anmeldungen`
    ADD COLUMN `teilnehmer1_id` INT UNSIGNED DEFAULT NULL AFTER `kabine`,
    ADD COLUMN `teilnehmer2_id` INT UNSIGNED DEFAULT NULL AFTER `teilnehmer1_id`,
    ADD COLUMN `teilnehmer3_id` INT UNSIGNED DEFAULT NULL AFTER `teilnehmer2_id`,
    ADD COLUMN `teilnehmer4_id` INT UNSIGNED DEFAULT NULL AFTER `teilnehmer3_id`;

-- -----------------------------------------------------------------------------
-- Schritt 2: Daten aus JSON in neue Spalten migrieren
-- -----------------------------------------------------------------------------
UPDATE `fan_anmeldungen`
SET
    teilnehmer1_id = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(teilnehmer_ids, '$[0]')), 'null'),
    teilnehmer2_id = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(teilnehmer_ids, '$[1]')), 'null'),
    teilnehmer3_id = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(teilnehmer_ids, '$[2]')), 'null'),
    teilnehmer4_id = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(teilnehmer_ids, '$[3]')), 'null')
WHERE teilnehmer_ids IS NOT NULL;

-- -----------------------------------------------------------------------------
-- Schritt 3: Verwaiste IDs bereinigen (IDs die nicht in fan_teilnehmer existieren)
-- -----------------------------------------------------------------------------
UPDATE `fan_anmeldungen` a
SET a.teilnehmer1_id = NULL
WHERE a.teilnehmer1_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fan_teilnehmer t WHERE t.teilnehmer_id = a.teilnehmer1_id);

UPDATE `fan_anmeldungen` a
SET a.teilnehmer2_id = NULL
WHERE a.teilnehmer2_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fan_teilnehmer t WHERE t.teilnehmer_id = a.teilnehmer2_id);

UPDATE `fan_anmeldungen` a
SET a.teilnehmer3_id = NULL
WHERE a.teilnehmer3_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fan_teilnehmer t WHERE t.teilnehmer_id = a.teilnehmer3_id);

UPDATE `fan_anmeldungen` a
SET a.teilnehmer4_id = NULL
WHERE a.teilnehmer4_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM fan_teilnehmer t WHERE t.teilnehmer_id = a.teilnehmer4_id);

-- -----------------------------------------------------------------------------
-- Schritt 4: Foreign Keys hinzufügen (optional - kann weggelassen werden)
-- -----------------------------------------------------------------------------
-- HINWEIS: Foreign Keys können Probleme verursachen. Falls nicht gewünscht,
-- diesen Abschnitt überspringen. Die Anwendungslogik prüft die Datenintegrität.
--
-- ALTER TABLE `fan_anmeldungen`
--     ADD CONSTRAINT `fk_anmeldung_teilnehmer1`
--         FOREIGN KEY (`teilnehmer1_id`) REFERENCES `fan_teilnehmer` (`teilnehmer_id`) ON DELETE SET NULL,
--     ADD CONSTRAINT `fk_anmeldung_teilnehmer2`
--         FOREIGN KEY (`teilnehmer2_id`) REFERENCES `fan_teilnehmer` (`teilnehmer_id`) ON DELETE SET NULL,
--     ADD CONSTRAINT `fk_anmeldung_teilnehmer3`
--         FOREIGN KEY (`teilnehmer3_id`) REFERENCES `fan_teilnehmer` (`teilnehmer_id`) ON DELETE SET NULL,
--     ADD CONSTRAINT `fk_anmeldung_teilnehmer4`
--         FOREIGN KEY (`teilnehmer4_id`) REFERENCES `fan_teilnehmer` (`teilnehmer_id`) ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- Schritt 5: Index für schnellere Abfragen
-- -----------------------------------------------------------------------------
ALTER TABLE `fan_anmeldungen`
    ADD INDEX `idx_teilnehmer1` (`teilnehmer1_id`),
    ADD INDEX `idx_teilnehmer2` (`teilnehmer2_id`),
    ADD INDEX `idx_teilnehmer3` (`teilnehmer3_id`),
    ADD INDEX `idx_teilnehmer4` (`teilnehmer4_id`);

-- -----------------------------------------------------------------------------
-- Schritt 6: Alte JSON-Spalte entfernen (optional - erst nach erfolgreicher Prüfung!)
-- -----------------------------------------------------------------------------
-- Führe diesen Befehl erst aus, nachdem du geprüft hast, dass die Migration erfolgreich war:
-- ALTER TABLE `fan_anmeldungen` DROP COLUMN `teilnehmer_ids`;

-- =============================================================================
-- Prüfung: Vergleiche Anzahl der migrierten Teilnehmer
-- =============================================================================
-- SELECT
--     a.anmeldung_id,
--     JSON_LENGTH(a.teilnehmer_ids) as json_count,
--     (CASE WHEN a.teilnehmer1_id IS NOT NULL THEN 1 ELSE 0 END +
--      CASE WHEN a.teilnehmer2_id IS NOT NULL THEN 1 ELSE 0 END +
--      CASE WHEN a.teilnehmer3_id IS NOT NULL THEN 1 ELSE 0 END +
--      CASE WHEN a.teilnehmer4_id IS NOT NULL THEN 1 ELSE 0 END) as spalten_count
-- FROM fan_anmeldungen a
-- WHERE JSON_LENGTH(a.teilnehmer_ids) !=
--     (CASE WHEN a.teilnehmer1_id IS NOT NULL THEN 1 ELSE 0 END +
--      CASE WHEN a.teilnehmer2_id IS NOT NULL THEN 1 ELSE 0 END +
--      CASE WHEN a.teilnehmer3_id IS NOT NULL THEN 1 ELSE 0 END +
--      CASE WHEN a.teilnehmer4_id IS NOT NULL THEN 1 ELSE 0 END);
