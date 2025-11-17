-- ============================================
-- DATENBANK-UPDATE FÜR SITZUNGSVERWALTUNG
-- Version 3.0 - 17.11.2025
-- Feature: Sichtbarkeits-Typen für Sitzungen
-- ============================================
--
-- Dieses Script fügt die neuen Felder für:
-- - Sichtbarkeits-Typen (öffentlich, angemeldet, eingeladene)
-- - Spezial-User "Mitglied alle" für öffentliche Sitzungen
-- hinzu
--
-- WICHTIG: Führen Sie dieses Script aus, BEVOR Sie die neuen PHP-Dateien hochladen!
-- ============================================

-- 1. Sichtbarkeits-Feld für Meetings hinzufügen
-- --------------------------------------------

ALTER TABLE meetings
ADD COLUMN IF NOT EXISTS visibility_type ENUM('public', 'authenticated', 'invited_only')
DEFAULT 'invited_only'
AFTER status;

-- Index für schnellere Abfragen
ALTER TABLE meetings
ADD INDEX IF NOT EXISTS idx_visibility (visibility_type);

-- 2. Spezial-User "Mitglied alle" erstellen
-- --------------------------------------------

-- Prüfen ob User bereits existiert
INSERT IGNORE INTO members (
    first_name,
    last_name,
    email,
    password_hash,
    is_admin,
    is_active,
    created_at
) VALUES (
    'Mitglied',
    'alle',
    'oeffentlich@system.local',
    -- Passwort: "oeffentlich" (sollte vom Admin geändert werden!)
    '$2y$10$YourHashHere',
    0,
    1,
    NOW()
);

-- 3. Bestehende Daten aktualisieren
-- --------------------------------------------

-- Alle bestehenden Meetings behalten den Standard 'invited_only'
-- Falls gewünscht, können Sie hier manuell Meetings auf 'public' oder 'authenticated' setzen

-- Beispiel: Alle vergangenen Meetings als 'authenticated' markieren
-- UPDATE meetings
-- SET visibility_type = 'authenticated'
-- WHERE status = 'ended' AND DATE(meeting_date) < CURDATE();

-- 4. Verifizierung
-- --------------------------------------------

-- Prüfen ob das Feld vorhanden ist
SELECT
    'meetings' as table_name,
    COUNT(CASE WHEN COLUMN_NAME = 'visibility_type' THEN 1 END) as has_visibility_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'meetings';

-- Prüfen ob Spezial-User erstellt wurde
SELECT
    member_id,
    first_name,
    last_name,
    email,
    is_active
FROM members
WHERE email = 'oeffentlich@system.local';

-- 5. Statistik
-- --------------------------------------------

-- Zeige Anzahl Meetings pro Sichtbarkeits-Typ
SELECT
    visibility_type,
    COUNT(*) as count,
    status
FROM meetings
GROUP BY visibility_type, status
ORDER BY visibility_type, status;

-- ============================================
-- ERFOLGSMELDUNG
-- ============================================

SELECT 'Datenbank erfolgreich aktualisiert!' as Status,
       NOW() as Timestamp;

-- ============================================
-- HINWEISE:
-- ============================================
--
-- Nach erfolgreicher Ausführung dieses Scripts:
-- 1. WICHTIG: Setzen Sie ein sicheres Passwort für den User "Mitglied alle"!
--    UPDATE members SET password_hash = PASSWORD('IhrSicheresPasswort')
--    WHERE email = 'oeffentlich@system.local';
--
-- 2. Laden Sie die neuen PHP-Dateien hoch
-- 3. Testen Sie die Sichtbarkeits-Funktion
--
-- Sichtbarkeits-Typen:
-- - public: Nur für User "Mitglied alle", read-only Ansicht
-- - authenticated: Alle eingeloggten User sehen diese Sitzungen
-- - invited_only: Nur eingeladene Teilnehmer sehen die Sitzung (Standard)
--
-- ============================================
