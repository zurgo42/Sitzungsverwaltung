<?php
/**
 * login.php - Anmeldeseite für Meeting-System
 */

session_start();
require_once 'config.php';
require_once 'config_adapter.php';  // Konfiguration für Mitgliederquelle
require_once 'member_functions.php';  // Prozedurale Wrapper-Funktionen

// PDO-Verbindung für login.php
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
    } else {
        die("Datenbankverbindung fehlgeschlagen. Bitte kontaktiere den Administrator.");
    }
}

$error = '';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Wenn schon eingeloggt, zur Hauptseite
if (isset($_SESSION['member_id'])) {
    header('Location: index.php');
    exit;
}

// Login-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    try {
        // Authentifizierung über Wrapper-Funktion
        // Funktioniert mit members ODER berechtigte Tabelle (siehe config_adapter.php)
        $user = authenticate_member($pdo, $email, $password);

        if ($user) {
            // Login erfolgreich - Session setzen
            $_SESSION['member_id'] = $user['member_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();

            header('Location: index.php');
            exit;
        } else {
            $error = 'Email oder Passwort ist falsch!';
        }
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            $error = 'Datenbankfehler: ' . $e->getMessage();
        } else {
            $error = 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden - Meeting-Verwaltungssystem</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        
        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
            }
            
            .login-banner {
                display: none;
            }
        }
        
        .login-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
        }
        
        .login-banner h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-banner p {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .login-form {
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-form h2 {
            margin-bottom: 30px;
            color: #333;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .demo-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 5px;
            padding: 15px;
            margin-top: 30px;
            color: #1565c0;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .demo-info h3 {
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .demo-users {
            margin-top: 10px;
            display: grid;
            gap: 8px;
        }
        
        .demo-user {
            background: rgba(255, 255, 255, 0.5);
            padding: 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .demo-user code {
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 6px;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-banner">
            <h1>📋 Meeting-Verwaltung</h1>
            <p>Organisiere deine Online-Sitzungen effizient. Tagesordnung, Diskussionen, Protokolle - alles an einem Ort.</p>
        </div>
        
        <div class="login-form">
            <h2>Anmelden</h2>
            
            <?php if ($error): ?>
                <div class="error-message show">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">E-Mail-Adresse:</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Passwort:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit">Anmelden</button>
            </form>
            
            <div class="demo-info">
                <h3>🔐 Demo-Benutzer (Testsystem)</h3>
                <p>Dies ist ein Testsystem. Verwende eine dieser Demo-Identitäten:</p>
                <div class="demo-users">
                    <div class="demo-user"><strong>Vorstand:</strong> <code>max@example.com</code> / <code>test123</code></div>
                    <div class="demo-user"><strong>Vorstand:</strong> <code>erika@example.com</code> / <code>test123</code></div>
                    <div class="demo-user"><strong>Führung:</strong> <code>hans@example.com</code> / <code>test123</code></div>
                    <div class="demo-user"><strong>GF:</strong> <code>julia@example.com</code> / <code>test123</code></div>
                    <div class="demo-user"><strong>Assistenz:</strong> <code>thomas@example.com</code> / <code>test123</code></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>