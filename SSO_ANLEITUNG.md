# SSO-Modus (Single Sign-On) - Anleitung

## Was ist der SSO-Modus?

Der SSO-Modus ermöglicht die Integration der Sitzungsverwaltung in ein bestehendes System, bei dem die Benutzer bereits authentifiziert sind. In diesem Modus:

- ✅ **Kein Login-Formular** - Benutzer sind bereits extern eingeloggt
- ✅ **Mitgliedsnummer als Kennung** - Statt Email/Passwort wird die MNr verwendet
- ✅ **Automatischer Login** - System loggt Benutzer automatisch ein
- ✅ **Flexible Datenquelle** - Externe Tabelle `berechtigte` statt `members`

---

## Konfiguration

### 1. SSO-Modus aktivieren

Öffnen Sie `config_adapter.php` und ändern Sie:

```php
// Login-Formular DEAKTIVIEREN
define('REQUIRE_LOGIN', false);

// Externe Tabelle verwenden
define('MEMBER_SOURCE', 'berechtigte');
```

### 2. SSO-Quelle konfigurieren

Wählen Sie, woher die Mitgliedsnummer kommt:

#### Option A: Hardcoded (nur für Tests!)

```php
define('SSO_SOURCE', 'hardcoded');
define('TEST_MEMBERSHIP_NUMBER', '0495018');  // Ihre Test-MNr
```

#### Option B: Session (empfohlen für Produktion)

```php
define('SSO_SOURCE', 'session');
// Die MNr wird aus $_SESSION['MNr'] gelesen
```

Ihr externes System muss dann `$_SESSION['MNr']` setzen, z.B.:

```php
// In Ihrem Haupt-System NACH dem Login:
$_SESSION['MNr'] = $benutzer['mitgliedsnummer'];
```

#### Option C: GET-Parameter

```php
define('SSO_SOURCE', 'get');
// Die MNr wird aus $_GET['MNr'] gelesen
```

URL: `https://example.com/Sitzungsverwaltung/index.php?MNr=0495018`

**⚠️ NICHT empfohlen** für Produktion (Sicherheitsrisiko!)

#### Option D: POST-Parameter

```php
define('SSO_SOURCE', 'post');
// Die MNr wird aus $_POST['MNr'] gelesen
```

---

## Feld-Mapping (berechtigte → Standard)

Die externe Tabelle `berechtigte` wird automatisch gemappt:

| berechtigte-Feld | Standard-Feld | Beschreibung |
|------------------|---------------|--------------|
| `ID` | `member_id` | Eindeutige ID |
| `MNr` | `membership_number` | Mitgliedsnummer |
| `Vorname` | `first_name` | Vorname |
| `Name` | `last_name` | Nachname |
| `eMail` | `email` | E-Mail-Adresse |
| `funktionsbeschreibung` | `role` | Rolle (unverändert übernommen) |
| `aktiv` | `is_admin` | Admin wenn: `aktiv==18` ODER `MNr==0495018` |
| `aktiv` | `is_confidential` | Vertraulich wenn: `aktiv>17` |

**WICHTIG:** Die `role`-Beschränkungen aus der `members`-Tabelle gelten NICHT für `berechtigte`!
Die Rolle wird direkt aus `funktionsbeschreibung` übernommen.

---

## Testen

### Test 1: Hardcoded-Modus

**config_adapter.php:**
```php
define('REQUIRE_LOGIN', false);
define('MEMBER_SOURCE', 'berechtigte');
define('SSO_SOURCE', 'hardcoded');
define('TEST_MEMBERSHIP_NUMBER', '0495018');
```

**Erwartetes Verhalten:**
1. Aufruf von `index.php`
2. Automatischer Login mit MNr `0495018`
3. Direkt auf Hauptseite (Meetings)
4. Kein Login-Formular

### Test 2: Session-Modus

**config_adapter.php:**
```php
define('REQUIRE_LOGIN', false);
define('MEMBER_SOURCE', 'berechtigte');
define('SSO_SOURCE', 'session');
```

