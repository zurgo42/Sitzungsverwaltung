-- Fix: Setze DEFAULT-Werte für bestehende Meetings (nach Migration)
-- Datum: 2026-01-17
-- Beschreibung: Meetings die vor der Migration erstellt wurden haben NULL-Werte

-- Setze collaborative_protocol auf 0 (Standard-Modus) für alle bestehenden Meetings
UPDATE svmeetings
SET collaborative_protocol = 0
WHERE collaborative_protocol IS NULL;
