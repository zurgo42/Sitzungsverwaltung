# Technische Dokumentation - AIDA Fantreffen

## Projektstruktur

```
fantreffen/
├── config/
│   ├── config.example.php      # Vorlage für Konfiguration
│   └── config.php              # Lokale Konfiguration (nicht in Git)
├── cron/
│   ├── process_mail_queue.php  # Stündlicher Cronjob: Mail-Queue abarbeiten
│   └── daily_tasks.php         # Täglicher Cronjob: Erinnerungen & Cleanup
├── docs/
│   ├── schema.sql              # Haupt-Datenbankschema
│   ├── schema_mail_vorlagen.sql # Mail-Vorlagen Schema
│   └── TECHNISCHE_DOKUMENTATION.md
├── public/
│   ├── admin/
│   │   ├── mail-senden.php     # Massen-Mails & Queue-Verarbeitung
│   │   ├── mail-vorlagen.php   # Mail-Vorlagen bearbeiten
│   │   ├── reise-bearbeiten.php
│   │   ├── teilnehmerliste.php
│   │   └── ...
│   ├── dashboard.php           # Anmeldung für Teilnehmer
│   ├── index.php               # Startseite
│   └── login.php
├── src/
│   ├── Database.php            # PDO-Wrapper
│   ├── MailService.php         # Mail-System (Queue, Versand, Vorlagen)
│   ├── Reise.php               # Reise-Model
│   ├── Session.php             # Session-Verwaltung
│   └── User.php                # User-Model
└── templates/
    ├── header.php
    └── footer.php
```

---

## Mail-System

### Übersicht

Das Mail-System besteht aus folgenden Komponenten:

1. **MailService.php** - Zentrale Klasse für alle Mail-Operationen
2. **fan_mail_queue** - Datenbanktabelle für die Warteschlange
3. **fan_mail_vorlagen** - Datenbanktabelle für editierbare Vorlagen
4. **Cronjobs** - Automatische Verarbeitung

### Datenbank-Tabellen

#### fan_mail_queue
```sql
CREATE TABLE fan_mail_queue (
    mail_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reise_id INT UNSIGNED DEFAULT NULL,        -- Zuordnung zur Reise
    vorlage_code VARCHAR(50) DEFAULT NULL,     -- Verwendete Vorlage
    empfaenger VARCHAR(255) NOT NULL,          -- E-Mail-Adresse
    betreff VARCHAR(255) NOT NULL,
    inhalt_html TEXT NOT NULL,
    inhalt_text TEXT,
    prioritaet TINYINT DEFAULT 5,              -- 1-10, höher = wichtiger
    erstellt DATETIME DEFAULT CURRENT_TIMESTAMP,
    gesendet DATETIME DEFAULT NULL,            -- NULL = noch nicht gesendet
    versuche TINYINT DEFAULT 0,                -- Anzahl Sendeversuche
    letzter_fehler TEXT DEFAULT NULL
);
```

#### fan_mail_vorlagen
```sql
CREATE TABLE fan_mail_vorlagen (
    vorlage_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,          -- z.B. 'admin_ernennung'
    name VARCHAR(100) NOT NULL,                -- Anzeigename
    beschreibung TEXT,
    betreff VARCHAR(255) NOT NULL,             -- Mit Platzhaltern
    inhalt_html TEXT NOT NULL,                 -- HTML-Version
    inhalt_text TEXT,                          -- Text-Version
    platzhalter JSON,                          -- Liste der Platzhalter
    aktiv TINYINT(1) DEFAULT 1,
    erstellt DATETIME DEFAULT CURRENT_TIMESTAMP,
    aktualisiert DATETIME ON UPDATE CURRENT_TIMESTAMP
);
```

### MailService.php - Methoden

#### Konstruktor
```php
$mailService = new MailService($db);
```

#### Provider-Erkennung
```php
$provider = $mailService->getProvider('test@gmx.de');
// Gibt zurück: 'gmx', 'web', 't-online', 'yahoo' oder 'default'
```

#### Mail in Queue einfügen
```php
$mailId = $mailService->queueMail(
    'empfaenger@example.de',    // Empfänger
    'Betreff',                  // Betreff
    '<html>...</html>',         // HTML-Inhalt
    'Text-Version',             // Text-Inhalt (optional)
    $reiseId,                   // Reise-ID (optional)
    'vorlage_code',             // Vorlage-Code (optional)
    5                           // Priorität 1-10 (optional)
);
```

#### Mail aus Vorlage senden
```php
$success = $mailService->sendFromVorlage(
    'admin_ernennung',          // Vorlage-Code
    'empfaenger@example.de',    // Empfänger
    [                           // Platzhalter-Werte
        'vorname' => 'Max',
        'schiff' => 'AIDAcosma',
        ...
    ],
    $reiseId,                   // Reise-ID (optional)
    8                           // Priorität (optional)
);
```

#### Spezifische Mail-Methoden

