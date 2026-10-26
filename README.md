# KONA – Konferenz- und Antragstool (Sitzungsverwaltung)

KONA gehört zu einer Familie: **MISS** (Mitglieder-Informations- und Service-System), **KONA** (Sitzungen, Anträge, Beschlüsse) und **VERA** (Versammlungsorganisation). Alle drei zeigen in der Kopfzeile Logo, Vereinsname und Anwendungsname. Vereinsname und Logo stehen in der `config.php` (`ORG_NAME`, `ORG_LOGO`) und sind von MISS unabhängig, auch im Adapterbetrieb; ohne Eintrag erscheint das Standardzeichen (`logo.php`). Der Verzeichnisname auf dem Server ist frei wählbar; `STANDALONE_PATH` in der `config.php` muss dazu passen.

Eine webbasierte Komplettlösung für die professionelle Organisation, Durchführung und Dokumentation von Meetings, Sitzungen und Gremiensitzungen.

## Überblick

Die Sitzungsverwaltung unterstützt den gesamten Lebenszyklus von Meetings – von der Terminplanung über die Tagesordnung bis hin zum Protokoll und der Aufgabenverfolgung. Entwickelt für Organisationen, Vereine, Vorstände und Gremien, die ihre Sitzungen strukturiert und nachvollziehbar gestalten möchten.

## Hauptfunktionen

### 📅 Terminplanung

**Demokratische Terminfindung mit dem integrierten Doodle-Tool:**

- Erstellen Sie Umfragen mit mehreren Terminvorschlägen
- Teilnehmer stimmen mit Ja/Nein/Vielleicht ab
- Übersichtliche Auswertung zeigt den besten Termin
- Automatische Meeting-Erstellung nach Festlegung
- E-Mail-Benachrichtigungen:
  - Einladung zur Terminabstimmung
  - Bestätigung nach Festlegung
  - Erinnerung X Tage vor dem Termin
- **Für mich ausblenden:** Jeder Nutzer kann erledigte Umfragen individuell ausblenden; sie bleiben über "Ergebnisse" abrufbar und können jederzeit wieder eingeblendet werden

**Ideal für:** Gremiensitzungen, Vorstandssitzungen, Projektmeetings

### 🗳️ Meinungsbild-Tool

**Schnelle Umfragen und Stimmungsbilder erfassen:**

- **4 Zielgruppen:**
  - **Individual (Link):** Generieren Sie einen einzigartigen Link zum Teilen
  - **Meeting-Teilnehmer:** Umfrage nur für eingeladene Personen eines Meetings
  - **Öffentlich:** Jeder Besucher kann teilnehmen
  - **🆕 Externe Teilnehmer:** Token-basierter Zugang für Gäste ohne Login (z.B. Beiratsmitglieder, Partner)

- **13 vorgefertigte Antwort-Templates:**
  - Ja/Nein/Enthaltung
  - Passt-Skala (5-stufig)
  - Dafür/Dagegen
  - Gefällt mir
  - Numerische Skalen
  - Dringlichkeit, Wichtigkeit, Priorität
  - ... und viele mehr
  - Plus: Freies Template für eigene Optionen (bis zu 10)

- **Flexible Einstellungen:**
  - Mehrfachantworten erlauben/verbieten
  - Anonym oder mit Namensnennung
  - Individuelle Anonymität (User entscheidet selbst)
  - Laufzeit frei wählbar
  - Zwischenergebnisse nach X Tagen anzeigen
  - Automatische Löschung nach Ablauf

- **Grafische Auswertung:**
  - Balkendiagramme mit Prozentangaben
  - Einzelantworten mit Kommentaren (für Ersteller/Admins)
  - Export-Optionen

**Ideal für:** Meinungsbilder, Feature-Bewertungen, Stimmungsabfragen, Quick Polls

### 📋 Meeting-Management

**Vollständiger Sitzungszyklus:**

1. **Meeting anlegen:**
   - Titel, Datum, Ort (physisch oder virtuell)
   - Video-Link für Online-Meetings
   - Einladen von Teilnehmern
   - Sichtbarkeits-Einstellungen (🔒 Nur Eingeladene, 👔 Führungsteam, 🌐 Öffentlich)
   - Sichtbarkeitsstatus wird überall angezeigt (Meetings, Tagesordnung, Protokolle)
   - 🆕 **Meeting duplizieren:** Für regelmäßige Sitzungen - kopiert alle Einstellungen mit +7 Tage

