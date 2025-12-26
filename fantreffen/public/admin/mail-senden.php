<?php
/**
 * Admin: Massen-Mails an Teilnehmer senden
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';
require_once __DIR__ . '/../../src/MailService.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$reiseModel = new Reise($db);
$mailService = new MailService($db);

$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('Location: ../index.php');
    exit;
}

$currentUser = $session->getUser();

// Berechtigung prüfen
$isAdmin = Session::isSuperuser() || $reiseModel->isReiseAdmin($reiseId, $currentUser['user_id']);
if (!$isAdmin) {
    header('Location: ../index.php');
    exit;
}

$fehler = '';
$erfolg = '';

// Mail-Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'treffen_bestaetigt':
                // Treffen als bestätigt markieren und Mails senden
                $db->update('fan_reisen', [
                    'treffen_status' => 'bestaetigt'
                ], 'reise_id = ?', [$reiseId]);

                $count = $mailService->sendTreffenBestaetigt($reiseId);
                $erfolg = "$count Teilnehmer wurden per E-Mail informiert.";
                $reise = $reiseModel->findById($reiseId); // Neu laden
                break;

            case 'kabine_fehlt':
                $count = $mailService->sendKabineFehlt($reiseId);
                $erfolg = "$count Teilnehmer ohne Kabinennummer wurden erinnert.";
                break;

            case 'custom':
                // Eigene Mail an alle
                $betreff = trim($_POST['betreff'] ?? '');
                $inhalt = trim($_POST['inhalt'] ?? '');

                if (empty($betreff) || empty($inhalt)) {
                    $fehler = 'Betreff und Inhalt sind erforderlich.';
                } else {
                    $anmeldungen = $db->fetchAll(
                        "SELECT a.*, u.email, t.vorname, t.name
                         FROM fan_anmeldungen a
                         JOIN fan_users u ON a.user_id = u.user_id
                         LEFT JOIN fan_teilnehmer t ON t.teilnehmer_id = a.teilnehmer1_id
                         WHERE a.reise_id = ?",
                        [$reiseId]
                    );

                    $count = 0;
                    foreach ($anmeldungen as $a) {
                        $personalizedSubject = str_replace(
                            ['{vorname}', '{name}', '{schiff}'],
                            [$a['vorname'] ?? '', $a['name'] ?? '', $reise['schiff']],
                            $betreff
                        );
                        $personalizedContent = str_replace(
                            ['{vorname}', '{name}', '{schiff}', '{kabine}'],
                            [$a['vorname'] ?? '', $a['name'] ?? '', $reise['schiff'], $a['kabine'] ?? '-'],
                            $inhalt
                        );

                        $htmlContent = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6;">'
                            . nl2br(htmlspecialchars($personalizedContent))
                            . '</body></html>';

                        $mailService->queueMail(
                            $a['email'],
                            $personalizedSubject,
                            $htmlContent,
                            $personalizedContent,
                            $reiseId,
                            null,
                            5
                        );
                        $count++;
                    }

                    $erfolg = "$count E-Mails wurden in die Warteschlange gestellt.";
                }
                break;
        }
    }
}

// Statistiken laden
$anmeldungCount = $db->fetchColumn(
    "SELECT COUNT(*) FROM fan_anmeldungen WHERE reise_id = ?",
    [$reiseId]
);
$ohneKabine = $db->fetchColumn(
    "SELECT COUNT(*) FROM fan_anmeldungen WHERE reise_id = ? AND (kabine IS NULL OR kabine = '')",
    [$reiseId]
);

// Pending Mails - prüfen ob reise_id Spalte existiert
try {
    $pendingMails = $db->fetchColumn(
        "SELECT COUNT(*) FROM fan_mail_queue WHERE reise_id = ? AND gesendet IS NULL",
        [$reiseId]
    );
} catch (Exception $e) {
    // Spalte reise_id existiert noch nicht
    $pendingMails = 0;
}

$csrfToken = $session->getCsrfToken();
$reise = $reiseModel->formatForDisplay($reise);
$pageTitle = 'E-Mails senden - ' . $reise['schiff'];

include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Start</a></li>
                <li class="breadcrumb-item"><a href="reise-bearbeiten.php?id=<?= $reiseId ?>">
                    <?= htmlspecialchars($reise['schiff']) ?>
                </a></li>
                <li class="breadcrumb-item active">E-Mails senden</li>
            </ol>
        </nav>

        <h1>E-Mails senden</h1>

        <?php if ($fehler): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>
        <?php if ($erfolg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Statistik -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><?= htmlspecialchars($reise['schiff']) ?></h5>
            </div>
            <div class="card-body">
                <p>
                    <?= $reise['anfang_formatiert'] ?> - <?= $reise['ende_formatiert'] ?>
                </p>
                <p>
                    <strong>Status:</strong>
                    <?php
                    $statusBadge = [
                        'geplant' => 'warning',
                        'bestaetigt' => 'success',
                        'abgesagt' => 'danger'
                    ];
                    ?>
                    <span class="badge bg-<?= $statusBadge[$reise['treffen_status']] ?>">
                        <?= ucfirst($reise['treffen_status']) ?>
                    </span>
                </p>
                <hr>
                <p><strong><?= $anmeldungCount ?></strong> Anmeldungen</p>
                <p><strong><?= $ohneKabine ?></strong> ohne Kabinennummer</p>
                <p><strong><?= $pendingMails ?></strong> Mails in Warteschlange</p>
            </div>
        </div>

        <a href="reise-bearbeiten.php?id=<?= $reiseId ?>" class="btn btn-outline-secondary w-100">
            ← Zurück zur Reise
        </a>
    </div>

    <!-- Mail-Aktionen -->
    <div class="col-md-8">
        <!-- Vordefinierte Aktionen -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Schnellaktionen</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Treffen bestätigen -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6>Treffen bestätigt</h6>
                                <p class="small text-muted">
                                    Setzt Status auf "bestätigt" und informiert alle Teilnehmer
                                    über Ort und Zeit. Fehlende Kabinennummern werden angemahnt.
                                </p>
                                <form method="post" onsubmit="return confirm('Wirklich alle <?= $anmeldungCount ?> Teilnehmer informieren?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="treffen_bestaetigt">
                                    <button type="submit" class="btn btn-success w-100"
                                            <?= $reise['treffen_status'] === 'bestaetigt' ? 'disabled' : '' ?>>
                                        ✓ Treffen bestätigen & informieren
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Kabine fehlt -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6>Kabine fehlt</h6>
                                <p class="small text-muted">
                                    Sendet eine Erinnerung an alle <?= $ohneKabine ?> Teilnehmer,
                                    die noch keine Kabinennummer eingetragen haben.
                                </p>
                                <form method="post" onsubmit="return confirm('<?= $ohneKabine ?> Teilnehmer ohne Kabine erinnern?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="kabine_fehlt">
                                    <button type="submit" class="btn btn-warning w-100"
                                            <?= $ohneKabine === 0 ? 'disabled' : '' ?>>
                                        ⚠ Kabine-Erinnerung senden
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Eigene Mail -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Eigene Nachricht an alle Teilnehmer</h5>
            </div>
            <div class="card-body">
                <form method="post" onsubmit="return confirm('E-Mail an alle <?= $anmeldungCount ?> Teilnehmer senden?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="custom">

                    <div class="mb-3">
                        <label class="form-label">Betreff</label>
                        <input type="text" name="betreff" class="form-control"
                               placeholder="z.B. Wichtige Info zum Fantreffen auf der {schiff}">
                        <div class="form-text">Platzhalter: {vorname}, {name}, {schiff}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nachricht</label>
                        <textarea name="inhalt" class="form-control" rows="8"
                                  placeholder="Hallo {vorname},&#10;&#10;hier eine wichtige Information..."></textarea>
                        <div class="form-text">Platzhalter: {vorname}, {name}, {schiff}, {kabine}</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Nachricht an alle <?= $anmeldungCount ?> Teilnehmer senden
                    </button>
                </form>
            </div>
        </div>

        <!-- Mail-Vorlagen Link -->
        <?php if (Session::isSuperuser()): ?>
        <div class="mt-3 text-end">
            <a href="mail-vorlagen.php" class="btn btn-outline-secondary">
                ⚙ Mail-Vorlagen bearbeiten
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
