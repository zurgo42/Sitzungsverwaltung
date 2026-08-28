# Changelog - Sitzungsverwaltung

Alle wichtigen Änderungen am Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [Unreleased]

### Added (Neu)

#### Aktions-Protokollierung / Audit-Log (2026-08-13)

Vollständige Aktions-Protokollierung aller user-verursachten Datenbankänderungen.

- **Neue Datei `protokoll_helper.php`** mit zwei Funktionen:
  - `get_protokoll_user($current_user)` → `[$mnr, $kurz]` – ermittelt Mitgliedsnummer und Kurzname des eingeloggten Users (SSO-Session oder DB)
  - `protokoll($pdo, $mnr, $kurz, $was, $string, $filter=3)` – schreibt einen Eintrag in die Tabelle `protokoll`; `$filter`-Wert steuert Deduplizierung (0=tägl., 1=tägl., 2=stündl., 3=immer)
- **Neue Tabelle `protokoll`** in `init-db.php` (Schema: `MNr`, `KurzN`, `zeit`, `was`, `string` – kompatibel mit Altformat)
- **Monatsarchivierung** in `pseudo_cron.php`: Beim ersten Seitenaufruf eines neuen Monats wird `protokoll` → `YYYYMMprotokoll` kopiert und anschließend truncated; svconfig-Schlüssel `protokoll_last_archive` speichert den zuletzt archivierten Monat
- **~55 `protokoll()`-Aufrufe** in 8 Dateien instrumentiert:
  - `antrag_neu.php`: Antrag-Neu
  - `antrag_bearbeiten.php`: Antrag-Speichern, Antrag-Finalisieren, Antrag-Verwerfen, WZV-beantragt, WZV-Zustimmung
  - `abstimmungen.php`: Votum-Speichern, Votum-Bemerkungen, Abstimmung-Hinweis, Antrag-Zurueckziehen, Antrag-Kopie-Neu
  - `process_meetings.php`: Sitzung-Erstellen, -Bearbeiten, -Loeschen, -Starten, -Duplizieren
  - `process_agenda.php`: ~38 Labels (TOP-Neu, TOP-Bearbeiten, TOP-Loeschen, TOP-Verschieben, TOP-Wiedervorlage, Kommentar-*, Protokoll-*, Sitzung-*, Abstimmung-*, Stimme, TODO-*, Anwesenheit u. a.)
  - `process_todos.php`: TODO-Erstellen, TODO-Status, TODO-Zurueckziehen
  - `process_protocol.php`: Protokoll-Speichern

**Neue Dokumentation:** `docs/audit-logging.md`

### Changed (Geändert)

#### Monatssumme-Limit konfigurierbar (2026-08-13)

- `berechneMonatssumme()` in `antrag_bearbeiten.php` erhält `$v_limit` jetzt aus der svconfig-Tabelle statt als hartkodierte Konstante
- Konfigurationsschlüssel: `bart_V_betrag_limit` (Typ: number, Gruppe: antragstypen, Standardwert: 500)

#### Textformatierung in Antragsansicht (2026-08-13)

- **Neue Hilfsfunktion `format_antrag_text()`** in `includes/voting_helper.php`: normiert Zeilenenden, wandelt Doppelleerzeilen in `<p>`-Tags und Einzelzeilenumbrüche in `<br>`-Tags um
- **`render_hinweis_text()` überarbeitet**: unterstützt gemischtes DB-Format (altes `<br>`-Trenner und neues `\n---\n`-Trenner), strippt führende `<br>`, erkennt beide Datumsformate
- **CSS**: `white-space: pre-wrap` aus `.acc-content` entfernt (erzeugte doppelte Zeilenumbrüche)
- Betroffen: `antrag_ansehen.php`, `includes/voting_helper.php`

#### SSO-Fixes für opinion_standalone.php (2026-08-13)

