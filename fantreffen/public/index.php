<?php
/**
 * index.php - Startseite
 * Zeigt aktuelle Reisen und ermöglicht die Anmeldung
 */

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Reise.php';
require_once __DIR__ . '/../src/PdfService.php';

Session::start();

$pageTitle = 'AIDA Fantreffen';

// Aktive Reisen laden
$meineAnmeldungen = [];
$meineAdminReisen = [];
$isSuperuser = Session::isSuperuser();

try {
    $db = Database::getInstance();

    $reiseManager = new Reise($db);
    $pdfService = new PdfService();
    $aktiveReisen = $reiseManager->getAktive();

    // Reisen für Anzeige formatieren
    $aktiveReisen = array_map(function($r) use ($reiseManager) {
        $r = $reiseManager->formatForDisplay($r);
        $r['bild'] = $reiseManager->getSchiffBild($r['schiff']);
        return $r;
    }, $aktiveReisen);

    // Gesamtzahl der Anmeldungen pro Reise laden
    $anmeldungenProReise = [];
    $stats = $db->fetchAll(
        "SELECT reise_id, COUNT(DISTINCT anmeldung_id) as anzahl_anmeldungen,
                SUM(
                    (CASE WHEN teilnehmer1_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN teilnehmer2_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN teilnehmer3_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN teilnehmer4_id IS NOT NULL THEN 1 ELSE 0 END)
                ) as anzahl_teilnehmer
         FROM fan_anmeldungen
         GROUP BY reise_id"
    );

    foreach ($stats as $s) {
        $anmeldungenProReise[$s['reise_id']] = [
            'anmeldungen' => (int)$s['anzahl_anmeldungen'],
            'teilnehmer' => (int)$s['anzahl_teilnehmer']
        ];
    }

    // User-Anmeldungen laden
    if (Session::isLoggedIn()) {
        $userId = $_SESSION['user_id'];

        // Anmeldungen mit Teilnehmeranzahl laden
        $anmeldungen = $db->fetchAll(
            "SELECT reise_id,
                    (CASE WHEN teilnehmer1_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN teilnehmer2_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN teilnehmer3_id IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN teilnehmer4_id IS NOT NULL THEN 1 ELSE 0 END) as anzahl_teilnehmer
             FROM fan_anmeldungen WHERE user_id = ?",
            [$userId]
        );
        foreach ($anmeldungen as $a) {
            $meineAnmeldungen[$a['reise_id']] = (int)$a['anzahl_teilnehmer'];
        }

        // Admin-Reisen laden
        $adminReisen = $reiseManager->getAdminReisen($userId);
        foreach ($adminReisen as $ar) {
            $meineAdminReisen[$ar['reise_id']] = true;
        }
    }

} catch (Exception $e) {
    $aktiveReisen = [];
    $dbError = true;
}

require_once __DIR__ . '/../templates/header.php';
?>

<!-- Hero-Bereich -->
<div class="bg-light rounded-3 p-4 p-md-5 mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="display-5 fw-bold mb-3">Willkommen beim AIDA Fantreffen!</h1>
            <p class="lead">
                Hier kannst du dich für Fantreffen auf AIDA-Kreuzfahrten anmelden.
                Wähle einfach eine Reise aus und melde dich mit deinen Mitreisenden an.
            </p>
        </div>
        <div class="col-md-4 text-center d-none d-md-block">
            <img src="images/FantreffenSchiff.jpg" alt="Fantreffen" class="img-fluid rounded shadow">
        </div>
    </div>

    <!-- Akkordeon für Details -->
    <div class="accordion mt-4" id="detailsAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button">
                    ℹ Was ist das AIDA Fantreffen?
                </button>
            </h2>
            <div id="detailsContent" class="accordion-collapse collapse" data-bs-parent="#detailsAccordion">
                <div class="accordion-body">
                    <p>
                        Das <strong>AIDA Fantreffen</strong> ist ein ungezwungenes Treffen von AIDA-Fans an Bord.
                        Hier lernen sich Gleichgesinnte kennen, tauschen Erfahrungen aus und verbringen gemeinsam Zeit während der Kreuzfahrt.
                    </p>
                    <p><strong>Was erwartet dich?</strong></p>
                    <ul>
                        <li>Kennenlernen anderer AIDA-Fans</li>
                        <li>Gemeinsamer Sektempfang</li>
                        <li>Erfahrungsaustausch und Tipps</li>
                        <li>Verabredung gemeinsamer Aktivitäten</li>
                        <li>Insider-Informationen vom General Manager</li>
                        <li>Manchmal Überraschungen von der Crew</li>
                        <li>Nette Gesellschaft während der Reise</li>
                    </ul>
                    <p><strong>Wie funktioniert die Anmeldung?</strong></p>
                    <ol>
                        <li>Wähle eine Reise aus</li>
                        <li>Registriere dich (falls noch nicht geschehen)</li>
                        <li>Trage deine Teilnehmer und Kabinennummer ein</li>
                        <li>Fertig! Du erhältst alle Infos zum Treffpunkt.</li>
                    </ol>
                    <p><strong><br>Du möchtest für eine andere Reise selbst ein Fantreffen organisieren?</strong></p>
                    <p class="mb-0">
                        Gern - dieses Projekt ist entsprechend ausgelegt. Dann melde dich bei uns <strong><a href="mailto:admin@aidafantreffen.de">per Mail</a></strong>; wir legen für dich eine entsprechende Seite an und dein Job ist es, die Mitreisenden anzusprechen, das Treffen bei Aida anzumelden, mit diesem Script die Fans zu benachrichtigen und die Namensschilder etc. zu drucken.<br>Und dann beim Treffen an Bord die Fans zu begrüßen und mit dem ersten Glas Sekt auf eine schöne Reise anzustoßen...
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="alert alert-warning">
        ⚠ Die Datenbankverbindung konnte nicht hergestellt werden.
    </div>
