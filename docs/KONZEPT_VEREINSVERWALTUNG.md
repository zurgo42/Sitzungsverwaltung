# Konzept: Vereinsverwaltung als Erweiterung der Sitzungsverwaltung

Stand: Entwurf zur Abstimmung. Alle Aufwandsangaben sind grobe Schätzungen (Arbeitstage einer Person, etwa ±30 %).

## 1. Ziel und Abgrenzung

**Ziel:** Ein schlankes Werkzeug für Vereine, die heute mit Excel oder überladener Software arbeiten. Es steuert den Zugriff der Mitglieder, führt ein Mitgliederverzeichnis, bietet Mailverteiler, eine vollständige Beitragsverwaltung mit Rechnung, Lastschrift und Mahnung sowie eine einfache Mitgliederversammlung.

**Leitlinien**
- **Bausteine statt Monolith:** Jede Funktion ist ein Baustein, den der Admin ein- oder ausschaltet. Vom Minimum (nur Verzeichnis) bis zum vollen Umfang.
- **Standalone, gleiche Datenbank:** Die neuen Tabellen liegen neben denen des Sitzungstools (Präfix `sv`). Mitglieder, Rollen, Audit-Log, Mail-Warteschlange und Benachrichtigungen werden gemeinsam genutzt. Dadurch entstehen die Verknüpfungen zum Sitzungstool (z. B. Rollen als Sitzungsteilnehmer, Mitgliederversammlung mit Protokoll) von selbst.
- **Dateien statt Schnittstellen:** Banken und Buchhaltung werden über genormte Dateien bedient (SEPA-Lastschrift, Kontoauszug, Export-Vorlagen), nicht über direkte Anbindungen.
- **Einstellbar statt vereinsspezifisch:** Beitragsgruppen, Konten-Zuordnung, Texte, Felder und Fristen stellt der Admin ein.

**Nicht-Ziele**
- Keine direkte Bankanbindung (FinTS, Bank-APIs) und keine eigene Finanzbuchführung.
- Keine Hybrid-Abstimmung in der Mitgliederversammlung (Abstimmung nur vor Ort).
- Keine Vollmachtsverwaltung, keine Anmeldepflicht zur Mitgliederversammlung (in der Standardeinstellung).
- Spendenbescheinigungen: nicht im ersten Umfang (später als eigener Baustein möglich).

## 2. Bausteine und Schalter

| Nr. | Baustein | Inhalt | Abhängig von |
|---|---|---|---|
| M0 | Mitglieder & Zugriff | Konten, Login, Rollen, Rechtegruppen, Selbstpflege | – |
| M1 | Mitgliederverzeichnis | Stammdaten, Status, Eintritt/Austritt, Auswertungen, Import/Export | M0 |
| M2 | Mailverteiler | Verteiler nach Rolle/Merkmal, Versand, Abmeldung, Protokoll | M0, M1 |
| M3 | Beiträge | Beitragsgruppen, Abweichungen, Rechnungslauf, Zahlungen, Mahnung | M1 |
| M4 | Lastschrift | SEPA-Mandate, Einzugsdatei, Rücklastschriften | M3 |
| M5 | Mitgliederversammlung | Einladung, Teilnehmerliste/Anwesenheit, Protokoll | M0, M1 |
| X | Exporte | Buchhaltungs-Vorlagen, Listen | M3 |

Im Admin-Bereich steht je Baustein ein Schalter. Ausgeschaltete Bausteine erscheinen nicht im Menü und führen keine Hintergrundaufgaben aus.

## 3. Rechte und Zugriffe (M0)

**Konten:** Basis ist die vorhandene Mitgliedertabelle `svmembers` (E-Mail, Passwort-Hash, Rolle, Admin-Flag). Neu: Passwort-Zurücksetzen per Mail, optional Zwei-Faktor-Anmeldung, Sperren/Entsperren.

