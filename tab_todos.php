<?php

// Benachrichtigungsmodul laden
require_once 'module_notifications.php';
/**
 * tab_todos.php - Erledigen-Verwaltung mit verbesserter Struktur
 * Version: 3.0 | 29.10.2025 02:50 MEZ
 * 
 * NEUE STRUKTUR:
 * PC-Ansicht:
 *   1. Meine Aufgaben als Cards (gestaffelt nebeneinander, aufklappbar)
 *   2. Andere Aufgaben als Accordion-Tabelle
 * 
 * Mobile-Ansicht:
 *   - Meine Aufgaben als Cards (untereinander)
 *   - Andere Aufgaben als Cards (untereinander)
 */

require_once 'functions.php';

// Hilfsfunktion: URLs in Beschreibungen anklickbar machen
function make_links_clickable($text) {
    $text = htmlspecialchars($text);
    // URLs anklickbar machen
    $text = preg_replace(
        '/(https?:\/\/[^\s]+)/',
        '<a href="$1" target="_blank" style="color: #2196f3; text-decoration: underline;">$1</a>',
        $text
    );
    return nl2br($text);
}

$currentMemberID = $_SESSION['member_id'] ?? 0;

if (!$currentMemberID) {
    die('❌ Bitte melde dich an.');
}

// POST-Handler wurde nach process_todos.php verschoben
// und wird in index.php VOR HTML-Output ausgeführt

// ============================================
// HILFSFUNKTIONEN
// ============================================

function status_anzeige($status) {
    switch ($status) {
        case 'open':        return 'Offen';
        case 'in progress': return 'In Bearbeitung';
        case 'delayed':     return 'Verzögert';
        case 'done':        return 'Erledigt';
        default:            return htmlspecialchars($status);
    }
}

// ============================================
// DATENBANK-ABFRAGE
// ============================================

$sql = "
SELECT
    t.*,
    m1.first_name AS assigned_to_first_name,
    m1.last_name AS assigned_to_last_name,
    m2.first_name AS created_by_first_name,
    m2.last_name AS created_by_last_name,
    mtg.meeting_name,
    CASE
        WHEN t.assigned_to_member_id = :member_id THEN 'own'
        WHEN t.status <> 'done' THEN 'active'
        ELSE 'done'
    END AS todo_typ
FROM svtodos t
LEFT JOIN svmembers m1 ON t.assigned_to_member_id = m1.member_id
LEFT JOIN svmembers m2 ON t.created_by_member_id = m2.member_id
LEFT JOIN svmeetings mtg ON t.meeting_id = mtg.meeting_id
WHERE
    (
        t.is_private = 0
        OR t.assigned_to_member_id = :member_id
        OR t.created_by_member_id = :member_id
    )
ORDER BY
    (t.assigned_to_member_id = :member_id) DESC,
    (t.status <> 'done') DESC,
    CASE
        WHEN t.assigned_to_member_id = :member_id THEN IFNULL(t.due_date, '9999-12-31')
        WHEN t.status <> 'done' THEN IFNULL(t.due_date, '9999-12-31')
        ELSE m1.last_name
    END ASC,
    IFNULL(t.completed_at, '9999-12-31') ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['member_id' => $currentMemberID]);

// Aufteilen in eigene und fremde ToDos

// Aufteilen in eigene (offen/erledigt) und fremde ToDos
$own_todos_open = [];
$own_todos_done = [];
$other_todos = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['assigned_to_member_id'] == $currentMemberID) {
        if ($row['status'] === 'done') {
            $own_todos_done[] = $row;
        } else {
            $own_todos_open[] = $row;
        }
    } else {
        $other_todos[] = $row;
    }
}

// ============================================
// DESKTOP: EIGENE TODOS ALS GESTAFFELTE CARDS
// ============================================
?>

<!-- BENACHRICHTIGUNGEN -->
<?php render_user_notifications($pdo, $current_user['member_id']); ?>

<h2>Meine ToDos</h2>

