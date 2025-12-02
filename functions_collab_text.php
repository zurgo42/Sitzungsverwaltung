<?php
/**
 * functions_collab_text.php - Backend-Funktionen für kollaborative Texte
 *
 * Funktionen für gemeinsames Arbeiten an Texten während Sitzungen
 * Absatz-basiertes Editieren mit Lock-System
 */

/**
 * Erstellt einen neuen kollaborativen Text für eine Sitzung
 *
 * @param PDO $pdo Datenbankverbindung
 * @param int $meeting_id ID der Sitzung
 * @param int $initiator_member_id ID des Protokollanten
 * @param string $title Titel des Textes
 * @param string $initial_content Optional: Initialer Text-Inhalt
 * @return int|false text_id bei Erfolg, false bei Fehler
 */
function createCollabText($pdo, $meeting_id, $initiator_member_id, $title, $initial_content = '') {
    try {
        $pdo->beginTransaction();

        // Text erstellen
        $stmt = $pdo->prepare("
            INSERT INTO svcollab_texts (meeting_id, initiator_member_id, title, status)
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute([$meeting_id, $initiator_member_id, $title]);
        $text_id = $pdo->lastInsertId();

        // Ersten Absatz erstellen (falls initial content vorhanden)
        if (!empty(trim($initial_content))) {
            $stmt = $pdo->prepare("
                INSERT INTO svcollab_text_paragraphs (text_id, paragraph_order, content, last_edited_by, last_edited_at)
                VALUES (?, 1, ?, ?, NOW())
            ");
            $stmt->execute([$text_id, $initial_content, $initiator_member_id]);
        } else {
            // Leeren ersten Absatz erstellen
            $stmt = $pdo->prepare("
                INSERT INTO svcollab_text_paragraphs (text_id, paragraph_order, content)
                VALUES (?, 1, '')
            ");
            $stmt->execute([$text_id]);
        }

        // Alle Sitzungs-Teilnehmer als Participants hinzufügen
        $stmt = $pdo->prepare("
            INSERT INTO svcollab_text_participants (text_id, member_id, last_seen)
            SELECT ?, member_id, NOW()
            FROM svmeeting_participants
            WHERE meeting_id = ? AND status IN ('present', 'confirmed')
        ");
        $stmt->execute([$text_id, $meeting_id]);

        // Erste Version speichern
        $stmt = $pdo->prepare("
            INSERT INTO svcollab_text_versions (text_id, version_number, content, created_by, version_note)
            VALUES (?, 1, ?, ?, 'Initiale Version')
        ");
        $stmt->execute([$text_id, $initial_content, $initiator_member_id]);

        $pdo->commit();
        return $text_id;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("createCollabText Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Lädt einen kollaborativen Text mit allen Absätzen
 *
 * @param PDO $pdo
 * @param int $text_id
 * @return array|false Text-Daten mit Absätzen, oder false bei Fehler
 */
function getCollabText($pdo, $text_id) {
    try {
        // Text-Metadaten laden
        $stmt = $pdo->prepare("
            SELECT t.*,
                   m.first_name as initiator_first_name,
                   m.last_name as initiator_last_name,
                   mt.meeting_name
            FROM svcollab_texts t
            JOIN svmembers m ON t.initiator_member_id = m.member_id
            JOIN svmeetings mt ON t.meeting_id = mt.meeting_id
            WHERE t.text_id = ?
        ");
        $stmt->execute([$text_id]);
        $text = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$text) {
            return false;
        }

        // Absätze laden
        $stmt = $pdo->prepare("
            SELECT p.*,
                   m.first_name as editor_first_name,
                   m.last_name as editor_last_name,
                   l.member_id as locked_by_member_id,
                   lm.first_name as locked_by_first_name,
                   lm.last_name as locked_by_last_name
            FROM svcollab_text_paragraphs p
            LEFT JOIN svmembers m ON p.last_edited_by = m.member_id
            LEFT JOIN svcollab_text_locks l ON p.paragraph_id = l.paragraph_id
            LEFT JOIN svmembers lm ON l.member_id = lm.member_id
            WHERE p.text_id = ?
            ORDER BY p.paragraph_order ASC
        ");
        $stmt->execute([$text_id]);
        $text['paragraphs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $text;

    } catch (PDOException $e) {
        error_log("getCollabText Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Versucht einen Absatz zu sperren (Lock zu erwerben)
 *
 * @param PDO $pdo
 * @param int $paragraph_id
 * @param int $member_id
 * @return bool true bei Erfolg, false wenn bereits gesperrt
 */
function lockParagraph($pdo, $paragraph_id, $member_id) {
    try {
        // Alte Locks aufräumen (> 2 Minuten inaktiv)
        $pdo->exec("
            DELETE FROM svcollab_text_locks
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ");

        // Prüfen ob schon ein Lock existiert
        $stmt = $pdo->prepare("
            SELECT l.member_id, m.first_name, m.last_name
            FROM svcollab_text_locks l
            JOIN svmembers m ON l.member_id = m.member_id
            WHERE l.paragraph_id = ?
        ");
        $stmt->execute([$paragraph_id]);
        $existing_lock = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_lock) {
            // Wenn eigener Lock → OK, Activity-Zeit aktualisieren
            if ($existing_lock['member_id'] == $member_id) {
                $stmt = $pdo->prepare("
                    UPDATE svcollab_text_locks
                    SET last_activity = NOW()
                    WHERE paragraph_id = ? AND member_id = ?
                ");
                $stmt->execute([$paragraph_id, $member_id]);
                return true;
            }
            // Lock gehört jemand anderem
            return false;
        }

        // Lock erwerben
        $stmt = $pdo->prepare("
            INSERT INTO svcollab_text_locks (paragraph_id, member_id, locked_at, last_activity)
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$paragraph_id, $member_id]);
        return true;

    } catch (PDOException $e) {
        error_log("lockParagraph Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gibt einen Lock frei
 *
 * @param PDO $pdo
 * @param int $paragraph_id
 * @param int $member_id Nur der Lock-Besitzer kann freigeben
 * @return bool
 */
function unlockParagraph($pdo, $paragraph_id, $member_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM svcollab_text_locks
            WHERE paragraph_id = ? AND member_id = ?
        ");
        $stmt->execute([$paragraph_id, $member_id]);
        return true;

    } catch (PDOException $e) {
        error_log("unlockParagraph Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Speichert einen Absatz und gibt den Lock frei
 *
 * @param PDO $pdo
 * @param int $paragraph_id
 * @param int $member_id
 * @param string $content
 * @return bool
 */
function saveParagraph($pdo, $paragraph_id, $member_id, $content) {
    try {
        // Prüfen ob User den Lock hat
        $stmt = $pdo->prepare("
            SELECT member_id FROM svcollab_text_locks WHERE paragraph_id = ?
        ");
        $stmt->execute([$paragraph_id]);
        $lock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lock || $lock['member_id'] != $member_id) {
            return false; // Kein Lock oder nicht der Besitzer
        }

        // Absatz aktualisieren
        $stmt = $pdo->prepare("
            UPDATE svcollab_text_paragraphs
            SET content = ?, last_edited_by = ?, last_edited_at = NOW()
            WHERE paragraph_id = ?
        ");
        $stmt->execute([$content, $member_id, $paragraph_id]);

        // Lock freigeben
        unlockParagraph($pdo, $paragraph_id, $member_id);

        return true;

    } catch (PDOException $e) {
        error_log("saveParagraph Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fügt einen neuen Absatz hinzu
 *
 * @param PDO $pdo
 * @param int $text_id
 * @param int $member_id
 * @param int $after_order Nach welchem Absatz einfügen (paragraph_order)
 * @return int|false paragraph_id bei Erfolg
 */
function addParagraph($pdo, $text_id, $member_id, $after_order = null) {
    try {
        $pdo->beginTransaction();

        // Wenn after_order angegeben, alle folgenden Absätze um 1 verschieben
        if ($after_order !== null) {
            $stmt = $pdo->prepare("
                UPDATE svcollab_text_paragraphs
                SET paragraph_order = paragraph_order + 1
                WHERE text_id = ? AND paragraph_order > ?
                ORDER BY paragraph_order DESC
            ");
            $stmt->execute([$text_id, $after_order]);
            $new_order = $after_order + 1;
        } else {
            // Am Ende anhängen
            $stmt = $pdo->prepare("
                SELECT MAX(paragraph_order) as max_order
                FROM svcollab_text_paragraphs
                WHERE text_id = ?
            ");
            $stmt->execute([$text_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_order = ($result['max_order'] ?? 0) + 1;
        }

        // Neuen Absatz erstellen
        $stmt = $pdo->prepare("
            INSERT INTO svcollab_text_paragraphs (text_id, paragraph_order, content, last_edited_by, last_edited_at)
            VALUES (?, ?, '', ?, NOW())
        ");
        $stmt->execute([$text_id, $new_order, $member_id]);
        $paragraph_id = $pdo->lastInsertId();

        $pdo->commit();
        return $paragraph_id;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("addParagraph Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Löscht einen Absatz
 *
 * @param PDO $pdo
 * @param int $paragraph_id
 * @param int $member_id User der löscht
 * @return bool
 */
function deleteParagraph($pdo, $paragraph_id, $member_id) {
    try {
        $pdo->beginTransaction();

        // Absatz-Info holen (für Order-Update)
        $stmt = $pdo->prepare("
            SELECT text_id, paragraph_order
            FROM svcollab_text_paragraphs
            WHERE paragraph_id = ?
        ");
        $stmt->execute([$paragraph_id]);
        $para = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$para) {
            $pdo->rollBack();
            return false;
        }

        // Absatz löschen
        $stmt = $pdo->prepare("DELETE FROM svcollab_text_paragraphs WHERE paragraph_id = ?");
        $stmt->execute([$paragraph_id]);

        // Folgende Absätze um 1 nach oben schieben
        $stmt = $pdo->prepare("
            UPDATE svcollab_text_paragraphs
            SET paragraph_order = paragraph_order - 1
            WHERE text_id = ? AND paragraph_order > ?
        ");
        $stmt->execute([$para['text_id'], $para['paragraph_order']]);

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("deleteParagraph Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Aktualisiert "last_seen" für einen Teilnehmer (Heartbeat)
 *
 * @param PDO $pdo
 * @param int $text_id
 * @param int $member_id
 * @return bool
 */
function updateParticipantHeartbeat($pdo, $text_id, $member_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO svcollab_text_participants (text_id, member_id, last_seen)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_seen = NOW()
        ");
        $stmt->execute([$text_id, $member_id]);
        return true;

    } catch (PDOException $e) {
        error_log("updateParticipantHeartbeat Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Holt Online-Status aller Teilnehmer (wer war in letzten 30 Sekunden aktiv?)
 *
 * @param PDO $pdo
 * @param int $text_id
 * @return array Liste von Teilnehmern mit Namen
 */
function getOnlineParticipants($pdo, $text_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.member_id, p.last_seen,
                   m.first_name, m.last_name
            FROM svcollab_text_participants p
            JOIN svmembers m ON p.member_id = m.member_id
            WHERE p.text_id = ?
              AND p.last_seen > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            ORDER BY m.first_name, m.last_name
        ");
        $stmt->execute([$text_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("getOnlineParticipants Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Erstellt eine neue Version (Snapshot) des aktuellen Textes
 *
 * @param PDO $pdo
 * @param int $text_id
 * @param int $member_id User der die Version erstellt
 * @param string $note Optional: Notiz zur Version
 * @return int|false version_number bei Erfolg
 */
function createTextVersion($pdo, $text_id, $member_id, $note = '') {
    try {
        // Alle Absätze zu einem Text zusammenfügen
        $stmt = $pdo->prepare("
            SELECT content
            FROM svcollab_text_paragraphs
            WHERE text_id = ?
            ORDER BY paragraph_order ASC
        ");
        $stmt->execute([$text_id]);
        $paragraphs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $full_content = implode("\n\n", array_column($paragraphs, 'content'));

        // Höchste Versionsnummer ermitteln
        $stmt = $pdo->prepare("
            SELECT MAX(version_number) as max_version
            FROM svcollab_text_versions
            WHERE text_id = ?
        ");
        $stmt->execute([$text_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_version = ($result['max_version'] ?? 0) + 1;

        // Version speichern
        $stmt = $pdo->prepare("
            INSERT INTO svcollab_text_versions (text_id, version_number, content, created_by, version_note)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$text_id, $new_version, $full_content, $member_id, $note]);

        return $new_version;

    } catch (PDOException $e) {
        error_log("createTextVersion Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Finalisiert einen Text (beendet Bearbeitung)
 *
 * @param PDO $pdo
 * @param int $text_id
 * @param int $member_id User der finalisiert (muss Initiator sein)
 * @param string $final_name Name für finale Version
 * @return bool
 */
function finalizeCollabText($pdo, $text_id, $member_id, $final_name) {
    try {
        // Prüfen ob User der Initiator ist
        $stmt = $pdo->prepare("
            SELECT initiator_member_id FROM svcollab_texts WHERE text_id = ?
        ");
        $stmt->execute([$text_id]);
        $text = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$text || $text['initiator_member_id'] != $member_id) {
            return false; // Nicht berechtigt
        }

        $pdo->beginTransaction();

        // Finale Version erstellen
        createTextVersion($pdo, $text_id, $member_id, 'Finale Version');

        // Alle Locks aufheben
        $stmt = $pdo->prepare("
            DELETE l FROM svcollab_text_locks l
            JOIN svcollab_text_paragraphs p ON l.paragraph_id = p.paragraph_id
            WHERE p.text_id = ?
        ");
        $stmt->execute([$text_id]);

        // Text als finalized markieren
        $stmt = $pdo->prepare("
            UPDATE svcollab_texts
            SET status = 'finalized', finalized_at = NOW(), final_name = ?
            WHERE text_id = ?
        ");
        $stmt->execute([$final_name, $text_id]);

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("finalizeCollabText Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Holt alle Versionen eines Textes
 *
 * @param PDO $pdo
 * @param int $text_id
 * @return array Liste von Versionen
 */
function getTextVersions($pdo, $text_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT v.*, m.first_name, m.last_name
            FROM svcollab_text_versions v
            JOIN svmembers m ON v.created_by = m.member_id
            WHERE v.text_id = ?
            ORDER BY v.version_number DESC
        ");
        $stmt->execute([$text_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("getTextVersions Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Holt eine bestimmte Version
 *
 * @param PDO $pdo
 * @param int $text_id
 * @param int $version_number
 * @return array|false Version-Daten
 */
function getTextVersion($pdo, $text_id, $version_number) {
    try {
        $stmt = $pdo->prepare("
            SELECT v.*, m.first_name, m.last_name
            FROM svcollab_text_versions v
            JOIN svmembers m ON v.created_by = m.member_id
            WHERE v.text_id = ? AND v.version_number = ?
        ");
        $stmt->execute([$text_id, $version_number]);
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("getTextVersion Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Holt alle kollaborativen Texte einer Sitzung
 *
 * @param PDO $pdo
 * @param int $meeting_id
 * @return array Liste von Texten
 */
function getCollabTextsByMeeting($pdo, $meeting_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*,
                   m.first_name as initiator_first_name,
                   m.last_name as initiator_last_name,
                   COUNT(DISTINCT p.member_id) as participant_count
            FROM svcollab_texts t
            JOIN svmembers m ON t.initiator_member_id = m.member_id
            LEFT JOIN svcollab_text_participants p ON t.text_id = p.text_id
            WHERE t.meeting_id = ?
            GROUP BY t.text_id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$meeting_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("getCollabTextsByMeeting Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Prüft ob ein User Zugriff auf einen Text hat (ist Teilnehmer der Sitzung)
 *
 * @param PDO $pdo
 * @param int $text_id
 * @param int $member_id
 * @return bool
 */
function hasCollabTextAccess($pdo, $text_id, $member_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as has_access
            FROM svcollab_texts t
            JOIN svmeeting_participants mp ON t.meeting_id = mp.meeting_id
            WHERE t.text_id = ? AND mp.member_id = ?
        ");
        $stmt->execute([$text_id, $member_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['has_access'] > 0;

    } catch (PDOException $e) {
        error_log("hasCollabTextAccess Error: " . $e->getMessage());
        return false;
    }
}
