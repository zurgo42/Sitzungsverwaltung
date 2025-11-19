# Migration vom alten zum neuen System

## Übersicht der Änderungen

Dieser Guide hilft dir beim Umstieg vom alten Referenten-Skript (Version 2013) zum neuen modernen System.

## Hauptunterschiede

### 1. Datei-Organisation

**Alt:**
- Eine einzige monolithische PHP-Datei
- Vermischung von HTML, PHP und JavaScript
- Keine klare Struktur

**Neu:**
```
referenten/
├── referenten.php          # Controller (Logik)
├── includes/               # PHP-Klassen
│   ├── Database.php
│   ├── ReferentenModel.php
│   └── Security.php
├── templates/              # HTML-Templates
│   ├── header.php
│   ├── footer.php
│   ├── formular.php
│   ├── liste.php
│   └── vortrag_detail.php
├── css/                    # Styles
│   └── referenten.css
└── js/                     # JavaScript
    └── referenten.js
```

### 2. Datenbank-Zugriff

**Alt:**
```php
$link = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS);
mysqli_select_db($link, MYSQL_DATABASE);
$db = 'SELECT * FROM Refpool WHERE MNr="'.$MNr.'"';
$s = mysqli_query($link, $db);
```
⚠️ **Probleme:**
- Keine Prepared Statements
- SQL-Injection-Gefahr
- String-Konkatenation

**Neu:**
```php
$model = new ReferentenModel();
$vortraege = $model->getVortraegeByMNr($mNr);
```
✅ **Vorteile:**
- PDO mit Prepared Statements
- Objektorientiert
- SQL-Injection-sicher

### 3. Sicherheit

**Alt:**
```php
// Direkte Verwendung von $_POST ohne Validierung
$drow['Thema'] = ipost('Thema');

// Kein CSRF-Schutz
// Keine Input-Sanitization
// Unsicheres Escaping
```

**Neu:**
```php
// CSRF-Schutz
$csrfToken = Security::generateCSRFToken();
Security::verifyCSRFToken($_POST['csrf_token']);

// Input-Validierung
$data = [
    'Thema' => Security::cleanInput($_POST['Thema'] ?? '')
];

// XSS-Schutz bei Ausgabe
<?= Security::escape($vortrag['Thema']) ?>
```

### 4. HTML-Struktur

**Alt:**
```html
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
...
<div id="header"><div id="headerBG"><div id="headerR1">...
```

**Neu:**
```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    ...
<header class="header">
    <div class="header-content">
        <h1>MinD-Referentenliste</h1>
```

### 5. CSS

**Alt:**
```html
<!-- Externe CSS-Datei mit veralteten Styles -->
<link href="herz.css" rel="stylesheet" type="text/css">

<!-- Viele Inline-Styles -->
<td class="text5" style="text-align: right; color: red;">
```

**Neu:**
```css
/* Modern mit CSS-Variablen */
:root {
    --primary-color: #2c5aa0;
    --spacing-md: 1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-container {
        grid-template-columns: 1fr;
    }
}
```

### 6. JavaScript

**Alt:**
```javascript
function FensterOeffnen(Adresse) {
  MeinFenster = window.open(Adresse, "Zweitfenster",
    "width=280,height=400,left=100,top=200");
  MeinFenster.focus();
}
```

**Neu:**
```javascript
class ModalManager {
    constructor() {
        this.modal = document.getElementById('detailModal');
        this.init();
    }

    async loadContent(url) {
        const response = await fetch(url);
        const html = await response.text();
        this.setContent(html);
    }
}
```

## Migrations-Schritte

### Schritt 1: Datenbank-Backup

```bash
mysqldump -u username -p datenbankname Refname Refpool PLZ > backup_$(date +%Y%m%d).sql
```

### Schritt 2: Datenbank-Schema anpassen

```sql
-- Charset aktualisieren
ALTER TABLE Refname CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE Refpool CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- IP-Feld für IPv6 erweitern
ALTER TABLE Refpool MODIFY COLUMN IP VARCHAR(45);

-- E-Mail-Feld erweitern
ALTER TABLE Refname MODIFY COLUMN eMail VARCHAR(100);

-- Foreign Key hinzufügen (falls nicht vorhanden)
ALTER TABLE Refpool ADD CONSTRAINT fk_refpool_mnr
  FOREIGN KEY (MNr) REFERENCES Refname(MNr)
  ON DELETE CASCADE ON UPDATE CASCADE;
```

### Schritt 3: Altes System sichern

```bash
# Aktuelles System umbenennen
mv referenten.php referenten_alt.php

# Backup-Verzeichnis erstellen
mkdir -p backup_old_system
cp -r *.php *.css backup_old_system/
```

### Schritt 4: Neues System installieren

```bash
# Neues Verzeichnis erstellen
mkdir referenten

# Dateien kopieren (siehe README.md)
```

### Schritt 5: Konfiguration anpassen

Stelle sicher, dass `config.php` korrekt ist:

