<?php
/**
 * terminplanung_response.php - Public Response für Terminabstimmung
 *
 * Für nicht-eingeloggte User zum Eingeben ihrer Terminpräferenzen
 * Zugriff via: terminplanung_response.php?token=XXXXXXXX
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
$stmt = $pdo->prepare("SELECT * FROM svpolls WHERE access_token = ? AND status = 'open'");
$stmt->execute([$token]);
$poll = $stmt->fetch();

if (!$poll) {
    die('❌ Ungültiger oder abgelaufener Zugangs-Link');
}

// Optionen laden
$stmt = $pdo->prepare("
    SELECT * FROM svpoll_options
    WHERE poll_id = ?
    ORDER BY display_order, option_date, option_time
");
$stmt->execute([$poll['poll_id']]);
$options = $stmt->fetchAll();

// POST-Verarbeitung: Antwort speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_response'])) {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $responses = $_POST['responses'] ?? [];

        // Validierung
        if (empty($first_name) || empty($last_name)) {
            throw new Exception('Vor- und Nachname sind erforderlich');
        }

        if (empty($email)) {
            throw new Exception('E-Mail-Adresse ist erforderlich');
        }

        // Prüfen ob E-Mail bereits abgestimmt hat
        $stmt = $pdo->prepare("
            SELECT response_id FROM svpoll_responses
            WHERE poll_id = ? AND participant_email = ?
        ");
        $stmt->execute([$poll['poll_id'], $email]);
        if ($stmt->fetch()) {
            throw new Exception('Mit dieser E-Mail-Adresse wurde bereits abgestimmt');
        }

        // Antworten speichern
        $pdo->beginTransaction();

        foreach ($options as $option) {
            $availability = $responses[$option['option_id']] ?? 'no';

            $stmt = $pdo->prepare("
                INSERT INTO svpoll_responses
                (poll_id, option_id, participant_name, participant_email, availability, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $poll['poll_id'],
                $option['option_id'],
                $first_name . ' ' . $last_name,
                $email,
                $availability
            ]);
        }

        $pdo->commit();

        $success = true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = $e->getMessage();
    }
}

// Formatierung
function format_date_german($date) {
    $days = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    $months = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    $timestamp = strtotime($date);
    $day = $days[date('w', $timestamp)];
    $date_num = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);

    return "$day, $date_num. $month $year";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminabstimmung</title>
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
            max-width: 800px;
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
        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus, input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .option-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .option-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }
        .option-header {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .availability-options {
            display: flex;
            gap: 10px;
        }
        .availability-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
        }
        .availability-btn input[type="radio"] {
            display: none;
        }
        .availability-btn.yes {
            border-color: #4caf50;
            color: #4caf50;
        }
        .availability-btn.yes input[type="radio"]:checked + span {
            background: #4caf50;
            color: white;
        }
        .availability-btn.maybe {
            border-color: #ff9800;
            color: #ff9800;
        }
        .availability-btn.maybe input[type="radio"]:checked + span {
            background: #ff9800;
            color: white;
        }
        .availability-btn.no {
            border-color: #f44336;
            color: #f44336;
        }
        .availability-btn.no input[type="radio"]:checked + span {
            background: #f44336;
            color: white;
        }
        .availability-btn span {
            display: block;
            padding: 8px;
            border-radius: 4px;
            transition: all 0.3s;
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
            <h1>📅 Terminabstimmung</h1>
            <p>Bitte wählen Sie Ihre verfügbaren Termine</p>
        </div>

        <div class="content">
            <?php if (isset($success) && $success): ?>
                <div class="success-message">
                    <h2>✅ Vielen Dank!</h2>
                    <p>Ihre Terminpräferenzen wurden erfolgreich gespeichert.</p>
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
                    <?php if (!empty($poll['location'])): ?>
                        <p><strong>📍 Ort:</strong> <?php echo htmlspecialchars($poll['location']); ?></p>
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
                        <h3>Ihre Verfügbarkeit</h3>
                        <?php foreach ($options as $option): ?>
                            <div class="option-card">
                                <div class="option-header">
                                    <?php echo format_date_german($option['option_date']); ?> um
                                    <?php echo date('H:i', strtotime($option['option_time'])); ?> Uhr
                                </div>
                                <div class="availability-options">
                                    <label class="availability-btn yes">
                                        <input type="radio" name="responses[<?php echo $option['option_id']; ?>]" value="yes">
                                        <span>✅ Ja</span>
                                    </label>
                                    <label class="availability-btn maybe">
                                        <input type="radio" name="responses[<?php echo $option['option_id']; ?>]" value="maybe">
                                        <span>❓ Vielleicht</span>
                                    </label>
                                    <label class="availability-btn no">
                                        <input type="radio" name="responses[<?php echo $option['option_id']; ?>]" value="no" checked>
                                        <span>❌ Nein</span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="submit-button">
                        📨 Antwort absenden
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
