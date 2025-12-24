<?php
/**
 * Dashboard - Übersicht für eingeloggte User
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Reise.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$user = new User($db);
$reise = new Reise($db);

$currentUser = $session->getUser();
$meineAnmeldungen = $reise->getAnmeldungenByUser($currentUser['user_id']);
$aktiveReisen = $reise->getAktiveReisen();

// Map für schnelle Prüfung ob angemeldet
$angemeldeteReisen = [];
foreach ($meineAnmeldungen as $a) {
    $angemeldeteReisen[$a['reise_id']] = true;
}

$pageTitle = 'Übersicht';
include __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1>Willkommen, <?= htmlspecialchars($currentUser['email']) ?>!</h1>

        <?php if ($session->isAdmin()): ?>
            <span class="badge bg-primary">Admin</span>
        <?php endif; ?>
        <?php if ($session->isSuperuser()): ?>
            <span class="badge bg-danger">Superuser</span>
        <?php endif; ?>
    </div>
</div>

<div class="row mt-4">
    <!-- Schnellzugriff -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Schnellzugriff</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="profil.php" class="btn btn-outline-primary">
                        👤 Mein Profil & Teilnehmer
                    </a>
                    <a href="reisen.php" class="btn btn-outline-success">
                        🚢 Reisen anzeigen
                    </a>
                    <a href="passwort.php" class="btn btn-outline-secondary">
                        🔑 Passwort ändern
                    </a>
                </div>

                <?php if ($session->isSuperuser()): ?>
                    <hr>
                    <h6>Superuser</h6>
                    <div class="d-grid gap-2">
                        <a href="admin/reise-neu.php" class="btn btn-outline-danger">
                            ➕ Neue Reise anlegen
                        </a>
                        <a href="admin/benutzer.php" class="btn btn-outline-danger">
                            👥 Benutzer verwalten
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Meine Anmeldungen -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Meine Anmeldungen</h5>
            </div>
            <div class="card-body">
                <?php if (empty($meineAnmeldungen)): ?>
                    <p class="text-muted">Du hast dich noch für keine Reise angemeldet.</p>
                    <a href="reisen.php" class="btn btn-primary">Jetzt für eine Reise anmelden</a>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Schiff</th>
                                    <th>Zeitraum</th>
                                    <th>Kabine</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($meineAnmeldungen as $anmeldung): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($anmeldung['schiff']) ?></td>
                                        <td>
                                            <?= date('d.m.Y', strtotime($anmeldung['anfang'])) ?> -
                                            <?= date('d.m.Y', strtotime($anmeldung['ende'])) ?>
                                        </td>
                                        <td><?= htmlspecialchars($anmeldung['kabine'] ?? '-') ?></td>
                                        <td>
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
                                            $status = $anmeldung['treffen_status'];
                                            ?>
                                            <span class="badge bg-<?= $statusClass[$status] ?>">
                                                <?= $statusText[$status] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="reise.php?id=<?= $anmeldung['reise_id'] ?>"
                                               class="btn btn-sm btn-outline-primary">Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Aktuelle Reisen -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Aktuelle Fantreffen-Reisen</h5>
            </div>
            <div class="card-body">
                <?php if (empty($aktiveReisen)): ?>
                    <p class="text-muted">Derzeit sind keine aktiven Reisen geplant.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($aktiveReisen as $r):
                            $istAngemeldet = isset($angemeldeteReisen[$r['reise_id']]);
                            $cardClass = $istAngemeldet ? 'border-success border-2' : '';
                        ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 <?= $cardClass ?>">
                                    <?php if ($istAngemeldet): ?>
                                        <div class="bg-success text-white py-1 text-center small">
                                            <i class="bi bi-check-circle"></i> Du bist angemeldet
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($r['schiff']) ?></h5>
                                        <p class="card-text">
                                            <strong>Zeitraum:</strong><br>
                                            <?= date('d.m.Y', strtotime($r['anfang'])) ?> -
                                            <?= date('d.m.Y', strtotime($r['ende'])) ?>
                                        </p>
                                        <?php if ($r['treffen_ort']): ?>
                                            <p class="card-text">
                                                <strong>Treffpunkt:</strong><br>
                                                <?= htmlspecialchars($r['treffen_ort']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <a href="reise.php?id=<?= $r['reise_id'] ?>"
                                           class="btn btn-primary btn-sm">Details & Anmelden</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