```php
// Admin-Ernennung (automatisch bei Zuweisung)
$mailService->sendAdminErnennung($userId, $reiseId, $passwort = null);

// Anmeldebestätigung (automatisch bei Neuanmeldung)
$mailService->sendAnmeldebestaetigung($userId, $reiseId);

// Treffen bestätigt (manuell durch Admin)
$count = $mailService->sendTreffenBestaetigt($reiseId);

// Kabine fehlt (manuell durch Admin)
$count = $mailService->sendKabineFehlt($reiseId);

// Erinnerung vor Anreise (per Cronjob)
$count = $mailService->sendErinnerungAnreise($reiseId);
```

#### Queue verarbeiten
```php
$stats = $mailService->processQueue([
    'gmx' => 10,        // Max 10 Mails/Stunde an GMX
    'web' => 10,
    't-online' => 10,
    'yahoo' => 10,
    'default' => 20
]);

// Rückgabe:
// ['processed' => 5, 'sent' => 4, 'failed' => 1, 'skipped' => 0]
```

#### Direktversand (ohne Queue)
```php
$success = $mailService->sendMailDirect(
    'empfaenger@example.de',
    'Betreff',
    '<html>...</html>',
    'Text-Version'
);
```

### Mail-Fluss

```
┌─────────────────────────────────────────────────────────────────┐
│                        MAIL-ERZEUGUNG                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  dashboard.php                  reise-bearbeiten.php            │
│       │                                │                        │
│       ▼                                ▼                        │
│  sendAnmeldebestaetigung()      sendAdminErnennung()            │
│       │                                │                        │
│       └────────────┬───────────────────┘                        │
│                    ▼                                            │
│             sendFromVorlage()                                   │
│                    │                                            │
│                    ▼                                            │
│              queueMail()                                        │
│                    │                                            │
│                    ▼                                            │
│         ┌─────────────────────┐                                 │
│         │   fan_mail_queue    │                                 │
│         │   (Warteschlange)   │                                 │
│         └─────────────────────┘                                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        MAIL-VERSAND                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  mail-senden.php (manuell)      process_mail_queue.php (cron)   │
│       │                                │                        │
│       └────────────┬───────────────────┘                        │
│                    ▼                                            │
│             processQueue()                                      │
│                    │                                            │
│                    ▼                                            │
│     ┌──────────────────────────────┐                            │
│     │  Provider-Limits prüfen      │                            │
│     │  (gmx, web, t-online, yahoo) │                            │
│     └──────────────────────────────┘                            │
│                    │                                            │
│                    ▼                                            │
│            sendMailDirect()                                     │
│                    │                                            │
│                    ▼                                            │
│              PHPMailer                                          │
│                    │                                            │
│                    ▼                                            │
│             SMTP-Server                                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Vorlagen-Codes

| Code | Beschreibung | Auslöser |
|------|--------------|----------|
| `admin_ernennung` | Benachrichtigung bei Admin-Ernennung | Automatisch in reise-bearbeiten.php |
| `anmeldung_bestaetigung` | Bestätigung der Anmeldung | Automatisch in dashboard.php |
| `treffen_bestaetigt` | Info über bestätigtes Treffen | Manuell durch Admin |
| `kabine_fehlt` | Erinnerung Kabinennummer | Manuell durch Admin |
| `erinnerung_anreise` | Tag vor Anreise | Cronjob daily_tasks.php |

### Platzhalter

Verfügbare Platzhalter in Vorlagen:

| Platzhalter | Beschreibung |
|-------------|--------------|
| `{vorname}` | Vorname des Empfängers |
| `{name}` | Nachname des Empfängers |
| `{email}` | E-Mail-Adresse |
| `{schiff}` | Schiffsname |
| `{anfang}` | Reisebeginn (formatiert) |
| `{ende}` | Reiseende (formatiert) |
| `{treffen_ort}` | Treffpunkt |
| `{treffen_zeit}` | Treffzeit (formatiert) |
| `{kabine}` | Kabinennummer |
| `{login_link}` | Link zur Anmeldeseite |
| `{passwort}` | Passwort (nur bei Admin-Ernennung) |
| `{passwort_info}` | Passwort-Block (nur bei neuem User) |

---

## Konfiguration

### config.php

```php
<?php
// Datenbank
define('DB_HOST', 'localhost');
define('DB_NAME', 'datenbankname');
define('DB_USER', 'username');
define('DB_PASS', 'passwort');

