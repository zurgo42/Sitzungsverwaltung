-- Migrations-Bundle: Kollaboratives Protokoll Komplett
-- Datum: 2026-01-15
-- Führt alle 3 Migrationen in der richtigen Reihenfolge aus

-- ============================================================================
-- MIGRATION 1: Basis Kollaboratives Protokoll (v2.0)
-- ============================================================================

-- Spalte für Meeting-Einstellung hinzufügen
ALTER TABLE svmeetings
ADD COLUMN collaborative_protocol TINYINT(1) DEFAULT 0
COMMENT 'Kollaboratives Protokoll: 0=Nur Protokollführung, 1=Alle Teilnehmer können schreiben'
AFTER secretary_name;

-- Tabelle für Auto-Save Versionen (Konflikt-Erkennung)
CREATE TABLE IF NOT EXISTS svprotocol_versions (
    version_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    protocol_text TEXT,
    modified_by INT NOT NULL,
    modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    version_hash VARCHAR(64) NOT NULL COMMENT 'MD5-Hash des Inhalts für Konflikt-Erkennung',
    INDEX idx_item_id (item_id),
    INDEX idx_modified_at (modified_at),
    FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (modified_by) REFERENCES svmembers(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabelle für Edit-Locks (wer editiert gerade)
CREATE TABLE IF NOT EXISTS svprotocol_editing (
    item_id INT PRIMARY KEY,
    member_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracking wer gerade am Protokoll schreibt (kein Lock, nur Info)';

-- ============================================================================
-- MIGRATION 2: Force-Update Tracking (v2.x - optional, nicht kritisch)
-- ============================================================================

-- Spalte für Force-Update Tracking
ALTER TABLE svagenda_items
ADD COLUMN force_update_at TIMESTAMP NULL DEFAULT NULL
COMMENT 'Timestamp des letzten Force-Updates (Prioritäts-Button)'
AFTER protocol_notes;

-- Index für schnellere Abfragen
CREATE INDEX idx_force_update ON svagenda_items(force_update_at);

-- ============================================================================
-- MIGRATION 3: Queue-System (v3.0)
-- ============================================================================

-- Tabelle für Änderungs-Queue
CREATE TABLE IF NOT EXISTS svprotocol_changes_queue (
    change_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    member_id INT NOT NULL,
    protocol_text TEXT NOT NULL COMMENT 'Vollständiger Protokolltext (nicht nur Delta)',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed TINYINT(1) DEFAULT 0 COMMENT '0=Wartet, 1=Verarbeitet',
    processed_at TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_item_processed (item_id, processed),
    INDEX idx_submitted (submitted_at),

    FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES svmembers(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Queue für kollaborative Protokoll-Änderungen (chronologisch verarbeitet)';

-- Spalte für Master-Tracking (wer ist aktuell Protokollführung)
ALTER TABLE svagenda_items
ADD COLUMN protocol_master_id INT NULL DEFAULT NULL
COMMENT 'Member-ID der Protokollführung (Master-System für diesen TOP)'
AFTER protocol_notes;

-- Foreign Key für protocol_master_id
ALTER TABLE svagenda_items
ADD CONSTRAINT fk_protocol_master
FOREIGN KEY (protocol_master_id) REFERENCES svmembers(member_id) ON DELETE SET NULL;

-- Index für schnellere Abfragen
CREATE INDEX idx_protocol_master ON svagenda_items(protocol_master_id);
