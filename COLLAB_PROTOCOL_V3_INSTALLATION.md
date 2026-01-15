# Kollaboratives Protokoll Version 3.0 - Installation

## Überblick: Master-Slave Queue-System

**Version 3.0** ersetzt das instabile Peer-to-Peer System (v2.x) durch eine stabile Master-Slave Architektur mit Queue.

### Unterschied zu v2.x:

| Version 2.x (ALT) | Version 3.0 (NEU) |
|-------------------|-------------------|
| ❌ Peer-to-Peer | ✅ Master-Slave |
| ❌ Race Conditions | ✅ Chronologische Queue |
| ❌ Texte verschwinden | ✅ Stabile Verarbeitung |
| ❌ Chaos bei 2+ Editoren | ✅ Vorhersehbar |

### Neue Architektur:

```
PROTOKOLLFÜHRUNG (Master)
├── Hauptfenster (grün)
│   └── Zeigt Source of Truth
│   └── Editierbar → Queue
│   └── Queue-Anzeige
└── Fortsetzungsfeld (blau)
    └── Neuer Text direkt anhängen
    └── Priorisiert (kein Queue)
    └── Auto-clear nach Übertragung

ANDERE USER (Slave)
└── Hauptfenster (orange)
    └── Zeigt Source of Truth
    └── Editierbar → Queue
    └── Status: "In Queue (Pos. X)"

QUEUE PROCESSING
└── Alle 2 Sekunden
└── Chronologisch (FIFO)
└── Last Write Wins
```

## Installation

### Schritt 1: Migration ausführen

**Option A: Via Browser (empfohlen)**

1. Öffne: `http://deine-domain.de/Sitzungsverwaltung/run_queue_migration.php`
2. Warte auf: "✅ Migration abgeschlossen!"

**Option B: Via Kommandozeile**

```bash
cd /pfad/zu/Sitzungsverwaltung
php run_queue_migration.php
```

**Option C: Direkt via MySQL**

```bash
mysql -u username -p datenbank_name < migrations/add_protocol_queue_system.sql
```

### Schritt 2: Hard-Refresh im Browser

**WICHTIG:** Browser-Cache leeren!

- **Windows/Linux**: `Ctrl + F5`
- **Mac**: `Cmd + Shift + R`

### Schritt 3: Console checken

Öffne Browser-Console (F12 → Console-Tab)

Sollte anzeigen:
```
📋 Kollaboratives Protokoll v3.0 - Master-Slave mit Queue
Initialisiere Hauptfelder: X, Fortsetzungsfelder: Y
Kollaboratives Protokoll initialisiert (Queue-System)
```

## Benutzung

### Als Protokollführung:

**Du siehst 2 Felder:**

1. **Hauptfenster (grün)** - "Protokoll (Hauptsystem)"
   - Zeigt den aktuellen Stand, den alle sehen
   - Kann editiert werden (geht dann in Queue)
   - Oben rechts: Queue-Anzeige wenn Einträge warten
   - Status: "⏳ In Queue (Pos. X)" nach Edit

2. **Fortsetzungsfeld (blau)** - "Fortsetzungsfeld (priorisiert)"
   - Für schnelles Weiter-Schreiben während Sitzung
   - Text wird nach 2 Sekunden Pause direkt ans Hauptsystem angehängt
   - Feld wird nach Übertragung automatisch geleert
   - **PRIORISIERT**: Keine Queue, sofort übertragen
   - Status: "✅ Übertragen"

**Workflow:**
1. Tippe neuen Text ins Fortsetzungsfeld
2. Nach 2s Pause → automatisch ans Hauptfenster angehängt
3. Fortsetzungsfeld wird geleert
4. Weiter tippen für nächsten Absatz

**Queue verarbeiten:**
- Läuft automatisch alle 2 Sekunden
- Du siehst: "📥 Queue: 3 Einträge (Name1, Name2)"
- Einträge werden chronologisch verarbeitet
- Letzter Eintrag gewinnt (Last Write Wins)

### Als normaler User:

**Du siehst 1 Feld:**

**Hauptfenster (orange)** - "Protokoll (schreibt an Protokollführung)"
- Zeigt den aktuellen Stand
- Kann editiert werden
- Änderungen gehen in Queue
- Status: "⏳ In Queue (Pos. 2)"
- Nach Verarbeitung: Hauptfenster wird aktualisiert