2. **Tagesordnung erstellen:**
   - Strukturierte TOPs mit Nummerierung
   - 8 verschiedene Kategorien:
     - Information, Klärung, Diskussion
     - Aussprache, Antrag/Beschluss
     - Wahl, Bericht, Sonstiges
   - Prioritäten und Zeitschätzungen
   - Vertrauliche TOPs markieren
   - TOP-Gruppierungen
   - 🆕 **Wiedervorlage-System:** TOPs zu künftigen Sitzungen verschieben
     - Anträge werden automatisch mitverschoben
     - Dokumentation in beiden Sitzungen
     - Für Einladende, Protokollant und Sitzungsleiter

3. **Kollaborative Vorbereitung:**
   - Kommentare zu jedem TOP
   - Prioritäts-Bewertungen durch Teilnehmer
   - Zeitschätzungen anpassen
   - Automatische Berechnung der Gesamtdauer

4. **Meeting durchführen:**
   - Sitzungsleitung und Protokollführung festlegen
   - TOPs aktivieren und abarbeiten
   - 🆕 **Verbesserte Abstimmungen:**
     - Meinungsbilder mit freier Fragestellung
     - Beschlussabstimmungen mit Antrags-Integration
     - Offene oder geheime Abstimmung
     - Kompakte Ergebnisdarstellung
     - Automatische Protokoll-Formatierung
   - Echtzeit-Protokollierung

5. **Nachbereitung:**
   - Protokoll erstellen (öffentlich und vertraulich getrennt)
   - Änderungsanfragen verwalten
   - TODOs erfassen und zuweisen

### 📋 Antrags- und Beschluss-System

**Professionelles Workflow-Management für Anträge und Vorstandsbeschlüsse:**

**🆕 Vollständig integriertes System mit VTool-Kompatibilität**

- **Automatische Nummernvergabe:**
  - Format: Präfix + Datum + Laufnummer (z.B. A26051401)
  - Verschiedene Typen: Anträge (A), Vorstandsbeschlüsse (B), Beschlüsse (VS)
  - Bis zu 99 Einträge pro Tag
  
- **Antragsverwaltung:**
  - Übersicht aller offenen Anträge
  - Filter nach Status, Typ, Antragsteller
  - Volltextsuche in Nummer, Titel und Beschlusstext
  - Mobile-responsive Darstellung
  
- **Vollständiges Formular:**
  - **Stammdaten**: Ressort, Verantwortlicher, Verein/Stiftung, Sichtbarkeit (extern/intern/nicht öffentlich)
  - **Antrag**: Titel, Beschlusstext, Begründung, finanzielle Auswirkungen
  - **Unterlagen**: Bis zu 4 Datei-Uploads mit Beschreibungen
  - **Freigabe**: Konfigurierbare Freigabeprozesse und Betragsgrenzwerte
  - **Auswirkungen**: Finanzielle, personelle und sachliche Konsequenzen
  
- **Konfigurierbare Antragstypen:**
  - V (Vorlage), R (Ressort), B (Vorstandsbeschluss)
  - Individuelle Voting-Regeln pro Typ (Einstimmig, Mehrheit, etc.)
  - Admin-konfigurierbare Betragsgrenzwerte
  
- **Integration mit Meetings:**
  - Anträge direkt aus Tagesordnungspunkten erstellen
  - Kategorie "Antrag/Beschluss" beim TOP
  - Verknüpfung mit Sitzungsprotokollen
  - Automatische Überführung in Beschlussbuch
  
- **Abstimmungs-Integration:**
  - Direkte Abstimmung in Sitzungen
  - Status: ANGENOMMEN/ABGELEHNT/VERTAGT
  - Integration mit beschluesse-Tabelle
  - Automatische Protokollierung
  - **Abstimmungsdauer** (Admin-Einstellung `bart_B_abstimmung_tage`, Standard 7 Tage): Der Antrag steht ab Einstellung (B-Nummer, "In Abstimmung seit") die vollen N Tage zur Abstimmung; letzter Abstimmungstag ist bis 24:00 Uhr, die automatische Auswertung erfolgt am Tag danach
  - **Erinnerung:** Ca. 24 Stunden vor Fristende erhalten alle Abstimmberechtigten, die noch nicht abgestimmt haben, automatisch eine E-Mail (nicht abschaltbar, einmal pro Antrag und Mitglied)
  - Abstimmberechtigte sehen in der Antragsliste den Button "Ansehen und Abstimmen"; abgegebene Voten können nicht mehr geändert werden
  - E-Mail "Neuer Antrag" wird beim ersten Speichern mit Titel verschickt, danach "Antrag geändert"
  
