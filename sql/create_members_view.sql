-- ============================================
-- SQL-Skript: View für berechtigte-Tabelle
-- ============================================
--
-- ⚠️ HINWEIS: Diese View ist OPTIONAL und NICHT mehr empfohlen!
--
-- Die Sitzungsverwaltung enthält bereits ein Adapter-System
-- (member_functions.php + BerechtigteAdapter), das viel flexibler ist.
--
-- EMPFOHLEN: Nutzen Sie config_adapter.php statt dieser View!
-- Siehe INTEGRATION.md für Details.
--
-- Diese View ist nur noch nützlich, wenn Sie:
-- - Die Adapter-Architektur nicht nutzen wollen
-- - Direkten Datenbankzugriff auf svmembers benötigen
-- - Legacy-Code haben, der svmembers hart codiert
--
-- ============================================

-- View löschen falls vorhanden
DROP VIEW IF EXISTS svmembers;

-- View erstellen
-- ANPASSEN: Spaltennamen entsprechend Ihrer Tabellenstruktur
CREATE OR REPLACE VIEW svmembers AS
SELECT
    member_id,              -- ANPASSEN: z.B. MNr AS member_id
    first_name,             -- ANPASSEN: z.B. vorname AS first_name
    last_name,              -- ANPASSEN: z.B. nachname AS last_name
    email,
    role,                   -- ANPASSEN: z.B. rolle AS role
    phone,                  -- ANPASSEN: z.B. telefon AS phone
    is_active,              -- ANPASSEN: z.B. 1 AS is_active (falls Spalte nicht existiert)
    NOW() AS created_at,    -- ANPASSEN: z.B. erstelldatum AS created_at
    NOW() AS updated_at     -- ANPASSEN: z.B. aenderungsdatum AS updated_at
FROM berechtigte
WHERE is_active = 1;        -- ANPASSEN: Ihre Aktivitätsbedingung

-- ============================================
-- BEISPIEL: Falls Ihre Spaltennamen anders sind
-- ============================================

/*
CREATE OR REPLACE VIEW svmembers AS
SELECT
    MNr AS member_id,
    vorname AS first_name,
    nachname AS last_name,
    email,
    CASE
        WHEN rolle = 'admin' THEN 'vorstand'
        WHEN rolle = 'manager' THEN 'gf'
        WHEN rolle = 'assistent' THEN 'assistenz'
        WHEN rolle = 'leitung' THEN 'führungsteam'
        ELSE 'mitglied'
    END AS role,
    telefon AS phone,
    aktiv AS is_active,
    erstelldatum AS created_at,
    aenderungsdatum AS updated_at
FROM berechtigte
WHERE aktiv = 1;
*/

-- ============================================
-- TESTEN
-- ============================================

-- Alle Mitglieder anzeigen
SELECT * FROM svmembers LIMIT 5;

-- Anzahl Mitglieder pro Rolle
SELECT role, COUNT(*) as anzahl
FROM svmembers
GROUP BY role;

-- ============================================
-- HINWEIS
-- ============================================
--
-- Nach Erstellung der View können Sie in config_adapter.php
-- die Funktionen get_all_members() und get_member_by_id()
-- direkt auf 'svmembers' statt 'berechtigte' zugreifen lassen.
--
-- Beispiel:
--
-- function get_all_members($pdo) {
--     $stmt = $pdo->query("SELECT * FROM svmembers ORDER BY last_name");
--     return $stmt->fetchAll(PDO::FETCH_ASSOC);
-- }
--
-- ============================================
