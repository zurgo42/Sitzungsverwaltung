# VTool → Sitzungsverwaltung: Vollständige Migration

## Ziel
Alle Daten aus `antraege` + `beschluesse` in `svbproposals` migrieren

## Datenmodell-Verständnis

### Phase 1: ANTRAG (antraege)
- Einreichung des Antrags
- Bearbeitung & Diskussion
- Vorbereitung zur Abstimmung

### Phase 2: BESCHLUSS (beschluesse)
- Finales Abstimmungsergebnis
- Verbindlicher Beschlusstext (kann vom Antrag abweichen!)
- Stimmauszählung

→ **Ein Antrag wird zum Beschluss** → beide Daten in einer Zeile!

## Felder-Mapping

### Bereits vorhanden in svbproposals ✅
| VTool Feld | SVB Feld | Anmerkung |
|------------|----------|-----------|
| titel | title | ✅ |
| beschluss | description | ✅ |
| begr | justification | ✅ |
| pers | personnel_impact | ✅ |
| sach | material_impact | ✅ |
| verant | responsible_person | ✅ |
| thread | forum_thread_id | ✅ (als INT) |
| fintext | financial_description | ✅ |
| wichtig | important | ✅ |
| ergebnis | result_text | ✅ |
| Betrag | financial_amount | ✅ |

### Bereits in separaten Tabellen ✅
| VTool Feld | SVB Tabelle | Anmerkung |
|------------|-------------|-----------|
| file1-4 | svbproposal_attachments | ✅ Normalisiert |
| filetext1-4 | svbproposal_attachments.description | ✅ |
| VName1-6 | svbproposal_votes.voter_id | ✅ |
| Votum1-6 | svbproposal_votes.vote_type | ✅ |
| VBegr1-6 | svbproposal_votes.internal_comment | ⚠️ Noch nicht migriert |
| VProt1-6 | svbproposal_votes.protocol_note | ⚠️ Noch nicht migriert |
| VBedenk1-6 | svbproposal_votes.??? | ❌ FEHLT! |
| VDat1-6 | svbproposal_votes.voted_at | ⚠️ Noch nicht migriert |
| ressort1 | svbproposal_departments | ✅ Normalisiert |
| ressort2 | svbproposal_departments | ✅ Normalisiert |
| verf1 | svbproposal_approvers | ✅ |
| verf2 | svbproposal_approvers | ✅ |

### FEHLENDE Felder in svbproposals ❌

#### Kategorie: Antrag-Metadaten
| VTool | Bedeutung | Typ | Benötigt? |
|-------|-----------|-----|-----------|
| bart | Art des Antrags (A/B/C?) | VARCHAR(1) | ⚠️ Unklar |
| praesenz | Präsenz-Meeting-Typ | VARCHAR(11) | ⚠️ Unklar |
| antrst | Status-Code in VTool | VARCHAR(3) | ℹ️ Dokumentation |
| verein | Vereins-Flag | VARCHAR(1) | ⚠️ Unklar |
| int_ext | Intern/Extern | VARCHAR(1) | ✅ JA |

#### Kategorie: Budget & Finanzen
| VTool | Bedeutung | Typ | Benötigt? |
|-------|-----------|-----|-----------|
| budgetnr | Budget-Nummer | VARCHAR(16) | ✅ JA |
| budget | Budget-Code | VARCHAR(8) | ✅ JA |
| fin | Finanz-Verantwortlicher (Code) | VARCHAR(8) | ✅ JA |
| zufin | An Finanzen weitergeleitet | VARCHAR(16) | ✅ JA |
| zbem | Bemerkungen Finanzen | TEXT | ✅ JA |

#### Kategorie: Workflow & Flags
| VTool | Bedeutung | Typ | Benötigt? |
|-------|-----------|-----|-----------|
| hinweis | Hinweise/Notizen | TEXT | ✅ JA |
| vorher | Vorher-Flag | VARCHAR(1) | ⚠️ Unklar |
| sofort | Sofort-Flag | VARCHAR(1) | ⚠️ Unklar |
| durch | Bearbeitet durch | VARCHAR(64) | ✅ JA |
| verf | Ersteller-Code | VARCHAR(64) | ✅ JA |
| warantrag | War-Antrag-Nummer | VARCHAR(9) | ✅ JA (Historie) |

