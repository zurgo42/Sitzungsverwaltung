#!/bin/bash
# Sanitisierungs-Skript für VTool-Verzeichnis
# Entfernt Credentials und sensitive Daten vor der Analyse

set -e

SOURCE_DIR="$1"
TARGET_DIR="/home/user/Sitzungsverwaltung/vtool-reference"

if [ -z "$SOURCE_DIR" ]; then
    echo "Verwendung: $0 /pfad/zum/vtool-verzeichnis"
    echo "Beispiel: $0 /mnt/d/xampp/htdocs/vtool"
    exit 1
fi

if [ ! -d "$SOURCE_DIR" ]; then
    echo "Fehler: Verzeichnis $SOURCE_DIR existiert nicht"
    exit 1
fi

echo "=== VTool Sanitisierungs-Script ==="
echo "Quelle: $SOURCE_DIR"
echo "Ziel: $TARGET_DIR"
echo ""

# Zielverzeichnis erstellen
mkdir -p "$TARGET_DIR"

# Liste der zu ignorierenden Dateien/Verzeichnisse
EXCLUDE_PATTERNS=(
    "*.log"
    "*.bak"
    "*.tmp"
    "*~"
    ".git"
    "vendor"
    "node_modules"
)

echo "Kopiere Dateien..."
rsync -av \
    --exclude='*.log' \
    --exclude='*.bak' \
    --exclude='*.tmp' \
    --exclude='*~' \
    --exclude='.git' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    "$SOURCE_DIR/" "$TARGET_DIR/"

echo ""
echo "Sanitisiere Konfigurationsdateien..."

# Funktion zum Sanitisieren von PHP-Config-Dateien
sanitize_php_config() {
    local file="$1"
    if [ -f "$file" ]; then
        echo "  - Sanitisiere: $file"
        # Erstelle Backup
        cp "$file" "$file.original"

        # Ersetze Credentials mit Platzhaltern
        sed -i "s/define('DB_USER'[^)]*)/define('DB_USER', 'BEISPIEL_USER')/g" "$file"
        sed -i "s/define('DB_PASS'[^)]*)/define('DB_PASS', 'BEISPIEL_PASSWORT')/g" "$file"
        sed -i "s/define('DB_PASSWORD'[^)]*)/define('DB_PASSWORD', 'BEISPIEL_PASSWORT')/g" "$file"
        sed -i "s/define('DB_NAME'[^)]*)/define('DB_NAME', 'beispiel_db')/g" "$file"
        sed -i "s/define('SMTP_PASS'[^)]*)/define('SMTP_PASS', 'BEISPIEL_SMTP_PASS')/g" "$file"
        sed -i "s/define('SMTP_PASSWORD'[^)]*)/define('SMTP_PASSWORD', 'BEISPIEL_SMTP_PASS')/g" "$file"
        sed -i "s/define('LDAP_BIND_PW'[^)]*)/define('LDAP_BIND_PW', 'BEISPIEL_LDAP_PASS')/g" "$file"
        sed -i "s/define('LDAP_PASSWORD'[^)]*)/define('LDAP_PASSWORD', 'BEISPIEL_LDAP_PASS')/g" "$file"
        sed -i "s/define('API_KEY'[^)]*)/define('API_KEY', 'BEISPIEL_API_KEY')/g" "$file"
        sed -i "s/define('SECRET_KEY'[^)]*)/define('SECRET_KEY', 'BEISPIEL_SECRET_KEY')/g" "$file"

        # Variablen-basierte Credentials
        sed -i "s/\\\$db_pass[[:space:]]*=[^;]*/\$db_pass = 'BEISPIEL_PASSWORT'/g" "$file"
        sed -i "s/\\\$db_password[[:space:]]*=[^;]*/\$db_password = 'BEISPIEL_PASSWORT'/g" "$file"
        sed -i "s/\\\$smtp_pass[[:space:]]*=[^;]*/\$smtp_pass = 'BEISPIEL_SMTP_PASS'/g" "$file"
        sed -i "s/\\\$ldap_password[[:space:]]*=[^;]*/\$ldap_password = 'BEISPIEL_LDAP_PASS'/g" "$file"

        # Umbenennen zu .example
        mv "$file" "${file}.example"
        echo "    → Umbenannt zu ${file}.example"
    fi
}

# Sanitisiere bekannte Config-Dateien
find "$TARGET_DIR" -name "config.php" -type f | while read file; do
    sanitize_php_config "$file"
done

find "$TARGET_DIR" -name "db_config.php" -type f | while read file; do
    sanitize_php_config "$file"
done

find "$TARGET_DIR" -name "settings.php" -type f | while read file; do
    sanitize_php_config "$file"
done

# .htaccess Dateien behandeln
echo ""
echo "Behandle .htaccess Dateien..."
find "$TARGET_DIR" -name ".htaccess" -type f | while read file; do
    echo "  - Sanitisiere: $file"
    # Entferne AuthLDAPBindPassword und ähnliche sensible Direktiven
    sed -i 's/AuthLDAPBindPassword .*/AuthLDAPBindPassword "BEISPIEL_LDAP_PASSWORD"/g' "$file"
    sed -i 's/AuthUserFile .*/AuthUserFile "\/path\/to\/htpasswd"/g' "$file"
    # Umbenennen
    mv "$file" "${file}.example"
    echo "    → Umbenannt zu ${file}.example"
done

# .env Dateien behandeln
echo ""
echo "Behandle .env Dateien..."
find "$TARGET_DIR" -name ".env" -type f | while read file; do
    echo "  - Sanitisiere: $file"
    sed -i 's/PASSWORD=.*/PASSWORD=BEISPIEL_PASSWORT/g' "$file"
    sed -i 's/SECRET=.*/SECRET=BEISPIEL_SECRET/g' "$file"
    sed -i 's/KEY=.*/KEY=BEISPIEL_KEY/g' "$file"
    sed -i 's/TOKEN=.*/TOKEN=BEISPIEL_TOKEN/g' "$file"
    mv "$file" "${file}.example"
    echo "    → Umbenannt zu ${file}.example"
done

# Original-Backups entfernen (enthalten echte Credentials)
echo ""
echo "Entferne Backup-Dateien mit Credentials..."
find "$TARGET_DIR" -name "*.original" -type f -delete

echo ""
echo "=== Sanitisierung abgeschlossen ==="
echo ""
echo "WICHTIG: Bitte prüfen Sie manuell:"
echo "  1. Dateien in: $TARGET_DIR"
echo "  2. Suchen Sie nach weiteren Credentials:"
echo "     grep -r 'password\\|secret\\|key' $TARGET_DIR --include='*.php' | less"
echo "  3. Falls SQL-Dumps vorhanden sind, prüfen Sie auf personenbezogene Daten"
echo ""
echo "Wenn alles OK ist, kann ich die Dateien analysieren."
