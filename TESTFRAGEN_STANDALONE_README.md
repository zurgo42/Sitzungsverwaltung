# IQ-Test Testfragen-Modul - Standalone-Version

Standalone-fähiges Modul zum Sammeln von IQ-Test-Aufgaben von Mitgliedern

## Überblick

Das Testfragen-Modul ermöglicht es Mitgliedern, eigene IQ-Test-Aufgaben einzureichen. Diese können sowohl textbasiert als auch figural (bildbasiert) sein. Eine Redaktionsansicht ermöglicht es Admins, alle Einreichungen zu sichten und zu bewerten.

## Features

### Für Mitglieder
- **Zwei Aufgabentypen**:
  - **Textlich**: Frage + 5 Textantworten
  - **Figural**: Frage + 5 Bildantworten oder 1 Komplett-Bild
- **Validierung**: Pflichtfelder und Eingabeprüfung
- **Kategorisierung**:
  - Inhaltsbereich (Verbal, Numerisch, Figural, Anderes)
  - Schwierigkeitseinschätzung (5-stufig)
  - Optionaler sekundärer Inhaltsbereich
- **Kommentarfunktion**: Allgemeine Anmerkungen nach Einreichung
- **Anonymität**: Nur MNr wird gespeichert, Name nicht in Aufgabendetails

### Für Redaktion/Admins
- **Übersicht aller Einreichungen**: Sortiert nach Datum
- **Detailansicht pro Frage**:
  - Aufgabenstellung
  - Alle 5 Antworten (mit Markierung der richtigen)
  - Regel/Lösung
  - Inhaltsbereich und Schwierigkeit
  - Einreicher-Name und Datum
- **Bildvorschau**: Inline-Anzeige hochgeladener Bilder
- **Filter und Export**: (noch zu implementieren)

### Sicherheit
- ✅ **PDO mit Prepared Statements** - SQL-Injection-sicher
- ✅ **MIME-Type-Validierung** - Nur echte Bilddateien
- ✅ **Sichere Dateinamen** - Automatisch generiert mit Zeitstempel
- ✅ **XSS-Schutz** - htmlspecialchars() für alle Ausgaben
- ✅ **Input-Validierung** - Client & Server
- ✅ **Upload-Limit** - Max. 6 Dateien pro Einreichung

## Installation

### 1. Datenbank-Schema erstellen

Führen Sie die SQL-Datei aus:

```bash
mysql -u USERNAME -p DATENBANKNAME < schema_testfragen.sql
```

Oder über phpMyAdmin:
- SQL-Tab öffnen
- Inhalt von `schema_testfragen.sql` kopieren und ausführen

Das Script erstellt folgende Tabellen:
- `testfragen` - Haupttabelle für Aufgaben
- `testkommentar` - Allgemeine Kommentare

### 2. Upload-Verzeichnis

Das Verzeichnis `uploads/testfragen/` wird automatisch erstellt, wenn es nicht existiert. Stellen Sie sicher, dass der Webserver Schreibrechte hat:

```bash
mkdir -p uploads/testfragen
chmod 755 uploads/testfragen
```

### 3. Integration prüfen

Das Tool ist bereits in `index.php` integriert. Nach der Installation ist es unter dem Tab "Testfragen" verfügbar.

## Verwendung

### In der Sitzungsverwaltung (Standard)

Automatisch verfügbar nach Installation. Keine weitere Konfiguration nötig.

### Standalone-Integration in andere Projekte

**Minimale Integration:**

```php
<?php
// Datenbankverbindung
$pdo = new PDO('mysql:host=localhost;dbname=yourdb', 'user', 'pass');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Session starten
session_start();

// Mitgliedsnummer setzen (aus Ihrer Session/Login-Logik)
$MNr = '1234567'; // Ihre Mitgliedsnummer

// Modul einbinden
require_once 'testfragen_standalone.php';
?>
```

**Mit eigenem User-System:**

