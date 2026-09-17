# Berechtigungssystem - Dokumentation

## Überblick

Das Sitzungsverwaltungssystem verwendet ein zweistufiges Berechtigungssystem:

1. **aktiv** (0-19): Numerisches Berechtigungslevel für Antrags-, Freigabe- und Zugriffsrechte
2. **Funktion**: Rollenzuordnung (Vo, GF, VA, RL, etc.) für organisatorische Zuordnung

## Berechtigungslevel (aktiv)

### Level 0: Öffentlich
- **Berechtigungen**: Zugriff auf die Beschlussdatenbank und vereinsöffentliche Bereiche
- **Verwendung**: Externe Beobachter, öffentliche Einsicht

### Level 1: Spezielle Zugriffe
- **Berechtigungen**: Spezifische Zugriffe je nach Aufgabe
- **Beispiel**: Verwaltungskraft für Mitgliederangelegenheiten (z.B. Wiederaufnahmen)

### Level 2: Kassenfunktionen
- **Berechtigungen**: Vorgänge einstellen, Buchen, Zahlungen veranlassen, alle Beschlüsse sehen
- **Verwendung**: Kassenführung, Buchhaltung

### Level 3: Finanzprüfer, Steuerberater
- **Berechtigungen**: Alles sehen, aber keine aktiven Handlungen vornehmen
- **Verwendung**: Externe Prüfer, Steuerberater mit Lesezugriff

### Level 4-7: Reserviert
- **Status**: Aktuell nicht belegt (für zukünftige Erweiterungen)

### Level 8: Finanzen in Orga-Teams
- **Berechtigungen**: Freigaben sehen/erteilen
- **Verwendung**: Für Finanzen verantwortliche Personen in Orga-Teams

### Level 9: Finanzen in anderen Teams
- **Berechtigungen**: Freigaben sehen/erteilen
- **Verwendung**: Für Finanzen verantwortliche Personen in anderen Teams

### Level 10: Temporäre Aktive
- **Berechtigungen**: Nur für Terminabstimmungen
- **Verwendung**: Datenschutz-Team, Schlichter, temporäre Arbeitsgruppen
- **Beispiel**: Mitglieder, die für spezifische Aufgaben eingebunden werden

### Level 11: Teamleiter zentral
- **Berechtigungen**: 
  - Freigaben sehen/erteilen
  - Anträge sehen/bearbeiten
  - Einbeziehung in Terminabfragen
- **Verwendung**: Zentrale Teamleitungen

### Level 12: Projektleiter
- **Berechtigungen**: 
  - Anträge sehen/bearbeiten
  - Freigaben sehen/erteilen für das eigene Ressort
  - Einbeziehung in Terminabfragen
- **Verwendung**: Projektleitungen ohne eigenes Budget

### Level 13: Projektleitung mit Budget
- **Berechtigungen**: 
  - Anträge sehen/bearbeiten
  - Freigaben sehen/erteilen für das eigene Ressort
  - Einbeziehung in Terminabfragen
- **Verwendung**: Projektleitungen mit eigenem Budget oder ähnlichen Befugnissen

### Level 14: Zweiter Ressortleiter
- **Berechtigungen**: 
  - Freigaben sehen/erteilen für das eigene Ressort
  - Führungsteam-Rechte
- **Verwendung**: Stellvertretende Ressortleitung

### Level 15: Ressortleitung
- **Berechtigungen**: 
  - Freigaben sehen/erteilen für das eigene Ressort
  - Führungsteam-Rechte
- **Verwendung**: Hauptverantwortliche Ressortleitung

### Level 16: Sonderaufgaben (Notvorstand)
- **Berechtigungen**: Alle Vorstandsrechte, aber auf Zeit
- **Verwendung**: Temporäre Vorstandsfunktionen, Notvorstand
- **Besonderheit**: Zeitlich begrenzte Vollmacht

