# VTool-Analyse: Online-Antrags- und Beschlusssystem (Aktualisiert)

## Executive Summary

Basierend auf der vollständigen Code-Analyse und Ihrem Feedback erstellen wir ein modernisiertes Antrags- und Beschlusssystem für die Sitzungsverwaltung.

**Scope:**
- ✅ Antragstellung mit kollaborativer Bearbeitung
- ✅ Regelbasierte Abstimmungsverfahren  
- ✅ Durchsuchbare Beschlussdatenbank
- ✅ Migration aller Altdaten
- ❌ Zahlungsfreigaben (bleibt im VTool)
- 📋 Forum-Integration (Discourse, für später)
- 📋 Sitzungs-Integration (spezieller TOP mit Link-Liste, für später)

---

## 1. VTOOL-ARCHITEKTUR (Ist-Zustand)

### 1.1 Dateistruktur

**Hauptsteuerung:** `vtool.php` (850+ Zeilen)
- Routing über `$steuer` Variable
- Include verschiedener Module je nach Funktion
- Monolithische Struktur mit inline HTML

**Module nach Funktionsbereichen:**

```
Anträge & Beschlüsse (steuer 20-39):
├── ilbeantraege.php        # Anträge bearbeiten/anzeigen (937 Zeilen)
├── ilspantraege.php        # Anträge speichern (296 Zeilen)
├── ibeschl_vtool.php       # Beschluss-Anzeige-Funktion (85 Zeilen)
└── ilvbeschluesse.php      # Beschlussdatenbank (247 Zeilen)

Zahlungsfreigaben (steuer 0-10):
└── ilkasse.php + ilspfreigabe.php

Termine (steuer 60-69):
└── ilfindtermin.php + ilsptermin.php + ilterminplanung.php

Tagesordnung (steuer 70-79):
└── iltagesordnung.php

Weitere:
├── ilmeinungsbild.php      # Meinungsbilder (steuer 220-229)
├── ilmsuche.php            # Mitgliedersuche (steuer 105)
├── ildokumente.php         # Dokumente (steuer 101)
└── ilmvbeschluesse.php     # MV-Beschlüsse (steuer 15)

Wartung:
├── iwartungvtool.php       # Cronjob (täglich 3 Uhr)
└── ifunktionen_vtool.php   # Hilfsfunktionen
```

### 1.2 Routing-Logik

**Steuerung über $steuer:**
```php
20 = Antragsformular leer
21 = Antrag editieren & speichern
22 = Antrag zum Editieren laden
23 = Neuen Antrag erstellen
24 = Antrag löschen
25 = Abstimmungen anzeigen
26 = Antrag zur Abstimmung anzeigen
27 = Votum speichern
28 = Beschluss kompakt zeigen
29 = Antrag zur Abstimmung stellen

34 = Admin: Neuen Beschluss eintragen (Eingabe)
35 = Admin: Beschluss-Datensatz anlegen
36 = Admin: Beschluss editieren (Auswahl)
37 = Admin: Beschluss editieren (Formular)
39 = Admin: Beschluss speichern
```

**Zusätzliche Steuerung über Submit-Buttons:**
```php
if ($weiter == "So speichern") $steuer = 21;
if ($weiter == "So absenden") $steuer = 23;
if (substr($weiter,0,14) == "unwiderruflich") $steuer = 24;
if (strpos($weiter,"stellen")) $steuer = 29;
```

### 1.3 Cronjob-Funktionen (iwartungvtool.php)

**Stündlich (15 min nach jeder Stunde):**
- E-Mail-Benachrichtigungen nach Nutzer-Präferenz
- Mögliche Frequenzen: nie, alle 5 Tage, alle 3 Tage, täglich, täglich bei ToDo, stündlich

**Täglich um 3 Uhr:**
- Abstimmungen durch Zeitablauf beenden
- Ergebnis automatisch auswerten und Beschluss erzeugen
- Leere Antragsformulare löschen

