-- Migration: Erstelle Lock-Tabelle für kollaborative Mitschrift
-- Verhindert gleichzeitiges Editieren durch mehrere User

CREATE TABLE IF NOT EXISTS svprotocol_lock (
    item_id INT NOT NULL,
    member_id INT NOT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY idx_member (member_id),
    KEY idx_locked_at (locked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alte Queue- und Editing-Tabellen werden nicht mehr benötigt
-- (werden aber nicht gelöscht, falls Rollback nötig ist)
