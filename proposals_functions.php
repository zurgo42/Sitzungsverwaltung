<?php
/**
 * proposals_functions.php - Core business logic for proposals system
 * Erstellt: 2025-05-11
 *
 * Enthält alle Kern-Funktionen für Anträge und Beschlüsse:
 * - CRUD-Operationen für Proposals
 * - Workflow-Management (Status-Übergänge)
 * - Abstimmungslogik
 * - Freigabe-Prozesse
 * - Attachment-Verwaltung
 * - Kommentar-Funktionen
 */

// Dependencies
require_once __DIR__ . '/member_functions.php';
require_once __DIR__ . '/config_proposals.php';
require_once __DIR__ . '/adapters/ProposalPermissionAdapter.php';

// ============================================
// PROPOSAL CRUD OPERATIONS
// ============================================

/**
 * Erstellt einen neuen Antrag
 *
 * @param PDO $pdo Datenbankverbindung
 * @param array $user User-Array vom MemberAdapter
 * @param array $data Antragsdaten
 * @return array ['success' => bool, 'proposal_id' => int, 'error' => string]
 */
function create_proposal($pdo, $user, $data) {
    try {
        // Validierung
        if (!isset($user['member_id'])) {
            return ['success' => false, 'error' => 'Kein gültiger Benutzer'];
        }

        // Pflichtfelder prüfen
        $required = ['title', 'description'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Feld '$field' ist erforderlich"];
            }
        }

        // Finanziellen Betrag validieren
        $amount = floatval($data['financial_amount'] ?? 0);
        if ($amount < 0) {
            return ['success' => false, 'error' => 'Finanzieller Betrag darf nicht negativ sein'];
        }

        // Decision type automatisch bestimmen (falls nicht angegeben)
        $decision_type = $data['decision_type'] ?? get_decision_type_by_amount($amount);

        // Antragsnummer generieren
        $proposal_number = generate_proposal_number($pdo, 'draft');

        // Standardwerte
        $visibility = $data['visibility'] ?? PROPOSAL_DEFAULT_VISIBILITY;
        $status = 'draft';

        // Insert
        $sql = "
            INSERT INTO proposals (
                proposal_number, status, submitter_id, department_id, co_department_id,
                organization_type, visibility, title, description, justification,
                financial_amount, financial_description, personnel_impact, material_impact,
                responsible_person, decision_type, simplified_approval, review_person,
                expedite_justification, submitted_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, NOW()
            )
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $proposal_number,
            $status,
            $user['member_id'],
            $data['department_id'] ?? null,
            $data['co_department_id'] ?? null,
            ORGANIZATION_TYPE,
            $visibility,
            $data['title'],
            $data['description'],
            $data['justification'] ?? null,
            $amount,
            $data['financial_description'] ?? null,
            $data['personnel_impact'] ?? null,
            $data['material_impact'] ?? null,
            $data['responsible_person'] ?? null,
            $decision_type,
            $data['simplified_approval'] ?? 'none',
            $data['review_person'] ?? null,
            $data['expedite_justification'] ?? null
        ]);

        $proposal_id = $pdo->lastInsertId();

        // Change-Log erstellen
        if (PROPOSAL_TRACK_CHANGES) {
            log_proposal_change($pdo, $proposal_id, $user['member_id'], 'created', 'Antrag erstellt');
        }

        return [
            'success' => true,
            'proposal_id' => $proposal_id,
            'proposal_number' => $proposal_number
        ];

    } catch (PDOException $e) {
        error_log("Error creating proposal: " . $e->getMessage());
        return ['success' => false, 'error' => 'Datenbankfehler beim Erstellen des Antrags'];
    }
}

/**
 * Aktualisiert einen bestehenden Antrag
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param array $user User-Array
 * @param array $data Zu aktualisierende Felder
 * @return array ['success' => bool, 'error' => string]
 */
