<?php
/**
 * personal_notes_functions.php - Funktionen für persönliche Notizen
 * Erstellt: 2026-05-09
 *
 * Verwaltet persönliche Notizen von Teilnehmern zu Agenda Items
 */

/**
 * Lädt die persönliche Notiz eines Users zu einem Agenda Item
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $item_id ID des Agenda Items
 * @param int $member_id ID des Members
 * @return array|null Notiz-Daten oder null wenn keine existiert
 */
function get_personal_note($pdo, $item_id, $member_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM svagenda_personal_notes
        WHERE item_id = ? AND member_id = ?
    ");
    $stmt->execute([$item_id, $member_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Speichert oder aktualisiert eine persönliche Notiz
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $item_id ID des Agenda Items
 * @param int $member_id ID des Members
 * @param string $note_text Notiz-Text
 * @return bool Erfolg
 */
function save_personal_note($pdo, $item_id, $member_id, $note_text) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO svagenda_personal_notes (item_id, member_id, note_text)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                note_text = VALUES(note_text),
                updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$item_id, $member_id, $note_text]);
    } catch (PDOException $e) {
        error_log("Fehler beim Speichern der persönlichen Notiz: " . $e->getMessage());
        return false;
    }
}

/**
 * Fügt automatisch eine TODO-Zeile zu den persönlichen Notizen hinzu
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $item_id ID des Agenda Items
 * @param int $member_id ID des Members
 * @param string $todo_title Titel des TODOs
 * @param string|null $todo_due_date Fälligkeitsdatum
 * @return bool Erfolg
 */
function append_todo_to_personal_note($pdo, $item_id, $member_id, $todo_title, $todo_due_date = null) {
    // Bestehende Notiz laden
    $existing_note = get_personal_note($pdo, $item_id, $member_id);

    // TODO-Zeile formatieren
    $due_text = $todo_due_date ? " bis " . date('d.m.Y', strtotime($todo_due_date)) : "";
    $todo_line = "📌 ToDo angelegt: " . $todo_title . $due_text;

    // An bestehende Notiz anhängen oder neue erstellen
    if ($existing_note && !empty($existing_note['note_text'])) {
        $new_text = $existing_note['note_text'] . "\n" . $todo_line;
    } else {
        $new_text = $todo_line;
    }

    return save_personal_note($pdo, $item_id, $member_id, $new_text);
}

/**
 * Löscht eine persönliche Notiz
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $item_id ID des Agenda Items
 * @param int $member_id ID des Members
 * @return bool Erfolg
 */
function delete_personal_note($pdo, $item_id, $member_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM svagenda_personal_notes
            WHERE item_id = ? AND member_id = ?
        ");
        return $stmt->execute([$item_id, $member_id]);
    } catch (PDOException $e) {
        error_log("Fehler beim Löschen der persönlichen Notiz: " . $e->getMessage());
        return false;
    }
}
