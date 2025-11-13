<?php
/**
 * process_protokoll.php - Protokoll-Logik
 * Timestamp: 2025-10-22 21:00 MEZ
 * Generiert kompakte HTML-Protokolle bei Genehmigung
 */

// Hilfsfunktion: Kompaktes Protokoll generieren
function generate_compact_protocol($pdo, $meeting_id) {
    // Meeting-Daten laden
    $stmt = $pdo->prepare("
        SELECT m.*,
            chairman.first_name as chairman_first, chairman.last_name as chairman_last,
            secretary.first_name as secretary_first, secretary.last_name as secretary_last
        FROM meetings m
        LEFT JOIN members chairman ON m.chairman_member_id = chairman.member_id
        LEFT JOIN members secretary ON m.secretary_member_id = secretary.member_id
        WHERE m.meeting_id = ?
    ");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();
    
    if (!$meeting) {
        return false;
    }
    
    // Teilnehmer laden
    $stmt = $pdo->prepare("
        SELECT m.first_name, m.last_name
        FROM meeting_participants mp
        JOIN members m ON mp.member_id = m.member_id
        WHERE mp.meeting_id = ?
        ORDER BY m.last_name, m.first_name
    ");
    $stmt->execute([$meeting_id]);
    $participants = $stmt->fetchAll();
    
    // Agenda Items laden (nur nicht-vertrauliche, sortiert nach TOP-Nummer)
    $stmt = $pdo->prepare("
        SELECT *
        FROM agenda_items
        WHERE meeting_id = ? AND is_confidential = 0
        ORDER BY 
            CASE 
                WHEN top_number = 0 THEN 0
                WHEN top_number = 99 THEN 998
                WHEN top_number = 999 THEN 999
                ELSE top_number
            END
    ");
    $stmt->execute([$meeting_id]);
    $agenda_items = $stmt->fetchAll();
    
    // HTML-Protokoll zusammenbauen
    $html = '<tr class="mitr"><td class="mitr">';
    
    // Kopfzeile
    $html .= '<h3>Protokoll:<br>';
    if (!empty($meeting['meeting_name'])) {
        $html .= htmlspecialchars($meeting['meeting_name']) . '<br>';
    }
    $html .= 'Meeting am ' . date('d.m.Y', strtotime($meeting['meeting_date']));
    if ($meeting['started_at'] && $meeting['ended_at']) {
        $html .= ' von ' . date('H:i', strtotime($meeting['started_at'])) 
              . ' bis ' . date('H:i', strtotime($meeting['ended_at']));
    }
    $html .= '</h3>';
    
    // Teilnehmer
    if (!empty($participants)) {
        $html .= 'Teilnehmer:<br>';
        $names = array_map(function($p) {
            return htmlspecialchars($p['first_name'] . ' ' . $p['last_name']);
        }, $participants);
        $html .= implode(', ', $names) . '<br>';
    }
    
    // Sitzungsleitung & Protokoll
    if ($meeting['chairman_first']) {
        $html .= 'Sitzungsleitung: ' . htmlspecialchars($meeting['chairman_first'] . ' ' . $meeting['chairman_last']) . '<br>';
    }
    if ($meeting['secretary_first']) {
        $html .= 'Protokollführer: ' . htmlspecialchars($meeting['secretary_first'] . ' ' . $meeting['secretary_last']) . '<br>';
    }
    
    // Besprechungspunkte
    $html .= '<tr class="mitr"><td class="mitr"><h3>Besprechungspunkte:</h3>';
    
    foreach ($agenda_items as $item) {
        // TOP-Nummer und Titel
        if ($item['top_number'] == 0) {
            $html .= '<b>TOP 0 - Sitzungsleitung und Protokoll</b><br>';
        } elseif ($item['top_number'] == 99) {
            $html .= '<b>TOP Verschiedenes</b><br>';
        } elseif ($item['top_number'] == 999) {
            // TOP 999 überspringen (Sitzungsende)
            continue;
        } else {
            $html .= '<b>TOP ' . $item['top_number'] . ' - ' . htmlspecialchars($item['title']) . '</b><br>';
        }
        
        // Beschreibung (falls vorhanden)
        if (!empty($item['description']) && $item['description'] != '-') {
            $html .= htmlspecialchars($item['description']) . '<br>';
        }
        
        // Protokoll-Notizen
        if (!empty($item['protocol_notes']) && $item['protocol_notes'] != '-') {
            $html .= nl2br(htmlspecialchars($item['protocol_notes'])) . '<br>';
        }
        
        $html .= '<br>';
    }
    
    $html .= '</td></tr>';
    
    // Genehmigungsvermerk
    $html .= '<tr class="mitr"><td class="mitr">';
    $html .= '<b>Das Protokoll wurde am ' . date('d.m.Y - H:i') . ' Uhr genehmigt.</b>';
    $html .= '</td></tr>';
    
    return $html;
}

// Hilfsfunktion: Wörter im Text highlighten
function highlightWords($text, $keywords) {
    if (empty($keywords)) {
        return $text;
    }
    
    $words = array_filter(array_map('trim', explode(' ', $keywords)));
    
    foreach ($words as $word) {
        if (strlen($word) < 3) continue; // Zu kurze Wörter ignorieren
        
        // Suche case-insensitive, ersetze aber case-preserving
        $text = preg_replace(
            '/(' . preg_quote($word, '/') . ')/iu',
            '<span class="hbl">$1</span>',
            $text
        );
    }
    
    return $text;
}

// Wenn Protokoll genehmigt wird (aus process_agenda.php aufgerufen)
function save_approved_protocol($pdo, $meeting_id) {
    $protokoll_html = generate_compact_protocol($pdo, $meeting_id);
    
    if ($protokoll_html) {
        $stmt = $pdo->prepare("UPDATE meetings SET protokoll = ? WHERE meeting_id = ?");
        $stmt->execute([$protokoll_html, $meeting_id]);
        return true;
    }
    
    return false;
}
