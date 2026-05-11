<?php
/**
 * VTool zu Sitzungsverwaltung - Daten-Migration
 *
 * Migriert Anträge, Beschlüsse und zugehörige Daten aus VTool-Datenbank
 * in das neue Proposals-System der Sitzungsverwaltung.
 *
 * WICHTIG:
 * - Dieses Skript LIEST nur aus VTool-DB (keine Änderungen!)
 * - Test zuerst in Sandbox mit --dry-run
 * - Vollständiges Backup vor produktiver Migration
 *
 * Usage:
 *   php migrate_vtool_data.php --source-db=vtool_sandbox --target-db=sitzungsverwaltung_dev --dry-run
 *   php migrate_vtool_data.php --source-db=vtool_production --target-db=sitzungsverwaltung --execute
 */

// Fehlerberichterstattung
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(3600); // 1 Stunde

// Optionen parsen
$options = getopt('', [
    'source-db:',
    'target-db:',
    'source-host::',
    'target-host::',
    'dry-run',
    'execute',
    'verbose',
    'help'
]);

if (isset($options['help']) || empty($options['source-db']) || empty($options['target-db'])) {
    echo <<<HELP

VTool Daten-Migration
=====================

Usage:
  php migrate_vtool_data.php --source-db=DB_NAME --target-db=DB_NAME [OPTIONS]

Required:
  --source-db=NAME      Name der VTool-Quelldatenbank
  --target-db=NAME      Name der Sitzungsverwaltung-Zieldatenbank

Optional:
  --source-host=HOST    Host der Quelldatenbank (default: localhost)
  --target-host=HOST    Host der Zieldatenbank (default: localhost)
  --dry-run             Testlauf ohne Schreibzugriff
  --execute             Führt Migration aus
  --verbose             Ausführliche Ausgabe
  --help                Diese Hilfe anzeigen

Beispiele:
  # Test-Migration
  php migrate_vtool_data.php --source-db=vtool_sandbox --target-db=sitzung_dev --dry-run

  # Produktiv-Migration
  php migrate_vtool_data.php --source-db=vtool --target-db=sitzungsverwaltung --execute

HELP;
    exit(0);
}

// Konfiguration
$source_db = $options['source-db'];
$target_db = $options['target-db'];
$source_host = $options['source-host'] ?? 'localhost';
$target_host = $options['target-host'] ?? 'localhost';
$dry_run = isset($options['dry-run']);
$execute = isset($options['execute']);
$verbose = isset($options['verbose']);

if (!$dry_run && !$execute) {
    die("ERROR: Bitte --dry-run oder --execute angeben!\n");
}

// Logging
$log_file = __DIR__ . '/migration_' . date('Y-m-d_H-i-s') . '.log';
function log_message($message, $level = 'INFO') {
    global $log_file, $verbose;
    $timestamp = date('Y-m-d H:i:s');
    $log_line = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $log_line, FILE_APPEND);
    if ($verbose || $level === 'ERROR') {
        echo $log_line;
    }
}

echo "\n";
echo "===========================================\n";
echo "VTool → Sitzungsverwaltung Migration\n";
echo "===========================================\n";
echo "Source: $source_host/$source_db\n";
echo "Target: $target_host/$target_db\n";
echo "Mode: " . ($dry_run ? "DRY RUN (keine Änderungen)" : "EXECUTE (schreibt Daten)") . "\n";
echo "Log: $log_file\n";
echo "===========================================\n\n";

log_message("Migration gestartet");
log_message("Source: $source_host/$source_db");
log_message("Target: $target_host/$target_db");
log_message("Mode: " . ($dry_run ? "DRY-RUN" : "EXECUTE"));

