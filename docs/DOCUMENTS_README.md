# 📁 Dokumentenverwaltung - Installation & Verwendung

**Version:** 1.0
**Erstellt:** 18.11.2025
**Modernisierte Version des alten Dokumentenverwaltungs-Systems**

## 📋 Übersicht

Die **Dokumentenverwaltung** ist ein modernes System zur Verwaltung von Vereinsdokumenten mit:

✅ **Features:**
- Dokumente hochladen (PDF, DOC, XLS, Bilder, etc.)
- Kategorisierung (Satzung, Ordnungen, Formulare, MV-Unterlagen, etc.)
- Zugriffskontrolle nach Rollen
- Volltextsuche in Titel, Beschreibung und Stichworten
- Filter und Sortierung
- Versionierung
- Kurz-URLs für wichtige Dokumente
- Download-Tracking
- Responsive Design (Mobile + Desktop)
- Admin-Bereich zum Verwalten

## 🚀 Installation

### 1. Datenbank einrichten

```bash
# In MySQL/MariaDB einloggen
mysql -u root -p

# SQL-Schema ausführen
mysql -u root -p DATENBANK_NAME < schema_documents.sql
```

Oder manuell via phpMyAdmin: `schema_documents.sql` importieren

### 2. Upload-Verzeichnis erstellen

```bash
# Im Projektverzeichnis
mkdir -p uploads/documents
chmod 755 uploads/documents
```

**Wichtig:** Das Verzeichnis muss vom Webserver beschreibbar sein!

### 3. Integration in index.php

Füge in `index.php` den neuen Tab hinzu:

```php
// In der Tab-Liste (ca. Zeile 100)
$allowed_tabs = [
    // ... bestehende Tabs
    'documents' => ['label' => '📁 Dokumente', 'file' => 'tab_documents.php'],
];
```

Oder nutze das bereitgestellte Update-Script (siehe unten).

### 4. Testen

```
http://localhost/Sitzungsverwaltung/?tab=documents
```

## 📖 Verwendung

### Für Mitglieder

1. **Dokumente ansehen:**
   - Tab "Dokumente" aufrufen
   - Nach Kategorien filtern
   - Suchbegriff eingeben
   - Dokument anklicken zum Download

2. **Suche:**
   - Suchfeld nutzt Volltextsuche
   - Durchsucht Titel, Beschreibung und Stichworte
   - Kombinierbar mit Kategorie-Filter

### Für Admins

1. **Dokument hochladen:**
   - "Dokument hochladen" Button klicken
   - Datei auswählen (max. 50 MB empfohlen)
   - Metadaten eingeben:
     - **Titel** (Pflicht): Aussagekräftiger Name
     - **Kategorie** (Pflicht): Satzung, Ordnungen, etc.
     - **Beschreibung**: Ausführliche Erklärung
     - **Version**: z.B. "2025" oder "v1.2"
     - **Zugriffslevel**: Wer darf das Dokument sehen?
     - **Stichworte**: Für bessere Suche
     - **Kurz-URL**: Optional, für externe Links

2. **Dokument bearbeiten:**
   - Stift-Icon in der Dokumentkarte klicken
   - Metadaten ändern
   - Status ändern (aktiv/archiviert/versteckt/veraltet)
   - Speichern

3. **Dokument löschen:**
   - Bearbeiten-Ansicht öffnen
   - "Löschen" Button
   - Wählen:
     - **Verstecken**: Dokument wird ausgeblendet, kann wiederhergestellt werden
     - **Permanent löschen**: Datei wird vom Server gelöscht (nicht rückgängig!)

4. **Bulk-Aktionen:**
   - Mehrere Dokumente auswählen
   - Aktion wählen (Archivieren, Aktivieren, Verstecken, Löschen)
   - Anwenden

## 🔐 Zugriffskontrolle

Die Zugriffskontrolle basiert auf dem bestehenden Rollen-System:

| Level | Rolle | Zugriff |
|-------|-------|---------|
| 0 | Alle Mitglieder | Öffentliche Dokumente |
| 12 | Projektleitung | Projekt-Dokumente |
| 15 | Ressortleitung | Ressort-Dokumente |
| 18 | Assistenz | Interne Dokumente |
| 19 | Vorstand | Vertrauliche Dokumente |

**Beispiel:**
- Ein Dokument mit Level 15 kann von Ressortleitung, Assistenz und Vorstand gesehen werden
- Ein Dokument mit Level 0 sehen alle Mitglieder

## 📂 Dateistruktur

```
/Sitzungsverwaltung
├── documents_functions.php      # Hilfsfunktionen
├── process_documents.php        # Backend (POST-Handler)
├── tab_documents.php            # Frontend (UI)
├── download_document.php        # Download-Handler mit Tracking
├── schema_documents.sql         # Datenbank-Schema
├── DOCUMENTS_README.md          # Diese Datei
└── uploads/
    └── documents/               # Hochgeladene Dateien
        ├── doc_20251118_xxxxx.pdf
        └── doc_20251118_yyyyy.docx
```

## 🗄️ Datenbank-Struktur

### Tabelle: `documents`

```sql
CREATE TABLE documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,           -- Eindeutiger Dateiname auf Server
    original_filename VARCHAR(255) NOT NULL,  -- Original-Dateiname des Uploads
    filepath VARCHAR(500) NOT NULL,           -- Relativer Pfad zur Datei
    filesize INT NOT NULL DEFAULT 0,          -- Größe in Bytes
    filetype VARCHAR(50) NOT NULL,            -- Dateierweiterung
    title VARCHAR(255) NOT NULL,              -- Anzeige-Titel
    description TEXT,                         -- Beschreibung
    keywords TEXT,                            -- Suchbegriffe
    version VARCHAR(50),                      -- Versionsnummer
    short_url VARCHAR(255),                   -- Kurz-URL (optional)
    category ENUM(...),                       -- Kategorie
    access_level INT DEFAULT 0,               -- Zugriffslevel
    status ENUM(...) DEFAULT 'active',        -- Status
    uploaded_by_member_id INT,                -- Uploader
    created_at DATETIME NOT NULL,             -- Upload-Datum
    updated_at DATETIME,                      -- Letzte Änderung
    admin_notes TEXT                          -- Admin-Notizen
);
```

### Tabelle: `document_downloads`

```sql
CREATE TABLE document_downloads (
    download_id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    member_id INT,
    downloaded_at DATETIME NOT NULL,
    ip_address VARCHAR(45)
);
```

## 🎨 Design & Look&Feel

Das System nutzt das **moderne Design** der Sitzungsverwaltung:

- ✅ Bootstrap 5 (Cards, Buttons, Forms)
- ✅ Responsive Grid-Layout
- ✅ Mobile-first Design
- ✅ Konsistente Farbgebung
- ✅ Icons (Bootstrap Icons)
- ✅ Smooth Animations
- ✅ Accessibility (ARIA-Labels)

**Anpassungen:**
- CSS kann in `styles.css` überschrieben werden
- Farben passen sich automatisch an bestehende Theme an

## 🔄 Migration vom alten System

Falls du vom alten `dokumente.php` migrierst:

### Automatische Migration (geplant)

Ein Migrations-Script kann erstellt werden, das:
1. Alte Einträge aus der `dokumente`-Tabelle liest
2. In neues Schema konvertiert
3. Dateien ins neue Verzeichnis kopiert

### Manuelle Migration

1. **Dokumente einzeln neu hochladen** (empfohlen für kleine Mengen)
2. **Datenbank-Mapping:**
   - `name` → `original_filename`
   - `titel` → `title`
   - `beschreibung` → `description`
   - `stichworte` → `keywords`
   - `k1` → `category` (Mapping: 1→satzung, 2→ordnungen, etc.)
   - `zugriff` → `access_level`

## 🧪 Testen

### Test-Checkliste