### Level 17: Ressortleitung Finanzen
- **Berechtigungen**: 
  - Alle Freigaben sehen, bearbeiten, erteilen für das eigene Ressort
  - Führungsteam-Rechte
- **Verwendung**: Finanzen-Ressortleitung mit erweiterten Rechten

### Level 18: Geschäftsführung/Admin
- **Berechtigungen**: 
  - Sieht alles
  - Kann Benutzer anlegen
  - Daten pflegen
  - Alle Anträge/Freigaben bearbeiten
- **Verwendung**: Geschäftsführer, System-Administratoren
- **Wichtig**: Höchstes operatives Berechtigungslevel

### Level 19: Vorstandsmitglied
- **Berechtigungen**: 
  - Sieht alles, auch andere Ressorts
  - Alle Anträge/Freigaben
  - **Stimmrecht bei Vorstandsbeschlüssen**
- **Verwendung**: Gewählte Vorstandsmitglieder
- **Wichtig**: Höchstes Berechtigungslevel

---

## Funktionscodes

### Vorstand

| Code | Bezeichnung | Beschreibung |
|------|-------------|--------------|
| Vo   | Vorstandsmitglied | Gewähltes Vorstandsmitglied |
| FVo  | Vorstand Finanzen | Kassenwart, Finanzvorstand |
| FVv  | Vorstand + Vertreter Finanzvorstand | Doppelfunktion |
| GF   | Geschäftsführer | Operative Leitung |
| VA   | Vorstandsassistenz | Vorstandsrechte außer Abstimmung |

### Leitungsfunktionen

| Code | Bezeichnung | Beschreibung |
|------|-------------|--------------|
| RL   | Ressortleiter | Leitung eines Ressorts |
| PL   | Projektleiter | Leitung eines Projekts |
| JT   | Organisator Jahrestreffen | Spezielle Leitungsfunktion |
| TM   | Teamleiter | Leitung eines Teams |
| FP   | Finanzprüfer/Kassenprüfer | Prüfungsfunktion |

### Unterstützung

| Code | Bezeichnung | Beschreibung |
|------|-------------|--------------|
| SV   | Sekretariat Vorstand | Unterstützung Vorstand |
| MB   | Mitgliederbetreuung | Verwaltung Mitgliederdaten |
| Ka   | Kassenführung | Finanzielle Verwaltung |
| Orga | Hilfsfunktion Antragstellung | Unterstützung bei Anträgen |
| AD   | Technischer Admin | IT-Administration |

### Ehemalige (Aufbewahrungsfrist)

| Code | Bezeichnung | Beschreibung |
|------|-------------|--------------|
| Rx   | Ehemalige Ressortleitung | Aufbewahrungspflicht 10 Jahre |
| Vx   | Ehemaliges Vorstandsmitglied | Aufbewahrungspflicht 10 Jahre |
| Xx   | Früher berechtigtes Mitglied | Aufbewahrungspflicht 10 Jahre |

**Wichtig**: Berechtigte mit "x"-Funktionen dürfen erst nach 10 Jahren ohne Zugriff gelöscht werden (steuerliche Aufbewahrungsfrist). Dies wird durch `aktiv=0` oder niedriges aktiv-Level erreicht, während die historischen Daten erhalten bleiben.

---

## Kombination aktiv + Funktion

### Empfohlene Kombinationen

| Funktion | Typisches aktiv | Erklärung |
|----------|----------------|-----------|
| Vo, FVo, FVv | 19 | Vorstand mit vollem Stimmrecht |
| GF | 18 | Geschäftsführung mit Adminrechten |
| VA | 18 | Vorstandsassistenz ohne Stimmrecht |
| RL | 15-17 | Je nach Ressort (17 bei Finanzen) |
| PL | 12-13 | Mit oder ohne Budget |
| TM | 11 | Teamleitung |
| JT | 12-13 | Projektverantwortung |
| FP | 3 | Nur Lesezugriff |
| SV, MB, Ka, Orga | 8-11 | Je nach Verantwortungsbereich |
| AD | 18 | System-Admin-Rechte |
| Rx, Vx, Xx | 0 | Keine aktiven Rechte mehr |

