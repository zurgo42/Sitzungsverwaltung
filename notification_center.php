<?php
/**
 * notification_center.php - Benachrichtigungs-Center UI
 *
 * Wird in der Menüleiste eingebunden
 * Zeigt Badge mit Counter + Dropdown
 */

if (!isset($_SESSION['member_id'])) {
    return; // Nicht eingeloggt
}

require_once 'notifications_functions.php';

$current_member_id = $_SESSION['member_id'];
$unread_count = count_unread_notifications($pdo, $current_member_id);
$notifications = get_unread_notifications($pdo, $current_member_id, 10);

// Icons für Typen
$type_icons = [
    'meeting' => '📅',
    'todo' => '✅',
    'comment' => '💬',
    'assignment' => '👤',
    'reminder' => '⏰',
    'system' => '⚙️'
];
?>

<style>
.notification-bell {
    position: relative;
    display: inline-block;
    cursor: pointer;
    padding: 8px 14px;
    background: var(--grau);
    color: #333;
    border-radius: 5px;
    transition: all 0.3s;
    font-weight: 500;
    font-size: 14px;
    white-space: nowrap;
}

.notification-bell:hover {
    background: var(--primary-lighter);
    color: #333;
}

.notification-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background: #f44336;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
}

.notification-dropdown {
    display: none;
    position: absolute;
    right: 10px;
    top: 60px;
    width: 400px;
    max-height: 500px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    overflow: hidden;
}

.notification-dropdown.show {
    display: block;
}

.notification-header {
    padding: 15px;
    background: #2196f3;
    color: white;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background 0.2s;
}

.notification-item:hover {
    background: #f5f5f5;
}

.notification-item-unread {
    background: #e3f2fd;
}

.notification-item-icon {
    font-size: 24px;
    margin-right: 10px;
    float: left;
}

.notification-item-content {
    margin-left: 40px;
}

.notification-item-title {
    font-weight: bold;
    margin-bottom: 4px;
}

.notification-item-message {
    font-size: 13px;
    color: #666;
    margin-bottom: 4px;
}

.notification-item-time {
    font-size: 11px;
    color: #999;
}

.notification-footer {
    padding: 10px 15px;
    background: #f9f9f9;
    text-align: center;
    border-top: 1px solid #ddd;
}

.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #999;
}
</style>

<!-- Notification Bell -->
<div class="notification-bell" id="notification-bell" onclick="toggleNotificationDropdown()">
    🔔 Nachricht
    <?php if ($unread_count > 0): ?>
        <span class="notification-badge" id="notification-badge"><?php echo $unread_count; ?></span>
    <?php endif; ?>
</div>

<!-- Dropdown -->
<div class="notification-dropdown" id="notification-dropdown">
    <div class="notification-header">
        <span>Benachrichtigungen</span>
        <button onclick="markAllRead()" style="background: transparent; border: none; color: white; cursor: pointer; font-size: 12px;">
            Alle gelesen
        </button>
    </div>

    <div class="notification-list" id="notification-list">
        <?php if (empty($notifications)): ?>
            <div class="notification-empty">
                <div style="font-size: 48px; margin-bottom: 10px;">✅</div>
                Keine neuen Benachrichtigungen
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item notification-item-unread"
                     onclick="handleNotificationClick(<?php echo $notif['notification_id']; ?>, '<?php echo htmlspecialchars($notif['link'] ?? '', ENT_QUOTES); ?>')">

                    <div class="notification-item-icon">
                        <?php echo $type_icons[$notif['type']] ?? '📢'; ?>
                    </div>

                    <div class="notification-item-content">
                        <div class="notification-item-title">
                            <?php echo htmlspecialchars($notif['title']); ?>
                        </div>
                        <div class="notification-item-message">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <div class="notification-item-time">
                            <?php
                            $time_diff = time() - strtotime($notif['created_at']);
                            if ($time_diff < 60) {
                                echo 'Gerade eben';
                            } elseif ($time_diff < 3600) {
                                echo floor($time_diff / 60) . ' Min';
                            } elseif ($time_diff < 86400) {
                                echo floor($time_diff / 3600) . ' Std';
                            } else {
                                echo date('d.m.Y H:i', strtotime($notif['created_at']));
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notification-dropdown');
    dropdown.classList.toggle('show');
}

function handleNotificationClick(notificationId, link) {
    // Als gelesen markieren
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'notification_id=' + notificationId
    });

    // Badge aktualisieren
    updateNotificationBadge();

    // Zu Link navigieren
    if (link) {
        window.location.href = link;
    }
}

function markAllRead() {
    console.log('markAllRead called'); // DEBUG

    fetch('mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => {
        console.log('Response received:', response); // DEBUG
        return response.json();
    })
    .then(data => {
        console.log('Data received:', data); // DEBUG
        if (data.success) {
            console.log('Success - reloading'); // DEBUG
            location.reload();
        } else {
            console.error('Mark all read failed:', data);
            alert('Fehler beim Markieren: ' + (data.error || 'Unbekannt'));
            location.reload();
        }
    })
    .catch(error => {
        console.error('Mark all read error:', error);
        alert('Netzwerkfehler: ' + error.message);
        location.reload();
    });
}

function updateNotificationBadge() {
    const badge = document.getElementById('notification-badge');
    if (badge) {
        const currentCount = parseInt(badge.textContent);
        if (currentCount > 1) {
            badge.textContent = currentCount - 1;
        } else {
            badge.remove();
        }
    }
}

// Dropdown schließen bei Klick außerhalb
document.addEventListener('click', function(e) {
    const bell = document.getElementById('notification-bell');
    const dropdown = document.getElementById('notification-dropdown');

    if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// Periodisches Update (alle 30 Sekunden)
setInterval(function() {
    fetch('get_notification_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('notification-badge');
            const bell = document.getElementById('notification-bell');

            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                } else {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.id = 'notification-badge';
                    newBadge.textContent = data.count;
                    bell.appendChild(newBadge);
                }
            } else if (badge) {
                badge.remove();
            }
        });
}, 30000); // 30 Sekunden
</script>
<?php
// Browser-Push Permission Request (falls noch nicht erteilt)
if ($unread_count > 0): ?>
<script>
// Einmalig Push-Permission anfragen
if ('Notification' in window && Notification.permission === 'default') {
    setTimeout(() => {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                console.log('Push notifications enabled');
                // TODO: Subscription an Server senden
            }
        });
    }, 3000); // 3 Sekunden nach Laden
}
</script>
<?php endif; ?>
