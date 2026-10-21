# Changelog - Sitzungsverwaltung

Alle wichtigen Änderungen am Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [Unreleased]

### Added (Neu)

#### Dokumentensammlung (tab_dokumente_allgemein.php): Dateiname ändern (2026-10-29)

- Im Bearbeiten-Bereich der Dokumentensammlung (Tabelle `dokumente`) gibt es das Feld „Dateiname“. Beim Speichern wird die Datei im Upload-Verzeichnis umbenannt und `name` aktualisiert; der Name erscheint in der Liste unter dem Titel („📎 Name“). Endung und die Präfixe `xxx` (verborgen) / `vvv` (überholt) bleiben erhalten; Namen werden bereinigt (Umlaute umschrieben, nur A-Z a-z 0-9 . _ -, max. 100 Zeichen); bei vorhandenem Namen oder fehlender Datei wird nichts geändert. Links sind ausgenommen. Weitergegebene Links auf den alten Dateinamen funktionieren danach nicht mehr (Kurz-URL nutzen).
- Die Datei wird auch gefunden, wenn der gespeicherte Name von der Datei auf dem Server abweicht (URL- oder HTML-kodierte Umlaute/Leerzeichen, ISO-8859-1 statt UTF-8, zerlegte Umlaute); beim Umbenennen wird der Name in der Datenbank dann bereinigt. Relative Verzeichnisse (z. B. `../docs/`) stammen oft aus dem älteren System und beziehen sich dort auf ein anderes Arbeitsverzeichnis; deshalb werden mehrere Basisverzeichnisse probiert (aktuelles Verzeichnis, Verzeichnis des Skripts, dessen Eltern, DOCUMENT_ROOT). Bei Nichtfinden nennt die Meldung die durchsuchten Verzeichnisse.
- Die Dateisuche liegt jetzt in `file_helpers.php` (`sv_find_file()`, `sv_locate_file()`, `sv_clean_filename()`) und wird auch von „Verbergen", „Als überholt" und „Löschen" genutzt. Findet eine Aktion die Datei nicht, meldet sie das und nennt die durchsuchten Verzeichnisse (vorher wurde nur die Datenbank geändert, die Datei blieb unter dem alten Namen abrufbar). Zusätzlich erkannt: doppelt kodierte Umlaute (UTF-8 als Latin-1/cp1252 gespeichert). Beim Verbergen/Überholt-Markieren wird der Datenbankname dem tatsächlichen Dateinamen angeglichen.

#### Dokumente: Dateiname ändern (2026-10-28)

- Admins können beim Bearbeiten eines Dokuments den Dateinamen ändern (`rename_document_file()`, Feld „Dateiname“ in der Bearbeitungsansicht). Die Datei wird auf dem Server umbenannt; `filename`, `filepath` und `original_filename` erhalten den neuen Namen, der auch in der Liste („📎 Name“ unter dem Titel), in der Detailansicht und als Name beim Herunterladen erscheint. Endung bleibt unverändert; Umlaute werden umschrieben, Leerzeichen zu „_“, erlaubt sind A-Z a-z 0-9 . _ - (max. 100 Zeichen). Existiert der Name schon, wird abgelehnt; bei Datenbankfehlern wird die Datei zurückbenannt. Externe Links sind ausgenommen.

#### Fix: Link zur individuellen Terminabfrage / zum Meinungsbild (2026-10-27)

- `generate_external_access_link()`: Ohne konfigurierte öffentliche URL (`svconfig.terminplanung_standalone_url` bzw. `opinion_standalone_url`) wurde der Link auf das **Wurzelverzeichnis der Domain** geraten (`https://domain/terminplanung_standalone.php`), das bei Installation in einem Unterverzeichnis auf eine falsche Seite führt. Jetzt gilt: Liegt im Wurzelverzeichnis der Domain eine gleichnamige Datei (öffentlicher Wrapper), zeigt der Link dorthin (wie zuvor); sonst auf das Verzeichnis der Sitzungsverwaltung (funktioniert für eingeloggte Mitglieder). Ein erster Zwischenstand zeigte immer auf den geschützten Bereich, wodurch Besucher als Mitglieder erkannt wurden und kein Registrierungsformular (Name, E-Mail) erhielten.
- `terminplanung_standalone.php` im öffentlichen Wrapper-Modus (`$TERMINPLANUNG_PUBLIC_MODE`): Die eigene URL (Formular-Ziele, Weitergabe-Links) stammt jetzt aus der aufgerufenen Wrapper-URL statt aus dem echten Dateipfad im geschützten Bereich; der Wrapper muss `$TERMINPLANUNG_PUBLIC_URL` nicht mehr setzen. Zuvor führten Links auf der öffentlichen Seite in den SSO-geschützten Bereich.
- `opinion_standalone.php`: im öffentlichen Wrapper-Modus (`$OPINION_PUBLIC_MODE`) wird `$OPINION_PUBLIC_URL` ebenfalls aus der aufgerufenen Wrapper-URL abgeleitet, falls der Wrapper sie nicht setzt.
- **Mitglieder auf der öffentlichen Seite:** Wer über den öffentlichen Wrapper (ohne SSO) kommt, wurde als Externer behandelt und sah das Registrierungsformular. Die Registrierungsseite bietet jetzt „Du bist Mitglied? Melde dich hier an“ mit Link auf dieselbe Seite im geschützten Bereich (SSO erkennt das Mitglied). In der Terminabfrage steht bei individuellen Umfragen zusätzlich ein Link „Für Mitglieder“.
- **Token-Link zeigte die falsche Seite:** `terminplanung_standalone.php?token=…` löste die Umfrage zwar auf, setzte aber weder `poll_id` noch `view`; Dashboard und `tab_termine.php` lesen beides aus `$_GET` und zeigten deshalb die Umfrage-Übersicht statt der gemeinten Umfrage. Der Token setzt jetzt `poll_id` und `view=poll`. Entsprechend setzt `opinion_standalone.php` `poll_id` für `tab_opinion.php`.
- Terminabfrage: Hinweis unter dem Link, wenn keine öffentliche URL hinterlegt ist (Externe brauchen die öffentliche URL des Wrappers, siehe `terminplanung_public.EXAMPLE.php`).

#### welcome.php: Funktionsliste nach Menü-Reihenfolge und Modulschaltern (2026-10-26)

- Die Funktionsliste der Demo-Startseite folgt der Reihenfolge der Tabs; eingeschaltete Bereiche stehen zuerst, per Modulschalter ausgeschaltete danach ausgegraut mit Fußnote. Neu in der Liste: MV-Beschlüsse und Hinweiskasten.

#### Drei Demo-Versionen; Antrags-TOPs ohne Modul „Anträge" (2026-10-25)

