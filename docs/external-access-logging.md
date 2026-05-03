# Externe Zugriffs-Logs

## Übersicht

Das System protokolliert automatisch alle externen Zugriffe auf Terminumfragen und Meinungsbilder in der Datenbank-Tabelle `svexternal_access_log`.

## Geloggte Ereignisse

### 1. Member-Login (✅ member_login)
- **Wann**: Wenn ein Nutzer sich über die Mitgliedsnummer anmeldet
- **Gespeichert**: member_id, mnr, IP, User-Agent
- **Status**: success = true

### 2. Ungültige Mitgliedsnummer (⚠️ invalid_mnr)
- **Wann**: Wenn eine nicht existierende Mitgliedsnummer eingegeben wird
- **Gespeichert**: mnr, IP, User-Agent
- **Status**: success = false
- **Zweck**: Missbrauchserkennung

### 3. Externe Registrierung (👤 external_registration)
- **Wann**: Wenn sich jemand als externer Teilnehmer registriert
- **Gespeichert**: external_participant_id, email, first_name, last_name, IP, User-Agent
- **Status**: success = true

### 4. Registrierungsfehler (❌ registration_error)
- **Wann**: Bei technischen Fehlern während der Registrierung
- **Gespeichert**: Alle eingegebenen Daten, Fehlermeldung, IP, User-Agent
- **Status**: success = false

## Admin-Ansicht

Die Logs sind im **Admin-Bereich** unter dem Abschnitt **"🔐 Externe Zugriffs-Logs"** einsehbar:

### Statistiken (letzte 30 Tage)
- Anzahl Member-Logins
- Anzahl ungültige MNr-Versuche
- Anzahl externe Registrierungen
- Anzahl Fehler

### Log-Tabelle (letzte 100 Einträge)
Spalten:
- **Zeitpunkt**: Wann erfolgte der Zugriff?
- **Typ**: Art des Zugriffs (Member-Login, ungültige MNr, etc.)
- **Umfrage**: Terminumfrage oder Meinungsbild + ID
- **Person**: Name/E-Mail/MNr der Person
- **IP-Adresse**: IP des Zugriffs
- **Status**: Erfolg oder Fehler
- **Details**: Button für erweiterte Informationen (User-Agent, Fehlermeldungen)

## Sicherheit & Datenschutz

- **IP-Adressen** werden gespeichert zur Missbrauchserkennung
- **User-Agents** helfen bei der Identifikation von Bots
- **Erfolgreiche Zugriffe** werden protokolliert für Nachvollziehbarkeit
- **Fehlgeschlagene Zugriffe** werden besonders hervorgehoben

## Technische Details

### Installation
```bash
# Migration ausführen
mysql -u [user] -p [database] < migrations/add_external_access_log_table.sql
```

### Funktionen
- `log_external_access()` - Erstellt einen Log-Eintrag
- `get_external_access_logs()` - Lädt Logs mit Filterung
- `count_external_access_by_type()` - Statistiken nach Typ

### Retention
- Logs werden **unbegrenzt** gespeichert
- Bei Bedarf kann ein Cleanup-Job erstellt werden
- Foreign Keys mit `ON DELETE SET NULL` verhindern Datenintegrität-Probleme

## Verwendung für Admins

1. **Normale Überwachung**: Regelmäßig die Statistiken prüfen
2. **Missbrauchserkennung**: Auffällig viele ungültige MNr-Versuche von einer IP?
3. **Debugging**: Bei Problemen mit externer Teilnahme die Logs prüfen
4. **Transparenz**: Nachvollziehen, wer wann teilgenommen hat

## Beispiel-Abfragen

```sql
-- Alle fehlgeschlagenen Zugriffe
SELECT * FROM svexternal_access_log WHERE success = FALSE ORDER BY created_at DESC;

-- Zugriffe von einer bestimmten IP
SELECT * FROM svexternal_access_log WHERE ip_address = '192.168.1.100';

-- Statistik pro Umfrage
SELECT poll_type, poll_id, COUNT(*) as zugriffe
FROM svexternal_access_log
GROUP BY poll_type, poll_id
ORDER BY zugriffe DESC;

-- Ungültige MNr-Versuche der letzten 7 Tage
SELECT * FROM svexternal_access_log
WHERE access_type = 'invalid_mnr'
AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;
```
