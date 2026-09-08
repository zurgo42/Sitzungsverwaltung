# Meinungsbild-Tool

Vollständiges Umfrage/Meinungsbild-System für die Sitzungsverwaltung

## Überblick

Das Meinungsbild-Tool ermöglicht es, schnell und einfach Umfragen zu erstellen und auszuwerten. Es unterstützt verschiedene Zielgruppen, vorgefertigte Antwort-Templates und grafische Auswertungen.

## Features

### Zielgruppen
1. **Individuell (Link)**: Generiert einen eindeutigen Link, den Sie an beliebige Personen weitergeben können
2. **Meeting-Teilnehmer (Liste)**: Umfrage wird nur den Teilnehmern eines bestimmten Meetings angezeigt
3. **Öffentlich**: Jeder Besucher der Seite kann teilnehmen (auch ohne Login)

### Antwort-Templates
13 vorgefertigte Antwort-Sets:
1. **Ja/Nein/Enthaltung** - Klassische 3er-Abstimmung
2. **Passt-Skala** - 5-stufig: Passt sehr gut bis passt gar nicht
3. **Dafür/Dagegen** - 5-stufig: Unbedingt dafür bis unbedingt dagegen
4. **Gefällt mir** - 5-stufig: Gefällt mir sehr gut bis überhaupt nicht
5. **Skala 1-9** - Numerische Bewertungsskala
6. **Dringlichkeit** - Von "Sofort!" bis "Nicht machen"
7. **Wichtigkeit** - Von "unabdingbar" bis "Auf keinen Fall"
8. **Wünsche** - Von "Sehr!" bis "Auf keinen Fall"
9. **Häufigkeit** - Von "immer" bis "nie"
10. **Priorität** - Von "Absolutes Muss" bis "Auf keinen Fall"
11. **Frei** - Leeres Template für eigene Optionen
12. **Nützlichkeit** - Spezifisch für Feature-Bewertungen
13. **Bewertung** - Einfach: langweilig, Zeitvertreib, spannend

### Einstellungen
- **Mehrfachantworten**: Teilnehmer können mehrere Optionen auswählen
- **Anonym/Offen**: Namen der Teilnehmer anzeigen oder verbergen
- **Individuelle Anonymität**: Teilnehmer können selbst wählen, anonym zu bleiben
- **Laufzeit**: Frei wählbar (Standard: 14 Tage)
- **Zwischenergebnisse**: Ab wann Ergebnisse sichtbar werden (Standard: 7 Tage)
- **Löschdatum**: Pflichtangabe beim Erstellen (Datumsfeld, Standard: heute +90 Tage); Umfragen werden automatisch gelöscht wenn das Datum überschritten ist (Lazy-Deletion)

### Listenansicht
- **Für mich ausblenden**: Jeder Nutzer kann Umfragen individuell aus seiner Liste ausblenden – die Umfrage bleibt für andere sichtbar
- Ausgeblendete Umfragen erscheinen zugeklappt unter "Für mich ausgeblendet" und sind über "Ergebnisse" weiterhin abrufbar
- **Wieder einblenden**: Button zum Rückgängigmachen der Ausblendung

### Berechtigungen
- **Ersteller**:
  - Kann Umfrage beenden und löschen
  - Sieht immer alle Ergebnisse
  - Kann eigene Antwort editieren (solange nur 1 Antwort vorhanden)
- **Admins**:
  - Sehen alle Ergebnisse
  - Können jede Umfrage beenden/löschen
- **Teilnehmer**:
  - Sehen Ergebnisse nach Ablauf der Frist oder nach eigener Teilnahme
  - Können Freitext-Kommentare hinzufügen

### Auswertung
- **Grafische Balkendiagramme**: Prozentuale Verteilung der Antworten
- **Zahlen**: Absolute Anzahl und Prozentsätze
- **Einzelne Antworten**: Ersteller/Admins sehen alle Antworten mit Namen und Kommentaren
- **Export**: (noch zu implementieren)

## Installation