- **Demo-Versionen:** „Vollausstattung" und „Einfachausstattung" sind vorgegeben (aus `demo_data.json`, von `demo_build.php`); „aktuell" ist der zuletzt selbst gespeicherte Stand (`demo_data_aktuell.json`, geschrieben von `demo_export.php`, nicht im Repository). `demo_import.php?profil=voll|einfach|aktuell` lädt die gewählte Version und überschreibt den aktuellen Stand; im Admin-Bereich (System & Demo-Funktionen) stehen dafür drei Knöpfe. Beim Laden von „aktuell" gelten die mitgespeicherten Modulschalter.
- **TOPs ohne Modul „Anträge/Beschlüsse":** Die Kategorie „Antrag/Beschluss" wird bei neuen TOPs nicht mehr angeboten (bestehende bleiben als „Beschluss" erhalten), die Antrags-Verknüpfung und der Hinweis „Eintrag ins Beschlussbuch" werden ausgeblendet, es werden keine Anträge angelegt und keine Beschlussbuch-Einträge geschrieben. Der TOP wird wie jeder andere behandelt; Ergebnis steht im Protokoll.

#### Modulschalter und zwei Demo-Ausstattungen (2026-10-24)

- **Modulschalter** (`module_config.php`, Admin → Datenbank-Initialisierung → „Module"): Anträge/Beschlüsse, Meinungsbild, Kalender, MV-Beschlüsse, Textbearbeitung, Abwesenheiten und der Hinweiskasten am Seitenanfang sind einzeln ein- und ausschaltbar; dazu die Profile „Einfachausstattung" und „Vollausstattung". Fest enthalten bleiben Sitzungen/Tagesordnung, Protokolle, Termin finden, ToDo, Dokumente, Nachrichten und Admin.
- Ausgeschaltete Bereiche verschwinden aus dem Menü, ein direkter Aufruf (`?tab=…`, `abstimmungen.php`, `beschlussbuch.php`, `antrag_*.php`, `opinion_standalone.php`, `process_kalender.php`) wird abgewiesen. Daten bleiben erhalten. Abstimmungs- und Abwesenheitshinweise im Hinweiskasten entfallen mit ihren Bereichen.
- Gespeichert in `svconfig` (`module_<name>`, Kategorie `modules`). Fehlt ein Schlüssel, ist der Bereich eingeschaltet: bestehende Installationen (auch die Adapter-Version) ändern sich nicht.
- **Demo:** `tools/demo_import.php` fragt nach der Ausstattung (Voll oder Einfach, auch vorwählbar per `?profil=einfach`) und setzt die Modulschalter entsprechend.

#### Beschlussart im Editiermodus änderbar (2026-10-23)

- „Antrag bearbeiten": Im Editiermodus (A) ist die Beschlussart jetzt wählbar (Verfügung / Ressortbeschluss / Vorstandsbeschluss, soweit aktiviert). Vorher war das Feld schreibgeschützt, obwohl die Fehlermeldung bei Überschreiten des Betragslimits „Bitte wähle einen anderen Typ" empfahl. Die Betragsprüfung gilt für die neu gewählte Art; nach dem Speichern erscheinen die passenden Felder (z. B. 2. Abstimmende bei Ressortbeschluss).
- Die Prüfung der monatlichen Verfügungsgrenze gilt nur noch für Verfügungen; ein Wechsel zum Ressortbeschluss („sonst Ressortbeschluss") ist damit nicht mehr blockiert.

#### Fix: Antragsliste zeigt Bedenkzeit als „noch offen" (2026-10-22)

- `tab_proposals.php`: Wer Bedenkzeit gewählt hat (Votum 5), gilt in „noch offen: …" als offen (mit „Bedenkzeit bis TT.MM."), nicht mehr als abgestimmt. Zuvor stand „alle abgestimmt", obwohl die Auswertung (`auswerten_abstimmung()`) auf die Bedenkzeit wartet.

#### Fix: Abschluss-Hinweis bei Verfügungen (2026-10-21)

- `antrag_ansehen.php`: Beschlossene Anträge (VS-Nummer) zeigen oben „✓ Abgeschlossen" (bei Verfügungen: „Diese Verfügung ist beschlossen (ohne Abstimmung) und im Beschlussbuch eingetragen"). Das Beschlussdatum („Verfügt am"/„Beschlossen am") kommt bei fehlenden Stimmen aus der VS-Nummer; vorher fehlte es bei Verfügungen ganz, weil es nur aus dem letzten Votum bestimmt wurde.
- `tab_proposals.php`: Erkennung der Nummern `VS…` im Status-Hinweis korrigiert.

#### Schutz vor zu früher Auswertung von Abstimmungen (2026-10-20)

- `auswerten_abstimmung()` führt eine erzwungene Auswertung (Fristablauf) nur noch aus, wenn `b_date` gesetzt und die Abstimmungszeit tatsächlich abgelaufen ist (`b_date < heute - Abstimmungsdauer`). Jeder Aufrufer ist damit abgesichert; andernfalls wird nichts geändert und ein Hinweis ins Fehlerprotokoll geschrieben.
- `b_date` wird beim Einstellen aus dem PHP-Datum gesetzt (statt `CURDATE()` der Datenbank), damit Einstellung und Fristvergleich dieselbe Zeitzone nutzen.

#### Fix: Verfügung ohne Abstimmung, Ressortbeschluss mit den angegebenen Personen (2026-10-19)

- **Verfügung (Typ V):** „Verbindlich einstellen" (auch die automatische Einstellung) beschließt die Verfügung sofort: Der Antrag erhält eine VS-Nummer und wird ins Beschlussbuch eingetragen („Verfügung von <Name>"), es gibt keine Abstimmung mehr. Vorher wurden bis zu sechs Mitglieder mit aktiv ≥ 14 als Abstimmende eingetragen. Mail „Verfügung beschlossen"; Weiterleitung auf die Ansicht des Beschlusses.
- **Ressortbeschluss (Typ R):** Die Abstimmung wird für die beiden im Antrag angegebenen Personen (`verf1`, `verf2`) angelegt. Vorher wurden Antragsteller und FVo/FVv eingetragen, ohne Rücksicht auf die Auswahl im Antrag.
- Oberfläche: Beschriftung „Verfügung durch", angepasste Hinweise und Sicherheitsabfrage bei Verfügungen.
- Bereits laufende Abstimmungen zu Verfügungen werden unverändert wie bisher ausgewertet.
- Demo: Verfügungen sind ohne Abstimmung beschlossen (Beschlussbuch „Verfügung von …"), das frühere V-Beispiel in Abstimmung ist ein Vorstandsbeschluss.

#### Automatisch zur Abstimmung stellen nach Ablauf der Wartezeit (2026-10-18)

- **Option am Antrag:** In „Antrag bearbeiten" (Abschnitt „Nächste Schritte") gibt es für Anträge im Editiermodus (A) den Haken „Nach Ablauf der Wartezeit automatisch zur Abstimmung stellen". Die Wartezeit ist die je Antragstyp konfigurierte (Admin: `bart_*_wartezeit_tage`); eine Wartezeitverkürzung (zwei Zustimmungen) zählt ebenfalls.
- **Nur für Typen mit konfigurierter Wartezeit:** Der Haken erscheint nur, wenn für den Antragstyp eine Wartezeit eingestellt ist; der Cron ignoriert Anträge von Typen ohne Wartezeit.
- **Cron:** `auto_abstimmung_pruefen()` (neu: `antrag_auto_abstimmung.php`, aufgerufen im Pseudo-Cron und in `cron_notifications.php`) stellt solche Anträge ein, sobald Wartezeit und Pflichtangaben (Ressort, Verantwortliche/r, bei V/R die Verfügungsberechtigten) vorliegen; sonst bleibt der Antrag unverändert. Es gelten dieselben Regeln, Abstimmende und Mails wie bei „Verbindlich einstellen" (gemeinsame Funktion `antrag_zur_abstimmung_stellen()`); der Vorgang wird protokolliert (`Antrag-Auto-Finalisieren`). Schlägt eine Einstellung fehl, wird der Haken entfernt und der Fehler geloggt.
- **Demo:** Beispiel-Antrag (laufende Nummer `77`) mit gesetztem Haken. Der Kreislauf (A wartet, wird vom Cron zu B, nach 2 Tagen zurück auf A mit neuer Nummer) wird von `update_demo_dates.php` aufrechterhalten und ist nur aktiv, wenn für Typ B eine Wartezeit konfiguriert ist.
- **Datenbank:** neue Spalte `auto_abstimmung TINYINT(1) NOT NULL DEFAULT 0` in der Anträge-Tabelle. Neuinstallation/`init-db.php` legt sie an; für laufende Installationen `tools/migrate_auto_abstimmung.php` (`?dry=1` = Trockenlauf). Ohne die Spalte ist das Feature inaktiv (kein Haken sichtbar, Cron ohne Wirkung).

#### Sicherheit für laufende Installationen (2026-10-17)

- **Fix `audit_pdo.php`:** `lastInsertId()` lieferte nach jedem Schreibzugriff die ID des Audit-Eintrags statt des eigentlichen Datensatzes; die ID wird jetzt vor dem Log-Eintrag gesichert.
- `audit_loader.php`: lädt das Audit-Logging nur ab PHP 8.0 und fällt sonst auf normales PDO zurück (`sv_new_pdo()`); fehlt `svaudit_log`, schaltet sich das Logging still ab.
- `tools/migrate_neue_tabellen.php`: legt ausschließlich die neuen Tabellen an (`svaudit_log`, `svevents`, `svevents_participants`, `svvote_reminders`); `?dry=1` zeigt nur an. Für laufende Installationen statt `init-db.php`.
- Audit-Log: auch `antrag_neu.php`, `ajax_feedback.php`, `mark_notification_read.php`, `mark_all_notifications_read.php` und `meine_benachrichtigungen.php` nutzen jetzt `sv_new_pdo()` (Anlegen von Anträgen, Feedback, Benachrichtigungs-Einstellungen werden protokolliert).
- Audit-Log: Einträge älter als 180 Tage werden vom Pseudo-Cron gelöscht.
- Abstimmungs-Erinnerung: kein Versand und keine Markierung bei `MAIL_ENABLED=false` oder ohne absolute `meeting_system_url`; fehlgeschlagene Mails werden nicht im Minutentakt wiederholt; Datenbankfehler führen nie zu Seitenfehlern.
- Demo-Werkzeuge (`demo_import.php`, `update_demo_dates.php`) sind bei `MEMBER_SOURCE='berechtigte'` gesperrt und verändern nie echte Daten.
- Hinweise im Kalender und Admin-Tab verweisen auf `tools/migrate_neue_tabellen.php`.

