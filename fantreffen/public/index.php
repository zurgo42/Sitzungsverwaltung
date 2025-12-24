<?php
/**
 * index.php - Startseite
 * Zeigt aktuelle Reisen und ermöglicht die Anmeldung
 */

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Reise.php';

Session::start();

$pageTitle = 'AIDA Fantreffen - Startseite';

// Aktive Reisen laden
try {
    $reiseManager = new Reise();
    $aktiveReisen = $reiseManager->getAktive();

    // Reisen für Anzeige formatieren
    $aktiveReisen = array_map(function($r) use ($reiseManager) {
        $r = $reiseManager->formatForDisplay($r);
        $r['bild'] = $reiseManager->getSchiffBild($r['schiff']);
        return $r;
    }, $aktiveReisen);
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
            <div class="d-flex align-items-center mb-3">
                <img src="images/Logo_ws_klein_transparent.png" alt="AIDA Fantreffen" class="me-3" style="max-height: 80px;">
                <h1 class="display-5 fw-bold mb-0">AIDA Fantreffen</h1>
            </div>
            <p class="lead">
                Willkommen bei den AIDA-Fantreffen! Hier kannst du dich für Fantreffen auf AIDA-Kreuzfahrten anmelden.
            </p>
            <?php if (!Session::isLoggedIn()): ?>
                <a href="registrieren.php" class="btn btn-primary btn-lg me-2">
                    <i class="bi bi-person-plus"></i> Jetzt registrieren
                </a>
                <a href="login.php" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Anmelden
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-primary btn-lg me-2">
                    <i class="bi bi-speedometer2"></i> Mein Dashboard
                </a>
                <a href="reisen.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-ship"></i> Alle Reisen
                </a>
            <?php endif; ?>
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
                    <p>
                        <strong>Was erwartet dich?</strong>
                    </p>
                    <ul>
                        <li>Kennenlernen anderer AIDA-Fans</li>
                        <li>Gemeinsamer Sektempfang</li>
                        <li>Erfahrungsaustausch und Tipps</li>
                        <li>Manchmal Überraschungen von der Crew</li>
                        <li>Nette Gesellschaft während der Reise</li>
                    </ul>
                    <p>
                        <strong>Wie funktioniert die Anmeldung?</strong>
                    </p>
                    <ol>
                        <li>Registriere dich mit deiner E-Mail-Adresse</li>
                        <li>Lege deine Teilnehmer an (bis zu 4 Personen)</li>
                        <li>Melde dich für eine Reise an und gib deine Kabinennummer an</li>
                        <li>Du erhältst alle Infos zum Treffpunkt per E-Mail oder über die Smartphone-Seite</li>
                    </ol>
                    <p class="mb-0">
                        Die Teilnahme ist <strong>kostenlos</strong> und unverbindlich.
                        Du kannst dich jederzeit wieder abmelden.
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
        Bitte prüfe die Konfiguration.
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
        <?php foreach ($aktiveReisen as $reise): ?>
            <div class="col">
                <div class="card card-reise h-100">
                    <img src="<?= htmlspecialchars($reise['bild']) ?>"
                         class="card-img-top"
                         alt="<?= htmlspecialchars($reise['schiff']) ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($reise['schiff']) ?></h5>
                        <?php if ($reise['bahnhof']): ?>
                            <p class="card-text text-muted small mb-1">
                                <i class="bi bi-geo-alt"></i> ab <?= htmlspecialchars($reise['bahnhof']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="card-text">
                            <i class="bi bi-calendar3"></i>
                            <?= $reise['anfang_formatiert'] ?> - <?= $reise['ende_formatiert'] ?>
                            <span class="text-muted">(<?= $reise['dauer_tage'] ?> Tage)</span>
                        </p>

                        <!-- Treffen-Status -->
                        <?php
                        $statusClass = match($reise['treffen_status']) {
                            'bestaetigt' => 'badge-treffen-bestaetigt',
                            'abgesagt'   => 'badge-treffen-abgesagt',
                            default      => 'badge-treffen-geplant'
                        };
                        $statusText = match($reise['treffen_status']) {
                            'bestaetigt' => 'Treffen bestätigt',
                            'abgesagt'   => 'Treffen abgesagt',
                            default      => 'Treffen geplant'
                        };
                        ?>
                        <span class="badge <?= $statusClass ?>">
                            <?= $statusText ?>
                        </span>

                        <?php if ($reise['treffen_ort'] && $reise['treffen_status'] === 'bestaetigt'): ?>
                            <p class="card-text mt-2 small">
                                <i class="bi bi-pin-map"></i>
                                <?= htmlspecialchars($reise['treffen_ort']) ?>
                                <?php if ($reise['treffen_zeit']): ?>
                                    <br>
                                    <i class="bi bi-clock"></i>
                                    <?= date('d.m.Y H:i', strtotime($reise['treffen_zeit'])) ?> Uhr
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-people"></i>
                                <?= $reise['anzahl_anmeldungen'] ?? 0 ?> Anmeldungen
                            </small>
                            <a href="reise.php?id=<?= $reise['reise_id'] ?>" class="btn btn-sm btn-primary">
                                Details & Anmelden
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Info-Bereich -->
<div class="row mt-5">
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-person-check display-4 text-primary mb-3"></i>
                <h5>Einfache Anmeldung</h5>
                <p class="text-muted">
                    Registriere dich einmal und melde dich für beliebig viele Reisen an.
                    Deine Teilnehmer-Daten bleiben gespeichert.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-envelope-check display-4 text-primary mb-3"></i>
                <h5>Info per Mail</h5>
                <p class="text-muted">
                    Du erhältst automatisch alle wichtigen Infos zum Treffen per E-Mail -
                    inklusive Ort, Zeit und Sonderinfos.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-phone display-4 text-primary mb-3"></i>
                <h5>Mobil abrufbar</h5>
                <p class="text-muted">
                    Auf dem Schiff kannst du jederzeit den aktuellen Stand abrufen -
                    wann und wo das Treffen stattfindet.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