**Workflow:**
1. Tippe deine Änderung/Ergänzung
2. Nach 1.5s Pause → automatisch in Queue gespeichert
3. Status zeigt Position in Queue
4. Warte auf Verarbeitung (alle 2s)
5. Hauptfenster wird aktualisiert wenn verarbeitet

## Technische Details

### Datenbank-Tabellen:

**svprotocol_changes_queue**
- Speichert alle Queue-Einträge
- Chronologische Verarbeitung (submitted_at)
- Markiert als processed nach Verarbeitung

**svagenda_items**
- Neue Spalte: `protocol_master_id` (wer ist Master)

### API-Endpunkte:

1. **protocol_queue_save.php**
   - Speichert Änderungen in Queue
   - Für: Normale User + Protokollführung (Hauptfenster-Edit)
   - Returns: Queue-Position

2. **protocol_secretary_append.php**
   - Hängt Text direkt ans Hauptsystem an
   - Nur für: Protokollführung (Fortsetzungsfeld)
   - Priorisiert (keine Queue)

3. **protocol_process_queue.php**
   - Verarbeitet Queue chronologisch
   - Läuft alle 2 Sekunden (von Protokollführung aufgerufen)
   - Last Write Wins

4. **protocol_get_updates.php**
   - Lädt aktuellen Stand
   - Returns: Queue-Größe, is_secretary Flag

### JavaScript-Logik:

**Protokollführung:**
- 2 Intervals: autoLoadMain (2s), queueProcess (2s)
- Hauptfenster → saveToQueue() nach 1.5s Pause
- Fortsetzungsfeld → saveAppendField() nach 2s Pause

**Normale User:**
- 1 Interval: autoLoadMain (2s)
- Hauptfenster → saveToQueue() nach 1.5s Pause
- Blockierung während eigener Eingabe

## Troubleshooting

### "Database migration required"

Migration noch nicht ausgeführt:
```bash
php run_queue_migration.php
```

### Console zeigt noch v2.1

Browser-Cache leeren:
- Ctrl + F5 (Windows/Linux)
- Cmd + Shift + R (Mac)

### Queue wird nicht verarbeitet

- Nur Protokollführung verarbeitet Queue
- Läuft automatisch alle 2 Sekunden
- Check Console für Fehler

### Text verschwindet immer noch

- Stelle sicher dass v3.0 läuft (Console checken)
- Hard-Refresh durchgeführt?
- Migration ausgeführt?

### Fortsetzungsfeld funktioniert nicht

- Nur Protokollführung hat Fortsetzungsfeld
- Bist du als Protokollführung eingeloggt?
- Check: Grüne Box oben, blaue Box unten

## FAQ

**Q: Kann ich zwischen v2.x und v3.0 wechseln?**
A: Nein. Nach Migration auf v3.0 solltest du dabei bleiben. Das alte System ist instabil.

**Q: Was passiert wenn Queue voll läuft?**
A: Queue wird alle 2 Sekunden verarbeitet. Bei normalem Gebrauch sollte sie nie lang werden.

**Q: Kann ich Queue manuell leeren?**
A: Queue wird automatisch verarbeitet. Du kannst `processQueue(item_id)` in Console aufrufen für sofortige Verarbeitung.

**Q: Was wenn Protokollführung offline geht?**
A: Queue-Processing stoppt. Einträge bleiben gespeichert. Beim nächsten Login wird Queue automatisch verarbeitet.

**Q: Wie löse ich echte Konflikte?**
A: Queue-System verhindert Konflikte. Last Write Wins, aber chronologisch. Bei echtem Konflikt: Protokollführung editiert Hauptfenster.

## Vorteile von v3.0

✅ **Stabil**: Keine verschwindenden Texte mehr
✅ **Vorhersehbar**: Chronologische Verarbeitung
✅ **Transparent**: Queue-Status sichtbar
✅ **Schnell**: Protokollführung kann weiter schreiben (Fortsetzungsfeld)
✅ **Fair**: Alle Änderungen werden erfasst
✅ **Einfach**: Klare Rollen (Master vs. Slave)

## Support

Bei Problemen:
1. Console checken (F12)
2. Migration ausgeführt?
3. Hard-Refresh gemacht?
4. Version 3.0 aktiv? (Console-Log)

---

**Version 3.0 - Stabil durch Master-Slave Queue-System**
