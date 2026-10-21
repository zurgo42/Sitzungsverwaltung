<?php
/**
 * file_helpers.php - Gemeinsame Hilfsfunktionen für Dateien auf dem Server
 *
 * - sv_find_file():    findet eine Datei trotz abweichender Schreibweise/Kodierung des Namens
 * - sv_locate_file():  findet die Datei eines Datenbankeintrags auch bei relativem Verzeichnis aus dem älteren System
 * - sv_clean_filename(): bereinigt einen neuen Dateinamen (ohne Endung) für das Dateisystem
 * Einbindung: require_once __DIR__ . '/file_helpers.php';
 */

/**
 * Findet die Datei zu einem in der Datenbank gespeicherten Namen auch dann, wenn Schreibweise oder Kodierung abweichen
 * (Umlaute/Leerzeichen: URL- oder HTML-kodiert, ISO-8859-1 statt UTF-8, zerlegte Umlaute). Gibt den echten Pfad oder null zurück.
 */
function sv_find_file(string $verz, string $name): ?string {
    if (file_exists($verz . $name)) return $verz . $name;

    $norm = function (string $s): string {
        $s = rawurldecode(str_replace('+', '%2B', $s));          // %20, %C3%84 ... (Plus-Zeichen bleibt)
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');   // &auml; ...
        $cp = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');          // doppelt kodiert? (Ã¼ -> ü)
        if ($cp !== false && $cp !== $s && mb_check_encoding($cp, 'UTF-8') && preg_match('/[^\x00-\x7F]/', $cp)) {
            $s = $cp;
        }
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');   // Latin-1-Bytes -> UTF-8
        }
        if (class_exists('Normalizer')) {
            $s = Normalizer::normalize($s, Normalizer::FORM_C) ?: $s;   // zerlegte Umlaute zusammensetzen
        }
        return $s;
    };

    // Typische Varianten direkt probieren
    $candidates = [
        rawurldecode($name),
        urldecode($name),
        html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        mb_convert_encoding($name, 'ISO-8859-1', 'UTF-8'),
        mb_convert_encoding($name, 'Windows-1252', 'UTF-8'),     // doppelt kodierte Umlaute (UTF-8 als Latin-1/cp1252 gespeichert)
        mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1'),
    ];
    foreach ($candidates as $c) {
        if ($c !== '' && file_exists($verz . $c)) return $verz . $c;
    }

    // Verzeichnis durchsuchen und normalisiert vergleichen
    $dir = @scandir($verz);
    if ($dir) {
        $want = $norm($name);
        foreach ($dir as $f) {
            if ($f === '.' || $f === '..') continue;
            if ($norm($f) === $want) return $verz . $f;
        }
    }
    return null;
}

/**
 * Sucht die Datei eines Eintrags. Ein relatives Verzeichnis (z. B. "../docs/") stammt oft aus dem älteren System und
 * bezieht sich dort auf ein anderes Arbeitsverzeichnis; deshalb werden mehrere Basisverzeichnisse probiert
 * (aktuelles Verzeichnis, Verzeichnis dieses Skripts, dessen Eltern, DOCUMENT_ROOT).
 * Rückgabe: ['pfad' => gefundener Pfad|null, 'versucht' => [durchsuchte Verzeichnisse]]
 */
function sv_locate_file(string $verz, string $name): array {
    $app_dir = __DIR__;   // Verzeichnis der Sitzungsverwaltung
    $bases = [''];
    if ($verz !== '' && $verz[0] !== '/' && !preg_match('/^[A-Za-z]:[\\\\\/]/', $verz)) {
        $bases = ['', $app_dir . '/', dirname($app_dir) . '/', dirname($app_dir, 2) . '/'];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) $bases[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
    }
    $tried = [];
    foreach ($bases as $base) {
        $dir = $base . $verz;
        if (substr($dir, -1) !== '/') $dir .= '/';
        $real = is_dir($dir) ? (realpath($dir) ?: $dir) : $dir;
        if (in_array($real, $tried, true)) continue;
        $tried[] = $real;
        $found = sv_find_file($dir, $name);
        if ($found !== null) return ['pfad' => $found, 'versucht' => $tried];
    }
    return ['pfad' => null, 'versucht' => $tried];
}

/** Dateinamen für den Server bereinigen (ohne Endung): Umlaute umschreiben, nur A-Z a-z 0-9 . _ -, max. 100 Zeichen */
function sv_clean_filename(string $base, string $ext): string {
    $base = trim($base);
    if ($ext !== '' && strcasecmp(substr($base, -strlen($ext) - 1), '.' . $ext) === 0) {
        $base = substr($base, 0, -strlen($ext) - 1);   // versehentlich mit eingegebene Endung entfernen
    }
    $base = strtr($base, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
    $base = trim(preg_replace('/_+/', '_', $base), '._-');
    return substr($base, 0, 100);
}
