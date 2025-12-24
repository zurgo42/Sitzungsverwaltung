<?php
// Session starten falls noch nicht gestartet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userEmail = $_SESSION['email'] ?? '';
$userRolle = $_SESSION['rolle'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'AIDA Fantreffen') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../css/style.css' : 'css/style.css' ?>" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../index.php' : 'index.php' ?>">
                AIDA Fantreffen
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../reisen.php' : 'reisen.php' ?>">Reisen</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../dashboard.php' : 'dashboard.php' ?>">Übersicht</a>
                        </li>
                        <?php if ($userRolle === 'superuser'): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    Verwaltung
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'reise-neu.php' : 'admin/reise-neu.php' ?>">Neue Reise anlegen</a></li>
                                    <li><a class="dropdown-item" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'benutzer.php' : 'admin/benutzer.php' ?>">Benutzer verwalten</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <?= htmlspecialchars($userEmail) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../profil.php' : 'profil.php' ?>">Mein Profil</a></li>
                                <li><a class="dropdown-item" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../passwort.php' : 'passwort.php' ?>">Passwort ändern</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../logout.php' : 'logout.php' ?>">Abmelden</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../login.php' : 'login.php' ?>">Anmelden</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../registrieren.php' : 'registrieren.php' ?>">Registrieren</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hauptinhalt -->
    <main class="container my-4">
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>
