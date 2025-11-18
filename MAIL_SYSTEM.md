# E-Mail-System - Dokumentation

## Übersicht

Das E-Mail-System unterstützt drei verschiedene Backends:

1. **`mail`** - Standard PHP mail() (empfohlen als Basis)
2. **`phpmailer`** - PHPMailer library mit SMTP-Support
3. **`queue`** - Datenbank-Queue mit Cronjob-Versand

Alle Backends haben automatische Fallbacks auf `mail` wenn Probleme auftreten.

---

## Backend 1: Standard PHP mail()

### Verwendung
Setzen in `config.php`:
```php
define('MAIL_BACKEND', 'mail');
```

### Vorteile
- ✅ Funktioniert auf jedem Server ohne zusätzliche Installation
- ✅ Einfach und zuverlässig
- ✅ Keine zusätzlichen Dependencies

### Nachteile
- ❌ Keine SMTP-Authentifizierung
- ❌ Kann bei manchen Providern als Spam markiert werden
- ❌ Keine Kontrolle über Versandgeschwindigkeit

### Empfohlen für
- Entwicklungsumgebung
- Server mit funktionierendem Sendmail
- Geringes E-Mail-Aufkommen

---

## Backend 2: PHPMailer mit SMTP

### Installation
```bash
# Via Composer (empfohlen)
composer require phpmailer/phpmailer

# Oder: Manuelle Installation
# Download von https://github.com/PHPMailer/PHPMailer
# In vendor/phpmailer/ entpacken
```

### Konfiguration in config.php
```php
define('MAIL_BACKEND', 'phpmailer');
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);  // 587=TLS, 465=SSL, 25=unverschlüsselt
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);
define('SMTP_USER', 'user@example.com');
define('SMTP_PASS', 'passwort');
```

### Vorteile
- ✅ Professioneller SMTP-Versand
- ✅ Authentifizierung möglich
- ✅ TLS/SSL-Verschlüsselung
- ✅ Bessere Zustellrate
- ✅ Weniger Spam-Probleme

### Nachteile
- ❌ Benötigt PHPMailer-Installation
- ❌ SMTP-Zugangsdaten erforderlich
- ❌ Keine Kontrolle über Versandgeschwindigkeit

### Empfohlen für
- Produktivserver mit SMTP-Zugang
- Professioneller E-Mail-Versand
- Server ohne funktionierendes Sendmail

### Fallback
Falls PHPMailer nicht installiert ist, wird automatisch auf `mail` zurückgegriffen.

---

## Backend 3: Queue-System (Datenbank + Cronjob)

### Installation

#### 1. Datenbank-Tabelle erstellen
```bash
# Via MySQL:
mysql -u username -p databasename < database_update_mail_queue.sql

# Oder via phpMyAdmin:
# SQL-Tab -> Inhalt von database_update_mail_queue.sql einfügen -> Ausführen
```

#### 2. Cronjob einrichten
```bash
# Crontab bearbeiten:
crontab -e

# Folgende Zeile hinzufügen (alle 5 Minuten):
*/5 * * * * /usr/bin/php /pfad/zu/Sitzungsverwaltung/process_mail_queue.php >> /var/log/mail_queue.log 2>&1
```

### Konfiguration in config.php
```php
define('MAIL_BACKEND', 'queue');
define('MAIL_QUEUE_BATCH_SIZE', 10);    // Mails pro Cronjob-Durchlauf
define('MAIL_QUEUE_DELAY', 1);          // Sekunden zwischen Mails
define('MAIL_QUEUE_MAX_ATTEMPTS', 3);   // Max. Zustellversuche
```

### Vorteile
- ✅ **Verhindert Provider-Blockierungen** durch kontrollierte Versandgeschwindigkeit
- ✅ Automatische Wiederholungsversuche bei Fehlern
- ✅ Priorisierung möglich
- ✅ Keine Verzögerung der Web-Requests
- ✅ Zeitgesteuerter Versand möglich
- ✅ Monitoring über Datenbank

### Nachteile
- ❌ Cronjob erforderlich
- ❌ Verzögerung beim Versand (abhängig von Cronjob-Intervall)
- ❌ Zusätzliche Datenbank-Tabelle

### Empfohlen für
- **Server mit strengen Mail-Limits** (z.B. Shared Hosting)
- Hohe E-Mail-Volumina
- Provider die bei zu vielen Mails blockieren
- Zeitgesteuerte Mail-Kampagnen