function update_proposal($pdo, $proposal_id, $user, $data) {
    try {
        // Proposal laden und Berechtigung prüfen
        $proposal = get_proposal_by_id($pdo, $proposal_id);
        if (!$proposal) {
            return ['success' => false, 'error' => 'Antrag nicht gefunden'];
        }

        $memberAdapter = get_member_adapter($pdo);
        $permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);

        if (!$permAdapter->can_edit_proposal($user, $proposal)) {
            return ['success' => false, 'error' => 'Keine Berechtigung zum Bearbeiten'];
        }

        // Nur bestimmte Felder dürfen aktualisiert werden
        $allowed_fields = [
            'title', 'description', 'justification',
            'financial_amount', 'financial_description',
            'personnel_impact', 'material_impact', 'responsible_person',
            'department_id', 'co_department_id', 'visibility',
            'simplified_approval', 'review_person', 'expedite_justification'
        ];

        $update_fields = [];
        $update_values = [];
        $changes = [];

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                $update_fields[] = "$field = ?";
                $update_values[] = $data[$field];

                // Änderung protokollieren
                if ($proposal[$field] != $data[$field]) {
                    $changes[] = "$field: '{$proposal[$field]}' → '{$data[$field]}'";
                }
            }
        }

        if (empty($update_fields)) {
            return ['success' => true, 'message' => 'Keine Änderungen'];
        }

        // Update durchführen
        $sql = "UPDATE proposals SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $update_values[] = $proposal_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($update_values);

        // Change-Log
        if (PROPOSAL_TRACK_CHANGES && !empty($changes)) {
            log_proposal_change($pdo, $proposal_id, $user['member_id'], 'updated', implode('; ', $changes));
        }

        return ['success' => true];

    } catch (PDOException $e) {
        error_log("Error updating proposal: " . $e->getMessage());
        return ['success' => false, 'error' => 'Datenbankfehler beim Aktualisieren'];
    }
}

/**
 * Lädt einen Antrag anhand der ID
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param bool $include_relations Soll Related Data geladen werden?
 * @return array|null Proposal-Daten oder null
 */
function get_proposal_by_id($pdo, $proposal_id, $include_relations = false) {
    $stmt = $pdo->prepare("SELECT * FROM proposals WHERE id = ?");
    $stmt->execute([$proposal_id]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        return null;
    }

    // Submitter-Daten laden
    $submitter = get_member_by_id($pdo, $proposal['submitter_id']);
    if ($submitter) {
        $proposal['submitter_name'] = $submitter['first_name'] . ' ' . $submitter['last_name'];
        $proposal['submitter_email'] = $submitter['email'];
    }

    if ($include_relations) {
        // Attachments laden
        $proposal['attachments'] = get_proposal_attachments($pdo, $proposal_id);

        // Votes laden
        $proposal['votes'] = get_proposal_votes($pdo, $proposal_id);

        // Comments laden
        $proposal['comments'] = get_proposal_comments($pdo, $proposal_id);

        // Expedite approvals laden
        if ($proposal['expedite_approver1_id']) {
            $approver1 = get_member_by_id($pdo, $proposal['expedite_approver1_id']);
            $proposal['expedite_approver1_name'] = $approver1 ? ($approver1['first_name'] . ' ' . $approver1['last_name']) : null;
        }
        if ($proposal['expedite_approver2_id']) {
            $approver2 = get_member_by_id($pdo, $proposal['expedite_approver2_id']);
            $proposal['expedite_approver2_name'] = $approver2 ? ($approver2['first_name'] . ' ' . $approver2['last_name']) : null;
        }
    }

    return $proposal;
}

/**
 * Lädt einen Antrag anhand der Antragsnummer
 *
 * @param PDO $pdo
 * @param string $proposal_number
 * @return array|null
 */
