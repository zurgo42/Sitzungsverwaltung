# VTool-Analyse: Online-Antrags- und Beschlusssystem

## Executive Summary

Das VTool ist ein über Jahre gewachsenes System für Online-Beschlussfassung in Vereinen. Es kombiniert:
- Antragstellung mit kollaborativer Bearbeitung
- Regelbasierte Abstimmungsverfahren
- Dokumentation in einer Beschlussdatenbank
- Integration mit Zahlungsfreigaben

**Zentrale Herausforderung:** Vereinsspezifische Logik muss konfigurierbar werden.

---

## 1. FUNKTIONALE REQUIREMENTS

### 1.1 Antragstellung (Steuer 20-24)

**Was passiert:**
- Jeder Berechtigte kann Anträge einstellen
- Anträge erhalten automatische Nummer: `AJJMMTTNN` (A = im Editiermodus)
- Andere können den Antrag editieren (kollaborativ)
- Antragsteller wird über Änderungen per Mail informiert

**Datenfelder eines Antrags:**
```
- Antragsteller (antrst = Member-ID)
- Ressort (ressort1, ressort2)
- Verein/Stiftung
- intern/extern/nicht öffentlich/Entwurf
- Titel, Beschlusstext
- Finanzielle Auswirkungen (fin = Betrag, fintext = Beschreibung)
- Personelle Auswirkungen
- Sachliche Auswirkungen
- Begründung
- Verantwortlich für Umsetzung
- Forum-Thread-ID
- Bis zu 4 Dateianhänge (file1-4, filetext1-4)
- Vereinfachte Freigabe (sofort-Flag)
- Bemerkung zum Zahlungsvorgang
- Verknüpfung zu Präsenzsitzung (optional)
```

**Besonderheiten:**
- Wartezeit: 7 Tage zwischen Antragstellung und Abstimmung (per Verfahrensordnung)
- Wartezeitverkürzung: Ressortvorstand kann begründen, 2 weitere VMs müssen zustimmen
- Präsenzanträge: Können Offline-Sitzungen zugeordnet werden

### 1.2 Beschlussarten & Freigabe-Logik

**KRITISCH: Vereinsspezifische Regeln**

Das VTool kennt 3 Beschlussarten basierend auf Betragsgrenzen:

| Beschlussart | Bedingung | Freigabe durch | Notation |
|--------------|-----------|----------------|----------|
| **Verfügung (V)** | fin < 600 EUR UND < 2000 EUR/Monat | 1 Person (Ressortvorstand) | Sofort wirksam |
| **Ressortbeschluss (R)** | 600 EUR ≤ fin ≤ 3000 EUR | Ressortvorstand + Finanzvorstand (Vier-Augen) | |
| **Vorstandsbeschluss (B)** | fin > 3000 EUR ODER VM verlangt es | Alle Vorstandsmitglieder | Mehrheitsabstimmung |

**Override-Regel:**
- Jedes Vorstandsmitglied kann verlangen, dass ein Antrag als Vorstandsbeschluss behandelt wird (wichtig-Flag)

**Monatliche Verfügungsgrenze:**
- Einzelverfügungen < 600 EUR, aber max. 2000 EUR/Kalendermonat pro Ressort

**Vereinfachte Freigabe (bei Zahlungen):**
1. Rechnung = Angebot → sofort überweisen
2. Nach Prüfung durch [Person X] → ohne weitere Freigabe überweisen

### 1.3 Abstimmungsverfahren (Steuer 25-29)

**Workflow:**
```
A[JJMMTTNN] (Editiermodus)
    ↓ "verbindlich einstellen"
B[JJMMTTNN] (in Abstimmung) ← Datum ändert sich auf aktuelles Datum
    ↓ Abstimmung
V[JJMMTTNN] (Beschluss)
oder
X[JJMMTTNN] (abgelehnt)
Z[JJMMTTNN] (zurückgezogen)
```

**Abstimmungsregeln Vorstandsbeschluss:**
- Jedes VM hat ein Votum: Ja / Nein / Enthaltung / Rückverweis / Bedenkzeit
- **Rückverweis:** Antrag nicht abstimmungsreif → Zurück zur Beratung
- **Bedenkzeit:** Max. 7 Tage, aber nicht länger als Abstimmungsfrist
- **Beschluss:** Mehr Ja als Nein = angenommen
- **Abwesende:** Wenn < 2 VMs abwesend gemeldet, zählen nur anwesende Stimmen
- Abstimmungsfrist: 7 Tage ab Einstellung

