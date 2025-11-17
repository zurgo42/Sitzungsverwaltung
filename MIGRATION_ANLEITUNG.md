# Migration zur flexiblen Mitgliederverwaltung

## Für Entwickler und Nachfolger

Diese Anleitung erklärt, wie das System so umgebaut wurde, dass es mit verschiedenen Mitgliedertabellen arbeiten kann.

---

## Das Problem

Das System nutzte ursprünglich eine `members` Tabelle. Aber in vielen Installationen gibt es bereits eine andere Mitgliederverwaltung mit anderer Tabellenstruktur (z.B. `berechtigte`).

**Früher:**
```php
// Direkt aus members-Tabelle lesen
$stmt = $pdo->query("SELECT * FROM members");
$members = $stmt->fetchAll();
```

**Problem:** Was wenn die Tabelle `berechtigte` heißt und andere Felder hat?
- `ID` statt `member_id`
- `Vorname` statt `first_name`
- `Name` statt `last_name`
- etc.

---

## Die Lösung: Wrapper-Funktionen

Statt direkt SQL zu schreiben, nutzen wir jetzt **Funktionen**:

```php
// Neue Methode - funktioniert mit JEDER Tabelle
$members = get_all_members($pdo);
```

Diese Funktion:
1. Schaut in der Konfiguration nach, welche Tabelle genutzt werden soll
2. Holt die Daten aus der richtigen Tabelle
3. Wandelt die Felder in ein einheitliches Format um
4. Gibt sie zurück

**Das Ergebnis ist IMMER gleich**, egal welche Tabelle dahinter steckt!

---

## Wichtige Dateien

### 1. `config_adapter.php` - Die Konfiguration
Hier wird festgelegt, welche Tabelle verwendet wird:

```php
// Standard-Tabelle (members)
define('MEMBER_SOURCE', 'members');

// ODER externe Tabelle (berechtigte)
define('MEMBER_SOURCE', 'berechtigte');
```

**Das ist die EINZIGE Zeile, die Sie ändern müssen, um umzuschalten!**

### 2. `member_functions.php` - Die Funktionen
Hier sind alle Funktionen definiert:
- `get_all_members($pdo)` - Alle Mitglieder holen
- `get_member_by_id($pdo, $id)` - Ein Mitglied nach ID
- `create_member($pdo, $data)` - Neues Mitglied anlegen
- `update_member($pdo, $id, $data)` - Mitglied ändern
- `delete_member($pdo, $id)` - Mitglied löschen
- `authenticate_member($pdo, $email, $password)` - Login

**Diese Funktionen IMMER verwenden**, nicht direkt SQL schreiben!

### 3. `adapters/MemberAdapter.php` - Die Übersetzung
Hier passiert die "Magie":
- Übersetzt zwischen verschiedenen Tabellenstrukturen
- Sie müssen diese Datei NICHT verstehen, um das System zu nutzen
- Nur bei neuen Tabellen-Typen anpassen

---

## Migration: Schritt für Schritt

### Phase 1: Vorbereitung ✅ ERLEDIGT

- [x] `member_functions.php` erstellt
- [x] `adapters/MemberAdapter.php` erstellt
- [x] `config_adapter.php` erstellt
- [x] Dokumentation geschrieben

### Phase 2: Code anpassen (IN ARBEIT)

Folgende Dateien müssen angepasst werden:

#### A. Kritische Dateien (sofort):
- [ ] `auth.php` - Login-Logik
- [ ] `process_admin.php` - Admin-Verwaltung
- [ ] `tab_admin.php` - Admin-Anzeige

#### B. Wichtige Dateien (bald):
- [ ] `functions.php` - Helper-Funktionen
- [ ] `tab_meetings.php` - Meeting-Verwaltung
- [ ] `process_meetings.php` - Meeting-Verarbeitung

#### C. Weitere Dateien (später):
- [ ] Alle anderen Dateien, die Mitgliederdaten nutzen

### Phase 3: Testing
1. Mit `MEMBER_SOURCE='members'` testen (sollte wie bisher funktionieren)
2. Mit `MEMBER_SOURCE='berechtigte'` testen (neue Funktionalität)
3. Hin- und herschalten und prüfen

---

## Beispiel: Datei anpassen