### Manueller Versand (Test/Debug)
```bash
# Via CLI:
php process_mail_queue.php

# Via Browser (nur für Admins):
https://example.com/process_mail_queue.php
```

### Queue-Status überwachen
```sql
-- Offene Mails
SELECT COUNT(*) FROM mail_queue WHERE status = 'pending';

-- Fehlgeschlagene Mails
SELECT * FROM mail_queue WHERE status = 'failed';

-- Letzte versendete Mails
SELECT * FROM mail_queue WHERE status = 'sent' ORDER BY sent_at DESC LIMIT 10;
```

---

## Empfehlungen nach Server-Typ

### XAMPP / Lokale Entwicklung
```php
define('MAIL_BACKEND', 'mail');
define('MAIL_ENABLED', false);  // Kein echter Versand in Entwicklung
```

### Shared Hosting mit Mail-Limits
```php
define('MAIL_BACKEND', 'queue');
define('MAIL_QUEUE_BATCH_SIZE', 5);   // Kleine Batches
define('MAIL_QUEUE_DELAY', 2);         // Längere Pause
```

### VPS/Dedicated Server mit SMTP
```php
define('MAIL_BACKEND', 'phpmailer');
// + SMTP-Einstellungen
```

### Einfacher Webspace mit Sendmail
```php
define('MAIL_BACKEND', 'mail');
```

---

## Troubleshooting

### Mails kommen nicht an
1. **Prüfen:** `MAIL_ENABLED` = `true` in config.php?
2. **Log prüfen:** Error-Log des Servers ansehen
3. **Spam-Ordner:** Empfänger prüfen lassen
4. **Test-Mail:** `send_test_mail('test@example.com')` aufrufen

### Queue wird nicht verarbeitet
1. **Cronjob aktiv?** `crontab -l` prüfen
2. **Log ansehen:** `/var/log/mail_queue.log`
3. **Manuell testen:** `php process_mail_queue.php`
4. **Rechte prüfen:** process_mail_queue.php ausführbar?

### PHPMailer nicht gefunden
1. **Installation prüfen:** `composer require phpmailer/phpmailer`
2. **Autoload vorhanden?** vendor/autoload.php existiert?
3. **Fallback:** System nutzt automatisch `mail` als Fallback

---

## Migration zwischen Backends

### Von mail → queue
1. Datenbank-Update ausführen (database_update_mail_queue.sql)
2. Cronjob einrichten
3. `MAIL_BACKEND` auf `queue` setzen
4. Testen mit Test-Mail

### Von queue → mail
1. Noch offene Queue-Mails versenden: `php process_mail_queue.php`
2. `MAIL_BACKEND` auf `mail` ändern
3. Cronjob kann deaktiviert bleiben (stört nicht)

---

## Code-Beispiele

### Einfache Mail senden
```php
require_once 'mail_functions.php';

$result = multipartmail(
    'empfaenger@example.com',
    'Betreff',
    'Text-Version der Nachricht',
    '<p>HTML-Version der Nachricht</p>'
);
```

### Test-Mail senden
```php
require_once 'mail_functions.php';
send_test_mail('ihre@email.com');
```

### Mail mit Queue-Priorität
```php
// Hohe Priorität (wird zuerst versendet)
// Direkt in mail_queue einfügen:
$stmt = $pdo->prepare("
    INSERT INTO mail_queue
    (recipient, subject, message_text, message_html, from_email, from_name, status, priority)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', 10)
");
$stmt->execute(['user@example.com', 'Wichtig!', 'Text', '<p>HTML</p>',
                MAIL_FROM, MAIL_FROM_NAME]);
```

---

## Sicherheit

- ✅ Alle Backends nutzen UTF-8 Encoding
- ✅ HTML wird nicht escaped (bereits in Nachricht enthalten)
- ✅ From/Reply-To Header korrekt gesetzt
- ✅ process_mail_queue.php ist admin-geschützt bei Browser-Aufruf
- ✅ Keine E-Mail-Injection möglich

---

## Support

Bei Fragen oder Problemen:
1. Error-Log prüfen
2. `send_test_mail()` aufrufen
3. Queue-Status in Datenbank prüfen
4. Fallback auf `mail` testen