### 1. Datenbank-Migration

Führen Sie die beiden Migrations-Dateien aus:

```bash
mysql -u USERNAME -p DATENBANKNAME < migrations/create_opinion_polls.sql
mysql -u USERNAME -p DATENBANKNAME < migrations/insert_opinion_templates.sql
```

Oder über phpMyAdmin:
- SQL-Tab öffnen
- Inhalt von `create_opinion_polls.sql` kopieren und ausführen
- Inhalt von `insert_opinion_templates.sql` kopieren und ausführen

### 2. Cronjob einrichten (optional, für Auto-Löschung)

```bash
# Crontab bearbeiten
crontab -e

# Zeile hinzufügen (täglich um 2:00 Uhr):
0 2 * * * /usr/bin/php /pfad/zu/cron_delete_expired_opinions.php
```

### 3. Integration prüfen

Das Tool ist bereits in `index.php` integriert. Nach der Installation ist es unter dem Tab "📊 Meinungsbild" verfügbar.

## Verwendung

### Umfrage erstellen

1. Navigieren Sie zum Tab "Meinungsbild"
2. Klicken Sie auf "+ Neues Meinungsbild erstellen"
3. **Frage formulieren**: Geben Sie Ihre Frage ein
4. **Zielgruppe wählen**:
   - Individual: Für Link-Versand
   - Liste: Wählen Sie ein Meeting aus
   - Öffentlich: Jeder darf teilnehmen
5. **Antwortmöglichkeiten**:
   - Wählen Sie ein Template ODER
   - Geben Sie bis zu 10 eigene Optionen ein
6. **Einstellungen**:
   - Mehrfachantworten erlauben?
   - Anonym oder mit Namen?
   - Laufzeit festlegen
   - Zwischenergebnisse-Zeitpunkt
   - Auto-Löschung
7. **E-Mail (optional)**: Link per E-Mail versenden
8. Klicken Sie auf "Meinungsbild erstellen"

### Link teilen (bei Individual-Typ)

Nach dem Erstellen wird ein eindeutiger Zugangslink generiert:
```
https://ihre-domain.de/index.php?tab=opinion&view=participate&token=ABC123...
```

Diesen Link können Sie per E-Mail, Chat oder andere Kanäle teilen.

### Teilnehmen

1. Öffnen Sie die Umfrage (über Link oder Übersicht)
2. Wählen Sie Ihre Antwort(en)
3. Optional: Fügen Sie einen Kommentar hinzu
4. Optional: Wählen Sie "anonym bleiben" (wenn Umfrage nicht anonym ist)
5. Klicken Sie auf "Antwort absenden"

### Ergebnisse ansehen

**Als Ersteller/Admin**:
- Immer verfügbar über "Ergebnisse anzeigen"

**Als Teilnehmer**:
- Nach eigener Teilnahme
- Nach Ablauf der Frist für Zwischenergebnisse
- Nach Ende der Umfrage

Die Ergebnisse werden als Balkendiagramme dargestellt mit absoluten Zahlen und Prozenten.

### Umfrage beenden/löschen

**Beenden** (nur Ersteller/Admin):
- Klicken Sie in den Details auf "⏸️ Beenden"
- Die Umfrage wird sofort beendet, aber nicht gelöscht
- Ergebnisse bleiben verfügbar

**Löschen** (nur Ersteller/Admin):
- Klicken Sie in den Details auf "🗑️ Löschen"
- Soft-Delete: Umfrage wird ausgeblendet, Daten bleiben in DB
- Bestätigung erforderlich

## Datenbankstruktur

### opinion_polls
Haupttabelle für Meinungsbilder
- `poll_id`: Eindeutige ID
- `title`: Die gestellte Frage
- `target_type`: individual, list, public
- `access_token`: Eindeutiger Link (bei individual)
- `template_id`: Gewähltes Template
- `allow_multiple_answers`: Mehrfachantworten erlaubt?
- `is_anonymous`: Anonyme Umfrage?
- `ends_at`, `delete_at`: Automatisch berechnet

