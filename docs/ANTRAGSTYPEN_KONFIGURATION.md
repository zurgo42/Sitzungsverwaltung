# Antragstypen-Konfiguration

## Überblick

Das System unterstützt drei vordefinierte Antragstypen:

- **V** = Verfügung (kleinere Ausgaben und operative Entscheidungen)
- **R** = Ressortbeschluss (Entscheidungen innerhalb eines Ressorts/Bereichs)
- **B** = Vorstandsbeschluss (Entscheidungen des Vorstands)

Jeder Typ kann individuell aktiviert/deaktiviert und konfiguriert werden.

## Konfiguration in svconfig

### Pro Typ (V, R, B):

| Config Key | Typ | Beschreibung | Standard |
|-----------|-----|--------------|----------|
| `bart_X_aktiv` | boolean | Typ ist aktiviert | 1 |
| `bart_X_bezeichnung` | text | Anzeigename | z.B. "Verfügung" |
| `bart_X_beschreibung` | text | Erklärung des Typs | - |
| `bart_X_betrag_aktiv` | boolean | Betragsgrenze aktiv | V:1, R:1, B:0 |
| `bart_X_betrag_limit` | number | Max. Betrag in Euro | V:500, R:2000, B:0 |
| `bart_X_wartezeit_aktiv` | boolean | Wartezeit aktiv | 1 |
| `bart_X_wartezeit_tage` | number | Wartezeit in Tagen | V:3, R:7, B:14 |
| `bart_X_freigabe_vereinfacht` | boolean | Vereinfachte Freigabe | V:1, R:1, B:0 |

### Globale Einstellungen:

| Config Key | Typ | Beschreibung | Standard |
|-----------|-----|--------------|----------|
| `bart_show_betrag_in_liste` | boolean | Beträge in Liste anzeigen | 1 |
| `bart_pflicht_bei_betrag` | number | Betragsangabe Pflicht ab (€) | 100 |

## Auswirkungen auf das System

### 1. Antragserstellung (antrag_bearbeiten.php)

**Typ-Auswahl:**
- Nur aktive Typen werden im Dropdown angezeigt
- Bezeichnungen aus `bart_X_bezeichnung`
- Tooltip mit `bart_X_beschreibung`

**Betragsvalidierung:**
```php
if ($bart_config["bart_{$bart}_betrag_aktiv"] == '1') {
    $limit = (int)$bart_config["bart_{$bart}_betrag_limit"];
    if ($betrag > $limit) {
        // Fehler: Betrag überschreitet Limit für diesen Typ
    }
}
```

**Betrags-Pflichtfeld:**
```php
$betrag_required = $betrag >= $bart_config['bart_pflicht_bei_betrag'];
```

### 2. Antragsliste (tab_proposals.php, antragsliste.php)

**Betrags-Spalte:**
```php
if ($bart_config['bart_show_betrag_in_liste'] == '1') {
    // Betrags-Spalte anzeigen
}
```

**Typ-Filter:**
- Nur aktive Typen im Filter-Dropdown
- Bezeichnungen aus Konfiguration

### 3. Workflow (Freigabe & Abstimmung)

**Wartezeit-Prüfung:**
```php
if ($bart_config["bart_{$bart}_wartezeit_aktiv"] == '1') {
    $wartezeit_tage = (int)$bart_config["bart_{$bart}_wartezeit_tage"];
    $frühester_beschluss = strtotime("+{$wartezeit_tage} days", strtotime($antrag_datum));
    
    if (time() < $frühester_beschluss) {
        // Noch in Wartezeit - Beschluss nicht möglich
    }
}
```

**Vereinfachte Freigabe:**
```php
if ($bart_config["bart_{$bart}_freigabe_vereinfacht"] == '1') {
    // Automatische Freigabe-Regeln anwenden:
    // - Bei kleinen Beträgen automatisch freigeben
    // - Bei bekannten Antragstellern Vorprüfung überspringen
    // - etc.
}
```

### 4. Antragsansicht (antrag_ansehen.php)

