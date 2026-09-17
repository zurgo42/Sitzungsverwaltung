# iframe-Integration - Komplette Anleitung

## Übersicht

Die iframe-Integration löst **alle Style-Konflikte** durch vollständige Isolation. Die Sitzungsverwaltung läuft im iframe und teilt nur die Session mit dem Hauptsystem.

---

## Schritt 1: Hauptsystem - Session vorbereiten

In Ihrer Hauptanwendung (z.B. `dashboard.php`, `auth.php` oder `index.php`):

```php
<?php
session_start();

// === IHRE BESTEHENDE AUTHENTIFIZIERUNG ===
// z.B. SSO, LDAP, OAuth, etc.

// Nach erfolgreicher Authentifizierung:
$MNr = '0495018'; // Ihre Mitgliedsnummer vom SSO-System
// Oder: $MNr = $user->membership_number;
// Oder: $MNr = $_SESSION['user_data']['mnr'];

// WICHTIG: MNr in Session speichern für iframe-Zugriff!
$_SESSION['MNr'] = $MNr;
$_SESSION['authenticated'] = true;

// Ihre weiteren Initialisierungen...
?>
```

---

## Schritt 2: config_adapter.php konfigurieren

**Bereits erledigt!** ✅ Die Datei ist jetzt konfiguriert mit:
- `MEMBER_SOURCE = 'berechtigte'` → Nutzt externe Tabelle
- `REQUIRE_LOGIN = false` → SSO-Modus aktiviert
- `SSO_SOURCE = 'session'` → Liest `$_SESSION['MNr']`

---

## Schritt 3: iframe in Ihre Seite einbinden

### Variante A: Einfache Integration

```php
<!-- In Ihrer Hauptseite, z.B. dashboard.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Ihr System - Sitzungsverwaltung</title>
    <!-- Ihre bestehenden CSS/JS -->
</head>
<body>
    <!-- Ihre Navigation -->
    <nav>
        <a href="?page=home">Home</a>
        <a href="?page=sitzungen">Sitzungen</a>
        <a href="?page=andere">Andere</a>
    </nav>

    <?php if (isset($_GET['page']) && $_GET['page'] === 'sitzungen'): ?>
        <!-- Sitzungsverwaltung als iframe -->
        <div style="width: 100%; max-width: 1400px; margin: 0 auto;">
            <iframe
                id="sitzungsverwaltung"
                src="/Sitzungsverwaltung/iframe_wrapper.php"
                style="width: 100%; border: none; min-height: 800px;">
            </iframe>
        </div>

        <script>
        // Automatische Höhenanpassung
        window.addEventListener('message', function(event) {
            if (event.data.type === 'resize') {
                var iframe = document.getElementById('sitzungsverwaltung');
                iframe.style.height = (event.data.height + 20) + 'px';
            }
        });
        </script>
    <?php else: ?>
        <!-- Ihr normaler Inhalt -->
        <p>Willkommen...</p>
    <?php endif; ?>
</body>
</html>
```

### Variante B: Als Modal/Overlay

```php
<!-- Button zum Öffnen -->
<button onclick="openSitzungsverwaltung()">📋 Sitzungsverwaltung</button>

<!-- Modal -->
<div id="sv-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999;">
    <div style="position: relative; width: 95%; height: 95%; margin: 2.5%; background: white; border-radius: 8px; overflow: hidden;">
        <button onclick="closeSitzungsverwaltung()" style="position: absolute; top: 10px; right: 10px; z-index: 10000; background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
            ✕ Schließen
        </button>
        <iframe
            id="sitzungsverwaltung"
            src="/Sitzungsverwaltung/iframe_wrapper.php"
            style="width: 100%; height: 100%; border: none;">
        </iframe>
    </div>
</div>

<script>
function openSitzungsverwaltung() {
    document.getElementById('sv-modal').style.display = 'block';
}
function closeSitzungsverwaltung() {
    document.getElementById('sv-modal').style.display = 'none';
}
</script>
```

### Variante C: In bestehendes Tab-System

