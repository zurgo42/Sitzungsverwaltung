# VTool Import - Schritt für Schritt Anleitung

## Ihre Situation
- VTool liegt auf Ihrem Windows-System unter: `D:\xampp\htdocs\[vtool-verzeichnis]`
- Ich arbeite in einer Linux-Umgebung unter: `/home/user/Sitzungsverwaltung`
- Wir müssen die Dateien von Windows zu Linux übertragen

## Lösung: 3 einfache Wege

---

## ⭐ Weg 1: Direkt im Chat teilen (EMPFOHLEN für erste Analyse)

**Am einfachsten für den Start:**

1. Öffnen Sie die wichtigsten VTool-Dateien in Ihrem Editor
2. Kopieren Sie den Inhalt hier in den Chat

**Welche Dateien brauche ich zuerst?**
```
📄 Antragsbearbeitung: antrag.php oder antraege.php (oder ähnlich)
📄 Abstimmung: abstimmung.php oder voting.php (oder ähnlich)
📄 Datenbank-Schema: Tabellen für Anträge und Abstimmungen
📄 Config (optional): config.php (nur Struktur, KEINE echten Passwörter)
```

**Vorgehen:**
```
Nachricht 1: "Hier ist die Antragsbearbeitungs-Datei:"
[Inhalt von antrag.php]

Nachricht 2: "Hier ist die Abstimmungs-Datei:"
[Inhalt von abstimmung.php]

Nachricht 3: "Hier sind die relevanten Datenbank-Tabellen:"
SHOW CREATE TABLE antraege;
SHOW CREATE TABLE abstimmungen;
[etc.]
```

**Vorteile:**
- ✓ Sofort möglich, kein technisches Setup nötig
- ✓ Sie behalten die Kontrolle (sehen, was Sie teilen)
- ✓ Können Credentials vorher manuell entfernen

---

## 🔧 Weg 2: Mit WSL (Windows Subsystem for Linux)

**Wenn Sie WSL installiert haben:**

### Schritt 1: WSL öffnen
- Drücken Sie `Windows + R`
- Tippen Sie: `wsl` oder `ubuntu` (je nach Installation)
- Enter drücken

### Schritt 2: Prüfen, ob Windows-Laufwerk verfügbar ist
```bash
ls /mnt/d/xampp/htdocs/
```

**Sehen Sie Ihre Verzeichnisse?**
- ✓ Ja → Weiter zu Schritt 3
- ✗ Nein → Probieren Sie: `ls /mnt/c/xampp/htdocs/` (C-Laufwerk)
- ✗ Immer noch nein → Verwenden Sie Weg 1 oder Weg 3

### Schritt 3: Zum Projekt-Verzeichnis navigieren
```bash
cd /home/user/Sitzungsverwaltung
```

### Schritt 4: Sanitisierungs-Skript ausführen
```bash
./sanitize_vtool.sh /mnt/d/xampp/htdocs/[IHR-VTOOL-VERZEICHNIS]
```

**Beispiel:** Wenn VTool unter `D:\xampp\htdocs\vtool` liegt:
```bash
./sanitize_vtool.sh /mnt/d/xampp/htdocs/vtool
```

### Schritt 5: Prüfen Sie das Ergebnis
```bash
ls -la vtool-reference/
```

### Schritt 6: Manuell nachprüfen
```bash
# Suche nach übersehenen Passwörtern
grep -r "password\|secret" vtool-reference/ --include="*.php" | grep -v BEISPIEL

# Wenn etwas gefunden wird, das noch echt aussieht:
nano vtool-reference/pfad/zur/datei.php  # Manuell editieren
```

### Schritt 7: Mir Bescheid sagen
Schreiben Sie hier im Chat: "VTool ist importiert und bereinigt, kannst du jetzt analysieren."

---

## 📁 Weg 3: Manuelles Kopieren per ZIP

**Falls WSL nicht funktioniert:**

### Schritt 1: VTool-Verzeichnis als ZIP packen
- Rechtsklick auf Ihr VTool-Verzeichnis
- "Senden an" → "ZIP-komprimierter Ordner"
- Nennen Sie es: `vtool-export.zip`

