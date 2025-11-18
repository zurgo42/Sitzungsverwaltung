# Entwickler-Dokumentation - Sitzungsverwaltung

Technische Dokumentation für Entwickler, die das System verstehen, warten oder erweitern möchten.

## Architektur-Überblick

### Technologie-Stack

- **Backend:** PHP 7.4+ (prozedural)
- **Datenbank:** MySQL 5.7+ / MariaDB 10.2+
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Session-Management:** PHP Sessions
- **E-Mail:** PHP `mail()`, PHPMailer (optional), Mail Queue

### Architektur-Muster

- **MVC-Light:** Lose Trennung von Logik und Präsentation
- **Prozedural:** Keine OOP, Funktionen in separaten Dateien
- **Multi-Page Application:** Klassische Webseiten-Struktur
- **Session-based Authentication:** PHP Sessions für Login

## Verzeichnisstruktur

```
/
├── config.php                  # Datenbank & Konfiguration (nicht im Repo)
├── config.example.php          # Konfigurations-Vorlage
├── config_adapter.php          # Adapter für verschiedene DB-Schemas
├── init-db.php                 # Datenbank-Initialisierung + Default-Admin
├── index.php                   # Haupt-Entry-Point / Routing
├── login.php                   # Login-Seite
├── logout.php                  # Logout-Handler
│
├── functions.php               # Core-Hilfsfunktionen
├── member_functions.php        # Mitglieder-Management
├── mail_functions.php          # E-Mail-Versand
├── opinion_functions.php       # Meinungsbild-Tool Helpers
│
├── process_meeting.php         # Meeting-Actions Backend
├── process_termine.php         # Terminplanung Backend
├── process_opinion.php         # Meinungsbild Backend
├── process_admin.php           # Admin-Aktionen Backend
│
├── tab_dashboard.php           # Dashboard-View
├── tab_meetings.php            # Meetings-Übersicht
├── tab_termine.php             # Terminplanung-UI
├── tab_opinion.php             # Meinungsbild-UI
├── tab_members.php             # Mitglieder-Verwaltung
├── tab_todos.php               # TODO-Liste
├── tab_admin_log.php           # Admin-Log
│
├── opinion_views/              # Unterviews für Meinungsbild-Tool
│   ├── list.php                # Übersicht Meinungsbilder
│   ├── create.php              # Meinungsbild erstellen
│   ├── detail.php              # Details anzeigen
│   ├── participate.php         # Teilnahme-Formular
│   └── results.php             # Ergebnisse anzeigen
│
├── cron_poll_reminders.php     # Cronjob: Poll-Erinnerungen
├── cron_process_mail_queue.php # Cronjob: E-Mail-Queue abarbeiten
├── cron_delete_expired_opinions.php # Cronjob: Alte Polls löschen
│
├── terminplanung_standalone.php # Standalone-Version: Terminplanung
├── opinion_standalone.php      # Standalone-Version: Meinungsbild
│
├── migrations/                 # SQL & PHP Migrations
│   ├── create_polls.sql
│   ├── add_poll_reminders.sql
│   ├── create_opinion_polls.sql
│   ├── insert_opinion_templates.sql
│   └── add_location_to_polls.php
│
├── tools/                      # Hilfs-Tools
│   ├── demo_export.php         # Demo-Daten exportieren
│   └── demo_import.php         # Demo-Daten importieren
│
└── README.md                   # User-Dokumentation
    INSTALL.md                  # Installations-Anleitung
    DEVELOPER.md                # Diese Datei
    OPINION_TOOL_README.md      # Meinungsbild-Tool Doku
```

## Datenbank-Schema

### Übersicht der Tabellen

Die Anwendung verwendet 24 Tabellen, gruppiert in folgende Bereiche:

#### 1. Core-Tabellen

**members** - Mitglieder/Benutzer
```sql
member_id (PK)
membership_number (UNIQUE)
email (UNIQUE)
password_hash
first_name, last_name
role (vorstand, gf, assistenz, fuehrungsteam, Mitglied)
is_admin, is_active, is_confidential
created_at
```

#### 2. Meeting-Tabellen

