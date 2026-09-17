# Integration der Sitzungsverwaltung in bestehendes System

## Übersicht

Die Sitzungsverwaltung verwendet ein **Adapter-System**, das eine flexible Anbindung verschiedener Datenquellen ermöglicht. Alle benötigten Funktionen sind bereits vorhanden - Sie müssen nur die richtige Konfiguration setzen.

**Das Wichtigste vorab:**
Die Sitzungsverwaltung enthält bereits einen fertigen `BerechtigteAdapter`, der Ihre `berechtigte`-Tabelle auf das interne Format mappt. Sie müssen **keine neuen Funktionen schreiben**!

---

## 1. Voraussetzungen

### Erfüllt ✅
- [x] Alle Skripte im Unterverzeichnis `/Sitzungsverwaltung/`
- [x] Alle Datenbanktabellen mit `sv`-Prefix übertragen
- [x] `config.php` mit Datenbank-Zugriff angepasst
- [x] **Adapter-System bereits vorhanden** (`member_functions.php`, `adapters/MemberAdapter.php`)

### Zu prüfen
- [ ] Tabelle `berechtigte` hat folgende Spalten:
  - `ID` (Primärschlüssel)
  - `MNr` (Mitgliedsnummer für SSO)
  - `Vorname`, `Name`, `eMail`
  - `Funktion` (z.B. 'GF', 'SV', 'RL', 'AD', 'FP')
  - `aktiv` (Aktivitäts-Status)

---

## 2. Wie funktioniert das Adapter-System?

Die Sitzungsverwaltung enthält bereits **zwei fertige Adapter**:

1. **StandardMemberAdapter** - für die interne `svmembers`-Tabelle
2. **BerechtigteAdapter** - für Ihre externe `berechtigte`-Tabelle

### Vorhandene Dateien (NICHT ändern!)

**`member_functions.php`** - Wrapper-Funktionen:
- `get_all_members($pdo)` - Alle Mitglieder holen
- `get_member_by_id($pdo, $id)` - Ein Mitglied nach ID
- `get_member_by_email($pdo, $email)` - Ein Mitglied nach E-Mail
- **`get_member_by_membership_number($pdo, $mnr)`** - **Für SSO!**
- `create_member()`, `update_member()`, `delete_member()` - CRUD-Operationen

**`adapters/MemberAdapter.php`** - Adapter-Implementierungen:
- `BerechtigteAdapter` - Mappt automatisch:
  - `ID` → `member_id`
  - `MNr` → `membership_number`
  - `Vorname` → `first_name`
  - `Name` → `last_name`
  - `eMail` → `email`
  - `Funktion` + `aktiv` → `role` (Geschäftsführung, Assistenz, Führungsteam, Mitglied)

**Erstellen Sie eine einfache `config_adapter.php` mit nur 3 Zeilen Code:**

```php
<?php
// 1. Ihr bestehendes System einbinden
require_once __DIR__ . '/../ihre_bestehende_config.php';

// 2. Sitzungsverwaltung Config laden
require_once __DIR__ . '/config.php';

// 3. WICHTIG: Adapter auf "berechtigte" umschalten
define('MEMBER_SOURCE', 'berechtigte');

// 4. Member-Funktionen laden (nutzt jetzt BerechtigteAdapter!)
require_once __DIR__ . '/member_functions.php';

// 5. SSO-Integration: $MNr von Ihrem System übernehmen
if (isset($MNr) && !isset($_SESSION['member_id'])) {
    // Mitglied aus berechtigte-Tabelle holen (via Adapter)
    $current_user = get_member_by_membership_number($pdo, $MNr);

    if ($current_user) {
        $_SESSION['member_id'] = $current_user['member_id'];
        $_SESSION['current_user'] = $current_user;
    } else {
        // User nicht gefunden
        header('Location: /ihre_login_seite.php');
        exit;
    }
}

// 6. Falls nicht via SSO: current_user aus Session laden
if (!isset($current_user) && isset($_SESSION['member_id'])) {
    $current_user = get_member_by_id($pdo, $_SESSION['member_id']);
}

// Fertig! Alle Sitzungsverwaltungs-Skripte nutzen jetzt automatisch
// die berechtigte-Tabelle via BerechtigteAdapter.
?>
```

