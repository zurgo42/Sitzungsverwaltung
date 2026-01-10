<?php
/**
 * Run migration: Add file attachments to comments
 * Created: 2026-01-10
 */

require_once 'config.php';

// Database connection
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

try {
    echo "Running migration: Add file attachments to comments...\n";

    // Add columns to svagenda_comments table
    echo "Updating svagenda_comments table...\n";
    $pdo->exec("
        ALTER TABLE svagenda_comments
        ADD COLUMN attachment_filename VARCHAR(500) DEFAULT NULL AFTER comment_text,
        ADD COLUMN attachment_original_name VARCHAR(255) DEFAULT NULL AFTER attachment_filename,
        ADD COLUMN attachment_size INT DEFAULT NULL AFTER attachment_original_name,
        ADD COLUMN attachment_mime_type VARCHAR(100) DEFAULT NULL AFTER attachment_size,
        ADD INDEX idx_attachment (attachment_filename)
    ");
    echo "  ✓ svagenda_comments updated\n";

    // Add columns to svagenda_live_comments table
    echo "Updating svagenda_live_comments table...\n";
    $pdo->exec("
        ALTER TABLE svagenda_live_comments
        ADD COLUMN attachment_filename VARCHAR(500) DEFAULT NULL AFTER comment_text,
        ADD COLUMN attachment_original_name VARCHAR(255) DEFAULT NULL AFTER attachment_filename,
        ADD COLUMN attachment_size INT DEFAULT NULL AFTER attachment_original_name,
        ADD COLUMN attachment_mime_type VARCHAR(100) DEFAULT NULL AFTER attachment_size,
        ADD INDEX idx_attachment (attachment_filename)
    ");
    echo "  ✓ svagenda_live_comments updated\n";

    echo "\n✓ Migration completed successfully!\n";
    echo "  - Added attachment columns to both tables\n";
    echo "  - Added indexes for efficient file lookups\n";

} catch (PDOException $e) {
    // Check if columns already exist
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "⚠ Migration already applied (columns exist)\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