- SSO-Auto-Login nach Session-Start eingebaut (lädt `config_adapter.php`, ruft `get_sso_membership_number()`)
- Standalone-URL jetzt über svconfig konfigurierbar: Schlüssel `opinion_standalone_url`

#### Konfigurierbare externe URLs für Standalone-Seiten (2026-08-13)

- **Neue svconfig-Schlüssel** `opinion_standalone_url` und `terminplanung_standalone_url` für konfigurierbare öffentliche URLs der Standalone-Seiten
- `generate_external_access_link()` in `external_participants_functions.php` prüft zuerst die svconfig-Einträge, fällt auf `BASE_URL` zurück
- Admin-UI (`tab_admin_init.php`): korrekter Placeholder-Text für URL-Felder
- Initialisierung in `init-db.php` und `tab_admin_init.php`

#### Upload-Verzeichnis für Antragsunterlagen (2026-08-13)

- Upload-Verzeichnis in `antrag_bearbeiten.php` und `process_agenda.php` von `uploads/antraege/` auf `Scans/` geändert
- DB-Format der Dateipfade (`Scans/filename`) kompatibel mit Altprogramm-Dateien

#### SSO Session-Timeout UX-Verbesserungen (2026-08-13)

- `index.php`: Fehlermeldung bei abgelaufenem SSO-Token jetzt "Anmeldung abgelaufen" statt "Bitte Zugriff verifizieren"
- VTool-Button erhält `target="_top"` für korrekte Navigation in iframe-Umgebungen
- `login.php`: Leitet im SSO-Modus automatisch zu `index.php` weiter (mit SSO-Auto-Login-Versuch)

#### external_participant_register.php im SSO-Modus (2026-08-13)

- Mitgliedsnummer-Sektion wird im SSO-Modus ausgeblendet (kein separates Login-Formular im SSO-Kontext sinnvoll)

---

### Changed (Geändert)

#### Migration zu reiner Adapter-Architektur (2026-05-27)
- **VIEW-System vollständig entfernt** - Keine "magischen" VIEWs mehr
- **Alle Member-Zugriffe über Adapter-Funktionen** - Expliziter, nachvollziehbarer Code
- **7 verbleibende SQL-JOINs gefixed** - Konvertiert zu Adapter-Calls
- **Gründe:** Bessere Wartbarkeit, Klarheit für künftige Admins, kein verstecktes VIEW-System

**Betroffene Dateien:**
- process_meetings.php - Name-Lookups über `get_member_by_id()`
- tab_agenda_display_protocol_ready.php - Kommentare ohne JOINs
- tab_agenda_display_ended.php - Kommentare ohne JOINs  
- functions_collab_text.php - Leadership-Members über `get_all_members()`
- tab_admin.php - Poll-Creator über Adapter
- external_participant_register.php - Vereinfachte Membership-Check
- debug_post_comments.php - Debug-Queries über Adapter
- member_functions.php - `ensure_svmembers_view()` entfernt
- functions.php - VIEW-Call entfernt
- sql/create_members_view.sql - Als DEPRECATED markiert

**Vorteile:**
- ✅ Kein verstecktes VIEW-System mehr
- ✅ Explizite Adapter-Calls statt "Magie"
- ✅ Einfacher zu debuggen für künftige Admins
- ✅ Konsistent: Überall gleiche Adapter-Funktionen

### Added (Neu)

#### 📋 Vollständiges Antrags- und Beschluss-System (2026-05-20 bis 2026-05-22)
**Ein komplettes Workflow-System für Anträge und Vorstandsbeschlüsse mit VTool-Integration**

##### Kernfunktionen:
- **Automatische Antragsnummern-Vergabe**: 
  - Format: `A + YYMMDD + lfd. Nr.` (z.B. A26051401)
  - Bis zu 99 Anträge pro Tag
  - Verschiedene Präfixe: A (Antrag), B (Vorstandsbeschluss), VS (Beschlossen)
  
- **Antragsverwaltung** (`tab_proposals.php`):
  - Übersicht aller offenen Anträge
  - Filter nach Status, Präfix, Antragstyp
  - Volltextsuche (Nummer, Titel, Beschluss)
  - Mobile-responsive Design
  
