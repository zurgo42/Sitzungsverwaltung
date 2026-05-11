<?php
/**
 * ProposalPermissionAdapter.php - Berechtigungsprüfung für Anträge & Beschlüsse
 *
 * Diese Klasse verwaltet alle Berechtigungen rund um Anträge und Beschlüsse.
 * Arbeitet mit MemberAdapter zusammen, um rollenbasierte Berechtigungen zu prüfen.
 */

class ProposalPermissionAdapter {
    private $pdo;
    private $memberAdapter;

    /**
     * @param PDO $pdo Datenbankverbindung
     * @param MemberAdapterInterface $memberAdapter Member-Adapter für Rollenzugriff
     */
    public function __construct($pdo, $memberAdapter) {
        $this->pdo = $pdo;
        $this->memberAdapter = $memberAdapter;
    }

    /**
     * Prüft ob User Anträge erstellen darf
     *
     * @param array $user User-Array vom MemberAdapter
     * @return bool
     */
    public function can_create_proposal($user) {
        if (!$user || !isset($user['member_id'])) {
            return false;
        }

        // Inaktive User dürfen keine Anträge erstellen
        if (isset($user['is_active']) && !$user['is_active']) {
            return false;
        }

        // Alle aktiven Mitglieder dürfen Anträge erstellen
        return true;
    }

    /**
     * Prüft ob User einen Antrag bearbeiten darf
     *
     * Berechtigt sind:
     * - Antragsteller selbst (nur im Status 'draft' oder 'editing')
     * - Admins (immer)
     *
     * @param array $user User-Array vom MemberAdapter
     * @param array $proposal Proposal-Array aus der DB
     * @return bool
     */
    public function can_edit_proposal($user, $proposal) {
        if (!$user || !$proposal) {
            return false;
        }

        // Admins dürfen immer bearbeiten
        if ($this->is_admin($user)) {
            return true;
        }

        // Antragsteller darf nur im Status 'draft' oder 'editing' bearbeiten
        if ($proposal['submitter_id'] == $user['member_id']) {
            return in_array($proposal['status'], ['draft', 'editing']);
        }

        return false;
    }

    /**
     * Prüft ob User einen Antrag löschen/zurückziehen darf
     *
     * @param array $user User-Array vom MemberAdapter
     * @param array $proposal Proposal-Array aus der DB
     * @return bool
     */
    public function can_delete_proposal($user, $proposal) {
        if (!$user || !$proposal) {
            return false;
        }

        // Admins dürfen immer löschen
        if ($this->is_admin($user)) {
            return true;
        }

        // Antragsteller darf nur im Status 'draft' löschen
        if ($proposal['submitter_id'] == $user['member_id']) {
            return $proposal['status'] === 'draft';
        }

        return false;
    }

    /**
     * Prüft ob User einen Antrag zurückziehen darf (Status → 'withdrawn')
     *
     * @param array $user User-Array vom MemberAdapter
     * @param array $proposal Proposal-Array aus der DB
     * @return bool
     */
    public function can_withdraw_proposal($user, $proposal) {
        if (!$user || !$proposal) {
            return false;
        }

        // Nur bei bestimmten Status möglich
        if (!in_array($proposal['status'], ['editing', 'voting'])) {
            return false;
        }

        // Admins dürfen immer zurückziehen
        if ($this->is_admin($user)) {
            return true;
        }

        // Antragsteller darf zurückziehen
        return $proposal['submitter_id'] == $user['member_id'];
    }

    /**
     * Prüft ob User einen Antrag anzeigen darf (basiert auf visibility)
     *
     * Visibility-Regeln:
     * - 'public': Alle eingeloggten User
     * - 'leadership': Führungsteam + GF + Assistenz + Vorstand
     * - 'board': Nur Vorstand + GF + Assistenz
     * - 'draft': Nur Antragsteller + Admins
     *
     * @param array $user User-Array vom MemberAdapter
     * @param array $proposal Proposal-Array aus der DB
     * @return bool
     */
    public function can_view_proposal($user, $proposal) {
        if (!$user || !$proposal) {
            return false;
        }

        // Admins sehen alles
        if ($this->is_admin($user)) {
            return true;
        }

        // Antragsteller sieht eigenen Antrag immer
        if ($proposal['submitter_id'] == $user['member_id']) {
            return true;
        }

        $visibility = $proposal['visibility'] ?? 'public';

        switch ($visibility) {
            case 'public':
                // Alle eingeloggten User
                return true;

            case 'leadership':
                // Führungsteam, GF, Assistenz, Vorstand
                return $this->is_leadership($user);

            case 'board':
                // Nur Vorstand, GF, Assistenz
                return $this->is_board_or_higher($user);

            case 'draft':
                // Nur Antragsteller (bereits oben geprüft) + Admins
                return false;

            default:
                return false;
        }
    }

