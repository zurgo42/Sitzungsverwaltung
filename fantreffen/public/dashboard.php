<?php
/**
 * Dashboard - Anmeldung für eine bestimmte Reise
 * Einfaches Formular: Kabine, E-Mail, 4 Teilnehmer
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Reise.php';
require_once __DIR__ . '/../src/MailService.php';

$session = new Session();
$db = Database::getInstance();
$reiseModel = new Reise($db);
$mailService = new MailService($db);

// Reise-ID aus URL
$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('Location: index.php');
    exit;
}

// Login erforderlich - sonst weiter zu Login mit Redirect
if (!$session->isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('dashboard.php?id=' . $reiseId));
    exit;
}

$currentUser = $session->getUser();
$userId = $currentUser['user_id'];
$userEmail = $currentUser['email'];

$fehler = '';
$erfolg = '';

// Bestehende Anmeldung und Teilnehmer laden
$anmeldung = $db->fetchOne(
    "SELECT * FROM fan_anmeldungen WHERE user_id = ? AND reise_id = ?",
    [$userId, $reiseId]
);

// Teilnehmer des Users laden
$teilnehmer = $db->fetchAll(
    "SELECT * FROM fan_teilnehmer WHERE user_id = ? ORDER BY position ASC",
    [$userId]
);

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'speichern') {
            $kabine = trim($_POST['kabine'] ?? '');

            // Teilnehmer verarbeiten (4 Stück)
            $teilnehmerDaten = [];

            for ($i = 1; $i <= 4; $i++) {
                $vorname = trim($_POST["vorname_$i"] ?? '');
                $name = trim($_POST["name_$i"] ?? '');
                $nickname = trim($_POST["nickname_$i"] ?? '');
                $mobil = trim($_POST["mobil_$i"] ?? '');

                if (!empty($vorname) && !empty($name)) {
                    $teilnehmerDaten[] = [
                        'position' => $i,
                        'vorname' => $vorname,
                        'name' => $name,
                        'nickname' => $nickname ?: null,
                        'mobil' => $mobil ?: null
                    ];
                }
            }

            if (empty($teilnehmerDaten)) {
                $fehler = 'Bitte mindestens einen Teilnehmer angeben.';
            } else {
                try {
                    $db->beginTransaction();

                    // Alte Teilnehmer löschen und neu anlegen
                    $db->delete('fan_teilnehmer', 'user_id = ?', [$userId]);

                    // Teilnehmer-IDs nach Position (1-4) sammeln
                    $teilnehmerNachPosition = [1 => null, 2 => null, 3 => null, 4 => null];

                    foreach ($teilnehmerDaten as $t) {
                        $db->insert('fan_teilnehmer', [
                            'user_id' => $userId,
                            'position' => $t['position'],
                            'vorname' => $t['vorname'],
                            'name' => $t['name'],
                            'nickname' => $t['nickname'],
                            'mobil' => $t['mobil']
                        ]);
                        $teilnehmerNachPosition[$t['position']] = (int)$db->getPdo()->lastInsertId();
                    }

                    // Anmeldung erstellen oder aktualisieren
                    $anmeldungDaten = [
                        'kabine' => $kabine ?: null,
                        'teilnehmer1_id' => $teilnehmerNachPosition[1],
                        'teilnehmer2_id' => $teilnehmerNachPosition[2],
                        'teilnehmer3_id' => $teilnehmerNachPosition[3],
                        'teilnehmer4_id' => $teilnehmerNachPosition[4]
                    ];

                    $isNewAnmeldung = false;
                    if ($anmeldung) {
                        $db->update('fan_anmeldungen', $anmeldungDaten,
                            'anmeldung_id = ?', [$anmeldung['anmeldung_id']]);
                    } else {
                        $anmeldungDaten['user_id'] = $userId;
                        $anmeldungDaten['reise_id'] = $reiseId;
                        $db->insert('fan_anmeldungen', $anmeldungDaten);
                        $isNewAnmeldung = true;
                    }

                    $db->commit();

                    // Bei Neuanmeldung Bestätigungs-Mail senden (wenn aktiviert)
                    $mailSent = false;
                    if ($isNewAnmeldung && defined('MAIL_SEND_ANMELDEBESTAETIGUNG') && MAIL_SEND_ANMELDEBESTAETIGUNG) {
                        $mailSent = $mailService->sendAnmeldebestaetigung($userId, $reiseId);
                    }

                    // Zurück zur Startseite
                    if ($isNewAnmeldung) {
                        $msg = 'Deine Anmeldung wurde gespeichert!';
                        if ($mailSent) {
                            $msg .= ' Du erhältst eine Bestätigung per E-Mail.';
                        }
                        Session::success($msg);
                    } else {
                        Session::success('Deine Anmeldung wurde aktualisiert!');
                    }
                    header('Location: index.php');
                    exit;

                } catch (Exception $e) {
                    $db->rollback();
                    $fehler = 'Fehler beim Speichern: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'abmelden') {
            if ($anmeldung) {
                $db->delete('fan_anmeldungen', 'anmeldung_id = ?', [$anmeldung['anmeldung_id']]);
                Session::success('Du hast dich erfolgreich abgemeldet.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

// Teilnehmer für Formular vorbereiten (4 Slots)
$formTeilnehmer = array_fill(1, 4, ['vorname' => '', 'name' => '', 'nickname' => '', 'mobil' => '']);
foreach ($teilnehmer as $t) {
    $pos = $t['position'] ?? 1;
    if ($pos >= 1 && $pos <= 4) {
        $formTeilnehmer[$pos] = [
            'vorname' => $t['vorname'] ?? '',
            'name' => $t['name'] ?? '',
            'nickname' => $t['nickname'] ?? '',
            'mobil' => $t['mobil'] ?? ''
        ];
    }
}

$csrfToken = $session->getCsrfToken();
$reise = $reiseModel->formatForDisplay($reise);
$pageTitle = 'Anmeldung: ' . $reise['schiff'];

// Yahoo-Warnung prüfen
$userEmail = strtolower($currentUser['email'] ?? '');
$isYahooUser = (strpos($userEmail, 'yahoo') !== false) || (strpos($userEmail, 'aol') !== false);

include __DIR__ . '/../templates/header.php';
?>

<?php if ($isYahooUser): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <strong>⚠ Hinweis zu deiner E-Mail-Adresse:</strong><br>
    Du bist mit einer Yahoo/AOL-Adresse angemeldet. Leider blockiert Yahoo häufig E-Mails von Webseiten wie dieser.
    Es kann sein, dass du <strong>keine Benachrichtigungen</strong> zum Fantreffen erhältst.<br>
    <small class="text-muted">Falls möglich, hinterlege bitte eine alternative E-Mail-Adresse (z.B. GMX, Web.de, Gmail).</small>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <!-- Reise-Info -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <img src="<?= htmlspecialchars($reiseModel->getSchiffBild($reise['schiff'])) ?>"
                             class="img-fluid rounded" alt="<?= htmlspecialchars($reise['schiff']) ?>">
                    </div>
                    <div class="col-md-9">
                        <h2 class="mb-2"><?= htmlspecialchars($reise['schiff']) ?></h2>
                        <p class="mb-1">
                            📅 <?= $reise['anfang_formatiert'] ?> - <?= $reise['ende_formatiert'] ?>
                            (<?= $reise['dauer_tage'] ?> Tage)
                        </p>
                        <?php if ($reise['bahnhof']): ?>
                            <p class="mb-0 text-muted">
                                📍 ab <?= htmlspecialchars($reise['bahnhof']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($fehler): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>

        <!-- Anmeldeformular -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <?= $anmeldung ? 'Deine Anmeldung bearbeiten' : 'Zum Fantreffen anmelden' ?>
                </h4>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="speichern">

                    <!-- Kabine und E-Mail -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="kabine" class="form-label">Kabinennummer</label>
                            <input type="text" class="form-control" id="kabine" name="kabine"
                                   value="<?= htmlspecialchars($anmeldung['kabine'] ?? '') ?>"
                                   placeholder="z.B. 8123">
                            <div class="form-text">Falls schon bekannt</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-Mail-Adresse</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($userEmail) ?>" disabled>
                            <div class="form-text">Kann nicht geändert werden</div>
                        </div>
                    </div>

                    <hr>

                    <h5 class="mb-3">Teilnehmer am Fantreffen</h5>
                    <p class="text-muted small mb-3">
                        ℹ Die Mobilnummer wird <strong>nicht veröffentlicht</strong> und ist nur für die Organisatoren sichtbar.
                    </p>

                    <!-- 4 Teilnehmer-Felder -->
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="row mb-3 pb-3 <?= $i < 4 ? 'border-bottom' : '' ?>">
                            <div class="col-12 mb-2">
                                <strong>Teilnehmer <?= $i ?><?= $i === 1 ? ' (du selbst)' : '' ?></strong>
                            </div>
                            <div class="col-md-3">
                                <label for="vorname_<?= $i ?>" class="form-label">Vorname <?= $i === 1 ? '*' : '' ?></label>
                                <input type="text" class="form-control" id="vorname_<?= $i ?>" name="vorname_<?= $i ?>"
                                       value="<?= htmlspecialchars($formTeilnehmer[$i]['vorname']) ?>"
                                       <?= $i === 1 ? 'required' : '' ?>>
                            </div>
                            <div class="col-md-3">
                                <label for="name_<?= $i ?>" class="form-label">Nachname <?= $i === 1 ? '*' : '' ?></label>
                                <input type="text" class="form-control" id="name_<?= $i ?>" name="name_<?= $i ?>"
                                       value="<?= htmlspecialchars($formTeilnehmer[$i]['name']) ?>"
                                       <?= $i === 1 ? 'required' : '' ?>>
                            </div>
                            <div class="col-md-3">
                                <label for="nickname_<?= $i ?>" class="form-label">Nickname</label>
                                <input type="text" class="form-control" id="nickname_<?= $i ?>" name="nickname_<?= $i ?>"
                                       value="<?= htmlspecialchars($formTeilnehmer[$i]['nickname']) ?>"
                                       placeholder="z.B. Forenname">
                            </div>
                            <div class="col-md-3">
                                <label for="mobil_<?= $i ?>" class="form-label">Mobil</label>
                                <input type="tel" class="form-control" id="mobil_<?= $i ?>" name="mobil_<?= $i ?>"
                                       value="<?= htmlspecialchars($formTeilnehmer[$i]['mobil']) ?>">
                            </div>
                        </div>
                    <?php endfor; ?>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            ✓ <?= $anmeldung ? 'Änderungen speichern' : 'Anmelden' ?>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Abbrechen</a>
                    </div>
                </form>

                <?php if ($anmeldung): ?>
                    <hr>
                    <form method="post" onsubmit="return confirm('Wirklich abmelden?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="abmelden">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            ✕ Von dieser Reise abmelden
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
