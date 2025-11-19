# Abwesenheitsverwaltung (Vertretungsmanagement)

Tab für Führungsteam zur Verwaltung von Abwesenheiten und Vertretungen

## Überblick

Das Abwesenheitsmodul ermöglicht es Mitgliedern des Führungsteams (Vorstand, GF, Assistenz, Führungsteam), ihre Abwesenheitszeiten einzutragen und optional eine Vertretung zu benennen. Die Abwesenheiten werden automatisch allen Mitgliedern in einem kompakten Widget unterhalb der Tabs angezeigt.

## Features

### Für Führungsteam-Mitglieder
- ✅ **Abwesenheiten eintragen**: Zeitraum (Von-Bis), optionaler Grund, optionale Vertretung
- ✅ **Eigene Abwesenheiten verwalten**: Bearbeiten und Löschen (nur zukünftige)
- ✅ **Übersicht aller Abwesenheiten**: Wer ist wann abwesend
- ✅ **Automatische Validierung**: End-Datum muss >= Start-Datum sein

### Für alle Mitglieder
- ✅ **Widget unterhalb der Tabs**: Zeigt aktuelle und zukünftige Abwesenheiten
- ✅ **Kompakte Darstellung**: Name (DD.MM. - DD.MM.) – Grund | Vertr.: Name
- ✅ **Hervorhebung**: Aktuell abwesende Personen werden fett dargestellt

## Installation

### 1. Datenbank-Schema erstellen

```bash
mysql -u USERNAME -p DATENBANKNAME < schema_absences.sql
```

Oder via init-db.php (empfohlen für Neuinstallationen):
```bash
php init-db.php
```

### 2. Berechtigung prüfen

Das Modul ist nur für Führungsteam-Mitglieder sichtbar. Stellen Sie sicher, dass die Rollen korrekt in der Datenbank gesetzt sind:
- `vorstand` / `Vorstand`
- `gf` / `Geschäftsführung`
- `assistenz` / `Assistenz`
- `fuehrungsteam` / `Führungsteam`

## Verwendung

### Abwesenheit eintragen

1. Klicken Sie auf den Tab **"🏖️ Vertretung"** (nur für Führungsteam sichtbar)
2. Füllen Sie das Formular aus:
   - **Von*** (Pflichtfeld): Startdatum der Abwesenheit
   - **Bis*** (Pflichtfeld): Enddatum der Abwesenheit
   - **Grund** (optional): z.B. "Urlaub", "Dienstreise", "Konferenz"
   - **Vertretung durch** (optional): Wählen Sie ein anderes Führungsteam-Mitglied
3. Klicken Sie auf **"Abwesenheit eintragen"**

### Abwesenheit bearbeiten

1. In der Liste "Meine eingetragenen Abwesenheiten" klicken Sie auf **"✏️ Bearbeiten"**
2. Ändern Sie die Daten
3. Klicken Sie auf **"Änderungen speichern"**

**Hinweis**: Nur zukünftige Abwesenheiten können bearbeitet werden. Vergangene Abwesenheiten werden ausgegraut angezeigt.

### Abwesenheit löschen

1. In der Liste "Meine eingetragenen Abwesenheiten" klicken Sie auf **"🗑️ Löschen"**
2. Bestätigen Sie die Sicherheitsabfrage

## Widget-Anzeige

Das Widget wird automatisch auf allen Seiten unterhalb der Tabs angezeigt, wenn mindestens eine aktuelle oder zukünftige Abwesenheit eingetragen ist.

**Beispiel:**
```
🏖️ Abwesenheiten:
Max Mustermann (20.12. - 27.12.) – Urlaub Vertr.: Anna Schmidt •
Peter Meyer (25.11. - 25.11.) – Konferenz •
Julia Schneider (01.12. - 05.12.)
```

**Hervorhebung:**
- Aktuell abwesende Personen (heute zwischen Start und End) werden **fett** dargestellt
- Widget erscheint nur, wenn Abwesenheiten vorhanden sind (kein leeres Widget)

## Datenbankstruktur

### Tabelle: absences

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `absence_id` | INT | Eindeutige ID (Auto-Increment) |
| `member_id` | INT | Wer ist abwesend (FK zu members) |
| `start_date` | DATE | Beginn der Abwesenheit |
| `end_date` | DATE | Ende der Abwesenheit |
| `reason` | TEXT | Grund (optional) |
| `substitute_member_id` | INT | Vertretung (optional, FK zu members) |
| `created_at` | DATETIME | Zeitpunkt der Erstellung |
| `created_by_member_id` | INT | Wer hat eingetragen (FK zu members) |

**Indizes:**
- `idx_member` (member_id)
- `idx_dates` (start_date, end_date)
- `idx_substitute` (substitute_member_id)
- `idx_created_by` (created_by_member_id)

## Dateien

### Kern-Dateien
- **schema_absences.sql** - Datenbank-Schema
- **tab_absences.php** - Haupt-Interface (Verwaltung)
- **process_absences.php** - CRUD-Handler (Create, Update, Delete)
- **widget_absences.php** - Kompaktes Display-Widget
- **index.php** - Integration (Tab + Widget)

### System-Dateien (aktualisiert)
- **init-db.php** - Enthält absences-Tabelle
- **tools/demo_export.php** - Exportiert absences-Daten
- **tools/demo_import.php** - Importiert absences-Daten

## Berechtigungen

### Wer hat Zugriff?

Nur Mitglieder mit folgenden Rollen:
- **Vorstand** (`vorstand` / `Vorstand`)
- **Geschäftsführung** (`gf` / `Geschäftsführung`)
- **Assistenz** (`assistenz` / `Assistenz`)
- **Führungsteam** (`fuehrungsteam` / `Führungsteam`)