function get_proposal_by_number($pdo, $proposal_number) {
    $stmt = $pdo->prepare("SELECT * FROM proposals WHERE proposal_number = ?");
    $stmt->execute([$proposal_number]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Lädt alle Anträge mit Filteroptionen
 *
 * @param PDO $pdo
 * @param array $filters Filter-Optionen
 * @param array $user Aktueller User (für Permission-Check)
 * @return array Liste von Proposals
 */
function get_proposals($pdo, $filters = [], $user = null) {
    $where = [];
    $params = [];

    // Status-Filter
    if (isset($filters['status'])) {
        if (is_array($filters['status'])) {
            $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
            $where[] = "status IN ($placeholders)";
            $params = array_merge($params, $filters['status']);
        } else {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
    }

    // Department-Filter
    if (isset($filters['department_id'])) {
        $where[] = "(department_id = ? OR co_department_id = ?)";
        $params[] = $filters['department_id'];
        $params[] = $filters['department_id'];
    }

    // Submitter-Filter
    if (isset($filters['submitter_id'])) {
        $where[] = "submitter_id = ?";
        $params[] = $filters['submitter_id'];
    }

    // Decision type Filter
    if (isset($filters['decision_type'])) {
        $where[] = "decision_type = ?";
        $params[] = $filters['decision_type'];
    }

    // Zeitraum-Filter
    if (isset($filters['from_date'])) {
        $where[] = "submitted_at >= ?";
        $params[] = $filters['from_date'];
    }
    if (isset($filters['to_date'])) {
        $where[] = "submitted_at <= ?";
        $params[] = $filters['to_date'];
    }

    // SQL zusammenbauen
    $sql = "SELECT p.*,
                   m.first_name as submitter_first_name,
                   m.last_name as submitter_last_name
            FROM proposals p
            LEFT JOIN svmembers m ON p.submitter_id = m.member_id";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY p.submitted_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Permission-Filter anwenden (falls User angegeben)
    if ($user) {
        $memberAdapter = get_member_adapter($pdo);
        $permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);
        $proposals = $permAdapter->filter_viewable_proposals($user, $proposals);
    }

    return $proposals;
}

// ============================================
// WORKFLOW MANAGEMENT
// ============================================

/**
 * Finalisiert einen Antrag (Übergang von 'draft'/'editing' zu 'voting' oder direkt zu 'approved')
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param array $user User-Array
 * @return array ['success' => bool, 'error' => string, 'new_status' => string]
 */
function finalize_proposal($pdo, $proposal_id, $user) {
    try {
        $pdo->beginTransaction();

        $proposal = get_proposal_by_id($pdo, $proposal_id);
        if (!$proposal) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Antrag nicht gefunden'];
        }

        // Berechtigung prüfen
        $memberAdapter = get_member_adapter($pdo);
        $permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);

        if (!$permAdapter->can_edit_proposal($user, $proposal)) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Keine Berechtigung'];
        }

        // Prüfen ob noch in 'draft' oder 'editing'
        if (!in_array($proposal['status'], ['draft', 'editing'])) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Antrag ist bereits finalisiert'];
        }

        // Prüfen ob Wartezeit abgelaufen ist (oder Expedite genehmigt)
        if ($proposal['status'] === 'editing') {
            $waiting_period = PROPOSAL_WAITING_PERIOD_DAYS;
            $finalized_at = strtotime($proposal['finalized_at']);
            $now = time();
            $days_passed = ($now - $finalized_at) / 86400;

            $expedite_approved = ($proposal['expedite_approver1_id'] && $proposal['expedite_approver2_id']);

            if ($days_passed < $waiting_period && !$expedite_approved) {
                $pdo->rollBack();
                $days_remaining = ceil($waiting_period - $days_passed);
                return ['success' => false, 'error' => "Wartezeit noch nicht abgelaufen ($days_remaining Tage verbleibend)"];
            }
        }

        // Nächsten Status bestimmen
        $decision_type = $proposal['decision_type'];

        if ($decision_type === 'single') {
            // Verfügung: Direkt zu 'approved' (keine Abstimmung)
            $new_status = 'approved';
            $decided_at = date('Y-m-d H:i:s');
            $voting_deadline = null;
        } else {
            // Department/Board: Abstimmung erforderlich
            $new_status = 'voting';
            $decided_at = null;
            $voting_deadline = date('Y-m-d H:i:s', strtotime('+' . PROPOSAL_VOTING_PERIOD_DAYS . ' days'));
        }

        // Status-Übergang durchführen
        $new_proposal_number = generate_proposal_number($pdo, $new_status);

        $stmt = $pdo->prepare("
            UPDATE proposals
            SET status = ?,
                proposal_number = ?,
                finalized_at = NOW(),
                voting_deadline = ?,
                decided_at = ?,
                previous_proposal = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $new_status,
            $new_proposal_number,
            $voting_deadline,
            $decided_at,
            $proposal['proposal_number'],
            $proposal_id
        ]);

        // Change-Log
        if (PROPOSAL_TRACK_CHANGES) {
            log_proposal_change($pdo, $proposal_id, $user['member_id'], 'finalized',
                "Status: {$proposal['status']} → $new_status, Nummer: {$proposal['proposal_number']} → $new_proposal_number");
        }

        $pdo->commit();

        return [
            'success' => true,
            'new_status' => $new_status,
            'proposal_number' => $new_proposal_number,
            'voting_deadline' => $voting_deadline
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error finalizing proposal: " . $e->getMessage());
        return ['success' => false, 'error' => 'Fehler beim Finalisieren'];
    }
}

