# Kollaborative Texte - System-Dokumentation

## 1. SYSTEM-ÜBERSICHT

### Zweck
Ermöglicht mehreren Benutzern gleichzeitig an einem Text zu arbeiten (ähnlich Google Docs).
- Absatz-basiertes Editing (nicht Zeichen-basiert wie Google Docs)
- Lock-System verhindert gleichzeitige Bearbeitung desselben Absatzes
- Echtzeit-Updates für alle Teilnehmer
- Online-User-Anzeige

### Zwei Modi
1. **Meeting-Modus**: Text gehört zu einer Sitzung → Alle Sitzungs-Teilnehmer haben Zugriff
2. **Allgemein-Modus**: Text ohne Sitzung → Vorstand/GF/Assistenz/Führungsteam haben Zugriff

---

## 2. DATENMODELL

### Tabellen

#### `svcollab_texts`
- `text_id` (PK)
- `meeting_id` (FK, nullable) - NULL = Allgemein-Modus
- `initiator_member_id` (FK) - Ersteller
- `title` - Titel
- `status` - 'active' oder 'finalized'
- `created_at`, `finalized_at`, `final_name`

#### `svcollab_text_paragraphs`
- `paragraph_id` (PK)
- `text_id` (FK)
- `paragraph_order` - Reihenfolge (1, 2, 3...)
- `content` - Text-Inhalt
- `last_edited_by` (FK), `last_edited_at`

#### `svcollab_text_locks`
- `lock_id` (PK)
- `paragraph_id` (FK)
- `member_id` (FK) - Wer hat den Lock?
- `locked_at`, `last_activity`
- **Timeout**: 2 Minuten Inaktivität → Lock wird automatisch gelöscht

#### `svcollab_text_participants`
- `text_id`, `member_id` (Composite PK)
- `last_seen` - Timestamp des letzten Heartbeats
- **Online-Status**: last_seen < 60 Sekunden = "online"

#### `svcollab_text_versions`
- `version_id` (PK)
- `text_id` (FK), `version_number`
- `content` - Snapshot des gesamten Textes
- `created_by` (FK), `created_at`, `version_note`

---

## 3. BENUTZER-FEATURES

### Schutz vor Datenverlust
- **Concurrent Editing Prevention**: User können nur einen Absatz gleichzeitig bearbeiten
  - Beim Versuch, einen zweiten Absatz zu öffnen, erscheint eine Warnung
  - Verhindert Datenverlust durch gleichzeitige Bearbeitung mehrerer Absätze
  - Implementiert über `editingParagraphId` Variable

### Text-Vorschau und Export
- **Text anzeigen**: Alle Absätze werden zusammengefügt angezeigt
- **In Zwischenablage kopieren**: Mit einem Klick den gesamten Text kopieren
  - Nutzt moderne Clipboard API (`navigator.clipboard.writeText()`)
  - Erfolgs-/Fehler-Feedback für den User
  - Ideal für Weiterverarbeitung in anderen Programmen

### Berechtigungen
- **Meeting-Modus**: Alle Sitzungsteilnehmer
- **Allgemein-Modus**:
  - Vorstand: Vollzugriff
  - Geschäftsführung: Vollzugriff
  - Assistenz: Vollzugriff
  - Führungsteam: Vollzugriff (neu seit Version 2025)
  - Mitglieder: Kein Zugriff auf allgemeine Texte

---

## 4. FRONTEND-ARCHITEKTUR

### JavaScript-Variablen (tab_texte.php)
```javascript
const TEXT_ID = <?php echo $text_id; ?>;
const CURRENT_USER_ID = <?php echo $current_user['member_id']; ?>;
let lastUpdate = new Date().toISOString();
let pollingInterval = null;       // Polling alle 1,5s
let heartbeatInterval = null;     // Heartbeat alle 15s
let editingParagraphId = null;    // Welcher Absatz wird gerade editiert?
```

### Zwei Hauptprozesse

#### A) **Polling-Loop** (alle 1,5 Sekunden)
```
startPolling()
  ↓
setInterval(fetchUpdates, 1500)
  ↓
GET api/collab_text_get_updates.php?text_id=X&since=TIMESTAMP
  ↓
Erhält:
- online_users[]       → updateOnlineUsers()
- paragraphs[]         → updateParagraphs()
- text_status          → ggf. Redirect zu Final-View
- server_time          → Aktualisiert lastUpdate
```

