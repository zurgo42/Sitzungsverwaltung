<?php
/**
 * Reise.php - Verwaltung von Fantreffen-Reisen
 */

require_once __DIR__ . '/Database.php';

class Reise {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Erstellt eine neue Reise
     */
    public function create(array $data): int {
        return $this->db->insert('fan_reisen', [
            'schiff'           => $data['schiff'],
            'bahnhof'          => $data['bahnhof'] ?? null,
            'anfang'           => $data['anfang'],
            'ende'             => $data['ende'],
            'treffen_ort'      => $data['treffen_ort'] ?? null,
            'treffen_zeit'     => $data['treffen_zeit'] ?? null,
            'treffen_status'   => $data['treffen_status'] ?? 'geplant',
            'treffen_info'     => $data['treffen_info'] ?? null,
            'link_wasserurlaub'=> $data['link_wasserurlaub'] ?? null,
            'link_facebook'    => $data['link_facebook'] ?? null,
            'link_kids'        => $data['link_kids'] ?? null,
            'erstellt_von'     => $data['erstellt_von'] ?? null
        ]);
    }

    /**
     * Findet eine Reise anhand der ID
     */
    public function findById(int $reiseId): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM fan_reisen WHERE reise_id = ?",
            [$reiseId]
        );
    }

    /**
     * Gibt alle aktiven Reisen zurück (Enddatum in der Zukunft)
     */
    public function getAktive(): array {
        return $this->db->fetchAll(
            "SELECT r.*, COUNT(a.anmeldung_id) AS anzahl_anmeldungen
             FROM fan_reisen r
             LEFT JOIN fan_anmeldungen a ON r.reise_id = a.reise_id
             WHERE r.ende >= CURDATE()
             GROUP BY r.reise_id
             ORDER BY r.anfang ASC"
        );
    }

    /**
     * Alias für getAktive()
     */
    public function getAktiveReisen(): array {
        return $this->getAktive();
    }

    /**
     * Gibt alle Anmeldungen eines Users zurück
     */
    public function getAnmeldungenByUser(int $userId): array {
        return $this->db->fetchAll(
            "SELECT a.*, r.schiff, r.anfang, r.ende, r.treffen_status, r.treffen_ort, r.treffen_zeit
             FROM fan_anmeldungen a
             JOIN fan_reisen r ON a.reise_id = r.reise_id
             WHERE a.user_id = ?
             ORDER BY r.anfang DESC",
            [$userId]
        );
    }

    /**
     * Gibt alle vergangenen Reisen zurück
     */
    public function getVergangene(int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT r.*, COUNT(a.anmeldung_id) AS anzahl_anmeldungen
             FROM fan_reisen r
             LEFT JOIN fan_anmeldungen a ON r.reise_id = a.reise_id
             WHERE r.ende < CURDATE()
             GROUP BY r.reise_id
             ORDER BY r.anfang DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Gibt alle Reisen zurück (für Admin)
     */
    public function getAll(): array {
        return $this->db->fetchAll(
            "SELECT r.*, COUNT(a.anmeldung_id) AS anzahl_anmeldungen,
                    u.email AS ersteller_email
             FROM fan_reisen r
             LEFT JOIN fan_anmeldungen a ON r.reise_id = a.reise_id
             LEFT JOIN fan_users u ON r.erstellt_von = u.user_id
             GROUP BY r.reise_id
             ORDER BY r.anfang DESC"
        );
    }

    /**
     * Aktualisiert eine Reise
     */
    public function update(int $reiseId, array $data): bool {
        return $this->db->update('fan_reisen', $data, 'reise_id = ?', [$reiseId]) > 0;
    }

    /**
     * Aktualisiert den Treffen-Status
     */
    public function updateStatus(int $reiseId, string $status, ?string $info = null): bool {
        $data = ['treffen_status' => $status];

        if ($info !== null) {
            $data['treffen_info'] = $info;
        }

        return $this->update($reiseId, $data);
    }

    /**
     * Löscht eine Reise
     */
    public function delete(int $reiseId): bool {
        return $this->db->delete('fan_reisen', 'reise_id = ?', [$reiseId]) > 0;
    }

    /**
     * Fügt einen Admin für eine Reise hinzu
     */
    public function addAdmin(int $reiseId, int $userId): bool {
        try {
            $this->db->insert('fan_reise_admins', [
                'reise_id' => $reiseId,
                'user_id'  => $userId
            ]);
            return true;
        } catch (Exception $e) {
            return false; // Vermutlich bereits vorhanden
        }
    }

    /**
     * Entfernt einen Admin von einer Reise
     */
    public function removeAdmin(int $reiseId, int $userId): bool {
        return $this->db->delete(
            'fan_reise_admins',
            'reise_id = ? AND user_id = ?',
            [$reiseId, $userId]
        ) > 0;
    }

    /**
     * Gibt alle Admins einer Reise zurück
     */
    public function getAdmins(int $reiseId): array {
        return $this->db->fetchAll(
            "SELECT u.user_id, u.email
             FROM fan_reise_admins ra
             JOIN fan_users u ON ra.user_id = u.user_id
             WHERE ra.reise_id = ?",
            [$reiseId]
        );
    }

    /**
     * Gibt die Anzahl der Teilnehmer für eine Reise zurück
     */
    public function getTeilnehmerAnzahl(int $reiseId): int {
        return (int) $this->db->fetchColumn(
            "SELECT SUM(
                (CASE WHEN teilnehmer1_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN teilnehmer2_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN teilnehmer3_id IS NOT NULL THEN 1 ELSE 0 END) +
                (CASE WHEN teilnehmer4_id IS NOT NULL THEN 1 ELSE 0 END)
             )
             FROM fan_anmeldungen
             WHERE reise_id = ?",
            [$reiseId]
        );
    }

    /**
     * Formatiert die Reise für die Anzeige
     */
    public function formatForDisplay(array $reise): array {
        $anfang = new DateTime($reise['anfang']);
        $ende = new DateTime($reise['ende']);

        $reise['anfang_formatiert'] = $anfang->format('d.m.Y');
        $reise['ende_formatiert'] = $ende->format('d.m.Y');
        $reise['dauer_tage'] = $anfang->diff($ende)->days + 1;

        $reise['titel'] = $reise['schiff'];
        if (!empty($reise['bahnhof'])) {
            $reise['titel'] .= ' ab ' . $reise['bahnhof'];
        }

        return $reise;
    }

    /**
     * Gibt Schiffsbild-URL zurück
     */
    public function getSchiffBild(string $schiff): string {
        // Schiffsname zu Dateiname (z.B. "AIDAprima" -> "aidaprima")
        $name = strtolower($schiff);

        $bildPfad = "images/$name.jpg";

        // Fallback auf Standard-Bild
        if (!file_exists(__DIR__ . "/../public/$bildPfad")) {
            return "images/aida.jpg";
        }

        return $bildPfad;
    }

    /**
     * Prüft ob ein User Admin einer bestimmten Reise ist
     */
    public function isReiseAdmin(int $reiseId, int $userId): bool {
        $result = $this->db->fetchOne(
            "SELECT 1 FROM fan_reise_admins WHERE reise_id = ? AND user_id = ?",
            [$reiseId, $userId]
        );
        return $result !== null;
    }

    /**
     * Gibt alle Reisen zurück, bei denen der User Admin ist
     */
    public function getAdminReisen(int $userId): array {
        return $this->db->fetchAll(
            "SELECT r.reise_id FROM fan_reise_admins ra
             JOIN fan_reisen r ON ra.reise_id = r.reise_id
             WHERE ra.user_id = ?",
            [$userId]
        );
    }
}
