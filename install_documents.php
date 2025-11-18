<?php
/**
 * install_documents.php - Installations-Script für Dokumentenverwaltung
 *
 * VERWENDUNG:
 * 1. Via Browser: http://localhost/Sitzungsverwaltung/install_documents.php
 * 2. Via CLI: php install_documents.php
 *
 * Führt folgende Schritte aus:
 * - Erstellt Datenbank-Tabellen
 * - Erstellt Upload-Verzeichnis
 * - Prüft Berechtigungen
 * - Gibt Installations-Status aus
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CLI oder Browser?
$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentenverwaltung Installation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #2196F3; padding-bottom: 10px; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #ddd; background: #f9f9f9; }
        .step.success { border-color: #4CAF50; background: #e8f5e9; }
        .step.error { border-color: #f44336; background: #ffebee; }
        .step.warning { border-color: #ff9800; background: #fff3e0; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .btn { display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📁 Dokumentenverwaltung Installation</h1>';
}

// Config laden
if (!file_exists(__DIR__ . '/config.php')) {
    die('FEHLER: config.php nicht gefunden!');
}

require_once __DIR__ . '/config.php';

// DB-Verbindung
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    output_step('✓ Datenbankverbindung hergestellt', 'success');
} catch (PDOException $e) {
    output_step('✗ Datenbankverbindung fehlgeschlagen: ' . $e->getMessage(), 'error');
    exit(1);
}

// Schritt 1: Tabellen erstellen
output_step('Erstelle Datenbank-Tabellen...', 'info');

try {
    // Tabelle: documents
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documents (
            document_id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            filepath VARCHAR(500) NOT NULL,
            filesize INT NOT NULL DEFAULT 0,
            filetype VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            keywords TEXT,
            version VARCHAR(50),
            short_url VARCHAR(255),
            category ENUM('satzung', 'ordnungen', 'richtlinien', 'formulare', 'mv_unterlagen', 'dokumentationen', 'urteile', 'medien', 'sonstige') DEFAULT 'sonstige',
            access_level INT DEFAULT 0,
            status ENUM('active', 'archived', 'hidden', 'outdated') DEFAULT 'active',
            uploaded_by_member_id INT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            admin_notes TEXT,
            INDEX idx_category (category),
            INDEX idx_access_level (access_level),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_title (title),
            FULLTEXT INDEX idx_search (title, description, keywords),
            FOREIGN KEY (uploaded_by_member_id) REFERENCES members(member_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    output_step('✓ Tabelle "documents" erstellt', 'success');

    // Tabelle: document_downloads
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    output_step('✓ Tabelle "document_downloads" erstellt', 'success');

} catch (PDOException $e) {
    output_step('✗ Fehler beim Erstellen der Tabellen: ' . $e->getMessage(), 'error');
    exit(1);
}

// Schritt 2: Upload-Verzeichnis erstellen
output_step('Erstelle Upload-Verzeichnis...', 'info');

$upload_dir = __DIR__ . '/uploads/documents';

if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        output_step('✓ Verzeichnis erstellt: ' . $upload_dir, 'success');
    } else {
        output_step('✗ Konnte Verzeichnis nicht erstellen: ' . $upload_dir, 'error');
        output_step('Bitte manuell erstellen: mkdir -p uploads/documents && chmod 755 uploads/documents', 'warning');
    }
} else {
    output_step('✓ Verzeichnis existiert bereits: ' . $upload_dir, 'success');
}

// Schritt 3: Berechtigungen prüfen
output_step('Prüfe Berechtigungen...', 'info');

if (is_writable($upload_dir)) {
    output_step('✓ Upload-Verzeichnis ist beschreibbar', 'success');
} else {
    output_step('✗ Upload-Verzeichnis ist NICHT beschreibbar!', 'error');
    output_step('Berechtigungen setzen: chmod 755 ' . $upload_dir, 'warning');
}

// Schritt 4: Integration in index.php prüfen
output_step('Prüfe Integration in index.php...', 'info');

$index_file = __DIR__ . '/index.php';
if (file_exists($index_file)) {
    $index_content = file_get_contents($index_file);

    if (strpos($index_content, 'tab_documents.php') !== false) {
        output_step('✓ Tab bereits in index.php integriert', 'success');
    } else {
        output_step('⚠ Tab noch nicht in index.php integriert', 'warning');
        output_step('Füge folgende Zeile in $allowed_tabs Array ein:', 'info');
        output_step("'documents' => ['label' => '📁 Dokumente', 'file' => 'tab_documents.php'],", 'info');
    }
} else {
    output_step('⚠ index.php nicht gefunden', 'warning');
}

// Schritt 5: Zusammenfassung
if (!$is_cli) echo '<hr>';

output_step('=== Installation abgeschlossen! ===', 'success');
output_step('', 'info');
output_step('Nächste Schritte:', 'info');
output_step('1. Stelle sicher dass der Tab in index.php integriert ist', 'info');
output_step('2. Öffne: http://localhost/Sitzungsverwaltung/?tab=documents', 'info');
output_step('3. Als Admin anmelden und erstes Dokument hochladen', 'info');
output_step('4. Siehe DOCUMENTS_README.md für weitere Informationen', 'info');

if (!$is_cli) {
    echo '<hr>';
    echo '<a href="?tab=documents" class="btn">Zur Dokumentenverwaltung →</a>';
    echo '</div></body></html>';
}

// Helper-Funktion
function output_step($message, $type = 'info') {
    global $is_cli;

    if ($is_cli) {
        // CLI-Ausgabe
        $prefix = '';
        switch ($type) {
            case 'success': $prefix = '[✓]'; break;
            case 'error': $prefix = '[✗]'; break;
            case 'warning': $prefix = '[⚠]'; break;
            default: $prefix = '[i]'; break;
        }
        echo "$prefix $message\n";
    } else {
        // Browser-Ausgabe
        $class = 'step ' . $type;
        echo "<div class='$class'>$message</div>";
    }
}
?>