**Zweck**:
- Anzeige der Online-User aktualisieren
- Content-Updates von anderen Usern anzeigen
- Lock-Status anzeigen (wer bearbeitet welchen Absatz?)

#### B) **Heartbeat-Loop** (alle 15 Sekunden)
```
startHeartbeat()
  ↓
setInterval(sendHeartbeat, 15000)
  ↓
POST api/collab_text_heartbeat.php {text_id: X}
  ↓
Aktualisiert: svcollab_text_participants.last_seen = NOW()
```

**Zweck**:
- Anderen Usern signalisieren "Ich bin noch da"
- Ermöglicht Online-User-Erkennung

---

## 5. BACKEND-ARCHITEKTUR

### API-Endpoints (alle in `/api/`)

#### Lese-Operationen
1. **collab_text_get_updates.php** (GET)
   - Parameter: `text_id`, `since` (Timestamp)
   - Liefert: `online_users`, `paragraphs`, `text_status`, `server_time`
   - **Wird alle 1,5s aufgerufen!** → Muss SCHNELL sein

2. **collab_text_get_version.php** (GET)
   - Parameter: `text_id`, `version`
   - Liefert: Version-Daten für Vorschau

#### Schreib-Operationen
3. **collab_text_create.php** (POST)
   - Erstellt neuen Text + ersten Absatz + Teilnehmer + erste Version

4. **collab_text_heartbeat.php** (POST)
   - Aktualisiert `last_seen` Timestamp
   - **Wird alle 15s aufgerufen!** → Muss SCHNELL sein

5. **collab_text_lock_paragraph.php** (POST)
   - Versucht Lock zu erwerben
   - Löscht alte Locks (> 2 Min)
   - Liefert Fehler wenn bereits gelockt

6. **collab_text_save_paragraph.php** (POST)
   - Speichert Content
   - Gibt Lock frei
   - Nur erlaubt wenn User den Lock hat

7. **collab_text_add_paragraph.php** (POST)
   - Fügt neuen Absatz hinzu
   - Verschiebt paragraph_order der folgenden Absätze

8. **collab_text_delete_paragraph.php** (POST)
   - Löscht Absatz
   - Verschiebt paragraph_order

9. **collab_text_finalize.php** (POST)
   - Nur Initiator darf finalisieren
   - Erstellt finale Version
   - Löscht alle Locks
   - Setzt status='finalized'

10. **collab_text_create_version.php** (POST)
    - Erstellt Snapshot

### PHP-Funktionen (functions_collab_text.php)

**Core-Funktionen**:
- `createCollabText()` - Erstellt Text mit Transaction
- `getCollabText()` - Lädt Text mit allen Absätzen + Locks
- `lockParagraph()` - Lock-Erwerb mit Cleanup alter Locks
- `unlockParagraph()` - Lock-Freigabe
- `saveParagraph()` - Speichern + Lock freigeben
- `addParagraph()` - Einfügen mit Order-Verschiebung
- `deleteParagraph()` - Löschen mit Order-Verschiebung
- `updateParticipantHeartbeat()` - last_seen UPDATE
- `getOnlineParticipants()` - Alle mit last_seen < 60s
- `createTextVersion()` - Snapshot erstellen
- `finalizeCollabText()` - Text abschließen
- `hasCollabTextAccess()` - Zugriffsprüfung

---

## 6. PERFORMANCE-KRITISCHE PUNKTE

### Häufigste API-Calls (Pro User)
- **Polling**: 40 Requests/Minute (alle 1,5s)
- **Heartbeat**: 4 Requests/Minute (alle 15s)
- **Gesamt**: ~44 API-Requests pro Minute pro User!

### Mit 3 Usern gleichzeitig
- 132 API-Requests/Minute = **2,2 Requests/Sekunde**

### Performance-Anforderungen
| Operation | Max. Akzeptabel | Ziel |
|-----------|----------------|------|
| Polling (get_updates) | 200ms | <100ms |
| Heartbeat | 200ms | <50ms |
| Lock erwerben | 500ms | <200ms |
| Speichern | 1000ms | <300ms |

