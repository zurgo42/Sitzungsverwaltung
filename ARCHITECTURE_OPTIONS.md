# Architektur-Optionen für flexible Datenbankanbindung

## Problem
Das System verwendet eine `members` Tabelle, aber es soll auch mit anderen Tabellenstrukturen arbeiten können (z.B. Ihre `berechtigte` Tabelle), ohne Datenduplizierung.

---

## Option 1: Data Access Layer / Adapter Pattern ⭐ **EMPFOHLEN**

### Beschreibung
Eine PHP-Klasse übersetzt zwischen Ihrer Original-Tabelle und der erwarteten Struktur.

### Implementierung
```php
// Siehe: adapters/MemberAdapter.php
$memberAdapter = MemberAdapterFactory::create($pdo, 'berechtigte');
$members = $memberAdapter->getAllMembers();
// Gibt Daten in Standard-Format zurück, egal welche Tabelle
```

### Vorteile
- ✅ **Keine Datenduplizierung** - Daten bleiben in Original-Tabelle
- ✅ **Immer aktuell** - Kein Sync nötig
- ✅ **Zentrale Änderungen** - Nur Adapter anpassen
- ✅ **Testbar** - Mock-Adapter für Unit-Tests
- ✅ **Mehrere Quellen** - Verschiedene Tabellen parallel nutzbar
- ✅ **Schrittweise Migration** - Kann parallel zu bestehendem Code laufen

### Nachteile
- ⚠️ Erfordert Refactoring des Codes
- ⚠️ Initiale Entwicklungszeit

### Aufwand
**Einmalig:** 4-6 Stunden für vollständige Implementierung
**Wartung:** Minimal - nur bei neuen Feldern

### Dateien
- `adapters/MemberAdapter.php` - Adapter-Implementierung ✅ ERSTELLT
- `config_adapter.php` - Konfiguration ✅ ERSTELLT
- `ADAPTER_USAGE_EXAMPLE.php` - Verwendungsbeispiele ✅ ERSTELLT

---

## Option 2: Database View

### Beschreibung
Ein Datenbank-View mappt Ihre `berechtigte` Tabelle auf die erwartete Struktur.

### Implementierung
```sql
CREATE VIEW members AS
SELECT
    ID as member_id,
    MNr as membership_number,
    Vorname as first_name,
    Name as last_name,
    eMail as email,
    CASE
        WHEN Funktion = 'Vorstand' THEN 'vorstand'
        WHEN Funktion = 'Geschäftsführung' THEN 'gf'
        ELSE 'Mitglied'
    END as role,
    CASE
        WHEN Funktion IN ('Vorstand', 'Geschäftsführung') THEN 1
        ELSE 0
    END as is_admin,
    CASE
        WHEN aktiv >= 2 THEN 1
        ELSE 0
    END as is_confidential,
    angelegt as created_at
FROM berechtigte
WHERE aktiv > 0;
```

### Vorteile
- ✅ **Einfachste Lösung** - Kein Code-Änderung nötig
- ✅ **Keine Datenduplizierung**
- ✅ **Immer aktuell**
- ✅ **Transparent** - Anwendung merkt nichts

### Nachteile
- ⚠️ **Nur lesend** - INSERT/UPDATE komplizierter (braucht INSTEAD OF Trigger)
- ⚠️ **Datenbank-spezifisch** - Bei DB-Wechsel neu erstellen
- ⚠️ **Komplexe Mappings schwierig** - Bei Passwort-Hashing etc.
- ⚠️ **Performance** - View kann langsamer sein als Tabelle

### Aufwand
**Einmalig:** 1-2 Stunden
**Wartung:** Mittel - bei Schema-Änderungen anpassen

---

## Option 3: Konfigurations-basiertes Mapping

### Beschreibung
Eine Konfigurationsdatei definiert Feld-Mappings, generischer Code verwendet diese.

### Implementierung
```php
// config/table_mapping.php
$TABLE_MAPPING = [
    'table_name' => 'berechtigte',
    'fields' => [
        'member_id' => 'ID',
        'first_name' => 'Vorname',
        'last_name' => 'Name',
        'email' => 'eMail',
        // ...
    ],
    'computed_fields' => [
        'role' => 'CASE WHEN Funktion = "Vorstand" THEN "vorstand" ...',
        'is_admin' => 'CASE WHEN Funktion IN ("Vorstand", "GF") THEN 1 ELSE 0 END'
    ]
];
```

### Vorteile
- ✅ **Flexibel** - Einfache Anpassung via Konfiguration
- ✅ **Keine Datenduplizierung**
- ✅ **Programmatisch** - In Code nutzbar

### Nachteile
- ⚠️ **Komplexe Logik schwierig** - Bei verschachtelten Mappings
- ⚠️ **Erfordert generischen Query-Builder**
- ⚠️ **Fehleranfällig** - Tippfehler in Config nicht immer sofort erkannt

### Aufwand
**Einmalig:** 3-4 Stunden
**Wartung:** Mittel

---

