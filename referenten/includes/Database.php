<?php
/**
 * Datenbank-Klasse für sichere PDO-Verbindungen
 * Modernisierte Version mit Prepared Statements
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Lade die LOKALE config.php (im referenten/ Verzeichnis)
        $configPath = __DIR__ . '/../config.php';

        if (!file_exists($configPath)) {
            throw new Exception("config.php nicht gefunden! Bitte erstellen Sie referenten/config.php");
        }

        require_once $configPath;

        try {
            // Unterstütze beide Konstantennamen (DB_* und MYSQL_*)
            $host = defined('DB_HOST') ? DB_HOST : (defined('MYSQL_HOST') ? MYSQL_HOST : 'localhost');
            $user = defined('DB_USER') ? DB_USER : (defined('MYSQL_USER') ? MYSQL_USER : 'root');
            $pass = defined('DB_PASS') ? DB_PASS : (defined('MYSQL_PASS') ? MYSQL_PASS : '');
            $name = defined('DB_NAME') ? DB_NAME : (defined('MYSQL_DATABASE') ? MYSQL_DATABASE : '');

            $dsn = "mysql:host=" . $host . ";dbname=" . $name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Datenbankverbindung fehlgeschlagen");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Verhindere Klonen und Deserialisierung
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
