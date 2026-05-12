<?php
/**
 * antrag_bearbeiten.php - Antrag bearbeiten
 *
 * Bearbeitung eines bestehenden Antrags
 * Rechte: aktiv > 10
 */

session_start();
require_once 'session_config.php';
require_once 'config.php';

// Prüfen ob eingeloggt
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

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

// Hilfsfunktion: aktiv-Level des aktuellen Users holen
function getUserAktivLevel($pdo, $member_id) {
    $stmt = $pdo->prepare("SELECT aktiv FROM berechtigte WHERE ID = ?");
    $stmt->execute([$member_id]);
    $result = $stmt->fetch();
    return $result ? (int)$result['aktiv'] : 0;
}

// Berechtigungen prüfen
$user_aktiv = getUserAktivLevel($pdo, $_SESSION['member_id']);

if ($user_aktiv <= 10) {
    die("Keine Berechtigung. Sie benötigen aktiv > 10 um Anträge zu bearbeiten.");
}

// Antragsnummer aus URL
$antrnr = $_GET['antrnr'] ?? '';

if (!$antrnr) {
    die("Keine Antragsnummer angegeben.");
}

// Antrag laden
$stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
$stmt->execute([$antrnr]);
$antrag = $stmt->fetch();

if (!$antrag) {
    die("Antrag nicht gefunden.");
}

