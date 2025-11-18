# 📧 Mail-System Standalone

**Version:** 1.0
**Erstellt:** 18.11.2025

## 📋 Übersicht

Das **Mail-System Standalone** ist eine portable Mail-Versand-Lösung, die sowohl in die Sitzungsverwaltung integriert als auch vollständig eigenständig auf anderen Servern verwendet werden kann.

## ✨ Features

- ✅ **Multi-Backend-Support**: PHP `mail()`, PHPMailer (SMTP), Queue (Datenbank)
- ✅ **Multipart-Mails**: Text + HTML in einer Mail
- ✅ **Web-Interface**: Test-Interface für Mail-Versand und Queue-Verwaltung
- ✅ **Minimale Abhängigkeiten**: Läuft überall wo PHP läuft
- ✅ **Portabel**: Einfach auf andere Server kopieren
- ✅ **Konfigurierbar**: Separate Config-Datei für Standalone-Betrieb

---

## 🚀 Installation

### 1. Dateien kopieren

Kopieren Sie folgende Dateien auf Ihren Server:

```bash
mail_standalone.php          # Haupt-Script
process_mail_queue.php       # (Optional) Für Queue-Backend
```

### 2. Konfiguration

Beim ersten Aufruf von `mail_standalone.php` wird automatisch eine Konfigurationsdatei `mail_standalone_config.php` erstellt.

**Wichtig:** Passen Sie die Konfiguration an Ihre Bedürfnisse an!

```php
<?php
// mail_standalone_config.php

// Mail-Versand aktivieren
define('MAIL_ENABLED', true);
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'Mein System');

// Backend wählen: 'mail', 'phpmailer', 'queue'
define('MAIL_BACKEND', 'mail');

// SMTP-Einstellungen (nur für phpmailer)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);
define('SMTP_USER', 'user@example.com');
define('SMTP_PASS', 'passwort');

// Datenbank-Einstellungen (nur für queue)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mail_queue_db');

// Admin-Zugang für Web-Interface
define('MAIL_ADMIN_USER', 'admin');
define('MAIL_ADMIN_PASS', 'changeme');  // BITTE ÄNDERN!
?>
```

---

## 📖 Verwendung

### Als PHP-Include (Empfohlen)

```php
<?php
// Mail-System einbinden
require_once 'pfad/zu/mail_standalone.php';

// Einfache Konfiguration
$mail_config = [
    'enabled' => true,
    'backend' => 'mail',  // 'mail', 'phpmailer', 'queue'
    'from_email' => 'noreply@example.com',
    'from_name' => 'Mein System'
];

// Mail senden
$result = mail_standalone_send(
    'empfaenger@example.com',           // Empfänger
    'Test-Betreff',                     // Betreff
    'Text-Version der Nachricht',       // Text-Inhalt
    '<p>HTML-Version</p>',              // HTML-Inhalt (optional)
    $mail_config                        // Config (optional)
);

if ($result) {
    echo "✅ Mail erfolgreich versendet!";
} else {
    echo "❌ Fehler beim Versenden";
}
?>
```

### Via Web-Interface

1. Öffnen Sie `https://ihredomain.com/mail_standalone.php` im Browser
2. Melden Sie sich mit den Admin-Zugangsdaten an (siehe Config)
3. Verwenden Sie das Test-Interface zum Versenden von Mails
4. Überwachen Sie die Queue (wenn Queue-Backend aktiviert)

**Screenshots:**
- System-Status zeigt aktuelle Konfiguration
- Test-Formular zum manuellen Versand
- Queue-Statistiken (bei Queue-Backend)

---

## 🔧 Backends

### 1. PHP `mail()` Backend

**Vorteile:**
- ✅ Funktioniert überall (Standard PHP)
- ✅ Keine zusätzlichen Abhängigkeiten
- ✅ Einfache Konfiguration

**Nachteile:**
- ⚠️ Abhängig von Server-Konfiguration
- ⚠️ Manchmal als Spam markiert

**Konfiguration:**
```php
define('MAIL_BACKEND', 'mail');
```

---

### 2. PHPMailer Backend (SMTP)

**Vorteile:**
- ✅ Professioneller SMTP-Versand
- ✅ Bessere Zustellrate
- ✅ Authentifizierung möglich

**Nachteile:**
- ⚠️ Benötigt PHPMailer Library
- ⚠️ SMTP-Zugangsdaten erforderlich

**Installation:**
```bash
composer require phpmailer/phpmailer
```