**Abstimmungsregeln Ressortbeschluss:**
- Beide müssen zustimmen (verf1 + verf2)
- Nein von einem = abgelehnt

**Abstimmungsregeln Verfügung:**
- verf1 stimmt zu = sofort wirksam als Beschluss

**Transparenz:**
- Votum + Begründung (intern, nicht im Protokoll)
- Protokollnotiz (öffentlich, wird dokumentiert)
- Hinweise an Antragsteller

### 1.4 Beschlussdatenbank (Steuer 10-11)

**Anzeige & Suche:**
- Zeitraum-Filter (Start- bis Enddatum)
- Volltext-Suche (Titel, Beschluss, Begründung, Ressort, Sachliche Auswirkungen)
- Sortierung (aufsteigend/absteigend)
- Limit (Anzahl Datensätze)
- Darstellung: Kurzübersicht / Komplette Tabelle / MensaNews-Format

**Sichtbarkeitsregeln:**
- extern: Alle Mitglieder
- nicht öffentlich: Führungskreis
- intern: Nur Vorstand

**Export für MensaNews:**
- Spezielle vereinfachte Darstellung
- Direktlink für Newsletter

### 1.5 Admin-Funktionen (Steuer 34-39)

- Nachträgliches Eintragen von Beschlüssen (z.B. nach Präsenzsitzung)
- Editieren bestehender Beschlüsse
- Beschlussnummer manuell vergeben (VS-Nummern für Sonderf��lle)

---

## 2. DATENBANK-STRUKTUR

### 2.1 Aktuelle Tabellen (VTool)

**antraege** (Zentrale Tabelle - enthält sowohl Anträge als auch Beschlüsse)
```sql
antrnr VARCHAR (AJJMMTTNN, BJJMMTTNN, VJJMMTTNN, XJJMMTTNN, ZJJMMTTNN)
antrst INT (Antragsteller Member-ID)
bart ENUM('V','R','B') (Beschlussart)
ressort1, ressort2 VARCHAR
verein ENUM('V','S') (Verein/Stiftung)
int_ext ENUM('e','n','i','v') (extern/nicht öffentlich/intern/Entwurf)
titel, beschluss, begr TEXT
fin DECIMAL (Betrag)
fintext, pers, sach TEXT (Auswirkungen)
verant TEXT (Verantwortlich)
thread INT (Forum-ID)
file1, file2, file3, file4 VARCHAR (Dateipfade/Links)
filetext1-4 VARCHAR (Beschreibung)
sofort TINYINT (Vereinfachte Freigabe: 1=Rechnung=Angebot, 2=nach Prüfung)
durch VARCHAR (Prüfperson bei sofort=2)
zufin BOOLEAN (Zustimmung Finanzvorstand)
zbem TEXT (Bemerkung Zahlungsvorgang)
praesenz INT (Kennung Präsenzsitzung, 0=online)

-- Abstimmungsdaten
VName1-6 INT (Member-IDs der Stimmberechtigten)
Votum1-6 TINYINT (1=Ja, 2=Nein, 3=Enthaltung, 4=Rückverweis, 5=Bedenkzeit, 6=zurückgezogen)
VDat1-6 DATETIME (Abstimmungszeitpunkt)
VBegr1-6 TEXT (Begründung, intern)
VProt1-6 TEXT (Protokollnotiz, öffentlich)
VBedenk1-6 DATE (Bedenkzeit bis)

verf1, verf2 INT (Verfügungsberechtigte bei R/V)
wichtig INT (VM-ID, das Vorstandsbeschluss verlangt, oder 0)

-- Wartezeitverkürzung
verkb TEXT (Begründung)
verk1, verk2 INT (Zustimmende VMs)

warantrag VARCHAR (Ursprüngliche Antragsnummer vor Status-Wechsel)
ergebnis TEXT (Abstimmungsergebnis-Text)
hinweis TEXT (Hinweise von anderen Berechtigten an Antragsteller)
lzugriff DATETIME (Letzter Zugriff)
```

**beschluesse** (Wird gefüllt, wenn Beschluss gefasst wurde)
```sql
antrnr VARCHAR (VSJJMMTTNN)
wichtig BOOLEAN (Besondere Bedeutung)
ressort VARCHAR (Klartext, nicht ID!)
int_ext, titel, beschluss, fintext, pers, sach, begr TEXT
dafuer TEXT (Abstimmungsergebnis-Text, z.B. "2:1:1, Dafür: X, Y")
anmerkungen TEXT
```

