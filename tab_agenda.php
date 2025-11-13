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
    echo '<div class="error-message">Bitte wählen Sie ein Meeting aus.</div>';
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

// Alle TOPs abrufen
$stmt = $pdo->prepare("
    SELECT ai.*, 
           creator.first_name as creator_first, 
           creator.last_name as creator_last,
           creator.member_id as creator_member_id
    FROM agenda_items ai
    LEFT JOIN members creator ON ai.created_by_member_id = creator.member_id
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

// Alle Mitglieder laden
$all_members = get_all_members($pdo);

// Teilnehmer des Meetings laden
$stmt = $pdo->prepare("
    SELECT m.* 
    FROM members m
    JOIN meeting_participants mp ON m.member_id = mp.member_id
    WHERE mp.meeting_id = ?
    ORDER BY m.last_name, m.first_name
");
$stmt->execute([$current_meeting_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <strong>Datum:</strong> <?php echo date('d.m.Y H:i', strtotime($meeting['meeting_date'])); ?> Uhr<br>
        
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