Das Modul erkennt automatisch, ob es in der Sitzungsverwaltung läuft oder standalone. Im Standalone-Modus:

1. Nutzt es die `berechtigte` Tabelle
2. Mappt Spalten automatisch auf das Standard-Format
3. Funktioniert mit beiden Tabellennamen (`members` oder `berechtigte`)

**Voraussetzungen:**
- `$pdo`: PDO-Datenbankverbindung
- `$MNr`: Mitgliedsnummer des eingeloggten Users
- Tabelle `berechtigte` mit Spalten: `ID`, `MNr`, `Vorname`, `Name`, `eMail`, `Funktion`, `aktiv`

**Berechtigungen:**

Standardmäßig ist nur MNr `0495018` als Admin konfiguriert. Ändern Sie dies in `testfragen_standalone.php`:

```php
// Zeile ~102 anpassen
$isAdmin = ($current_user['membership_number'] == 'IHRE_MNR') || $current_user['is_admin'];
```

## Aufgabe einreichen

### Textliche Aufgabe

1. **Art wählen**: "Textliche Aufgabe" auswählen
2. **Aufgabenstellung**: Frage formulieren
3. **5 Antworten**: Alle Antwortmöglichkeiten eingeben
4. **Richtige Antwort**: Auswählen (1-5)
5. **Regel**: Lösungsweg beschreiben
6. **Inhaltsbereich**: Kategorie wählen
7. **Schwierigkeit**: Einschätzen (1-5)
8. **Absenden**

**Beispiel:**
```
Aufgabe: Welche Zahl kommt als nächstes? 2, 4, 8, 16, ...
Antwort 1: 24
Antwort 2: 20
Antwort 3: 32  ← RICHTIG
Antwort 4: 64
Antwort 5: 18
Regel: Jede Zahl wird verdoppelt
Inhaltsbereich: Numerisch/Rechnerisch
Schwierigkeit: Eher niedrig
```

### Figurale Aufgabe

1. **Art wählen**: "Figurale Aufgabe (mit Bildern)" auswählen
2. **Aufgabenstellung**: Beschreibung eingeben
3. **Bilder hochladen**:
   - **Option A**: 1 Komplett-Bild mit allen Antworten
   - **Option B**: 5 Einzelbilder (je eines pro Antwort)
4. **Richtige Antwort**: Auswählen (1-5)
5. **Regel**: Lösungsweg beschreiben
6. **Inhaltsbereich**: "Figural/Räumlich-Visuell" wählen
7. **Schwierigkeit**: Einschätzen (1-5)
8. **Absenden**

**Unterstützte Formate:** JPG, PNG, GIF, WEBP

## Redaktionsansicht

Als Admin können Sie alle Einreichungen sichten:

1. URL aufrufen: `?tab=testfragen&redaktion=1`
2. Übersicht aller Fragen
3. Filterbar nach:
   - Typ (Text/Figural)
   - Schwierigkeit
   - Inhaltsbereich
   - Einreicher

**Anzeige pro Frage:**
- Frage-ID und Typ-Badge
- Einreicher-Name und Datum
- Aufgabenstellung (mit Bild, falls vorhanden)
- Alle 5 Antworten im Grid (richtige grün hervorgehoben)
- Regel/Lösung
- Inhaltsbereich und Schwierigkeit

## Datenbankstruktur

### testfragen

Haupttabelle für alle eingereichten Aufgaben:

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `id` | INT | Eindeutige ID (Auto-Increment) |
| `member_id` | INT | Referenz auf User (berechtigte.ID) |
| `aufgabe` | TEXT | Aufgabenstellung |
| `antwort1-5` | TEXT | Antwortmöglichkeiten (bei Text) |
| `richtig` | TINYINT | Nummer der richtigen Antwort (1-5) |
| `regel` | TEXT | Beschreibung der Lösung |
| `inhalt` | TINYINT | Hauptinhaltsbereich (1-4) |
| `tinhalt` | VARCHAR | Text bei "Anderes" |
| `inhaltw` | TINYINT | Sekundärer Inhaltsbereich (0-4) |
| `tinhaltw` | VARCHAR | Text bei "Anderes" (sekundär) |
| `schwer` | TINYINT | Schwierigkeit (1-5) |
| `is_figural` | TINYINT | 0=Text, 1=Figural |
| `file0-5` | VARCHAR | Dateinamen der Uploads |
| `datum` | DATETIME | Einreichungsdatum |

