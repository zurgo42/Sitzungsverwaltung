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
            $stmt = $pdo->prepare("SELECT meeting_id, title, protocol_notes FROM svagenda_items WHERE item_id = ?");
            $stmt->execute([$item_id]);
            $item_data = $stmt->fetch();
            $meeting_id = $item_data['meeting_id'] ?? 0;
            $old_notes = (string)($item_data['protocol_notes'] ?? '');

            $stmt = $pdo->prepare("UPDATE svagenda_items SET protocol_notes = ? WHERE item_id = ?");
            $stmt->execute([$notes, $item_id]);

            [$_prot_mnr, $_prot_kurz] = get_protokoll_user($current_user);
            $prot_diff = protokoll_feld_diff('Protokoll', $old_notes, $notes) ?? '(unverändert)';
            protokoll($pdo, $_prot_mnr, $_prot_kurz, 'Protokoll-Speichern',
                'Sitzung ' . $meeting_id . ' – ' . ($item_data['title'] ?? '') . ': ' . $prot_diff);

            header("Location: ?tab=agenda&meeting_id=$meeting_id");
            exit;
        } catch (PDOException $e) {
            $error = "Fehler beim Speichern des Protokolls";
        }
    }
}
?>