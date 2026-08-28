<?php
/**
 * apply_meeting_decisions_migration.php
 * Applies meeting decisions migration manually
 */

require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    echo "Applying migration...\n";

    // 1. Add allow_decisions to svmeetings
    try {
        $pdo->exec("ALTER TABLE svmeetings ADD COLUMN allow_decisions TINYINT(1) DEFAULT 1 COMMENT 'Beschlüsse in dieser Sitzung erlauben (1=ja, 0=nein)'");
        echo "✓ Added allow_decisions to svmeetings\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "- allow_decisions already exists in svmeetings\n";
        } else {
            throw $e;
        }
    }

    // 2. Add antrnr to svagenda_items
    try {
        $pdo->exec("ALTER TABLE svagenda_items ADD COLUMN antrnr VARCHAR(15) NULL COMMENT 'Verknüpfung zu antraege.antrnr für Beschlussfassung'");
        echo "✓ Added antrnr to svagenda_items\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "- antrnr already exists in svagenda_items\n";
        } else {
            throw $e;
        }
    }

    // 3. Add index on antrnr
    try {
        $pdo->exec("ALTER TABLE svagenda_items ADD INDEX idx_antrnr (antrnr)");
        echo "✓ Added index on antrnr\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "- Index idx_antrnr already exists\n";
        } else {
            throw $e;
        }
    }

    // 4. Add meeting_id to antraege
    try {
        $pdo->exec("ALTER TABLE antraege ADD COLUMN meeting_id INT NULL COMMENT 'Verknüpfung zur Sitzung falls Präsenzantrag'");
        echo "✓ Added meeting_id to antraege\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "- meeting_id already exists in antraege\n";
        } else {
            throw $e;
        }
    }

    // 5. Add index on meeting_id
    try {
        $pdo->exec("ALTER TABLE antraege ADD INDEX idx_meeting (meeting_id)");
        echo "✓ Added index on meeting_id\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "- Index idx_meeting already exists\n";
        } else {
            throw $e;
        }
    }

    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