**Zwei Ebenen**
1. **Rolle** (grob, wie bisher): Mitglied, Führungsteam, Assistenz, Geschäftsführung, Vorstand, Admin.
2. **Funktionen und Gremien** (fein, mit Zeitraum): z. B. Kassenwart, Schriftführung, Ressortleitung. Je Funktion sind Rechte pro Baustein einstellbar (ansehen, bearbeiten, Beiträge buchen, Mails senden, exportieren). Funktionen haben Beginn und Ende, damit Ämterwechsel nachvollziehbar bleiben.

**Rechte-Matrix** (Beispiel, im Admin änderbar)

| Aktion | Mitglied | Funktion Verzeichnis | Funktion Kasse | Vorstand | Admin |
|---|---|---|---|---|---|
| Eigene Daten ansehen/ändern | ja | ja | ja | ja | ja |
| Verzeichnis ansehen | Auswahl der Felder | ja | – | ja | ja |
| Verzeichnis bearbeiten | – | ja | – | nein | ja |
| Beiträge/Zahlungen | nur eigene | – | ja | ansehen | ja |
| Mailverteiler senden | – | optional | – | ja | ja |

**Selbstpflege:** Mitglieder ändern Adresse, Telefon und E-Mail selbst. Pro Feld stellt der Admin ein: frei änderbar, mit Freigabe durch das Verzeichnis, oder gesperrt. IBAN und Name ändern sich nur über Mandat/Verwaltung, nicht frei.

**Sichtbarkeit im Verzeichnis:** Jedes Mitglied legt fest, welche Angaben andere Mitglieder sehen (Standard: nur Name und Funktion). Der Admin setzt die Standardwerte.

## 4. Mitgliederverzeichnis (M1)

**Stammdaten** (Felder ein-/ausblendbar, eigene Zusatzfelder möglich): Mitgliedsnummer, Anrede, Titel, Name, Geburtsdatum (optional), Adresse, Telefon, E-Mail, Eintritt, Austritt, Kündigungsvermerk, Beitragsgruppe, Bemerkung, Status (Interessent, aktiv, ruhend, ausgetreten, Ehrenmitglied).

**Auswertungen:** Filter und Spaltenauswahl, speicherbare Listen, Export nach CSV/Excel. Typische Listen: Eintritte/Austritte im Zeitraum, Mitglieder ohne E-Mail, Geburtstage (nur mit Einwilligung), Funktionsträger.

**Import:** Excel/CSV mit Zuordnung der Spalten, Vorschau, Fehlerliste, Prüfung auf Dubletten. Das ist der Einstieg für Vereine, die aus einer Tabelle kommen.

**Änderungsverlauf:** Das vorhandene Audit-Log protokolliert, wer wann was geändert hat. Zusätzlich eine lesbare Historie je Mitglied (Beitragsgruppe, Status, Funktion).

## 5. Mailverteiler (M2)

- **Verteiler** entstehen aus Rolle, Funktion, Beitragsgruppe, Status oder einer gespeicherten Liste. Dynamische Verteiler bleiben aktuell, feste Verteiler werden von Hand gepflegt.
- **Versand** über die vorhandene Mail-Warteschlange (Batch, Pause zwischen den Mails). Platzhalter wie Anrede und Name.
- **Abmeldung:** Link im Fuß jeder Rundmail; vereinsnotwendige Mitteilungen (Einladung, Beitragsrechnung) sind davon ausgenommen und als solche markiert.
- **Protokoll:** Wer hat wann was an welchen Verteiler gesendet, mit Zustellstatus (versendet/fehlgeschlagen).
- **Hinweis zur Zustellbarkeit:** Ob Mails ankommen, hängt an SPF, DKIM und der Absenderadresse des Servers. Das ist Admin-Konfiguration und wird in der Dokumentation beschrieben.

## 6. Beiträge (M3)

**Beitragsgruppen:** Frei anlegbar (Bezeichnung, Betrag, Rhythmus: jährlich/halbjährlich/vierteljährlich/monatlich, Eintrittsregel: ab Eintritt anteilig / ab nächstem Monat / volles Jahr). Typisch sind etwa vier Hauptgruppen.

