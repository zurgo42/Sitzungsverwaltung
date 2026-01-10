<?php
/**
 * module_agenda_overview.php - Tagesordnungs-Übersicht mit Bewertungen
 * Version 3.0 - 12.11.2025
 * 
 * NEU: Bewertungen (Priorität/Dauer) werden hier in einer Tabelle erfasst
 */

/**
 * Rendert die Übersicht aller TOPs mit Bewertungsmöglichkeit
 * 
 * @param array $agenda_items Array aller Agenda Items
 * @param array $current_user Aktueller User (optional, wird aus globaler Variable geholt)
 * @param int $current_meeting_id Meeting ID (optional, wird aus globaler Variable geholt)
 * @param PDO $pdo Datenbankverbindung (optional, wird aus globaler Variable geholt)
 */
function render_agenda_overview($agenda_items, $current_user = null, $current_meeting_id = null, $pdo = null) {
    // Parameter aus globalen Variablen holen wenn nicht übergeben
    if ($current_user === null) {
        global $current_user;
    }
    if ($current_meeting_id === null) {
        global $current_meeting_id;
    }
    if ($pdo === null) {
        global $pdo;
    }
    
    // Parameter validieren
    if (!is_array($current_user) || !isset($current_user['member_id'])) {
        echo '<div style="padding: 20px; background: #ffebee; border: 2px solid #f44336; border-radius: 8px; margin: 20px 0;">';
		echo '<strong>Fehler:</strong> Benutzerdaten nicht verfügbar. Bitte neu einloggen.';
        echo '</div>';
        return;
    }
    
    if (!$pdo || !$current_meeting_id) {
        echo '<div style="padding: 20px; background: #ffebee; border: 2px solid #f44336; border-radius: 8px; margin: 20px 0;">';
        echo '<strong>Fehler:</strong> Datenbank oder Meeting-ID nicht verfügbar.';
        echo '</div>';
        return;
    }
    
    // Meeting-Details laden für Berechtigungs-Prüfung und Anzeige
    $stmt = $pdo->prepare("
        SELECT secretary_member_id, chairman_member_id, meeting_date
        FROM svmeetings
        WHERE meeting_id = ?
    ");
    $stmt->execute([$current_meeting_id]);
    $meeting_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Namen über Adapter holen (nicht über JOIN!)
    if ($meeting_data) {
        $chairman = get_member_by_id($pdo, $meeting_data['chairman_member_id']);
        $secretary = get_member_by_id($pdo, $meeting_data['secretary_member_id']);

        $meeting_data['chairman_first'] = $chairman['first_name'] ?? null;
        $meeting_data['chairman_last'] = $chairman['last_name'] ?? null;
        $meeting_data['secretary_first'] = $secretary['first_name'] ?? null;
        $meeting_data['secretary_last'] = $secretary['last_name'] ?? null;
    }
    
    // Berechtigung für vertrauliche TOPs prüfen
    $is_secretary = ($meeting_data && $meeting_data['secretary_member_id'] == $current_user['member_id']);
    $is_chairman = ($meeting_data && $meeting_data['chairman_member_id'] == $current_user['member_id']);
    
    $can_see_confidential = (
        $current_user['is_admin'] == 1 ||
        $current_user['is_confidential'] == 1 ||
        in_array($current_user['role'], ['vorstand', 'gf']) ||
        $is_secretary ||
        $is_chairman
    );
    
    // Eigene Bewertungen des Users laden
    $user_ratings = [];
    try {
        $stmt = $pdo->prepare("
            SELECT item_id, priority_rating, duration_estimate 
            FROM svagenda_comments 
            WHERE member_id = ? AND item_id IN (SELECT item_id FROM svagenda_items WHERE meeting_id = ?)
        ");
        $stmt->execute([$current_user['member_id'], $current_meeting_id]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_ratings[$row['item_id']] = [
                'priority' => $row['priority_rating'],
                'duration' => $row['duration_estimate']
            ];
        }
    } catch (PDOException $e) {
        error_log("Fehler beim Laden der Bewertungen: " . $e->getMessage());
        $user_ratings = [];
    }
    ?>
    
    <details open style="margin: 20px 0; border: 2px solid #2c5aa0; border-radius: 8px; padding: 10px; background: #f0f4f8;">
        <summary style="cursor: pointer; font-weight: bold; font-size: 1.1em; color: #2c5aa0; padding: 5px;">
            📋 Übersicht & Bewertungen
        </summary>
        
        <div style="margin-top: 15px;">
            <!-- Meeting-Leitung und Protokoll -->
            <div style="margin-bottom: 15px; padding: 10px; background: white; border-left: 4px solid #2c5aa0; border-radius: 4px;">
                <strong>Vorgesehene Sitzungsleitung:</strong>
                <?php echo $meeting_data['chairman_first'] ? htmlspecialchars($meeting_data['chairman_first'] . ' ' . $meeting_data['chairman_last']) : '<em>nicht festgelegt</em>'; ?>
                <br>
                <strong>Protokoll:</strong>
                <?php echo $meeting_data['secretary_first'] ? htmlspecialchars($meeting_data['secretary_first'] . ' ' . $meeting_data['secretary_last']) : '<em>nicht festgelegt</em>'; ?>
            </div>

            <p style="margin-bottom: 10px; color: #555;">
                <strong>Hinweis:</strong> Hier kannst du alle TOPs auf einen Blick sehen und deine Bewertungen (Priorität & geschätzte Dauer) eingeben.
            </p>
            
            <form method="POST" action="" style="margin-top: 15px;">
                <input type="hidden" name="save_ratings_overview" value="1">
                
                <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <thead>
                        <tr style="background: #2c5aa0; color: white;">
                            <th style="padding: 5px; text-align: left; border: 1px solid #ddd; width: 60px;">TOP</th>
                            <th style="padding: 5px; text-align: left; border: 1px solid #ddd;">Titel</th>
                            <th style="padding: 5px; text-align: center; border: 1px solid #ddd;">Ihre<br>Prio</th>
                            <th style="padding: 5px; text-align: center; border: 1px solid #ddd;">Ihre<br>Dauer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agenda_items as $item): ?>
                            <?php
							// Vertrauliche TOPs nur für berechtigte User anzeigen
							if ($item['is_confidential'] && !$can_see_confidential) {
								continue;
							}

							// TOP 999 nicht in Übersicht anzeigen
							if ($item['top_number'] == 999) {
								continue;
							}

                            $top_display = '';
                            if ($item['top_number'] == 0) $top_display = '';
                            elseif ($item['top_number'] == 99) $top_display = ' ';
                            else $top_display = $item['top_number'];
                            
                            // Kategorie-Daten aus globaler Definition holen
                            $cat_data = get_category_data($item['category']);
                            $category_display = $cat_data['icon'] . ' ' . $cat_data['label'];
                            
                            $user_prio = $user_ratings[$item['item_id']]['priority'] ?? '';
							if ($user_prio) $user_prio = intval($user_prio);
							$user_duration = $user_ratings[$item['item_id']]['duration'] ?? '';
							if ($user_duration) $user_duration = intval($user_duration);
                            ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center; font-weight: bold;">
								 <a href="#top-<?php echo $item['item_id']; ?>" style="color: #2c5aa0; text-decoration: none;">
                                 <?php echo htmlspecialchars($top_display); ?>
                                </a>
								</td>
                                <td style="padding: 8px; border: 1px solid #ddd;"> 
									<?php echo $category_display.': '; ?>
									<a href="#top-<?php echo $item['item_id']; ?>" style="color: #333; text-decoration: none;">
                                    <?php echo '<br><strong>'.htmlspecialchars($item['title']).'</strong>'; ?>
                                    </a>
                                    <?php if ($item['is_confidential']): ?>
                                        <span style="color: #d32f2f; font-weight: bold; margin-left: 5px;">🔒</span>
                                    <?php endif; ?>
									<?php if ($item['top_number'] <> 0 AND $item['top_number'] <> 99) {
									echo '<br>Ø Prio: ';
									echo $item['priority'] ? number_format($item['priority'], 1) : '-'; 
									echo '; ';}
									if ($item['top_number'] == 99) echo '<br>';
									if ($item['top_number'] <> 0) {echo 'Ø Dauer: ';
									echo $item['estimated_duration'] ? number_format($item['estimated_duration'],1) . ' Min' : '-';
									} ?>
                                
                                    
                                <td style="padding: 3px; border: 1px solid #ddd; text-align: center;">
								<?php if ($item['top_number'] <> 0 AND $item['top_number'] <> 99) { ?>
                                  <input type="number"
                                           name="priority_rating[<?php echo $item["item_id"]; ?>]"
                                           value="<?php echo htmlspecialchars($user_prio); ?>"
                                           min="1" max="9" step="1"
                                           style="width: 45px; padding: 4px; text-align: center; border: 1px solid #ccc; border-radius: 4px; background: <?php echo empty($user_prio) ? '#d4edda' : '#fff'; ?>;"
                                           placeholder="1-9">
								<?php }
                                if ($item['top_number'] <> 0) { ?>
                                  <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">
                                    <input type="number"
                                           name="duration_estimate[<?php echo $item["item_id"]; ?>]"
                                           value="<?php echo htmlspecialchars($user_duration); ?>"
                                           min="1" max="300"
                                           style="width: 40px; padding: 4px; text-align: center; border: 1px solid #ccc; border-radius: 4px; background: <?php echo empty($user_duration) ? '#d4edda' : '#fff'; ?>;">
								<?php	}
                                ?>
								</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 15px; text-align: right;">
                    <button type="submit" style="background: #2c5aa0; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; font-weight: bold;">
                        💾 Bewertungen speichern
                    </button>
                </div>
            </form>
            
            <div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-left: 4px solid #2c5aa0; border-radius: 4px;">
                <strong>Legende:</strong><br>
                <strong>Priorität:</strong> 1 (niedrig) bis 9 (sehr wichtig); <strong>Dauer:</strong> Geschätzte Diskussionsdauer in Minuten<br>
                <strong>Ø:</strong> Durchschnitt aller Teilnehmer-Bewertungen
            </div>
        </div>
    </details>
    
    <?php
}

/**
 * Rendert eine kompakte Übersicht (nur Links, keine Bewertungen)
 * Für Status: active, ended, protocol_ready, archived
 */
function render_simple_agenda_overview($agenda_items, $current_user = null, $current_meeting_id = null, $pdo = null) {
    // Parameter aus globalen Variablen holen wenn nicht übergeben
    if ($current_user === null) {
        global $current_user;
    }
    if ($current_meeting_id === null) {
        global $current_meeting_id;
    }
    if ($pdo === null) {
        global $pdo;
    }
    
    // Meeting-Details laden für Berechtigungs-Prüfung
    $is_secretary = false;
    $is_chairman = false;
    if ($pdo && $current_meeting_id) {
        $stmt = $pdo->prepare("SELECT secretary_member_id, chairman_member_id FROM svmeetings WHERE meeting_id = ?");
        $stmt->execute([$current_meeting_id]);
        $meeting_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($meeting_data && isset($current_user['member_id'])) {
            $is_secretary = ($meeting_data['secretary_member_id'] == $current_user['member_id']);
            $is_chairman = ($meeting_data['chairman_member_id'] == $current_user['member_id']);
        }
    }
    
    // Berechtigung für vertrauliche TOPs prüfen
    $can_see_confidential = (
        isset($current_user['is_admin']) && $current_user['is_admin'] == 1 ||
        isset($current_user['is_confidential']) && $current_user['is_confidential'] == 1 ||
        isset($current_user['role']) && in_array($current_user['role'], ['vorstand', 'gf']) ||
        $is_secretary ||
        $is_chairman
    );
    
    ?>
    <details open style="margin: 20px 0; border: 2px solid #2c5aa0; border-radius: 8px; padding: 10px; background: #f0f4f8;">
        <summary style="cursor: pointer; font-weight: bold; font-size: 1.1em; color: #2c5aa0; padding: 5px;">
            📋 Übersicht
        </summary>
        
        <div style="margin-top: 15px;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($agenda_items as $item): ?>
                    <?php
                    // Vertrauliche TOPs nur für berechtigte User anzeigen
                    if ($item['is_confidential'] && !$can_see_confidential) {
                        continue;
                    }

                    // TOP 999 nicht in Übersicht anzeigen
                    if ($item['top_number'] == 999) {
                        continue;
                    }

                    $top_display = '';
                    if ($item['top_number'] == 0) $top_display = 'TOP 0';
                    elseif ($item['top_number'] == 99) $top_display = 'Sonstiges';
                    else $top_display = 'TOP ' . $item['top_number'];
                    
                    // Kategorie-Icon aus globaler Definition holen
                    $cat_data = get_category_data($item['category']);
                    $icon = $cat_data['icon'];
                    ?>
                    <li style="margin: 8px 0; padding: 8px; background: white; border-radius: 4px; border-left: 4px solid #2c5aa0;">
                        <a href="#top-<?php echo $item['item_id']; ?>" style="color: #2c5aa0; text-decoration: none; display: flex; align-items: center; gap: 10px;">
                            <span style="font-weight: bold; min-width: 80px;"><?php echo $icon; ?> <?php echo htmlspecialchars($top_display); ?></span>
                            <span style="flex: 1;"><?php echo htmlspecialchars($item['title']); ?></span>
                            <?php if ($item['is_confidential']): ?>
                                <span style="color: #d32f2f; font-weight: bold;">🔒</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </details>
    <?php
}
?>