**Monatserster um 0 Uhr:**
- Protokolldatei archivieren (`protokoll` → `JJMMprotokoll`)
- Neue leere Protokolldatei anlegen

**Täglich um 4 Uhr:**
- IQPlus-Daten importieren (extern)

---

## 2. MODERNISIERTE ARCHITEKTUR

### 2.1 Modulare Struktur (wie Sitzungsverwaltung)

```
proposals/
├── process_proposals.php       # POST-Handler
├── tab_proposals_list.php      # Liste Anträge im Editiermodus
├── tab_proposals_vote.php      # Abstimmungen
├── tab_proposals_display.php   # Einzelantrag anzeigen
├── tab_resolutions.php         # Beschlussdatenbank
├── proposals_functions.php     # Business-Logik
├── proposals_display.php       # Display-Funktionen
├── proposals_mail.php          # E-Mail-Benachrichtigungen
├── proposals_cron.php          # Wartungsfunktionen
└── config_proposals.php        # Konfiguration

migrations/
└── migrate_vtool_data.php      # Import aus VTool

admin/
├── tab_proposals_admin.php     # Admin-Funktionen
└── process_proposals_admin.php # Admin POST-Handler
```

### 2.2 Action-basiertes Routing (statt $steuer)

**URL-Struktur:**
```
tab_proposals_list.php          # Liste aller Anträge
tab_proposals_list.php?id=123   # Antrag 123 anzeigen/editieren
tab_proposals_vote.php          # Abstimmungen
tab_proposals_vote.php?id=456   # Abstimmung 456
tab_resolutions.php?search=X    # Beschlüsse suchen
```

**POST Actions in process_proposals.php:**
```php
switch ($_POST['action']) {
    case 'create':   create_proposal();
    case 'save':     save_proposal();
    case 'delete':   delete_proposal();
    case 'finalize': finalize_proposal();  // → zur Abstimmung
    case 'vote':     save_vote();
    case 'withdraw': withdraw_proposal();
    case 'expedite': request_expedite();   // Wartezeitverkürzung
}
```

---

## 3. DATENBANK-MIGRATION

### 3.1 Alte Struktur → Neue Struktur

**antraege** (VTool) → **proposals** (neu)

**Mapping:**
```sql
-- Felder 1:1 übernehmen
antrnr          → proposal_number
antrst          → submitter_id
ressort1/2      → department_id / co_department_id
verein          → organization_type ('V'→'association', 'S'→'foundation')
int_ext         → visibility ('e'→'public', 'n'→'leadership', 'i'→'board', 'v'→'draft')
titel           → title
beschluss       → description
begr            → justification
fin             → financial_amount
fintext         → financial_description
pers            → personnel_impact
sach            → material_impact
verant          → responsible_person
thread          → forum_thread_id (veraltet, kann NULL)
praesenz        → meeting_id
lzugriff        → last_modified

-- Status aus Prefix ableiten
SUBSTR(antrnr,1,1):
  'A' → status = 'editing'
  'B' → status = 'voting'
  'V' → status = 'approved'
  'X' → status = 'rejected'
  'Z' → status = 'withdrawn'

-- Beschlussart
bart            → decision_type ('V'→'single', 'R'→'department', 'B'→'board')

-- Dateien normalisieren (file1-4 → proposal_attachments)
file1, filetext1 → INSERT INTO proposal_attachments
file2, filetext2 → INSERT INTO proposal_attachments
file3, filetext3 → INSERT INTO proposal_attachments
file4, filetext4 → INSERT INTO proposal_attachments

-- Abstimmungen normalisieren (VName1-6, Votum1-6 → proposal_votes)
VName1, Votum1, VDat1, VBegr1, VProt1 → INSERT INTO proposal_votes
VName2, Votum2, VDat2, VBegr2, VProt2 → INSERT INTO proposal_votes
...

-- Freigabeberechtigte (verf1, verf2 → proposal_approvers)
verf1 → INSERT INTO proposal_approvers (role='primary')
verf2 → INSERT INTO proposal_approvers (role='secondary')

-- Wartezeitverkürzung
verkb, verk1, verk2 → proposal_expedite_requests

-- Datumsfelder
SUBSTR(antrnr,1,7) → submitted_at  # A210315 → 2021-03-15
(nur wenn 'B' oder 'V') → finalized_at
(nur wenn 'V' oder 'X') → decided_at
```

