<?php
/**
 * Export für AIDA - Liste zur Übermittlung an AIDA
 * Format: Vorname Name Kabinennummer: XXX (sortiert nach Kabine)
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
    header('Location: ../index.php');
    exit;
}

$currentUser = $session->getUser();

// Berechtigung prüfen (Superuser oder Reise-Admin)
$isAdmin = Session::isSuperuser() || $reiseModel->isReiseAdmin($reiseId, $currentUser['user_id']);

if (!$isAdmin) {
    header('Location: ../index.php');
    exit;
}

// Alle Teilnehmer dieser Reise laden - sortiert nach Kabine
$teilnehmer = $db->fetchAll(
    "SELECT t.vorname, t.name, a.kabine
     FROM fan_anmeldungen a
     JOIN fan_teilnehmer t ON t.teilnehmer_id IN (
         a.teilnehmer1_id, a.teilnehmer2_id, a.teilnehmer3_id, a.teilnehmer4_id
     )
     WHERE a.reise_id = ?
     ORDER BY CAST(a.kabine AS UNSIGNED), a.kabine, t.name, t.vorname",
    [$reiseId]
);

$gesamtTeilnehmer = count($teilnehmer);
$reise = $reiseModel->formatForDisplay($reise);
$pageTitle = 'An AIDA übermitteln - ' . $reise['schiff'];

include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Start</a></li>
                <li class="breadcrumb-item"><a href="reise-bearbeiten.php?id=<?= $reiseId ?>">
                    <?= htmlspecialchars($reise['schiff']) ?>
                </a></li>
                <li class="breadcrumb-item active">An AIDA übermitteln</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>➤ An AIDA übermitteln</h1>
            <div class="btn-group no-print">
                <button onclick="copyToClipboard()" class="btn btn-primary">
                    📋 Kopieren
                </button>
                <button onclick="window.print()" class="btn btn-outline-secondary">
                    🖨 Drucken
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reise-Info -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <strong><?= htmlspecialchars($reise['schiff']) ?></strong><br>
                <?= $reise['anfang_formatiert'] ?> - <?= $reise['ende_formatiert'] ?>
            </div>
            <div class="col-md-4 text-end">
                <strong><?= $gesamtTeilnehmer ?> Teilnehmer</strong>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            📋 Teilnehmerliste für AIDA
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3 no-print">
            Diese Liste kann direkt an AIDA übermittelt werden. Klicke auf "Kopieren" um die Liste in die Zwischenablage zu kopieren.
        </p>

        <?php if (empty($teilnehmer)): ?>
            <div class="alert alert-info">
                ℹ Noch keine Teilnehmer für diese Reise angemeldet.
            </div>
        <?php else: ?>
            <div class="bg-light p-3 rounded" id="aidaListe">
                <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit;"><?php
$nr = 0;
foreach ($teilnehmer as $t):
    $nr++;
    $kabine = $t['kabine'] ?: '(unbekannt)';
    echo htmlspecialchars($t['vorname'] . ' ' . $t['name'] . ' Kabinennummer: ' . $kabine) . "\n";
endforeach;
?></pre>
            </div>

            <div class="mt-3 text-muted">
                <strong>Gesamt:</strong> <?= $gesamtTeilnehmer ?> Teilnehmer
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4 no-print">
    <a href="reise-bearbeiten.php?id=<?= $reiseId ?>" class="btn btn-secondary">
        ← Zurück zur Reise
    </a>
</div>

<script>
function copyToClipboard() {
    const liste = document.getElementById('aidaListe');
    const text = liste.innerText;

    navigator.clipboard.writeText(text).then(() => {
        alert('Liste wurde in die Zwischenablage kopiert!');
    }).catch(err => {
        // Fallback für ältere Browser
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Liste wurde in die Zwischenablage kopiert!');
    });
}
</script>

<style>
@media print {
    .no-print { display: none !important; }
    .navbar, footer { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { background: none !important; color: #000 !important; border-bottom: 2px solid #000; }
    pre { font-size: 12pt; }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