- **Beschlussbuch** (`beschlussbuch.php`):
  - Alle beschlossenen Anträge (VS-Präfix)
  - Suchfunktion mit Highlighting
  - URL-Erkennung in Texten
  - Dark Mode Support
  
- **Vollständiges Antragsformular**:
  - **Stammdaten**: Ressort, Verantwortlicher, Verein/Stiftung, Sichtbarkeit
  - **Antrag**: Titel, Beschlusstext, Begründung, Betrag
  - **Auswirkungen**: Finanziell, Personell, Sachlich
  - **Angebote/Unterlagen**: Bis zu 4 Datei-Uploads mit Beschreibung
  - **Vereinfachte Freigabe**: Sofort-Überweisung, Vorprüfung
  - **Bemerkungen**: Hinweise mit Zeitstempel
  
- **Konfigurierbare Antragstypen**:
  - V (Vorlage), R (Ressort), B (Vorstandsbeschluss)
  - Individuelle Voting-Regeln pro Typ
  - Betragsgrenzwerte konfigurierbar
  - Verwaltung über Admin-Bereich
  
- **Berechtigungssystem**:
  - Basierend auf `aktiv`-Level aus VTool berechtigte-Tabelle
  - Level 18+: Interne Anträge sichtbar
  - Level 19+: Admin-Funktionen (permanentes Löschen)
  - Funktionsbasiert: VA (Vorstandsassistenz) hat erweiterte Rechte
  
- **Integration mit Meetings**:
  - Anträge direkt aus TOPs erstellen
  - Kategorie "Antrag/Beschluss" beim TOP-Anlegen
  - Vollständiges Formular in der Sitzungsvorbereitung
  - Automatische Verknüpfung mit Tagesordnung
  - Meeting-ID wird im Antrag gespeichert
  
- **Voting/Abstimmungs-Integration**:
  - Direkte Abstimmung über Anträge in Sitzungen
  - Integration mit `beschluesse`-Tabelle
  - ANGENOMMEN/ABGELEHNT-Status
  - Automatische Überführung in Beschlussbuch bei Annahme
  - Protokoll-Formatierung für Abstimmungsergebnisse
  
- **Workflow-Features**:
  - Antrag duplizieren (mit allen Daten)
  - Wiedervorlage zu anderen Sitzungen
  - Wartezeit-Anzeige (Expedite-Verfahren)
  - Originalantrag-Referenz bei Duplikaten
  - Permanentes Löschen nur für X/Z-Präfixe

##### Technische Details:
- **Neue Tabellen**: `svantraege`, `svbeschluesse` (optional), `svressorts`, `svconfig`, `antragstypen_config`, `aktiv_level_config`
- **VTool-Integration**: Nutzt optional existierende VTool-Tabellen (`antraege`, `beschluesse`)
- **Konstanten**: `TABLE_ANTRAEGE`, `TABLE_BESCHLUESSE` für flexible Tabellenzuordnung
- **Helper**: `antragstypen_helper.php` für Konfigurationsverwaltung

##### Neue Dateien:
- `antrag_neu.php` - Neuen Antrag erstellen
- `antrag_bearbeiten.php` - Antrag bearbeiten (63KB)
- `antrag_ansehen.php` - Antrag ansehen
- `tab_proposals.php` - Antragsverwaltung-Tab
- `beschlussbuch.php` - Beschlussbuch (VS-Anträge)
- `includes/antragstypen_helper.php` - Konfigurationsfunktionen
- `apply_meeting_decisions_migration.php` - Migrations-Tool

##### UI-Features:
- **Dark Mode**: Vollständige Unterstützung auf allen Antragsseiten
- **Mobile-optimiert**: Responsive Design für Smartphones/Tablets
- **URL-Erkennung**: Automatisch klickbare Links in Texten
- **Syntax-Highlighting**: Suchbegriffe werden hervorgehoben
- **Accordion-Views**: Platzsparende Darstellung langer Inhalte

