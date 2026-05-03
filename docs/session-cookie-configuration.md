# Session- und Cookie-Konfiguration

## Session-Konfiguration (config.php)

Die Session-Konfiguration ist **identisch zum VTool** für Cookie-Sharing zwischen beiden Systemen:

```php
// Session-Konfiguration (MUSS identisch zu VTool sein für Cookie-Sharing!)
ini_set('session.cookie_path', '/');              // Cookie für gesamte Domain
ini_set('session.cookie_httponly', 1);            // Schutz vor XSS
ini_set('session.cookie_samesite', 'Lax');        // CSRF-Schutz
ini_set('session.use_only_cookies', 1);           // Nur Cookies, keine URL-Parameter

// HTTPS-Sicherheit (nur wenn HTTPS aktiv)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);          // Cookie nur über HTTPS
}
```

### Wichtige Punkte:

1. **cookie_path = '/'**: Cookie gilt für die gesamte Domain
   - Ermöglicht Cookie-Sharing zwischen Sitzungstool und VTool
   - Beide Systeme können dieselbe Session verwenden

2. **cookie_httponly = 1**: Schutz vor XSS-Angriffen
   - JavaScript kann nicht auf Session-Cookie zugreifen
   - Reduziert Risiko von Session-Hijacking

3. **cookie_samesite = 'Lax'**: CSRF-Schutz
   - Cookie wird bei Top-Level-Navigation mitgesendet
   - Nicht bei Cross-Site-Requests (z.B. von anderen Domains)

4. **use_only_cookies = 1**: Keine Session-ID in URLs
   - Verhindert Session-Fixation-Angriffe
   - Session-ID nur über Cookies, nicht über GET-Parameter

5. **cookie_secure = 1** (nur bei HTTPS):
   - Cookie wird nur über verschlüsselte Verbindungen gesendet
   - Schutz vor Man-in-the-Middle-Angriffen

## Cookie-Laufzeiten im System

### 1. Session-Cookie (PHP-Session)
- **Name**: PHPSESSID (Standard)
- **Laufzeit**: Browser-Session (gelöscht beim Schließen)
- **Konfiguration**: PHP-Standard (session.cookie_lifetime = 0)
- **Zweck**: Benutzer-Login, aktuelle Session

### 2. Externe Teilnehmer-Cookie
- **Name**: `sv_external_participant`
- **Laufzeit**: **7 Tage** (604.800 Sekunden)
- **Datei**: `external_participants_functions.php` (Zeile 48)
- **Zweck**: Wiedererkennung bei zukünftigen externen Umfragen
- **Inhalt**: JSON mit first_name, last_name, email

```php
// Cookie für 7 Tage speichern
$expires = time() + (7 * 24 * 60 * 60);
setcookie('sv_external_participant', $cookie_data, $expires, '/', '', false, true);
```

### 3. SSO-Remember-Cookie (falls aktiviert)
- **Name**: `sv_remember_token` (falls implementiert)
- **Laufzeit**: Konfigurierbar (typisch 30 Tage)
- **Zweck**: "Angemeldet bleiben"-Funktion

## Session-Lebensdauer

### Server-seitig (PHP)
- **session.gc_maxlifetime**: PHP-Standard (1440 Sekunden = 24 Minuten)
- **Bedeutung**: Inaktive Sessions werden nach 24 Minuten gelöscht
- **Anpassbar**: In config.php via `ini_set('session.gc_maxlifetime', 3600);`

### Client-seitig (Cookie)
- **session.cookie_lifetime**: 0 (Browser-Session)
- **Bedeutung**: Cookie wird beim Schließen des Browsers gelöscht
- **Ausnahme**: "Angemeldet bleiben" nutzt separaten Remember-Token

## Kompatibilität mit VTool

### Identische Einstellungen (✓)
- ✅ `session.cookie_path = '/'`
- ✅ `session.cookie_httponly = 1`
- ✅ `session.cookie_samesite = 'Lax'`
- ✅ `session.cookie_secure = 1` (bei HTTPS)

### Unterschiede (falls vorhanden)
- **VTool**: Verwendet mysqli (altes MySQL-Interface)
- **Sitzungstool**: Verwendet PDO (modernes Interface)
- **Auswirkung**: Keine - beide können dieselbe Session teilen

## Empfehlungen

### Für Produktivsystem:
1. **HTTPS erzwingen**: Immer cookie_secure aktivieren
2. **Session-Timeout**: Bei Bedarf auf 1 Stunde erhöhen:
   ```php
   ini_set('session.gc_maxlifetime', 3600);
   ```
3. **Session-Regeneration**: Nach Login neue Session-ID generieren:
   ```php
   session_regenerate_id(true);
   ```

### Für Demo-System:
1. Cookie-Settings bleiben wie konfiguriert
2. Längere Session-Laufzeit möglich (weniger Timeouts)
3. HTTPS optional (aber empfohlen)

## Sicherheits-Checkliste

- ✅ Session-Cookies nur über HTTP (httponly)
- ✅ CSRF-Schutz via SameSite
- ✅ Keine Session-IDs in URLs
- ✅ Cookie-Pfad auf '/' für Domain-weite Gültigkeit
- ✅ HTTPS-Only bei Produktivsystem (cookie_secure)
- ⚠️ Session-Regeneration nach Login implementieren
- ⚠️ Session-Timeout bei Inaktivität prüfen

## Debugging

### Cookie prüfen:
```javascript
// Im Browser-Console
document.cookie
```

### Session prüfen:
```php
// In PHP
var_dump($_SESSION);
var_dump(session_id());
```

### Cookie-Laufzeit testen:
```php
// Aktuelles Timestamp + Ablaufzeit
echo "Externe Teilnehmer Cookie läuft ab: " . 
     date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60));
```

## Änderungshistorie

- **2026-05-03**: Session-Konfiguration an VTool angeglichen
- **2025-12-18**: Externe Teilnehmer-Cookie auf 7 Tage geändert (vorher 30 Tage)
