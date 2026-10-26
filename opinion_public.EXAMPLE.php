<?php
/**
 * opinion_public.EXAMPLE.php
 *
 * WRAPPER für öffentlichen Zugriff auf das Meinungsbild-Tool
 * (für Nicht-Mitglieder und Zugriff außerhalb der SSO-Schranke)
 *
 * ========================================
 * VERWENDUNG:
 * ========================================
 *
 * 1. Diese Datei als opinion_standalone.php in das öffentliche
 *    Verzeichnis (ohne SSO-Schutz) kopieren.
 *
 * 2. Die beiden Pfad-/URL-Angaben unten anpassen.
 *
 * 3. Das öffentliche Verzeichnis darf KEINE anderen Sitzungsverwaltungs-
 *    Dateien enthalten (nur diese eine Datei).
 *
 * ========================================
 * WAS MACHT $OPINION_PUBLIC_MODE?
 * ========================================
 *
 * Wenn diese Variable gesetzt ist, zeigt opinion_standalone.php
 * immer die eigenständige Ansicht mit eingebettetem CSS – auch wenn der
 * Nutzer eingeloggt ist. Ohne diesen Flag würde das Skript beim
 * eingeloggten Nutzer die vollständige Sitzungsverwaltungs-Oberfläche
 * (tab_opinion.php) einblenden, die SSO-geschützte CSS-Dateien lädt.
 */

// Flag setzen: Standalone-CSS verwenden, kein Sitzungsverwaltungs-Header
$OPINION_PUBLIC_MODE = true;

// ANPASSEN: Öffentliche URL dieser Datei (wird für Weitergabe-Links in E-Mails und Umfragen verwendet)
// Muss auf die URL dieser Datei zeigen, die auch ohne SSO erreichbar ist.
$OPINION_PUBLIC_URL = 'https://ihre-domain.de/pfad-zum-oeffentlichen-bereich/opinion_standalone.php';

// ANPASSEN: Absoluter Pfad zur echten opinion_standalone.php
// (im SSO-geschützten Verzeichnis)
require_once '/srv/www/vhosts/aktive/htdocs/vorstand/Sitzungsverwaltung/opinion_standalone.php';
