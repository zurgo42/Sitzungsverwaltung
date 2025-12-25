<?php
/**
 * index.php - Startseite
 * Zeigt aktuelle Reisen und ermöglicht die Anmeldung
 */

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Reise.php';

Session::start();

$pageTitle = 'AIDA Fantreffen';

// Aktive Reisen laden
$meineAnmeldungen = [];
$meineAdminReisen = [];
$isSuperuser = Session::isSuperuser();

try {
    $db = Database::getInstance();
    $reiseManager = new Reise($db);
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
                SUM(JSON_LENGTH(teilnehmer_ids)) as anzahl_teilnehmer
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
            "SELECT reise_id, teilnehmer_ids FROM fan_anmeldungen WHERE user_id = ?",
            [$userId]
        );
        foreach ($anmeldungen as $a) {
            $teilnehmerIds = json_decode($a['teilnehmer_ids'] ?? '[]', true);
            $meineAnmeldungen[$a['reise_id']] = count($teilnehmerIds);
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
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#detailsContent">
                    <i class="bi bi-info-circle me-2"></i> Was ist das AIDA Fantreffen?
                </button>
            </h2>
            <div id="detailsContent" class="accordion-collapse collapse" data-bs-parent="#detailsAccordion">
                <div class="accordion-body">
                    <p>
                        Das <strong>AIDA Fantreffen</strong> ist ein ungezwungenes Treffen von AIDA-Fans an Bord.
                        Hier lernen sich Gleichgesinnte kennen, tauschen Erfahrungen aus und verbringen gemeinsam
                        Zeit während der Kreuzfahrt.
                    </p>
                    <p><strong>Was erwartet dich?</strong></p>
                    <ul>
                        <li>Kennenlernen anderer AIDA-Fans</li>
                        <li>Gemeinsamer Sektempfang</li>
                        <li>Erfahrungsaustausch und Tipps</li>
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
                    <p class="mb-0">
                        Die Teilnahme ist <strong>kostenlos</strong> und unverbindlich.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($dbError)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        Die Datenbankverbindung konnte nicht hergestellt werden.
    </div>
<?php endif; ?>

<!-- Aktuelle Reisen -->
<h2 class="mb-4">
    <i class="bi bi-calendar-event"></i> Aktuelle Reisen
</h2>

<?php if (empty($aktiveReisen)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        Aktuell sind keine Reisen geplant. Schau später nochmal vorbei!
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
        ?>
            <div class="col">
                <div class="card h-100 <?= $cardClass ?>">
                    <img src="<?= htmlspecialchars($reise['bild']) ?>"
                         class="card-img-top"
                         alt="<?= htmlspecialchars($reise['schiff']) ?>"
                         style="width: 100%; height: auto;">

                    <?php if ($istAngemeldet): ?>
                        <div class="bg-success text-white py-2 text-center">
                            <i class="bi bi-check-circle"></i>
                            Angemeldet mit <?= $anzahlTeilnehmer ?> Person<?= $anzahlTeilnehmer > 1 ? 'en' : '' ?>
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
                        <p class="card-text">
                            <i class="bi bi-calendar3"></i>
                            <?= $reise['anfang_formatiert'] ?> - <?= $reise['ende_formatiert'] ?>
                        </p>
                        <?php if ($reise['bahnhof']): ?>
                            <p class="card-text text-muted">
                                <i class="bi bi-geo-alt"></i> ab <?= htmlspecialchars($reise['bahnhof']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($gesamtTeilnehmer > 0): ?>
                            <p class="card-text">
                                <i class="bi bi-people"></i>
                                <strong><?= $gesamtTeilnehmer ?></strong> Teilnehmer angemeldet
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer bg-transparent">
                        <?php if ($istAngemeldet): ?>
                            <a href="dashboard.php?id=<?= $reiseId ?>" class="btn btn-success w-100">
                                <i class="bi bi-eye"></i> Details
                            </a>
                        <?php else: ?>
                            <a href="dashboard.php?id=<?= $reiseId ?>" class="btn btn-primary w-100">
                                <i class="bi bi-hand-index"></i> Möchte dabeisein
                            </a>
                        <?php endif; ?>

                        <?php if ($istAdmin): ?>
                            <a href="admin/reise-bearbeiten.php?id=<?= $reiseId ?>" class="btn btn-outline-secondary w-100 mt-2">
                                <i class="bi bi-gear"></i> Admin
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