/**
 * Zieht einen Antrag zurück (Status → 'withdrawn')
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param array $user
 * @param string $reason Begründung
 * @return array
 */
function withdraw_proposal($pdo, $proposal_id, $user, $reason = '') {
    try {
        $proposal = get_proposal_by_id($pdo, $proposal_id);
        if (!$proposal) {
            return ['success' => false, 'error' => 'Antrag nicht gefunden'];
        }

        $memberAdapter = get_member_adapter($pdo);
        $permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);

        if (!$permAdapter->can_withdraw_proposal($user, $proposal)) {
            return ['success' => false, 'error' => 'Keine Berechtigung zum Zurückziehen'];
        }

        // Status ändern
        $new_proposal_number = generate_proposal_number($pdo, 'withdrawn');

        $stmt = $pdo->prepare("
            UPDATE proposals
            SET status = 'withdrawn',
                proposal_number = ?,
                previous_proposal = ?,
                decided_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_proposal_number, $proposal['proposal_number'], $proposal_id]);

        // Change-Log
        if (PROPOSAL_TRACK_CHANGES) {
            $change_desc = "Antrag zurückgezogen";
            if ($reason) {
                $change_desc .= ": $reason";
            }
            log_proposal_change($pdo, $proposal_id, $user['member_id'], 'withdrawn', $change_desc);
        }

        return ['success' => true, 'proposal_number' => $new_proposal_number];

    } catch (Exception $e) {
        error_log("Error withdrawing proposal: " . $e->getMessage());
        return ['success' => false, 'error' => 'Fehler beim Zurückziehen'];
    }
}

// ============================================
// VOTING FUNCTIONS
// ============================================

/**
 * Gibt eine Stimme für einen Antrag ab
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param array $user
 * @param string $vote_type 'yes', 'no', 'abstain', 'refer_back', 'request_time'
 * @param string $comment Optionaler Kommentar
 * @return array
 */
function submit_vote($pdo, $proposal_id, $user, $vote_type, $comment = '') {
    try {
        $proposal = get_proposal_by_id($pdo, $proposal_id);
        if (!$proposal) {
            return ['success' => false, 'error' => 'Antrag nicht gefunden'];
        }

        // Berechtigung prüfen
        $memberAdapter = get_member_adapter($pdo);
        $permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);

        if (!$permAdapter->can_vote($user, $proposal)) {
            return ['success' => false, 'error' => 'Keine Stimmberechtigung'];
        }

        // Bereits abgestimmt?
        if ($permAdapter->has_voted($user, $proposal_id)) {
            return ['success' => false, 'error' => 'Sie haben bereits abgestimmt'];
        }

        // Vote speichern
        $stmt = $pdo->prepare("
            INSERT INTO proposal_votes (proposal_id, voter_id, vote_type, comment, voted_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$proposal_id, $user['member_id'], $vote_type, $comment]);

        // Change-Log
        if (PROPOSAL_TRACK_CHANGES) {
            log_proposal_change($pdo, $proposal_id, $user['member_id'], 'voted', "Stimme abgegeben: $vote_type");
        }

        return ['success' => true];

    } catch (Exception $e) {
        error_log("Error submitting vote: " . $e->getMessage());
        return ['success' => false, 'error' => 'Fehler beim Abstimmen'];
    }
}

