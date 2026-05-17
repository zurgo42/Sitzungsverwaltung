<?php
/**
 * antrag_ansehen.php - Read-Only Antragsansicht
 *
 * Zeigt alle Details eines Antrags in einer übersichtlichen,
 * nicht-editierbaren Ansicht.
 *
 * Verwendbar für:
 * - A-Anträge (während Bearbeitung)
 * - B-Anträge (während Abstimmung)
 * - VS-Anträge (Beschlussbuch)
 * - X/Z-Anträge (gelöschte/zurückgezogene)
 */

session_start();
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$user_stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
$user_stmt->execute([$_SESSION['member_id']]);
$user = $user_stmt->fetch();

$antrnr = $_GET['antrnr'] ?? '';
if (!$antrnr) {
    die("Keine Antragsnummer angegeben.");
}

// Antrag laden mit allen verknüpften Daten
$stmt = $pdo->prepare("
    SELECT a.*,
           b.Vorname, b.Name, b.KurzN as AntragstellerKurz,
           r1.Ressort as ressort1_name,
           r2.Ressort as ressort2_name
    FROM antraege a
    LEFT JOIN berechtigte b ON a.antrst = b.ID
    LEFT JOIN ressortliste r1 ON a.ressort1 = r1.ID
    LEFT JOIN ressortliste r2 ON a.ressort2 = r2.ID
    WHERE a.antrnr = ?
");
$stmt->execute([$antrnr]);
$antrag = $stmt->fetch();

if (!$antrag) {
    die("Antrag nicht gefunden.");
}

// Berechtigungen prüfen
$kann_intern_sehen = ($user['aktiv'] > 17 || $user['Funktion'] === 'VA' || $user['is_admin'] == 1);
if ($antrag['int_ext'] === 'i' && !$kann_intern_sehen) {
    die("Keine Berechtigung, diesen Antrag anzusehen.");
}

// Verfügungsberechtigte laden
$verf1_name = $verf2_name = '';
if ($antrag['verf1']) {
    $v_stmt = $pdo->prepare("SELECT Vorname, Name, KurzN FROM berechtigte WHERE ID = ?");
    $v_stmt->execute([$antrag['verf1']]);
    $v = $v_stmt->fetch();
    $verf1_name = $v ? $v['Vorname'] . ' ' . $v['Name'] : '';
}
if ($antrag['verf2']) {
    $v_stmt = $pdo->prepare("SELECT Vorname, Name, KurzN FROM berechtigte WHERE ID = ?");
    $v_stmt->execute([$antrag['verf2']]);
    $v = $v_stmt->fetch();
    $verf2_name = $v ? $v['Vorname'] . ' ' . $v['Name'] : '';
}

// Kann bearbeiten?
$prefix = substr($antrnr, 0, 1);
$kann_bearbeiten = (
    $prefix === 'A' &&
    ($user['aktiv'] > 10) &&
    ($antrag['antrst'] == $user['ID'] || $user['aktiv'] >= 18)
);

$bart_text = ['V' => 'Verfügung', 'R' => 'Ressortbeschluss', 'B' => 'Vorstandsbeschluss'];
$int_ext_text = ['e' => '🌐 Extern (alle Ms)', 'n' => '👥 Nicht öffentlich (Führung)', 'i' => '🔒 Intern (nur Vorstand)'];
$sofort_text = [0 => 'Normal', 1 => '🔥 Eilig', 2 => '⚡ Sehr eilig'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrag <?= htmlspecialchars($antrnr) ?> - Ansicht</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="antrag-styles.css">
    <script>
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
    <style>
        .view-section {
            background: var(--bg-primary);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        .view-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .view-label {
            font-weight: 600;
            color: var(--text-secondary);
        }
        .view-value {
            color: var(--text-primary);
        }
        .view-text-block {
            background: var(--bg-secondary);
            padding: 12px;
            border-radius: 4px;
            border-left: 4px solid var(--primary);
            margin-top: 8px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <button onclick="toggleDarkMode()" class="btn btn-secondary" style="float: right; margin-bottom: 10px;">
        🌓 Dark Mode
    </button>

    <div class="container">
        <a href="antragsliste.php" class="back-link">← Zurück zur Liste</a>

        <div class="header">
            <h1>Antrag <?= htmlspecialchars($antrnr) ?></h1>
            <div class="actions">
                <?php if ($kann_bearbeiten): ?>
                    <a href="antrag_bearbeiten.php?antrnr=<?= urlencode($antrnr) ?>" class="btn btn-primary">✏️ Bearbeiten</a>
                <?php endif; ?>
                <?php if ($prefix === 'B'): ?>
                    <a href="abstimmungen.php?antrnr=<?= urlencode($antrnr) ?>" class="btn btn-secondary">🗳️ Zur Abstimmung</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sektion 1: Basisdaten -->
        <div class="view-section section-gray-1">
            <div class="section-header">Basisdaten</div>

            <div class="view-row">
                <div class="view-label">Antragsnummer:</div>
                <div class="view-value"><strong><?= htmlspecialchars($antrnr) ?></strong></div>
            </div>
            <div class="view-row">
                <div class="view-label">Antragsteller:</div>
                <div class="view-value"><?= htmlspecialchars($antrag['Vorname'] . ' ' . $antrag['Name']) ?> (<?= htmlspecialchars($antrag['AntragstellerKurz']) ?>)</div>
            </div>
            <div class="view-row">
                <div class="view-label">Beschlussart:</div>
                <div class="view-value"><?= $bart_text[$antrag['bart']] ?? $antrag['bart'] ?></div>
            </div>
            <?php if ($antrag['verf1']): ?>
            <div class="view-row">
                <div class="view-label">Abstimmung durch:</div>
                <div class="view-value">
                    <?= htmlspecialchars($verf1_name) ?>
                    <?php if ($verf2_name): ?>
                        + <?= htmlspecialchars($verf2_name) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="view-row">
                <div class="view-label">Ressort:</div>
                <div class="view-value">
                    <?= htmlspecialchars($antrag['ressort1_name'] ?? '') ?>
                    <?php if ($antrag['ressort2_name']): ?>
                        + <?= htmlspecialchars($antrag['ressort2_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="view-row">
                <div class="view-label">Verantwortlich:</div>
                <div class="view-value"><?= htmlspecialchars($antrag['verant'] ?? '') ?></div>
            </div>
            <div class="view-row">
                <div class="view-label">Sichtbarkeit:</div>
                <div class="view-value"><?= $int_ext_text[$antrag['int_ext']] ?? 'Extern' ?></div>
            </div>
            <div class="view-row">
                <div class="view-label">Verein/Stiftung:</div>
                <div class="view-value"><?= $antrag['verein'] === 'S' ? 'Stiftung' : 'Verein' ?></div>
            </div>
            <?php if ($antrag['sofort']): ?>
            <div class="view-row">
                <div class="view-label">Priorität:</div>
                <div class="view-value"><?= $sofort_text[$antrag['sofort']] ?? 'Normal' ?></div>
            </div>
            <?php endif; ?>
            <?php if ($antrag['praesenz']): ?>
            <div class="view-row">
                <div class="view-label">Abstimmungsform:</div>
                <div class="view-value"><?= $antrag['praesenz'] === 'praesenz' ? 'Präsenzsitzung' : 'Online' ?></div>
            </div>
            <?php endif; ?>
            <?php if ($antrag['thread']): ?>
            <div class="view-row">
                <div class="view-label">Forum-Thread:</div>
                <div class="view-value">
                    <a href="https://vorstand.mensa.de/forum/index.php?id=<?= (int)$antrag['thread'] ?>" target="forum" style="color: var(--primary);">
                        → Thread #<?= (int)$antrag['thread'] ?> öffnen
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <div class="view-row">
                <div class="view-label">Letzter Zugriff:</div>
                <div class="view-value"><?= $antrag['lzugriff'] ? date('d.m.Y H:i', strtotime($antrag['lzugriff'])) : '-' ?></div>
            </div>
        </div>

        <!-- Sektion 2: Antrag -->
        <div class="view-section section-gray-2">
            <div class="section-header">Antrag</div>

            <h3 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 600;">Titel:</h3>
            <div style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">
                <?= nl2br(htmlspecialchars($antrag['titel'])) ?>
            </div>

            <?php if ($antrag['beschluss']): ?>
            <h3 style="margin: 15px 0 8px 0; font-size: 15px; font-weight: 600;">Wortlaut des Beschlusses:</h3>
            <div class="view-text-block">
                <?= nl2br(htmlspecialchars($antrag['beschluss'])) ?>
            </div>
            <?php endif; ?>

            <?php if ($antrag['begr']): ?>
            <h3 style="margin: 15px 0 8px 0; font-size: 15px; font-weight: 600;">Begründung:</h3>
            <div class="view-text-block" style="border-left-color: var(--success);">
                <?= nl2br(htmlspecialchars($antrag['begr'])) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sektion 3: Auswirkungen -->
        <div class="view-section section-gray-3">
            <div class="section-header">Auswirkungen</div>

            <?php if ($antrag['fin'] > 0): ?>
            <h3 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 600;">Finanzielle Auswirkungen:</h3>
            <div style="font-size: 22px; font-weight: 700; color: var(--danger); margin-bottom: 8px;">
                <?= number_format($antrag['fin'], 0, ',', '.') ?> €
            </div>
            <?php if ($antrag['fintext']): ?>
            <div class="view-text-block" style="border-left-color: var(--danger);">
                <?= nl2br(htmlspecialchars($antrag['fintext'])) ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($antrag['pers'] && $antrag['pers'] !== 'keine'): ?>
            <h3 style="margin: 15px 0 8px 0; font-size: 15px; font-weight: 600;">Personelle Auswirkungen:</h3>
            <div class="view-text-block">
                <?= nl2br(htmlspecialchars($antrag['pers'])) ?>
            </div>
            <?php endif; ?>

            <?php if ($antrag['sach'] && $antrag['sach'] !== 'keine'): ?>
            <h3 style="margin: 15px 0 8px 0; font-size: 15px; font-weight: 600;">Sachliche Auswirkungen:</h3>
            <div class="view-text-block">
                <?= nl2br(htmlspecialchars($antrag['sach'])) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sektion 4: Angebote/Unterlagen -->
        <?php
        $has_files = false;
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($antrag["file$i"])) {
                $has_files = true;
                break;
            }
        }
        if ($has_files):
        ?>
        <div class="view-section section-gray-4">
            <div class="section-header">Angebote / Unterlagen</div>

            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php if (!empty($antrag["file$i"])): ?>
                <div style="margin-bottom: 10px;">
                    <a href="<?= htmlspecialchars($antrag["file$i"]) ?>" target="_blank"
                       style="color: var(--primary); font-weight: 600; text-decoration: none;">
                        📎 <?= basename($antrag["file$i"]) ?>
                    </a>
                    <?php if (!empty($antrag["filetext$i"])): ?>
                        <div style="font-size: 13px; color: var(--text-secondary); margin-top: 3px; margin-left: 20px;">
                            <?= htmlspecialchars($antrag["filetext$i"]) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <!-- Sektion 5: Hinweise/Protokoll -->
        <?php if ($antrag['hinweis']): ?>
        <div class="view-section section-gray-5">
            <div class="section-header">Hinweise / Protokoll</div>
            <div class="view-text-block" style="border-left-color: var(--warning); background: rgba(250, 170, 0, 0.1);">
                <?= nl2br(htmlspecialchars($antrag['hinweis'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Zurück-Button -->
        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border-color);">
            <a href="antragsliste.php" class="btn btn-secondary">← Zurück zur Liste</a>
            <?php if ($kann_bearbeiten): ?>
                <a href="antrag_bearbeiten.php?antrnr=<?= urlencode($antrnr) ?>" class="btn btn-primary">✏️ Bearbeiten</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