**beschluesse** (VTool) → Nur noch in **proposals** (Status='approved')

Die Tabelle `beschluesse` enthält redundante Daten, die bereits in `antraege` stehen.
Bei der Migration:
1. Prüfen ob Antrag in `antraege` existiert → dann überspringen
2. Wenn nur in `beschluesse` → als neuen Eintrag in `proposals` anlegen mit status='approved'

### 3.2 Migrationsskript

```php
// migrate_vtool_data.php

// Phase 1: Proposals aus antraege
$old_proposals = mysqli_query($link_vtool, "SELECT * FROM antraege");
while ($old = mysqli_fetch_assoc($old_proposals)) {
    // Status ableiten
    $status = match(substr($old['antrnr'], 0, 1)) {
        'A' => 'editing',
        'B' => 'voting',
        'V' => 'approved',
        'X' => 'rejected',
        'Z' => 'withdrawn',
        default => 'draft'
    };
    
    // Grunddaten
    $new_id = insert_proposal($pdo_new, [
        'proposal_number' => $old['antrnr'],
        'status' => $status,
        'submitter_id' => $old['antrst'],
        'title' => $old['titel'],
        // ... weitere Felder
    ]);
    
    // Dateien migrieren
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($old["file$i"])) {
            insert_attachment($pdo_new, $new_id, [
                'file_path' => $old["file$i"],
                'description' => $old["filetext$i"]
            ]);
        }
    }
    
    // Abstimmungen migrieren
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($old["VName$i"])) {
            insert_vote($pdo_new, $new_id, [
                'voter_id' => $old["VName$i"],
                'vote_type' => map_vote_type($old["Votum$i"]),
                'voted_at' => $old["VDat$i"],
                'internal_comment' => $old["VBegr$i"],
                'protocol_note' => $old["VProt$i"]
            ]);
        }
    }
}

// Phase 2: Beschlüsse aus beschluesse, die nicht in antraege sind
$old_resolutions = mysqli_query($link_vtool, "
    SELECT b.* FROM beschluesse b
    LEFT JOIN antraege a ON b.antrnr = a.antrnr
    WHERE a.antrnr IS NULL
");
// Diese als approved proposals anlegen
```

### 3.3 Datei-Migration

**Scans-Verzeichnis:**
```bash
# Alte Struktur: vorstand/Scans/A210315f1Rechnung.pdf
# Neue Struktur: Sitzungsverwaltung/proposals/attachments/123/rechnung.pdf

# Migration:
rsync -av /pfad/zu/vtool/Scans/ /pfad/zu/sitzungsverwaltung/proposals/attachments/
# Dann Pfade in proposal_attachments anpassen
```

---

## 4. KONFIGURATION (angepasst)

### 4.1 config_proposals.php

