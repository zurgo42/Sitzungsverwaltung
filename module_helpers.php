<?php
/**
 * module_helpers.php - Hilfsfunktionen für tab_agenda
 * 
 * Enthält wiederkehrende Funktionen für:
 * - Teilnehmerlisten-Formatierung
 * - Mitgliedsnamen-Abfrage
 * - Zeitbedarfs-Berechnung
 */

/**
 * Gibt Teilnehmerliste als HTML aus (zeigt nur Anwesende)
 */
function render_participant_list($pdo, $meeting_id, $participants) {
    $present_list = [];
    foreach ($participants as $p) {
        $stmt = $pdo->prepare("SELECT attendance_status FROM meeting_participants WHERE meeting_id = ? AND member_id = ?");
        $stmt->execute([$meeting_id, $p['member_id']]);
        $attendance = $stmt->fetch();
        $status = $attendance['attendance_status'] ?? 'absent';
        
        if ($status === 'present') {
            $present_list[] = htmlspecialchars($p['first_name'] . ' ' . $p['last_name']);
        } elseif ($status === 'partial') {
            $present_list[] = htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) . ' (zeitweise)';
        }
    }
    
    if (empty($present_list)) {
        return '<em>Noch keine Teilnehmer erfasst</em>';
    }
    return implode(', ', $present_list);
}

/**
 * Gibt Namen eines Members zurück
 * Verwendet den Adapter um korrekt aus berechtigte oder members zu lesen
 */
function get_member_name($pdo, $member_id) {
    if (!$member_id) return 'Nicht festgelegt';

    // get_member_by_id() verwenden (aus functions.php) - nutzt den Adapter
    $member = get_member_by_id($pdo, $member_id);

    return $member ? htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) : 'Nicht festgelegt';
}

/**
 * Zeitbedarf nach aktuellem TOP berechnen
 */
function calculate_remaining_time($pdo, $agenda_items, $current_index) {
    $regular_time = 0;
    $confidential_time = 0;
    
    for ($i = $current_index; $i < count($agenda_items); $i++) {
        $item = $agenda_items[$i];
        
        // TOP 999 (Sitzungsende) und TOP 0 überspringen
        if (in_array($item['top_number'], [0, 999])) {
            continue;
        }
        
        // avg_duration verwenden (wurde bereits berechnet in tab_agenda.php)
        $avg_duration = floatval($item['avg_duration'] ?? $item['estimated_duration'] ?? 0);
        
        if ($item['is_confidential'] == 1) {
            $confidential_time += $avg_duration;
        } else {
            $regular_time += $avg_duration;
        }
    }
    
    return [
        'regular' => round($regular_time),
        'confidential' => round($confidential_time)
    ];
}

/**
 * Rendert bearbeitbare Teilnehmerliste für Protokollant
 */
function render_editable_participant_list($pdo, $meeting_id, $participants, $meeting_status) {
    ?>
    <div style="margin: 20px 0; padding: 15px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 8px;">
        <h3 style="color: #1976d2; margin-bottom: 15px;">👥 Teilnehmerverwaltung</h3>
        
        <form method="POST" action="">
            <input type="hidden" name="update_attendance" value="1">
            
            <div style="display: grid; gap: 10px;">
                <?php foreach ($participants as $p): 
                    $stmt = $pdo->prepare("SELECT attendance_status FROM meeting_participants WHERE meeting_id = ? AND member_id = ?");
                    $stmt->execute([$meeting_id, $p['member_id']]);
                    $attendance = $stmt->fetch();
                    $status = $attendance['attendance_status'] ?? 'absent';
                ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 8px; background: white; border-radius: 4px;">
                        <span style="flex: 1; font-weight: 600;">
                            <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                        </span>
                        
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio" 
                                   name="attendance[<?php echo $p['member_id']; ?>]" 
                                   value="present"
                                   <?php echo $status === 'present' ? 'checked' : ''; ?>>
                            <span>✅ Anwesend</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio" 
                                   name="attendance[<?php echo $p['member_id']; ?>]" 
                                   value="partial"
                                   <?php echo $status === 'partial' ? 'checked' : ''; ?>>
                            <span>⏱️ Zeitweise</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio" 
                                   name="attendance[<?php echo $p['member_id']; ?>]" 
                                   value="absent"
                                   <?php echo $status === 'absent' ? 'checked' : ''; ?>>
                            <span>❌ Abwesend</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="submit" style="margin-top: 15px; background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                💾 Teilnehmerliste speichern
            </button>
        </form>
    </div>
    <?php
}

/**
 * Rendert schreibgeschützte Teilnehmerliste
 */
function render_readonly_participant_list($pdo, $meeting_id, $participants) {
    // Sitzungsdatum laden
    $stmt = $pdo->prepare("SELECT meeting_date FROM meetings WHERE meeting_id = ?");
    $stmt->execute([$meeting_id]);
    $meeting_date = $stmt->fetchColumn() ?: date('Y-m-d');

    // Abwesenheiten für das Sitzungsdatum laden
    $stmt_absences = $pdo->prepare("
        SELECT member_id, reason, substitute_member_id,
               (SELECT CONCAT(first_name, ' ', last_name) FROM members WHERE member_id = absences.substitute_member_id) as substitute_name
        FROM absences
        WHERE ? BETWEEN start_date AND end_date
    ");
    $stmt_absences->execute([$meeting_date]);
    $absent_members = [];
    while ($row = $stmt_absences->fetch()) {
        $absent_members[$row['member_id']] = $row;
    }

    // Abwesende Teilnehmer mit Warnung sammeln
    $absent_warnings = [];
    foreach ($participants as $p) {
        if (isset($absent_members[$p['member_id']])) {
            $info = $absent_members[$p['member_id']];
            $warning = htmlspecialchars($p['first_name'] . ' ' . $p['last_name']);
            $details = [];
            if ($info['reason']) $details[] = htmlspecialchars($info['reason']);
            if ($info['substitute_name']) $details[] = 'Vertr.: ' . htmlspecialchars($info['substitute_name']);
            if ($details) $warning .= ' (' . implode(', ', $details) . ')';
            $absent_warnings[] = $warning;
        }
    }
    ?>
    <div style="margin: 15px 0; padding: 12px; background: #f9f9f9; border-radius: 8px;">
        <strong>👥 Teilnehmer:</strong><br>
        <?php echo render_participant_list($pdo, $meeting_id, $participants); ?>
        <?php if (!empty($absent_warnings)): ?>
            <div style="margin-top: 8px; padding: 8px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; font-size: 13px;">
                <strong style="color: #856404;">⚠️ Abwesend gemeldet:</strong>
                <?php echo implode(', ', $absent_warnings); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
