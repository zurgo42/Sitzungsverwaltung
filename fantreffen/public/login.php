<?php
/**
 * login.php - Benutzer-Login
 */

require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/User.php';

Session::start();

// Bereits eingeloggt?
if (Session::isLoggedIn()) {
    Session::redirect('dashboard.php');
}

$error = '';
$email = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $passwort = $_POST['passwort'] ?? '';

    if (empty($email) || empty($passwort)) {
        $error = 'Bitte E-Mail und Passwort eingeben.';
    } else {
        try {
            $userManager = new User();
            $user = $userManager->login($email, $passwort);

            if ($user) {
                Session::login($user);
                Session::success('Willkommen zurück!');
                Session::redirect($_GET['redirect'] ?? 'index.php');
            } else {
                $error = 'E-Mail oder Passwort ist falsch.';
            }
        } catch (Exception $e) {
            $error = 'Ein Fehler ist aufgetreten. Bitte später erneut versuchen.';
        }
    }
}

$pageTitle = 'Anmelden - Aida Fantreffen';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">
                    → Anmelden
                </h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        ⚠ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
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
                               required>
                        <label for="passwort">Passwort</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                        → Anmelden
                    </button>

                    <div class="text-center">
                        <a href="passwort-vergessen.php" class="text-muted small">
                            Passwort vergessen?
                        </a>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-3">
                Noch kein Konto?
                <a href="registrieren.php">Jetzt registrieren</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