**Individuelle Abweichungen:** Je Mitglied ein abweichender Betrag oder eine Ermäßigung in Prozent, mit Grund, Gültigkeit von/bis und Hinweis, wer sie bewilligt hat (Nachvollziehbarkeit für Revision und Datenschutz).

**Rechnungslauf**
1. Vorschau: Alle Mitglieder mit fälligem Beitrag, Summe, Ausnahmen.
2. Erzeugen: Rechnungen mit laufender Nummer, Rechnungsdatum, Fälligkeit, Positionen, Steuerhinweis als Textbaustein (Beiträge sind in der Regel umsatzsteuerfrei).
3. **Ausgabe:** PDF je Rechnung. Versand per Mail mit Anhang an Mitglieder mit E-Mail-Adresse; Sammel-PDF zum Ausdrucken für Postversand. Der Zustellweg ist je Mitglied einstellbar.
4. Lastschrift-Mitglieder bekommen eine Rechnung mit dem Hinweis auf den Einzug (Vorabankündigung, Frist einstellbar).

**Zahlungen**
- Erfassen von Hand (Datum, Betrag, Zuordnung zur Rechnung).
- **Kontoauszug-Import:** CAMT-Datei oder CSV mit einstellbarer Spaltenzuordnung je Bank. Das System schlägt Zuordnungen vor (Rechnungsnummer, Mitgliedsnummer, Name, Betrag) und nimmt sie erst nach Bestätigung vor. Teilzahlungen und Überzahlungen sind möglich.
- Offene Posten je Mitglied und Gesamtliste.

**Mahnwesen:** Einstellbare Mahnstufen (Zahlungserinnerung, 1. Mahnung, 2. Mahnung) mit Fristen, Mahngebühr und Text. Mahnlauf erzeugt Vorschlagsliste, die geprüft und freigegeben wird; Versand wie bei Rechnungen. Mitglieder mit Stundung oder Ermäßigung werden ausgenommen oder markiert.

**Auswertungen:** Soll/Ist je Zeitraum, offene Beiträge, Beitragsaufkommen je Gruppe, Mitgliederstand.

## 7. Lastschrift (M4)

- **Mandate:** Mandatsreferenz, Unterschriftsdatum, IBAN (und BIC, wo nötig), Kontoinhaber, Status (aktiv/widerrufen), Hinweis auf Mandatsunterlage (Scan als Dokument). IBAN wird in der Datenbank verschlüsselt gespeichert.
- **Einzug:** Auswahl der fälligen Rechnungen mit Mandat → Einzugsdatum → Datei im SEPA-Format (pain.008). Die Formatversion ist einstellbar, weil Banken sich unterscheiden. Vorschau mit Summe und Anzahl vor dem Erzeugen.
- **Nach dem Einzug:** Bestätigen, dass die Datei bei der Bank eingereicht wurde; die Rechnungen gelten danach als „im Einzug". Rücklastschriften werden erfasst (Grund, Gebühr) und setzen die Rechnung wieder offen.
- **Vereinsdaten:** Gläubiger-ID, Name, Konto des Vereins stellt der Admin ein.
- **Test:** Das Format wird vor dem Einsatz mit einer Testdatei bei der Bank des Vereins geprüft.

## 8. Mitgliederversammlung (M5)

Bewusst einfach, ohne Anmeldung und Vollmachten.

