# SSOdirekt-Modus - Anleitung (AKTUALISIERT)

## Übersicht

Der **SSOdirekt-Modus** ist die empfohlene Methode zur Integration der Sitzungsverwaltung in Ihr bestehendes System. Im Gegensatz zur iframe-Einbindung läuft die Sitzungsverwaltung als eigenständige Seite mit angepasstem Design.

**Status:** ⚠️ Diese Anleitung wurde aktualisiert (Dez 2025) - behebt kritische Bugs in der ursprünglichen Version!

## Vorteile gegenüber iframe

| Feature | iframe | SSOdirekt |
|---------|--------|-----------|
| GET-Parameter funktionieren | ❌ Problematisch | ✅ Vollständig |
| Formulare funktionieren | ❌ Müssen angepasst werden | ✅ Out-of-the-box |
| Styling anpassbar | ⚠️ Begrenzt | ✅ Vollständig |
| Browser History | ❌ Nein | ✅ Ja |
| Performance | ⚠️ Doppeltes Laden | ✅ Optimal |

---

## ⚠️ WICHTIG: Voraussetzungen

**Bevor Sie beginnen, stellen Sie sicher:**

1. **Session-Sharing**: Ihr Hauptsystem und die Sitzungsverwaltung müssen die GLEICHE PHP-Session nutzen
2. **Cookie-Path**: Session-Cookie muss Path `/` haben (nicht `/vtool/` oder ähnliches)
3. **Session-Variable**: Ihr SSO-System muss `$_SESSION['MNr']` setzen (exakt dieser Name!)
4. **Mitgliederdaten**: User müssen in der `berechtigte`-Tabelle existieren

---

## Schritt 1: config_adapter.php für SSO konfigurieren

**KRITISCH:** Öffnen Sie `/Sitzungsverwaltung/config_adapter.php` und setzen Sie:

```php
<?php
// ============================================
// SSO-MODUS AKTIVIEREN (KRITISCH!)
// ============================================

// WICHTIG: Für SSO muss dies FALSE sein!
define('REQUIRE_LOGIN', false);  // ← SSO-Modus aktivieren

// Woher kommt die Mitgliedsnummer im SSO-Modus?
define('SSO_SOURCE', 'session');  // ← Aus $_SESSION['MNr'] lesen

// Welche Tabelle für Mitgliederdaten verwenden?
define('MEMBER_SOURCE', 'berechtigte');  // ← Ihre externe Mitgliedertabelle

// ============================================
// SSOdirekt STYLING-KONFIGURATION
// ============================================

$SSO_DIRECT_CONFIG = [
    // === STYLING ===
    // Passen Sie diese Farben an Ihr Vereins-Design an
    'primary_color' => '#1976d2',          // Header/Footer Hintergrund
    'border_color' => '#0d47a1',           // Border-Farbe
    'header_text_color' => '#ffffff',      // Text-Farbe im Header
    'footer_text_color' => '#ffffff',      // Text-Farbe im Footer

    // Logo (relativ oder absolut)
    'logo_path' => '/img/logo.png',        // Pfad zu Ihrem Logo
    'logo_height' => '40px',

    // === NAVIGATION ===
    // Text und URL für den Zurück-Button
    'back_button_text' => 'Zurück zum VTool',
    'back_button_url' => 'https://ihre-domain.de/vtool.php',

    // === FOOTER ===
    // HTML-Inhalt für Footer (kann Links, Styling etc. enthalten)
    'footer_html' => '<p style="margin: 0;">© 2025 Ihr Verein e.V. | <a href="/impressum" style="color: inherit;">Impressum</a></p>',

    // === SEITEN-TITEL ===
    'page_title' => 'Sitzungsverwaltung - Ihr Verein e.V.',
];
?>
```

**⚠️ ACHTUNG:** Ohne `REQUIRE_LOGIN = false` funktioniert SSO NICHT!

### Farbbeispiele

**Mensa-Design (Blau):**
```php
'primary_color' => '#1976d2',
'border_color' => '#0d47a1',
```

**Grün:**
```php
'primary_color' => '#4caf50',
'border_color' => '#2e7d32',
```

**Rot:**
```php
'primary_color' => '#d32f2f',
'border_color' => '#b71c1c',
```

**Dunkel:**
```php
'primary_color' => '#424242',
'border_color' => '#212121',
```

---

## Schritt 2: SSO im Hauptsystem einrichten

**KRITISCH:** Die Session-Variable muss EXAKT `$_SESSION['MNr']` heißen!

In Ihrer Hauptanwendung (z.B. `vtool.php`, `auth.php`):

