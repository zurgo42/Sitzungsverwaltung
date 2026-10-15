<?php
/**
 * demo.php - Einstieg zum Demo-Daten-Generator
 *
 * Der frühere Mini-Generator (8 Mitglieder, 3 Meetings) wurde durch tools/demo_build.php ersetzt:
 * ein deterministischer Builder, der einen vollständigen Demo-Stand mit allen Funktionen aus
 * frei erfundenen Daten als tools/demo_data.json schreibt (Import danach mit tools/demo_import.php).
 *
 * Aufruf im Browser: demo.php (System-Admin-Passwort erforderlich, DEMO_MODE_ENABLED = true)
 * Aufruf per CLI:    php tools/demo_build.php [--anchor=YYYY-MM-DD]
 */

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/tools/demo_build.php';

exit(demo_build_main());
