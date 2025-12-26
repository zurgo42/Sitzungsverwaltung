<?php
/**
 * Database.php - PDO-Wrapper für sichere Datenbankzugriffe
 *
 * Verwendung:
 *   $db = Database::getInstance();
 *   $users = $db->fetchAll("SELECT * FROM users WHERE rolle = ?", ['admin']);
 *   $user = $db->fetchOne("SELECT * FROM users WHERE user_id = ?", [123]);
 *   $db->execute("UPDATE users SET letzter_login = NOW() WHERE user_id = ?", [123]);
 *   $newId = $db->insert("users", ['email' => 'test@example.de', 'rolle' => 'user']);
 */

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $configFile = __DIR__ . '/../config/config.php';

        if (!file_exists($configFile)) {
            throw new Exception("Konfigurationsdatei nicht gefunden: $configFile");
        }

        require_once $configFile;

        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=utf8mb4",
            DB_HOST,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    }

    /**
     * Singleton-Pattern: Gibt immer dieselbe Instanz zurück
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt alle Ergebnisse zurück
     */
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt eine Zeile zurück
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Führt eine SELECT-Abfrage aus und gibt einen einzelnen Wert zurück
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn($column);
    }

    /**
     * Führt INSERT, UPDATE oder DELETE aus
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Fügt einen Datensatz ein und gibt die neue ID zurück
     */
    public function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Aktualisiert Datensätze
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = ?";
        }
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE $table SET $setClause WHERE $where";

        $params = array_merge(array_values($data), $whereParams);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Löscht Datensätze
     */
    public function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Startet eine Transaktion
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    /**
     * Bestätigt eine Transaktion
     */
    public function commit(): bool {
        return $this->pdo->commit();
    }

    /**
     * Macht eine Transaktion rückgängig
     */
    public function rollback(): bool {
        return $this->pdo->rollBack();
    }

    /**
     * Gibt das PDO-Objekt für erweiterte Operationen zurück
     */
    public function getPdo(): PDO {
        return $this->pdo;
    }
}