```php
<?php
// Betragsgrenzen (in EUR)
define('PROPOSAL_SINGLE_APPROVAL_MAX', 600);
define('PROPOSAL_SINGLE_APPROVAL_MONTHLY_MAX', 2000);
define('PROPOSAL_DEPARTMENT_APPROVAL_MAX', 3000);
define('PROPOSAL_BOARD_APPROVAL_MIN', 3000);

// Fristen (in Tagen)
define('PROPOSAL_WAITING_PERIOD_DAYS', 7);
define('PROPOSAL_VOTING_PERIOD_DAYS', 7);
define('PROPOSAL_CONSIDERATION_MAX_DAYS', 7);

// Abstimmungsregeln
define('PROPOSAL_VOTING_MODE', 'simple_majority'); // mehr Ja als Nein
define('PROPOSAL_QUORUM_ABSENT_MAX', 2);  // Max. 2 VMs abwesend

// Nummerierung
define('PROPOSAL_NUMBER_FORMAT', '{PREFIX}{YY}{MM}{DD}{COUNTER}');
define('PROPOSAL_PREFIX_DRAFT', 'A');
define('PROPOSAL_PREFIX_VOTING', 'B');
define('PROPOSAL_PREFIX_APPROVED', 'V');
define('PROPOSAL_PREFIX_REJECTED', 'X');
define('PROPOSAL_PREFIX_WITHDRAWN', 'Z');
define('PROPOSAL_PREFIX_SPECIAL', 'VS');  // Sonderbeschlüsse

// E-Mail-Einstellungen
define('PROPOSAL_MAIL_FROM', 'vorstand@mensa.de');
define('PROPOSAL_MAIL_REPLY_TO', 'vorstand@mensa.de');

// URLs
define('PROPOSAL_BASE_URL', 'https://aktive.mensa.de/Sitzungsverwaltung/');

// Features
define('PROPOSAL_ENABLE_FORUM_LINK', false);  // Discourse später
define('PROPOSAL_ENABLE_MEETING_LINK', true); // Sitzungs-Verknüpfung
define('PROPOSAL_ENABLE_EXPEDITE', true);     // Wartezeitverkürzung
define('PROPOSAL_ENABLE_ATTACHMENTS', true);

// Multi-Org (für Sie: immer false)
define('PROPOSAL_MULTI_ORG', false);
define('PROPOSAL_DEFAULT_ORG_ID', 1);
```

### 4.2 Rollen-Mapping

**VTool → Sitzungsverwaltung:**
```php
// Mapping aus berechtigte.aktiv
$role_mapping = [
    0  => null,              // Kein Zugang
    1  => 'special',         // Sonderrechte
    2  => 'treasury',        // Kasse
    3  => 'auditor',         // Finanzprüfer
    8  => 'special_approval', // JT etc.
    12 => 'project_lead',    // Projektleiter
    14 => 'deputy_lead',     // RL-Stellvertreter
    15 => 'department_lead', // Ressortleiter
    17 => 'finance_committee', // FinKo
    18 => 'management',      // Geschäftsführung
    19 => 'board',           // Vorstand
];

// Mapping aus berechtigte.Funktion
$function_mapping = [
    'FVo' => 'finance_board',      // Finanzvorstand
    'FVv' => 'finance_board_deputy', // Finanz-Stellvertreter
    'GF'  => 'management',
    'Vo'  => 'board',
    'Ka'  => 'treasury',
];

// Berechtigungen
$permissions = [
    'auditor' => [
        'proposals.view_all',
        'proposals.view_internal',
    ],
    'project_lead' => [
        'proposals.create',
        'proposals.edit_own',
        'proposals.finalize_own',
        'proposals.view_leadership',
    ],
    'department_lead' => [
        'proposals.create',
        'proposals.edit_any',  // in eigenem Ressort
        'proposals.finalize_any',
        'proposals.approve_single',  // Verfügungen
        'proposals.approve_department',  // Ressortbeschlüsse
        'proposals.expedite_waiting',
        'proposals.view_internal',
    ],
    'board' => [
        'proposals.create',
        'proposals.edit_any',
        'proposals.finalize_any',
        'proposals.delete_any',
        'proposals.vote',
        'proposals.request_board_decision',  // wichtig-Flag
        'proposals.expedite_waiting',
        'proposals.admin_edit',
        'proposals.view_internal',
    ],
    'finance_board' => [
        // Wie board, plus:
        'proposals.approve_department',  // 2. Zustimmung
        'proposals.approve_simplified',  // zufin-Flag
    ],
    'management' => [
        // Wie board, plus:
        'proposals.admin_functions',
    ],
];
```

---

## 5. IMPLEMENTIERUNGSPLAN (angepasst)

### Phase 0: Vorbereitung (Woche 0)