/**
 * Lädt alle Stimmen zu einem Antrag
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param bool $include_names Sollen Namen geladen werden?
 * @return array
 */
function get_proposal_votes($pdo, $proposal_id, $include_names = true) {
    $sql = "SELECT v.* FROM proposal_votes v WHERE v.proposal_id = ? ORDER BY v.voted_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$proposal_id]);
    $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($include_names) {
        foreach ($votes as &$vote) {
            $voter = get_member_by_id($pdo, $vote['voter_id']);
            if ($voter) {
                $vote['voter_name'] = $voter['first_name'] . ' ' . $voter['last_name'];
            }
        }
    }

    return $votes;
}

/**
 * Berechnet das Abstimmungsergebnis
 *
 * @param array $votes Array von Vote-Objekten
 * @return array ['yes' => int, 'no' => int, 'abstain' => int, 'total' => int, 'result' => string]
 */
function calculate_vote_result($votes) {
    $counts = [
        'yes' => 0,
        'no' => 0,
        'abstain' => 0,
        'refer_back' => 0,
        'request_time' => 0
    ];

    foreach ($votes as $vote) {
        $type = $vote['vote_type'];
        if (isset($counts[$type])) {
            $counts[$type]++;
        }
    }

    $total = count($votes);
    $yes_percent = $total > 0 ? ($counts['yes'] / $total * 100) : 0;
    $no_percent = $total > 0 ? ($counts['no'] / $total * 100) : 0;

    // Ergebnis bestimmen (einfache Mehrheit)
    if ($counts['yes'] > $counts['no']) {
        $result = 'approved';
    } elseif ($counts['no'] > $counts['yes']) {
        $result = 'rejected';
    } else {
        $result = 'tied';
    }

    return [
        'yes' => $counts['yes'],
        'no' => $counts['no'],
        'abstain' => $counts['abstain'],
        'refer_back' => $counts['refer_back'],
        'request_time' => $counts['request_time'],
        'total' => $total,
        'yes_percent' => round($yes_percent, 1),
        'no_percent' => round($no_percent, 1),
        'result' => $result
    ];
}

/**
 * Finalisiert eine Abstimmung (automatisch oder manuell)
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @return array
 */
function finalize_voting($pdo, $proposal_id) {
    try {
        $pdo->beginTransaction();

        $proposal = get_proposal_by_id($pdo, $proposal_id, true);
        if (!$proposal || $proposal['status'] !== 'voting') {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Antrag ist nicht im Abstimmungsstatus'];
        }

        // Abstimmungsergebnis berechnen
        $vote_result = calculate_vote_result($proposal['votes']);

        // Neuen Status bestimmen
        $new_status = $vote_result['result'] === 'approved' ? 'approved' : 'rejected';
        $new_proposal_number = generate_proposal_number($pdo, $new_status);

        // Ergebnis-Text generieren
        $result_text = sprintf(
            "Abstimmung: %d Ja, %d Nein, %d Enthaltungen (gesamt %d Stimmen). Ergebnis: %s",
            $vote_result['yes'],
            $vote_result['no'],
            $vote_result['abstain'],
            $vote_result['total'],
            $new_status === 'approved' ? 'Angenommen' : 'Abgelehnt'
        );

        // Update Proposal
        $stmt = $pdo->prepare("
            UPDATE proposals
            SET status = ?,
                proposal_number = ?,
                previous_proposal = ?,
                decided_at = NOW(),
                result_text = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $new_status,
            $new_proposal_number,
            $proposal['proposal_number'],
            $result_text,
            $proposal_id
        ]);

        // Change-Log
        if (PROPOSAL_TRACK_CHANGES) {
            log_proposal_change($pdo, $proposal_id, null, 'finalized_voting', $result_text);
        }

        $pdo->commit();

        return [
            'success' => true,
            'new_status' => $new_status,
            'proposal_number' => $new_proposal_number,
            'vote_result' => $vote_result,
            'result_text' => $result_text
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error finalizing voting: " . $e->getMessage());
        return ['success' => false, 'error' => 'Fehler beim Finalisieren der Abstimmung'];
    }
}

