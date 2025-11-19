<?php include __DIR__ . '/header.php'; ?>

<div class="list-container">
    <div class="list-header">
        <h2>MinD-Referentenliste</h2>
        <p class="list-description">
            Hier findest du <strong><?= $anzaktive ?></strong> Vortragsthemen von Mitgliedern,
            die für Vorträge/Referate/Workshops etc. zur Verfügung stehen.
        </p>
        <p class="list-hint">
            Klicke auf eine Spaltenüberschrift zum Sortieren.
            Details zum Angebot erscheinen beim Klick auf "Details".
        </p>
        <?php if ($meinePLZ): ?>
            <p class="plz-info">
                Entfernungen werden ab deiner PLZ <strong><?= Security::escape($meinePLZ) ?></strong> berechnet.
            </p>
        <?php else: ?>
            <p class="plz-warning">
                Trage deine PLZ im <a href="referenten.php">Eingabeformular</a> ein,
                um Entfernungen angezeigt zu bekommen.
            </p>
        <?php endif; ?>
    </div>

    <div class="table-controls">
        <form action="referenten.php" method="post" class="sort-form">
            <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>">
            <input type="hidden" name="steuer" value="4">
            <label for="sort-select">Sortieren nach:</label>
            <select id="sort-select" name="weiter" onchange="this.form.submit()">
                <option value="Kategorie" <?= $sortBy === 'Kategorie' ? 'selected' : '' ?>>Kategorie</option>
                <option value="Name" <?= $sortBy === 'Name' ? 'selected' : '' ?>>Name</option>
                <option value="PLZ" <?= $sortBy === 'PLZ' ? 'selected' : '' ?>>PLZ</option>
                <option value="Thema" <?= $sortBy === 'Thema' ? 'selected' : '' ?>>Thema</option>
                <option value="Wo" <?= $sortBy === 'Wo' ? 'selected' : '' ?>>Region</option>
                <option value="Was" <?= $sortBy === 'Was' ? 'selected' : '' ?>>Art</option>
            </select>
        </form>
    </div>

    <div class="table-responsive">
        <table class="referenten-table">
            <thead>
                <tr>
                    <th>Kategorie</th>
                    <th>Referent/in</th>
                    <th>PLZ</th>
                    <th>Thema</th>
                    <th>Region</th>
                    <th>Art</th>
                    <?php if ($meinePLZ): ?>
                        <th>Entfernung</th>
                    <?php endif; ?>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vortraege as $vortrag): ?>
                    <tr>
                        <td data-label="Kategorie"><?= Security::escape($vortrag['Kategorie']) ?></td>
                        <td data-label="Referent/in">
                            <?= Security::escape($vortrag['Titel']) ?>
                            <?= Security::escape($vortrag['Vorname']) ?>
                            <?= Security::escape($vortrag['Name']) ?>
                        </td>
                        <td data-label="PLZ"><?= Security::escape($vortrag['PLZ']) ?></td>
                        <td data-label="Thema" class="thema-cell">
                            <strong><?= Security::escape($vortrag['Thema']) ?></strong>
                        </td>
                        <td data-label="Region"><?= Security::escape($vortrag['Wo']) ?></td>
                        <td data-label="Art"><?= Security::escape($vortrag['Was']) ?></td>
                        <?php if ($meinePLZ): ?>
                            <?php
                            $entfernung = $model->calculateDistance($meinePLZ, $vortrag['PLZ']);
                            $distanceClass = $entfernung < 100 ? 'distance-near' : ($entfernung < 300 ? 'distance-medium' : 'distance-far');
                            ?>
                            <td data-label="Entfernung" class="<?= $distanceClass ?>">
                                <?= $entfernung ?> km
                            </td>
                        <?php endif; ?>
                        <td data-label="Aktionen" class="action-cell">
                            <button class="btn btn-small btn-info details-btn"
                                data-id="<?= $vortrag['ID'] ?>"
                                data-mnr="<?= Security::escape($vortrag['PersMNr']) ?>">
                                Details
                            </button>
                            <a href="mailto:<?= Security::escape($vortrag['eMail']) ?>?subject=<?= urlencode('Anfrage aus der MinD-Vortragsliste') ?>&body=<?= urlencode('Hallo ' . $vortrag['Vorname'] . ',

es geht um dein Angebot: ' . $vortrag['Was'] . ' - ' . $vortrag['Thema'] . '

') ?>"
                                class="btn btn-small btn-primary">
                                E-Mail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="list-footer">
        <form action="referenten.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>">
            <input type="hidden" name="steuer" value="0">
            <button type="submit" class="btn btn-secondary">Zum Eingabe-Formular</button>
        </form>
    </div>
</div>

<!-- Modal für Vortragsdetails -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div id="modalBody"></div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