```php
<?php
define('MYSQL_HOST', 'localhost');
define('MYSQL_USER', 'dein_username');
define('MYSQL_PASS', 'dein_passwort');
define('MYSQL_DATABASE', 'dein_datenbankname');
```

### Schritt 6: Testen

1. **Funktionstest:**
   - Rufe `referenten/referenten.php` auf
   - Teste Login (falls vorhanden)
   - Teste Formular-Eingabe
   - Teste Listen-Anzeige
   - Teste Detail-Ansicht

2. **Browser-Test:**
   - Chrome
   - Firefox
   - Safari (wenn möglich)
   - Mobile Browser

3. **Sicherheitstest:**
   - Teste CSRF-Schutz (Form ohne Token absenden)
   - Teste XSS (Script-Tags in Eingabefeldern)
   - Teste SQL-Injection (Sonderzeichen in Feldern)

### Schritt 7: URLs anpassen

**Alt:**
```
http://example.de/referenten.php
```

**Neu:**
```
http://example.de/referenten/referenten.php
```

**Optional: URL-Rewriting**

Füge in `.htaccess` im Hauptverzeichnis hinzu:

```apache
RewriteEngine On
RewriteRule ^referenten$ referenten/referenten.php [L]
```

Dann funktioniert auch:
```
http://example.de/referenten
```

## Daten-Migration

Die Datenbank-Daten können 1:1 übernommen werden, da die Tabellen-Struktur kompatibel ist. Lediglich die Charset-Konvertierung ist erforderlich (siehe oben).

## Feature-Vergleich

| Feature | Alt | Neu |
|---------|-----|-----|
| HTML-Version | 4.01 | HTML5 |
| Responsive Design | ❌ | ✅ |
| Mobile-Optimierung | ❌ | ✅ |
| CSRF-Schutz | ❌ | ✅ |
| XSS-Schutz | ⚠️ Teilweise | ✅ |
| SQL-Injection-Schutz | ⚠️ Teilweise | ✅ |
| Prepared Statements | ❌ | ✅ |
| PDO | ❌ (mysqli) | ✅ |
| OOP-Struktur | ❌ | ✅ |
| Code-Trennung | ❌ | ✅ |
| Formular-Validierung | ⚠️ Server-seitig | ✅ Client + Server |
| Modal-Fenster | ❌ Popup | ✅ Modern |
| Browser-Caching | ❌ | ✅ |
| GZIP-Kompression | ❌ | ✅ |
| CSS-Variablen | ❌ | ✅ |
| ES6+ JavaScript | ❌ | ✅ |
| Barrierefreiheit | ⚠️ Eingeschränkt | ✅ |

## Wartung nach Migration

### Regelmäßige Aufgaben

1. **Datenbank-Backup** (täglich/wöchentlich)
   ```bash
   0 2 * * * mysqldump -u user -p database > /backup/db_$(date +\%Y\%m\%d).sql
   ```

2. **Log-Dateien prüfen**
   ```bash
   tail -f /var/log/php_errors.log
   ```

3. **Sicherheits-Updates**
   - PHP regelmäßig aktualisieren
   - Abhängigkeiten prüfen

## Rollback-Plan

Falls Probleme auftreten:

```bash
# Schritt 1: Altes System wiederherstellen
mv referenten_alt.php referenten.php

# Schritt 2: Datenbank wiederherstellen (falls nötig)
mysql -u username -p datenbankname < backup_YYYYMMDD.sql

# Schritt 3: Neues System entfernen
mv referenten referenten_new_backup
```

## Support

Bei Problemen während der Migration:

1. Überprüfe die Logs
2. Teste einzelne Komponenten
3. Vergleiche mit dem alten System
4. Kontaktiere den Support

## Best Practices nach Migration

1. **Monitoring einrichten**
   - Error-Logging aktivieren
   - Access-Logs überwachen

2. **Backups automatisieren**
   - Täglich Datenbank-Backups
   - Wöchentlich File-Backups

3. **Dokumentation pflegen**
   - Änderungen dokumentieren
   - Anpassungen festhalten

4. **Regelmäßige Updates**
   - Sicherheits-Patches einspielen
   - Code auf Verbesserungen prüfen

## Häufige Probleme

### Problem: Sessions funktionieren nicht
**Lösung:**
```php
// In php.ini oder .htaccess
session.save_path = "/tmp"
session.gc_maxlifetime = 1440
```

### Problem: CSS/JS werden nicht geladen
**Lösung:**
- Pfade in templates/header.php prüfen
- Browser-Cache leeren
- Dateiberechtigungen prüfen (644)

### Problem: Datenbankverbindung schlägt fehl
**Lösung:**
- config.php Zugangsdaten prüfen
- PDO-Extension aktiviert? `php -m | grep pdo`
- MySQL-Benutzer-Rechte prüfen

## Fazit

Die Migration ist ein wichtiger Schritt zur Modernisierung. Das neue System bietet:

- ✅ Bessere Sicherheit
- ✅ Bessere Wartbarkeit
- ✅ Bessere Benutzererfahrung
- ✅ Zukunftssicherheit

Mit sorgfältiger Planung und Testing ist die Migration in wenigen Stunden durchführbar.