## Option 4: Sync-Script (Ihre Idee)

### Beschreibung
Ein Script kopiert Daten periodisch von `berechtigte` nach `members`.

### Implementierung
```php
// sync_members.php (als Cronjob alle 5 Minuten)
$berechtigte = $pdo->query("SELECT * FROM berechtigte WHERE aktiv > 0")->fetchAll();

foreach ($berechtigte as $b) {
    $pdo->prepare("
        INSERT INTO members (member_id, first_name, last_name, email, ...)
        VALUES (?, ?, ?, ?, ...)
        ON DUPLICATE KEY UPDATE first_name = ?, last_name = ?, ...
    ")->execute([...]);
}
```

### Vorteile
- ✅ **Einfach zu implementieren** - Klares Konzept
- ✅ **Keine Code-Änderungen** - System arbeitet wie bisher
- ✅ **Performance** - Lesen aus `members` ist schnell

### Nachteile
- ❌ **Datenduplizierung** - Daten doppelt gespeichert
- ❌ **Nicht sofort aktuell** - Verzögerung durch Sync-Intervall
- ❌ **Konsistenzprobleme** - Was wenn Sync fehlschlägt?
- ❌ **Speicherplatz** - Doppelte Datenhaltung
- ❌ **Wartungsaufwand** - Cronjob muss laufen, überwacht werden

### Aufwand
**Einmalig:** 2-3 Stunden
**Wartung:** Hoch - Monitoring, Fehlerbehandlung

---

## Vergleichstabelle

| Kriterium | Option 1: Adapter | Option 2: View | Option 3: Config | Option 4: Sync |
|-----------|-------------------|----------------|------------------|----------------|
| **Keine Duplikation** | ✅ Ja | ✅ Ja | ✅ Ja | ❌ Nein |
| **Immer aktuell** | ✅ Ja | ✅ Ja | ✅ Ja | ⚠️ Verzögert |
| **Einfache Implementierung** | ⚠️ Mittel | ✅ Einfach | ⚠️ Mittel | ✅ Einfach |
| **Flexibilität** | ✅ Hoch | ⚠️ Mittel | ✅ Hoch | ⚠️ Niedrig |
| **Performance** | ✅ Gut | ⚠️ OK | ✅ Gut | ✅ Sehr gut |
| **Testbarkeit** | ✅ Sehr gut | ⚠️ Schwierig | ✅ Gut | ⚠️ Mittel |
| **Wartungsaufwand** | ✅ Niedrig | ⚠️ Mittel | ⚠️ Mittel | ❌ Hoch |
| **Mehrere Quellen** | ✅ Ja | ⚠️ Schwierig | ⚠️ Schwierig | ⚠️ Schwierig |

---

## Empfehlung

### **Für Ihr Projekt: Option 1 (Adapter Pattern)**

**Begründung:**
1. Sie haben bereits eine funktionierende `berechtigte` Tabelle
2. Keine Datenduplizierung gewünscht
3. Zukunftssicherheit - weitere Systeme könnten hinzukommen
4. Professionelle, wartbare Architektur

### **Migrationsstrategie:**

#### Phase 1: Vorbereitung (1-2 Stunden)
1. ✅ Adapter-Dateien erstellt
2. Adapter mit Ihrer `berechtigte` Tabelle testen
3. Mapping-Logik anpassen (Funktion → role, aktiv → is_confidential)

#### Phase 2: Schrittweise Migration (3-4 Stunden)
1. **Datei 1:** `tab_admin.php` - Admin-Bereich umstellen
2. **Datei 2:** `auth.php` - Login-Logik umstellen
3. **Datei 3:** `functions.php` - Helper-Funktionen umstellen
4. **Weitere Dateien** nach und nach

#### Phase 3: Testing & Rollout (1-2 Stunden)
1. Beide Systeme parallel testen
2. Wenn stabil: Umschalten via `config_adapter.php`
3. Optional: `members` Tabelle löschen

### **Sofort-Einsatz möglich:**

Sie können **heute noch** anfangen:
```php
// In config_adapter.php
define('MEMBER_ADAPTER_TYPE', 'berechtigte');  // UMSCHALTEN!

// Ab jetzt nutzt das System Ihre berechtigte-Tabelle
```

---

## Nächste Schritte

Wenn Sie sich für Option 1 entscheiden:

1. **Testen Sie den BerechtigteAdapter:**
   ```bash
   php -f test_adapter.php
   ```

2. **Passen Sie die Mappings an:**
   - Öffnen Sie `adapters/MemberAdapter.php`
   - Prüfen Sie `mapRoleFromFunktion()`
   - Prüfen Sie `isConfidential()` (aktiv-Logik)
   - Passen Sie an Ihre Anforderungen an

3. **Erste Datei migrieren:**
   - Empfehlung: Starten Sie mit `tab_admin.php`
   - Siehe Beispiel in `ADAPTER_USAGE_EXAMPLE.php`

4. **Schrittweise ausrollen**

Haben Sie Fragen zur Implementierung? Ich helfe gerne weiter!
