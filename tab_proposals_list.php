<?php
/**
 * tab_proposals_list.php - Antrags- und Beschlussverwaltung (Präsentation)
 * Erstellt: 2025-05-11
 *
 * Zeigt Antrags-Liste, Filteroptionen und Erstellungs-Formular
 * Nur Darstellung - alle Verarbeitungen in process_proposals.php
 */

// Dependencies
require_once 'proposals_functions.php';
require_once 'adapters/ProposalPermissionAdapter.php';

// Member-Adapter und Permission-Adapter initialisieren
$memberAdapter = get_member_adapter($pdo);
$permAdapter = new ProposalPermissionAdapter($pdo, $memberAdapter);

// Filter aus URL laden
$filter_status = $_GET['filter_status'] ?? 'all';
$filter_department = $_GET['filter_department'] ?? 'all';
$filter_decision_type = $_GET['filter_decision_type'] ?? 'all';
$view = $_GET['view'] ?? 'list'; // 'list', 'my', 'voting', 'archived'

// Proposals laden basierend auf View
$filters = [];

switch ($view) {
    case 'my':
        // Nur eigene Anträge
        $filters['submitter_id'] = $current_user['member_id'];
        break;

    case 'voting':
        // Nur Anträge im Voting-Status
        $filters['status'] = 'voting';
        break;

    case 'archived':
        // Abgeschlossene Anträge
        $filters['status'] = ['approved', 'rejected', 'withdrawn'];
        break;

    case 'list':
    default:
        // Alle aktiven Anträge
        $filters['status'] = ['draft', 'editing', 'voting'];
        break;
}

// Weitere Filter anwenden
if ($filter_status !== 'all') {
    $filters['status'] = $filter_status;
}
if ($filter_department !== 'all') {
    $filters['department_id'] = $filter_department;
}
if ($filter_decision_type !== 'all') {
    $filters['decision_type'] = $filter_decision_type;
}

// Proposals laden
$proposals = get_proposals($pdo, $filters, $current_user);

// Departments für Filter laden
$departments_stmt = $pdo->query("SELECT * FROM svbproposal_departments WHERE is_active = 1 ORDER BY name");
$departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Voting-Statistik für aktuellen User
$voting_stats = $permAdapter->get_voting_stats($current_user);

// Benachrichtigungsmodul laden
require_once 'module_notifications.php';
?>

<style>
/* Proposal Cards */
.proposal-card {
    background: white;
    border: 1px solid #ddd;
    border-left: 4px solid #2196F3;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: box-shadow 0.2s;
}