// Bei POST: Speichern
$saved = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $update = $pdo->prepare("
            UPDATE antraege SET
                titel = ?,
                beschluss = ?,
                begr = ?,
                pers = ?,
                sach = ?,
                fintext = ?,
                fin = ?,
                bart = ?,
                verant = ?,
                ressort1 = ?,
                ressort2 = ?,
                wichtig = ?,
                int_ext = ?,
                hinweis = ?,
                lzugriff = NOW()
            WHERE antrnr = ?
        ");

        // Automatik: bart basierend auf fin berechnen
        $fin = floatval($_POST['fin'] ?? 0);
        $bart = 'B'; // Default: Vorstandsbeschluss

        if ($fin <= 600) {
            $bart = 'V'; // Verfügung
        } elseif ($fin <= 3000) {
            $bart = 'R'; // Ressortbeschluss
        }

        $update->execute([
            $_POST['titel'],
            $_POST['beschluss'],
            $_POST['begr'] ?? null,
            $_POST['pers'] ?? null,
            $_POST['sach'] ?? null,
            $_POST['fintext'] ?? null,
            $fin,
            $bart,
            $_POST['verant'] ?? null,
            $_POST['ressort1'] ?? null,
            $_POST['ressort2'] ?? null,
            isset($_POST['wichtig']) ? 1 : 0,
            $_POST['int_ext'] ?? null,
            $_POST['hinweis'] ?? null,
            $antrnr
        ]);

        $saved = true;

        // Antrag neu laden
        $stmt->execute([$antrnr]);
        $antrag = $stmt->fetch();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Ressort-Liste für Dropdown
$ressorts_stmt = $pdo->query("SELECT DISTINCT ressort FROM ressortliste ORDER BY ressort");
$ressorts = $ressorts_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrag bearbeiten: <?= htmlspecialchars($antrag['antrnr']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        .antrnr {
            color: #0066cc;
            font-size: 14px;
        }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #0066cc;
            color: white;
        }
        .btn-primary:hover {
            background: #0052a3;
        }
        .btn-secondary {
            background: #666;
            color: white;
        }
        .btn-secondary:hover {
            background: #555;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #0066cc;
            text-decoration: none;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .bart-display {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 12px;
            margin-left: 10px;
        }
        .bart-v {
            background: #d4edda;
            color: #155724;
        }
        .bart-r {
            background: #fff3cd;
            color: #856404;
        }
        .bart-b {
            background: #cce5ff;
            color: #004085;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const finInput = document.getElementById('fin');
            const bartInfo = document.getElementById('bart-info');

            function updateBartDisplay() {
                const fin = parseFloat(finInput.value) || 0;
                let bart, bartLabel, bartClass;

                if (fin <= 600) {
                    bart = 'V';
                    bartLabel = 'Verfügung';
                    bartClass = 'bart-v';
                } else if (fin <= 3000) {
                    bart = 'R';
                    bartLabel = 'Ressortbeschluss';
                    bartClass = 'bart-r';
                } else {
                    bart = 'B';
                    bartLabel = 'Vorstandsbeschluss';
                    bartClass = 'bart-b';
                }

                bartInfo.innerHTML = `
                    <strong>Automatische Zuordnung:</strong>
                    <span class="bart-display ${bartClass}">${bartLabel} (${bart})</span>
                    <br><small>≤600€ = Verfügung | 601-3000€ = Ressortbeschluss | >3000€ = Vorstandsbeschluss</small>
                `;
            }

            finInput.addEventListener('input', updateBartDisplay);
            updateBartDisplay(); // Initial
        });
    </script>
</head>
<body>
    <div class="container">
        <a href="antragsliste.php" class="back-link">← Zurück zur Antragsliste</a>

        <div class="header">
            <h1>Antrag bearbeiten</h1>
            <div class="antrnr"><?= htmlspecialchars($antrag['antrnr']) ?></div>
        </div>

        <?php if ($saved): ?>
            <div class="alert alert-success">
                ✓ Antrag erfolgreich gespeichert!
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ✗ Fehler: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-container">
            <div class="form-group">
                <label for="titel">Titel *</label>
                <input type="text" id="titel" name="titel"
                       value="<?= htmlspecialchars($antrag['titel']) ?>" required>
            </div>

            <div class="form-group">
                <label for="beschluss">Beschlusstext *</label>
                <textarea id="beschluss" name="beschluss" required><?= htmlspecialchars($antrag['beschluss']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="begr">Begründung</label>
                <textarea id="begr" name="begr"><?= htmlspecialchars($antrag['begr'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="pers">Personelle Auswirkungen</label>
                    <textarea id="pers" name="pers"><?= htmlspecialchars($antrag['pers'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="sach">Sachliche Auswirkungen</label>
                    <textarea id="sach" name="sach"><?= htmlspecialchars($antrag['sach'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="fin">Betrag (€)</label>
                <input type="number" id="fin" name="fin" step="0.01" min="0"
                       value="<?= htmlspecialchars($antrag['fin'] ?? '0') ?>">
                <div class="help-text" id="bart-info">
                    Automatik:
                    ≤600€ = Verfügung |
                    601-3000€ = Ressortbeschluss |
                    >3000€ = Vorstandsbeschluss
                </div>
            </div>

            <div class="form-group">
                <label for="fintext">Finanzielle Auswirkungen (Beschreibung)</label>
                <textarea id="fintext" name="fintext"><?= htmlspecialchars($antrag['fintext'] ?? '') ?></textarea>
                <div class="help-text">Textliche Beschreibung der finanziellen Auswirkungen</div>
            </div>

            <div class="form-group">
                <label for="verant">Verantwortlich</label>
                <input type="text" id="verant" name="verant"
                       value="<?= htmlspecialchars($antrag['verant'] ?? '') ?>">
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="ressort1">Ressort 1</label>
                    <select id="ressort1" name="ressort1">
                        <option value="">-- Kein Ressort --</option>
                        <?php foreach ($ressorts as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"
                                    <?= $antrag['ressort1'] === $r ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ressort2">Ressort 2</label>
                    <select id="ressort2" name="ressort2">
                        <option value="">-- Kein Ressort --</option>
                        <?php foreach ($ressorts as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"
                                    <?= $antrag['ressort2'] === $r ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="int_ext">Intern/Extern</label>
                <select id="int_ext" name="int_ext">
                    <option value="">-- Nicht angegeben --</option>
                    <option value="i" <?= $antrag['int_ext'] === 'i' ? 'selected' : '' ?>>Intern</option>
                    <option value="e" <?= $antrag['int_ext'] === 'e' ? 'selected' : '' ?>>Extern</option>
                </select>
            </div>

            <div class="form-group">
                <label for="hinweis">Hinweise</label>
                <textarea id="hinweis" name="hinweis"><?= htmlspecialchars($antrag['hinweis'] ?? '') ?></textarea>
            </div>

            <div class="form-group checkbox-group">
                <input type="checkbox" id="wichtig" name="wichtig" value="1"
                       <?= $antrag['wichtig'] ? 'checked' : '' ?>>
                <label for="wichtig" style="margin: 0;">Als wichtig markieren</label>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="antragsliste.php" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</body>
</html>