- **Beschlussbuch:**
  - Alle angenommenen Beschlüsse (VS-Präfix)
  - Suchfunktion mit Highlighting
  - Dark Mode Support
  - URL-Erkennung in Texten
  
- **Berechtigungssystem:**
  - Rollenbasiert nach VTool-Standard (aktiv-Level)
  - Interne vs. externe Sichtbarkeit
  - Admin-Funktionen (Level 19+)
  
- **Workflow-Features:**
  - Antrag duplizieren
  - Wiedervorlage zu anderen Sitzungen
  - Wartezeit-Verwaltung (Expedite)
  - Versionshistorie

**Ideal für:** Vorstandsbeschlüsse, Ressortan­träge, Finanzfreigaben, Vereinsbeschlüsse

### 📅 Kalender

- **Terminübersicht:** Nächste 90 Tage, gruppiert nach Monat; Sitzungen werden automatisch übernommen
- **Eigene Termine:** Typen Sitzung, Treffen, Abwesenheit, Frist, Sonstiges; wiederkehrend (wöchentlich, 14-tägig, monatlich, jährlich)
- **Sichtbarkeit:** Alle, bestimmte Rollen (nur Admins) oder ausgewählte Teilnehmer
- **Abstimmungsfristen:** Offene Abstimmungen, bei denen die eigene Stimme noch fehlt, erscheinen automatisch als "Frist"
- **Rechte:** Mitglieder (Rolle `mitglied`) lesen nur; alle anderen Rollen legen Termine an, bearbeiten/löschen darf der Ersteller oder ein Admin
- Details: [docs/KALENDER.md](docs/KALENDER.md)

### ✅ Aufgaben-Management (TODOs)

**Nachverfolgung von Beschlüssen und Aufgaben:**

- TODOs aus Meetings oder unabhängig erstellen
- Zuweisen an Mitglieder
- Fälligkeitsdaten setzen
- Status-Verfolgung (offen, in Bearbeitung, erledigt)
- Private TODOs (nur für Ersteller sichtbar)
- Automatische Benachrichtigungen
- Verknüpfung mit Meeting-Protokollen

### 📁 Dokumentenverwaltung

**Zentrale Ablage für Vereinsdokumente:**

- **Upload-Funktionen:**
  - Unterstützte Formate: PDF, DOC, DOCX, XLS, XLSX, RTF, TXT, Bilder
  - 🆕 **Externe Links:** Verlinke auf Cloud-Dokumente (SharePoint, Google Drive, etc.) statt Upload
  - Vermeidet doppelte Datenhaltung
  - Metadaten: Titel, Beschreibung, Version, Stichworte

- **Kategorisierung:**
  - Satzung, Ordnungen, Richtlinien
  - Formulare, MV-Unterlagen
  - Dokumentationen, Urteile, Medien
  - Automatische Kategorisierung

- **Zugriffskontrolle:**
  - Rollenbasierte Berechtigungen (Level 0-19)
  - Dokumente für spezifische Gruppen freigeben
  - Admin-only Bearbeitung und Upload

- **Suche & Filter:**
  - Volltextsuche in Titel, Beschreibung und Stichworten
  - Filter nach Kategorie und Status
  - Sortierung nach Datum, Titel, Kategorie

- **Features:**
  - Kurz-URLs für wichtige Dokumente
  - Download-Tracking und Statistiken
  - Versionierung
  - Status-Verwaltung (aktiv, archiviert, versteckt, veraltet)
  - Responsive Design (Desktop & Mobile)

**Ideal für:** Satzungen, Protokolle, Formulare, Handbücher, Berichte

### 📝 Protokoll-System

**Professionelle Dokumentation:**

- **Öffentliches Protokoll:** Für allgemeine Verbreitung
- **Vertrauliches Protokoll:** Nur für berechtigte Personen
- Getrennte Speicherung und Zugriffsrechte
- **Rollenbasierte Protokoll-Filterung:**
  - Vorstand/GF/Assistenz: Sehen alle Protokolle
  - Führungsteam: Sehen Protokolle von eigenen Meetings + öffentliche + Führungsteam-Meetings
  - Mitglieder: Sehen nur öffentliche Protokolle
- Markdown-Unterstützung für Formatierung
- Änderungsanfragen von Teilnehmern
- Versionierung

