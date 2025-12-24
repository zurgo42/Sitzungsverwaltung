<?php
/**
 * Reise bearbeiten (Superuser oder Reise-Admin)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';
require_once __DIR__ . '/../../src/User.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$reiseModel = new Reise($db);
$userModel = new User($db);

$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('Location: ../reisen.php');
    exit;
}

$currentUser = $session->getUser();

// Berechtigung prüfen
$isAdmin = $session->isSuperuser();
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

$fehler = '';
$erfolg = '';

if (isset($_GET['neu'])) {
    $erfolg = 'Reise wurde erfolgreich angelegt!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $action = $_POST['action'] ?? 'update';

        if ($action === 'update') {
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
            } else {
                $reiseModel->update($reiseId, [
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
                    'link_kids' => $linkKids ?: null
                ]);

                $erfolg = 'Reise wurde aktualisiert.';
                $reise = $reiseModel->findById($reiseId);
            }
        } elseif ($action === 'add_admin' && $session->isSuperuser()) {
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $user = $userModel->findByEmail($adminEmail);

            if (!$user) {
                $fehler = 'Benutzer mit dieser E-Mail nicht gefunden.';
            } else {
                if ($reiseModel->addAdmin($reiseId, $user['user_id'])) {
                    // User zum Admin machen wenn noch nicht
                    if ($user['rolle'] === 'user') {
                        $userModel->updateRole($user['user_id'], 'admin');
                    }
                    $erfolg = 'Admin wurde hinzugefügt.';
                } else {
                    $fehler = 'Admin konnte nicht hinzugefügt werden (bereits vorhanden?).';
                }
            }
        } elseif ($action === 'remove_admin' && $session->isSuperuser()) {
            $adminUserId = (int)($_POST['admin_user_id'] ?? 0);
            if ($reiseModel->removeAdmin($reiseId, $adminUserId)) {
                $erfolg = 'Admin wurde entfernt.';
            } else {
                $fehler = 'Admin konnte nicht entfernt werden.';
            }
        } elseif ($action === 'delete' && $session->isSuperuser()) {
            if ($reiseModel->delete($reiseId)) {
                header('Location: ../reisen.php?deleted=1');
                exit;
            } else {
                $fehler = 'Reise konnte nicht gelöscht werden.';
            }
        }
    }
}

$reiseAdmins = $reiseModel->getAdmins($reiseId);
$csrfToken = $session->getCsrfToken();

$pageTitle = 'Reise bearbeiten';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="../reise.php?id=<?= $reiseId ?>">
                    <?= htmlspecialchars($reise['schiff']) ?>
                </a></li>
                <li class="breadcrumb-item active">Bearbeiten</li>
            </ol>
        </nav>
        <h1>Reise bearbeiten</h1>
    </div>
</div>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>

<?php if ($erfolg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Reisedaten</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="update">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="schiff" class="form-label">Schiff *</label>
                            <select class="form-select" id="schiff" name="schiff" required>
                                <?php
                                $schiffe = ['AIDAcosma', 'AIDAnova', 'AIDAprima', 'AIDAperla', 'AIDAmar',
                                           'AIDAblu', 'AIDAsol', 'AIDAstella', 'AIDAluna', 'AIDAbella', 'AIDAdiva'];
                                foreach ($schiffe as $s):
                                    $selected = ($reise['schiff'] === $s) ? 'selected' : '';
                                ?>
                                    <option value="<?= $s ?>" <?= $selected ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bahnhof" class="form-label">Abfahrtshafen</label>
                            <input type="text" class="form-control" id="bahnhof" name="bahnhof"
                                   value="<?= htmlspecialchars($reise['bahnhof'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="anfang" class="form-label">Reisebeginn *</label>
                            <input type="date" class="form-control" id="anfang" name="anfang"
                                   value="<?= $reise['anfang'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ende" class="form-label">Reiseende *</label>
                            <input type="date" class="form-control" id="ende" name="ende"
                                   value="<?= $reise['ende'] ?>" required>
                        </div>
                    </div>

                    <hr>
                    <h6>Fantreffen</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="treffen_ort" class="form-label">Treffpunkt</label>
                            <input type="text" class="form-control" id="treffen_ort" name="treffen_ort"
                                   value="<?= htmlspecialchars($reise['treffen_ort'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="treffen_zeit" class="form-label">Treffen Datum/Uhrzeit</label>
                            <input type="datetime-local" class="form-control" id="treffen_zeit" name="treffen_zeit"
                                   value="<?= $reise['treffen_zeit'] ? date('Y-m-d\TH:i', strtotime($reise['treffen_zeit'])) : '' ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="treffen_status" class="form-label">Status</label>
                            <select class="form-select" id="treffen_status" name="treffen_status">
                                <option value="geplant" <?= $reise['treffen_status'] === 'geplant' ? 'selected' : '' ?>>
                                    Geplant
                                </option>
                                <option value="bestaetigt" <?= $reise['treffen_status'] === 'bestaetigt' ? 'selected' : '' ?>>
                                    Bestätigt
                                </option>
                                <option value="abgesagt" <?= $reise['treffen_status'] === 'abgesagt' ? 'selected' : '' ?>>
                                    Abgesagt
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="treffen_info" class="form-label">Info zum Treffen</label>
                        <textarea class="form-control" id="treffen_info" name="treffen_info"
                                  rows="3"><?= htmlspecialchars($reise['treffen_info'] ?? '') ?></textarea>
                    </div>

                    <hr>
                    <h6>Links</h6>

                    <div class="mb-3">
                        <label for="link_wasserurlaub" class="form-label">Wasserurlaub-Link</label>
                        <input type="url" class="form-control" id="link_wasserurlaub" name="link_wasserurlaub"
                               value="<?= htmlspecialchars($reise['link_wasserurlaub'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="link_facebook" class="form-label">Facebook-Link</label>
                        <input type="url" class="form-control" id="link_facebook" name="link_facebook"
                               value="<?= htmlspecialchars($reise['link_facebook'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="link_kids" class="form-label">Kids-Club-Link</label>
                        <input type="url" class="form-control" id="link_kids" name="link_kids"
                               value="<?= htmlspecialchars($reise['link_kids'] ?? '') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">Speichern</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Admins verwalten -->
        <?php if ($session->isSuperuser()): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Reise-Admins</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reiseAdmins)): ?>
                        <p class="text-muted">Keine Admins zugewiesen.</p>
                    <?php else: ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($reiseAdmins as $admin): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($admin['email']) ?>
                                    <form method="post" class="d-inline"
                                          onsubmit="return confirm('Admin entfernen?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="remove_admin">
                                        <input type="hidden" name="admin_user_id" value="<?= $admin['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">×</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="add_admin">
                        <div class="input-group">
                            <input type="email" class="form-control" name="admin_email"
                                   placeholder="E-Mail des Admins" required>
                            <button type="submit" class="btn btn-outline-primary">Hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reise löschen -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Gefahrenzone</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Diese Aktion kann nicht rückgängig gemacht werden.
                        Alle Anmeldungen werden ebenfalls gelöscht.
                    </p>
                    <form method="post" onsubmit="return confirm('Reise wirklich unwiderruflich löschen?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger">Reise löschen</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Link zur Smartphone-Seite -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Smartphone-Seite</h5>
            </div>
            <div class="card-body">
                <p class="small">Link für Teilnehmer zum Abrufen des Treffpunkt-Status:</p>
                <div class="input-group">
                    <input type="text" class="form-control form-control-sm" readonly
                           value="<?= htmlspecialchars('https://' . ($_SERVER['HTTP_HOST'] ?? 'example.com') . '/status.php?id=' . $reiseId) ?>"
                           id="statusLink">
                    <button class="btn btn-outline-secondary btn-sm" type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('statusLink').value)">
                        Kopieren
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
