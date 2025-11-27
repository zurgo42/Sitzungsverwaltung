<?php
/**
 * AJAX-Endpoint: Kommentare eines TOPs abrufen
 */

// Error Reporting aktivieren für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Nicht direkt ausgeben, sondern als JSON

// Output buffering starten
ob_start();

try {
    session_start();
    
    // DB-Verbindung aufbauen
    require_once 'config.php';
    require_once 'functions.php';
    
    // Falls $pdo nicht durch config.php erstellt wurde, hier erstellen
    if (!isset($pdo)) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
    
    // Alle Ausgaben vorher verwerfen
    ob_clean();
    
    header('Content-Type: application/json');
    
    // Prüfen ob User eingeloggt ist
    if (!isset($_SESSION['member_id'])) {
        echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
        exit;
    }
    
    // Item ID prüfen
    if (!isset($_GET['item_id'])) {
        echo json_encode(['success' => false, 'error' => 'Keine Item-ID angegeben']);
        exit;
    }
    
    $item_id = intval($_GET['item_id']);
    
    // Kommentare abrufen
    $stmt = $pdo->prepare("
        SELECT 
            ac.comment_id,
            ac.comment_text,
            ac.priority_rating,
            ac.duration_estimate,
            ac.created_at,
            m.first_name,
            m.last_name,
            m.member_id
        FROM svagenda_comments ac
        JOIN svmembers m ON ac.member_id = m.member_id
        WHERE ac.item_id = ?
        ORDER BY ac.created_at ASC
    ");
    $stmt->execute([$item_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Alle vorherigen Ausgaben löschen
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    
} catch (Exception $e) {
    // Alle vorherigen Ausgaben löschen
    ob_clean();
    
    // Fehler als JSON ausgeben
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// Buffer beenden
ob_end_flush();
?>