**Hinweis:** Die Datenhaltung ist redundant (Beschluss steht in beiden Tabellen). Sollte vereinheitlicht werden.

### 2.2 Empfohlene Modernisierung

**Neue Struktur:**

```sql
-- Zentrale Tabelle
proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_number VARCHAR(15) UNIQUE,  -- AJJMMTTNN format
    status ENUM('draft', 'editing', 'voting', 'approved', 'rejected', 'withdrawn'),
    
    -- Antragsdaten
    submitter_id INT,
    department_id INT,  -- statt ressort1
    co_department_id INT NULL,  -- statt ressort2
    organization_type ENUM('association', 'foundation'),
    visibility ENUM('public', 'leadership', 'board', 'draft'),
    
    title VARCHAR(500),
    description TEXT,
    justification TEXT,
    
    -- Auswirkungen
    financial_amount DECIMAL(10,2),
    financial_description TEXT,
    personnel_impact TEXT,
    material_impact TEXT,
    responsible_person TEXT,
    
    -- Verknüpfungen
    forum_thread_id INT NULL,
    meeting_id INT NULL,  -- für Präsenzanträge
    
    -- Freigabe-Logik
    decision_type ENUM('single', 'department', 'board'),  -- V/R/B
    simplified_approval ENUM('none', 'invoice_match', 'after_review'),
    review_person VARCHAR(255) NULL,
    finance_approval BOOLEAN DEFAULT FALSE,
    payment_note TEXT,
    
    -- Workflow
    submitted_at TIMESTAMP,
    finalized_at TIMESTAMP NULL,  -- Antrag verbindlich gestellt
    voting_deadline TIMESTAMP NULL,
    decided_at TIMESTAMP NULL,
    last_modified TIMESTAMP,
    
    FOREIGN KEY (submitter_id) REFERENCES members(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id)
);

-- Dateianhänge (normalisiert statt file1-4)
proposal_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT,
    file_path VARCHAR(500),
    file_url VARCHAR(500) NULL,  -- für externe Links
    description VARCHAR(255),
    uploaded_at TIMESTAMP,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
);

-- Abstimmungsdaten (normalisiert statt VName1-6)
proposal_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT,
    voter_id INT,
    vote_type ENUM('yes', 'no', 'abstain', 'refer_back', 'request_time'),
    internal_comment TEXT,  -- VBegr
    protocol_note TEXT,  -- VProt
    consideration_until DATE NULL,  -- VBedenk
    voted_at TIMESTAMP,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (voter_id) REFERENCES members(id),
    UNIQUE KEY unique_vote (proposal_id, voter_id)
);

-- Freigabeberechtigte (normalisiert statt verf1/verf2)
proposal_approvers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT,
    approver_id INT,
    approver_role ENUM('primary', 'finance', 'secondary'),
    approved BOOLEAN DEFAULT FALSE,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES members(id)
);

-- Wartezeitverkürzung
proposal_expedite_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT,
    justification TEXT,
    approver1_id INT NULL,
    approver2_id INT NULL,
    approved_at1 TIMESTAMP NULL,
    approved_at2 TIMESTAMP NULL,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
);

-- Änderungshistorie
proposal_changes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT,
    changed_by INT,
    field_name VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    changed_at TIMESTAMP,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES members(id)
);

-- Hinweise/Kommentare
proposal_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT,
    author_id INT,
    comment_text TEXT,
    is_for_submitter BOOLEAN DEFAULT TRUE,  -- an Antragsteller vs. allgemein
    created_at TIMESTAMP,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES members(id)
);

-- Konfiguration: Ressorts/Departments (statt hardcodiert)
departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    short_name VARCHAR(20) UNIQUE,
    full_name VARCHAR(255),
    active BOOLEAN DEFAULT TRUE,
    sort_order INT
);

-- Konfiguration: Entscheidungsregeln
decision_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT,  -- für Multi-Org-Fähigkeit
    rule_name VARCHAR(100),
    
    -- Betragsgrenzen
    single_approval_max DECIMAL(10,2),  -- < 600
    single_approval_monthly_max DECIMAL(10,2),  -- 2000/Monat
    department_approval_max DECIMAL(10,2),  -- 3000
    board_approval_min DECIMAL(10,2),  -- > 3000
    
    -- Fristen
    waiting_period_days INT DEFAULT 7,
    voting_period_days INT DEFAULT 7,
    consideration_max_days INT DEFAULT 7,
    
    -- Quorum
    min_board_members_present INT DEFAULT 3,  -- bei Abwesenheit
    
    active BOOLEAN DEFAULT TRUE
);
```

