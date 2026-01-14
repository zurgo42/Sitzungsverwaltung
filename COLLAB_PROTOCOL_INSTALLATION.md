# Kollaboratives Protokoll - Installations-Anleitung

## Schritt 1: Datenbank-Migration ausführen

Du musst die Datenbank-Tabellen erstellen, damit das kollaborative Protokoll funktioniert.

### Option A: Via Browser (einfachste Methode)

1. Öffne im Browser: `http://deine-domain.de/Sitzungsverwaltung/run_collab_migration.php`
2. Du solltest sehen: "✅ Migration abgeschlossen!"
3. Fertig!

### Option B: Via Kommandozeile

```bash
cd /pfad/zu/Sitzungsverwaltung
php run_collab_migration.php
```

### Option C: Direkt via MySQL

```bash
mysql -u username -p datenbank_name < migrations/add_collaborative_protocol.sql
```

## Schritt 2: Funktionalität testen

1. **Neues Meeting erstellen:**
   - Gehe zu "Sitzungen" → "Neue Sitzung erstellen"
   - Aktiviere die Checkbox: ✅ "Kollaboratives Protokoll"
   - Speichern

2. **Meeting starten:**
   - Meeting öffnen
   - "Sitzung starten"

3. **Protokoll testen:**
   - Als Protokollführung: Grünes Protokoll-Feld sollte sichtbar sein
   - Als Teilnehmer: Ebenfalls grünes Protokoll-Feld (wenn kollaborativ aktiv)
   - Beginne zu tippen → Status sollte sich ändern: "✏️ Schreibe..."
   - Nach 2 Sekunden: "💾 Speichere..." → "✓ Gespeichert"

4. **Mit mehreren Teilnehmern testen:**
   - Öffne die Sitzung in 2-3 Browser-Tabs (verschiedene Benutzer)
   - Alle sollten ins Protokoll schreiben können
   - Oben sollte angezeigt werden: "✍️ Hermann schreibt gerade..."
   - Änderungen sollten bei allen erscheinen

## Was wurde erstellt:

### Datenbank-Änderungen:
- **svmeetings.collaborative_protocol** - Spalte für Modus-Auswahl (0/1)
- **svprotocol_versions** - Tabelle für Versions-Historie
- **svprotocol_editing** - Tabelle für "wer schreibt gerade"

### API-Endpunkte:
- `api/protocol_autosave.php` - Auto-Save (alle 2s)
- `api/protocol_get_updates.php` - Updates laden

### JavaScript:
- `js/collab_protocol.js` - Auto-Sync Logic

## Fehlerbehebung:

### Problem: "Database migration required"
- **Ursache:** Migration wurde nicht ausgeführt
- **Lösung:** Siehe Schritt 1

### Problem: "Failed to fetch"
- **Ursache:** API-Dateien nicht erreichbar oder Berechtigungsproblem
- **Lösung:**
  - Prüfe ob `api/protocol_autosave.php` existiert
  - Prüfe Dateiberechtigungen: `chmod 644 api/protocol_*.php`

### Problem: "Not authenticated"
- **Ursache:** Session abgelaufen
- **Lösung:** Neu einloggen

### Problem: Änderungen erscheinen nicht bei anderen
- **Ursache:** JavaScript-Fehler oder Netzwerk-Problem
- **Lösung:**
  - Browser-Konsole öffnen (F12)
  - Fehler in der Konsole prüfen
  - Netzwerk-Tab prüfen ob API-Requests erfolgreich sind

## Bekannte Einschränkungen:

1. **"Last Write Wins"**: Bei gleichzeitigen Änderungen an derselben Stelle gewinnt die letzte Speicherung
2. **Cursor-Position**: Wird ungefähr beibehalten, kann aber bei starken Änderungen anderer springen
3. **Konflikte**: Werden angezeigt, aber nicht automatisch gemergt

## Klassischer Modus vs. Kollaborativer Modus:

| Feature | Klassisch | Kollaborativ |
|---------|-----------|--------------|
| Wer kann schreiben | Nur Protokollführung | Alle Teilnehmer |
| Speichern | Manuell per Button | Auto-Save alle 2s |
| Updates | Keine | Auto-Load alle 2s |
| Anzeige "wer schreibt" | Nein | Ja |
| Konflikt-Warnung | Nein | Ja |
| Farbe | Blau | Grün |

## Support:

Bei Problemen bitte folgende Informationen bereitstellen:
1. Browser-Konsole (F12 → Console Tab)
2. Netzwerk-Tab (F12 → Network Tab) - Fehlerhafte Requests
3. PHP Error Log
