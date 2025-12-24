<?php
/**
 * Session.php - Session-Management und Hilfsfunktionen
 */

class Session {
    private static bool $started = false;

    /**
     * Startet die Session (falls noch nicht gestartet)
     */
    public static function start(): void {
        if (self::$started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            // Sichere Session-Einstellungen
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);

            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }

            session_start();
        }

        self::$started = true;
    }

    /**
     * Prüft ob ein Benutzer eingeloggt ist
     */
    public static function isLoggedIn(): bool {
        self::start();
        return isset($_SESSION['user_id']);
    }

    /**
     * Gibt die aktuelle User-ID zurück
     */
    public static function getUserId(): ?int {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Gibt die Rolle des eingeloggten Benutzers zurück
     */
    public static function getRolle(): ?string {
        self::start();
        return $_SESSION['rolle'] ?? null;
    }

    /**
     * Prüft ob der Benutzer Superuser ist
     */
    public static function isSuperuser(): bool {
        return self::getRolle() === 'superuser';
    }

    /**
     * Speichert User-Daten in der Session
     */
    public static function login(array $user): void {
        self::start();

        // Session-ID erneuern (gegen Session-Fixation)
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['rolle']   = $user['rolle'];
    }

    /**
     * Beendet die Session (Logout)
     */
    public static function logout(): void {
        self::start();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Setzt eine Flash-Nachricht (wird nach dem Anzeigen gelöscht)
     */
    public static function flash(string $message, string $type = 'info'): void {
        self::start();
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }

    /**
     * Setzt eine Erfolgsmeldung
     */
    public static function success(string $message): void {
        self::flash($message, 'success');
    }

    /**
     * Setzt eine Fehlermeldung
     */
    public static function error(string $message): void {
        self::flash($message, 'danger');
    }

    /**
     * Setzt eine Warnung
     */
    public static function warning(string $message): void {
        self::flash($message, 'warning');
    }

    /**
     * Leitet um und beendet das Skript
     */
    public static function redirect(string $url): never {
        header("Location: $url");
        exit;
    }

    /**
     * Erfordert Login, sonst Weiterleitung
     */
    public static function requireLogin(string $redirectTo = 'login.php'): void {
        if (!self::isLoggedIn()) {
            self::flash('Bitte melde dich an.', 'warning');
            self::redirect($redirectTo);
        }
    }

    /**
     * Erfordert Superuser-Rechte
     */
    public static function requireSuperuser(string $redirectTo = 'index.php'): void {
        self::requireLogin();

        if (!self::isSuperuser()) {
            self::flash('Keine Berechtigung für diesen Bereich.', 'danger');
            self::redirect($redirectTo);
        }
    }

    // =========================================================================
    // Instanz-Wrapper für Kompatibilität mit OOP-Stil
    // =========================================================================

    public function __construct() {
        self::start();
    }

    /**
     * Gibt User-Daten zurück
     */
    public function getUser(): ?array {
        if (!self::isLoggedIn()) {
            return null;
        }
        return [
            'user_id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'rolle' => $_SESSION['rolle']
        ];
    }

    /**
     * Prüft ob eingeloggt (Instanz-Methode)
     */
    public function isAdmin(): bool {
        $rolle = self::getRolle();
        return $rolle === 'admin' || $rolle === 'superuser';
    }

    /**
     * Gibt CSRF-Token zurück
     */
    public function getCsrfToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validiert CSRF-Token
     */
    public function validateCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