### Kritische Optimierungen
1. **Session schließen sofort nach Auth**
   ```php
   if (!isset($_SESSION['member_id'])) exit;
   $member_id = $_SESSION['member_id'];
   session_write_close();  // KRITISCH! Verhindert Session-Locking
   ```

2. **Leichtgewichtige DB-Verbindung**
   - `db_connection.php` statt `functions.php`
   - Vermeidet 2000ms Overhead

3. **Kein Debug-Logging in Produktion**
   - Jedes `error_log()` kostet Zeit

4. **Indizes auf kritischen Feldern**
   - `svcollab_text_participants(text_id, last_seen)`
   - `svcollab_text_locks(paragraph_id)`
   - `svcollab_text_paragraphs(text_id, paragraph_order)`

---

## 7. ABLAUF-SZENARIEN

### Szenario A: User öffnet Editor-View

**Schritt 1**: Server-seitiger PHP-Render (tab_texte.php)
```php
$text = getCollabText($pdo, $text_id);  // Lädt Text + Absätze + Locks
// Rendert HTML mit allen Absätzen
```

**Schritt 2**: JavaScript-Initialisierung
```javascript
document.addEventListener('DOMContentLoaded', function() {
    startPolling();     // Startet 1,5s Polling
    startHeartbeat();   // Startet 15s Heartbeat + sofort einmal senden
});
```

**Schritt 3**: Erste Heartbeat wird SOFORT gesendet
```javascript
sendHeartbeat();  // last_seen wird auf NOW() gesetzt
```

**Schritt 4**: Nach 1,5s erste Polling-Anfrage
```
GET get_updates.php → Zeigt Online-User an (inkl. sich selbst)
```

**Erwartetes Verhalten**:
- Nach 0s: Heartbeat gesendet → last_seen = NOW()
- Nach 1,5s: Polling → Online-User wird angezeigt (da last_seen < 60s)
- Alle 15s: Heartbeat → Bleibt online

---

### Szenario B: User bearbeitet Absatz

**Schritt 1**: User klickt "Bearbeiten"
```javascript
editParagraph(paragraphId)
  ↓
POST lock_paragraph.php {paragraph_id: X}
```

**Backend prüft**:
1. Alte Locks löschen (> 2 Min)
2. Existiert Lock für Absatz?
   - Ja, anderer User → Fehler mit Benutzername
   - Ja, eigener Lock → OK, last_activity updaten
   - Nein → Lock erwerben

**Schritt 2**: Bei Erfolg → Edit-Mode
```javascript
showEditMode(paragraphId)
  ↓
Textarea wird angezeigt
editingParagraphId = paragraphId  // Verhindert Update während Edit
```

**Schritt 3**: Während Bearbeitung
- **Polling läuft weiter**: Andere Absätze werden aktualisiert
- **Eigener Absatz NICHT**: Wegen `editingParagraphId` Check
- **Lock bleibt aktiv**: Timeout 2 Minuten

**Schritt 4**: User klickt "Speichern"
```javascript
saveParagraph(paragraphId)
  ↓
POST save_paragraph.php {paragraph_id: X, content: "..."}
```

**Backend**:
1. Prüft Lock-Besitz
2. UPDATE paragraph SET content=..., last_edited_at=NOW()
3. DELETE lock
4. Success

**Schritt 5**: Frontend nach Speichern
```javascript
editingParagraphId = null;  // Erlaubt wieder Updates
location.reload();          // Seite neu laden
```

**Erwartetes Timing**:
- Lock-Request: <200ms
- Save-Request: <300ms
- Reload: <1000ms
- **Gesamt**: ~1,5 Sekunden

---

### Szenario C: Mehrere User gleichzeitig

**User A** öffnet Editor:
- Heartbeat → last_seen = 10:00:00
- Polling → Sieht sich selbst als online

**User B** öffnet Editor (10 Sekunden später):
- Heartbeat → last_seen = 10:00:10
- Polling → Sieht User A + sich selbst

**User A** Polling (10:00:11):
- Empfängt online_users = [User A, User B]
- Anzeige aktualisiert: "User B" erscheint

**User A** bearbeitet Absatz 1:
- Lock erworben
- Polling von User B zeigt: "Absatz 1: 🔒 Wird bearbeitet von User A"

**User B** versucht Absatz 1 zu bearbeiten:
- Lock-Request → Fehler: "Dieser Absatz wird gerade von User A bearbeitet"