#### Fix: init-db.php vollständig, Beschlussbuch zwei Jahre (2026-10-16)

- `tools/init-db.php`: Fehlende Spalten werden nachgezogen (`svpolls.target_type/access_token` inkl. Token-Trigger, `svdocuments.external_url`, `svagenda_items.referent_member_id`, `external_participant_id` in `svpoll_responses`/`svopinion_responses` mit NULL-fähiger `member_id`). Idempotent.
- `tools/init-db.php`: System-Konfiguration (Aktiv-Level, `bart_*`, Abstimmungsregeln u. a.) wird jetzt pro Schlüssel ergänzt statt nur bei leerer Tabelle; vorhandene Werte bleiben unverändert.
- `beschlussbuch.php`: Standard-Zeitraum umfasst das Vorjahr und das laufende Jahr.

#### Demo-Daten-Generator, Datenschutz und tägliche Datumsfortschreibung (2026-10-16)

- `tools/demo_build.php`: Neuer deterministischer Generator (CLI und Browser), der `tools/demo_data.json` aus frei erfundenen Daten erzeugt – Mitglieder aller Rollen (Passwort `test123`, nur `@example.com`), Sitzungen in allen Status, TOPs, Protokolle, ToDos, Abwesenheiten mit Vertretung, Terminumfragen, Meinungsbilder, Dokumente, Texte, Anträge in allen Phasen (A/B/VS/X/Z), Kalender, Benachrichtigungen und alle Admin-Einstellungen (`svconfig`, Ressorts); `demo.php` ruft den Builder auf (ersetzt den alten Mini-Generator)
- `tools/demo_common.php`: Gemeinsame Liste (`DEMO_EXCLUDED_TABLES`) der Tabellen, die nie exportiert/importiert und beim Import geleert werden: `svmail_queue`, `svmail_notifications`, `svpush_subscriptions`, `svexternal_access_log`, `svaudit_log`, `svadmin_log`, `svdocument_downloads`, `svvote_reminders`, `svcollab_text_locks`, `protokoll`
- `tools/demo_export.php`: nutzt die gemeinsame Ausschlussliste, bereinigt Token-Spalten, exportiert `feedback`, schreibt `demo_anchor_date` und warnt bei Mailadressen außerhalb von example.com/.org/.net
- `tools/demo_import.php`: ignoriert Datenschutz-Tabellen, verwirft unbekannte Spalten (Schema-Abweichungen), bildet `TABLE_*` ab und leitet `berechtigte` aus `svmembers` ab (nur Standalone), damit Anträge, Terminplanung und Meinungsbild im Demo funktionieren
- `tools/demo_analyze.php`: Feature-Matrix, Datenschutz-Prüfung, Erinnerungs-Antrag und Rollen-Zuordnung (auch per CLI: `php tools/demo_analyze.php`)
- `tools/update_demo_dates.php`: Tagesaktualisierung über `svconfig.demo_anchor_date` (einmal pro Tag, atomar) – verschiebt Sitzungen, ToDos (Fälligkeit), Abwesenheiten, Benachrichtigungen u. a. relativ zu heute; der Erinnerungs-Antrag `B26010101` steht immer auf `b_date = heute − Abstimmungsdauer` (24h-Erinnerung, mindestens eine Stimme fehlt, `svvote_reminders` wird geleert), alle anderen B-Anträge behalten jüngere `b_date`-Werte
- Rollen-Mapping `svmembers` ↔ `berechtigte` (aktiv/Funktion) in `docs/PERMISSIONS.md` dokumentiert
- `index.php`: Datumsaktualisierung im Demo-Modus läuft jetzt vor dem Pseudo-Cron; `welcome.php`/`login.php`: Demo-Benutzerliste und Login-Hinweis an den neuen Demo-Stand angepasst
- `tab_vertretung.php`: Undefinierte Variable `$available_members` im Bearbeiten-Dialog korrigiert (PHP-Warnung)

#### Generelles Audit-Log aller DB-Schreibzugriffe (2026-10-15)

- `audit_pdo.php`: `AuditPDO` (erweitert `PDO`) und `AuditStatement` (über `PDO::ATTR_STATEMENT_CLASS`) protokollieren transparent jedes `INSERT`, `UPDATE`, `DELETE` und `REPLACE` (auch direkte `exec()`-Aufrufe) in der Tabelle `svaudit_log`
  - Gespeichert werden Zeitpunkt, Mitglied (`$_SESSION['member_id']`, `NULL` bei Cron/extern), IP, aufrufendes Skript, Aktion, Tabelle, betroffene Zeilen und die SQL-Abfrage mit eingesetzten Parameterwerten (max. 2000 Zeichen, lange Werte werden auf 300 Zeichen gekürzt)
  - Fehler beim Schreiben des Audit-Logs unterbrechen nie die eigentliche Aktion
- `config.example.php`: Lädt `audit_pdo.php` (falls vorhanden) und erzeugt `$pdo` als `AuditPDO`, sonst als normales `PDO`
- `tools/init-db.php`: Legt die Tabelle `svaudit_log` an
- `tab_admin.php`: Neuer Abschnitt "Datenbank-Protokoll (Audit-Log)" im Admin-Tab mit Filtern und SQL-Anzeige per Modal-Dialog; Hinweis, falls die Tabelle noch fehlt
- `docs/audit-logging.md`: Neuer Abschnitt zum Datenbank-Audit-Log (`svaudit_log`), klare Abgrenzung zum Aktions-Protokoll (`protokoll`)

#### Kalender-Modul (2026-10-15)

- Neuer Tab "Kalender" (`tab_kalender.php`, `process_kalender.php`), für alle eingeloggten Mitglieder sichtbar
- Terminliste für die nächsten 90 Tage, gruppiert nach Monat; Sitzungen aus `svmeetings` erscheinen automatisch
- Wiederkehrende Termine (wöchentlich, 14-tägig, monatlich, jährlich) werden dynamisch expandiert
- Ereignistypen: Sitzung, Treffen, Abwesenheit, Frist, Sonstiges
- Sichtbarkeit (`target_type`): alle, Rollen (nur Admins; vorstand/gf/assistenz) oder Auswahlliste (Häkchen, Teilnehmer nachträglich änderbar). Der Ersteller ist nicht automatisch Teilnehmer, sieht seine eigenen Termine aber immer
- Rolle `mitglied` darf nur lesen; alle anderen Rollen dürfen Termine anlegen. Bearbeiten/Löschen nur Ersteller oder Admin (Soft-Delete: `status = 'deleted'`)
- Offene Abstimmungen erscheinen für jedes Mitglied, das noch nicht abgestimmt hat, dynamisch als "Frist" (Link zu `abstimmungen.php`); diese Einträge werden nicht gespeichert
- Neue Tabellen `svevents` und `svevents_participants` in `tools/init-db.php`; fehlen sie, zeigt der Tab einen Hinweis auf `tools/init-db.php`
- Siehe `docs/KALENDER.md`

#### Abstimmungs-Erinnerung (2026-10-15)

- `voting_reminder.php`: `send_voting_deadline_reminders()` verschickt ca. 24 Stunden vor Ende der Abstimmung eine Erinnerungs-E-Mail an alle Abstimmberechtigten, die noch nicht abgestimmt haben (Antrag, Link zu `abstimmungen.php`, Hinweis auf "kein Votum" im Beschlussbuch)
  - Nicht abschaltbar; je Antrag und Mitglied nur einmal (Tabelle `svvote_reminders`, wird automatisch angelegt)
  - Aufruf aus `pseudo_cron.php` und `cron_notifications.php`
- `get_open_votings()` liefert offene Abstimmungen mit Frist und fehlenden Stimmen (auch für die Kalender-Fristen)

#### Benachrichtigungskachel: Begrüßung und VTool-Freigaben (2026-10-15)

- `module_notifications.php`: Kachel (sobald Meldungen vorliegen) beginnt mit "Hallo <Vorname>!"
- Abschnitt "offene Zahlungsfreigaben im VTool" (Anzahl der Datensätze, in denen das Mitglied in f1-f6 steht und das zugehörige vf1-vf6 noch nicht F/X/N ist) mit Link zu `https://aktive.mensa.de/vorstand/vtool.php?steuer=9`; wird nur angezeigt, wenn die Tabelle `freigabe` existiert