#### Wiedervorlage-System für TOPs (2026-05-26)
- **TOP-Verschiebung mit Antragsverfolgung**: TOPs können zu künftigen Sitzungen verschoben werden
- Automatische Übertragung verknüpfter Anträge (antrnr) beim Verschieben
- Meeting-Zuweisung wird in der Antrags-Datenbank aktualisiert
- Dokumentation in beiden Sitzungen (Quell- und Ziel-Meeting)
- Nur für Einladende, Protokollant und Sitzungsleiter verfügbar
- Nicht verfügbar für System-TOPs (0, 99)

#### Optimierte Abwesenheiten-Anzeige (2026-05-26)
- **Intelligente Limitierung**: Zeigt die nächsten 4 Abwesenheiten statt alle
- **Kollapsible "weitere..." Sektion**: Bei mehr als 4 Einträgen wird Rest ausklappbar angezeigt
- **Dynamischer Link-Text**: 
  - "Details" bei 4 oder weniger Einträgen
  - "weitere..." bei mehr als 4 Einträgen
- **Responsive Layout**: Optimierte Darstellung auf mobilen Geräten
- **Farbkodierung**: Aktuelle Abwesenheiten in Rot hervorgehoben
- Anwendung in:
  - Benachrichtigungsbox (gelbe Infobox)
  - Sitzungsvorbereitung
  - Teilnehmerverwaltung

#### Verbessertes Voting-System (2026-05-25/26)
- **Frage-Feld für Abstimmungen**: Neue Spalte `voting_question` für Abstimmungen ohne Antrags-Bezug
- **Meinungsbild vs. Beschlussabstimmung**: 
  - ANGENOMMEN/ABGELEHNT nur bei Antrags-Abstimmungen
  - Meinungsbilder zeigen nur Voting-Frage und Ergebnisse
- **Kompaktere Darstellung**: 
  - Geschlossene Abstimmungen nehmen weniger Platz ein
  - Optimierte Protokoll-Formatierung
- **Checkbox-Integration**: "Als Antrag/Beschluss anlegen" direkt im Voting-Dialog
- **Protokoll-Verbesserungen**: Strukturierte Darstellung in Meeting-Protokollen

#### VTool-Integration (2026-05-25)
- **Vollständige Tabellennamen-Abstraktion**: 
  - Konstanten `TABLE_ANTRAEGE` und `TABLE_BESCHLUESSE`
  - Flexibel zwischen VTool- und eigenständigen Tabellen wechselbar
- **Nutzung existierender VTool-Tabellen**:
  - `beschluesse` statt `svbeschluesse`
  - `antraege` bleibt unverändert (bereits VTool-kompatibel)
- Keine Daten-Duplikation mehr zwischen Systemen
- Nahtlose Integration in bestehende VTool-Infrastruktur

### Changed (Geändert)

#### Benachrichtigungs-Optimierungen (2026-05-26)
- **Termine limitiert**: Maximal 3 kommende Termine in gelber Infobox
- **Fokus auf Relevantes**: Wichtigste/nächste Termine werden priorisiert
- Volle Termin-Liste weiterhin über Tab-Navigation erreichbar

#### UI-Verbesserungen (2026-05-26)
- **Pflichtfeld-Hinweise**: Konsistente Markierung mit rotem Stern (*)
- **Antragsteller-Anzeige**: Nur Vor- und Nachname in Edit-Formularen (kompakter)
- **Voting-Navigation**: Verbesserte Benutzerführung bei Abstimmungen
- **Responsive Optimierungen**: Bessere Darstellung auf Tablets und Smartphones

### Fixed (Behoben)

#### Voting-System Bugfixes (2026-05-25/26)
- HTML-Struktur-Fehler in geschlossenen Abstimmungen behoben
- Parse-Error in `tab_agenda_display_preparation.php` korrigiert (fehlender PHP-Schlusstag)
- Voting-Ergebnis-Anzeige bei Meinungsbildern ohne Antrag korrigiert

