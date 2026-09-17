# HTTP Basic Auth Setup für SSO-Modus

## Überblick

Statt Session-Sharing zwischen VTool und Sitzungsverwaltung nutzen wir HTTP Basic Auth:
1. Apache/Nginx authentifiziert User via LDAP (oder .htpasswd)
2. Username wird via `$_SERVER['PHP_AUTH_USER']` an PHP übergeben
3. System mappt Username → Mitgliedsnummer via `berechtigte`-Tabelle
4. User wird automatisch eingeloggt

## Vorteile

✅ Keine Session-Probleme mehr  
✅ Unabhängig vom VTool  
✅ Standard-Webserver-Auth (bewährt und sicher)  
✅ Funktioniert mit LDAP, Active Directory, .htpasswd  
✅ Kein PHP-Code für LDAP-Queries nötig  

## Setup-Schritte

### 1. Datenbank-Mapping prüfen

Die `berechtigte`-Tabelle muss ein Feld enthalten, das den Login-Namen speichert.

**Wichtig:** Passe `config_adapter.php` an, falls dein Feld anders heißt:

```php
// In get_mnr_by_username() Funktion (Zeile ~228):
$stmt = $pdo->prepare("
    SELECT MNr
    FROM berechtigte
    WHERE LOWER(Username) = LOWER(?)     -- ← Dein Login-Feld hier
       OR LOWER(EMail) = LOWER(?)
    LIMIT 1
");
```

**Mögliche Feldnamen:**
- `Username`, `login`, `user_login`
- `EMail`, `email`, `mail`
- `uid`, `sAMAccountName` (bei AD)

### 2. .htaccess konfigurieren

**Option A: Mit LDAP (empfohlen für Produktion)**

```bash
# .htaccess.ldap-example nach .htaccess kopieren
cp .htaccess.ldap-example .htaccess

# Dann anpassen:
nano .htaccess
```

Wichtige Einstellungen:
- `AuthLDAPURL`: Dein LDAP-Server
- `AuthLDAPBindDN`: Service-Account (falls benötigt)
- `Require`: Zugriffsbeschränkung

**Option B: Mit .htpasswd (für Tests)**

```bash
# .htpasswd erstellen
htpasswd -c .htpasswd testuser

# .htaccess anlegen:
cat > .htaccess << 'EOF'
AuthType Basic
AuthName "Sitzungsverwaltung"
AuthUserFile /absoluter/pfad/zu/.htpasswd
Require valid-user
EOF
```

### 3. config_adapter.php prüfen

Stelle sicher, dass diese Einstellungen gesetzt sind:

```php
define('REQUIRE_LOGIN', false);           // SSO-Modus aktiv
define('MEMBER_SOURCE', 'berechtigte');   // Berechtigte-Tabelle nutzen
define('SSO_SOURCE', 'http_auth');        // HTTP Auth verwenden
```

### 4. Apache-Module aktivieren (falls LDAP)

```bash
# LDAP-Module aktivieren
sudo a2enmod authnz_ldap
sudo a2enmod ldap

# Apache neu starten
sudo systemctl restart apache2
```

### 5. Testen

1. **Rufe die Sitzungsverwaltung auf:**
   - Browser sollte Basic Auth Dialog zeigen
   - Mit LDAP-Username + Passwort anmelden

2. **test_session_info.php aufrufen:**
   - Sollte nun die MNr anzeigen
   - Username sollte gemappt werden

3. **Bei Problemen: Apache Error Log prüfen:**
   ```bash
   sudo tail -f /var/log/apache2/error.log
   ```

## Debugging

### Username wird nicht erkannt

**Problem:** `$_SERVER['PHP_AUTH_USER']` ist leer

**Lösung:** Prüfe Apache-Konfiguration:
```apache
# In VirtualHost oder .htaccess:
<IfModule mod_authnz_ldap.c>
    AuthType Basic
    AuthBasicProvider ldap
    # ...
</IfModule>
```

### Username → MNr Mapping schlägt fehl

**Problem:** User wird nicht in `berechtigte`-Tabelle gefunden

**Debug:**
```sql
-- Prüfe, ob User in Tabelle existiert:
SELECT MNr, Username, EMail 
FROM berechtigte 
WHERE LOWER(Username) = 'testuser' 
   OR LOWER(EMail) = 'testuser@example.com';
```

**Lösung:** 
- Passe Feldnamen in `get_mnr_by_username()` an
- Stelle sicher, dass Username korrekt geschrieben ist (case-insensitive)

### LDAP-Verbindung schlägt fehl

**Problem:** "LDAP: Connection refused"

**Lösung:**
```bash
# LDAP-Server testen
ldapsearch -x -H ldap://ldap.example.com:389 -b "ou=users,dc=example,dc=com" uid=testuser

# Firewall prüfen
sudo ufw allow 389/tcp  # LDAP
sudo ufw allow 636/tcp  # LDAPS
```

## Sicherheitshinweise

🔒 **HTTPS verwenden:** HTTP Basic Auth ohne HTTPS = Klartext-Passwörter!

```apache
# In VirtualHost:
<VirtualHost *:443>
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
    # ...
</VirtualHost>
```

🔒 **LDAPS verwenden** (verschlüsselte LDAP-Verbindung):
```apache
AuthLDAPURL "ldaps://ldap.example.com:636/..."
```

🔒 **Session-Cookies sichern:**
```php
php_value session.cookie_secure 1
php_value session.cookie_httponly 1
```

## Rückfall auf Session-Modus

Falls HTTP Auth doch nicht funktioniert, einfach zurückwechseln:

```php
// In config_adapter.php:
define('SSO_SOURCE', 'session');  // Zurück zu Session
```

## Support

Bei Problemen:
1. Apache Error Log prüfen: `/var/log/apache2/error.log`
2. PHP Error Log prüfen: `/var/log/php/error.log`
3. `test_session_info.php` aufrufen für Debug-Infos
4. Logging in `config_adapter.php` aktiviert (siehe error_log Aufrufe)
