# SSOdirekt-Modus - Anleitung

## Übersicht

Der **SSOdirekt-Modus** ist die empfohlene Methode zur Integration der Sitzungsverwaltung in Ihr bestehendes System. Im Gegensatz zur iframe-Einbindung läuft die Sitzungsverwaltung als eigenständige Seite mit angepasstem Design.

## Vorteile gegenüber iframe

| Feature | iframe | SSOdirekt |
|---------|--------|-----------|
| GET-Parameter funktionieren | ❌ Problematisch | ✅ Vollständig |
| Formulare funktionieren | ❌ Müssen angepasst werden | ✅ Out-of-the-box |
| Styling anpassbar | ⚠️ Begrenzt | ✅ Vollständig |
| Browser History | ❌ Nein | ✅ Ja |
| Performance | ⚠️ Doppeltes Laden | ✅ Optimal |

---

## Schritt 1: config_adapter.php anpassen

Öffnen Sie `/Sitzungsverwaltung/config_adapter.php` und passen Sie die SSOdirekt-Konfiguration an:

```php
// Display-Modus (wird von sso_direct.php überschrieben)
define('DISPLAY_MODE', 'standalone');

// SSOdirekt Konfiguration
$SSO_DIRECT_CONFIG = [
    // === STYLING ===
    // Passen Sie diese Farben an Ihr Vereins-Design an
    'primary_color' => '#1976d2',          // Header/Footer Hintergrund
    'border_color' => '#0d47a1',           // Border-Farbe
    'header_text_color' => '#ffffff',      // Text-Farbe im Header
    'footer_text_color' => '#ffffff',      // Text-Farbe im Footer

    // Logo (relativ oder absolut)
    'logo_path' => '/path/to/your/logo.png',
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
```

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

In Ihrer Hauptanwendung (z.B. `vtool.php`, `auth.php`):

```php
<?php
session_start();

// Nach erfolgreicher SSO-Authentifizierung:
$MNr = '0495018'; // Ihre Mitgliedsnummer vom SSO-System

// In Session speichern für Sitzungsverwaltung
$_SESSION['MNr'] = $MNr;
$_SESSION['authenticated'] = true;
?>
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

### Problem: "Zugriff verweigert"

**Ursache:** `$_SESSION['MNr']` ist nicht gesetzt

**Lösung:**
1. Prüfen Sie Ihr Hauptsystem: Wird `$_SESSION['MNr'] = ...` gesetzt?
2. Prüfen Sie Session-Einstellungen (Schritt 4)
3. Debug: Öffnen Sie `/Sitzungsverwaltung/debug_iframe.php`

### Problem: User wird nicht gefunden

**Ursache:** User existiert nicht in `berechtigte`-Tabelle

**Lösung:**
```sql
-- In Datenbank prüfen:
SELECT * FROM berechtigte WHERE MNr = '0495018';
```

Falls User existiert, prüfen Sie Filter-Bedingung:
- `aktiv > 17` ODER
- `Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')`

### Problem: Styling passt nicht

**Lösung:** Farben in `config_adapter.php` anpassen (siehe Schritt 1)

### Problem: Logo wird nicht angezeigt

**Lösung:**
1. Prüfen Sie Pfad in `$SSO_DIRECT_CONFIG['logo_path']`
2. Pfad kann relativ (`/img/logo.png`) oder absolut sein
3. Prüfen Sie Dateiberechtigungen

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

## Support

Bei Problemen:
1. Debug-Skript aufrufen: `/Sitzungsverwaltung/debug_iframe.php`
2. Browser-Konsole prüfen (F12)
3. PHP-Error-Log prüfen

**Version:** 1.0
**Datum:** 2025-12-09
**Status:** Produktionsreif ✅
