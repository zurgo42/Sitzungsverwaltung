<?php
/**
 * notification_mailer.php – E-Mail-Benachrichtigungssystem
 *
 * Event-Typen:
 *   antrag_neu          Neuer Antrag eingestellt
 *   antrag_geaendert    Antrag geändert
 *   antrag_hinweis      Hinweis / Meinung zu einem Antrag
 *   antrag_abstimmung   Antrag zur Abstimmung eingestellt
 *   antrag_beschlossen  Abstimmungsergebnis festgestellt
 *   top_neu             Neuer Tagesordnungspunkt
 *   top_kommentar       Kommentar zu einem TOP
 *   todo_zugewiesen     Neues ToDo zugewiesen
 *
 * Zustellmodus (event_type '_digest_mode' in svnotification_prefs):
 *   email=0  Sofort (Standard)
 *   email=1  Tageszusammenfassung
 *
 * Opt-out-Defaults (kein DB-Eintrag = Standard-AN):
 *   aktiv >= 16: antrag_neu, top_neu, todo_zugewiesen
 *   aktiv >= 17: antrag_abstimmung
 */

// ------------------------------------------------------------------
// Standard-Ein-Schwellwerte (aktiv-Mindestwert für Default-AN)
// ------------------------------------------------------------------
if (!defined('NM_DEFAULT_ON')) {
    define('NM_DEFAULT_ON', serialize([
        'antrag_neu'        => 16,
        'top_neu'           => 16,
        'todo_zugewiesen'   => 16,
        'antrag_abstimmung' => 17,
    ]));
}

function nm_default_on_map() {
    static $map = null;
    if ($map === null) $map = unserialize(NM_DEFAULT_ON);
    return $map;
}

// ------------------------------------------------------------------
// Hilfsfunktionen
// ------------------------------------------------------------------

/**
 * Liefert die Basis-URL der Anwendung (mit abschließendem /).
 */
function nm_site_url($pdo = null) {
    static $url = null;
    if ($url !== null) return $url;
    if ($pdo) {
        try {
            $stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'meeting_system_url' LIMIT 1");
            $val = $stmt ? $stmt->fetchColumn() : '';
            if ($val) {
                $url = rtrim((string)$val, '/') . '/';
                return $url;
            }
        } catch (Exception $e) {}
    }
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path  = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $url   = $proto . '://' . $host . rtrim($path, '/') . '/';
    return $url;
}

/**
 * Prüft ob ein Mitglied eine Benachrichtigung erhalten möchte.
 * Ohne expliziten Eintrag greift das Default-Regelwerk.
 */