---

## 3. VEREINSSPEZIFISCHE TEILE → KONFIGURIERBAR

### 3.1 Betragsgrenzen & Freigabe-Regeln

**Aktuell hardcodiert:**
```php
if ($ab['fin'] > 3000) { ... } // Vorstandsbeschluss
if ($ab['fin'] >= 600 AND $ab['fin'] < 3000) { ... } // Ressortbeschluss
if ($ab['fin'] < 600) { ... } // Verfügung
```

**Soll: Konfigurierbar**
```php
config_proposals.php:

define('SINGLE_APPROVAL_MAX', 600);
define('SINGLE_APPROVAL_MONTHLY_MAX', 2000);
define('DEPARTMENT_APPROVAL_MAX', 3000);
define('BOARD_APPROVAL_MIN', 3000);
define('WAITING_PERIOD_DAYS', 7);
define('VOTING_PERIOD_DAYS', 7);
```

Oder: In Datenbank in `decision_rules` Tabelle.

### 3.2 Rollen & Berechtigungen

**Aktuell:**
- Hardcodierte Funktionen: 'Vo' (Vorstand), 'FVo' (Finanzvorstand), 'GF' (Geschäftsführung)
- aktiv-Level: 3, 11, 14, 18, 19

**Soll:**
- Flexibles Rollen-System
- Rollen-Permissions-Mapping

```php
roles:
- board_member (entspricht aktiv=19, Vorstand)
- department_lead (entspricht aktiv>=11, Koordinator/Ressortvorstand)
- finance_lead (entspricht FVo)
- management (entspricht GF, aktiv>=18)
- leadership (entspricht aktiv>=14, Führungskreis)
- member (entspricht aktiv=3)

permissions:
- proposals.create
- proposals.edit_own
- proposals.edit_any
- proposals.finalize_own
- proposals.delete_own
- proposals.vote
- proposals.expedite_waiting
- proposals.view_internal
- proposals.view_confidential
- proposals.admin_edit
```

### 3.3 Ressort/Department-Struktur

**Aktuell:**
- $ress[], $rname[], $rliste[] Arrays mit hartkodierten Werten

**Soll:**
- Datenbank-gesteuert
- Admin-UI zum Verwalten von Departments

### 3.4 Abstimmungsregeln

**Vereinsspezifisch:**
- "Mehr Ja als Nein = angenommen" (einfache Mehrheit)
- Andere Vereine könnten brauchen:
  - Absolute Mehrheit
  - 2/3-Mehrheit
  - Quorum (mind. X Stimmen abgegeben)

**Soll: Konfigurierbar**
```php
config:
VOTING_MODE = 'simple_majority' | 'absolute_majority' | 'two_thirds' | 'unanimous'
QUORUM_REQUIRED = false | true
QUORUM_PERCENTAGE = 50
```

### 3.5 Nummerierungsschema

**Aktuell:**
- AJJMMTTNN, BJJMMTTNN, VJJMMTTNN
- Hartcodiert in folgenummer()

**Soll:**
- Template-basiert: `{PREFIX}{YEAR}{MONTH}{DAY}{COUNTER}`
- Konfigurierbare Präfixe für Status

### 3.6 E-Mail-Benachrichtigungen

**Aktuell:**
- Hardcodierte URLs: `https://aktive.mensa.de/vorstand/vtool.php?...`
- Hardcodierte Texte

**Soll:**
- Template-System für E-Mails
- Konfigurierbare Base-URL
- Mehrsprachigkeit (optional)

---

## 4. UI/UX-VERBESSERUNGEN

### 4.1 Probleme im aktuellen VTool

1. **Monolithische Dateien:** 937 Zeilen in einer Datei, vermischt Logik und Darstellung
2. **Steuer-Variable:** Komplexes Routing über `if ($steuer == XX)`
3. **Nicht responsive:** Nur bedingt smartphone-fähig
4. **Inline-Styles:** Schwer wartbar
5. **Keine Trennung:** PHP-Logik, HTML, SQL alles gemischt

### 4.2 Moderne Architektur

**Vorschlag:**

