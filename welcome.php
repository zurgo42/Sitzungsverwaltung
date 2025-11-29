<?php
/**
 * welcome.php - Demo-Startseite mit Projekterklärung und Test-Logins
 *
 * Diese Seite zeigt:
 * - Erklärung des Projekts und seiner Features
 * - Liste der Demo-Mitglieder zum direkten Einloggen
 * - Auto-Fill der Login-Daten per Klick
 */

session_start();
require_once 'config.php';

// Wenn bereits eingeloggt, zur Hauptseite weiterleiten
if (isset($_SESSION['member_id'])) {
    header('Location: index.php');
    exit;
}

// PDO-Verbindung erstellen
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('<h1>Datenbankverbindung fehlgeschlagen</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

// Demo-Mitglieder laden (nur wenn members-Tabelle verwendet wird)
$demo_members = [];
try {
    $stmt = $pdo->query("
        SELECT member_id, first_name, last_name, role, email, is_admin
        FROM svmembers
        WHERE is_active = 1
        ORDER BY
            CASE role
                WHEN 'vorstand' THEN 1
                WHEN 'gf' THEN 2
                WHEN 'assistenz' THEN 3
                WHEN 'fuehrungsteam' THEN 4
                ELSE 5
            END,
            last_name
        LIMIT 10
    ");
    $demo_members = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fehler ignorieren, falls Tabelle nicht existiert
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Willkommen - Sitzungsverwaltung</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .welcome-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 40px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .hero h1 {
            font-size: 3em;
            margin: 0 0 20px 0;
        }
        .hero p {
            font-size: 1.3em;
            margin: 0;
            opacity: 0.95;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .feature-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .feature-card h3 {
            color: #667eea;
            margin-top: 0;
            font-size: 1.4em;
        }
        .feature-card ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .feature-card li {
            margin: 8px 0;
            line-height: 1.6;
        }
        .demo-section {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .demo-section h2 {
            color: #333;
            margin-top: 0;
            font-size: 2em;
            text-align: center;
            margin-bottom: 30px;
        }
        .demo-users {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .demo-user {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .demo-user:hover {
            transform: scale(1.05);
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .demo-user h4 {
            margin: 0 0 10px 0;
            font-size: 1.3em;
            color: #333;
        }
        .demo-user .role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .demo-user .email {
            color: #666;
            font-size: 0.9em;
            margin: 5px 0;
        }
        .demo-user .password {
            background: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
            margin-top: 10px;
            color: #28a745;
        }
        .admin-badge {
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            margin-left: 8px;
        }
        .role-vorstand { background: #e8f5e9; color: #2e7d32; }
        .role-gf { background: #fff3e0; color: #e65100; }
        .role-assistenz { background: #e3f2fd; color: #1565c0; }
        .role-fuehrungsteam { background: #f3e5f5; color: #6a1b9a; }
        .role-default { background: #f5f5f5; color: #616161; }
        .login-form {
            max-width: 400px;
            margin: 40px auto 0;
            background: #f9f9f9;
            padding: 30px;
            border-radius: 8px;
            border: 2px solid #667eea;
        }
        .login-form h3 {
            margin-top: 0;
            text-align: center;
            color: #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: scale(1.02);
        }
        .hint {
            text-align: center;
            color: #666;
            font-size: 0.9em;
            margin-top: 20px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <!-- Hero Section -->
        <div class="hero">
            <h1>🏛️ Sitzungsverwaltung</h1>
            <p>Professionelles Meeting-Management für Organisationen, Vereine und Gremien</p>
        </div>

        <!-- Features -->
        <div class="features">
            <div class="feature-card">
                <h3>📅 Meeting-Verwaltung</h3>
                <ul>
                    <li>Meetings planen und organisieren</li>
                    <li>Teilnehmerverwaltung</li>
                    <li>Status-Tracking (Vorbereitung, Aktiv, Beendet)</li>
                    <li>Automatische Benachrichtigungen</li>
                </ul>
            </div>

            <div class="feature-card">
                <h3>📋 Tagesordnung</h3>
                <ul>
                    <li>Digitale TOP-Verwaltung</li>
                    <li>Kommentarfunktion für Teilnehmer</li>
                    <li>Antragsschluss-Verwaltung</li>
                    <li>Live-Abstimmungen</li>
                    <li>Kategorien: Information, Diskussion, Beschluss, Wahl</li>
                </ul>
            </div>

            <div class="feature-card">
                <h3>📝 Protokolle</h3>
                <ul>
                    <li>Protokollerstellung während der Sitzung</li>
                    <li>Öffentliche & vertrauliche Protokolle</li>
                    <li>Änderungswünsche & Freigabe</li>
                    <li>Langzeitarchivierung (auch nach Meeting-Löschung)</li>
                </ul>
            </div>

            <div class="feature-card">
                <h3>✅ TODO-Verwaltung</h3>
                <ul>
                    <li>Aufgaben aus Meetings erstellen</li>
                    <li>Zuständigkeiten festlegen</li>
                    <li>Fälligkeitsdaten & Prioritäten</li>
                    <li>Vollständige Historie</li>
                </ul>
            </div>

            <div class="feature-card">
                <h3>📊 Umfragen & Meinungsbilder</h3>
                <ul>
                    <li>Terminabstimmungen</li>
                    <li>Meinungsumfragen (anonym möglich)</li>
                    <li>Öffentliche & interne Umfragen</li>
                    <li>Echtzeit-Auswertungen</li>
                </ul>
            </div>

            <div class="feature-card">
                <h3>👥 Benutzerverwaltung</h3>
                <ul>
                    <li>Rollensystem (Vorstand, GF, Assistenz, etc.)</li>
                    <li>Abwesenheiten & Vertretungen</li>
                    <li>Flexible Berechtigungen</li>
                    <li>SSO-Integration möglich</li>
                </ul>
            </div>
        </div>

        <!-- Demo Login Section -->
        <div class="demo-section">
            <h2>🎭 Demo-System</h2>
            <p style="text-align: center; color: #666; font-size: 1.1em; margin-bottom: 30px;">
                Klicken Sie auf einen Testbenutzer unten, um sich als diese Person einzuloggen und die verschiedenen Rollen auszuprobieren.
            </p>

            <?php if (!empty($demo_members)): ?>
                <div class="demo-users">
                    <?php foreach ($demo_members as $member):
                        $role_class = 'role-' . strtolower(str_replace(' ', '-', $member['role']));
                        if (!in_array($role_class, ['role-vorstand', 'role-gf', 'role-assistenz', 'role-fuehrungsteam'])) {
                            $role_class = 'role-default';
                        }
                    ?>
                        <div class="demo-user" onclick="fillLogin('<?php echo htmlspecialchars($member['email']); ?>')">
                            <h4>
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                <?php if ($member['is_admin']): ?>
                                    <span class="admin-badge">ADMIN</span>
                                <?php endif; ?>
                            </h4>
                            <span class="role <?php echo $role_class; ?>">
                                <?php echo htmlspecialchars($member['role']); ?>
                            </span>
                            <div class="email"><?php echo htmlspecialchars($member['email']); ?></div>
                            <div class="password">🔑 demo123</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #999;">
                    Keine Demo-Benutzer gefunden. Bitte führen Sie zuerst <code>demo.php</code> oder <code>tools/demo_import.php</code> aus.
                </p>
            <?php endif; ?>

            <!-- Login Form -->
            <div class="login-form">
                <h3>Anmelden</h3>
                <form method="POST" action="index.php" id="loginForm">
                    <div class="form-group">
                        <label>E-Mail:</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form-group">
                        <label>Passwort:</label>
                        <input type="password" name="password" id="password" required value="demo123">
                    </div>
                    <button type="submit" name="login" class="btn-login">Anmelden</button>
                </form>
                <p class="hint">💡 Klicken Sie auf einen Benutzer oben, um die E-Mail automatisch einzutragen</p>
            </div>
        </div>
    </div>

    <script>
        function fillLogin(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'demo123';
            document.getElementById('email').focus();
            // Scroll zum Formular
            document.getElementById('loginForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    </script>
</body>
</html>