**Inhaltsbereiche:**
1. Verbal/Sprachlich
2. Numerisch/Rechnerisch
3. Figural/Räumlich-Visuell
4. Anderes (mit Freitext)

**Schwierigkeitsgrade:**
1. Sehr niedrig
2. Eher niedrig
3. Mittel
4. Eher hoch
5. Sehr hoch

### testkommentar

Allgemeine Kommentare der Teilnehmer:

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `id` | INT | Eindeutige ID |
| `member_id` | INT | Referenz auf User |
| `kommentar` | TEXT | Kommentartext |
| `datum` | DATETIME | Datum |
| `todo` | VARCHAR | Status (offen/bearbeitet/erledigt) |

## Dateiverwaltung

### Upload-Logik

```
uploads/testfragen/
├── frage_1_file0_1700000000.jpg    # Komplett-Bild
├── frage_2_file1_1700000001.png    # Einzelbild Antwort 1
├── frage_2_file2_1700000002.png    # Einzelbild Antwort 2
└── ...
```

**Namensschema:** `frage_{ID}_file{0-5}_{timestamp}.{ext}`

### Sicherheit

- MIME-Type-Prüfung (nicht nur Extension)
- Automatische Umbenennung (Original-Name wird verworfen)
- Upload-Verzeichnis außerhalb von Document-Root möglich (empfohlen)

**Empfohlene .htaccess für uploads/testfragen/:**

```apache
# Nur Bildausgabe erlauben, kein PHP
<FilesMatch "\.(php|phtml|php3|php4|php5|phps)$">
    Require all denied
</FilesMatch>

# Nur Bilder erlauben
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Require all granted
</FilesMatch>
```

## Anpassungen

### Admin-Berechtigung ändern

In `testfragen_standalone.php` Zeile ~102:

```php
// Mehrere MNr als Admins
$isAdmin = in_array($current_user['membership_number'], ['0495018', '1234567']);

// Oder basierend auf Rolle
$isAdmin = in_array($current_user['role'], ['gf', 'assistenz']);
```

### Redaktions-Rolle einführen

Ersetzen Sie Zeile ~103:

```php
// Neue Rolle "redaktion"
$isRedaktion = isset($_GET['redaktion']) &&
               (in_array($current_user['role'], ['gf', 'assistenz', 'redaktion']));
```

### Styling anpassen

Für die Sitzungsverwaltung: `testfragen_styles.css` bearbeiten

Für Standalone: Inline-Styles in `testfragen_standalone.php` (ab Zeile ~314)

### Datei-Limits ändern

In `testfragen_standalone.php`:

```php
// Max. Upload-Größe in php.ini:
upload_max_filesize = 10M
post_max_size = 12M

// Anzahl Dateien: Aktuell 0-5 (6 Dateien)
// Um zu ändern, Loop in Zeile ~237 anpassen
```

## Statistiken und Auswertung

### Anzahl Einreichungen

```php
$stmt = $pdo->query("SELECT COUNT(*) FROM testfragen");
$count = $stmt->fetchColumn();
echo "Anzahl Einreichungen: $count";
```

### Nach Typ

```php
$stmt = $pdo->query("
    SELECT
        is_figural,
        COUNT(*) as anzahl
    FROM testfragen
    GROUP BY is_figural
");
```

### Nach Schwierigkeit

```php
$stmt = $pdo->query("
    SELECT
        schwer,
        COUNT(*) as anzahl
    FROM testfragen
    GROUP BY schwer
    ORDER BY schwer
");
```

