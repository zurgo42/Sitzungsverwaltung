<?php
/**
 * Referenten-Modell
 * Verwaltet alle Datenbankoperationen für Referenten und deren Vorträge
 */

require_once __DIR__ . '/Database.php';

class ReferentenModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Lädt persönliche Daten eines Referenten
     */
    public function getPersonData($mNr) {
        $stmt = $this->db->prepare("SELECT * FROM Refname WHERE MNr = ?");
        $stmt->execute([$mNr]);
        return $stmt->fetch();
    }

    /**
     * Speichert oder aktualisiert persönliche Daten
     */
    public function savePersonData($data) {
        $existing = $this->getPersonData($data['MNr']);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE Refname SET
                    Vorname = ?, Name = ?, Titel = ?, PLZ = ?, Ort = ?,
                    Gebj = ?, Beruf = ?, Telefon = ?, eMail = ?, datum = NOW()
                WHERE MNr = ?
            ");
            return $stmt->execute([
                $data['Vorname'], $data['Name'], $data['Titel'],
                $data['PLZ'], $data['Ort'], $data['Gebj'],
                $data['Beruf'], $data['Telefon'], $data['eMail'], $data['MNr']
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO Refname
                (MNr, Vorname, Name, Titel, PLZ, Ort, Gebj, Beruf, Telefon, eMail, datum)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([
                $data['MNr'], $data['Vorname'], $data['Name'], $data['Titel'],
                $data['PLZ'], $data['Ort'], $data['Gebj'], $data['Beruf'],
                $data['Telefon'], $data['eMail']
            ]);
        }
    }

    /**
     * Lädt einen Vortrag anhand der ID
     */
    public function getVortrag($id, $mNr = null) {
        if ($mNr) {
            $stmt = $this->db->prepare("SELECT * FROM Refpool WHERE ID = ? AND MNr = ?");
            $stmt->execute([$id, $mNr]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM Refpool WHERE ID = ?");
            $stmt->execute([$id]);
        }
        return $stmt->fetch();
    }

    /**
     * Lädt alle Vorträge eines Referenten
     */
    public function getVortraegeByMNr($mNr) {
        $stmt = $this->db->prepare("
            SELECT * FROM Refpool
            WHERE MNr = ?
            ORDER BY datum DESC, aktiv DESC, ID ASC
        ");
        $stmt->execute([$mNr]);
        return $stmt->fetchAll();
    }

    /**
     * Lädt alle aktiven Vorträge mit Referenten-Informationen
     */
    public function getAllActiveVortraege($sortBy = 'PLZ') {
        $allowedSorts = ['Kategorie', 'Name', 'Vorname', 'PLZ', 'Thema', 'Wo', 'Was'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'PLZ';
        }

        $stmt = $this->db->prepare("
            SELECT
                Refpool.Kategorie AS Kategorie,
                Refname.Name AS Name,
                Refname.Vorname AS Vorname,
                Refname.Titel AS Titel,
                Refname.PLZ AS PLZ,
                Refpool.Thema AS Thema,
                Refpool.Wo AS Wo,
                Refpool.Entf AS Entf,
                Refpool.Was AS Was,
                Refpool.ID AS ID,
                Refpool.MNr AS PoolMNR,
                Refname.MNr AS PersMNr,
                Refname.eMail AS eMail,
                Refname.Ort AS Ort
            FROM Refname
            INNER JOIN Refpool ON Refpool.MNr = Refname.MNr
            WHERE Refpool.aktiv = 1
            ORDER BY $sortBy, Name, Thema ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Speichert einen neuen Vortrag
     */
    public function saveVortrag($data) {
        $stmt = $this->db->prepare("
            INSERT INTO Refpool
            (MNr, Was, Wo, Entf, Thema, Inhalt, Kategorie, Equipment,
             Dauer, Kompetenz, Bemerkung, aktiv, IP, datum)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $data['MNr'], $data['Was'], $data['Wo'], $data['Entf'],
            $data['Thema'], $data['Inhalt'], $data['Kategorie'],
            $data['Equipment'], $data['Dauer'], $data['Kompetenz'],
            $data['Bemerkung'], $data['aktiv'], $data['IP']
        ]);
    }

    /**
     * Aktualisiert einen bestehenden Vortrag
     */
    public function updateVortrag($id, $mNr, $data) {
        $stmt = $this->db->prepare("
            UPDATE Refpool SET
                Was = ?, Wo = ?, Entf = ?, Thema = ?, Inhalt = ?,
                Kategorie = ?, Equipment = ?, Dauer = ?, Kompetenz = ?,
                Bemerkung = ?, aktiv = ?, IP = ?, datum = NOW()
            WHERE ID = ? AND MNr = ?
        ");

        return $stmt->execute([
            $data['Was'], $data['Wo'], $data['Entf'], $data['Thema'],
            $data['Inhalt'], $data['Kategorie'], $data['Equipment'],
            $data['Dauer'], $data['Kompetenz'], $data['Bemerkung'],
            $data['aktiv'], $data['IP'], $id, $mNr
        ]);
    }

    /**
     * Zählt aktive Vorträge
     */
    public function countActiveVortraege() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM Refpool WHERE aktiv = 1");
        return $stmt->fetchColumn();
    }

    /**
     * Berechnet Koordinaten einer PLZ
     */
    public function getKoordinaten($plz) {
        $stmt = $this->db->prepare("SELECT lon, lat, Ort FROM PLZ WHERE plz LIKE ?");
        $stmt->execute([$plz . '%']);
        return $stmt->fetch();
    }

    /**
     * Berechnet Entfernung zwischen zwei PLZ
     */
    public function calculateDistance($plz1, $plz2) {
        $coords1 = $this->getKoordinatenRecursive($plz1);
        $coords2 = $this->getKoordinatenRecursive($plz2);

        if (!$coords1 || !$coords2) {
            return 0;
        }

        $lat1 = deg2rad($coords1['lat']);
        $lon1 = deg2rad($coords1['lon']);
        $lat2 = deg2rad($coords2['lat']);
        $lon2 = deg2rad($coords2['lon']);

        $distance = acos(sin($lat2) * sin($lat1) + cos($lat2) * cos($lat1) * cos($lon2 - $lon1)) * 6380;
        return round($distance, 0);
    }

    /**
     * Rekursive Koordinatensuche (kürzt PLZ ab wenn nicht gefunden)
     */
    private function getKoordinatenRecursive($plz) {
        if (strlen($plz) < 1) {
            return null;
        }

        $coords = $this->getKoordinaten($plz);
        if ($coords) {
            return $coords;
        }

        return $this->getKoordinatenRecursive(substr($plz, 0, -1));
    }
}
