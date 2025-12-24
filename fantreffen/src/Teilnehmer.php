<?php
/**
 * Teilnehmer.php - Verwaltung von Teilnehmern pro User
 * Jeder User kann bis zu 4 Teilnehmer anlegen
 */

require_once __DIR__ . '/Database.php';

class Teilnehmer {
    private Database $db;
    private const MAX_TEILNEHMER = 4;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Gibt alle Teilnehmer eines Users zurück
     */
    public function getByUser(int $userId): array {
        return $this->db->fetchAll(
            "SELECT * FROM fan_teilnehmer
             WHERE user_id = ?
             ORDER BY position ASC",
            [$userId]
        );
    }

    /**
     * Findet einen Teilnehmer anhand der ID
     */
    public function findById(int $teilnehmerId): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM fan_teilnehmer WHERE teilnehmer_id = ?",
            [$teilnehmerId]
        );
    }

    /**
     * Prüft ob User noch Teilnehmer hinzufügen kann
     */
    public function canAddMore(int $userId): bool {
        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM fan_teilnehmer WHERE user_id = ?",
            [$userId]
        );
        return $count < self::MAX_TEILNEHMER;
    }

    /**
     * Gibt die nächste freie Position zurück
     */
    public function getNextPosition(int $userId): int {
        $maxPos = $this->db->fetchColumn(
            "SELECT COALESCE(MAX(position), 0) FROM fan_teilnehmer WHERE user_id = ?",
            [$userId]
        );
        return $maxPos + 1;
    }

    /**
     * Erstellt einen neuen Teilnehmer
     */
    public function create(int $userId, array $data): ?int {
        if (!$this->canAddMore($userId)) {
            return null;
        }

        return $this->db->insert('fan_teilnehmer', [
            'user_id'  => $userId,
            'name'     => $data['name'],
            'vorname'  => $data['vorname'],
            'nickname' => $data['nickname'] ?? null,
            'mobil'    => $data['mobil'] ?? null,
            'position' => $this->getNextPosition($userId)
        ]);
    }

    /**
     * Aktualisiert einen Teilnehmer
     */
    public function update(int $teilnehmerId, int $userId, array $data): bool {
        // Sicherstellen, dass Teilnehmer dem User gehört
        $teilnehmer = $this->findById($teilnehmerId);
        if (!$teilnehmer || $teilnehmer['user_id'] !== $userId) {
            return false;
        }

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['vorname'])) $updateData['vorname'] = $data['vorname'];
        if (array_key_exists('nickname', $data)) $updateData['nickname'] = $data['nickname'];
        if (array_key_exists('mobil', $data)) $updateData['mobil'] = $data['mobil'];

        if (empty($updateData)) {
            return true;
        }

        return $this->db->update(
            'fan_teilnehmer',
            $updateData,
            'teilnehmer_id = ?',
            [$teilnehmerId]
        ) >= 0;
    }

    /**
     * Löscht einen Teilnehmer
     */
    public function delete(int $teilnehmerId, int $userId): bool {
        // Sicherstellen, dass Teilnehmer dem User gehört
        $teilnehmer = $this->findById($teilnehmerId);
        if (!$teilnehmer || $teilnehmer['user_id'] !== $userId) {
            return false;
        }

        return $this->db->delete(
            'fan_teilnehmer',
            'teilnehmer_id = ?',
            [$teilnehmerId]
        ) > 0;
    }

    /**
     * Gibt die maximale Anzahl Teilnehmer zurück
     */
    public function getMaxTeilnehmer(): int {
        return self::MAX_TEILNEHMER;
    }

    /**
     * Formatiert Teilnehmer für Anzeige
     */
    public function formatForDisplay(array $teilnehmer): string {
        $display = $teilnehmer['vorname'] . ' ' . $teilnehmer['name'];
        if (!empty($teilnehmer['nickname'])) {
            $display .= ' (' . $teilnehmer['nickname'] . ')';
        }
        return $display;
    }
}
