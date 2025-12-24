<?php
/**
 * Reisen-Übersicht - Alle aktiven Fantreffen
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Reise.php';

$session = new Session();
$db = Database::getInstance();
$reiseModel = new Reise($db);

$aktiveReisen = $reiseModel->getAktive();
$vergangeneReisen = $reiseModel->getVergangene(5);

// Anmeldungen des aktuellen Users laden
$meineAnmeldungen = [];
if ($session->isLoggedIn()) {
    $currentUser = $session->getUser();
    $anmeldungen = $reiseModel->getAnmeldungenByUser($currentUser['user_id']);
    foreach ($anmeldungen as $a) {
        $meineAnmeldungen[$a['reise_id']] = true;
    }
}

$pageTitle = 'Fantreffen-Reisen';
include __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1>Fantreffen-Reisen</h1>
        <p class="lead">Hier findest du alle geplanten AIDA-Fantreffen.</p>
    </div>
</div>

<!-- Aktive Reisen -->
<div class="row mt-4">
    <div class="col-12">
        <h2>Aktuelle Reisen</h2>
        <?php if (empty($aktiveReisen)): ?>
            <div class="alert alert-info">
                Derzeit sind keine aktiven Fantreffen geplant. Schau später wieder vorbei!
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($aktiveReisen as $reise):
                    $istAngemeldet = isset($meineAnmeldungen[$reise['reise_id']]);
                    $cardClass = $istAngemeldet ? 'border-success border-2' : '';
                ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 <?= $cardClass ?>">
                            <?php if ($istAngemeldet): ?>
                                <div class="card-header bg-success text-white py-1 text-center small">
                                    <i class="bi bi-check-circle"></i> Du bist angemeldet
                                </div>
                            <?php endif; ?>
                            <div class="card-header">
                                <?php
                                $statusClass = [
                                    'geplant' => 'warning',
                                    'bestaetigt' => 'success',
                                    'abgesagt' => 'danger'
                                ];
                                $statusText = [
                                    'geplant' => 'Geplant',
                                    'bestaetigt' => 'Bestätigt',
                                    'abgesagt' => 'Abgesagt'
                                ];
                                $status = $reise['treffen_status'];
                                ?>
                                <span class="badge bg-<?= $statusClass[$status] ?> float-end">
                                    <?= $statusText[$status] ?>
                                </span>
                                <h5 class="mb-0"><?= htmlspecialchars($reise['schiff']) ?></h5>
                            </div>
                            <div class="card-body">
                                <p>
                                    <strong>Zeitraum:</strong><br>
                                    <?= date('d.m.Y', strtotime($reise['anfang'])) ?> -
                                    <?= date('d.m.Y', strtotime($reise['ende'])) ?>
                                </p>
                                <?php if ($reise['bahnhof']): ?>
                                    <p>
                                        <strong>Abfahrt:</strong><br>
                                        <?= htmlspecialchars($reise['bahnhof']) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($reise['treffen_ort']): ?>
                                    <p>
                                        <strong>Treffpunkt:</strong><br>
                                        <?= htmlspecialchars($reise['treffen_ort']) ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-muted">
                                    <?= $reise['anzahl_anmeldungen'] ?> Anmeldung(en)
                                </p>
                            </div>
                            <div class="card-footer">
                                <a href="reise.php?id=<?= $reise['reise_id'] ?>" class="btn btn-primary">
                                    Details & Anmelden
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Vergangene Reisen -->
<?php if (!empty($vergangeneReisen)): ?>
    <div class="row mt-5">
        <div class="col-12">
            <h2>Vergangene Fantreffen</h2>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Schiff</th>
                            <th>Zeitraum</th>
                            <th>Teilnehmer</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vergangeneReisen as $reise): ?>
                            <tr>
                                <td><?= htmlspecialchars($reise['schiff']) ?></td>
                                <td>
                                    <?= date('d.m.Y', strtotime($reise['anfang'])) ?> -
                                    <?= date('d.m.Y', strtotime($reise['ende'])) ?>
                                </td>
                                <td><?= $reise['anzahl_anmeldungen'] ?></td>
                                <td>
                                    <a href="reise.php?id=<?= $reise['reise_id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">Ansehen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($session->isLoggedIn()): ?>
    <div class="row mt-4">
        <div class="col-12">
            <a href="dashboard.php" class="btn btn-secondary">Zur Übersicht</a>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
