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

// Direkt-Login verarbeiten
if (isset($_GET['demo_email'])) {
    $email = $_GET['demo_email'];
    $password = 'test123';

    // Authentifizierung
    $stmt = $pdo->prepare("SELECT * FROM svmembers WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['member_id'] = $user['member_id'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit;
    }
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .features-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .features-section h2 {
            margin: 0 0 25px 0;
            color: #333;
            font-size: 1.5em;
        }

        .features-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px 30px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .feature-item .icon {
            font-size: 1.5em;
            flex-shrink: 0;
        }

        .feature-item .content {
            flex: 1;
        }

        .feature-item .content h3 {
            margin: 0 0 5px 0;
            font-size: 1em;
            color: #333;
        }

        .feature-item .content p {
            margin: 0;
            font-size: 0.9em;
            color: #666;
            line-height: 1.4;
        }

        .demo-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .demo-section h2 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.5em;
        }

        .demo-section p {
            margin-bottom: 20px;
            color: #666;
        }

        .demo-users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .demo-user-btn {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 6px;
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.2s;
        }

        .demo-user-btn:hover {
            border-color: #4CAF50;
            background: #e8f5e9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .demo-user-btn h4 {
            margin: 0 0 6px 0;
            font-size: 1em;
            color: #333;
        }

        .demo-user-btn .role-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            display: inline-block;
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

        /* Mobile Anpassung */
        @media (max-width: 768px) {
            .features-list {
                grid-template-columns: 1fr;
                gap: 15px;
            }
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
        <div class="features-section">
            <h2>Was diese Anwendung leistet:</h2>
            <div class="features-list">
                <div class="feature-item">
                    <div class="icon">📅</div>
                    <div class="content">
                        <h3>Meetings</h3>
                        <p>Meetings planen, Teilnehmer verwalten, Status-Tracking</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="icon">📋</div>
                    <div class="content">
                        <h3>Tagesordnung</h3>
                        <p>TOP-Verwaltung, Kommentare, Live-Abstimmungen</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="icon">📅</div>
                    <div class="content">
                        <h3>Termine</h3>
                        <p>Terminabstimmungen und Umfragen</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="icon">📊</div>
                    <div class="content">
                        <h3>Meinungsbild</h3>
                        <p>Anonyme Umfragen und Stimmungsbilder</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="icon">✅</div>
                    <div class="content">
                        <h3>ToDos</h3>
                        <p>Aufgabenverwaltung mit Fälligkeiten</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="icon">📋</div>
                    <div class="content">
                        <h3>Protokolle</h3>
                        <p>Protokollerstellung und Freigabe</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="icon">📁</div>
                    <div class="content">
                        <h3>Dokumente</h3>
                        <p>Dokumentenverwaltung und -archiv</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="icon">👥</div>
                    <div class="content">
                        <h3>Vertretung</h3>
                        <p>Abwesenheiten und Vertretungen verwalten</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demo Login -->
        <div class="demo-section">
            <h2>🎭 Demo-Zugang</h2>
            <p>Klicken Sie auf einen Testbenutzer, um sich als diese Person einzuloggen:</p>

            <?php if (!empty($demo_members)): ?>
                <div class="demo-users-grid">
                    <?php foreach ($demo_members as $member): ?>
                        <a href="?demo_email=<?php echo urlencode($member['email']); ?>" class="demo-user-btn">
                            <h4><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h4>
                            <span class="role-badge">
                                <?php echo htmlspecialchars($member['role']); ?>
                            </span>
                        </a>
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

    <!-- FOOTER -->
    <footer class="page-footer">
        <?php echo FOOTER_COPYRIGHT; ?> |
        <a href="<?php echo FOOTER_IMPRESSUM_URL; ?>" target="_blank">Impressum</a> |
        <a href="<?php echo FOOTER_DATENSCHUTZ_URL; ?>" target="_blank">Datenschutz</a>
    </footer>
</body>
</html>