**meetings** - Sitzungen
```sql
meeting_id (PK)
meeting_name
meeting_date, expected_end_date
location, video_link
invited_by_member_id (FK → members)
chairman_member_id, secretary_member_id (FK → members)
started_at, ended_at
status (preparation, active, ended, protocol_ready, archived)
visibility_type (public, authenticated, invited_only)
protokoll, prot_intern, protocol_intern
created_at
```

**meeting_participants** - Teilnehmer an Meetings
```sql
participant_id (PK)
meeting_id (FK → meetings)
member_id (FK → members)
status (invited, confirmed, present, absent)
attendance_status (present, partial, absent)
invited_at
UNIQUE(meeting_id, member_id)
```

**agenda_items** - Tagesordnungspunkte
```sql
item_id (PK)
meeting_id (FK → meetings)
top_number
title, description
category (information, klaerung, diskussion, aussprache, antrag_beschluss, wahl, bericht, sonstiges)
proposal_text
vote_yes, vote_no, vote_abstain
vote_result (einvernehmlich, einstimmig, angenommen, abgelehnt)
priority, estimated_duration
protocol_notes
is_confidential, is_active
created_by_member_id (FK → members)
grouped_with_item_id (FK → agenda_items, self-referencing)
created_at, updated_at
```

**agenda_comments** - Kommentare zu TOPs
```sql
comment_id (PK)
item_id (FK → agenda_items)
member_id (FK → members)
comment_text
priority_rating, duration_estimate
created_at, updated_at
```

#### 3. Protokoll-Tabellen

**protocols** - Protokolle
```sql
protocol_id (PK)
meeting_id (FK → meetings)
protocol_type (public, confidential)
content
created_at
```

**protocol_change_requests** - Änderungsanfragen
```sql
request_id (PK)
protocol_id (FK → protocols)
member_id (FK → members)
item_id (FK → agenda_items)
change_request
created_at
```

#### 4. TODO-Tabellen

**todos** - Aufgaben
```sql
todo_id (PK)
meeting_id (FK → meetings)
item_id (FK → agenda_items)
assigned_to_member_id, created_by_member_id (FK → members)
title, description
status (open, ...)
is_private
entry_date, due_date
completed_at
protocol_link
created_at
```

**todo_log** - Änderungs-Historie
```sql
log_id (PK)
todo_id
changed_by
change_type, old_value, new_value
change_time
```

#### 5. Admin-Log

**admin_log** - Audit-Trail
```sql
log_id (PK)
admin_member_id (FK → members)
action_type, action_description
target_type, target_id
old_values (JSON), new_values (JSON)
ip_address, user_agent
created_at
```

#### 6. Terminplanung-Tabellen

**polls** - Terminabstimmungen
```sql
poll_id (PK)
title, description
created_by_member_id (FK → members)
meeting_id (FK → meetings)
location, video_link, duration
status (open, closed, finalized)
final_date_id (FK → poll_dates)
reminder_enabled, reminder_days, reminder_recipients, reminder_sent
created_at, updated_at, finalized_at
```

**poll_dates** - Terminvorschläge
```sql
date_id (PK)
poll_id (FK → polls)
suggested_date, suggested_end_date
location, notes
sort_order
created_at
```

**poll_participants** - Berechtigte Teilnehmer
```sql
participant_id (PK)
poll_id (FK → polls)
member_id (FK → members)
created_at
UNIQUE(poll_id, member_id)
```

**poll_responses** - Abstimmungen
```sql
response_id (PK)
poll_id (FK → polls)
date_id (FK → poll_dates)
member_id (FK → members)
vote (-1=Nein, 0=Vielleicht, 1=Ja)
comment
created_at, updated_at
UNIQUE(poll_id, date_id, member_id)
```

#### 7. Meinungsbild-Tool-Tabellen

**opinion_answer_templates** - Antwort-Vorlagen
```sql
template_id (PK)
template_name
option_1 .. option_10
created_at
```

**opinion_polls** - Meinungsbilder
```sql
poll_id (PK)
title
creator_member_id (FK → members)
target_type (individual, list, public)
list_id (FK → meetings) -- für target_type=list
access_token (UNIQUE) -- für target_type=individual
template_id (FK → opinion_answer_templates)
allow_multiple_answers, is_anonymous
duration_days, show_intermediate_after_days, delete_after_days
status (active, ended, deleted)
created_at, ends_at, delete_at
```