### 👥 Mitglieder-Verwaltung

**Rollen und Berechtigungen:**

- **Vorstand / Geschäftsführung:** Volle Admin-Rechte, alle Protokolle und Meetings sichtbar
- **Assistenz:** Meetings anlegen und verwalten, alle Protokolle sichtbar
- **Führungsteam:** Erweiterte Rechte, Textbearbeitung, sehen eigene Meetings + öffentliche + Führungsteam-Meetings
- **Mitglied:** Basis-Teilnahmerechte, sehen nur öffentliche Protokolle

**Features:**
- Mitgliedsnummern
- Aktiv/Inaktiv-Status
- Vertraulichkeits-Kennzeichnung
- E-Mail-Benachrichtigungen

### 🏖️ Abwesenheits-Verwaltung

**Transparente Vertretungsregelungen:**

- **Abwesenheiten eintragen:**
  - Zeitraum angeben (von - bis)
  - Vertretung auswählen
  - Optional: Grund angeben

- **Übersichtliche Darstellung:**
  - Desktop: Tabellenansicht mit allen Details
  - Smartphone: Card-Layout für bessere Lesbarkeit
  - Farbliche Kennzeichnung (aktuell, zukünftig, vergangen)
  - 🆕 **Intelligente Limitierung:** Zeigt die 4 nächsten Abwesenheiten
  - 🆕 **Kollapsible Ansicht:** "weitere..." Link bei mehr als 4 Einträgen
  - 🆕 **Benachrichtigungen:** Gelbe Infobox zeigt relevante Abwesenheiten kompakt

- **Funktionen:**
  - Eigene Abwesenheiten verwalten
  - Aktuelle Vertretungen einsehen
  - Löschen von zukünftigen Abwesenheiten
  - Automatische Filterung nach Zeitraum
  - Responsive Layout für mobile Geräte

**Ideal für:** Urlaubsplanung, Vertretungsregelungen, Abwesenheitskalender

### 📝 Kollaborative Texte

**Gemeinsam an Texten arbeiten während Sitzungen:**

- **Zwei Modi:**
  - **Meeting-Modus:** Alle Sitzungsteilnehmer können mitarbeiten
  - **Allgemein-Modus:** Vorstand, GF, Assistenz und Führungsteam können allgemeine Texte erstellen

- **Absatz-basiertes Editieren:**
  - Text ist in Absätze unterteilt
  - Jeder Absatz kann einzeln bearbeitet werden
  - Automatisches Lock-System verhindert Konflikte
  - Während ein Teilnehmer einen Absatz bearbeitet, sehen andere "🔒 Name bearbeitet"
  - **Schutz vor Datenverlust:** Nur ein Absatz kann gleichzeitig bearbeitet werden

- **Live-Synchronisation:**
  - Änderungen werden alle 1-2 Sekunden aktualisiert
  - Online-Status zeigt wer gerade aktiv ist
  - Änderungen anderer Teilnehmer erscheinen automatisch

- **Funktionen:**
  - Neue Absätze hinzufügen
  - Absätze löschen (mindestens einer muss bleiben)
  - Text anzeigen und in Zwischenablage kopieren (mit einem Klick)
  - Versions-Snapshots erstellen
  - Absätze neu anordnen

- **Finalisierung:**
  - Nur Initiator kann Text finalisieren
  - Nach Finalisierung: kein Editieren mehr möglich
  - Finaler Text für alle Teilnehmer verfügbar
  - Mit einem Klick kopierbar

**Ideal für:** Pressemeldungen, offizielle Briefe, gemeinsame Stellungnahmen, heikle Formulierungen

**DSGVO-konform:** Alle Daten bleiben auf Ihrem Server – kein Google Docs notwendig!

### 💬 Intelligentes Kommentar-System

**Drei-Phasen-Kommentierung:**

1. **Vorbereitung:**
   - Diskussionsbeiträge zu TOPs
   - Prioritäts-Bewertungen
   - Zeitschätzungen

2. **Live während der Sitzung:**
   - Echtzeit-Kommentare
   - Auto-Scroll zu neuesten Einträgen
   - Dynamisch wachsende Textfelder

3. **Nachträglich (nach Sitzungsende):**
   - Anmerkungen zum Protokollentwurf
   - Für alle Beteiligten (inkl. Protokollant und Sitzungsleitung)
   - Transparente Hinweise: "Kommentare werden nach Genehmigung verworfen"
   - Separate Eingabefelder für verschiedene Rollen

