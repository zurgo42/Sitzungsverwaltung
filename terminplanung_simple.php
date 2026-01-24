<?php
/**
 * terminplanung_simple.php - Vereinfachte Terminplanung (ohne Meeting-Kontext)
 *
 * VERWENDUNG:
 * ===========
 * Aus anderer Anwendung aufrufen:
 *
 * <?php
 *   require_once 'pfad/zu/config.php';  // DB-Config
 *   $pdo = new PDO(...);                 // DB-Verbindung
 *   $MNr = '1234567';                    // Mitgliedsnummer des eingeloggten Users
 *   require_once 'pfad/zu/terminplanung_simple.php';
 * ?>
 *
 * FEATURES:
 * - Keine vorgefertigten Adressatengruppen
 * - Nur manuelle Empfänger-Auswahl
 * - Kein Meeting erforderlich
 * - E-Mail-Versand mit Access-Token
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

// Funktionen laden
require_once __DIR__ . '/external_participants_functions.php';

// User laden
$stmt = $pdo->prepare("SELECT * FROM berechtigte WHERE MNr = ?");
$stmt->execute([$MNr]);
$ber = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ber) {
    die('FEHLER: Benutzer nicht gefunden.');
}

// User-Daten in Standard-Format
$current_user = [
    'member_id' => $ber['ID'],
    'membership_number' => $ber['MNr'],
    'first_name' => $ber['Vorname'],
    'last_name' => $ber['Name'],
    'email' => $ber['eMail'],
    'role' => $ber['Funktion']
];

// Alle potentiellen Empfänger laden
$stmt = $pdo->query("
    SELECT ID as member_id, MNr as membership_number, Vorname as first_name,
           Name as last_name, eMail as email, Funktion, aktiv
    FROM berechtigte
    WHERE aktiv > 17 OR Funktion IN ('RL', 'SV', 'AD', 'FP', 'GF')
    ORDER BY Name, Vorname
");
$all_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// POST-Verarbeitung: Neue Terminplanung erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_poll'])) {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $options = $_POST['options'] ?? [];
        $recipients = $_POST['recipients'] ?? [];

        // Validierung
        if (empty($title)) {
            throw new Exception('Titel ist erforderlich');
        }

        // Termine filtern (leere entfernen)
        $filtered_options = array_filter($options, function($opt) {
            return !empty(trim($opt['date'])) && !empty(trim($opt['time']));
        });

        if (count($filtered_options) < 2) {
            throw new Exception('Mindestens 2 Terminoptionen erforderlich');
        }

        if (empty($recipients)) {
            throw new Exception('Mindestens 1 Empfänger auswählen');
        }

        // Access Token generieren
        $access_token = bin2hex(random_bytes(32));

        // Terminplanung erstellen
        $stmt = $pdo->prepare("
            INSERT INTO svpolls (title, description, location, created_by_member_id,
                                 created_at, status, access_token, target_type, deadline)
            VALUES (?, ?, ?, ?, NOW(), 'open', ?, 'list', DATE_ADD(NOW(), INTERVAL 14 DAY))
        ");
        $stmt->execute([
            $title,
            $description,
            $location,
            $current_user['member_id'],
            $access_token
        ]);

        $poll_id = $pdo->lastInsertId();

        // Terminoptionen speichern
        $stmt = $pdo->prepare("
            INSERT INTO svpoll_options (poll_id, option_date, option_time, display_order)
            VALUES (?, ?, ?, ?)
        ");

        $order = 1;
        foreach ($filtered_options as $opt) {
            $stmt->execute([
                $poll_id,
                $opt['date'],
                $opt['time'],
                $order++
            ]);
        }

        // Empfänger speichern und E-Mails versenden
        $stmt = $pdo->prepare("
            INSERT INTO svpoll_participants (poll_id, member_id)
            VALUES (?, ?)
        ");

        foreach ($recipients as $member_id) {
            $stmt->execute([$poll_id, intval($member_id)]);

            // E-Mail an Empfänger
            $recipient = null;
            foreach ($all_members as $m) {
                if ($m['member_id'] == $member_id) {
                    $recipient = $m;
                    break;
                }
            }

            if ($recipient && !empty($recipient['email'])) {
                $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                        "://" . $_SERVER['HTTP_HOST'] .
                        dirname($_SERVER['PHP_SELF']) .
                        "/terminplanung_response.php?token=" . $access_token;

                $mail_subject = "Terminabstimmung: " . $title;
                $mail_body = "Hallo " . $recipient['first_name'] . ",\n\n";
                $mail_body .= $current_user['first_name'] . " " . $current_user['last_name'] . " bittet um Ihre Rückmeldung für:\n\n";
                $mail_body .= $title . "\n\n";

                if (!empty($description)) {
                    $mail_body .= "Beschreibung:\n" . $description . "\n\n";
                }

                $mail_body .= "Bitte geben Sie Ihre Verfügbarkeit an:\n";
                $mail_body .= $link . "\n\n";
                $mail_body .= "Mit freundlichen Grüßen";

                mail($recipient['email'], $mail_subject, $mail_body, "From: noreply@" . $_SERVER['HTTP_HOST']);
            }
        }

        $success_message = "Terminplanung erfolgreich erstellt und E-Mails versendet!";

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
    <title>Terminplanung erstellen</title>
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
        input[type="text"], textarea, input[type="date"], input[type="time"] {
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
        .option-row {
            display: grid;
            grid-template-columns: 1fr 1fr 40px;
            gap: 10px;
            margin-bottom: 10px;
        }
        .add-button, .remove-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .add-button {
            background: #4caf50;
            color: white;
        }
        .remove-button {
            background: #f44336;
            color: white;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>📅 Terminplanung erstellen</h1>

        <div class="user-info">
            👤 Angemeldet als: <strong><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></strong>
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
                <input type="text" name="title" required placeholder="z.B. Team-Meeting Terminabstimmung">
            </div>

            <div class="form-group">
                <label>Beschreibung</label>
                <textarea name="description" placeholder="Zusätzliche Informationen..."></textarea>
            </div>

            <div class="form-group">
                <label>Ort</label>
                <input type="text" name="location" placeholder="z.B. Konferenzraum 1 oder Online">
            </div>

            <div class="form-group">
                <label>Terminoptionen * (mindestens 2)</label>
                <div id="options-container">
                    <div class="option-row">
                        <input type="date" name="options[0][date]" required>
                        <input type="time" name="options[0][time]" required>
                        <span></span>
                    </div>
                    <div class="option-row">
                        <input type="date" name="options[1][date]" required>
                        <input type="time" name="options[1][time]" required>
                        <span></span>
                    </div>
                </div>
                <button type="button" class="add-button" onclick="addOption()">+ Weitere Option</button>
            </div>

            <div class="form-group">
                <label>Empfänger auswählen * (mindestens 1)</label>
                <div class="recipients-list">
                    <?php foreach ($all_members as $member): ?>
                        <?php if ($member['member_id'] != $current_user['member_id']): ?>
                            <div class="recipient-item">
                                <label>
                                    <input type="checkbox" name="recipients[]" value="<?php echo $member['member_id']; ?>">
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

            <button type="submit" class="submit-button">📅 Terminplanung erstellen und versenden</button>
        </form>
    </div>

    <script>
    let optionCount = 2;

    function addOption() {
        optionCount++;
        const container = document.getElementById('options-container');
        const row = document.createElement('div');
        row.className = 'option-row';
        row.innerHTML = `
            <input type="date" name="options[${optionCount}][date]">
            <input type="time" name="options[${optionCount}][time]">
            <button type="button" class="remove-button" onclick="this.parentElement.remove()">×</button>
        `;
        container.appendChild(row);
    }
    </script>
</body>
</html>
