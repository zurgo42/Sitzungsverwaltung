# VTool Import - Quick Start

## 🚀 Schnellste Methode (2 Minuten)

### Was Sie brauchen:
```
✓ Einen Texteditor (Notepad++, VS Code, oder einfach Notepad)
✓ Zugriff auf Ihr VTool-Verzeichnis
```

### Schritt 1: Dateien öffnen
Navigieren Sie zu: `D:\xampp\htdocs\[vtool-verzeichnis]`

Öffnen Sie diese Dateien:
- Die Datei für Antragsbearbeitung (z.B. `antrag.php`, `antraege.php`)
- Die Datei für Abstimmungen (z.B. `abstimmung.php`, `voting.php`)

### Schritt 2: Passwörter entfernen (falls sichtbar)
Suchen Sie nach (Strg+F):
- `password`
- `DB_PASS`
- `secret`

Ersetzen Sie echte Werte durch: `BEISPIEL_PASSWORT`

### Schritt 3: Im Chat teilen
Kopieren Sie den Dateiinhalt und senden Sie hier:

```
Nachricht: "Hier ist die Antragsbearbeitung:"
[STRG+A, STRG+C, STRG+V]

Nachricht: "Hier ist die Abstimmung:"
[STRG+A, STRG+C, STRG+V]
```

### Fertig! ✓
Ich kann jetzt mit der Analyse beginnen.

---

## 💡 Nicht sicher, welche Dateien?

Senden Sie mir einfach eine **Liste aller Dateien**:

### Windows Eingabeaufforderung:
```cmd
cd D:\xampp\htdocs\vtool
dir /b
```

### Oder: Screenshot
Machen Sie einen Screenshot des Verzeichnisses und beschreiben Sie, was Sie sehen.

---

## 🔍 Beispiel, wie ich die Dateien brauche:

**Nachricht 1:**
```
Hier ist antrag.php:

<?php
require_once 'config.php';

// Antragsbearbeitung
if ($_POST['action'] == 'neuer_antrag') {
    // ... Code ...
}
// ... rest des Codes ...
?>
```

**Nachricht 2:**
```
Hier ist abstimmung.php:

<?php
require_once 'config.php';

// Abstimmung durchführen
if ($_POST['vote']) {
    // ... Code ...
}
// ... rest des Codes ...
?>
```

**Nachricht 3:**
```
Hier sind die Datenbank-Tabellen (aus phpMyAdmin Export):

CREATE TABLE antraege (
    id INT PRIMARY KEY,
    titel VARCHAR(255),
    ...
);

CREATE TABLE abstimmungen (
    id INT PRIMARY KEY,
    ...
);
```

---

## ⚡ Zu lang für eine Nachricht?

**Kein Problem!** Teilen Sie große Dateien auf:

```
Nachricht: "antrag.php - Teil 1 von 3"
[Erste 100 Zeilen]

Nachricht: "antrag.php - Teil 2 von 3"
[Nächste 100 Zeilen]

Nachricht: "antrag.php - Teil 3 von 3"
[Rest]
```

Oder sagen Sie mir: "Die Datei ist sehr groß (2000 Zeilen), welche Teile sind wichtig?"

---

## Das war's!

Sie brauchen:
- ❌ Kein Linux
- ❌ Kein WSL
- ❌ Keine komplizierte Installation
- ✅ Nur: Editor → Kopieren → Einfügen

Los geht's! 🎯
