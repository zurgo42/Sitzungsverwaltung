<?php
/**
 * Debug-Script für Abwesenheiten
 * Zeigt alle Abwesenheiten aus der Datenbank an
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'member_functions.php';

// HTML Header
echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>Debug Abwesenheiten</title>";
echo "<style>body { font-family: monospace; padding: 20px; } table { border-collapse: collapse; width: 100%; margin-top: 20px; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f2f2f2; } .duplicate { background: #ffcccc; }</style>";
echo "</head><body>";

echo "<h1>Debug: Abwesenheiten</h1>";

// 1. Direkte DB-Abfrage
echo "<h2>1. Direkte Datenbank-Abfrage (svabsences)</h2>";
$stmt = $pdo->query("SELECT * FROM svabsences ORDER BY start_date ASC");
$absences_raw = $stmt->fetchAll();

echo "<p><strong>Anzahl Einträge:</strong> " . count($absences_raw) . "</p>";

echo "<table>";
echo "<tr><th>absence_id</th><th>member_id</th><th>start_date</th><th>end_date</th><th>substitute_member_id</th><th>reason</th></tr>";
foreach ($absences_raw as $abs) {
    echo "<tr>";
    echo "<td>" . $abs['absence_id'] . "</td>";
    echo "<td>" . $abs['member_id'] . "</td>";
    echo "<td>" . $abs['start_date'] . "</td>";
    echo "<td>" . $abs['end_date'] . "</td>";
    echo "<td>" . ($abs['substitute_member_id'] ?? '–') . "</td>";
    echo "<td>" . htmlspecialchars($abs['reason'] ?? '–') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Prüfung auf doppelte absence_ids
echo "<h2>2. Prüfung auf doppelte absence_ids</h2>";
$stmt = $pdo->query("
    SELECT absence_id, COUNT(*) as count
    FROM svabsences
    GROUP BY absence_id
    HAVING count > 1
");
$duplicates = $stmt->fetchAll();

if (empty($duplicates)) {
    echo "<p style='color: green;'>✅ Keine doppelten absence_ids gefunden.</p>";
} else {
    echo "<p style='color: red;'>❌ WARNUNG: Doppelte absence_ids gefunden!</p>";
    echo "<table>";
    echo "<tr><th>absence_id</th><th>Anzahl</th></tr>";
    foreach ($duplicates as $dup) {
        echo "<tr class='duplicate'>";
        echo "<td>" . $dup['absence_id'] . "</td>";
        echo "<td>" . $dup['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. get_absences_with_names Funktion
echo "<h2>3. Ausgabe von get_absences_with_names()</h2>";
$all_absences = get_absences_with_names($pdo);

echo "<p><strong>Anzahl Einträge:</strong> " . count($all_absences) . "</p>";

echo "<table>";
echo "<tr><th>absence_id</th><th>Mitglied</th><th>Zeitraum</th><th>Vertretung</th><th>Grund</th></tr>";
foreach ($all_absences as $abs) {
    echo "<tr>";
    echo "<td>" . ($abs['absence_id'] ?? '???') . "</td>";
    echo "<td>" . htmlspecialchars(($abs['first_name'] ?? '???') . ' ' . ($abs['last_name'] ?? '???')) . " (" . ($abs['role'] ?? '???') . ")</td>";
    echo "<td>" . date('d.m.Y', strtotime($abs['start_date'])) . " - " . date('d.m.Y', strtotime($abs['end_date'])) . "</td>";
    echo "<td>" . ($abs['sub_first_name'] ? htmlspecialchars($abs['sub_first_name'] . ' ' . $abs['sub_last_name']) : '–') . "</td>";
    echo "<td>" . htmlspecialchars($abs['reason'] ?? '–') . "</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Array-Dump für Debugging
echo "<h2>4. Array-Dump von get_absences_with_names()</h2>";
echo "<pre>";
print_r($all_absences);
echo "</pre>";

echo "</body></html>";
?>
