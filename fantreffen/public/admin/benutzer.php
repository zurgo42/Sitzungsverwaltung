<?php
/**
 * Benutzerverwaltung (nur Superuser)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/User.php';

$session = new Session();
$session->requireLogin();

if (!$session->isSuperuser()) {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$userModel = new User($db);

$fehler = '';
$erfolg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $action = $_POST['action'] ?? '';
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $currentUser = $session->getUser();

        // Nicht sich selbst bearbeiten
        if ($targetUserId === $currentUser['user_id']) {
            $fehler = 'Du kannst deine eigene Rolle nicht ändern.';
        } else {
            if ($action === 'set_role') {
                $newRole = $_POST['rolle'] ?? 'user';
                if (in_array($newRole, ['user', 'admin', 'superuser'])) {
                    if ($userModel->updateRole($targetUserId, $newRole)) {
                        $erfolg = 'Rolle wurde geändert.';
                    } else {
                        $fehler = 'Fehler beim Ändern der Rolle.';
                    }
                }
            } elseif ($action === 'delete') {
                if ($userModel->delete($targetUserId)) {
                    $erfolg = 'Benutzer wurde gelöscht.';
                } else {
                    $fehler = 'Fehler beim Löschen des Benutzers.';
                }
            }
        }
    }
}

// Alle Benutzer laden
$users = $db->fetchAll(
    "SELECT u.*,
            (SELECT COUNT(*) FROM fan_teilnehmer t WHERE t.user_id = u.user_id) AS anzahl_teilnehmer,
            (SELECT COUNT(*) FROM fan_anmeldungen a WHERE a.user_id = u.user_id) AS anzahl_anmeldungen
     FROM fan_users u
     ORDER BY u.rolle DESC, u.email ASC"
);

$csrfToken = $session->getCsrfToken();
$currentUser = $session->getUser();

$pageTitle = 'Benutzerverwaltung';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Übersicht</a></li>
                <li class="breadcrumb-item active">Benutzerverwaltung</li>
            </ol>
        </nav>
        <h1>Benutzerverwaltung</h1>
    </div>
</div>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>

<?php if ($erfolg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>E-Mail</th>
                        <th>Rolle</th>
                        <th>Teilnehmer</th>
                        <th>Anmeldungen</th>
                        <th>Registriert</th>
                        <th>Letzter Login</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($user['email']) ?>
                                <?php if ($user['user_id'] === $currentUser['user_id']): ?>
                                    <span class="badge bg-info">Du</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $rolleClass = [
                                    'user' => 'secondary',
                                    'admin' => 'primary',
                                    'superuser' => 'danger'
                                ];
                                $rolleText = [
                                    'user' => 'User',
                                    'admin' => 'Admin',
                                    'superuser' => 'Superuser'
                                ];
                                ?>
                                <span class="badge bg-<?= $rolleClass[$user['rolle']] ?>">
                                    <?= $rolleText[$user['rolle']] ?>
                                </span>
                            </td>
                            <td><?= $user['anzahl_teilnehmer'] ?></td>
                            <td><?= $user['anzahl_anmeldungen'] ?></td>
                            <td><?= date('d.m.Y', strtotime($user['erstellt'])) ?></td>
                            <td>
                                <?= $user['letzter_login']
                                    ? date('d.m.Y H:i', strtotime($user['letzter_login']))
                                    : '-' ?>
                            </td>
                            <td>
                                <?php if ($user['user_id'] !== $currentUser['user_id']): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                                data-bs-toggle="dropdown">
                                            Rolle ändern
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php foreach (['user', 'admin', 'superuser'] as $rolle): ?>
                                                <?php if ($user['rolle'] !== $rolle): ?>
                                                    <li>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="action" value="set_role">
                                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                            <input type="hidden" name="rolle" value="<?= $rolle ?>">
                                                            <button type="submit" class="dropdown-item">
                                                                → <?= $rolleText[$rolle] ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <form method="post" class="d-inline"
                                          onsubmit="return confirm('Benutzer wirklich löschen? Alle Daten gehen verloren!');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Löschen</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="../dashboard.php" class="btn btn-secondary">Zurück zur Übersicht</a>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