// ============================================
// EXPEDITE APPROVAL
// ============================================

/**
 * Genehmigt eine Wartezeitverkürzung
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param array $user Vorstandsmitglied
 * @return array
 */
function approve_expedite($pdo, $proposal_id, $user) {
    try {
        $proposal = get_proposal_by_id($pdo, $proposal_id);
        if (!$proposal) {
            return ['success' => false, 'error' => 'Antrag nicht gefunden'];
        }

        $memberAdapter = get_member_adapter($pdo);
        $permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);

        if (!$permAdapter->can_approve_expedite($user, $proposal)) {
            return ['success' => false, 'error' => 'Keine Berechtigung für Wartezeitverkürzung'];
        }

        // Bereits genehmigt?
        if ($proposal['expedite_approver1_id'] == $user['member_id'] ||
            $proposal['expedite_approver2_id'] == $user['member_id']) {
            return ['success' => false, 'error' => 'Sie haben bereits genehmigt'];
        }

        // Erste oder zweite Genehmigung?
        if (!$proposal['expedite_approver1_id']) {
            $stmt = $pdo->prepare("
                UPDATE proposals
                SET expedite_approver1_id = ?,
                    expedite_approved_at1 = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$user['member_id'], $proposal_id]);
            $approver_number = 1;
        } elseif (!$proposal['expedite_approver2_id']) {
            $stmt = $pdo->prepare("
                UPDATE proposals
                SET expedite_approver2_id = ?,
                    expedite_approved_at2 = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$user['member_id'], $proposal_id]);
            $approver_number = 2;
        } else {
            return ['success' => false, 'error' => 'Wartezeitverkürzung bereits vollständig genehmigt'];
        }

        // Change-Log
        if (PROPOSAL_TRACK_CHANGES) {
            log_proposal_change($pdo, $proposal_id, $user['member_id'], 'expedite_approved',
                "Wartezeitverkürzung genehmigt (Genehmigung $approver_number von " . PROPOSAL_EXPEDITE_APPROVERS_REQUIRED . ")");
        }

        return [
            'success' => true,
            'approver_number' => $approver_number,
            'required' => PROPOSAL_EXPEDITE_APPROVERS_REQUIRED
        ];

    } catch (Exception $e) {
        error_log("Error approving expedite: " . $e->getMessage());
        return ['success' => false, 'error' => 'Fehler bei Genehmigung'];
    }
}

// ============================================
// ATTACHMENTS
// ============================================

/**
 * Fügt einen Anhang zu einem Antrag hinzu
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param array $user
 * @param string $file_path Lokaler Pfad oder URL
 * @param string $description
 * @param string $type 'file' oder 'url'
 * @return array
 */
