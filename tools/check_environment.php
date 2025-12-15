<?php
/**
 * check_environment.php - Diagnose-Tool für Umgebungserkennung
 *
 * Zeigt an, ob das System in lokaler (XAMPP) oder Produktivumgebung läuft
 */

require_once __DIR__ . '/../config.php';

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umgebungs-Diagnose</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .environment {
            font-size: 24px;
            font-weight: bold;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
        }
        .local {
            background-color: #4CAF50;
            color: white;
        }
        .production {
            background-color: #f44336;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .true {
            color: #4CAF50;
            font-weight: bold;
        }
        .false {
            color: #999;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>🔍 Umgebungs-Diagnose</h1>

    <div class="card">
        <div class="environment <?php echo IS_LOCAL ? 'local' : 'production'; ?>">
            <?php if (IS_LOCAL): ?>
                🏠 LOKALE ENTWICKLUNGSUMGEBUNG (XAMPP)
            <?php else: ?>
                🌐 PRODUKTIVSERVER
            <?php endif; ?>
        </div>

        <h2>Erkannte Datenbank-Konfiguration</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Wert</th>
            </tr>
            <tr>
                <td>DB_HOST</td>
                <td><strong><?php echo DB_HOST; ?></strong></td>
            </tr>
            <tr>
                <td>DB_USER</td>
                <td><?php echo DB_USER; ?></td>
            </tr>
            <tr>
                <td>DB_NAME</td>
                <td><?php echo DB_NAME; ?></td>
            </tr>
            <tr>
                <td>DEBUG_MODE</td>
                <td class="<?php echo DEBUG_MODE ? 'true' : 'false'; ?>">
                    <?php echo DEBUG_MODE ? 'AN' : 'AUS'; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="card">
        <h2>Erkennungs-Indikatoren</h2>
        <table>
            <tr>
                <th>Indikator</th>
                <th>Wert</th>
                <th>Lokal?</th>
            </tr>
            <tr>
                <td>SERVER_NAME</td>
                <td><?php echo $_SERVER['SERVER_NAME'] ?? 'nicht gesetzt'; ?></td>
                <td class="<?php echo (isset($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1'])) ? 'true' : 'false'; ?>">
                    <?php echo (isset($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1'])) ? '✓' : '✗'; ?>
                </td>
            </tr>
            <tr>
                <td>HTTP_HOST</td>
                <td><?php echo $_SERVER['HTTP_HOST'] ?? 'nicht gesetzt'; ?></td>
                <td class="<?php echo (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? 'true' : 'false'; ?>">
                    <?php echo (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? '✓' : '✗'; ?>
                </td>
            </tr>
            <tr>
                <td>SERVER_ADDR</td>
                <td><?php echo $_SERVER['SERVER_ADDR'] ?? 'nicht gesetzt'; ?></td>
                <td class="<?php echo (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1'])) ? 'true' : 'false'; ?>">
                    <?php echo (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1'])) ? '✓' : '✗'; ?>
                </td>
            </tr>
            <tr>
                <td>Dateipfad enthält 'xampp'</td>
                <td><?php echo __FILE__; ?></td>
                <td class="<?php echo (stripos(__FILE__, 'xampp') !== false) ? 'true' : 'false'; ?>">
                    <?php echo (stripos(__FILE__, 'xampp') !== false) ? '✓' : '✗'; ?>
                </td>
            </tr>
            <tr>
                <td>Dateipfad enthält 'htdocs'</td>
                <td><?php echo __FILE__; ?></td>
                <td class="<?php echo (stripos(__FILE__, 'htdocs') !== false) ? 'true' : 'false'; ?>">
                    <?php echo (stripos(__FILE__, 'htdocs') !== false) ? '✓' : '✗'; ?>
                </td>
            </tr>
        </table>
    </div>

    <?php if (IS_LOCAL): ?>
    <div class="warning">
        <strong>⚠️ Hinweis für lokale Entwicklung:</strong><br>
        Stelle sicher, dass die lokale Datenbank '<strong><?php echo DB_NAME; ?></strong>' existiert und alle benötigten Tabellen enthält.
        <br><br>
        Führe ggf. die SQL-Struktur vom Produktivserver in deine lokale Datenbank ein.
    </div>
    <?php endif; ?>

    <div class="card" style="background-color: #f0f0f0; margin-top: 30px;">
        <h3>💡 Wie funktioniert die Erkennung?</h3>
        <p>
            Das System prüft automatisch mehrere Indikatoren, um festzustellen, ob es auf einem lokalen Server (XAMPP)
            oder dem Produktivserver läuft:
        </p>
        <ul>
            <li>Server-Name (localhost, 127.0.0.1, etc.)</li>
            <li>HTTP-Host enthält "localhost"</li>
            <li>Server-Adresse ist 127.0.0.1 oder ::1</li>
            <li>Dateipfad enthält "xampp" oder "htdocs"</li>
        </ul>
        <p>
            Wenn <strong>mindestens ein</strong> Indikator zutrifft, wird die lokale Umgebung erkannt
            und die entsprechende Datenbank-Konfiguration verwendet.
        </p>
    </div>
</body>
</html>