---

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

0. **Aktions-Protokollierung** (2026-08-13):
```sql
-- Wird automatisch durch init-db.php ausgeführt
CREATE TABLE IF NOT EXISTS protokoll (
    MNr    VARCHAR(12) DEFAULT NULL,
    KurzN  VARCHAR(12) DEFAULT NULL,
    zeit   VARCHAR(20) DEFAULT NULL,
    was    TEXT        DEFAULT NULL,
    string TEXT        DEFAULT NULL,
    INDEX idx_mnr  (MNr),
    INDEX idx_zeit (zeit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Neue svconfig-Schlüssel (ebenfalls automatisch via init-db.php):
INSERT IGNORE INTO svconfig (config_key, config_value, config_type, description, config_group)
VALUES
  ('opinion_standalone_url',       '', 'text', '...', 'notifications'),
  ('terminplanung_standalone_url', '', 'text', '...', 'notifications'),
  ('protokoll_last_archive',       '', 'text', '...', 'system');
```

1. **Antrags- und Beschluss-System** (2026-05-20 bis 2026-05-22):
```sql
-- Wird automatisch durch init-db.php ausgeführt
-- Neue Tabellen:
CREATE TABLE svantraege (...);  -- Optional (nutzt ggf. VTool antraege)
CREATE TABLE svbeschluesse (...);  -- Optional (nutzt ggf. VTool beschluesse)
CREATE TABLE svressorts (...);
CREATE TABLE svconfig (...);
CREATE TABLE antragstypen_config (...);
CREATE TABLE aktiv_level_config (...);

-- Neue Spalten in svmeetings:
ALTER TABLE svmeetings ADD COLUMN allow_decisions TINYINT(1) DEFAULT 1;

-- Siehe auch: apply_meeting_decisions_migration.php
```

2. **Voting-Question Spalte** (2026-05-25):
```sql
-- Wird automatisch durch init-db.php ausgeführt
ALTER TABLE svvotings 
ADD COLUMN voting_question VARCHAR(500) DEFAULT NULL 
COMMENT 'Frage bei Stimmungsbild (wenn kein Antrag)' 
AFTER initiated_by_member_id;
```

3. **Externe Teilnehmer** (2025-12-23):
```sql
-- Siehe: migrations/add_external_participants.sql
CREATE TABLE svexternal_participants (...);
ALTER TABLE svopinion_responses ADD COLUMN external_participant_id INT DEFAULT NULL;
-- ...
```

3. **Externe Dokument-Links** (2025-12-23):
```sql
-- Siehe: migrations/add_external_url_to_documents.sql
ALTER TABLE svdocuments ADD COLUMN external_url VARCHAR(1000) DEFAULT NULL;
ALTER TABLE svdocuments MODIFY COLUMN filepath VARCHAR(500) NULL;
-- ...
```

**Manuelle Anpassungen:**

- **VTool-Integration**: Prüfen Sie `config.php` - Konstanten `TABLE_ANTRAEGE` und `TABLE_BESCHLUESSE` 
  - Standard: `antraege` und `beschluesse` (VTool-Tabellen)
  - Eigenständig: `antraege` und `svbeschluesse`
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

**Neue Tabellen (Antrags-System)** (2026-05-20 bis 2026-05-22):
- `svantraege` - Antragsverwaltung (optional, nutzt ggf. VTool `antraege`)
- `svbeschluesse` - Beschlussverwaltung (optional, nutzt ggf. VTool `beschluesse`)
- `svressorts` - Ressort-/Abteilungsverwaltung
- `svconfig` - Systemkonfiguration (Betragsgrenzwerte, etc.)
- `antragstypen_config` - Konfiguration der Antragstypen (V, R, B)
- `aktiv_level_config` - Konfiguration der Berechtigungslevel

