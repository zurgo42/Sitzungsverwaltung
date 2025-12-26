<?php
/**
 * Migrationsskript: Alte Tabellen -> Neue Struktur
 *
 * Migriert Daten von:
 *   - fanreisen -> fan_reisen
 *   - fannamen -> fan_users, fan_teilnehmer, fan_anmeldungen
 *
 * ACHTUNG: Vor der Ausführung:
 *   1. Backup der Datenbank erstellen!
 *   2. schema.sql ausführen (neue Tabellen anlegen)
 *   3. Dieses Skript EINMAL ausführen
 *
 * Aufruf: php migration.php
 */

// Konfiguration laden
require_once __DIR__ . '/../config/config.php';

echo "=== Fantreffen Migration ===\n\n";

// Datenbankverbindung
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "[OK] Datenbankverbindung hergestellt\n";
} catch (PDOException $e) {
    die("[FEHLER] Datenbankverbindung: " . $e->getMessage() . "\n");
}

// Prüfen ob alte Tabellen existieren
$alteTabellen = $pdo->query("SHOW TABLES LIKE 'fanreisen'")->rowCount();
if ($alteTabellen === 0) {
    die("[FEHLER] Alte Tabelle 'fanreisen' nicht gefunden.\n");
}

echo "\n--- Schritt 1: Reisen migrieren ---\n";

// Alte Reisen laden
$alteReisen = $pdo->query("SELECT * FROM fanreisen ORDER BY id")->fetchAll();
echo "Gefunden: " . count($alteReisen) . " Reisen\n";

$reiseMapping = []; // alte ID -> neue ID