### Changed (Geändert)

#### Abstimmungsdauer und Auswertung (2026-10-15)

- Die Abstimmung läuft die vollen N Tage (`svconfig`-Schlüssel `bart_B_abstimmung_tage`, Standard 7): Letzter Abstimmungstag ist `b_date` + N Tage (bis 24:00 Uhr). Die automatische Auswertung (`auswerten_abstimmung()` mit `force`) erfolgt erst am Tag danach (Bedingung `b_date < heute - N`) in `pseudo_cron.php` bzw. `cron_notifications.php`
- Berichtigung zum Eintrag vom 2026-09-16: Anträge ohne `b_date` werden nicht mehr anhand des Datums in `antrnr` ausgewertet, sondern von der automatischen Auswertung übersprungen
- Abstimmungs-Mail (`nm_event_antrag_abstimmung`) nennt jetzt den letzten Abstimmungstag

#### Mail "Neuer Antrag" und "Antrag geändert" (2026-10-15)

- "Neuer Antrag" wird nicht mehr beim Anlegen (`antrag_neu.php`) verschickt, sondern beim ersten Speichern mit Titel in `antrag_bearbeiten.php`; jedes weitere Speichern löst "Antrag geändert" aus
- "Antrag geändert"-Mail führt Abstimmberechtigte eines B-Antrags direkt zu `abstimmungen.php`

#### Antragsverwaltung (2026-10-15)

- `tab_proposals.php`: "In Abstimmung seit" zeigt `b_date` (nicht mehr das Datum der Antragstellung)
- Abstimmberechtigte (VName1-6) sehen bei B-Anträgen den Button "Ansehen und Abstimmen" mit Link zu `abstimmungen.php`; abgegebene Voten können angesehen, aber nicht mehr geändert werden

### Fixed (Behoben)

#### Diverse Korrekturen (2026-10-15)

- MTool-Modus: `process_opinion.php` verwendet das per `mtool_mnr` übergebene Mitglied als Ersteller des Meinungsbilds statt des angemeldeten Admins
- `berechtigte_editor.php`: Listenansicht hat einen Zurück-Link zu `index.php?tab=admin`
- Session-Handling in eigenständigen Prozessoren (z.B. `process_kalender.php`): `session_config.php` muss vor `session_start()` geladen werden

### Fixed (Behoben)

#### Antrag-Finalisierung erzeugte X- statt B-Präfix (2026-09-16)

**Ursache:** `pseudo_cron.php` verwendete das Erstellungsdatum aus dem `antrnr`-String (YYMMDD) als Startpunkt für die Abstimmungsfrist. Ein Antrag, der 7 Tage nach Erstellung finalisiert wurde, galt bei der nächsten Cron-Ausführung sofort als abgelaufen (0 Stimmen → X-Präfix).

**Behoben in:**
- Neue Spalte `b_date DATE NULL` in der `antraege`-Tabelle: Wird beim A→B-Umbenennen (Finalisierung) auf `CURDATE()` gesetzt
- `antrag_bearbeiten.php` (`finalisiereAntrag()`): Setzt `b_date = CURDATE()` beim Umbenennen
- `pseudo_cron.php`: Nutzt jetzt `b_date` als Fristbeginn; Anträge ohne `b_date` (Altdaten) verwenden weiterhin das Datum aus `antrnr`
- `tools/init-db.php`: Migration für `b_date`-Spalte (wird beim Aufruf automatisch ergänzt)

#### Finalize-Button: Aktion fehlte nach Deaktivierung (2026-09-16)

- `antrag_bearbeiten.php`: "Verbindlich einstellen"-Button von `type="submit"` auf `type="button"` umgestellt
- Hintergrund: Browser senden den `name/value` von deaktivierten Submit-Buttons nicht mit — der `action=finalize`-Wert fehlte im POST, wenn der Button nach Klick gesperrt wurde
- Lösung: JavaScript erzeugt ein verstecktes `<input name="action" value="finalize">` und ruft `form.submit()` auf; der Button wird dann optisch deaktiviert

#### Server-seitige Prüfung in verwerfenAntrag() (2026-09-16)

- `antrag_bearbeiten.php`: `verwerfenAntrag()` prüft nun serverseitig, dass der Antrag das A-Präfix hat
- Verhindert das versehentliche Zurückziehen von B-Anträgen (zur Abstimmung stehend) durch direkte POST-Anfragen

#### fin-Betrag fehlte in beschluesse.fintext (2026-09-16)

- `voting_helper.php` (`beschluss_annehmen()`): Kombiniert jetzt `fin` und `fintext` aus der `antraege`-Tabelle:
  - Betrag + Text: `"1.234,56 Euro – Beschreibung"`
  - Nur Betrag: `"1.234,56 Euro"`
  - Nur Text / kein Betrag: unverändert wie bisher
- Vorher wurde `fin` (Geldwert) ignoriert, wenn `fintext` leer war

### Changed (Geändert)

#### Löschbereich in Antrag-Bearbeitungsseite (2026-09-16)

- `antrag_bearbeiten.php`: "Gefahrenbereich"-Box von rot auf bernsteinfarben/gelb umgestaltet (weniger alarmierend)
- Doppelter gelber Hinweis-Kasten über der Löschzone entfernt

### Added (Neu)

#### SCANS_DIR-Konstante für Upload-Verzeichnis (2026-09-16)

- `config.example.php`: Neue Konstante `SCANS_DIR` definiert das Verzeichnis für Antragsunterlagen
  - Neuinstallation: `__DIR__ . '/Scans/'` (Scans/ innerhalb Sitzungsverwaltung)
  - Altsystem/Legacy: `__DIR__ . '/../Scans/'` (eine Ebene höher, für Kompatibilität)
- `antrag_bearbeiten.php`, `process_agenda.php`: Nutzen jetzt `SCANS_DIR` statt hartkodierter Pfade
- `download_scan.php`: Lädt `SCANS_DIR` aus `config.php`

### Changed (Geändert)

#### Verzeichnisstruktur bereinigt (2026-09-17)

Produktivskripte liegen jetzt ausschließlich im Root. Wartungs- und Diagnose-Skripte sind in `tools/` zentralisiert.

**Verschoben: `includes/` → Root**
- `includes/antragstypen_helper.php` → `antragstypen_helper.php`
- `includes/voting_helper.php` → `voting_helper.php`
- `includes/`-Verzeichnis gelöscht

**Gelöscht: `deprecated/`**
- `ajax_get_protocol.php`, `ajax_meeting_actions.php` nicht mehr referenziert

**In `tools/` zentralisiert (waren im Root):**
- `init-db.php`, `fix_missing_beschluesse.php`, `diagnose_antraege.php`
- `apply_meeting_decisions_migration.php`, `install_documents.php`, `debug_post_comments.php`
- `run_init_demo_config.php`, `check_documents.php`
- `test_http_auth.php`, `test_mail_system.php`, `test_session_info.php`, `test_sso.php`

**`migrations/` → `tools/`** (alle SQL- und PHP-Migrationsdateien)

**Gelöscht: `sql/`** (veraltete SQL-Dateien: `add_verfuegung_monatslimit.sql`, `create_feedback_table.sql`, `create_members_view.sql.DEPRECATED`)

**require_once-Pfade** in allen verschobenen Skripten aktualisiert (`require_once 'config.php'` → `require_once __DIR__ . '/../config.php'`).

---

### Added (Neu)

#### E-Mail-Benachrichtigungssystem – Erweiterungen (2026-09-13)

**Datei `notification_mailer.php`:**
- **Vertraulichkeitsfilter**: Nachrichten zu vertraulichen TOPs (`svagenda_items.is_confidential = 1`) und internen Anträgen (`int_ext = 'i'`) werden nur an Mitglieder mit `is_confidential = 1` zugestellt. Alle `nm_event_*`-Funktionen haben dafür den optionalen Parameter `$is_confidential = false` erhalten. Im Callback: `if ($is_confidential && !($m['is_confidential'] ?? 0)) return null;`
- **Neue Hilfsfunktion `nm_deep_url($pdo, $path)`**: Erzeugt Deep-Links für E-Mails als `login.php?redirect=<Ziel>`. Nicht eingeloggte Empfänger landen nach dem Login direkt auf dem referenzierten Vorgang. Bereits eingeloggte User werden von `login.php` sofort weitergeleitet. Betrifft alle 7 Event-Funktionen: `antrag_neu`, `antrag_geaendert`, `antrag_hinweis`, `antrag_abstimmung`, `antrag_beschlossen`, `top_neu`, `top_kommentar`, `todo_zugewiesen`.
- **Abbestell-Link**: Footer-Link führt nun via `login.php?redirect=meine_benachrichtigungen.php` zu den Benachrichtigungseinstellungen statt zur Startseite.