### VORHER (direkte SQL):
```php
// In tab_admin.php
$stmt = $pdo->query("SELECT * FROM members ORDER BY last_name");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### NACHHER (mit Funktionen):
```php
// In tab_admin.php
require_once 'member_functions.php';

$members = get_all_members($pdo);
```

**Das war's!** Die Variable `$members` hat exakt die gleiche Struktur wie vorher.

---

## Häufige Aufgaben

### Mitglied nach ID holen
```php
// VORHER
$stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->execute([5]);
$member = $stmt->fetch();

// NACHHER
$member = get_member_by_id($pdo, 5);
```

### Neues Mitglied anlegen
```php
// VORHER
$stmt = $pdo->prepare("INSERT INTO members (first_name, last_name, email, ...) VALUES (?, ?, ?, ...)");
$stmt->execute(['Max', 'Mustermann', 'max@example.com', ...]);

// NACHHER
create_member($pdo, [
    'first_name' => 'Max',
    'last_name' => 'Mustermann',
    'email' => 'max@example.com',
    // ...
]);
```

### Mitglied ändern
```php
// VORHER
$stmt = $pdo->prepare("UPDATE members SET first_name = ?, last_name = ? WHERE member_id = ?");
$stmt->execute(['Maxine', 'Musterfrau', 5]);

// NACHHER
update_member($pdo, 5, [
    'first_name' => 'Maxine',
    'last_name' => 'Musterfrau'
]);
```

---

## Feldnamen - WICHTIG!

**Verwenden Sie IMMER diese Feldnamen**, auch wenn Ihre Tabelle andere hat:

| Standard-Feldname | In members | In berechtigte |
|-------------------|------------|----------------|
| `member_id` | member_id | ID |
| `membership_number` | membership_number | MNr |
| `first_name` | first_name | Vorname |
| `last_name` | last_name | Name |
| `email` | email | eMail |
| `role` | role | Funktion (gemappt) |
| `is_admin` | is_admin | (aus Funktion berechnet) |
| `is_confidential` | is_confidential | aktiv (>= 2) |
| `created_at` | created_at | angelegt |

**Die Adapter übernehmen die Umwandlung automatisch!**

---

## Troubleshooting

### "Funktion get_all_members existiert nicht"
→ `require_once 'member_functions.php';` am Anfang der Datei fehlt

### "MEMBER_SOURCE ist nicht definiert"
→ `require_once 'config_adapter.php';` in member_functions.php fehlt
→ Oder config_adapter.php wurde noch nicht angelegt

### "Felder haben falsche Namen"
→ Prüfen Sie, ob Sie die Standard-Feldnamen verwenden (siehe Tabelle oben)
→ Niemals direkt auf DB-Feldnamen zugreifen!

### "Änderungen werden nicht gespeichert"
→ Prüfen Sie MEMBER_SOURCE in config_adapter.php
→ Bei 'berechtigte': Prüfen Sie das Mapping im BerechtigteAdapter

---

## Für fortgeschrittene Entwickler

### Neue Tabellenstruktur hinzufügen

Wenn Sie eine weitere Tabellenstruktur unterstützen wollen:

1. Öffnen Sie `adapters/MemberAdapter.php`
2. Kopieren Sie die `BerechtigteAdapter` Klasse
3. Benennen Sie sie um (z.B. `MeineTabelleAdapter`)
4. Passen Sie die SQL-Queries und Mappings an
5. Erweitern Sie die `MemberAdapterFactory`

Beispiel siehe `BerechtigteAdapter` in der Datei.

---

## Zusammenfassung

✅ **Ein System - Mehrere Datenquellen**
- Gleicher Code funktioniert mit verschiedenen Tabellen
- Umschalten via Konfiguration
- Keine Datenduplizierung

✅ **Einfach zu warten**
- Klare, prozedurale Funktionen
- Gute Dokumentation
- Schrittweise Migration möglich

✅ **Zukunftssicher**
- Neue Tabellenstrukturen einfach hinzufügbar
- Bestehender Code läuft weiter
- Testbar und sicher

---

## Nächste Schritte

1. **Backup machen** ✅ (Sie machen das gerade)
2. **Code anpassen** - siehe "Phase 2" oben
3. **Testen** - mit beiden Datenquellen
4. **In Produktion** - wenn alles funktioniert

Bei Fragen oder Problemen: Siehe diese Dokumentation oder `member_functions.php` (dort ist alles erklärt).