**opinion_poll_options** - Antwortoptionen
```sql
option_id (PK)
poll_id (FK → opinion_polls)
option_text
sort_order
```

**opinion_poll_participants** - Berechtigte Teilnehmer (bei target_type=list)
```sql
participant_id (PK)
poll_id (FK → opinion_polls)
member_id (FK → members)
created_at
UNIQUE(poll_id, member_id)
```

**opinion_responses** - Antworten
```sql
response_id (PK)
poll_id (FK → opinion_polls)
member_id (FK → members) -- NULL bei public/anonym
session_token -- für anonyme Teilnahme
free_text -- Kommentar
force_anonymous -- User will anonym bleiben
responded_at
```

**opinion_response_options** - Gewählte Optionen (M:N)
```sql
response_option_id (PK)
response_id (FK → opinion_responses)
option_id (FK → opinion_poll_options)
UNIQUE(response_id, option_id)
```

#### 8. E-Mail-Warteschlange

**mail_queue** - Asynchrone E-Mails
```sql
queue_id (PK)
recipient, subject
message_text, message_html
from_email, from_name
status (pending, sending, sent, failed)
priority, attempts, max_attempts
last_error
created_at, send_at, sent_at
```

### Trigger

**opinion_polls_before_insert** - Automatische Datumsberechnung
```sql
BEFORE INSERT ON opinion_polls
BEGIN
    SET NEW.ends_at = DATE_ADD(NOW(), INTERVAL NEW.duration_days DAY)
    SET NEW.delete_at = DATE_ADD(NOW(), INTERVAL NEW.delete_after_days DAY)
END
```

## Dateiübersicht

### Core-Dateien

#### config.php
**Zweck:** Zentrale Konfiguration
**Enthält:**
- Datenbankverbindungs-Parameter (DB_HOST, DB_NAME, DB_USER, DB_PASS)
- E-Mail-Konfiguration (MAIL_FROM, SMTP-Settings)
- Basis-URL (BASE_URL)

**Hinweis:** Nicht im Git-Repository, wird aus `config.example.php` kopiert

#### init-db.php
**Zweck:** Datenbank-Initialisierung
**Funktionen:**
- Datenbank erstellen (falls nicht vorhanden)
- Alle 24 Tabellen mit CREATE TABLE IF NOT EXISTS
- Trigger für opinion_polls erstellen
- 13 Meinungsbild-Templates einfügen
- **Default-Admin-User anlegen** (admin@example.com / admin123)
  - Nur wenn members-Tabelle leer ist
  - Rolle: gf, is_admin: 1

**Verwendung:** Einmalig nach Installation über Browser aufrufen
**Wichtig:** Default-Admin-Passwort sofort ändern!

#### tools/demo_export.php
**Zweck:** Demo-Daten exportieren
**Funktionen:**
- Exportiert alle Tabellen in JSON-Format
- Erstellt `tools/demo_data.json`
- Umfasst: members, meetings, agenda_items, todos, polls, opinion_polls, etc.

**Verwendung:** Zum Erstellen eines Demo-Snapshots

#### tools/demo_import.php
**Zweck:** Demo-Daten importieren
**Funktionen:**
- **LÖSCHT ALLE DATEN** (members, meetings, todos, polls, opinion_polls, etc.)
- Importiert demo_data.json
- Setzt Datenbank auf Demo-Stand zurück

**Verwendung:** Nur für Demo-Umgebungen oder zum Zurücksetzen
**Warnung:** Löscht auch den Default-Admin!

#### index.php
**Zweck:** Haupt-Entry-Point & Routing
**Ablauf:**
1. Session starten
2. Login prüfen (`$_SESSION['member_id']`)
3. Navigation rendern
4. Tab-Routing basierend auf `$_GET['tab']`:
   - dashboard, meetings, termine, opinion, members, todos, admin_log, etc.
5. Entsprechende `tab_*.php` inkludieren

**Authentifizierung:**
```php
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}
```

#### login.php
**Zweck:** Login-Seite
**Ablauf:**
1. POST empfangen mit email + password
2. User aus DB laden: `get_member_by_email()`
3. Passwort prüfen: `password_verify()`
4. Session setzen: `$_SESSION['member_id']`
5. Redirect zu index.php