```php
<?php
// Session-Konfiguration (BEIDE Systeme müssen gleich sein!)
ini_set('session.cookie_path', '/');           // ← Cookie für gesamte Domain
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

// Falls HTTPS:
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start();

// Nach erfolgreicher SSO-Authentifizierung:
$MNr = '0495018'; // Ihre Mitgliedsnummer vom SSO-System

// ⚠️ WICHTIG: Exakt dieser Variablenname!
$_SESSION['MNr'] = $MNr;  // ← MUSS exakt 'MNr' heißen!

// Optional (für Ihr eigenes System):
$_SESSION['authenticated'] = true;
$_SESSION['user_name'] = 'Max Mustermann';
?>
```

**⚠️ HÄUFIGER FEHLER:**
```php
// ❌ FALSCH - andere Namen funktionieren NICHT:
$_SESSION['membership_number'] = '0495018';  // ← Wird NICHT erkannt
$_SESSION['mnr'] = '0495018';                // ← Wird NICHT erkannt
$_SESSION['member_id'] = '0495018';          // ← Wird NICHT erkannt

// ✅ RICHTIG - exakt dieser Name:
$_SESSION['MNr'] = '0495018';  // ← Großes M, kleines N, kleines r
```

---

## Schritt 3: Link zur Sitzungsverwaltung einbauen

### Variante A: Als Menüpunkt

```php
<!-- In Ihrer Navigation -->
<nav>
    <a href="/dashboard.php">Dashboard</a>
    <a href="/Sitzungsverwaltung/sso_direct.php">Sitzungsverwaltung</a>
    <a href="/profil.php">Profil</a>
</nav>
```

### Variante B: Als Button

```php
<a href="/Sitzungsverwaltung/sso_direct.php"
   style="display: inline-block; padding: 12px 24px; background: #1976d2; color: white; text-decoration: none; border-radius: 4px;">
    📋 Zur Sitzungsverwaltung
</a>
```

### Variante C: In Tab-System integrieren

```php
<?php
$module = $_GET['module'] ?? 'home';

switch($module) {
    case 'sitzungen':
        // Zur Sitzungsverwaltung weiterleiten
        header('Location: /Sitzungsverwaltung/sso_direct.php');
        exit;

    case 'home':
    default:
        include 'home.php';
        break;
}
?>
```

---

## Schritt 4: Session-Konfiguration prüfen

**WICHTIG:** Beide Systeme müssen die gleiche Session nutzen!

### In Ihrer `config.php` (Hauptsystem):

```php
<?php
// Session-Einstellungen
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

// Falls HTTPS:
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start();
?>
```

### In `/Sitzungsverwaltung/config.php`:

Sollte bereits kompatibel sein, prüfen Sie:
```php
ini_set('session.cookie_path', '/');
ini_set('session.cookie_samesite', 'Lax');
```

---

## Schritt 5: Testen

### Test 1: Direkter Zugriff

Rufen Sie auf: `https://ihre-domain.de/Sitzungsverwaltung/sso_direct.php`

**Erwartetes Ergebnis:**
- Seite lädt mit Ihrem Custom-Styling
- Header zeigt Ihr Logo und "Zurück zum VTool"
- Footer zeigt Ihren Custom-HTML-Inhalt
- User ist automatisch eingeloggt (via SSO)

### Test 2: Navigation

- ✅ Tabs funktionieren (GET-Parameter)
- ✅ Formulare funktionieren
- ✅ Browser History funktioniert
- ✅ Keine Style-Konflikte

### Test 3: Zurück-Button

Klicken Sie auf "Zurück zum VTool" → sollte zu Ihrer Hauptseite zurückführen

---

## Troubleshooting

### ❌ Problem 1: "Zugriff verweigert" - Session-Variable fehlt

**Symptom:** Fehlerseite "Sie müssen angemeldet sein"

**Ursachen:**
1. ` $_SESSION['MNr']` ist nicht gesetzt
2. Falscher Variablenname (z.B. `$_SESSION['membership_number']`)
3. Session wird nicht zwischen Systemen geteilt

**Debug-Schritte:**

```php
// 1. In Ihrem Hauptsystem DIREKT nach session_start():
<?php
session_start();
echo '<pre>';
var_dump($_SESSION);  // Ist 'MNr' vorhanden?
echo '</pre>';
?>

// 2. In /Sitzungsverwaltung/sso_direct.php GANZ OBEN hinzufügen:
<?php
session_start();
echo '<pre>DEBUG SESSION:';
var_dump($_SESSION);
echo '</pre>';
die();
?>
```