```
proposals/
├── process_proposals.php      # POST-Handler für alle Aktionen
├── tab_proposals.php           # Anträge auflisten + erstellen
├── tab_proposals_vote.php      # Abstimmung
├── tab_resolutions.php         # Beschlüsse anzeigen/suchen
├── proposals_functions.php     # Business-Logik
├── proposals_display.php       # Display-Funktionen
└── config_proposals.php        # Konfiguration

Aktionen (statt steuer):
- create: Neuen Antrag erstellen
- edit: Antrag bearbeiten
- save: Speichern
- delete: Löschen
- finalize: Verbindlich einstellen
- vote: Abstimmen
- withdraw: Zurückziehen
- expedite: Wartezeitverkürzung
```

**Routing:**
```php
// process_proposals.php
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        handle_create_proposal($pdo, $current_user);
        break;
    case 'edit':
        handle_edit_proposal($pdo, $current_user);
        break;
    case 'vote':
        handle_vote($pdo, $current_user);
        break;
    // ...
}
```

### 4.3 Responsive Design

**Mobile-First:**
- Card-Layout statt Tabellen
- Collapsible Sections für Details
- Touch-freundliche Buttons
- Progressive Disclosure

**Beispiel:**
```html
<!-- Desktop: Mehrspaltige Tabelle -->
<!-- Mobile: Cards mit Details on Tap -->

<div class="proposal-card">
    <div class="proposal-header">
        <span class="proposal-number">A230512-01</span>
        <span class="proposal-status badge">In Bearbeitung</span>
    </div>
    <h3>Titel des Antrags</h3>
    <div class="proposal-meta">
        <span>Von: Max Mustermann</span>
        <span>Ressort: IT</span>
        <span>600 EUR</span>
    </div>
    <details class="proposal-details">
        <summary>Details anzeigen</summary>
        <p>Beschlusstext...</p>
        <!-- Weitere Details -->
    </details>
</div>
```

### 4.4 Visuelle Verbesserungen

**Status-Visualisierung:**
```
┌─────────────┐     ┌──────────┐     ┌──────────┐
│  Entwurf    │ --> │ Wartez.  │ --> │ Abstimm. │ --> ✅ Beschluss
│  (Editier.) │     │ 7 Tage   │     │ läuft    │     ❌ Abgelehnt
└─────────────┘     └──────────┘     └──────────┘
```

**Progress-Indicator für Abstimmungen:**
```
Abstimmung läuft: ████████░░ 8/10 Stimmen
Ja: 5  |  Nein: 2  |  Enthaltung: 1
Noch offen: VM Weber, VM Schmidt
```

**Farbcodierung:**
- 🟢 Grün: Beschluss gefasst
- 🔵 Blau: In Abstimmung
- 🟡 Gelb: Wartezeit
- ⚪ Grau: Im Editiermodus
- 🔴 Rot: Abgelehnt

---

## 5. IMPLEMENTIERUNGSPLAN

### Phase 1: Grundgerüst (Woche 1-2)

**Ziel:** Basis-Funktionalität mit modernem Code

1. **Datenbank-Schema erstellen**
   - `proposals` Tabelle
   - `proposal_votes` Tabelle
   - `proposal_attachments` Tabelle
   - `departments` Tabelle mit Seed-Daten
   - `decision_rules` Tabelle mit Default-Config

2. **Basis-Konfiguration**
   - `config_proposals.php` mit allen Defaults
   - Migrations-Skript zum Übertragen alter Daten (optional, später)

3. **Core-Funktionen**
   - `proposals_functions.php`:
     - `create_proposal()`
     - `update_proposal()`
     - `get_proposal()`
     - `list_proposals()`
     - `generate_proposal_number()`
     - `calculate_decision_type()` (V/R/B basierend auf Betrag)

4. **Einfaches UI**
   - `tab_proposals.php`: Liste + Formular zum Erstellen
   - `process_proposals.php`: create/edit Actions
   - Noch ohne Abstimmung, nur Editier-Workflow

**Deliverable:** Man kann Anträge erstellen und auflisten.

### Phase 2: Workflow & Berechtigungen (Woche 3)

1. **Berechtigungs-System**
   - `check_proposal_permission()` Funktion
   - Adapter an bestehendes Mitglieder-System

2. **Editier-Workflow**
   - Kollaboratives Editieren
   - Änderungshistorie (`proposal_changes`)
   - E-Mail-Benachrichtigungen bei Änderungen
   - Kommentare/Hinweise (`proposal_comments`)

3. **Wartezeit & Finalisierung**
   - "Verbindlich einstellen" Button
   - Wartezeitberechnung
   - Wartezeitverkürzung (`proposal_expedite_requests`)
   - Status-Wechsel A → B