// Mail-Server (SMTP)
define('MAIL_HOST', 'mail.example.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'info@example.de');
define('MAIL_PASS', 'mail_passwort');
define('MAIL_FROM', 'info@example.de');       // Absender-Adresse
define('MAIL_FROM_NAME', 'AIDA Fantreffen');  // Absender-Name

// Anwendung
define('APP_NAME', 'AIDA Fantreffen');
define('BASE_URL', 'https://example.de/fantreffen');
define('APP_DEBUG', false);
```

### PHPMailer-Einbindung

PHPMailer muss im Root-Verzeichnis liegen. Der MailService erwartet:

```php
require_once __DIR__ . '/../../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/SMTP.php';
require_once __DIR__ . '/../../PHPMailer/Exception.php';
```

---

## Cronjobs

### process_mail_queue.php (stündlich)

```bash
0 * * * * php /pfad/zu/fantreffen/cron/process_mail_queue.php
```

Verarbeitet die Mail-Queue mit Provider-Limits.

### daily_tasks.php (täglich, z.B. 6:00 Uhr)

```bash
0 6 * * * php /pfad/zu/fantreffen/cron/daily_tasks.php
```

- Sendet Erinnerungen für Reisen, die morgen beginnen
- Löscht Daten für Reisen, die gestern endeten

---

## Fehlersuche Mail-System

### 1. Queue prüfen

```sql
-- Alle wartenden Mails
SELECT * FROM fan_mail_queue WHERE gesendet IS NULL;

-- Fehlgeschlagene Mails (3+ Versuche)
SELECT * FROM fan_mail_queue WHERE versuche >= 3 AND gesendet IS NULL;

-- Letzte Fehler anzeigen
SELECT mail_id, empfaenger, versuche, letzter_fehler
FROM fan_mail_queue
WHERE letzter_fehler IS NOT NULL;
```

### 2. Vorlagen prüfen

```sql
-- Alle aktiven Vorlagen
SELECT code, name, aktiv FROM fan_mail_vorlagen;

-- Bestimmte Vorlage prüfen
SELECT * FROM fan_mail_vorlagen WHERE code = 'admin_ernennung';
```

### 3. SMTP-Verbindung testen

In `sendMailDirect()` (MailService.php, ca. Zeile 320) kann Debug aktiviert werden:

```php
$mail->SMTPDebug = 2;  // Ausführliche Ausgabe
```

### 4. Häufige Fehler

| Fehler | Ursache | Lösung |
|--------|---------|--------|
| `Class 'PHPMailer' not found` | PHPMailer nicht gefunden | Pfad in MailService.php prüfen |
| `SMTP connect() failed` | SMTP-Verbindung fehlgeschlagen | Host/Port/Credentials in config.php prüfen |
| `Vorlage nicht gefunden` | Vorlage fehlt in DB | INSERT aus schema_mail_vorlagen.sql ausführen |
| `str_contains undefined` | PHP < 8.0 | Bereits behoben mit strpos() |
| `mixed type error` | PHP < 8.0 | Bereits behoben |

---

## Bounce-Handling (Zurückgewiesene Mails)

### Problem: Yahoo/AOL Blocks

Yahoo und AOL blockieren häufig E-Mails von Shared-Hosting-Servern mit Fehlermeldungen wie:

```
421 4.7.0 [TSS04] Messages temporarily deferred due to unexpected volume
or user complaints - see https://postmaster.yahooinc.com/error-codes
```

### Ursachen

1. **Schlechte IP-Reputation** - Die Shared-Hosting-IP wurde von anderen Nutzern "verbrannt"
2. **Fehlendes SPF/DKIM/DMARC** - E-Mail-Authentifizierung nicht korrekt konfiguriert
3. **Zu viele Mails** - Rate-Limiting durch den Provider
4. **Spam-Beschwerden** - Empfänger haben frühere Mails als Spam markiert

### Lösungsansätze

| Lösung | Aufwand | Effektivität |
|--------|---------|--------------|
| **SPF/DKIM/DMARC einrichten** | Mittel | Hoch |
| **Dedizierte IP verwenden** | Hoch (VPS) | Hoch |
| **Externen Mailservice nutzen** | Niedrig | Sehr hoch |
| **Yahoo-Empfänger ausschließen** | Niedrig | Workaround |

### Externe Mailservices (empfohlen)

Für zuverlässigen Mailversand sind externe Services besser geeignet:

- **Mailjet** - Kostenlos bis 200 Mails/Tag
- **SendGrid** - Kostenlos bis 100 Mails/Tag
- **Amazon SES** - Sehr günstig, aber Setup komplexer

### SPF/DKIM einrichten (beim Domain-Hoster)

1. **SPF-Record** in DNS hinzufügen:
   ```
   v=spf1 include:_spf.example-hosting.de ~all
   ```

2. **DKIM-Record** vom Mailserver abrufen und eintragen

3. **DMARC-Record** hinzufügen:
   ```
   v=DMARC1; p=none; rua=mailto:dmarc@deine-domain.de
   ```

### Bounce-Erkennung im System

Aktuell werden Bounces nicht automatisch erkannt. Bei Bedarf könnte implementiert werden:

1. **Return-Path Header** mit Bounce-Adresse setzen
2. **Bounce-Mailbox** per IMAP/POP3 auslesen
3. **Betroffene Adressen** in der Datenbank markieren

---

## Versionshinweise

- **PHP-Version:** Kompatibel mit PHP 7.4+
- **Datenbank:** MySQL 5.7+ / MariaDB 10.3+
- **PHPMailer:** Version 6.x erforderlich