**Lösungen:**
- ✅ **RICHTIG:** `$_SESSION['MNr'] = '0495018';` (großes M, kleines Nr)
- ❌ **FALSCH:** Jeder andere Variablenname!

### ❌ Problem 2: "Zugriff verweigert" - REQUIRE_LOGIN nicht deaktiviert

**Symptom:** Fehlerseite obwohl `$_SESSION['MNr']` gesetzt ist

**Ursache:** `REQUIRE_LOGIN = true` in config_adapter.php (SSO ist damit deaktiviert!)

**Lösung:**
```php
// In config_adapter.php:
define('REQUIRE_LOGIN', false);  // ← MUSS false sein für SSO!
```

**Wie testen:**
```php
// In /Sitzungsverwaltung/test_sso.php (neue Datei):
<?php
require_once 'config_adapter.php';
echo 'REQUIRE_LOGIN = ' . (REQUIRE_LOGIN ? 'true' : 'false') . '<br>';
echo 'SSO_SOURCE = ' . SSO_SOURCE . '<br>';
echo 'MEMBER_SOURCE = ' . MEMBER_SOURCE . '<br>';

session_start();
echo '$_SESSION[MNr] = ' . ($_SESSION['MNr'] ?? 'NICHT GESETZT') . '<br>';

$mnr = get_sso_membership_number();
echo 'get_sso_membership_number() = ' . ($mnr ?? 'NULL') . '<br>';
?>
```

**Erwartete Ausgabe:**
```
REQUIRE_LOGIN = false
SSO_SOURCE = session
MEMBER_SOURCE = berechtigte
$_SESSION[MNr] = 0495018
get_sso_membership_number() = 0495018
```

### ❌ Problem 3: User wird nicht gefunden

**Symptom:** "Mitgliedsnummer wurde nicht gefunden"

**Ursache:** User existiert nicht in `berechtigte`-Tabelle oder ist inaktiv

**Debug:**
```sql
-- 1. User in DB suchen:
SELECT * FROM berechtigte WHERE MNr = '0495018';

-- 2. Prüfen ob User die Filter-Bedingung erfüllt:
SELECT MNr, Vorname, Name, Funktion, aktiv
FROM berechtigte
WHERE MNr = '0495018'
  AND (aktiv >= 18 OR Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF'));
```

**Lösungen:**
- User existiert nicht → In `berechtigte` anlegen
- `aktiv` zu niedrig → Auf 18+ setzen ODER Funktion auf RL/SV/AD/FP/GF
- Falsche MNr → MNr-Format prüfen (führende Nullen?)

### ❌ Problem 4: Session wird nicht geteilt

**Symptom:** Im Hauptsystem eingeloggt, aber Sitzungsverwaltung sieht andere Session

**Ursachen:**
- Unterschiedliche `session.cookie_path` Settings
- Unterschiedliche Session-Namen
- Sub-Domain-Problem

**Lösung:**
```php
// BEIDE Systeme müssen IDENTISCH sein:
ini_set('session.cookie_path', '/');     // ← Wichtig!
ini_set('session.cookie_domain', '');    // ← Leer lassen
ini_set('session.name', 'PHPSESSID');    // ← Standard-Name

// Optional: Explizit gleichen Namen setzen:
session_name('MY_APP_SESSION');  // In BEIDEN Systemen gleich!
```

**Test:**
```php
// 1. Im Hauptsystem:
<?php
session_start();
$_SESSION['test'] = 'HALLO';
echo 'Session ID: ' . session_id();
echo '<br>Session Name: ' . session_name();
?>

// 2. In /Sitzungsverwaltung/sso_direct.php:
<?php
session_start();
echo 'Session ID: ' . session_id();  // ← Muss GLEICH sein!
echo '<br>Session Name: ' . session_name();
echo '<br>Test: ' . ($_SESSION['test'] ?? 'NICHT GESEHEN');
?>
```

### ⚠️ Problem 5: Styling passt nicht

**Lösung:** Farben in `config_adapter.php` → `$SSO_DIRECT_CONFIG` anpassen

### ⚠️ Problem 6: Logo wird nicht angezeigt

**Lösungen:**
1. Pfad prüfen: `$SSO_DIRECT_CONFIG['logo_path'] = '/img/logo.png';`
2. Datei existiert: `/var/www/html/img/logo.png`
3. Berechtigungen: `chmod 644 logo.png`
4. Browser-Cache leeren (Strg+F5)

---

## Vergleich: iframe vs. SSOdirekt

### iframe-Modus (Alternative)

**Weiterhin verfügbar über:** `/Sitzungsverwaltung/iframe_wrapper.php`