**Typ-Anzeige:**
```php
$typ_bezeichnung = $bart_config["bart_{$antrag['bart']}_bezeichnung"];
echo "<strong>Typ:</strong> $typ_bezeichnung";

if ($bart_config["bart_{$antrag['bart']}_betrag_aktiv"] == '1') {
    $limit = $bart_config["bart_{$antrag['bart']}_betrag_limit"];
    echo " (max. {$limit}€)";
}
```

**Wartezeit-Anzeige:**
```php
if ($bart_config["bart_{$antrag['bart']}_wartezeit_aktiv"] == '1') {
    $tage = $bart_config["bart_{$antrag['bart']}_wartezeit_tage"];
    $ablauf = strtotime("+{$tage} days", strtotime($antrag['datum']));
    
    echo "Frühester Beschluss: " . date('d.m.Y', $ablauf);
}
```

## Szenarien

### Kleiner Verein (nur Vorstandsbeschlüsse)

```sql
UPDATE svconfig SET config_value = '0' WHERE config_key = 'bart_V_aktiv';
UPDATE svconfig SET config_value = '0' WHERE config_key = 'bart_R_aktiv';
UPDATE svconfig SET config_value = '1' WHERE config_key = 'bart_B_aktiv';
```

Resultat:
- Nur Typ B verfügbar
- Keine Typ-Auswahl nötig (wird automatisch auf B gesetzt)
- Vereinfachtes Interface

### Verein ohne Betragsgrenzen

```sql
UPDATE svconfig SET config_value = '0' WHERE config_key LIKE 'bart_%_betrag_aktiv';
```

Resultat:
- Keine Betragsvalidierung
- Betragsfeld optional (außer wenn > pflicht_bei_betrag)

### Verein ohne Wartezeiten

```sql
UPDATE svconfig SET config_value = '0' WHERE config_key LIKE 'bart_%_wartezeit_aktiv';
```

Resultat:
- Sofortige Beschlussfassung möglich
- Keine Wartezeit-Anzeige

## Migration bestehender Anträge

Beim Deaktivieren eines Typs müssen bestehende Anträge berücksichtigt werden:

```sql
-- Prüfen ob Typ V deaktiviert werden kann
SELECT COUNT(*) FROM antraege WHERE bart = 'V' AND status NOT IN ('X', 'Z');

-- Wenn > 0: Warnung anzeigen, nicht deaktivieren
-- Alternative: Anträge auf anderen Typ umstellen
```

## Code-Integration Checkliste

Beim Entwickeln/Anpassen von Antragsskripten:

- [ ] Config aus svconfig laden (category = 'antragstypen')
- [ ] `bart_X_aktiv` prüfen bevor Typ angezeigt wird
- [ ] Bezeichnungen aus `bart_X_bezeichnung` verwenden
- [ ] Betragsgrenzen validieren wenn `bart_X_betrag_aktiv`
- [ ] Wartezeiten prüfen wenn `bart_X_wartezeit_aktiv`
- [ ] Vereinfachte Freigabe anwenden wenn `bart_X_freigabe_vereinfacht`
- [ ] Fallback für deaktivierte Typen (z.B. auf B umleiten)
- [ ] Admin-Warnung bei Änderungen mit betroffenen Anträgen

## Beispiel: Config laden

```php
// Config laden
$bart_stmt = $pdo->query("
    SELECT config_key, config_value 
    FROM svconfig 
    WHERE category = 'antragstypen'
");
$bart_config = [];
while ($row = $bart_stmt->fetch()) {
    $bart_config[$row['config_key']] = $row['config_value'];
}

// Typ prüfen
function ist_typ_aktiv($typ, $config) {
    return ($config["bart_{$typ}_aktiv"] ?? '0') == '1';
}

// Aktive Typen für Dropdown
$aktive_typen = [];
foreach (['V', 'R', 'B'] as $typ) {
    if (ist_typ_aktiv($typ, $bart_config)) {
        $aktive_typen[$typ] = $bart_config["bart_{$typ}_bezeichnung"];
    }
}
```
