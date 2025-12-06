# Integration der Sitzungsverwaltung in bestehendes System

## Übersicht

Die Sitzungsverwaltung wird als Modul in Ihr bestehendes System integriert, nutzt dessen SSO-Authentifizierung (`$MNr`) und die vorhandene `berechtigte`-Tabelle statt der eigenen Mitgliederverwaltung.

---

## 1. Voraussetzungen

### Erfüllt ✅
- [x] Alle Skripte im Unterverzeichnis `/Sitzungsverwaltung/`
- [x] Alle Datenbanktabellen mit `sv`-Prefix übertragen
- [x] `config.php` mit Datenbank-Zugriff angepasst
- [x] `config_adapter.php` bereits entwickelt

### Zu prüfen
- [ ] Tabelle `berechtigte` hat folgende Spalten:
  - `member_id` oder `MNr` (Primärschlüssel)
  - `first_name` / `vorname`
  - `last_name` / `nachname`
  - `email`
  - `role` / `rolle` (z.B. 'vorstand', 'gf', 'mitglied')

---

## 2. config_adapter.php - Aufbau und Anpassung

Die `config_adapter.php` muss als zentrale Schnittstelle zwischen Ihrem System und der Sitzungsverwaltung fungieren.

### 2.1 Grundstruktur (Falls noch nicht vorhanden)

```php
<?php
/**
 * config_adapter.php
 *
 * Adapter zwischen bestehendem System und Sitzungsverwaltung
 * - Mapped berechtigte-Tabelle auf members-Struktur
 * - Integriert SSO-Variable $MNr
 */

// Vorhandenes System einbinden
require_once __DIR__ . '/../ihre_bestehende_config.php';

// Sitzungsverwaltung config einbinden
require_once __DIR__ . '/config.php';

// ============================================
// SESSION & AUTHENTICATION
// ============================================

// SSO-Variable $MNr aus bestehendem System übernehmen
if (isset($MNr) && !isset($_SESSION['member_id'])) {
    $_SESSION['member_id'] = $MNr;
}

// ============================================
// MITGLIEDER-TABELLE MAPPING
// ============================================

/**
 * Holt Mitglied aus berechtigte-Tabelle
 * Mapped die Struktur auf Sitzungsverwaltungs-Format
 */
function get_member_by_id($pdo, $member_id) {
    $stmt = $pdo->prepare("
        SELECT
            member_id,           -- oder: MNr AS member_id
            first_name,          -- oder: vorname AS first_name
            last_name,           -- oder: nachname AS last_name
            email,
            role,                -- oder: rolle AS role
            phone,               -- optional
            is_active            -- optional
        FROM berechtigte
        WHERE member_id = ?     -- oder: MNr = ?
    ");
    $stmt->execute([$member_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Holt alle aktiven Mitglieder
 */
function get_all_members($pdo) {
    $stmt = $pdo->query("
        SELECT
            member_id,           -- oder: MNr AS member_id
            first_name,          -- oder: vorname AS first_name
            last_name,           -- oder: nachname AS last_name
            email,
            role,                -- oder: rolle AS role
            phone
        FROM berechtigte
        WHERE is_active = 1     -- oder Ihre Aktivitätsbedingung
        ORDER BY last_name, first_name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Prüft ob User Admin ist
 */
function is_admin($member) {
    if (!$member) return false;

    $admin_roles = ['vorstand', 'gf', 'assistenz'];
    return in_array(strtolower($member['role']), $admin_roles);
}

/**
 * Prüft ob User Leadership-Rolle hat
 */
function is_leadership($member) {
    if (!$member) return false;

    $leadership_roles = ['vorstand', 'gf', 'assistenz', 'führungsteam'];
    return in_array(strtolower($member['role']), $leadership_roles);
}

// ============================================
// CURRENT USER LADEN
// ============================================

// Aktuellen User aus Session laden
if (isset($_SESSION['member_id'])) {
    $current_user = get_member_by_id($pdo, $_SESSION['member_id']);

    if ($current_user) {
        // Zusätzliche Flags für einfachere Verwendung
        $current_user['is_admin'] = is_admin($current_user);
        $current_user['is_leadership'] = is_leadership($current_user);

        // In Session speichern für schnelleren Zugriff
        $_SESSION['current_user'] = $current_user;
    } else {
        // User nicht gefunden - zurück zum Login
        header('Location: /ihre_login_seite.php');
        exit;
    }
} else {
    // Nicht eingeloggt - zurück zum Login
    header('Location: /ihre_login_seite.php');
    exit;
}

// ============================================
// OVERRIDE FUNCTIONS.PHP FUNKTIONEN
// ============================================

// Falls functions.php bereits geladen wurde, überschreiben wir die Funktionen
if (function_exists('get_all_members')) {
    // Bereits geladen - wir müssen nichts tun
} else {
    // Noch nicht geladen - Funktionen sind bereits definiert
}

?>
```

