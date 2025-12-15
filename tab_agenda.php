<?php
/**
 * tab_agenda.php - MODULARE VERSION 2.1
 * Version 2.1 - 07.11.2025
 * 
 * Neu hinzugefügt:
 * - module_helpers.php - Hilfsfunktionen
 * - module_protocol.php - Protokoll-Generierung
 * - Erweiterte Display-Module für alle Status
 */

// Module laden
require_once 'module_categories.php';
require_once 'module_proposals.php';
require_once 'module_comments.php';
require_once 'module_helpers.php';
require_once 'module_protocol.php';
require_once 'module_agenda_overview.php';

// ============================================
// 1. VALIDIERUNG & MEETING LADEN
// ============================================

if (!$current_meeting_id) {
    echo '<div class="error-message">Bitte wähle ein Meeting aus.</div>';
    return;
}

$meeting = get_meeting_details($pdo, $current_meeting_id);
if (!$meeting) {
    echo '<div class="error-message">Meeting nicht gefunden.</div>';
    return;
}

// ============================================
// 2. DATEN LADEN
// ============================================

// Alle TOPs abrufen (ohne JOIN auf members!)
$stmt = $pdo->prepare("
    SELECT ai.*
    FROM svagenda_items ai
    WHERE ai.meeting_id = ?
    ORDER BY
        CASE
            WHEN ai.top_number = 0 THEN 0
            WHEN ai.top_number = 99 THEN 999998
            WHEN ai.top_number = 999 THEN 999999
            WHEN ai.top_number BETWEEN 1 AND 98 THEN (20 - ai.priority) * 1000 + ai.top_number
            WHEN ai.is_confidential = 1 THEN 1000000 + (20 - ai.priority) * 1000 + ai.top_number
            ELSE 999997
        END
");
$stmt->execute([$current_meeting_id]);
$agenda_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Creator-Namen über Adapter holen
foreach ($agenda_items as &$item) {
    if ($item['created_by_member_id']) {
        $creator = get_member_by_id($pdo, $item['created_by_member_id']);
        $item['creator_first'] = $creator['first_name'] ?? null;
        $item['creator_last'] = $creator['last_name'] ?? null;
        $item['creator_member_id'] = $item['created_by_member_id'];
    } else {
        $item['creator_first'] = null;
        $item['creator_last'] = null;
        $item['creator_member_id'] = null;
    }
}
unset($item);

// Alle Mitglieder laden
$all_members = get_all_members($pdo);

// Teilnehmer des Meetings laden (über Adapter!)
$stmt = $pdo->prepare("
    SELECT member_id
    FROM svmeeting_participants
    WHERE meeting_id = ?
");
$stmt->execute([$current_meeting_id]);
$participant_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Member-Daten über Adapter holen
$participants = [];
foreach ($participant_ids as $member_id) {
    $member = get_member_by_id($pdo, $member_id);
    if ($member) {
        $participants[] = $member;
    }
}

// Sortieren nach Nachname, Vorname
usort($participants, function($a, $b) {
    $cmp = strcasecmp($a['last_name'], $b['last_name']);
    if ($cmp === 0) {
        return strcasecmp($a['first_name'], $b['first_name']);
    }
    return $cmp;
});

// Berechtigungen prüfen
$is_secretary = ($meeting['secretary_member_id'] == $current_user['member_id']);
$is_chairman = ($meeting['chairman_member_id'] == $current_user['member_id']);
$can_edit_meeting = ($is_secretary || $is_chairman);

// ============================================
// 3. AUSGABE BEGINNT HIER
// ============================================
?>

<div class="tab-content">
    <?php if (in_array($meeting['status'], ['ended', 'protocol_ready', 'archived'])): ?>
        <h2>📜 Sitzungsverlauf</h2>
    <?php else: ?>
        <h2>📋 Tagesordnung</h2>
    <?php endif; ?>
    
    <!-- Meeting-Informationen -->
    <div class="meeting-info-box" style="background: #f9f9f9; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
        <strong>Termin:</strong> <?php echo htmlspecialchars($meeting['meeting_name'] ?? 'Ohne Namen'); ?><br>
        <strong>Ort:</strong> <?php echo htmlspecialchars($meeting['location']); ?><br>
        <strong>Datum:</strong> <?php
            echo date('d.m.Y H:i', strtotime($meeting['meeting_date'])) . ' Uhr';

            // Geplantes Ende anzeigen
            if (!empty($meeting['expected_end_date'])) {
                $start_date = date('Y-m-d', strtotime($meeting['meeting_date']));
                $end_date = date('Y-m-d', strtotime($meeting['expected_end_date']));

                if ($start_date !== $end_date) {
                    // Anderer Tag
                    echo ' bis (geplant) ' . date('d.m.Y H:i', strtotime($meeting['expected_end_date'])) . ' Uhr';
                } else {
                    // Gleicher Tag
                    echo ' bis (geplant) ' . date('H:i', strtotime($meeting['expected_end_date'])) . ' Uhr';
                }
            }
        ?><br>

        <?php if (!empty($meeting['video_link'])): ?>
            <strong>Video-Link:</strong> <a href="<?= htmlspecialchars($meeting['video_link']) ?>" target="_blank" style="color: #2196f3;"><?= htmlspecialchars($meeting['video_link']) ?></a><br>
        <?php endif; ?>

        <?php if (in_array($meeting['status'], ['ended', 'protocol_ready', 'archived']) && $meeting['ended_at']): ?>
            <strong>Ende:</strong> <?php echo date('d.m.Y H:i', strtotime($meeting['ended_at'])); ?> Uhr<br>
        <?php endif; ?>
        
        <strong>Status:</strong> 
        <?php
        switch($meeting['status']) {
            case 'preparation':
                echo '📝 In Vorbereitung';
                break;
            case 'active':
                echo '🟢 Sitzung läuft';
                break;
            case 'ended':
                echo '✅ Sitzung beendet';
                break;
            case 'protocol_ready':
                echo '📋 Protokoll bereit';
                break;
            case 'archived':
                echo '📁 Archiviert';
                break;
        }
        ?>
    </div>

    <?php
    // ============================================
    // 4. FORMULARE JE NACH STATUS
    // ============================================
    
    if ($meeting['status'] === 'preparation') {
        // VORBEREITUNG: TOPs anzeigen mit Bearbeitungsmöglichkeit
        // (Das Formular für neue TOPs ist bereits in der Display-Datei enthalten)
        include 'tab_agenda_display_preparation.php';
        
    } elseif ($meeting['status'] === 'active') {
        // AKTIVE SITZUNG: TOPs mit Protokoll-Funktionen, Live-Kommentare
        include 'tab_agenda_display_active.php';
        
    } elseif ($meeting['status'] === 'ended') {
        // SITZUNG BEENDET: Protokollant editiert, Teilnehmer kommentieren nach
        include 'tab_agenda_display_ended.php';
        
    } elseif ($meeting['status'] === 'protocol_ready') {
        // PROTOKOLL BEREIT: Warten auf Genehmigung durch Sitzungsleiter
        include 'tab_agenda_display_protocol_ready.php';
        
    } else {
        // ARCHIVED: Nur Ansicht
        include 'tab_agenda_display_readonly.php';
    }
    ?>
</div>

<?php
// JavaScript für Module laden
render_category_javascript();
?>