function nm_has_pref($pdo, $member_id, $event_type, $aktiv) {
    static $cache = [];
    $key = (int)$member_id . '_' . $event_type;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare("SELECT email FROM svnotification_prefs WHERE member_id = ? AND event_type = ?");
        $stmt->execute([(int)$member_id, $event_type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return $cache[$key] = false;
    }
    if ($row !== false) return $cache[$key] = (bool)$row['email'];
    $map = nm_default_on_map();
    if (isset($map[$event_type]) && (int)$aktiv >= $map[$event_type]) {
        return $cache[$key] = true;
    }
    return $cache[$key] = false;
}

/**
 * Liefert den Zustellmodus: 0 = Sofort, 1 = Digest.
 */
function nm_get_delivery_mode($pdo, $member_id) {
    static $cache = [];
    $mid = (int)$member_id;
    if (array_key_exists($mid, $cache)) return $cache[$mid];
    try {
        $stmt = $pdo->prepare("SELECT email FROM svnotification_prefs WHERE member_id = ? AND event_type = '_digest_mode'");
        $stmt->execute([$mid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cache[$mid] = $row ? (int)$row['email'] : 0;
    } catch (Exception $e) {
        return $cache[$mid] = 0;
    }
}

/**
 * Fügt eine Benachrichtigung in svmail_notifications ein.
 */
function nm_enqueue($pdo, $member_id, $event_type, $subject, $body_text, $body_html, $is_digest) {
    try {
        $pdo->prepare("
            INSERT INTO svmail_notifications (member_id, event_type, subject, body_text, body_html, is_digest, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([(int)$member_id, $event_type, $subject, $body_text, $body_html, $is_digest ? 1 : 0]);
    } catch (Exception $e) {
        error_log('nm_enqueue: ' . $e->getMessage());
    }
}

/**
 * Sendet eine Benachrichtigung an alle Mitglieder mit aktiv > 10
 * die den event_type abonniert haben.
 *
 * $build_fn($member): [$subject, $body_text, $body_html] | null (überspringen)
 */
function nm_notify_all($pdo, $event_type, $build_fn) {
    try {
        if (!function_exists('get_all_members')) return;
        $all = get_all_members($pdo);
        foreach ($all as $m) {
            $aktiv = (int)($m['aktiv'] ?? 0);
            if ($aktiv <= 10 || empty($m['email'])) continue;
            if (!nm_has_pref($pdo, $m['member_id'], $event_type, $aktiv)) continue;
            $result = $build_fn($m);
            if ($result === null) continue;
            [$subj, $txt, $html] = $result;
            $mode = nm_get_delivery_mode($pdo, $m['member_id']);
            nm_enqueue($pdo, $m['member_id'], $event_type, $subj, $txt, $html, $mode);
        }
    } catch (Exception $e) {
        error_log('nm_notify_all(' . $event_type . '): ' . $e->getMessage());
    }
}

// ------------------------------------------------------------------
// Mail-Template-Helfer
// ------------------------------------------------------------------

function nm_html_wrap($pdo, $content_html) {
    $name     = defined('MAIL_FROM_NAME') ? htmlspecialchars(MAIL_FROM_NAME) : 'Sitzungsverwaltung';
    $prefs    = htmlspecialchars(nm_site_url($pdo) . 'meine_benachrichtigungen.php');
    return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,'
        . '\'Segoe UI\',Roboto,sans-serif;">'
        . '<div style="max-width:580px;margin:24px auto;background:#fff;border-radius:8px;'
        . 'overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.12);">'
        . '<div style="background:#0055aa;padding:18px 28px;">'
        . '<p style="margin:0;color:#fff;font-size:15px;font-weight:600;">' . $name . '</p></div>'
        . '<div style="padding:24px 28px;">' . $content_html . '</div>'
        . '<div style="padding:12px 28px;background:#f8f9fb;border-top:1px solid #e4e7ec;">'
        . '<p style="margin:0;font-size:11px;color:#999;">'
        . '<a href="' . $prefs . '" style="color:#0055aa;text-decoration:none;">Benachrichtigungseinstellungen ändern</a>'
        . '</p></div></div></body></html>';
}

function nm_btn($url, $label) {
    return '<a href="' . htmlspecialchars($url) . '" style="display:inline-block;margin-top:18px;'
        . 'padding:10px 22px;background:#0055aa;color:#fff;text-decoration:none;'
        . 'border-radius:5px;font-size:14px;font-weight:600;">' . htmlspecialchars($label) . '</a>';
}

function nm_tbl_row($label, $value) {
    return '<tr><td style="padding:5px 14px 5px 0;color:#666;font-size:13px;white-space:nowrap;vertical-align:top;">'
        . htmlspecialchars($label) . '</td>'
        . '<td style="padding:5px 0;font-size:14px;font-weight:600;color:#222;">' . htmlspecialchars($value) . '</td></tr>';
}

// ------------------------------------------------------------------
// Event-Funktionen
// ------------------------------------------------------------------

function nm_event_antrag_neu($pdo, $antrnr, $titel, $bart_label) {
    $url = nm_site_url($pdo) . 'antrag_bearbeiten.php?antrnr=' . urlencode($antrnr);
    nm_notify_all($pdo, 'antrag_neu', function($m) use ($antrnr, $titel, $bart_label, $url, $pdo) {
        $subj = 'Neuer Antrag: ' . $titel;
        $txt  = "Neuer Antrag eingestellt:\n\nNr.: {$antrnr}\nTyp: {$bart_label}\nTitel: {$titel}\n\nLink: {$url}";
        $html = nm_html_wrap($pdo,
            '<h2 style="margin:0 0 16px;color:#1a1a1a;font-size:18px;">Neuer Antrag eingestellt</h2>'
            . '<table style="border-collapse:collapse;">'
            . nm_tbl_row('Nr.', $antrnr) . nm_tbl_row('Typ', $bart_label) . nm_tbl_row('Titel', $titel)
            . '</table>' . nm_btn($url, 'Antrag ansehen'));
        return [$subj, $txt, $html];
    });
}

function nm_event_antrag_geaendert($pdo, $antrnr, $titel, $diff_string) {
    // Nicht senden wenn nichts geändert wurde
    if (substr($diff_string, -strlen('(unverändert)')) === '(unverändert)') return;
    $url = nm_site_url($pdo) . 'antrag_bearbeiten.php?antrnr=' . urlencode($antrnr);
    nm_notify_all($pdo, 'antrag_geaendert', function($m) use ($antrnr, $titel, $diff_string, $url, $pdo) {
        $subj = 'Antrag geändert: ' . $titel;
        $txt  = "Antrag {$antrnr} wurde geändert:\n\nTitel: {$titel}\n\nÄnderungen:\n{$diff_string}\n\nLink: {$url}";
        $html = nm_html_wrap($pdo,
            '<h2 style="margin:0 0 16px;color:#1a1a1a;font-size:18px;">Antrag geändert</h2>'
            . '<table style="border-collapse:collapse;margin-bottom:14px;">'
            . nm_tbl_row('Nr.', $antrnr) . nm_tbl_row('Titel', $titel)
            . '</table>'
            . '<p style="margin:0 0 6px;font-size:12px;color:#666;font-weight:600;">ÄNDERUNGEN</p>'
            . '<pre style="margin:0;padding:12px;background:#f4f6f8;border-radius:4px;font-size:12px;'
            . 'white-space:pre-wrap;word-break:break-word;color:#333;">'
            . htmlspecialchars($diff_string) . '</pre>'
            . nm_btn($url, 'Antrag ansehen'));
        return [$subj, $txt, $html];
    });
}

function nm_event_antrag_hinweis($pdo, $antrnr, $titel, $hinweis_text) {
    $url = nm_site_url($pdo) . 'abstimmungen.php?antrnr=' . urlencode($antrnr);
    nm_notify_all($pdo, 'antrag_hinweis', function($m) use ($antrnr, $titel, $hinweis_text, $url, $pdo) {
        $subj = 'Hinweis zu Antrag ' . $antrnr . ': ' . mb_substr($titel, 0, 55);
        $txt  = "Neuer Hinweis zu Antrag {$antrnr} ({$titel}):\n\n{$hinweis_text}\n\nLink: {$url}";
        $html = nm_html_wrap($pdo,
            '<h2 style="margin:0 0 16px;color:#1a1a1a;font-size:18px;">Hinweis zu Antrag ' . htmlspecialchars($antrnr) . '</h2>'
            . '<table style="border-collapse:collapse;margin-bottom:14px;">'
            . nm_tbl_row('Titel', $titel) . '</table>'
            . '<blockquote style="margin:0;padding:12px 16px;background:#f0f5ff;'
            . 'border-left:4px solid #0055aa;border-radius:0 4px 4px 0;font-size:14px;color:#333;">'
            . nl2br(htmlspecialchars($hinweis_text)) . '</blockquote>'
            . nm_btn($url, 'Antrag ansehen'));
        return [$subj, $txt, $html];
    });
}

function nm_event_antrag_abstimmung($pdo, $antrnr, $neue_nr, $titel, $frist_datum) {
    $url   = nm_site_url($pdo) . 'abstimmungen.php?antrnr=' . urlencode($neue_nr);
    $frist = $frist_datum ? date('d.m.Y', strtotime((string)$frist_datum)) : '—';
    nm_notify_all($pdo, 'antrag_abstimmung', function($m) use ($neue_nr, $titel, $frist, $url, $pdo) {
        $subj = 'Abstimmung läuft: ' . $titel;
        $txt  = "Antrag zur Abstimmung eingestellt:\n\nNr.: {$neue_nr}\nTitel: {$titel}\nFrist: {$frist}\n\nLink: {$url}";
        $html = nm_html_wrap($pdo,
            '<h2 style="margin:0 0 16px;color:#1a1a1a;font-size:18px;">Antrag zur Abstimmung</h2>'
            . '<table style="border-collapse:collapse;">'
            . nm_tbl_row('Nr.', $neue_nr) . nm_tbl_row('Titel', $titel) . nm_tbl_row('Abstimmungsfrist', $frist)
            . '</table>' . nm_btn($url, 'Jetzt abstimmen'));
        return [$subj, $txt, $html];
    });
}

function nm_event_antrag_beschlossen($pdo, $antrnr, $titel, $angenommen) {
    $ergebnis = $angenommen ? 'Angenommen' : 'Abgelehnt';
    $url = nm_site_url($pdo) . 'antrag_bearbeiten.php?antrnr=' . urlencode($antrnr);
    nm_notify_all($pdo, 'antrag_beschlossen', function($m) use ($antrnr, $titel, $ergebnis, $angenommen, $url, $pdo) {
        $subj  = 'Abstimmungsergebnis: ' . $titel;
        $txt   = "Abstimmung abgeschlossen:\n\nNr.: {$antrnr}\nTitel: {$titel}\nErgebnis: {$ergebnis}\n\nLink: {$url}";
        $color = $angenommen ? '#1a7c3e' : '#c0392b';
        $badge = $angenommen ? '✓ Angenommen' : '✗ Abgelehnt';
        $html  = nm_html_wrap($pdo,
            '<h2 style="margin:0 0 16px;color:#1a1a1a;font-size:18px;">Abstimmungsergebnis</h2>'
            . '<table style="border-collapse:collapse;margin-bottom:16px;">'
            . nm_tbl_row('Nr.', $antrnr) . nm_tbl_row('Titel', $titel) . '</table>'
            . '<p style="margin:0;font-size:20px;font-weight:700;color:' . $color . ';">'
            . htmlspecialchars($badge) . '</p>'
            . nm_btn($url, 'Details ansehen'));
        return [$subj, $txt, $html];
    });
}

function nm_event_top_neu($pdo, $meeting_id, $top_title, $meeting_date, $meeting_name) {
    $url   = nm_site_url($pdo) . 'index.php?tab=agenda&meeting_id=' . urlencode($meeting_id);
    $datum = $meeting_date ? date('d.m.Y', strtotime((string)$meeting_date)) : '—';
    nm_notify_all($pdo, 'top_neu', function($m) use ($top_title, $datum, $meeting_name, $url, $pdo) {
        $subj = 'Neuer TOP für ' . $datum . ': ' . mb_substr($top_title, 0, 55);
        $txt  = "Neuer Tagesordnungspunkt:\n\nSitzung: {$meeting_name} ({$datum})\nTOP: {$top_title}\n\nLink: {$url}";
        $html = nm_html_wrap($pdo,
            '<h2 style="margin:0 0 16px;color:#1a1a1a;font-size:18px;">Neuer Tagesordnungspunkt</h2>'
            . '<table style="border-collapse:collapse;">'
            . nm_tbl_row('Sitzung', $meeting_name . ' (' . $datum . ')') . nm_tbl_row('TOP', $top_title)
            . '</table>' . nm_btn($url, 'Zur Sitzung'));
        return [$subj, $txt, $html];
    });
}

function nm_event_top_kommentar($pdo, $meeting_id, $top_title, $meeting_date, $meeting_name, $comment_text, $author_name) {
    $url   = nm_site_url($pdo) . 'index.php?tab=agenda&meeting_id=' . urlencode($meeting_id);
    $datum = $meeting_date ? date('d.m.Y', strtotime((string)$meeting_date)) : '—';
    nm_notify_all($pdo, 'top_kommentar', function($m) use ($top_title, $datum, $meeting_name, $comment_text, $author_name, $url, $pdo) {
        $subj = 'Kommentar zu TOP: ' . mb_substr($top_title, 0, 55);
        $txt  = "Kommentar zu einem TOP:\n\nSitzung: {$meeting_name} ({$datum})\nTOP: {$top_title}\nVon: {$author_name}\n\n{$comment_text}\n\nLink: {$url}";
        $html = nm_html_wrap($pdo,
            '<h2 style="margin:0 0 16px;color:#1a1a1a;font-size:18px;">Kommentar zu TOP</h2>'
            . '<table style="border-collapse:collapse;margin-bottom:14px;">'
            . nm_tbl_row('Sitzung', $meeting_name . ' (' . $datum . ')')
            . nm_tbl_row('TOP', $top_title)
            . nm_tbl_row('Von', $author_name)
            . '</table>'
            . '<blockquote style="margin:0;padding:12px 16px;background:#f0f5ff;'
            . 'border-left:4px solid #0055aa;border-radius:0 4px 4px 0;font-size:14px;color:#333;">'
            . nl2br(htmlspecialchars($comment_text)) . '</blockquote>'
            . nm_btn($url, 'Zur Sitzung'));
        return [$subj, $txt, $html];
    });
}

function nm_event_todo_zugewiesen($pdo, $assignee_member, $todo_title, $todo_desc, $due_date, $creator_name) {
    if (empty($assignee_member['email'])) return;
    $aktiv = (int)($assignee_member['aktiv'] ?? 0);
    if ($aktiv <= 10) return;
    if (!nm_has_pref($pdo, $assignee_member['member_id'], 'todo_zugewiesen', $aktiv)) return;

    $url    = nm_site_url($pdo) . 'index.php?tab=todos';
    $due    = $due_date ? date('d.m.Y', strtotime((string)$due_date)) : 'kein Fälligkeitsdatum';
    $vorname = $assignee_member['first_name'] ?? '';
    $subj   = 'Neues ToDo: ' . $todo_title;
    $txt    = "Hallo {$vorname},\n\nIhnen wurde ein neues ToDo zugewiesen:\n\nTitel: {$todo_title}\n"
            . "Von: {$creator_name}\nFällig: {$due}\n\n" . ($todo_desc ? "Beschreibung:\n{$todo_desc}\n\n" : '')
            . "Link: {$url}";
    $desc_html = $todo_desc
        ? '<p style="margin:12px 0 0;padding:12px;background:#f4f6f8;border-radius:4px;font-size:14px;color:#333;">'
          . nl2br(htmlspecialchars($todo_desc)) . '</p>'
        : '';
    $html = nm_html_wrap($pdo,
        '<h2 style="margin:0 0 8px;color:#1a1a1a;font-size:18px;">Neues ToDo</h2>'
        . '<p style="margin:0 0 16px;color:#555;font-size:14px;">Hallo ' . htmlspecialchars($vorname) . ',</p>'
        . '<table style="border-collapse:collapse;">'
        . nm_tbl_row('Titel', $todo_title)
        . nm_tbl_row('Von', $creator_name)
        . nm_tbl_row('Fällig', $due)
        . '</table>' . $desc_html . nm_btn($url, 'Zur ToDo-Liste'));

    $mode = nm_get_delivery_mode($pdo, $assignee_member['member_id']);
    nm_enqueue($pdo, $assignee_member['member_id'], 'todo_zugewiesen', $subj, $txt, $html, $mode);
}

// ------------------------------------------------------------------
// Pseudo-Cron-Verarbeitung
// ------------------------------------------------------------------

/**
 * Versendet alle ausstehenden Sofort-Benachrichtigungen (is_digest=0).
 * Wird von pseudo_cron.php jede Minute aufgerufen.
 */
function nm_process_immediate($pdo) {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) return;
    if (!function_exists('multipartmail')) {
        if (file_exists(__DIR__ . '/mail_functions.php')) require_once __DIR__ . '/mail_functions.php';
        if (!function_exists('multipartmail')) return;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT id, member_id, subject, body_text, body_html
            FROM svmail_notifications
            WHERE is_digest = 0 AND sent_at IS NULL
            ORDER BY created_at ASC
            LIMIT 30
        ");
        $stmt->execute();
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('nm_process_immediate: ' . $e->getMessage());
        return;
    }
    if (empty($pending)) return;

    $all_members = function_exists('get_all_members') ? get_all_members($pdo) : [];
    $mmap = [];
    foreach ($all_members as $m) $mmap[(int)$m['member_id']] = $m;

    $now = date('Y-m-d H:i:s');
    $sent = 0;
    foreach ($pending as $notif) {
        $member = $mmap[(int)$notif['member_id']] ?? null;
        $email  = $member['email'] ?? '';
        $pdo->prepare("UPDATE svmail_notifications SET sent_at = ? WHERE id = ?")->execute([$now, $notif['id']]);
        if (!$email) continue;
        try {
            multipartmail($email, $notif['subject'], $notif['body_text'], $notif['body_html'], MAIL_FROM, MAIL_FROM_NAME);
            $sent++;
        } catch (Exception $e) {
            error_log('nm_process_immediate member=' . $notif['member_id'] . ': ' . $e->getMessage());
        }
    }
    if ($sent > 0) {
        @file_put_contents(__DIR__ . '/pseudo_cron.log',
            '[' . date('Y-m-d H:i:s') . "] NM-Sofort: {$sent} Mail(s) versendet\n", FILE_APPEND);
    }
}

/**
 * Sendet die Tageszusammenfassung an alle Digest-Empfänger.
 * Wird von pseudo_cron.php einmal täglich nach der konfigurierten Stunde aufgerufen.
 */
function nm_process_digest($pdo) {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) return;
    if (!function_exists('multipartmail')) {
        if (file_exists(__DIR__ . '/mail_functions.php')) require_once __DIR__ . '/mail_functions.php';
        if (!function_exists('multipartmail')) return;
    }
    try {
        $stmt = $pdo->query("SELECT DISTINCT member_id FROM svmail_notifications WHERE is_digest = 1 AND sent_at IS NULL");
        $member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log('nm_process_digest: ' . $e->getMessage());
        return;
    }
    if (empty($member_ids)) return;

    $all_members = function_exists('get_all_members') ? get_all_members($pdo) : [];
    $mmap = [];
    foreach ($all_members as $m) $mmap[(int)$m['member_id']] = $m;

    $site_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Sitzungsverwaltung';
    $today = date('d.m.Y');
    $now   = date('Y-m-d H:i:s');
    $prefs_url = nm_site_url($pdo) . 'meine_benachrichtigungen.php';
    $sent  = 0;

    foreach ($member_ids as $mid) {
        $mid    = (int)$mid;
        $member = $mmap[$mid] ?? null;
        $email  = $member['email'] ?? '';

        $items_stmt = $pdo->prepare("
            SELECT subject, body_text FROM svmail_notifications
            WHERE member_id = ? AND is_digest = 1 AND sent_at IS NULL
            ORDER BY created_at ASC
        ");
        $items_stmt->execute([$mid]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        $pdo->prepare("UPDATE svmail_notifications SET sent_at = ? WHERE member_id = ? AND is_digest = 1 AND sent_at IS NULL")
            ->execute([$now, $mid]);

        if (!$email || empty($items)) continue;

        $vorname = $member['first_name'] ?? '';
        $subj    = $site_name . ' – Zusammenfassung ' . $today;

        // Text-Version
        $txt = "Guten Abend {$vorname},\n\nIhre Zusammenfassung für den {$today}:\n\n";
        foreach ($items as $item) {
            $txt .= "▶ " . $item['subject'] . "\n" . $item['body_text'] . "\n\n" . str_repeat('-', 50) . "\n\n";
        }
        $txt .= "Benachrichtigungseinstellungen: {$prefs_url}";

        // HTML-Version
        $items_html = '';
        foreach ($items as $item) {
            $items_html .= '<div style="margin-bottom:16px;padding:14px 16px;background:#f4f7fb;'
                . 'border-left:4px solid #0055aa;border-radius:0 6px 6px 0;">'
                . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#0055aa;text-transform:uppercase;">'
                . htmlspecialchars($item['subject']) . '</p>'
                . '<p style="margin:0;font-size:13px;color:#333;white-space:pre-wrap;">'
                . htmlspecialchars($item['body_text']) . '</p></div>';
        }

        $content = '<h2 style="margin:0 0 6px;color:#1a1a1a;font-size:18px;">Zusammenfassung</h2>'
            . '<p style="margin:0 0 18px;font-size:14px;color:#555;">Guten Abend '
            . htmlspecialchars($vorname) . ', hier Ihre Meldungen für den ' . $today . ':</p>'
            . $items_html;

        $name_h  = htmlspecialchars($site_name);
        $prefs_h = htmlspecialchars($prefs_url);
        $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">'
            . '<div style="max-width:580px;margin:24px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.12);">'
            . '<div style="background:#0055aa;padding:18px 28px;"><p style="margin:0;color:#fff;font-size:15px;font-weight:600;">' . $name_h . '</p></div>'
            . '<div style="padding:24px 28px;">' . $content . '</div>'
            . '<div style="padding:12px 28px;background:#f8f9fb;border-top:1px solid #e4e7ec;">'
            . '<p style="margin:0;font-size:11px;color:#999;"><a href="' . $prefs_h . '" style="color:#0055aa;text-decoration:none;">Benachrichtigungseinstellungen</a></p>'
            . '</div></div></body></html>';

        try {
            multipartmail($email, $subj, $txt, $html, MAIL_FROM, MAIL_FROM_NAME);
            $sent++;
        } catch (Exception $e) {
            error_log('nm_process_digest member=' . $mid . ': ' . $e->getMessage());
        }
    }
    if ($sent > 0) {
        @file_put_contents(__DIR__ . '/pseudo_cron.log',
            '[' . date('Y-m-d H:i:s') . "] NM-Digest: {$sent} Zusammenfassung(en) versendet\n", FILE_APPEND);
    }
}