**Wann nutzen:**
- Wenn Sie die Sitzungsverwaltung in bestehende Seite einbetten wollen
- Wenn komplette Style-Isolation wichtig ist
- ⚠️ GET-Parameter-Navigation funktioniert nur eingeschränkt

**Footer:** Wird automatisch ausgeblendet (da Parent-Seite eigenen Footer hat)

### SSOdirekt-Modus (Empfohlen)

**Aufruf:** `/Sitzungsverwaltung/sso_direct.php`

**Wann nutzen:**
- Für produktive Integration (empfohlen!)
- Wenn alle Features vollständig funktionieren sollen
- Wenn Custom-Styling wichtig ist

---

## Beispiel: Komplette Integration

### 1. Hauptsystem (`vtool.php`):

```php
<?php
session_start();
require_once 'config.php';

// SSO-Authentifizierung
$MNr = $_SESSION['user_mnr'] ?? null;

if ($MNr) {
    $_SESSION['MNr'] = $MNr;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>VTool - Mensa in Deutschland</title>
</head>
<body>
    <nav>
        <a href="?page=home">Home</a>
        <a href="/Sitzungsverwaltung/sso_direct.php">Sitzungsverwaltung</a>
    </nav>

    <h1>Willkommen im VTool</h1>
</body>
</html>
```

### 2. Config angepasst (`config_adapter.php`):

```php
$SSO_DIRECT_CONFIG = [
    'primary_color' => '#1976d2',
    'border_color' => '#0d47a1',
    'header_text_color' => '#ffffff',
    'footer_text_color' => '#ffffff',
    'logo_path' => '/img/mensa_logo.png',
    'logo_height' => '40px',
    'back_button_text' => 'Zurück zum VTool',
    'back_button_url' => 'https://aktive.mensa.de/vtool.php',
    'footer_html' => '<p>© 2025 Mensa in Deutschland e.V.</p>',
    'page_title' => 'Sitzungsverwaltung - Mensa',
];
```

**Fertig!** ✅

---

## Support und Debug-Hilfen

Bei Problemen:

1. **Test-Skript erstellen** (`test_sso.php`):
```php
<?php
require_once 'config_adapter.php';
session_start();

echo '<h2>SSO Debug-Info</h2>';
echo '<pre>';
echo 'REQUIRE_LOGIN = ' . (REQUIRE_LOGIN ? 'true (❌ FALSCH für SSO!)' : 'false (✅ RICHTIG)') . "\n";
echo 'SSO_SOURCE = ' . SSO_SOURCE . "\n";
echo 'MEMBER_SOURCE = ' . MEMBER_SOURCE . "\n\n";

echo 'Session ID: ' . session_id() . "\n";
echo 'Session Name: ' . session_name() . "\n\n";

echo '$_SESSION[MNr] = ' . ($_SESSION['MNr'] ?? '❌ NICHT GESETZT') . "\n";

$mnr = get_sso_membership_number();
echo 'get_sso_membership_number() = ' . ($mnr ?? '❌ NULL') . "\n\n";

if ($mnr) {
    require_once 'member_functions.php';
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $user = get_member_by_membership_number($pdo, $mnr);

    if ($user) {
        echo "✅ User gefunden:\n";
        echo "  Name: " . $user['first_name'] . ' ' . $user['last_name'] . "\n";
        echo "  Email: " . $user['email'] . "\n";
        echo "  Rolle: " . $user['role'] . "\n";
    } else {
        echo "❌ User NICHT in Datenbank gefunden!\n";
    }
}

echo '</pre>';
?>
```

2. **Browser-Konsole prüfen** (F12 → Console-Tab)
3. **PHP-Error-Log prüfen** (`/var/log/apache2/error.log`)
4. **Session-Cookies prüfen** (F12 → Application → Cookies)

---

## Änderungshistorie

**Version 2.0 - 16.12.2025** ⚠️ **BREAKING CHANGES**
- ✅ Behoben: `REQUIRE_LOGIN` muss `false` sein (war nicht dokumentiert!)
- ✅ Behoben: Session-Variablen-Inkonsistenz in sso_direct.php
- ✅ Behoben: Fehlende Code-Bugs in Session-Handling
- ✅ Verbessert: Klare Troubleshooting-Sektion mit echten Lösungen
- ✅ Ergänzt: Test-Skripte für einfacheres Debugging
- ⚠️ **Migration erforderlich:** Siehe "Schritt 1" für neue config_adapter.php

**Version 1.0 - 09.12.2025**
- Erste Version (enthielt kritische Bugs)

---

**Status:** ✅ Produktionsreif (Version 2.0)
**Letzte Aktualisierung:** 16.12.2025
**Autor:** Sitzungsverwaltung Development Team