### 2.2 Spaltennamen anpassen

**Falls Ihre `berechtigte`-Tabelle andere Spaltennamen hat**, passen Sie die SQL-Queries an:

```php
// Beispiel: Ihre Tabelle nutzt 'MNr' statt 'member_id'
SELECT
    MNr AS member_id,
    vorname AS first_name,
    nachname AS last_name,
    ...
```

---

## 3. Integration in Ihr System

### 3.1 Einfache Include-Variante

```php
<?php
// In Ihrer Hauptseite (z.B. dashboard.php)

session_start();

// Ihr bestehendes System
require_once 'ihre_config.php';

// SSO - $MNr wird von Ihrem System gesetzt
$MNr = $_SESSION['user_id']; // oder wie auch immer Sie die ID setzen

// Sitzungsverwaltung einbinden
require_once __DIR__ . '/Sitzungsverwaltung/config_adapter.php';

// Optional: Tab-System
if (isset($_GET['module']) && $_GET['module'] === 'sitzungen') {
    // Sitzungsverwaltung anzeigen
    include __DIR__ . '/Sitzungsverwaltung/index.php';
} else {
    // Ihr normales Dashboard
    include 'ihr_dashboard.php';
}
?>
```

### 3.2 Als Tab/Modul einbinden

```php
<!-- In Ihrer Navigation -->
<nav>
    <a href="?module=home">Home</a>
    <a href="?module=sitzungen">Sitzungsverwaltung</a>
    <a href="?module=andere">Andere Module</a>
</nav>

<?php
switch($_GET['module'] ?? 'home') {
    case 'sitzungen':
        require_once __DIR__ . '/Sitzungsverwaltung/config_adapter.php';
        include __DIR__ . '/Sitzungsverwaltung/index.php';
        break;

    case 'home':
    default:
        include 'ihr_home.php';
        break;
}
?>
```

### 3.3 Als iFrame (Alternative)

```html
<!-- Falls Sie die Sitzungsverwaltung in einem iFrame einbetten wollen -->
<iframe src="/Sitzungsverwaltung/index.php"
        style="width: 100%; height: 800px; border: none;">
</iframe>
```

**Hinweis:** Bei iFrame müssen Sie sicherstellen, dass die Session geteilt wird (Same-Site Cookies).

---

## 4. Anpassungen in den Sitzungsverwaltungs-Skripten

### 4.1 index.php - Anpassungen am Anfang

**Ersetzen Sie in `/Sitzungsverwaltung/index.php` (ca. Zeile 1-20):**

```php
<?php
session_start();

// WICHTIG: config_adapter.php STATT config.php einbinden
require_once __DIR__ . '/config_adapter.php';

// $current_user ist jetzt bereits durch config_adapter.php geladen
// Falls nicht, Fehlerbehandlung:
if (!isset($current_user) || !$current_user) {
    die('Fehler: Benutzer nicht authentifiziert. Bitte melden Sie sich an.');
}

// Rest des Skripts bleibt unverändert
...
```

### 4.2 Alle anderen PHP-Dateien

