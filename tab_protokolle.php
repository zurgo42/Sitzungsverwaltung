<?php
/**
 * tab_protokolle.php - Protokoll-Darstellung
 * Timestamp: 2025-10-22 21:00 MEZ
 * Zeigt durchsuchbare Protokolle an
 */

// Logik einbinden
require_once 'process_protokoll.php';

// Benachrichtigungsmodul laden
require_once 'module_notifications.php';

$stichwort = isset($_GET['stichwort']) ? trim($_GET['stichwort']) : '';
$meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : 0;
$confidential = (in_array($current_user['role'], ['vorstand', 'gf', 'assistenz']));

// Einzelnes Protokoll anzeigen
if ($meeting_id > 0) {
    $stmt = $pdo->prepare("SELECT protokoll, prot_intern, meeting_name, meeting_date FROM svmeetings WHERE meeting_id = ? AND protokoll IS NOT NULL");
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();

    if ($meeting) {
        echo '<div class="protocol-view">';

        // Benachrichtigungen anzeigen
        render_user_notifications($pdo, $current_user['member_id']);

        echo '<h2>📋 Protokoll anzeigen</h2>';

        echo '<p><a href="?tab=protokolle" class="btn-secondary" style="text-decoration: none; display: inline-block; padding: 8px 16px;">&larr; Zurück zur Übersicht</a></p>';
       
        if (!empty($stichwort)) {
            echo '<p class="search-info">Suche nach: <span class="highlight">' . htmlspecialchars($stichwort) . '</span></p>';
            echo '<table width="100%" class="protocol-table">';
            echo highlightWords(str_replace(array("&gt;","&lt;"),array(">","<"),$meeting['protokoll']), $stichwort);
			echo '</table>';
			if ($confidential AND strlen($meeting['prot_intern'])>5) {
			echo '<br><br><h3 style="color:red">Interner Teil</h3><table width="100%" class="protocol-table">';
            echo highlightWords(str_replace(array("&gt;","&lt;"),array(">","<"),$meeting['prot_intern']), $stichwort);
            echo '</table>';}
        } else {
            echo '<table width="100%" class="protocol-table">';
            echo str_replace(array("&gt;","&lt;"),array(">","<"),$meeting['protokoll']);
            echo '</table>';
 			if ($confidential AND strlen($meeting['prot_intern'])>5) {
            echo '<br><br><h3 style="color:red">Interner Teil</h3><table width="100%" class="protocol-table">';
            echo str_replace(array("&gt;","&lt;"),array(">","<"),$meeting['prot_intern']);
            echo '</table>';
            echo '</table>';}
       }
        
        echo '</div>';
    } else {
        echo '<div class="error-message">Protokoll nicht gefunden oder noch nicht genehmigt.</div>';
    }
    
} else {
    // Protokoll-Übersicht mit Suchfunktion
    ?>

    <!-- BENACHRICHTIGUNGEN -->
    <?php render_user_notifications($pdo, $current_user['member_id']); ?>

    <h2>📋 Protokolle</h2>
    
    <div class="search-box">
        <form method="GET" action="">
            <input type="hidden" name="tab" value="protokolle">
            <div class="search-input-group">
                <input type="text" name="stichwort" value="<?php echo htmlspecialchars($stichwort); ?>" 
                       placeholder="Stichwort suchen..." class="search-input">
                <button type="submit" class="btn-primary">🔍 Suchen</button>
                <?php if (!empty($stichwort)): ?>
                    <a href="?tab=protokolle" class="btn-secondary">✕ Zurücksetzen</a>
                <?php endif; 
            echo '</div>';
 //				if ($confidential) echo "Es wird nur in den öffentlichen Protokollen gesucht!";
				?>
       </form>
    </div>
    
    <?php
    // Protokolle laden
    if (!empty($stichwort)) {
        // Suche in Protokollen
        $stmt = $pdo->prepare("
            SELECT meeting_id, meeting_name, meeting_date, protokoll
            FROM svmeetings 
            WHERE protokoll IS NOT NULL 
            AND (protokoll LIKE ? OR meeting_name LIKE ?)
            ORDER BY meeting_date DESC
        ");
        $search_term = '%' . $stichwort . '%';
        $stmt->execute([$search_term, $search_term]);
        $protocols = $stmt->fetchAll();
        
        if (!empty($protocols)) {
            echo '<p class="search-results-count">' . count($protocols) . ' Protokoll(e) gefunden</p>';
        }
    } else {
        // Alle Protokolle
        $stmt = $pdo->query("
            SELECT meeting_id, meeting_name, meeting_date 
            FROM svmeetings 
            WHERE protokoll IS NOT NULL 
            ORDER BY meeting_date DESC
        ");
        $protocols = $stmt->fetchAll();
    }
    
    if (empty($protocols)) {
        echo '<div class="info-box">';
        if (!empty($stichwort)) {
            echo 'Keine Protokolle mit dem Stichwort "' . htmlspecialchars($stichwort) . '" gefunden.';
        } else {
            echo 'Noch keine genehmigten Protokolle vorhanden.';
        }
        echo '</div>';
    } else {
        echo '<table class="admin-table protocol-list">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Datum</th>';
        echo '<th>Meeting</th>';
        echo '<th>Aktion</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($protocols as $protocol) {
            echo '<tr>';
            echo '<td>' . date('d.m.Y H:i', strtotime($protocol['meeting_date'])) . '</td>';
            echo '<td>';
            
            if (!empty($stichwort)) {
                // Zeige Vorschau mit Highlight
                echo '<strong>';
                if (!empty($protocol['meeting_name'])) {
                    echo highlightWords(htmlspecialchars($protocol['meeting_name']), $stichwort);
                } else {
                    echo 'Meeting';
                }
                echo '</strong><br>';
                
                // Ersten Treffer als Vorschau zeigen
                $preview = strip_tags($protocol['protokoll']);
                $preview = html_entity_decode($preview);
                
                // Finde Position des ersten Treffers
                $pos = stripos($preview, $stichwort);
                if ($pos !== false) {
                    $start = max(0, $pos - 100);
                    $length = 200;
                    $snippet = substr($preview, $start, $length);
                    if ($start > 0) $snippet = '...' . $snippet;
                    if ($start + $length < strlen($preview)) $snippet .= '...';
                    
                    echo '<small class="protocol-preview">';
                    echo highlightWords(htmlspecialchars($snippet), $stichwort);
                    echo '</small>';
                }
            } else {
                echo '<strong>';
                echo !empty($protocol['meeting_name']) ? htmlspecialchars($protocol['meeting_name']) : 'Meeting';
                echo '</strong>';
            }
            
            echo '</td>';
            echo '<td>';
            echo '<a href="?tab=protokolle&meeting_id=' . $protocol['meeting_id'];
            if (!empty($stichwort)) {
                echo '&stichwort=' . urlencode($stichwort);
            }
            echo '" class="btn-view">📄 Anzeigen</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
}
?>