#### logout.php
**Zweck:** Logout-Handler
**Ablauf:**
1. `session_destroy()`
2. Redirect zu login.php

### Funktions-Bibliotheken

#### functions.php
**Core-Hilfsfunktionen:**
- `get_db_connection()` - PDO-Verbindung
- `is_admin($user)` - Admin-Prüfung
- `sanitize_input($input)` - Input-Bereinigung
- `format_date($date)` - Datumsformatierung
- `log_admin_action()` - Admin-Log schreiben

#### member_functions.php
**Mitglieder-Management:**
- `get_member_by_id($pdo, $id)` - Member laden
- `get_member_by_email($pdo, $email)` - Member per E-Mail
- `get_all_members($pdo)` - Alle Mitglieder
- `create_member($pdo, $data)` - Neues Mitglied
- `update_member($pdo, $id, $data)` - Member updaten
- `delete_member($pdo, $id)` - Member löschen (soft-delete via is_active=0)

#### mail_functions.php
**E-Mail-Versand:**
- `send_mail($to, $subject, $message_text, $message_html)` - E-Mail senden
  - Erkennt PHPMailer automatisch
  - Fallback auf `mail()`
  - Option: Queue-basiert
- `queue_mail(...)` - E-Mail in Warteschlange
- `send_poll_invitation($pdo, $poll_id)` - Terminabstimmungs-Einladung
- `send_poll_finalization_notification($pdo, $poll_id, $recipients)` - Bestätigung
- `send_poll_reminder($pdo, $poll_id)` - Erinnerungsmail

#### opinion_functions.php
**Meinungsbild-Tool Helpers:**
- `get_all_opinion_polls($pdo, $member_id, $include_public)` - Liste Polls
- `get_opinion_poll_with_options($pdo, $poll_id)` - Poll + Optionen laden
- `can_participate($poll, $member_id)` - Teilnahme-Berechtigung
- `has_responded($pdo, $poll_id, $member_id, $session_token)` - Bereits geantwortet?
- `get_user_response($pdo, ...)` - User-Antwort laden
- `get_opinion_results($pdo, $poll_id)` - Statistiken berechnen
- `get_all_responses($pdo, $poll_id, $show_names)` - Alle Antworten
- `can_show_final_results($poll, $user, $has_responded)` - Berechtigungsprüfung
- `get_poll_access_link($poll, $base_url)` - Link generieren
- `get_poll_by_token($pdo, $token)` - Poll per Token laden

**Wichtig:** Korrekte Handhabung von NULL-Werten bei SQL-Queries für `member_id` und `session_token`

### Backend-Processing-Dateien

#### process_meeting.php
**Meeting-Aktionen:**
- `action=create_meeting` - Neues Meeting
- `action=update_meeting` - Meeting bearbeiten
- `action=delete_meeting` - Meeting löschen
- `action=start_meeting` - Sitzung starten
- `action=end_meeting` - Sitzung beenden
- `action=add_agenda_item` - TOP hinzufügen
- `action=update_agenda_item` - TOP bearbeiten
- `action=vote_on_item` - Abstimmung dokumentieren

**Security:**
- Berechtigungs-Checks vor jeder Aktion
- Prepared Statements
- CSRF-Protection via Session

#### process_termine.php
**Terminplanung-Aktionen:**
- `action=create_poll` - Neue Terminabstimmung
- `action=submit_vote` - Abstimmen
- `action=finalize_poll` - Termin festlegen
  - Optional: Meeting erstellen (`create_meeting=1`)
  - Bestätigungs-E-Mail senden
- `action=delete_poll` - Poll löschen

#### process_opinion.php
**Meinungsbild-Aktionen:**
- `action=create_opinion` - Neues Meinungsbild
  - Template oder custom Optionen
  - Teilnehmer-Liste bei target_type=list
  - Access-Token generieren bei target_type=individual
- `action=submit_response` - Antwort abgeben
  - Check ob bereits geantwortet (mit korrektem NULL-Handling!)
  - Editieren erlaubt für Ersteller (wenn nur 1 Antwort)
- `action=end_opinion` - Beenden
- `action=delete_opinion` - Soft-Delete (status='deleted')