**Datei `process_agenda.php`:**
- `add_comment_preparation`-Handler löst nun `nm_event_top_kommentar` aus (war bisher nicht implementiert; die anderen drei Kommentar-Handler hatten die Benachrichtigung bereits).

#### Abwesenheiten-Verwaltung – Admin-Erweiterungen (2026-09-13)

- **Admin: Liste neueste zuerst** (`process_admin.php`): `get_absences_with_names()` wird mit `array_reverse()` umgekehrt, sodass die neueste Abwesenheit oben erscheint.
- **Admin: "Alle vergangenen löschen"-Button** (`tab_admin.php`, `process_absences.php`): Schaltfläche mit Bestätigungsdialog über der Abwesenheitsliste; löscht alle `svabsences`-Einträge mit `end_date < CURDATE()`. Nur für Admins (`is_admin`). Erfolgs-/Fehlermeldung via `?msg=past_absences_deleted`.
- **Admin: `process_absences.php` auch für `tab=admin`** (`index.php`): Die Abwesenheitsverarbeitung wird nun auch geladen, wenn die aktive Seite `admin` ist (vorher: nur `meetings` und `vertretung`).

### Fixed (Behoben)

#### Doppelte Abwesenheitsanzeige in Übersichtskachel (2026-09-13)

**Ursache**: PHP-Referenzfehler nach `foreach ($array as &$var)`. Nach der Schleife zeigt `$var` noch auf das letzte Array-Element. Wenn anschließend `array_slice()` eine Kopie anfertigt und ein zweites `foreach` denselben Variablennamen verwendet, werden die referenzierten Slots bei jeder Iteration überschrieben, sodass das letzte Element in der Kachel doppelt erscheint.

**Behoben in:**
- `module_notifications.php`: `unset($abs)` nach dem `foreach ($all_absences as &$abs)` für das `is_current`-Flag
- `functions.php` (`get_absences_with_names`): `unset($absence)` nach dem Namens-Auflösungs-Foreach

#### `render_user_notifications()` unterdrückte alle Benachrichtigungen bei `hide_absences` (2026-09-13)

- `module_notifications.php`: Das frühe `return` bei `hide_absences = true` brach die Funktion ab, bevor die gesammelten Benachrichtigungen (Todos, Termine, Abstimmungen …) ausgegeben wurden. Ersetzt durch einen konditionalen Block `if (empty($options['hide_absences'])) { ... }` um nur den Abwesenheiten-Abschnitt zu überspringen.

#### Admin konnte Abwesenheit einer anderen Person nicht speichern (2026-09-09)

- `index.php`: `process_absences.php` wurde nur bei `tab=meetings` und `tab=vertretung` geladen, nicht bei `tab=admin`. Daher wurden Admin-Speicherungen auf der Admin-Seite ohne Verarbeitung weitergeleitet. Behoben durch Ergänzung von `|| $active_tab === 'admin'` in der Routing-Bedingung.

#### Antrag bearbeiten: Seitentitel ohne Antragstitel (2026-09-13)

- `antrag_bearbeiten.php`: `<h1>`-Überschrift zeigt nun den Antragstitel: „Antrag bearbeiten: [Titel]".

#### MTool-Integration & Listenbereinigung für Meinungsbilder und Terminplanung (2026-08-28)

**Meinungsbilder – MTool-Integration:**
- `opinion_standalone.php` als `require_once` aus MTool aufrufbar: erkennt `$MNr`, baut HTML-Wrapper und lädt `tab_opinion.php`
- Fix: Früher `exit()` in Public-Polls-Listing übersprang den MTool-Block wenn keine `poll_id`/`token` in der URL war (`!isset($MNr)` Guard)
- `$OPINION_PUBLIC_URL` wird nur gesetzt wenn nicht bereits vom Aufrufer definiert; explizit in `$GLOBALS` geschrieben für Funktions-Scope-Kompatibilität
- Session-Fallback `$_SESSION['opinion_mtool_share_url']` in `process_opinion.php` für korrekte Redirects
- `_opinion_url()` für externen Teilnehmer-Pfad (standalone ohne `tab_opinion.php`) definiert

**Für mich ausblenden – Meinungsbilder:**
- Jeder eingeloggte Nutzer kann Umfragen individuell ausblenden ("👁 Für mich ausblenden")
- Ausgeblendete Umfragen erscheinen in zugeklapptem Bereich "Für mich ausgeblendet (n)" mit Ergebnis-Link
- "↩ Wieder einblenden" macht die Ausblendung rückgängig
- Neue Tabelle `svopinion_user_hidden` (wird automatisch angelegt, auch in `init-db.php`)
- Neue POST-Aktionen `hide_poll` und `unhide_poll` in `process_opinion.php`

**Für mich ausblenden – Terminplanung:**
- Identische Funktion für Terminumfragen
- Neue Tabelle `svtermine_user_hidden` (wird automatisch angelegt, auch in `init-db.php`)
- Neue POST-Aktionen `hide_poll` und `unhide_poll` in `process_termine.php`

**Pflicht-Löschdatum beim Erstellen (Meinungsbilder):**
- Datumsfeld "Automatisch löschen am" ersetzt die Tages-Eingabe (Pflichtfeld, Default: heute +90 Tage, min: morgen)
- Lazy-Deletion: `get_all_opinion_polls()` setzt abgelaufene Umfragen automatisch auf `deleted`
- `ends_at` wird beim Erstellen jetzt korrekt gesetzt

#### Aktions-Protokollierung / Audit-Log (2026-08-13)

Vollständige Aktions-Protokollierung aller user-verursachten Datenbankänderungen.

- **Neue Datei `protokoll_helper.php`** mit zwei Funktionen:
  - `get_protokoll_user($current_user)` → `[$mnr, $kurz]` – ermittelt Mitgliedsnummer und Kurzname des eingeloggten Users (SSO-Session oder DB)
  - `protokoll($pdo, $mnr, $kurz, $was, $string, $filter=3)` – schreibt einen Eintrag in die Tabelle `protokoll`; `$filter`-Wert steuert Deduplizierung (0=tägl., 1=tägl., 2=stündl., 3=immer)
- **Neue Tabelle `protokoll`** in `init-db.php` (Schema: `MNr`, `KurzN`, `zeit`, `was`, `string` – kompatibel mit Altformat)
- **Monatsarchivierung** in `pseudo_cron.php`: Beim ersten Seitenaufruf eines neuen Monats wird `protokoll` → `YYYYMMprotokoll` kopiert und anschließend truncated; svconfig-Schlüssel `protokoll_last_archive` speichert den zuletzt archivierten Monat
- **~55 `protokoll()`-Aufrufe** in 8 Dateien instrumentiert:
  - `antrag_neu.php`: Antrag-Neu
  - `antrag_bearbeiten.php`: Antrag-Speichern, Antrag-Finalisieren, Antrag-Verwerfen, WZV-beantragt, WZV-Zustimmung
  - `abstimmungen.php`: Votum-Speichern, Votum-Bemerkungen, Abstimmung-Hinweis, Antrag-Zurueckziehen, Antrag-Kopie-Neu
  - `process_meetings.php`: Sitzung-Erstellen, -Bearbeiten, -Loeschen, -Starten, -Duplizieren
  - `process_agenda.php`: ~38 Labels (TOP-Neu, TOP-Bearbeiten, TOP-Loeschen, TOP-Verschieben, TOP-Wiedervorlage, Kommentar-*, Protokoll-*, Sitzung-*, Abstimmung-*, Stimme, TODO-*, Anwesenheit u. a.)
  - `process_todos.php`: TODO-Erstellen, TODO-Status, TODO-Zurueckziehen
  - `process_protocol.php`: Protokoll-Speichern

**Neue Dokumentation:** `docs/audit-logging.md`

### Changed (Geändert)

#### Monatssumme-Limit konfigurierbar (2026-08-13)

- `berechneMonatssumme()` in `antrag_bearbeiten.php` erhält `$v_limit` jetzt aus der svconfig-Tabelle statt als hartkodierte Konstante
- Konfigurationsschlüssel: `bart_V_betrag_limit` (Typ: number, Gruppe: antragstypen, Standardwert: 500)

