<?php
/**
 * MemberAdapter.php - Abstrakte Schnittstelle für Mitgliederdaten
 *
 * Diese Klasse definiert die Schnittstelle, die alle Member-Adapter implementieren müssen.
 * Ermöglicht flexible Anbindung verschiedener Datenquellen.
 */

interface MemberAdapterInterface {
    public function getMemberById($id);
    public function getAllMembers();
    public function getMemberByEmail($email);
    public function getMemberByMembershipNumber($mnr);  // NEU für SSO
    public function createMember($data);
    public function updateMember($id, $data);
    public function deleteMember($id);
    public function authenticate($email, $password);
}

/**
 * StandardMemberAdapter - Adapter für die "members" Tabelle
 */
class StandardMemberAdapter implements MemberAdapterInterface {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getMemberById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM members WHERE member_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllMembers() {
        return $this->pdo->query("SELECT * FROM members ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMemberByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM members WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMemberByMembershipNumber($mnr) {
        $stmt = $this->pdo->prepare("SELECT * FROM members WHERE membership_number = ?");
        $stmt->execute([$mnr]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createMember($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO members (first_name, last_name, email, password_hash, role, is_admin, is_confidential)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['password_hash'],
            $data['role'],
            $data['is_admin'] ?? 0,
            $data['is_confidential'] ?? 0
        ]);
        return $this->pdo->lastInsertId();
    }

    public function updateMember($id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE members
            SET first_name = ?, last_name = ?, email = ?, role = ?, is_admin = ?, is_confidential = ?
            WHERE member_id = ?
        ");
        return $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['role'],
            $data['is_admin'] ?? 0,
            $data['is_confidential'] ?? 0,
            $id
        ]);
    }

    public function deleteMember($id) {
        $stmt = $this->pdo->prepare("DELETE FROM members WHERE member_id = ?");
        return $stmt->execute([$id]);
    }

    public function authenticate($email, $password) {
        $member = $this->getMemberByEmail($email);
        if ($member && password_verify($password, $member['password_hash'])) {
            return $member;
        }
        return false;
    }
}

/**
 * BerechtigteAdapter - Adapter für Ihre "berechtigte" Tabelle
 *
 * Mappt die Felder der "berechtigte" Tabelle auf die Standard-Struktur
 */
class BerechtigteAdapter implements MemberAdapterInterface {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Konvertiert ein berechtigte-Array in das Standard-Format
     */
    private function mapToStandard($row) {
        if (!$row) return null;

        $funktion = $row['Funktion'] ?? '';
        $aktiv = $row['aktiv'] ?? 0;

        return [
            'member_id' => $row['ID'],
            'membership_number' => $row['MNr'],
            'first_name' => $row['Vorname'],
            'last_name' => $row['Name'],
            'email' => $row['eMail'],
            'role' => $this->mapRole($funktion, $aktiv),
            'is_admin' => $this->isAdmin($funktion, $row['MNr']),
            'is_confidential' => $this->isConfidential($funktion, $aktiv),
            'password_hash' => '', // Kein Passwort bei SSO
            'created_at' => $row['angelegt'] ?? null,
            'role_priority' => $this->getRolePriority($funktion, $aktiv) // Für Sortierung
        ];
    }

    /**
     * Mappt Funktion und aktiv auf Rollenbeschreibung
     *
     * Regeln:
     * - aktiv=19 → Vorstand
     * - Funktion=GF → Geschäftsführung
     * - Funktion=SV → Assistenz
     * - Funktion=RL → Führungsteam
     * - Funktion=AD → Mitglied
     * - Funktion=FP → Mitglied
     */
    private function mapRole($funktion, $aktiv) {
        // Vorstand hat höchste Priorität
        if ($aktiv == 19) {
            return 'Vorstand';
        }

        // Dann Funktions-basierte Rollen
        $roleMapping = [
            'GF' => 'Geschäftsführung',
            'SV' => 'Assistenz',
            'RL' => 'Führungsteam',
            'AD' => 'Mitglied',
            'FP' => 'Mitglied'
        ];

        return $roleMapping[$funktion] ?? 'Mitglied';
    }

    /**
     * Gibt Sortier-Priorität für Rollen zurück
     * Niedriger = höhere Priorität (oben)
     */
    private function getRolePriority($funktion, $aktiv) {
        if ($aktiv == 19) return 1; // Vorstand

        $priorityMapping = [
            'GF' => 2, // Geschäftsführung
            'SV' => 3, // Assistenz
            'RL' => 4, // Führungsteam
            'AD' => 5, // Mitglied
            'FP' => 5  // Mitglied
        ];

        return $priorityMapping[$funktion] ?? 6;
    }

