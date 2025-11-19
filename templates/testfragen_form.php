<?php
/**
 * Formular für Test

fragen-Einreichung
 */
?>

<form method="post" enctype="multipart/form-data" class="testfragen-form" id="testfragenForm">
    <input type="hidden" name="action" value="submit_question">

    <!-- Art der Aufgabe -->
    <div class="form-section">
        <h3>Art der Aufgabe</h3>
        <div class="radio-group">
            <label class="radio-label">
                <input type="radio" name="figural" value="0" checked onchange="toggleFiguralMode(false)">
                <span>Textliche Aufgabe</span>
            </label>
            <label class="radio-label">
                <input type="radio" name="figural" value="1" onchange="toggleFiguralMode(true)">
                <span>Figurale Aufgabe (mit Bildern)</span>
            </label>
        </div>
        <p class="help-text">
            Bei figuralen Aufgaben: Entweder 1 Komplett-Bild ODER 5 Einzelbilder hochladen
        </p>
    </div>

    <!-- Aufgabenstellung -->
    <div class="form-section">
        <h3>Aufgabenstellung</h3>
        <div class="form-row">
            <div class="form-group flex-2">
                <label for="aufgabe">Aufgabenstellung (Text) *</label>
                <textarea name="aufgabe" id="aufgabe" rows="4" class="form-control" required
                    placeholder="z.B. Welche Zahl kommt als nächstes? 2, 4, 8, 16, ..."></textarea>
            </div>
            <div class="form-group flex-1 figural-field" style="display:none;">
                <label for="file0">Oder: Komplett-Bild hochladen</label>
                <input type="file" name="files[0]" id="file0" accept="image/*" class="form-control">
                <p class="help-text">Alle Antworten auf einem Bild</p>
            </div>
        </div>
    </div>

    <!-- Antwortmöglichkeiten -->
    <div class="form-section">
        <h3>Antwortmöglichkeiten (genau 5)</h3>

        <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="form-row answer-row">
                <div class="form-group flex-2 text-field">
                    <label for="antwort<?= $i ?>">Antwort <?= $i ?> *</label>
                    <input type="text" name="antwort<?= $i ?>" id="antwort<?= $i ?>" class="form-control"
                        placeholder="Antworttext eingeben" required>
                </div>
                <div class="form-group flex-1 figural-field" style="display:none;">
                    <label for="file<?= $i ?>">Bild <?= $i ?> hochladen</label>
                    <input type="file" name="files[<?= $i ?>]" id="file<?= $i ?>" accept="image/*" class="form-control figural-upload">
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Richtige Antwort & Regel -->
    <div class="form-section">
        <h3>Lösung</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="richtig">Richtige Antwort *</label>
                <select name="richtig" id="richtig" class="form-control" required>
                    <option value="">Bitte wählen...</option>
                    <option value="1">Antwort 1</option>
                    <option value="2">Antwort 2</option>
                    <option value="3">Antwort 3</option>
                    <option value="4">Antwort 4</option>
                    <option value="5">Antwort 5</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group full-width">
                <label for="regel">Beschreibung der Regel *</label>
                <textarea name="regel" id="regel" rows="3" class="form-control" required
                    placeholder="z.B. Jede Zahl wird verdoppelt"></textarea>
            </div>
        </div>
    </div>

    <!-- Inhaltsbereich -->
    <div class="form-section">
        <h3>Inhaltsbereich</h3>
        <div class="form-group">
            <label>Welcher Inhaltsbereich steht im Vordergrund? *</label>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="inhalt" value="1" required onchange="updateSecondaryContent()">
                    <span>Verbal/Sprachlich</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="inhalt" value="2" required onchange="updateSecondaryContent()">
                    <span>Numerisch/Rechnerisch</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="inhalt" value="3" required onchange="updateSecondaryContent()">
                    <span>Figural/Räumlich-Visuell</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="inhalt" value="4" required onchange="updateSecondaryContent()">
                    <span>Etwas anderes:</span>
                    <input type="text" name="tinhalt" id="tinhalt" class="inline-input" placeholder="beschreiben">
                </label>
            </div>
        </div>

        <div class="form-group mt-20">
            <label>Gibt es weitere wichtige Inhaltsbereiche?</label>
            <div class="radio-group" id="secondaryContent">
                <label class="radio-label">
                    <input type="radio" name="inhaltw" value="0" checked>
                    <span>Nein, nur der obige</span>
                </label>
                <label class="radio-label secondary-option" data-value="1">
                    <input type="radio" name="inhaltw" value="1">
                    <span>Verbal/Sprachlich</span>
                </label>
                <label class="radio-label secondary-option" data-value="2">
                    <input type="radio" name="inhaltw" value="2">
                    <span>Numerisch/Rechnerisch</span>
                </label>
                <label class="radio-label secondary-option" data-value="3">
                    <input type="radio" name="inhaltw" value="3">
                    <span>Figural/Räumlich-Visuell</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="inhaltw" value="4">
                    <span>Etwas anderes:</span>
                    <input type="text" name="tinhaltw" id="tinhaltw" class="inline-input" placeholder="beschreiben">
                </label>
            </div>
        </div>
    </div>

    <!-- Schwierigkeit -->
    <div class="form-section">
        <h3>Schwierigkeit</h3>
        <div class="form-group">
            <label>Wie schätzt Du die Schwierigkeit ein? *</label>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="schwer" value="1" required>
                    <span>Sehr niedrig</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="schwer" value="2" required>
                    <span>Eher niedrig</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="schwer" value="3" required>
                    <span>Mittel</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="schwer" value="4" required>
                    <span>Eher hoch</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="schwer" value="5" required>
                    <span>Sehr hoch</span>
                </label>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <p class="help-text">Datenschutz: Es werden nur diese Angaben gespeichert, dein Eintrag bleibt anonym</p>
        <button type="submit" class="btn btn-primary btn-large">Absenden und speichern</button>
    </div>
</form>

<script>
// Toggle zwischen Text und Figural
function toggleFiguralMode(isFigural) {
    const textFields = document.querySelectorAll('.text-field');
    const figuralFields = document.querySelectorAll('.figural-field');
    const figuralUploads = document.querySelectorAll('.figural-upload');

    textFields.forEach(field => {
        field.style.display = isFigural ? 'none' : 'block';
        const input = field.querySelector('input, textarea');
        if (input) input.required = !isFigural;
    });

    figuralFields.forEach(field => {
        field.style.display = isFigural ? 'block' : 'none';
    });

    // Bei figuralen Aufgaben: Entweder file0 ODER alle files1-5
    if (isFigural) {
        document.getElementById('file0').addEventListener('change', function() {
            if (this.files.length > 0) {
                figuralUploads.forEach(upload => upload.required = false);
            }
        });

        figuralUploads.forEach(upload => {
            upload.addEventListener('change', function() {
                const allFilled = Array.from(figuralUploads).every(u => u.files.length > 0);
                if (allFilled) {
                    document.getElementById('file0').required = false;
                }
            });
        });
    }
}

// Secondary Content basierend auf Primary verstecken
function updateSecondaryContent() {
    const primary = document.querySelector('input[name="inhalt"]:checked');
    if (!primary) return;

    const primaryValue = primary.value;
    const secondaryOptions = document.querySelectorAll('.secondary-option');

    secondaryOptions.forEach(option => {
        if (option.dataset.value === primaryValue) {
            option.style.display = 'none';
            option.querySelector('input').checked = false;
        } else {
            option.style.display = 'block';
        }
    });
}

// Initial ausführen
document.addEventListener('DOMContentLoaded', () => {
    updateSecondaryContent();
});
</script>