    /**
     * Prüft ob User bei einem Antrag abstimmen darf
     *
     * Abstimmungsberechtigt sind:
     * - decision_type='single': Niemand (nur Freigabe durch Einzelperson)
     * - decision_type='department': Ressortleitung + Finanzverantwortliche
     * - decision_type='board': Alle Vorstandsmitglieder
     *
     * @param array $user User-Array vom MemberAdapter
     * @param array $proposal Proposal-Array aus der DB
     * @return bool
     */
    public function can_vote($user, $proposal) {
        if (!$user || !$proposal) {
            return false;
        }

        // Nur im Status 'voting' kann abgestimmt werden
        if ($proposal['status'] !== 'voting') {
            return false;
        }

        $decision_type = $proposal['decision_type'] ?? 'board';

        switch ($decision_type) {
            case 'single':
                // Keine Abstimmung, nur Einzelfreigabe
                return false;

            case 'department':
                // Ressortleitung des Departments ODER Finanzverantwortliche
                return $this->is_department_lead($user, $proposal['department_id'])
                    || $this->is_finance_approver($user);

            case 'board':
                // Alle Vorstandsmitglieder
                return $this->is_board_member($user);

            default:
                return false;
        }
    }

    /**
     * Prüft ob User Wartezeitverkürzung genehmigen darf
     *
     * Berechtigt: Vorstandsmitglieder (aber nicht der Antragsteller selbst)
     *
     * @param array $user User-Array vom MemberAdapter
     * @param array $proposal Proposal-Array aus der DB
     * @return bool
     */
    public function can_approve_expedite($user, $proposal) {
        if (!$user || !$proposal) {
            return false;
        }

        // Nur Vorstandsmitglieder
        if (!$this->is_board_member($user)) {
            return false;
        }

        // Antragsteller darf nicht seine eigene Wartezeitverkürzung genehmigen
        if ($proposal['submitter_id'] == $user['member_id']) {
            return false;
        }

        return true;
    }

    /**
     * Prüft ob User Finanzfreigabe erteilen darf
     *
     * @param array $user User-Array vom MemberAdapter
     * @return bool
     */
    public function can_approve_finance($user) {
        return $this->is_finance_approver($user);
    }

    /**
     * Prüft ob User interne/vertrauliche Anträge sehen darf
     *
     * @param array $user User-Array vom MemberAdapter
     * @return bool
     */
    public function can_view_internal($user) {
        if (!$user) {
            return false;
        }

        // is_confidential Flag aus MemberAdapter nutzen
        return ($user['is_confidential'] ?? 0) == 1;
    }

    /**
     * Prüft ob User Kommentare hinzufügen darf
     *
     * @param array $user User-Array vom MemberAdapter
     * @param array $proposal Proposal-Array aus der DB
     * @return bool
     */
    public function can_comment($user, $proposal) {
        // Wer den Antrag sehen darf, darf auch kommentieren
        return $this->can_view_proposal($user, $proposal);
    }

    /**
     * Gibt alle Departments/Ressorts zurück, für die der User verantwortlich ist
     *
     * @param array $user User-Array vom MemberAdapter
     * @return array Array von department_id Strings
     */
    public function get_user_departments($user) {
        if (!$user || !isset($user['member_id'])) {
            return [];
        }

        // Abfrage aus proposal_departments Tabelle
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT department_id
            FROM proposal_departments
            WHERE leader_id = ? AND is_active = 1
        ");
        $stmt->execute([$user['member_id']]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'department_id');
    }

    // ===================================================================
    // HELPER FUNCTIONS - Rollenprüfungen
    // ===================================================================

    /**
     * Prüft ob User Admin ist
     */
    private function is_admin($user) {
        return ($user['is_admin'] ?? 0) == 1;
    }

    /**
     * Prüft ob User Vorstandsmitglied ist
     */
    private function is_board_member($user) {
        $role = strtolower($user['role'] ?? '');
        return $role === 'vorstand';
    }

    /**
     * Prüft ob User zur Geschäftsführung gehört
     */
    private function is_management($user) {
        $role = strtolower($user['role'] ?? '');
        return $role === 'gf';
    }

    /**
     * Prüft ob User zur Assistenz gehört (GF-Assistenz oder Vorstandsassistenz)
     */
    private function is_assistant($user) {
        $role = strtolower($user['role'] ?? '');
        return $role === 'assistenz';
    }

    /**
     * Prüft ob User zum Führungsteam gehört
     */
    private function is_leadership_team($user) {
        $role = strtolower($user['role'] ?? '');
        return $role === 'fuehrungsteam';
    }

    /**
     * Prüft ob User zur erweiterten Führung gehört
     * (Vorstand, GF, Assistenz, Führungsteam)
     */
    private function is_leadership($user) {
        $role = strtolower($user['role'] ?? '');
        return in_array($role, ['vorstand', 'gf', 'assistenz', 'fuehrungsteam']);
    }

