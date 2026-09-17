-- Migration: Dateianhänge für Tagesordnungspunkte
-- Erstellt am: 2026-04-27
-- Beschreibung: Ermöglicht Upload von Dateien zu TOPs via Drag & Drop

-- Tabelle für Attachments an TOPs
CREATE TABLE IF NOT EXISTS svagenda_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL COMMENT 'FK zu svagenda_items',
    filename VARCHAR(255) NOT NULL COMMENT 'Gespeicherter Dateiname (unique)',
    original_filename VARCHAR(255) NOT NULL COMMENT 'Original-Dateiname vom User',
    filepath VARCHAR(500) NOT NULL COMMENT 'Pfad zur Datei relativ zu uploads/',
    filesize INT NOT NULL COMMENT 'Dateigröße in Bytes',
    filetype VARCHAR(100) NOT NULL COMMENT 'MIME-Type',
    uploaded_by_member_id INT DEFAULT NULL COMMENT 'Wer hat hochgeladen (NULL wenn Member gelöscht)',
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_item_id (item_id),
    INDEX idx_uploaded_by (uploaded_by_member_id),
    INDEX idx_uploaded_at (uploaded_at),

    FOREIGN KEY (item_id) REFERENCES svagenda_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_member_id) REFERENCES svmembers(member_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upload-Verzeichnis erstellen (manuell via Shell):
-- mkdir -p uploads/agenda_attachments
-- chmod 755 uploads/agenda_attachments
