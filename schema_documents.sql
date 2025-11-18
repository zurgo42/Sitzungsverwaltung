-- Dokumentenverwaltung Schema
-- Erstellt: 18.11.2025

CREATE TABLE IF NOT EXISTS documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,

    -- Datei-Informationen
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    filesize INT NOT NULL DEFAULT 0,
    filetype VARCHAR(50) NOT NULL,

    -- Metadaten
    title VARCHAR(255) NOT NULL,
    description TEXT,
    keywords TEXT,
    version VARCHAR(50),
    short_url VARCHAR(255),

    -- Kategorisierung
    category ENUM('satzung', 'ordnungen', 'richtlinien', 'formulare', 'mv_unterlagen', 'dokumentationen', 'urteile', 'medien', 'sonstige') DEFAULT 'sonstige',

    -- Zugriffskontrolle
    access_level INT DEFAULT 0,
    -- 0 = alle Mitglieder
    -- 1-19 = entsprechend Rollen (siehe member-Tabelle)
    -- 99 = gelöscht/versteckt

    -- Status
    status ENUM('active', 'archived', 'hidden', 'outdated') DEFAULT 'active',

    -- Verwaltung
    uploaded_by_member_id INT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    admin_notes TEXT,

    -- Indices
    INDEX idx_category (category),
    INDEX idx_access_level (access_level),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_title (title),
    FULLTEXT INDEX idx_search (title, description, keywords),

    FOREIGN KEY (uploaded_by_member_id) REFERENCES members(member_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Download-Tracking (optional)
CREATE TABLE IF NOT EXISTS document_downloads (
    download_id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    member_id INT,
    downloaded_at DATETIME NOT NULL,
    ip_address VARCHAR(45),

    INDEX idx_document (document_id),
    INDEX idx_member (member_id),
    INDEX idx_downloaded_at (downloaded_at),

    FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dokumenten-Verzeichnis erstellen
-- mkdir -p uploads/documents
-- chmod 755 uploads/documents
