<?php
/**
 * process_protocol.php - Verarbeitung von Protokoll-Aktionen
 */

// Protokoll hinzuf�gen
if (isset($_POST['add_protocol'])) {
    $item_id = $_POST['item_id'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    if ($item_id) {
        try {
            $stmt = $pdo->prepare("UPDATE svagenda_items SET protocol_notes = ? WHERE item_id = ?");
            $stmt->execute([$notes, $item_id]);
            
            $stmt = $pdo->prepare("SELECT meeting_id FROM svagenda_items WHERE item_id = ?");
            $stmt->execute([$item_id]);
            $meeting_id = $stmt->fetch()['meeting_id'];
            
            header("Location: ?tab=agenda&meeting_id=$meeting_id");
            exit;
        } catch (PDOException $e) {
            $error = "Fehler beim Speichern des Protokolls";
        }
    }
}
?>