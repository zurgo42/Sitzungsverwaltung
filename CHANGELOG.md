# Changelog - Sitzungsverwaltung

Alle wichtigen Änderungen am Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [Unreleased]

### Added (Neu)

#### Externe Teilnehmer für Meinungsbilder (2025-12-23)
- **Externe Teilnehmer ohne Login**: Meinungsbilder können nun mit externen Personen geteilt werden
- Neue Tabelle `svexternal_participants` für Gast-Teilnehmer
- Token-basierter Zugriff via URL (z.B. `opinion_standalone.php?token=abc123`)
- Frontend: "🔗 Link für Externe" Button zum Generieren von Zugangslinks
- Backend-API: `api/external_participant_create.php` und `api/external_participant_revoke.php`
- Admin-Ansicht für Token-Verwaltung
- Dokumentation: `EXTERNE_TEILNEHMER_README.md`

#### Externe Links für Dokumente (2025-12-23)
- **Dokumentenverwaltung**: Dokumente können nun als externe Links statt Uploads verwaltet werden
- Neue Spalte `external_url` in `svdocuments` Tabelle
- Radio-Button-Auswahl im Upload-/Edit-Formular: "📁 Datei hochladen" oder "🔗 Externer Link"
- Vermeidet doppelte Datenhaltung bei Cloud-Dokumenten (SharePoint, Google Drive, etc.)
- Anzeige unterscheidet zwischen lokalem Download und externem Link
- Dokumentation: `DOCUMENTS_README.md` (aktualisiert)

#### Meeting-Duplikation (2025-12-23)
- **"📋 Duplizieren"** Button für regelmäßige Sitzungen
- Kopiert alle Einstellungen, Teilnehmer, Sichtbarkeit
- Datum wird automatisch +7 Tage gesetzt
- Nur für Sitzungs-Ersteller und Admins verfügbar

#### Weitere Features
- **Video-Link-Feld** in Sitzungsverwaltung vergrößert (min-width: 400px)
- **KurzURL** für Dokumente (optional, erzeugt zusätzlichen "🔗 Kurzlink" Button)

### Changed (Geändert)

#### Dokumentenverwaltung UI-Refactoring (2025-12-23)
- Dokumentenverwaltung aus Admin-Bereich in eigenen **"📁 Dokumente"** Tab verschoben
- Button "➕ Dokument hinzufügen" unter der Liste statt oben
- Nur für User mit `is_admin=1` Flag sichtbar (nicht mehr rollenbasiert)
- Bearbeiten- und Löschen-Buttons direkt bei jedem Dokument
  - ✏️ Bearbeiten (Orange)
  - 🗑️ Löschen (Rot mit Bestätigung)

#### Access-Level vereinfacht (2025-12-23)
Dokumenten-Zugriffslevel auf 3 Kategorien reduziert:
- **0**: Alle Mitglieder
- **15**: Führungsrollen (Vorstand, GF, Assistenz, Führungsteam)
- **18**: Vorstand + GF + Assistenz

Alte feingranulare Levels (Projektleitung, Ressortleitung) entfernt.

#### Production Reset Tool verbessert (2025-12-23)
- Vereinfacht: Statt Passwort-Eingabe nur noch "RESET" als Bestätigung
- 2-Stufen-Prozess: RESET-Wort → Zwei Checkboxen
- Keine Session-basierten Authentifizierungs-Probleme mehr

### Fixed (Behoben)

#### SSO-Integration nach DB-Reset (2025-12-23)
- **Critical Fix**: Adapter-Auswahl nach Datenbank-Reset korrigiert
- Problem: Nach Reset wurde falsche Datenquelle (svmembers statt berechtigte) verwendet
- Lösung: `config_adapter.php` prüft nun `REQUIRE_LOGIN` **vor** Session-Check
- Alle direkten SQL-Zugriffe auf `svmembers` durch Adapter-Calls ersetzt:
  - `functions.php`: `get_visible_meetings()`, `can_user_access_meeting()`
  - `functions_collab_text.php`: `hasCollabTextAccess()`
  - `module_notifications.php`: `render_user_notifications()`
  - `process_mail_queue.php`: Admin-Check

#### Weitere Bugfixes
- Meeting-Anzeige nach Erstellung fehlte (Adapter-Problem)
- SQL Parameter Count Mismatch beim Meeting duplizieren
- Spaltennamen in svdocuments korrigiert (filename statt file_name)

### Security (Sicherheit)
- Token-basierter Zugriff für externe Teilnehmer (kein Login erforderlich)
- Token-Widerruf-Mechanismus für Admins
- Externe Teilnehmer haben nur Zugriff auf zugewiesene Meinungsbilder

---

## Upgrade-Hinweise

### Von vorheriger Version

**Datenbank-Migration erforderlich:**

1. **Externe Teilnehmer**:
```sql
-- Siehe: migrations/add_external_participants.sql
CREATE TABLE svexternal_participants (...);
ALTER TABLE svopinion_responses ADD COLUMN external_participant_id INT DEFAULT NULL;
-- ...
```

2. **Externe Dokument-Links**:
```sql
-- Siehe: migrations/add_external_url_to_documents.sql
ALTER TABLE svdocuments ADD COLUMN external_url VARCHAR(1000) DEFAULT NULL;
ALTER TABLE svdocuments MODIFY COLUMN filepath VARCHAR(500) NULL;
-- ...
```

**Manuelle Anpassungen:**

- `config_adapter.php`: Falls individuell angepasst, siehe Commit für `!REQUIRE_LOGIN` Check
- Dokumenten-Zugriffslevel ggf. von alten Werten (12, 19) auf neue migrieren (0, 15, 18)

---

## Dokumentation

- **Neue Dokumentationen**:
  - `EXTERNE_TEILNEHMER_README.md` - Externes Meinungsbild-System
  - `CHANGELOG.md` - Diese Datei

- **Aktualisierte Dokumentationen**:
  - `DOCUMENTS_README.md` - Externe Links Feature
  - `README.md` - Allgemeine Projektbeschreibung

---

## Technische Details

### Datenbankschema-Änderungen

**Neue Tabellen**:
- `svexternal_participants` - Externe Teilnehmer für Meinungsbilder

**Neue Spalten**:
- `svdocuments.external_url` (VARCHAR 1000, NULL)
- `svopinion_responses.external_participant_id` (INT, NULL)

**Modified Constraints**:
- `svdocuments.filepath` - Jetzt NULL erlaubt (bei externen Links)
- `svdocuments.filename` - Jetzt NULL erlaubt (bei externen Links)
- `svdocuments.filesize` - Jetzt NULL erlaubt (bei externen Links)

### API-Endpunkte (neu)

- `POST api/external_participant_create.php` - Token für externen Zugang erstellen
- `POST api/external_participant_revoke.php` - Token widerrufen

### Standalone-Tools (erweitert)

- `opinion_standalone.php?token=...` - Token-basierter Zugriff für Externe
- `tools/production_reset.php` - Vereinfachter Reset-Prozess

---

## Mitwirkende

Session: claude/fix-sso-integration-01NbwbYdHVMH7hEM5HwQmFji
Datum: 2025-12-23
