# Datenbank-Migrationen

Dieses Verzeichnis enthält alle Datenbank-Migrationen für die Sitzungsverwaltung.

## Aktuelle Migrationen (Session: 2025-12-23)

### 1. Externe Teilnehmer für Meinungsbilder
**Datei**: `add_external_participants.sql`

**Was wird hinzugefügt:**
- Neue Tabelle: `svexternal_participants`
- Neue Spalte: `svopinion_responses.external_participant_id`
- Neue Spalte: `svpoll_responses.external_participant_id`
- Foreign Keys zwischen den Tabellen

**Zweck**: Ermöglicht externen Personen (z.B. Beirat, Partner) die Teilnahme an Meinungsbildern ohne Login via Token-URL.

---

### 2. Externe Links für Dokumente
**Datei**: `add_external_url_to_documents.sql`

**Was wird hinzugefügt:**
- Neue Spalte: `svdocuments.external_url` (VARCHAR 1000)
- Ändert: `filepath`, `filename`, `filesize` auf NULL erlaubt
- Neuer Index: `idx_external_url`

**Zweck**: Dokumente können als externe Links (z.B. SharePoint, Google Drive) verwaltet werden statt hochgeladen zu werden.

---

### 3. Target Type für Polls (optional)
**Datei**: `add_target_type_to_polls.sql`

**Was wird hinzugefügt:**
- Neue Spalte: `svpolls.target_type` (ENUM)
- Neue Spalte: `svpolls.access_token` (VARCHAR 64)
- Index und Daten-Updates

**Zweck**: Erweitert Terminplanung um verschiedene Zielgruppen-Typen.

**Hinweis**: Nur nötig, wenn die Terminplanung genutzt wird.

---

## Ausführung der Migrationen

### Option 1: Automatisches Skript (empfohlen)

**Linux/Mac:**
```bash
cd /pfad/zu/Sitzungsverwaltung/migrations
./run_migrations.sh
```

**Windows:**
```cmd
cd C:\pfad\zu\Sitzungsverwaltung\migrations
run_migrations.bat
```

Das Skript führt dich interaktiv durch alle Migrationen und fragt vor jeder Ausführung nach Bestätigung.

---

### Option 2: Manuelle Ausführung

**Von überall (absolute Pfade):**

```bash
# Migration 1: Externe Teilnehmer
mysql -u root -p Sitzungsverwaltung < /pfad/zu/Sitzungsverwaltung/migrations/add_external_participants.sql

# Migration 2: Externe Dokument-Links
mysql -u root -p Sitzungsverwaltung < /pfad/zu/Sitzungsverwaltung/migrations/add_external_url_to_documents.sql

# Migration 3: Target Type (optional)
mysql -u root -p Sitzungsverwaltung < /pfad/zu/Sitzungsverwaltung/migrations/add_target_type_to_polls.sql
```

**Aus dem Migrations-Verzeichnis (relative Pfade):**

```bash
cd /pfad/zu/Sitzungsverwaltung/migrations

mysql -u root -p Sitzungsverwaltung < add_external_participants.sql
mysql -u root -p Sitzungsverwaltung < add_external_url_to_documents.sql
mysql -u root -p Sitzungsverwaltung < add_target_type_to_polls.sql
```

---

### Option 3: Via PHPMyAdmin

1. Öffne PHPMyAdmin
2. Wähle die Datenbank "Sitzungsverwaltung"
3. Gehe zum Tab "SQL"
4. Kopiere den Inhalt der SQL-Datei in das Textfeld
5. Klicke auf "OK"

---

## Reihenfolge beachten!

Die Migrationen müssen in dieser Reihenfolge ausgeführt werden:

1. ✅ `add_external_participants.sql` (Zuerst!)
2. ✅ `add_external_url_to_documents.sql`
3. ⚠️ `add_target_type_to_polls.sql` (Optional)

**Grund**: Foreign Keys setzen voraus, dass die referenzierten Tabellen bereits existieren.

---

## Überprüfung nach der Migration

Nach erfolgreicher Ausführung kannst du prüfen:

```sql
-- Prüfe ob Tabelle existiert
SHOW TABLES LIKE 'svexternal_participants';

-- Prüfe neue Spalte in svdocuments
DESCRIBE svdocuments;

-- Prüfe neue Spalte in svopinion_responses
DESCRIBE svopinion_responses;
```

---

## Rollback (Notfall)

Falls etwas schiefgeht, können die Änderungen rückgängig gemacht werden:

### Rollback: Externe Teilnehmer
```sql
-- Foreign Keys entfernen
ALTER TABLE svopinion_responses DROP FOREIGN KEY fk_opinion_external;
ALTER TABLE svpoll_responses DROP FOREIGN KEY fk_poll_external;

-- Spalten entfernen
ALTER TABLE svopinion_responses DROP COLUMN external_participant_id;
ALTER TABLE svpoll_responses DROP COLUMN external_participant_id;

-- Tabelle löschen
DROP TABLE svexternal_participants;
```

### Rollback: Externe Dokument-Links
```sql
-- Index entfernen
DROP INDEX idx_external_url ON svdocuments;

-- Spalte entfernen
ALTER TABLE svdocuments DROP COLUMN external_url;

-- Spalten wieder auf NOT NULL setzen (nur wenn nötig)
ALTER TABLE svdocuments
MODIFY COLUMN filepath VARCHAR(500) NOT NULL,
MODIFY COLUMN filename VARCHAR(255) NOT NULL,
MODIFY COLUMN filesize INT NOT NULL;
```

---

## Fehlerbehebung

### "Table already exists"
Die Migration wurde bereits ausgeführt. Du kannst sie überspringen.

### "Unknown column"
Eine abhängige Migration fehlt. Führe die Migrationen in der richtigen Reihenfolge aus.

### "Access denied"
Prüfe die MySQL-Zugangsdaten. Der User benötigt CREATE, ALTER und INDEX Berechtigungen.

### "Can't connect to MySQL"
- Ist MySQL gestartet?
- Stimmen Host, Port und Zugangsdaten?
- Firewall-Einstellungen prüfen

---

## Nach der Migration

1. ✅ **Funktionstest**: Öffne die Anwendung und teste die neuen Features
2. ✅ **Backup**: Erstelle ein Backup der aktualisierten Datenbank
3. ⚠️ **Cron-Job** (optional): Richte Cleanup für externe Teilnehmer ein

```cron
# Täglich um 3 Uhr morgens
0 3 * * * /usr/bin/php /pfad/zu/Sitzungsverwaltung/cron_cleanup_external_participants.php
```

---

## Support

Bei Problemen:
- Siehe `SESSION_HANDOVER.md` für Details
- Siehe `CHANGELOG.md` für Feature-Beschreibungen
- Siehe `EXTERNE_TEILNEHMER_README.md` für Anleitung

---

**Stand**: 2025-12-23
**Session**: claude/fix-sso-integration-01NbwbYdHVMH7hEM5HwQmFji
