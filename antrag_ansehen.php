<?php
/**
 * antrag_ansehen.php - Kompakte Read-Only Antragsansicht
 * Zeigt ALLE Felder aus dem Bearbeitungsformular in kompakter Form
 */

require_once 'session_config.php';
session_start();
require_once 'config.php';
require_once 'includes/antragstypen_helper.php';
require_once 'includes/voting_helper.php';

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

// Antragstypen-Config laden
$bart_config = lade_antragstypen_config($pdo);

// Voting-Config laden
$voting_config = lade_voting_config($pdo);

$user_stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
$user_stmt->execute([$_SESSION['member_id']]);
$user = $user_stmt->fetch();

$antrnr = $_GET['antrnr'] ?? '';
if (!$antrnr) die("Keine Antragsnummer angegeben.");

// Antrag laden
$stmt = $pdo->prepare("
    SELECT a.*,
           b.Vorname, b.Name, b.KurzN as AntragstellerKurz,
           r1.Ressort as ressort1_name,
           r2.Ressort as ressort2_name
    FROM antraege a
    LEFT JOIN berechtigte b ON a.antrst = b.ID
    LEFT JOIN svressorts r1 ON a.ressort1 = r1.ID
    LEFT JOIN svressorts r2 ON a.ressort2 = r2.ID
    WHERE a.antrnr = ?
");
$stmt->execute([$antrnr]);
$antrag = $stmt->fetch();
if (!$antrag) die("Antrag nicht gefunden.");

// Berechtigungen
$kann_intern_sehen = ($user['aktiv'] > 17 || $user['Funktion'] === 'VA' || $user['is_admin'] == 1);
if ($antrag['int_ext'] === 'i' && !$kann_intern_sehen) {
    die("Keine Berechtigung.");
}

// Verfügungsberechtigte
$verf1_name = $verf2_name = '';
if ($antrag['verf1']) {
    $v = $pdo->prepare("SELECT Vorname, Name, KurzN FROM berechtigte WHERE ID = ?")->execute([$antrag['verf1']]);
    $v = $pdo->prepare("SELECT Vorname, Name, KurzN FROM berechtigte WHERE ID = ?");
    $v->execute([$antrag['verf1']]);
    $vd = $v->fetch();
    $verf1_name = $vd ? $vd['KurzN'] : '';
}
if ($antrag['verf2']) {
    $v = $pdo->prepare("SELECT Vorname, Name, KurzN FROM berechtigte WHERE ID = ?");
    $v->execute([$antrag['verf2']]);
    $vd = $v->fetch();
    $verf2_name = $vd ? $vd['KurzN'] : '';
}

// Wartezeitverkürzer
$verk1_name = $verk2_name = '';
if ($antrag['verk1']) {
    $v = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
    $v->execute([$antrag['verk1']]);
    $verk1_name = $v->fetchColumn() ?: '';
}
if ($antrag['verk2']) {
    $v = $pdo->prepare("SELECT KurzN FROM berechtigte WHERE ID = ?");
    $v->execute([$antrag['verk2']]);
    $verk2_name = $v->fetchColumn() ?: '';
}

$prefix = substr($antrnr, 0, 1);
$ist_admin = ((int)($user['aktiv'] ?? 0) >= 19 || ($user['is_admin'] ?? 0) == 1);

// Berechtigungsprüfung
if ($prefix === 'A') {
    // A-Anträge: Antragsteller oder Vorstand darf bearbeiten
    $kann_bearbeiten = (($user['aktiv'] > 10) && ($antrag['antrst'] == $user['ID'] || $user['aktiv'] >= 18));
} else {
    // B/VS/X/Z-Anträge: Nur Admins dürfen bearbeiten
    $kann_bearbeiten = $ist_admin;
}

// Typ-Bezeichnungen aus Config
$bart_text = get_aktive_typen($bart_config);
// Fallback für alle Typen (auch wenn deaktiviert)
foreach (['V', 'R', 'B'] as $typ) {
    if (!isset($bart_text[$typ])) {
        $bart_text[$typ] = get_typ_bezeichnung($typ, $bart_config);
    }
}
$int_ext_text = ['e' => '🌐 Extern', 'n' => '👥 Führung', 'i' => '🔒 Vorstand'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrag <?= htmlspecialchars($antrnr) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="antrag-styles.css">
    <script>
        // Dark Mode automatisch von index.php übernehmen
        (function() {
            const hasCookie = document.cookie.split(';').some(c => c.trim().startsWith('darkMode='));
            if (hasCookie && document.cookie.includes('darkMode=enabled')) {
                document.documentElement.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
            } else if (!hasCookie) {
                const savedDarkMode = localStorage.getItem('darkMode');
                if (savedDarkMode === 'enabled') {
                    document.documentElement.classList.add('dark-mode');
                    document.body.classList.add('dark-mode');
                }
            }
        })();
    </script>
    <style>
        body { font-size: 13px; line-height: 1.4; }
        .compact-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px 15px; margin-bottom: 15px; }
        .compact-row { display: flex; gap: 6px; margin-bottom: 6px; font-size: 12px; }
        .compact-label { font-weight: 600; color: var(--text-secondary); min-width: 100px; flex-shrink: 0; }
        .compact-value { color: var(--text-primary); }
        .section-compact { background: var(--bg-primary); padding: 12px; margin-bottom: 12px; border-radius: 6px; border: 1px solid var(--border-color); }
        .section-title { font-weight: 700; font-size: 14px; margin-bottom: 10px; color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 4px; }
        .text-box { background: var(--bg-secondary); padding: 8px; border-radius: 4px; border-left: 3px solid var(--primary); font-size: 12px; margin-top: 6px; word-wrap: break-word; overflow-wrap: break-word; }
        .accordion { cursor: pointer; background: var(--bg-secondary); padding: 8px; border-radius: 4px; margin: 6px 0; font-weight: 600; user-select: none; }
        .accordion:hover { background: var(--hover-bg); }
        .accordion::before { content: '▶ '; font-size: 10px; margin-right: 6px; }
        .accordion.active::before { content: '▼ '; }
        .acc-content { display: none; padding: 8px; background: var(--bg-secondary); border-radius: 4px; margin-bottom: 6px; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; }
        .grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .compact-value { word-wrap: break-word; overflow-wrap: break-word; }
        a { word-wrap: break-word; overflow-wrap: break-word; }
        @media (max-width: 1024px) {
            .compact-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .compact-grid { grid-template-columns: 1fr; gap: 6px; }
            .compact-label { min-width: 80px; }
            .grid-2col { grid-template-columns: 1fr; }
            .container { padding: 10px !important; }
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1400px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h1 style="margin: 0; font-size: 20px;">Antrag <?= htmlspecialchars($antrnr) ?></h1>
            <div style="display: flex; gap: 8px;">
                <?php if ($kann_bearbeiten): ?>
                    <a href="antrag_bearbeiten.php?antrnr=<?= urlencode($antrnr) ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">✏️ Bearbeiten</a>
                <?php endif; ?>
                <?php if ($prefix === 'B'): ?>
                    <a href="abstimmungen.php?antrnr=<?= urlencode($antrnr) ?>" class="btn btn-warning" style="padding: 6px 12px; font-size: 12px;">🗳️ Abstimmen</a>
                <?php endif; ?>
                <a href="index.php?tab=proposals" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">← Liste</a>
            </div>
        </div>

        <?php if ($prefix === 'B'): ?>
            <!-- Hinweis: Abstimmung aktiv -->
            <div style="background: rgba(250, 170, 0, 0.15); border: 2px solid #FAAA00; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(250, 170, 0, 0.3);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 28px;">🗳️</div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 16px; margin-bottom: 4px; color: #000;">
                            Abstimmung läuft
                        </div>
                        <div style="font-size: 13px; color: #666;">
                            Dieser Antrag befindet sich aktuell in der Abstimmung.
                            <?php if (!$kann_bearbeiten): ?>
                                Während der Abstimmung können nur Administratoren Änderungen vornehmen.
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="abstimmungen.php?antrnr=<?= urlencode($antrnr) ?>" class="btn btn-warning" style="padding: 10px 16px; font-size: 14px; white-space: nowrap;">
                        → Zur Abstimmung
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- BASISDATEN -->
        <div class="section-compact">
            <div class="section-title">Basisdaten</div>
            <div class="compact-grid">
                <div class="compact-row">
                    <div class="compact-label">Antragsteller:</div>
                    <div class="compact-value"><?= htmlspecialchars($antrag['Vorname']) ?> <?= htmlspecialchars($antrag['Name']) ?></div>
                </div>
                <div class="compact-row">
                    <div class="compact-label">Beschlussart:</div>
                    <div class="compact-value"><strong><?= $bart_text[$antrag['bart']] ?? $antrag['bart'] ?></strong></div>
                </div>
                <?php
                // Abstimmungsregel nur zeigen, wenn mehrere Optionen aktiviert
                $all_rules = get_voting_rules($voting_config);
                if (count($all_rules) > 1):
                ?>
                <div class="compact-row">
                    <div class="compact-label">Abstimmungsregel:</div>
                    <div class="compact-value">
                        <?php
                        $regel = $antrag['abstimmregel'] ?? 'einfach';
                        echo htmlspecialchars($all_rules[$regel]['label'] ?? $regel);
                        ?>
                        <small style="color: #666; font-size: 11px; display: block;">
                            <?= htmlspecialchars($all_rules[$regel]['desc'] ?? '') ?>
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($antrag['ressort1_name'] || $antrag['ressort2_name']): ?>
                <div class="compact-row">
                    <div class="compact-label">Ressorts:</div>
                    <div class="compact-value"><?= htmlspecialchars($antrag['ressort1_name'] ?? '') ?><?= $antrag['ressort2_name'] ? ' + ' . htmlspecialchars($antrag['ressort2_name']) : '' ?></div>
                </div>
                <?php endif; ?>
                <div class="compact-row">
                    <div class="compact-label">Verantwortlich:</div>
                    <div class="compact-value"><?= htmlspecialchars($antrag['verant'] ?? '') ?></div>
                </div>
                <?php if ($verf1_name): ?>
                <div class="compact-row">
                    <div class="compact-label">Abstimmung:</div>
                    <div class="compact-value"><?= htmlspecialchars($verf1_name) ?><?= $verf2_name ? ' + ' . htmlspecialchars($verf2_name) : '' ?></div>
                </div>
                <?php endif; ?>
                <div class="compact-row">
                    <div class="compact-label">Sichtbarkeit:</div>
                    <div class="compact-value"><?= $int_ext_text[$antrag['int_ext']] ?? 'Extern' ?></div>
                </div>
                <div class="compact-row">
                    <div class="compact-label">Verein/Stiftung:</div>
                    <div class="compact-value"><?= $antrag['verein'] === 'S' ? 'Stiftung' : 'Verein' ?></div>
                </div>
                <?php if (isset($antrag['praesenz']) && $antrag['praesenz'] == 1): ?>
                <div class="compact-row">
                    <div class="compact-label">Abstimmungsform:</div>
                    <div class="compact-value">Präsenzsitzung</div>
                </div>
                <?php endif; ?>
                <?php if ($antrag['thread']): ?>
                <div class="compact-row">
                    <div class="compact-label">Forum:</div>
                    <div class="compact-value"><a href="https://vorstand.mensa.de/forum/index.php?id=<?= (int)$antrag['thread'] ?>" target="forum" style="color: var(--primary);">→ Thread #<?= (int)$antrag['thread'] ?></a></div>
                </div>
                <?php endif; ?>
                <div class="compact-row">
                    <div class="compact-label">Letzte Änderung:</div>
                    <div class="compact-value"><?= $antrag['lzugriff'] ? date('d.m.Y H:i', strtotime($antrag['lzugriff'])) : '-' ?></div>
                </div>
            </div>

            <?php
            // Wartezeit-Anzeige wenn noch laufend (nur bei B-Anträgen) - prominente Box
            $prefix = substr($antrnr, 0, 1);
            if ($prefix === 'B' && preg_match('/^B(\d{6})/', $antrnr, $matches)) {
                $datum_str = $matches[1];
                $abstimmung_seit = '20' . substr($datum_str, 0, 2) . '-' . substr($datum_str, 2, 2) . '-' . substr($datum_str, 4, 2);

                // Wartezeit aus Config holen
                $wartezeit_tage = $bart_config["bart_{$antrag['bart']}_wartezeit_tage"] ?? 7;
                $wartezeit_ende = date('Y-m-d H:i:s', strtotime($abstimmung_seit . ' + ' . $wartezeit_tage . ' days'));

                // Prüfen ob Wartezeit noch läuft
                if (strtotime($wartezeit_ende) > time()) {
                    $tage_verbleibend = ceil((strtotime($wartezeit_ende) - time()) / 86400);
                    ?>
                    <div style="background: #ffebee; border: 2px solid #d32f2f; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;">
                        <div style="font-size: 18px; font-weight: 700; color: #d32f2f; margin-bottom: 5px;">
                            ⏳ Wartezeit
                        </div>
                        <div style="font-size: 16px; color: #c62828; font-weight: 600;">
                            Endet am <?= date('d.m.Y', strtotime($wartezeit_ende)) ?> um <?= date('H:i', strtotime($wartezeit_ende)) ?> Uhr
                        </div>
                        <div style="font-size: 14px; color: #666; margin-top: 5px;">
                            (noch <?= $tage_verbleibend ?> <?= $tage_verbleibend == 1 ? 'Tag' : 'Tage' ?>)
                        </div>
                    </div>
                    <?php
                }
            }
            ?>

            <div class="grid-2col">
                <?php
                // Abstimmungs- und Beschlussdatum
                $prefix = substr($antrnr, 0, 1);
                if ($prefix === 'B' || $prefix === 'V'):
                    // Zur Abstimmung gestellt: Datum aus warantrag oder erstes VDat
                    $abstimmung_seit = null;
                    if (!empty($antrag['warantrag'])) {
                        // Bei VS-Anträgen: warantrag enthält alte B-Nummer, Datum extrahieren
                        $old_nr = $antrag['warantrag'];
                        if (preg_match('/^B(\d{6})/', $old_nr, $matches)) {
                            $datum_str = $matches[1];
                            $abstimmung_seit = '20' . substr($datum_str, 0, 2) . '-' . substr($datum_str, 2, 2) . '-' . substr($datum_str, 4, 2);
                        }
                    }
                    // Letztes Votum finden
                    $letztes_votum = null;
                    for ($i = 6; $i >= 1; $i--) {
                        if (!empty($antrag["VDat$i"])) {
                            $letztes_votum = $antrag["VDat$i"];
                            break;
                        }
                    }
                    if ($abstimmung_seit): ?>
                <div class="compact-row">
                    <div class="compact-label">Zur Abstimmung:</div>
                    <div class="compact-value"><?= date('d.m.Y', strtotime($abstimmung_seit)) ?></div>
                </div>
                    <?php endif;
                    if ($prefix === 'V' && $letztes_votum): ?>
                <div class="compact-row">
                    <div class="compact-label">Beschlossen am:</div>
                    <div class="compact-value" style="font-weight: 600; color: var(--success);"><?= date('d.m.Y H:i', strtotime($letztes_votum)) ?></div>
                </div>
                    <?php endif;
                endif; ?>
            </div>
        </div>

        <!-- ANTRAG -->
        <div class="section-compact">
            <div class="section-title">Antrag</div>
            <div style="font-weight: 700; font-size: 15px; margin-bottom: 8px;"><?= nl2br(htmlspecialchars($antrag['titel'])) ?></div>

            <?php if ($antrag['beschluss']): ?>
            <div style="font-weight: 600; font-size: 11px; color: #666; margin-top: 10px; margin-bottom: 4px;">WORTLAUT DES BESCHLUSSES:</div>
            <div class="text-box" style="border-left-color: var(--primary);">
                <?= nl2br(htmlspecialchars($antrag['beschluss'])) ?>
            </div>
            <?php endif; ?>

            <?php if ($antrag['begr']): ?>
            <?php $begr_lang = strlen($antrag['begr']) > 300; ?>
            <?php if ($begr_lang): ?>
                <div class="accordion" onclick="this.classList.toggle('active'); this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block';">
                    Begründung anzeigen
                </div>
                <div class="acc-content"><?= nl2br(htmlspecialchars($antrag['begr'])) ?></div>
            <?php else: ?>
                <div style="font-weight: 600; font-size: 11px; color: #666; margin-top: 10px; margin-bottom: 4px;">BEGRÜNDUNG:</div>
                <div class="text-box" style="border-left-color: var(--success);">
                    <?= nl2br(htmlspecialchars($antrag['begr'])) ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- AUSWIRKUNGEN -->
        <div class="section-compact">
            <div class="section-title">Auswirkungen</div>

            <?php if ($antrag['fin'] > 0): ?>
            <div style="font-size: 18px; font-weight: 700; color: var(--danger); margin-bottom: 6px;">
                <?= number_format($antrag['fin'], 0, ',', '.') ?> €
            </div>
            <?php endif; ?>

            <?php if ($antrag['fintext']): ?>
            <?php $fintext_lang = strlen($antrag['fintext']) > 250; ?>
            <?php if ($fintext_lang): ?>
                <div class="accordion" onclick="this.classList.toggle('active'); this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block';">
                    Finanzielle Details anzeigen
                </div>
                <div class="acc-content"><?= nl2br(htmlspecialchars($antrag['fintext'])) ?></div>
            <?php else: ?>
                <div style="font-weight: 600; font-size: 11px; color: #666; margin-bottom: 4px;">FINANZIELLE DETAILS:</div>
                <div class="text-box" style="border-left-color: var(--danger);">
                    <?= nl2br(htmlspecialchars($antrag['fintext'])) ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($antrag['pers'] && strlen($antrag['pers']) > 3): ?>
            <?php $pers_lang = strlen($antrag['pers']) > 250; ?>
            <?php if ($pers_lang): ?>
                <div class="accordion" onclick="this.classList.toggle('active'); this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block';">
                    Personelle Auswirkungen anzeigen
                </div>
                <div class="acc-content"><?= nl2br(htmlspecialchars($antrag['pers'])) ?></div>
            <?php else: ?>
                <div style="font-weight: 600; font-size: 11px; color: #666; margin-bottom: 4px; margin-top: 8px;">PERSONELLE AUSWIRKUNGEN:</div>
                <div class="text-box"><?= nl2br(htmlspecialchars($antrag['pers'])) ?></div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($antrag['sach'] && strlen($antrag['sach']) > 3): ?>
            <?php $sach_lang = strlen($antrag['sach']) > 250; ?>
            <?php if ($sach_lang): ?>
                <div class="accordion" onclick="this.classList.toggle('active'); this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block';">
                    Sachliche Auswirkungen anzeigen
                </div>
                <div class="acc-content"><?= nl2br(htmlspecialchars($antrag['sach'])) ?></div>
            <?php else: ?>
                <div style="font-weight: 600; font-size: 11px; color: #666; margin-bottom: 4px; margin-top: 8px;">SACHLICHE AUSWIRKUNGEN:</div>
                <div class="text-box" style="border-left-color: var(--success);">
                    <?= nl2br(htmlspecialchars($antrag['sach'])) ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- UNTERLAGEN + FREIGABEN (2-spaltig auf Desktop) -->
        <div class="grid-2col">

            <!-- UNTERLAGEN -->
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
            <div class="section-compact">
                <div class="section-title">Unterlagen</div>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <?php if (!empty($antrag["file$i"])): ?>
                    <div style="margin-bottom: 6px;">
                        <a href="<?= htmlspecialchars($antrag["file$i"]) ?>" target="_blank" style="color: var(--primary); font-weight: 600; font-size: 12px; text-decoration: none;">
                            📎 <?= basename($antrag["file$i"]) ?>
                        </a>
                        <?php if (!empty($antrag["filetext$i"])): ?>
                            <div style="font-size: 11px; color: #666; margin-left: 15px;"><?= htmlspecialchars($antrag["filetext$i"]) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <!-- VEREINFACHTE FREIGABE -->
            <?php if ($antrag['sofort'] || $antrag['zufin'] || $antrag['zbem']): ?>
            <div class="section-compact">
                <div class="section-title">Vereinfachte Freigabe</div>
                <?php if ($antrag['sofort'] == 1): ?>
                    <div style="font-size: 12px; margin-bottom: 4px;">✓ Wenn Rechnungsbetrag = Angebot → sofort überweisen</div>
                <?php elseif ($antrag['sofort'] == 2): ?>
                    <div style="font-size: 12px; margin-bottom: 4px;">✓ Nach Vorprüfung durch <strong><?= htmlspecialchars($antrag['durch'] ?? '') ?></strong> überweisen</div>
                <?php endif; ?>
                <?php if ($antrag['vorher']): ?>
                    <div style="font-size: 12px; color: #2e7d32; margin-bottom: 4px;">✓ Zustimmung Finanzvorstand liegt vor</div>
                <?php endif; ?>
                <?php if ($antrag['zufin']): ?>
                    <div style="font-size: 12px; color: var(--warning); margin-bottom: 4px;">⚠️ Freigabe durch GF zusätzlich erforderlich</div>
                <?php endif; ?>
                <?php if ($antrag['zbem']): ?>
                    <div style="font-size: 11px; color: #666; margin-top: 6px; padding: 6px; background: var(--bg-secondary); border-radius: 4px;">
                        <strong>Bemerkung Finanzreferat:</strong> <?= htmlspecialchars($antrag['zbem']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <!-- Ende grid-2col -->

        <!-- WARTEZEITVERKÜRZUNG -->
        <?php if ($antrag['verk1'] || $antrag['verk2']): ?>
            <div class="section-compact">
                <div class="section-title">Wartezeitverkürzung</div>
                <div style="font-size: 12px; color: #2e7d32;">
                    ✓ Zustimmung zur Verkürzung: <?= htmlspecialchars($verk1_name) ?><?= $verk2_name ? ', ' . htmlspecialchars($verk2_name) : '' ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- HINWEISE -->
        <?php if ($antrag['hinweis']): ?>
        <div class="section-compact">
            <div class="section-title">Hinweise / Protokoll</div>
            <?php $hinweis_lang = strlen($antrag['hinweis']) > 400; ?>
            <?php if ($hinweis_lang): ?>
                <div class="accordion" onclick="this.classList.toggle('active'); this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block';">
                    Hinweise anzeigen
                </div>
                <div class="acc-content"><?= nl2br(htmlspecialchars($antrag['hinweis'])) ?></div>
            <?php else: ?>
                <div class="text-box" style="border-left-color: var(--warning); background: rgba(250, 170, 0, 0.1);">
                    <?= nl2br(htmlspecialchars($antrag['hinweis'])) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