**User A** speichert Absatz 1:
- Lock wird freigegeben
- Nächstes Polling von User B: Absatz 1 Content aktualisiert, Lock weg

---

## 8. FEHLER-SZENARIEN & RECOVERY

### A) Heartbeat schlägt fehl
**Ursachen**:
- 401 Unauthorized → Session abgelaufen
- 500 Server Error → DB-Problem
- Network Error → Verbindungsproblem

**Aktuelles Verhalten**:
```javascript
.catch(err => console.error('Heartbeat error:', err));
// → Fehler wird nur geloggt, User merkt nichts
```

**Folge**:
- last_seen wird nicht aktualisiert
- Nach 60s: User erscheint als "offline" für andere
- User selbst arbeitet weiter (merkt nichts)

**Gewünschtes Verhalten**:
- Nach 3 aufeinanderfolgenden Fehlern → Warnung anzeigen
- Nach 5 Fehlern → "Verbindung unterbrochen" + Reload anbieten

### B) Lock-Timeout während Bearbeitung
**Szenario**:
- User bearbeitet Absatz
- Wird unterbrochen (Telefonat)
- Nach 2 Minuten: Lock läuft ab
- Anderer User erwirbt Lock
- Ursprünglicher User klickt "Speichern"

**Aktuelles Verhalten**:
```php
saveParagraph() → Prüft Lock → return false
API → 403 Error
```

**Gewünschtes Verhalten**:
- Fehlermeldung: "Der Absatz wurde gesperrt. Ihre Änderungen wurden nicht gespeichert."
- Content in Zwischenablage oder Download anbieten

### C) Gleichzeitiges Speichern
**Szenario**:
- User A hat Lock
- User B versucht gleichzeitig zu sperren

**Schutz**:
- Lock-Mechanismus verhindert dies
- DB-Level: paragraph_id ist UNIQUE in svcollab_text_locks

### D) Polling schlägt fehl
**Aktuelles Verhalten**:
```javascript
.catch(err => console.error('Polling error:', err));
// → Nächster Poll in 1,5s
```

**Problem**:
- Bei dauerhaftem Fehler: Keine Updates mehr
- User merkt nicht dass System "eingefroren" ist

---

## 9. PERFORMANCE-BOTTLENECKS

### Identifizierte Probleme

#### Problem 1: Session-Locking
**Was passiert**:
- PHP sperrt Session-File beim `session_start()`
- Parallel-Requests warten sequentiell
- Mit Polling + Heartbeat: Bis zu 3 Requests gleichzeitig → Schlange

**Lösung**:
```php
session_start();
$member_id = $_SESSION['member_id'];
session_write_close();  // Sofort schließen!
```

#### Problem 2: functions.php Overhead
**Was passiert**:
- `require_once('../functions.php')` lädt ~2000ms
- Lädt Dutzende Funktionen die nicht gebraucht werden
- Jeder API-Call zahlt diesen Preis

**Lösung**:
- Neue `db_connection.php` nur mit PDO-Init
- Von 2000ms auf ~1ms reduziert

#### Problem 3: Fehlende Indizes
**Queries ohne Index**:
```sql
-- get_updates.php
SELECT * FROM svcollab_text_participants
WHERE text_id = ? AND last_seen > DATE_SUB(NOW(), INTERVAL 60 SECOND)
-- Braucht: INDEX(text_id, last_seen)

-- get_updates.php
SELECT * FROM svcollab_text_paragraphs WHERE text_id = ? ORDER BY paragraph_order
-- Braucht: INDEX(text_id, paragraph_order)
```

#### Problem 4: N+1 Queries
**getCollabText()**:
```php
// 1 Query: Text-Metadaten
// 1 Query: Alle Absätze mit JOINs (OK)
```
→ Aktuell OK, keine N+1 Probleme

#### Problem 5: Polling-Frequenz
**Aktuell**: Alle 1,5 Sekunden
**Alternative**: Exponential Backoff
- Bei Aktivität: 1,5s
- Nach 1 Min Inaktivität: 3s
- Nach 5 Min: 10s

---

## 10. DIAGNOSE-CHECKLISTE

Wenn Performance-Probleme auftreten:

### Frontend-Checks
- [ ] Browser DevTools → Network Tab
  - Wie lange dauert get_updates.php?
  - Wie lange dauert heartbeat.php?
  - Gibt es 401/403/500 Fehler?
  - Werden Requests in Serie oder parallel abgearbeitet?

- [ ] Console-Errors
  - Heartbeat failures?
  - Polling errors?
  - JavaScript-Fehler?

### Backend-Checks
- [ ] DB-Indizes vorhanden?
  ```sql
  SHOW INDEX FROM svcollab_text_participants;
  SHOW INDEX FROM svcollab_text_paragraphs;
  SHOW INDEX FROM svcollab_text_locks;
  ```

- [ ] Session schließt sofort?
  ```php
  // In allen API-Files prüfen:
  session_write_close(); // Muss VOR allen langsamen Operationen sein
  ```

- [ ] Lightweight DB-Connection?
  ```php
  require_once('db_connection.php');  // Nicht functions.php!
  ```

- [ ] Slow Query Log
  ```sql
  SHOW VARIABLES LIKE 'slow_query_log';
  SET GLOBAL slow_query_log = 'ON';
  SET GLOBAL long_query_time = 0.1;  -- 100ms threshold
  ```

### System-Checks
- [ ] PHP OPcache enabled?
  ```bash
  php -i | grep opcache
  ```

- [ ] MySQL Query Cache?
  ```sql
  SHOW VARIABLES LIKE 'query_cache%';
  ```

- [ ] Ausreichend RAM?
  ```bash
  free -h
  ```

---

## 11. ERWARTETES SYSTEM-VERHALTEN

### Bei normalem Betrieb (1 User)
- Editor öffnen: <1 Sekunde
- Absatz bearbeiten (Lock): <200ms
- Absatz speichern: <300ms
- Polling-Response: <100ms
- Heartbeat-Response: <50ms
- Online-User erscheint: Nach max. 1,5s (erstes Polling)

### Bei normalem Betrieb (3 User parallel)
- Alle Timings wie oben
- Online-User-Liste zeigt alle 3 User
- Lock-Konflikte werden korrekt angezeigt
- Keine "undefined" in Meldungen
- Updates erscheinen bei allen innerhalb 1,5s

### Bei Edge-Cases
- Lock-Timeout (2 Min): Anderer User kann übernehmen
- User geht offline: Nach 60s verschwindet aus Online-Liste
- Finalisierung: Alle User werden zu Final-View umgeleitet

---

## 12. AKTUELLE PROBLEME (Stand: letzter Test)

### ✅ Behoben
- [x] 401 Unauthorized bei Heartbeat → Authentication-Bug gefixt
- [x] "undefined" in Lock-Meldungen → Null-Checks hinzugefügt
- [x] Session-Locking → session_write_close() überall
- [x] Datenverlust bei gleichzeitiger Bearbeitung mehrerer Absätze → Concurrent Editing Prevention
- [x] Umständliche Textvorschau → Copy-to-Clipboard Button hinzugefügt
- [x] Fehlende Berechtigungen für Führungsteam → Vollzugriff auf allgemeine Texte

### ❌ Offen
- [ ] **Performance immer noch langsam (>5 Sekunden)** (Falls zutreffend)
  - Bearbeiten-Button: >5 Sekunden bis Reaktion
  - Speichern: >7 Sekunden
  - Online-User: Erscheinen erst nach langer Wartezeit

### 🔍 Zu untersuchen
- Sind alle API-Endpoints wirklich schnell?
- Gibt es weitere Session-Probleme?
- Sind DB-Indizes vorhanden?
- Was zeigt der Browser Network-Tab?
- Was zeigt der MySQL Slow Query Log?

---

## 13. NÄCHSTE SCHRITTE

1. **Systematische Performance-Messung**
   - Network Tab: Jede API-Call-Dauer einzeln messen
   - PHP-Timing: Zeitstempel in APIs loggen
   - MySQL-Profiling: EXPLAIN auf kritische Queries

2. **DB-Indizes prüfen und erstellen**

3. **Bottleneck identifizieren**
   - Ist es PHP?
   - Ist es MySQL?
   - Ist es Session-Handling?
   - Ist es das Frontend (JavaScript)?

4. **Gezielt optimieren**
   - Nur das echte Problem fixen
   - Messen ob Verbesserung eingetreten ist
   - Nicht weiter "rumprobieren"
