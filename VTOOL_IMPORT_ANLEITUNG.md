# VTool Import - Anleitung

## Zweck
Dieses Skript kopiert das VTool-Verzeichnis und entfernt automatisch alle sensiblen Daten (Credentials, Passwörter, API-Keys), damit der Code sicher analysiert werden kann.

## Voraussetzungen

### Unter Windows (WSL/Git Bash)
1. Stellen Sie sicher, dass Ihr Windows-Laufwerk gemountet ist:
   ```bash
   ls /mnt/d/xampp/htdocs/
   ```
   Falls nicht verfügbar, mounten Sie es oder verwenden Sie den vollständigen Windows-Pfad.

### Unter Linux
Direkter Zugriff auf das VTool-Verzeichnis erforderlich.

## Verwendung

### Schritt 1: Skript ausführen
```bash
cd /home/user/Sitzungsverwaltung
./sanitize_vtool.sh /pfad/zum/vtool-verzeichnis
```

**Beispiel (Windows mit WSL):**
```bash
./sanitize_vtool.sh /mnt/d/xampp/htdocs/vtool
```

**Beispiel (Linux):**
```bash
./sanitize_vtool.sh /var/www/html/vtool
```

### Schritt 2: Manuelle Überprüfung
Nach der automatischen Sanitisierung sollten Sie manuell prüfen:

```bash
# Suche nach möglicherweise übersehenen Credentials
grep -r 'password\|secret\|key\|token' vtool-reference/ --include='*.php' | grep -v BEISPIEL

# Liste aller .example Dateien (sanitisiert)
find vtool-reference/ -name "*.example"

# Prüfe auf SQL-Dumps mit personenbezogenen Daten
find vtool-reference/ -name "*.sql"
```

### Schritt 3: Zusätzliche manuelle Bereinigung (falls nötig)

Falls das Skript etwas übersehen hat:

```bash
# Bestimmte Datei manuell editieren
nano vtool-reference/pfad/zur/datei.php

# Oder ganze Dateien löschen
rm vtool-reference/backup_mit_daten.sql
```

## Was das Skript macht

### ✓ Kopiert
- Alle PHP-Dateien
- HTML/CSS/JS-Dateien
- SQL-Schema-Dateien
- Dokumentation

### ✗ Ignoriert
- `.git` Verzeichnisse
- `*.log` Dateien
- `*.bak` Backups
- `vendor/` und `node_modules/`

### 🔒 Sanitisiert

**In PHP-Dateien:**
```php
// Vorher:
define('DB_PASS', 'mein-echtes-passwort-123');
// Nachher:
define('DB_PASS', 'BEISPIEL_PASSWORT');
```

**In .htaccess:**
```apache
# Vorher:
AuthLDAPBindPassword "echt3s-ldap-pa55w0rt"
# Nachher:
AuthLDAPBindPassword "BEISPIEL_LDAP_PASSWORD"
```

**Umbenennung:**
- `config.php` → `config.php.example`
- `.htaccess` → `.htaccess.example`
- `.env` → `.env.example`

## Ergebnis

Nach erfolgreicher Ausführung liegt eine sichere Kopie in:
```
/home/user/Sitzungsverwaltung/vtool-reference/
```

Diese kann dann sicher analysiert werden, ohne echte Credentials preiszugeben.

## Wichtige Hinweise

⚠️ **SQL-Dumps:** Falls SQL-Dateien mit echten Mitgliederdaten vorhanden sind, sollten diese manuell geprüft und ggf. gelöscht werden. Das Skript kann personenbezogene Daten in SQL-Dumps nicht automatisch erkennen.

⚠️ **Kommentare im Code:** Falls Credentials in Kommentaren stehen, werden diese möglicherweise nicht erkannt.

⚠️ **Hardcoded Credentials:** Falls Credentials direkt in SQL-Queries oder als String-Literals eingebettet sind, können diese übersehen werden.

## Bei Problemen

Falls das Skript nicht funktioniert oder Sie Fragen haben:
1. Prüfen Sie die Fehlermeldung
2. Stellen Sie sicher, dass `rsync` installiert ist: `which rsync`
3. Prüfen Sie Dateiberechtigungen: `ls -la sanitize_vtool.sh`
