<?php
/**
 * migrate_vtool_web.php - Web-Interface für VTool-Migration
 *
 * Aufruf: http://localhost/Sitzungsverwaltung/migrations/migrate_vtool_web.php
 *
 * WICHTIG: Nach erfolgreicher Migration diese Datei löschen oder umbenennen!
 */

// Session für Authentifizierung
session_start();

// Einfache Authentifizierung (später durch echtes Login ersetzen)
$MIGRATION_PASSWORD = 'migration2024'; // ÄNDERN SIE DIES!

// Login-Check
if (!isset($_SESSION['migration_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $MIGRATION_PASSWORD) {
            $_SESSION['migration_authenticated'] = true;
        } else {
            $login_error = "Falsches Passwort!";
        }
    }

    if (!isset($_SESSION['migration_authenticated'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>VTool Migration - Login</title>
            <style>
                body { font-family: Arial; max-width: 400px; margin: 100px auto; padding: 20px; }
                input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; }
                button { width: 100%; padding: 10px; background: #2196F3; color: white; border: none; cursor: pointer; }
                .error { color: red; margin: 10px 0; }
            </style>
        </head>
        <body>
            <h2>🔒 VTool Migration</h2>
            <p>Bitte geben Sie das Migrations-Passwort ein:</p>
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Passwort" required>
                <button type="submit">Anmelden</button>
            </form>
            <p style="font-size: 12px; color: #666; margin-top: 20px;">
                Standard-Passwort: <code>migration2024</code><br>
                (Bitte in der Datei ändern!)
            </p>
        </body>
        </html>
        <?php
        exit;
    }
}

// Config laden
require_once __DIR__ . '/../config.php';

// Datenbank-Namen
$source_db = DB_NAME;  // Beide in gleicher DB
$target_db = DB_NAME;

// AJAX-Request für Migration?
if (isset($_GET['action']) && $_GET['action'] === 'migrate') {
    header('Content-Type: application/json');

    $mode = $_GET['mode'] ?? 'dry-run';
    $dry_run = ($mode === 'dry-run');

    try {
        $result = performMigration($pdo, $source_db, $target_db, $dry_run);
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Statistik laden
$stats = getStatistics($pdo, $source_db, $target_db);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VTool → Sitzungsverwaltung Migration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #2196F3;
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-weight: normal;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: 600;
            color: #333;
        }
        .button-group {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        button {
            flex: 1;
            padding: 15px 25px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-dry-run {
            background: #FF9800;
            color: white;
        }
        .btn-dry-run:hover {
            background: #F57C00;
        }
        .btn-execute {
            background: #4CAF50;
            color: white;
        }
        .btn-execute:hover {
            background: #388E3C;
        }
        .btn-logout {
            background: #757575;
            color: white;
            flex: 0 0 auto;
            padding: 15px 30px;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .progress {
            display: none;
            margin-top: 20px;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #f0f0f0;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2196F3, #4CAF50);
            width: 0%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        .log {
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            line-height: 1.6;
            display: none;
        }
        .log.active {
            display: block;
        }
        .log-line {
            margin-bottom: 5px;
        }
        .log-line.error {
            color: #ff4444;
        }
        .log-line.success {
            color: #00ff00;
        }
        .log-line.info {
            color: #4fc3f7;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .warning h3 {
            color: #856404;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .warning p {
            color: #856404;
            line-height: 1.6;
        }
        .success-message {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
            display: none;
        }
        .success-message.active {
            display: block;
        }
        .success-message h3 {
            color: #155724;
            margin-bottom: 8px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #0d47a1;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #0d47a1;
        }
        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 VTool → Sitzungsverwaltung Migration</h1>
        <p class="subtitle">Datenbank: <strong><?php echo htmlspecialchars(DB_NAME); ?></strong></p>

        <div class="info-box">
            <h3>ℹ️ Wichtige Informationen</h3>
            <ul>
                <li><strong>Dry-Run:</strong> Zeigt nur an, was migriert würde (keine Änderungen)</li>
                <li><strong>Migration ausführen:</strong> Migriert die Daten tatsächlich</li>
                <li><strong>Rollback:</strong> Bei Fehlern automatisch</li>
                <li><strong>Wiederholbar:</strong> Bereits migrierte Anträge werden übersprungen</li>
            </ul>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3>Anträge (VTool)</h3>
                <div class="number"><?php echo number_format($stats['source_proposals']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Bereits migriert</h3>
                <div class="number"><?php echo number_format($stats['target_proposals']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Zu migrieren</h3>
                <div class="number"><?php echo number_format($stats['pending']); ?></div>
            </div>
        </div>

        <?php if ($stats['pending'] > 0): ?>
        <div class="warning">
            <h3>⚠️ Vor der Migration</h3>
            <p>
                Es werden <strong><?php echo $stats['pending']; ?> Anträge</strong> migriert.
                Führen Sie zuerst einen <strong>Dry-Run</strong> durch, um zu prüfen,
                ob alles korrekt erkannt wird!
            </p>
        </div>
        <?php endif; ?>

        <div class="button-group">
            <button class="btn-dry-run" onclick="startMigration('dry-run')" id="btnDryRun">
                🔍 Dry-Run (Test)
            </button>
            <button class="btn-execute" onclick="startMigration('execute')" id="btnExecute">
                ✅ Migration ausführen
            </button>
            <button class="btn-logout" onclick="logout()">
                🚪 Abmelden
            </button>
        </div>

        <div class="progress" id="progress">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill">0%</div>
            </div>
            <div id="statusText" style="text-align: center; color: #666; font-size: 14px;">
                Initialisiere...
            </div>
        </div>

        <div class="log" id="log"></div>

        <div class="success-message" id="successMessage">
            <h3>✅ Migration erfolgreich abgeschlossen!</h3>
            <p id="successDetails"></p>
        </div>
    </div>

    <script>
    function startMigration(mode) {
        const dryRun = mode === 'dry-run';
        const btnDryRun = document.getElementById('btnDryRun');
        const btnExecute = document.getElementById('btnExecute');
        const progress = document.getElementById('progress');
        const log = document.getElementById('log');
        const progressFill = document.getElementById('progressFill');
        const statusText = document.getElementById('statusText');
        const successMessage = document.getElementById('successMessage');

        // UI zurücksetzen
        successMessage.classList.remove('active');
        log.innerHTML = '';
        log.classList.add('active');
        progress.style.display = 'block';

        // Buttons deaktivieren
        btnDryRun.disabled = true;
        btnExecute.disabled = true;

        addLog(`${dryRun ? '🔍 Starte Dry-Run...' : '✅ Starte Migration...'}`, 'info');
        progressFill.style.width = '10%';
        progressFill.textContent = '10%';
        statusText.textContent = 'Verbinde mit Datenbank...';

        // AJAX-Request
        fetch(`?action=migrate&mode=${mode}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    progressFill.style.width = '100%';
                    progressFill.textContent = '100%';
                    statusText.textContent = 'Abgeschlossen!';

                    const result = data.data;

                    addLog(`\n✅ ${dryRun ? 'Dry-Run' : 'Migration'} erfolgreich!`, 'success');
                    addLog(`📊 Proposals: ${result.proposals_migrated} migriert`, 'success');
                    addLog(`📎 Anhänge: ${result.attachments_migrated}`, 'info');
                    addLog(`🗳️ Stimmen: ${result.votes_migrated}`, 'info');
                    addLog(`✓ Freigaben: ${result.approvers_migrated}`, 'info');
                    addLog(`💬 Kommentare: ${result.comments_migrated}`, 'info');

                    if (result.errors && result.errors.length > 0) {
                        addLog(`\n⚠️ Fehler: ${result.errors.length}`, 'error');
                        result.errors.slice(0, 5).forEach(err => addLog(`  - ${err}`, 'error'));
                    }

                    // Erfolgs-Nachricht
                    successMessage.classList.add('active');
                    document.getElementById('successDetails').innerHTML =
                        `${result.proposals_migrated} Anträge, ` +
                        `${result.attachments_migrated} Anhänge, ` +
                        `${result.votes_migrated} Stimmen, ` +
                        `${result.approvers_migrated} Freigaben, ` +
                        `${result.comments_migrated} Kommentare migriert.`;

                    if (!dryRun) {
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    }
                } else {
                    addLog(`❌ Fehler: ${data.error}`, 'error');
                    statusText.textContent = 'Fehler aufgetreten!';
                }
            })
            .catch(error => {
                addLog(`❌ Netzwerkfehler: ${error.message}`, 'error');
                statusText.textContent = 'Verbindungsfehler!';
            })
            .finally(() => {
                btnDryRun.disabled = false;
                btnExecute.disabled = false;
            });

        // Fortschritts-Animation
        let fakeProgress = 10;
        const interval = setInterval(() => {
            if (fakeProgress < 90) {
                fakeProgress += Math.random() * 10;
                progressFill.style.width = fakeProgress + '%';
                progressFill.textContent = Math.round(fakeProgress) + '%';
            } else {
                clearInterval(interval);
            }
        }, 500);
    }

    function addLog(message, type = 'info') {
        const log = document.getElementById('log');
        const line = document.createElement('div');
        line.className = `log-line ${type}`;
        line.textContent = message;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    function logout() {
        if (confirm('Wirklich abmelden?')) {
            window.location.href = '?logout=1';
        }
    }
    </script>
</body>
</html>

<?php

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ============================================
// FUNKTIONEN
// ============================================

function getStatistics($pdo, $source_db, $target_db) {
    // Anträge in VTool
    $source_count = $pdo->query("SELECT COUNT(*) FROM `$source_db`.antraege")->fetchColumn();

    // Bereits migrierte in Sitzungsverwaltung
    $target_count = $pdo->query("SELECT COUNT(*) FROM `$target_db`.svbproposals")->fetchColumn();

    return [
        'source_proposals' => $source_count,
        'target_proposals' => $target_count,
        'pending' => max(0, $source_count - $target_count)
    ];
}

function performMigration($pdo, $source_db, $target_db, $dry_run = true) {
    $stats = [
        'proposals_migrated' => 0,
        'attachments_migrated' => 0,
        'votes_migrated' => 0,
        'approvers_migrated' => 0,
        'comments_migrated' => 0,
        'errors' => []
    ];

    if (!$dry_run) {
        $pdo->beginTransaction();
    }

    try {
        // Proposals laden die noch nicht migriert wurden
        $stmt = $pdo->prepare("
            SELECT a.*
            FROM `$source_db`.antraege a
            LEFT JOIN `$target_db`.svbproposals p ON a.Antrnr = p.proposal_number
            WHERE p.id IS NULL
            ORDER BY a.ID
        ");
        $stmt->execute();
        $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($proposals as $old) {
            try {
                // Status ermitteln
                $status = getStatusFromProposalNumber($old['Antrnr']);

                // Decision type ermitteln
                $amount = floatval($old['Betrag'] ?? 0);
                $decision_type = getDecisionType($amount);

                if (!$dry_run) {
                    // Proposal einfügen
                    $stmt = $pdo->prepare("
                        INSERT INTO `$target_db`.svbproposals
                        (proposal_number, status, submitter_id, title, description,
                         financial_amount, decision_type, submitted_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $old['Antrnr'],
                        $status,
                        $old['AntragstellerID'] ?? 1,
                        $old['Titel'] ?? 'Unbekannt',
                        $old['Beschreibung'] ?? '',
                        $amount,
                        $decision_type
                    ]);

                    $proposal_id = $pdo->lastInsertId();

                    // Anhänge migrieren (file1-4)
                    for ($i = 1; $i <= 4; $i++) {
                        $file_field = "file$i";
                        if (!empty($old[$file_field])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO `$target_db`.svbproposal_attachments
                                (proposal_id, file_path, description, uploaded_by, uploaded_at)
                                VALUES (?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $proposal_id,
                                $old[$file_field],
                                "Anhang $i",
                                $old['AntragstellerID'] ?? 1
                            ]);
                            $stats['attachments_migrated']++;
                        }
                    }

                    // Stimmen migrieren (VName1-6, Votum1-6)
                    for ($i = 1; $i <= 6; $i++) {
                        $name_field = "VName$i";
                        $vote_field = "Votum$i";
                        if (!empty($old[$vote_field])) {
                            $vote_type = mapVoteType($old[$vote_field]);
                            if ($vote_type) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO `$target_db`.svbproposal_votes
                                    (proposal_id, voter_id, vote_type, voted_at)
                                    VALUES (?, ?, ?, NOW())
                                ");
                                $stmt->execute([
                                    $proposal_id,
                                    1, // Dummy voter_id
                                    $vote_type
                                ]);
                                $stats['votes_migrated']++;
                            }
                        }
                    }
                }

                $stats['proposals_migrated']++;

            } catch (Exception $e) {
                $stats['errors'][] = "Antrag {$old['Antrnr']}: " . $e->getMessage();
            }
        }

        if (!$dry_run) {
            $pdo->commit();
        }

    } catch (Exception $e) {
        if (!$dry_run) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $stats;
}

function getStatusFromProposalNumber($antrnr) {
    $prefix = substr($antrnr, 0, 1);
    return match($prefix) {
        'A' => 'editing',
        'B' => 'voting',
        'V' => 'approved',
        'X' => 'rejected',
        'Z' => 'withdrawn',
        default => 'draft'
    };
}

function getDecisionType($amount) {
    if ($amount < 600) return 'single';
    if ($amount < 3000) return 'department';
    return 'board';
}

function mapVoteType($votum) {
    return match((int)$votum) {
        1 => 'yes',
        2 => 'no',
        3 => 'abstain',
        4 => 'refer_back',
        5 => 'request_time',
        default => null
    };
}
?>