    /**
     * Prüft ob User zu Vorstand oder höher gehört
     * (Vorstand, GF, Assistenz)
     */
    private function is_board_or_higher($user) {
        $role = strtolower($user['role'] ?? '');
        return in_array($role, ['vorstand', 'gf', 'assistenz']);
    }

    /**
     * Prüft ob User Finanzverantwortlicher ist
     *
     * Finanzverantwortliche werden in der Konfiguration definiert
     * oder aus der berechtigte-Tabelle ermittelt (z.B. spezielle Rolle)
     */
    private function is_finance_approver($user) {
        if (!$user || !isset($user['member_id'])) {
            return false;
        }

        // Prüfen ob User in finance_approvers Konfiguration
        // (könnte aus config_proposals.php kommen)

        // Alternativ: Prüfe ob User eine spezielle Rolle hat
        // Für MinD: GF und Assistenz haben Finanzverantwortung
        $role = strtolower($user['role'] ?? '');
        if (in_array($role, ['gf', 'assistenz'])) {
            return true;
        }

        // Oder prüfe explizite Liste in Datenbank
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM proposal_departments
            WHERE leader_id = ? AND department_id = 'finance' AND is_active = 1
        ");
        $stmt->execute([$user['member_id']]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Prüft ob User Ressortleitung für ein bestimmtes Department ist
     *
     * @param array $user User-Array vom MemberAdapter
     * @param string $department_id Department ID
     * @return bool
     */
    private function is_department_lead($user, $department_id) {
        if (!$user || !$department_id) {
            return false;
        }

        $departments = $this->get_user_departments($user);
        return in_array($department_id, $departments);
    }

    // ===================================================================
    // BULK OPERATIONS - Für Listen und Übersichten
    // ===================================================================

    /**
     * Filtert eine Liste von Proposals nach Sichtbarkeitsrechten
     *
     * @param array $user User-Array vom MemberAdapter
     * @param array $proposals Array von Proposal-Arrays
     * @return array Gefilterte Liste
     */
    public function filter_viewable_proposals($user, $proposals) {
        if (!$user || !is_array($proposals)) {
            return [];
        }

        return array_filter($proposals, function($proposal) use ($user) {
            return $this->can_view_proposal($user, $proposal);
        });
    }

    /**
     * Gibt alle Proposals zurück, bei denen der User abstimmen darf
     *
     * @param array $user User-Array vom MemberAdapter
     * @return array Array von Proposal-IDs
     */
    public function get_votable_proposal_ids($user) {
        if (!$user || !isset($user['member_id'])) {
            return [];
        }

        // Je nach Rolle unterschiedliche Proposals
        $conditions = [];
        $params = [];

        if ($this->is_board_member($user)) {
            // Vorstand: Alle board-Beschlüsse
            $conditions[] = "decision_type = 'board'";
        }

        if ($this->is_finance_approver($user)) {
            // Finanzverantwortliche: Alle department-Beschlüsse
            $conditions[] = "decision_type = 'department'";
        }

        $departments = $this->get_user_departments($user);
        if (!empty($departments)) {
            // Ressortleitung: Beschlüsse des eigenen Ressorts
            $placeholders = str_repeat('?,', count($departments) - 1) . '?';
            $conditions[] = "(decision_type = 'department' AND department_id IN ($placeholders))";
            $params = array_merge($params, $departments);
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = "
            SELECT id
            FROM proposals
            WHERE status = 'voting'
              AND (" . implode(' OR ', $conditions) . ")
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    /**
     * Prüft ob User bereits bei einem Proposal abgestimmt hat
     *
     * @param array $user User-Array vom MemberAdapter
     * @param int $proposal_id Proposal ID
     * @return bool
     */
    public function has_voted($user, $proposal_id) {
        if (!$user || !$proposal_id) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM proposal_votes
            WHERE proposal_id = ? AND voter_id = ?
        ");
        $stmt->execute([$proposal_id, $user['member_id']]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Gibt Statistik über Voting-Status für einen User zurück
     *
     * @param array $user User-Array vom MemberAdapter
     * @return array ['pending' => int, 'voted' => int]
     */
    public function get_voting_stats($user) {
        $votable_ids = $this->get_votable_proposal_ids($user);

        if (empty($votable_ids)) {
            return ['pending' => 0, 'voted' => 0];
        }

        $placeholders = str_repeat('?,', count($votable_ids) - 1) . '?';

        // Zähle bereits abgestimmte
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT proposal_id)
            FROM proposal_votes
            WHERE proposal_id IN ($placeholders) AND voter_id = ?
        ");
        $stmt->execute([...$votable_ids, $user['member_id']]);
        $voted = $stmt->fetchColumn();

        return [
            'pending' => count($votable_ids) - $voted,
            'voted' => $voted
        ];
    }
}
?>