1. **Anlegen:** Datum, Ort, Online-Link, Tagesordnung. Vorlage aus dem Sitzungstool (Tagesordnungspunkte, Anträge) kann übernommen werden.
2. **Einladung:** Mail an alle stimmberechtigten Mitglieder über M2, mit Frist und Hinweis auf die Satzung (Text einstellbar).
3. **Teilnehmerliste:** Anwesenheit wird vor Ort (oder per Kurzsuche) abgehakt; getrennt nach „vor Ort" und „online". Die Zahl der Stimmberechtigten vor Ort steht jederzeit zur Verfügung (für die Beschlussfähigkeit).
4. **Abstimmung:** Vor Ort außerhalb des Systems; Ergebnisse je TOP (Ja/Nein/Enthaltung) werden eingetragen. Keine Online-Abstimmung.
5. **Protokoll:** Erzeugung aus den TOPs mit Teilnehmerzahl, Beginn/Ende und Beschlüssen, mit demselben Generator wie im Sitzungstool; Freigabe und Archiv.
6. **Verknüpfung:** Beschlüsse der Mitgliederversammlung erscheinen im vorhandenen Beschlussbuch bzw. in den MV-Beschlüssen.

## 9. Exporte und Schnittstellen (X)

- **Buchhaltungs-Export:** Frei einstellbare Vorlagen. Je Beitragsart und Zahlungsart ordnet der Admin Konten und Buchungstexte zu. Vorgegeben: eine CSV-Vorlage mit einstellbaren Spalten und das DATEV-Buchungsstapel-Format als fertige Vorlage. Weitere Formate sind Vorlagen, keine Programmierung.
- **Bank-Profile:** Spaltenzuordnung und Datumsformat je Bank für den Kontoauszug-Import.
- **Datenübernahme:** Import-Assistent (siehe M1) für Mitglieder; für Mandate und offene Rechnungen eigene Importformate mit Prüfliste.
- **Keine Live-Schnittstellen:** Banken und Buchhaltungssoftware werden nicht direkt angesprochen.

## 10. Datenmodell (neue Tabellen, Auszug)

| Baustein | Tabelle | Wesentliche Felder |
|---|---|---|
| M0/M1 | `svmember_details` | member_id, Mitgliedsnummer, Anrede, Titel, Adresse, Telefon, Geburtsdatum, Eintritt, Austritt, Status, Bemerkung |
| M0 | `svfunctions`, `svmember_functions` | Funktion, Rechte je Baustein; Mitglied, Funktion, von, bis |
| M0 | `svfield_settings` | Feld, sichtbar, selbst änderbar, Freigabe nötig |
| M1 | `svmember_changes` | Änderungsvorschläge aus der Selbstpflege (Feld, alt, neu, Status) |
| M2 | `svmailing_lists`, `svmailing_list_members`, `svmailings` | Verteiler und Regeln, feste Mitglieder, Versandprotokoll |
| M3 | `svfee_groups`, `svmember_fees` | Gruppe, Betrag, Rhythmus; Zuordnung, Abweichung, Grund, von, bis |
| M3 | `svinvoices`, `svinvoice_items` | Nummer, Mitglied, Datum, Fälligkeit, Status, Zustellweg; Positionen |
| M3 | `svpayments`, `svpayment_allocations` | Zahlungseingang, Zuordnung zu Rechnungen |
| M3 | `svdunning_levels`, `svdunning_log` | Mahnstufen, Mahnungen je Rechnung |
| M4 | `svsepa_mandates`, `svsepa_batches`, `svsepa_items` | Mandate, Einzugsläufe, Einzelposten mit Ergebnis |
| X | `svexport_templates`, `svbank_profiles` | Vorlagen, Konten-Zuordnung, Bank-Spalten |
| M5 | `svassemblies`, `svassembly_attendance` | Versammlung, Anwesenheit (vor Ort/online) |
| alle | `svconfig` | Schalter und Einstellungen (vorhanden) |

Zu M5 und M2 werden vorhandene Tabellen mitgenutzt (Sitzungen/Protokolle, Mail-Warteschlange, Benachrichtigungen, Audit-Log).

## 11. Datenschutz und Sicherheit

