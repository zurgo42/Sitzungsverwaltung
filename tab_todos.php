<?php
/**
 * tab_todos.php - ToDo-Verwaltung mit verbesserter Struktur
 * Version: 3.0 | 29.10.2025 02:50 MEZ
 * 
 * NEUE STRUKTUR:
 * PC-Ansicht:
 *   1. Eigene ToDos als Cards (gestaffelt nebeneinander, aufklappbar)
 *   2. Fremde ToDos als Accordion-Tabelle
 * 
 * Mobile-Ansicht:
 *   - Eigene ToDos als Cards (untereinander)
 *   - Fremde ToDos als Cards (untereinander)
 */

require_once 'functions.php';

$currentMemberID = $_SESSION['member_id'] ?? 0;

if (!$currentMemberID) {
    die('❌ Bitte melden Sie sich an.');
}

// ============================================
// POST-HANDLER
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // STATUS ÄNDERN
    if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
        $todo_id = (int)($_POST['todo_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        
        $allowed_statuses = ['open', 'in progress', 'delayed', 'done'];
        if (!$todo_id || !in_array($new_status, $allowed_statuses)) {
            die('❌ Ungültige Eingabe.');
        }
        
        // Berechtigung prüfen (nur Empfänger)
        $stmt = $pdo->prepare("SELECT status, assigned_to_member_id FROM todos WHERE todo_id = ?");
        $stmt->execute([$todo_id]);
        $todo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$todo || $todo['assigned_to_member_id'] != $currentMemberID) {
            die('❌ Keine Berechtigung.');
        }
        
        try {
            if ($new_status === 'done') {
                $stmt = $pdo->prepare("UPDATE todos SET status = ?, completed_at = NOW() WHERE todo_id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE todos SET status = ?, completed_at = NULL WHERE todo_id = ?");
            }
            $stmt->execute([$new_status, $todo_id]);
            
            // Logging
            $logstmt = $pdo->prepare("INSERT INTO todo_log (todo_id, changed_by, change_type, old_value, new_value) VALUES (?, ?, 'status-change', ?, ?)");
            $logstmt->execute([$todo_id, $currentMemberID, $todo['status'], $new_status]);
            
            header('Location: index.php?tab=todos&msg=status_changed');
            exit;
        } catch (PDOException $e) {
            error_log('Todo Status Error: ' . $e->getMessage());
            die('❌ Fehler beim Aktualisieren.');
        }
    }
    
    // TODO ZURÜCKZIEHEN
    if (isset($_POST['action']) && $_POST['action'] === 'retract') {
        $todo_id = (int)($_POST['todo_id'] ?? 0);
        
        if (!$todo_id) {
            die('❌ Ungültige ToDo-ID.');
        }
        
        $stmt = $pdo->prepare("SELECT created_by_member_id, status, title FROM todos WHERE todo_id = ?");
        $stmt->execute([$todo_id]);
        $todo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$todo || $todo['created_by_member_id'] != $currentMemberID) {
            die('❌ Keine Berechtigung.');
        }
        
        if (!in_array($todo['status'], ['open', 'in progress'])) {
            die('❌ Nur offene ToDos können zurückgezogen werden.');
        }
        
        try {
            // Logging (vor dem Löschen!)
            $log = $pdo->prepare("INSERT INTO todo_log (todo_id, changed_by, change_type, old_value, new_value) VALUES (?, ?, 'todo-retract', ?, NULL)");
            $log->execute([$todo_id, $currentMemberID, $todo['status']]);
            
            $delete = $pdo->prepare("DELETE FROM todos WHERE todo_id = ?");
            $delete->execute([$todo_id]);
            
            header('Location: index.php?tab=todos&msg=todo_retracted');
            exit;
        } catch (PDOException $e) {
            error_log('Todo Retract Error: ' . $e->getMessage());
            die('❌ Fehler beim Zurückziehen.');
        }
    }
}

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
    meetings.meeting_name,
    CASE
        WHEN t.assigned_to_member_id = :member_id THEN 'own'
        WHEN t.status <> 'done' THEN 'active'
        ELSE 'done'
    END AS todo_typ
FROM todos t
LEFT JOIN members m1 ON t.assigned_to_member_id = m1.member_id
LEFT JOIN members m2 ON t.created_by_member_id = m2.member_id
LEFT JOIN meetings ON t.meeting_id = meetings.meeting_id
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

<h2>Meine ToDos</h2>

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
                            <?php echo nl2br(htmlspecialchars($row['description'] ?? '')); ?>
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