**Das war's!** ✅ Keine Spaltennamen anpassen, keine Funktionen schreiben - alles ist bereits fertig.

---

## 3. Wie der BerechtigteAdapter Ihre Tabelle mappt

Der vorhandene `BerechtigteAdapter` kennt bereits Ihre Tabellenstruktur:

### Feld-Mapping (automatisch)
| berechtigte | → | Internes Format |
|-------------|---|-----------------|
| `ID` | → | `member_id` |
| `MNr` | → | `membership_number` |
| `Vorname` | → | `first_name` |
| `Name` | → | `last_name` |
| `eMail` | → | `email` |

### Rollen-Mapping (automatisch)
| Ihre Tabelle | → | Rolle |
|--------------|---|-------|
| `aktiv = 19` | → | `Vorstand` |
| `Funktion = 'GF'` | → | `Geschäftsführung` |
| `Funktion = 'SV'` | → | `Assistenz` |
| `Funktion = 'RL'` | → | `Führungsteam` |
| `Funktion = 'AD'` oder `'FP'` | → | `Mitglied` |

### Admin-Rechte (automatisch)
- `Funktion = 'GF'` → Admin
- `Funktion = 'SV'` → Admin
- `MNr = '0495018'` → Admin (Spezial-Admin)

**Sie müssen nichts davon selbst programmieren!**

---

## 4. Integration in Ihr System

### Variante 1: Include in bestehende Seite

```php
<?php
// In Ihrer Hauptseite (z.B. dashboard.php)
session_start();

// Ihr bestehendes System mit SSO
require_once 'ihre_config.php';
// $MNr wird von Ihrem System gesetzt

// Sitzungsverwaltung einbinden
require_once __DIR__ . '/Sitzungsverwaltung/config_adapter.php';

// Sitzungsverwaltung anzeigen
include __DIR__ . '/Sitzungsverwaltung/index.php';
?>
```

### Variante 2: Als Tab/Modul

```php
<!-- In Ihrer Navigation -->
<nav>
    <a href="?module=home">Home</a>
    <a href="?module=sitzungen">Sitzungen</a>
</nav>

<?php
if (isset($_GET['module']) && $_GET['module'] === 'sitzungen') {
    require_once __DIR__ . '/Sitzungsverwaltung/config_adapter.php';
    include __DIR__ . '/Sitzungsverwaltung/index.php';
} else {
    include 'ihr_home.php';
}
?>
```

---

## 5. Keine Änderungen an anderen Skripten nötig!

**WICHTIG:** Sie müssen **KEINE** anderen PHP-Dateien anpassen!

Der Adapter funktioniert automatisch, weil:
- Alle Skripte nutzen bereits die Wrapper-Funktionen aus `member_functions.php`
- Diese Funktionen prüfen automatisch `MEMBER_SOURCE` und nutzen den richtigen Adapter
- Der `BerechtigteAdapter` mappt automatisch alle Felder

**Konkret bedeutet das:**
- ❌ `index.php` - NICHT ändern
- ❌ `process_*.php` - NICHT ändern
- ❌ `tab_*.php` - NICHT ändern
- ❌ `api/*.php` - NICHT ändern
- ❌ `functions.php` - NICHT ändern

**Einzige Datei, die Sie erstellen:** `config_adapter.php` (siehe oben)

---

## 6. Testing & Debugging

### Test-Checklist

- [ ] **config_adapter.php erstellt:** Mit `MEMBER_SOURCE = 'berechtigte'`
- [ ] **Login funktioniert:** `$MNr` wird korrekt übernommen
- [ ] **User-Daten werden geladen:** `$current_user` enthält Daten aus `berechtigte`
- [ ] **Rollen funktionieren:** Leadership-Features nur für entsprechende Rollen sichtbar
- [ ] **Meetings erstellen:** Neue Meetings werden mit korrekter `member_id` erstellt
- [ ] **Teilnehmer-Auswahl:** Dropdown zeigt alle User aus `berechtigte`

