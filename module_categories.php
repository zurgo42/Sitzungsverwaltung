<?php
/**
 * module_categories.php - Kategorie-Funktionen für TOPs
 * 
 * Dieses Modul enthält alle Funktionen und Definitionen für TOP-Kategorien
 * Einbindung: require_once 'module_categories.php';
 */

// Kategorie-Definitionen mit Icons (zentrale Definition für alle Dateien)
$GLOBALS['category_labels'] = [
    'information' => ['icon' => 'ℹ️', 'label' => 'Information', 'color' => '#e3f2fd', 'text_color' => '#1976d2'],
    'klaerung' => ['icon' => '❓', 'label' => 'Klärung', 'color' => '#fff3e0', 'text_color' => '#f57c00'],
    'diskussion' => ['icon' => '💬', 'label' => 'Diskussion', 'color' => '#e8f5e9', 'text_color' => '#388e3c'],
    'aussprache' => ['icon' => '💬', 'label' => 'Aussprache', 'color' => '#e8f5e9', 'text_color' => '#388e3c'],
    'antrag_beschluss' => ['icon' => '📜', 'label' => 'Antrag/Beschluss', 'color' => '#fffbf0', 'text_color' => '#856404'],
    'wahl' => ['icon' => '🗳️', 'label' => 'Wahl', 'color' => '#f3e5f5', 'text_color' => '#7b1fa2'],
    'bericht' => ['icon' => '📊', 'label' => 'Bericht', 'color' => '#e0f2f1', 'text_color' => '#00796b'],
    'sonstiges' => ['icon' => '📌', 'label' => 'Sonstiges', 'color' => '#f5f5f5', 'text_color' => '#616161']
];

/**
 * Gibt Kategorie-Daten zurück
 */
function get_category_data($category) {
    $categories = $GLOBALS['category_labels'];
    return $categories[$category] ?? $categories['information'];
}

/**
 * Rendert ein Kategorie-Badge
 */
function render_category_badge($category) {
    $cat = get_category_data($category);
    echo '<span class="badge" style="background: ' . $cat['color'] . '; color: ' . $cat['text_color'] . '; font-size: 11px; padding: 3px 10px; border-radius: 12px; margin-left: 8px;">';
    echo $cat['icon'] . ' ' . $cat['label'];
    echo '</span>';
}

/**
 * Rendert ein Kategorie-Auswahlfeld
 */
function render_category_select($name, $id, $selected = 'information', $onchange = '') {
    $categories = $GLOBALS['category_labels'];
    echo '<select name="' . htmlspecialchars($name) . '" id="' . htmlspecialchars($id) . '"';
    if ($onchange) {
        echo ' onchange="' . htmlspecialchars($onchange) . '"';
    }
    echo ' style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
    
    foreach ($categories as $key => $cat) {
        $selected_attr = ($key === $selected) ? ' selected' : '';
        echo '<option value="' . $key . '"' . $selected_attr . '>';
        echo $cat['icon'] . ' ' . $cat['label'];
        echo '</option>';
    }
    
    echo '</select>';
}

/**
 * JavaScript für Kategorie-Funktionen
 */
function render_category_javascript() {
    ?>
    <script>
    // Antragstext-Feld ein/ausblenden
    function toggleProposalField(prefix) {
        const select = document.getElementById(prefix + '_category');
        const proposalDiv = document.getElementById(prefix + '_proposal');

        if (select && proposalDiv) {
            if (select.value === 'antrag_beschluss') {
                proposalDiv.style.display = 'block';
            } else {
                proposalDiv.style.display = 'none';
            }
        }
    }

    // Vollständiges Antragsformular ein/ausblenden (für Meeting-Beschlüsse)
    function toggleProposalForm() {
        const checkbox = document.getElementById('create_proposal_checkbox');
        const formFields = document.getElementById('proposal_form_fields');

        if (checkbox && formFields) {
            if (checkbox.checked) {
                formFields.style.display = 'block';
            } else {
                formFields.style.display = 'none';
            }
        }
    }

    // Bei Seitenlade alle Felder prüfen
    document.addEventListener('DOMContentLoaded', function() {
        // Neues TOP Formular
        toggleProposalField('new_top');

        // Alle Edit-Formulare
        document.querySelectorAll('[id$="_category"]').forEach(function(select) {
            const match = select.id.match(/edit_(\d+)_category/);
            if (match) {
                toggleProposalField('edit_' + match[1] + '_top');
            }
        });
    });
    </script>
    <?php
}
?>