.proposal-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.proposal-card.status-draft { border-left-color: #9E9E9E; }
.proposal-card.status-editing { border-left-color: #FF9800; }
.proposal-card.status-voting { border-left-color: #2196F3; }
.proposal-card.status-approved { border-left-color: #4CAF50; }
.proposal-card.status-rejected { border-left-color: #F44336; }
.proposal-card.status-withdrawn { border-left-color: #757575; }

.proposal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.proposal-number {
    font-size: 0.9em;
    color: #666;
    font-weight: 600;
    font-family: monospace;
}

.proposal-status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge-draft { background: #EEEEEE; color: #424242; }
.status-badge-editing { background: #FFF3E0; color: #E65100; }
.status-badge-voting { background: #E3F2FD; color: #1565C0; }
.status-badge-approved { background: #E8F5E9; color: #2E7D32; }
.status-badge-rejected { background: #FFEBEE; color: #C62828; }
.status-badge-withdrawn { background: #F5F5F5; color: #616161; }

.proposal-title {
    font-size: 1.1em;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.proposal-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.9em;
    color: #666;
    margin-bottom: 12px;
}

.proposal-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
}

.proposal-description {
    color: #555;
    line-height: 1.5;
    margin-bottom: 12px;
}

.proposal-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.9em;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #2196F3;
    color: white;
}

.btn-primary:hover {
    background: #1976D2;
}

.btn-success {
    background: #4CAF50;
    color: white;
}

.btn-success:hover {
    background: #388E3C;
}

.btn-secondary {
    background: #757575;
    color: white;
}

.btn-secondary:hover {
    background: #616161;
}

.btn-danger {
    background: #F44336;
    color: white;
}

.btn-danger:hover {
    background: #D32F2F;
}

/* Filter Bar */
.filter-bar {
    background: #f5f5f5;
    padding: 16px;
    border-radius: 4px;
    margin-bottom: 20px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-group label {
    font-size: 0.85em;
    font-weight: 600;
    color: #666;
}

.filter-group select {
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}

/* View Tabs */
.view-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.view-tab {
    padding: 12px 24px;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 500;
    color: #666;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.view-tab:hover {
    color: #2196F3;
}

.view-tab.active {
    color: #2196F3;
    border-bottom-color: #2196F3;
}

.view-tab .badge {
    background: #f44336;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.8em;
    margin-left: 6px;
}

/* Form Styles */
.form-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.form-section h3 {
    margin-top: 0;
    margin-bottom: 16px;
}

.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #333;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-group small {
    display: block;
    margin-top: 4px;
    color: #666;
    font-size: 0.85em;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-icon {
    font-size: 4em;
    margin-bottom: 16px;
}

/* Stats Box */
.stats-box {
    background: #E3F2FD;
    border-left: 4px solid #2196F3;
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.stats-box strong {
    color: #1565C0;
}
</style>

<!-- BENACHRICHTIGUNGEN -->
<?php render_user_notifications($pdo, $current_user['member_id']); ?>

<h2>📋 Anträge & Beschlüsse</h2>

<?php if (isset($_GET['success'])): ?>
    <div class="message">
        <?php
        switch($_GET['success']) {
            case 'created': echo '✅ Antrag erfolgreich erstellt!'; break;
            case 'updated': echo '✅ Antrag erfolgreich aktualisiert!'; break;
            case 'finalized': echo '✅ Antrag erfolgreich finalisiert!'; break;
            case 'withdrawn': echo '✅ Antrag erfolgreich zurückgezogen!'; break;
            case 'voted': echo '✅ Stimme erfolgreich abgegeben!'; break;
            case 'expedite_approved': echo '✅ Wartezeitverkürzung genehmigt!'; break;
            default: echo '✅ Aktion erfolgreich durchgeführt!';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="error-message">
        <?php
        $error_msg = $_GET['error_msg'] ?? '';
        switch($_GET['error']) {
            case 'permission': echo '❌ Keine Berechtigung für diese Aktion.'; break;
            case 'not_found': echo '❌ Antrag nicht gefunden.'; break;
            case 'invalid_data': echo '❌ Ungültige Daten: ' . htmlspecialchars($error_msg); break;
            case 'already_voted': echo '❌ Sie haben bereits abgestimmt.'; break;
            case 'waiting_period': echo '❌ Wartezeit noch nicht abgelaufen: ' . htmlspecialchars($error_msg); break;
            default: echo '❌ Ein Fehler ist aufgetreten: ' . htmlspecialchars($error_msg);
        }
        ?>
    </div>
<?php endif; ?>

<!-- Voting Stats -->
<?php if ($voting_stats['pending'] > 0): ?>
<div class="stats-box">
    📊 <strong>Sie haben <?php echo $voting_stats['pending']; ?> ausstehende Abstimmung<?php echo $voting_stats['pending'] != 1 ? 'en' : ''; ?>!</strong>
    <a href="?tab=proposals&view=voting" style="margin-left: 12px; text-decoration: underline;">Jetzt abstimmen →</a>
</div>
<?php endif; ?>

<!-- View Tabs -->
<div class="view-tabs">
    <button class="view-tab <?php echo $view === 'list' ? 'active' : ''; ?>"
            onclick="window.location.href='?tab=proposals&view=list'">
        📋 Alle Anträge
    </button>
    <button class="view-tab <?php echo $view === 'my' ? 'active' : ''; ?>"
            onclick="window.location.href='?tab=proposals&view=my'">
        👤 Meine Anträge
    </button>
    <button class="view-tab <?php echo $view === 'voting' ? 'active' : ''; ?>"
            onclick="window.location.href='?tab=proposals&view=voting'">
        🗳️ Abstimmungen
        <?php if ($voting_stats['pending'] > 0): ?>
            <span class="badge"><?php echo $voting_stats['pending']; ?></span>
        <?php endif; ?>
    </button>
    <button class="view-tab <?php echo $view === 'archived' ? 'active' : ''; ?>"
            onclick="window.location.href='?tab=proposals&view=archived'">
        📁 Archiv
    </button>
</div>

<!-- Filter Bar -->
<?php if ($view === 'list' || $view === 'archived'): ?>
<div class="filter-bar">
    <div class="filter-group">
        <label>Status:</label>
        <select id="filter-status" onchange="applyFilters()">
            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Alle</option>
            <option value="draft" <?php echo $filter_status === 'draft' ? 'selected' : ''; ?>>Entwurf</option>
            <option value="editing" <?php echo $filter_status === 'editing' ? 'selected' : ''; ?>>In Bearbeitung</option>
            <option value="voting" <?php echo $filter_status === 'voting' ? 'selected' : ''; ?>>In Abstimmung</option>
            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Angenommen</option>
            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Abgelehnt</option>
            <option value="withdrawn" <?php echo $filter_status === 'withdrawn' ? 'selected' : ''; ?>>Zurückgezogen</option>
        </select>
    </div>

    <div class="filter-group">
        <label>Ressort:</label>
        <select id="filter-department" onchange="applyFilters()">
            <option value="all">Alle</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?php echo htmlspecialchars($dept['department_id']); ?>"
                        <?php echo $filter_department === $dept['department_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($dept['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label>Beschlussart:</label>
        <select id="filter-decision-type" onchange="applyFilters()">
            <option value="all">Alle</option>
            <option value="single" <?php echo $filter_decision_type === 'single' ? 'selected' : ''; ?>>Verfügung</option>
            <option value="department" <?php echo $filter_decision_type === 'department' ? 'selected' : ''; ?>>Ressortbeschluss</option>
            <option value="board" <?php echo $filter_decision_type === 'board' ? 'selected' : ''; ?>>Vorstandsbeschluss</option>
        </select>
    </div>

    <button class="btn-small btn-secondary" onclick="resetFilters()">Filter zurücksetzen</button>
</div>
<?php endif; ?>

<!-- Neuer Antrag Button -->
<?php if ($permAdapter->can_create_proposal($current_user)): ?>
<div style="margin-bottom: 20px;">
    <button class="accordion-button" onclick="toggleAccordion(this)">
        ➕ Neuen Antrag erstellen
    </button>
    <div class="accordion-content">
        <form method="POST" action="process_proposals.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="form-section">
                <h3>Antragsdaten</h3>

                <div class="form-group">
                    <label>Titel: *</label>
                    <input type="text" name="title" required maxlength="500">
                    <small>Kurze, prägnante Beschreibung des Antrags</small>
                </div>

                <div class="form-group">
                    <label>Beschreibung: *</label>
                    <textarea name="description" required></textarea>
                    <small>Detaillierte Beschreibung des Antrags und der beantragten Maßnahmen</small>
                </div>

                <div class="form-group">
                    <label>Begründung:</label>
                    <textarea name="justification"></textarea>
                    <small>Begründung für die Notwendigkeit des Antrags</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Organisatorisches</h3>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Ressort:</label>
                        <select name="department_id">
                            <option value="">-- Kein Ressort --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department_id']); ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Verantwortliche Person:</label>
                        <input type="text" name="responsible_person">
                        <small>Wer wird den Antrag umsetzen?</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Sichtbarkeit:</label>
                    <select name="visibility">
                        <option value="public">Öffentlich (alle Mitglieder)</option>
                        <option value="leadership">Führungskreis</option>
                        <option value="board">Nur Vorstand</option>
                        <option value="draft">Nur ich (Entwurf)</option>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <h3>Finanzielle Auswirkungen</h3>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Betrag (EUR):</label>
                        <input type="number" name="financial_amount" step="0.01" min="0" value="0">
                        <small>Finanzieller Umfang des Antrags</small>
                    </div>

                    <div class="form-group">
                        <label>Beschlussart:</label>
                        <select name="decision_type">
                            <option value="">Automatisch bestimmen</option>
                            <option value="single">Verfügung (&lt; 600 EUR)</option>
                            <option value="department">Ressortbeschluss (600-3000 EUR)</option>
                            <option value="board">Vorstandsbeschluss (&gt; 3000 EUR)</option>
                        </select>
                        <small>Wird automatisch anhand des Betrags bestimmt</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Finanzielle Beschreibung:</label>
                    <textarea name="financial_description" rows="3"></textarea>
                    <small>Details zu Kosten, Budget-Quellen, etc.</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Weitere Auswirkungen</h3>

                <div class="form-group">
                    <label>Personelle Auswirkungen:</label>
                    <textarea name="personnel_impact" rows="3"></textarea>
                    <small>Auswirkungen auf Personal, Arbeitsaufwand, etc.</small>
                </div>

                <div class="form-group">
                    <label>Materielle Auswirkungen:</label>
                    <textarea name="material_impact" rows="3"></textarea>
                    <small>Benötigte Ressourcen, Materialien, etc.</small>
                </div>
            </div>

            <?php if (PROPOSAL_ALLOW_EXPEDITE): ?>
            <div class="form-section">
                <h3>Wartezeitverkürzung (optional)</h3>

                <div class="form-group">
                    <label>Begründung für verkürzte Wartezeit:</label>
                    <textarea name="expedite_justification" rows="3"></textarea>
                    <small>
                        Falls Sie eine Verkürzung der <?php echo PROPOSAL_WAITING_PERIOD_DAYS; ?>-tägigen Wartezeit beantragen möchten,
                        begründen Sie dies hier. Erfordert Zustimmung von <?php echo PROPOSAL_EXPEDITE_APPROVERS_REQUIRED; ?> Vorstandsmitgliedern.
                    </small>
                </div>
            </div>
            <?php endif; ?>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn-small btn-secondary" onclick="toggleAccordion(this.closest('.accordion-content').previousElementSibling)">
                    Abbrechen
                </button>
                <button type="submit" class="btn-small btn-primary">
                    Antrag erstellen
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Proposal List -->
<?php if (empty($proposals)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <h3>Keine Anträge gefunden</h3>
        <p>
            <?php if ($view === 'my'): ?>
                Sie haben noch keine Anträge erstellt.
            <?php elseif ($view === 'voting'): ?>
                Aktuell gibt es keine Anträge zur Abstimmung.
            <?php else: ?>
                Es sind keine Anträge vorhanden, die Ihren Filterkriterien entsprechen.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <?php foreach ($proposals as $proposal): ?>
        <?php
        $status_class = 'status-' . $proposal['status'];
        $status_badge_class = 'status-badge-' . $proposal['status'];

        // Status-Labels
        $status_labels = [
            'draft' => 'Entwurf',
            'editing' => 'In Bearbeitung',
            'voting' => 'In Abstimmung',
            'approved' => 'Angenommen',
            'rejected' => 'Abgelehnt',
            'withdrawn' => 'Zurückgezogen'
        ];

        $decision_type_labels = [
            'single' => 'Verfügung',
            'department' => 'Ressortbeschluss',
            'board' => 'Vorstandsbeschluss'
        ];

        // Permission checks
        $can_edit = $permAdapter->can_edit_proposal($current_user, $proposal);
        $can_vote = $permAdapter->can_vote($current_user, $proposal);
        $can_withdraw = $permAdapter->can_withdraw_proposal($current_user, $proposal);
        $has_voted = $permAdapter->has_voted($current_user, $proposal['id']);
        $can_approve_expedite = $permAdapter->can_approve_expedite($current_user, $proposal);

        // Expedite status
        $expedite_approved = ($proposal['expedite_approver1_id'] && $proposal['expedite_approver2_id']);
        ?>

        <div class="proposal-card <?php echo $status_class; ?>">
            <div class="proposal-header">
                <span class="proposal-number"><?php echo htmlspecialchars($proposal['proposal_number']); ?></span>
                <span class="proposal-status-badge <?php echo $status_badge_class; ?>">
                    <?php echo $status_labels[$proposal['status']]; ?>
                </span>
            </div>

            <div class="proposal-title">
                <?php echo htmlspecialchars($proposal['title']); ?>
            </div>

            <div class="proposal-meta">
                <div class="proposal-meta-item">
                    👤 <?php echo htmlspecialchars($proposal['submitter_name'] ?? 'Unbekannt'); ?>
                </div>
                <div class="proposal-meta-item">
                    📅 <?php echo date('d.m.Y', strtotime($proposal['submitted_at'])); ?>
                </div>
                <div class="proposal-meta-item">
                    📊 <?php echo $decision_type_labels[$proposal['decision_type']]; ?>
                </div>
                <?php if ($proposal['financial_amount'] > 0): ?>
                    <div class="proposal-meta-item">
                        💰 <?php echo number_format($proposal['financial_amount'], 2, ',', '.'); ?> EUR
                    </div>
                <?php endif; ?>
                <?php if ($proposal['department_id']): ?>
                    <div class="proposal-meta-item">
                        🏢 <?php
                        $dept = array_filter($departments, fn($d) => $d['department_id'] === $proposal['department_id']);
                        echo $dept ? htmlspecialchars(reset($dept)['name']) : $proposal['department_id'];
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="proposal-description">
                <?php
                $desc = $proposal['description'];
                echo htmlspecialchars(strlen($desc) > 200 ? substr($desc, 0, 200) . '...' : $desc);
                ?>
            </div>

            <?php if ($proposal['status'] === 'voting' && $proposal['voting_deadline']): ?>
                <div style="background: #FFF3E0; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; font-size: 0.9em;">
                    ⏰ Abstimmung endet: <strong><?php echo date('d.m.Y H:i', strtotime($proposal['voting_deadline'])); ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($proposal['expedite_justification'] && !$expedite_approved): ?>
                <div style="background: #E3F2FD; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; font-size: 0.9em;">
                    ⚡ Wartezeitverkürzung beantragt
                    <?php if ($proposal['expedite_approver1_id']): ?>
                        (1/<?php echo PROPOSAL_EXPEDITE_APPROVERS_REQUIRED; ?> genehmigt)
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="proposal-actions">
                <a href="?tab=proposals&action=view&id=<?php echo $proposal['id']; ?>" class="btn-small btn-primary">
                    👁️ Details
                </a>

                <?php if ($can_edit): ?>
                    <a href="?tab=proposals&action=edit&id=<?php echo $proposal['id']; ?>" class="btn-small btn-secondary">
                        ✏️ Bearbeiten
                    </a>
                <?php endif; ?>

                <?php if ($can_vote && !$has_voted): ?>
                    <a href="?tab=proposals&action=vote&id=<?php echo $proposal['id']; ?>" class="btn-small btn-success">
                        🗳️ Abstimmen
                    </a>
                <?php endif; ?>

                <?php if ($can_vote && $has_voted): ?>
                    <span class="btn-small" style="background: #E8F5E9; color: #2E7D32; cursor: default;">
                        ✓ Abgestimmt
                    </span>
                <?php endif; ?>

                <?php if ($can_approve_expedite && $proposal['expedite_justification'] && !$expedite_approved): ?>
                    <form method="POST" action="process_proposals.php" style="display: inline;">
                        <input type="hidden" name="action" value="approve_expedite">
                        <input type="hidden" name="proposal_id" value="<?php echo $proposal['id']; ?>">
                        <button type="submit" class="btn-small btn-success">
                            ⚡ Wartezeitverkürzung genehmigen
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($can_withdraw): ?>
                    <form method="POST" action="process_proposals.php" style="display: inline;"
                          onsubmit="return confirm('Antrag wirklich zurückziehen?');">
                        <input type="hidden" name="action" value="withdraw">
                        <input type="hidden" name="proposal_id" value="<?php echo $proposal['id']; ?>">
                        <button type="submit" class="btn-small btn-danger">
                            🚫 Zurückziehen
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
function applyFilters() {
    const status = document.getElementById('filter-status').value;
    const department = document.getElementById('filter-department').value;
    const decisionType = document.getElementById('filter-decision-type').value;
    const view = '<?php echo $view; ?>';

    let url = '?tab=proposals&view=' + view;
    if (status !== 'all') url += '&filter_status=' + status;
    if (department !== 'all') url += '&filter_department=' + department;
    if (decisionType !== 'all') url += '&filter_decision_type=' + decisionType;

    window.location.href = url;
}

function resetFilters() {
    const view = '<?php echo $view; ?>';
    window.location.href = '?tab=proposals&view=' + view;
}

function toggleAccordion(button) {
    const content = button.nextElementSibling;
    const isOpen = content.style.maxHeight;

    if (isOpen) {
        content.style.maxHeight = null;
        button.classList.remove('active');
    } else {
        content.style.maxHeight = content.scrollHeight + 'px';
        button.classList.add('active');
    }
}
</script>
