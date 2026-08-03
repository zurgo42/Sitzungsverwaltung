<?php
/**
 * mail_functions.php - E-Mail-Funktionen für die Sitzungsverwaltung
 * Erstellt: 17.11.2025
 * Erweitert: 17.11.2025 (Multi-Backend-Support)
 *
 * Stellt E-Mail-Funktionen bereit für:
 * - Terminplanung-Benachrichtigungen
 * - Meeting-Einladungen
 * - Protokoll-Benachrichtigungen
 *
 * KONFIGURATION: siehe config.php
 * - MAIL_ENABLED: E-Mail-Versand aktivieren/deaktivieren
 * - MAIL_BACKEND: 'mail' (Standard), 'phpmailer', 'queue'
 * - MAIL_FROM: Absender-E-Mail-Adresse
 * - MAIL_FROM_NAME: Absender-Name
 * - Für PHPMailer: SMTP_HOST, SMTP_PORT, SMTP_AUTH, SMTP_SECURE, SMTP_USER, SMTP_PASS
 * - Für Queue: $pdo wird benötigt
 *
 * BACKENDS:
 * - 'mail':      Standard PHP mail() - funktioniert überall
 * - 'phpmailer': Verwendet PHPMailer library wenn verfügbar (besser für SMTP)
 * - 'queue':     Speichert Mails in Datenbank, Versand via Cronjob (process_mail_queue.php)
 */

/**
 * Sendet eine HTML-E-Mail (multipart: text + HTML)
 *
 * Diese Funktion entspricht der multipartmail() aus der anderen Anwendung
 * und ist kompatibel mit beiden Systemen.
 *
 * WICHTIG: Wenn MAIL_BACKEND='queue', wird globale Variable $pdo benötigt
 *
 * @param string $to Empfänger-E-Mail-Adresse
 * @param string $subject Betreff
 * @param string $message_text Text-Version der Nachricht
 * @param string $message_html HTML-Version der Nachricht
 * @param string|null $from_email Optional: Absender-E-Mail (Standard: aus config.php)
 * @param string|null $from_name Optional: Absender-Name (Standard: aus config.php)
 * @return bool true bei Erfolg, false bei Fehler
 */
function multipartmail($to, $subject, $message_text, $message_html = '', $from_email = null, $from_name = null) {
    // Mail-Versand deaktiviert?
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        error_log("Mail-Versand deaktiviert (MAIL_ENABLED=false): An $to - $subject");
        return true; // Kein Fehler, nur deaktiviert
    }

    // Defaults aus Config
    $from_email = $from_email ?? (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com');
    $from_name = $from_name ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Meeting-System');

    // HTML-Version generieren falls nicht vorhanden
    if (empty($message_html)) {
        $message_html = nl2br(htmlspecialchars($message_text));
    }

    // Backend ermitteln
    $backend = defined('MAIL_BACKEND') ? MAIL_BACKEND : 'mail';

    // Backend-spezifischer Versand
    switch ($backend) {
        case 'phpmailer':
            return send_via_phpmailer($to, $subject, $message_text, $message_html, $from_email, $from_name);

        case 'queue':
            return queue_mail($to, $subject, $message_text, $message_html, $from_email, $from_name);

        case 'mail':
        default:
            return send_via_mail($to, $subject, $message_text, $message_html, $from_email, $from_name);
    }
}

/**
 * Sendet E-Mail via PHP mail() - Standard-Backend
 *
 * @param string $to Empfänger
 * @param string $subject Betreff
 * @param string $message_text Text-Version
 * @param string $message_html HTML-Version
 * @param string $from_email Absender-E-Mail
 * @param string $from_name Absender-Name
 * @return bool true bei Erfolg
 */
function send_via_mail($to, $subject, $message_text, $message_html, $from_email, $from_name) {
    // Boundary für Multipart
    $boundary = md5(uniqid(time()));

    // Betreff MIME-encoden (für Umlaute)
    $subject_encoded = mb_encode_mimeheader($subject, 'UTF-8');

    // From-Name MIME-encoden (für Umlaute im Namen)
    $from_name_encoded = mb_encode_mimeheader($from_name, 'UTF-8');

    // Headers
    $headers = [];
    $headers[] = "From: $from_name_encoded <$from_email>";
    $headers[] = "Reply-To: $from_email";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
    $headers[] = "X-Mailer: PHP/" . phpversion();

    // Body zusammensetzen
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $message_text . "\r\n\r\n";

    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $message_html . "\r\n\r\n";

    $body .= "--$boundary--";

    // E-Mail senden (mit encodiertem Subject)
    $result = mail($to, $subject_encoded, $body, implode("\r\n", $headers));

    if (!$result) {
        error_log("Mail-Versand (mail) fehlgeschlagen: An $to - $subject");
    }

    return $result;
}

