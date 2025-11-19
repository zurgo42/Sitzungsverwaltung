# Testfragen-Modul - Modernisierte Version

## Übersicht

Das Testfragen-Modul ermöglicht es Mensa-Mitgliedern, IQ-Test-Aufgaben einzureichen für die Entwicklung eines neuen Spieltests.

**Modernisiert von:** `dokumente.php` (2013) → `tab_testfragen.php` (2025)

## Was wurde modernisiert?

### Von Alt (2013):
- ❌ Unsichere mysqli-Queries mit String-Konkatenation
- ❌ Kein CSRF-Schutz
- ❌ Unsicheres File-Upload
- ❌ HTML und PHP vermischt
- ❌ Keine Validierung
- ❌ Globals everywhere

### Zu Neu (2025):
- ✅ PDO mit Prepared Statements (SQL-Injection-sicher)
- ✅ Sicheres File-Upload-Handling (MIME-Type-Validierung)
- ✅ Moderne HTML5-Struktur
- ✅ Client- und Server-seitige Validierung
- ✅ Integration ins bestehende Tab-System
- ✅ Nutzt bestehende Infrastruktur (functions.php, style.css, $pdo)
- ✅ Redaktionsansicht für Moderation

## Installation

### 1. Datenbank-Tabellen erstellen

```bash
mysql -u username -p datenbank < schema_testfragen.sql
```

Oder per phpMyAdmin: SQL-Skript `schema_testfragen.sql` ausführen.

### 2. Upload-Verzeichnis erstellen

```bash
mkdir -p uploads/testfragen
chmod 755 uploads/testfragen
```

Das Skript erstellt das Verzeichnis automatisch, aber stelle sicher, dass es beschreibbar ist.

### 3. CSS einbinden

Füge in deiner Haupt-HTML-Datei (z.B. `index.php`) hinzu:

```html
<link rel="stylesheet" href="testfragen_styles.css">
```

Oder füge den Inhalt von `testfragen_styles.css` zu deiner bestehenden `style.css` hinzu.

### 4. Tab registrieren

In deiner Navigation (z.B. `index.php`) füge den Tab hinzu:

```php
case 'testfragen':
    require 'tab_testfragen.php';
    break;
```

## Verwendung

### Für Mitglieder

**Testfrage einreichen:**
1. Rufe auf: `index.php?tab=testfragen`
2. Wähle: Textaufgabe oder Figurale Aufgabe
3. Fülle das Formular aus
4. Bei figuralen Aufgaben: Bilder hochladen
5. Absenden

**Kommentar hinterlassen:**
- Nach der Einreichung kann ein optionaler Kommentar abgegeben werden
- Wird separat gespeichert (nicht mit der Frage verknüpft)

### Für Redaktion/Admins

**Einreichungen sichten:**
```
index.php?tab=testfragen&redaktion=1
```

Zeigt alle eingereichten Fragen mit:
- Aufgabenstellung
- Alle Antworten
- Richtige Antwort (markiert)
- Regelbesch

reibung
- Metadaten (Inhalt, Schwierigkeit)
- Hochgeladene Bilder

## Dateistruktur

```
Sitzungsverwaltung/
├── tab_testfragen.php           # Haupt-Tab-Datei
├── templates/
│   ├── testfragen_form.php      # Formular für Einreichung
│   └── testfragen_redaktion.php # Redaktionsansicht
├── testfragen_styles.css        # Zusätzliche Styles
├── schema_testfragen.sql        # Datenbank-Schema
├── uploads/
│   └── testfragen/              # Hochgeladene Bilder
└── TESTFRAGEN_README.md         # Diese Datei
```

## Datenbank-Schema

### Tabelle: `testfragen`

Speichert die eingereichten Aufgaben.

**Wichtige Felder:**
- `member_id` - Wer hat es eingereicht
- `aufgabe` - Aufgabenstellung
- `antwort1-5` - Fünf Antwortmöglichkeiten
- `richtig` - Nummer der richtigen Antwort (1-5)
- `regel` - Beschreibung der Regel
- `inhalt` - Hauptinhaltsbereich (1=verbal, 2=numerisch, 3=figural, 4=anderes)
- `schwer` - Schwierigkeit (1-5)
- `is_figural` - Ob es eine figurale Aufgabe ist
- `file0-5` - Dateinamen der hochgeladenen Bilder
- `reviewed`, `approved` - Für Redaktions-Workflow (zukünftig)

