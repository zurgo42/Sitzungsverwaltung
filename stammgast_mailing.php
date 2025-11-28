<?php
/**
 * stammgast_mailing.php - Modernisiertes Stammgast-Mailing-Tool
 * Erstellt: 2025-11-28
 *
 * FEATURES:
 * - Korrektes UTF-8 Encoding für Umlaute
 * - SQL-Injection-Schutz durch Prepared Statements
 * - Sauberes PHPMailer-Setup
 * - Bessere Fehlerbehandlung
 * - Moderner PHP-Code
 */

// ============= KONFIGURATION =============
// Wenn dieses Skript in einer anderen Anwendung verwendet wird,
// config.php optional laden (falls vorhanden)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// PHPMailer laden - versuche verschiedene Pfade
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Composer Installation
    require_once __DIR__ . '/vendor/autoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
} elseif (file_exists(__DIR__ . '/PHPMailer/PHPMailerAutoload.php')) {
    // Manuelle Installation (alte Version)
    require_once __DIR__ . '/PHPMailer/PHPMailerAutoload.php';
} elseif (file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    // Manuelle Installation (neue Version)
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
} elseif (class_exists('PHPMailer')) {
    // PHPMailer bereits geladen (z.B. durch andere Includes)
    // Nichts zu tun
} else {
    die('FEHLER: PHPMailer nicht gefunden!<br><br>
        Bitte PHPMailer installieren:<br>
        1. Via Composer: composer require phpmailer/phpmailer<br>
        2. Manuell herunterladen von: https://github.com/PHPMailer/PHPMailer<br>
        3. Entpacken in einen "PHPMailer" Ordner im gleichen Verzeichnis wie dieses Skript');
}

// ============= MAIL-KONFIGURATION =============
// Falls nicht bereits definiert (z.B. durch config.php)
if (!defined('MAIL_HOST')) {
    define('MAIL_HOST', 'mx2e4b.netcup.net');
    define('MAIL_PORT', 25);
    define('MAIL_USERNAME', 'info@baltrumhus.de');
    define('MAIL_PASSWORD', '1Pkigg!b');
    define('MAIL_FROM_EMAIL', 'info@baltrumhus.de');
    define('MAIL_FROM_NAME', 'Die Meiers: Baltrum Hus in Lee');
    define('MAIL_REPLY_TO', 'info@baltrumhus.de');
}

// Charset auf UTF-8 setzen
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// ============= DATENBANKVERBINDUNG =============
// Datenbankverbindung erstellen, falls noch nicht vorhanden (z.B. durch Include)
if (!isset($link) || !$link) {
    // Fallback-Werte, falls config.php nicht geladen wurde
    $db_host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $db_user = defined('DB_USER') ? DB_USER : 'root';
    $db_pass = defined('DB_PASS') ? DB_PASS : '';
    $db_name = defined('DB_NAME') ? DB_NAME : 'k126904_div';

    $link = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    if (!$link) {
        die('Datenbankverbindung fehlgeschlagen: ' . mysqli_connect_error());
    }
    mysqli_set_charset($link, "utf8mb4");
}

// ============= FORMULAR-VERARBEITUNG =============
$von_datum = $_POST['von_datum'] ?? date('d.m.Y', strtotime('-2 year'));
$mail_subject = $_POST['mail_subject'] ?? 'In Erinnerung an Ihren Baltrumaufenthalt und Stammgast-Bonus';
$mail_body = $_POST['mail_body'] ?? "Liebe Gäste,<br>
wir hoffen, Sie erinnern sich gern an Ihren wunderschönen Aufenthalt vom {anreise} bis {abreise} im Hus in Lee auf Baltrum.<br><br>
Jetzt, wo die Tage rapide kürzer werden, ist der Gedanke an unbeschwerte Ferientage besonders reizvoll.<br><br>
Wir haben unsere Webseite neu gestaltet und auch unseren neuen Buchungskalender für 2026 freigeschaltet. Erfahrungsgemäß - man sieht das auch schon - wird in Baltrum relativ früh gebucht. Deshalb möchten wir Ihnen als einem unserer Stammgäste anbieten, Ihren Wunschzeitraum für 2026 schon jetzt zu reservieren.<br><br>
**Ihre Vorteile bei einer Frühbuchung:**<br><br>
* **Wunschtermin-Garantie:** Sichern Sie sich genau die Tage, die perfekt in Ihre Planung passen.<br>
* **Stammgast-Bonus:** Als Dankeschön für Ihre Treue kommen wir Ihnen preislich entgegen:<br>
- Sie zahlen keinen Endreinigungs-Aufschlag für den ersten Tag - das wären sonst 110 Euro<br>
- und wir reduzieren - nur für Sie - den Gesamtpreis um 10%.<br>
Damit das funktioniert geben Sie bitte bei Ihrer Buchungsanfrage unter dem Feld für die Personenzahl das Wort 'Stammgast' ein. Dann wird Ihnen automatisch der reduzierte Preis angezeigt.<br><br>
Alle Informationen und nun auch den aktuellen Belegungskalender für 2026 finden Sie hier:<br>
-> https://baltrumhus.de <-
<br><br>
Wir freuen uns schon heute darauf, Sie bald wieder im Hus in Lee willkommen zu heißen!<br>
Wie immer stehen wir Ihnen gern für Fragen zur Verfügung: 02129 948148.<br><br>
Herzliche Grüße und bis bald!<br><br>
Ihre Meiers";

$errors = [];
$success_messages = [];

// ============= MAIL-VERSAND =============
if (isset($_POST['send_mails']) && !empty($_POST['markiert'])) {
    $ids = array_map('intval', $_POST['markiert']);

    if (empty($ids)) {
        $errors[] = "Keine Empfänger ausgewählt.";
    } else {
        // Prepared Statement für Sicherheit
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT * FROM anfragen WHERE Lnr IN ($placeholders)";
        $stmt = mysqli_prepare($link, $sql);

        // Bind parameters dynamisch
        $types = str_repeat('i', count($ids));
        mysqli_stmt_bind_param($stmt, $types, ...$ids);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $sent_count = 0;
        $failed_count = 0;

        while ($row = mysqli_fetch_assoc($result)) {
            // E-Mail-Adresse validieren
            if (empty($row['email']) || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Ungültige E-Mail-Adresse für {$row['name']} {$row['vorname']}: {$row['email']}";
                $failed_count++;
                continue;
            }

            // Platzhalter ersetzen
            $body_personalized = str_replace(
                ['{anreise}', '{abreise}', '{name}', '{vorname}'],
                [$row['anreise'], $row['abreise'], $row['name'], $row['vorname']],
                $mail_body
            );

            // HTML-Version erstellen
            $emailhtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <img src="http://baltrumhus.de/images/Ansicht.jpg" alt="Baltrum Ferienwohnungen Hus in Lee" height="120">
    <div>' . $body_personalized . '</div>
</body>
</html>';

            // Links umwandeln
            $emailhtml = preg_replace('/-> (https?:\/\/[^\s<]+) <-/', '<a href="$1">$1</a>', $emailhtml);

            // Text-Version (ohne HTML-Tags)
            $emailtext = strip_tags(str_replace('<br>', "\n", $body_personalized));

            // Mail versenden
            try {
                if (send_mail_phpmailer($row['email'], $mail_subject, $emailhtml, $emailtext)) {
                    $sent_count++;
                } else {
                    $errors[] = "Fehler beim Versand an {$row['email']}";
                    $failed_count++;
                }
            } catch (Exception $e) {
                $errors[] = "Exception beim Versand an {$row['email']}: " . $e->getMessage();
                $failed_count++;
            }
        }

        mysqli_stmt_close($stmt);

        // Erfolgsmeldung
        if ($sent_count > 0) {
            $success_messages[] = "✓ {$sent_count} E-Mail(s) erfolgreich versendet.";
        }
        if ($failed_count > 0) {
            $errors[] = "✗ {$failed_count} E-Mail(s) konnten nicht versendet werden.";
        }
    }

} elseif (isset($_POST['send_mails'])) {
    $errors[] = "Bitte mindestens einen Empfänger markieren.";
}

/**
 * Sendet E-Mail via PHPMailer mit korrektem UTF-8 Encoding
 */
function send_mail_phpmailer($to, $subject, $html_body, $text_body) {
    $mail = new PHPMailer(true);

    try {
        // Server-Einstellungen
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->Port = MAIL_PORT;

        // Encoding (WICHTIG für Umlaute!)
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = 'base64';
        $mail->setLanguage('de');

        // Absender
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);

        // Empfänger
        $mail->addAddress($to);

        // Inhalt
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body;

        // Senden
        return $mail->send();

    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        throw $e;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stammgast-Mailing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #34495e;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }
        textarea {
            min-height: 200px;
            resize: vertical;
        }
        .small-hint {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        th {
            background: #3498db;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        tr:hover {
            background: #f8f9fa;
        }
        tr.whg-2 {
            background: #95a5a6;
            color: white;
        }
        tr.whg-2:hover {
            background: #7f8c8d;
        }
        .btn {
            background: #27ae60;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .btn:hover {
            background: #229954;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .success {
            background: #27ae60;
            color: white;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .info-box {
            background: #ecf0f1;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Stammgast-Mailing</h1>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($success_messages)): ?>
            <?php foreach ($success_messages as $msg): ?>
                <div class="success"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="von_datum">Ab Datum der letzten Anreise:</label>
                <input type="text" id="von_datum" name="von_datum" value="<?= htmlspecialchars($von_datum) ?>" placeholder="TT.MM.JJJJ">
                <div class="small-hint">Format: TT.MM.JJJJ (z.B. 01.01.2023)</div>
            </div>

            <div class="form-group">
                <label for="mail_subject">Betreff:</label>
                <textarea id="mail_subject" name="mail_subject" rows="2"><?= htmlspecialchars($mail_subject) ?></textarea>
            </div>

            <div class="form-group">
                <label for="mail_body">Nachricht:</label>
                <textarea id="mail_body" name="mail_body"><?= htmlspecialchars($mail_body) ?></textarea>
                <div class="small-hint">
                    <strong>Platzhalter:</strong> {anreise} {abreise} {name} {vorname}<br>
                    <strong>Links:</strong> Verwenden Sie -&gt; URL &lt;- für automatische Link-Umwandlung
                </div>
            </div>

            <div class="info-box">
                <strong>ℹ️ Hinweis:</strong> Markieren Sie unten die Gäste, die die E-Mail erhalten sollen, und klicken Sie dann auf "Mails versenden".
            </div>

            <h2>Gästeliste</h2>
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select_all" onclick="toggleAll(this)"> Alle</th>
                        <th>Anfrage</th>
                        <th>Name</th>
                        <th>Vorname</th>
                        <th>Email</th>
                        <th>Whg</th>
                        <th>Pers</th>
                        <th>Anreise</th>
                        <th>Abreise</th>
                    </tr>
                </thead>
                <tbody>
<?php
// Datum vorbereiten
$d_ab = mysqli_real_escape_string($link, $von_datum);

// SQL: Neueste Anfrage pro E-Mail ab gegebenem Datum
$sql = "
    SELECT a.*
    FROM anfragen a
    JOIN (
        SELECT email, MAX(STR_TO_DATE(anfrage, '%d.%m.%Y')) AS maxdatum
        FROM anfragen
        WHERE whg <> 0
          AND email IS NOT NULL
          AND email != ''
          AND STR_TO_DATE(anfrage, '%d.%m.%Y') > STR_TO_DATE(?, '%d.%m.%Y')
        GROUP BY email
    ) b ON a.email = b.email AND STR_TO_DATE(a.anfrage, '%d.%m.%Y') = b.maxdatum
    WHERE a.whg <> 0
    ORDER BY b.maxdatum DESC
";

$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, 's', $d_ab);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count++;
    $row_class = ($row['whg'] == 2) ? 'whg-2' : '';
    echo "<tr class='$row_class'>
        <td><input type='checkbox' name='markiert[]' value='{$row['Lnr']}' class='row-checkbox'></td>
        <td>" . htmlspecialchars($row['anfrage']) . "</td>
        <td>" . htmlspecialchars($row['name']) . "</td>
        <td>" . htmlspecialchars($row['vorname']) . "</td>
        <td>" . htmlspecialchars($row['email']) . "</td>
        <td>" . htmlspecialchars($row['whg']) . "</td>
        <td>" . htmlspecialchars($row['pers']) . "</td>
        <td>" . htmlspecialchars($row['anreise']) . "</td>
        <td>" . htmlspecialchars($row['abreise']) . "</td>
    </tr>";
}

mysqli_stmt_close($stmt);
?>
                </tbody>
            </table>

            <div class="info-box">
                <strong>📊 Anzahl Gäste:</strong> <?= $count ?> Empfänger gefunden
            </div>

            <button type="submit" name="send_mails" class="btn">📨 Markierte Mails versenden</button>
        </form>
    </div>

    <script>
        // Alle Checkboxen an/aus
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
    </script>
</body>
</html>
<?php
mysqli_close($link);
?>