// Datenbank-Verbindungen
try {
    // Config einlesen
    require_once __DIR__ . '/../config.php';

    // Source-Verbindung (VTool)
    $source_pdo = new PDO(
        "mysql:host=$source_host;dbname=$source_db;charset=utf8mb4",
        MYSQL_USER,
        MYSQL_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    log_message("Source-DB Verbindung erfolgreich");

    // Target-Verbindung (Sitzungsverwaltung)
    $target_pdo = new PDO(
        "mysql:host=$target_host;dbname=$target_db;charset=utf8mb4",
        MYSQL_USER,
        MYSQL_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    log_message("Target-DB Verbindung erfolgreich");

} catch (PDOException $e) {
    log_message("DB-Verbindungsfehler: " . $e->getMessage(), 'ERROR');
    die("ABBRUCH: Datenbank-Verbindung fehlgeschlagen!\n");
}

// ========================================
// MIGRATION FUNCTIONS
// ========================================

/**
 * Status aus Antragsnummer ableiten
 */
function get_status_from_proposal_number($antrnr) {
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

/**
 * Vote-Type aus VTool-Votum ableiten
 */
function map_vote_type($votum) {
    return match((int)$votum) {
        1 => 'yes',
        2 => 'no',
        3 => 'abstain',
        4 => 'refer_back',
        5 => 'request_time',
        default => null
    };
}

/**
 * Visibility aus int_ext ableiten
 */
function map_visibility($int_ext) {
    return match($int_ext) {
        'e' => 'public',
        'n' => 'leadership',
        'i' => 'board',
        'v' => 'draft',
        default => 'public'
    };
}

/**
 * Decision Type ableiten
 */
function map_decision_type($bart) {
    return match($bart) {
        'V' => 'single',
        'R' => 'department',
        'B' => 'board',
        default => 'board'
    };
}

/**
 * Organization Type ableiten
 */
function map_organization_type($verein) {
    return match($verein) {
        'S' => 'foundation',
        'V' => 'association',
        default => 'association'
    };
}

/**
 * Simplified Approval ableiten
 */
function map_simplified_approval($sofort) {
    return match((int)$sofort) {
        1 => 'invoice_match',
        2 => 'after_review',
        default => 'none'
    };
}

// Statistiken
$stats = [
    'proposals_total' => 0,
    'proposals_migrated' => 0,
    'proposals_skipped' => 0,
    'attachments_migrated' => 0,
    'votes_migrated' => 0,
    'approvers_migrated' => 0,
    'comments_migrated' => 0,
    'errors' => []
];

echo "\n[1/5] PROPOSALS MIGRIEREN\n";
echo "==========================\n";

try {
    // Anträge aus VTool laden
    $source_proposals = $source_pdo->query("SELECT * FROM antraege ORDER BY antrnr")->fetchAll();
    $stats['proposals_total'] = count($source_proposals);

    log_message("Gefunden: {$stats['proposals_total']} Anträge in VTool");
    echo "Gefunden: {$stats['proposals_total']} Anträge\n";

    if (!$dry_run && $execute) {
        $target_pdo->beginTransaction();
    }

    foreach ($source_proposals as $old) {
        $antrnr = $old['antrnr'];

        try {
            // Status ableiten
            $status = get_status_from_proposal_number($antrnr);

            // Submitted-At aus Antragsnummer
            $year = '20' . substr($antrnr, 1, 2);
            $month = substr($antrnr, 3, 2);
            $day = substr($antrnr, 5, 2);
            $submitted_at = "$year-$month-$day 00:00:00";

            // Proposal-Daten vorbereiten
            $proposal_data = [
                'proposal_number' => $antrnr,
                'status' => $status,
                'submitter_id' => $old['antrst'] ?? null,
                'department_id' => $old['ressort1'] ?? null,
                'co_department_id' => $old['ressort2'] ?? null,
                'organization_type' => map_organization_type($old['verein'] ?? 'V'),
                'visibility' => map_visibility($old['int_ext'] ?? 'e'),
                'title' => $old['titel'] ?? '',
                'description' => $old['beschluss'] ?? '',
                'justification' => $old['begr'] ?? null,
                'financial_amount' => $old['fin'] ?? 0,
                'financial_description' => $old['fintext'] ?? null,
                'personnel_impact' => $old['pers'] ?? null,
                'material_impact' => $old['sach'] ?? null,
                'responsible_person' => $old['verant'] ?? null,
                'forum_thread_id' => $old['thread'] ?? null,
                'meeting_id' => ($old['praesenz'] ?? 0) > 1 ? $old['praesenz'] : null,
                'previous_proposal' => $old['warantrag'] ?? null,
                'decision_type' => map_decision_type($old['bart'] ?? 'B'),
                'simplified_approval' => map_simplified_approval($old['sofort'] ?? 0),
                'review_person' => $old['durch'] ?? null,
                'finance_approval' => ($old['zufin'] ?? 0) ? 1 : 0,
                'payment_note' => $old['zbem'] ?? null,
                'expedite_justification' => $old['verkb'] ?? null,
                'expedite_approver1_id' => ($old['verk1'] ?? 0) ?: null,
                'expedite_approver2_id' => ($old['verk2'] ?? 0) ?: null,
                'submitted_at' => $submitted_at,
                'finalized_at' => in_array($status, ['voting', 'approved', 'rejected']) ? $submitted_at : null,
                'decided_at' => in_array($status, ['approved', 'rejected']) ? $submitted_at : null,
                'last_modified' => $old['lzugriff'] ?? $submitted_at,
                'result_text' => $old['ergebnis'] ?? null,
                'important' => ($old['wichtig'] ?? 0) ? 1 : 0
            ];

            if ($dry_run) {
                log_message("DRY-RUN: Würde migrieren: $antrnr", 'INFO');
            } else {
                // Insert Proposal
                $sql = "INSERT INTO proposals (" . implode(', ', array_keys($proposal_data)) . ")
                        VALUES (:" . implode(', :', array_keys($proposal_data)) . ")";
                $stmt = $target_pdo->prepare($sql);
                $stmt->execute($proposal_data);
                $proposal_id = $target_pdo->lastInsertId();

                log_message("Migriert: $antrnr → ID $proposal_id");

                // Dateien migrieren (file1-4)
                for ($i = 1; $i <= 4; $i++) {
                    $file = $old["file$i"] ?? null;
                    $filetext = $old["filetext$i"] ?? null;

                    if (!empty($file)) {
                        $is_url = strpos($file, '://') !== false;
                        $stmt = $target_pdo->prepare("
                            INSERT INTO proposal_attachments
                            (proposal_id, file_path, file_url, description, uploaded_by)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $proposal_id,
                            $is_url ? null : $file,
                            $is_url ? $file : null,
                            $filetext,
                            $old['antrst']
                        ]);
                        $stats['attachments_migrated']++;
                    }
                }

                // Abstimmungen migrieren (VName1-6, Votum1-6)
                for ($i = 1; $i <= 6; $i++) {
                    $voter_id = $old["VName$i"] ?? null;
                    $votum = $old["Votum$i"] ?? null;

                    if ($voter_id && $votum) {
                        $vote_type = map_vote_type($votum);
                        if ($vote_type) {
                            $stmt = $target_pdo->prepare("
                                INSERT INTO proposal_votes
                                (proposal_id, voter_id, vote_type, internal_comment, protocol_note, consideration_until, voted_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $proposal_id,
                                $voter_id,
                                $vote_type,
                                $old["VBegr$i"] ?? null,
                                $old["VProt$i"] ?? null,
                                $old["VBedenk$i"] ?? null,
                                $old["VDat$i"] ?? null
                            ]);
                            $stats['votes_migrated']++;
                        }
                    }
                }

                // Freigabeberechtigte migrieren (verf1, verf2)
                if (!empty($old['verf1'])) {
                    $stmt = $target_pdo->prepare("
                        INSERT INTO proposal_approvers
                        (proposal_id, approver_id, approver_role)
                        VALUES (?, ?, 'primary')
                    ");
                    $stmt->execute([$proposal_id, $old['verf1']]);
                    $stats['approvers_migrated']++;
                }

                if (!empty($old['verf2'])) {
                    $role = ($old['bart'] ?? '') === 'R' ? 'finance' : 'secondary';
                    $stmt = $target_pdo->prepare("
                        INSERT INTO proposal_approvers
                        (proposal_id, approver_id, approver_role)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$proposal_id, $old['verf2'], $role]);
                    $stats['approvers_migrated']++;
                }

                // Hinweise migrieren
                if (!empty($old['hinweis'])) {
                    $stmt = $target_pdo->prepare("
                        INSERT INTO proposal_comments
                        (proposal_id, author_id, comment_text, is_for_submitter)
                        VALUES (?, 0, ?, 1)
                    ");
                    $stmt->execute([$proposal_id, $old['hinweis']]);
                    $stats['comments_migrated']++;
                }
            }

            $stats['proposals_migrated']++;

            if ($stats['proposals_migrated'] % 100 == 0) {
                echo ".";
            }

        } catch (Exception $e) {
            log_message("Fehler bei $antrnr: " . $e->getMessage(), 'ERROR');
            $stats['errors'][] = "$antrnr: " . $e->getMessage();
            $stats['proposals_skipped']++;
        }
    }

    if (!$dry_run && $execute) {
        $target_pdo->commit();
        log_message("Proposals-Migration abgeschlossen - Transaction committed");
    }

} catch (Exception $e) {
    if (!$dry_run && $execute && $target_pdo->inTransaction()) {
        $target_pdo->rollBack();
        log_message("ROLLBACK wegen Fehler: " . $e->getMessage(), 'ERROR');
    }
    die("\nFEHLER: " . $e->getMessage() . "\n");
}

echo "\n\n";
echo "===========================================\n";
echo "MIGRATION ABGESCHLOSSEN\n";
echo "===========================================\n";
echo "Mode: " . ($dry_run ? "DRY RUN" : "EXECUTED") . "\n\n";
echo "Proposals:\n";
echo "  Total:     {$stats['proposals_total']}\n";
echo "  Migriert:  {$stats['proposals_migrated']}\n";
echo "  Übersprungen: {$stats['proposals_skipped']}\n\n";
echo "Details:\n";
echo "  Anhänge:   {$stats['attachments_migrated']}\n";
echo "  Abstimmungen: {$stats['votes_migrated']}\n";
echo "  Freigaben: {$stats['approvers_migrated']}\n";
echo "  Kommentare: {$stats['comments_migrated']}\n\n";

if (!empty($stats['errors'])) {
    echo "FEHLER ({count($stats['errors'])}):\n";
    foreach (array_slice($stats['errors'], 0, 10) as $error) {
        echo "  - $error\n";
    }
    if (count($stats['errors']) > 10) {
        echo "  ... und " . (count($stats['errors']) - 10) . " weitere (siehe Log)\n";
    }
    echo "\n";
}

echo "Log: $log_file\n";
echo "===========================================\n\n";

log_message("Migration beendet");
log_message("Proposals migriert: {$stats['proposals_migrated']} von {$stats['proposals_total']}");

if ($dry_run) {
    echo "⚠️  DRY RUN - Keine Daten wurden geschrieben!\n";
    echo "   Für echte Migration mit --execute ausführen.\n\n";
}

exit(empty($stats['errors']) ? 0 : 1);
