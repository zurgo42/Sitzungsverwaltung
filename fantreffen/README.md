# AIDA Fantreffen - Verwaltungssystem

Ein webbasiertes Verwaltungssystem für AIDA-Kreuzfahrt-Fantreffen. Ermöglicht Organisatoren die Verwaltung von Reisen, Teilnehmern und Kommunikation.

## Features

- **Reiseverwaltung** - Anlegen und Verwalten von Fantreffen-Reisen
- **Teilnehmerverwaltung** - Anmeldung, Statusverfolgung, Kabinenzuweisung
- **Mail-System** - Queue-basierter Versand mit Provider-Limits und editierbaren Vorlagen
- **PDF-Generierung** - Faltblätter und Einladungsbögen
- **Admin-Bereich** - Teilnehmerlisten, Exporte (CSV, E-Mail-Liste), Namensschilder
- **Öffentliche Statusseite** - Teilnehmerzahlen ohne Login einsehbar

## Projektstruktur

```
fantreffen/
├── config/              # Konfigurationsdateien
│   └── config.example.php
├── cron/                # Cronjobs für Mail-Queue und Erinnerungen
├── docs/                # Dokumentation und SQL-Schemas
├── pdf/                 # Generierte PDF-Dateien
├── public/              # Webroot
│   ├── admin/           # Admin-Bereich
│   ├── css/             # Stylesheets
│   ├── images/          # Bilder
│   └── *.php            # Öffentliche Seiten
├── src/                 # PHP-Klassen
│   ├── Database.php     # PDO-Wrapper
│   ├── MailService.php  # Mail-System
│   ├── PdfService.php   # PDF-Generierung
│   ├── Reise.php        # Reise-Model
│   ├── Session.php      # Session-Verwaltung
│   ├── Teilnehmer.php   # Teilnehmer-Model
│   └── User.php         # User-Model
└── templates/           # Header/Footer Templates
```

## Voraussetzungen

- PHP 7.4 oder höher
- MySQL 5.7+ / MariaDB 10.3+
- PHPMailer 6.x (separat installieren)
- FPDF (separat installieren, für PDF-Generierung)

## Installation

1. **Dateien hochladen**
   ```bash
   git clone https://github.com/zurgo42/fantreffen.git
   ```

2. **Konfiguration erstellen**
   ```bash
   cp config/config.example.php config/config.php
   ```
   Dann `config/config.php` mit den eigenen Zugangsdaten anpassen.

3. **Datenbank einrichten**
   ```bash
   mysql -u username -p datenbankname < docs/schema.sql
   mysql -u username -p datenbankname < docs/schema_mail_vorlagen.sql
   ```

4. **Abhängigkeiten installieren**

   PHPMailer und FPDF müssen im übergeordneten Verzeichnis liegen:
   ```
   /
   ├── PHPMailer/
   │   ├── PHPMailer.php
   │   ├── SMTP.php
   │   └── Exception.php
   ├── fpdf/
   │   └── fpdf.php
   └── fantreffen/
   ```

5. **Cronjobs einrichten**
   ```bash
   # Mail-Queue (stündlich)
   0 * * * * php /pfad/zu/fantreffen/cron/process_mail_queue.php

   # Erinnerungen und Cleanup (täglich um 6:00)
   0 6 * * * php /pfad/zu/fantreffen/cron/daily_tasks.php
   ```

6. **Webserver konfigurieren**

   Der DocumentRoot sollte auf `/fantreffen/public/` zeigen.

## Konfiguration

Wichtige Einstellungen in `config/config.php`:

```php
// Datenbank
define('DB_HOST', 'localhost');
define('DB_NAME', 'datenbankname');
define('DB_USER', 'username');
define('DB_PASS', 'passwort');

// Mail-Server (SMTP)
define('MAIL_HOST', 'mail.example.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'info@example.de');
define('MAIL_PASS', 'passwort');
define('MAIL_FROM', 'info@example.de');
define('MAIL_FROM_NAME', 'AIDA Fantreffen');
define('MAIL_BCC_ADMIN', '');  // Optional: BCC an Admin

// Anwendung
define('APP_NAME', 'AIDA Fantreffen');
define('BASE_URL', 'https://example.de/fantreffen');
```

## Mail-System

Das System verwendet eine Queue-basierte Architektur mit Provider-spezifischen Limits:

| Provider | Limit/Stunde |
|----------|-------------|
| GMX      | 10          |
| Web.de   | 10          |
| T-Online | 10          |
| Yahoo    | 10          |
| Andere   | 20          |

### Verfügbare Mail-Vorlagen

- `admin_ernennung` - Benachrichtigung bei Admin-Ernennung
- `anmeldung_bestaetigung` - Bestätigung der Anmeldung
- `treffen_bestaetigt` - Info über bestätigtes Treffen
- `kabine_fehlt` - Erinnerung Kabinennummer
- `erinnerung_anreise` - Tag vor Anreise

Vorlagen können im Admin-Bereich unter "Mail-Vorlagen" bearbeitet werden.

## Dokumentation

Ausführliche technische Dokumentation: [docs/TECHNISCHE_DOKUMENTATION.md](docs/TECHNISCHE_DOKUMENTATION.md)

## Lizenz

Privates Projekt - Alle Rechte vorbehalten.

## Autor

Entwickelt für die Organisation von AIDA-Fantreffen.