**Entscheidungen & Setup:**
- ✅ Multi-Org: NEIN
- ✅ Alte Daten: JA, vollständig migrieren
- ✅ Zahlungen: NICHT in Scope
- ✅ Forum: Später (Discourse)
- ✅ Meeting-Link: Später (spezieller TOP)

**Technisch:**
- Branch erstellen: `feature/proposals-module`
- Verzeichnisstruktur anlegen
- Migrations-Strategie festlegen

### Phase 1: Datenbank & Migration (Woche 1-2)

**Ziel:** Alte Daten sicher übernehmen

1. **Neue Tabellen erstellen**
   ```sql
   proposals
   proposal_attachments
   proposal_votes
   proposal_approvers
   proposal_expedite_requests
   proposal_changes
   proposal_comments
   departments (aus ressortliste)
   decision_rules
   ```

2. **Migrationsskript entwickeln**
   - Testlauf auf Kopie der VTool-DB
   - Datenintegrität prüfen
   - Rollback-Strategie

3. **Test-Migration**
   - Alle 3 Tabellen (antraege, beschluesse, zahlungen falls nötig)
   - Validierung: Anzahl Datensätze, Stichproben

**Deliverable:** Vollständig migrierte Daten in neuem Schema

### Phase 2: Core-Funktionen (Woche 3)

1. **proposals_functions.php**
   ```php
   create_proposal()
   update_proposal()
   get_proposal()
   list_proposals($filters)
   generate_proposal_number($prefix)
   calculate_decision_type($amount)
   check_waiting_period($proposal_id)
   check_monthly_limit($submitter_id, $amount)
   ```

2. **Berechtigungssystem**
   ```php
   check_proposal_permission($user, $action, $proposal)
   get_user_role($user_id)
   can_edit_proposal($user, $proposal)
   can_approve_proposal($user, $proposal)
   ```

3. **Einfaches UI**
   - `tab_proposals_list.php`: Liste (nur Anzeige)
   - `tab_proposals_display.php`: Einzelantrag (nur Anzeige)
   - Keine Bearbeitung/Abstimmung noch

**Deliverable:** Beschlüsse anzeigen funktioniert

### Phase 3: Editier-Workflow (Woche 4)

1. **Anträge erstellen/editieren**
   - Formular in `tab_proposals_list.php`
   - `process_proposals.php`: create/save Actions
   - Validierung (Pflichtfelder, Ressort, etc.)

2. **Kollaboratives Editieren**
   - Änderungshistorie (`proposal_changes`)
   - Kommentare (`proposal_comments`)
   - E-Mail bei Änderungen (`proposals_mail.php`)

3. **Finalisierung**
   - "Verbindlich einstellen" Button
   - Wartezeitberechnung
   - Freigabeberechtigte ermitteln (verf1/verf2)
   - Status A → B (oder direkt → V bei Verfügung)

**Deliverable:** Vollständiger Editier-Workflow

### Phase 4: Abstimmung (Woche 5)

1. **Abstimmungs-UI**
   - `tab_proposals_vote.php`
   - Liste zur Abstimmung stehender Anträge
   - Abstimmungsformular (Ja/Nein/Enthaltung/Rückverweis/Bedenkzeit)

2. **Abstimmungs-Logik**
   - Votum speichern
   - Abwesenheitsprüfung
   - Automatische Auswertung bei Vollständigkeit
   - Status B → V/X

3. **Wartezeitverkürzung**
   - Begründung eingeben
   - 2 weitere VMs müssen zustimmen
   - Wartezeit aufheben

**Deliverable:** Komplette Abstimmung funktioniert

### Phase 5: Beschlussdatenbank (Woche 6)

1. **Suche & Filter**
   - `tab_resolutions.php`
   - Zeitraum-Filter
   - Volltext-Suche
   - Ressort-Filter
   - Sichtbarkeitsregeln (extern/intern)

2. **Export (optional)**
   - PDF-Export einzelner Beschlüsse
   - CSV-Export Listen

**Deliverable:** Durchsuchbare Beschlussdatenbank

