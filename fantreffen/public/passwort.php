<?php
/**
 * Passwort ändern
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/User.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$userModel = new User($db);

$currentUser = $session->getUser();
$fehler = '';
$erfolg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken. Bitte erneut versuchen.';
    } else {
        $aktuellesPasswort = $_POST['aktuelles_passwort'] ?? '';
        $neuesPasswort = $_POST['neues_passwort'] ?? '';
        $neuesPasswortWdh = $_POST['neues_passwort_wdh'] ?? '';

        if (empty($aktuellesPasswort) || empty($neuesPasswort) || empty($neuesPasswortWdh)) {
            $fehler = 'Bitte alle Felder ausfüllen.';
        } elseif ($neuesPasswort !== $neuesPasswortWdh) {
            $fehler = 'Die neuen Passwörter stimmen nicht überein.';
        } elseif (strlen($neuesPasswort) < 8) {
            $fehler = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        } else {
            // Aktuelles Passwort prüfen
            $user = $userModel->findByEmail($currentUser['email'], true);
            if (!$user || !password_verify($aktuellesPasswort, $user['passwort_hash'])) {
                $fehler = 'Das aktuelle Passwort ist nicht korrekt.';
            } else {
                // Neues Passwort setzen
                if ($userModel->updatePassword($currentUser['user_id'], $neuesPasswort)) {
                    $erfolg = 'Dein Passwort wurde erfolgreich geändert.';
                } else {
                    $fehler = 'Fehler beim Ändern des Passworts.';
                }
            }
        }
    }
}

$csrfToken = $session->getCsrfToken();

$pageTitle = 'Passwort ändern';
include __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <h1>Passwort ändern</h1>

        <?php if ($fehler): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <?php if ($erfolg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="mb-3">
                        <label for="aktuelles_passwort" class="form-label">Aktuelles Passwort</label>
                        <input type="password" class="form-control" id="aktuelles_passwort"
                               name="aktuelles_passwort" required>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="neues_passwort" class="form-label">Neues Passwort</label>
                        <input type="password" class="form-control" id="neues_passwort"
                               name="neues_passwort" required minlength="8">
                        <div class="form-text">Mindestens 8 Zeichen</div>
                    </div>

                    <div class="mb-3">
                        <label for="neues_passwort_wdh" class="form-label">Neues Passwort wiederholen</label>
                        <input type="password" class="form-control" id="neues_passwort_wdh"
                               name="neues_passwort_wdh" required minlength="8">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Passwort ändern</button>
                        <a href="profil.php" class="btn btn-secondary">Abbrechen</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
