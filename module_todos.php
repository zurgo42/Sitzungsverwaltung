<?php
/**
 * module_todos.php - ToDo-Vergabe während der Sitzung
 * 
 * Ermöglicht dem Protokollführer während der Sitzung ToDos zu vergeben
 */

/**
 * Zeigt ToDo-Erstellungsformular (nur für Sekretär in active Status)
 * 
 * @param PDO $pdo
 * @param array $item - Der TOP
 * @param int $meeting_id
 * @param bool $is_secretary
 * @param string $meeting_status
 * @param array $participants - Teilnehmerliste
 */
function render_todo_creation_form($pdo, $item, $meeting_id, $is_secretary, $meeting_status, $participants) {
    // Nur für Sekretär in aktiver Sitzung, nicht für TOP 0, 99, 999
    if (!$is_secretary || $meeting_status !== 'active' ||
        in_array($item['top_number'], [0, 99, 999])) {
        return;
    }

    // Teilnehmer mit Anwesenheitsstatus laden (OHNE svmembers - nutze Adapter)
    require_once __DIR__ . '/adapters/MemberAdapter.php';
    $memberAdapter = get_member_adapter($pdo);

    $stmt = $pdo->prepare("
        SELECT mp.member_id, mp.attendance_status
        FROM svmeeting_participants mp
        WHERE mp.meeting_id = ?
    ");
    $stmt->execute([$meeting_id]);
    $participant_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Member-Daten über Adapter holen
    $participants_with_attendance = [];
    foreach ($participant_rows as $row) {
        $member = $memberAdapter->getMemberById($row['member_id']);
        if ($member) {
            $participants_with_attendance[] = [
                'member_id' => $member['member_id'],
                'first_name' => $member['first_name'],
                'last_name' => $member['last_name'],
                'attendance_status' => $row['attendance_status']
            ];
        }
    }

    // Sortieren nach Nachname
    usort($participants_with_attendance, function($a, $b) {
        return strcmp($a['last_name'], $b['last_name']);
    });
    ?>
    
    <div style="margin-top: 15px; padding: 12px; background: #fff8e1; border: 2px solid #ffc107; border-radius: 6px;">
        <h4 style="color: #f57c00; margin-bottom: 12px;">✅ ToDo erstellen</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                    Zuständig:
                </label>
                <select name="todo_assigned_to[<?php echo $item['item_id']; ?>]" 
                        style="width: 100%; padding: 6px; border: 1px solid #ffc107; border-radius: 4px;">
                    <option value="">Kein ToDo</option>
                    <?php foreach ($participants_with_attendance as $p): ?>
                        <option value="<?php echo $p['member_id']; ?>">
                            <?php 
                            echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']);
                            if (in_array($p['attendance_status'], ['present', 'partial'])) {
                                echo ' ✓';
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                    Fällig am:
                </label>
                <input type="date" 
                       name="todo_due_date[<?php echo $item['item_id']; ?>]"
                       style="width: 100%; padding: 6px; border: 1px solid #ffc107; border-radius: 4px;">
            </div>
        </div>
        
        <div style="margin-bottom: 10px;">
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">
                Aufgabe:
            </label>
            <input type="text" 
                   name="todo_description[<?php echo $item['item_id']; ?>]" 
                   placeholder="Beschreibung der Aufgabe"
                   style="width: 100%; padding: 6px; border: 1px solid #ffc107; border-radius: 4px;">
        </div>
        
        <div style="font-size: 13px;">
            <label style="display: inline-flex; align-items: center; margin-right: 15px; cursor: pointer;">
                <input type="radio" 
                       name="todo_private[<?php echo $item['item_id']; ?>]" 
                       value="0" 
                       checked 
                       style="margin-right: 5px;">
                Öffentlich
            </label>
            <label style="display: inline-flex; align-items: center; cursor: pointer;">
                <input type="radio" 
                       name="todo_private[<?php echo $item['item_id']; ?>]" 
                       value="1"
                       style="margin-right: 5px;">
                Privat
            </label>
        </div>
        
        <small style="display: block; margin-top: 8px; color: #666; font-size: 11px;">
            💡 ToDos werden beim Speichern des Protokolls erstellt
        </small>
    </div>
    <?php
}
?>
