<?php
/**
 * external_participant_register.php - Registrierungsformular für externe Teilnehmer
 * Erstellt: 2025-12-18
 *
 * Zeigt Formular zur Erfassung externer Teilnehmer-Daten
 * Wird von standalone-Skripten eingebunden wenn Zugriff ohne Login erfolgt
 *
 * Vorausgesetzte Variablen:
 * - $poll_type: 'termine' oder 'meinungsbild'
 * - $poll_id: ID der Umfrage
 * - $poll: Array mit Umfrage-Daten (title, description, etc.)
 * - $pdo: PDO-Datenbankverbindung
 */

// Cookie-Daten laden (falls vorhanden)
$cookie_data = get_external_participant_from_cookie();
$from_cookie = false;

if ($cookie_data && empty($_POST)) {
    $from_cookie = true;
}

// Prüfen ob bereits registriert
$external_session = get_external_participant_session();
$already_registered = false;

if ($external_session
    && $external_session['poll_type'] === $poll_type
    && $external_session['poll_id'] == $poll_id) {
    $already_registered = true;
}

// Formular-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_external'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mnr = trim($_POST['mnr'] ?? '');
    $consent = isset($_POST['consent']);

    $errors = [];

    // Validierung
    if (empty($first_name)) {
        $errors[] = 'Bitte geben Sie Ihren Vornamen ein.';
    }
    if (empty($last_name)) {
        $errors[] = 'Bitte geben Sie Ihren Nachnamen ein.';
    }
    if (empty($email)) {
        $errors[] = 'Bitte geben Sie Ihre E-Mail-Adresse ein.';
    } elseif (!validate_external_email($email)) {
        $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    }
    if (!$consent) {
        $errors[] = 'Bitte stimmen Sie der Datenspeicherung zu.';
    }

    if (empty($errors)) {
        try {
            // IP-Adresse für Tracking
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

            // Externen Teilnehmer erstellen
            $result = create_external_participant(
                $pdo,
                $poll_type,
                $poll_id,
                $first_name,
                $last_name,
                $email,
                !empty($mnr) ? $mnr : null,
                $ip_address
            );

            // Session setzen
            set_external_participant_session(
                $result['session_token'],
                $poll_type,
                $poll_id,
                $result['external_id']
            );

            // Cookie für 30 Tage speichern (zur Wiedererkennung)
            save_external_participant_cookie($first_name, $last_name, $email);

            // Erfolgsmeldung und Weiterleitung
            $_SESSION['success'] = 'Willkommen! Sie können jetzt an der Umfrage teilnehmen.';

            // Zur Umfrage weiterleiten
            // Verwende $redirect_script falls vom standalone-Skript gesetzt, sonst PHP_SELF
            $script_name = isset($redirect_script) ? $redirect_script : basename($_SERVER['SCRIPT_NAME']);
            $redirect_url = $script_name . '?poll_id=' . $poll_id;
            if (isset($_GET['token'])) {
                $redirect_url .= '&token=' . urlencode($_GET['token']);
            }
            header('Location: ' . $redirect_url);
            exit;

        } catch (Exception $e) {
            $errors[] = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
            error_log("Externe Registrierung fehlgeschlagen: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umfrage - Registrierung</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }

        h1 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .poll-title {
            color: #667eea;
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 20px 0;
        }

        .intro-text {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        label .required {
            color: #e74c3c;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        input[type="text"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .checkbox-group {
            margin: 25px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .checkbox-group label {
            display: flex;
            align-items: start;
            font-weight: normal;
            cursor: pointer;
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .error-box {
            background: #fee;
            border: 2px solid #e74c3c;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .error-box ul {
            margin: 0;
            padding-left: 20px;
            color: #c0392b;
        }

        .success-box {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            color: #155724;
        }

        .info-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .privacy-notice {
            font-size: 12px;
            color: #999;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        @media (max-width: 600px) {
            .register-container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .poll-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>📊 Umfrage-Teilnahme</h1>
        <p class="poll-title"><?php echo htmlspecialchars($poll['title'] ?? 'Umfrage'); ?></p>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="error-box">
                <strong>⚠️ Bitte korrigieren Sie folgende Fehler:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-box">
                ✓ <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if ($from_cookie): ?>
            <div class="success-box">
                👋 Willkommen zurück! Deine Daten wurden automatisch ausgefüllt. Du kannst sie bei Bedarf anpassen.
            </div>
        <?php endif; ?>

        <div class="intro-text">
            <p>Um an dieser Umfrage teilzunehmen, benötigen wir einige Angaben von dir. Deine Daten werden vertraulich behandelt und ausschließlich für diese Umfrage verwendet.</p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="register_external" value="1">

            <div class="form-group">
                <label>
                    Vorname <span class="required">*</span>
                </label>
                <input type="text"
                       name="first_name"
                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ($cookie_data['first_name'] ?? '')); ?>"
                       required
                       placeholder="z.B. Max">
            </div>

            <div class="form-group">
                <label>
                    Nachname <span class="required">*</span>
                </label>
                <input type="text"
                       name="last_name"
                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ($cookie_data['last_name'] ?? '')); ?>"
                       required
                       placeholder="z.B. Mustermann">
            </div>

            <div class="form-group">
                <label>
                    E-Mail-Adresse <span class="required">*</span>
                </label>
                <input type="email"
                       name="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ($cookie_data['email'] ?? '')); ?>"
                       required
                       placeholder="max.mustermann@example.com">
                <p class="info-text">
                    Wir verwenden deine E-Mail-Adresse nur zur Identifikation für diese Umfrage.
                </p>
            </div>

            <div class="form-group">
                <label>
                    Mitgliedsnummer (optional)
                </label>
                <input type="text"
                       name="mnr"
                       value="<?php echo htmlspecialchars($_POST['mnr'] ?? ''); ?>"
                       placeholder="Falls vorhanden">
                <p class="info-text">
                    Wenn du Mitglied bist, kannst du hier optional deine Mitgliedsnummer angeben.
                </p>
            </div>

            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="consent" value="1" required>
                    <span>
                        Ich stimme zu, dass meine Daten für diese Umfrage gespeichert werden.
                        Die Daten werden 6 Monate nach Abschluss der Umfrage automatisch gelöscht.
                        Zur vereinfachten Wiedererkennung wird ein Cookie für 30 Tage gespeichert. <span class="required">*</span>
                    </span>
                </label>
            </div>

            <button type="submit" class="btn-primary">
                ✓ Registrieren und zur Umfrage
            </button>

            <div class="privacy-notice">
                <strong>🔒 Datenschutz:</strong> Deine Angaben werden vertraulich behandelt und nur für diese Umfrage verwendet.
                Nach 6 Monaten werden alle Daten automatisch gelöscht.
            </div>
        </form>
    </div>
</body>
</html>
