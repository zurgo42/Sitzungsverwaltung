# Installationsanleitung - Sitzungsverwaltung

Diese Anleitung beschreibt die Einrichtung der Sitzungsverwaltung auf einem Webserver.

## Voraussetzungen

### Server-Anforderungen

- **Webserver**: Apache 2.4+ oder nginx
- **PHP**: Version 7.4 oder höher (empfohlen: PHP 8.0+)
- **MySQL/MariaDB**: Version 5.7+ / 10.2+
- **PHP-Extensions**:
  - `pdo_mysql`
  - `mbstring`
  - `session`
  - `json`

### Optionale Anforderungen

- **E-Mail-Versand**: SMTP-Server oder `mail()` Funktion
- **Cronjobs**: Für automatische Erinnerungen und Aufräumarbeiten
- **SSL-Zertifikat**: Empfohlen für produktive Umgebungen

## Installation

### 1. Dateien hochladen

Laden Sie alle Projektdateien in Ihr Webserver-Verzeichnis hoch:

```bash
# Via FTP, SFTP oder direkt auf dem Server:
/var/www/html/sitzungsverwaltung/
```

Oder klonen Sie das Repository:

```bash
git clone <repository-url> /var/www/html/sitzungsverwaltung
cd /var/www/html/sitzungsverwaltung
```

### 2. Konfiguration anpassen

Kopieren Sie die Beispiel-Konfiguration und passen Sie sie an:

```bash
cp config.example.php config.php
```

Öffnen Sie `config.php` und tragen Sie Ihre Daten ein:

```php
<?php
// Datenbank-Verbindung
define('DB_HOST', 'localhost');        // Datenbank-Server
define('DB_NAME', 'sitzungsverwaltung');  // Datenbankname
define('DB_USER', 'ihr_db_benutzer');  // Datenbank-Benutzer
define('DB_PASS', 'ihr_db_passwort');  // Datenbank-Passwort

// E-Mail-Konfiguration
define('MAIL_FROM', 'noreply@ihre-domain.de');
define('MAIL_FROM_NAME', 'Sitzungsverwaltung');

// Optional: PHPMailer SMTP
define('SMTP_HOST', 'smtp.ihre-domain.de');
define('SMTP_PORT', 587);
define('SMTP_USER', 'smtp_benutzer');
define('SMTP_PASS', 'smtp_passwort');
define('SMTP_SECURE', 'tls');  // 'tls' oder 'ssl'

// Basis-URL für Links (für E-Mail-Benachrichtigungen)
define('BASE_URL', 'https://ihre-domain.de/sitzungsverwaltung');
?>
```

### 3. Datenbank einrichten

#### Option A: Via init-db.php (Empfohlen für Neuinstallationen)

1. Öffnen Sie im Browser: `https://ihre-domain.de/sitzungsverwaltung/init-db.php`
2. Das Skript erstellt automatisch:
   - Die Datenbank (falls sie noch nicht existiert)
   - Alle 24 Tabellen
   - Den erforderlichen Trigger
   - 13 Meinungsbild-Templates
3. Folgen Sie den Anweisungen auf dem Bildschirm

#### Option B: Via MySQL-Kommandozeile

```bash
# MySQL-Konsole öffnen
mysql -u root -p

# Datenbank erstellen
CREATE DATABASE sitzungsverwaltung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Benutzer erstellen und Rechte vergeben
CREATE USER 'sitzung_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
GRANT ALL PRIVILEGES ON sitzungsverwaltung.* TO 'sitzung_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Datenbank-Struktur importieren
mysql -u sitzung_user -p sitzungsverwaltung < /pfad/zu/init-db.sql
```

Hinweis: Es gibt keine `init-db.sql` Datei. Verwenden Sie stattdessen `init-db.php` über den Browser oder führen Sie die SQL-Befehle aus den Migrations-Dateien manuell aus.

#### Option C: Via phpMyAdmin

1. Öffnen Sie phpMyAdmin
2. Erstellen Sie eine neue Datenbank: `sitzungsverwaltung`
   - Zeichensatz: `utf8mb4`
   - Kollation: `utf8mb4_unicode_ci`
3. Führen Sie `init-db.php` über den Browser aus (siehe Option A)

### 4. Berechtigungen setzen

