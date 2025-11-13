# ?? Meeting-System - Installationsanleitung

Dieses Dokument erklärt, wie du das Meeting-Verwaltungssystem einrichtest und zum Laufen bringst.

---

## ?? Systemanforderungen

- **PHP** 7.4 oder höher
- **MySQL/MariaDB** 5.7 oder höher
- **Webserver** (Apache, Nginx, etc.)
- **Moderne Browser** (Chrome, Firefox, Safari, Edge)

---

## ?? Dateistruktur

```
meeting-system/
+-- index.php              # Hauptseite (Frontend)
+-- login.php              # Login-Seite
+-- api.php                # API-Endpoints
+-- session.php            # Session-Management (optional)
+-- init-db.php            # Datenbank-Initialisierung
+-- README.txt             # Diese Datei
```

---

## ?? Installation - Schritt für Schritt

### 1?? Webverzeichnis vorbereiten

```bash
# Erstelle ein neues Verzeichnis für das Projekt
mkdir meeting-system
cd meeting-system
```

### 2?? Dateien erstellen

Speichere die folgenden Dateien in dein `meeting-system` Verzeichnis:

- **login.php** - Die Login-Seite (HTML/PHP gemischt)
- **index.php** - Die Hauptanwendung (HTML/PHP gemischt)
- **api.php** - Die API (reines PHP)
- **session.php** - Session-Management (Optional, zur Anpassung)

### 3?? Datenbank erstellen

Öffne dein **phpMyAdmin** oder einen SQL-Client und führe folgende Befehle aus:

```sql
-- Datenbank erstellen
CREATE DATABASE meetings;
USE meetings;

-- Teilnehmer-Tabelle
CREATE TABLE IF NOT EXISTS participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(100),
    role ENUM('board', 'gf', 'assistant', 'leadership') NOT NULL
);

-- Sitzungen
CREATE TABLE IF NOT EXISTS meetings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inviter_id INT NOT NULL,
    meeting_date DATETIME NOT NULL,
    meeting_start DATETIME,
    meeting_end DATETIME,
    chairman_id INT,
    protocol_writer_id INT,
    status ENUM('preparing', 'running', 'editing', 'approved', 'archived') DEFAULT 'preparing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inviter_id) REFERENCES participants(id),
    FOREIGN KEY (chairman_id) REFERENCES participants(id),
    FOREIGN KEY (protocol_writer_id) REFERENCES participants(id)
);

-- Tagesordnung
CREATE TABLE IF NOT EXISTS meeting_agenda (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    topic_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority DECIMAL(3,1) DEFAULT 5,
    estimated_duration INT,
    protocol_content TEXT,
    is_locked BOOLEAN DEFAULT FALSE,
    is_confidential BOOLEAN DEFAULT FALSE,
    coupled_with INT,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id)
);

-- Diskussionsbeiträge
CREATE TABLE IF NOT EXISTS discussion_contributions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    agenda_id INT NOT NULL,
    participant_id INT NOT NULL,
    content TEXT,
    priority INT,
    suggested_duration INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agenda_id) REFERENCES meeting_agenda(id),
    FOREIGN KEY (participant_id) REFERENCES participants(id)
);

-- Sitzungsteilnehmer
CREATE TABLE IF NOT EXISTS meeting_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    participant_id INT NOT NULL,
    is_attending BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id),
    FOREIGN KEY (participant_id) REFERENCES participants(id),
    UNIQUE KEY (meeting_id, participant_id)
);

-- ToDos
CREATE TABLE IF NOT EXISTS todos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    assigned_to INT NOT NULL,
    assigned_by INT NOT NULL,
    description TEXT,
    due_date DATE,
    completed_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id),
    FOREIGN KEY (assigned_to) REFERENCES participants(id),
    FOREIGN KEY (assigned_by) REFERENCES participants(id)
);

-- Öffentliche Protokolle
CREATE TABLE IF NOT EXISTS protocols_public (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    chairman_id INT NOT NULL,
    protocol_writer_id INT NOT NULL,
    start_time DATETIME,
    end_time DATETIME,
    protocol_content LONGTEXT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FULLTEXT INDEX ft_content (protocol_content),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id)
);

-- Vertrauliche Protokolle
CREATE TABLE IF NOT EXISTS protocols_confidential (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    chairman_id INT NOT NULL,
    protocol_writer_id INT NOT NULL,
    protocol_content LONGTEXT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FULLTEXT INDEX ft_content (protocol_content),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id)
);

-- Protokoll-Änderungswünsche
CREATE TABLE IF NOT EXISTS protocol_change_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    participant_id INT NOT NULL,
    requested_change TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id),
    FOREIGN KEY (participant_id) REFERENCES participants(id)
);

-- Demo-Teilnehmer einfügen (für Testzwecke)
INSERT INTO participants (member_id, first_name, last_name, email, role) VALUES
(1, 'Max', 'Mustermann', 'max@example.com', 'board'),
(2, 'Erika', 'Musterfrau', 'erika@example.com', 'board'),
(3, 'Hans', 'Müller', 'hans@example.com', 'leadership'),
(4, 'Julia', 'Schmidt', 'julia@example.com', 'gf'),
(5, 'Thomas', 'Weber', 'thomas@example.com', 'assistant');
```

### 4?? Datenbank-Verbindung anpassen

Öffne die Datei **api.php** und passe folgende Zeilen an (Zeile ~25):

```php
$db_host = "localhost";      // Dein MySQL-Host
$db_user = "root";           // Dein MySQL-Benutzer
$db_pass = "";               // Dein MySQL-Passwort
$db_name = "meetings";       // Name der Datenbank
```

### 5?? Im Browser testen

1. Öffne deinen Browser
2. Gehe zu: `http://localhost/meeting-system/login.php`
3. Melde dich an mit:
   - **Email:** `max@example.com`
   - **Passwort:** `test123`

---