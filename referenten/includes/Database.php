<?php
/**
 * Datenbank-Klasse für sichere PDO-Verbindungen
 * Modernisierte Version mit Prepared Statements
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        require_once __DIR__ . '/../../config.php';

        try {
            $dsn = "mysql:host=" . MYSQL_HOST . ";dbname=" . MYSQL_DATABASE . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, $options);
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
