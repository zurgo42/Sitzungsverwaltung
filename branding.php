<?php
/**
 * Name der Anwendung (Familie MISS / KONA / VERA). Vereinsname und Logo stehen in config.php (ORG_NAME, ORG_LOGO).
 */
if (!defined('SV_APP_NAME')) define('SV_APP_NAME', 'KONA');
if (!defined('SV_APP_TAG')) define('SV_APP_TAG', 'Konferenz- und Antragstool');
if (!defined('SV_APP_LONG')) define('SV_APP_LONG', 'Konferenz- und Antragstool für Sitzungen, Anträge und Beschlüsse');

/**
 * Vereinsname für die Kopfzeile: zuerst ORG_NAME aus der config.php, sonst (gemeinsame Datenbank mit MISS) der Vereinsname
 * aus dessen Einstellungen (md_settings: org.name), sonst leer.
 */
function sv_org_name(?PDO $pdo = null): string
{
    static $name = null;
    if ($name !== null) return $name;
    $name = defined('ORG_NAME') ? trim((string)ORG_NAME) : '';
    if ($name === '' && $pdo) {
        try {
            foreach (['org.name', 'club.name'] as $key) {
                $st = $pdo->prepare('SELECT setting_value FROM md_settings WHERE setting_key = ?');
                $st->execute([$key]);
                $v = trim((string)$st->fetchColumn());
                if ($v !== '') { $name = $v; break; }
            }
        } catch (Throwable $e) { /* MISS nicht installiert: kein Vereinsname */ }
    }
    return $name;
}

/**
 * Logo für die Kopfzeile: ORG_LOGO aus der config.php (z. B. '../MISS/logo.php' für dasselbe Logo wie in MISS),
 * sonst das Standardzeichen aus logo.php (Wappen mit dem Anfangsbuchstaben des Vereins).
 */
function sv_org_logo(string $orgName = ''): string
{
    $logo = defined('ORG_LOGO') ? trim((string)ORG_LOGO) : '';
    if ($logo !== '') return $logo;
    return 'logo.php' . ($orgName !== '' ? '?i=' . rawurlencode(mb_strtoupper(mb_substr($orgName, 0, 1))) : '');
}
