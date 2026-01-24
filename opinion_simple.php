<?php
/**
 * opinion_simple.php - Vereinfachtes Meinungsbild (ohne Meeting-Kontext)
 *
 * VERWENDUNG:
 * ===========
 * Aus anderer Anwendung aufrufen:
 *
 * <?php
 *   require_once 'pfad/zu/config.php';  // DB-Config
 *   $pdo = new PDO(...);                 // DB-Verbindung
 *   $MNr = '1234567';                    // Mitgliedsnummer des eingeloggten Users
 *   require_once 'pfad/zu/opinion_simple.php';
 * ?>
 *
 * FEATURES:
 * - Keine vorgefertigten Adressatengruppen
 * - Nur manuelle Empfänger-Auswahl
 * - Kein Meeting erforderlich
 * - E-Mail-Versand mit Access-Token
 * - Templates aus svopinion_templates
 */

// Session starten falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Voraussetzungen prüfen
if (!isset($pdo)) {
    die('FEHLER: $pdo nicht definiert. Bitte PDO-Verbindung vor dem Include erstellen.');
}

if (!isset($MNr) || empty($MNr)) {
    die('FEHLER: $MNr nicht definiert. Bitte Mitgliedsnummer übergeben.');
}

// Helper-Funktion laden
require_once __DIR__ . '/user_data_helper.php';

// User-Daten holen (erst berechtigte, dann LDAP)
$user_data = get_user_data($pdo, $MNr);

if (!$user_data) {
    die('FEHLER: Benutzer nicht gefunden.');
}

$current_user = [
    'membership_number' => $MNr,
    'first_name' => $user_data['first_name'],
    'last_name' => $user_data['last_name'],
    'email' => $user_data['email']
];

