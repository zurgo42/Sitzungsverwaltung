<?php
/**
 * widget_absences.php - Widget zur Anzeige aktueller Abwesenheiten
 * Wird unterhalb der Tabs auf allen Seiten angezeigt
 * NUR FÜR FÜHRUNGSTEAM SICHTBAR
 */

// Nur laden wenn $pdo und $current_user verfügbar sind
if (!isset($pdo) || !isset($current_user)) {
    return;
}

// Berechtigung prüfen: Nur Führungsteam sieht das Widget
$leadership_roles = ['vorstand', 'gf', 'assistenz', 'fuehrungsteam', 'Vorstand', 'Geschäftsführung', 'Assistenz', 'Führungsteam'];
if (!in_array($current_user['role'], $leadership_roles)) {
    return;
}

// Aktuelle Abwesenheiten laden (nur heute und zukünftig)
try {
    $stmt = $pdo->query("
        SELECT a.*,
               m1.first_name as member_first_name, m1.last_name as member_last_name,
               m2.first_name as substitute_first_name, m2.last_name as substitute_last_name
        FROM absences a
        LEFT JOIN members m1 ON a.member_id = m1.member_id
        LEFT JOIN members m2 ON a.substitute_member_id = m2.member_id
        WHERE a.end_date >= CURDATE()
        ORDER BY a.start_date ASC
        LIMIT 20
    ");
    $absences = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fehler ignorieren (z.B. wenn Tabelle noch nicht existiert)
    $absences = [];
}

// Nur anzeigen wenn Abwesenheiten vorhanden sind
if (empty($absences)) {
    return;
}
?>

<div class="absences-widget">
    <div class="widget-header">
        <strong>🏖️ Abwesenheiten:</strong>
    </div>
    <div class="widget-content">
        <?php
        $today = new DateTime();
        $items = [];

        foreach ($absences as $absence):
            $start = new DateTime($absence['start_date']);
            $end = new DateTime($absence['end_date']);
            $is_current = $start <= $today && $end >= $today;

            $name = htmlspecialchars($absence['member_first_name'] . ' ' . $absence['member_last_name']);
            $date_range = $start->format('d.m.') . ' - ' . $end->format('d.m.');

            $text = $name . ' (' . $date_range . ')';

            if ($absence['reason']) {
                $text .= ' – ' . htmlspecialchars($absence['reason']);
            }

            if ($absence['substitute_member_id']) {
                $substitute_name = htmlspecialchars($absence['substitute_first_name'] . ' ' . $absence['substitute_last_name']);
                $text .= ' <span class="substitute">Vertr.: ' . $substitute_name . '</span>';
            }

            if ($is_current) {
                $text = '<strong>' . $text . '</strong>';
            }

            $items[] = $text;
        endforeach;

        echo implode(' • ', $items);
        ?>
    </div>
</div>

<style>
.absences-widget {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 10px 15px;
    margin: 15px 0 20px 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

.absences-widget .widget-header {
    margin-bottom: 5px;
    color: #495057;
}

.absences-widget .widget-content {
    color: #6c757d;
}

.absences-widget .substitute {
    color: #007bff;
    font-style: italic;
}

@media (max-width: 768px) {
    .absences-widget {
        font-size: 0.85rem;
        padding: 8px 12px;
    }
}
</style>