```php
<!-- Ihre bestehenden Tabs -->
<div class="tabs">
    <button onclick="showTab('home')">Home</button>
    <button onclick="showTab('sitzungen')">Sitzungen</button>
</div>

<div id="tab-home" class="tab-content">
    <!-- Ihr Home-Content -->
</div>

<div id="tab-sitzungen" class="tab-content" style="display: none;">
    <iframe
        id="sitzungsverwaltung"
        src="/Sitzungsverwaltung/iframe_wrapper.php"
        style="width: 100%; border: none; height: 1000px;">
    </iframe>
</div>

<script>
function showTab(tabName) {
    // Alle Tabs ausblenden
    document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');

    // Gewählten Tab anzeigen
    document.getElementById('tab-' + tabName).style.display = 'block';
}

// Höhenanpassung für iframe
window.addEventListener('message', function(event) {
    if (event.data.type === 'resize') {
        var iframe = document.getElementById('sitzungsverwaltung');
        iframe.style.height = (event.data.height + 20) + 'px';
    }
});
</script>
```

---

## Schritt 4: Session-Konfiguration prüfen

**WICHTIG:** Beide Systeme müssen die gleiche Session nutzen!

### In Ihrer `config.php` (Hauptsystem):

```php
<?php
// Session-Einstellungen für iframe-Kompatibilität
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');  // WICHTIG für iframe!

// Falls HTTPS:
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start();
?>
```

### In `/Sitzungsverwaltung/config.php`:

Sollte bereits kompatibel sein, aber prüfen Sie:
```php
ini_set('session.cookie_path', '/');
ini_set('session.cookie_samesite', 'Lax');
```

---

## Schritt 5: Testen

### Test 1: Debug-Modus aktivieren

Rufen Sie auf: `https://ihre-domain.de/Sitzungsverwaltung/iframe_wrapper.php?debug=1`

Sie sollten sehen:
```
SSO_SOURCE: session
MEMBER_SOURCE: berechtigte
$_SESSION['MNr']: 0495018
$MNr: 0495018
Current User: Array (...)
```

### Test 2: Im iframe testen

```html
<!-- Temporärer Test -->
<iframe src="/Sitzungsverwaltung/iframe_wrapper.php?debug=1" style="width: 100%; height: 400px;"></iframe>
```

---

## Troubleshooting

### Problem: "Nicht angemeldet" im iframe

**Ursache:** `$_SESSION['MNr']` ist nicht gesetzt

**Lösung:**
1. Prüfen Sie Ihr Hauptsystem: Wird `$_SESSION['MNr'] = ...` gesetzt?
2. Aktivieren Sie Debug: `iframe_wrapper.php?debug=1`
3. Prüfen Sie Session-Einstellungen (siehe Schritt 4)

### Problem: User wird nicht gefunden

**Ursache:** User existiert nicht in `berechtigte`-Tabelle oder erfüllt Filter-Bedingung nicht

**Lösung:**
1. Prüfen Sie in der Datenbank:
   ```sql
   SELECT * FROM berechtigte WHERE MNr = '0495018';
   ```
2. Prüfen Sie Filter in `BerechtigteAdapter::shouldInclude()`:
   - `aktiv > 17` ODER
   - `Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')`

### Problem: Styles vom Hauptsystem beeinflussen iframe

**Ursache:** CSS vererbt sich nicht in iframes (das ist ja der Vorteil!)

**Lösung:** Das sollte nicht passieren. Falls doch, prüfen Sie:
- Wird das iframe korrekt geladen?
- Nutzen Sie `iframe_wrapper.php` oder direkt `index.php`?

### Problem: Höhe passt sich nicht an

**Ursache:** postMessage funktioniert nicht oder wird nicht empfangen

**Lösung:**
```javascript
// Debug: Nachrichten protokollieren
window.addEventListener('message', function(event) {
    console.log('Message received:', event.data);
    if (event.data.type === 'resize') {
        var iframe = document.getElementById('sitzungsverwaltung');
        iframe.style.height = (event.data.height + 20) + 'px';
    }
});
```

---

## Fertig! ✅

Mit dieser Integration haben Sie:
- ✅ Vollständige Style-Isolation (keine Konflikte mehr!)
- ✅ SSO-Integration via Session
- ✅ Automatische User-Erkennung aus `berechtigte`-Tabelle
- ✅ Automatische Höhenanpassung

**Nächste Schritte:**
1. Hauptsystem anpassen (`$_SESSION['MNr']` setzen)
2. iframe einbinden (eine der drei Varianten)
3. Testen mit Debug-Modus
4. Produktiv schalten!