foreach ($alteReisen as $alt) {
    // Prüfen ob schon migriert (anhand Schiff + Datum)
    $exists = $pdo->prepare("SELECT reise_id FROM fan_reisen WHERE schiff = ? AND anfang = ?");
    $exists->execute([$alt['schiff'], $alt['anfang']]);

    if ($exists->fetch()) {
        echo "  [SKIP] {$alt['schiff']} ({$alt['anfang']}) - bereits vorhanden\n";
        continue;
    }

    // Neue Reise anlegen
    $stmt = $pdo->prepare("
        INSERT INTO fan_reisen (schiff, bahnhof, anfang, ende, treffen_ort, treffen_zeit,
                                treffen_status, link_wasserurlaub, link_facebook, link_kids, erstellt)
        VALUES (?, ?, ?, ?, ?, ?, 'geplant', ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $alt['schiff'],
        $alt['bahnhof'] ?? null,
        $alt['anfang'],
        $alt['ende'],
        $alt['treffpunkt'] ?? null,
        $alt['treffzeit'] ?? null,
        $alt['link_wasserurlaub'] ?? null,
        $alt['link_facebook'] ?? null,
        $alt['link_kids'] ?? null
    ]);

    $neueReiseId = $pdo->lastInsertId();
    $reiseMapping[$alt['id']] = $neueReiseId;

    echo "  [OK] {$alt['schiff']} ({$alt['anfang']}) -> ID $neueReiseId\n";

    // Admin-E-Mails verarbeiten (wenn vorhanden)
    if (!empty($alt['admin_email'])) {
        $adminEmails = array_map('trim', explode(',', $alt['admin_email']));
        foreach ($adminEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            // User finden oder anlegen
            $userStmt = $pdo->prepare("SELECT user_id FROM fan_users WHERE email = ?");
            $userStmt->execute([strtolower($email)]);
            $user = $userStmt->fetch();

            if (!$user) {
                // User anlegen (ohne Passwort - muss später gesetzt werden)
                $tempHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $insertUser = $pdo->prepare("
                    INSERT INTO fan_users (email, passwort_hash, rolle, erstellt)
                    VALUES (?, ?, 'admin', NOW())
                ");
                $insertUser->execute([strtolower($email), $tempHash]);
                $userId = $pdo->lastInsertId();
                echo "    [NEU] Admin-User angelegt: $email (ID $userId)\n";
            } else {
                $userId = $user['user_id'];
                // Rolle auf Admin setzen wenn nur User
                $pdo->prepare("UPDATE fan_users SET rolle = 'admin' WHERE user_id = ? AND rolle = 'user'")
                    ->execute([$userId]);
            }

            // Als Reise-Admin eintragen
            try {
                $pdo->prepare("INSERT IGNORE INTO fan_reise_admins (reise_id, user_id) VALUES (?, ?)")
                    ->execute([$neueReiseId, $userId]);
            } catch (Exception $e) {
                // Ignorieren wenn bereits vorhanden
            }
        }
    }
}

echo "\n--- Schritt 2: Teilnehmer migrieren ---\n";

// Alte Teilnehmer laden
$alteTeilnehmer = $pdo->query("SELECT * FROM fannamen ORDER BY id")->fetchAll();
echo "Gefunden: " . count($alteTeilnehmer) . " Anmeldungen\n";

$emailToUser = []; // Cache: E-Mail -> User-ID

foreach ($alteTeilnehmer as $alt) {
    $email = strtolower(trim($alt['email']));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "  [SKIP] Ungültige E-Mail übersprungen\n";
        continue;
    }

    // User finden oder anlegen
    if (!isset($emailToUser[$email])) {
        $userStmt = $pdo->prepare("SELECT user_id FROM fan_users WHERE email = ?");
        $userStmt->execute([$email]);
        $user = $userStmt->fetch();

        if (!$user) {
            // Neuen User anlegen
            $tempHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $insertUser = $pdo->prepare("
                INSERT INTO fan_users (email, passwort_hash, rolle, erstellt)
                VALUES (?, ?, 'user', NOW())
            ");
            $insertUser->execute([$email, $tempHash]);
            $emailToUser[$email] = $pdo->lastInsertId();
            echo "  [NEU] User angelegt: $email\n";
        } else {
            $emailToUser[$email] = $user['user_id'];
        }
    }

    $userId = $emailToUser[$email];

    // Teilnehmer anlegen (bis zu 6 pro alter Anmeldung)
    $teilnehmerIds = [];

    for ($i = 1; $i <= 6; $i++) {
        $nameField = $i === 1 ? 'name' : "name$i";
        $vornameField = $i === 1 ? 'vorname' : "vorname$i";
        $nicknameField = $i === 1 ? 'nickname' : "nickname$i";

        $name = trim($alt[$nameField] ?? '');
        $vorname = trim($alt[$vornameField] ?? '');

        if (empty($name) || empty($vorname)) continue;

        $nickname = trim($alt[$nicknameField] ?? '');

        // Prüfen ob Teilnehmer bereits existiert
        $existsStmt = $pdo->prepare("
            SELECT teilnehmer_id FROM fan_teilnehmer
            WHERE user_id = ? AND name = ? AND vorname = ?
        ");
        $existsStmt->execute([$userId, $name, $vorname]);
        $existing = $existsStmt->fetch();

        if ($existing) {
            $teilnehmerIds[] = $existing['teilnehmer_id'];
        } else {
            // Neuen Teilnehmer anlegen
            $insertTeilnehmer = $pdo->prepare("
                INSERT INTO fan_teilnehmer (user_id, name, vorname, nickname, position, erstellt)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insertTeilnehmer->execute([$userId, $name, $vorname, $nickname ?: null, $i]);
            $teilnehmerIds[] = $pdo->lastInsertId();
        }
    }

    if (empty($teilnehmerIds)) {
        echo "  [SKIP] Keine Teilnehmer für {$alt['email']}\n";
        continue;
    }

    // Reise-ID ermitteln
    $altReiseId = $alt['reise_id'] ?? $alt['reiseid'] ?? null;
    if (!$altReiseId || !isset($reiseMapping[$altReiseId])) {
        // Versuche über Schiff/Datum zu finden
        if (!empty($alt['schiff']) && !empty($alt['anfang'])) {
            $findReise = $pdo->prepare("SELECT reise_id FROM fan_reisen WHERE schiff = ? AND anfang = ?");
            $findReise->execute([$alt['schiff'], $alt['anfang']]);
            $reise = $findReise->fetch();
            if ($reise) {
                $neueReiseId = $reise['reise_id'];
            } else {
                echo "  [SKIP] Reise nicht gefunden für {$alt['email']}\n";
                continue;
            }
        } else {
            echo "  [SKIP] Keine Reise-Zuordnung für {$alt['email']}\n";
            continue;
        }
    } else {
        $neueReiseId = $reiseMapping[$altReiseId];
    }

    // Anmeldung anlegen
    try {
        $insertAnmeldung = $pdo->prepare("
            INSERT INTO fan_anmeldungen (user_id, reise_id, kabine, bemerkung,
                teilnehmer1_id, teilnehmer2_id, teilnehmer3_id, teilnehmer4_id, erstellt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $insertAnmeldung->execute([
            $userId,
            $neueReiseId,
            $alt['kabine'] ?? null,
            $alt['bemerkung'] ?? null,
            $teilnehmerIds[0] ?? null,
            $teilnehmerIds[1] ?? null,
            $teilnehmerIds[2] ?? null,
            $teilnehmerIds[3] ?? null
        ]);
        echo "  [OK] Anmeldung: {$alt['email']} -> Reise $neueReiseId (" . count($teilnehmerIds) . " Teilnehmer)\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "  [SKIP] Anmeldung bereits vorhanden: {$alt['email']} -> Reise $neueReiseId\n";
        } else {
            echo "  [FEHLER] {$e->getMessage()}\n";
        }
    }
}

echo "\n=== Migration abgeschlossen ===\n";
echo "\nHINWEIS: Alle migrierten User ohne vorheriges Konto haben ein\n";
echo "zufälliges Passwort erhalten. Sie müssen 'Passwort vergessen'\n";
echo "nutzen oder vom Admin zurückgesetzt werden.\n";