// Alle potentiellen Empfänger laden
$stmt = $pdo->query("
    SELECT MNr as membership_number, Vorname as first_name,
           Name as last_name, eMail as email
    FROM berechtigte
    WHERE aktiv > 17 OR Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')
    ORDER BY Name, Vorname
");
$all_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verfügbare Templates laden
$stmt = $pdo->query("
    SELECT * FROM svopinion_templates
    WHERE is_active = 1
    ORDER BY template_name
");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// POST-Verarbeitung: Neues Meinungsbild erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_poll'])) {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $template_id = intval($_POST['template_id'] ?? 0);
        $recipients = $_POST['recipients'] ?? [];

        // Validierung
        if (empty($title)) {
            throw new Exception('Titel ist erforderlich');
        }

        if ($template_id <= 0) {
            throw new Exception('Bitte Template auswählen');
        }

        if (empty($recipients)) {
            throw new Exception('Mindestens 1 Empfänger auswählen');
        }

        // Access Token generieren
        $access_token = bin2hex(random_bytes(32));

        // Meinungsbild erstellen
        $stmt = $pdo->prepare("
            INSERT INTO svopinion_polls (title, description, template_id, created_by_name,
                                         created_at, status, access_token, target_type, deadline)
            VALUES (?, ?, ?, ?, NOW(), 'open', ?, 'list', DATE_ADD(NOW(), INTERVAL 14 DAY))
        ");
        $stmt->execute([
            $title,
            $description,
            $template_id,
            format_user_name($current_user),
            $access_token
        ]);

        $poll_id = $pdo->lastInsertId();

        // Empfänger speichern und E-Mails versenden
        $stmt = $pdo->prepare("
            INSERT INTO svopinion_participants (poll_id, participant_name, participant_email)
            VALUES (?, ?, ?)
        ");

        foreach ($recipients as $mnr) {
            // Empfänger-Daten holen
            $recipient = get_user_data($pdo, $mnr);

            if ($recipient && !empty($recipient['email'])) {
                $recipient_name = $recipient['first_name'] . ' ' . $recipient['last_name'];

                $stmt->execute([
                    $poll_id,
                    $recipient_name,
                    $recipient['email']
                ]);

                // E-Mail an Empfänger
                $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                        "://" . $_SERVER['HTTP_HOST'] .
                        dirname($_SERVER['PHP_SELF']) .
                        "/opinion_response.php?token=" . $access_token;

                $mail_subject = "Meinungsbild: " . $title;
                $mail_body = "Hallo " . $recipient['first_name'] . ",\n\n";
                $mail_body .= format_user_name($current_user) . " bittet um Ihre Meinung:\n\n";
                $mail_body .= $title . "\n\n";

                if (!empty($description)) {
                    $mail_body .= "Beschreibung:\n" . $description . "\n\n";
                }

                $mail_body .= "Bitte geben Sie Ihre Antwort ab:\n";
                $mail_body .= $link . "\n\n";
                $mail_body .= "Mit freundlichen Grüßen";

                mail($recipient['email'], $mail_subject, $mail_body, "From: noreply@" . $_SERVER['HTTP_HOST']);
            }
        }

        $success_message = "Meinungsbild erfolgreich erstellt und E-Mails versendet!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meinungsbild erstellen</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .user-info {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        select {
            cursor: pointer;
        }
        .recipients-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        .recipient-item {
            padding: 5px;
            margin: 2px 0;
        }
        .submit-button {
            background: #2196f3;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
        }
        .submit-button:hover {
            background: #1976d2;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .template-info {
            background: #fff3e0;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💭 Meinungsbild erstellen</h1>

        <div class="user-info">
            👤 Angemeldet als: <strong><?php echo htmlspecialchars(format_user_name($current_user)); ?></strong>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="success">✅ <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="create_poll" value="1">

            <div class="form-group">
                <label>Titel *</label>
                <input type="text" name="title" required placeholder="z.B. Entscheidung zum neuen Projekt">
            </div>

            <div class="form-group">
                <label>Beschreibung</label>
                <textarea name="description" placeholder="Zusätzliche Informationen zur Abstimmung..."></textarea>
            </div>

            <div class="form-group">
                <label>Template / Antwortmöglichkeiten *</label>
                <select name="template_id" required id="template_select" onchange="showTemplateInfo()">
                    <option value="">-- Bitte wählen --</option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?php echo $template['template_id']; ?>"
                                data-description="<?php echo htmlspecialchars($template['description']); ?>"
                                data-options="<?php echo htmlspecialchars($template['options_json']); ?>">
                            <?php echo htmlspecialchars($template['template_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="template-info" class="template-info" style="display: none;"></div>
            </div>

            <div class="form-group">
                <label>Empfänger auswählen * (mindestens 1)</label>
                <div class="recipients-list">
                    <?php foreach ($all_members as $member): ?>
                        <?php if ($member['membership_number'] != $MNr): ?>
                            <div class="recipient-item">
                                <label>
                                    <input type="checkbox" name="recipients[]" value="<?php echo htmlspecialchars($member['membership_number']); ?>">
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                    <?php if (!empty($member['email'])): ?>
                                        (<?php echo htmlspecialchars($member['email']); ?>)
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="submit-button">💭 Meinungsbild erstellen und versenden</button>
        </form>
    </div>

    <script>
    function showTemplateInfo() {
        const select = document.getElementById('template_select');
        const infoDiv = document.getElementById('template-info');
        const option = select.options[select.selectedIndex];

        if (option.value) {
            const description = option.getAttribute('data-description');
            const optionsJson = option.getAttribute('data-options');

            let html = '<strong>Beschreibung:</strong> ' + description;

            if (optionsJson) {
                try {
                    const options = JSON.parse(optionsJson);
                    if (options.options && options.options.length > 0) {
                        html += '<br><strong>Antwortmöglichkeiten:</strong> ' + options.options.join(', ');
                    }
                } catch (e) {}
            }

            infoDiv.innerHTML = html;
            infoDiv.style.display = 'block';
        } else {
            infoDiv.style.display = 'none';
        }
    }
    </script>
</body>
</html>
