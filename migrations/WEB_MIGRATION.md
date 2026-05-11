# VTool Migration - Web-Interface

## 🌐 Für Server ohne CLI-Zugriff

Wenn Sie keinen Kommandozeilen-Zugriff auf dem Server haben (typisch bei Webhosting), können Sie die Migration über den Browser durchführen.

## Setup

### 1. config.php erstellen

Falls noch nicht vorhanden:

```bash
# Lokal in Windows:
copy config.example.php config.php

# Oder Linux/Mac:
cp config.example.php config.php
```

Dann `config.php` bearbeiten und Datenbank-Zugangsdaten eintragen.

### 2. Passwort ändern (WICHTIG!)

Öffnen Sie `migrations/migrate_vtool_web.php` und ändern Sie Zeile 12:

```php
$MIGRATION_PASSWORD = 'IHR_SICHERES_PASSWORT';
```

**⚠️ Standard-Passwort: `migration2024` - BITTE ÄNDERN!**

## Migration durchführen

### 1. Browser öffnen

**Lokal:**
```
http://localhost/Sitzungsverwaltung/migrations/migrate_vtool_web.php
```

**Auf Server:**
```
https://ihre-domain.de/pfad/zu/Sitzungsverwaltung/migrations/migrate_vtool_web.php
```

### 2. Anmelden

Geben Sie das Migrations-Passwort ein.

### 3. Statistik prüfen

Sie sehen:
- ✅ Anzahl Anträge in VTool
- ✅ Bereits migrierte Anträge
- ✅ Noch zu migrierende Anträge

### 4. Dry-Run (Empfohlen!)

Klicken Sie auf **"🔍 Dry-Run (Test)"**

Dieser Test:
- ✅ Zeigt an, was migriert werden würde
- ✅ Ändert KEINE Daten
- ✅ Prüft ob alles funktioniert

Schauen Sie sich das Log an:
- Grün = Erfolg
- Rot = Fehler
- Blau = Information

### 5. Migration ausführen

Wenn der Dry-Run erfolgreich war:

Klicken Sie auf **"✅ Migration ausführen"**

Das Script:
- Migriert alle Anträge von VTool nach Sitzungsverwaltung
- Zeigt Fortschritt in Echtzeit
- Erstellt automatisch Rollback bei Fehlern
- Lädt nach Erfolg neu

## Features

### ✨ Vorteile gegenüber CLI

- ✅ Keine Kommandozeile nötig
- ✅ Funktioniert auf jedem Server
- ✅ Schöne Benutzeroberfläche
- ✅ Echtzeit-Fortschrittsanzeige
- ✅ Farbcodiertes Log
- ✅ Automatische Statistik

### 🔒 Sicherheit

- Passwort-geschützt
- Session-basiert
- Nach Migration: Datei löschen!

### 🔄 Wiederholbar

- Bereits migrierte Anträge werden übersprungen
- Kann mehrfach ausgeführt werden
- Nur neue Daten werden migriert

## Nach erfolgreicher Migration

### ⚠️ WICHTIG: Datei löschen oder umbenennen!

```bash
# Lokal oder auf Server:
mv migrations/migrate_vtool_web.php migrations/migrate_vtool_web.php.DONE

# Oder löschen:
rm migrations/migrate_vtool_web.php
```

**Warum?** 
- Verhindert unbefugten Zugriff
- Migration sollte nur einmal laufen
- Sicherheitsrisiko wenn öffentlich zugänglich

## Troubleshooting

### "No such file or directory: config.php"

→ Erstellen Sie `config.php` aus `config.example.php`

### "Table 'svbproposals' doesn't exist"

→ Führen Sie zuerst `init-db.php` aus (über Browser oder CLI)

### "Falsches Passwort"

→ Ändern Sie das Passwort in `migrate_vtool_web.php` Zeile 12

### "500 Internal Server Error"

→ Prüfen Sie:
1. PHP Version >= 7.4
2. `config.php` existiert
3. Datenbank-Verbindung funktioniert
4. PHP Error Log: `/xampp/apache/logs/error.log`

## Datenbank-Info

Bei Ihnen sind **alle Tabellen in einer Datenbank**:

```
Datenbank: aktive
├── antraege (VTool, alt)
└── svbproposals (Sitzungsverwaltung, neu)
```

Die Migration kopiert Daten von `antraege` → `svbproposals` innerhalb derselben Datenbank.

## Was wird migriert?

- ✅ Alle Anträge (Titel, Beschreibung, Betrag)
- ✅ Status (A/B/V/X/Z → editing/voting/approved/rejected/withdrawn)
- ✅ Dateianhänge (file1-4 → separate Zeilen)
- ✅ Abstimmungen (VName1-6, Votum1-6 → separate Zeilen)
- ✅ Freigaben (verf1/verf2)
- ✅ Kommentare

## Was wird NICHT migriert?

- ❌ Zahlungssystem (bleibt in VTool)
- ❌ Freigabe-Workflow (wird neu aufgebaut)
- ❌ Forum-Verknüpfungen (später)