### Top-Einreicher

```php
$stmt = $pdo->query("
    SELECT
        b.Vorname,
        b.Name,
        COUNT(*) as anzahl
    FROM testfragen t
    JOIN berechtigte b ON t.member_id = b.ID
    GROUP BY t.member_id
    ORDER BY anzahl DESC
    LIMIT 10
");
```

## TODO / Zukünftige Features

- [ ] **CSRF-Protection**: Token-basierte Absicherung aller POST-Requests
- [ ] **Rollen-System**: Redaktions-Rolle in DB statt hardcoded
- [ ] **Batch-Operationen**: Mehrere Fragen gleichzeitig bearbeiten/exportieren
- [ ] **Export-Funktion**: CSV/JSON/PDF-Export aller Fragen
- [ ] **Statistik-Dashboard**: Grafische Auswertung
- [ ] **Bewertungs-System**: Redaktion kann Fragen bewerten/kommentieren
- [ ] **Status-Workflow**: offen → geprüft → freigegeben → verwendet
- [ ] **Test-Preview**: Fragen in Test-Umgebung anzeigen
- [ ] **Duplikatserkennung**: Ähnliche Fragen identifizieren
- [ ] **Bildbearbeitung**: Crop/Resize beim Upload
- [ ] **LaTeX-Support**: Mathematische Formeln

## Best Practices

### Für Teilnehmer

- **Eindeutige Aufgaben**: Vermeiden Sie mehrdeutige Fragestellungen
- **Klare Antworten**: Alle 5 Antworten sollten plausibel sein
- **Gute Bilder**: Bei figuralen Aufgaben hochauflösende Bilder verwenden
- **Regel beschreiben**: Lösungsweg verständlich erklären
- **Schwierigkeit realistisch**: Selbst lösen und Zeit messen

### Für Redaktion

- **Regelmäßig sichten**: Zeitnah Feedback geben
- **Konstruktive Kommentare**: TODO-Feld in testkommentar nutzen
- **Kategorisierung prüfen**: Inhaltsbereich korrekt?
- **Schwierigkeit validieren**: Mit Testern verifizieren
- **Duplikate mergen**: Ähnliche Aufgaben zusammenfassen

## Troubleshooting

### Upload schlägt fehl

**Problem:** "Nur Bilder erlaubt" trotz Bild-Upload

**Lösung:**
1. Prüfen Sie PHP-Extension `fileinfo`: `php -m | grep fileinfo`
2. Installieren falls fehlt: `apt-get install php-fileinfo` (Ubuntu/Debian)
3. Server neu starten

### Bilder werden nicht angezeigt

**Problem:** 404 bei Bildaufruf

**Lösung:**
1. Prüfen Sie Schreibrechte: `ls -la uploads/testfragen/`
2. Setzen Sie korrekte Rechte: `chmod 755 uploads/testfragen/`
3. Prüfen Sie .htaccess im Upload-Verzeichnis

### Redaktionsansicht leer

**Problem:** Keine Einreichungen sichtbar trotz Daten in DB

**Lösung:**
1. SQL-Join prüfen: Zeile ~12 in `templates/testfragen_redaktion.php`
2. Tabellennamen anpassen falls nötig (`members` vs `berechtigte`)
3. Error-Log prüfen: `tail -f /var/log/php_errors.log`

### "FEHLER: $pdo nicht definiert"

**Problem:** Bei Standalone-Integration

**Lösung:**
```php
// VOR require_once 'testfragen_standalone.php':
$pdo = new PDO(...);
$MNr = '1234567';
session_start();
```

## Support

Bei Problemen:
1. Prüfen Sie die PHP error_log
2. Prüfen Sie Upload-Verzeichnis-Rechte
3. Prüfen Sie Datenbankverbindung
4. Stellen Sie sicher, dass `schema_testfragen.sql` ausgeführt wurde

## Lizenz

Teil der Sitzungsverwaltung - Internes Tool für Mensa Deutschland e.V.