#### Kategorie: Verknüpfungen
| VTool | Bedeutung | Typ | Benötigt? |
|-------|-----------|-----|-----------|
| verkb | Verknüpfungs-Bemerkung | TEXT | ✅ JA |
| verk1 | Verknüpfung 1 | INT | ✅ JA |
| verk2 | Verknüpfung 2 | INT | ✅ JA |

#### Kategorie: Sonstiges
| VTool | Bedeutung | Typ | Benötigt? |
|-------|-----------|-----|-----------|
| Zeitablauf | Zeitlicher Ablauf | TEXT | ✅ JA |
| lzugriff | Letzter Zugriff | DATETIME | ℹ️ Optional |

### FEHLENDE Felder aus beschluesse ❌

#### Beschluss-spezifische Daten (FINAL VERSION)
| beschluesse | Bedeutung | Typ | Mapping |
|-------------|-----------|-----|---------|
| fertig | Beschluss abgeschlossen | VARCHAR(1) | → decision_finalized |
| text | Beschluss-Volltext | TEXT | → decision_text |
| titel | Beschluss-Titel (final) | TEXT | → decision_title |
| beschluss | Beschluss-Inhalt (final) | TEXT | → decision_content |
| fintext | Finanz-Text (final) | TEXT | → decision_finance_text |
| pers | Personal-Kosten (final) | TEXT | → decision_personnel |
| sach | Sachkosten (final) | TEXT | → decision_material |
| begr | Begründung (final) | TEXT | → decision_justification |
| ressort | Ressorts (final) | TEXT | → decision_departments |
| int_ext | Intern/Extern (final) | VARCHAR(8) | → decision_visibility |

#### Abstimmungs-Ergebnisse
| beschluesse | Bedeutung | Typ | Mapping |
|-------------|-----------|-----|---------|
| dafuer | Namen: Dafür | TEXT | → votes_for_list |
| dagegen | Namen: Dagegen | TEXT | → votes_against_list |
| enthaltungen | Namen: Enthaltungen | TEXT | → votes_abstain_list |
| anmerkungen | Anmerkungen zum Beschluss | TEXT | → decision_notes |

## Vorgeschlagene Schema-Änderungen

### 1. svbproposals erweitern

