<?php
/**
 * Admin: Mail-Vorlagen bearbeiten
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/MailService.php';

$session = new Session();
$session->requireLogin();

// Nur Superuser
if (!Session::isSuperuser()) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance();
$mailService = new MailService($db);

$fehler = '';
$erfolg = '';

// Vorlage speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $vorlageId = (int)($_POST['vorlage_id'] ?? 0);

        if ($vorlageId) {
            $mailService->updateVorlage($vorlageId, [
                'name' => trim($_POST['name'] ?? ''),
                'beschreibung' => trim($_POST['beschreibung'] ?? ''),
                'betreff' => trim($_POST['betreff'] ?? ''),
                'inhalt_html' => $_POST['inhalt_html'] ?? '',
                'inhalt_text' => $_POST['inhalt_text'] ?? '',
                'aktiv' => isset($_POST['aktiv']) ? 1 : 0
            ]);
            $erfolg = 'Vorlage wurde gespeichert.';
        }
    }
}

// Vorlagen laden
$vorlagen = $mailService->getAllVorlagen();

// Bearbeitungs-Modus
$editVorlage = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($vorlagen as $v) {
        if ($v['vorlage_id'] === $editId) {
            $editVorlage = $v;
            break;
        }
    }
}

$csrfToken = $session->getCsrfToken();
$pageTitle = 'Mail-Vorlagen';

include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Start</a></li>
                <li class="breadcrumb-item active">Mail-Vorlagen</li>
            </ol>
        </nav>

        <h1>Mail-Vorlagen</h1>
        <p class="text-muted">Bearbeite die E-Mail-Vorlagen für automatische Benachrichtigungen.</p>

        <?php if ($fehler): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>
        <?php if ($erfolg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Vorlagen-Liste -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Vorlagen</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($vorlagen as $v): ?>
                    <a href="?edit=<?= $v['vorlage_id'] ?>"
                       class="list-group-item list-group-item-action <?= ($editVorlage && $editVorlage['vorlage_id'] === $v['vorlage_id']) ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($v['name']) ?></strong>
                                <?php if (!$v['aktiv']): ?>
                                    <span class="badge bg-secondary">inaktiv</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($v['code']) ?></small>
                            </div>
                            <span>→</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Platzhalter</h5>
            </div>
            <div class="card-body small">
                <code>{vorname}</code> - Vorname des Teilnehmers<br>
                <code>{name}</code> - Nachname<br>
                <code>{email}</code> - E-Mail-Adresse<br>
                <code>{schiff}</code> - Schiffsname<br>
                <code>{anfang}</code> - Reisebeginn<br>
                <code>{ende}</code> - Reiseende<br>
                <code>{treffen_ort}</code> - Treffpunkt<br>
                <code>{treffen_zeit}</code> - Treffzeit<br>
                <code>{kabine}</code> - Kabinennummer<br>
                <code>{login_link}</code> - Link zur Anmeldung
            </div>
        </div>
    </div>

    <!-- Bearbeiten -->
    <div class="col-md-8">
        <?php if ($editVorlage): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Vorlage bearbeiten: <?= htmlspecialchars($editVorlage['name']) ?></h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="vorlage_id" value="<?= $editVorlage['vorlage_id'] ?>">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control"
                                       value="<?= htmlspecialchars($editVorlage['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Code (nicht änderbar)</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($editVorlage['code']) ?>" disabled>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Beschreibung</label>
                            <input type="text" name="beschreibung" class="form-control"
                                   value="<?= htmlspecialchars($editVorlage['beschreibung'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Betreff</label>
                            <input type="text" name="betreff" class="form-control"
                                   value="<?= htmlspecialchars($editVorlage['betreff']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Inhalt (HTML)</label>
                            <textarea name="inhalt_html" class="form-control" rows="15" required><?= htmlspecialchars($editVorlage['inhalt_html']) ?></textarea>
                            <div class="form-text">Vollständiges HTML inkl. Styling</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Inhalt (Text-Version)</label>
                            <textarea name="inhalt_text" class="form-control" rows="8"><?= htmlspecialchars($editVorlage['inhalt_text'] ?? '') ?></textarea>
                            <div class="form-text">Wird für E-Mail-Clients ohne HTML-Unterstützung verwendet</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="aktiv" id="aktiv"
                                       <?= $editVorlage['aktiv'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="aktiv">Vorlage aktiv</label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Speichern</button>
                            <a href="?" class="btn btn-outline-secondary">Abbrechen</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Vorschau -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Vorschau</h5>
                </div>
                <div class="card-body">
                    <iframe id="preview" style="width: 100%; height: 400px; border: 1px solid #ddd;"></iframe>
                </div>
            </div>

            <script>
                // Live-Vorschau
                function updatePreview() {
                    const html = document.querySelector('textarea[name="inhalt_html"]').value;
                    const iframe = document.getElementById('preview');
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(html
                        .replace(/\{vorname\}/g, 'Max')
                        .replace(/\{name\}/g, 'Mustermann')
                        .replace(/\{email\}/g, 'max@example.de')
                        .replace(/\{schiff\}/g, 'AIDAprima')
                        .replace(/\{anfang\}/g, '01.03.2025')
                        .replace(/\{ende\}/g, '08.03.2025')
                        .replace(/\{treffen_ort\}/g, 'Theatrium Deck 5')
                        .replace(/\{treffen_zeit\}/g, '02.03.2025 17:00 Uhr')
                        .replace(/\{kabine\}/g, '8123')
                        .replace(/\{login_link\}/g, '#')
                        .replace(/\{kabine_hinweis\}/g, '')
                    );
                    doc.close();
                }
                document.querySelector('textarea[name="inhalt_html"]').addEventListener('input', updatePreview);
                updatePreview();
            </script>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <h4>Wähle eine Vorlage zum Bearbeiten</h4>
                    <p>Klicke links auf eine Vorlage, um sie zu bearbeiten.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
