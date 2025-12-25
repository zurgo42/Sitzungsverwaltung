<?php
/**
 * Haupteinstiegspunkt - leitet auf public/ weiter
 *
 * Für die Produktion: Document Root auf /public setzen
 * oder diese Datei als Fallback nutzen
 */

// Weiterleitung auf public/index.php
header('Location: public/index.php');
exit;