Stellen Sie sicher, dass der Webserver Schreibrechte auf bestimmte Verzeichnisse hat:

```bash
# Falls vorhanden: Upload-Verzeichnisse
chmod 755 /var/www/html/sitzungsverwaltung
chown -R www-data:www-data /var/www/html/sitzungsverwaltung

# Session-Verzeichnis (falls custom)
chmod 700 sessions/
```

### 5. Demo-Daten laden (Optional)

Für Testzwecke oder zum Kennenlernen der Anwendung:

1. Öffnen Sie im Browser: `https://ihre-domain.de/sitzungsverwaltung/demo.php`
2. Das Skript erstellt:
   - 8 Test-Mitglieder mit verschiedenen Rollen
   - Ein Beispiel-Meeting mit Tagesordnungspunkten
   - Beispiel-Kommentare und ToDos

**Standard-Login-Daten nach Demo-Installation:**

| Rolle | E-Mail | Passwort |
|-------|--------|----------|
| Vorstand | test@example.com | test123 |
| Vorstand 2 | vorstand2@example.com | test123 |
| GF | gf@example.com | test123 |
| Assistenz | assistenz@example.com | test123 |
| Führungsteam 1 | ft1@example.com | test123 |
| Führungsteam 2 | ft2@example.com | test123 |
| Mitglied 1 | mitglied1@example.com | test123 |
| Mitglied 2 | mitglied2@example.com | test123 |

**WICHTIG:** Ändern Sie diese Passwörter sofort oder löschen Sie die Test-Accounts in einer Produktivumgebung!

### 6. Erste Schritte

1. Öffnen Sie die Anwendung: `https://ihre-domain.de/sitzungsverwaltung/`
2. Melden Sie sich an (mit Demo-Daten oder erstellen Sie manuell den ersten Benutzer)
3. Passen Sie unter "Einstellungen" die Anwendung an Ihre Bedürfnisse an

## Cronjobs einrichten (Optional aber empfohlen)

Für automatische E-Mail-Erinnerungen und Aufräumarbeiten:

```bash
# Crontab bearbeiten
crontab -e

# Folgende Zeilen hinzufügen:

# Poll-Erinnerungen prüfen (täglich um 8:00 Uhr)
0 8 * * * /usr/bin/php /var/www/html/sitzungsverwaltung/cron_poll_reminders.php

# Abgelaufene Opinion-Polls löschen (täglich um 2:00 Uhr)
0 2 * * * /usr/bin/php /var/www/html/sitzungsverwaltung/cron_delete_expired_opinions.php

# E-Mail-Warteschlange abarbeiten (alle 5 Minuten)
*/5 * * * * /usr/bin/php /var/www/html/sitzungsverwaltung/cron_process_mail_queue.php
```

Testen Sie die Cronjobs manuell:

```bash
php /var/www/html/sitzungsverwaltung/cron_poll_reminders.php
```

## E-Mail-Konfiguration

### Variante 1: PHP mail() Funktion

Keine weitere Konfiguration nötig. Die Anwendung verwendet automatisch die `mail()` Funktion.

**Voraussetzung:** Der Server muss korrekt für E-Mail-Versand konfiguriert sein (Postfix, Sendmail, etc.)

### Variante 2: SMTP (empfohlen)

1. Installieren Sie PHPMailer (falls nicht vorhanden):

```bash
composer require phpmailer/phpmailer
```

Oder laden Sie PHPMailer manuell herunter und kopieren Sie es nach `/lib/PHPMailer/`

2. Konfigurieren Sie SMTP in `config.php` (siehe Schritt 2)

3. Die Anwendung erkennt automatisch, ob PHPMailer verfügbar ist

### Variante 3: E-Mail-Warteschlange

Für größere Installationen empfohlen:

1. E-Mails werden in die Tabelle `mail_queue` geschrieben
2. Der Cronjob `cron_process_mail_queue.php` versendet sie asynchron
3. Konfigurieren Sie den Cronjob (siehe oben)

## Sicherheit

### Produktivumgebung

**Vor dem Live-Gang:**

1. **Demo-Daten löschen:**
   ```sql
   DELETE FROM members WHERE email LIKE '%@example.com';
   ```