    /**
     * Prüft Admin-Rechte
     * Admin wenn: Funktion=GF ODER Funktion=SV ODER MNr='0495018'
     */
    private function isAdmin($funktion, $mnr) {
        return (in_array($funktion, ['GF', 'SV']) || $mnr == '0495018') ? 1 : 0;
    }

    /**
     * Prüft vertraulichen Zugriff
     * Vertraulich wenn: aktiv=19 ODER Funktion=GF ODER Funktion=SV
     */
    private function isConfidential($funktion, $aktiv) {
        return ($aktiv == 19 || in_array($funktion, ['GF', 'SV'])) ? 1 : 0;
    }

    /**
     * Prüft ob ein Datensatz inkludiert werden soll
     * Filter: aktiv > 17 ODER Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')
     */
    private function shouldInclude($row) {
        $aktiv = $row['aktiv'] ?? 0;
        $funktion = $row['Funktion'] ?? '';

        return ($aktiv > 17) || in_array($funktion, ['RL', 'SV', 'AD', 'FP', 'GF']);
    }

    public function getMemberById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM berechtigte WHERE ID = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $this->shouldInclude($row)) {
            return $this->mapToStandard($row);
        }
        return null;
    }

    public function getAllMembers() {
        $rows = $this->pdo->query("
            SELECT * FROM berechtigte
            ORDER BY Name, Vorname
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Filter anwenden
        $filtered = array_filter($rows, [$this, 'shouldInclude']);

        // Zu Standard-Format konvertieren
        $members = array_map([$this, 'mapToStandard'], $filtered);

        // Nach role_priority sortieren, dann nach Name
        usort($members, function($a, $b) {
            if ($a['role_priority'] !== $b['role_priority']) {
                return $a['role_priority'] - $b['role_priority'];
            }
            return strcmp($a['last_name'], $b['last_name']);
        });

        return $members;
    }

    public function getMemberByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM berechtigte WHERE eMail = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $this->shouldInclude($row)) {
            return $this->mapToStandard($row);
        }
        return null;
    }

    public function getMemberByMembershipNumber($mnr) {
        $stmt = $this->pdo->prepare("SELECT * FROM berechtigte WHERE MNr = ?");
        $stmt->execute([$mnr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $this->shouldInclude($row)) {
            return $this->mapToStandard($row);
        }
        return null;
    }

    public function createMember($data) {
        // Implementierung für Ihre Tabelle
        $stmt = $this->pdo->prepare("
            INSERT INTO berechtigte (MNr, Vorname, Name, eMail, Funktion, aktiv, angelegt)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $data['membership_number'] ?? null,
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $this->mapFunktionFromRole($data['role']),
            $this->mapAktivFromConfidential($data['is_confidential'])
        ]);
        return $this->pdo->lastInsertId();
    }

    public function updateMember($id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE berechtigte
            SET Vorname = ?, Name = ?, eMail = ?, Funktion = ?, aktiv = ?
            WHERE ID = ?
        ");
        return $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $this->mapFunktionFromRole($data['role']),
            $this->mapAktivFromConfidential($data['is_confidential']),
            $id
        ]);
    }

    public function deleteMember($id) {
        // Soft Delete - setzt aktiv auf 0
        $stmt = $this->pdo->prepare("UPDATE berechtigte SET aktiv = 0 WHERE ID = ?");
        return $stmt->execute([$id]);
    }

    public function authenticate($email, $password) {
        // Authentifizierung über Ihre bestehende Logik
        // Falls Passwörter in anderer Tabelle gespeichert sind
        $member = $this->getMemberByEmail($email);

        // ANPASSEN: Ihre Authentifizierungslogik
        // z.B. Abfrage einer separaten Passwort-Tabelle

        return $member; // Temporär - anpassen!
    }

    // Hilfs-Mapping-Funktionen
    private function mapFunktionFromRole($role) {
        $mapping = [
            'vorstand' => 'Vorstand',
            'gf' => 'Geschäftsführung',
            'assistenz' => 'Assistenz',
            'fuehrungsteam' => 'Führungsteam',
            'Mitglied' => 'Mitglied'
        ];
        return $mapping[$role] ?? 'Mitglied';
    }

    private function mapAktivFromConfidential($is_confidential) {
        // ANPASSEN: Ihre Logik für aktiv-Werte
        return $is_confidential ? 2 : 1;
    }
}

/**
 * MemberAdapterFactory - Erstellt den passenden Adapter
 */
class MemberAdapterFactory {
    public static function create($pdo, $adapterType = 'standard') {
        switch ($adapterType) {
            case 'berechtigte':
                return new BerechtigteAdapter($pdo);
            case 'standard':
            default:
                return new StandardMemberAdapter($pdo);
        }
    }
}
?>
