<?php
/**
 * module_protocol.php - Protokoll-Generierung
 * 
 * Generiert HTML-codierte Protokolle aus den TOPs
 * Unterscheidet zwischen öffentlichem und vertraulichem Protokoll
 */

/**
 * Generiert Kurzprotokoll aus allen TOPs
 * 
 * @return array ['public' => string, 'confidential' => string]
 */
function generate_protocol($pdo, $meeting, $agenda_items, $participants) {
    // Protokoll-Variablen initialisieren (HTML-codiert für Datenbank)
    $protokoll = '&lt;tr class=&quot;mitr&quot;&gt;&lt;td class=&quot;mitr&quot;&gt;&lt;h3&gt;Protokoll:&lt;br&gt;';
    $protokoll_intern = '';
    $protokoll_intern_exist = 0;
    
    // === HEADER: Meeting-Informationen ===
    
    // Start- und Endzeitpunkt aus Meeting-Daten holen
    // WICHTIG: started_at/ended_at verwenden (nicht meeting_date oder TOP 999)
    $start_time = $meeting['started_at'] ?? $meeting['meeting_date']; // Fallback auf meeting_date
    $end_time = $meeting['ended_at']; // Korrektes Sitzungsende verwenden

    // Meeting-Name und Zeit
    $protokoll .= htmlspecialchars($meeting['meeting_name'] ?? 'Ohne Namen') . '&lt;br&gt;am ' .
                 date('d.m.Y H:i', strtotime($start_time)) . ' Uhr';

    if ($end_time) {
        $protokoll .= ' bis ';
        if (date('d.m.Y', strtotime($start_time)) != date('d.m.Y', strtotime($end_time))) {
            $protokoll .= date('d.m.Y', strtotime($end_time)) . ' ';
        }
        $protokoll .= date('H:i', strtotime($end_time)) . ' Uhr ';
    }
    
    // Ort
    if (!strpos($meeting['location'], 'nline')) {
        $protokoll .= 'in ';
    }
    $protokoll .= htmlspecialchars($meeting['location']);
    
    // === TEILNEHMER ===
    $present_list = [];
    foreach ($participants as $p) {
        $stmt = $pdo->prepare("SELECT attendance_status FROM svmeeting_participants WHERE meeting_id = ? AND member_id = ?");
        $stmt->execute([$meeting['meeting_id'], $p['member_id']]);
        $attendance = $stmt->fetch();
        $status = $attendance['attendance_status'] ?? 'absent';
        
        if ($status === 'present') {
            $present_list[] = $p['first_name'] . ' ' . $p['last_name'];
        } elseif ($status === 'partial') {
            $present_list[] = $p['first_name'] . ' ' . $p['last_name'] . ' (zeitweise)';
        }
    }
    
    $protokoll .= '&lt;/h3&gt;&lt;strong&gt;Teilgenommen haben: &lt;/strong&gt;' . 
                 htmlspecialchars(implode(', ', $present_list));
    
    // Sitzungsleitung und Protokoll
    $chairman_name = get_member_name($pdo, $meeting['chairman_member_id']);
    $secretary_name = get_member_name($pdo, $meeting['secretary_member_id']);
    
    $protokoll .= '&lt;br&gt;&lt;strong&gt;Sitzungsleitung: &lt;/strong&gt;' . 
                 htmlspecialchars($chairman_name);
    $protokoll .= ', &lt;strong&gt;Protokoll: &lt;/strong&gt;' . 
                 htmlspecialchars($secretary_name);
    
    // === TOPs DURCHGEHEN ===
    foreach ($agenda_items as $item) {
        // Nur TOP 999 überspringen (technischer Marker für Ende)
        if ($item['top_number'] == 999) {
            continue;
        }
        
        // TOP 0: Spezialbehandlung
        if ($item['top_number'] == 0) {
            // TOP 0 nur wenn Protokollnotizen vorhanden
            if (!empty($item['protocol_notes'])) {
                $protokoll .= '&lt;br&gt;&lt;br&gt;&lt;strong&gt;Eröffnung:&lt;/strong&gt;&lt;br&gt;';
                $protokoll .= nl2br(htmlspecialchars($item['protocol_notes']));
            }
            continue;
        }
        
        // Vertrauliche TOPs in separates Protokoll
        if ($item['is_confidential']) {
            if ($protokoll_intern_exist == 0) {
                $protokoll_intern = '&lt;tr class=&quot;mitr&quot;&gt;&lt;td class=&quot;mitr&quot;&gt;&lt;h3&gt;Internes Protokoll:&lt;br&gt;';
                $protokoll_intern .= htmlspecialchars($meeting['meeting_name'] ?? 'Ohne Namen') . '&lt;br&gt;am ' . 
                                   date('d.m.Y H:i', strtotime($meeting['meeting_date'])) . ' Uhr';
                if ($end_time) {
                    $protokoll_intern .= ' bis ';
                    if (date('d.m.Y', strtotime($meeting['meeting_date'])) != date('d.m.Y', strtotime($end_time))) {
                        $protokoll_intern .= date('d.m.Y', strtotime($end_time)) . ' ';
                    }
                    $protokoll_intern .= date('H:i', strtotime($end_time)) . ' Uhr ';
                }
                if (!strpos($meeting['location'], 'nline')) {
                    $protokoll_intern .= 'in ';
                }
                $protokoll_intern .= htmlspecialchars($meeting['location']);
                $protokoll_intern .= '&lt;/h3&gt;&lt;strong&gt;Teilgenommen haben: &lt;/strong&gt;' . 
                                   htmlspecialchars(implode(', ', $present_list));
                $protokoll_intern .= '&lt;br&gt;&lt;strong&gt;Sitzungsleitung: &lt;/strong&gt;' . 
                                   htmlspecialchars($chairman_name);
                $protokoll_intern .= ', &lt;strong&gt;Protokoll: &lt;/strong&gt;' . 
                                   htmlspecialchars($secretary_name);
                $protokoll_intern_exist = 1;
            }
            
            $protokoll_intern .= '&lt;br&gt;&lt;br&gt;&lt;strong&gt;TOP ' . $item['top_number'] . ': ' .
                               htmlspecialchars($item['title']) . '&lt;/strong&gt;&lt;br&gt;';

            // Antragstext bei Antrag/Beschluss
            if ($item['category'] === 'antrag_beschluss' && !empty($item['proposal_text'])) {
                $protokoll_intern .= '&lt;em&gt;Antragstext: ' .
                                   nl2br(htmlspecialchars($item['proposal_text'])) . '&lt;/em&gt;&lt;br&gt;';
            }

            // Protokolltext oder Hinweis
            if (!empty($item['protocol_notes'])) {
                $protokoll_intern .= nl2br(htmlspecialchars($item['protocol_notes']));
            } else {
                $protokoll_intern .= '&lt;em style=&quot;color: #999;&quot;&gt;(noch kein Protokoll)&lt;/em&gt;';
            }
            
            // Abstimmungsergebnis
            if (!empty($item['vote_result'])) {
                $protokoll_intern .= '&lt;br&gt;&lt;strong&gt;Abstimmungsergebnis: &lt;/strong&gt;';
                
                if (in_array($item['vote_result'], ['einvernehmlich', 'einstimmig'])) {
                    $protokoll_intern .= ucfirst($item['vote_result']);
                } else {
                    $protokoll_intern .= ($item['vote_yes'] ?? 0) . ' Ja, ' . 
                                       ($item['vote_no'] ?? 0) . ' Nein, ' . 
                                       ($item['vote_abstain'] ?? 0) . ' Enthaltung - ' . 
                                       ucfirst($item['vote_result']);
                }
            }
            
        } else {
            // Öffentliches Protokoll
            $protokoll .= '&lt;br&gt;&lt;br&gt;&lt;strong&gt;TOP ' . $item['top_number'] . ': ' .
                         htmlspecialchars($item['title']) . '&lt;/strong&gt;&lt;br&gt;';

            // Antragstext bei Antrag/Beschluss
            if ($item['category'] === 'antrag_beschluss' && !empty($item['proposal_text'])) {
                $protokoll .= '&lt;em&gt;Antragstext: ' .
                             nl2br(htmlspecialchars($item['proposal_text'])) . '&lt;/em&gt;&lt;br&gt;';
            }

            // Protokolltext oder Hinweis
            if (!empty($item['protocol_notes'])) {
                $protokoll .= nl2br(htmlspecialchars($item['protocol_notes']));
            } else {
                $protokoll .= '&lt;em style=&quot;color: #999;&quot;&gt;(noch kein Protokoll)&lt;/em&gt;';
            }
            
            // Abstimmungsergebnis
            if (!empty($item['vote_result'])) {
                $protokoll .= '&lt;br&gt;&lt;strong&gt;Abstimmungsergebnis: &lt;/strong&gt;';
                
                if (in_array($item['vote_result'], ['einvernehmlich', 'einstimmig'])) {
                    $protokoll .= ucfirst($item['vote_result']);
                } else {
                    $protokoll .= ($item['vote_yes'] ?? 0) . ' Ja, ' . 
                                 ($item['vote_no'] ?? 0) . ' Nein, ' . 
                                 ($item['vote_abstain'] ?? 0) . ' Enthaltung - ' . 
                                 ucfirst($item['vote_result']);
                }
            }
        }
    }
    
    // Sitzungsende hinzufügen
    if ($end_time) {
        $protokoll .= '&lt;br&gt;&lt;strong&gt;Sitzungsende: ' . 
                     date('H:i', strtotime($end_time)) . ' Uhr&lt;/strong&gt;';
        if ($protokoll_intern_exist) {
            $protokoll_intern .= '&lt;br&gt;&lt;strong&gt;Sitzungsende am ' . 
                               date('d.m.Y', strtotime($end_time)) . ' um ' . 
                               date('H:i', strtotime($end_time)) . ' Uhr&lt;/strong&gt;';
        }
    }
    
    // Abschluss
    $protokoll .= '&lt;/td&gt;&lt;/tr&gt;';
    if ($protokoll_intern_exist) {
        $protokoll_intern .= '&lt;/td&gt;&lt;/tr&gt;';
    }
    
    return [
        'public' => $protokoll,
        'confidential' => $protokoll_intern_exist ? $protokoll_intern : ''
    ];
}

/**
 * Zeigt das Kurzprotokoll dekodiert an
 */
function display_protocol($protocol_html) {
    if (empty($protocol_html)) {
        echo '<div style="color: #999; padding: 20px; text-align: center;">Noch kein Protokoll vorhanden</div>';
        return;
    }

    // HTML-Entities dekodieren für Anzeige
    $decoded = html_entity_decode($protocol_html);
    // <tr> und <td> Tags durch divs ersetzen für bessere Darstellung (nach dem Dekodieren!)
    $decoded = str_replace(['<tr class="mitr">', '</tr>'], ['<div>', '</div>'], $decoded);
    $decoded = str_replace(['<td class="mitr">', '</td>'], ['', ''], $decoded);

    echo '<div style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; line-height: 1.8;">';
    echo $decoded;
    echo '</div>';
}
?>