### Phase 6: Dateianhänge (Woche 7)

1. **Upload-Funktionalität**
   - Wie in Sitzungsverwaltung (Drag & Drop)
   - `proposal_attachments` Tabelle
   - Link-Eingabe (externe URLs)
   - Bis zu 4 Dateien/Links

2. **Download & Anzeige**
   - PDF-Vorschau
   - Berechtigungsprüfung

**Deliverable:** Dateianhänge funktionieren

### Phase 7: Cronjob & E-Mails (Woche 8)

1. **proposals_cron.php**
   - Abstimmungen nach 7 Tagen beenden
   - E-Mail-Benachrichtigungen
   - Leere Anträge löschen

2. **E-Mail-Templates**
   - Antrag erstellt
   - Antrag geändert
   - Zur Abstimmung gestellt
   - Abstimmung beendet
   - Kommentar hinzugefügt

3. **Cronjob-Integration**
   - In bestehenden Pseudo-Cron einbinden
   - Oder separater Cron-Aufruf

**Deliverable:** Automatisierung vollständig

### Phase 8: Admin & Polish (Woche 9)

1. **Admin-Funktionen**
   - Nachträglich Beschlüsse eintragen
   - Beschlüsse editieren
   - Departments verwalten
   - Konfiguration anpassen

2. **UI-Verbesserungen**
   - Responsive optimieren
   - Status-Visualisierung
   - Progress-Indicator
   - Loading-States

3. **Testing & Bugfixes**

**Deliverable:** Produktionsreif

### Phase 9: Go-Live & Wartung (Woche 10)

1. **Deployment**
   - Productive DB-Migration
   - Alte Scans kopieren
   - DNS/Routing anpassen (falls nötig)

2. **Monitoring**
   - Erste Wochen beobachten
   - Feedback sammeln

3. **Schulung/Dokumentation**
   - Benutzer-Handbuch
   - Admin-Handbuch

**Deliverable:** Produktiv im Einsatz

---

## 6. OFFENE PUNKTE FÜR SPÄTER

### 6.1 Sitzungs-Integration (Merkliste)

**Konzept "Offene Anträge" TOP:**
```
┌─────────────────────────────────────┐
│ TOP 5: Offene Anträge               │
│                                      │
│ Beschlusstext:                       │
│ "Folgende Anträge stehen aktuell    │
│ zur Bearbeitung/Abstimmung:"         │
│                                      │
│ [Link-Liste generiert aus DB]       │
│ - A230512-01: Antrag XYZ (editieren)│
│ - B230510-02: Antrag ABC (abstimmen)│
│ - ...                                 │
└─────────────────────────────────────┘
```

**Implementierung:**
- Funktion `get_open_proposals_summary()`
- In `tab_agenda_display_*.php` einbinden
- Bei Bedarf Button "TOP erstellen"

### 6.2 Forum-Integration Discourse (Merkliste)

**Neue Struktur:**
- Discourse API statt MLF2
- Thread automatisch erstellen bei Antragstellung
- Bidirektionale Verknüpfung

**Später, wenn:**
- Discourse-API verfügbar
- Klare Anforderungen

---

## 7. ZUSAMMENFASSUNG

**Was wir machen:**
✅ Modernes Antrags- und Beschlusssystem
✅ Basierend auf bewährter VTool-Logik
✅ Mit Architektur der Sitzungsverwaltung
✅ Vollständige Migration aller Altdaten
✅ Konfigurierbar für Ihre Bedürfnisse

**Was wir NICHT machen:**
❌ Zahlungsfreigaben (bleibt im VTool)
❌ Multi-Organisations-Fähigkeit
❌ Forum-Integration (später)
❌ Automatische Meeting-Integration (später, manuell per Link)

**Zeitplan:**
- 9 Wochen Entwicklung
- 1 Woche Go-Live
- = 10 Wochen gesamt

**Nächster Schritt:**
Soll ich mit Phase 1 (Datenbank & Migration) beginnen?
