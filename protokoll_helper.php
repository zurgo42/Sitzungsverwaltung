<?php
/**
 * protokoll_helper.php
 * Aktions-Protokollierung: jede durch User verursachte DB-Änderung wird
 * in der Tabelle `protokoll` eingetragen (kompatibel zum Altformat).
 */

/**
 * Gibt [MNr, KurzN] des aktuell eingeloggten Users zurück.
 * MNr: $_SESSION['MNr'] (SSO) oder membership_number aus $current_user.
 * KurzN: "V. Nachname" aus $current_user.
 */
function get_protokoll_user($current_user) {
    $mnr  = $_SESSION['MNr']
         ?? $current_user['membership_number']
         ?? (string)($current_user['member_id'] ?? 'NN');
    $kurz = substr($current_user['first_name'] ?? '', 0, 1)
          . '. ' . ($current_user['last_name'] ?? 'NN');
    return [$mnr, $kurz];
}

/**
 * Schreibt einen Eintrag in die Protokoll-Tabelle.
 *
 * @param PDO    $pdo
 * @param string $mnr    Mitgliedsnummer
 * @param string $kurz   KurzName (z.B. "V. Mustermann")
 * @param string $was    Aktionskürzel (z.B. 'Antrag-Speichern')
 * @param string $string Beschreibung der Änderung
 * @param int    $filter 0=tägl. dedup, 1=tägl., 2=stündl., 3=immer (Standard)
 */
function protokoll($pdo, $mnr, $kurz, $was, $string, $filter = 3) {
    $datum = date('Y-m-d H:i:s');
    try {
        if ($filter < 3) {
            $f = 5 + 5 * $filter; // 0→5 Zeichen (Jahr), 1→10 (Tag), 2→15 (Stunde)
            $stmt = $pdo->prepare(
                "SELECT zeit, string FROM protokoll WHERE MNr=? ORDER BY zeit DESC LIMIT 1"
            );
            $stmt->execute([$mnr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ($was === 'Zugriff'
                    && substr($row['zeit'], 0, $f) === substr($datum, 0, $f)) return;
                if ($row['string'] === $string
                    && substr($row['zeit'], 0, $f) === substr($datum, 0, $f)) return;
            }
        }
        $stmt = $pdo->prepare(
            "INSERT INTO protokoll (MNr, KurzN, zeit, was, string) VALUES (?,?,?,?,?)"
        );
        $stmt->execute([$mnr, $kurz, $datum, $was, $string]);
    } catch (Exception $e) {
        // Protokollfehler dürfen die Hauptanwendung nicht stören
        error_log('protokoll() Error: ' . $e->getMessage());
    }
}
