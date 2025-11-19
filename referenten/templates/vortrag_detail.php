<div class="vortrag-detail">
    <h3>
        <?= Security::escape($person['Titel']) ?>
        <?= Security::escape($person['Vorname']) ?>
        <?= Security::escape($person['Name']) ?>
    </h3>

    <div class="detail-section">
        <p>
            <strong>PLZ/Ort:</strong>
            <?= Security::escape($person['PLZ']) ?> <?= Security::escape($person['Ort']) ?>
        </p>

        <?php if (!empty($person['Beruf'])): ?>
            <p>
                <strong>Beruf:</strong>
                <?= Security::escape($person['Beruf']) ?>
            </p>
        <?php endif; ?>

        <p>
            <strong>E-Mail:</strong>
            <a href="mailto:<?= Security::escape($person['eMail']) ?>">
                <?= Security::escape($person['eMail']) ?>
            </a>
        </p>

        <?php if (!empty($person['Telefon'])): ?>
            <p>
                <strong>Telefon:</strong>
                <?= Security::escape($person['Telefon']) ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="detail-section">
        <h4><?= Security::escape($vortrag['Was']) ?></h4>
        <h5><?= Security::escape($vortrag['Thema']) ?></h5>

        <?php if (!empty($vortrag['Inhalt'])): ?>
            <p>
                <strong>Inhalt:</strong><br>
                <?= nl2br(Security::escape($vortrag['Inhalt'])) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($vortrag['Dauer'])): ?>
            <p>
                <strong>Dauer:</strong>
                <?= Security::escape($vortrag['Dauer']) ?> Minuten
            </p>
        <?php endif; ?>

        <?php if (!empty($vortrag['Equipment'])): ?>
            <p>
                <strong>Benötigte Technik:</strong>
                <?= Security::escape($vortrag['Equipment']) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($vortrag['Kompetenz'])): ?>
            <p>
                <strong>Kompetenz für dieses Angebot:</strong><br>
                <?= nl2br(Security::escape($vortrag['Kompetenz'])) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($vortrag['Bemerkung'])): ?>
            <p>
                <strong>Bemerkung:</strong><br>
                <?= nl2br(Security::escape($vortrag['Bemerkung'])) ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="detail-actions">
        <a href="mailto:<?= Security::escape($person['eMail']) ?>?subject=<?= urlencode('Anfrage aus der MinD-Vortragsliste') ?>&body=<?= urlencode('Hallo ' . $person['Vorname'] . ',

es geht um dein Angebot: ' . $vortrag['Was'] . ' - ' . $vortrag['Thema'] . '

') ?>"
            class="btn btn-primary">
            E-Mail senden
        </a>
    </div>
</div>
