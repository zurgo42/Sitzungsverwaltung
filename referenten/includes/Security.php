<?php
/**
 * Security-Klasse für CSRF-Schutz und XSS-Prävention
 */

class Security {
    /**
     * Generiert ein CSRF-Token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Überprüft das CSRF-Token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Escaped HTML-Output zur XSS-Prävention
     */
    public static function escape($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Bereinigt Input-Daten
     */
    public static function cleanInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'cleanInput'], $data);
        }
        return trim(strip_tags($data));
    }

    /**
     * Validiert E-Mail-Adresse
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validiert PLZ (5-stellig)
     */
    public static function isValidPLZ($plz) {
        return preg_match('/^\d{5}$/', $plz);
    }

    /**
     * Validiert MNr
     */
    public static function isValidMNr($mNr) {
        return preg_match('/^\d{9}$/', $mNr);
    }

    /**
     * Loggt Zugriffe (sichere Version)
     */
    public static function logAccess($mNr, $action = 'access') {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'mNr' => $mNr,
            'action' => $action,
            'ip' => self::getClientIP(),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        error_log(json_encode($logData));
    }

    /**
     * Ermittelt Client-IP sicher
     */
    public static function getClientIP() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Bei X-Forwarded-For kann es mehrere IPs geben
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                // Validiere IP
                if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    return substr(trim($ip), 0, 45); // Max IPv6 Länge
                }
            }
        }

        return 'unknown';
    }
}
