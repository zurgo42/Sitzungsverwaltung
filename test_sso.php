<?php
/**
 * test_sso.php - SSO-Konfiguration testen
 *
 * Dieses Skript hilft beim Debugging von SSO-Problemen.
 * Aufruf: https://ihre-domain.de/Sitzungsverwaltung/test_sso.php
 */

// Konfiguration laden
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config_adapter.php';
require_once __DIR__ . '/member_functions.php';

session_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSO Debug-Info</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #1976d2;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
            border-left: 4px solid #1976d2;
            padding-left: 10px;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-weight: bold;
        }
        .status.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        pre {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .check {
            color: #28a745;
            font-weight: bold;
        }
        .cross {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 SSO Debug-Info</h1>

        <h2>1. Konfiguration</h2>
        <div class="info-row">
            <span class="info-label">REQUIRE_LOGIN:</span>
            <span class="info-value">
                <?php
                if (REQUIRE_LOGIN) {
                    echo '<span class="cross">✗ true</span> <small>(SSO deaktiviert!)</small>';
                } else {
                    echo '<span class="check">✓ false</span> <small>(SSO aktiv)</small>';
                }
                ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">SSO_SOURCE:</span>
            <span class="info-value"><?php echo SSO_SOURCE; ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">MEMBER_SOURCE:</span>
            <span class="info-value"><?php echo MEMBER_SOURCE; ?></span>
        </div>

        <?php if (REQUIRE_LOGIN): ?>
            <div class="status error">
                ❌ <strong>FEHLER:</strong> REQUIRE_LOGIN = true<br>
                SSO funktioniert nur mit REQUIRE_LOGIN = false!<br>
                Ändern Sie config_adapter.php wie in der Anleitung beschrieben.
            </div>
        <?php else: ?>
            <div class="status success">
                ✓ REQUIRE_LOGIN korrekt konfiguriert für SSO
            </div>
        <?php endif; ?>

        <h2>2. Session-Status</h2>
        <div class="info-row">
            <span class="info-label">Session ID:</span>
            <span class="info-value"><?php echo session_id(); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Session Name:</span>
            <span class="info-value"><?php echo session_name(); ?></span>
        </div>

        <h3>Session-Inhalt:</h3>
        <pre><?php print_r($_SESSION); ?></pre>

        <h2>3. SSO-Mitgliedsnummer</h2>
        <?php
        $session_mnr = $_SESSION['MNr'] ?? null;
        ?>
        <div class="info-row">
            <span class="info-label">$_SESSION['MNr']:</span>
            <span class="info-value">
                <?php
                if ($session_mnr) {
                    echo '<span class="check">✓ ' . htmlspecialchars($session_mnr) . '</span>';
                } else {
                    echo '<span class="cross">✗ NICHT GESETZT</span>';
                }
                ?>
            </span>
        </div>

        <?php
        $mnr = get_sso_membership_number();
        ?>
        <div class="info-row">
            <span class="info-label">get_sso_membership_number():</span>
            <span class="info-value">
                <?php
                if ($mnr) {
                    echo '<span class="check">✓ ' . htmlspecialchars($mnr) . '</span>';
                } else {
                    echo '<span class="cross">✗ NULL</span>';
                }
                ?>
            </span>
        </div>

        <?php if (!$session_mnr): ?>
            <div class="status error">
                ❌ <strong>FEHLER:</strong> $_SESSION['MNr'] ist nicht gesetzt!<br>
                Ihr Hauptsystem muss $_SESSION['MNr'] = '0495018' (oder Ihre MNr) setzen.<br>
                <strong>WICHTIG:</strong> Der Variablenname muss EXAKT 'MNr' sein (großes M, kleines Nr)!
            </div>
        <?php elseif (!$mnr): ?>
            <div class="status error">
                ❌ <strong>FEHLER:</strong> get_sso_membership_number() gibt NULL zurück!<br>
                Vermutlich ist REQUIRE_LOGIN = true oder SSO_SOURCE falsch konfiguriert.
            </div>
        <?php else: ?>
            <div class="status success">
                ✓ Mitgliedsnummer korrekt aus Session gelesen
            </div>
        <?php endif; ?>

        <h2>4. User-Lookup</h2>
        <?php if ($mnr): ?>
            <?php
            try {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $user = get_member_by_membership_number($pdo, $mnr);

                if ($user):
            ?>
                    <div class="status success">
                        ✓ User gefunden in Datenbank!
                    </div>

                    <div class="info-row">
                        <span class="info-label">Member ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['member_id']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Rolle:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['role']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Mitgliedsnummer:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['membership_number'] ?? 'N/A'); ?></span>
                    </div>

                    <h3>Vollständiger User-Datensatz:</h3>
                    <pre><?php print_r($user); ?></pre>

                <?php else: ?>
                    <div class="status error">
                        ❌ <strong>FEHLER:</strong> User mit MNr '<?php echo htmlspecialchars($mnr); ?>' nicht in Datenbank gefunden!
                    </div>

                    <p><strong>Mögliche Ursachen:</strong></p>
                    <ul>
                        <li>User existiert nicht in Tabelle '<?php echo MEMBER_SOURCE; ?>'</li>
                        <li>User ist inaktiv (aktiv < 18 bei berechtigte-Tabelle)</li>
                        <li>Filter-Bedingung schließt User aus</li>
                        <li>Falsche MNr (führende Nullen? Großbuchstaben?)</li>
                    </ul>

                    <p><strong>SQL-Test:</strong></p>
                    <pre>SELECT * FROM <?php echo (MEMBER_SOURCE === 'berechtigte' ? 'berechtigte' : 'svmembers'); ?>
WHERE <?php echo (MEMBER_SOURCE === 'berechtigte' ? 'MNr' : 'membership_number'); ?> = '<?php echo htmlspecialchars($mnr); ?>';</pre>
                <?php endif; ?>

            <?php
            } catch (Exception $e) {
                ?>
                <div class="status error">
                    ❌ <strong>DATENBANK-FEHLER:</strong><br>
                    <?php echo htmlspecialchars($e->getMessage()); ?>
                </div>
                <?php
            }
            ?>
        <?php else: ?>
            <div class="status warning">
                ⚠️ Keine Mitgliedsnummer verfügbar - User-Lookup nicht möglich
            </div>
        <?php endif; ?>

        <h2>5. Zusammenfassung</h2>
        <?php
        $all_ok = true;
        $errors = [];

        if (REQUIRE_LOGIN) {
            $all_ok = false;
            $errors[] = "REQUIRE_LOGIN muss false sein";
        }
        if (!$session_mnr) {
            $all_ok = false;
            $errors[] = "\$_SESSION['MNr'] ist nicht gesetzt";
        }
        if (!$mnr) {
            $all_ok = false;
            $errors[] = "get_sso_membership_number() gibt NULL zurück";
        }
        if ($mnr && !isset($user)) {
            $all_ok = false;
            $errors[] = "User konnte nicht aus DB geladen werden";
        }
        if ($mnr && isset($user) && !$user) {
            $all_ok = false;
            $errors[] = "User nicht in Datenbank gefunden";
        }
        ?>

        <?php if ($all_ok): ?>
            <div class="status success" style="font-size: 1.2em;">
                🎉 <strong>ALLES OK!</strong> SSO ist korrekt konfiguriert und funktioniert.
            </div>
            <p>
                <a href="sso_direct.php" style="display: inline-block; padding: 12px 24px; background: #1976d2; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">
                    → Zur Sitzungsverwaltung
                </a>
            </p>
        <?php else: ?>
            <div class="status error" style="font-size: 1.2em;">
                ❌ <strong>SSO NICHT FUNKTIONSFÄHIG</strong>
            </div>
            <p><strong>Gefundene Probleme:</strong></p>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><strong>Nächste Schritte:</strong></p>
            <ol>
                <li>Siehe ANLEITUNG_SSOdirekt.md für detaillierte Anleitung</li>
                <li>Prüfen Sie config_adapter.php</li>
                <li>Prüfen Sie Ihr Hauptsystem (Session-Variable 'MNr')</li>
                <li>Prüfen Sie Session-Cookie-Settings</li>
            </ol>
        <?php endif; ?>

        <hr style="margin: 30px 0;">
        <p style="color: #666; font-size: 0.9em;">
            <strong>Hinweis:</strong> Dieses Skript dient nur zum Debugging.
            Löschen Sie es nach erfolgreicher SSO-Konfiguration aus Sicherheitsgründen.
        </p>
    </div>
</body>
</html>
