<?php
/**
 * test_http_auth.php - Testet HTTP Basic Auth Konfiguration
 *
 * Zeigt:
 * - Ob HTTP Auth aktiv ist
 * - Welcher Username authentifiziert wurde
 * - Ob Username → MNr Mapping funktioniert
 * - Aktuelle SSO-Konfiguration
 */

session_start();
require_once 'config.php';
require_once 'config_adapter.php';
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTTP Auth Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .box h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
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
        .status.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        table td:first-child {
            font-weight: 600;
            width: 200px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔐 HTTP Basic Auth Test</h2>

        <?php
        // 1. SSO-Konfiguration prüfen
        echo "<h3>1. Konfiguration</h3>";
        echo "<table>";
        echo "<tr><td>REQUIRE_LOGIN:</td><td><code>" . (REQUIRE_LOGIN ? 'true (Login-Modus)' : 'false (SSO-Modus)') . "</code></td></tr>";
        echo "<tr><td>SSO_SOURCE:</td><td><code>" . SSO_SOURCE . "</code></td></tr>";
        echo "<tr><td>MEMBER_SOURCE:</td><td><code>" . MEMBER_SOURCE . "</code></td></tr>";
        echo "</table>";

        if (REQUIRE_LOGIN) {
            echo '<div class="status error">❌ REQUIRE_LOGIN ist auf true - SSO-Modus ist nicht aktiv!</div>';
            echo '<p>Setze in <code>config_adapter.php</code>: <code>define("REQUIRE_LOGIN", false);</code></p>';
        } else {
            echo '<div class="status success">✅ SSO-Modus ist aktiv</div>';
        }

        if (SSO_SOURCE !== 'http_auth') {
            echo '<div class="status error">❌ SSO_SOURCE ist nicht auf "http_auth" gesetzt!</div>';
            echo '<p>Setze in <code>config_adapter.php</code>: <code>define("SSO_SOURCE", "http_auth");</code></p>';
        } else {
            echo '<div class="status success">✅ HTTP Auth Modus ist konfiguriert</div>';
        }

        // 2. HTTP Auth Status prüfen
        echo "<h3>2. HTTP Basic Auth Status</h3>";

        $php_auth_user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $remote_user = $_SERVER['REMOTE_USER'] ?? null;

        echo "<table>";
        echo "<tr><td>\$_SERVER['PHP_AUTH_USER']:</td><td><code>" . ($php_auth_user ? htmlspecialchars($php_auth_user) : '❌ NICHT GESETZT') . "</code></td></tr>";
        echo "<tr><td>\$_SERVER['REMOTE_USER']:</td><td><code>" . ($remote_user ? htmlspecialchars($remote_user) : '❌ NICHT GESETZT') . "</code></td></tr>";
        echo "</table>";

        if ($php_auth_user || $remote_user) {
            $username = $php_auth_user ?: $remote_user;
            echo '<div class="status success">✅ HTTP Auth ist aktiv - User: <strong>' . htmlspecialchars($username) . '</strong></div>';
        } else {
            echo '<div class="status error">❌ HTTP Auth ist nicht aktiv - keine Authentifizierung erkannt</div>';
            echo '<div class="status info">';
            echo '<strong>Mögliche Ursachen:</strong><ul>';
            echo '<li>.htaccess fehlt oder ist nicht korrekt konfiguriert</li>';
            echo '<li>Apache mod_authnz_ldap ist nicht aktiviert</li>';
            echo '<li>LDAP-Server ist nicht erreichbar</li>';
            echo '</ul>';
            echo '<strong>Nächste Schritte:</strong><ul>';
            echo '<li>Kopiere <code>.htaccess.ldap-example</code> nach <code>.htaccess</code></li>';
            echo '<li>Passe LDAP-Einstellungen in .htaccess an</li>';
            echo '<li>Aktiviere Apache-Module: <code>sudo a2enmod authnz_ldap ldap</code></li>';
            echo '</ul>';
            echo '</div>';
        }

        // 3. Username → MNr Mapping testen
        if ($php_auth_user || $remote_user) {
            echo "<h3>3. Username → Mitgliedsnummer Mapping</h3>";

            $username = $php_auth_user ?: $remote_user;
            $mnr = get_mnr_by_username($username);

            echo "<table>";
            echo "<tr><td>Username:</td><td><code>" . htmlspecialchars($username) . "</code></td></tr>";
            echo "<tr><td>Gemappte MNr:</td><td><code>" . ($mnr ? htmlspecialchars($mnr) : '❌ NICHT GEFUNDEN') . "</code></td></tr>";
            echo "</table>";

            if ($mnr) {
                echo '<div class="status success">✅ Mapping erfolgreich - MNr: <strong>' . htmlspecialchars($mnr) . '</strong></div>';

                // User-Daten laden
                $user = get_member_by_membership_number($pdo, $mnr);
                if ($user) {
                    echo "<h4>User-Daten aus Datenbank:</h4>";
                    echo "<table>";
                    echo "<tr><td>Name:</td><td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td></tr>";
                    echo "<tr><td>Email:</td><td>" . htmlspecialchars($user['email'] ?? '-') . "</td></tr>";
                    echo "<tr><td>Rolle:</td><td>" . htmlspecialchars($user['role']) . "</td></tr>";
                    echo "<tr><td>Status:</td><td>" . htmlspecialchars($user['status']) . "</td></tr>";
                    echo "</table>";
                    echo '<div class="status success">✅ User-Daten erfolgreich geladen - SSO funktioniert!</div>';
                } else {
                    echo '<div class="status error">❌ User mit MNr ' . htmlspecialchars($mnr) . ' nicht in svmembers/berechtigte gefunden</div>';
                }
            } else {
                echo '<div class="status error">❌ Username konnte nicht auf MNr gemappt werden</div>';
                echo '<div class="status info">';
                echo '<strong>Mögliche Ursachen:</strong><ul>';
                echo '<li>Username existiert nicht in <code>berechtigte</code>-Tabelle</li>';
                echo '<li>Feldname in Datenbank ist anders als erwartet</li>';
                echo '<li>Groß-/Kleinschreibung stimmt nicht</li>';
                echo '</ul>';
                echo '<strong>Debug-Query ausführen:</strong><br>';
                echo '<code>SELECT MNr, Username, EMail FROM berechtigte WHERE LOWER(Username) = "' . strtolower($username) . '";</code>';
                echo '</div>';

                // Verfügbare Felder anzeigen
                if (MEMBER_SOURCE === 'berechtigte') {
                    try {
                        $stmt = $pdo->query("SELECT * FROM berechtigte LIMIT 1");
                        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($sample) {
                            echo "<h4>Verfügbare Felder in berechtigte-Tabelle:</h4>";
                            echo "<code>" . implode(', ', array_keys($sample)) . "</code>";
                            echo "<p><small>Passe get_mnr_by_username() in config_adapter.php an diese Felder an!</small></p>";
                        }
                    } catch (Exception $e) {
                        // Ignore
                    }
                }
            }
        }

        // 4. SSO Test
        echo "<h3>4. Kompletter SSO-Flow Test</h3>";

        $sso_mnr = get_sso_membership_number();

        echo "<table>";
        echo "<tr><td>get_sso_membership_number():</td><td><code>" . ($sso_mnr ? htmlspecialchars($sso_mnr) : '❌ NULL') . "</code></td></tr>";
        echo "</table>";

        if ($sso_mnr) {
            echo '<div class="status success">✅ SSO-Flow funktioniert komplett - die Sitzungsverwaltung sollte jetzt ohne Session-Probleme laufen!</div>';
            echo '<p><strong>Nächster Schritt:</strong> Öffne <a href="index.php">index.php</a> - du solltest automatisch eingeloggt werden.</p>';
        } else {
            echo '<div class="status error">❌ SSO-Flow funktioniert nicht - siehe Fehler oben</div>';
        }

        // 5. Session-Info
        echo "<h3>5. Session-Status</h3>";
        echo "<table>";
        echo "<tr><td>Session aktiv:</td><td><code>" . (session_status() === PHP_SESSION_ACTIVE ? '✅ Ja' : '❌ Nein') . "</code></td></tr>";
        echo "<tr><td>\$_SESSION['member_id']:</td><td><code>" . ($_SESSION['member_id'] ?? '❌ NICHT GESETZT') . "</code></td></tr>";
        echo "<tr><td>\$_SESSION['MNr']:</td><td><code>" . ($_SESSION['MNr'] ?? '❌ NICHT GESETZT') . "</code></td></tr>";
        echo "</table>";
        ?>

        <h3>📚 Dokumentation</h3>
        <p>Siehe <a href="README_HTTP_AUTH.md">README_HTTP_AUTH.md</a> für detaillierte Setup-Anleitung.</p>
    </div>

    <div class="box">
        <h2>🔧 Quick Actions</h2>
        <ul>
            <li><a href="index.php">→ Zur Sitzungsverwaltung</a></li>
            <li><a href="test_session_info.php">→ Session-Info anzeigen</a></li>
            <li><a href="logout.php">→ Ausloggen</a></li>
        </ul>
    </div>
</body>
</html>
