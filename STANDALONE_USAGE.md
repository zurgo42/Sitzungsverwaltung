# Standalone-Nutzung von Terminplanung und Meinungsbild

## Verwendung aus einem anderen Verzeichnis

### Beispiel: Aufruf aus `/MTool/test.php`

```php
<?php
// DB-Verbindung herstellen
require_once '../Sitzungsverwaltung/config.php';
$pdo = new PDO(...);

// Mitgliedsnummer des eingeloggten Users
$MNr = '0495018';

// WICHTIG: Form-Action-Pfad setzen (relativ zum aktuellen Script)
$form_action_path = '../Sitzungsverwaltung/';

// Terminplanung laden
require_once '../Sitzungsverwaltung/terminplanung_simple.php';

// ODER: Meinungsbild laden
// require_once '../Sitzungsverwaltung/opinion_simple.php';
?>
```

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
 */

// Session starten
session_start();

// DB-Config laden
require_once '../Sitzungsverwaltung/config.php';

// PDO-Verbindung (anpassen an deine config.php)
$pdo = new PDO("mysql:host=localhost;dbname=mydb", "user", "password");

// Mitgliedsnummer aus Session oder Login
$MNr = $_SESSION['mnr'] ?? '0495018';

// Form-Action-Pfad setzen
$form_action_path = '../Sitzungsverwaltung/';

// Terminplanung einbinden
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
