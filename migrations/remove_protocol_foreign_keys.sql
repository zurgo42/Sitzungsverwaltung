-- Migration: Foreign Key Constraints für SSO-Adapter entfernen
-- Datum: 2026-01-17
-- Beschreibung: Bei Verwendung des SSO-Adapters existiert svmembers nicht,
--               daher müssen die Foreign Keys entfernt werden

-- 1. Foreign Key von protocol_master_id entfernen
ALTER TABLE svagenda_items
DROP FOREIGN KEY IF EXISTS fk_protocol_master;

-- 2. Foreign Key von svprotocol_versions.modified_by entfernen
ALTER TABLE svprotocol_versions
DROP FOREIGN KEY IF EXISTS svprotocol_versions_ibfk_2;

-- 3. Foreign Key von svprotocol_editing.member_id entfernen
ALTER TABLE svprotocol_editing
DROP FOREIGN KEY IF EXISTS svprotocol_editing_ibfk_2;

-- 4. Foreign Key von svprotocol_changes_queue.member_id entfernen
ALTER TABLE svprotocol_changes_queue
DROP FOREIGN KEY IF EXISTS svprotocol_changes_queue_ibfk_2;

-- Die Spalten bleiben erhalten, nur die Foreign Keys werden entfernt
-- So können member_ids aus dem SSO-System verwendet werden
