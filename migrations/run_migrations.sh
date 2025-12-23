#!/bin/bash
# Migrations für Production Deployment
# Alle Migrationen dieser Session ausführen

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DB_NAME="Sitzungsverwaltung"

echo "==================================="
echo "Sitzungsverwaltung - Migrationen"
echo "==================================="
echo ""

# Prüfen ob MySQL erreichbar ist
echo "Prüfe MySQL-Verbindung..."
mysql -u root -p -e "SELECT 1" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "❌ Fehler: Konnte keine Verbindung zu MySQL herstellen."
    echo "Bitte stelle sicher, dass MySQL läuft und die Zugangsdaten korrekt sind."
    exit 1
fi
echo "✓ MySQL-Verbindung OK"
echo ""

# Migration 1: Externe Teilnehmer
echo "-----------------------------------"
echo "Migration 1: Externe Teilnehmer"
echo "-----------------------------------"
echo "Datei: add_external_participants.sql"
echo ""
echo "Diese Migration fügt hinzu:"
echo "  - Tabelle: svexternal_participants"
echo "  - Spalte: svopinion_responses.external_participant_id"
echo "  - Spalte: svpoll_responses.external_participant_id"
echo ""
read -p "Migration ausführen? (j/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Jj]$ ]]; then
    mysql -u root -p "$DB_NAME" < "$SCRIPT_DIR/add_external_participants.sql"
    if [ $? -eq 0 ]; then
        echo "✓ Migration erfolgreich"
    else
        echo "❌ Migration fehlgeschlagen"
        exit 1
    fi
else
    echo "⊘ Übersprungen"
fi
echo ""

# Migration 2: Externe Dokument-Links
echo "-----------------------------------"
echo "Migration 2: Externe Dokument-Links"
echo "-----------------------------------"
echo "Datei: add_external_url_to_documents.sql"
echo ""
echo "Diese Migration fügt hinzu:"
echo "  - Spalte: svdocuments.external_url"
echo "  - Ändert: filepath, filename, filesize auf NULL erlaubt"
echo ""
read -p "Migration ausführen? (j/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Jj]$ ]]; then
    mysql -u root -p "$DB_NAME" < "$SCRIPT_DIR/add_external_url_to_documents.sql"
    if [ $? -eq 0 ]; then
        echo "✓ Migration erfolgreich"
    else
        echo "❌ Migration fehlgeschlagen"
        exit 1
    fi
else
    echo "⊘ Übersprungen"
fi
echo ""

# Optional: Weitere Migrationen
echo "-----------------------------------"
echo "Optionale Migrationen"
echo "-----------------------------------"
echo ""

# Migration 3: Target Type für Polls (optional)
echo "Migration 3: Target Type für Polls (optional)"
echo "Datei: add_target_type_to_polls.sql"
echo "Wird nur benötigt, wenn die Terminplanung genutzt wird"
echo ""
read -p "Migration ausführen? (j/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Jj]$ ]]; then
    mysql -u root -p "$DB_NAME" < "$SCRIPT_DIR/add_target_type_to_polls.sql"
    if [ $? -eq 0 ]; then
        echo "✓ Migration erfolgreich"
    else
        echo "❌ Migration fehlgeschlagen (evtl. bereits vorhanden)"
    fi
else
    echo "⊘ Übersprungen"
fi
echo ""

echo "==================================="
echo "Alle Migrationen abgeschlossen!"
echo "==================================="
echo ""
echo "Nächste Schritte:"
echo "1. Prüfe die Logs auf Fehler"
echo "2. Teste die neuen Features im Browser"
echo "3. Optional: Richte Cron-Job ein für Cleanup"
echo ""
