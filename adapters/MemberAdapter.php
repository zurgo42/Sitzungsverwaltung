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

        return [
            'member_id' => $row['ID'],
            'membership_number' => $row['MNr'],
            'first_name' => $row['Vorname'],
            'last_name' => $row['Name'],
            'email' => $row['eMail'],
            'role' => $row['funktionsbeschreibung'] ?? 'Mitglied',  // Direkt übernehmen
            'is_admin' => $this->isAdmin($row['aktiv'], $row['MNr']),
            'is_confidential' => $this->isConfidential($row['aktiv']),
            'password_hash' => '', // Kein Passwort bei SSO
            'created_at' => $row['angelegt'] ?? null
        ];
    }

    /**
     * Prüft Admin-Rechte
     * Admin wenn: aktiv == 18 ODER MNr == 0495018
     */
    private function isAdmin($aktiv, $mnr) {
        return ($aktiv == 18 || $mnr == '0495018') ? 1 : 0;
    }

    /**
     * Mappt "aktiv" auf "is_confidential"
     * Vertraulich wenn: aktiv > 17
     */
    private function isConfidential($aktiv) {
        return ($aktiv > 17) ? 1 : 0;
    }

    public function getMemberById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM berechtigte WHERE ID = ? AND aktiv > 0");
        $stmt->execute([$id]);
        return $this->mapToStandard($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function getAllMembers() {
        $rows = $this->pdo->query("
            SELECT * FROM berechtigte
            WHERE aktiv > 0
            ORDER BY Name, Vorname
        ")->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapToStandard'], $rows);
    }

    public function getMemberByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM berechtigte WHERE eMail = ? AND aktiv > 0");
        $stmt->execute([$email]);
        return $this->mapToStandard($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function getMemberByMembershipNumber($mnr) {
        $stmt = $this->pdo->prepare("SELECT * FROM berechtigte WHERE MNr = ? AND aktiv > 0");
        $stmt->execute([$mnr]);
        return $this->mapToStandard($stmt->fetch(PDO::FETCH_ASSOC));
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
