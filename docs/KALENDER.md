# Kalender-Modul

Der Tab **Kalender** (`tab_kalender.php`, Backend `process_kalender.php`) zeigt alle für das eingeloggte Mitglied relevanten Termine der nächsten 90 Tage, gruppiert nach Monat. Der Tab ist für alle eingeloggten Mitglieder sichtbar.

## Voraussetzung

Die Tabellen `svevents` und `svevents_participants` werden von `tools/init-db.php` angelegt. Fehlen sie, zeigt der Tab einen Hinweis mit Link auf `tools/init-db.php`.

## Was im Kalender erscheint

| Quelle | Beschreibung |
|---|---|
| `svevents` | Manuell angelegte Termine (Typen: Sitzung, Treffen, Abwesenheit, Frist, Sonstiges) |
| `svmeetings` | Sitzungen werden automatisch eingeblendet (Typ "Sitzung"), archivierte Sitzungen nicht |
| Offene Abstimmungen | Für jedes Mitglied, das bei einem B-Antrag noch nicht abgestimmt hat, ein Eintrag "Abstimmungsfrist: <Antrag>" (Typ "Frist") am letzten Abstimmungstag mit Link zu `abstimmungen.php`. Wird dynamisch berechnet (`get_open_votings()` aus `voting_reminder.php`), nicht gespeichert |

## Wiederkehrende Termine

`recurrence`: `none`, `weekly`, `biweekly`, `monthly`, `yearly`, optional mit Enddatum (`recurrence_end_date`). Es wird nur der Originaltermin gespeichert; die Vorkommnisse im Anzeigezeitraum werden beim Laden dynamisch erzeugt.

## Sichtbarkeit (`target_type`)

| Wert | Wer sieht den Termin |
|---|---|
| `all` | alle Mitglieder |
| `roles` | nur Mitglieder der gewählten Rollen (`vorstand`, `gf`, `assistenz`); kann nur von Admins gewählt werden |
| `list` | nur die per Häkchen ausgewählten Teilnehmer (`svevents_participants`); die Auswahl ist nachträglich änderbar |

Der Ersteller ist **nicht** automatisch Teilnehmer, sieht seine eigenen Termine aber immer.

## Berechtigungen

- Anlegen: alle Rollen außer `mitglied`
- Bearbeiten und Löschen: Ersteller oder Admin
- Löschen ist ein Soft-Delete (`status = 'deleted'`); `status` kennt außerdem `active` und `cancelled`

## Tabellen

- `svevents`: `event_id`, `title`, `description`, `location`, `start_datetime`, `end_datetime`, `all_day`, `event_type`, `recurrence`, `recurrence_end_date`, `target_type`, `target_roles`, `created_by_member_id`, `meeting_id`, `status`, `created_at`, `updated_at`
- `svevents_participants`: `event_id`, `member_id` (nur bei `target_type = 'list'`)