**Session-Token für Anonyme:**
```php
function get_or_create_session_token() {
    if (!isset($_SESSION['opinion_session_token'])) {
        $_SESSION['opinion_session_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['opinion_session_token'];
}
```

#### process_admin.php
**Admin-Aktionen:**
- Mitglieder-Verwaltung
- Systemeinstellungen
- Log-Auswertungen

### View-Dateien

#### tab_*.php Dateien
**Zweck:** Haupt-Tabs der Anwendung
**Struktur:**
```php
<?php
// 1. Berechtigungs-Check
if (!$is_admin) {
    die('Keine Berechtigung');
}

// 2. Daten laden
$data = get_data_from_db($pdo);

// 3. HTML ausgeben
?>
<h2>Titel</h2>
<!-- UI-Elemente -->
```

**Tab-Übersicht:**
- `tab_dashboard.php` - Dashboard mit Übersichten
- `tab_meetings.php` - Meeting-Liste
- `tab_termine.php` - Terminplanung-UI
- `tab_opinion.php` - Meinungsbild-Routing
- `tab_members.php` - Mitglieder-Liste (Admin)
- `tab_todos.php` - TODO-Verwaltung
- `tab_admin_log.php` - Audit-Log (Admin)

#### opinion_views/*.php
**Unterviews für Meinungsbild-Tool:**
- `list.php` - Übersicht aller Meinungsbilder
- `create.php` - Erstellungs-Formular mit Template-Auswahl
- `detail.php` - Details + Admin-Buttons + Access-Link
- `participate.php` - Teilnahme-Formular
- `results.php` - Grafische Auswertung mit Balkendiagrammen

**Routing in tab_opinion.php:**
```php
$view = $_GET['view'] ?? 'list';
$poll_id = $_GET['poll_id'] ?? null;

if ($view === 'list') {
    include 'opinion_views/list.php';
} elseif ($view === 'create') {
    include 'opinion_views/create.php';
}
// ...
```

### Cronjob-Dateien

#### cron_poll_reminders.php
**Zweck:** Erinnerungs-E-Mails für Terminabstimmungen
**Ablauf:**
1. Finde alle Polls mit `reminder_enabled=1 AND reminder_sent=0`
2. Prüfe ob finalisiert und `final_date_id` gesetzt
3. Berechne Erinnerungs-Datum: `final_date - reminder_days`
4. Wenn heute >= Erinnerungs-Datum:
   - `send_poll_reminder($pdo, $poll_id)`
   - Setze `reminder_sent=1`

**Cronjob-Einrichtung:**
```bash
0 8 * * * /usr/bin/php /pfad/zu/cron_poll_reminders.php
```

#### cron_delete_expired_opinions.php
**Zweck:** Alte Meinungsbilder löschen
**Ablauf:**
1. Finde Polls mit `delete_at < NOW() AND status != 'deleted'`
2. Soft-Delete: `UPDATE opinion_polls SET status='deleted' WHERE poll_id=?`
3. Logging

**Cronjob:**
```bash
0 2 * * * /usr/bin/php /pfad/zu/cron_delete_expired_opinions.php
```

#### cron_process_mail_queue.php
**Zweck:** E-Mail-Warteschlange abarbeiten
**Ablauf:**
1. Lade Mails mit `status='pending' ORDER BY priority DESC, created_at ASC LIMIT 50`
2. Für jede Mail:
   - Setze `status='sending'`
   - Versuch zu senden via `send_mail()`
   - Bei Erfolg: `status='sent', sent_at=NOW()`
   - Bei Fehler: `attempts++, last_error=...`
     - Wenn `attempts >= max_attempts`: `status='failed'`

**Cronjob:**
```bash
*/5 * * * * /usr/bin/php /pfad/zu/cron_process_mail_queue.php
```

## Code-Konventionen

### Namensgebung

- **Tabellen:** Plural, Kleinbuchstaben, Unterstriche (`meeting_participants`)
- **Spalten:** Kleinbuchstaben, Unterstriche (`member_id`)
- **PHP-Funktionen:** Snake_case (`get_member_by_id()`)
- **PHP-Variablen:** Snake_case (`$current_user`)
- **Konstanten:** Großbuchstaben (`DB_HOST`)

### SQL-Queries

