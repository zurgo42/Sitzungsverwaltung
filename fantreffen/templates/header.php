<?php
// Session starten falls noch nicht gestartet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userEmail = $_SESSION['email'] ?? '';
$userRolle = $_SESSION['rolle'] ?? '';
$basePath = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'AIDA Fantreffen') ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= $basePath ?>Logo_ws_klein_transparent.png">
    <link rel="apple-touch-icon" href="<?= $basePath ?>Logo_ws_klein_transparent.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= $basePath ?>css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= $basePath ?>index.php">
                <img src="<?= $basePath ?>Logo_ws_klein_transparent.png" alt="" height="40" class="me-2">
                AIDA Fantreffen
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if ($isLoggedIn): ?>
                        <?php if ($userRolle === 'superuser'): ?>
                            <!-- Superuser Navigation -->
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $basePath ?>admin/reise-neu.php">
                                    <i class="bi bi-plus-circle"></i> Reise hinzufügen
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $basePath ?>admin/benutzer.php">
                                    <i class="bi bi-people"></i> Benutzer
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="bi bi-shield-check"></i> Superuser
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= $basePath ?>passwort.php">Passwort ändern</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>logout.php">Abmelden</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <!-- Normaler User Navigation -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <?= htmlspecialchars($userEmail) ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= $basePath ?>passwort.php">Passwort ändern</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>logout.php">Abmelden</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>login.php">Anmelden</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>registrieren.php">Registrieren</a>
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