function add_proposal_attachment($pdo, $proposal_id, $user, $file_path, $description, $type = 'file') {
    try {
        // Prüfen ob Proposal existiert
        $proposal = get_proposal_by_id($pdo, $proposal_id);
        if (!$proposal) {
            return ['success' => false, 'error' => 'Antrag nicht gefunden'];
        }

        // Anzahl Anhänge prüfen
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM proposal_attachments WHERE proposal_id = ?");
        $count_stmt->execute([$proposal_id]);
        $count = $count_stmt->fetchColumn();

        if ($count >= PROPOSAL_MAX_ATTACHMENTS) {
            return ['success' => false, 'error' => 'Maximale Anzahl Anhänge erreicht'];
        }

        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO proposal_attachments (proposal_id, file_path, file_url, description, uploaded_by, uploaded_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        if ($type === 'url') {
            $stmt->execute([$proposal_id, null, $file_path, $description, $user['member_id']]);
        } else {
            $stmt->execute([$proposal_id, $file_path, null, $description, $user['member_id']]);
        }

        $attachment_id = $pdo->lastInsertId();

        return ['success' => true, 'attachment_id' => $attachment_id];

    } catch (Exception $e) {
        error_log("Error adding attachment: " . $e->getMessage());
        return ['success' => false, 'error' => 'Fehler beim Hinzufügen des Anhangs'];
    }
}

/**
 * Lädt alle Anhänge eines Antrags
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @return array
 */
function get_proposal_attachments($pdo, $proposal_id) {
    $stmt = $pdo->prepare("
        SELECT a.*, m.first_name, m.last_name
        FROM proposal_attachments a
        LEFT JOIN svmembers m ON a.uploaded_by = m.member_id
        WHERE a.proposal_id = ?
        ORDER BY a.uploaded_at ASC
    ");
    $stmt->execute([$proposal_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// COMMENTS
// ============================================

/**
 * Fügt einen Kommentar zu einem Antrag hinzu
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param array $user
 * @param string $comment
 * @return array
 */
function add_proposal_comment($pdo, $proposal_id, $user, $comment) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO proposal_comments (proposal_id, commenter_id, comment, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$proposal_id, $user['member_id'], $comment]);

        $comment_id = $pdo->lastInsertId();

        return ['success' => true, 'comment_id' => $comment_id];

    } catch (Exception $e) {
        error_log("Error adding comment: " . $e->getMessage());
        return ['success' => false, 'error' => 'Fehler beim Hinzufügen des Kommentars'];
    }
}

/**
 * Lädt alle Kommentare eines Antrags
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @return array
 */
function get_proposal_comments($pdo, $proposal_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, m.first_name, m.last_name
        FROM proposal_comments c
        LEFT JOIN svmembers m ON c.commenter_id = m.member_id
        WHERE c.proposal_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$proposal_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Generiert eine neue Antragsnummer
 *
 * @param PDO $pdo
 * @param string $status Status des Antrags
 * @return string Antragsnummer (z.B. "A-2024-001")
 */
function generate_proposal_number($pdo, $status) {
    global $PROPOSAL_NUMBER_PREFIXES;

    $prefix = $PROPOSAL_NUMBER_PREFIXES[$status] ?? 'D';
    $year = date('Y');

    // Höchste Nummer des Jahres finden
    $stmt = $pdo->prepare("
        SELECT proposal_number
        FROM proposals
        WHERE proposal_number LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute(["$prefix-$year-%"]);
    $last_number = $stmt->fetchColumn();

    if ($last_number && preg_match('/-(\d+)$/', $last_number, $matches)) {
        $counter = intval($matches[1]) + 1;
    } else {
        $counter = 1;
    }

    return sprintf("%s-%s-%03d", $prefix, $year, $counter);
}

/**
 * Protokolliert eine Änderung an einem Antrag
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @param int|null $user_id User der die Änderung durchgeführt hat (null = System)
 * @param string $change_type Art der Änderung
 * @param string $description Beschreibung
 */
function log_proposal_change($pdo, $proposal_id, $user_id, $change_type, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO proposal_changes (proposal_id, changed_by, change_type, description, changed_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$proposal_id, $user_id, $change_type, $description]);
    } catch (Exception $e) {
        error_log("Error logging proposal change: " . $e->getMessage());
    }
}

/**
 * Lädt Änderungshistorie eines Antrags
 *
 * @param PDO $pdo
 * @param int $proposal_id
 * @return array
 */
function get_proposal_changes($pdo, $proposal_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, m.first_name, m.last_name
        FROM proposal_changes c
        LEFT JOIN svmembers m ON c.changed_by = m.member_id
        WHERE c.proposal_id = ?
        ORDER BY c.changed_at DESC
    ");
    $stmt->execute([$proposal_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Prüft ob die Wartezeit für einen Antrag abgelaufen ist
 *
 * @param array $proposal Proposal-Daten
 * @return bool
 */
function is_waiting_period_over($proposal) {
    if ($proposal['status'] !== 'editing') {
        return false;
    }

    // Expedite genehmigt?
    if ($proposal['expedite_approver1_id'] && $proposal['expedite_approver2_id']) {
        return true;
    }

    // Wartezeit prüfen
    $waiting_period = PROPOSAL_WAITING_PERIOD_DAYS;
    $finalized_at = strtotime($proposal['finalized_at']);
    $now = time();
    $days_passed = ($now - $finalized_at) / 86400;

    return $days_passed >= $waiting_period;
}
?>