**Prepared Statements IMMER verwenden:**
```php
// Richtig:
$stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->execute([$member_id]);

// Falsch (SQL-Injection-Risiko):
$query = "SELECT * FROM members WHERE member_id = $member_id";
```

**NULL-Handling bei WHERE-Klauseln:**
```php
// Problem: (member_id = ? OR session_token = ?) funktioniert nicht mit NULL
// Lösung: Queries aufteilen:
if ($member_id !== null) {
    $stmt = $pdo->prepare("... WHERE member_id = ?");
    $stmt->execute([$member_id]);
} else if ($session_token !== null) {
    $stmt = $pdo->prepare("... WHERE session_token = ?");
    $stmt->execute([$session_token]);
}
```

### XSS-Schutz

**HTML-Output IMMER escapen:**
```php
echo htmlspecialchars($user_input);

// Bei Attributen:
<input value="<?php echo htmlspecialchars($value); ?>">
```

### Session-Management

**Session immer am Anfang starten:**
```php
session_start();
```

**Login-Check:**
```php
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}
```

## Sicherheits-Features

### Authentifizierung

- **Passwort-Hashing:** `password_hash()` mit `PASSWORD_DEFAULT` (Bcrypt)
- **Passwort-Verifikation:** `password_verify()`
- **Session-basiert:** `$_SESSION['member_id']`

### Autorisierung

- **Rollen-System:** 5 Rollen (vorstand, gf, assistenz, fuehrungsteam, Mitglied)
- **Berechtigungs-Checks:** Vor jeder kritischen Aktion
```php
if (!in_array($user['role'], ['vorstand', 'gf'])) {
    die('Keine Berechtigung');
}
```

### Audit-Logging

**Admin-Aktionen werden geloggt:**
```php
log_admin_action($pdo, $admin_id, 'delete_meeting', 'Meeting gelöscht',
                 'meeting', $meeting_id, $old_values, null);
```

### Vertraulichkeit

- **Getrennte Protokolle:** `protokoll` (public) vs. `prot_intern` / `protocol_intern` (confidential)
- **Vertrauliche TOPs:** `is_confidential` Flag
- **Vertrauliche Mitglieder:** `is_confidential` Flag

## Erweiterungen entwickeln

### Neues Feature hinzufügen

1. **Datenbank-Schema erweitern:**
   - Neue SQL-Datei in `migrations/` erstellen
   - Tabellen/Spalten hinzufügen
   - `init-db.php` aktualisieren

2. **Backend-Logik erstellen:**
   - Funktionen in entsprechende `*_functions.php` einfügen
   - Processing-Datei erstellen/erweitern (`process_*.php`)

3. **Frontend-UI erstellen:**
   - Neue `tab_*.php` oder View in Unterverzeichnis
   - Routing in `index.php` hinzufügen

4. **Testen:**
   - Mit Demo-Daten testen
   - Berechtigungen prüfen
   - SQL-Injections ausschließen
   - XSS-Schutz verifizieren

5. **Dokumentation:**
   - README.md aktualisieren (User-Sicht)
   - DEVELOPER.md aktualisieren (technisch)
   - Optional: Separate Feature-Doku

### Beispiel: Neues Feld für Meetings

**1. Migration erstellen (`migrations/add_meeting_budget.sql`):**
```sql
ALTER TABLE meetings
ADD COLUMN budget DECIMAL(10,2) DEFAULT NULL COMMENT 'Budget für Meeting in EUR';
```

**2. init-db.php aktualisieren:**
```php
// In meetings-Tabellen-Definition:
budget DECIMAL(10,2) DEFAULT NULL,
```

**3. Frontend erweitern (`tab_meetings.php`):**
```php
<input type="number" name="budget" step="0.01"
       value="<?php echo $meeting['budget'] ?? ''; ?>">
```

**4. Backend erweitern (`process_meeting.php`):**
```php
case 'create_meeting':
    $budget = floatval($_POST['budget'] ?? 0);
    $stmt = $pdo->prepare("INSERT INTO meetings (..., budget) VALUES (..., ?)");
    $stmt->execute([..., $budget]);
```

## Testing

### Manuelle Tests

1. **Login-Tests:**
   - Korrektes Login
   - Falsches Passwort
   - Nicht-existierender User