/**
 * Sendet E-Mail via PHPMailer (falls installiert)
 *
 * Fallback auf send_via_mail() wenn PHPMailer nicht verfügbar
 *
 * @param string $to Empfänger
 * @param string $subject Betreff
 * @param string $message_text Text-Version
 * @param string $message_html HTML-Version
 * @param string $from_email Absender-E-Mail
 * @param string $from_name Absender-Name
 * @return bool true bei Erfolg
 */
function send_via_phpmailer($to, $subject, $message_text, $message_html, $from_email, $from_name) {
    // Prüfen ob PHPMailer verfügbar ist
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Versuche Autoload
        $phpmailer_path = __DIR__ . '/vendor/autoload.php';
        if (file_exists($phpmailer_path)) {
            require_once $phpmailer_path;
        }
    }

    // PHPMailer nicht verfügbar -> Fallback auf mail()
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer nicht verfügbar - Fallback auf mail()");
        return send_via_mail($to, $subject, $message_text, $message_html, $from_email, $from_name);
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // SMTP-Konfiguration (falls definiert)
        if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';

            if (defined('SMTP_AUTH') && SMTP_AUTH) {
                $mail->SMTPAuth = true;
                $mail->Username = defined('SMTP_USER') ? SMTP_USER : '';
                $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
            }
        }

        // Absender
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        $mail->addReplyTo($from_email, $from_name);

        // Inhalt
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $message_html;
        $mail->AltBody = $message_text;

        // Senden
        $result = $mail->send();

        if (!$result) {
            error_log("Mail-Versand (PHPMailer) fehlgeschlagen: An $to - $subject");
        }

        return $result;

    } catch (Exception $e) {
        error_log("PHPMailer Exception: " . $e->getMessage());
        // Fallback auf mail()
        return send_via_mail($to, $subject, $message_text, $message_html, $from_email, $from_name);
    }
}

/**
 * Fügt E-Mail zur Warteschlange hinzu (Queue-Backend)
 *
 * Speichert Mail in mail_queue Tabelle zur späteren Verarbeitung via Cronjob
 * Benötigt globale Variable $pdo
 *
 * @param string $to Empfänger
 * @param string $subject Betreff
 * @param string $message_text Text-Version
 * @param string $message_html HTML-Version
 * @param string $from_email Absender-E-Mail
 * @param string $from_name Absender-Name
 * @return bool true bei Erfolg (Mail wurde zur Queue hinzugefügt)
 */
function queue_mail($to, $subject, $message_text, $message_html, $from_email, $from_name) {
    global $pdo;

    if (!$pdo) {
        error_log("Queue-Mail: \$pdo nicht verfügbar - Fallback auf mail()");
        return send_via_mail($to, $subject, $message_text, $message_html, $from_email, $from_name);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO svmail_queue
            (recipient, subject, message_text, message_html, from_email, from_name, status, created_at, priority)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), 5)
        ");

        $result = $stmt->execute([
            $to,
            $subject,
            $message_text,
            $message_html,
            $from_email,
            $from_name
        ]);

        if (!$result) {
            error_log("Queue-Mail: INSERT fehlgeschlagen - Fallback auf mail()");
            return send_via_mail($to, $subject, $message_text, $message_html, $from_email, $from_name);
        }

        return true;

    } catch (Exception $e) {
        error_log("Queue-Mail Exception: " . $e->getMessage() . " - Fallback auf mail()");
        return send_via_mail($to, $subject, $message_text, $message_html, $from_email, $from_name);
    }
}

/**
 * Sendet Einladungsmail für neue Umfrage an ausgewählte Teilnehmer
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $poll_id ID der Umfrage
 * @param string|null $host_url_base Optional: Basis-URL für Links
 * @return int Anzahl der versendeten E-Mails
 */
