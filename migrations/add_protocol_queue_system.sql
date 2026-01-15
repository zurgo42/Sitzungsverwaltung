-- Migration: Queue-basiertes kollaboratives Protokoll (Master-Slave Pattern)
-- Datum: 2026-01-15
-- Beschreibung: Ersetzt Peer-to-Peer durch stabiles Queue-System mit Protokollführung als Master

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