**Neue Tabellen (Sonstige)**:
- `svexternal_participants` - Externe Teilnehmer für Meinungsbilder (2025-12-23)
- `protokoll` - Aktions-Protokoll aller User-Datenbankänderungen (2026-08-13)
- `YYYYMMprotokoll` (dynamisch) - Monatliche Archivtabellen, automatisch angelegt (2026-08-13)

**Neue Spalten**:
- `svmeetings.allow_decisions` (TINYINT 1, DEFAULT 1) - Beschlüsse in Meeting erlauben (2026-05-20)
- `svagenda_items.antrnr` (VARCHAR 20, NULL) - Verknüpfung mit Antrag (2026-05-20)
- `svvotings.voting_question` (VARCHAR 500, NULL) - Frage bei Stimmungsbild ohne Antrag (2026-05-25)
- `svdocuments.external_url` (VARCHAR 1000, NULL) - Externe Dokument-Links (2025-12-23)
- `svopinion_responses.external_participant_id` (INT, NULL) - Zuordnung zu externen Teilnehmern (2025-12-23)

**Modified Constraints**:
- `svdocuments.filepath` - Jetzt NULL erlaubt (bei externen Links)
- `svdocuments.filename` - Jetzt NULL erlaubt (bei externen Links)
- `svdocuments.filesize` - Jetzt NULL erlaubt (bei externen Links)

**Konstanten für Tabellennamen** (2026-05-25):
- `TABLE_ANTRAEGE` - Konfigurierbar: `antraege` (Standard/VTool) oder `antraege` (eigenständig)
- `TABLE_BESCHLUESSE` - Konfigurierbar: `beschluesse` (VTool) oder `svbeschluesse` (eigenständig)

### Neue Dateien (Antrags-System) (2026-05-20 bis 2026-05-22)

**Hauptdateien**:
- `antrag_neu.php` - Neuen Antrag erstellen (automatische Nummernvergabe)
- `antrag_bearbeiten.php` - Antrag bearbeiten (vollständiges Formular, 63KB)
- `antrag_ansehen.php` - Antrag ansehen (Detailansicht)
- `tab_proposals.php` - Antragsverwaltung-Tab (Filter, Suche, Verwaltung)
- `beschlussbuch.php` - Beschlussbuch (alle VS-Beschlüsse)

**Helper & Tools**:
- `includes/antragstypen_helper.php` - Konfigurationsfunktionen für Antragstypen
- `apply_meeting_decisions_migration.php` - Migrations-Tool für Datenbankschema
- `protokoll_helper.php` - Aktions-Protokollierung: `get_protokoll_user()`, `protokoll()` (2026-08-13)

**Styles**:
- `css/antrag-styles.css` - Styling für Antragsseiten (inkl. Dark Mode)

### API-Endpunkte (neu)

- `POST api/external_participant_create.php` - Token für externen Zugang erstellen
- `POST api/external_participant_revoke.php` - Token widerrufen

### Standalone-Tools (erweitert)

- `opinion_standalone.php?token=...` - Token-basierter Zugriff für Externe
- `tools/production_reset.php` - Vereinfachter Reset-Prozess

---

## Mitwirkende

**Aktuelle Session:**
- Session: claude/continue-session-management-01NbwbYdHVMH7hEM5HwQmFji
- Datum: 2026-08-13
- Features: Aktions-Protokollierung, Konfigurierbare URLs, Monatssumme-Limit, Textformatierung, SSO-Fixes, Upload-Verzeichnis

**Vorherige Sessions:**
- Session: claude/continue-session-management-01NbwbYdHVMH7hEM5HwQmFji
- Datum: 2026-05-25 bis 2026-05-26
- Features: Wiedervorlage, Voting-System, Abwesenheiten-Optimierung, VTool-Integration

- Session: claude/fix-sso-integration-01NbwbYdHVMH7hEM5HwQmFji
- Datum: 2025-12-23
- Features: Externe Teilnehmer, Externe Links, Meeting-Duplikation
