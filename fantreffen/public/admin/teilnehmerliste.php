<?php
/**
 * Teilnehmerliste einer Reise (für Admins)
 * Alle Daten, sortiert nach Kabine/Nachname, bearbeitbar
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Session.php';
require_once __DIR__ . '/../../src/Reise.php';

$session = new Session();
$session->requireLogin();

$db = Database::getInstance();
$reiseModel = new Reise($db);

$reiseId = (int)($_GET['id'] ?? 0);
$reise = $reiseModel->findById($reiseId);

if (!$reise) {
    header('Location: ../index.php');
    exit;
}

$currentUser = $session->getUser();

// Berechtigung prüfen (Superuser oder Reise-Admin)
$isAdmin = Session::isSuperuser() || $reiseModel->isReiseAdmin($reiseId, $currentUser['user_id']);

if (!$isAdmin) {
    header('Location: ../index.php');
    exit;
}

$fehler = '';
$erfolg = '';

// POST-Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültiger Sicherheitstoken.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update') {
            // Teilnehmer aktualisieren
            $teilnehmerId = (int)($_POST['teilnehmer_id'] ?? 0);
            $vorname = trim($_POST['vorname'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $mobil = trim($_POST['mobil'] ?? '');
            $kabine = trim($_POST['kabine'] ?? '');

            if ($teilnehmerId && $vorname && $name) {
                // Teilnehmer aktualisieren
                $db->update('fan_teilnehmer', [
                    'vorname' => $vorname,
                    'name' => $name,
                    'nickname' => $nickname ?: null,
                    'mobil' => $mobil ?: null
                ], 'teilnehmer_id = ?', [$teilnehmerId]);

                // Kabine aktualisieren (in Anmeldung)
                $anmeldungId = (int)($_POST['anmeldung_id'] ?? 0);
                if ($anmeldungId) {
                    $db->update('fan_anmeldungen', [
                        'kabine' => $kabine ?: null
                    ], 'anmeldung_id = ?', [$anmeldungId]);
                }

                $erfolg = 'Teilnehmer wurde aktualisiert.';
            }

        } elseif ($action === 'delete') {
            // Teilnehmer löschen
            $teilnehmerId = (int)($_POST['teilnehmer_id'] ?? 0);
            $anmeldungId = (int)($_POST['anmeldung_id'] ?? 0);

            if ($teilnehmerId && $anmeldungId) {
                // Teilnehmer aus Anmeldung entfernen
                $anmeldung = $db->fetchOne(
                    "SELECT teilnehmer_ids FROM fan_anmeldungen WHERE anmeldung_id = ?",
                    [$anmeldungId]
                );

                if ($anmeldung) {
                    $ids = json_decode($anmeldung['teilnehmer_ids'] ?? '[]', true);
                    $ids = array_filter($ids, fn($id) => $id != $teilnehmerId);

                    if (empty($ids)) {
                        // Letzte Teilnehmer - ganze Anmeldung löschen
                        $db->delete('fan_anmeldungen', 'anmeldung_id = ?', [$anmeldungId]);
                        $erfolg = 'Anmeldung wurde vollständig gelöscht.';
                    } else {
                        // Nur diesen Teilnehmer entfernen
                        $db->update('fan_anmeldungen', [
                            'teilnehmer_ids' => json_encode(array_values($ids))
                        ], 'anmeldung_id = ?', [$anmeldungId]);
                        $erfolg = 'Teilnehmer wurde entfernt.';
                    }

                    // Teilnehmer-Datensatz löschen
                    $db->delete('fan_teilnehmer', 'teilnehmer_id = ?', [$teilnehmerId]);
                }
            }

        } elseif ($action === 'add') {
            // Neuen Teilnehmer hinzufügen
            $vorname = trim($_POST['vorname'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $mobil = trim($_POST['mobil'] ?? '');
            $kabine = trim($_POST['kabine'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($vorname && $name && $email) {
                try {
                    $db->beginTransaction();

                    // User finden oder erstellen
                    $user = $db->fetchOne("SELECT user_id FROM fan_users WHERE email = ?", [$email]);
                    $isNewUser = false;
                    if (!$user) {
                        // Neuen User mit Standardpasswort erstellen
                        $standardPasswort = 'aidafantreffen';
                        $db->insert('fan_users', [
                            'email' => $email,
                            'passwort_hash' => password_hash($standardPasswort, PASSWORD_DEFAULT),
                            'rolle' => 'user'
                        ]);
                        $userId = $db->getPdo()->lastInsertId();
                        $isNewUser = true;
                    } else {
                        $userId = $user['user_id'];
                    }

                    // Teilnehmer erstellen
                    $db->insert('fan_teilnehmer', [
                        'user_id' => $userId,
                        'position' => 1,
                        'vorname' => $vorname,
                        'name' => $name,
                        'nickname' => $nickname ?: null,
                        'mobil' => $mobil ?: null
                    ]);
                    $teilnehmerId = $db->getPdo()->lastInsertId();

                    // Prüfen ob User schon für diese Reise angemeldet ist
                    $existingAnmeldung = $db->fetchOne(
                        "SELECT * FROM fan_anmeldungen WHERE user_id = ? AND reise_id = ?",
                        [$userId, $reiseId]
                    );

                    if ($existingAnmeldung) {
                        // Zu bestehender Anmeldung hinzufügen
                        $ids = json_decode($existingAnmeldung['teilnehmer_ids'] ?? '[]', true);
                        $ids[] = $teilnehmerId;
                        $db->update('fan_anmeldungen', [
                            'teilnehmer_ids' => json_encode($ids),
                            'kabine' => $kabine ?: $existingAnmeldung['kabine']
                        ], 'anmeldung_id = ?', [$existingAnmeldung['anmeldung_id']]);
                    } else {
                        // Neue Anmeldung erstellen
                        $db->insert('fan_anmeldungen', [
                            'user_id' => $userId,
                            'reise_id' => $reiseId,
                            'kabine' => $kabine ?: null,
                            'teilnehmer_ids' => json_encode([$teilnehmerId])
                        ]);
                    }

                    $db->commit();

                    if ($isNewUser) {
                        // E-Mail-Daten für Modal speichern
                        $_SESSION['new_user_email'] = [
                            'email' => $email,
                            'vorname' => $vorname,
                            'name' => $name,
                            'schiff' => $reise['schiff'],
                            'anfang' => $reise['anfang'],
                            'ende' => $reise['ende']
                        ];
                        $erfolg = 'Teilnehmer wurde hinzugefügt. Neuer Benutzer angelegt mit Passwort "aidafantreffen".';
                    } else {
                        $erfolg = 'Teilnehmer wurde hinzugefügt.';
                    }

                } catch (Exception $e) {
                    $db->rollback();
                    $fehler = 'Fehler: ' . $e->getMessage();
                }
            } else {
                $fehler = 'Vorname, Nachname und E-Mail sind erforderlich.';
            }
        }
    }
}

// Alle Teilnehmer dieser Reise laden - sortiert nach Kabine, dann Nachname
// Hinweis: teilnehmer_ids enthält JSON-Array mit String-IDs wie ["1","2","3"]
$teilnehmer = $db->fetchAll(
    "SELECT t.teilnehmer_id, t.vorname, t.name, t.nickname, t.mobil,
            a.anmeldung_id, a.kabine, a.erstellt AS anmeldung_datum,
            u.email, u.user_id
     FROM fan_anmeldungen a
     JOIN fan_users u ON a.user_id = u.user_id
     JOIN fan_teilnehmer t ON (
         JSON_CONTAINS(a.teilnehmer_ids, CAST(t.teilnehmer_id AS CHAR))
         OR JSON_CONTAINS(a.teilnehmer_ids, CONCAT('\"', t.teilnehmer_id, '\"'))
     )
     WHERE a.reise_id = ?
     ORDER BY CAST(a.kabine AS UNSIGNED), a.kabine, t.name, t.vorname",
    [$reiseId]
);

$gesamtTeilnehmer = count($teilnehmer);
$kabinenCount = count(array_unique(array_filter(array_column($teilnehmer, 'kabine'))));

$reise = $reiseModel->formatForDisplay($reise);
$csrfToken = $session->getCsrfToken();
$pageTitle = 'Teilnehmerliste - ' . $reise['schiff'];
$editId = (int)($_GET['edit'] ?? 0);

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
                <li class="breadcrumb-item active">Teilnehmerliste</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-people"></i> Teilnehmerliste</h1>
            <div class="btn-group no-print">
                <button onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="bi bi-printer"></i> Drucken
                </button>
                <a href="export-csv.php?id=<?= $reiseId ?>" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-spreadsheet"></i> CSV
                </a>
            </div>
        </div>

        <?php if ($fehler): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($fehler) ?></div>
        <?php endif; ?>
        <?php if ($erfolg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($erfolg) ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Reise-Info -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <strong><?= htmlspecialchars($reise['schiff']) ?></strong><br>
                <?= $reise['anfang_formatiert'] ?> - <?= $reise['ende_formatiert'] ?>
            </div>
            <div class="col-md-3">
                <strong>Teilnehmer:</strong> <?= $gesamtTeilnehmer ?><br>
                <strong>Kabinen:</strong> <?= $kabinenCount ?>
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-person-plus"></i> Teilnehmer hinzufügen
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (empty($teilnehmer)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Noch keine Anmeldungen für diese Reise vorhanden.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th style="width: 80px;">Kabine</th>
                    <th>Nachname</th>
                    <th>Vorname</th>
                    <th>Nickname</th>
                    <th>Mobil</th>
                    <th>E-Mail</th>
                    <th class="no-print" style="width: 120px;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teilnehmer as $t): ?>
                    <?php if ($editId === $t['teilnehmer_id']): ?>
                        <!-- Bearbeiten-Zeile -->
                        <tr class="table-warning">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="teilnehmer_id" value="<?= $t['teilnehmer_id'] ?>">
                                <input type="hidden" name="anmeldung_id" value="<?= $t['anmeldung_id'] ?>">
                                <td>
                                    <input type="text" name="kabine" class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($t['kabine'] ?? '') ?>" style="width: 70px;">
                                </td>
                                <td>
                                    <input type="text" name="name" class="form-control form-control-sm" required
                                           value="<?= htmlspecialchars($t['name']) ?>">
                                </td>
                                <td>
                                    <input type="text" name="vorname" class="form-control form-control-sm" required
                                           value="<?= htmlspecialchars($t['vorname']) ?>">
                                </td>
                                <td>
                                    <input type="text" name="nickname" class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($t['nickname'] ?? '') ?>">
                                </td>
                                <td>
                                    <input type="tel" name="mobil" class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($t['mobil'] ?? '') ?>">
                                </td>
                                <td><?= htmlspecialchars($t['email']) ?></td>
                                <td class="no-print">
                                    <button type="submit" class="btn btn-sm btn-success" title="Speichern">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <a href="?id=<?= $reiseId ?>" class="btn btn-sm btn-secondary" title="Abbrechen">
                                        <i class="bi bi-x"></i>
                                    </a>
                                </td>
                            </form>
                        </tr>
                    <?php else: ?>
                        <!-- Normale Anzeige -->
                        <tr>
                            <td><strong><?= htmlspecialchars($t['kabine'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($t['name']) ?></td>
                            <td><?= htmlspecialchars($t['vorname']) ?></td>
                            <td>
                                <?php if ($t['nickname']): ?>
                                    <em><?= htmlspecialchars($t['nickname']) ?></em>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['mobil']): ?>
                                    <a href="tel:<?= htmlspecialchars($t['mobil']) ?>"><?= htmlspecialchars($t['mobil']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($t['email']) ?>"><?= htmlspecialchars($t['email']) ?></a>
                            </td>
                            <td class="no-print">
                                <a href="?id=<?= $reiseId ?>&edit=<?= $t['teilnehmer_id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" class="d-inline"
                                      onsubmit="return confirm('Teilnehmer wirklich löschen?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="teilnehmer_id" value="<?= $t['teilnehmer_id'] ?>">
                                    <input type="hidden" name="anmeldung_id" value="<?= $t['anmeldung_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="6"><?= $gesamtTeilnehmer ?> Teilnehmer in <?= $kabinenCount ?> Kabinen</th>
                    <th class="no-print"></th>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endif; ?>

<div class="mt-4 no-print">
    <a href="reise-bearbeiten.php?id=<?= $reiseId ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück zur Reise
    </a>
</div>

<!-- Modal: Teilnehmer hinzufügen -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Teilnehmer hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Vorname *</label>
                            <input type="text" name="vorname" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Nachname *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Nickname</label>
                            <input type="text" name="nickname" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Kabine</label>
                            <input type="text" name="kabine" class="form-control" placeholder="z.B. 8123">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Mobil</label>
                            <input type="tel" name="mobil" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">E-Mail *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    <p class="text-muted small mt-3">
                        * Pflichtfelder. Falls die E-Mail-Adresse noch nicht existiert, wird ein neuer Benutzer angelegt.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus"></i> Hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// E-Mail-Vorlage Modal anzeigen wenn neuer User hinzugefügt wurde
$showNewUserEmail = $_SESSION['new_user_email'] ?? null;
unset($_SESSION['new_user_email']);

if ($showNewUserEmail):
    $emailTo = $showNewUserEmail['email'];
    $vorname = $showNewUserEmail['vorname'];
    $schiff = $showNewUserEmail['schiff'];
    $anfang = date('d.m.Y', strtotime($showNewUserEmail['anfang']));
    $ende = date('d.m.Y', strtotime($showNewUserEmail['ende']));

    $subject = "Anmeldung zum Fantreffen auf der $schiff";
    $body = "Hallo $vorname,\n\n";
    $body .= "du wurdest für das AIDA Fantreffen angemeldet:\n\n";
    $body .= "Schiff: $schiff\n";
    $body .= "Zeitraum: $anfang - $ende\n\n";
    $body .= "Für dich wurde ein Benutzerkonto angelegt.\n";
    $body .= "Deine Zugangsdaten:\n";
    $body .= "E-Mail: $emailTo\n";
    $body .= "Passwort: aidafantreffen\n\n";
    $body .= "WICHTIG: Bitte ändere dein Passwort nach dem ersten Login!\n\n";
    $body .= "Du kannst dich hier einloggen und deine Daten ergänzen:\n";
    $body .= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname(dirname($_SERVER['SCRIPT_NAME'])) . "/login.php\n\n";
    $body .= "Viele Grüße\n";
    $body .= "Das Fantreffen-Team";
?>
<div class="modal fade show" id="emailModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-envelope"></i> E-Mail an neuen Benutzer senden</h5>
                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('emailModal').style.display='none'"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Neuer Benutzer angelegt!</strong> Bitte informiere den Teilnehmer über seine Zugangsdaten.
                </div>

                <p>Sende diese E-Mail an <strong><?= htmlspecialchars($emailTo) ?></strong>:</p>

                <div class="mb-3">
                    <label class="form-label">Betreff:</label>
                    <input type="text" class="form-control" id="emailSubject" value="<?= htmlspecialchars($subject) ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nachricht:</label>
                    <textarea class="form-control" id="emailBody" rows="12" readonly><?= htmlspecialchars($body) ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <a href="mailto:<?= rawurlencode($emailTo) ?>?subject=<?= rawurlencode($subject) ?>&body=<?= rawurlencode($body) ?>"
                       class="btn btn-primary">
                        <i class="bi bi-envelope"></i> E-Mail-Programm öffnen
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="copyNewUserEmail()">
                        <i class="bi bi-clipboard"></i> Text kopieren
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('emailModal').style.display='none'">
                    Schließen
                </button>
            </div>
        </div>
    </div>
</div>
<script>
function copyNewUserEmail() {
    const subject = document.getElementById('emailSubject').value;
    const body = document.getElementById('emailBody').value;
    const text = "Betreff: " + subject + "\n\n" + body;
    navigator.clipboard.writeText(text).then(() => {
        alert('Text wurde in die Zwischenablage kopiert!');
    });
}
</script>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .table { font-size: 10pt; }
    .navbar, footer { display: none !important; }
    .card { border: none !important; }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