#### Textformatierung in Antragsansicht (2026-08-13)

- **Neue Hilfsfunktion `format_antrag_text()`** in `includes/voting_helper.php`: normiert Zeilenenden, wandelt Doppelleerzeilen in `<p>`-Tags und Einzelzeilenumbrüche in `<br>`-Tags um
- **`render_hinweis_text()` überarbeitet**: unterstützt gemischtes DB-Format (altes `<br>`-Trenner und neues `\n---\n`-Trenner), strippt führende `<br>`, erkennt beide Datumsformate
- **CSS**: `white-space: pre-wrap` aus `.acc-content` entfernt (erzeugte doppelte Zeilenumbrüche)
- Betroffen: `antrag_ansehen.php`, `includes/voting_helper.php`

#### SSO-Fixes für opinion_standalone.php (2026-08-13)

- SSO-Auto-Login nach Session-Start eingebaut (lädt `config_adapter.php`, ruft `get_sso_membership_number()`)
- Standalone-URL jetzt über svconfig konfigurierbar: Schlüssel `opinion_standalone_url`

#### Konfigurierbare externe URLs für Standalone-Seiten (2026-08-13)

- **Neue svconfig-Schlüssel** `opinion_standalone_url` und `terminplanung_standalone_url` für konfigurierbare öffentliche URLs der Standalone-Seiten
- `generate_external_access_link()` in `external_participants_functions.php` prüft zuerst die svconfig-Einträge, fällt auf `BASE_URL` zurück
- Admin-UI (`tab_admin_init.php`): korrekter Placeholder-Text für URL-Felder
- Initialisierung in `init-db.php` und `tab_admin_init.php`

#### Upload-Verzeichnis für Antragsunterlagen (2026-08-13)

- Upload-Verzeichnis in `antrag_bearbeiten.php` und `process_agenda.php` von `uploads/antraege/` auf `Scans/` geändert
- DB-Format der Dateipfade (`Scans/filename`) kompatibel mit Altprogramm-Dateien

#### SSO Session-Timeout UX-Verbesserungen (2026-08-13)

- `index.php`: Fehlermeldung bei abgelaufenem SSO-Token jetzt "Anmeldung abgelaufen" statt "Bitte Zugriff verifizieren"
- VTool-Button erhält `target="_top"` für korrekte Navigation in iframe-Umgebungen
- `login.php`: Leitet im SSO-Modus automatisch zu `index.php` weiter (mit SSO-Auto-Login-Versuch)

#### external_participant_register.php im SSO-Modus (2026-08-13)

- Mitgliedsnummer-Sektion wird im SSO-Modus ausgeblendet (kein separates Login-Formular im SSO-Kontext sinnvoll)

---

### Changed (Geändert)

#### Migration zu reiner Adapter-Architektur (2026-05-27)
- **VIEW-System vollständig entfernt** - Keine "magischen" VIEWs mehr
- **Alle Member-Zugriffe über Adapter-Funktionen** - Expliziter, nachvollziehbarer Code
- **7 verbleibende SQL-JOINs gefixed** - Konvertiert zu Adapter-Calls
- **Gründe:** Bessere Wartbarkeit, Klarheit für künftige Admins, kein verstecktes VIEW-System

**Betroffene Dateien:**
- process_meetings.php - Name-Lookups über `get_member_by_id()`
- tab_agenda_display_protocol_ready.php - Kommentare ohne JOINs
- tab_agenda_display_ended.php - Kommentare ohne JOINs  
- functions_collab_text.php - Leadership-Members über `get_all_members()`
- tab_admin.php - Poll-Creator über Adapter
- external_participant_register.php - Vereinfachte Membership-Check
- debug_post_comments.php - Debug-Queries über Adapter
- member_functions.php - `ensure_svmembers_view()` entfernt
- functions.php - VIEW-Call entfernt
- sql/create_members_view.sql - Als DEPRECATED markiert

**Vorteile:**
- ✅ Kein verstecktes VIEW-System mehr
- ✅ Explizite Adapter-Calls statt "Magie"
- ✅ Einfacher zu debuggen für künftige Admins
- ✅ Konsistent: Überall gleiche Adapter-Funktionen

### Added (Neu)

#### 📋 Vollständiges Antrags- und Beschluss-System (2026-05-20 bis 2026-05-22)
**Ein komplettes Workflow-System für Anträge und Vorstandsbeschlüsse mit VTool-Integration**

##### Kernfunktionen:
- **Automatische Antragsnummern-Vergabe**: 
  - Format: `A + YYMMDD + lfd. Nr.` (z.B. A26051401)
  - Bis zu 99 Anträge pro Tag
  - Verschiedene Präfixe: A (Antrag), B (Vorstandsbeschluss), VS (Beschlossen)
  
- **Antragsverwaltung** (`tab_proposals.php`):
  - Übersicht aller offenen Anträge
  - Filter nach Status, Präfix, Antragstyp
  - Volltextsuche (Nummer, Titel, Beschluss)
  - Mobile-responsive Design
  
- **Beschlussbuch** (`beschlussbuch.php`):
  - Alle beschlossenen Anträge (VS-Präfix)
  - Suchfunktion mit Highlighting
  - URL-Erkennung in Texten
  - Dark Mode Support
  
- **Vollständiges Antragsformular**:
  - **Stammdaten**: Ressort, Verantwortlicher, Verein/Stiftung, Sichtbarkeit
  - **Antrag**: Titel, Beschlusstext, Begründung, Betrag
  - **Auswirkungen**: Finanziell, Personell, Sachlich
  - **Angebote/Unterlagen**: Bis zu 4 Datei-Uploads mit Beschreibung
  - **Vereinfachte Freigabe**: Sofort-Überweisung, Vorprüfung
  - **Bemerkungen**: Hinweise mit Zeitstempel
  
- **Konfigurierbare Antragstypen**:
  - V (Vorlage), R (Ressort), B (Vorstandsbeschluss)
  - Individuelle Voting-Regeln pro Typ
  - Betragsgrenzwerte konfigurierbar
  - Verwaltung über Admin-Bereich
  
- **Berechtigungssystem**:
  - Basierend auf `aktiv`-Level aus VTool berechtigte-Tabelle
  - Level 18+: Interne Anträge sichtbar
  - Level 19+: Admin-Funktionen (permanentes Löschen)
  - Funktionsbasiert: VA (Vorstandsassistenz) hat erweiterte Rechte
  
- **Integration mit Meetings**:
  - Anträge direkt aus TOPs erstellen
  - Kategorie "Antrag/Beschluss" beim TOP-Anlegen
  - Vollständiges Formular in der Sitzungsvorbereitung
  - Automatische Verknüpfung mit Tagesordnung
  - Meeting-ID wird im Antrag gespeichert
  
- **Voting/Abstimmungs-Integration**:
  - Direkte Abstimmung über Anträge in Sitzungen
  - Integration mit `beschluesse`-Tabelle
  - ANGENOMMEN/ABGELEHNT-Status
  - Automatische Überführung in Beschlussbuch bei Annahme
  - Protokoll-Formatierung für Abstimmungsergebnisse
  
- **Workflow-Features**:
  - Antrag duplizieren (mit allen Daten)
  - Wiedervorlage zu anderen Sitzungen
  - Wartezeit-Anzeige (Expedite-Verfahren)
  - Originalantrag-Referenz bei Duplikaten
  - Permanentes Löschen nur für X/Z-Präfixe

##### Technische Details:
- **Neue Tabellen**: `svantraege`, `svbeschluesse` (optional), `svressorts`, `svconfig`, `antragstypen_config`, `aktiv_level_config`
- **VTool-Integration**: Nutzt optional existierende VTool-Tabellen (`antraege`, `beschluesse`)
- **Konstanten**: `TABLE_ANTRAEGE`, `TABLE_BESCHLUESSE` für flexible Tabellenzuordnung
- **Helper**: `antragstypen_helper.php` für Konfigurationsverwaltung

##### Neue Dateien:
- `antrag_neu.php` - Neuen Antrag erstellen
- `antrag_bearbeiten.php` - Antrag bearbeiten (63KB)
- `antrag_ansehen.php` - Antrag ansehen
- `tab_proposals.php` - Antragsverwaltung-Tab
- `beschlussbuch.php` - Beschlussbuch (VS-Anträge)
- `includes/antragstypen_helper.php` - Konfigurationsfunktionen
- `apply_meeting_decisions_migration.php` - Migrations-Tool

