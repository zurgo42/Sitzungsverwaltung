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
    $stmt = $pdo->prepare("
        SELECT ct.text_id, ct.status
        FROM svcollab_texts ct
        LEFT JOIN svcollab_text_participants p ON ct.text_id = p.text_id AND p.member_id = ?
        WHERE ct.text_id = ? AND (p.member_id IS NOT NULL OR ct.created_by = ?)
    ");
    $stmt->execute([$current_user_id, $text_id, $current_user_id]);
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

    // Vertauschen mit temporärem Wert um UNIQUE constraint Probleme zu vermeiden
    $pdo->beginTransaction();

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
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Swap paragraphs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Datenbankfehler']);
}