**Ersetzen Sie überall:**

```php
// ALT:
require_once 'config.php';

// NEU:
require_once __DIR__ . '/config_adapter.php';
```

**Betrifft folgende Dateien:**
- `process_*.php` (alle Process-Dateien)
- `tab_*.php` (alle Tab-Dateien)
- `api/*.php` (alle API-Dateien)
- `functions.php` (WICHTIG!)

### 4.3 functions.php - Member-Funktionen auskommentieren

**In `/Sitzungsverwaltung/functions.php`:**

Auskommentieren oder entfernen Sie die `get_all_members()` und `get_member_by_id()` Funktionen, da diese jetzt aus `config_adapter.php` kommen:

```php
// AUSKOMMENTIERT - wird durch config_adapter.php bereitgestellt
/*
function get_all_members($pdo) {
    ...
}

function get_member_by_id($pdo, $member_id) {
    ...
}
*/
```

---

## 5. Datenbank-Anpassungen

### 5.1 Fremdschlüssel prüfen

Falls Ihre Tabellen Fremdschlüssel-Constraints haben, stellen Sie sicher, dass diese auf die richtige Tabelle zeigen:

```sql
-- Beispiel: svmeetings Tabelle
-- ALT: FOREIGN KEY (invited_by_member_id) REFERENCES svmembers(member_id)
-- NEU: FOREIGN KEY (invited_by_member_id) REFERENCES berechtigte(member_id)

-- Constraint entfernen (falls vorhanden)
ALTER TABLE svmeetings DROP FOREIGN KEY fk_invited_by;

-- Neuen Constraint erstellen (optional - kann auch weggelassen werden)
ALTER TABLE svmeetings
ADD CONSTRAINT fk_invited_by
FOREIGN KEY (invited_by_member_id) REFERENCES berechtigte(member_id)
ON DELETE SET NULL;
```

**Empfehlung:** Lassen Sie die Fremdschlüssel weg, wenn beide Tabellen nicht synchron bleiben.

### 5.2 View erstellen (Optional - Elegante Lösung)

Falls Sie die Sitzungsverwaltung-Tabellen nicht ändern wollen:

```sql
-- View erstellen, die berechtigte als svmembers verfügbar macht
CREATE OR REPLACE VIEW svmembers AS
SELECT
    member_id,        -- oder: MNr AS member_id
    first_name,       -- oder: vorname AS first_name
    last_name,        -- oder: nachname AS last_name
    email,
    role,             -- oder: rolle AS role
    phone,
    is_active,
    created_at,
    updated_at
FROM berechtigte;
```

**Vorteil:** Die Sitzungsverwaltung kann unverändert bleiben und direkt `svmembers` nutzen.

---

## 6. Rollen-Mapping

### 6.1 Rollen-Struktur anpassen

Falls Ihre `berechtigte`-Tabelle andere Rollennamen verwendet:

**In config_adapter.php:**

```php
/**
 * Mapped Ihre Rollen auf Sitzungsverwaltungs-Rollen
 */
function map_role($original_role) {
    $role_mapping = [
        'admin' => 'vorstand',
        'manager' => 'gf',
        'assistant' => 'assistenz',
        'leader' => 'führungsteam',
        'user' => 'mitglied',
        // ... weitere Mappings
    ];

    return $role_mapping[strtolower($original_role)] ?? 'mitglied';
}

// In get_member_by_id() verwenden:
function get_member_by_id($pdo, $member_id) {
    $stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        // Rolle mappen
        $member['role'] = map_role($member['original_role_field']);
    }

    return $member;
}
```

---

## 7. URL-Struktur

### 7.1 Relative Pfade sicherstellen

In `config.php` oder `config_adapter.php`:

```php
// Basis-URL für Assets
define('BASE_URL', '/Sitzungsverwaltung');

// Oder dynamisch:
define('BASE_URL', dirname($_SERVER['SCRIPT_NAME']));
```

### 7.2 .htaccess (Optional)