**Features:**
- **URL-Erkennung:** Links in Kommentaren und Protokollen werden automatisch klickbar
- **Intelligente Alerts:** Warnungen für Bild-/PDF-Links auf Mobilgeräten
- **Auto-Resize:** Textfelder passen sich dynamisch an den Inhalt an
- **Auto-Scroll:** Neue Live-Kommentare werden automatisch sichtbar

### 🔧 Admin-Funktionen

**Backup & Restore:**
- Regelmäßige Datenbank-Sicherungen erstellen
- Passwortgeschützt (System-Admin-Passwort)
- Backup-Dateien herunterladen
- Datenbank aus Backup wiederherstellen
- Migration-Tools für Schema-Updates

**Demo-Daten:**
- Demo-Daten aus erfundenen Daten erzeugen (`tools/demo_build.php`, alle Funktionen, alle Rollen)
- Demo-Daten exportieren und importieren (ohne Mailadressen, IPs, Tokens, Push-Abos und Logs)
- Demo-Daten analysieren (Feature-Matrix, Datenschutz-Prüfung)
- Tägliche Datumsfortschreibung im Demo-Modus (`tools/update_demo_dates.php`)
- Tabellen-Migration durchführen

### 📱 Mobile-Optimierung

**Responsive Design für alle Geräte:**

- **Terminplanung:** Flexible Grid-Layouts auf Smartphones
- **Abwesenheiten:** Card-Layout statt breiter Tabellen
- **Navigation:** Touch-optimierte Bedienung
- **Formulare:** Angepasste Eingabefelder für mobile Keyboards
- **Alerts:** Spezielle Warnungen für Bild-/PDF-Links

### 🔒 Sicherheit und Datenschutz

- **Zugriffskontrolle:** Rollenbasierte Berechtigungen
- **Vertraulichkeit:** Separate Speicherung sensibler Daten
- **Audit-Log:** Alle schreibenden Datenbankzugriffe (INSERT/UPDATE/DELETE/REPLACE) werden automatisch samt SQL und Parameterwerten in `svaudit_log` protokolliert; Einsicht für Admins im Admin-Tab "Datenbank-Protokoll (Audit-Log)" (siehe `docs/audit-logging.md`)
- **Anonymität:** Optionen für anonyme Teilnahme bei Umfragen
- **Session-Management:** Automatischer Logout bei Inaktivität

## Benutzeroberfläche

### Dashboard-Übersicht

Nach dem Login sehen Sie:

- **Aktuelle Meetings:** Anstehende und laufende Sitzungen
- **Meine TODOs:** Übersicht zugewiesener Aufgaben
- **Benachrichtigungen:** Kachel mit persönlicher Begrüßung ("Hallo <Vorname>!") und offenen Hinweisen (u.a. Einladungen, Abstimmungen, Finanzfreigaben); offene VTool-Zahlungsfreigaben werden mit Link ins VTool angezeigt, sofern die VTool-Tabelle `freigabe` vorhanden ist

### Haupt-Tabs

1. **📅 Termine:** Terminplanung mit Umfragen (Doodle-Style)
2. **🤝 Meetings:** Alle Sitzungen verwalten
3. **📋 Tagesordnung:** Tagesordnungspunkte bearbeiten (nur bei ausgewählter Sitzung)
4. **📝 Gemeinsame Texte:** Kollaboratives Editieren während Sitzungen (nur bei ausgewählter Sitzung)
5. **📄 Protokolle:** Protokolle einsehen und verwalten
6. **✅ Meine TODOs:** Aufgaben-Übersicht
7. **🏖️ Vertretungen:** Abwesenheits- und Vertretungsverwaltung
8. **🗳️ Meinungsbild:** Umfragen und Abstimmungen
9. **📅 Kalender:** Terminübersicht der nächsten 90 Tage (Sitzungen, eigene Termine, Abstimmungsfristen)
10. **📁 Dokumente:** Zentrale Dokumentenverwaltung
11. **📋 Anträge:** Antrags- und Beschlussverwaltung (🆕 Vollständiges Workflow-System)
12. **📖 Beschlussbuch:** Alle beschlossenen Anträge (VS-Nummer)
13. **⚙️ Admin:** Verwaltung und Systemeinstellungen (nur für Admins)

## Typische Anwendungsszenarien

### Szenario 1: Vorstandssitzung planen

