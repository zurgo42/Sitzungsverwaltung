<?php
/**
 * index.php - Startseite
 * Zeigt aktuelle Reisen und ermöglicht die Anmeldung
 */

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Reise.php';

Session::start();

$pageTitle = 'Aida Fantreffen - Startseite';

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
<div class="bg-light rounded-3 p-5 mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="display-5 fw-bold">Willkommen beim Aida Fantreffen!</h1>
            <p class="lead">
                Hier kannst du dich für Fantreffen auf AIDA-Kreuzfahrten anmelden.
                Triff Gleichgesinnte, stoße mit einem Glas Sekt an und erhalte Insider-Infos vom Kapitän.
            </p>
            <?php if (!Session::isLoggedIn()): ?>
                <a href="registrieren.php" class="btn btn-primary btn-lg me-2">
                    <i class="bi bi-person-plus"></i> Jetzt registrieren
                </a>
                <a href="login.php" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Anmelden
                </a>
            <?php else: ?>
                <a href="meine-reisen.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-ship"></i> Meine Reisen
                </a>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-center d-none d-md-block">
            <img src="images/FantreffenSchiff.jpg" alt="Fantreffen" class="img-fluid rounded shadow">
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
