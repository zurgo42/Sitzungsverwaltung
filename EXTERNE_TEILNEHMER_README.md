# Externe Teilnehmer - Dokumentation

**Erstellt:** 2025-12-18
**Version:** 1.0

## Übersicht

Dieses Feature ermöglicht externen Nutzern (ohne Account) die Teilnahme an Umfragen via Link. Es wurde entwickelt, um die Anforderungen für Termine und Meinungsbild zu vereinheitlichen und DSGVO-konform umzusetzen.

## Konzept

### 1. Einladung (nur für Vereinsmitglieder)

Umfragen können nur von registrierten Mitgliedern erstellt werden:
- **SSO-User** mit MNr (aus vorgelagertem System)
- **Registrierte User** aus `svmembers` bzw. `berechtigte`

**Zwei Einladungsmodi:**
- **(A) Individueller Link** mit langer Kennung → funktioniert ohne Login
- **(B) Einladungsmails** an ausgewählte registrierte Teilnehmer

### 2. Beteiligung (auch für Externe)

An Umfragen teilnehmen können:
- Alle aus (1) - registrierte Mitglieder
- **PLUS: Externe Nutzer** ohne Account via individuellem Link

Bei externen Nutzern erfasst das System:
- Vorname, Nachname
- E-Mail-Adresse
- Optional: Mitgliedsnummer (falls vorhanden)
- Einwilligung zur Datenspeicherung

### 3. Datenschutz (DSGVO-konform)

- Externe Teilnehmer-Daten werden in separater Tabelle `svexternal_participants` gespeichert
- **Automatische Löschung:** 6 Monate nach letzter Aktivität
- Einwilligung zur Datenspeicherung wird eingeholt
- IP-Adresse wird nur zur Missbrauchsprävention gespeichert

## Neue Dateien

### 1. Datenbank-Migration
**Datei:** `migrations/add_external_participants.sql`

Erstellt die Tabelle `svexternal_participants` und erweitert die Response-Tabellen:
```sql
-- Neue Tabelle für externe Teilnehmer
CREATE TABLE svexternal_participants (
    external_id INT PRIMARY KEY AUTO_INCREMENT,
    poll_type ENUM('termine', 'meinungsbild'),
    poll_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(255),
    mnr VARCHAR(50),
    session_token VARCHAR(64) UNIQUE,
    created_at TIMESTAMP,
    last_activity TIMESTAMP,
    ...
);

-- Erweitert Response-Tabellen
ALTER TABLE svpoll_responses ADD COLUMN external_participant_id INT;
ALTER TABLE svopinion_responses ADD COLUMN external_participant_id INT;
```

**Migration ausführen:**
```bash
mysql -u root -p sitzungsverwaltung < migrations/add_external_participants.sql
```

### 2. Hilfsfunktionen
**Datei:** `external_participants_functions.php`

Zentrale Funktionen für externe Teilnehmer:
- `create_external_participant()` - Registrierung
- `get_external_participant_by_token()` - Token-basierte Authentifizierung
- `get_current_participant()` - Prüft ob User eingeloggt oder extern
- `cleanup_old_external_participants()` - 6-Monats-Löschung
- `generate_external_access_link()` - Link-Generierung

**Verwendung:**
```php
require_once 'external_participants_functions.php';

// Teilnehmer registrieren
$result = create_external_participant(
    $pdo,
    'termine',
    $poll_id,
    'Max',
    'Mustermann',
    'max@example.com'
);

// Aktuellen Teilnehmer ermitteln
$participant = get_current_participant($current_user, $pdo, 'termine', $poll_id);
```

### 3. Registrierungsformular
**Datei:** `external_participant_register.php`

Responsive HTML-Formular zur Erfassung externer Teilnehmer. Wird von standalone-Skripten eingebunden wenn kein Login vorliegt.

**Features:**
- Validierung von Name, Email
- DSGVO-Einwilligung
- Mobile-optimiert
- Session-basierte Authentifizierung

### 4. Cron-Job für automatische Löschung
**Datei:** `cron_cleanup_external_participants.php`

Löscht externe Teilnehmer, die > 6 Monate inaktiv sind.

**Einrichtung via Crontab (täglich um 2:00 Uhr):**
```bash
crontab -e

# Zeile hinzufügen:
0 2 * * * /usr/bin/php /pfad/zu/cron_cleanup_external_participants.php >> /var/log/cleanup_external.log 2>&1
```

