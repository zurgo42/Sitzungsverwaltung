# Aktions-Protokollierung (Audit-Log)

Es gibt zwei voneinander unabhängige Protokolle:

| Protokoll | Tabelle | Zweck | Gefüllt durch |
|---|---|---|---|
| **Datenbank-Protokoll (Audit-Log)** | `svaudit_log` | Technisches Log aller schreibenden SQL-Abfragen | automatisch (`audit_pdo.php`) |
| **Aktions-Protokoll** | `protokoll` | Fachliche Aktionen mit Label (VTool-kompatibel) | explizit per `protokoll()` |

## Datenbank-Protokoll (`svaudit_log`)

`audit_pdo.php` stellt die Klassen `AuditPDO` (erweitert `PDO`) und `AuditStatement` bereit. `AuditPDO` registriert `AuditStatement` über `PDO::ATTR_STATEMENT_CLASS`; dadurch wird jedes `INSERT`, `UPDATE`, `DELETE` und `REPLACE` nach erfolgreicher Ausführung protokolliert – ohne Änderung am Anwendungscode. Auch direkte `exec()`-Aufrufe werden erfasst. `SELECT`-Abfragen und Schreibzugriffe auf `svaudit_log` selbst werden nicht protokolliert.

### Einbindung

Die zentrale Verbindung wird in `config.php` (Vorlage: `config.example.php`) aufgebaut:

```php
if (file_exists(__DIR__ . '/audit_pdo.php')) {
    require_once __DIR__ . '/audit_pdo.php';
}
$pdo = class_exists('AuditPDO')
    ? new AuditPDO($dsn, DB_USER, DB_PASS, $options)
    : new PDO($dsn, DB_USER, DB_PASS, $options);
```

Ohne `audit_pdo.php` läuft die Anwendung unverändert mit normalem `PDO` (ohne Protokollierung). Bestehende `config.php`-Dateien müssen entsprechend angepasst werden.

### Tabelle `svaudit_log`

Die Tabelle wird von `tools/init-db.php` angelegt (nach einem Update `tools/init-db.php` erneut aufrufen).

| Spalte | Beschreibung |
|---|---|
| `log_id` | Laufende Nummer |
| `logged_at` | Zeitpunkt (DATETIME(3)) |
| `member_id` | Eingeloggtes Mitglied (`$_SESSION['member_id']`), `NULL` bei Cron/extern |
| `ip_address` | IP-Adresse (`cli` bei Kommandozeile) |
| `script` | Aufrufendes Skript (`SCRIPT_NAME`) |
| `action` | `INSERT`, `UPDATE`, `DELETE` oder `REPLACE` |
| `table_name` | Betroffene Tabelle |
| `affected_rows` | Anzahl betroffener Zeilen |
| `query` | SQL-Abfrage **mit eingesetzten Parameterwerten** (max. 2000 Zeichen; Werte über 300 Zeichen werden gekürzt) |

Die Parameterwerte werden nur für die Anzeige eingesetzt (`?` und benannte Parameter); ausgeführt wird weiterhin die parametrisierte Abfrage. Ein Fehler beim Schreiben des Logs wird nur per `error_log()` gemeldet und bricht die eigentliche Aktion nie ab.

**Datenschutz:** Die gespeicherten Abfragen können personenbezogene Daten enthalten (z.B. Mitgliedsdaten, Kommentartexte). Zugriff nur für Admins. Die Tabelle wird nicht automatisch bereinigt oder archiviert.

### Anzeige im Admin-Bereich

Admin-Tab, Abschnitt **"Datenbank-Protokoll (Audit-Log)"** (`tab_admin.php`): Liste der neuesten Einträge mit Filtern (Mitglied bzw. "Cron / extern", Tabelle, Aktion, Datum von/bis, Anzahl 50-500) und Button zur Anzeige der vollständigen SQL-Abfrage in einem Modal-Dialog. Fehlt die Tabelle, erscheint ein Hinweis.

---

## Aktions-Protokoll (`protokoll`)

Alle durch Benutzer verursachten Datenbankänderungen werden in der Tabelle `protokoll` festgehalten. Das System ist kompatibel mit dem Altformat des VTool-Protokolls.

## Zweck