<?php
// Success/Error Messages
if (isset($_SESSION['success'])) {
    echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

// Admin-Check: assistenz, gf, vorstand oder is_admin=1
$is_admin = in_array($current_user['role'] ?? '', ['assistenz', 'gf', 'vorstand']) || ($current_user['is_admin'] ?? 0) == 1;
?>

<!-- NEUES TODO ERSTELLEN -->
<div style="margin-bottom: 30px;">
    <button class="accordion-button" onclick="this.classList.toggle('active'); this.nextElementSibling.classList.toggle('active');" style="width: 100%; text-align: left; padding: 12px 15px; background: #4CAF50; border: none; cursor: pointer; font-size: 16px; font-weight: bold; color: white; border-radius: 5px;">
        ➕ Neues ToDo erstellen
    </button>
    <div class="accordion-content" style="display: none; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px; background: white;">
        <form method="POST" action="index.php?tab=todos">
            <input type="hidden" name="action" value="create_todo">

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Titel *</label>
                <input type="text" name="title" required placeholder="z.B. Präsentation vorbereiten" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Beschreibung</label>
                <textarea name="description" rows="4" placeholder="Details zur Aufgabe..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Fälligkeitsdatum</label>
                <input type="date" name="due_date" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <?php if ($is_admin): ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Zuweisen an</label>
                <select name="assigned_to_member_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="<?php echo $currentMemberID; ?>">Mir selbst</option>
                    <?php
                    // Adapter-kompatibel: get_all_members() verwenden
                    $all_members_for_assign = get_all_members($pdo);
                    foreach ($all_members_for_assign as $member) {
                        if ($member['member_id'] != $currentMemberID) {
                            echo '<option value="' . $member['member_id'] . '">';
                            echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')');
                            echo '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>

            <div style="margin-bottom: 15px;">
                <label style="display: block;">
                    <input type="checkbox" name="is_private" value="1">
                    <strong>Privat</strong> (nur für mich und den Empfänger sichtbar)
                </label>
            </div>

            <button type="submit" style="background: #4CAF50; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-size: 14px;">
                ✓ ToDo erstellen
            </button>
        </form>
    </div>
</div>

<style>
.accordion-button.active {
    background: #45a049 !important;
}
.accordion-content.active {
    display: block !important;
}
</style>


<?php if (empty($own_todos_open)): ?>

    <p style="color: #999;">Sie haben keine offenen ToDos.</p>
<?php else: ?>

    <div class="own-todos-container">
        <div class="own-todos-cards">
            <?php foreach ($own_todos_open as $idx => $row): 
                $datum = $row['due_date'] ?? '';
                $dt = ($datum && $datum !== '0000-00-00') 
                    ? date('d.m.Y', strtotime($datum)) 
                    : 'Offen';
                
                $is_overdue = ($datum && $datum !== '0000-00-00' && strtotime($datum) < strtotime(date('Y-m-d')));
                $is_creator = ($row['created_by_member_id'] == $currentMemberID);
                
                $tab_class = $is_overdue ? 'overdue' : '';
            ?>
                <div class="todo-filecard">
                    <div class="todo-filecard-tab <?php echo $tab_class; ?>">
                        <?php echo htmlspecialchars($dt); ?>
                    </div>
                    
                    <div class="todo-filecard-content">
                        <div class="todo-filecard-title">
                            <?php echo htmlspecialchars($row['title'] ?? ''); ?>
                        </div>
                        
                        <div class="todo-filecard-row">
                            <strong>Aufgabe:</strong>
                            <?php echo make_links_clickable($row['description'] ?? ''); ?>
                        </div>
                        
                        <div class="todo-filecard-row">
                            <strong>Status:</strong>
                            <?php echo status_anzeige($row['status'] ?? ''); ?>
                        </div>
                        
                        <div class="todo-filecard-row">
                            <strong>Quelle:</strong>
                            <?php echo htmlspecialchars($row['meeting_name'] ?? 'Direkt erstellt'); ?><br>
                            <small style="color: #999;">Erstellt von: <?php echo htmlspecialchars(
                                ($row['created_by_first_name'] ?? '') . ' ' . 
                                ($row['created_by_last_name'] ?? '')
                            ); ?></small>
                        </div>
                        
                        <div class="todo-filecard-qr desktop-only">
                            <img src="qr.php?id=<?php echo (int)$row['todo_id']; ?>" alt="QR">
                        </div>
                        
                        <div class="todo-filecard-mobile-link mobile-only">
                            <a href="todo_ics.php?id=<?php echo (int)$row['todo_id']; ?>">📅 In Kalender importieren</a>
                        </div>
                        
                        <div class="todo-filecard-actions">
                            <form method="post" class="status-form">
                                <input type="hidden" name="action" value="change_status"/>
                                <input type="hidden" name="todo_id" value="<?php echo (int)$row['todo_id']; ?>"/>
                                <select name="new_status">
                                    <option value="open"<?php echo $row['status']=='open' ? ' selected' : ''; ?>>Offen</option>
                                    <option value="in progress"<?php echo $row['status']=='in progress' ? ' selected' : ''; ?>>In Bearbeitung</option>
                                    <option value="delayed"<?php echo $row['status']=='delayed' ? ' selected' : ''; ?>>Verzögert</option>
                                    <option value="done"<?php echo $row['status']=='done' ? ' selected' : ''; ?>>Erledigt</option>
                                </select>
                                <button type="submit" class="btn-status">Status ändern</button>
                            </form>
                            
                            <?php if ($is_creator && $row['status'] == 'open'): ?>
                            <form method="post" class="retract-form" onsubmit="return confirm('ToDo wirklich zurückziehen?')">
                                <input type="hidden" name="action" value="retract"/>
                                <input type="hidden" name="todo_id" value="<?php echo (int)$row['todo_id']; ?>"/>
                                <button type="submit" class="btn-retract">ToDo zurückziehen</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>


<!-- ERLEDIGTE EIGENE TODOS -->
<?php if (!empty($own_todos_done)): ?>

    <div class="own-todos-done-section">
        <h3>Diese ToDos hast du bereits erledigt:</h3>
        
        <!-- DESKTOP: Tabelle -->
        <table class="done-todos-table desktop-only">
            <thead>
                <tr>
                    <th>Aufgabe</th>
                    <th>Erledigt am</th>
                    <th>Quelle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($own_todos_done as $row): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($row['title'] ?? ''); ?></strong><br>
                            <small><?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 80)); ?><?php echo strlen($row['description'] ?? '') > 80 ? '...' : ''; ?></small>
                        </td>
                        <td>
                            <?php echo $row['completed_at'] ? date('d.m.Y', strtotime($row['completed_at'])) : '✅'; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['meeting_name'] ?? 'Direkt erstellt'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- MOBILE: Cards -->
        <div class="done-todos-cards mobile-only">
            <?php foreach ($own_todos_done as $row): ?>
                <div class="done-todo-card">
                    <div class="done-todo-card-header">
                        <strong><?php echo htmlspecialchars($row['title'] ?? ''); ?></strong>
                        <span class="done-badge">✅ Erledigt</span>
                    </div>
                    <div class="done-todo-card-body">
                        <?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 80)); ?><?php echo strlen($row['description'] ?? '') > 80 ? '...' : ''; ?>
                    </div>
                    <div class="done-todo-card-footer">
                        <span>📅 <?php echo $row['completed_at'] ? date('d.m.Y', strtotime($row['completed_at'])) : 'Erledigt'; ?></span>
                        <span>📋 <?php echo htmlspecialchars($row['meeting_name'] ?? 'Direkt erstellt'); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <p class="info-text">Erledigte Aufgaben werden nach einiger Zeit automatisch gelöscht</p>
    </div>
