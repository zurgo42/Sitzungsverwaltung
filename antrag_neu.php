<?php
/**
 * antrag_neu.php - Neuen Antrag erstellen
 *
 * Erzeugt einen neuen Antrag mit automatischer Nummernvergabe
 * und leitet zur Bearbeitung weiter.
 */

require_once 'session_config.php';
session_start();
require_once 'config.php';
require_once 'config_adapter.php';
require_once 'member_functions.php';
require_once 'includes/antragstypen_helper.php';
require_once 'protokoll_helper.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['member_id'];

// Datenbankverbindung
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// Berechtigung prüfen: aktiv > 10 erforderlich
$current_user = get_member_by_id($pdo, $current_user_id);
if (!$current_user || (int)($current_user['aktiv'] ?? 0) <= 10) {
    die("Keine Berechtigung. Sie benötigen aktiv > 10 um Anträge zu stellen.");
}

// Antragstypen-Config laden
$bart_config = lade_antragstypen_config($pdo);
$aktive_typen = get_aktive_typen($bart_config);

/**
 * Generiert eine neue Folgenummer für Anträge
 * Basierend auf der VTool-Funktion folgenummer()
 *
 * Format: A + YYMMDD + laufende Nummer (01-99)
 * Beispiel: A26051401 (erster Antrag am 14.05.2026)
 *
 * @param PDO $pdo Datenbankverbindung
 * @param string $prefix Buchstabe (Standard: 'A' für Antrag)
 * @param string $date Optional: Datum im Format YYMMDD (Standard: heute)
 * @return string Die neue Antragsnummer
 */
function generiereAntragsnummer($pdo, $prefix = 'A', $date = '') {
    // Präfix erstellen (z.B. A260514)
    if ($date === '') {
        $date_part = date('ymd'); // YYMMDD Format
    } else {
        $date_part = $date;
    }

    $prefix_pattern = $prefix . $date_part;

    // Suche nach bestehenden Anträgen mit diesem Präfix
    $stmt = $pdo->prepare("
        SELECT antrnr
        FROM " . TABLE_ANTRAEGE . "
        WHERE antrnr LIKE ?
        ORDER BY antrnr DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix_pattern . '%']);
    $existing = $stmt->fetch();
    $a_num = $existing ? (int)substr($existing['antrnr'], -2) : 0;

    // VS-umbenannte Einträge in TABLE_ANTRAEGE berücksichtigen
    $stmt->execute(['VS' . $date_part . '%']);
    $existing_vs = $stmt->fetch();
    $vs_num_a = $existing_vs ? (int)substr($existing_vs['antrnr'], -2) : 0;

    // Auch manuell in TABLE_BESCHLUESSE eingetragene VS-Nummern prüfen
    $stmt_b = $pdo->prepare("SELECT antrnr FROM " . TABLE_BESCHLUESSE . " WHERE antrnr LIKE ? ORDER BY antrnr DESC LIMIT 1");
    $stmt_b->execute(['VS' . $date_part . '%']);
    $existing_b = $stmt_b->fetch();
    $vs_num_b = $existing_b ? (int)substr($existing_b['antrnr'], -2) : 0;

    $last_number = max($a_num, $vs_num_a, $vs_num_b);

    if ($last_number === 0) {
        return $prefix_pattern . '01';
    }

    $new_number = $last_number + 1;

    // Prüfen ob Limit erreicht
    if ($new_number > 99) {
        throw new Exception("Tageslimit für Anträge erreicht (max. 99 pro Tag)");
    }

    // Neue Nummer mit führender Null formatieren
    return $prefix_pattern . str_pad($new_number, 2, '0', STR_PAD_LEFT);
}

// Prüfen ob Typen verfügbar sind
if (empty($aktive_typen)) {
    die('Fehler: Keine Antragstypen aktiviert. Bitte kontaktiere einen Administrator.');
}

