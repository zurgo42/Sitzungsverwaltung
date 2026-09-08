<?php
/**
 * meine_benachrichtigungen.php – Individuelle E-Mail-Benachrichtigungseinstellungen
 */

require_once 'session_config.php';
session_start();
require_once 'config.php';
require_once 'config_adapter.php';
require_once 'member_functions.php';
require_once 'notification_mailer.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$member_id   = (int)$_SESSION['member_id'];
$current_user = get_member_by_id($pdo, $member_id);
if (!$current_user) { header('Location: login.php'); exit; }
$aktiv = (int)($current_user['aktiv'] ?? 0);

// ---- POST: Einstellungen speichern ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    $delivery_mode = (int)($_POST['delivery_mode'] ?? 0) === 1 ? 1 : 0;

    // Zustellmodus speichern
    $pdo->prepare("
        INSERT INTO svnotification_prefs (member_id, event_type, email)
        VALUES (?, '_digest_mode', ?)
        ON DUPLICATE KEY UPDATE email = VALUES(email)
    ")->execute([$member_id, $delivery_mode]);

    // Jede Event-Typ-Checkbox speichern
    $event_types = ['antrag_neu', 'antrag_geaendert', 'antrag_hinweis', 'antrag_abstimmung',
                    'antrag_beschlossen', 'top_neu', 'top_kommentar', 'todo_zugewiesen'];
    foreach ($event_types as $et) {
        $enabled = isset($_POST['pref_' . $et]) ? 1 : 0;
        $pdo->prepare("
            INSERT INTO svnotification_prefs (member_id, event_type, email)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE email = VALUES(email)
        ")->execute([$member_id, $et, $enabled]);
    }

    $_SESSION['nm_saved'] = true;
    header('Location: meine_benachrichtigungen.php');
    exit;
}

// ---- Aktuelle Einstellungen laden ----
$prefs_stmt = $pdo->prepare("SELECT event_type, email FROM svnotification_prefs WHERE member_id = ?");
$prefs_stmt->execute([$member_id]);
$prefs_raw = $prefs_stmt->fetchAll();
$prefs = [];
foreach ($prefs_raw as $row) $prefs[$row['event_type']] = (bool)$row['email'];

// Effektiven Zustand ermitteln (berücksichtigt Defaults)
function pref_state($prefs, $event_type, $aktiv) {
    if (isset($prefs[$event_type])) return $prefs[$event_type];
    $map = nm_default_on_map();
    return isset($map[$event_type]) && $aktiv >= $map[$event_type];
}

$delivery_mode = isset($prefs['_digest_mode']) ? (int)$prefs['_digest_mode'] : 0;

$events = [
    'antrag_neu'         => ['label' => 'Neuer Antrag eingestellt',              'desc' => 'Titel und Link zum Antrag'],
    'antrag_geaendert'   => ['label' => 'Antrag geändert',                       'desc' => 'Titel und Zusammenfassung der Änderungen'],
    'antrag_hinweis'     => ['label' => 'Hinweis / Meinung zu einem Antrag',     'desc' => 'Der Hinweistext und Link zum Antrag'],
    'antrag_abstimmung'  => ['label' => 'Antrag zur Abstimmung eingestellt',     'desc' => 'Titel, Link und Abstimmungsfrist'],
    'antrag_beschlossen' => ['label' => 'Abstimmungsergebnis bekannt',           'desc' => 'Ergebnis (angenommen / abgelehnt) und Link'],
    'top_neu'            => ['label' => 'Neuer Tagesordnungspunkt',              'desc' => 'Sitzungsdatum und TOP-Titel'],
    'top_kommentar'      => ['label' => 'Kommentar zu einem TOP',                'desc' => 'Sitzungsdatum, TOP-Titel und Kommentartext'],
    'todo_zugewiesen'    => ['label' => 'Mir wurde ein ToDo zugewiesen',         'desc' => 'Vollständiges ToDo und Link zur Liste'],
];

$saved = !empty($_SESSION['nm_saved']);
unset($_SESSION['nm_saved']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benachrichtigungseinstellungen</title>
    <link rel="stylesheet" href="style.css">
    <script>
        (function() {
            const h = document.cookie.includes('darkMode=enabled') ||
                      (!document.cookie.includes('darkMode=') && localStorage.getItem('darkMode') === 'enabled');
            if (h) document.documentElement.classList.add('dark-mode');
        })();
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
               background: var(--bg-secondary); color: var(--text-primary); padding: 30px 16px; }
        .card { max-width: 680px; margin: 0 auto; background: var(--bg-primary);
                border-radius: 8px; box-shadow: 0 2px 8px var(--shadow-color);
                border: 1px solid var(--border-color); overflow: hidden; }
        .card-header { padding: 20px 28px; border-bottom: 1px solid var(--border-color); }
        .card-header h1 { font-size: 20px; }
        .card-header p { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }
        .card-body { padding: 24px 28px; }
        .section-title { font-size: 13px; font-weight: 700; color: var(--text-secondary);
                         text-transform: uppercase; letter-spacing: .04em; margin-bottom: 12px; }
        .radio-group { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 28px; }
        .radio-card { flex: 1; min-width: 200px; border: 2px solid var(--border-color);
                      border-radius: 8px; padding: 14px 16px; cursor: pointer;
                      display: flex; align-items: flex-start; gap: 10px;
                      transition: border-color .15s, background .15s; }
        .radio-card:has(input:checked), .radio-card.selected { border-color: var(--primary); background: var(--hover-bg); }
        .radio-card input { margin-top: 2px; cursor: pointer; }
        .radio-card .rc-label { font-size: 14px; font-weight: 600; }
        .radio-card .rc-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .event-list { list-style: none; }
        .event-item { display: flex; align-items: flex-start; gap: 12px;
                      padding: 12px 0; border-bottom: 1px solid var(--border-color); }
        .event-item:last-child { border-bottom: none; }
        .event-item label { cursor: pointer; display: flex; align-items: flex-start; gap: 12px; width: 100%; }
        .event-item input[type=checkbox] { margin-top: 2px; flex-shrink: 0; width: 18px; height: 18px; cursor: pointer; }
        .ev-label { font-size: 14px; font-weight: 600; }
        .ev-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .ev-badge { font-size: 10px; color: var(--primary); font-weight: 600;
                    border: 1px solid currentColor; border-radius: 3px; padding: 1px 5px;
                    margin-left: 6px; vertical-align: middle; }
        .alert-success { background: rgba(26,124,62,.1); color: #1a7c3e;
                         padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;
                         border-left: 4px solid #1a7c3e; font-size: 14px; }
        .btn-row { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
        .btn-primary { background: var(--primary); color: #fff; border: none; padding: 11px 28px;
                       border-radius: 5px; font-size: 15px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: var(--dunkelblau); }
        .btn-back { background: none; border: 1px solid var(--border-color); color: var(--text-primary);
                    padding: 11px 20px; border-radius: 5px; font-size: 14px; cursor: pointer;
                    text-decoration: none; display: inline-flex; align-items: center; }
        @media (max-width: 600px) {
            .card-header, .card-body { padding: 16px; }
            .radio-group { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>Benachrichtigungseinstellungen</h1>
        <p>Steuern Sie, wann Sie eine E-Mail erhalten möchten.</p>
    </div>
    <div class="card-body">
        <?php if ($saved): ?>
            <div class="alert-success">Einstellungen gespeichert.</div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="save_prefs" value="1">

            <div class="section-title">Zustellung</div>
            <div class="radio-group">
                <label class="radio-card <?= $delivery_mode === 0 ? 'selected' : '' ?>">
                    <input type="radio" name="delivery_mode" value="0" <?= $delivery_mode === 0 ? 'checked' : '' ?>>
                    <div>
                        <div class="rc-label">Sofort</div>
                        <div class="rc-desc">Jede Benachrichtigung wird unmittelbar versendet.</div>
                    </div>
                </label>
                <label class="radio-card <?= $delivery_mode === 1 ? 'selected' : '' ?>">
                    <input type="radio" name="delivery_mode" value="1" <?= $delivery_mode === 1 ? 'checked' : '' ?>>
                    <div>
                        <div class="rc-label">Tageszusammenfassung</div>
                        <div class="rc-desc">Alle Meldungen gesammelt einmal am Abend.</div>
                    </div>
                </label>
            </div>

            <div class="section-title">Ereignisse</div>
            <ul class="event-list">
                <?php foreach ($events as $et => $ev): ?>
                    <?php
                    $checked = pref_state($prefs, $et, $aktiv);
                    $default_on_map = nm_default_on_map();
                    $is_default_on = isset($default_on_map[$et]) && $aktiv >= $default_on_map[$et];
                    ?>
                    <li class="event-item">
                        <label>
                            <input type="checkbox" name="pref_<?= $et ?>" value="1"
                                <?= $checked ? 'checked' : '' ?>>
                            <div>
                                <div class="ev-label">
                                    <?= htmlspecialchars($ev['label']) ?>
                                    <?php if ($is_default_on): ?>
                                        <span class="ev-badge">Standard: AN</span>
                                    <?php endif; ?>
                                </div>
                                <div class="ev-desc"><?= htmlspecialchars($ev['desc']) ?></div>
                            </div>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="btn-row">
                <button type="submit" class="btn-primary">Speichern</button>
                <a href="index.php" class="btn-back">← Zurück</a>
            </div>
        </form>
    </div>
</div>
<script>
    // Radio-Karten-Styling auf Klick aktualisieren
    document.querySelectorAll('.radio-card input[type=radio]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.radio-card').forEach(function(c) { c.classList.remove('selected'); });
            radio.closest('.radio-card').classList.add('selected');
        });
    });
</script>
</body>
</html>