##### UI-Features:
- **Dark Mode**: Vollständige Unterstützung auf allen Antragsseiten
- **Mobile-optimiert**: Responsive Design für Smartphones/Tablets
- **URL-Erkennung**: Automatisch klickbare Links in Texten
- **Syntax-Highlighting**: Suchbegriffe werden hervorgehoben
- **Accordion-Views**: Platzsparende Darstellung langer Inhalte

#### Wiedervorlage-System für TOPs (2026-05-26)
- **TOP-Verschiebung mit Antragsverfolgung**: TOPs können zu künftigen Sitzungen verschoben werden
- Automatische Übertragung verknüpfter Anträge (antrnr) beim Verschieben
- Meeting-Zuweisung wird in der Antrags-Datenbank aktualisiert
- Dokumentation in beiden Sitzungen (Quell- und Ziel-Meeting)
- Nur für Einladende, Protokollant und Sitzungsleiter verfügbar
- Nicht verfügbar für System-TOPs (0, 99)

#### Optimierte Abwesenheiten-Anzeige (2026-05-26)
- **Intelligente Limitierung**: Zeigt die nächsten 4 Abwesenheiten statt alle
- **Kollapsible "weitere..." Sektion**: Bei mehr als 4 Einträgen wird Rest ausklappbar angezeigt
- **Dynamischer Link-Text**: 
  - "Details" bei 4 oder weniger Einträgen
  - "weitere..." bei mehr als 4 Einträgen
- **Responsive Layout**: Optimierte Darstellung auf mobilen Geräten
- **Farbkodierung**: Aktuelle Abwesenheiten in Rot hervorgehoben
- Anwendung in:
  - Benachrichtigungsbox (gelbe Infobox)
  - Sitzungsvorbereitung
  - Teilnehmerverwaltung

#### Verbessertes Voting-System (2026-05-25/26)
- **Frage-Feld für Abstimmungen**: Neue Spalte `voting_question` für Abstimmungen ohne Antrags-Bezug
- **Meinungsbild vs. Beschlussabstimmung**: 
  - ANGENOMMEN/ABGELEHNT nur bei Antrags-Abstimmungen
  - Meinungsbilder zeigen nur Voting-Frage und Ergebnisse
- **Kompaktere Darstellung**: 
  - Geschlossene Abstimmungen nehmen weniger Platz ein
  - Optimierte Protokoll-Formatierung
- **Checkbox-Integration**: "Als Antrag/Beschluss anlegen" direkt im Voting-Dialog
- **Protokoll-Verbesserungen**: Strukturierte Darstellung in Meeting-Protokollen

#### VTool-Integration (2026-05-25)
- **Vollständige Tabellennamen-Abstraktion**: 
  - Konstanten `TABLE_ANTRAEGE` und `TABLE_BESCHLUESSE`
  - Flexibel zwischen VTool- und eigenständigen Tabellen wechselbar
- **Nutzung existierender VTool-Tabellen**:
  - `beschluesse` statt `svbeschluesse`
  - `antraege` bleibt unverändert (bereits VTool-kompatibel)
- Keine Daten-Duplikation mehr zwischen Systemen
- Nahtlose Integration in bestehende VTool-Infrastruktur

### Changed (Geändert)

#### Benachrichtigungs-Optimierungen (2026-05-26)
- **Termine limitiert**: Maximal 3 kommende Termine in gelber Infobox
- **Fokus auf Relevantes**: Wichtigste/nächste Termine werden priorisiert
- Volle Termin-Liste weiterhin über Tab-Navigation erreichbar

#### UI-Verbesserungen (2026-05-26)
- **Pflichtfeld-Hinweise**: Konsistente Markierung mit rotem Stern (*)
- **Antragsteller-Anzeige**: Nur Vor- und Nachname in Edit-Formularen (kompakter)
- **Voting-Navigation**: Verbesserte Benutzerführung bei Abstimmungen
- **Responsive Optimierungen**: Bessere Darstellung auf Tablets und Smartphones

### Fixed (Behoben)

#### Voting-System Bugfixes (2026-05-25/26)
- HTML-Struktur-Fehler in geschlossenen Abstimmungen behoben
- Parse-Error in `tab_agenda_display_preparation.php` korrigiert (fehlender PHP-Schlusstag)
- Voting-Ergebnis-Anzeige bei Meinungsbildern ohne Antrag korrigiert

---

#### Externe Teilnehmer für Meinungsbilder (2025-12-23)
- **Externe Teilnehmer ohne Login**: Meinungsbilder können nun mit externen Personen geteilt werden
- Neue Tabelle `svexternal_participants` für Gast-Teilnehmer
- Token-basierter Zugriff via URL (z.B. `opinion_standalone.php?token=abc123`)
- Frontend: "🔗 Link für Externe" Button zum Generieren von Zugangslinks
- Backend-API: `api/external_participant_create.php` und `api/external_participant_revoke.php`
- Admin-Ansicht für Token-Verwaltung
- Dokumentation: `EXTERNE_TEILNEHMER_README.md`

#### Externe Links für Dokumente (2025-12-23)
- **Dokumentenverwaltung**: Dokumente können nun als externe Links statt Uploads verwaltet werden
- Neue Spalte `external_url` in `svdocuments` Tabelle
- Radio-Button-Auswahl im Upload-/Edit-Formular: "📁 Datei hochladen" oder "🔗 Externer Link"
- Vermeidet doppelte Datenhaltung bei Cloud-Dokumenten (SharePoint, Google Drive, etc.)
- Anzeige unterscheidet zwischen lokalem Download und externem Link
- Dokumentation: `DOCUMENTS_README.md` (aktualisiert)

#### Meeting-Duplikation (2025-12-23)
- **"📋 Duplizieren"** Button für regelmäßige Sitzungen
- Kopiert alle Einstellungen, Teilnehmer, Sichtbarkeit
- Datum wird automatisch +7 Tage gesetzt
- Nur für Sitzungs-Ersteller und Admins verfügbar

#### Weitere Features
- **Video-Link-Feld** in Sitzungsverwaltung vergrößert (min-width: 400px)
- **KurzURL** für Dokumente (optional, erzeugt zusätzlichen "🔗 Kurzlink" Button)

### Changed (Geändert)

#### Dokumentenverwaltung UI-Refactoring (2025-12-23)
- Dokumentenverwaltung aus Admin-Bereich in eigenen **"📁 Dokumente"** Tab verschoben
- Button "➕ Dokument hinzufügen" unter der Liste statt oben
- Nur für User mit `is_admin=1` Flag sichtbar (nicht mehr rollenbasiert)
- Bearbeiten- und Löschen-Buttons direkt bei jedem Dokument
  - ✏️ Bearbeiten (Orange)
  - 🗑️ Löschen (Rot mit Bestätigung)

#### Access-Level vereinfacht (2025-12-23)
Dokumenten-Zugriffslevel auf 3 Kategorien reduziert:
- **0**: Alle Mitglieder
- **15**: Führungsrollen (Vorstand, GF, Assistenz, Führungsteam)
- **18**: Vorstand + GF + Assistenz

Alte feingranulare Levels (Projektleitung, Ressortleitung) entfernt.

#### Production Reset Tool verbessert (2025-12-23)
- Vereinfacht: Statt Passwort-Eingabe nur noch "RESET" als Bestätigung
- 2-Stufen-Prozess: RESET-Wort → Zwei Checkboxen
- Keine Session-basierten Authentifizierungs-Probleme mehr

### Fixed (Behoben)

#### SSO-Integration nach DB-Reset (2025-12-23)
- **Critical Fix**: Adapter-Auswahl nach Datenbank-Reset korrigiert
- Problem: Nach Reset wurde falsche Datenquelle (svmembers statt berechtigte) verwendet
- Lösung: `config_adapter.php` prüft nun `REQUIRE_LOGIN` **vor** Session-Check
- Alle direkten SQL-Zugriffe auf `svmembers` durch Adapter-Calls ersetzt:
  - `functions.php`: `get_visible_meetings()`, `can_user_access_meeting()`
  - `functions_collab_text.php`: `hasCollabTextAccess()`
  - `module_notifications.php`: `render_user_notifications()`
  - `process_mail_queue.php`: Admin-Check

#### Weitere Bugfixes
- Meeting-Anzeige nach Erstellung fehlte (Adapter-Problem)
- SQL Parameter Count Mismatch beim Meeting duplizieren
- Spaltennamen in svdocuments korrigiert (filename statt file_name)

### Security (Sicherheit)
- Token-basierter Zugriff für externe Teilnehmer (kein Login erforderlich)
- Token-Widerruf-Mechanismus für Admins
- Externe Teilnehmer haben nur Zugriff auf zugewiesene Meinungsbilder

