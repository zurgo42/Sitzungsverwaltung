-- Tabelle für persönliche Notizen zu Agenda Items
-- Erstellt: 2026-05-09
-- Erlaubt Teilnehmern, private Notizen zu TOPs zu machen

CREATE TABLE IF NOT EXISTS svagenda_personal_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    member_id INT NOT NULL,
    note_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes für Performance
    INDEX idx_item_member (item_id, member_id),
    INDEX idx_member (member_id),

    -- Ein User kann nur eine Notiz pro TOP haben
    UNIQUE KEY unique_note_per_member_item (item_id, member_id),

    -- Foreign Keys (optional, je nach DB-Setup)
    FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kommentar zur Tabelle
ALTER TABLE svagenda_personal_notes COMMENT = 'Persönliche Notizen von Teilnehmern zu Agenda Items';
