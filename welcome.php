<?php
/**
 * welcome.php - Demo-Startseite mit Projekterklärung und Test-Logins
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

// Demo-Mitglieder laden
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
    // Fehler ignorieren
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
        .welcome-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .intro {
            background: white;
            padding: 20px 30px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .intro h2 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.5em;
        }
        .intro p {
            margin: 0 0 10px 0;
            line-height: 1.6;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .feature-card {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .feature-card h3 {
            margin: 0 0 8px 0;
            font-size: 1.1em;
            color: #333;
        }
        .feature-card p {
            margin: 0;
            font-size: 0.9em;
            color: #666;
            line-height: 1.4;
        }
        .demo-login {
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .demo-login h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.5em;
        }
        .demo-users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .demo-user-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .demo-user-card:hover {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        .demo-user-card h4 {
            margin: 0 0 8px 0;
            font-size: 1.1em;
        }
        .demo-user-card .email {
            font-size: 0.85em;
            color: #666;
            margin: 5px 0;
        }
        .demo-user-card .password {
            background: #fff;
            padding: 5px 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85em;
            color: #28a745;
            margin-top: 8px;
            display: inline-block;
        }
        .login-form-container {
            max-width: 400px;
            margin: 0 auto;
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        .login-form-container h3 {
            margin: 0 0 20px 0;
            text-align: center;
            color: #333;
        }
        .hint {
            text-align: center;
            color: #666;
            font-size: 0.9em;
            margin-top: 15px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <!-- HEADER wie im Hauptskript -->
    <div class="header">
        <h1>🏛️ Sitzungsverwaltung</h1>
        <div class="user-info">
            <span>Demo-Modus</span>
        </div>
    </div>

    <div class="welcome-content">
        <!-- Intro -->
        <div class="intro">
            <h2>Willkommen zur Sitzungsverwaltung</h2>
            <p>
                Professionelles Meeting-Management-System für Organisationen, Vereine und Gremien.
                Verwalten Sie Meetings, Tagesordnungen, Protokolle, Abstimmungen und mehr – alles an einem Ort.
            </p>
        </div>

        <!-- Features (exakt wie die Tabs im Skript) -->
        <div class="features-grid">
            <div class="feature-card">
                <h3>📅 Meetings</h3>
                <p>Meetings planen, Teilnehmer verwalten, Status-Tracking</p>
            </div>

            <div class="feature-card">
                <h3>📋 Tagesordnung</h3>
                <p>TOP-Verwaltung, Kommentare, Live-Abstimmungen</p>
            </div>

            <div class="feature-card">
                <h3>📅 Termine</h3>
                <p>Terminabstimmungen und Umfragen</p>
            </div>

            <div class="feature-card">
                <h3>📊 Meinungsbild</h3>
                <p>Anonyme Umfragen und Stimmungsbilder</p>
            </div>

            <div class="feature-card">
                <h3>✅ ToDos</h3>
                <p>Aufgabenverwaltung mit Fälligkeiten</p>
            </div>

            <div class="feature-card">
                <h3>📋 Protokolle</h3>
                <p>Protokollerstellung und Freigabe</p>
            </div>

            <div class="feature-card">
                <h3>📁 Dokumente</h3>
                <p>Dokumentenverwaltung und -archiv</p>
            </div>

            <div class="feature-card">
                <h3>👥 Vertretung</h3>
                <p>Abwesenheiten und Vertretungen verwalten</p>
            </div>
        </div>

        <!-- Demo Login -->
        <div class="demo-login">
            <h2>🎭 Demo-Zugang</h2>
            <p style="margin-bottom: 20px; color: #666;">
                Klicken Sie auf einen Testbenutzer, um sich als diese Person einzuloggen:
            </p>

            <?php if (!empty($demo_members)): ?>
                <div class="demo-users-grid">
                    <?php foreach ($demo_members as $member): ?>
                        <div class="demo-user-card" onclick="fillLogin('<?php echo htmlspecialchars($member['email']); ?>')">
                            <h4>
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                <?php if ($member['is_admin']): ?>
                                    <span class="role-badge" style="background: #ff6b6b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7em; margin-left: 5px;">ADMIN</span>
                                <?php endif; ?>
                            </h4>
                            <span class="role-badge role-<?php echo strtolower($member['role']); ?>">
                                <?php echo htmlspecialchars($member['role']); ?>
                            </span>
                            <div class="email"><?php echo htmlspecialchars($member['email']); ?></div>
                            <div class="password">🔑 test123</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 40px;">
                    Keine Demo-Benutzer gefunden. Bitte führen Sie zuerst <code>demo.php</code> oder <code>tools/demo_import.php</code> aus.
                </p>
            <?php endif; ?>

            <!-- Login Form -->
            <div class="login-form-container">
                <h3>Anmelden</h3>
                <form method="POST" action="index.php" id="loginForm">
                    <div class="form-group">
                        <label>E-Mail:</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form-group">
                        <label>Passwort:</label>
                        <input type="password" name="password" id="password" required value="test123">
                    </div>
                    <button type="submit" name="login">Anmelden</button>
                </form>
                <p class="hint">💡 Klicken Sie auf einen Benutzer, um die E-Mail automatisch einzutragen</p>
            </div>
        </div>
    </div>

    <script>
        function fillLogin(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'test123';
            document.getElementById('email').focus();
            document.getElementById('loginForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    </script>
</body>
</html>
