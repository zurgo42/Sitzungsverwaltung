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
 * Erzeugt einen lesbaren Diff-String für ein einzelnes Feld.
 *
 * Kurze Werte (≤80 Zeichen): "feld='alt'→'neu'"
 * Lange Texte: zeigt die geänderte Passage mit je $ctx Zeichen Umfeld:
 *   "feld=[…Kontext«alter Text»→«neuer Text»Kontext…]"
 *
 * Gibt null zurück wenn alt === neu.
 */
function protokoll_feld_diff($feld, $alt, $neu, $ctx = 30) {
    $alt = (string)$alt;
    $neu = (string)$neu;
    // Zeilenenden normalisieren (DB liefert \r\n, Browser-POST \n – sonst $i=0)
    $alt = str_replace(["\r\n", "\r"], "\n", $alt);
    $neu = str_replace(["\r\n", "\r"], "\n", $neu);
    if ($alt === $neu) return null;

    // Kurze Werte: direkt vorher→nachher (ungekürzt)
    if (mb_strlen($alt) <= 80 && mb_strlen($neu) <= 80) {
        return $feld . "='" . $alt . "'→'" . $neu . "'";
    }

    // Lange Texte: geänderte Passage mit Kontext
    // Zeichenweise aufteilen (UTF-8-sicher, PHP-7.3-kompatibel)
    $ca = preg_split('//u', $alt, -1, PREG_SPLIT_NO_EMPTY);
    $cn = preg_split('//u', $neu, -1, PREG_SPLIT_NO_EMPTY);
    $la = count($ca);
    $ln = count($cn);

    // Ersten Unterschied finden
    $i = 0;
    while ($i < $la && $i < $ln && $ca[$i] === $cn[$i]) $i++;

    // Letzten Unterschied finden (von hinten)
    $ea = $la - 1;
    $en = $ln - 1;
    while ($ea > $i && $en > $i && $ca[$ea] === $cn[$en]) { $ea--; $en--; }

    $old_part = implode('', array_slice($ca, $i, max(0, $ea - $i + 1)));
    $new_part = implode('', array_slice($cn, $i, max(0, $en - $i + 1)));

    // Kontext (beide Seiten aus dem alten Text, identischer Rahmen)
    $before = implode('', array_slice($ca, max(0, $i - $ctx), min($ctx, $i)));
    $after  = implode('', array_slice($ca, $ea + 1, $ctx));
    $pre    = $i > $ctx            ? '…' : '';
    $suf    = $la > $ea + 1 + $ctx ? '…' : '';

    // Format: [pre.before«old»after→pre.before«new»after.suf]
    return $feld . '=['
        . $pre . $before . '«' . $old_part . '»' . $after
        . '→'
        . $pre . $before . '«' . $new_part . '»' . $after . $suf
        . ']';
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