- **Rechte:** Zugriffe nach der Rechte-Matrix, Serverseitig geprüft; sensible Bereiche (Beiträge, Bankdaten) nur für Funktionen mit ausdrücklichem Recht.
- **Bankdaten:** IBAN verschlüsselt; Anzeige gekürzt; vollständige Anzeige nur für berechtigte Funktionen und protokolliert.
- **Protokoll:** Das Audit-Log erfasst Schreibzugriffe mit Benutzer, Zeit und Skript. Aufbewahrung einstellbar.
- **Auskunft und Löschen:** Auskunft als Export je Mitglied; Löschen/Sperren nach Austritt mit einstellbaren Fristen (Aufbewahrung für Buchungsdaten bleibt unberührt).
- **Einwilligungen:** Für optionale Angaben (z. B. Geburtstag im Kalender) und für Rundmails über die vereinsnotwendigen Mitteilungen hinaus, mit Zeitstempel.
- **Verzeichnis der Verarbeitungstätigkeiten:** Mustertext in der Dokumentation.
- **Anmeldung:** Passwort-Regeln, Sperre nach Fehlversuchen, optional Zwei-Faktor.

## 12. Lieferstufen

| Stufe | Inhalt | Aufwand | Abnahme |
|---|---|---|---|
| 1 | M0 + M1 + M2 | 10–15 Tage | Mitglieder importiert, Login und Selbstpflege laufen, erster Rundbrief versendet |
| 2 | M3 (ohne Lastschrift), Exporte | 14–20 Tage | Rechnungslauf mit Mail und Sammel-PDF, Zahlungen aus Kontoauszug zugeordnet, Mahnlauf, Export für Buchhaltung |
| 3 | M4 | 5–8 Tage | Testdatei von der Bank akzeptiert, Einzug und Rücklastschrift durchgespielt |
| 4 | M5 | 3–5 Tage | Versammlung mit Teilnehmerliste und Protokoll durchgeführt |
| Querschnitt | Datenschutz, Tests, Demo, Dokumentation | 5–8 Tage | läuft stufenweise mit |

**Gesamt:** etwa 8 bis 12 Wochen für eine Person. Die Stufen 1 und 2 ersetzen eine Excel-Verwaltung bereits ohne Lastschrift. Die Lastschrift folgt, sobald Rechnungen und Zahlungen im Betrieb laufen.

## 13. Risiken

1. **Lastschrift-Datei:** Banken akzeptieren das Format unterschiedlich. Gegenmaßnahme: Formatversion einstellbar, Test mit der Bank des Pilot-Vereins vor dem ersten echten Einzug.
2. **Geldfehler:** Fehler in Beträgen oder Zuordnungen sind teuer. Gegenmaßnahme: Vorschau vor jedem Lauf, Bestätigung, Protokoll, automatisierte Tests für die Berechnungen (Anteile, Abweichungen, Teilzahlungen).
3. **Hosting:** PDF-Erstellung und Verschlüsselung brauchen passende PHP-Erweiterungen bzw. eine mitgelieferte Bibliothek. Vor dem Bau prüfen.
4. **Zustellbarkeit von Mails:** Hängt an der Server-Konfiguration des jeweiligen Vereins.
5. **Beitragsmodelle:** Familien-, Jugend- und Sonderregeln unterscheiden sich. Gegenmaßnahme: einstellbare Gruppen mit Abweichungen; echte Sonderfälle als Erweiterung.
6. **Datenübernahme:** Qualität der Altdaten (Dubletten, fehlende Felder) bestimmt den Aufwand beim Einstieg.

## 14. Offene Entscheidungen

1. Reihenfolge: Stufe 2 vor oder nach Stufe 3, falls der Pilot-Verein die Lastschrift früh braucht?
2. Soll der Baustein Spendenbescheinigung (Zuwendungsbestätigung) in einer späteren Stufe eingeplant werden?
3. Zwei-Faktor-Anmeldung: Standard oder optional?
4. PDF-Erstellung: mitgelieferte Bibliothek oder Einsatz nur dort, wo das Hosting es erlaubt?
5. Pilot-Verein: Zugang zu einer anonymisierten Beispieldatei (Mitglieder, Beitragsgruppen, Kontoauszug), damit die Importe an echten Daten geprüft werden.
