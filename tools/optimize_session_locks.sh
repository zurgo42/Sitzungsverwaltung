#!/bin/bash
# Fügt session_write_close() zu allen collab_text API-Endpunkten hinzu

cd /home/user/Sitzungsverwaltung/api

for file in collab_text_*.php; do
    # Skip if already contains session_write_close
    if grep -q "session_write_close()" "$file"; then
        echo "✓ $file already optimized"
        continue
    fi

    # Find line with member_id check and add session_write_close after it
    # Pattern: After the authentication check block, before any real work
    sed -i '/if (!isset(\$_SESSION\[.member_id.\])) {/,/exit;/a\
\
// Session-Daten gelesen → Session sofort schließen für parallele Requests\
$member_id = $_SESSION["member_id"];\
session_write_close(); // Gibt das Session-Lock frei!' "$file"

    # Replace all $_SESSION['member_id'] with $member_id
    sed -i "s/\$_SESSION\['member_id'\]/\$member_id/g" "$file"

    echo "✓ $file optimized"
done

echo "Done! All API endpoints optimized for parallel requests."
