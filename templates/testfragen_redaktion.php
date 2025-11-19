<?php
/**
 * Redaktionsansicht für Testfragen
 * Zeigt alle eingereichten Fragen
 */

// Alle Testfragen laden
$stmt = $pdo->query("
    SELECT t.*, m.first_name, m.last_name
    FROM testfragen t
    LEFT JOIN berechtigte m ON t.member_id = m.member_id
    ORDER BY t.datum DESC
");
$fragen = $stmt->fetchAll();

// Inhalt-Labels
$inhaltLabels = [
    1 => 'Verbal/Sprachlich',
    2 => 'Numerisch/Rechnerisch',
    3 => 'Figural/Räumlich',
    4 => 'Anderes'
];

// Schwierigkeits-Labels
$schwerLabels = [
    1 => 'Sehr niedrig',
    2 => 'Eher niedrig',
    3 => 'Mittel',
    4 => 'Eher hoch',
    5 => 'Sehr hoch'
];
?>

<div class="redaktion-container">
    <p class="mb-20"><strong>Anzahl Einreichungen:</strong> <?= count($fragen) ?></p>

    <?php if (empty($fragen)): ?>
        <p class="text-muted">Noch keine Einreichungen vorhanden.</p>
    <?php else: ?>
        <div class="fragen-list">
            <?php foreach ($fragen as $frage): ?>
                <div class="frage-card">
                    <div class="frage-header">
                        <h4>Frage #<?= $frage['id'] ?>
                            <?php if ($frage['is_figural']): ?>
                                <span class="badge badge-info">Figural</span>
                            <?php endif; ?>
                        </h4>
                        <div class="frage-meta">
                            <?php if ($frage['first_name']): ?>
                                <span>von <?= htmlspecialchars($frage['first_name'] . ' ' . $frage['last_name']) ?></span>
                            <?php endif; ?>
                            <span><?= date('d.m.Y', strtotime($frage['datum'])) ?></span>
                        </div>
                    </div>

                    <div class="frage-content">
                        <div class="aufgabe-text">
                            <strong>Aufgabe:</strong>
                            <p><?= nl2br(htmlspecialchars($frage['aufgabe'])) ?></p>
                            <?php if ($frage['file0']): ?>
                                <div class="file-preview">
                                    <img src="uploads/testfragen/<?= htmlspecialchars($frage['file0']) ?>"
                                         alt="Aufgabenstellung" class="preview-image">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="antworten-grid">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="antwort-item <?= $i == $frage['richtig'] ? 'richtig' : '' ?>">
                                    <strong><?= $i ?>:</strong>
                                    <?php if ($frage["file$i"]): ?>
                                        <img src="uploads/testfragen/<?= htmlspecialchars($frage["file$i"]) ?>"
                                             alt="Antwort <?= $i ?>" class="preview-image-small">
                                    <?php else: ?>
                                        <?= htmlspecialchars($frage["antwort$i"]) ?>
                                    <?php endif; ?>
                                    <?php if ($i == $frage['richtig']): ?>
                                        <span class="richtig-marker">✓ RICHTIG</span>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="frage-details">
                            <div class="detail-item">
                                <strong>Regel:</strong>
                                <p><?= nl2br(htmlspecialchars($frage['regel'])) ?></p>
                            </div>

                            <div class="detail-row">
                                <div class="detail-item">
                                    <strong>Inhalt:</strong>
                                    <?= $inhaltLabels[$frage['inhalt']] ?? 'Unbekannt' ?>
                                    <?php if ($frage['inhalt'] == 4 && $frage['tinhalt']): ?>
                                        (<?= htmlspecialchars($frage['tinhalt']) ?>)
                                    <?php endif; ?>
                                    <?php if ($frage['inhaltw'] > 0): ?>
                                        + <?= $inhaltLabels[$frage['inhaltw']] ?? '' ?>
                                        <?php if ($frage['inhaltw'] == 4 && $frage['tinhaltw']): ?>
                                            (<?= htmlspecialchars($frage['tinhaltw']) ?>)
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="detail-item">
                                    <strong>Schwierigkeit:</strong>
                                    <?= $schwerLabels[$frage['schwer']] ?? 'Unbekannt' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Redaktions-spezifische Styles */
.redaktion-container {
    margin-top: 30px;
}

.fragen-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.frage-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
}

.frage-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid #dee2e6;
}

.frage-header h4 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.frage-meta {
    font-size: 0.875rem;
    color: #6c757d;
    display: flex;
    gap: 15px;
}

.badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-info {
    background: #17a2b8;
    color: white;
}

.aufgabe-text {
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 6px;
}

.aufgabe-text p {
    margin-top: 10px;
    color: #333;
}

.file-preview {
    margin-top: 10px;
}

.preview-image {
    max-width: 100%;
    max-height: 400px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.preview-image-small {
    max-width: 150px;
    max-height: 100px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.antworten-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.antwort-item {
    padding: 10px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.antwort-item.richtig {
    background: #d4edda;
    border-color: #28a745;
}

.richtig-marker {
    display: block;
    color: #28a745;
    font-weight: 600;
    font-size: 0.875rem;
    margin-top: 5px;
}

.frage-details {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.detail-item {
    margin-bottom: 10px;
}

.detail-item strong {
    display: block;
    margin-bottom: 5px;
    color: #495057;
}

.detail-item p {
    margin: 0;
    color: #6c757d;
}

.detail-row {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .frage-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .antworten-grid {
        grid-template-columns: 1fr;
    }

    .detail-row {
        flex-direction: column;
    }
}
</style>