### Tabelle: `testkommentar`

Speichert allgemeine Kommentare.

## Sicherheit

### Implementierte Schutzmaßnahmen:

1. **SQL-Injection-Schutz**
   - PDO mit Prepared Statements
   - Keine String-Konkatenation in Queries

2. **File-Upload-Sicherheit**
   - MIME-Type-Validierung
   - Nur erlaubte Bildformate (JPG, PNG, GIF, WEBP)
   - Sichere Dateinamen (kein User-Input)
   - Uploads außerhalb des Web-Root (empfohlen)

3. **XSS-Schutz**
   - Alle Ausgaben werden escaped (`htmlspecialchars()`)
   - Templates nutzen PHP-Kurztags mit Escaping

4. **Input-Validierung**
   - Server-seitig: Pflichtfelder, Datentypen, Bereiche
   - Client-seitig: HTML5-Validierung, JavaScript
   - Whitelist-Validierung für Enum-Werte

5. **Session-Sicherheit**
   - `require_login()` prüft Authentifizierung
   - Nutzung der bestehenden Session-Verwaltung

## Bekannte Einschränkungen

- **CSRF-Schutz:** Noch nicht implementiert (TODO)
- **File-Size-Limits:** Basiert auf PHP-Einstellungen (empfohlen: max 5 MB)
- **Rollen-System:** Hardcodiert für Admin (MNr 0495018) - sollte auf Rollen-System umgestellt werden

## Migration vom alten System

Falls du Daten vom alten `dokumente.php` migrieren willst:

```sql
-- Prüfe, ob Spalten fehlen
ALTER TABLE testfragen ADD COLUMN IF NOT EXISTS member_id INT(11) AFTER id;
ALTER TABLE testfragen ADD COLUMN IF NOT EXISTS is_figural TINYINT(1) DEFAULT 0;

-- Charset konvertieren
ALTER TABLE testfragen CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Erweiterungsmöglichkeiten

### Redaktions-Workflow
- Status-Änderungen (reviewed, approved)
- Kommentare der Redaktion
- Benachrichtigungen

### Export/Import
- Fragen exportieren (CSV, JSON)
- Batch-Import von Fragen

### Statistiken
- Dashboard mit Einreichungs-Statistiken
- Schwierigkeitsverteilung
- Inhaltsbereichs-Verteilung

### Testing-Phase
- Mitglieder können Fragen lösen
- Statistiken sammeln
- Schwierigkeitsanalyse

## Troubleshooting

### Problem: Upload schlägt fehl
**Lösung:**
```bash
# Prüfe Verzeichnis-Berechtigungen
ls -la uploads/testfragen

# Setze Berechtigungen
chmod 755 uploads/testfragen

# Prüfe PHP-Limits
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

### Problem: Bilder werden nicht angezeigt
**Lösung:**
- Prüfe Pfad in `tab_testfragen.php` (Zeile mit `$uploadDir`)
- Prüfe Pfad in Templates (Zeile mit `src="uploads/testfragen/..."`)
- Stelle sicher, dass Upload-Verzeichnis vom Webserver aus erreichbar ist

### Problem: "Keine Berechtigung"
**Lösung:**
- Stelle sicher, dass Session aktiv ist
- Prüfe `$_SESSION['member_id']`
- Prüfe `require_login()` in `functions.php`

## Performance-Tipps

1. **Bilder optimieren**
   - Automatische Bildkompression nach Upload
   - Thumbnails generieren für Redaktionsansicht

2. **Datenbank-Indizes**
   - Sind bereits im Schema enthalten
   - Bei vielen Fragen: Pagination implementieren

3. **Caching**
   - Redaktionsansicht cachen (nur bei Änderungen aktualisieren)

## Support

Bei Fragen oder Problemen:
1. Prüfe diese README
2. Schaue in die Code-Kommentare
3. Kontaktiere den Entwickler

## Changelog

### Version 2.0.0 (2025)
- Komplette Neuimplementierung
- PDO statt mysqli
- Sichere File-Uploads
- Integration ins Tab-System
- Moderne HTML5/CSS
- Redaktionsansicht

### Version 1.0.0 (2013)
- Ursprüngliche Version als `dokumente.php`

## Lizenz

Teil der Mensa-Sitzungsverwaltung
