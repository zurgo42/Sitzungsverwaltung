# VTool Datenmigration

## Voraussetzungen

1. **Zwei Datenbanken** müssen existieren:
   - **Source**: VTool Datenbank (z.B. `vtool`)
   - **Target**: Sitzungsverwaltung Datenbank (z.B. `sitzungsverwaltung`)

2. **Datenbankzugriff**: Beide Datenbanken müssen vom gleichen MySQL-Server erreichbar sein

3. **PHP CLI**: Das Script muss über die Kommandozeile ausgeführt werden (nicht im Browser!)

## Schritte

### 1. Vorbereitung (einmalig)

```bash
cd /pfad/zu/Sitzungsverwaltung
```

### 2. Dry-Run (Test ohne Änderungen)

**Zeigt nur an was migriert werden würde:**

```bash
php migrations/migrate_vtool_data.php \
  --source-db=vtool \
  --target-db=sitzungsverwaltung \
  --dry-run
```

**Parameter:**
- `--source-db`: Name der VTool-Datenbank
- `--target-db`: Name der Sitzungsverwaltung-Datenbank
- `--dry-run`: Nur Simulation, keine Änderungen

### 3. Tatsächliche Migration

**Erst nach erfolgreichem Dry-Run!**

```bash
php migrations/migrate_vtool_data.php \
  --source-db=vtool \
  --target-db=sitzungsverwaltung \
  --execute
```

**WICHTIG:** 
- `--execute` führt die Migration tatsächlich aus
- Erstellt Backup vorher empfohlen!
- Bei Fehlern wird automatisch ein Rollback durchgeführt

### 4. Ergebnis prüfen

Das Script erstellt eine Log-Datei:
```
migrations/migration_vtool_YYYYMMDD_HHMMSS.log
```

## Ausgabe

```
===========================================
VTOOL DATENMIGRATION
===========================================
Modus: DRY RUN (Simulation)
Source: vtool
Target: sitzungsverwaltung

Migriere Proposals...
  ✓ A-2024-001: "Beispielantrag"
  ✓ B-2024-002: "Anderer Antrag"
  ...

STATISTIK:
  Proposals: 45/50
  Anhänge: 89
  Abstimmungen: 234
  Freigaben: 67
  Kommentare: 12

Log: migrations/migration_vtool_20260511_143022.log
===========================================
```

## Häufige Probleme

### "Database access denied"
- DB-Credentials in `config.php` prüfen
- Beide Datenbanken müssen existieren

### "Table doesn't exist"
- Target-DB: `php init-db.php` ausführen (erstellt svbproposals etc.)
- Source-DB: VTool muss korrekt installiert sein

### "Parse error"
- PHP Version mindestens 7.4 erforderlich
- Match-Expression benötigt PHP 8.0+

### "500 Internal Server Error"
- **Script darf NICHT im Browser aufgerufen werden!**
- Nur via CLI: `php migrations/migrate_vtool_data.php`

## Rollback

Bei Fehlern während `--execute`:
- Automatischer Rollback durch Transaktion
- Keine Daten werden geändert
- Fehler im Log überprüfen

Manueller Rollback (falls nötig):
```sql
DELETE FROM svbproposals;
DELETE FROM svbproposal_attachments;
DELETE FROM svbproposal_votes;
DELETE FROM svbproposal_approvers;
DELETE FROM svbproposal_comments;
```

## Wiederholte Migration

Das Script kann mehrfach ausgeführt werden:
- Prüft auf Duplikate via `proposal_number`
- Überspringt bereits migrierte Anträge
- Nur neue/geänderte Daten werden migriert
