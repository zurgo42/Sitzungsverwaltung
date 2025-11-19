<?php
/**
 * tab_opinion.php - Meinungsbild-Tool UI
 * Erstellt: 2025-11-18
 */

require_once __DIR__ . '/opinion_functions.php';

// Aktuellen User holen
$current_user = null;
if (isset($_SESSION['member_id'])) {
    $current_user = get_member_by_id($pdo, $_SESSION['member_id']);
}

// View ermitteln
$view = $_GET['view'] ?? 'list';
$poll_id = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : null;
$access_token = $_GET['token'] ?? null;

// Bei Token-Zugriff
if ($access_token && !$poll_id) {
    $poll = get_poll_by_token($pdo, $access_token);
    if ($poll) {
        $poll_id = $poll['poll_id'];
        $view = 'participate';
    }
}
?>

<style>
.opinion-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.opinion-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.poll-meta {
    color: #666;
    font-size: 14px;
    margin-top: 10px;
}

.poll-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.status-active {
    background: #4CAF50;
    color: white;
}

.status-ended {
    background: #999;
    color: white;
}

/* Button-Styles */
.btn-primary, .btn-secondary {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.btn-primary {
    background: #2196F3;
    color: white;
}

.btn-primary:hover {
    background: #1976D2;
    box-shadow: 0 2px 8px rgba(33,150,243,0.3);
}

.btn-secondary {
    background: #f0f0f0;
    color: #333;
    border: 1px solid #ddd;
}

.btn-secondary:hover {
    background: #e0e0e0;
    border-color: #999;
}

.option-list {
    list-style: none;
    padding: 0;
}

.option-item {
    padding: 12px;
    margin: 8px 0;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.option-item:hover {
    border-color: #2196F3;
    background: #f0f8ff;
}

.option-item input[type="checkbox"],
.option-item input[type="radio"] {
    margin-right: 10px;
}

.result-bar {
    background: #e0e0e0;
    height: 30px;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
    margin: 10px 0;
}

.result-bar-fill {
    background: linear-gradient(90deg, #2196F3, #1976D2);
    height: 100%;
    transition: width 0.5s ease;
    display: flex;
    align-items: center;
    padding: 0 10px;
    color: white;
    font-weight: bold;
    font-size: 14px;
}

.result-bar-label {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: bold;
    color: #333;
}

.template-selector {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.template-card {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s;
}

.template-card:hover {
    border-color: #2196F3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.template-card.selected {
    border-color: #4CAF50;
    background: #f0fff0;
}

.template-card input[type="radio"] {
    display: none;
}

.custom-options-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin: 15px 0;
}

.access-link-box {
    background: #fffacd;
    border: 2px solid #ffd700;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}

.access-link-box input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
}

.btn-copy {
    background: #2196F3;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 8px;
}

.response-list {
    margin-top: 20px;
}

.response-item {
    background: #f9f9f9;
    border-left: 4px solid #2196F3;
    padding: 15px;
    margin: 10px 0;
    border-radius: 4px;
}

.response-meta {
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
}

.accordion {
    background: #f1f1f1;
    cursor: pointer;
    padding: 15px;
    border: none;
    text-align: left;
    font-size: 16px;
    font-weight: bold;
    width: 100%;
    transition: 0.3s;
    border-radius: 6px;
    margin-bottom: 10px;
}

.accordion:hover, .accordion.active {
    background: #ddd;
}

.accordion-content {
    display: none;
    padding: 15px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 0 0 6px 6px;
    margin-bottom: 15px;
}

/* Responsive: Smartphone-Darstellung verbessern */
@media (max-width: 767px) {
    .opinion-container {
        padding: 10px;
    }

    .opinion-card {
        padding: 15px;
    }

    .option-item {
        padding: 10px;
        margin: 6px 0;
    }

    .option-item label {
        font-size: 14px;
        word-wrap: break-word;
    }

    .btn-primary, .btn-secondary {
        width: 100%;
        text-align: center;
        padding: 12px 15px;
        margin-bottom: 10px;
        box-sizing: border-box;
    }

    .poll-meta {
        font-size: 12px;
    }

    .poll-meta span {
        display: block;
        margin: 3px 0 !important;
    }
}
</style>

<div class="opinion-container">
    <h2>📊 Meinungsbild</h2>

    <?php
    // Erfolgs-/Fehlermeldungen
    if (isset($_SESSION['success'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif;

    if (isset($_SESSION['error'])): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif;

    // VIEW-ROUTING
    if ($view === 'list') {
        include __DIR__ . '/opinion_views/list.php';
    } elseif ($view === 'create') {
        include __DIR__ . '/opinion_views/create.php';
    } elseif ($view === 'detail' && $poll_id) {
        include __DIR__ . '/opinion_views/detail.php';
    } elseif ($view === 'participate' && $poll_id) {
        include __DIR__ . '/opinion_views/participate.php';
    } elseif ($view === 'results' && $poll_id) {
        include __DIR__ . '/opinion_views/results.php';
    } else {
        echo "<p>Ansicht nicht gefunden.</p>";
    }
    ?>
</div>

<script>
// Accordion Toggle
document.addEventListener('DOMContentLoaded', function() {
    const accordions = document.querySelectorAll('.accordion');
    accordions.forEach(acc => {
        acc.addEventListener('click', function() {
            this.classList.toggle('active');
            const content = this.nextElementSibling;
            if (content.style.display === 'block') {
                content.style.display = 'none';
            } else {
                content.style.display = 'block';
            }
        });
    });
});

// Link kopieren
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Link wurde in die Zwischenablage kopiert!');
    });
}

// Template Auswahl
function selectTemplate(templateId) {
    document.querySelectorAll('.template-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    document.getElementById('template_id').value = templateId;

    // Custom Options ausblenden wenn Template gewählt
    const customSection = document.getElementById('custom-options-section');
    if (templateId && templateId != 11) {
        customSection.style.display = 'none';
    } else {
        customSection.style.display = 'block';
    }
}
</script>