2. **Admin-Account erstellen:**
   - Erstellen Sie einen sicheren Admin-Account mit starkem Passwort
   - Weisen Sie die Rolle `vorstand` oder `gf` zu

3. **config.php schützen:**
   ```bash
   chmod 600 config.php
   ```

4. **init-db.php und demo.php löschen oder schützen:**
   ```bash
   rm init-db.php demo.php
   # Oder:
   chmod 000 init-db.php demo.php
   ```

5. **HTTPS erzwingen:**
   - Richten Sie ein SSL-Zertifikat ein (Let's Encrypt empfohlen)
   - Leiten Sie HTTP auf HTTPS um

6. **.htaccess hinzufügen** (falls Apache):
   ```apache
   # .htaccess
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

   # Verzeichnis-Listing deaktivieren
   Options -Indexes

   # PHP-Dateien im Verzeichnis 'migrations' blockieren
   <FilesMatch "\.sql$">
       Require all denied
   </FilesMatch>
   ```

7. **Regelmäßige Backups einrichten:**
   ```bash
   # Backup-Skript (täglich)
   mysqldump -u sitzung_user -p sitzungsverwaltung > backup_$(date +%Y%m%d).sql
   ```

## Fehlersuche

### Problem: "Datenbank-Verbindungsfehler"

**Lösung:**
- Prüfen Sie `config.php`: Sind Host, Datenbankname, Benutzer und Passwort korrekt?
- Testen Sie die MySQL-Verbindung manuell: `mysql -u benutzer -p -h host datenbankname`
- Prüfen Sie, ob der MySQL-Server läuft: `systemctl status mysql`

### Problem: "Seite wird nicht angezeigt / 500 Error"

**Lösung:**
- Aktivieren Sie PHP-Fehleranzeige temporär in `index.php`:
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```
- Prüfen Sie die PHP-Error-Logs: `/var/log/apache2/error.log` oder `/var/log/php-fpm/error.log`
- Prüfen Sie Dateiberechtigungen

### Problem: "E-Mails werden nicht versendet"

**Lösung:**
- Prüfen Sie die SMTP-Konfiguration in `config.php`
- Testen Sie den SMTP-Server manuell: `telnet smtp.example.com 587`
- Prüfen Sie die `mail_queue` Tabelle auf Fehler:
  ```sql
  SELECT * FROM mail_queue WHERE status = 'failed';
  ```
- Prüfen Sie die Cronjob-Logs

### Problem: "Session-Fehler / Automatischer Logout"

**Lösung:**
- Prüfen Sie PHP-Session-Einstellungen in `php.ini`:
  ```ini
  session.gc_maxlifetime = 1440
  session.cookie_lifetime = 0
  ```
- Prüfen Sie, ob das Session-Verzeichnis beschreibbar ist: `/var/lib/php/sessions`

## Support und Weiterentwicklung

- **Dokumentation:** Siehe `README.md` für Benutzer-Handbuch
- **Technische Dokumentation:** Siehe `DEVELOPER.md`
- **Meinungsbild-Tool:** Siehe `OPINION_TOOL_README.md`

## Update von älteren Versionen

Falls Sie eine ältere Version aktualisieren:

1. **Backup erstellen:**
   ```bash
   mysqldump -u benutzer -p sitzungsverwaltung > backup_vor_update.sql
   ```

2. **Migrations ausführen:**

   Prüfen Sie das Verzeichnis `migrations/` auf neue Migrations-Dateien und führen Sie diese aus:

   ```bash
   mysql -u benutzer -p sitzungsverwaltung < migrations/create_polls.sql
   mysql -u benutzer -p sitzungsverwaltung < migrations/add_poll_reminders.sql
   mysql -u benutzer -p sitzungsverwaltung < migrations/create_opinion_polls.sql
   mysql -u benutzer -p sitzungsverwaltung < migrations/insert_opinion_templates.sql
   ```

3. **Dateien aktualisieren:**
   - Laden Sie alle neuen PHP-Dateien hoch
   - Überschreiben Sie NICHT Ihre `config.php`!

4. **Testen:**
   - Melden Sie sich an und prüfen Sie die Funktionen
   - Prüfen Sie die Cronjobs

---

**Viel Erfolg mit Ihrer Sitzungsverwaltung!**
