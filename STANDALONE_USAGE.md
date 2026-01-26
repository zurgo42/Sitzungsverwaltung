# Standalone-Nutzung von Terminplanung und Meinungsbild

## Verwendung aus einem anderen Verzeichnis

### Beispiel: Aufruf aus `/MTool/test.php`

```php
<?php
/**
 * WICHTIGE REIHENFOLGE:
 * 1. Session-Konfiguration (ini_set)
 * 2. Session starten (session_start)
 * 3. Config/DB-Verbindung laden
 * 4. Simple-Script includieren
 */

// SCHRITT 1: Session-Konfiguration (MUSS vor session_start() kommen!)
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', 1);

// Falls HTTPS:
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// SCHRITT 2: Session starten
session_start();

// SCHRITT 3: DB-Verbindung herstellen
require_once '../Sitzungsverwaltung/config.php';

// Mitgliedsnummer des eingeloggten Users
$MNr = $_SESSION['MNr'] ?? '0495018';

// WICHTIG: Form-Action-Pfad setzen (relativ zum aktuellen Script)
$form_action_path = '../Sitzungsverwaltung/';

// SCHRITT 4: Terminplanung laden
require_once '../Sitzungsverwaltung/terminplanung_simple.php';

// ODER: Meinungsbild laden
// require_once '../Sitzungsverwaltung/opinion_simple.php';
?>
```

### WICHTIG: Session-Konfiguration

**KRITISCHE REIHENFOLGE:**
Die Session-Einstellungen (`ini_set()`) müssen **VOR** `session_start()` gesetzt werden!

```php
// ✅ RICHTIG:
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// ❌ FALSCH:
session_start();
ini_set('session.cookie_path', '/');  // Zu spät! Gibt Warnings
```

**Hinweis:** `config.php` setzt die Session-Einstellungen automatisch, wenn die Session noch nicht aktiv ist. Du hast also zwei Optionen:

**Option 1 (empfohlen):** Session im aufrufenden Script konfigurieren und starten
```php
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once '../Sitzungsverwaltung/config.php';  // Überspringt ini_set()
```

**Option 2:** Config laden, dann Session starten
```php
require_once '../Sitzungsverwaltung/config.php';  // Setzt ini_set() automatisch
session_start();
```

Beide Optionen funktionieren - wichtig ist nur, dass die Session-Einstellungen identisch sind!

### Erklärung

**`$form_action_path`** - Der relative Pfad vom aufrufenden Script zum Sitzungsverwaltung-Verzeichnis.

- Wenn du aus `/MTool/test.php` aufrufst → `$form_action_path = '../Sitzungsverwaltung/';`
- Wenn du aus `/tools/myapp.php` aufrufst → `$form_action_path = '../Sitzungsverwaltung/';`
- Wenn du direkt aus `/Sitzungsverwaltung/` aufrufst → `$form_action_path = '';` (leer)

### Was passiert im Standalone-Modus?

✅ **Angezeigt:**
- Formular zum Erstellen von Terminumfragen/Meinungsbildern
- Nur Option "Individuell (Link)"
- Bestehende Umfragen/Meinungsbilder (damit User antworten können)

❌ **Ausgeblendet:**
- Benachrichtigungen/Abwesenheiten
- Option "Ausgewählte registrierte Teilnehmer"
- Teilnehmerliste
- Vorgefertigte Gruppen-Buttons (Vorstand, Führungsteam, etc.)

## Vollständiges Beispiel

```php
<?php
/**
 * Beispiel: test.php in /MTool/
 *
 * WICHTIG: Reihenfolge beachten!
 */

// SCHRITT 1: Session-Konfiguration (MUSS vor session_start!)
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', 1);

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// SCHRITT 2: Session starten
session_start();

// SCHRITT 3: DB-Config und Verbindung
require_once '../Sitzungsverwaltung/config.php';
// $pdo wird in config.php initialisiert

// SCHRITT 4: Mitgliedsnummer aus Session oder Login
$MNr = $_SESSION['MNr'] ?? '0495018';

// SCHRITT 5: Form-Action-Pfad setzen
$form_action_path = '../Sitzungsverwaltung/';

// SCHRITT 6: Terminplanung einbinden
require_once '../Sitzungsverwaltung/terminplanung_simple.php';
?>
```

## Fehlerbehebung

### Problem: "Fatal error: Call to undefined function get_user_data()"
**Lösung:** Die benötigten Dateien werden automatisch geladen, stelle sicher dass `terminplanung_simple.php` oder `opinion_simple.php` korrekt includiert wurde.

### Problem: "Formular führt zu 404 Not Found"
**Lösung:** Setze `$form_action_path` BEVOR du das Simple-Script includierst. Der Pfad muss relativ zum aufrufenden Script sein.

### Problem: "Benutzer nicht gefunden"
**Lösung:** Die MNr muss entweder in der `berechtigte`-Tabelle oder in LDAP existieren. Prüfe mit:
```php
$stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE MNr = ?");
$stmt->execute([$MNr]);
var_dump($stmt->fetch());
```

### Problem: "Nach Submit landet man auf welcome.php"
**Lösung:** Die Session-Konfiguration ist unterschiedlich. Stelle sicher dass:
1. `session.cookie_path` in beiden Systemen identisch ist (z.B. `/`)
2. Die Session-Einstellungen VOR `session_start()` gesetzt werden
3. Die `$form_action_path` Variable korrekt gesetzt ist

### Problem: "Session abgelaufen" nach Submit
**Lösung:** Die Process-Skripte (`process_termine.php`, `process_opinion.php`) können den User nicht aus der Session laden. Das passiert wenn:
1. Die Session-Cookie-Einstellungen unterschiedlich sind
2. Die MNr in der Session nicht korrekt gesetzt ist
3. Die Simple-Scripts nicht korrekt die Standalone-Session-Variablen setzen

### Problem: "Warning: ini_set(): Session ini settings cannot be changed when a session is active"
**Lösung:** Dieses Problem sollte mit der aktuellen Version nicht mehr auftreten, da `config.php` die Session-Einstellungen nur setzt, wenn die Session noch nicht aktiv ist.

Falls das Problem dennoch auftritt:
1. Stelle sicher, dass du die neueste Version von `config.php` verwendest
2. Die Session-Einstellungen müssen VOR `session_start()` gesetzt werden

**Korrekte Reihenfolge (beide funktionieren):**
```php
// Option 1: Session selbst konfigurieren und starten
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once '../Sitzungsverwaltung/config.php';  // Überspringt ini_set()

// Option 2: config.php die Session konfigurieren lassen
require_once '../Sitzungsverwaltung/config.php';  // Setzt ini_set()
session_start();
```
