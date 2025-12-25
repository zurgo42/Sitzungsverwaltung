<?php
/**
 * E-Mail-Liste exportieren
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

// Berechtigung prüfen
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

// Alle E-Mails laden
$emails = $db->fetchAll(
    "SELECT DISTINCT u.email
     FROM fan_anmeldungen a
     JOIN fan_users u ON a.user_id = u.user_id
     WHERE a.reise_id = ?
     ORDER BY u.email",
    [$reiseId]
);

$emailList = array_column($emails, 'email');

$pageTitle = 'E-Mail-Liste - ' . $reise['schiff'];
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Übersicht</a></li>
                <li class="breadcrumb-item"><a href="teilnehmerliste.php?id=<?= $reiseId ?>">Teilnehmerliste</a></li>
                <li class="breadcrumb-item active">E-Mail-Liste</li>
            </ol>
        </nav>

        <h1>E-Mail-Liste</h1>
        <p class="lead"><?= htmlspecialchars($reise['schiff']) ?> - <?= count($emailList) ?> E-Mail-Adressen</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Kommagetrennt (für BCC)</h5>
            </div>
            <div class="card-body">
                <textarea class="form-control" rows="4" readonly id="emailBcc"><?= htmlspecialchars(implode(', ', $emailList)) ?></textarea>
                <button class="btn btn-primary mt-2" onclick="copyToClipboard('emailBcc')">
                    In Zwischenablage kopieren
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Zeilenweise</h5>
            </div>
            <div class="card-body">
                <textarea class="form-control" rows="8" readonly id="emailLines"><?= htmlspecialchars(implode("\n", $emailList)) ?></textarea>
                <button class="btn btn-primary mt-2" onclick="copyToClipboard('emailLines')">
                    In Zwischenablage kopieren
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Mailto-Link</h5>
            </div>
            <div class="card-body">
                <p>Klicke hier, um eine E-Mail an alle Teilnehmer zu erstellen:</p>
                <a href="mailto:?bcc=<?= rawurlencode(implode(',', $emailList)) ?>&subject=<?= rawurlencode('Info zum Fantreffen ' . $reise['schiff']) ?>"
                   class="btn btn-success">
                    E-Mail-Programm öffnen
                </a>
                <p class="text-muted small mt-2">
                    Hinweis: Bei vielen Empfängern kann es zu Problemen kommen.
                    In dem Fall die BCC-Liste verwenden.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="teilnehmerliste.php?id=<?= $reiseId ?>" class="btn btn-secondary">
        Zurück zur Teilnehmerliste
    </a>
</div>

<script>
function copyToClipboard(elementId) {
    const textarea = document.getElementById(elementId);
    textarea.select();
    document.execCommand('copy');
    alert('In Zwischenablage kopiert!');
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
