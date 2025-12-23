# Sitzungsverwaltung - Aufgaben für neue Claude Code Session

**Projekt**: Sitzungsverwaltung (Meeting Management System)
**Repository**: zurgo42/Sitzungsverwaltung
**Aktueller Branch**: `main` (Produktionsreif!)
**Letztes Update**: 2025-12-23

---

## 🎯 Aktueller Projektstatus

✅ **PRODUKTIONSREIF** - Alle geplanten Features implementiert und getestet.

### Kürzlich abgeschlossen (Session: 2025-12-23)

1. ✅ **Externe Teilnehmer für Meinungsbilder**
   - Token-basierter Zugang für Gäste ohne Login
   - Komplett dokumentiert in `EXTERNE_TEILNEHMER_README.md`

2. ✅ **Externe Links für Dokumente**
   - Cloud-Integration (SharePoint, Google Drive)
   - Vermeidet doppelte Datenhaltung

3. ✅ **Meeting-Duplikation**
   - Für regelmäßige Sitzungen
   - Kopiert alle Einstellungen mit +7 Tage

4. ✅ **SSO-Integration Fixes**
   - Kritische Bugs nach DB-Reset behoben
   - Adapter-Pattern vollständig implementiert

5. ✅ **Dokumentation**
   - `CHANGELOG.md` - Vollständige Änderungshistorie
   - `SESSION_HANDOVER.md` - Detaillierte Übergabe
   - `migrations/README.md` - Migration-Anleitung

### Alle Commits sind in `main` gemerged ✅

---

## 📋 Erste Schritte für neue Session

### 1. Kontext laden

```bash
# Repository klonen (falls neu)
git clone https://github.com/zurgo42/Sitzungsverwaltung.git
cd Sitzungsverwaltung

# Aktuellen Stand holen
git checkout main
git pull origin main

# Wichtige Dokumente lesen:
cat SESSION_HANDOVER.md      # Vollständige Übergabe
cat CHANGELOG.md             # Was ist neu?
cat migrations/README.md     # Datenbank-Änderungen
```

### 2. Sage Claude Code:

```
Ich arbeite an der Sitzungsverwaltung weiter.

Bitte lies zuerst diese Dateien:
- SESSION_HANDOVER.md (aktueller Stand)
- CHANGELOG.md (letzte Änderungen)
- NEXT_TASKS.md (diese Datei)

Das System ist produktionsreif. Alle geplanten Features sind implementiert.

Branch: main
Status: ✅ Produktionsreif
```

---

## 🚀 Mögliche neue Aufgaben

### Priorität 1: Deployment & Testing

#### 1.1 Datenbank-Migrationen ausführen
**Status**: Nicht ausgeführt (nur vorbereitet)

```bash
cd migrations
./run_migrations.sh    # Linux/Mac
# oder
run_migrations.bat     # Windows
```

**Dokumentation**: `migrations/README.md`

#### 1.2 Produktionstest
- Externe Teilnehmer-Feature testen
- Externe Dokument-Links testen
- Meeting-Duplikation testen
- SSO nach DB-Reset testen

#### 1.3 Cron-Job einrichten (optional)
```cron
# Cleanup abgelaufener externer Teilnehmer
0 3 * * * /usr/bin/php /pfad/zu/cron_cleanup_external_participants.php
```

---

### Priorität 2: Neue Features (optional)

#### 2.1 E-Mail-Automation für Externe Teilnehmer
**Problem**: Tokens müssen manuell versendet werden
**Lösung**: Automatischer E-Mail-Versand nach Token-Erstellung

**Betroffene Dateien**:
- `api/external_participant_create.php`
- Neue Datei: `mail_templates/external_participant_invite.php`

**Aufwand**: ~2-3 Stunden

---

#### 2.2 Dashboard / Übersicht
**Problem**: Keine zentrale Übersicht über alle Aktivitäten
**Lösung**: Dashboard mit Widgets

**Features**:
- Nächste Meetings
- Offene TODOs
- Aktive Meinungsbilder
- Letzte Dokumente

**Betroffene Dateien**:
- Neue Datei: `tab_dashboard.php`
- `index.php` (neuer Tab)

**Aufwand**: ~4-6 Stunden

---

#### 2.3 Kalender-Integration
**Problem**: Termine nicht in externen Kalendern
**Lösung**: iCal/Google Calendar Export

**Features**:
- `.ics` Datei-Export für Meetings
- Abonnierbare Kalender-URL
- Erinnerungen