**Manuell testen:**
```bash
php cron_cleanup_external_participants.php
```

## Integration in bestehende Skripte

### Termine (terminplanung_standalone.php)

**TODO:** Folgende Anpassungen sind noch notwendig:

1. `external_participants_functions.php` einbinden
2. Prüfen ob User eingeloggt ODER als externer Teilnehmer registriert
3. Falls nicht: `external_participant_register.php` einbinden
4. Bei Antworten: `external_participant_id` statt nur `member_id` speichern

**Beispiel-Integration:**
```php
require_once 'external_participants_functions.php';

// Aktuellen Teilnehmer ermitteln
$participant = get_current_participant($current_user, $pdo, 'termine', $poll_id);

if ($participant['type'] === 'none') {
    // Registrierungsformular anzeigen
    $poll_type = 'termine';
    require 'external_participant_register.php';
    exit;
}

// User ist identifiziert - Umfrage anzeigen
if ($participant['type'] === 'member') {
    $member_id = $participant['id'];
    $external_id = null;
} else {
    $member_id = null;
    $external_id = $participant['id'];
}
```

### Meinungsbild (opinion_standalone.php)

**TODO:** Gleiche Anpassungen wie bei Termine.

### API-Dateien

Falls Antworten über separate API-Dateien gespeichert werden (z.B. `process_termine.php`), müssen diese ebenfalls angepasst werden:

```php
// Statt:
$stmt->execute([$poll_id, $date_id, $member_id, $vote]);

// Jetzt:
$stmt->execute([$poll_id, $date_id, $member_id, $external_id, $vote]);
```

## Session-Variable für SSO-Email

**TODO:** Im vorgelagerten SSO-System muss die Email-Adresse als Session-Variable bereitgestellt werden:

```php
// In index.php nach SSO-Login:
if ($sso_user) {
    $_SESSION['member_id'] = $sso_user['member_id'];
    $_SESSION['role'] = $sso_user['role'];
    $_SESSION['MNr'] = $sso_mnr;
    $_SESSION['email'] = $sso_user['email'];  // ← NEU
}
```

Dann kann diese Email in `member_functions.php` bzw. im Adapter verwendet werden.

## Workflow für externe Teilnehmer

```
1. Ersteller sendet Link:
   https://domain.de/terminplanung_standalone.php?poll_id=123

2. Externer Nutzer öffnet Link
   → Wird auf Registrierungsformular geleitet

3. Nutzer gibt Daten ein:
   - Vorname, Nachname
   - Email
   - Optional: Mitgliedsnummer
   - Einwilligung

4. System erstellt Eintrag in svexternal_participants
   - Generiert session_token
   - Speichert in Session

5. Nutzer kann an Umfrage teilnehmen
   - Antworten werden mit external_participant_id verknüpft

6. Nach 6 Monaten Inaktivität:
   - Cron-Job löscht externe Teilnehmer
   - CASCADE löscht auch zugehörige Antworten
```

## Offene Punkte (TODO)

- [ ] Datenbank-Migration ausführen
- [ ] `terminplanung_standalone.php` erweitern
- [ ] `opinion_standalone.php` erweitern
- [ ] `process_termine.php` anpassen (external_participant_id)
- [ ] `process_opinion.php` anpassen (falls vorhanden)
- [ ] Cron-Job einrichten
- [ ] SSO-System: Email als Session-Variable bereitstellen
- [ ] Testing: Externe Registrierung & Teilnahme
- [ ] Testing: 6-Monats-Löschung

## Sicherheitshinweise

1. **Session-Token:** 64-stellige Hex-Strings (secure random)
2. **Email-Validierung:** PHP `filter_var(FILTER_VALIDATE_EMAIL)`
3. **SQL-Injection:** Alle Queries mit Prepared Statements
4. **XSS-Schutz:** `htmlspecialchars()` bei allen Ausgaben
5. **CSRF-Schutz:** TODO - bei Bedarf CSRF-Tokens hinzufügen

## Support & Fragen

Bei Fragen oder Problemen:
1. Prüfen Sie die Logs: `/var/log/cleanup_external.log`
2. Prüfen Sie PHP-Error-Log
3. Testen Sie die Registrierung manuell

---

**Ende der Dokumentation**
