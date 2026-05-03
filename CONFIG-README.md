# ⚠️ CONFIG-DATEIEN: WICHTIGE HINWEISE

## Übersicht

Die folgenden Dateien enthalten **serversspezifische Konfigurationen**:

- `config.php` - Datenbank-Zugangsdaten, System-Einstellungen
- `config_adapter.php` - Login-Modus, Mitglieder-Datenquelle, Display-Modi

## ⚠️ WICHTIG BEI GIT PULL/MERGE

Diese Dateien sind seit dem letzten Commit in `.gitignore` eingetragen!

**Das bedeutet:**
- ✅ Änderungen an config-Dateien werden **NICHT** automatisch gepusht
- ✅ Bei `git pull` werden sie **NICHT** überschrieben
- ⚠️ **Neue Features** in diesen Dateien müssen **manuell** eingepflegt werden!

## Arbeitsablauf

### Bei lokalem Entwickeln:
1. Ändere `config.php` oder `config_adapter.php` nach Bedarf
2. Git wird diese Änderungen ignorieren
3. Deine lokalen Einstellungen bleiben erhalten

### Bei Deployment auf Produktivserver:
1. **VORHER**: Sichere deine produktivspezifischen Werte
   ```bash
   cp config.php config.backup.php
   cp config_adapter.php config_adapter.backup.php
   ```

2. `git pull` ausführen

3. **NACHHER**: Prüfe die `.example` Dateien auf neue Features
   ```bash
   diff config.php config.example.php
   diff config_adapter.php config_adapter.example.php
   ```

4. **Manuell** neue Features aus `.example` in deine `config.php` übernehmen

5. **Produktivspezifische Werte** wieder eintragen

## .example-Dateien

Die `.example`-Dateien enthalten:
- ✅ Alle aktuellen Funktionen und Features
- ✅ Kommentare und Erklärungen
- ✅ Beispielwerte für lokale Entwicklung

**Diese Dateien WERDEN in Git getrackt** und zeigen dir neue Features!

## Neue Installation

Wenn du das Sitzungstool auf einem **neuen Server** installierst:

```bash
# 1. Kopiere die Example-Dateien
cp config.example.php config.php
cp config_adapter.example.php config_adapter.php

# 2. Passe die Werte an deinen Server an
nano config.php          # DB-Zugangsdaten eintragen
nano config_adapter.php  # Login-Modus, Member-Source konfigurieren
```

## Was steht wo?

### config.php
- Datenbank-Zugangsdaten (DB_HOST, DB_USER, DB_PASS, DB_NAME)
- Session-Konfiguration
- Timezone, Session-Timeout
- E-Mail-Einstellungen
- SMTP-Konfiguration (falls aktiviert)
- Footer-Texte und URLs

### config_adapter.php
- Login-Modus (REQUIRE_LOGIN)
- Mitglieder-Datenquelle (MEMBER_SOURCE: 'members' oder 'berechtigte')
- SSO-Konfiguration (SSO_SOURCE)
- Test-Mitgliedsnummer (nur für Entwicklung)
- Display-Modi (Standard, SSO-Direkt, Minimal)

## Backup-Strategie

**Vor jedem größeren Update:**
```bash
# Backup erstellen
cp config.php config.backup-$(date +%Y%m%d).php
cp config_adapter.php config_adapter.backup-$(date +%Y%m%d).php

# Git pull
git pull

# Prüfen was neu ist
diff config.php config.example.php

# Manuell neue Features übernehmen
nano config.php
```

## Troubleshooting

### Problem: Nach `git pull` funktioniert nichts mehr

**Ursache:** Config-Datei wurde versehentlich überschrieben

**Lösung:**
```bash
# Backup wiederherstellen
cp config.backup.php config.php
cp config_adapter.backup.php config_adapter.php

# Oder von .example neu starten
cp config.example.php config.php
# Dann Werte manuell anpassen
```

### Problem: Neue Features fehlen nach Update

**Ursache:** Config-Dateien wurden nicht aktualisiert

**Lösung:**
```bash
# Was ist neu?
diff config.php config.example.php

# Neue Zeilen manuell übernehmen
nano config.php
```

## Serversspezifische Unterschiede

### Lokaler XAMPP:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('REQUIRE_LOGIN', true);
```

### Produktivserver (mit VTool):
```php
// Alte VTool config.php hat:
// MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DATABASE
// → Werden automatisch gemappt auf DB_*

define('REQUIRE_LOGIN', false);  // SSO-Modus
define('MEMBER_SOURCE', 'berechtigte');
```

### Produktivserver (ohne VTool):
```php
define('DB_HOST', '...');  // Dein DB-Server
define('DB_USER', '...');
define('DB_PASS', '...');
define('REQUIRE_LOGIN', true);
define('MEMBER_SOURCE', 'members');
```

## Checkliste bei Updates

- [ ] Backup der aktuellen config.php und config_adapter.php
- [ ] `git pull` ausführen
- [ ] `diff` zwischen deiner config und .example prüfen
- [ ] Neue Features aus .example übernehmen
- [ ] Produktivspezifische Werte kontrollieren
- [ ] System testen
- [ ] Bei Problemen: Backup wiederherstellen

---

**Erstellt:** 2026-05-03
**Grund:** Config-Dateien enthalten serversspezifische Werte und sollen nicht überschrieben werden
