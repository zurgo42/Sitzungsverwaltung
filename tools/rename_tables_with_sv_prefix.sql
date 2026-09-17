-- =========================================================
-- Migration: Umbenennung aller Tabellen mit "sv"-Präfix
-- Erstellt: 27.11.2025
-- Zweck: Bessere Identifizierbarkeit und Portabilität
-- =========================================================

-- WICHTIG: Dieses Skript benennt alle Tabellen um.
-- Es werden KEINE Daten gelöscht, nur die Tabellennamen geändert.
-- Foreign Keys werden automatisch mit umbenannt.

-- =========================================================
-- HAUPT-SITZUNGSVERWALTUNG TABELLEN (23 Tabellen)
-- =========================================================

-- 1. Core-Tabellen
RENAME TABLE members TO svmembers;

-- 2. Meeting-Tabellen
RENAME TABLE meetings TO svmeetings;
RENAME TABLE meeting_participants TO svmeeting_participants;
RENAME TABLE agenda_items TO svagenda_items;
RENAME TABLE agenda_comments TO svagenda_comments;

-- 3. Protokoll-Tabellen
RENAME TABLE protocols TO svprotocols;
RENAME TABLE protocol_change_requests TO svprotocol_change_requests;

-- 4. TODO-Tabellen
RENAME TABLE todos TO svtodos;
RENAME TABLE todo_log TO svtodo_log;

-- 5. Admin-Log
RENAME TABLE admin_log TO svadmin_log;

-- 6. Terminplanung-Tabellen
RENAME TABLE polls TO svpolls;
RENAME TABLE poll_dates TO svpoll_dates;
RENAME TABLE poll_participants TO svpoll_participants;
RENAME TABLE poll_responses TO svpoll_responses;

-- 7. Meinungsbild-Tool-Tabellen
RENAME TABLE opinion_answer_templates TO svopinion_answer_templates;
RENAME TABLE opinion_polls TO svopinion_polls;
RENAME TABLE opinion_poll_options TO svopinion_poll_options;
RENAME TABLE opinion_poll_participants TO svopinion_poll_participants;
RENAME TABLE opinion_responses TO svopinion_responses;
RENAME TABLE opinion_response_options TO svopinion_response_options;

-- 8. E-Mail-Warteschlange
RENAME TABLE mail_queue TO svmail_queue;

-- 9. Dokumentenverwaltung
RENAME TABLE documents TO svdocuments;
RENAME TABLE document_downloads TO svdocument_downloads;

-- =========================================================
-- REFERENTEN-MODUL TABELLEN (3 Tabellen)
-- =========================================================
-- Nur umbenennen wenn vorhanden

-- Diese werden separat behandelt, da sie optional sind
-- RENAME TABLE Refname TO svRefname;
-- RENAME TABLE Refpool TO svRefpool;
-- RENAME TABLE PLZ TO svPLZ;

-- =========================================================
-- ENDE DER MIGRATION
-- =========================================================

-- Notiz: Nach Ausführung dieses Skripts müssen alle PHP-Dateien
-- aktualisiert werden, um die neuen Tabellennamen zu verwenden.