### opinion_poll_options
Antwortoptionen pro Umfrage
- `option_id`: Eindeutige ID
- `poll_id`: Zugehörige Umfrage
- `option_text`: Text der Option
- `sort_order`: Reihenfolge

### opinion_responses
Antworten der Teilnehmer
- `response_id`: Eindeutige ID
- `poll_id`: Zugehörige Umfrage
- `member_id`: User (NULL bei public/anonymous)
- `session_token`: Für anonyme Teilnahme
- `free_text`: Kommentar
- `force_anonymous`: Teilnehmer will anonym bleiben

### opinion_response_options
Gewählte Optionen (M:N)
- Verknüpfung zwischen responses und options
- Ermöglicht Mehrfachantworten

### svopinion_user_hidden
Pro-User-Ausblendungen in der Listenansicht
- `poll_id`: Ausgeblendete Umfrage
- `member_id`: Nutzer, der ausgeblendet hat
- `hidden_at`: Zeitstempel
- PRIMARY KEY (poll_id, member_id) – verhindert Duplikate

## Standalone-Nutzung (Adapter-Pattern)

Das Meinungsbild-Tool ist so konzipiert, dass es als Standalone-Modul in andere Projekte integriert werden kann.

### Voraussetzungen

1. **Datenbankstruktur**: Die opinion_* Tabellen müssen existieren
2. **User-Tabelle**: Eine Tabelle mit Benutzern (kompatibel über Adapter)
3. **Session-Management**: PHP-Sessions müssen funktionieren

### Integration

**Minimale Integration:**

```php
<?php
// config.php anpassen oder eigene erstellen
require_once 'config.php';
require_once 'opinion_functions.php';

// Session starten
session_start();

// Optional: Eigenen User laden
$current_user = get_your_user(); // Ihre User-Funktion

// Meinungsbild-Tab einbinden
include 'tab_opinion.php';
?>
```

**Mit eigenem Adapter:**

Erstellen Sie eine `opinion_adapter.php`:

```php
<?php
/**
 * Adapter für Ihr System
 */

// Ihre User-Funktion wrappen
function get_member_by_id($pdo, $member_id) {
    // Ihre Logik zum Laden eines Users
    // MUSS zurückgeben: ['member_id', 'first_name', 'last_name', 'email', 'role']
}

// Optional: Meetings laden
function get_meetings_for_opinion($pdo) {
    // Ihre Logik zum Laden von Meetings
    return $pdo->query("SELECT * FROM your_meetings_table")->fetchAll();
}
?>
```

## Sicherheit

- **SQL-Injection**: Alle Queries verwenden Prepared Statements
- **XSS**: Alle Ausgaben werden mit htmlspecialchars() escaped
- **CSRF**: POST-Requests erforderlich für alle Aktionen
- **Zugriffskontrolle**: Berechtigungen werden vor jeder Aktion geprüft
- **Session-Sicherheit**: Session-Tokens für anonyme Teilnahme

## Performance

- **Indizes**: Auf allen wichtigen Spalten (poll_id, member_id, status, etc.)
- **Lazy Loading**: Ergebnisse werden nur bei Bedarf berechnet
- **Caching**: (noch zu implementieren)

## TODO / Zukünftige Features

- [ ] E-Mail-Benachrichtigungen implementieren
- [ ] Export-Funktion (CSV, PDF)
- [ ] Grafik-Bibliothek für schönere Diagramme
- [ ] Zeitliche Auswertung (Wann haben User geantwortet?)
- [ ] Mehrsprachigkeit
- [ ] Mobile-Optimierung
- [ ] QR-Code für schnellen Zugriff

## Support

Bei Problemen:
1. Prüfen Sie die PHP error_log
2. Prüfen Sie die Cronjob-Logs
3. Prüfen Sie die Datenbankverbindung
4. Stellen Sie sicher, dass alle Migrationen ausgeführt wurden

## Lizenz

Teil der Sitzungsverwaltung - Internes Tool