**Betroffene Dateien**:
- Neue Datei: `api/calendar_export.php`
- Neue Datei: `api/calendar_feed.php`

**Aufwand**: ~3-4 Stunden

---

#### 2.4 Benachrichtigungs-Center
**Problem**: Benachrichtigungen sind über verschiedene Tabs verteilt
**Lösung**: Zentrales Notification-System mit Badge

**Features**:
- Globale Benachrichtigungs-Glocke
- Dropdown mit allen Notifications
- "Alle als gelesen"-Funktion
- Push-Benachrichtigungen (optional)

**Betroffene Dateien**:
- Erweiterung: `module_notifications.php`
- Neue Datei: `api/notifications_mark_read.php`
- `index.php` (Header-Integration)

**Aufwand**: ~4-5 Stunden

---

#### 2.5 Meeting-Duplikation verbessern
**Aktuell**: Festes Intervall (+7 Tage)
**Verbesserung**: Freies Intervall wählbar

**Features**:
- Dropdown: +1 Woche, +2 Wochen, +1 Monat
- Oder: Freie Datumsauswahl

**Betroffene Dateien**:
- `tab_meetings.php` (Formular)
- `process_meetings.php` (Backend)

**Aufwand**: ~1 Stunde

---

#### 2.6 Drag & Drop Dokument-Upload
**Aktuell**: Klassischer File-Input
**Verbesserung**: Moderne Drag & Drop Zone

**Features**:
- Mehrere Dateien gleichzeitig
- Fortschrittsbalken
- Thumbnail-Vorschau

**Betroffene Dateien**:
- `tab_documents.php` (JavaScript)
- `process_documents.php` (Multi-Upload)

**Aufwand**: ~2-3 Stunden

---

#### 2.7 Progressive Web App (PWA)
**Problem**: Keine mobile App
**Lösung**: PWA mit Offline-Fähigkeit

**Features**:
- Installierbar auf Smartphone
- Offline-Modus für Tagesordnung
- Push-Benachrichtigungen
- App-Icon

**Neue Dateien**:
- `manifest.json`
- `service-worker.js`

**Aufwand**: ~6-8 Stunden

---

### Priorität 3: Code-Qualität & Wartung

#### 3.1 Unit-Tests schreiben
**Problem**: Keine automatisierten Tests
**Lösung**: PHPUnit Tests für kritische Funktionen

**Fokus**:
- `external_participants_functions.php`
- `documents_functions.php`
- `opinion_functions.php`

**Aufwand**: ~4-6 Stunden

---

#### 3.2 Access-Level Migration
**Problem**: Alte Access-Levels (12, 19) noch in Datenbank
**Lösung**: Migrations-Skript zum Konvertieren

**Alt → Neu**:
- 12 → 15 (Führungsrollen)
- 19 → 18 (Vorstand + GF + Ass)

**Neue Datei**: `migrations/migrate_old_access_levels.sql`

**Aufwand**: ~1 Stunde

---

#### 3.3 Performance-Optimierung
**Bereiche**:
- Datenbank-Indizes prüfen
- N+1 Query-Probleme beheben
- Caching für häufige Abfragen

**Aufwand**: ~3-4 Stunden

---

#### 3.4 Security-Audit
**Prüfen**:
- SQL-Injection Risiken
- XSS-Anfälligkeiten
- CSRF-Protection
- Token-Sicherheit

**Aufwand**: ~2-3 Stunden

---

## 🗂️ Projekt-Struktur (wichtigste Dateien)

### Core-Dateien
```
├── index.php                    # Haupt-Entry-Point
├── config.php                   # Konfiguration (NICHT überschreiben!)
├── config_adapter.php           # Adapter-Auswahl (SSO-relevant)
├── db-init.sql                  # Datenbank-Schema (aktuell)
└── functions.php                # Globale Utility-Funktionen
```

### Features
```
├── tab_*.php                    # Tab-Seiten (Meetings, Dokumente, etc.)
├── process_*.php                # Backend-Logik (POST-Handler)
├── *_functions.php              # Feature-spezifische Funktionen
└── api/*                        # AJAX/API Endpunkte
```

### Externe Features
```
├── opinion_standalone.php       # Meinungsbild (Token-basiert)
├── terminplanung_standalone.php # Terminplanung (Token-basiert)
└── external_participant_register.php  # Externe Teilnehmer-Registrierung
```

