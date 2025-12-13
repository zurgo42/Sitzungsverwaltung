<?php
/**
 * api/collab_text_swap_paragraphs.php
 * Vertauscht die Reihenfolge zweier Absätze
 */

session_start();
require_once('../config.php');
require_once('db_connection.php');
require_once('../functions_collab_text.php');

header('Content-Type: application/json');

// Authentifizierung
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

$current_user_id = $_SESSION['member_id'];

// JSON-Input
$input = json_decode(file_get_contents('php://input'), true);
$text_id = $input['text_id'] ?? 0;
$paragraph1_id = $input['paragraph1_id'] ?? 0;
$paragraph2_id = $input['paragraph2_id'] ?? 0;

if (!$text_id || !$paragraph1_id || !$paragraph2_id) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']);
    exit;
}

try {
    // Prüfen ob User Zugriff hat
    if (!hasCollabTextAccess($pdo, $text_id, $current_user_id)) {
        echo json_encode(['success' => false, 'error' => 'Kein Zugriff']);
        exit;
    }

    // Text-Status holen
    $stmt = $pdo->prepare("SELECT status FROM svcollab_texts WHERE text_id = ?");
    $stmt->execute([$text_id]);
    $text = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$text) {
        echo json_encode(['success' => false, 'error' => 'Kein Zugriff']);
        exit;
    }

    // Prüfen ob Text finalisiert
    if ($text['status'] === 'finalized') {
        echo json_encode(['success' => false, 'error' => 'Text ist finalisiert']);
        exit;
    }

    // Paragraph-Order der beiden Absätze holen
    $stmt = $pdo->prepare("SELECT paragraph_id, paragraph_order FROM svcollab_text_paragraphs WHERE paragraph_id IN (?, ?) AND text_id = ?");
    $stmt->execute([$paragraph1_id, $paragraph2_id, $text_id]);
    $paragraphs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($paragraphs) !== 2) {
        echo json_encode(['success' => false, 'error' => 'Absätze nicht gefunden']);
        exit;
    }

    // Order-Werte extrahieren
    $para1 = array_filter($paragraphs, fn($p) => $p['paragraph_id'] == $paragraph1_id);
    $para2 = array_filter($paragraphs, fn($p) => $p['paragraph_id'] == $paragraph2_id);
    $para1 = reset($para1);
    $para2 = reset($para2);

    $order1 = $para1['paragraph_order'];
    $order2 = $para2['paragraph_order'];

    // Vertauschen mit temporärem Wert um Probleme zu vermeiden
    $pdo->beginTransaction();

    try {
        // Temporärer negativer Wert für paragraph1
        $stmt = $pdo->prepare("UPDATE svcollab_text_paragraphs SET paragraph_order = ? WHERE paragraph_id = ?");
        $stmt->execute([-9999, $paragraph1_id]);

        // paragraph2 auf order1 setzen
        $stmt->execute([$order1, $paragraph2_id]);

        // paragraph1 auf order2 setzen
        $stmt->execute([$order2, $paragraph1_id]);

        $pdo->commit();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Swap transaction error: " . $e->getMessage());
        throw $e; // Re-throw damit äußerer catch block greift
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Swap paragraphs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