1. **Termin finden:**
   - Tab "Termine" → "Neue Terminabstimmung"
   - 3-4 Terminvorschläge eintragen
   - Teilnehmer auswählen
   - E-Mail-Einladung versenden

2. **Bester Termin festlegen:**
   - Nach Ablauf der Frist: Auswertung ansehen
   - Termin mit den meisten "Ja"-Stimmen wählen
   - "Festlegen" → Meeting wird automatisch erstellt
   - Bestätigungs-E-Mail an alle

3. **Tagesordnung vorbereiten:**
   - Tab "Meetings" → Meeting öffnen
   - TOPs hinzufügen (z.B. Finanzbericht, Personalplanung)
   - Prioritäten setzen

4. **Meeting durchführen:**
   - "Sitzung starten"
   - TOPs nacheinander aktivieren
   - Protokoll schreiben
   - Abstimmungen dokumentieren

5. **Nachbereitung:**
   - Protokoll finalisieren
   - TODOs an Verantwortliche zuweisen
   - "Meeting beenden"

### Szenario 2: Schnelles Meinungsbild einholen

1. **Umfrage erstellen:**
   - Tab "Meinungsbild" → "Neues Meinungsbild"
   - Frage formulieren: "Wie wichtig ist euch Feature X?"
   - Template wählen: "Wichtigkeit" (5-stufig)
   - Zielgruppe: "Individuell (Link)"
   - Laufzeit: 7 Tage

2. **Link teilen:**
   - Generierten Link kopieren
   - Per E-Mail, Chat oder Social Media teilen

3. **Ergebnisse auswerten:**
   - Automatische Balkendiagramme
   - Kommentare lesen
   - Entscheidung treffen

### Szenario 3: Vertrauliche Gremiensitzung

1. **Meeting mit Vertraulichkeit:**
   - Meeting anlegen mit `visibility_type = "invited_only"`
   - Nur bestimmte Mitglieder einladen
   - Vertrauliche TOPs markieren

2. **Getrennte Protokolle:**
   - Öffentliches Protokoll: Allgemeine Informationen
   - Vertrauliches Protokoll: Sensible Themen (nur für Berechtigte)

3. **Zugriffskontrolle:**
   - Nur eingeladene Teilnehmer sehen das Meeting
   - Vertrauliche TOPs nur für berechtigte Rollen sichtbar

## Vorteile

✅ **Zeitersparnis:** Automatisierte Prozesse reduzieren manuellen Aufwand

✅ **Transparenz:** Alle Informationen zentral und nachvollziehbar

✅ **Demokratie:** Faire Terminfindung und Meinungsabfragen

✅ **Struktur:** Standardisierte Abläufe für professionelle Meetings

✅ **Nachvollziehbarkeit:** Vollständige Dokumentation aller Entscheidungen

✅ **Flexibilität:** Anpassbar an verschiedene Organisationsformen

✅ **Zugänglichkeit:** Webbasiert, von überall erreichbar

✅ **Mobile-First:** Optimiert für Desktop und Smartphone

✅ **Benutzerfreundlichkeit:** Intuitive Bedienung mit intelligenten Features (Auto-Resize, URL-Erkennung)

✅ **Datensicherheit:** Backup & Restore-Funktionen inklusive

## Systemanforderungen (für Anwender)

- Moderner Webbrowser (Chrome, Firefox, Safari, Edge)
- Internetverbindung
- Optional: E-Mail-Konto für Benachrichtigungen

## Installation

Siehe [docs/INSTALL.md](docs/INSTALL.md) für detaillierte Installationsanweisungen.

## Support und Dokumentation

- **Installationsanleitung:** [docs/INSTALL.md](docs/INSTALL.md)
- **Entwickler-Dokumentation:** [DEVELOPER.md](DEVELOPER.md)
- **Meinungsbild-Tool (Details):** [docs/OPINION_TOOL_README.md](docs/OPINION_TOOL_README.md)
- **Dokumentenverwaltung (Details):** [docs/DOCUMENTS_README.md](docs/DOCUMENTS_README.md)
- **Kalender:** [docs/KALENDER.md](docs/KALENDER.md)
- **Audit-Log / Protokollierung:** [docs/audit-logging.md](docs/audit-logging.md)

## Lizenz

Internes Tool - Alle Rechte vorbehalten.

## Entwickelt für

Organisationen, Vereine, Vorstände, Gremien und Teams, die ihre Meetings professionell organisieren und dokumentieren möchten.

---

**Viel Erfolg mit KONA!**