### Sonderfälle

**Notvorstand** (temporär):
- Funktion: Vo
- aktiv: 16
- Besonderheit: Zeitlich begrenzt, alle Vorstandsrechte

**Finanzprüfer extern**:
- Funktion: FP
- aktiv: 3
- Besonderheit: Lesezugriff auf alles, keine Schreibrechte

**Temporäre Arbeitsgruppe**:
- Funktion: - (keine)
- aktiv: 10
- Besonderheit: Nur für Terminabstimmung

---

## Berechtigungsprüfung im Code

### Antragsberechtigungen (aus dem Proposal-System)

```php
// Beispiele aus dem Code:

// Interne Anträge sehen (>= 17)
if ($current_user['aktiv'] > 17) {
    // Darf interne Anträge sehen
}

// Stimmrecht bei Beschlüssen (>= 18)
if ($current_user['aktiv'] >= 18) {
    // Darf bei Vorstandsbeschlüssen abstimmen
}

// Admin-Funktionen (>= 19 oder Funktion GF)
if ($current_user['aktiv'] >= 19 || $current_user['funktion'] == 'GF') {
    // Darf Anträge bearbeiten/löschen
}

// Vorstandsassistenz-Check
if ($current_user['funktion'] == 'VA') {
    // Spezielle Rechte für Vorstandsassistenz
}
```

### Wichtige Regeln

1. **aktiv hat Vorrang**: Ein höheres aktiv-Level überschreibt Funktionsbeschränkungen
2. **Funktion ist additiv**: Manche Funktionen (GF, VA) geben zusätzliche Rechte unabhängig von aktiv
3. **Kombinationsprüfung**: Immer beide Felder prüfen für vollständige Berechtigungsprüfung

---

## Migration & Kompatibilität

### Standalone-Modus (svmembers)
- Felder: `aktiv INT`, `funktion VARCHAR(10)`
- Verwaltung: Admin-Interface in tab_admin.php
- Standard: `aktiv=1`, `funktion=NULL`

### Adapter-Modus (berechtigte)
- Felder: `aktiv INT`, `Funktion VARCHAR(10)` (Großschreibung!)
- Verwaltung: Extern im VTool-System
- Mapping: Automatisch über BerechtigteAdapter

### Konsistenz
Beide Modi nutzen identische Werte und Logik. Der Adapter normalisiert automatisch:
- `berechtigte.Funktion` → `funktion` (lowercase Feldname)
- Werte bleiben erhalten (GF, VA, RL, etc.)

---

## Hinweise für Administratoren

### Beim Anlegen neuer Mitglieder

1. **Grundregel**: Starten Sie mit niedrigem aktiv-Level (1) und erhöhen Sie bei Bedarf
2. **Funktion zuweisen**: Nur wenn organisatorisch relevant
3. **Vorstand**: Immer aktiv=19 + passende Funktion (Vo, FVo, etc.)
4. **Geschäftsführung**: aktiv=18 + Funktion=GF
5. **Externe**: aktiv=0-3 (nur Lesezugriff)

### Beim Ausscheiden von Mitgliedern

1. **NICHT löschen!** (Aufbewahrungsfrist 10 Jahre)
2. **aktiv=0** setzen
3. **Funktion** auf "x"-Variante ändern (Rx, Vx, Xx)
4. Nach 10 Jahren: Manuelles Löschen durch Admin in Datenbank

### Sicherheit

- **Prinzip der minimalen Rechte**: Nur so viele Rechte wie nötig
- **Regelmäßige Prüfung**: Mindestens jährlich aktiv-Levels überprüfen
- **Vier-Augen-Prinzip**: Änderungen an Level 18-19 sollten dokumentiert werden

---

*Zuletzt aktualisiert: 27. Mai 2026*