- Lückenlose Nachvollziehbarkeit von Änderungen (Wer hat wann was getan?)
- Diagnose bei Fehleingaben oder strittigen Änderungen
- Monatsweises Archiv zur langfristigen Aufbewahrung
- Kein Einfluss auf den Normalbetrieb (Fehler werden nur geloggt, nie nach oben gegeben)

## Tabellenschema

```sql
CREATE TABLE IF NOT EXISTS protokoll (
    MNr    VARCHAR(12) DEFAULT NULL,   -- Mitgliedsnummer des Users
    KurzN  VARCHAR(12) DEFAULT NULL,   -- Kurzname ("V. Nachname")
    zeit   VARCHAR(20) DEFAULT NULL,   -- Zeitstempel "YYYY-MM-DD HH:MM:SS"
    was    TEXT        DEFAULT NULL,   -- Aktionskürzel (s. u.)
    string TEXT        DEFAULT NULL,   -- Beschreibung der Änderung
    INDEX idx_mnr  (MNr),
    INDEX idx_zeit (zeit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Das Schema entspricht dem Altformat: keine `id`-Spalte, Zeitstempel als VARCHAR.

## Hilfsfunktionen (`protokoll_helper.php`)

### `get_protokoll_user($current_user)`

Ermittelt Mitgliedsnummer und Kurzname des eingeloggten Users.

- **MNr**: `$_SESSION['MNr']` (SSO) → `membership_number` → `member_id` → `'NN'`
- **KurzN**: Erster Buchstabe Vorname + `. ` + Nachname, z.B. `"V. Mustermann"`

```php
[$mnr, $kurz] = get_protokoll_user($current_user);
```

### `protokoll($pdo, $mnr, $kurz, $was, $string, $filter = 3)`

Schreibt einen Eintrag in `protokoll`. Fehler werden nur per `error_log()` ausgegeben und unterbrechen nie die Hauptanwendung.

**Parameter:**

| Parameter | Typ | Beschreibung |
|---|---|---|
| `$pdo` | PDO | Datenbankverbindung |
| `$mnr` | string | Mitgliedsnummer |
| `$kurz` | string | Kurzname |
| `$was` | string | Aktionskürzel (z.B. `'Antrag-Speichern'`) |
| `$string` | string | Beschreibung der Änderung |
| `$filter` | int | Deduplizierungsstufe (s. u.) |

**`$filter`-Werte:**

| Wert | Verhalten |
|---|---|
| `3` | Immer eintragen (Standard) |
| `2` | Deduplizierung: Gleichen Eintrag innerhalb einer Stunde nur einmal |
| `1` | Deduplizierung: Gleichen Eintrag innerhalb eines Tages nur einmal |
| `0` | Deduplizierung: Aktionskürzel `'Zugriff'` innerhalb eines Jahres nur einmal |

## Monatsarchivierung

Der `pseudo_cron.php` prüft beim ersten Seitenaufruf eines neuen Monats, ob eine Archivierung notwendig ist.

**Ablauf:**

1. Aktuellen Monat ermitteln (`date('Ym')`, z.B. `202608`)
2. svconfig-Schlüssel `protokoll_last_archive` lesen
3. Falls Monat verschieden: Archivtabelle `YYYYMMprotokoll` (z.B. `202608protokoll`) anlegen (`CREATE TABLE ... LIKE protokoll`), Daten kopieren (`INSERT INTO ... SELECT *`), Quelltabelle leeren (`TRUNCATE TABLE protokoll`)
4. `protokoll_last_archive` auf den neuen Monat setzen

**Archivtabellen** haben denselben Aufbau wie `protokoll` und können direkt per SQL abgefragt werden:

```sql
-- August 2026
SELECT * FROM `202608protokoll` WHERE MNr = '12345' ORDER BY zeit DESC;
```

Der svconfig-Schlüssel `protokoll_last_archive` wird automatisch gepflegt und sollte nicht manuell geändert werden.

## $was-Labels und ihre Bedeutung

### antrag_neu.php

| Label | Bedeutung |
|---|---|
| `Antrag-Neu` | Neuer Antrag angelegt |

### antrag_bearbeiten.php

| Label | Bedeutung |
|---|---|
| `Antrag-Speichern` | Antrag-Formular gespeichert |
| `Antrag-Finalisieren` | Antrag finalisiert (unveränderlicher Zustand) |
| `Antrag-Verwerfen` | Antrag verworfen/zurückgezogen |
| `WZV-beantragt` | Wartezeit-Verkürzung beantragt |
| `WZV-Zustimmung` | Wartezeit-Verkürzung genehmigt |

### abstimmungen.php

| Label | Bedeutung |
|---|---|
| `Votum-Speichern` | Abstimmungsergebnis gespeichert |
| `Votum-Bemerkungen` | Bemerkungen zu einem Votum eingetragen |
| `Abstimmung-Hinweis` | Hinweis-Text zu einer Abstimmung gesetzt |
| `Antrag-Zurueckziehen` | Antrag aus der Abstimmung zurückgezogen |
| `Antrag-Kopie-Neu` | Antrag als Kopie neu angelegt |

### process_meetings.php

| Label | Bedeutung |
|---|---|
| `Sitzung-Erstellen` | Neue Sitzung angelegt |
| `Sitzung-Bearbeiten` | Sitzungsdaten geändert |
| `Sitzung-Loeschen` | Sitzung gelöscht |
| `Sitzung-Starten` | Sitzung gestartet |
| `Sitzung-Duplizieren` | Sitzung dupliziert |

### process_agenda.php (Auswahl)

| Label | Bedeutung |
|---|---|
| `TOP-Neu` | Tagesordnungspunkt angelegt |
| `TOP-Bearbeiten` | TOP bearbeitet |
| `TOP-Loeschen` | TOP gelöscht |
| `TOP-Verschieben` | TOP zu anderer Sitzung verschoben |
| `TOP-Wiedervorlage` | TOP zur Wiedervorlage markiert |
| `Kommentar-*` | Kommentar-Aktionen (Neu, Bearbeiten, Loeschen) |
| `Protokoll-*` | Protokoll-Aktionen (Speichern, Freigeben) |
| `Abstimmung-*` | Abstimmungs-Aktionen (Neu, Starten, Schliessen) |
| `Stimme` | Einzelne Stimme abgegeben |
| `TODO-*` | TODO-Aktionen aus der Agenda heraus |
| `Anwesenheit` | Anwesenheit eines Teilnehmers eingetragen |

### process_todos.php

| Label | Bedeutung |
|---|---|
| `TODO-Erstellen` | Neues TODO angelegt |
| `TODO-Status` | TODO-Status geändert |
| `TODO-Zurueckziehen` | TODO zurückgezogen |

### process_protocol.php

| Label | Bedeutung |
|---|---|
| `Protokoll-Speichern` | Protokolltext gespeichert |

## Neue Stellen instrumentieren

1. `protokoll_helper.php` einbinden (einmalig pro Datei):

```php
require_once __DIR__ . '/protokoll_helper.php';
```

2. User-Daten ermitteln (einmalig, sobald `$current_user` bekannt ist):

```php
[$prot_mnr, $prot_kurz] = get_protokoll_user($current_user);
```

3. An jeder relevanten Stelle aufrufen:

```php
protokoll($pdo, $prot_mnr, $prot_kurz, 'MeinLabel', "Beschreibung: $details");
```

**Hinweise:**

- `$was` sollte ein kurzes, konsistentes Kürzel sein (Bindestriche statt Leerzeichen, kein Trailing-Space)
- `$string` darf längere Kontextinformationen enthalten (Antrags-Nr., TOP-Titel, etc.)
- Bei lesenden Zugriffen nicht protokollieren – nur schreibende Aktionen
- `$filter = 3` (Standard) ist für fast alle Fälle korrekt; `$filter = 1` eignet sich für häufige Aktionen, die täglich nur einmal relevant sind (z.B. Sitzung starten)
- Die Funktion wirft keine Exception nach oben, daher kein try/catch nötig

## Abfrage-Beispiele

```sql
-- Alle Aktionen eines Users heute
SELECT zeit, was, string FROM protokoll
WHERE MNr = '12345' AND DATE(zeit) = CURDATE()
ORDER BY zeit DESC;

-- Alle Antrag-Speichern-Aktionen der letzten Woche
SELECT MNr, KurzN, zeit, string FROM protokoll
WHERE was = 'Antrag-Speichern'
  AND zeit >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY zeit DESC;

-- Archiv: Aktivitäten aus Juli 2026
SELECT * FROM `202607protokoll` ORDER BY zeit DESC LIMIT 100;
```