```sql
ALTER TABLE svbproposals
-- VTool Legacy-Felder (Dokumentation)
ADD COLUMN vtool_bart VARCHAR(1) NULL COMMENT 'VTool: Art des Antrags',
ADD COLUMN vtool_praesenz VARCHAR(11) NULL COMMENT 'VTool: Präsenz-Typ',
ADD COLUMN vtool_antrst VARCHAR(3) NULL COMMENT 'VTool: Original-Status',
ADD COLUMN vtool_creator_code VARCHAR(64) NULL COMMENT 'VTool: Ersteller-Code (verf)',

-- Kategorisierung
ADD COLUMN internal_external ENUM('internal', 'external', 'mixed') NULL COMMENT 'Interne/Externe Wirkung',
ADD COLUMN club_type VARCHAR(1) NULL COMMENT 'Vereins-Zuordnung',

-- Budget & Finanzen
ADD COLUMN budget_number VARCHAR(16) NULL COMMENT 'Budget-Nummer',
ADD COLUMN budget_code VARCHAR(8) NULL COMMENT 'Budget-Code',
ADD COLUMN finance_officer_code VARCHAR(8) NULL COMMENT 'Finanz-Verantwortlicher',
ADD COLUMN forwarded_to_finance VARCHAR(16) NULL COMMENT 'An Finanzen weitergeleitet',
ADD COLUMN finance_notes TEXT NULL COMMENT 'Bemerkungen Finanzen',

-- Workflow
ADD COLUMN notes TEXT NULL COMMENT 'Allgemeine Hinweise',
ADD COLUMN processed_by VARCHAR(64) NULL COMMENT 'Bearbeitet durch',
ADD COLUMN prior_flag TINYINT(1) DEFAULT 0 COMMENT 'Vorher-Flag',
ADD COLUMN immediate_flag TINYINT(1) DEFAULT 0 COMMENT 'Sofort-Flag',

-- Historie & Verknüpfungen
ADD COLUMN original_proposal_ref VARCHAR(9) NULL COMMENT 'Ursprünglicher Antrag (warantrag)',
ADD COLUMN linked_proposal_1 INT NULL COMMENT 'Verknüpfter Antrag 1',
ADD COLUMN linked_proposal_2 INT NULL COMMENT 'Verknüpfter Antrag 2',
ADD COLUMN link_notes TEXT NULL COMMENT 'Verknüpfungs-Bemerkungen',

-- Zeitablauf
ADD COLUMN timeline TEXT NULL COMMENT 'Zeitlicher Ablauf',
ADD COLUMN last_accessed_at DATETIME NULL COMMENT 'Letzter Zugriff (VTool)',

-- BESCHLUSS-PHASE (finale Werte nach Abstimmung)
ADD COLUMN decision_finalized TINYINT(1) DEFAULT 0 COMMENT 'Beschluss abgeschlossen',
ADD COLUMN decision_title TEXT NULL COMMENT 'Finaler Beschluss-Titel',
ADD COLUMN decision_content TEXT NULL COMMENT 'Finaler Beschluss-Text',
ADD COLUMN decision_text_full TEXT NULL COMMENT 'Beschluss-Volltext',
ADD COLUMN decision_finance_text TEXT NULL COMMENT 'Finale Finanz-Begründung',
ADD COLUMN decision_personnel TEXT NULL COMMENT 'Finale Personal-Auswirkungen',
ADD COLUMN decision_material TEXT NULL COMMENT 'Finale Sach-Auswirkungen',
ADD COLUMN decision_justification TEXT NULL COMMENT 'Finale Begründung',
ADD COLUMN decision_departments TEXT NULL COMMENT 'Finale Ressort-Zuordnung',
ADD COLUMN decision_visibility VARCHAR(8) NULL COMMENT 'Finale Sichtbarkeit',
ADD COLUMN decision_notes TEXT NULL COMMENT 'Anmerkungen zum Beschluss',

-- Abstimmungsergebnis (Namenslisten aus beschluesse)
ADD COLUMN votes_for_list TEXT NULL COMMENT 'Namen: Dafür gestimmt',
ADD COLUMN votes_against_list TEXT NULL COMMENT 'Namen: Dagegen gestimmt',
ADD COLUMN votes_abstain_list TEXT NULL COMMENT 'Namen: Enthalten';
```

### 2. svbproposal_votes erweitern

```sql
ALTER TABLE svbproposal_votes
ADD COLUMN concerns TEXT NULL COMMENT 'Bedenken (VBedenk)',
ADD COLUMN concerns_until DATE NULL COMMENT 'Bedenkzeit bis (aus VBedenk)';
```

### 3. Migration aktualisieren

**Reihenfolge:**
1. Alle Anträge aus `antraege` migrieren
2. Passende Beschlüsse aus `beschluesse` laden und in decision_* Felder eintragen
3. Vote-Details (VBegr, VProt, VBedenk, VDat) migrieren
4. Departments (ressort1, ressort2) normalisiert migrieren

## Offene Fragen

1. **bart, praesenz, verein**: Was bedeuten diese Codes? Beispielwerte?
2. **vorher, sofort**: Welche Bedeutung haben diese Flags?
3. **thread**: Ist das eine Nummer oder URL? Format?
4. **fin, durch, verf**: Sind das Mitglieds-Codes oder Namen?
5. **beschluesse.fertig**: Was bedeutet "1" vs. "0"?

## Nächste Schritte

1. ✅ Schema-Änderungen in init-db.php
2. ✅ Migration erweitern für alle Felder
3. ✅ Vote-Details migrieren
4. ✅ Beschluesse mergen
5. ⚠️ Testen mit Dry-Run
6. ✅ Echte Migration

---
**Status**: Wartet auf Freigabe & Klärung der offenen Fragen