**Konfiguration:**
```php
define('MAIL_BACKEND', 'phpmailer');
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);
define('SMTP_USER', 'user@example.com');
define('SMTP_PASS', 'passwort');
```

---

### 3. Queue Backend (Datenbank)

**Vorteile:**
- ✅ Asynchroner Versand via Cronjob
- ✅ Verhindert Timeouts bei vielen Mails
- ✅ Retry-Mechanismus bei Fehlern
- ✅ Kontrollierte Versandgeschwindigkeit

**Nachteile:**
- ⚠️ Benötigt Datenbank
- ⚠️ Cronjob erforderlich

**Installation:**

1. **Datenbank erstellen:**
```sql
CREATE TABLE mail_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    message_text TEXT NOT NULL,
    message_html TEXT,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL,
    status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
    priority INT DEFAULT 5,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    created_at DATETIME NOT NULL,
    send_at DATETIME NULL,
    sent_at DATETIME NULL,
    last_error TEXT NULL,
    INDEX idx_status (status),
    INDEX idx_send_at (send_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

2. **Cronjob einrichten** (empfohlen: alle 5 Minuten):
```bash
*/5 * * * * /usr/bin/php /pfad/zu/process_mail_queue.php >> /var/log/mail_queue.log 2>&1
```

3. **Konfiguration:**
```php
define('MAIL_BACKEND', 'queue');
define('DB_HOST', 'localhost');
define('DB_USER', 'mail_user');
define('DB_PASS', 'passwort');
define('DB_NAME', 'mail_queue_db');

// Queue-Einstellungen
define('MAIL_QUEUE_BATCH_SIZE', 10);      // Mails pro Durchlauf
define('MAIL_QUEUE_DELAY', 1);            // Sekunden zwischen Mails
define('MAIL_QUEUE_MAX_ATTEMPTS', 3);     // Max. Zustellversuche
```

---

## 🔄 Integration in bestehende Anwendungen

### Beispiel 1: In Sitzungsverwaltung

```php
<?php
// In Sitzungsverwaltung ist mail_standalone.php bereits integriert
// und nutzt die config.php Einstellungen

require_once 'mail_functions.php';

// Mail senden
multipartmail(
    'empfaenger@example.com',
    'Betreff',
    'Text-Nachricht',
    '<p>HTML-Nachricht</p>'
);
?>
```

### Beispiel 2: In anderer Anwendung (Terminplanung, Meinungsbildung)

```php
<?php
// Minimale Integration
require_once 'pfad/zu/mail_standalone.php';

// Custom Config
$config = [
    'enabled' => true,
    'backend' => 'phpmailer',
    'from_email' => 'termine@example.com',
    'from_name' => 'Terminplanung',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_auth' => true,
    'smtp_user' => 'user@gmail.com',
    'smtp_pass' => 'app-password'
];

// Mail senden
mail_standalone_send(
    'teilnehmer@example.com',
    'Neue Terminumfrage',
    'Sie wurden zu einer Terminumfrage eingeladen...',
    '<p>Sie wurden zu einer <strong>Terminumfrage</strong> eingeladen...</p>',
    $config
);
?>
```

---

## 🧪 Testen

### Via Web-Interface

1. Öffnen Sie `mail_standalone.php` im Browser
2. Melden Sie sich an
3. Nutzen Sie das Test-Formular

### Via CLI

```bash
php -r "
require 'mail_standalone.php';
echo mail_standalone_send(
    'test@example.com',
    'CLI Test',
    'Test-Mail via CLI',
    '',
    ['enabled' => true, 'backend' => 'mail']
) ? 'OK' : 'FEHLER';
"
```

---

## 📬 Queue-Verwaltung

### Queue-Status prüfen

```bash
# Via Web-Interface
https://ihredomain.com/mail_standalone.php
# Zeigt Statistiken: pending, sent, failed

# Via MySQL
mysql -u user -p -e "SELECT status, COUNT(*) FROM mail_queue GROUP BY status;"
```

### Queue manuell abarbeiten

```bash
php process_mail_queue.php
```

### Queue-Statistiken

```sql
-- Alle ausstehenden Mails
SELECT * FROM mail_queue WHERE status = 'pending';

-- Fehlgeschlagene Mails
SELECT * FROM mail_queue WHERE status = 'failed';

-- Mails der letzten 24h
SELECT * FROM mail_queue WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

---

## 🔒 Sicherheit

### Admin-Zugang schützen

**Wichtig:** Ändern Sie die Standard-Zugangsdaten in `mail_standalone_config.php`:

