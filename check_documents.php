<?php
/**
 * check_documents.php - Prüft Status der Dokumentenverwaltung
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'member_functions.php';
require_once 'documents_functions.php';

echo "<!DOCTYPE html>
<html lang='de'>
<head>
    <meta charset='UTF-8'>
    <title>Dokumentenverwaltung - Status</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>📁 Dokumentenverwaltung - Status</h1>";

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "<p class='ok'>✓ Datenbankverbindung OK</p>";

    // Aktueller Benutzer
    if (isset($_SESSION['member_id'])) {
        $current_user = get_member_by_id($pdo, $_SESSION['member_id']);
        echo "<h2>Aktueller Benutzer:</h2>";
        echo "<p>Name: <strong>" . htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) . "</strong></p>";
        echo "<p>Rolle: <strong>" . htmlspecialchars($current_user['role']) . "</strong></p>";
        echo "<p>is_admin Flag: <strong>" . ($current_user['is_admin'] ? 'JA' : 'NEIN') . "</strong></p>";

        if ($current_user['is_admin']) {
            echo "<p class='ok'>✓ Du bist Admin - kannst Dokumente hochladen</p>";
        } else {
            echo "<p class='error'>✗ Du bist KEIN Admin - kannst keine Dokumente hochladen!</p>";
        }
    } else {
        echo "<p class='error'>✗ Nicht eingeloggt!</p>";
    }
    echo "<hr>";

    // Prüfe ob Tabelle documents existiert
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM svdocuments");
        $result = $stmt->fetch();
        echo "<p class='ok'>✓ Tabelle 'documents' existiert</p>";
        echo "<p>Anzahl Dokumente: <strong>" . $result['count'] . "</strong></p>";

        if ($result['count'] == 0) {
            echo "<p class='warning'>⚠ Noch keine Dokumente in der Datenbank!</p>";
            echo "<p>Bitte als Admin ein Dokument hochladen unter: <a href='?tab=documents&view=upload'>Dokument hochladen</a></p>";
        } else {
            // Zeige alle Dokumente
            echo "<h2>Vorhandene Dokumente:</h2>";
            $stmt = $pdo->query("SELECT * FROM svdocuments ORDER BY created_at DESC LIMIT 10");
            $docs = $stmt->fetchAll();

            echo "<table border='1' cellpadding='5' style='width:100%; border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Titel</th><th>Kategorie</th><th>Status</th><th>Access Level</th><th>Erstellt</th></tr>";
            foreach ($docs as $doc) {
                echo "<tr>";
                echo "<td>" . $doc['document_id'] . "</td>";
                echo "<td>" . htmlspecialchars($doc['title']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['category']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['status']) . "</td>";
                echo "<td>" . $doc['access_level'] . "</td>";
                echo "<td>" . $doc['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

    } catch (PDOException $e) {
        echo "<p class='error'>✗ Tabelle 'documents' existiert NICHT!</p>";
        echo "<p>Bitte Installation ausführen: <a href='install_documents.php'>install_documents.php</a></p>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }

    // Prüfe ob Tabelle document_downloads existiert
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM svdocument_downloads");
        echo "<p class='ok'>✓ Tabelle 'document_downloads' existiert</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Tabelle 'document_downloads' existiert NICHT!</p>";
    }

    // Prüfe Upload-Verzeichnis
    $upload_dir = __DIR__ . '/uploads/documents';
    if (is_dir($upload_dir)) {
        echo "<p class='ok'>✓ Upload-Verzeichnis existiert: $upload_dir</p>";

        if (is_writable($upload_dir)) {
            echo "<p class='ok'>✓ Upload-Verzeichnis ist beschreibbar</p>";
        } else {
            echo "<p class='error'>✗ Upload-Verzeichnis ist NICHT beschreibbar!</p>";
            echo "<p>Lösung: chmod 755 uploads/documents</p>";
        }

        // Zeige Dateien im Upload-Verzeichnis
        $files = scandir($upload_dir);
        $files = array_diff($files, ['.', '..']);

        if (count($files) > 0) {
            echo "<p>Dateien im Upload-Verzeichnis: <strong>" . count($files) . "</strong></p>";
            echo "<ul>";
            foreach ($files as $file) {
                $filepath = $upload_dir . '/' . $file;
                $size = filesize($filepath);
                echo "<li>$file (" . round($size/1024, 2) . " KB)</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='warning'>⚠ Keine Dateien im Upload-Verzeichnis</p>";
        }

    } else {
        echo "<p class='error'>✗ Upload-Verzeichnis existiert NICHT: $upload_dir</p>";
        echo "<p>Lösung: mkdir -p uploads/documents && chmod 755 uploads/documents</p>";
    }

    // Prüfe Funktionen
    echo "<h2>Funktions-Check:</h2>";

    if (function_exists('is_admin_user')) {
        echo "<p class='ok'>✓ is_admin_user() verfügbar</p>";
    } else {
        echo "<p class='error'>✗ is_admin_user() NICHT verfügbar</p>";
    }

    if (function_exists('get_member_access_level')) {
        echo "<p class='ok'>✓ get_member_access_level() verfügbar</p>";
    } else {
        echo "<p class='error'>✗ get_member_access_level() NICHT verfügbar</p>";
    }

    if (function_exists('get_documents')) {
        echo "<p class='ok'>✓ get_documents() verfügbar</p>";
    } else {
        echo "<p class='error'>✗ get_documents() NICHT verfügbar</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>FEHLER: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<h2>Nächste Schritte:</h2>";
echo "<ol>";
echo "<li>Falls Tabellen fehlen: <a href='install_documents.php'>Installation ausführen</a></li>";
echo "<li>Zur Dokumentenverwaltung: <a href='?tab=documents'>Dokumente</a></li>";
echo "<li>Dokument hochladen: <a href='?tab=documents&view=upload'>Upload</a> (nur Admin)</li>";
echo "</ol>";

echo "</body></html>";
?>