**Deliverable:** Vollständiger Editier-Workflow bis zur Abstimmung.

### Phase 3: Abstimmung (Woche 4)

1. **Abstimmungs-UI**
   - `tab_proposals_vote.php`
   - Anzeige aller zur Abstimmung stehenden Anträge
   - Abstimmungs-Formular (Ja/Nein/Enthaltung/...)

2. **Abstimmungs-Logik**
   - `handle_vote()` in process_proposals.php
   - Speichern in `proposal_votes`
   - Automatische Auswertung wenn komplett
   - Status-Wechsel B → V/X

3. **Verschiedene Abstimmungsarten**
   - Vorstandsbeschluss (alle VMs)
   - Ressortbeschluss (2 Personen)
   - Verfügung (1 Person, sofort → V)

**Deliverable:** Kompletter Abstimmungs-Workflow.

### Phase 4: Beschlussdatenbank (Woche 5)

1. **Beschluss-Anzeige**
   - `tab_resolutions.php`
   - Liste mit Filtern (Datum, Stichwort, Ressort)
   - Detail-Ansicht

2. **Such-Funktionalität**
   - Volltext-Suche
   - Filter-Kombinationen
   - Export (PDF/CSV optional)

3. **Sichtbarkeits-Regeln**
   - extern/intern/nicht öffentlich
   - Berechtigungsprüfung

**Deliverable:** Durchsuchbare Beschlussdatenbank.

### Phase 5: Dateianhänge & Zahlungen (Woche 6)

1. **File-Upload**
   - Integration wie in Sitzungsverwaltung (agenda_attachments)
   - Drag & Drop
   - Link-Eingabe (externe URLs)

2. **Zahlungs-Integration**
   - Vereinfachte Freigabe
   - Bemerkungsfeld
   - (Optional: Integration mit existierender Zahlungsverwaltung)

**Deliverable:** Vollständiges Feature-Set.

### Phase 6: Admin & Migration (Woche 7)

1. **Admin-Funktionen**
   - Beschlüsse nachträglich eintragen
   - Beschlüsse editieren
   - Departments verwalten
   - Konfiguration (decision_rules)

2. **Migrations-Tool**
   - VTool-Daten importieren
   - `antraege` → `proposals`
   - `beschluesse` → `proposals` (Status=approved)

**Deliverable:** System produktionsreif.

### Phase 7: Polish & Testing (Woche 8)

1. **UI-Verbesserungen**
   - Responsive optimieren
   - Accessibility (ARIA)
   - Loading-States
   - Error-Handling

2. **Testing**
   - Workflows durchspielen
   - Edge-Cases
   - Performance

3. **Dokumentation**
   - Benutzer-Handbuch
   - Admin-Handbuch
   - Code-Dokumentation

**Deliverable:** Produktionsreifes System mit Doku.

---

## 6. OFFENE FRAGEN

1. **Multi-Organisationen-Fähigkeit?**
   - Soll das System mehrere Vereine/Organisationen gleichzeitig verwalten können?
   - → Würde bedeuten: `organization_id` in allen Tabellen

2. **Präsenz-Sitzungen-Integration?**
   - Wie eng soll die Verknüpfung zur bestehenden Sitzungsverwaltung sein?
   - Sollen Anträge zu TOPs in Sitzungen werden?

3. **Zahlungsverwaltung?**
   - Gibt es bereits ein System für Zahlungen?
   - Wie detailliert soll die Integration sein?

4. **Forum-Integration?**
   - thread-ID: Wird das noch gebraucht?
   - Automatische Thread-Erstellung?

5. **Alte Daten migrieren?**
   - Sollen alte VTool-Beschlüsse übernommen werden?
   - Oder Clean-Start?

6. **MensaNews-Export?**
   - Ist das vereinsspezifisch oder generisch "Newsletter-Export"?

---

## 7. NÄCHSTE SCHRITTE

1. **Feedback zu diesem Dokument:**
   - Sind die Requirements vollständig?
   - Fehlt etwas Wichtiges?
   - Sind Prioritäten richtig?

2. **Entscheidungen treffen:**
   - Multi-Org: Ja/Nein?
   - Migrieren: Ja/Nein?
   - Start-Datum?

3. **Dann:**
   - Phase 1 beginnen: Datenbank-Schema
   - Parallel: UI-Mockups erstellen (optional)

**Möchten Sie mit der Implementierung beginnen oder erst Anpassungen am Plan?**