<?php endif; ?>


<!-- FREMDE TODOS -->
<?php if (!empty($other_todos)): ?>

    <div class="other-todos-section">
        <div class="other-todos-accordion-header" onclick="this.classList.toggle('open'); this.nextElementSibling.classList.toggle('open');">
            <span>Weitere öffentliche ToDos der anderen Aktiven (<?php echo count($other_todos); ?>)</span>
            <span class="other-todos-arrow"></span>
        </div>
        <div class="other-todos-content">
            <!-- DESKTOP: Tabelle -->
            <table class="other-todos-table desktop-only">
                <thead>
                    <tr>
                        <th>Empfänger</th>
                        <th>Aufgabe</th>
                        <th>Status</th>
                        <th>Fällig</th>
                        <th>Erstellt von</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($other_todos as $row): 
                        $is_creator = ($row['created_by_member_id'] == $currentMemberID);
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(($row['assigned_to_first_name'] ?? '') . ' ' . ($row['assigned_to_last_name'] ?? '')); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['title'] ?? ''); ?></strong><br>
                                <small><?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 100)); ?></small>
                            </td>
                            <td><?php echo status_anzeige($row['status'] ?? ''); ?></td>
                            <td><?php 
                                $datum = $row['due_date'] ?? '';
                                echo ($row['status'] == 'done') ? '✅' : (($datum && $datum !== '0000-00-00') ? date('d.m.Y', strtotime($datum)) : 'Offen');
                            ?></td>
                            <td><?php echo htmlspecialchars(($row['created_by_first_name'] ?? '') . ' ' . ($row['created_by_last_name'] ?? '')); ?></td>
                            <td>
                                <?php if ($is_creator && $row['status'] == 'open'): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('ToDo wirklich zurückziehen?')">
                                    <input type="hidden" name="action" value="retract"/>
                                    <input type="hidden" name="todo_id" value="<?php echo (int)$row['todo_id']; ?>"/>
                                    <button type="submit" class="btn-retract-small">Zurückziehen</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- MOBILE: Cards -->
            <div class="other-todos-cards mobile-only">
                <?php foreach ($other_todos as $row): 
                    $datum = $row['due_date'] ?? '';
                    $dt = ($datum && $datum !== '0000-00-00') ? date('d.m.Y', strtotime($datum)) : 'Offen';
                    $is_done = ($row['status'] === 'done');
                    $is_creator = ($row['created_by_member_id'] == $currentMemberID);
                ?>
                    <div class="other-todo-card">
                        <div class="other-todo-card-header">
                            <strong><?php echo htmlspecialchars($row['title'] ?? ''); ?></strong>
                            <span class="status-badge"><?php echo status_anzeige($row['status'] ?? ''); ?></span>
                        </div>
                        <div class="other-todo-card-body">
                            <?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 100)); ?>
                        </div>
                        <div class="other-todo-card-footer">
                            <span>👤 <?php echo htmlspecialchars(($row['assigned_to_first_name'] ?? '') . ' ' . ($row['assigned_to_last_name'] ?? '')); ?></span>
                            <span>📅 <?php echo $is_done ? '✅' : $dt; ?></span>
                        </div>
                        <?php if ($is_creator && $row['status'] == 'open'): ?>
                        <div class="other-todo-card-actions">
                            <form method="post" onsubmit="return confirm('ToDo wirklich zurückziehen?')">
                                <input type="hidden" name="action" value="retract"/>
                                <input type="hidden" name="todo_id" value="<?php echo (int)$row['todo_id']; ?>"/>
                                <button type="submit" class="btn-retract">ToDo zurückziehen</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

