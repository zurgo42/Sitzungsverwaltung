# Migrations-Verzeichnis - Übersicht und Status

**Stand:** 2026-05-04

## Zweck dieses Verzeichnisses

Migrations werden benötigt, um **bestehende Installationen** zu aktualisieren.

- ✅ **Neue Installationen** verwenden `init-db.php` (enthält bereits alle aktuellen Tabellen)
- ✅ **Bestehende Installationen** müssen Migrations ausführen, um neue Features zu erhalten

---

## Aktuelle Migrations (WICHTIG für Updates)

Diese Migrations sollten auf produktiven Servern ausgeführt werden, falls noch nicht geschehen:

### 1. Externe Teilnehmer (2025-12-18)
**Datei:** `add_external_participants.sql`

**Was wird hinzugefügt:**
- Tabelle `svexternal_participants`
- Spalte `external_participant_id` in `svpoll_responses`
- Spalte `external_participant_id` in `svopinion_responses`
- Foreign Keys und Unique Constraints

**Benötigt für:** Externe Umfrage-Teilnahme ohne Account

---

### 2. Dateianhänge für TOPs (2026-04-27)
**Datei:** `add_agenda_attachments.sql`

**Was wird hinzugefügt:**
- Tabelle `svagenda_attachments`

**Benötigt für:** Datei-Uploads zu Tagesordnungspunkten

---

### 3. Benachrichtigungs-Center (2026-04-27)
**Datei:** `add_notification_center.sql`

**Was wird hinzugefügt:**
- Tabelle `svnotifications`
- Tabelle `svpush_subscriptions`

**Benötigt für:** Benachrichtigungssystem und Browser-Push

---

### 4. Externes Zugriffs-Log (2026-05-03)
**Datei:** `add_external_access_log_table.sql`

**Was wird hinzugefügt:**
- Tabelle `svexternal_access_log`

**Benötigt für:** Protokollierung externer Zugriffe auf Umfragen

---

## Alte Migrations (Optional/Historisch)

Diese Migrations sind vermutlich bereits auf allen Servern ausgeführt oder durch neuere ersetzt:

### Umbenennung mit sv-Prefix
**Dateien:**
- `rename_tables_with_sv_prefix.sql`
- `rename_referenten_tables_with_sv_prefix.sql`
- `update_table_names_in_php.php`

**Status:** ⚠️ VERALTET - Diese Umbenennung ist längst abgeschlossen

---

### Terminplanung & Meinungsbilder
**Dateien:**
- `create_polls.sql`
- `create_opinion_polls.sql`
- `insert_opinion_templates.sql`
- `add_target_type_to_polls.sql`
- `add_location_to_polls.php`
- `add_poll_reminders.sql`
- `update_opinion_access_token_for_all_types.sql`
- `update_polls_access_token_for_all_types.sql`

**Status:** 
- ✅ Bereits in `init-db.php` integriert
- ⚠️ Können gelöscht werden, wenn alle Server aktualisiert sind

---

### Dokumentenverwaltung
**Datei:** `add_external_url_to_documents.sql`

**Status:** ✅ Bereits in `init-db.php` integriert

---

## Aufräum-Empfehlungen

### Können gelöscht werden (wenn alle Server auf neuem Stand):
```bash
# Alte Umbenennung
rm rename_tables_with_sv_prefix.sql
rm rename_referenten_tables_with_sv_prefix.sql
rm update_table_names_in_php.php

# Alte Poll/Opinion-Migrations (bereits in init-db.php)
rm create_polls.sql
rm create_opinion_polls.sql
rm insert_opinion_templates.sql
rm add_target_type_to_polls.sql
rm add_location_to_polls.php
rm add_poll_reminders.sql
rm update_opinion_access_token_for_all_types.sql
rm update_polls_access_token_for_all_types.sql

# Alte Dokument-Migration (bereits in init-db.php)
rm add_external_url_to_documents.sql
```

### MÜSSEN BEHALTEN werden:
```bash
# Aktuelle Features (2025-2026)
add_external_participants.sql
add_agenda_attachments.sql
add_notification_center.sql
add_external_access_log_table.sql
```

---

## SQL-Verzeichnis

**Verzeichnis:** `/sql/`

### Aktuell enthalten:
- `create_members_view.sql` - View für Mitgliederverwaltung

**Status:** ✅ Kann behalten werden

**Empfehlung:** Hier könnten künftig weitere Views oder Stored Procedures abgelegt werden

---

## Ablauf für Server-Updates

### Neue Installation:
```bash
# 1. init-db.php ausführen
php init-db.php

# 2. Fertig! (Alle Tabellen sind bereits vorhanden)
```

### Bestehende Installation aktualisieren:
```bash
# 1. Backup erstellen!
mysqldump -u root -p sitzungsverwaltung > backup_$(date +%Y%m%d).sql

# 2. Fehlende Migrations ausführen
mysql -u root -p sitzungsverwaltung < migrations/add_external_participants.sql
mysql -u root -p sitzungsverwaltung < migrations/add_agenda_attachments.sql
mysql -u root -p sitzungsverwaltung < migrations/add_notification_center.sql
mysql -u root -p sitzungsverwaltung < migrations/add_external_access_log_table.sql

# 3. Code aktualisieren
git pull

# 4. Testen!
```

---

## Automatisiertes Migrations-Skript

**Dateien:**
- `run_migrations.sh` (Linux/Mac)
- `run_migrations.bat` (Windows)

**Status:** ✅ Kann verwendet werden für automatische Migration

**Hinweis:** Prüfe ob das Skript die aktuellen Migrations enthält!

---

## Checkliste: Was muss ich tun?

### Als Entwickler (nach neuem Feature):
- [ ] Migration erstellen in `/migrations/`
- [ ] Tabelle/Spalte auch in `init-db.php` hinzufügen
- [ ] Tabelle in `tools/demo_import.php` → `$table_order` eintragen
- [ ] Diese README-Datei aktualisieren
- [ ] Migration auf Produktivserver testen

### Als Admin (bei Server-Update):
- [ ] Backup erstellen
- [ ] Neue Migrations ausführen (siehe Liste oben)
- [ ] `git pull` ausführen
- [ ] `config.php` und `config_adapter.php` prüfen (siehe CONFIG-README.md)
- [ ] System testen

---

**Erstellt:** 2026-05-04  
**Grund:** Übersicht über Migration-Status und Aufräumarbeiten