**Test-Script erstellen** (`test_sso_session.php`):
```php
<?php
session_start();
$_SESSION['MNr'] = '0495018';  // Ihre Test-MNr
header('Location: index.php');
?>
```

**Testen:**
1. Aufruf von `test_sso_session.php`
2. Automatische Weiterleitung zu `index.php`
3. Login erfolgt automatisch

### Test 3: Normaler Modus (Fallback)

**config_adapter.php:**
```php
define('REQUIRE_LOGIN', true);
define('MEMBER_SOURCE', 'members');
```

**Erwartetes Verhalten:**
- Login-Formular wird angezeigt
- Login mit Email/Passwort wie gewohnt

---

## Fehlerbehebung

### Fehler: "Keine Mitgliedsnummer übergeben"

**Ursache:** `SSO_SOURCE` ist falsch konfiguriert

**Lösung:**
- Bei `'session'`: Prüfen ob `$_SESSION['MNr']` gesetzt ist
- Bei `'get'`: URL mit `?MNr=...` aufrufen
- Bei `'hardcoded'`: `TEST_MEMBERSHIP_NUMBER` setzen

### Fehler: "Mitgliedsnummer wurde nicht gefunden"

**Ursache:** MNr existiert nicht in Tabelle `berechtigte` oder `aktiv = 0`

**Lösung:**
```sql
-- Prüfen ob MNr existiert:
SELECT * FROM berechtigte WHERE MNr = '0495018';

-- Prüfen ob aktiv:
SELECT * FROM berechtigte WHERE MNr = '0495018' AND aktiv > 0;
```

### SSO funktioniert nicht, Login-Formular wird angezeigt

**Ursache:** `REQUIRE_LOGIN` ist noch `true`

**Lösung:** In `config_adapter.php` ändern:
```php
define('REQUIRE_LOGIN', false);  // WICHTIG: false für SSO!
```

---

## Sicherheitshinweise

### ✅ Empfohlene Konfiguration (Produktion)

```php
define('REQUIRE_LOGIN', false);
define('MEMBER_SOURCE', 'berechtigte');
define('SSO_SOURCE', 'session');  // Session-basiert
define('TEST_MEMBERSHIP_NUMBER', null);  // Deaktiviert
```

Ihr Hauptsystem setzt dann:
```php
$_SESSION['MNr'] = $authenticated_user['membership_number'];
```

### ❌ NICHT für Produktion

```php
// UNSICHER - nur für Tests!
define('SSO_SOURCE', 'get');  // MNr in URL sichtbar
define('SSO_SOURCE', 'hardcoded');  // Alle Benutzer gleiche MNr
```

---

## Integration in bestehendes System

### Beispiel: WordPress Integration

```php
// In functions.php oder Custom Plugin
add_action('init', function() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $membership_number = get_user_meta($user->ID, 'membership_number', true);

        if ($membership_number) {
            $_SESSION['MNr'] = $membership_number;
        }
    }
});
```

### Beispiel: Eigenes System

```php
// Nach erfolgreichem Login in Ihrem System:
if ($login_successful) {
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['MNr'] = $user['membership_number'];  // Für Sitzungsverwaltung

    // Weiterleitung zur Sitzungsverwaltung:
    header('Location: /Sitzungsverwaltung/');
}
```

---

## Zurück zum normalen Login-Modus

```php
// In config_adapter.php:
define('REQUIRE_LOGIN', true);  // Login-Formular aktivieren
define('MEMBER_SOURCE', 'members');  // Interne Tabelle verwenden
```

---

## Support

Bei Fragen oder Problemen:
1. Prüfen Sie die Konfiguration in `config_adapter.php`
2. Testen Sie mit `test_connection.php` (Diagnose-Script)
3. Prüfen Sie die Datenbank-Felder in Tabelle `berechtigte`
4. Siehe auch: `MIGRATION_ANLEITUNG.md` für Adapter-Details
