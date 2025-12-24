<?php
/**
 * Profil - Teilnehmerverwaltung
 * User kann hier bis zu 4 Teilnehmer anlegen/bearbeiten
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Teilnehmer.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$teilnehmerModel = new Teilnehmer($db);

$currentUser = $session->getUser();
$userId = $currentUser['user_id'];
$fehler = '';
$erfolg = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken. Bitte erneut versuchen.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            // Neuen Teilnehmer hinzufügen
            $name = trim($_POST['name'] ?? '');
            $vorname = trim($_POST['vorname'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $mobil = trim($_POST['mobil'] ?? '');

            if (empty($name) || empty($vorname)) {
                $fehler = 'Name und Vorname sind Pflichtfelder.';
            } elseif (!$teilnehmerModel->canAddMore($userId)) {
                $fehler = 'Maximale Anzahl von 4 Teilnehmern erreicht.';
            } else {
                $id = $teilnehmerModel->create($userId, [
                    'name'     => $name,
                    'vorname'  => $vorname,
                    'nickname' => $nickname ?: null,
                    'mobil'    => $mobil ?: null
                ]);
                if ($id) {
                    $erfolg = 'Teilnehmer wurde hinzugefügt.';
                } else {
                    $fehler = 'Fehler beim Hinzufügen des Teilnehmers.';
                }
            }
        } elseif ($action === 'update') {
            // Teilnehmer aktualisieren
            $teilnehmerId = (int)($_POST['teilnehmer_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $vorname = trim($_POST['vorname'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $mobil = trim($_POST['mobil'] ?? '');

            if (empty($name) || empty($vorname)) {
                $fehler = 'Name und Vorname sind Pflichtfelder.';
            } else {
                $updated = $teilnehmerModel->update($teilnehmerId, $userId, [
                    'name'     => $name,
                    'vorname'  => $vorname,
                    'nickname' => $nickname ?: null,
                    'mobil'    => $mobil ?: null
                ]);
                if ($updated) {
                    $erfolg = 'Teilnehmer wurde aktualisiert.';
                } else {
                    $fehler = 'Fehler beim Aktualisieren des Teilnehmers.';
                }
            }
        } elseif ($action === 'delete') {
            // Teilnehmer löschen
            $teilnehmerId = (int)($_POST['teilnehmer_id'] ?? 0);
            if ($teilnehmerModel->delete($teilnehmerId, $userId)) {
                $erfolg = 'Teilnehmer wurde gelöscht.';
            } else {
                $fehler = 'Fehler beim Löschen des Teilnehmers.';
            }
        }
    }
}

$teilnehmer = $teilnehmerModel->getByUser($userId);
$canAddMore = $teilnehmerModel->canAddMore($userId);
$csrfToken = $session->getCsrfToken();

$pageTitle = 'Mein Profil';
include __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1>Mein Profil</h1>
        <p class="text-muted">E-Mail: <?= htmlspecialchars($currentUser['email']) ?></p>
    </div>
</div>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>

<?php if ($erfolg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Meine Teilnehmer (<?= count($teilnehmer) ?>/4)</h5>
                <?php if ($canAddMore): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                        + Teilnehmer hinzufügen
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Hier kannst du bis zu 4 Teilnehmer anlegen (z.B. dich selbst, Partner, Kinder).
                    Diese Teilnehmer können dann bei jeder Reiseanmeldung ausgewählt werden.
                </p>

                <?php if (empty($teilnehmer)): ?>
                    <div class="alert alert-info">
                        Du hast noch keine Teilnehmer angelegt. Füge mindestens einen Teilnehmer hinzu,
                        um dich für Reisen anmelden zu können.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Vorname</th>
                                    <th>Nickname</th>
                                    <th>Mobil</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teilnehmer as $t): ?>
                                    <tr>
                                        <td><?= $t['position'] ?></td>
                                        <td><?= htmlspecialchars($t['name']) ?></td>
                                        <td><?= htmlspecialchars($t['vorname']) ?></td>
                                        <td><?= htmlspecialchars($t['nickname'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($t['mobil'] ?? '-') ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal"
                                                    data-id="<?= $t['teilnehmer_id'] ?>"
                                                    data-name="<?= htmlspecialchars($t['name']) ?>"
                                                    data-vorname="<?= htmlspecialchars($t['vorname']) ?>"
                                                    data-nickname="<?= htmlspecialchars($t['nickname'] ?? '') ?>"
                                                    data-mobil="<?= htmlspecialchars($t['mobil'] ?? '') ?>">
                                                Bearbeiten
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal"
                                                    data-id="<?= $t['teilnehmer_id'] ?>"
                                                    data-name="<?= htmlspecialchars($t['vorname'] . ' ' . $t['name']) ?>">
                                                Löschen
                                            </button>
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

<div class="row mt-3">
    <div class="col-12">
        <a href="dashboard.php" class="btn btn-secondary">Zurück zur Übersicht</a>
        <a href="passwort.php" class="btn btn-outline-secondary">Passwort ändern</a>
    </div>
</div>

<!-- Modal: Teilnehmer hinzufügen -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Neuen Teilnehmer hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add-vorname" class="form-label">Vorname *</label>
                        <input type="text" class="form-control" id="add-vorname" name="vorname" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-name" class="form-label">Nachname *</label>
                        <input type="text" class="form-control" id="add-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-nickname" class="form-label">Nickname (optional)</label>
                        <input type="text" class="form-control" id="add-nickname" name="nickname">
                        <div class="form-text">Wird z.B. in der Teilnehmerliste angezeigt</div>
                    </div>
                    <div class="mb-3">
                        <label for="add-mobil" class="form-label">Mobilnummer (optional)</label>
                        <input type="tel" class="form-control" id="add-mobil" name="mobil">
                        <div class="form-text">Für Kontakt beim Fantreffen. <strong>Wird nicht veröffentlicht</strong>, nur für die Organisatoren sichtbar.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Teilnehmer bearbeiten -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="teilnehmer_id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title">Teilnehmer bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-vorname" class="form-label">Vorname *</label>
                        <input type="text" class="form-control" id="edit-vorname" name="vorname" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Nachname *</label>
                        <input type="text" class="form-control" id="edit-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-nickname" class="form-label">Nickname (optional)</label>
                        <input type="text" class="form-control" id="edit-nickname" name="nickname">
                    </div>
                    <div class="mb-3">
                        <label for="edit-mobil" class="form-label">Mobilnummer (optional)</label>
                        <input type="tel" class="form-control" id="edit-mobil" name="mobil">
                        <div class="form-text"><strong>Wird nicht veröffentlicht</strong>, nur für die Organisatoren sichtbar.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Teilnehmer löschen -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="teilnehmer_id" id="delete-id">
                <div class="modal-header">
                    <h5 class="modal-title">Teilnehmer löschen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Möchtest du <strong id="delete-name"></strong> wirklich löschen?</p>
                    <p class="text-danger">
                        Achtung: Der Teilnehmer wird auch aus allen Reiseanmeldungen entfernt!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal: Bearbeiten - Daten übertragen
document.getElementById('editModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    document.getElementById('edit-id').value = button.dataset.id;
    document.getElementById('edit-name').value = button.dataset.name;
    document.getElementById('edit-vorname').value = button.dataset.vorname;
    document.getElementById('edit-nickname').value = button.dataset.nickname;
    document.getElementById('edit-mobil').value = button.dataset.mobil;
});

// Modal: Löschen - Daten übertragen
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    document.getElementById('delete-id').value = button.dataset.id;
    document.getElementById('delete-name').textContent = button.dataset.name;
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
