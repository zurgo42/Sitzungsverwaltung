<?php
/**
 * abstimmungen.php - Abstimmungen über Anträge
 *
 * Zeigt alle zur Abstimmung stehenden Anträge (B-Präfix)
 * Ermöglicht Vorstandsmitgliedern die Abstimmung
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

// User-Daten laden
$user_stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
$user_stmt->execute([$_SESSION['member_id']]);
$user = $user_stmt->fetch();

$user_aktiv = (int)($user['aktiv'] ?? 0);

// Hilfsfunktion wurde entfernt - jetzt wird antrag_ansehen.php per iframe eingebunden
// (siehe Zeile 562+)

// Alte render_antrag_detail() Funktion nicht mehr benötigt
/*
function render_antrag_detail($pdo, $antrag) {
    // Antragsteller laden
    $antrst_stmt = $pdo->prepare("SELECT Vorname, Name, KurzN FROM berechtigte WHERE ID = ?");
    $antrst_stmt->execute([$antrag['antrst']]);
    $antrst = $antrst_stmt->fetch();

    // Ressorts laden
    $ressort1_name = $ressort2_name = '';
    if ($antrag['ressort1']) {
        $res_stmt = $pdo->prepare("SELECT Ressort FROM svressorts WHERE ID = ?");
        $res_stmt->execute([$antrag['ressort1']]);
        $ressort1_name = $res_stmt->fetchColumn() ?: '';
    }
    if ($antrag['ressort2']) {
        $res_stmt = $pdo->prepare("SELECT Ressort FROM svressorts WHERE ID = ?");
        $res_stmt->execute([$antrag['ressort2']]);
        $ressort2_name = $res_stmt->fetchColumn() ?: '';
    }

    $bart_text = ['V' => 'Verfügung', 'R' => 'Ressortbeschluss', 'B' => 'Vorstandsbeschluss'];
    $int_ext_text = ['e' => '🌐 Extern', 'n' => '👥 Nicht öffentlich', 'i' => '🔒 Intern'];
    ?>

    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
            <h2 style="margin: 0; color: #333; font-size: 20px;">
                Antrag <?= htmlspecialchars($antrag['antrnr']) ?>
            </h2>
            <button onclick="document.getElementById('details_<?= htmlspecialchars($antrag['antrnr']) ?>').style.display = document.getElementById('details_<?= htmlspecialchars($antrag['antrnr']) ?>').style.display === 'none' ? 'block' : 'none'; this.textContent = this.textContent.includes('▼') ? '▲ Details ausblenden' : '▼ Alle Details anzeigen';"
                    class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                ▼ Alle Details anzeigen
            </button>
        </div>

        <!-- Kompakte Übersicht (immer sichtbar) -->
        <div style="display: grid; grid-template-columns: 140px 1fr; gap: 8px; margin-bottom: 15px; font-size: 13px;">
            <div style="font-weight: 600; color: #666;">Antragsteller:</div>
            <div><?= htmlspecialchars(($antrst['Vorname'] ?? '') . ' ' . ($antrst['Name'] ?? '')) ?></div>

            <div style="font-weight: 600; color: #666;">Beschlussart:</div>
            <div><?= $bart_text[$antrag['bart']] ?? $antrag['bart'] ?></div>

            <div style="font-weight: 600; color: #666;">Ressort:</div>
            <div><?= htmlspecialchars($ressort1_name) ?><?= $ressort2_name ? ' + ' . htmlspecialchars($ressort2_name) : '' ?></div>

            <?php if ($antrag['fin'] > 0): ?>
            <div style="font-weight: 600; color: #666;">Betrag:</div>
            <div style="font-weight: 600; color: #d32f2f;"><?= number_format($antrag['fin'], 0, ',', '.') ?> €</div>
            <?php endif; ?>
        </div>

        <!-- Titel und Beschluss -->
        <div style="margin-top: 15px;">
            <h3 style="margin: 0 0 8px 0; font-size: 15px; color: #666;">Beschlusstitel:</h3>
            <div style="font-size: 16px; font-weight: 600; color: #000; margin-bottom: 12px;">
                <?= nl2br(htmlspecialchars($antrag['titel'])) ?>
            </div>

            <?php if ($antrag['beschluss']): ?>
                <h3 style="margin: 12px 0 8px 0; font-size: 15px; color: #666;">Wortlaut des Beschlusses:</h3>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 4px solid #0066cc; font-size: 14px;">
                    <?= nl2br(htmlspecialchars($antrag['beschluss'])) ?>
                </div>
            <?php endif; ?>

            <?php if ($antrag['begr']): ?>
                <h3 style="margin: 12px 0 8px 0; font-size: 15px; color: #666;">Begründung:</h3>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 14px;">
                    <?= nl2br(htmlspecialchars($antrag['begr'])) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Expandierbare Details - VOLLSTÄNDIG für rechtssicheres Votum -->
        <div id="details_<?= htmlspecialchars($antrag['antrnr']) ?>" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 2px solid #e0e0e0;">
            <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #d32f2f; font-weight: 700;">
                ⚠️ Vollständige Antragsinformationen für Abstimmung
            </h3>
            <div style="background: rgba(250, 170, 0, 0.1); padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px; color: #666;">
                Bitte prüfen Sie alle Details vor der Abstimmung. Diese Ansicht enthält ALLE Informationen aus dem Antragsformular.
            </div>

            <!-- Basisdaten -->
            <h4 style="margin: 12px 0 8px 0; font-size: 14px; color: #333; font-weight: 600;">Basisdaten:</h4>
            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 8px; margin-bottom: 15px; font-size: 13px; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                <div style="font-weight: 600; color: #666;">Antragsnummer:</div>
                <div><?= htmlspecialchars($antrag['antrnr']) ?></div>

                <div style="font-weight: 600; color: #666;">Antragsteller:</div>
                <div><?= htmlspecialchars(($antrst['Vorname'] ?? '') . ' ' . ($antrst['Name'] ?? '')) ?> (<?= htmlspecialchars($antrst['KurzN'] ?? '') ?>)</div>

                <div style="font-weight: 600; color: #666;">Beschlussart:</div>
                <div><?= $bart_text[$antrag['bart']] ?? $antrag['bart'] ?></div>

                <?php
                // Verfügungsberechtigte laden falls vorhanden
                $verf1_name = $verf2_name = '';
                if (!empty($antrag['verf1'])) {
                    $v_stmt = $pdo->prepare("SELECT Vorname, Name FROM berechtigte WHERE ID = ?");
                    $v_stmt->execute([$antrag['verf1']]);
                    $v = $v_stmt->fetch();
                    $verf1_name = $v ? $v['Vorname'] . ' ' . $v['Name'] : '';
                }
                if (!empty($antrag['verf2'])) {
                    $v_stmt = $pdo->prepare("SELECT Vorname, Name FROM berechtigte WHERE ID = ?");
                    $v_stmt->execute([$antrag['verf2']]);
                    $v = $v_stmt->fetch();
                    $verf2_name = $v ? $v['Vorname'] . ' ' . $v['Name'] : '';
                }
                if ($verf1_name):
                ?>
                <div style="font-weight: 600; color: #666;">Verfügungsberechtigt:</div>
                <div>
                    <?= htmlspecialchars($verf1_name) ?>
                    <?php if ($verf2_name): ?>
                        + <?= htmlspecialchars($verf2_name) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="font-weight: 600; color: #666;">Ressort:</div>
                <div><?= htmlspecialchars($ressort1_name) ?><?= $ressort2_name ? ' + ' . htmlspecialchars($ressort2_name) : '' ?></div>

                <div style="font-weight: 600; color: #666;">Verantwortlich:</div>
                <div><?= htmlspecialchars($antrag['verant'] ?? '') ?></div>

                <div style="font-weight: 600; color: #666;">Sichtbarkeit:</div>
                <div><?= $int_ext_text[$antrag['int_ext']] ?? 'Extern' ?></div>

                <div style="font-weight: 600; color: #666;">Verein/Stiftung:</div>
                <div><?= $antrag['verein'] === 'S' ? 'Stiftung' : 'Verein' ?></div>

                <?php if ($antrag['sofort']): ?>
                <div style="font-weight: 600; color: #666;">Priorität:</div>
                <div><?= $antrag['sofort'] == 1 ? '🔥 Eilig' : ($antrag['sofort'] == 2 ? '⚡ Sehr eilig' : 'Normal') ?></div>
                <?php endif; ?>

                <?php if (isset($antrag['praesenz'])): ?>
                <div style="font-weight: 600; color: #666;">Abstimmungsform:</div>
                <div><?= $antrag['praesenz'] == 1 ? 'Präsenzsitzung' : 'Online' ?></div>
                <?php endif; ?>

                <?php if ($antrag['thread']): ?>
                <div style="font-weight: 600; color: #666;">Forum-Thread:</div>
                <div>
                    <a href="https://vorstand.mensa.de/forum/index.php?id=<?= (int)$antrag['thread'] ?>" target="forum" style="color: #0066cc;">
                        → Thread #<?= (int)$antrag['thread'] ?> öffnen
                    </a>
                </div>
                <?php endif; ?>

                <div style="font-weight: 600; color: #666;">Letzter Zugriff:</div>
                <div><?= $antrag['lzugriff'] ? date('d.m.Y H:i', strtotime($antrag['lzugriff'])) : '-' ?></div>
            </div>

            <!-- Auswirkungen -->
            <h4 style="margin: 15px 0 8px 0; font-size: 14px; color: #333; font-weight: 600;">Auswirkungen:</h4>

            <?php if ($antrag['fintext']): ?>
                <div style="margin-bottom: 12px;">
                    <div style="font-weight: 600; color: #666; font-size: 13px; margin-bottom: 4px;">Finanzielle Details:</div>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 4px solid #d32f2f; font-size: 13px;">
                        <?= nl2br(htmlspecialchars($antrag['fintext'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($antrag['pers'] && $antrag['pers'] !== 'keine'): ?>
                <div style="margin-bottom: 12px;">
                    <div style="font-weight: 600; color: #666; font-size: 13px; margin-bottom: 4px;">Personelle Auswirkungen:</div>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 4px solid #0066cc; font-size: 13px;">
                        <?= nl2br(htmlspecialchars($antrag['pers'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($antrag['sach'] && $antrag['sach'] !== 'keine'): ?>
                <div style="margin-bottom: 12px;">
                    <div style="font-weight: 600; color: #666; font-size: 13px; margin-bottom: 4px;">Sachliche Auswirkungen:</div>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 4px solid #4caf50; font-size: 13px;">
                        <?= nl2br(htmlspecialchars($antrag['sach'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dateien -->
            <?php
            $has_files = false;
            for ($i = 1; $i <= 4; $i++) {
                if (!empty($antrag["file$i"])) {
                    if (!$has_files) {
                        echo '<h4 style="margin: 15px 0 8px 0; font-size: 14px; color: #333; font-weight: 600;">Angehängte Dateien/Angebote:</h4>';
                        $has_files = true;
                    }
                    echo '<div style="margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">';
                    echo '<a href="' . htmlspecialchars($antrag["file$i"]) . '" target="_blank" style="color: #0066cc; font-weight: 600; text-decoration: none;">';
                    echo '📎 ' . basename($antrag["file$i"]) . '</a>';
                    if (!empty($antrag["filetext$i"])) {
                        echo '<div style="margin-top: 3px; font-size: 12px; color: #666; margin-left: 20px;">' . htmlspecialchars($antrag["filetext$i"]) . '</div>';
                    }
                    echo '</div>';
                }
            }
            ?>

            <!-- Hinweise/Protokoll -->
            <?php if ($antrag['hinweis']): ?>
                <h4 style="margin: 15px 0 8px 0; font-size: 14px; color: #333; font-weight: 600;">Hinweise/Protokoll:</h4>
                <div style="background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107; font-size: 13px;">
                    <?= nl2br(htmlspecialchars($antrag['hinweis'])) ?>
                </div>
            <?php endif; ?>

            <!-- Link zur separaten Vollansicht -->
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                <a href="antrag_ansehen.php?antrnr=<?= urlencode($antrag['antrnr']) ?>" target="_blank"
                   class="btn btn-secondary" style="padding: 8px 16px; font-size: 13px;">
                    🔍 In separatem Fenster öffnen
                </a>
            </div>
        </div>
    </div>
    <?php
}
*/

