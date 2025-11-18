# E-Mail-Benachrichtigungen für Terminplanung

Dieses Dokument beschreibt die E-Mail-Funktionen für die Terminplanung.

## Überblick

Das System bietet drei Arten von E-Mail-Benachrichtigungen:

1. **Einladungsmail beim Erstellen einer Umfrage**
2. **Bestätigungsmail beim Finalisieren eines Termins**
3. **Erinnerungsmail X Tage vor dem Termin**

## Installation

### 1. Datenbank-Migration ausführen

Die Erinnerungsmail-Funktion benötigt neue Datenbank-Spalten. Führen Sie die Migration aus:

```bash
mysql -u [USERNAME] -p [DATENBANKNAME] < migrations/add_poll_reminders.sql
```

Oder über phpMyAdmin:
- Öffnen Sie phpMyAdmin
- Wählen Sie die Datenbank aus
- Gehen Sie zu SQL-Tab
- Kopieren Sie den Inhalt von `migrations/add_poll_reminders.sql` und führen Sie ihn aus

### 2. Cronjob einrichten (für Erinnerungsmails)

Damit Erinnerungsmails automatisch versendet werden, muss ein Cronjob eingerichtet werden:

```bash
# Crontab bearbeiten
crontab -e

# Fügen Sie diese Zeile hinzu (täglich um 8:00 Uhr):
0 8 * * * /usr/bin/php /pfad/zu/Sitzungsverwaltung/cron_send_poll_reminders.php

# Optional: Log-Datei anlegen
0 8 * * * /usr/bin/php /pfad/zu/Sitzungsverwaltung/cron_send_poll_reminders.php >> /var/log/poll_reminders.log 2>&1
```

**Wichtig**: Passen Sie den Pfad an Ihre Installation an!

### 3. E-Mail-Konfiguration prüfen

Stellen Sie sicher, dass in `config.php` folgende Einstellungen korrekt sind:

```php
// E-Mail aktivieren
define('MAIL_ENABLED', true);

// E-Mail-Backend wählen
define('MAIL_BACKEND', 'mail');  // 'mail', 'phpmailer', oder 'queue'

// Absender-Einstellungen
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'Meeting-System');

// Optional: APP_URL für Links in E-Mails
define('APP_URL', 'https://ihre-domain.de');
```

## Verwendung

### 1. Einladungsmail beim Erstellen

Beim Erstellen einer neuen Terminumfrage:

1. Füllen Sie das Formular aus (Titel, Beschreibung, Teilnehmer, Terminvorschläge)
2. Aktivieren Sie die Checkbox **"Einladungsmail an ausgewählte Teilnehmer senden"**
3. Klicken Sie auf "Umfrage erstellen"

→ Alle ausgewählten Teilnehmer erhalten eine E-Mail mit:
- Titel und Beschreibung der Umfrage
- Liste aller Terminvorschläge
- Link zur Umfrage zum Abstimmen

### 2. Bestätigungsmail beim Finalisieren

Beim Festlegen des finalen Termins:

1. Wählen Sie den finalen Termin aus
2. Wählen Sie die **Empfänger der Bestätigungsmail**:
   - **Nur Teilnehmer, die abgestimmt haben** (Standard)
   - **Alle ausgewählten Teilnehmer** (auch ohne Abstimmung)
   - **Keine E-Mail senden**
3. Optional: Aktivieren Sie **"Erinnerungsmail vor dem Termin senden"**
   - Geben Sie an, wie viele Tage vorher die Erinnerung gesendet werden soll (1-30 Tage)
4. Klicken Sie auf "Finalen Termin festlegen"

→ Die ausgewählten Empfänger erhalten eine E-Mail mit:
- Finalem Datum, Uhrzeit und Ort
- Hinweisen (falls vorhanden)
- Link zur Umfrage

### 3. Erinnerungsmail (automatisch)

Wenn beim Finalisieren die Erinnerungsmail aktiviert wurde:

- Der Cronjob läuft täglich (z.B. um 8:00 Uhr)
- X Tage vor dem Termin wird automatisch eine Erinnerung versendet
- Die Empfänger sind dieselben wie bei der Bestätigungsmail
- Die Erinnerung wird nur einmal versendet

Beispiel:
- Termin: 20.11.2025
- Erinnerung: 2 Tage vorher
- → Am 18.11.2025 um 8:00 Uhr wird die Erinnerungsmail versendet

## Fehlerbehebung

### E-Mails werden nicht versendet

1. **Prüfen Sie MAIL_ENABLED** in `config.php`
2. **Prüfen Sie die Logs** (error_log oder Log-Datei des Cronjobs)
3. **Testen Sie den Mailversand** mit der Funktion `send_test_mail()`
4. **Backend wechseln**: Versuchen Sie ein anderes Mail-Backend ('mail', 'phpmailer', 'queue')

### Erinnerungsmails werden nicht versendet

1. **Prüfen Sie ob der Cronjob läuft**:
   ```bash
   crontab -l  # Zeigt aktive Cronjobs
   ```

2. **Testen Sie das Script manuell**:
   ```bash
   php /pfad/zu/cron_send_poll_reminders.php
   ```

3. **Prüfen Sie die Datenbank**:
   ```sql
   SELECT poll_id, title, reminder_enabled, reminder_sent, reminder_days
   FROM polls
   WHERE status = 'finalized' AND reminder_enabled = 1;
   ```

### Teilnehmer ohne E-Mail-Adresse

- Nur Mitglieder mit gültiger E-Mail-Adresse in der Datenbank erhalten Benachrichtigungen
- Prüfen Sie die `members` Tabelle, ob E-Mail-Adressen hinterlegt sind

## Technische Details

### Datenbank-Struktur

Neue Spalten in der `polls` Tabelle:

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `reminder_enabled` | TINYINT(1) | Ob Erinnerungsmail aktiviert (1) oder nicht (0) |
| `reminder_days` | INT | Anzahl Tage vor Termin für Erinnerung |
| `reminder_recipients` | VARCHAR(20) | Empfänger: 'voters', 'all', 'none' |
| `reminder_sent` | TINYINT(1) | Ob Erinnerung bereits versendet wurde (1) oder nicht (0) |

### Funktionen

- `send_poll_invitation($pdo, $poll_id, $host_url_base)` - Einladungsmail
- `send_poll_finalization_notification($pdo, $poll_id, $final_date_id, $host_url_base, $recipients)` - Bestätigungsmail
- `send_poll_reminder($pdo, $poll_id, $host_url_base)` - Erinnerungsmail

Alle Funktionen sind in `mail_functions.php` definiert.

### E-Mail-Format

Alle E-Mails werden als **Multipart-Mails** versendet:
- **Text-Version** für einfache E-Mail-Clients
- **HTML-Version** mit Formatierung, Farben und Buttons

## Support

Bei Problemen:
1. Prüfen Sie die PHP error_log Datei
2. Prüfen Sie die Cronjob-Logs
3. Testen Sie die Mail-Funktionen manuell
