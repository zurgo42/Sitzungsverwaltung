<?php
/**
 * audit_loader.php - Fail-safe Einbindung des Audit-Loggings
 *
 * Lädt audit_pdo.php nur unter PHP >= 8.0 (dort wird Syntax verwendet, die ältere
 * Versionen nicht parsen können) und stellt sv_new_pdo() bereit: liefert AuditPDO,
 * wenn verfügbar, sonst ein normales PDO. So kann das Audit-Log die Anwendung nie lahmlegen.
 * Diese Datei selbst bleibt PHP-7.4-kompatibel.
 */
if (!function_exists('sv_new_pdo')) {
    if (PHP_VERSION_ID >= 80000 && !class_exists('AuditPDO', false)) {
        try {
            require_once __DIR__ . '/audit_pdo.php';
        } catch (\Throwable $e) {
            error_log('audit_pdo.php konnte nicht geladen werden: ' . $e->getMessage());
        }
    }

    function sv_new_pdo($dsn, $user, $pass, $opts = []) {
        if (class_exists('AuditPDO', false)) {
            return new AuditPDO($dsn, $user, $pass, $opts);
        }
        return new PDO($dsn, $user, $pass, $opts);
    }
}
