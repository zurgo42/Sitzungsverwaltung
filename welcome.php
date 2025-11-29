<?php
/**
 * welcome.php - Demo-Startseite
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
        SELECT member_id, first_name, last_name, role, email
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
    <title>Sitzungsverwaltung - Demo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .welcome-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
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
        .demo-section {
            background: white;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .demo-section h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.5em;
        }
        .login-inline {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .login-inline .form-group {
            margin: 0;
            flex: 1;
            min-width: 200px;
        }
        .login-inline .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.9em;
            font-weight: 600;
        }
        .login-inline .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .login-inline button {
            padding: 8px 25px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            align-self: flex-end;
        }
        .login-inline button:hover {
            background: #45a049;
        }
        .demo-users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .demo-user-card {
            background: #f8f9fa;
            padding: 12px 15px;
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
            margin: 0 0 6px 0;
            font-size: 1em;
        }
        .footer-links {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
        }
        .footer-links a {
            color: #2196F3;
            text-decoration: none;
            margin: 0 15px;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <h1>🏛️ Sitzungsverwaltung</h1>
        <div class="user-info">
            <span>Demo-Modus</span>
        </div>
    </div>

    <div class="welcome-content">
        <!-- Features -->
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
        <div class="demo-section">
            <h2>🎭 Demo-Zugang</h2>

            <!-- Login-Formular in einer Zeile -->
            <form method="POST" action="index.php" id="loginForm" class="login-inline">
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

            <?php if (!empty($demo_members)): ?>
                <p style="margin-bottom: 15px; color: #666; font-size: 0.95em;">
                    Klicken Sie auf einen Testbenutzer, um sich als diese Person einzuloggen:
                </p>
                <div class="demo-users-grid">
                    <?php foreach ($demo_members as $member): ?>
                        <div class="demo-user-card" onclick="fillLogin('<?php echo htmlspecialchars($member['email']); ?>')">
                            <h4><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h4>
                            <span class="role-badge role-<?php echo strtolower($member['role']); ?>">
                                <?php echo htmlspecialchars($member['role']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 40px;">
                    Keine Demo-Benutzer gefunden. Bitte führen Sie zuerst <code>demo.php</code> oder <code>tools/demo_import.php</code> aus.
                </p>
            <?php endif; ?>

            <!-- Footer Links -->
            <div class="footer-links">
                <a href="README.md" target="_blank">📖 Dokumentation</a>
                <span style="color: #ccc;">|</span>
                <a href="INSTALL.md" target="_blank">⚙️ Installation</a>
                <span style="color: #ccc;">|</span>
                <a href="https://github.com/zurgo42/Sitzungsverwaltung" target="_blank">GitHub</a>
            </div>
        </div>
    </div>

    <script>
        function fillLogin(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'test123';
            document.getElementById('email').focus();
        }
    </script>
</body>
</html>
