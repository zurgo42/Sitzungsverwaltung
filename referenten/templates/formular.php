<?php include __DIR__ . '/header.php'; ?>

<div class="form-container">
    <div class="form-sidebar">
        <h2>Vorträge und Diskussionen ...</h2>
        <p>... sind eine Bereicherung des regionalen/lokalen Mensa-Lebens.</p>

        <div class="info-box">
            <p>Die neue Referentenliste (derzeit <strong><?= $anzaktive ?></strong> Einträge) erhält man durch Klick auf den Button.</p>

            <form action="referenten.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>">
                <input type="hidden" name="steuer" value="4">
                <button type="submit" class="btn btn-primary btn-large">
                    Zur Referentenliste
                </button>
            </form>
        </div>

        <div class="info-text">
            <p>Es gibt viele Mensa-Mitglieder mit hochinteressanten Berufen, Hobbys und speziellen Fertigkeiten und Kenntnissen. Gehörst du auch dazu und hättest Lust, andere Ms daran teilhaben zu lassen?</p>

            <p>Mit dem Formular auf der rechten Seite kannst du dich als Referent eintragen und später jederzeit deine Angebote verändern, erweitern oder auch deaktivieren.</p>

            <p>Die Liste ist vereinsintern - nur Mensa-Mitglieder können sie abrufen und dich hier als Referenten finden.</p>

            <p><strong>Hinweis:</strong> Kommerzielle Angebote, Versuche zur Akquisition von Neukunden und dergleichen gehören bitte nicht in diese Liste.</p>

            <p>Für M2M-Vorträge sind übrigens keine Honorare vorgesehen. Wenn du eine längere Anreise hast, kannst du mit dem Einladenden sprechen, ob es für einen Zuschuss zu den Reisekosten ein Budget gibt.</p>
        </div>
    </div>

    <div class="form-main">
        <h2>Referentenformular</h2>

        <?php if (!empty($meineVortraege)): ?>
            <div class="existing-entries">
                <h3>Deine bestehenden Angebote:</h3>
                <form action="referenten.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>">
                    <input type="hidden" name="steuer" value="8">

                    <?php foreach ($meineVortraege as $i => $vortrag): ?>
                        <div class="entry-item">
                            <label class="radio-label">
                                <input type="radio" name="XThema" value="<?= $vortrag['ID'] ?>"
                                    <?= $i === count($meineVortraege) - 1 ? 'checked' : '' ?>>
                                <span class="entry-title">
                                    <?= Security::escape($vortrag['Thema']) ?>
                                    <?php if (!$vortrag['aktiv']): ?>
                                        <span class="badge badge-inactive">deaktiviert</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <a href="#" class="info-link" data-id="<?= $vortrag['ID'] ?>" data-mnr="<?= Security::escape($mNr) ?>">
                                Details anzeigen
                            </a>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-secondary">Ändern oder deaktivieren</button>
                </form>
            </div>
        <?php else: ?>
            <div class="info-message">
                <p>Hinweis: Falls unter deiner MNr bereits Vorträge gespeichert sind, wird hier per Auswahlliste die Möglichkeit der Änderung bzw. Deaktivierung geboten.</p>
            </div>
        <?php endif; ?>

        <h3><?= isset($vortragToEdit) ? 'Angebot bearbeiten' : 'Neues Angebot' ?></h3>

        <form action="referenten.php" method="post" class="referenten-form">
            <input type="hidden" name="csrf_token" value="<?= Security::escape($csrfToken) ?>">
            <input type="hidden" name="steuer" value="<?= $steuer ?>">
            <?php if (isset($vortragToEdit)): ?>
                <input type="hidden" name="ID" value="<?= $vortragToEdit['ID'] ?>">
            <?php endif; ?>

            <fieldset class="form-section">
                <legend>Deine Daten</legend>

                <div class="form-row">
                    <div class="form-group">
                        <label for="Vorname">Vorname *</label>
                        <input type="text" id="Vorname" name="Vorname" required
                            value="<?= Security::escape($personDaten['Vorname'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="Name">Name *</label>
                        <input type="text" id="Name" name="Name" required
                            value="<?= Security::escape($personDaten['Name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="Titel">Titel</label>
                        <input type="text" id="Titel" name="Titel"
                            value="<?= Security::escape($personDaten['Titel'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="PLZ">PLZ *</label>
                        <input type="text" id="PLZ" name="PLZ" pattern="\d{5}" required
                            value="<?= Security::escape($personDaten['PLZ'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="Ort">Ort *</label>
                        <input type="text" id="Ort" name="Ort" required
                            value="<?= Security::escape($personDaten['Ort'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="Gebj">Geburtsjahr</label>
                        <input type="text" id="Gebj" name="Gebj" pattern="\d{4}"
                            value="<?= Security::escape($personDaten['Gebj'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="Beruf">Beruf</label>
                        <input type="text" id="Beruf" name="Beruf"
                            value="<?= Security::escape($personDaten['Beruf'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="Telefon">Telefon</label>
                        <input type="tel" id="Telefon" name="Telefon"
                            value="<?= Security::escape($personDaten['Telefon'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="eMail">E-Mail *</label>
                        <input type="email" id="eMail" name="eMail" required
                            value="<?= Security::escape($personDaten['eMail'] ?? '') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend><?= isset($vortragToEdit) ? 'Mein Angebot' : 'Neues Angebot' ?></legend>

                <?php if (isset($vortragToEdit)): ?>
                    <div class="form-row">
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" name="aktiv" value="1"
                                    <?= ($vortragToEdit['aktiv'] ?? 1) ? 'checked' : '' ?>>
                                Angebot ist aktiv
                                <span class="help-text">Man kann ein Angebot zeitweise aus der Liste nehmen, indem man den Haken entfernt.</span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="Was">Was genau bietest du an? *</label>
                        <select id="Was" name="Was" required>
                            <?php foreach ($wasOptionen as $option): ?>
                                <option value="<?= Security::escape($option) ?>"
                                    <?= isset($vortragToEdit) && $vortragToEdit['Was'] === $option ? 'selected' : '' ?>>
                                    <?= Security::escape($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="Wo">In welcher Region? *</label>
                        <select id="Wo" name="Wo" required>
                            <?php foreach ($regionOptionen as $option): ?>
                                <option value="<?= Security::escape($option) ?>"
                                    <?= isset($vortragToEdit) && $vortragToEdit['Wo'] === $option ? 'selected' : '' ?>>
                                    <?= Security::escape($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="Entf">Max. Entfernung (km)</label>
                        <input type="number" id="Entf" name="Entf" min="0" max="9999"
                            value="<?= Security::escape($vortragToEdit['Entf'] ?? '') ?>"
                            placeholder="z.B. 100">
                        <span class="help-text">Maximale Entfernung in km Luftlinie</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="Kategorie">Kategorie *</label>
                        <select id="Kategorie" name="Kategorie" required>
                            <?php foreach ($kategorien as $kategorie): ?>
                                <?php if ($kategorie !== ''): ?>
                                    <option value="<?= Security::escape($kategorie) ?>"
                                        <?= isset($vortragToEdit) && $vortragToEdit['Kategorie'] === $kategorie ? 'selected' : '' ?>>
                                        <?= Security::escape($kategorie) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="Thema">Thema/Überschrift * <span class="char-counter" data-for="Thema"></span></label>
                        <input type="text" id="Thema" name="Thema" maxlength="60" required
                            value="<?= Security::escape($vortragToEdit['Thema'] ?? '') ?>"
                            placeholder="Bitte eine kurze, aussagekräftige Überschrift">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="Inhalt">Inhalt *</label>
                        <textarea id="Inhalt" name="Inhalt" rows="5" required
                            placeholder="Etwas ausführlichere Beschreibung des Inhalts"><?= Security::escape($vortragToEdit['Inhalt'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="Equipment">Benötigte Vortragstechnik</label>
                        <input type="text" id="Equipment" name="Equipment"
                            value="<?= Security::escape($vortragToEdit['Equipment'] ?? '') ?>"
                            placeholder="z.B. Beamer, Flipchart, Audioanlage">
                    </div>

                    <div class="form-group">
                        <label for="Dauer">Dauer (Minuten)</label>
                        <input type="number" id="Dauer" name="Dauer" min="0" max="999"
                            value="<?= Security::escape($vortragToEdit['Dauer'] ?? '') ?>"
                            placeholder="ohne Fragerunde/Diskussion">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="Kompetenz">Meine Kompetenz</label>
                        <textarea id="Kompetenz" name="Kompetenz" rows="3"
                            placeholder="Warum gerade du dieses Thema kompetent behandeln kannst"><?= Security::escape($vortragToEdit['Kompetenz'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="Bemerkung">Bemerkung</label>
                        <textarea id="Bemerkung" name="Bemerkung" rows="3"
                            placeholder="Weiterführende Informationen zu diesem Angebot"><?= Security::escape($vortragToEdit['Bemerkung'] ?? '') ?></textarea>
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= isset($vortragToEdit) ? 'Änderungen speichern' : 'In die Liste eintragen' ?>
                </button>
                <a href="referenten.php" class="btn btn-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
