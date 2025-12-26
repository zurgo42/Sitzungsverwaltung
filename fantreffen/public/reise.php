<?php
/**
 * Reise-Detailseite mit Anmeldefunktion
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Reise.php';
require_once __DIR__ . '/../src/Teilnehmer.php';

$session = new Session();
$db = Database::getInstance();
$reiseModel = new Reise($db);
$teilnehmerModel = new Teilnehmer($db);

$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('Location: reisen.php');
    exit;
}

$currentUser = $session->getUser();
$userId = $currentUser['user_id'] ?? null;
$fehler = '';
$erfolg = '';

// Prüfen ob User Admin dieser Reise oder Superuser ist
$isReiseAdmin = Session::isSuperuser();
if (!$isReiseAdmin && $userId) {
    $admins = $reiseModel->getAdmins($reiseId);
    foreach ($admins as $admin) {
        if ($admin['user_id'] === $userId) {
            $isReiseAdmin = true;
            break;
        }
    }
}

// Prüfen ob User bereits angemeldet
$eigeneAnmeldung = null;
$eigeneTeilnehmer = [];
if ($userId) {
    $eigeneAnmeldung = $db->fetchOne(
        "SELECT * FROM fan_anmeldungen WHERE user_id = ? AND reise_id = ?",
        [$userId, $reiseId]
    );
    $eigeneTeilnehmer = $teilnehmerModel->getByUser($userId);
}

// Anmeldung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $session->isLoggedIn()) {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'anmelden') {
            $kabine = trim($_POST['kabine'] ?? '');
            $bemerkung = trim($_POST['bemerkung'] ?? '');
            $selectedTeilnehmer = $_POST['teilnehmer'] ?? [];

            if (empty($selectedTeilnehmer)) {
                $fehler = 'Bitte wähle mindestens einen Teilnehmer aus.';
            } else {
                // Validieren dass Teilnehmer dem User gehören
                $validTeilnehmer = [];
                foreach ($selectedTeilnehmer as $tid) {
                    $t = $teilnehmerModel->findById((int)$tid);
                    if ($t && $t['user_id'] === $userId) {
                        $validTeilnehmer[] = (int)$tid;
                    }
                }

                if (empty($validTeilnehmer)) {
                    $fehler = 'Ungültige Teilnehmerauswahl.';
                } else {
                    try {
                        // Max 4 Teilnehmer in die Spalten einfügen
                        $anmeldungDaten = [
                            'user_id' => $userId,
                            'reise_id' => $reiseId,
                            'kabine' => $kabine ?: null,
                            'bemerkung' => $bemerkung ?: null,
                            'teilnehmer1_id' => $validTeilnehmer[0] ?? null,
                            'teilnehmer2_id' => $validTeilnehmer[1] ?? null,
                            'teilnehmer3_id' => $validTeilnehmer[2] ?? null,
                            'teilnehmer4_id' => $validTeilnehmer[3] ?? null
                        ];
                        $db->insert('fan_anmeldungen', $anmeldungDaten);
                        $erfolg = 'Du hast dich erfolgreich angemeldet!';
                        // Neu laden um Anmeldung anzuzeigen
                        $eigeneAnmeldung = $db->fetchOne(
                            "SELECT * FROM fan_anmeldungen WHERE user_id = ? AND reise_id = ?",
                            [$userId, $reiseId]
                        );
                    } catch (Exception $e) {
                        $fehler = 'Fehler bei der Anmeldung. Bist du bereits angemeldet?';
                    }
                }
            }
        } elseif ($action === 'aktualisieren' && $eigeneAnmeldung) {
            $kabine = trim($_POST['kabine'] ?? '');
            $bemerkung = trim($_POST['bemerkung'] ?? '');
            $selectedTeilnehmer = $_POST['teilnehmer'] ?? [];

            if (empty($selectedTeilnehmer)) {
                $fehler = 'Bitte wähle mindestens einen Teilnehmer aus.';
            } else {
                $validTeilnehmer = [];
                foreach ($selectedTeilnehmer as $tid) {
                    $t = $teilnehmerModel->findById((int)$tid);
                    if ($t && $t['user_id'] === $userId) {
                        $validTeilnehmer[] = (int)$tid;
                    }
                }

                $db->update('fan_anmeldungen', [
                    'kabine' => $kabine ?: null,
                    'bemerkung' => $bemerkung ?: null,
                    'teilnehmer1_id' => $validTeilnehmer[0] ?? null,
                    'teilnehmer2_id' => $validTeilnehmer[1] ?? null,
                    'teilnehmer3_id' => $validTeilnehmer[2] ?? null,
                    'teilnehmer4_id' => $validTeilnehmer[3] ?? null
                ], 'anmeldung_id = ?', [$eigeneAnmeldung['anmeldung_id']]);

                $erfolg = 'Deine Anmeldung wurde aktualisiert.';
                $eigeneAnmeldung = $db->fetchOne(
                    "SELECT * FROM fan_anmeldungen WHERE anmeldung_id = ?",
                    [$eigeneAnmeldung['anmeldung_id']]
                );
            }
        } elseif ($action === 'abmelden' && $eigeneAnmeldung) {
            $db->delete('fan_anmeldungen', 'anmeldung_id = ?', [$eigeneAnmeldung['anmeldung_id']]);
            $erfolg = 'Du hast dich erfolgreich abgemeldet.';
            $eigeneAnmeldung = null;
        }
    }
}

// Alle Anmeldungen für diese Reise laden (mit Details für Admin und für Abkürzung)
$anmeldungen = $db->fetchAll(
    "SELECT a.*, u.email,
            GROUP_CONCAT(
                CONCAT_WS('|', t.vorname, t.name, COALESCE(t.nickname, ''))
                SEPARATOR '||'
            ) AS teilnehmer_detail,
            (CASE WHEN a.teilnehmer1_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN a.teilnehmer2_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN a.teilnehmer3_id IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN a.teilnehmer4_id IS NOT NULL THEN 1 ELSE 0 END) AS anzahl_teilnehmer
     FROM fan_anmeldungen a
     JOIN fan_users u ON a.user_id = u.user_id
     LEFT JOIN fan_teilnehmer t ON t.teilnehmer_id IN (
         a.teilnehmer1_id, a.teilnehmer2_id, a.teilnehmer3_id, a.teilnehmer4_id
     )
     WHERE a.reise_id = ?
     GROUP BY a.anmeldung_id
     ORDER BY a.erstellt ASC",
    [$reiseId]
);

// Teilnehmer-Daten aufbereiten
$gesamtTeilnehmer = 0;
foreach ($anmeldungen as &$a) {
    $gesamtTeilnehmer += (int)$a['anzahl_teilnehmer'];

    // Teilnehmer parsen
    $a['teilnehmer_parsed'] = [];
    if ($a['teilnehmer_detail']) {
        $parts = explode('||', $a['teilnehmer_detail']);
        foreach ($parts as $part) {
            $fields = explode('|', $part);
            $a['teilnehmer_parsed'][] = [
                'vorname' => $fields[0] ?? '',
                'name' => $fields[1] ?? '',
                'nickname' => $fields[2] ?? ''
            ];
        }
    }
}
unset($a);

$csrfToken = $session->getCsrfToken();

$pageTitle = $reise['schiff'];
include __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="reisen.php">Reisen</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($reise['schiff']) ?></li>
                </ol>
            </nav>
            <?php if ($isReiseAdmin): ?>
                <div class="btn-group">
                    <a href="admin/reise-bearbeiten.php?id=<?= $reiseId ?>" class="btn btn-outline-primary btn-sm">
                        ✏ Bearbeiten
                    </a>
                    <a href="admin/teilnehmerliste.php?id=<?= $reiseId ?>" class="btn btn-outline-secondary btn-sm">
                        👥 Teilnehmerliste
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>

<?php if ($erfolg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Reise-Details -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <?php
                $statusClass = [
                    'geplant' => 'warning',
                    'bestaetigt' => 'success',
                    'abgesagt' => 'danger'
                ];
                $statusText = [
                    'geplant' => 'Treffen geplant',
                    'bestaetigt' => 'Treffen bestätigt',
                    'abgesagt' => 'Treffen abgesagt'
                ];
                $status = $reise['treffen_status'];
                ?>
                <span class="badge bg-<?= $statusClass[$status] ?> float-end">
                    <?= $statusText[$status] ?>
                </span>
                <h3 class="mb-0"><?= htmlspecialchars($reise['schiff']) ?></h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p>
                            <strong>Reisezeitraum:</strong><br>
                            <?= date('d.m.Y', strtotime($reise['anfang'])) ?> -
                            <?= date('d.m.Y', strtotime($reise['ende'])) ?>
                        </p>
                        <?php if ($reise['bahnhof']): ?>
                            <p>
                                <strong>Abfahrtshafen:</strong><br>
                                <?= htmlspecialchars($reise['bahnhof']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($reise['treffen_ort']): ?>
                            <p>
                                <strong>Treffpunkt:</strong><br>
                                <?= htmlspecialchars($reise['treffen_ort']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($reise['treffen_zeit']): ?>
                            <p>
                                <strong>Treffen am:</strong><br>
                                <?= date('d.m.Y H:i', strtotime($reise['treffen_zeit'])) ?> Uhr
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($reise['treffen_info']): ?>
                    <div class="alert alert-info mt-3">
                        <strong>Info zum Treffen:</strong><br>
                        <?= nl2br(htmlspecialchars($reise['treffen_info'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Links -->
                <?php if ($reise['link_wasserurlaub'] || $reise['link_facebook'] || $reise['link_kids']): ?>
                    <hr>
                    <h5>Links</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($reise['link_wasserurlaub']): ?>
                            <a href="<?= htmlspecialchars($reise['link_wasserurlaub']) ?>"
                               target="_blank" class="btn btn-outline-primary btn-sm">
                                Wasserurlaub
                            </a>
                        <?php endif; ?>
                        <?php if ($reise['link_facebook']): ?>
                            <a href="<?= htmlspecialchars($reise['link_facebook']) ?>"
                               target="_blank" class="btn btn-outline-primary btn-sm">
                                Facebook
                            </a>
                        <?php endif; ?>
                        <?php if ($reise['link_kids']): ?>
                            <a href="<?= htmlspecialchars($reise['link_kids']) ?>"
                               target="_blank" class="btn btn-outline-primary btn-sm">
                                Kids-Club
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Teilnehmerliste -->
        <?php if ($isReiseAdmin || $eigeneAnmeldung): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Teilnehmerliste (<?= $gesamtTeilnehmer ?> Personen)</h5>
                <?php if ($isReiseAdmin): ?>
                    <a href="admin/teilnehmerliste.php?id=<?= $reiseId ?>" class="btn btn-sm btn-outline-secondary">
                        ☰ Vollständige Liste
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($anmeldungen)): ?>
                    <p class="text-muted">Noch keine Anmeldungen vorhanden.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Teilnehmer</th>
                                    <th>Kabine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anmeldungen as $a):
                                    // Eigene Anmeldung gelb hervorheben
                                    $isOwn = $eigeneAnmeldung && $a['anmeldung_id'] === $eigeneAnmeldung['anmeldung_id'];
                                    $rowClass = $isOwn ? 'table-warning' : '';

                                    // Teilnehmer-Anzeige formatieren
                                    $teilnehmerAnzeige = [];
                                    foreach ($a['teilnehmer_parsed'] as $t) {
                                        if ($isReiseAdmin) {
                                            // Admin sieht volle Namen
                                            $display = $t['vorname'] . ' ' . $t['name'];
                                            if ($t['nickname']) {
                                                $display .= ' (' . $t['nickname'] . ')';
                                            }
                                        } else {
                                            // Normale User sehen abgekürzte Form: "H... M..."
                                            $display = mb_substr($t['vorname'], 0, 1) . '... ' . mb_substr($t['name'], 0, 1) . '...';
                                        }
                                        $teilnehmerAnzeige[] = $display;
                                    }

                                    // Kabine abkürzen für normale User
                                    $kabineAnzeige = $a['kabine'] ?: '-';
                                    if (!$isReiseAdmin && $a['kabine']) {
                                        $kabineAnzeige = mb_substr($a['kabine'], 0, 1) . '...';
                                    }
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td>
                                            <?= htmlspecialchars(implode(', ', $teilnehmerAnzeige) ?: '-') ?>
                                            <?php if ($isOwn): ?>
                                                <span class="badge bg-secondary ms-1">Du</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>Kabine <?= htmlspecialchars($kabineAnzeige) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Anmeldung -->
    <div class="col-lg-4">
        <?php if (!$session->isLoggedIn()): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Anmelden</h5>
                </div>
                <div class="card-body">
                    <p>Um dich für diese Reise anzumelden, musst du eingeloggt sein.</p>
                    <a href="login.php?redirect=reise.php?id=<?= $reiseId ?>" class="btn btn-primary">
                        Jetzt einloggen
                    </a>
                    <hr>
                    <p class="small">Noch kein Konto?</p>
                    <a href="registrieren.php" class="btn btn-outline-secondary btn-sm">
                        Registrieren
                    </a>
                </div>
            </div>
        <?php elseif (empty($eigeneTeilnehmer)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Anmelden</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        Du musst zuerst Teilnehmer in deinem Profil anlegen, bevor du dich anmelden kannst.
                    </div>
                    <a href="profil.php" class="btn btn-primary">
                        Zum Profil
                    </a>
                </div>
            </div>
        <?php elseif ($eigeneAnmeldung): ?>
            <!-- Bereits angemeldet - Bearbeiten -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Du bist angemeldet!</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="aktualisieren">

                        <div class="mb-3">
                            <label class="form-label">Teilnehmer</label>
                            <?php
                            $selectedIds = array_filter([
                                $eigeneAnmeldung['teilnehmer1_id'] ?? null,
                                $eigeneAnmeldung['teilnehmer2_id'] ?? null,
                                $eigeneAnmeldung['teilnehmer3_id'] ?? null,
                                $eigeneAnmeldung['teilnehmer4_id'] ?? null
                            ]);
                            foreach ($eigeneTeilnehmer as $t):
                                $checked = in_array($t['teilnehmer_id'], $selectedIds) ? 'checked' : '';
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="teilnehmer[]" value="<?= $t['teilnehmer_id'] ?>"
                                           id="t<?= $t['teilnehmer_id'] ?>" <?= $checked ?>>
                                    <label class="form-check-label" for="t<?= $t['teilnehmer_id'] ?>">
                                        <?= htmlspecialchars($teilnehmerModel->formatForDisplay($t)) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mb-3">
                            <label for="kabine" class="form-label">Kabinennummer</label>
                            <input type="text" class="form-control" id="kabine" name="kabine"
                                   value="<?= htmlspecialchars($eigeneAnmeldung['kabine'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="bemerkung" class="form-label">Bemerkung</label>
                            <textarea class="form-control" id="bemerkung" name="bemerkung"
                                      rows="2"><?= htmlspecialchars($eigeneAnmeldung['bemerkung'] ?? '') ?></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Aktualisieren</button>
                        </div>
                    </form>

                    <hr>

                    <form method="post" onsubmit="return confirm('Wirklich abmelden?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="abmelden">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                Von Reise abmelden
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Neue Anmeldung -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Jetzt anmelden</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="anmelden">

                        <div class="mb-3">
                            <label class="form-label">Wer fährt mit? *</label>
                            <?php foreach ($eigeneTeilnehmer as $t): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="teilnehmer[]" value="<?= $t['teilnehmer_id'] ?>"
                                           id="t<?= $t['teilnehmer_id'] ?>">
                                    <label class="form-check-label" for="t<?= $t['teilnehmer_id'] ?>">
                                        <?= htmlspecialchars($teilnehmerModel->formatForDisplay($t)) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mb-3">
                            <label for="kabine" class="form-label">Kabinennummer</label>
                            <input type="text" class="form-control" id="kabine" name="kabine"
                                   placeholder="z.B. 8123">
                            <div class="form-text">Falls schon bekannt</div>
                        </div>

                        <div class="mb-3">
                            <label for="bemerkung" class="form-label">Bemerkung</label>
                            <textarea class="form-control" id="bemerkung" name="bemerkung"
                                      rows="2" placeholder="Optionale Nachricht"></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                Verbindlich anmelden
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
