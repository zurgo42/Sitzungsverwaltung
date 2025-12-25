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

    <!-- Custom CSS (ohne Bootstrap) -->
    <link href="<?= $basePath ?>css/style.css" rel="stylesheet">

    <?php if (basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
    <!-- Preload LCP-Bild für schnellere Anzeige -->
    <link rel="preload" as="image" href="<?= $basePath ?>images/FantreffenSchiff.jpg">
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="<?= $basePath ?>index.php">
                <img src="<?= $basePath ?>Logo_ws_klein_transparent.png" alt="" height="40">
                AIDA Fantreffen
            </a>
            <button class="navbar-toggler" onclick="toggleNav()">☰</button>
            <ul class="navbar-nav" id="mainNav">
                <?php if ($isLoggedIn): ?>
                    <?php if ($userRolle === 'superuser'): ?>
                        <!-- Superuser Navigation -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>admin/reise-neu.php">
                                + Reise hinzufügen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>admin/benutzer.php">
                                Benutzer
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#">
                                ⚙ Superuser ▾
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= $basePath ?>passwort.php">Passwort ändern</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= $basePath ?>logout.php">Abmelden</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Normaler User Navigation -->
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#">
                                <?= htmlspecialchars($userEmail) ?> ▾
                            </a>
                            <ul class="dropdown-menu">
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
    </nav>

    <!-- Hauptinhalt -->
    <main class="container my-4">
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?>">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>
