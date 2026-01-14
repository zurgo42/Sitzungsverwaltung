-- Migration: Kollaboratives Protokoll-Feature
-- Datum: 2026-01-14
-- Beschreibung: Fügt Umschalt-Möglichkeit zwischen klassischem und kollaborativem Protokoll-Modus hinzu

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
