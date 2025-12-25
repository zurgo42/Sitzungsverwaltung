<?php
/**
 * registrieren.php - Benutzer-Registrierung
 */

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/User.php';

Session::start();

// Bereits eingeloggt?
if (Session::isLoggedIn()) {
    Session::redirect('index.php');
}

$errors = [];
$email = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $passwort = $_POST['passwort'] ?? '';
    $passwort2 = $_POST['passwort2'] ?? '';
    $datenschutz = isset($_POST['datenschutz']);

    // Validierung
    if (empty($email)) {
        $errors[] = 'Bitte E-Mail-Adresse eingeben.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte gültige E-Mail-Adresse eingeben.';
    }

    if (empty($passwort)) {
        $errors[] = 'Bitte Passwort eingeben.';
    } elseif (strlen($passwort) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    }

    if ($passwort !== $passwort2) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }

    if (!$datenschutz) {
        $errors[] = 'Bitte die Datenschutzerklärung akzeptieren.';
    }

    // Registrierung durchführen
    if (empty($errors)) {
        try {
            $userManager = new User();
            $userId = $userManager->register($email, $passwort);

            // Direkt einloggen
            $user = $userManager->findById($userId);
            Session::login($user);

            Session::success('Willkommen! Wähle eine Reise und melde dich an.');
            Session::redirect('index.php');
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'bereits registriert') !== false) {
                $errors[] = 'Diese E-Mail-Adresse ist bereits registriert.';
            } else {
                $errors[] = 'Ein Fehler ist aufgetreten. Bitte später erneut versuchen.';
            }
        }
    }
}

$pageTitle = 'Registrieren - Aida Fantreffen';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">
                    👤 Registrieren
                </h2>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        ⚠ <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="registrieren.php">
                    <div class="form-floating mb-3">
                        <input type="email"
                               class="form-control"
                               id="email"
                               name="email"
                               placeholder="name@example.com"
                               value="<?= htmlspecialchars($email) ?>"
                               required
                               autofocus>
                        <label for="email">E-Mail-Adresse</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password"
                               class="form-control"
                               id="passwort"
                               name="passwort"
                               placeholder="Passwort"
                               minlength="8"
                               required>
                        <label for="passwort">Passwort (mind. 8 Zeichen)</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password"
                               class="form-control"
                               id="passwort2"
                               name="passwort2"
                               placeholder="Passwort wiederholen"
                               required>
                        <label for="passwort2">Passwort wiederholen</label>
                    </div>

                    <div class="form-check mb-3 p-3 border rounded bg-light">
                        <input type="checkbox"
                               class="form-check-input"
                               id="datenschutz"
                               name="datenschutz"
                               style="width: 1.2em; height: 1.2em;"
                               required>
                        <label class="form-check-label ms-2" for="datenschutz">
                            <strong>Ich habe die <a href="datenschutz.php" target="_blank">Datenschutzerklärung</a> gelesen und akzeptiere sie.</strong>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">
                        👤 Registrieren
                    </button>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-3">
                Bereits registriert?
                <a href="login.php">Jetzt anmelden</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