### Debug-Modus

Fügen Sie temporär in `config_adapter.php` hinzu:

```php
// DEBUG: Nach dem SSO-Block
if (isset($_GET['debug']) && !empty($current_user)) {
    echo '<pre style="background: #f0f0f0; padding: 20px; margin: 20px; border: 2px solid #333;">';
    echo "<h3>🔧 DEBUG MODE</h3>\n";
    echo "SSO Variable \$MNr: " . ($MNr ?? 'nicht gesetzt') . "\n";
    echo "MEMBER_SOURCE: " . (defined('MEMBER_SOURCE') ? MEMBER_SOURCE : 'nicht gesetzt') . "\n\n";
    echo "Current User:\n";
    print_r($current_user);
    echo "\n\nAlle Members (erste 3):\n";
    print_r(array_slice(get_all_members($pdo), 0, 3));
    echo '</pre>';
    exit; // Nicht weitermachen
}
```

Aufruf: `?debug=1`

### Häufige Probleme

**Problem:** "MEMBER_SOURCE not defined"
→ Stellen Sie sicher, dass `define('MEMBER_SOURCE', 'berechtigte');` **VOR** `require_once 'member_functions.php'` steht

**Problem:** "User nicht gefunden"
→ Prüfen Sie mit Debug-Modus, ob `$MNr` korrekt gesetzt ist und ob der User in der `berechtigte`-Tabelle die Bedingung `shouldInclude()` erfüllt

**Problem:** "Keine Mitglieder sichtbar"
→ Der `BerechtigteAdapter` filtert nach `aktiv > 17` ODER `Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')`

---

## 7. Zusammenfassung - Quick Start

### ✅ In 3 Schritten zur Integration:

1. **`config_adapter.php` erstellen** (siehe Abschnitt 2)
   - Ihr System einbinden
   - `MEMBER_SOURCE = 'berechtigte'` definieren
   - SSO-Variable `$MNr` abfangen

2. **In Ihr System einbinden** (siehe Abschnitt 4)
   - Via Include oder als Modul/Tab

3. **Testen** (siehe Abschnitt 6)
   - Mit Debug-Modus prüfen

**Das war's!** Keine einzelnen PHP-Dateien anpassen, keine Datenbank-Views erstellen, keine Funktionen schreiben.

---

## 8. Anpassung des BerechtigteAdapter (falls nötig)

Falls Ihre `berechtigte`-Tabelle andere Werte für `Funktion` oder `aktiv` nutzt, können Sie den `BerechtigteAdapter` anpassen:

**Datei:** `/Sitzungsverwaltung/adapters/MemberAdapter.php`

**Rollen-Mapping ändern** (Zeile 154-162):
```php
private function mapRole($funktion, $aktiv) {
    if ($aktiv == 19) return 'Vorstand';

    $roleMapping = [
        'GF' => 'Geschäftsführung',     // ANPASSEN: Ihre Funktions-Kürzel
        'SV' => 'Assistenz',
        'RL' => 'Führungsteam',
        // ... weitere Mappings
    ];
    return $roleMapping[$funktion] ?? 'Mitglied';
}
```

**Filter-Bedingung ändern** (Zeile 203-218):
```php
private function shouldInclude($row) {
    $aktiv = $row['aktiv'] ?? 0;
    $funktion = $row['Funktion'] ?? '';

    // ANPASSEN: Ihre Inklusionsbedingung
    return ($aktiv > 17) || in_array($funktion, ['RL', 'SV', 'AD', 'FP', 'GF']);
}
```

---

**Version:** 2.0
**Datum:** 2025-12-06
**Status:** Vollständig überarbeitet - nutzt vorhandenes Adapter-System ✅