2. **Berechtigungs-Tests:**
   - Als Admin: Alle Features verfügbar?
   - Als Mitglied: Eingeschränkte Rechte?
   - Nicht-eingeladener User: Kein Zugriff auf Meeting?

3. **Feature-Tests:**
   - Meeting erstellen → TOPs → Durchführen → Protokoll
   - Terminabstimmung → Abstimmen → Festlegen → Meeting
   - Meinungsbild → Link teilen → Antworten → Auswertung

### Datenbank-Konsistenz

**Prüfen auf verwaiste Einträge:**
```sql
-- Agenda Items ohne Meeting
SELECT * FROM agenda_items ai
LEFT JOIN meetings m ON ai.meeting_id = m.meeting_id
WHERE m.meeting_id IS NULL;

-- Poll Participants ohne Member
SELECT * FROM poll_participants pp
LEFT JOIN members m ON pp.member_id = m.member_id
WHERE m.member_id IS NULL;
```

## Performance-Optimierungen

### Datenbank-Indizes

Alle wichtigen Spalten sind indiziert:
- Foreign Keys
- Häufig gesucht Felder (status, created_at, email)
- Composite Indexes für Multi-Column-Queries

### Query-Optimierung

**Vermeiden von N+1-Queries:**
```php
// Schlecht:
foreach ($meetings as $meeting) {
    $participants = get_participants($meeting_id); // N Queries
}

// Besser:
$all_participants = get_all_participants_grouped(); // 1 Query
```

**JOINs nutzen:**
```php
SELECT m.*, u.first_name, u.last_name
FROM meetings m
JOIN members u ON m.invited_by_member_id = u.member_id
```

### Caching

Aktuell kein Caching implementiert. Potenzielle Erweiterung:
- Session-basiertes Caching für `$current_user`
- File-basiertes Caching für Template-Listen
- Redis/Memcached für Produktivumgebungen

## Deployment

### Checkliste für Produktion

- [ ] `config.php` mit Production-Daten
- [ ] Default-Admin-Passwort ändern (admin@example.com)
- [ ] HTTPS erzwingen
- [ ] `init-db.php` löschen/sperren
- [ ] `tools/demo_export.php` und `tools/demo_import.php` löschen/sperren
- [ ] `migrations/*.php` löschen/sperren (nach erfolgter Migration)
- [ ] Demo-Accounts löschen (falls demo_import verwendet wurde)
- [ ] PHP-Fehleranzeige deaktivieren (`display_errors = Off`)
- [ ] Error-Logging aktivieren
- [ ] Cronjobs einrichten
- [ ] Backups konfigurieren
- [ ] SSL-Zertifikat installieren
- [ ] `.htaccess` für Sicherheit

### Git-Workflow

```bash
# Feature-Branch erstellen
git checkout -b feature/mein-feature

# Änderungen committen
git add .
git commit -m "Add: Neue Funktion XYZ"

# Pushen
git push origin feature/mein-feature

# Merge-Request erstellen
```

## Häufige Probleme & Lösungen

### "Session nicht gefunden" nach Login

**Ursache:** Session-Cookie wird nicht gesetzt
**Lösung:**
- Prüfen ob `session_start()` vor jeder Header-Ausgabe
- Prüfen ob Session-Verzeichnis beschreibbar

### "Datenbank-Verbindungsfehler"

**Ursache:** Falsche Credentials oder MySQL nicht erreichbar
**Lösung:**
- `config.php` prüfen
- MySQL-Service status: `systemctl status mysql`

### E-Mails kommen nicht an

**Ursache:** SMTP-Konfiguration falsch oder `mail()` nicht konfiguriert
**Lösung:**
- SMTP-Settings in `config.php` prüfen
- `mail_queue` Tabelle auf Fehler prüfen
- Cronjob läuft?

## Weiterführende Ressourcen

- **PHP-Dokumentation:** https://www.php.net/manual/de/
- **MySQL-Dokumentation:** https://dev.mysql.com/doc/
- **PDO-Tutorial:** https://www.php.net/manual/de/book.pdo.php
- **Security Best Practices:** https://cheatsheetseries.owasp.org/

---

**Viel Erfolg bei der Weiterentwicklung!**
