<?php
/**
 * migrate_vtool_web.php - Web-Interface für VTool-Migration
 *
 * Aufruf: http://localhost/Sitzungsverwaltung/migrations/migrate_vtool_web.php
 *
 * WICHTIG: Nach erfolgreicher Migration diese Datei löschen oder umbenennen!
 */

// Fehler-Reporting für AJAX-Anfragen anpassen
if (isset($_GET['action']) && $_GET['action'] === 'migrate') {
    // Für AJAX: Keine HTML-Fehler ausgeben
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

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

// PDO-Verbindung sicherstellen
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage() . "<br>Bitte config.php prüfen!");
    }
}

// Datenbank-Namen
$source_db = DB_NAME;  // Beide in gleicher DB
$target_db = DB_NAME;

// AJAX-Request für Migration? (MUSS VOR allen anderen Funktionsaufrufen kommen!)
if (isset($_GET['action']) && $_GET['action'] === 'migrate') {
    // Verhindere jegliche Ausgabe vor dem JSON-Header
    ob_start();

    header('Content-Type: application/json');

    $mode = $_GET['mode'] ?? 'dry-run';
    $dry_run = ($mode === 'dry-run');

    try {
        $result = performMigration($pdo, $source_db, $target_db, $dry_run);
        ob_clean(); // Lösche alle ungewollten Ausgaben
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===== AB HIER NUR FÜR HTML-AUSGABE =====

// Prüfen ob Tabellen existieren
$tables_exist = checkTablesExist($pdo, $source_db, $target_db);

// Statistik laden (nur wenn Tabellen existieren)
if ($tables_exist['ready']) {
    $stats = getStatistics($pdo, $source_db, $target_db);
} else {
    $stats = ['source_proposals' => 0, 'target_proposals' => 0, 'pending' => 0];
}

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

        <?php if (!$tables_exist['ready']): ?>
        <!-- Fehlendes Setup -->
        <div class="warning">
            <h3>⚠️ Datenbank-Setup erforderlich</h3>
            <p style="margin-bottom: 15px;">
                Die Zieltabellen für die Migration existieren noch nicht in der Datenbank.
            </p>
            <p style="margin-bottom: 10px;"><strong>Was fehlt?</strong></p>
            <ul style="margin-left: 20px; margin-bottom: 15px;">
                <?php if (!$tables_exist['source_table']): ?>
                <li style="color: #d32f2f;">❌ Tabelle <code>antraege</code> (VTool) nicht gefunden</li>
                <?php else: ?>
                <li style="color: #4CAF50;">✅ Tabelle <code>antraege</code> vorhanden</li>
                <?php endif; ?>

                <?php if (!$tables_exist['target_table']): ?>
                <li style="color: #d32f2f;">❌ Tabelle <code>svbproposals</code> (Sitzungsverwaltung) nicht gefunden</li>
                <?php else: ?>
                <li style="color: #4CAF50;">✅ Tabelle <code>svbproposals</code> vorhanden</li>
                <?php endif; ?>
            </ul>

            <div style="background: white; padding: 15px; border-radius: 4px; border-left: 4px solid #2196F3;">
                <p style="margin-bottom: 10px; font-weight: 600;">📋 Lösung:</p>
                <p style="margin-bottom: 10px;">
                    Führen Sie zuerst das Datenbank-Initialisierungs-Script aus:
                </p>
                <ol style="margin-left: 20px; line-height: 1.8;">
                    <li>Öffnen Sie im Browser: <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">http://<?php echo $_SERVER['HTTP_HOST']; ?>/Sitzungsverwaltung/init-db.php</code></li>
                    <li>Das Script erstellt automatisch alle benötigten Tabellen</li>
                    <li>Kommen Sie dann hierher zurück und laden Sie diese Seite neu</li>
                </ol>
            </div>
        </div>

        <div class="button-group">
            <button class="btn-secondary" onclick="window.location.href='../init-db.php'" style="background: #2196F3;">
                🔧 Jetzt init-db.php öffnen
            </button>
            <button class="btn-secondary" onclick="location.reload()" style="background: #FF9800;">
                🔄 Seite neu laden
            </button>
            <button class="btn-logout" onclick="logout()">
                🚪 Abmelden
            </button>
        </div>

        <?php else: ?>
        <!-- Normales Interface -->
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

        <?php endif; // Ende if ($tables_exist['ready']) ?>
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
                    addLog(`🔄 Beschlüsse: ${result.decisions_merged} merged`, 'info');

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
                        `${result.decisions_merged} Beschlüsse merged.`;

                    // Nur neu laden wenn KEINE Fehler und NICHT Dry-Run
                    if (!dryRun && (!result.errors || result.errors.length === 0)) {
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

function checkTablesExist($pdo, $source_db, $target_db) {
    $result = [
        'source_table' => false,
        'target_table' => false,
        'ready' => false
    ];

    try {
        // Prüfe ob antraege-Tabelle existiert (VTool)
        $stmt = $pdo->query("SHOW TABLES FROM `$source_db` LIKE 'antraege'");
        $result['source_table'] = ($stmt->rowCount() > 0);

        // Prüfe ob svbproposals-Tabelle existiert (Sitzungsverwaltung)
        $stmt = $pdo->query("SHOW TABLES FROM `$target_db` LIKE 'svbproposals'");
        $result['target_table'] = ($stmt->rowCount() > 0);

        $result['ready'] = $result['source_table'] && $result['target_table'];
    } catch (PDOException $e) {
        // Bei Fehler: nicht bereit
        $result['ready'] = false;
    }

    return $result;
}

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
        'decisions_merged' => 0,
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
            LEFT JOIN `$target_db`.svbproposals p ON a.antrnr = p.proposal_number
            WHERE p.id IS NULL
            ORDER BY a.antrnr
        ");
        $stmt->execute();
        $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($proposals as $old) {
            try {
                // DEBUG: Ersten Antrag komplett ausgeben (nur im Dry-Run)
                static $debug_done = false;
                if ($dry_run && !$debug_done) {
                    error_log("DEBUG: Beispiel-Antrag " . $old['antrnr']);
                    error_log("  file1: " . ($old['file1'] ?? 'NULL'));
                    error_log("  Votum1: " . ($old['Votum1'] ?? 'NULL'));
                    error_log("  verf1: " . ($old['verf1'] ?? 'NULL'));
                    $debug_done = true;
                }

                // Status ermitteln (aus Antrnr-Präfix)
                $status = getStatusFromProposalNumber($old['antrnr']);

                // Decision type: ERST aus bart, dann aus Betrag
                $decision_type = mapBartToDecisionType($old['bart'] ?? '');
                if (!$decision_type) {
                    // Fallback: Aus Betrag ableiten
                    $amount = floatval($old['Betrag'] ?? 0);
                    $decision_type = getDecisionType($amount);
                }

                // Organization type aus verein
                $org_type = ($old['verein'] ?? '') === 'S' ? 'foundation' : 'association';

                // Presence voting
                $presence_voting = ($old['praesenz'] ?? '') == '1' ? 1 : 0;

                // Flags
                $prior_review = ($old['vorher'] ?? '') == '1' ? 1 : 0;
                $immediate = ($old['sofort'] ?? '') == '1' ? 1 : 0;
                $important = !empty($old['wichtig']) ? 1 : 0;

                // Beschluss-Daten laden (falls vorhanden)
                $beschluss = null;
                if (!empty($old['antrnr'])) {
                    $stmt_b = $pdo->prepare("SELECT * FROM `$source_db`.beschluesse WHERE antrnr = ?");
                    $stmt_b->execute([$old['antrnr']]);
                    $beschluss = $stmt_b->fetch(PDO::FETCH_ASSOC);
                }

                if (!$dry_run) {
                    // Proposal einfügen mit ALLEN Feldern
                    $stmt = $pdo->prepare("
                        INSERT INTO `$target_db`.svbproposals
                        (proposal_number, status, submitter_id, title, description,
                         justification, financial_amount, financial_description,
                         personnel_impact, material_impact, responsible_person,
                         forum_thread_id, decision_type, organization_type,
                         internal_external, presence_voting, prior_review_required,
                         immediate_payment, prior_reviewer, important,
                         budget_number, budget_code, finance_code,
                         forwarded_to_finance, finance_remarks,
                         notes, hint_to_submitter,
                         original_proposal_number, linked_proposal_1, linked_proposal_2,
                         link_remarks, timeline, last_accessed_at,
                         vtool_bart, vtool_praesenz, vtool_antrst, vtool_verein,
                         vtool_creator_code, result_text,
                         decision_finalized, decision_important, decision_text,
                         decision_title, decision_content, decision_finance_text,
                         decision_personnel, decision_material, decision_justification,
                         decision_departments, decision_internal_external, decision_notes,
                         votes_for_list, votes_against_list, votes_abstain_list,
                         submitted_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $stmt->execute([
                        // Basis-Felder
                        $old['antrnr'],
                        $status,
                        $old['AntragstellerID'] ?? 1,
                        $old['titel'] ?? 'Unbekannt',
                        $old['beschluss'] ?? '',
                        $old['begr'] ?? null,
                        floatval($old['Betrag'] ?? 0),
                        $old['fintext'] ?? null,
                        $old['pers'] ?? null,
                        $old['sach'] ?? null,
                        $old['verant'] ?? null,
                        !empty($old['thread']) ? intval($old['thread']) : null,
                        $decision_type,
                        $org_type,
                        $old['int_ext'] ?? null,
                        $presence_voting,
                        $prior_review,
                        $immediate,
                        $old['durch'] ?? null,
                        $important,
                        // Budget & Finanzen
                        $old['budgetnr'] ?? null,
                        $old['budget'] ?? null,
                        $old['fin'] ?? null,
                        $old['zufin'] ?? null,
                        $old['zbem'] ?? null,
                        // Hinweise
                        null, // notes (gibt's nicht in antraege)
                        $old['hinweis'] ?? null,
                        // Verknüpfungen
                        $old['warantrag'] ?? null,
                        !empty($old['verk1']) ? intval($old['verk1']) : null,
                        !empty($old['verk2']) ? intval($old['verk2']) : null,
                        $old['verkb'] ?? null,
                        $old['Zeitablauf'] ?? null,
                        $old['lzugriff'] ?? null,
                        // VTool Legacy
                        $old['bart'] ?? null,
                        $old['praesenz'] ?? null,
                        $old['antrst'] ?? null,
                        $old['verein'] ?? null,
                        $old['verf'] ?? null,
                        $old['ergebnis'] ?? null,
                        // Beschluss-Phase (aus beschluesse-Tabelle)
                        $beschluss ? (($beschluss['fertig'] ?? '') == '1' ? 1 : 0) : 0,
                        $beschluss ? (($beschluss['wichtig'] ?? '') == '1' ? 1 : 0) : 0,
                        $beschluss['text'] ?? null,
                        $beschluss['titel'] ?? null,
                        $beschluss['beschluss'] ?? null,
                        $beschluss['fintext'] ?? null,
                        $beschluss['pers'] ?? null,
                        $beschluss['sach'] ?? null,
                        $beschluss['begr'] ?? null,
                        $beschluss['ressort'] ?? null,
                        $beschluss['int_ext'] ?? null,
                        $beschluss['anmerkungen'] ?? null,
                        $beschluss['dafuer'] ?? null,
                        $beschluss['dagegen'] ?? null,
                        $beschluss['enthaltungen'] ?? null
                    ]);

                    $proposal_id = $pdo->lastInsertId();
                }

                // Beschluss-Merge zählen (auch im Dry-Run)
                if ($beschluss) {
                    $stats['decisions_merged']++;
                }

                // Anhänge zählen (auch im Dry-Run, aber nur speichern wenn !$dry_run)
                for ($i = 1; $i <= 4; $i++) {
                    $file_field = "file$i";
                    $filetext_field = "filetext$i";
                    if (!empty($old[$file_field])) {
                        if (!$dry_run) {
                            $stmt = $pdo->prepare("
                                INSERT INTO `$target_db`.svbproposal_attachments
                                (proposal_id, file_path, description, uploaded_by, uploaded_at)
                                VALUES (?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $proposal_id,
                                $old[$file_field],
                                $old[$filetext_field] ?? "Anhang $i",
                                $old['AntragstellerID'] ?? 1
                            ]);
                        }
                        $stats['attachments_migrated']++;
                    }
                }

                // Stimmen zählen MIT DETAILS (auch im Dry-Run)
                for ($i = 1; $i <= 6; $i++) {
                    $votum = $old["Votum$i"] ?? null;
                    if (!empty($votum)) {
                        $vote_type = mapVoteType($votum);
                        if ($vote_type) {
                            if (!$dry_run) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO `$target_db`.svbproposal_votes
                                    (proposal_id, voter_id, vote_type,
                                     internal_comment, protocol_note, concerns,
                                     voted_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                ");

                                // VDat parsen (könnte leer oder ungültig sein)
                                $vdat = $old["VDat$i"] ?? null;
                                $voted_at = null;
                                if (!empty($vdat) && $vdat !== '0000-00-00 00:00:00') {
                                    $voted_at = $vdat;
                                }

                                $stmt->execute([
                                    $proposal_id,
                                    1, // Dummy voter_id (TODO: VName richtig mappen)
                                    $vote_type,
                                    $old["VBegr$i"] ?? null,
                                    $old["VProt$i"] ?? null,
                                    $old["VBedenk$i"] ?? null,
                                    $voted_at
                                ]);
                            }
                            $stats['votes_migrated']++;
                        }
                    }
                }

                // Freigabeberechtigte zählen (auch im Dry-Run)
                foreach (['verf1', 'verf2'] as $idx => $field) {
                    if (!empty($old[$field])) {
                        if (!$dry_run) {
                            $stmt = $pdo->prepare("
                                INSERT INTO `$target_db`.svbproposal_approvers
                                (proposal_id, approver_id, approver_role, approved)
                                VALUES (?, ?, ?, 0)
                            ");
                            $stmt->execute([
                                $proposal_id,
                                1, // Dummy ID
                                $idx == 0 ? 'primary' : 'secondary'
                            ]);
                        }
                        $stats['approvers_migrated']++;
                    }
                }

                $stats['proposals_migrated']++;

            } catch (Exception $e) {
                $error_msg = "Antrag {$old['antrnr']}: " . $e->getMessage();
                $stats['errors'][] = $error_msg;
                error_log("MIGRATION ERROR: " . $error_msg);
                // Bei mehr als 5 Fehlern: Abbrechen
                if (count($stats['errors']) > 5 && !$dry_run) {
                    throw new Exception("Zu viele Fehler - Migration abgebrochen");
                }
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

function mapBartToDecisionType($bart) {
    // V = Verfügung, R = Ressortbeschluss, B = Vorstandsabstimmung
    return match($bart) {
        'V' => 'single',        // Verfügung = Einzelentscheidung
        'R' => 'department',    // Ressortbeschluss
        'B' => 'board',         // Vorstandsabstimmung
        default => null
    };
}

function getDecisionType($amount) {
    // Fallback wenn bart nicht vorhanden: Aus Betrag ableiten
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
        6 => 'no_vote',
        default => null
    };
}
?>