- [ ] Dokument hochladen (PDF, DOC, XLS)
- [ ] Dokument herunterladen
- [ ] Suche funktioniert
- [ ] Filter nach Kategorie
- [ ] Sortierung (Datum, Titel, Kategorie)
- [ ] Zugriffskontrolle (als verschiedene Rollen testen)
- [ ] Dokument bearbeiten
- [ ] Dokument löschen/verstecken
- [ ] Mobile-Ansicht
- [ ] Download-Tracking

### Test-Accounts

Teste mit verschiedenen Rollen:
- Mitglied (Level 0)
- Ressortleitung (Level 15)
- Assistenz (Level 18)
- Vorstand (Level 19)

## 🐛 Fehlersuche

### Problem: Upload schlägt fehl

**Lösung:**
1. Prüfe Upload-Verzeichnis: `ls -la uploads/documents`
2. Prüfe Berechtigungen: `chmod 755 uploads/documents`
3. Prüfe PHP-Einstellungen:
   ```ini
   upload_max_filesize = 50M
   post_max_size = 50M
   ```

### Problem: Dokumente werden nicht angezeigt

**Lösung:**
1. Prüfe Datenbankverbindung
2. Prüfe Zugriffslevel des Users: `SELECT role FROM members WHERE member_id = X`
3. Prüfe Dokument-Status: `SELECT status FROM documents WHERE document_id = Y`

### Problem: Download funktioniert nicht

**Lösung:**
1. Prüfe ob Datei existiert: `ls uploads/documents/`
2. Prüfe `download_document.php` Logs
3. Prüfe Browser-Konsole für Fehler

## 📊 Statistiken

### Download-Statistiken abrufen

```sql
-- Top 10 Downloads
SELECT
    d.title,
    COUNT(dd.download_id) as downloads
FROM documents d
LEFT JOIN document_downloads dd ON d.document_id = dd.document_id
GROUP BY d.document_id
ORDER BY downloads DESC
LIMIT 10;
```

### Speicherplatz

```sql
-- Gesamtspeicher
SELECT
    SUM(filesize) / 1024 / 1024 as total_mb,
    COUNT(*) as total_documents
FROM documents
WHERE status = 'active';
```

## 🔒 Sicherheit

### Best Practices

1. **Datei-Validierung:**
   - Nur erlaubte Dateitypen: Siehe `get_allowed_file_types()`
   - Dateiname wird sanitized
   - Eindeutige Namen (uniqid)

2. **Zugriffskontrolle:**
   - Jeder Download wird geprüft
   - Session-basierte Auth
   - Role-basierte Permissions

3. **SQL-Injection:**
   - Prepared Statements (PDO)
   - Input-Validierung

4. **XSS:**
   - `htmlspecialchars()` auf allen Ausgaben
   - Content-Security-Policy empfohlen

5. **Uploads:**
   - Max. Dateigröße limitiert
   - Upload außerhalb von public_html (empfohlen)

## 🚧 Geplante Features

- [ ] Bulk-Upload (mehrere Dateien gleichzeitig)
- [ ] Drag & Drop Upload
- [ ] Dokument-Vorschau (PDF im Browser)
- [ ] Versionsverwaltung (Historie)
- [ ] Tags statt/zusätzlich zu Kategorien
- [ ] E-Mail-Benachrichtigung bei neuen Dokumenten
- [ ] Export als ZIP
- [ ] OCR für durchsuchbare PDFs
- [ ] Wasserzeichen für vertrauliche Dokumente

## 📝 Changelog

### Version 1.0 (18.11.2025)
- ✅ Initial Release
- ✅ Modernes Design (Bootstrap 5)
- ✅ Upload-Funktion
- ✅ Kategorisierung
- ✅ Zugriffskontrolle
- ✅ Suche & Filter
- ✅ Download-Tracking
- ✅ Admin-Bereich
- ✅ Mobile-Support

---

**Bei Fragen oder Problemen:** Siehe DEVELOPER.md oder kontaktiere den Admin

**Viel Erfolg! 🚀**
