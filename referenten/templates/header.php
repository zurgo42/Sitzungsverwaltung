<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="description" content="MinD-Referentenliste - Vortragsthemen und Referenten">
    <title><?= Security::escape($pageTitle ?? 'MinD-Referentenliste') ?></title>
    <link rel="shortcut icon" href="/MinD.ico" type="image/x-icon">
    <link rel="stylesheet" href="css/referenten.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1>MinD-Referentenliste</h1>
                <p class="header-subtitle">Vortragsthemen und Referenten</p>
            </div>
        </header>

        <main class="main-content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= Security::escape($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($messages)): ?>
                <div class="alert alert-success">
                    <?php foreach ($messages as $message): ?>
                        <p><?= Security::escape($message) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