function send_poll_invitation($pdo, $poll_id, $host_url_base = null) {
    // Umfrage-Daten laden
    $stmt = $pdo->prepare("
        SELECT p.*
        FROM svpolls p
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        return 0;
    }

    // Creator-Daten über Adapter laden
    if ($poll['created_by_member_id']) {
        $creator = get_member_by_id($pdo, $poll['created_by_member_id']);
        if ($creator) {
            $poll['first_name'] = $creator['first_name'];
            $poll['last_name'] = $creator['last_name'];
            $poll['creator_email'] = $creator['email'];
        }
    }

    // Terminvorschläge laden
    $stmt = $pdo->prepare("
        SELECT * FROM svpoll_dates
        WHERE poll_id = ?
        ORDER BY suggested_date ASC
    ");
    $stmt->execute([$poll_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ausgewählte Teilnehmer laden
    $stmt = $pdo->prepare("
        SELECT pp.member_id
        FROM svpoll_participants pp
        WHERE pp.poll_id = ?
    ");
    $stmt->execute([$poll_id]);
    $participant_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Member-Daten über Adapter laden (nur mit E-Mail)
    $participants = [];
    foreach ($participant_ids as $member_id) {
        $member = get_member_by_id($pdo, $member_id);
        if ($member && !empty($member['email'])) {
            $participants[] = [
                'email' => $member['email'],
                'first_name' => $member['first_name'],
                'last_name' => $member['last_name']
            ];
        }
    }

    if (empty($participants)) {
        return 0;
    }

    // Link zur Umfrage
    $poll_link = '';
    if ($host_url_base) {
        $poll_link = rtrim($host_url_base, '/') . '/?tab=termine&view=poll&poll_id=' . $poll_id;
    }

    // E-Mail-Betreff
    $subject = "Terminumfrage: " . $poll['title'];

    // E-Mail-Text (Plain)
    $message_text = "Hallo,\n\n";
    $message_text .= "{$poll['first_name']} {$poll['last_name']} hat eine neue Terminumfrage erstellt:\n\n";
    $message_text .= "📋 Titel: {$poll['title']}\n\n";

    if (!empty($poll['description'])) {
        $message_text .= "Beschreibung:\n{$poll['description']}\n\n";
    }

    $message_text .= "Terminvorschläge:\n";
    foreach ($dates as $idx => $date) {
        $date_str = date('d.m.Y', strtotime($date['suggested_date']));
        $time_start = date('H:i', strtotime($date['suggested_date']));
        $time_end = !empty($date['suggested_end_date']) ? date('H:i', strtotime($date['suggested_end_date'])) : '';
        $time_str = $time_start . (!empty($time_end) ? ' - ' . $time_end : '') . ' Uhr';
        $message_text .= "  " . ($idx + 1) . ". $date_str, $time_str\n";
    }
    $message_text .= "\n";

    if ($poll_link) {
        $message_text .= "Bitte stimme ab unter:\n$poll_link\n\n";
    }

    $message_text .= "---\n";
    $message_text .= "Diese Nachricht wurde automatisch vom Meeting-System versendet.\n";
    $message_text .= "Erstellt von: {$poll['first_name']} {$poll['last_name']}";

    // E-Mail-HTML
    $message_html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #4CAF50; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .date-list { background: white; border-left: 4px solid #4CAF50; padding: 15px; margin: 20px 0; }
            .date-list li { margin: 8px 0; }
            .footer { background: #f5f5f5; padding: 15px; border-radius: 0 0 5px 5px; font-size: 12px; color: #666; }
            .btn { display: inline-block; background: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📋 Neue Terminumfrage</h2>
            </div>
            <div class='content'>
                <p>Hallo,</p>
                <p><strong>" . htmlspecialchars($poll['first_name'] . ' ' . $poll['last_name']) . "</strong> hat eine neue Terminumfrage erstellt:</p>
                <h3>" . htmlspecialchars($poll['title']) . "</h3>
    ";

    if (!empty($poll['description'])) {
        $message_html .= "<p>" . nl2br(htmlspecialchars($poll['description'])) . "</p>";
    }

    $message_html .= "<div class='date-list'><strong>Terminvorschläge:</strong><ol>";
    foreach ($dates as $date) {
        $date_str = date('d.m.Y', strtotime($date['suggested_date']));
        $time_start = date('H:i', strtotime($date['suggested_date']));
        $time_end = !empty($date['suggested_end_date']) ? date('H:i', strtotime($date['suggested_end_date'])) : '';
        $time_str = $time_start . (!empty($time_end) ? ' - ' . $time_end : '') . ' Uhr';
        $message_html .= "<li>$date_str, $time_str</li>";
    }
    $message_html .= "</ol></div>";

    $message_html .= "<p><strong>Bitte stimme ab!</strong></p>";

    if ($poll_link) {
        $message_html .= "<a href='$poll_link' class='btn'>Zur Umfrage &rarr;</a>";
    }

    $message_html .= "
            </div>
            <div class='footer'>
                Diese Nachricht wurde automatisch vom Meeting-System versendet.<br>
                Erstellt von: " . htmlspecialchars($poll['first_name'] . ' ' . $poll['last_name']) . "
            </div>
        </div>
    </body>
    </html>
    ";

    // E-Mails versenden
    $sent_count = 0;
    foreach ($participants as $participant) {
        if (multipartmail(
            $participant['email'],
            $subject,
            $message_text,
            $message_html
        )) {
            $sent_count++;
        }
    }

    return $sent_count;
}

/**
 * Sendet Benachrichtigung über finalisierten Termin an Umfrage-Teilnehmer
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $poll_id ID der Umfrage
 * @param int $final_date_id ID des finalen Termins
 * @param string|null $host_url_base Optional: Basis-URL für Links (z.B. https://example.com)
 * @param string $recipients 'voters' (nur Abstimmende), 'all' (alle Teilnehmer), 'none' (keine Mail)
 * @return int Anzahl der versendeten E-Mails
 */
function send_poll_finalization_notification($pdo, $poll_id, $final_date_id, $host_url_base = null, $recipients = 'voters') {
    // Keine E-Mail senden
    if ($recipients === 'none') {
        return 0;
    }

    // Umfrage-Daten laden
    $stmt = $pdo->prepare("
        SELECT p.*
        FROM svpolls p
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        return 0;
    }

    // Creator-Daten über Adapter laden
    if ($poll['created_by_member_id']) {
        $creator = get_member_by_id($pdo, $poll['created_by_member_id']);
        if ($creator) {
            $poll['first_name'] = $creator['first_name'];
            $poll['last_name'] = $creator['last_name'];
            $poll['creator_email'] = $creator['email'];
        }
    }

    // Finalen Termin laden
    $stmt = $pdo->prepare("SELECT * FROM svpoll_dates WHERE date_id = ?");
    $stmt->execute([$final_date_id]);
    $final_date = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$final_date) {
        return 0;
    }

    // Teilnehmer laden (je nach Auswahl)
    if ($recipients === 'all') {
        // Alle ausgewählten Teilnehmer
        $stmt = $pdo->prepare("
            SELECT DISTINCT pp.member_id
            FROM svpoll_participants pp
            WHERE pp.poll_id = ?
        ");
        $stmt->execute([$poll_id]);
        $member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Nur Teilnehmer die abgestimmt haben (Standard)
        $stmt = $pdo->prepare("
            SELECT DISTINCT pr.member_id
            FROM svpoll_responses pr
            WHERE pr.poll_id = ?
        ");
        $stmt->execute([$poll_id]);
        $member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Member-Daten über Adapter laden (nur mit E-Mail)
    $participants = [];
    foreach ($member_ids as $member_id) {
        $member = get_member_by_id($pdo, $member_id);
        if ($member && !empty($member['email'])) {
            // Duplikate vermeiden
            $email_exists = false;
            foreach ($participants as $p) {
                if ($p['email'] === $member['email']) {
                    $email_exists = true;
                    break;
                }
            }
            if (!$email_exists) {
                $participants[] = [
                    'email' => $member['email'],
                    'first_name' => $member['first_name'],
                    'last_name' => $member['last_name']
                ];
            }
        }
    }

    // Termin formatieren
    $date_str = date('l, d.m.Y', strtotime($final_date['suggested_date']));
    $time_start = date('H:i', strtotime($final_date['suggested_date']));
    $time_end = !empty($final_date['suggested_end_date']) ? date('H:i', strtotime($final_date['suggested_end_date'])) : '';
    $time_str = $time_start . (!empty($time_end) ? ' - ' . $time_end : '') . ' Uhr';

    $location_str = !empty($final_date['location']) ? $final_date['location'] : 'Siehe Einladung';

    // Link zur Umfrage
    $poll_link = '';
    if ($host_url_base) {
        $poll_link = rtrim($host_url_base, '/') . '/?tab=termine&view=poll&poll_id=' . $poll_id;
    }

    // E-Mail-Betreff
    $subject = "Termin festgelegt: " . $poll['title'];

    // E-Mail-Text (Plain)
    $message_text = "Hallo,\n\n";
    $message_text .= "der finale Termin für \"{$poll['title']}\" wurde festgelegt:\n\n";
    $message_text .= "📅 Datum:  $date_str\n";
    $message_text .= "🕐 Uhrzeit: $time_str\n";
    $message_text .= "📍 Ort:    $location_str\n\n";

    if (!empty($final_date['notes'])) {
        $message_text .= "ℹ️  Hinweis: {$final_date['notes']}\n\n";
    }

    if ($poll_link) {
        $message_text .= "Details zur Umfrage: $poll_link\n\n";
    }

    $message_text .= "Bitte merke dir den Termin vor!\n\n";
    $message_text .= "---\n";
    $message_text .= "Diese Nachricht wurde automatisch vom Meeting-System versendet.\n";
    $message_text .= "Erstellt von: {$poll['first_name']} {$poll['last_name']}";

    // E-Mail-HTML
    $message_html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2196F3; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .date-box { background: white; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; }
            .date-box strong { display: block; margin-bottom: 5px; }
            .footer { background: #f5f5f5; padding: 15px; border-radius: 0 0 5px 5px; font-size: 12px; color: #666; }
            .btn { display: inline-block; background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>✅ Termin festgelegt</h2>
            </div>
            <div class='content'>
                <p>Hallo,</p>
                <p>der finale Termin für <strong>" . htmlspecialchars($poll['title']) . "</strong> wurde festgelegt:</p>

                <div class='date-box'>
                    <strong>📅 Datum:</strong> $date_str<br>
                    <strong>🕐 Uhrzeit:</strong> $time_str<br>
                    <strong>📍 Ort:</strong> " . htmlspecialchars($location_str) . "
    ";

    if (!empty($final_date['notes'])) {
        $message_html .= "<br><br><strong>ℹ️ Hinweis:</strong> " . htmlspecialchars($final_date['notes']);
    }

    $message_html .= "
                </div>

                <p>Bitte merke dir den Termin vor!</p>
    ";

    if ($poll_link) {
        $message_html .= "<a href='$poll_link' class='btn'>Umfrage ansehen</a>";
    }

    $message_html .= "
            </div>
            <div class='footer'>
                Diese Nachricht wurde automatisch vom Meeting-System versendet.<br>
                Erstellt von: " . htmlspecialchars($poll['first_name'] . ' ' . $poll['last_name']) . "
            </div>
        </div>
    </body>
    </html>
    ";

    // E-Mails versenden
    $sent_count = 0;
    foreach ($participants as $participant) {
        if (multipartmail(
            $participant['email'],
            $subject,
            $message_text,
            $message_html
        )) {
            $sent_count++;
        }
    }

    return $sent_count;
}

/**
 * Sendet einfache Benachrichtigung (nur Text)
 *
 * @param string $to Empfänger-E-Mail-Adresse
 * @param string $subject Betreff
 * @param string $message Nachricht
 * @param string|null $from Optional: Absender-E-Mail
 * @return bool true bei Erfolg
 */
function send_simple_mail($to, $subject, $message, $from = null) {
    return multipartmail($to, $subject, $message, '', $from);
}

/**
 * Sendet Erinnerungsmail für finalisierten Termin
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $poll_id ID der Umfrage
 * @param string|null $host_url_base Optional: Basis-URL für Links
 * @return int Anzahl der versendeten E-Mails
 */
function send_poll_reminder($pdo, $poll_id, $host_url_base = null) {
    // Umfrage-Daten laden
    $stmt = $pdo->prepare("
        SELECT p.*
        FROM svpolls p
        WHERE p.poll_id = ? AND p.status = 'finalized' AND p.reminder_enabled = 1 AND p.reminder_sent = 0
    ");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll || empty($poll['final_date_id'])) {
        return 0;
    }

    // Creator-Daten über Adapter laden
    if ($poll['created_by_member_id']) {
        $creator = get_member_by_id($pdo, $poll['created_by_member_id']);
        if ($creator) {
            $poll['first_name'] = $creator['first_name'];
            $poll['last_name'] = $creator['last_name'];
            $poll['creator_email'] = $creator['email'];
        }
    }

    // Finalen Termin laden
    $stmt = $pdo->prepare("SELECT * FROM svpoll_dates WHERE date_id = ?");
    $stmt->execute([$poll['final_date_id']]);
    $final_date = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$final_date) {
        return 0;
    }

    // Empfänger bestimmen
    $recipients_type = $poll['reminder_recipients'] ?? 'voters';

    if ($recipients_type === 'none') {
        return 0;
    }

    if ($recipients_type === 'all') {
        // Alle ausgewählten Teilnehmer
        $stmt = $pdo->prepare("
            SELECT DISTINCT pp.member_id
            FROM svpoll_participants pp
            WHERE pp.poll_id = ?
        ");
        $stmt->execute([$poll_id]);
        $member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        // Nur Teilnehmer die abgestimmt haben
        $stmt = $pdo->prepare("
            SELECT DISTINCT pr.member_id
            FROM svpoll_responses pr
            WHERE pr.poll_id = ?
        ");
        $stmt->execute([$poll_id]);
        $member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Member-Daten über Adapter laden (nur mit E-Mail)
    $participants = [];
    foreach ($member_ids as $member_id) {
        $member = get_member_by_id($pdo, $member_id);
        if ($member && !empty($member['email'])) {
            // Duplikate vermeiden
            $email_exists = false;
            foreach ($participants as $p) {
                if ($p['email'] === $member['email']) {
                    $email_exists = true;
                    break;
                }
            }
            if (!$email_exists) {
                $participants[] = [
                    'email' => $member['email'],
                    'first_name' => $member['first_name'],
                    'last_name' => $member['last_name']
                ];
            }
        }
    }

    if (empty($participants)) {
        return 0;
    }

    // Termin formatieren
    $date_str = date('l, d.m.Y', strtotime($final_date['suggested_date']));
    $time_start = date('H:i', strtotime($final_date['suggested_date']));
    $time_end = !empty($final_date['suggested_end_date']) ? date('H:i', strtotime($final_date['suggested_end_date'])) : '';
    $time_str = $time_start . (!empty($time_end) ? ' - ' . $time_end : '') . ' Uhr';

    $location_str = !empty($final_date['location']) ? $final_date['location'] : 'Siehe Einladung';

    // Berechne Tage bis zum Termin
    $days_until = ceil((strtotime($final_date['suggested_date']) - time()) / 86400);
    $days_text = $days_until == 1 ? 'morgen' : "in $days_until Tagen";

    // Link zur Umfrage
    $poll_link = '';
    if ($host_url_base) {
        $poll_link = rtrim($host_url_base, '/') . '/?tab=termine&view=poll&poll_id=' . $poll_id;
    }

    // E-Mail-Betreff
    $subject = "Erinnerung: " . $poll['title'] . " $days_text";

    // E-Mail-Text (Plain)
    $message_text = "Hallo,\n\n";
    $message_text .= "dies ist eine Erinnerung an den Termin \"{$poll['title']}\":\n\n";
    $message_text .= "📅 Datum:  $date_str ($days_text)\n";
    $message_text .= "🕐 Uhrzeit: $time_str\n";
    $message_text .= "📍 Ort:    $location_str\n\n";

    if (!empty($final_date['notes'])) {
        $message_text .= "ℹ️  Hinweis: {$final_date['notes']}\n\n";
    }

    if ($poll_link) {
        $message_text .= "Details zur Umfrage: $poll_link\n\n";
    }

    $message_text .= "Wir freuen uns auf deine Teilnahme!\n\n";
    $message_text .= "---\n";
    $message_text .= "Diese Nachricht wurde automatisch vom Meeting-System versendet.\n";
    $message_text .= "Erstellt von: {$poll['first_name']} {$poll['last_name']}";

    // E-Mail-HTML
    $message_html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #FF9800; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .date-box { background: white; border-left: 4px solid #FF9800; padding: 15px; margin: 20px 0; }
            .date-box strong { display: block; margin-bottom: 5px; }
            .footer { background: #f5f5f5; padding: 15px; border-radius: 0 0 5px 5px; font-size: 12px; color: #666; }
            .btn { display: inline-block; background: #FF9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 15px; }
            .reminder-badge { display: inline-block; background: #FF9800; color: white; padding: 5px 10px; border-radius: 3px; font-size: 14px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔔 Terminerinnerung</h2>
            </div>
            <div class='content'>
                <p>Hallo,</p>
                <p>dies ist eine Erinnerung an den Termin:</p>
                <h3>" . htmlspecialchars($poll['title']) . "</h3>
                <p><span class='reminder-badge'>$days_text</span></p>

                <div class='date-box'>
                    <strong>📅 Datum:</strong> $date_str<br>
                    <strong>🕐 Uhrzeit:</strong> $time_str<br>
                    <strong>📍 Ort:</strong> " . htmlspecialchars($location_str) . "
    ";

    if (!empty($final_date['notes'])) {
        $message_html .= "<br><br><strong>ℹ️ Hinweis:</strong> " . htmlspecialchars($final_date['notes']);
    }

    $message_html .= "
                </div>

                <p>Wir freuen uns auf deine Teilnahme!</p>
    ";

    if ($poll_link) {
        $message_html .= "<a href='$poll_link' class='btn'>Umfrage ansehen</a>";
    }

    $message_html .= "
            </div>
            <div class='footer'>
                Diese Nachricht wurde automatisch vom Meeting-System versendet.<br>
                Erstellt von: " . htmlspecialchars($poll['first_name'] . ' ' . $poll['last_name']) . "
            </div>
        </div>
    </body>
    </html>
    ";

    // E-Mails versenden
    $sent_count = 0;
    foreach ($participants as $participant) {
        if (multipartmail(
            $participant['email'],
            $subject,
            $message_text,
            $message_html
        )) {
            $sent_count++;
        }
    }

    return $sent_count;
}

/**
 * Sendet Test-E-Mail (für Debugging)
 *
 * @param string $to Empfänger
 * @return bool true bei Erfolg
 */
function send_test_mail($to) {
    $subject = "Test-E-Mail vom Meeting-System";
    $message = "Dies ist eine Test-E-Mail.\n\nZeitstempel: " . date('Y-m-d H:i:s');
    return send_simple_mail($to, $subject, $message);
}

/**
 * Sendet die Tagesordnungs-Erinnerungsmail nach Ablauf der Antragsschlussfrist.
 *
 * Empfänger: Sitzungs-Teilnehmer mit E-Mail-Adresse + optionale Zusatzadressen aus agenda_reminder_emails.
 * Inhalt: Sitzungsname, Datum/Uhrzeit, Liste aller TOPs außer category='wahl', je als Link auf die Sitzung.
 *
 * @param PDO    $pdo        Datenbankverbindung
 * @param int    $meeting_id ID der Sitzung
 * @param string $base_url   Basis-URL für Links (z.B. 'https://example.com/Sitzungsverwaltung')
 * @return int   Anzahl versendeter Mails
 */
function send_agenda_reminder_mail($pdo, $meeting_id, $base_url = '') {
    // Sitzungsdaten laden
    $stmt = $pdo->prepare("SELECT * FROM svmeetings WHERE meeting_id = ?");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$meeting || !$meeting['send_agenda_reminder'] || $meeting['agenda_reminder_sent']) {
        return 0;
    }

    // Basis-URL: svconfig hat Vorrang vor übergebenem $base_url
    $cfg_url_stmt = @$pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'meeting_system_url' LIMIT 1");
    $cfg_url = $cfg_url_stmt ? trim($cfg_url_stmt->fetchColumn() ?: '') : '';
    if ($cfg_url) {
        $base_url = $cfg_url;
    }
    $meeting_base = rtrim($base_url, '/');

    // TOPs laden (ohne 'wahl'-Kategorie, nach Priorität)
    $stmt = $pdo->prepare("
        SELECT item_id, top_number, title, category
        FROM svagenda_items
        WHERE meeting_id = ? AND category != 'wahl'
        ORDER BY priority DESC, top_number ASC
    ");
    $stmt->execute([$meeting_id]);
    $tops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tops)) {
        return 0;
    }

    // Sitzungs-Teilnehmer mit Mitglieds-Daten laden (für personalisierte Anrede)
    $stmt = $pdo->prepare("SELECT mp.member_id FROM svmeeting_participants mp WHERE mp.meeting_id = ?");
    $stmt->execute([$meeting_id]);
    $participant_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Teilnehmer: [email => first_name] für personalisierte Anrede
    $member_recipients = [];
    foreach ($participant_ids as $mid) {
        $member = get_member_by_id($pdo, $mid);
        if ($member && !empty($member['email'])) {
            $member_recipients[$member['email']] = $member['first_name'] ?? '';
        }
    }

    // Zusätzliche Adressen aus dem Meeting-Feld (keine Anrede möglich)
    $extra_recipients = [];
    if (!empty($meeting['agenda_reminder_emails'])) {
        foreach (preg_split('/[\s,;]+/', $meeting['agenda_reminder_emails']) as $addr) {
            $addr = trim($addr);
            if ($addr && filter_var($addr, FILTER_VALIDATE_EMAIL) && !isset($member_recipients[$addr])) {
                $extra_recipients[] = $addr;
            }
        }
    }

    if (empty($member_recipients) && empty($extra_recipients)) {
        $pdo->prepare("UPDATE svmeetings SET agenda_reminder_sent = 1 WHERE meeting_id = ?")->execute([$meeting_id]);
        return 0;
    }

    // Gemeinsame Inhaltsbausteine
    $meeting_date_fmt = date('d.m.Y', strtotime($meeting['meeting_date']));
    $meeting_time_fmt = date('H:i', strtotime($meeting['meeting_date']));
    $meeting_name     = $meeting['meeting_name'] ?: 'Sitzung';
    $meeting_link     = $meeting_base . '/index.php?tab=agenda&meeting_id=' . $meeting_id;

    $subject = "Tagesordnung: {$meeting_name} am {$meeting_date_fmt} um {$meeting_time_fmt} Uhr";

    $location_text = !empty($meeting['location']) ? ' (Ort: ' . $meeting['location'] . ')' : '';
    $location_html = !empty($meeting['location']) ? ' (Ort: ' . htmlspecialchars($meeting['location']) . ')' : '';

    // Hilfsfunktion: Mail-Texte mit individueller Anrede bauen
    $build_mail = function(string $salutation_text, string $salutation_html) use (
        $meeting_name, $meeting_date_fmt, $meeting_time_fmt,
        $location_text, $location_html, $tops, $meeting_link, $meeting_base
    ): array {
        $text  = $salutation_text . "\n\n";
        $text .= "in der Sitzung \"{$meeting_name}\" am {$meeting_date_fmt} um {$meeting_time_fmt} Uhr{$location_text} ";
        $text .= "stehen folgende Themen an.\n";
        $text .= "Bitte ggf. kurzfristig kommentieren, wenn es hierzu Hinweise gibt:\n\n";
        foreach ($tops as $top) {
            $top_link = $meeting_base . '/index.php?tab=agenda&meeting_id=' . explode('=', $meeting_link)[1]
                      . '#top-' . $top['item_id'];
            // Einfacher: meeting_link ist bereits vollständig, nur Fragment anhängen
            $top_url  = $meeting_link . '#top-' . $top['item_id'];
            $text .= "• " . $top['title'] . "\n  " . $top_url . "\n\n";
        }
        $text .= "Zur Sitzung: " . $meeting_link . "\n";

        $html  = '<p>' . $salutation_html . '</p>';
        $html .= '<p>in der Sitzung <strong>' . htmlspecialchars($meeting_name) . '</strong>';
        $html .= ' am <strong>' . $meeting_date_fmt . ' um ' . $meeting_time_fmt . ' Uhr</strong>' . $location_html;
        $html .= ' stehen folgende Themen an.<br>';
        $html .= 'Bitte ggf. kurzfristig kommentieren, wenn es hierzu Hinweise gibt:</p>';
        $html .= '<ol style="line-height:1.8;">';
        foreach ($tops as $top) {
            $top_url = $meeting_link . '#top-' . $top['item_id'];
            $html .= '<li><a href="' . htmlspecialchars($top_url) . '">'
                   . htmlspecialchars($top['title']) . '</a></li>';
        }
        $html .= '</ol>';
        $html .= '<p><a href="' . htmlspecialchars($meeting_link) . '">→ Zur Sitzungsübersicht</a></p>';

        return [$text, $html];
    };

    // Mails versenden
    $sent = 0;

    // Personalisierte Mails an Teilnehmer
    foreach ($member_recipients as $email => $first_name) {
        $anrede = $first_name ? "Liebe/r {$first_name}," : "Guten Tag,";
        [$text, $html] = $build_mail($anrede, htmlspecialchars($anrede));
        if (multipartmail($email, $subject, $text, $html)) {
            $sent++;
        }
    }

    // Generische Mails an Zusatz-Adressen
    if (!empty($extra_recipients)) {
        [$text, $html] = $build_mail("Guten Tag,", "Guten Tag,");
        foreach ($extra_recipients as $email) {
            if (multipartmail($email, $subject, $text, $html)) {
                $sent++;
            }
        }
    }

    // Als gesendet markieren
    $pdo->prepare("UPDATE svmeetings SET agenda_reminder_sent = 1 WHERE meeting_id = ?")->execute([$meeting_id]);

    return $sent;
}
?>