// POST-Verarbeitung: Votum speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['votum_action'])) {
    $antrnr = $_POST['antrnr'] ?? '';
    $abstimmend = (int)($_POST['abstimmend'] ?? 0);
    $votum = (int)($_POST['Votum'] ?? 0);
    $vbegr = $_POST['VBegr'] ?? '';
    $vprot = $_POST['VProt'] ?? '';
    $vbedenk = $_POST['VBedenk'] ?? '';

    if ($antrnr && $abstimmend > 0 && $abstimmend <= 6) {
        try {
            // Prüfen ob User berechtigt ist
            $check_stmt = $pdo->prepare("SELECT VName$abstimmend FROM antraege WHERE antrnr = ?");
            $check_stmt->execute([$antrnr]);
            $vname = $check_stmt->fetchColumn();

            if ($vname == $user['ID']) {
                // Votum speichern
                $update_sql = "UPDATE antraege SET
                    Votum$abstimmend = ?,
                    VDat$abstimmend = NOW(),
                    VBegr$abstimmend = ?,
                    VProt$abstimmend = ?";

                $params = [$votum, $vbegr, $vprot];

                // Bedenkzeit nur bei Votum = 5
                if ($votum == 5 && $vbedenk) {
                    $update_sql .= ", VBedenk$abstimmend = ?";
                    $params[] = $vbedenk;
                }

                $update_sql .= " WHERE antrnr = ?";
                $params[] = $antrnr;

                $pdo->prepare($update_sql)->execute($params);

                // Abstimmung auswerten
                auswerten_abstimmung($pdo, $antrnr);

                header("Location: abstimmungen.php?antrnr=" . urlencode($antrnr) . "&msg=voted");
                exit;
            } else {
                $error = "Sie sind nicht berechtigt, über diesen Antrag abzustimmen.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Hinweis hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hinweis'])) {
    $antrnr = $_POST['antrnr'] ?? '';
    $neuer_hinweis = $_POST['neuerhinweis'] ?? '';

    if ($antrnr && $neuer_hinweis) {
        $stmt = $pdo->prepare("SELECT hinweis FROM antraege WHERE antrnr = ?");
        $stmt->execute([$antrnr]);
        $antrag = $stmt->fetch();

        $hinweis = $antrag['hinweis'] ?? '';
        if ($hinweis) $hinweis .= "\n---\n";
        $hinweis .= date('d.m.Y H:i') . ' (' . $user['KurzN'] . '): ' . $neuer_hinweis;

        $pdo->prepare("UPDATE antraege SET hinweis = ? WHERE antrnr = ?")->execute([$hinweis, $antrnr]);

        header("Location: abstimmungen.php?antrnr=" . urlencode($antrnr) . "&msg=hinweis");
        exit;
    }
}

// Antrag zurückziehen (nur Antragsteller)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zurueckziehen'])) {
    $antrnr = $_POST['antrnr'] ?? '';

    if ($antrnr) {
        $stmt = $pdo->prepare("SELECT antrst FROM antraege WHERE antrnr = ?");
        $stmt->execute([$antrnr]);
        $antrag = $stmt->fetch();

        if ($antrag['antrst'] == $user['ID']) {
            // Z-Präfix für zurückgezogen
            $neue_nr = 'Z' . substr($antrnr, 1);
            $pdo->prepare("UPDATE antraege SET antrnr = ? WHERE antrnr = ?")->execute([$neue_nr, $antrnr]);

            header("Location: abstimmungen.php?msg=withdrawn");
            exit;
        }
    }
}

// Funktion: Abstimmung auswerten
function auswerten_abstimmung($pdo, $antrnr) {
    $stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
    $antrag = $stmt->fetch();

    if (!$antrag) return;

    // Zählen der abgegebenen Stimmen
    $abstimmende = 0;
    $abgestimmt = 0;
    $ja = $nein = $enthaltung = $rueckverweis = $bedenkzeit = $befangen = 0;

    for ($i = 1; $i <= 6; $i++) {
        if (!empty($antrag["VName$i"])) {
            $abstimmende++;
            $votum = (int)($antrag["Votum$i"] ?? 0);

            if ($votum > 0) {
                $abgestimmt++;
                switch ($votum) {
                    case 1: $ja++; break;
                    case 2: $nein++; break;
                    case 3: $enthaltung++; break;
                    case 4: $rueckverweis++; break;
                    case 5: $bedenkzeit++; break;
                    case 6: $befangen++; break;
                }
            }
        }
    }

    // Wenn Bedenkzeit oder Rückverweis: nicht abschließen
    if ($bedenkzeit > 0 || $rueckverweis > 0) {
        return;
    }

    // Prüfen ob alle abgestimmt haben
    if ($abgestimmt < $abstimmende) {
        return; // Noch nicht alle haben abgestimmt
    }

    // Auswertung je nach Beschlussart
    if ($antrag['bart'] === 'V') {
        // Verfügung: Ja = angenommen
        if ($ja > 0 && $nein == 0) {
            beschluss_annehmen($pdo, $antrnr, $antrag);
        } else {
            beschluss_ablehnen($pdo, $antrnr);
        }
    } elseif ($antrag['bart'] === 'R') {
        // Ressortbeschluss: Beide müssen Ja stimmen
        if ($ja == 2 && $nein == 0) {
            beschluss_annehmen($pdo, $antrnr, $antrag);
        } else {
            beschluss_ablehnen($pdo, $antrnr);
        }
    } elseif ($antrag['bart'] === 'B') {
        // Vorstandsbeschluss: Mehr Ja als Nein = angenommen
        if ($ja > $nein) {
            beschluss_annehmen($pdo, $antrnr, $antrag);
        } else {
            beschluss_ablehnen($pdo, $antrnr);
        }
    }
}

// Funktion: Beschluss annehmen (B → VS)
function beschluss_annehmen($pdo, $antrnr, $antrag) {
    $neue_nr = 'VS' . date('ymd') . substr($antrnr, 7);
    $pdo->prepare("UPDATE antraege SET antrnr = ?, warantrag = ? WHERE antrnr = ?")->execute([$neue_nr, $antrnr, $antrnr]);

    // TODO: Email-Benachrichtigung (später)
}

// Funktion: Beschluss ablehnen (B → X)
function beschluss_ablehnen($pdo, $antrnr) {
    $neue_nr = 'X' . substr($antrnr, 1);
    $pdo->prepare("UPDATE antraege SET antrnr = ? WHERE antrnr = ?")->execute([$neue_nr, $antrnr]);

    // TODO: Email-Benachrichtigung (später)
}

// Einzelnen Antrag anzeigen?
$antrnr = $_GET['antrnr'] ?? '';
$show_detail = !empty($antrnr);

if ($show_detail) {
    $stmt = $pdo->prepare("SELECT * FROM antraege WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
    $antrag = $stmt->fetch();

    if (!$antrag) {
        $error = "Antrag nicht gefunden.";
        $show_detail = false;
    }
}

// Alle B-Anträge laden
$antraege_stmt = $pdo->query("
    SELECT a.*, b.Vorname, b.Name, b.KurzN
    FROM antraege a
    LEFT JOIN berechtigte b ON a.antrst = b.ID
    WHERE a.antrnr LIKE 'B%'
    ORDER BY a.antrnr DESC
");
$antraege = $antraege_stmt->fetchAll();

// Prüfen ob User offene Abstimmungen hat
$pending_votes = [];
foreach ($antraege as $a) {
    for ($i = 1; $i <= 6; $i++) {
        if ($a["VName$i"] == $user['ID'] && empty($a["Votum$i"])) {
            $pending_votes[] = $a;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abstimmungen</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="antrag-styles.css">
    <script>
        // Dark Mode automatisch von index.php übernehmen
        (function() {
            const hasCookie = document.cookie.split(';').some(c => c.trim().startsWith('darkMode='));
            if (hasCookie && document.cookie.includes('darkMode=enabled')) {
                document.body.classList.add('dark-mode');
            } else if (!hasCookie) {
                const savedDarkMode = localStorage.getItem('darkMode');
                if (savedDarkMode === 'enabled') {
                    document.body.classList.add('dark-mode');
                }
            }
        })();
    </script>
</head>
<body>
    <div class="container">
        <a href="index.php?tab=proposals" class="back-link">← Zurück zur Übersicht</a>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'voted'): ?>
                <div class="alert alert-success">✓ Ihr Votum wurde gespeichert.</div>
            <?php elseif ($_GET['msg'] === 'hinweis'): ?>
                <div class="alert alert-success">✓ Hinweis wurde hinzugefügt.</div>
            <?php elseif ($_GET['msg'] === 'withdrawn'): ?>
                <div class="alert alert-success">✓ Antrag wurde zurückgezogen.</div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$show_detail && !empty($pending_votes)): ?>
            <!-- Hinweis auf offene Abstimmungen -->
            <div class="alert alert-warning" style="background: rgba(250, 170, 0, 0.15); border-color: #FAAA00; border-left-width: 6px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 32px;">⚠️</div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; font-size: 16px; margin-bottom: 5px; color: #000;">
                            Sie haben <?= count($pending_votes) ?> offene Abstimmung<?= count($pending_votes) > 1 ? 'en' : '' ?>
                        </div>
                        <div style="font-size: 14px; color: #666;">
                            Bitte stimmen Sie über folgende Anträge ab:
                        </div>
                        <div style="margin-top: 10px;">
                            <?php foreach ($pending_votes as $pv): ?>
                                <a href="abstimmungen.php?antrnr=<?= urlencode($pv['antrnr']) ?>"
                                   class="btn btn-warning"
                                   style="display: inline-block; margin-right: 10px; margin-bottom: 5px; padding: 8px 16px; font-size: 13px;">
                                    → <?= htmlspecialchars($pv['antrnr']) ?>: <?= htmlspecialchars(mb_substr($pv['titel'], 0, 50)) ?><?= mb_strlen($pv['titel']) > 50 ? '...' : '' ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($show_detail && $antrag): ?>
            <!-- Detailansicht eines Antrags -->
            <div class="header">
                <h1>Abstimmung über Antrag <?= htmlspecialchars($antrag['antrnr']) ?></h1>
                <a href="abstimmungen.php" class="btn btn-secondary">← Zurück zur Liste</a>
            </div>

            <?php
            // Wichtig-Hinweis anzeigen wenn gesetzt
            if (!empty($antrag['wichtig']) && $antrag['bart'] === 'B') {
                $wichtig_user_stmt = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
                $wichtig_user_stmt->execute([$antrag['wichtig']]);
                $wichtig_kurzn = $wichtig_user_stmt->fetchColumn();
                if ($wichtig_kurzn) {
                    echo '<div class="info-box" style="margin-bottom: 20px; background: rgba(250, 170, 0, 0.15); border-color: #FAAA00;">';
                    echo '<strong>ℹ️ Hinweis:</strong> Vorstandsabstimmung auf Anlass von <strong>' . htmlspecialchars($wichtig_kurzn) . '</strong>';
                    echo '</div>';
                }
            }

            // Antrag anzeigen - Einbettung der vollständigen Antragsansicht
            echo '<div style="border: 2px solid #0066cc; border-radius: 8px; margin-bottom: 20px; overflow: hidden;">';
            echo '<iframe src="antrag_ansehen.php?antrnr=' . urlencode($antrag['antrnr']) . '" style="width: 100%; border: none; min-height: 800px;" id="antrag_frame"></iframe>';
            echo '</div>';
            echo '<script>
                // Iframe-Höhe automatisch anpassen
                window.addEventListener("message", function(e) {
                    if (e.data.height) {
                        document.getElementById("antrag_frame").style.height = e.data.height + "px";
                    }
                });
            </script>';

            // Prüfen ob User abstimmberechtigt ist
            $user_position = 0;
            for ($i = 1; $i <= 6; $i++) {
                if ($antrag["VName$i"] == $user['ID']) {
                    $user_position = $i;
                    break;
                }
            }

            // Abstimmungsstatus anzeigen
            ?>
            <div class="votum-box">
                <h2 style="margin-bottom: 15px; font-size: 18px;">Abstimmungsstatus</h2>

                <?php
                // Alle Abstimmenden anzeigen
                for ($i = 1; $i <= 6; $i++) {
                    if (empty($antrag["VName$i"])) continue;

                    $voter_stmt = $pdo->prepare("SELECT Vorname, Name, KurzN FROM berechtigte WHERE ID = ?");
                    $voter_stmt->execute([$antrag["VName$i"]]);
                    $voter = $voter_stmt->fetch();

                    $votum = (int)($antrag["Votum$i"] ?? 0);
                    $votum_text = ['', 'Ja', 'Nein', 'Enthaltung', 'Rückverweis', 'Bedenkzeit', 'Befangen'];
                    $votum_colors = ['white', '#d4edda', '#f8d7da', '#fff3cd', '#e1bee7', '#fff8dc', '#e0e0e0'];

                    echo '<div style="padding: 10px; margin-bottom: 8px; background: ' . $votum_colors[$votum] . '; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">';
                    echo '<div><strong>' . htmlspecialchars($voter['Vorname'] . ' ' . $voter['Name']) . '</strong>';
                    if ($votum > 0) {
                        echo ' <span style="margin-left: 10px;">→ <strong>' . $votum_text[$votum] . '</strong></span>';
                        if (!empty($antrag["VDat$i"])) {
                            echo ' <span style="color: #666; font-size: 12px;">(' . date('d.m.Y H:i', strtotime($antrag["VDat$i"])) . ')</span>';
                        }
                    } else {
                        echo ' <span style="margin-left: 10px; color: #d32f2f;">→ Stimme steht noch aus</span>';
                    }
                    echo '</div>';

                    // Begründung/Protokollnotiz anzeigen
                    if (!empty($antrag["VBegr$i"]) || !empty($antrag["VProt$i"])) {
                        echo '<details style="margin-top: 5px;"><summary style="cursor: pointer; color: #0066cc; font-size: 12px;">Details</summary>';
                        if (!empty($antrag["VBegr$i"])) {
                            echo '<div style="margin-top: 5px; font-size: 12px; color: #666;"><strong>Bemerkung:</strong> ' . nl2br(htmlspecialchars($antrag["VBegr$i"])) . '</div>';
                        }
                        if (!empty($antrag["VProt$i"])) {
                            echo '<div style="margin-top: 5px; font-size: 12px; color: #333;"><strong>Protokollnotiz:</strong> ' . nl2br(htmlspecialchars($antrag["VProt$i"])) . '</div>';
                        }
                        echo '</details>';
                    }

                    echo '</div>';
                }

                // Wenn User abstimmberechtigt ist und noch nicht abgestimmt hat
                if ($user_position > 0 && empty($antrag["Votum$user_position"])) {
                    ?>
                    <form method="POST" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #0066cc;">
                        <h3 style="margin-bottom: 15px; font-size: 16px; color: #0066cc;">Ihre Abstimmung:</h3>

                        <input type="hidden" name="votum_action" value="1">
                        <input type="hidden" name="antrnr" value="<?= htmlspecialchars($antrag['antrnr']) ?>">
                        <input type="hidden" name="abstimmend" value="<?= $user_position ?>">

                        <div class="votum-options">
                            <label>
                                <input type="radio" name="Votum" value="1" required>
                                <strong>Ja</strong> - Ich stimme dem Antrag zu
                            </label>
                            <label>
                                <input type="radio" name="Votum" value="2">
                                <strong>Nein</strong> - Ich lehne den Antrag ab
                            </label>
                            <?php if ($antrag['bart'] === 'B'): ?>
                                <label>
                                    <input type="radio" name="Votum" value="3">
                                    <strong>Enthaltung</strong> - Ich enthalte mich
                                </label>
                                <label>
                                    <input type="radio" name="Votum" value="4">
                                    <strong>Rückverweis</strong> - Noch nicht abstimmungsreif
                                </label>
                                <label>
                                    <input type="radio" name="Votum" value="5">
                                    <strong>Bedenkzeit</strong> - Ich benötige mehr Zeit
                                </label>
                            <?php endif; ?>
                            <label>
                                <input type="radio" name="Votum" value="6">
                                <strong>Befangen</strong> - Ich bin befangen und nehme nicht teil
                            </label>
                        </div>

                        <div style="margin-top: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Bemerkung / Erläuterung (nicht im Protokoll):</label>
                            <textarea name="VBegr" rows="3"></textarea>
                        </div>

                        <div style="margin-top: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Formelle Protokollnotiz (wird veröffentlicht):</label>
                            <textarea name="VProt" rows="3"></textarea>
                        </div>

                        <div style="margin-top: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Bedenkzeit bis (nur bei Bedenkzeit):</label>
                            <input type="date" name="VBedenk" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>

                        <button type="submit" class="btn" style="margin-top: 20px; padding: 12px 24px; font-size: 16px;">
                            ✓ Abstimmen
                        </button>
                    </form>
                    <?php
                }
                ?>
            </div>

            <!-- Hinweis hinzufügen -->
            <div class="votum-box">
                <h3 style="margin-bottom: 10px; font-size: 16px;">Hinweis für Antragsteller hinzufügen:</h3>
                <form method="POST">
                    <input type="hidden" name="add_hinweis" value="1">
                    <input type="hidden" name="antrnr" value="<?= htmlspecialchars($antrag['antrnr']) ?>">
                    <textarea name="neuerhinweis" rows="3" placeholder="Ihr Hinweis..."></textarea>
                    <button type="submit" class="btn" style="margin-top: 10px;">Hinweis hinzufügen</button>
                </form>
            </div>

            <!-- Antrag zurückziehen (nur Antragsteller) -->
            <?php if ($antrag['antrst'] == $user['ID']): ?>
                <div class="votum-box" style="background: #fff3cd;">
                    <h3 style="margin-bottom: 10px; font-size: 16px; color: #856404;">Antrag zurückziehen</h3>
                    <p style="margin-bottom: 10px; color: #666;">
                        Als Antragsteller können Sie den Antrag während der laufenden Abstimmung zurückziehen.
                    </p>
                    <form method="POST" onsubmit="return confirm('Antrag wirklich zurückziehen?');">
                        <input type="hidden" name="zurueckziehen" value="1">
                        <input type="hidden" name="antrnr" value="<?= htmlspecialchars($antrag['antrnr']) ?>">
                        <button type="submit" class="btn btn-danger">Antrag zurückziehen</button>
                    </form>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Liste aller Abstimmungen -->
            <div class="header">
                <h1>Abstimmungen über Anträge</h1>
                <p style="margin-top: 10px; color: #666;">
                    Hier werden alle zur Abstimmung stehenden Anträge angezeigt.
                </p>
            </div>

            <?php if (empty($antraege)): ?>
                <div style="background: white; padding: 40px; text-align: center; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <p style="color: #999; font-size: 16px;">Derzeit stehen keine Anträge zur Abstimmung.</p>
                </div>
            <?php else: ?>
                <div class="antraege-liste">
                    <table>
                        <thead>
                            <tr>
                                <th>Antragsnummer</th>
                                <th>Antragsteller</th>
                                <th>Titel</th>
                                <th>Art</th>
                                <th>Status</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($antraege as $a):
                                // Prüfen ob User stimmberechtigt ist
                                $muss_abstimmen = false;
                                $hat_abgestimmt = false;
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($a["VName$i"] == $user['ID']) {
                                        $muss_abstimmen = true;
                                        $hat_abgestimmt = !empty($a["Votum$i"]);
                                        break;
                                    }
                                }

                                $bart_text = ['V' => 'Verfügung', 'R' => 'Ressortbeschluss', 'B' => 'Vorstandsbeschluss'];
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($a['antrnr']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($a['Vorname'] . ' ' . $a['Name']) ?></td>
                                    <td><?= htmlspecialchars($a['titel']) ?></td>
                                    <td><?= $bart_text[$a['bart']] ?? $a['bart'] ?></td>
                                    <td>
                                        <?php if ($muss_abstimmen && !$hat_abgestimmt): ?>
                                            <span class="badge badge-urgent">⚠️ Bitte abstimmen</span>
                                        <?php elseif ($hat_abgestimmt): ?>
                                            <span class="badge badge-pending">✓ Abgestimmt</span>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">Läuft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="abstimmungen.php?antrnr=<?= urlencode($a['antrnr']) ?>" class="btn" style="padding: 6px 12px; font-size: 13px;">
                                            Anzeigen
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