// Wenn POST-Request: Antrag erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_bart = $_POST['bart'] ?? '';

    // Validierung
    if (!isset($aktive_typen[$selected_bart])) {
        die('Fehler: Ungültiger Antragstyp ausgewählt.');
    }

    try {
        // Neue Antragsnummer generieren
        $neue_antrnr = generiereAntragsnummer($pdo);

        // Neuen Antrag in Datenbank erstellen (Minimalversion im Status A = Editing)
        $stmt = $pdo->prepare("
            INSERT INTO " . TABLE_ANTRAEGE . " (
                antrnr,
                antrst,
                bart,
                titel,
                beschluss,
                fin,
                lzugriff
            ) VALUES (?, ?, ?, '', '', '0', NOW())
        ");

        $stmt->execute([
            $neue_antrnr,
            $current_user_id,
            $selected_bart
        ]);

        // Protokollierung
        [$_prot_mnr, $_prot_kurz] = get_protokoll_user($current_user);
        protokoll($pdo, $_prot_mnr, $_prot_kurz, 'Antrag-Neu', $neue_antrnr . ' (' . $selected_bart . ')');

        // E-Mail-Benachrichtigung: Neuer Antrag
        if (!function_exists('nm_event_antrag_neu') && file_exists(__DIR__ . '/notification_mailer.php')) {
            require_once __DIR__ . '/notification_mailer.php';
        }
        if (function_exists('nm_event_antrag_neu')) {
            $bart_label = isset($aktive_typen[$selected_bart]['name']) ? $aktive_typen[$selected_bart]['name'] : $selected_bart;
            nm_event_antrag_neu($pdo, $neue_antrnr, '', $bart_label);
        }

        // Zur Bearbeitung weiterleiten
        header('Location: antrag_bearbeiten.php?antrnr=' . urlencode($neue_antrnr) . '&created=1');
        exit;

    } catch (Exception $e) {
        // Fehlerbehandlung
        error_log("Fehler beim Erstellen eines neuen Antrags: " . $e->getMessage());
        $error_message = 'Fehler beim Erstellen: ' . $e->getMessage();
    }
}

// Wenn nur ein Typ aktiv: Direkt erstellen ohne Auswahl
if (count($aktive_typen) === 1) {
    $default_bart = array_key_first($aktive_typen);
    $_POST['bart'] = $default_bart;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // Script wird oben nochmal ausgeführt
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Mehrere Typen: Auswahlformular anzeigen
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neuer Antrag</title>
    <link rel="stylesheet" href="style.css">
    <script>
        // Dark Mode automatisch von index.php übernehmen
        (function() {
            const hasCookie = document.cookie.split(';').some(c => c.trim().startsWith('darkMode='));
            if (hasCookie && document.cookie.includes('darkMode=enabled')) {
                document.documentElement.classList.add('dark-mode');
            } else if (!hasCookie) {
                const savedDarkMode = localStorage.getItem('darkMode');
                if (savedDarkMode === 'enabled') {
                    document.documentElement.classList.add('dark-mode');
                }
            }
        })();
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 40px 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: var(--bg-primary);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow-color);
            padding: 40px;
            border: 1px solid var(--border-color);
        }

        h1 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 24px;
        }

        .subtitle {
            color: var(--text-secondary);
            margin-bottom: 30px;
            font-size: 14px;
        }

        .type-option {
            border: 2px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .type-option:hover {
            border-color: var(--primary);
            background: var(--hover-bg);
        }

        .type-option input[type="radio"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .type-option label {
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            color: var(--text-primary);
        }

        .type-content {
            flex: 1;
        }

        .type-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .type-desc {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .type-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: var(--dunkelblau);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--neutral);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 12px 30px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: var(--hover-bg);
        }

        .error {
            background: rgba(211, 47, 47, 0.1);
            color: var(--danger);
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid var(--danger);
        }

        /* Mobile Optimierung */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 20px;
                max-width: 100vw;
                overflow-x: hidden;
            }

            h1 {
                font-size: 20px;
            }

            .type-option {
                padding: 15px;
            }

            .type-name {
                font-size: 16px;
            }

            .btn-primary,
            .btn-secondary {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Neuen Antrag erstellen</h1>
        <p class="subtitle">Wähle die Art des Antrags aus:</p>

        <?php if (isset($error_message)): ?>
            <div class="error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php foreach ($aktive_typen as $typ => $bezeichnung): ?>
                <div class="type-option">
                    <label>
                        <input type="radio" name="bart" value="<?= htmlspecialchars($typ) ?>" required>
                        <div class="type-content">
                            <div class="type-name"><?= htmlspecialchars($bezeichnung) ?></div>
                            <?php
                            $beschreibung = get_typ_beschreibung($typ, $bart_config);
                            if ($beschreibung):
                            ?>
                                <div class="type-desc"><?= htmlspecialchars($beschreibung) ?></div>
                            <?php endif; ?>

                            <?php
                            $meta_info = [];
                            if ($bart_config["bart_{$typ}_betrag_aktiv"] ?? false) {
                                $limit = $bart_config["bart_{$typ}_betrag_limit"] ?? 0;
                                if ($limit > 0) {
                                    $meta_info[] = "Max. {$limit}€";
                                }
                            }
                            if ($bart_config["bart_{$typ}_wartezeit_aktiv"] ?? false) {
                                $tage = $bart_config["bart_{$typ}_wartezeit_tage"] ?? 0;
                                if ($tage > 0) {
                                    $meta_info[] = "Wartezeit: {$tage} Tage";
                                }
                            }
                            if (!empty($meta_info)):
                            ?>
                                <div class="type-meta"><?= htmlspecialchars(implode(' • ', $meta_info)) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-primary">Antrag erstellen</button>
            <a href="index.php?tab=proposals" class="btn-secondary">Abbrechen</a>
        </form>
    </div>
</body>
</html>