---

## Upgrade-Hinweise

### Von vorheriger Version

**Datenbank-Migration erforderlich:**

0. **Aktions-Protokollierung** (2026-08-13):
```sql
-- Wird automatisch durch init-db.php ausgeführt
CREATE TABLE IF NOT EXISTS protokoll (
    MNr    VARCHAR(12) DEFAULT NULL,
    KurzN  VARCHAR(12) DEFAULT NULL,
    zeit   VARCHAR(20) DEFAULT NULL,
    was    TEXT        DEFAULT NULL,
    string TEXT        DEFAULT NULL,
    INDEX idx_mnr  (MNr),
    INDEX idx_zeit (zeit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Neue svconfig-Schlüssel (ebenfalls automatisch via init-db.php):
INSERT IGNORE INTO svconfig (config_key, config_value, config_type, description, config_group)
VALUES
  ('opinion_standalone_url',       '', 'text', '...', 'notifications'),
  ('terminplanung_standalone_url', '', 'text', '...', 'notifications'),
  ('protokoll_last_archive',       '', 'text', '...', 'system');
```

1. **Antrags- und Beschluss-System** (2026-05-20 bis 2026-05-22):
```sql
-- Wird automatisch durch init-db.php ausgeführt
-- Neue Tabellen:
CREATE TABLE svantraege (...);  -- Optional (nutzt ggf. VTool antraege)
CREATE TABLE svbeschluesse (...);  -- Optional (nutzt ggf. VTool beschluesse)
CREATE TABLE svressorts (...);
CREATE TABLE svconfig (...);
CREATE TABLE antragstypen_config (...);
CREATE TABLE aktiv_level_config (...);

-- Neue Spalten in svmeetings:
ALTER TABLE svmeetings ADD COLUMN allow_decisions TINYINT(1) DEFAULT 1;

-- Siehe auch: apply_meeting_decisions_migration.php
```

2. **Voting-Question Spalte** (2026-05-25):
```sql
-- Wird automatisch durch init-db.php ausgeführt
ALTER TABLE svvotings 
ADD COLUMN voting_question VARCHAR(500) DEFAULT NULL 
COMMENT 'Frage bei Stimmungsbild (wenn kein Antrag)' 
AFTER initiated_by_member_id;
```

3. **Externe Teilnehmer** (2025-12-23):
```sql
-- Siehe: migrations/add_external_participants.sql
CREATE TABLE svexternal_participants (...);
ALTER TABLE svopinion_responses ADD COLUMN external_participant_id INT DEFAULT NULL;
-- ...
```

3. **Externe Dokument-Links** (2025-12-23):
```sql
-- Siehe: migrations/add_external_url_to_documents.sql
ALTER TABLE svdocuments ADD COLUMN external_url VARCHAR(1000) DEFAULT NULL;
ALTER TABLE svdocuments MODIFY COLUMN filepath VARCHAR(500) NULL;
-- ...
```

**Manuelle Anpassungen:**

- **VTool-Integration**: Prüfen Sie `config.php` - Konstanten `TABLE_ANTRAEGE` und `TABLE_BESCHLUESSE` 
  - Standard: `antraege` und `beschluesse` (VTool-Tabellen)
  - Eigenständig: `antraege` und `svbeschluesse`
- `config_adapter.php`: Falls individuell angepasst, siehe Commit für `!REQUIRE_LOGIN` Check
- Dokumenten-Zugriffslevel ggf. von alten Werten (12, 19) auf neue migrieren (0, 15, 18)

---

## Dokumentation

- **Neue Dokumentationen**:
  - `EXTERNE_TEILNEHMER_README.md` - Externes Meinungsbild-System
  - `CHANGELOG.md` - Diese Datei

- **Aktualisierte Dokumentationen**:
  - `DOCUMENTS_README.md` - Externe Links Feature
  - `README.md` - Allgemeine Projektbeschreibung

---

## Technische Details

### Datenbankschema-Änderungen

**Neue Tabellen (Antrags-System)** (2026-05-20 bis 2026-05-22):
- `svantraege` - Antragsverwaltung (optional, nutzt ggf. VTool `antraege`)
- `svbeschluesse` - Beschlussverwaltung (optional, nutzt ggf. VTool `beschluesse`)
- `svressorts` - Ressort-/Abteilungsverwaltung
- `svconfig` - Systemkonfiguration (Betragsgrenzwerte, etc.)
- `antragstypen_config` - Konfiguration der Antragstypen (V, R, B)
- `aktiv_level_config` - Konfiguration der Berechtigungslevel

**Neue Tabellen (Sonstige)**:
- `svexternal_participants` - Externe Teilnehmer für Meinungsbilder (2025-12-23)
- `protokoll` - Aktions-Protokoll aller User-Datenbankänderungen (2026-08-13)
- `YYYYMMprotokoll` (dynamisch) - Monatliche Archivtabellen, automatisch angelegt (2026-08-13)

**Neue Spalten**:
- `svmeetings.allow_decisions` (TINYINT 1, DEFAULT 1) - Beschlüsse in Meeting erlauben (2026-05-20)
- `svagenda_items.antrnr` (VARCHAR 20, NULL) - Verknüpfung mit Antrag (2026-05-20)
- `svvotings.voting_question` (VARCHAR 500, NULL) - Frage bei Stimmungsbild ohne Antrag (2026-05-25)
- `svdocuments.external_url` (VARCHAR 1000, NULL) - Externe Dokument-Links (2025-12-23)
- `svopinion_responses.external_participant_id` (INT, NULL) - Zuordnung zu externen Teilnehmern (2025-12-23)

**Modified Constraints**:
- `svdocuments.filepath` - Jetzt NULL erlaubt (bei externen Links)
- `svdocuments.filename` - Jetzt NULL erlaubt (bei externen Links)
- `svdocuments.filesize` - Jetzt NULL erlaubt (bei externen Links)

**Konstanten für Tabellennamen** (2026-05-25):
- `TABLE_ANTRAEGE` - Konfigurierbar: `antraege` (Standard/VTool) oder `antraege` (eigenständig)
- `TABLE_BESCHLUESSE` - Konfigurierbar: `beschluesse` (VTool) oder `svbeschluesse` (eigenständig)

### Neue Dateien (Antrags-System) (2026-05-20 bis 2026-05-22)

**Hauptdateien**:
- `antrag_neu.php` - Neuen Antrag erstellen (automatische Nummernvergabe)
- `antrag_bearbeiten.php` - Antrag bearbeiten (vollständiges Formular, 63KB)
- `antrag_ansehen.php` - Antrag ansehen (Detailansicht)
- `tab_proposals.php` - Antragsverwaltung-Tab (Filter, Suche, Verwaltung)
- `beschlussbuch.php` - Beschlussbuch (alle VS-Beschlüsse)

**Helper & Tools**:
- `includes/antragstypen_helper.php` - Konfigurationsfunktionen für Antragstypen
- `apply_meeting_decisions_migration.php` - Migrations-Tool für Datenbankschema
- `protokoll_helper.php` - Aktions-Protokollierung: `get_protokoll_user()`, `protokoll()` (2026-08-13)

**Styles**:
- `css/antrag-styles.css` - Styling für Antragsseiten (inkl. Dark Mode)

### API-Endpunkte (neu)

- `POST api/external_participant_create.php` - Token für externen Zugang erstellen
- `POST api/external_participant_revoke.php` - Token widerrufen

### Standalone-Tools (erweitert)

- `opinion_standalone.php?token=...` - Token-basierter Zugriff für Externe
- `tools/production_reset.php` - Vereinfachter Reset-Prozess

---

## Mitwirkende

**Aktuelle Session:**
- Session: claude/continue-session-management-01NbwbYdHVMH7hEM5HwQmFji
- Datum: 2026-08-13
- Features: Aktions-Protokollierung, Konfigurierbare URLs, Monatssumme-Limit, Textformatierung, SSO-Fixes, Upload-Verzeichnis

**Vorherige Sessions:**
- Session: claude/continue-session-management-01NbwbYdHVMH7hEM5HwQmFji
- Datum: 2026-05-25 bis 2026-05-26
- Features: Wiedervorlage, Voting-System, Abwesenheiten-Optimierung, VTool-Integration

- Session: claude/fix-sso-integration-01NbwbYdHVMH7hEM5HwQmFji
- Datum: 2025-12-23
- Features: Externe Teilnehmer, Externe Links, Meeting-Duplikation