### Schritt 2: Credentials manuell entfernen

Öffnen Sie vorher diese Dateien und ersetzen Sie Passwörter:

**In `config.php`:**
```php
// Vorher:
define('DB_PASS', 'mein-echtes-passwort');

// Ändern zu:
define('DB_PASS', 'BEISPIEL_PASSWORT');
```

**In `.htaccess` (falls vorhanden):**
```apache
# Vorher:
AuthLDAPBindPassword "echt3s-passwort"

# Ändern zu:
AuthLDAPBindPassword "BEISPIEL_PASSWORT"
```

### Schritt 3: ZIP in Linux-Umgebung entpacken

**Falls Sie Zugriff auf das Linux-System haben:**
```bash
cd /home/user/Sitzungsverwaltung
mkdir vtool-reference
unzip /pfad/zu/vtool-export.zip -d vtool-reference/
```

**Oder:** Teilen Sie mir einzelne wichtige Dateien direkt im Chat (siehe Weg 1)

---

## ❓ Was ist mit SQL-Dateien?

**Falls Sie SQL-Dumps haben:**

### Option A: Schema ohne Daten exportieren (EMPFOHLEN)
In phpMyAdmin:
1. Datenbank auswählen
2. "Exportieren" → "Angepasst"
3. ✓ "Struktur" ankreuzen
4. ✗ "Daten" NICHT ankreuzen
5. Nur Tabellen auswählen, die mit Anträgen/Abstimmungen zu tun haben
6. Export starten
7. Datei hier im Chat teilen

### Option B: Mit anonymisierten Beispieldaten
```sql
-- Statt echter Daten:
INSERT INTO antraege (titel, antragsteller, ...) VALUES
('Beispiel-Antrag 1', 'Max Mustermann', ...),
('Beispiel-Antrag 2', 'Erika Beispiel', ...);
```

---

## 🎯 Was passiert danach?

Sobald ich die VTool-Dateien habe:

1. **Analyse** (~30 Min)
   - Verstehe die aktuelle Struktur
   - Identifiziere Requirements
   - Erkenne vereinsspezifische Teile

2. **Plan erstellen** (~20 Min)
   - Datenbank-Schema für Sitzungsverwaltung
   - Modulstruktur (process_*.php, tab_*.php)
   - Konfigurations-Optionen
   - Migrations-Strategie

3. **Besprechung mit Ihnen** (~10 Min)
   - Plan vorstellen
   - Prioritäten klären
   - Offene Fragen

4. **Implementierung** (mehrere Sessions)
   - Schrittweise Umsetzung
   - Tests
   - Dokumentation

---

## 🆘 Probleme?

**"Ich finde die WSL nicht"**
→ Verwenden Sie Weg 1 (direkt im Chat)

**"Das Skript sagt 'Permission denied'"**
→ Führen Sie aus: `chmod +x sanitize_vtool.sh`

**"Ich weiß nicht, welche Dateien wichtig sind"**
→ Schicken Sie mir eine Liste: `ls D:\xampp\htdocs\vtool`
   (oder Screenshot des Verzeichnisses)

**"Ich bin mir unsicher wegen Credentials"**
→ Kein Problem! Teilen Sie NUR die Dateinamen, dann besprechen wir,
   wie Sie sie sicher sanitisieren

---

## ✅ Zusammenfassung - Was SIE jetzt tun:

**Schnellstart (5 Minuten):**
1. Öffnen Sie die Hauptdateien für Anträge und Abstimmungen
2. Ersetzen Sie sichtbare Passwörter durch "BEISPIEL_PASSWORT"
3. Kopieren Sie den Inhalt hier in den Chat
4. Fertig!

**Mit Script (15 Minuten):**
1. WSL öffnen
2. `cd /home/user/Sitzungsverwaltung`
3. `./sanitize_vtool.sh /mnt/d/xampp/htdocs/vtool`
4. Mir Bescheid sagen

Welchen Weg möchten Sie gehen?
