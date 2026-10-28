<?php
/**
 * welcome.php - Demo-Startseite
 */

session_start();
require_once 'config.php';
require_once 'branding.php';

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

require_once __DIR__ . '/module_config.php';

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
        LIMIT 16
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
    <title>Demo - KONA</title>
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css') ?: time(); ?>">
    <style>
        .welcome-content {
            max-width: 1024px;   /* wie .header-inner und .content in style.css */
            margin: 20px auto;
            padding: 0;
        }

        @media (max-width: 1024px) {
            .welcome-content { padding: 0 15px; }
        }

        .features-box {
            background: white;
            padding: 20px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .features-box h2 {
            margin: 0 0 15px 0;
            font-size: 1.3em;
            color: #333;
        }

        .features-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
            line-height: 1.5;
        }

        .feature-item .icon {
            font-size: 1.2em;
            flex-shrink: 0;
        }

        .feature-item .text {
            line-height: 1.3;
        }

        .feature-item strong {
            color: #333;
        }

        /* Per Modulschalter ausgeschaltete Bereiche */
        .feature-item.disabled {
            opacity: 0.45;
            filter: grayscale(1);
        }

        .features-footnote {
            margin: 14px 0 0 0;
            font-size: 0.82em;
            color: #777;
        }

        .demo-box {
            background: white;
            padding: 20px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .demo-box h2 {
            margin: 0 0 12px 0;
            font-size: 1.3em;
            color: #333;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
        }

        .demo-btn {
            background: #f5f5f5;
            padding: 10px 12px;
            border-radius: 5px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            display: block;
            transition: all 0.2s;
            text-align: center;
        }

        .demo-btn:hover {
            border-color: #4CAF50;
            background: #e8f5e9;
            transform: translateY(-1px);
        }

        .demo-btn strong {
            display: block;
            font-size: 0.95em;
            margin-bottom: 3px;
        }

        .demo-btn small {
            color: #666;
            font-size: 0.85em;
        }

        .doc-links {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 0.9em;
        }

        .doc-links a {
            color: #2196F3;
            text-decoration: none;
            margin: 0 12px;
        }

        .doc-links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .demo-grid {
                grid-template-columns: 1fr;
            }
        }
        .app-lead { margin: 0 0 16px; color: var(--text-secondary, #555); line-height: 1.5; }
        .app-sub { margin: 0 0 12px; font-size: 1.05em; }
    </style>
</head>
<body>
    <!-- HEADER -->
    <?php $sv_org_name = sv_org_name($pdo); $sv_org_logo = sv_org_logo($sv_org_name); ?>
    <div class="header">
        <div class="header-inner header-dreiteilig">
            <a href="?" class="header-brand">
                <img src="<?php echo htmlspecialchars($sv_org_logo); ?>" alt="" class="header-logo">
                <span class="brand">
                    <span class="orgname"><?php echo htmlspecialchars($sv_org_name !== '' ? $sv_org_name : 'Sitzungsverwaltung'); ?></span>
                    <span class="appname"><b><?php echo SV_APP_NAME; ?></b> · <?php echo SV_APP_TAG; ?></span>
                </span>
            </a>
            <div class="header-mitte"><h1>Demo</h1></div>
            <div class="user-info"><span>Demo-Modus</span></div>
        </div>
    </div>

    <div class="welcome-content">
        <!-- Features -->
        <div class="features-box">
            <h2><?php echo SV_APP_NAME; ?> – <?php echo SV_APP_TAG; ?></h2>
            <p class="app-lead"><?php echo SV_APP_LONG; ?>: vorbereiten, diskutieren, abstimmen, dokumentieren. Die Anwendung arbeitet mit dem Mitglieder-Informationssystem MISS zusammen und teilt Mitglieder, Anmeldung und Aussehen mit ihm.</p>
            <h3 class="app-sub">Was <?php echo SV_APP_NAME; ?> leistet:</h3>
            <?php
            // Reihenfolge wie im Menü; Schlüssel = Modulschalter (null = fester Bereich, immer an)
            $features = [
                ['proposals', '📜', 'Anträge & Beschlüsse', 'Vollständiges Workflow-System für Vorstandsbeschlüsse und Anträge, automatische Nummernvergabe, konfigurierbare Antragstypen, Voting-Integration, Beschlussbuch für alle beschlossenen Anträge'],
                [null, '🤝', 'Sitzungen', 'Sitzungsplanung mit Video-Links, Teilnehmerverwaltung, Status-Workflow (Vorbereitung → Aktiv → Beendet → Protokoll)'],
                [null, '📋', 'Tagesordnung', 'TOPs mit Kategorien, Kommentare zur Vorbereitung, Live-Diskussion während Sitzung, Abstimmungsverwaltung'],
                [null, '📄', 'Protokolle', 'Automatische Protokollerstellung aus TOPs, Freigabe-Workflow, Änderungswünsche, PDF-Export, Archivierung'],
                [null, '📆', 'Termin finden', 'Terminabstimmung per Umfrage, automatische Kalendereinträge (.ics), Übersicht geplanter Termine'],
                [null, '✅', 'Erledigen', 'Persönliche Aufgabenliste mit Fälligkeiten, Prioritäten, Benachrichtigungen, Kalender-Export (.ics)'],
                ['opinion', '📊', 'Meinungsbild', 'Anonyme Umfragen zur Stimmungsabfrage, schnelles Feedback-Tool, Ergebnisauswertung in Echtzeit'],
                ['kalender', '📅', 'Kalender', 'Termine der nächsten 90 Tage, Sitzungen automatisch eingebunden, wiederkehrende Termine, Abstimmungsfristen mit E-Mail-Erinnerung'],
                [null, '📁', 'Dokumente', 'Zentrale Dokumentenverwaltung, Kategorisierung, Freigabe-Verwaltung, Versionierung, Archiv-Funktion'],
                ['mv_beschluesse', '📜', 'MV-Beschlüsse', 'Beschlüsse der Mitgliederversammlung erfassen und nachschlagen'],
                ['texte', '✍️', 'Textbearbeitung', 'Gemeinsames Erstellen von Texten (Pressemitteilungen, Stellungnahmen), absatzweise Bearbeitung mit Lock-System, Online-User-Anzeige, Versionierung'],
                ['vertretung', '🏖️', 'Abwesenheiten', 'Abwesenheitsverwaltung mit Zeiträumen, Vertretungsregelungen, automatische Anzeige in Meeting-Planung'],
                ['hinweise', '🔔', 'Hinweiskasten', 'Persönliche Übersicht offener Abstimmungen, Aufgaben, Termine und Abwesenheiten am Seitenanfang'],
            ];
            // Eingeschaltete Bereiche zuerst (in Menü-Reihenfolge), danach die ausgeschalteten (ausgegraut)
            $features_an = [];
            $features_aus = [];
            foreach ($features as $f) {
                if ($f[0] === null || sv_module_enabled($pdo, $f[0])) {
                    $features_an[] = $f;
                } else {
                    $features_aus[] = $f;
                }
            }
            ?>
            <div class="features-grid">
                <?php foreach ($features_an as $f): ?>
                <div class="feature-item">
                    <span class="icon"><?= $f[1] ?></span>
                    <span class="text"><strong><?= htmlspecialchars($f[2]) ?>:</strong> <?= htmlspecialchars($f[3]) ?></span>
                </div>
                <?php endforeach; ?>
                <?php foreach ($features_aus as $f): ?>
                <div class="feature-item disabled" title="In dieser Installation ausgeschaltet">
                    <span class="icon"><?= $f[1] ?></span>
                    <span class="text"><strong><?= htmlspecialchars($f[2]) ?>:</strong> <?= htmlspecialchars($f[3]) ?> <sup>1</sup></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($features_aus): ?>
            <p class="features-footnote"><sup>1</sup> Ausgegraut: Dieser Bereich ist in dieser Installation per Modulschalter ausgeschaltet. Er kann im Admin-Bereich (Module) zugeschaltet werden.</p>
            <?php endif; ?>
        </div>

        <!-- Demo Login -->
        <div class="demo-box">
            <h2>🎭 Demo-Zugang - Testbenutzer auswählen:</h2>

            <?php if (!empty($demo_members)): ?>
                <div class="demo-grid">
                    <?php foreach ($demo_members as $member): ?>
                        <a href="?demo_email=<?php echo urlencode($member['email']); ?>" class="demo-btn">
                            <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                            <small><?php echo htmlspecialchars($member['role']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 20px 0;">
                    Keine Demo-Benutzer. Bitte <code>tools/demo_import.php</code> ausführen.
                </p>
            <?php endif; ?>

            <div class="doc-links">
                <a href="doku.php" target="_blank">📖 Dokumentation</a>
                <a href="docs/INSTALL.md" target="_blank">⚙️ Installation</a>
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
