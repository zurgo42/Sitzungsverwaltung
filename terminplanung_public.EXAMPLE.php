<?php
/**
 * terminplanung_public.EXAMPLE.php
 *
 * WRAPPER für öffentlichen Zugriff auf die Terminplanung
 * (für Nicht-Mitglieder und Zugriff außerhalb der SSO-Schranke)
 *
 * ========================================
 * VERWENDUNG:
 * ========================================
 *
 * 1. Diese Datei als terminplanung_standalone.php in das öffentliche
 *    Verzeichnis (ohne SSO-Schutz) kopieren.
 *
 * 2. Den Pfad in Zeile 37 auf das echte Sitzungsverwaltungs-Verzeichnis
 *    anpassen (hinter der SSO-Schranke).
 *
 * 3. Das öffentliche Verzeichnis darf KEINE anderen Sitzungsverwaltungs-
 *    Dateien enthalten (nur diese eine Datei).
 *
 * ========================================
 * WAS MACHT $TERMINPLANUNG_PUBLIC_MODE?
 * ========================================
 *
 * Wenn diese Variable gesetzt ist, zeigt terminplanung_standalone.php
 * immer die eigenständige Ansicht mit eingebettetem CSS – auch wenn der
 * Nutzer eingeloggt ist. Ohne diesen Flag würde das Skript beim
 * eingeloggten Nutzer die vollständige Sitzungsverwaltungs-Oberfläche
 * (tab_termine.php) einblenden, die SSO-geschützte CSS-Dateien lädt.
 */

// Flag setzen: Standalone-CSS verwenden, kein Sitzungsverwaltungs-Header
$TERMINPLANUNG_PUBLIC_MODE = true;

// ANPASSEN: Absoluter Pfad zur echten terminplanung_standalone.php
// (im SSO-geschützten Verzeichnis)
require_once '/srv/www/vhosts/aktive/htdocs/vorstand/Sitzungsverwaltung/terminplanung_standalone.php';
