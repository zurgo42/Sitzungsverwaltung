<?php
/**
 * Teilnehmerliste einer Reise (für Admins)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$reiseModel = new Reise($db);

$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('Location: ../reisen.php');
    exit;
}

$currentUser = $session->getUser();

// Berechtigung prüfen (Superuser oder Reise-Admin)
$isAdmin = Session::isSuperuser();
if (!$isAdmin) {
    $admins = $reiseModel->getAdmins($reiseId);
    foreach ($admins as $admin) {
        if ($admin['user_id'] === $currentUser['user_id']) {
            $isAdmin = true;
            break;
        }
    }
}

if (!$isAdmin) {
    header('Location: ../dashboard.php');
    exit;
}

// Alle Anmeldungen mit Teilnehmern laden
$anmeldungen = $db->fetchAll(
    "SELECT a.*, u.email,
            (SELECT GROUP_CONCAT(
                CONCAT(t.vorname, ' ', t.name,
                    COALESCE(CONCAT(' (', t.nickname, ')'), ''),
                    COALESCE(CONCAT(' - ', t.mobil), '')
                ) SEPARATOR '||'
            ) FROM fan_teilnehmer t
            WHERE JSON_CONTAINS(a.teilnehmer_ids, CAST(t.teilnehmer_id AS CHAR))
            ) AS teilnehmer_liste
     FROM fan_anmeldungen a
     JOIN fan_users u ON a.user_id = u.user_id
     WHERE a.reise_id = ?
     ORDER BY a.erstellt ASC",
    [$reiseId]
);

// Statistiken berechnen
$gesamtAnmeldungen = count($anmeldungen);
$gesamtTeilnehmer = 0;
$teilnehmerMitMobil = 0;

foreach ($anmeldungen as &$a) {
    $ids = json_decode($a['teilnehmer_ids'] ?? '[]', true);
    $gesamtTeilnehmer += count($ids);

    // Teilnehmer-Liste parsen
    if ($a['teilnehmer_liste']) {
        $a['teilnehmer_array'] = explode('||', $a['teilnehmer_liste']);
        foreach ($a['teilnehmer_array'] as $t) {
            if (strpos($t, ' - ') !== false) {
                $teilnehmerMitMobil++;
            }
        }
    } else {
        $a['teilnehmer_array'] = [];
    }
}
unset($a);

$pageTitle = 'Teilnehmerliste - ' . $reise['schiff'];
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="reise-bearbeiten.php?id=<?= $reiseId ?>">
                    <?= htmlspecialchars($reise['schiff']) ?>
                </a></li>
                <li class="breadcrumb-item active">Teilnehmerliste</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Teilnehmerliste</h1>
            <div class="btn-group">
                <button onclick="window.print()" class="btn btn-outline-secondary">
                    Drucken
                </button>
                <a href="namensschilder.php?id=<?= $reiseId ?>" class="btn btn-outline-primary">
                    Namensschilder
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Reise-Info -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong><?= htmlspecialchars($reise['schiff']) ?></strong><br>
                <?= date('d.m.Y', strtotime($reise['anfang'])) ?> -
                <?= date('d.m.Y', strtotime($reise['ende'])) ?>
            </div>
            <div class="col-md-4">
                <strong>Anmeldungen:</strong> <?= $gesamtAnmeldungen ?><br>
                <strong>Teilnehmer gesamt:</strong> <?= $gesamtTeilnehmer ?>
            </div>
            <div class="col-md-4">
                <?php if ($reise['treffen_ort']): ?>
                    <strong>Treffpunkt:</strong> <?= htmlspecialchars($reise['treffen_ort']) ?><br>
                <?php endif; ?>
                <?php if ($reise['treffen_zeit']): ?>
                    <strong>Zeit:</strong> <?= date('d.m.Y H:i', strtotime($reise['treffen_zeit'])) ?> Uhr
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (empty($anmeldungen)): ?>
    <div class="alert alert-info">
        Noch keine Anmeldungen für diese Reise vorhanden.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Teilnehmer</th>
                    <th>Kabine</th>
                    <th>E-Mail</th>
                    <th>Angemeldet am</th>
                    <th class="no-print">Bemerkung</th>
                </tr>
            </thead>
            <tbody>
                <?php $nr = 0; foreach ($anmeldungen as $a): $nr++; ?>
                    <tr>
                        <td><?= $nr ?></td>
                        <td>
                            <?php foreach ($a['teilnehmer_array'] as $t):
                                // Mobil-Nummer abtrennen für Anzeige
                                $parts = explode(' - ', $t, 2);
                                $name = $parts[0];
                                $mobil = $parts[1] ?? '';
                            ?>
                                <div>
                                    <?= htmlspecialchars($name) ?>
                                    <?php if ($mobil): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($mobil) ?>)</small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td><?= htmlspecialchars($a['kabine'] ?? '-') ?></td>
                        <td><a href="mailto:<?= htmlspecialchars($a['email']) ?>"><?= htmlspecialchars($a['email']) ?></a></td>
                        <td><?= date('d.m.Y H:i', strtotime($a['erstellt'])) ?></td>
                        <td class="no-print">
                            <?php if ($a['bemerkung']): ?>
                                <small><?= htmlspecialchars($a['bemerkung']) ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2"><?= $gesamtTeilnehmer ?> Teilnehmer</th>
                    <th colspan="4"><?= $gesamtAnmeldungen ?> Anmeldungen</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Export-Optionen -->
    <div class="card mt-4 no-print">
        <div class="card-header">
            <h5 class="mb-0">Export</h5>
        </div>
        <div class="card-body">
            <p>Teilnehmerliste exportieren:</p>
            <a href="export-csv.php?id=<?= $reiseId ?>" class="btn btn-outline-success me-2">
                CSV-Export
            </a>
            <a href="export-email.php?id=<?= $reiseId ?>" class="btn btn-outline-primary">
                E-Mail-Liste
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="mt-4 no-print">
    <a href="reise-bearbeiten.php?id=<?= $reiseId ?>" class="btn btn-secondary">
        Zurück zur Reise
    </a>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    .table { font-size: 10pt; }
    .navbar, footer { display: none !important; }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
