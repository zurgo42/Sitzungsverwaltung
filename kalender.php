<?php
/**
 * Familienkalender - Modernisierte Version
 *
 * Features:
 * - PDO Datenbankzugriff
 * - Responsive Design
 * - Inline CSS (standalone)
 * - Vertikale Spaltendarstellung
 */

// =============================================================================
// KONFIGURATION
// =============================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Datenbank-Konfiguration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kalender');

// Anwendungs-Einstellungen
$seite = 'kalender.php';
$userid = 'kreuzfahrt';
$textzeigen = 1;
$tipzeigen = 1;
$timetag = 86400;

// =============================================================================
// DATENBANK-FUNKTIONEN (PDO)
// =============================================================================

function getPdo() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

function dbFetchAll($sql, $params = []) {
    $stmt = getPdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dbFetchOne($sql, $params = []) {
    $stmt = getPdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function dbExecute($sql, $params = []) {
    $stmt = getPdo()->prepare($sql);
    return $stmt->execute($params);
}

function dbLastInsertId() {
    return getPdo()->lastInsertId();
}

// =============================================================================
// HILFSFUNKTIONEN
// =============================================================================

function iget($name) {
    return isset($_GET[$name]) ? trim($_GET[$name]) : '';
}

function ipost($name) {
    return isset($_POST[$name]) ? trim($_POST[$name]) : '';
}

function ubeide($name) {
    if (isset($_POST[$name])) return trim($_POST[$name]);
    if (isset($_GET[$name])) return trim($_GET[$name]);
    return '';
}

// Datum-Konvertierungen
function dzut($datum) {
    if (is_numeric($datum)) return $datum;
    $parts = explode('.', $datum);
    if (count($parts) == 3) {
        return mktime(0, 0, 0, $parts[1], $parts[0], $parts[2]);
    }
    return strtotime($datum);
}

function tzud($timestamp) {
    return date('d.m.Y', $timestamp);
}

function datumformat($datum, $format = 0) {
    $ts = dzut($datum);
    return tzud($ts);
}

function dwandeln($isoDate, $format = 0) {
    $ts = strtotime($isoDate);
    return date('d.m.Y', $ts);
}

function escape($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// =============================================================================
// API-FUNKTIONEN
// =============================================================================

function Feiertage($year, $state) {
    $url = "https://feiertage-api.de/api/?jahr=$year&nur_land=$state";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) return null;
    $data = json_decode($response, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
}

function getHolidayData($year, $state) {
    $url = "https://ferien-api.de/api/v1/holidays/$state/$year";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode != 200) return null;
    $data = json_decode($response, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
}

// =============================================================================
// ZUGANGSSCHUTZ
// =============================================================================

$accessKey = iget('k');
$adminPass = ubeide('admin');

if ($accessKey != "6373" && $adminPass != "1kmPgg!") {
    die('<p style="font-family: sans-serif; padding: 20px;">
        <a href="?k=">Zugang: k=xxxx</a></p>');
}

// =============================================================================
// INITIALISIERUNG
// =============================================================================

$wtag = ["So", "Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"];
$farben = ["#FFFFFF", "#F8E0F1", "#ECE0F8", "#E0ECF8", "#ECF8E0",
           "#F7F8E0", "#FBF2EF", "#EFEFFB", "#CEF6D8", "#00FF00"];

// User-Daten laden
$user = dbFetchOne("SELECT * FROM kaluser WHERE id = ?", [$userid]);
$anzkat = $user['anzkat'] ?? 6;
$welche = ipost('welche') ?: 9;

// Datumswerte
$startdatum = date("d.m.Y");
if (strlen(ipost('startdatum')) > 2) {
    $startdatum = datumformat(ipost('startdatum'));
}

if (strlen(ipost('enddatum')) > 2) {
    $enddatum = datumformat(ipost('enddatum'));
} else {
    $enddatum = tzud(dzut($startdatum) + 180 * $timetag);
}

if (dzut($enddatum) <= dzut($startdatum)) {
    $enddatum = tzud(dzut($startdatum) + 180 * $timetag);
}

$kompakt = ipost('kompakt');
$isAdmin = ($adminPass == "1kmPgg!");

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Familienkalender</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --light: #ecf0f1;
            --border: #bdc3c7;
            --text: #2c3e50;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: var(--text);
            background: #f5f5f5;
            margin: 0;
            padding: 10px;
        }

        h3 {
            color: var(--primary);
            margin: 0 0 10px 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .controls {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        .controls p {
            margin: 0;
        }

        input[type="text"], input[type="checkbox"] {
            padding: 6px 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 13px;
        }

        input[type="text"] {
            width: 85px;
        }

        input[type="submit"], button {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        input[type="submit"]:hover, button:hover {
            background: #2980b9;
        }

        label {
            margin-right: 5px;
        }

        /* Kalender-Tabelle */
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-size: 12px;
        }

        .calendar-table th {
            background: var(--primary);
            color: white;
            padding: 8px 4px;
            text-align: center;
            font-weight: 600;
            font-size: 11px;
        }

        .calendar-table td {
            padding: 4px 6px;
            border: 1px solid #e0e0e0;
            text-align: center;
            vertical-align: top;
        }

        .calendar-table tr:hover td {
            background-color: rgba(52, 152, 219, 0.1);
        }

        .year-row td {
            background: var(--light) !important;
            font-weight: bold;
        }

        .mbgrau { background-color: #f9f9f9; }
        .mbheller { background-color: #ffffff; }

        /* Tooltip */
        .tip {
            position: relative;
            cursor: help;
            color: var(--secondary);
        }

        .tip span {
            display: none;
            position: absolute;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 11px;
            width: 200px;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            pointer-events: none;
        }

        .tip:hover span,
        .tip:focus span,
        .tip:active span {
            display: block !important;
        }

        .tip:focus {
            outline: none;
        }

        .tiprechts span {
            left: 20px;
            top: -5px;
        }

        .tiplinks span {
            right: 20px;
            top: -5px;
        }

        /* Admin-Bereich */
        .admin-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .admin-table th, .admin-table td {
            padding: 6px;
            border: 1px solid var(--border);
            text-align: left;
        }

        .admin-table th {
            background: var(--light);
            font-weight: 600;
        }

        .admin-table input[type="text"] {
            width: 100%;
            max-width: 120px;
        }

        .admin-table input[type="text"][name^="text"],
        .admin-table input[type="text"][name^="tip"] {
            max-width: 250px;
        }

        .admin-table input[type="text"][size="1"] {
            width: 30px;
        }

        .color-preview {
            display: inline-flex;
            gap: 2px;
            flex-wrap: wrap;
        }

        .color-box {
            padding: 4px 8px;
            border: 1px solid #ccc;
            font-size: 10px;
        }

        .text-small {
            font-size: 11px;
            color: #666;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                font-size: 12px;
                padding: 5px;
            }

            .calendar-table {
                font-size: 10px;
            }

            .calendar-table th, .calendar-table td {
                padding: 3px 2px;
            }

            input[type="text"] {
                width: 70px;
                font-size: 12px;
            }

            .admin-table {
                font-size: 10px;
            }

            .tip span {
                width: 150px;
            }
        }

        @media (max-width: 480px) {
            .calendar-table th, .calendar-table td {
                padding: 2px 1px;
                font-size: 9px;
            }

            .controls {
                padding: 10px;
            }
        }

        /* Mobile Admin-Tabelle */
        @media (max-width: 900px) {
            .admin-table {
                display: block !important;
            }

            .admin-table thead {
                display: none !important;
            }

            .admin-table tbody {
                display: block !important;
            }

            .admin-table tr {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 4px 8px;
                margin-bottom: 10px;
                border: 1px solid var(--border);
                border-radius: 6px;
                padding: 8px;
                background: #fafafa;
            }

            .admin-table tr:last-child {
                background: #e8f4fd;
            }

            .admin-table td {
                border: none !important;
                padding: 4px 2px !important;
                text-align: left !important;
            }

            .admin-table td::before {
                content: attr(data-label) ": ";
                font-weight: 600;
                font-size: 11px;
                color: var(--primary);
            }

            /* Text und Tip ganze Breite */
            .admin-table td:nth-child(5),
            .admin-table td:nth-child(6) {
                grid-column: span 2;
            }

            /* Löschen rechts */
            .admin-table td:nth-child(7) {
                grid-column: span 2;
                text-align: right !important;
            }

            .admin-table input[type="text"] {
                width: 100% !important;
                max-width: 100% !important;
                font-size: 16px !important;
                padding: 8px !important;
                border: 1px solid var(--border) !important;
                border-radius: 4px !important;
                box-sizing: border-box !important;
            }

            .admin-table input[type="text"][size="1"] {
                width: 60px !important;
            }

            .admin-table input[type="checkbox"] {
                width: 24px !important;
                height: 24px !important;
            }
        }
    </style>
</head>
<body>
<div class="container">

<form action="<?php echo $seite; ?>?k=6373" method="post" accept-charset="utf-8">

<div class="controls">
    <h3>Planungshilfe</h3>
    <p>
        Zeitraum:
        <input type="text" name="startdatum" value="<?php echo escape($startdatum); ?>">
        bis
        <input type="text" name="enddatum" value="<?php echo escape($enddatum); ?>">
        <label>
            <input type="checkbox" name="kompakt" value="1" <?php echo $kompakt ? 'checked' : ''; ?>>
            kompakt
        </label>
        <input type="submit" name="start" value="Anzeigen">
    </p>
</div>

<?php if ($isAdmin): ?>
<!-- =============================================================================
     ADMIN-BEREICH
     ============================================================================= -->
<div class="admin-section">
    <h3>Daten verwalten</h3>

    <?php
    // Automatismen: Feriendaten für Jahr eintragen
    $year = ubeide("year");
    if ($year) {
        // Alte Ferieneinträge löschen
        dbExecute("DELETE FROM kaldaten WHERE (kat=0 OR kat=1 OR kat=2) AND (tvon >= ? AND tbis <= ?)",
            [dzut("01.01.$year"), dzut("31.12.$year")]);

        // Neue Feriendaten holen
        $laender = ['NW', 'BY', 'SH'];
        foreach ($laender as $i => $state) {
            $holidayData = getHolidayData($year, $state);
            if (is_array($holidayData)) {
                foreach ($holidayData as $holiday) {
                    dbExecute("INSERT INTO kaldaten SET user = ?, tvon = ?, tbis = ?, kat = ?, farbe = ?",
                        [$userid, dzut(dwandeln($holiday['start'])), dzut(dwandeln($holiday['end'])), $i, $i]);
                }
            }
        }
        echo '<p style="color: green;">Feriendaten für ' . escape($year) . ' wurden aktualisiert.</p>';
    }

    // Speichern
    if (ipost('speichern') == 1) {
        $daten = dbFetchAll("SELECT * FROM kaldaten WHERE user = ? ORDER BY tvon ASC", [$userid]);
        $idmax = 0;

        foreach ($daten as $d) {
            $id = $d['id'];
            if ($id > $idmax) $idmax = $id;

            if (ipost("weg$id") == 1) {
                dbExecute("DELETE FROM kaldaten WHERE id = ?", [$id]);
            } else if (strlen(ipost("tvon$id")) > 2) {
                $bis = ipost("tbis$id") ?: ipost("tvon$id");

                if (dzut(ipost("tvon$id")) != dzut(tzud($d['tvon']))
                    || dzut($bis) != dzut(tzud($d['tbis']))
                    || ipost("kat$id") != $d['kat']
                    || ipost("farbe$id") != $d['farbe']
                    || ipost("text$id") != $d['text']
                    || ipost("tip$id") != $d['tip']) {

                    dbExecute("UPDATE kaldaten SET tvon = ?, tbis = ?, kat = ?, farbe = ?, text = ?, tip = ? WHERE id = ?",
                        [dzut(datumformat(ipost("tvon$id"))), dzut(datumformat($bis)),
                         ipost("kat$id"), ipost("farbe$id"), ipost("text$id"), ipost("tip$id"), $id]);
                }
            }
        }

        // Neuer Eintrag
        $id = $idmax + 1;
        if (strlen(ipost("tvon$id")) > 2) {
            $bis = ipost("tbis$id") ?: ipost("tvon$id");
            if (dzut(datumformat(ipost("tvon$id"))) <= dzut(datumformat($bis))) {
                dbExecute("INSERT INTO kaldaten (user, tvon, tbis, kat, farbe, text, tip) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$userid, dzut(datumformat(ipost("tvon$id"))), dzut(datumformat($bis)),
                     ipost("kat$id"), ipost("farbe$id"), ipost("text$id"), ipost("tip$id")]);
            }
        }
    }
    ?>

    <input type="hidden" name="admin" value="1kmPgg!">
    <input type="hidden" name="speichern" value="1">

    <table class="admin-table">
        <tr>
            <th>Von</th>
            <th>Bis</th>
            <th>Spalte</th>
            <th>Farbe</th>
            <th>Text</th>
            <th>Tip</th>
            <th>Löschen</th>
        </tr>

        <?php
        $filter = ($welche < 9) ? " AND kat = $welche" : "";
        $daten = dbFetchAll("SELECT * FROM kaldaten WHERE user = ? $filter ORDER BY tvon ASC", [$userid]);
        $idmax = 0;

        foreach ($daten as $d):
            $id = $d['id'];
            if ($id > $idmax) $idmax = $id;
            if ($d['kat'] < 5) continue; // Automatische Einträge ausblenden
        ?>
        <tr>
            <td data-label="Von">
                <?php echo $wtag[date("w", $d['tvon'])]; ?>
                <input type="text" name="tvon<?php echo $id; ?>" value="<?php echo tzud($d['tvon']); ?>">
            </td>
            <td data-label="Bis">
                <?php echo $wtag[date("w", $d['tbis'])]; ?>
                <input type="text" name="tbis<?php echo $id; ?>" value="<?php echo tzud($d['tbis']); ?>">
            </td>
            <td data-label="Spalte">
                <input type="text" size="1" name="kat<?php echo $id; ?>"
                       value="<?php echo $d['kat']; ?>"
                       style="background-color: <?php echo $farben[$d['kat']] ?? '#fff'; ?>">
            </td>
            <td data-label="Farbe">
                <input type="text" size="1" name="farbe<?php echo $id; ?>"
                       value="<?php echo $d['farbe']; ?>"
                       style="background-color: <?php echo (strlen($d['farbe']) > 2) ? $d['farbe'] : ($farben[$d['farbe']] ?? '#fff'); ?>">
            </td>
            <td data-label="Text"><input type="text" name="text<?php echo $id; ?>" value="<?php echo escape($d['text']); ?>"></td>
            <td data-label="Tip"><input type="text" name="tip<?php echo $id; ?>" value="<?php echo escape($d['tip']); ?>"></td>
            <td data-label="Löschen"><input type="checkbox" name="weg<?php echo $id; ?>" value="1"></td>
        </tr>
        <?php endforeach; ?>

        <!-- Neue Zeile -->
        <?php $id = $idmax + 1; ?>
        <tr style="background: #f0f8ff;">
            <td data-label="Von"><input type="text" name="tvon<?php echo $id; ?>" placeholder="tt.mm.jjjj"></td>
            <td data-label="Bis"><input type="text" name="tbis<?php echo $id; ?>" placeholder="tt.mm.jjjj"></td>
            <td data-label="Spalte"><input type="text" size="1" name="kat<?php echo $id; ?>" value="<?php echo ($welche < 9) ? $welche : 5; ?>"></td>
            <td data-label="Farbe"><input type="text" size="1" name="farbe<?php echo $id; ?>" value="0"></td>
            <td data-label="Text"><input type="text" name="text<?php echo $id; ?>" placeholder="Text"></td>
            <td data-label="Tip"><input type="text" name="tip<?php echo $id; ?>" placeholder="Tooltip"></td>
            <td></td>
        </tr>
    </table>

    <p style="margin-top: 10px;">
        <span class="text-small">Farben: </span>
        <span class="color-preview">
            <?php for ($f = 0; $f < 10; $f++): ?>
                <span class="color-box" style="background: <?php echo $farben[$f]; ?>"><?php echo $f; ?></span>
            <?php endfor; ?>
        </span>
    </p>

    <p class="text-small">
        Nur Spalte zeigen: <input type="text" size="1" name="welche" value="<?php echo $welche; ?>"> (9 = alle)
        &nbsp;|&nbsp;
        Feriendaten aktualisieren für Jahr: <input type="text" size="4" name="year" placeholder="2025">
    </p>

    <p><input type="submit" name="start" value="Speichern"></p>
</div>
<?php endif; ?>

<!-- =============================================================================
     KALENDER-ANZEIGE
     ============================================================================= -->

<table class="calendar-table">
    <thead>
        <tr>
            <th colspan="2">Datum</th>
            <?php for ($k = 0; $k < $anzkat; $k++): ?>
                <th><?php echo escape($user["kat$k"] ?? "Spalte $k"); ?></th>
            <?php endfor; ?>
        </tr>
    </thead>
    <tbody>

<?php
$dt = dzut($startdatum);
$endt = dzut($enddatum);
$wt = date("w", $dt);
$bg = "mbgrau";
$zeile0 = "";
$z0 = 0;

// Jahreszeile am Anfang
echo '<tr class="year-row"><td colspan="2"><b>' . substr(tzud($dt), 6, 4) . '</b></td>';
echo '<td colspan="' . $anzkat . '"></td></tr>';

while ($dt <= $endt):
    // Monatswechsel
    if (substr(tzud($dt), 0, 2) == "01") {
        $bg = ($bg == "mbheller") ? "mbgrau" : "mbheller";
        $dt = dzut(tzud($dt)); // Rundungsdifferenzen vermeiden
    }

    // Kopfzeile bei Quartalswechsel
    if (!$kompakt) {
        $datum = substr(tzud($dt), 0, 6);
        if ($datum == "01.01.") {
            echo '<tr class="year-row"><td colspan="2"><b>' . substr(tzud($dt), 6, 4) . '</b></td>';
            echo '<td colspan="' . $anzkat . '"></td></tr>';
        }
    }

    // Daten für diesen Tag laden
    $daten = dbFetchAll(
        "SELECT kaldaten.*, kaluser.* FROM kaldaten
         INNER JOIN kaluser ON kaldaten.user = kaluser.id
         WHERE kaldaten.user = ? AND kaldaten.tvon <= ? AND kaldaten.tbis >= ?
         ORDER BY kat ASC, tvon ASC",
        [$userid, $dt, $dt]
    );

    // Zeile aufbauen
    $tag = '<tr><td class="' . $bg . '">' . $wtag[$wt] . '</td>';
    $tag .= '<td class="' . $bg . '">' . substr(tzud($dt), 0, 6) . '</td>';

    $zeile = "";
    $zeile2 = "";

    if (empty($daten)) {
        // Keine Einträge
        for ($k = 0; $k < $anzkat; $k++) {
            $zeile .= '<td></td>';
            $zeile2 .= '<td></td>';
        }
    } else {
        // Einträge nach Kategorie sortieren
        $byKat = [];
        foreach ($daten as $d) {
            $byKat[$d['kat']][] = $d;
        }

        for ($k = 0; $k < $anzkat; $k++) {
            if (isset($byKat[$k])) {
                $entries = $byKat[$k];
                $d = $entries[0];

                // Farbe bestimmen
                $color = ($d['farbe'] == "0")
                    ? ($farben[$user["f$k"] ?? 0] ?? '#fff')
                    : ((strlen($d['farbe']) < 2) ? ($farben[$d['farbe']] ?? '#fff') : $d['farbe']);

                $zeile .= '<td style="background-color: ' . $color . ';">';
                $zeile2 .= '<td style="background-color: ' . $color . ';">...</td>';

                // Text
                if ($textzeigen && strlen($d['text']) > 0) {
                    $zeile .= escape($d['text']);
                } else {
                    $zeile .= escape($user["std$k"] ?? '');
                }

                // Tooltip
                if ($tipzeigen && strlen($d['tip'] ?? '') > 2 && (int)$d['tvon'] === (int)$dt) {
                    $tipClass = ($k / $anzkat < 0.4) ? 'tiprechts' : 'tiplinks';
                    $zeile .= '<br><a class="tip ' . $tipClass . '" href="#">&#9432;<span>' . escape($d['tip']) . '</span></a>';
                }

                // Zweiter Eintrag in gleicher Kategorie
                if (isset($entries[1]) && $textzeigen) {
                    $zeile .= '<br>' . escape($entries[1]['text']);
                }

                $zeile .= '</td>';
            } else {
                $zeile .= '<td></td>';
                $zeile2 .= '<td></td>';
            }
        }
    }

    // Ausgabe (kompakt oder normal)
    if (!$kompakt) {
        echo $tag . $zeile . '</tr>';
    } else {
        if ($zeile != $zeile0) {
            echo $tag . $zeile . '</tr>';
            $z0 = 0;
        } else if ($z0 < 1) {
            echo '<tr><td colspan="2" class="' . $bg . '">...</td>' . $zeile2 . '</tr>';
            $z0++;
        }
        $zeile0 = $zeile;
    }

    $dt += $timetag;
    $wt = ($wt < 6) ? $wt + 1 : 0;
endwhile;
?>

    </tbody>
</table>

</form>

<p class="text-small" style="text-align: center; margin-top: 10px;">
    Familienkalender &copy; <?php echo date('Y'); ?>
</p>

</div>
</body>
</html>