<?php endif; ?>

<!-- Aktuelle Reisen -->
<h2 class="mb-4">
    📅 Aktuelle Reisen
</h2>

<?php if (empty($aktiveReisen)): ?>
    <div class="alert alert-info">
        ℹ Aktuell sind keine Reisen geplant. Schau später nochmal vorbei!
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($aktiveReisen as $reise):
            $reiseId = $reise['reise_id'];
            $istAngemeldet = isset($meineAnmeldungen[$reiseId]);
            $anzahlTeilnehmer = $meineAnmeldungen[$reiseId] ?? 0;
            $istAdmin = $isSuperuser || isset($meineAdminReisen[$reiseId]);
            $cardClass = $istAngemeldet ? 'border-success border-2' : '';
            $gesamtTeilnehmer = $anmeldungenProReise[$reiseId]['teilnehmer'] ?? 0;
            $pdfStatus = $pdfService->existsForReise($reiseId);
        ?>
            <div class="col">
                <div class="card h-100 <?= $cardClass ?>">
                    <img src="<?= htmlspecialchars($reise['bild']) ?>"
                         class="card-img-top"
                         alt="<?= htmlspecialchars($reise['schiff']) ?>"
                         loading="lazy"
                         style="width: 100%; height: auto;">

                    <?php if ($istAngemeldet): ?>
                        <div class="bg-success text-white py-2 text-center">
                            ✓ Angemeldet mit <?= $anzahlTeilnehmer ?> Person<?= $anzahlTeilnehmer > 1 ? 'en' : '' ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><?= htmlspecialchars($reise['schiff']) ?></h5>
                            <?php
                            $statusLabels = [
                                'geplant' => ['Geplant', 'warning'],
                                'angemeldet' => ['Bei AIDA angemeldet', 'info'],
                                'bestaetigt' => ['Bestätigt', 'success'],
                                'abgesagt' => ['Abgesagt', 'danger']
                            ];
                            $status = $reise['treffen_status'] ?? 'geplant';
                            $label = $statusLabels[$status] ?? ['Geplant', 'warning'];
                            ?>
                            <span class="badge bg-<?= $label[1] ?>"><?= $label[0] ?></span>
                        </div>
                        <p class="card-text d-flex align-items-center">
                            <span>📅 <?= $reise['anfang_formatiert'] ?> - <?= $reise['ende_formatiert'] ?></span>
                            <?php if (!empty($reise['link_wasserurlaub']) || !empty($reise['link_facebook']) || !empty($reise['link_kids'])): ?>
                                <span class="ms-auto external-links">
                                    <?php if (!empty($reise['link_wasserurlaub'])): ?>
                                        <a href="<?= htmlspecialchars($reise['link_wasserurlaub']) ?>" target="_blank" title="Wasserurlaub">
                                            <img src="images/logoWasserurlaub.png" alt="Wasserurlaub" style="height: 20px;">
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($reise['link_facebook'])): ?>
                                        <a href="<?= htmlspecialchars($reise['link_facebook']) ?>" target="_blank" title="Facebook">
                                            <img src="images/facebook.jpg" alt="Facebook" style="height: 20px;">
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($reise['link_kids'])): ?>
                                        <a href="<?= htmlspecialchars($reise['link_kids']) ?>" target="_blank" title="Meine Landausflüge">
                                            <img src="images/meinelandausfluege.jpg" alt="Meine Landausflüge" style="height: 20px;">
                                        </a>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <?php if ($reise['bahnhof']): ?>
                            <p class="card-text text-muted">
                                📍 ab <?= htmlspecialchars($reise['bahnhof']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($gesamtTeilnehmer > 0): ?>
                            <p class="card-text">
                                👥 <strong><?= $gesamtTeilnehmer ?></strong> Teilnehmer angemeldet
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer bg-transparent">
                        <?php if ($istAngemeldet): ?>
                            <a href="dashboard.php?id=<?= $reiseId ?>" class="btn btn-success w-100">
                                👁 Details
                            </a>
                        <?php else: ?>
                            <a href="dashboard.php?id=<?= $reiseId ?>" class="btn btn-primary w-100">
                                👆 Möchte dabeisein
                            </a>
                        <?php endif; ?>

                        <?php if ($pdfStatus['faltblatt']): ?>
                            <a href="admin/pdf-download.php?id=<?= $reiseId ?>&type=faltblatt"
                               class="btn btn-outline-info w-100 mt-2" target="_blank">
                                🚢 Fanschiffchen zum Falten
                            </a>
                        <?php endif; ?>

                        <?php if ($istAdmin): ?>
                            <a href="admin/reise-bearbeiten.php?id=<?= $reiseId ?>" class="btn btn-outline-secondary w-100 mt-2">
                                ⚙ Admin
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