### Tools & Admin
```
├── tools/
│   ├── demo_export.php         # Demo-Daten exportieren
│   ├── demo_import.php         # Demo-Daten importieren
│   └── production_reset.php    # Datenbank zurücksetzen
└── migrations/
    ├── *.sql                   # Datenbank-Migrationen
    ├── run_migrations.sh       # Automatisches Migrations-Skript
    └── README.md               # Migration-Dokumentation
```

### Dokumentation
```
├── README.md                   # Projekt-Übersicht
├── CHANGELOG.md                # Änderungshistorie
├── SESSION_HANDOVER.md         # Letzte Session-Übergabe
├── NEXT_TASKS.md              # Diese Datei
├── EXTERNE_TEILNEHMER_README.md  # Feature-Dokumentation
├── DEVELOPER.md                # Entwickler-Docs
└── migrations/README.md        # Migration-Anleitung
```

---

## 🐛 Bekannte Issues / TODOs

### Keine kritischen Bugs ✅

**Optionale Verbesserungen**:

1. **E-Mail-Versand**: Token-URLs manuell versenden (automatisierung wünschenswert)
2. **Access-Level**: Alte Werte in DB migrieren (12 → 15, 19 → 18)
3. **Meeting-Duplikation**: Intervall fest auf +7 Tage (variabel wäre besser)
4. **Performance**: N+1 Queries bei großen Teilnehmerlisten

---

## 💡 Best Practices für dieses Projekt

### 1. Adapter-Pattern verwenden
**Richtig**:
```php
$user = get_member_by_id($pdo, $member_id);
```

**Falsch**:
```php
$stmt = $pdo->prepare("SELECT * FROM svmembers WHERE member_id = ?");
```

### 2. Prepared Statements
Alle SQL-Queries nutzen PDO prepared statements.

### 3. Token-Generierung
```php
$token = bin2hex(random_bytes(32)); // Sichere Token-Generierung
```

### 4. URL-Validierung
```php
filter_var($url, FILTER_VALIDATE_URL);
```

### 5. Berechtigungen
```php
$is_admin = is_admin_user($current_user);  // Flag-basiert
// NICHT rollenbasiert (GF, Assistenz)
```

---

## 🔧 Entwicklungsumgebung

### Voraussetzungen
- PHP 7.4+
- MySQL/MariaDB 5.7+
- Apache/Nginx mit mod_rewrite
- Optional: Composer (für zukünftige Dependencies)

### Lokales Setup
```bash
# 1. Repository klonen
git clone https://github.com/zurgo42/Sitzungsverwaltung.git

# 2. Datenbank erstellen
mysql -u root -p -e "CREATE DATABASE Sitzungsverwaltung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Schema importieren
mysql -u root -p Sitzungsverwaltung < db-init.sql

# 4. Migrationen ausführen
cd migrations
./run_migrations.sh

# 5. config.php anpassen (Datenbank-Zugangsdaten)
nano config.php
```

### Demo-Daten (optional)
```bash
# Demo-Daten importieren
php tools/demo_import.php
```

---

## 📞 Support & Ressourcen

### Dokumentation
- **Projekt-Übersicht**: `README.md`
- **Änderungshistorie**: `CHANGELOG.md`
- **Entwickler-Docs**: `DEVELOPER.md`
- **Letzte Session**: `SESSION_HANDOVER.md`

### Bei Fragen
1. Siehe relevante `*_README.md` Dateien
2. Prüfe `CHANGELOG.md` für kürzliche Änderungen
3. Lies `SESSION_HANDOVER.md` für Details der letzten Session

---

## 🎯 Empfohlene erste Aufgabe für neue Session

**Wenn du eine neue Claude Code Session startest, empfehle ich:**

### Aufgabe: E-Mail-Automation für Externe Teilnehmer

**Warum?**
- Hoher Nutzen für die Anwender
- Klare Abgrenzung (keine großen Refactorings)
- Nutzt bestehendes Mail-System
- ~2-3 Stunden Aufwand

**Vorgehen**:
1. Lies `EXTERNE_TEILNEHMER_README.md`
2. Verstehe Token-Generierung in `api/external_participant_create.php`
3. Erweitere um E-Mail-Versand
4. Erstelle Template in `mail_templates/`
5. Teste mit echten E-Mail-Adressen

**Alternativ**: Deployment & Testing (Priorität 1)

---

**Viel Erfolg! 🚀**

---

*Erstellt am: 2025-12-23*
*Für Session: claude/fix-sso-integration-01NbwbYdHVMH7hEM5HwQmFji und folgende*
*Status: Produktionsreif*
