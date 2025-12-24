<?php
/**
 * User.php - Benutzerverwaltung mit sicherem Passwort-Handling
 */

require_once __DIR__ . '/Database.php';

class User {
    private Database $db;

    public function __construct(?Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Registriert einen neuen Benutzer
     */
    public function register(string $email, string $passwort, string $rolle = 'user'): int {
        $email = strtolower(trim($email));

        // Prüfen ob Email bereits existiert
        if ($this->findByEmail($email)) {
            throw new Exception("Diese E-Mail-Adresse ist bereits registriert.");
        }

        // Passwort hashen (bcrypt)
        $hash = password_hash($passwort, PASSWORD_DEFAULT);

        return $this->db->insert('fan_users', [
            'email'         => $email,
            'passwort_hash' => $hash,
            'rolle'         => $rolle,
            'erstellt'      => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Prüft Login-Daten und gibt User-Daten zurück
     */
    public function login(string $email, string $passwort): ?array {
        $email = strtolower(trim($email));

        $user = $this->db->fetchOne(
            "SELECT * FROM fan_users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            return null;
        }

        // Passwort prüfen
        if (!password_verify($passwort, $user['passwort_hash'])) {
            return null;
        }

        // Letzten Login aktualisieren
        $this->db->execute(
            "UPDATE fan_users SET letzter_login = NOW() WHERE user_id = ?",
            [$user['user_id']]
        );

        // Passwort-Hash nicht zurückgeben
        unset($user['passwort_hash']);

        return $user;
    }

    /**
     * Findet Benutzer anhand der E-Mail
     */
    public function findByEmail(string $email, bool $includeHash = false): ?array {
        $fields = $includeHash
            ? "user_id, email, passwort_hash, rolle, erstellt, letzter_login"
            : "user_id, email, rolle, erstellt, letzter_login";

        return $this->db->fetchOne(
            "SELECT $fields FROM fan_users WHERE email = ?",
            [strtolower(trim($email))]
        );
    }

    /**
     * Findet Benutzer anhand der ID
     */
    public function findById(int $userId): ?array {
        return $this->db->fetchOne(
            "SELECT user_id, email, rolle, erstellt, letzter_login FROM fan_users WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Aktualisiert das Passwort
     */
    public function updatePassword(int $userId, string $neuesPasswort): bool {
        $hash = password_hash($neuesPasswort, PASSWORD_DEFAULT);

        return $this->db->execute(
            "UPDATE fan_users SET passwort_hash = ? WHERE user_id = ?",
            [$hash, $userId]
        ) > 0;
    }

    /**
     * Aktualisiert die Rolle eines Benutzers (nur Superuser)
     */
    public function updateRolle(int $userId, string $rolle): bool {
        $erlaubteRollen = ['user', 'admin', 'superuser'];

        if (!in_array($rolle, $erlaubteRollen)) {
            throw new Exception("Ungültige Rolle: $rolle");
        }

        return $this->db->execute(
            "UPDATE fan_users SET rolle = ? WHERE user_id = ?",
            [$rolle, $userId]
        ) > 0;
    }

    /**
     * Alias für updateRolle
     */
    public function updateRole(int $userId, string $rolle): bool {
        return $this->updateRolle($userId, $rolle);
    }

    /**
     * Prüft ob Benutzer Admin für eine bestimmte Reise ist
     */
    public function isAdminForReise(int $userId, int $reiseId): bool {
        $result = $this->db->fetchOne(
            "SELECT 1 FROM fan_reise_admins WHERE user_id = ? AND reise_id = ?",
            [$userId, $reiseId]
        );

        return $result !== null;
    }

    /**
     * Prüft ob Benutzer Superuser ist
     */
    public function isSuperuser(int $userId): bool {
        $user = $this->findById($userId);
        return $user && $user['rolle'] === 'superuser';
    }

    /**
     * Gibt alle Benutzer zurück (für Superuser)
     */
    public function getAll(): array {
        return $this->db->fetchAll(
            "SELECT user_id, email, rolle, erstellt, letzter_login
             FROM fan_users
             ORDER BY erstellt DESC"
        );
    }

    /**
     * Löscht einen Benutzer
     */
    public function delete(int $userId): bool {
        return $this->db->delete('fan_users', 'user_id = ?', [$userId]) > 0;
    }

    /**
     * Generiert ein Passwort-Reset-Token
     */
    public function generateResetToken(string $email): ?string {
        $user = $this->findByEmail($email);

        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->db->execute(
            "UPDATE fan_users SET reset_token = ?, reset_expires = ? WHERE user_id = ?",
            [$token, $expires, $user['user_id']]
        );

        return $token;
    }

    /**
     * Setzt Passwort mit Reset-Token zurück
     */
    public function resetPassword(string $token, string $neuesPasswort): bool {
        $user = $this->db->fetchOne(
            "SELECT user_id FROM fan_users
             WHERE reset_token = ? AND reset_expires > NOW()",
            [$token]
        );

        if (!$user) {
            return false;
        }

        $hash = password_hash($neuesPasswort, PASSWORD_DEFAULT);

        return $this->db->execute(
            "UPDATE fan_users SET passwort_hash = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?",
            [$hash, $user['user_id']]
        ) > 0;
    }
}