### Was können sie tun?

- ✅ Eigene Abwesenheiten erstellen
- ✅ Eigene Abwesenheiten bearbeiten (nur zukünftige)
- ✅ Eigene Abwesenheiten löschen (nur zukünftige)
- ✅ Alle aktuellen/zukünftigen Abwesenheiten sehen
- ❌ Abwesenheiten anderer Personen bearbeiten/löschen

## Sicherheit

- ✅ **SQL-Injection-Schutz**: Alle Queries mit Prepared Statements
- ✅ **XSS-Schutz**: Alle Ausgaben mit htmlspecialchars()
- ✅ **Berechtigungsprüfung**: Doppelte Prüfung (Tab + Process-Handler)
- ✅ **Eigentümerschaft**: Nur eigene Abwesenheiten bearbeiten/löschen
- ✅ **Input-Validierung**: Server- und Client-seitige Validierung

## Anpassungen

### Berechtigungen erweitern

In `tab_absences.php` und `process_absences.php`, Zeile ~15:

```php
$leadership_roles = ['vorstand', 'gf', 'assistenz', 'fuehrungsteam',
                     'Vorstand', 'Geschäftsführung', 'Assistenz', 'Führungsteam'];
```

Fügen Sie weitere Rollen hinzu, die Zugriff haben sollen.

### Widget-Styling anpassen

In `widget_absences.php` ab Zeile ~50:

```css
.absences-widget {
    background: #f8f9fa;  /* Hintergrundfarbe */
    border: 1px solid #dee2e6;  /* Rahmenfarbe */
    /* ... weitere Styles ... */
}
```

### Anzahl angezeigter Abwesenheiten

In `widget_absences.php`, Zeile ~19:

```php
LIMIT 20  // Erhöhen für mehr Einträge
```

## Troubleshooting

### Tab "Vertretung" wird nicht angezeigt

**Problem**: Der Tab erscheint nicht in der Navigation

**Lösung**:
1. Prüfen Sie Ihre Rolle: `SELECT role FROM members WHERE member_id = ?`
2. Stellen Sie sicher, dass die Rolle einer der erlaubten entspricht
3. Prüfen Sie, ob beide Schreibweisen (groß/klein) berücksichtigt sind

### Widget zeigt keine Abwesenheiten

**Problem**: Widget wird nicht angezeigt oder ist leer

**Lösung**:
1. Prüfen Sie, ob Abwesenheiten eingetragen sind: `SELECT * FROM absences WHERE end_date >= CURDATE()`
2. Stellen Sie sicher, dass `widget_absences.php` in `index.php` inkludiert ist
3. Prüfen Sie PHP error_log auf Fehler

### Fehler beim Speichern

**Problem**: "Fehler beim Speichern" Meldung

**Lösung**:
1. Prüfen Sie Datenbankverbindung
2. Stellen Sie sicher, dass die Tabelle `absences` existiert
3. Prüfen Sie, ob Foreign Keys korrekt sind (member_id muss in members existieren)
4. Schauen Sie in `error_log` für Details

### Vergangene Abwesenheiten löschen

**Problem**: Alte Einträge sammeln sich an

**Lösung**:
Automatisches Löschen via Cronjob:

```bash
# Crontab bearbeiten
crontab -e

# Monatlich alte Abwesenheiten löschen (älter als 6 Monate):
0 3 1 * * mysql -u USER -pPASS DBNAME -e "DELETE FROM absences WHERE end_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
```

## Best Practices

### Für Nutzer

1. **Frühzeitig eintragen**: Tragen Sie Abwesenheiten sobald Sie bekannt sind ein
2. **Vertretung benennen**: Helfen Sie dem Team, indem Sie eine Vertretung angeben
3. **Grund angeben**: Ein kurzer Grund hilft bei der Planung (z.B. "Urlaub", "Konferenz")
4. **Aktuell halten**: Ändern Sie Einträge, wenn sich Pläne ändern

### Für Admins

1. **Regelmäßig prüfen**: Schauen Sie gelegentlich in die "Alle Abwesenheiten"-Liste
2. **Alte Daten löschen**: Richten Sie einen Cronjob ein, um alte Einträge zu entfernen
3. **Rollen pflegen**: Stellen Sie sicher, dass Rollen korrekt vergeben sind
4. **Backup**: Absences-Tabelle in reguläre Backups einbeziehen

## TODO / Zukünftige Features

- [ ] **E-Mail-Benachrichtigung**: Bei neuer Abwesenheit automatisch Team informieren
- [ ] **Kalendersync**: Export als iCal-Datei
- [ ] **Konflikte erkennen**: Warnung wenn zu viele gleichzeitig abwesend
- [ ] **Vertretungsplan**: Übersicht wer wen wann vertritt
- [ ] **Wiederkehrende Abwesenheiten**: Z.B. "Jeden Montag"
- [ ] **Kategorien**: Unterscheidung Urlaub/Krankheit/Dienstreise
- [ ] **Genehmigung**: Workflow mit Bestätigung durch Vorgesetzte
- [ ] **Statistik**: Abwesenheitstage pro Person/Jahr

## Support

Bei Problemen:
1. Prüfen Sie PHP error_log
2. Prüfen Sie Browser-Console auf JavaScript-Fehler
3. Testen Sie die Datenbankverbindung
4. Stellen Sie sicher, dass `schema_absences.sql` ausgeführt wurde

## Lizenz

Teil der Sitzungsverwaltung - Internes Tool für Mensa Deutschland e.V.
