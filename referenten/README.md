# MinD-Referentenliste - Modernisierte Version

## Überblick

Dies ist eine komplett modernisierte Version der MinD-Referentenliste. Das ursprüngliche Skript von 2013 wurde von Grund auf neu entwickelt mit modernen Web-Technologien und Best Practices.

## Was wurde modernisiert?

### 1. **HTML**
- ✅ HTML5 statt HTML 4.01 Transitional
- ✅ Semantische HTML-Elemente
- ✅ Responsive Meta-Tags
- ✅ Saubere Struktur ohne Inline-Styles

### 2. **PHP**
- ✅ **PDO statt mysqli** - Moderne Datenbankanbindung
- ✅ **Prepared Statements** - Schutz vor SQL-Injection
- ✅ **Objektorientierte Programmierung** - Bessere Code-Organisation
- ✅ **Trennung von Logik und Präsentation** - MVC-ähnliche Struktur
- ✅ **CSRF-Schutz** - Sicherheitstoken für Formulare
- ✅ **XSS-Prävention** - Ausgaben werden escaped
- ✅ **Input-Validierung** - Sichere Datenverarbeitung
- ✅ **Error Handling** - Try-Catch-Blöcke und Logging

### 3. **CSS**
- ✅ Modernes, responsives Design
- ✅ CSS Custom Properties (CSS-Variablen)
- ✅ Flexbox und Grid Layout
- ✅ Mobile-First Ansatz
- ✅ Ansprechende Animationen und Übergänge
- ✅ Konsistentes Farbschema
- ✅ Accessibility-Features

### 4. **JavaScript**
- ✅ ES6+ Syntax (Klassen, Arrow Functions, etc.)
- ✅ Modulare Struktur
- ✅ Fetch API statt XMLHttpRequest
- ✅ Asynchrone Programmierung (async/await)
- ✅ Event Delegation
- ✅ Formular-Validierung in Echtzeit
- ✅ Modal-Fenster statt Popup-Windows
- ✅ Zeichenzähler für Textfelder

### 5. **Sicherheit**
- ✅ CSRF-Schutz
- ✅ XSS-Prävention
- ✅ SQL-Injection-Schutz
- ✅ Input-Sanitization
- ✅ Sichere Session-Verwaltung
- ✅ IP-Validierung
- ✅ Error Logging

## Dateistruktur

```
referenten/
├── referenten.php          # Haupt-Controller
├── includes/
│   ├── Database.php        # Datenbankverbindung (PDO)
│   ├── ReferentenModel.php # Datenbank-Operationen
│   └── Security.php        # Sicherheitsfunktionen
├── templates/
│   ├── header.php          # Header-Template
│   ├── footer.php          # Footer-Template
│   ├── formular.php        # Formular-Ansicht
│   ├── liste.php           # Listen-Ansicht
│   └── vortrag_detail.php  # Detail-Ansicht
├── css/
│   └── referenten.css      # Modernes CSS
├── js/
│   └── referenten.js       # Modernes JavaScript
└── README.md               # Diese Datei
```

## Installation

### Voraussetzungen

- PHP 7.4 oder höher (empfohlen: PHP 8.0+)
- MySQL 5.7+ oder MariaDB 10.2+
- PDO-Extension für PHP

### Schritte

1. **Dateien hochladen**
   ```bash
   # Kopiere das gesamte referenten-Verzeichnis auf deinen Server
   ```

