<?php
/**
 * Name der Anwendung (Familie MISS / KONA / VERA). Vereinsname und Logo stehen in config.php (ORG_NAME, ORG_LOGO).
 */
if (!defined('SV_APP_NAME')) define('SV_APP_NAME', 'KONA');
if (!defined('SV_APP_TAG')) define('SV_APP_TAG', 'Konferenz- und Antragstool');
if (!defined('SV_APP_LONG')) define('SV_APP_LONG', 'Konferenz- und Antragstool für Sitzungen, Anträge und Beschlüsse');

/**
 * Vereinsname für die Kopfzeile: ORG_NAME aus der config.php (unabhängig von MISS und vom Betrieb mit oder ohne Adapter).
 * Ohne Eintrag bleibt er leer, dann steht in der Kopfzeile "Sitzungsverwaltung".
 */
function sv_org_name(?PDO $pdo = null): string
{
    return defined('ORG_NAME') ? trim((string)ORG_NAME) : '';
}

/**
 * Basispfad der Anwendung auf dem Server (z. B. "/Sitzungsverwaltung"): STANDALONE_PATH aus der config.php,
 * sonst aus dem Pfad des laufenden Skripts. Ohne Schrägstrich am Ende; im Wurzelverzeichnis ein leerer Text.
 */
function sv_base_path(): string
{
    if (defined('STANDALONE_PATH') && trim((string)STANDALONE_PATH) !== '') return rtrim((string)STANDALONE_PATH, '/');
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}

/**
 * Logo für die Kopfzeile: ORG_LOGO aus der config.php (Pfad oder URL, z. B. 'logo.png'),
 * sonst das Standardzeichen aus logo.php (Wappen mit dem Anfangsbuchstaben des Vereins).
 */
function sv_org_logo(string $orgName = ''): string
{
    $logo = defined('ORG_LOGO') ? trim((string)ORG_LOGO) : '';
    if ($logo !== '') return $logo;
    return 'logo.php' . ($orgName !== '' ? '?i=' . rawurlencode(mb_strtoupper(mb_substr($orgName, 0, 1))) : '');
}