```php
define('MAIL_ADMIN_USER', 'ihr_benutzername');
define('MAIL_ADMIN_PASS', 'sicheres_passwort');
```

### Datei-Berechtigungen

```bash
# Config-Datei nur für Owner lesbar
chmod 600 mail_standalone_config.php

# Script ausführbar
chmod 755 mail_standalone.php
chmod 755 process_mail_queue.php
```

### HTTPS verwenden

Für Produktivbetrieb sollte das Web-Interface nur via HTTPS erreichbar sein!

---

## 🐛 Fehlersuche

### Problem: Mails werden nicht versendet

**Lösung:**
1. Prüfen Sie `MAIL_ENABLED` in der Config (muss `true` sein)
2. Prüfen Sie Server-Logs: `/var/log/apache2/error.log` oder PHP Error Log
3. Testen Sie mit `mail()` Backend zuerst
4. Prüfen Sie Spam-Ordner beim Empfänger

### Problem: PHPMailer-Fehler

**Lösung:**
1. Prüfen Sie ob PHPMailer installiert ist: `composer require phpmailer/phpmailer`
2. Prüfen Sie SMTP-Zugangsdaten
3. Testen Sie SMTP-Verbindung: `telnet smtp.example.com 587`

### Problem: Queue läuft nicht

**Lösung:**
1. Prüfen Sie ob Datenbank-Tabelle existiert (siehe SQL oben)
2. Prüfen Sie DB-Zugangsdaten in Config
3. Prüfen Sie Cronjob: `crontab -l`
4. Manuell testen: `php process_mail_queue.php`

### Problem: 403 Forbidden beim Web-Interface

**Lösung:**
1. Prüfen Sie Datei-Berechtigungen: `chmod 755 mail_standalone.php`
2. Prüfen Sie `.htaccess` Einstellungen
3. Prüfen Sie PHP-Konfiguration

---

## 📊 Best Practices

### 1. Backend-Auswahl

- **Entwicklung:** `mail` Backend (einfach, schnell)
- **Produktion (wenige Mails):** `phpmailer` Backend (zuverlässig)
- **Produktion (viele Mails):** `queue` Backend (skalierbar)

### 2. Fehlerbehandlung

```php
<?php
$result = mail_standalone_send(...);

if (!$result) {
    // Fehler loggen
    error_log("Mail-Versand fehlgeschlagen: " . print_r($_POST, true));

    // Benutzer informieren
    echo "Fehler beim Versenden. Bitte später erneut versuchen.";
}
?>
```

### 3. Queue-Monitoring

Richten Sie Monitoring ein für:
- Anzahl pending Mails (Alarm bei > 100)
- Anzahl failed Mails (Alarm bei > 10)
- Cronjob-Ausführung (Alarm wenn > 15 Min nicht gelaufen)

---

## 🔗 Integration mit anderen Standalone-Tools

### Mit Terminplanung-Standalone

```php
<?php
require_once 'terminplanung_standalone.php';
require_once 'mail_standalone.php';

// Nach Umfrage-Erstellung Mail senden
function send_poll_notification($poll_id) {
    $poll = get_poll($poll_id);
    $participants = get_poll_participants($poll_id);

    foreach ($participants as $participant) {
        mail_standalone_send(
            $participant['email'],
            "Neue Terminumfrage: " . $poll['title'],
            "Sie wurden zu einer Terminumfrage eingeladen...",
            '<p>Neue <strong>Terminumfrage</strong>...</p>'
        );
    }
}
?>
```

### Mit Meinungsbildung-Standalone

```php
<?php
require_once 'opinion_standalone.php';
require_once 'mail_standalone.php';

// Nach Meinungsbild-Erstellung Mail senden
function send_opinion_notification($opinion_id) {
    // Ähnlich wie oben...
}
?>
```

---

## 📝 Changelog

### Version 1.0 (18.11.2025)
- ✅ Initial Release
- ✅ Multi-Backend-Support (mail, phpmailer, queue)
- ✅ Web-Interface
- ✅ Queue-Management
- ✅ Standalone-Konfiguration
- ✅ Integration in Sitzungsverwaltung

---

## 🆘 Support

Bei Problemen oder Fragen:

1. **Dokumentation lesen** (diese Datei)
2. **Logs prüfen** (PHP Error Log, Apache Error Log)
3. **Test-Interface nutzen** (Web-Interface)
4. **Issue erstellen** (falls GitHub-Repo vorhanden)

---

## 📄 Lizenz

Dieses Script ist Teil der Sitzungsverwaltung und kann frei verwendet werden.

---

**Viel Erfolg! 🚀**