Falls Sie schönere URLs wollen:

```apache
# /Sitzungsverwaltung/.htaccess
RewriteEngine On
RewriteBase /Sitzungsverwaltung/

# Alle Anfragen zu index.php leiten (außer Dateien/Verzeichnisse)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?route=$1 [L,QSA]
```

---

## 8. Testing & Debugging

### 8.1 Test-Checklist

- [ ] **Login funktioniert:** `$MNr` wird korrekt übernommen
- [ ] **User-Daten werden geladen:** `var_dump($current_user)` zeigt Daten aus `berechtigte`
- [ ] **Rollen funktionieren:** Leadership-Features nur für entsprechende Rollen sichtbar
- [ ] **Meetings erstellen:** Neue Meetings werden mit korrekter `member_id` erstellt
- [ ] **Teilnehmer-Auswahl:** Dropdown zeigt alle User aus `berechtigte`
- [ ] **Benachrichtigungen:** Werden korrekt angezeigt basierend auf Rolle

### 8.2 Debug-Modus

In `config_adapter.php`:

```php
// Temporär zum Debuggen:
if (isset($_GET['debug']) && $_SESSION['member_id'] == 1) {
    echo '<pre>';
    echo "SSO Variable \$MNr: " . ($MNr ?? 'nicht gesetzt') . "\n";
    echo "Session member_id: " . ($_SESSION['member_id'] ?? 'nicht gesetzt') . "\n";
    echo "Current User:\n";
    print_r($current_user);
    echo '</pre>';
}
```

Aufruf: `?module=sitzungen&debug=1`

### 8.3 Häufige Fehler

**Fehler:** "Undefined variable: current_user"
- **Lösung:** `config_adapter.php` wird nicht geladen. Prüfen Sie include-Pfade.

**Fehler:** "Table 'svmembers' doesn't exist"
- **Lösung:** functions.php nutzt noch alte Member-Funktionen. View erstellen oder Funktionen überschreiben.

**Fehler:** "No members found"
- **Lösung:** Spaltennnamen in `get_all_members()` prüfen und anpassen.

---

## 9. Sicherheits-Hinweise

### 9.1 Session-Sicherheit

```php
// In config_adapter.php - Session-Einstellungen
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // nur bei HTTPS
ini_set('session.cookie_samesite', 'Lax');
```

### 9.2 SQL-Injection-Schutz

Alle Queries in Sitzungsverwaltung nutzen bereits Prepared Statements ✅

### 9.3 XSS-Schutz

Alle Ausgaben nutzen bereits `htmlspecialchars()` ✅

---

## 10. Zusammenfassung - Quick Start

### Schritt-für-Schritt Anleitung:

1. **config_adapter.php erstellen** (siehe Abschnitt 2.1)
   - Spaltennnamen an Ihre `berechtigte`-Tabelle anpassen
   - SSO-Variable `$MNr` integrieren

2. **index.php anpassen** (siehe Abschnitt 4.1)
   - `require_once 'config.php'` → `require_once 'config_adapter.php'`

3. **Alle anderen PHP-Dateien anpassen** (siehe Abschnitt 4.2)
   - Suchen & Ersetzen: `require_once 'config.php'` → `require_once __DIR__ . '/config_adapter.php'`

4. **functions.php anpassen** (siehe Abschnitt 4.3)
   - `get_all_members()` und `get_member_by_id()` auskommentieren

5. **In Ihr System einbinden** (siehe Abschnitt 3.1 oder 3.2)
   - Via Include oder als Modul

6. **Testen** (siehe Abschnitt 8.1)
   - Alle Funktionen durchgehen

---

## 11. Support & Weiterentwicklung

Bei Fragen oder Problemen:
- Prüfen Sie die Debug-Ausgabe (Abschnitt 8.2)
- Checken Sie die Datenbank-Logs
- Überprüfen Sie die Session-Variablen

---

**Version:** 1.0
**Datum:** 2025-12-06
**Status:** Integration Ready ✅
