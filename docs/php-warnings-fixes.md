# PHP-Warnungen: Übersicht und Fixes

## Status der Error Suppression (@-Operator)

### Aktuell verwendete @ in nicht-deprecated Dateien:

**index.php (Zeile 374):**
```php
@include_once 'pseudo_cron.php';
```
- **Status**: ✅ OK
- **Grund**: Optional, Fehler werden geloggt
- **Action**: Kein Fix nötig

### DEPRECATED Dateien (werden ersetzt):

Die folgenden Dateien nutzen @-Operatoren, sind aber als DEPRECATED markiert:
- `ajax_get_protocol.php` → wird durch `api/meeting_get_updates.php` ersetzt
- `ajax_meeting_actions.php` → wird durch `api/meeting_actions.php` ersetzt

**Migration-Datum**: 03.12.2025

## Häufige PHP-Warning-Quellen

### 1. Undefined Array Key

**Problem:**
```php
// WARNUNG: Undefined array key 'field'
$value = $_POST['field'];
```

**Fix:**
```php
// Empfohlen (PHP 7.4+):
$value = $_POST['field'] ?? '';

// Alternative:
$value = isset($_POST['field']) ? $_POST['field'] : '';
```

**Status im Projekt**: ✅ Meiste Stellen verwenden bereits `??` oder `isset()`

### 2. Undefined Variable

**Problem:**
```php
// WARNUNG: Undefined variable $var
echo $var;
```

**Fix:**
```php
// Variable vor Nutzung initialisieren:
$var = '';
// oder prüfen:
if (isset($var)) {
    echo $var;
}
```

### 3. Session-Warnings

**Problem:**
```php
// WARNUNG: Session already started
session_start();
```

**Fix (bereits implementiert):**
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

**Status**: ✅ Bereits in index.php und external_participants_functions.php implementiert

### 4. Header already sent

**Problem:**
```php
// WARNUNG: Cannot modify header information - headers already sent
echo "Text";
header('Location: index.php');
```

**Fix:**
```php
// Header VOR jeder Ausgabe:
header('Location: index.php');
exit;
// Danach nichts mehr ausgeben
```

**Status**: ✅ Wird in allen wichtigen Dateien korrekt gehandhabt

## Error Reporting Einstellungen

### Produktivsystem (Empfehlung):
```php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php/sitzungstool_errors.log');
```

### Entwicklungssystem (aktuell in config.php):
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
```

**Status**: ✅ Aktuell im Debug-Modus (config.php Zeile 124-126)

## Spezifische Checks durchgeführt

### ✅ Session-Handling
- Alle `session_start()` Aufrufe prüfen Status vorher
- Keine doppelten Session-Starts

### ✅ Array-Zugriffe
- Datenbankabfragen nutzen `fetch()` mit null-check
- `$_POST`, `$_GET`, `$_SESSION` meist mit `??` oder `isset()`

### ✅ Include/Require
- Kritische Includes (config.php, functions.php) ohne @
- Nur optionale Includes (pseudo_cron.php) mit @ in try-catch

### ✅ Header-Redirects
- Alle `header('Location:')` gefolgt von `exit`
- Keine Ausgabe vor Headers

## Bekannte Warnungen (harmlos)

### 1. Pseudo-Cron
```
Warning: include_once(pseudo_cron.php): failed to open stream
```
- **Status**: Erwartet, wenn Datei nicht existiert
- **Handling**: In try-catch, wird geloggt
- **Action**: Keine

### 2. mysqli vs PDO
```
Deprecated: mysql_* functions are deprecated
```
- **Status**: Nicht vorhanden - System nutzt PDO
- **Action**: Keine

## Testing-Empfehlungen

### 1. Error-Log prüfen:
```bash
# PHP Error Log anzeigen
tail -f /var/log/php/error.log

# Apache Error Log
tail -f /var/log/apache2/error.log
```

### 2. Alle Formulare testen:
- Login/Logout
- Meeting erstellen/bearbeiten
- TOPs hinzufügen
- Abstimmungen
- Externe Umfragen

### 3. Browser-Console prüfen:
- JavaScript-Fehler
- Failed AJAX-Requests
- 404/500 Fehler

## Migration-Plan für DEPRECATED Dateien

**Bis 03.12.2025**:
1. Alle Aufrufe von `ajax_get_protocol.php` durch `api/meeting_get_updates.php` ersetzen
2. Alle Aufrufe von `ajax_meeting_actions.php` durch `api/meeting_actions.php` ersetzen
3. Alte Dateien löschen oder als `.deprecated` markieren

## Monitoring

### Log-Rotation einrichten:
```bash
# /etc/logrotate.d/sitzungstool
/var/log/php/sitzungstool_errors.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    missingok
}
```

### Automatisches Error-Reporting:
```php
// In config.php für Produktivsystem:
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[$errno] $errstr in $errfile:$errline");
    
    // Bei kritischen Fehlern Admin benachrichtigen
    if ($errno === E_ERROR || $errno === E_USER_ERROR) {
        mail('admin@example.com', 'Sitzungstool Error', $errstr);
    }
});
```

## Zusammenfassung

✅ **Session-Konfiguration**: An VTool angeglichen  
✅ **Error Suppression**: Minimal, nur wo nötig  
✅ **Array-Zugriffe**: Meist mit null-coalescing  
✅ **Error Reporting**: Debug-Modus aktiviert für Entwicklung  
⚠️ **DEPRECATED Dateien**: Migration bis 03.12.2025  
📝 **Empfehlung**: Produktivsystem Error-Reporting auf Log-Only umstellen