2. **Datenbank-Tabellen**

   Die folgenden Tabellen werden benötigt:

   ```sql
   -- Persönliche Daten der Referenten
   CREATE TABLE IF NOT EXISTS Refname (
       MNr VARCHAR(10) PRIMARY KEY,
       Vorname VARCHAR(50),
       Name VARCHAR(50),
       Titel VARCHAR(20),
       PLZ VARCHAR(5),
       Ort VARCHAR(50),
       Gebj VARCHAR(4),
       Beruf VARCHAR(100),
       Telefon VARCHAR(30),
       eMail VARCHAR(100),
       datum DATETIME
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   -- Vortragsangebote
   CREATE TABLE IF NOT EXISTS Refpool (
       ID INT AUTO_INCREMENT PRIMARY KEY,
       MNr VARCHAR(10),
       Was VARCHAR(50),
       Wo VARCHAR(100),
       Entf INT,
       Thema VARCHAR(100),
       Inhalt TEXT,
       Kategorie VARCHAR(50),
       Equipment VARCHAR(100),
       Dauer VARCHAR(10),
       Kompetenz TEXT,
       Bemerkung TEXT,
       aktiv TINYINT(1) DEFAULT 1,
       IP VARCHAR(45),
       datum DATETIME,
       FOREIGN KEY (MNr) REFERENCES Refname(MNr)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   -- PLZ-Datenbank für Entfernungsberechnung
   CREATE TABLE IF NOT EXISTS PLZ (
       plz VARCHAR(5) PRIMARY KEY,
       Ort VARCHAR(100),
       lon DECIMAL(10, 7),
       lat DECIMAL(10, 7)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

3. **Konfiguration**

   Stelle sicher, dass die `config.php` im Hauptverzeichnis existiert:

   ```php
   <?php
   define('MYSQL_HOST', 'localhost');
   define('MYSQL_USER', 'dein_username');
   define('MYSQL_PASS', 'dein_passwort');
   define('MYSQL_DATABASE', 'dein_datenbankname');
   ?>
   ```

4. **Berechtigungen**
   ```bash
   chmod 755 referenten/
   chmod 644 referenten/*.php
   chmod 644 referenten/css/*.css
   chmod 644 referenten/js/*.js
   ```

5. **Testen**
   - Rufe `referenten/referenten.php` im Browser auf
   - Teste das Formular
   - Teste die Listen-Ansicht
   - Teste die Detail-Ansicht

## Verwendung

### Für Referenten

1. **Persönliche Daten eingeben**
   - Fülle das Formular mit deinen Kontaktdaten aus
   - Diese werden einmalig gespeichert

2. **Vortrag anlegen**
   - Wähle Art des Angebots (Vortrag, Workshop, etc.)
   - Gib die Region an
   - Beschreibe dein Thema
   - Füge Details hinzu

3. **Vortrag bearbeiten**
   - Wähle einen bestehenden Vortrag aus der Liste
   - Klicke auf "Ändern oder deaktivieren"
   - Passe die Daten an

4. **Vortrag deaktivieren**
   - Öffne den Bearbeitungsmodus
   - Entferne den Haken bei "Angebot ist aktiv"
   - Speichere

### Für Interessenten

1. **Liste aufrufen**
   - Klicke auf "Zur Referentenliste"

2. **Sortieren**
   - Wähle im Dropdown die Sortierspalte
   - Oder klicke auf die Spaltenüberschriften

3. **Details anzeigen**
   - Klicke auf "Details" bei einem Eintrag
   - Ein Modal-Fenster öffnet sich

4. **Kontakt aufnehmen**
   - Klicke auf "E-Mail"
   - Dein E-Mail-Programm öffnet sich mit Vorbefüllung

## Features

### Responsives Design
- ✅ Optimiert für Desktop, Tablet und Smartphone
- ✅ Mobile-First Ansatz
- ✅ Touch-freundliche Bedienelemente

### Barrierefreiheit
- ✅ Semantisches HTML
- ✅ Tastaturnavigation
- ✅ ARIA-Labels (wo nötig)
- ✅ Kontrastreiche Farben

### Performance
- ✅ Minimales JavaScript
- ✅ Effiziente CSS
- ✅ Lazy Loading für Details
- ✅ Datenbankindizes

### Sicherheit
- ✅ CSRF-Schutz bei allen Formularen
- ✅ XSS-Prävention bei allen Ausgaben
- ✅ SQL-Injection-Schutz durch Prepared Statements
- ✅ Input-Validierung
- ✅ Sichere Session-Verwaltung

## Browser-Unterstützung

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ⚠️ Internet Explorer wird nicht unterstützt

## Entwicklung

### Lokale Entwicklung

1. **PHP Development Server**
   ```bash
   cd referenten
   php -S localhost:8000
   ```

2. **Änderungen testen**
   - HTML/PHP: Seite neu laden
   - CSS: Hard Reload (Strg+Shift+R)
   - JavaScript: Console für Debugging

### Code-Stil

- **PHP**: PSR-12 Coding Standard
- **JavaScript**: ES6+ mit Semikolons
- **CSS**: BEM-ähnliche Namenskonvention

## Troubleshooting

### Problem: "Keine Verbindung zur Datenbank"
**Lösung**: Überprüfe die config.php und Datenbankzugangsdaten

### Problem: "CSRF-Token ungültig"
**Lösung**: Stelle sicher, dass Sessions funktionieren. Ggf. session_start() Fehler prüfen.

### Problem: Modal öffnet sich nicht
**Lösung**: Überprüfe die Browser-Console auf JavaScript-Fehler. Stelle sicher, dass referenten.js geladen wird.

### Problem: Styles werden nicht angezeigt
**Lösung**: Überprüfe den Pfad zu referenten.css. Ggf. Browser-Cache leeren.

## Migration vom alten System

Wenn du vom alten System migrierst:

1. **Datenbank-Backup erstellen**
   ```bash
   mysqldump -u username -p datenbankname Refname Refpool > backup.sql
   ```

2. **Tabellen-Struktur anpassen** (falls nötig)
   ```sql
   ALTER TABLE Refpool MODIFY COLUMN IP VARCHAR(45);
   ALTER TABLE Refname MODIFY COLUMN eMail VARCHAR(100);
   ```

3. **Altes Skript umbenennen**
   ```bash
   mv referenten.php referenten_alt.php
   ```

4. **Neues System aktivieren**
   - Kopiere die neuen Dateien
   - Teste gründlich

## Support

Bei Fragen oder Problemen:
- Überprüfe diese README
- Schaue in die Code-Kommentare
- Kontaktiere den Administrator

## Changelog

### Version 2.0.0 (2025)
- Komplette Neuimplementierung
- Moderne Technologie-Stack
- Responsives Design
- Verbesserte Sicherheit
- Bessere Benutzererfahrung

### Version 1.0.0 (2013)
- Ursprüngliche Version
- HTML 4.01, mysqli, jQuery

## Lizenz

Dieses Projekt ist Teil der MinD-Infrastruktur.

## Credits

- **Original-Entwicklung**: Dr. Hermann Meier (2013)
- **Modernisierung**: 2025
- **Framework**: Vanilla PHP, CSS, JavaScript (kein Framework erforderlich)
