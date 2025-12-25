<?php
/**
 * Neue Reise anlegen (nur Superuser)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';

$session = new Session();
$session->requireLogin();

if (!$session->isSuperuser()) {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$reiseModel = new Reise($db);

$fehler = '';
$erfolg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $schiff = trim($_POST['schiff'] ?? '');
        $bahnhof = trim($_POST['bahnhof'] ?? '');
        $anfang = $_POST['anfang'] ?? '';
        $ende = $_POST['ende'] ?? '';
        $treffenOrt = trim($_POST['treffen_ort'] ?? '');
        $treffenZeit = $_POST['treffen_zeit'] ?? '';
        $treffenStatus = $_POST['treffen_status'] ?? 'geplant';
        $treffenInfo = trim($_POST['treffen_info'] ?? '');
        $linkWasserurlaub = trim($_POST['link_wasserurlaub'] ?? '');
        $linkFacebook = trim($_POST['link_facebook'] ?? '');
        $linkKids = trim($_POST['link_kids'] ?? '');

        if (empty($schiff) || empty($anfang) || empty($ende)) {
            $fehler = 'Schiff und Reisezeitraum sind Pflichtfelder.';
        } elseif (strtotime($anfang) > strtotime($ende)) {
            $fehler = 'Das Enddatum muss nach dem Anfangsdatum liegen.';
        } else {
            $currentUser = $session->getUser();

            $reiseId = $reiseModel->create([
                'schiff' => $schiff,
                'bahnhof' => $bahnhof ?: null,
                'anfang' => $anfang,
                'ende' => $ende,
                'treffen_ort' => $treffenOrt ?: null,
                'treffen_zeit' => $treffenZeit ?: null,
                'treffen_status' => $treffenStatus,
                'treffen_info' => $treffenInfo ?: null,
                'link_wasserurlaub' => $linkWasserurlaub ?: null,
                'link_facebook' => $linkFacebook ?: null,
                'link_kids' => $linkKids ?: null,
                'erstellt_von' => $currentUser['user_id']
            ]);

            if ($reiseId) {
                header('Location: reise-bearbeiten.php?id=' . $reiseId . '&neu=1');
                exit;
            } else {
                $fehler = 'Fehler beim Erstellen der Reise.';
            }
        }
    }
}

$csrfToken = $session->getCsrfToken();

$pageTitle = 'Neue Reise anlegen';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Übersicht</a></li>
                <li class="breadcrumb-item active">Neue Reise</li>
            </ol>
        </nav>
        <h1>Neue Fantreffen-Reise anlegen</h1>
    </div>
</div>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <h5>Reisedaten</h5>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="schiff" class="form-label">Schiff *</label>
                            <select class="form-select" id="schiff" name="schiff" required>
                                <option value="">Bitte wählen...</option>
                                <option value="AIDAcosma">AIDAcosma</option>
                                <option value="AIDAnova">AIDAnova</option>
                                <option value="AIDAprima">AIDAprima</option>
                                <option value="AIDAperla">AIDAperla</option>
                                <option value="AIDAmar">AIDAmar</option>
                                <option value="AIDAblu">AIDAblu</option>
                                <option value="AIDAsol">AIDAsol</option>
                                <option value="AIDAstella">AIDAstella</option>
                                <option value="AIDAluna">AIDAluna</option>
                                <option value="AIDAbella">AIDAbella</option>
                                <option value="AIDAdiva">AIDAdiva</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bahnhof" class="form-label">Abfahrtshafen</label>
                            <input type="text" class="form-control" id="bahnhof" name="bahnhof"
                                   placeholder="z.B. Hamburg, Kiel, Mallorca">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="anfang" class="form-label">Reisebeginn *</label>
                            <input type="date" class="form-control" id="anfang" name="anfang" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ende" class="form-label">Reiseende *</label>
                            <input type="date" class="form-control" id="ende" name="ende" required>
                        </div>
                    </div>

                    <hr>
                    <h5>Fantreffen</h5>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="treffen_ort" class="form-label">Treffpunkt</label>
                            <input type="text" class="form-control" id="treffen_ort" name="treffen_ort"
                                   placeholder="z.B. Theatrium Deck 9">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="treffen_zeit" class="form-label">Treffen Datum/Uhrzeit</label>
                            <input type="datetime-local" class="form-control" id="treffen_zeit" name="treffen_zeit">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="treffen_status" class="form-label">Status</label>
                            <select class="form-select" id="treffen_status" name="treffen_status">
                                <option value="geplant">Geplant</option>
                                <option value="bestaetigt">Bestätigt</option>
                                <option value="abgesagt">Abgesagt</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="treffen_info" class="form-label">Info zum Treffen</label>
                        <textarea class="form-control" id="treffen_info" name="treffen_info" rows="3"
                                  placeholder="Zusätzliche Informationen für die Teilnehmer"></textarea>
                    </div>

                    <hr>
                    <h5>Links (optional)</h5>

                    <div class="mb-3">
                        <label for="link_wasserurlaub" class="form-label">Wasserurlaub-Link</label>
                        <input type="url" class="form-control" id="link_wasserurlaub" name="link_wasserurlaub"
                               placeholder="https://...">
                    </div>

                    <div class="mb-3">
                        <label for="link_facebook" class="form-label">Facebook-Link</label>
                        <input type="url" class="form-control" id="link_facebook" name="link_facebook"
                               placeholder="https://...">
                    </div>

                    <div class="mb-3">
                        <label for="link_kids" class="form-label">Kids-Club-Link</label>
                        <input type="url" class="form-control" id="link_kids" name="link_kids"
                               placeholder="https://...">
                    </div>

                    <hr>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Reise anlegen</button>
                        <a href="../dashboard.php" class="btn btn-secondary">Abbrechen</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
