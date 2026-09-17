-- =========================================================
-- Migration: Umbenennung Referenten-Modul Tabellen mit "sv"-Präfix
-- Erstellt: 27.11.2025
-- Zweck: Bessere Identifizierbarkeit und Portabilität
-- =========================================================

-- WICHTIG: Dieses Skript ist optional und sollte nur ausgeführt werden,
-- wenn das Referenten-Modul installiert ist.

-- =========================================================
-- REFERENTEN-MODUL TABELLEN (3 Tabellen)
-- =========================================================

RENAME TABLE Refname TO svRefname;
RENAME TABLE Refpool TO svRefpool;
RENAME TABLE PLZ TO svPLZ;

-- =========================================================
-- ENDE DER MIGRATION
-- =========================================================
