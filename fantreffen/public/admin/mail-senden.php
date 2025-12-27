<?php
/**
 * Admin: Mails an Teilnehmer senden
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

// Login-Link generieren
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'example.com';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$loginLink = $protocol . '://' . $host . $basePath . '/login.php';

// Mail-Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'treffen_bestaetigt':
                $db->update('fan_reisen', [
                    'treffen_status' => 'bestaetigt'
                ], 'reise_id = ?', [$reiseId]);

                $count = $mailService->sendTreffenBestaetigt($reiseId);
                $erfolg = "$count Teilnehmer wurden per E-Mail informiert.";
                $reise = $reiseModel->findById($reiseId);
                break;

            case 'kabine_fehlt':
                $count = $mailService->sendKabineFehlt($reiseId);
                $erfolg = "$count Teilnehmer ohne Kabinennummer wurden erinnert.";
                break;

            case 'custom':
                $betreff = trim($_POST['betreff'] ?? '');
                $inhalt = trim($_POST['inhalt'] ?? '');
                $selectedUsers = $_POST['selected_users'] ?? [];

                if (empty($betreff) || empty($inhalt)) {
                    $fehler = 'Betreff und Inhalt sind erforderlich.';
                } elseif (empty($selectedUsers)) {
                    $fehler = 'Bitte mindestens einen Empfänger auswählen.';
                } else {
                    // Nur ausgewählte Teilnehmer laden
                    $placeholders = implode(',', array_fill(0, count($selectedUsers), '?'));
                    $params = array_merge([$reiseId], $selectedUsers);

                    $anmeldungen = $db->fetchAll(
                        "SELECT a.*, u.email, u.user_id, t.vorname, t.name
                         FROM fan_anmeldungen a
                         JOIN fan_users u ON a.user_id = u.user_id
                         LEFT JOIN fan_teilnehmer t ON t.teilnehmer_id = a.teilnehmer1_id
                         WHERE a.reise_id = ? AND u.user_id IN ($placeholders)",
                        $params
                    );

                    // Platzhalter-Werte für alle Mails
                    $treffenOrt = $reise['treffen_ort'] ?? 'wird noch bekannt gegeben';
                    $treffenZeit = $reise['treffen_zeit']
                        ? date('d.m.Y H:i', strtotime($reise['treffen_zeit'])) . ' Uhr'
                        : 'wird noch bekannt gegeben';

                    $count = 0;
                    foreach ($anmeldungen as $a) {
                        // Alle Platzhalter ersetzen
                        $replacements = [
                            '{vorname}' => $a['vorname'] ?? '',
                            '{name}' => $a['name'] ?? '',
                            '{schiff}' => $reise['schiff'],
                            '{kabine}' => $a['kabine'] ?? '-',
                            '{anfang}' => date('d.m.Y', strtotime($reise['anfang'])),
                            '{ende}' => date('d.m.Y', strtotime($reise['ende'])),
                            '{treffen_ort}' => $treffenOrt,
                            '{treffen_zeit}' => $treffenZeit,
                            '{login_link}' => $loginLink,
                            '{email}' => $a['email']
                        ];

                        $personalizedSubject = str_replace(
                            array_keys($replacements),
                            array_values($replacements),
                            $betreff
                        );
                        $personalizedContent = str_replace(
                            array_keys($replacements),
                            array_values($replacements),
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

            case 'process_queue':
                $maxMails = (int)($_POST['max_mails'] ?? 10);
                $stats = $mailService->processQueue([
                    'gmx' => $maxMails,
                    'web' => $maxMails,
                    't-online' => $maxMails,
                    'yahoo' => $maxMails,
                    'default' => $maxMails
                ]);
                $erfolg = "Queue verarbeitet: {$stats['sent']} gesendet, {$stats['failed']} fehlgeschlagen, {$stats['skipped']} übersprungen.";
                break;
        }
    }
}

// Teilnehmerliste laden (alphabetisch nach Nachname)
$teilnehmerListe = $db->fetchAll(
    "SELECT a.anmeldung_id, a.kabine, u.user_id, u.email, t.vorname, t.name
     FROM fan_anmeldungen a
     JOIN fan_users u ON a.user_id = u.user_id
     LEFT JOIN fan_teilnehmer t ON t.teilnehmer_id = a.teilnehmer1_id
     WHERE a.reise_id = ?
     ORDER BY COALESCE(t.name, u.email) ASC, COALESCE(t.vorname, '') ASC",
    [$reiseId]
);

// Statistiken
$anmeldungCount = count($teilnehmerListe);
$ohneKabine = $db->fetchColumn(
    "SELECT COUNT(*) FROM fan_anmeldungen WHERE reise_id = ? AND (kabine IS NULL OR kabine = '')",
    [$reiseId]
);

try {
    $pendingMails = $db->fetchColumn(
        "SELECT COUNT(*) FROM fan_mail_queue WHERE reise_id = ? AND gesendet IS NULL",
        [$reiseId]
    );
    $pendingMailsTotal = $db->fetchColumn(
        "SELECT COUNT(*) FROM fan_mail_queue WHERE gesendet IS NULL"
    );
} catch (Exception $e) {
    $pendingMails = 0;
    $pendingMailsTotal = $db->fetchColumn(
        "SELECT COUNT(*) FROM fan_mail_queue WHERE gesendet IS NULL"
    );
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

        <h1>📧 Mails an Teilnehmer</h1>

        <?php if ($fehler): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>
        <?php if ($erfolg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Statistik & Navigation -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><?= htmlspecialchars($reise['schiff']) ?></h5>
            </div>
            <div class="card-body">
                <p><?= $reise['anfang_formatiert'] ?> - <?= $reise['ende_formatiert'] ?></p>
                <p>
                    <strong>Status:</strong>
                    <?php
                    $statusBadge = [
                        'geplant' => 'warning',
                        'angemeldet' => 'info',
                        'bestaetigt' => 'success',
                        'abgesagt' => 'danger'
                    ];
                    ?>
                    <span class="badge bg-<?= $statusBadge[$reise['treffen_status']] ?? 'secondary' ?>">
                        <?= ucfirst($reise['treffen_status']) ?>
                    </span>
                </p>
                <hr>
                <p><strong><?= $anmeldungCount ?></strong> Anmeldungen</p>
                <p><strong><?= $ohneKabine ?></strong> ohne Kabinennummer</p>
                <p><strong><?= $pendingMails ?></strong> Mails in Warteschlange</p>
            </div>
        </div>

        <a href="reise-bearbeiten.php?id=<?= $reiseId ?>" class="btn btn-outline-secondary w-100 mb-3">
            ← Zurück zur Reise
        </a>

        <?php if (Session::isSuperuser()): ?>
        <a href="mail-vorlagen.php" class="btn btn-outline-secondary w-100">
            ⚙ Mail-Vorlagen bearbeiten
        </a>
        <?php endif; ?>
    </div>

    <!-- Mail-Aktionen -->
    <div class="col-md-8">
        <!-- Schnellaktionen -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Schnellaktionen</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6>Treffen bestätigt</h6>
                                <p class="small text-muted">
                                    Setzt Status auf "bestätigt" und informiert alle Teilnehmer.
                                </p>
                                <form method="post" onsubmit="return confirm('Alle <?= $anmeldungCount ?> Teilnehmer informieren?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="treffen_bestaetigt">
                                    <button type="submit" class="btn btn-success w-100"
                                            <?= $reise['treffen_status'] === 'bestaetigt' ? 'disabled' : '' ?>>
                                        ✓ Treffen bestätigen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6>Kabine fehlt</h6>
                                <p class="small text-muted">
                                    Erinnert <?= $ohneKabine ?> Teilnehmer ohne Kabinennummer.
                                </p>
                                <form method="post" onsubmit="return confirm('<?= $ohneKabine ?> Teilnehmer erinnern?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="kabine_fehlt">
                                    <button type="submit" class="btn btn-warning w-100"
                                            <?= $ohneKabine == 0 ? 'disabled' : '' ?>>
                                        ⚠ Erinnerung senden
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Eigene Mail mit Empfängerauswahl -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Eigene Nachricht an ausgewählte Teilnehmer</h5>
            </div>
            <div class="card-body">
                <form method="post" id="customMailForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="custom">

                    <!-- Empfängerliste -->
                    <div class="mb-3">
                        <label class="form-label">Empfänger auswählen</label>
                        <div class="mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll(true)">
                                Alle auswählen
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll(false)">
                                Alle abwählen
                            </button>
                            <span class="ms-2 text-muted" id="selectedCount"><?= $anmeldungCount ?> ausgewählt</span>
                        </div>
                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($teilnehmerListe as $t): ?>
                                <div class="form-check">
                                    <input class="form-check-input recipient-checkbox" type="checkbox"
                                           name="selected_users[]" value="<?= $t['user_id'] ?>"
                                           id="user_<?= $t['user_id'] ?>" checked
                                           onchange="updateCount()">
                                    <label class="form-check-label" for="user_<?= $t['user_id'] ?>">
                                        <?php if ($t['name']): ?>
                                            <strong><?= htmlspecialchars($t['name']) ?></strong><?php if ($t['vorname']): ?>, <?= htmlspecialchars($t['vorname']) ?><?php endif; ?>
                                        <?php else: ?>
                                            <em><?= htmlspecialchars($t['email']) ?></em>
                                        <?php endif; ?>
                                        <small class="text-muted">(<?= htmlspecialchars($t['email']) ?>)</small>
                                        <?php if (empty($t['kabine'])): ?>
                                            <span class="badge bg-warning text-dark">ohne Kabine</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Betreff</label>
                        <input type="text" name="betreff" class="form-control"
                               placeholder="z.B. Wichtige Info zum Fantreffen auf der {schiff}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nachricht</label>
                        <textarea name="inhalt" class="form-control" rows="8"
                                  placeholder="Hallo {vorname},&#10;&#10;hier eine wichtige Information...&#10;&#10;Zugang: {login_link}"></textarea>
                    </div>

                    <div class="mb-3">
                        <details>
                            <summary class="text-muted small" style="cursor: pointer;">Verfügbare Platzhalter anzeigen</summary>
                            <div class="mt-2 small">
                                <code>{vorname}</code> - Vorname des Empfängers<br>
                                <code>{name}</code> - Nachname des Empfängers<br>
                                <code>{email}</code> - E-Mail-Adresse<br>
                                <code>{schiff}</code> - Schiffsname<br>
                                <code>{anfang}</code> - Reisebeginn<br>
                                <code>{ende}</code> - Reiseende<br>
                                <code>{kabine}</code> - Kabinennummer<br>
                                <code>{treffen_ort}</code> - Treffpunkt<br>
                                <code>{treffen_zeit}</code> - Treffzeit<br>
                                <code>{login_link}</code> - Link zur Anmeldeseite
                            </div>
                        </details>
                    </div>

                    <button type="submit" class="btn btn-primary" onclick="return confirmSend()">
                        📧 Nachricht senden
                    </button>
                </form>
            </div>
        </div>

        <!-- Mail-Queue -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">📬 Mail-Warteschlange</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="mb-1"><strong><?= $pendingMails ?></strong> Mails für diese Reise</p>
                        <p class="mb-0 text-muted"><strong><?= $pendingMailsTotal ?></strong> Mails insgesamt</p>
                    </div>
                    <div class="col-md-6">
                        <form method="post" class="d-flex gap-2 align-items-center justify-content-end">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="process_queue">
                            <label class="form-label mb-0 me-2">Max:</label>
                            <select name="max_mails" class="form-select form-select-sm" style="width: auto;">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                            <button type="submit" class="btn btn-info" <?= $pendingMailsTotal == 0 ? 'disabled' : '' ?>>
                                ▶ Abarbeiten
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectAll(checked) {
    document.querySelectorAll('.recipient-checkbox').forEach(cb => cb.checked = checked);
    updateCount();
}

function updateCount() {
    const count = document.querySelectorAll('.recipient-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count + ' ausgewählt';
}

function confirmSend() {
    const count = document.querySelectorAll('.recipient-checkbox:checked').length;
    if (count === 0) {
        alert('Bitte mindestens einen Empfänger auswählen.');
        return false;
    }
    return confirm('E-Mail an ' + count + ' Empfänger senden?');
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
