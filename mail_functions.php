<?php
/**
 * mail_functions.php - E-Mail-Funktionen für die Sitzungsverwaltung
 * Erstellt: 17.11.2025
 *
 * Stellt E-Mail-Funktionen bereit für:
 * - Terminplanung-Benachrichtigungen
 * - Meeting-Einladungen
 * - Protokoll-Benachrichtigungen
 *
 * KONFIGURATION: siehe config.php
 * - MAIL_ENABLED: E-Mail-Versand aktivieren/deaktivieren
 * - MAIL_FROM: Absender-E-Mail-Adresse
 * - MAIL_FROM_NAME: Absender-Name
 */

/**
 * Sendet eine HTML-E-Mail (multipart: text + HTML)
 *
 * Diese Funktion entspricht der multipartmail() aus der anderen Anwendung
 * und ist kompatibel mit beiden Systemen.
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

    // Boundary für Multipart
    $boundary = md5(uniqid(time()));

    // Headers
    $headers = [];
    $headers[] = "From: $from_name <$from_email>";
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

    // E-Mail senden
    $result = mail($to, $subject, $body, implode("\r\n", $headers));

    if (!$result) {
        error_log("Mail-Versand fehlgeschlagen: An $to - $subject");
    }

    return $result;
}

/**
 * Sendet Benachrichtigung über finalisierten Termin an alle Umfrage-Teilnehmer
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $poll_id ID der Umfrage
 * @param int $final_date_id ID des finalen Termins
 * @param string|null $host_url_base Optional: Basis-URL für Links (z.B. https://example.com)
 * @return int Anzahl der versendeten E-Mails
 */
function send_poll_finalization_notification($pdo, $poll_id, $final_date_id, $host_url_base = null) {
    // Umfrage-Daten laden
    $stmt = $pdo->prepare("
        SELECT p.*, m.first_name, m.last_name, m.email as creator_email
        FROM polls p
        LEFT JOIN members m ON p.created_by_member_id = m.member_id
        WHERE p.poll_id = ?
    ");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$poll) {
        return 0;
    }

    // Finalen Termin laden
    $stmt = $pdo->prepare("SELECT * FROM poll_dates WHERE date_id = ?");
    $stmt->execute([$final_date_id]);
    $final_date = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$final_date) {
        return 0;
    }

    // Alle Teilnehmer die abgestimmt haben
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.email, m.first_name, m.last_name
        FROM poll_responses pr
        LEFT JOIN members m ON pr.member_id = m.member_id
        WHERE pr.poll_id = ? AND m.email IS NOT NULL AND m.email != ''
    ");
    $stmt->execute([$poll_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    $message_text .= "Bitte merken Sie sich den Termin vor!\n\n";
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

                <p>Bitte merken Sie sich den Termin vor!</p>
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
?>
