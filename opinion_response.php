<?php
/**
 * opinion_response.php - Public Response für Meinungsbild
 *
 * Für nicht-eingeloggte User zum Eingeben ihrer Meinung
 * Zugriff via: opinion_response.php?token=XXXXXXXX
 */

session_start();
require_once __DIR__ . '/config.php';

// PDO-Verbindung
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('❌ Datenbankverbindung fehlgeschlagen');
}

// Token aus URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('❌ Kein gültiger Zugangs-Link');
}

// Poll laden
$stmt = $pdo->prepare("SELECT * FROM svopinion_polls WHERE access_token = ? AND status = 'open'");
$stmt->execute([$token]);
$poll = $stmt->fetch();

if (!$poll) {
    die('❌ Ungültiger oder abgelaufener Zugangs-Link');
}

// Template laden
$stmt = $pdo->prepare("SELECT * FROM svopinion_templates WHERE template_id = ?");
$stmt->execute([$poll['template_id']]);
$template = $stmt->fetch();

// Optionen laden (je nach Template-Typ)
$options = [];
if ($template['options_type'] === 'predefined') {
    $options_json = json_decode($template['options_json'], true);
    $options = $options_json['options'] ?? [];
}

// POST-Verarbeitung: Antwort speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_response'])) {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $response = trim($_POST['response'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        // Validierung
        if (empty($first_name) || empty($last_name)) {
            throw new Exception('Vor- und Nachname sind erforderlich');
        }

        if (empty($email)) {
            throw new Exception('E-Mail-Adresse ist erforderlich');
        }

        if (empty($response)) {
            throw new Exception('Bitte wählen Sie eine Option');
        }

        // Prüfen ob E-Mail bereits abgestimmt hat
        $stmt = $pdo->prepare("
            SELECT response_id FROM svopinion_responses
            WHERE poll_id = ? AND participant_email = ?
        ");
        $stmt->execute([$poll['poll_id'], $email]);
        if ($stmt->fetch()) {
            throw new Exception('Mit dieser E-Mail-Adresse wurde bereits abgestimmt');
        }

        // Antwort speichern
        $stmt = $pdo->prepare("
            INSERT INTO svopinion_responses
            (poll_id, participant_name, participant_email, response_value, comment, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $poll['poll_id'],
            $first_name . ' ' . $last_name,
            $email,
            $response,
            $comment
        ]);

        $success = true;

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
    <title>Meinungsbild</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .poll-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .poll-info h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 22px;
        }
        .poll-info p {
            color: #666;
            line-height: 1.6;
            margin: 5px 0;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .form-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"], input[type="email"], textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        input[type="text"]:focus, input[type="email"]:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        .option-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .option-card:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .option-card input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .option-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        .option-label {
            flex: 1;
            font-weight: 500;
            color: #333;
        }
        .submit-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .success-message {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💭 Meinungsbild</h1>
            <p>Ihre Meinung ist gefragt</p>
        </div>

        <div class="content">
            <?php if (isset($success) && $success): ?>
                <div class="success-message">
                    <h2>✅ Vielen Dank!</h2>
                    <p>Ihre Antwort wurde erfolgreich gespeichert.</p>
                </div>
            <?php else: ?>

                <?php if (isset($error_message)): ?>
                    <div class="error-message">
                        ❌ <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="poll-info">
                    <h2><?php echo htmlspecialchars($poll['title']); ?></h2>
                    <?php if (!empty($poll['description'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($poll['description'])); ?></p>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="submit_response" value="1">

                    <div class="form-section">
                        <h3>Ihre Daten</h3>
                        <div class="form-group">
                            <label>Vorname *</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Nachname *</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label>E-Mail *</label>
                            <input type="email" name="email" required>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Ihre Antwort</h3>
                        <?php foreach ($options as $index => $option): ?>
                            <label class="option-card" onclick="selectOption(this)">
                                <input type="radio" name="response" value="<?php echo htmlspecialchars($option); ?>" required>
                                <span class="option-label"><?php echo htmlspecialchars($option); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-section">
                        <h3>Optionaler Kommentar</h3>
                        <div class="form-group">
                            <textarea name="comment" placeholder="Möchten Sie Ihre Antwort begründen oder ergänzen?"></textarea>
                        </div>
                    </div>

                    <button type="submit" class="submit-button">
                        📨 Antwort absenden
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </div>

    <script>
    function selectOption(card) {
        // Alle Cards zurücksetzen
        document.querySelectorAll('.option-card').forEach(c => {
            c.classList.remove('selected');
        });
        // Gewählte Card markieren
        card.classList.add('selected');
    }

    // Initial check
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.option-card input[type="radio"]:checked').forEach(radio => {
            radio.closest('.option-card').classList.add('selected');
        });
    });
    </script>
</body>
</html>
