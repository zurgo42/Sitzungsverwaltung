<?php
/**
 * tab_documents.php - Dokumentenverwaltung Tab
 * Modernes UI für Dokumentenverwaltung
 */

if (!isset($_SESSION['member_id'])) {
    echo '<div class="alert alert-warning">Bitte melden Sie sich an, um Dokumente zu sehen.</div>';
    return;
}

require_once __DIR__ . '/documents_functions.php';

$current_user = get_member_by_id($pdo, $_SESSION['member_id']);
$is_admin = is_admin_user($current_user);
$member_access_level = get_member_access_level($current_user);

// View bestimmen
$view = $_GET['view'] ?? 'list';
$document_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Success/Error Messages
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['success']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['error']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    unset($_SESSION['error']);
}

// ============================================
// VIEW: LISTE
// ============================================

if ($view === 'list') {
    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>📁 Dokumentenverwaltung</h2>
                    <?php if ($is_admin): ?>
                        <button type="button" onclick="window.location.href='?tab=documents&view=upload'" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Dokument hochladen
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Info-Box -->
                <div class="alert alert-info mb-3">
                    <strong>📚 Willkommen in der Dokumentensammlung!</strong><br>
                    Hier finden Sie alle wichtigen Vereinsdokumente in der jeweils aktuellen Version.
                </div>

                <!-- Filter & Suche als Akkordion -->
                <div class="accordion mb-4" id="filterAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                                <i class="bi bi-funnel me-2"></i> Filter & Suche
                            </button>
                        </h2>
                        <div id="filterCollapse" class="accordion-collapse collapse" data-bs-parent="#filterAccordion">
                            <div class="accordion-body">
                                <form method="GET" id="filterForm">
                                    <input type="hidden" name="tab" value="documents">

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Suche</label>
                                            <input type="text" name="search" class="form-control"
                                                   placeholder="Titel, Beschreibung, Stichworte..."
                                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Kategorie</label>
                                            <select name="category" class="form-select">
                                                <option value="">Alle Kategorien</option>
                                                <?php
                                                foreach (get_document_categories() as $key => $label) {
                                                    $selected = (isset($_GET['category']) && $_GET['category'] === $key) ? 'selected' : '';
                                                    echo "<option value='$key' $selected>$label</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Sortierung</label>
                                            <select name="sort" class="form-select">
                                                <option value="date_desc" <?= (!isset($_GET['sort']) || $_GET['sort'] === 'date_desc') ? 'selected' : '' ?>>
                                                    Neueste zuerst
                                                </option>
                                                <option value="date_asc" <?= (isset($_GET['sort']) && $_GET['sort'] === 'date_asc') ? 'selected' : '' ?>>
                                                    Älteste zuerst
                                                </option>
                                                <option value="title" <?= (isset($_GET['sort']) && $_GET['sort'] === 'title') ? 'selected' : '' ?>>
                                                    Alphabetisch (Titel)
                                                </option>
                                                <option value="category" <?= (isset($_GET['sort']) && $_GET['sort'] === 'category') ? 'selected' : '' ?>>
                                                    Nach Kategorie
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-search"></i> Filtern
                                            </button>
                                        </div>

                                        <?php if ($is_admin && $member_access_level >= 15): ?>
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="show_all" value="1"
                                                       id="showAll" <?= isset($_GET['show_all']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="showAll">
                                                    Auch versteckte/archivierte Dokumente anzeigen
                                                </label>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                // Filter zusammenstellen
                $filters = [
                    'search' => $_GET['search'] ?? '',
                    'category' => $_GET['category'] ?? '',
                    'sort' => $_GET['sort'] ?? 'date_desc'
                ];

                // Admin kann auch versteckte sehen
                if ($is_admin && isset($_GET['show_all'])) {
                    $filters['status'] = null; // Alle Status
                } else {
                    $filters['status'] = 'active'; // Nur aktive
                }

                // Dokumente laden
                $documents = get_documents($pdo, $filters, $member_access_level);

                if (empty($documents)) {
                    echo '<div class="alert alert-warning">Keine Dokumente gefunden.</div>';
                } else {
                    // Tabellen-Ansicht (Desktop) / Card-Ansicht (Mobile)
                    ?>

                    <!-- Desktop: Tabelle -->
                    <div class="d-none d-md-block">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Titel</th>
                                        <th>Kategorie</th>
                                        <th>Version</th>
                                        <th>Typ</th>
                                        <th>Größe</th>
                                        <th>Datum</th>
                                        <th style="width: 200px;">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc):
                                        $categories = get_document_categories();
                                        $cat_label = $categories[$doc['category']] ?? $doc['category'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($doc['title']) ?></strong>
                                            <?php if ($doc['status'] !== 'active'): ?>
                                                <span class="badge bg-secondary ms-1"><?= ucfirst($doc['status']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($doc['access_level'] > 0): ?>
                                                <span class="badge bg-warning text-dark ms-1">Eingeschränkt</span>
                                            <?php endif; ?>
                                            <?php if ($doc['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(mb_substr($doc['description'], 0, 80)) ?><?= mb_strlen($doc['description']) > 80 ? '...' : '' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($cat_label) ?></span></td>
                                        <td><?= $doc['version'] ? '<span class="badge bg-secondary">v' . htmlspecialchars($doc['version']) . '</span>' : '-' ?></td>
                                        <td><?= strtoupper($doc['filetype']) ?></td>
                                        <td><?= format_filesize($doc['filesize']) ?></td>
                                        <td><?= date('d.m.Y', strtotime($doc['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if (!empty($doc['short_url'])): ?>
                                                    <a href="<?= htmlspecialchars($doc['short_url']) ?>" class="btn btn-primary" target="_blank" title="Öffnen">
                                                        <i class="bi bi-link-45deg"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="download_document.php?id=<?= $doc['document_id'] ?>" class="btn btn-primary" target="_blank" title="Herunterladen">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($is_admin): ?>
                                                    <a href="?tab=documents&view=edit&id=<?= $doc['document_id'] ?>" class="btn btn-outline-secondary" title="Bearbeiten">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile: Cards -->
                    <div class="d-md-none">
                        <?php foreach ($documents as $doc):
                            $categories = get_document_categories();
                            $cat_label = $categories[$doc['category']] ?? $doc['category'];
                        ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?= htmlspecialchars($doc['title']) ?>
                                    <?php if ($doc['status'] !== 'active'): ?>
                                        <span class="badge bg-secondary ms-1"><?= ucfirst($doc['status']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($doc['access_level'] > 0): ?>
                                        <span class="badge bg-warning text-dark ms-1">Eingeschränkt</span>
                                    <?php endif; ?>
                                </h5>

                                <p class="card-text text-muted small mb-2">
                                    <span class="badge bg-info"><?= htmlspecialchars($cat_label) ?></span>
                                    <?php if ($doc['version']): ?>
                                        <span class="badge bg-secondary">v<?= htmlspecialchars($doc['version']) ?></span>
                                    <?php endif; ?>
                                </p>

                                <?php if ($doc['description']): ?>
                                    <p class="card-text"><?= nl2br(htmlspecialchars(mb_substr($doc['description'], 0, 120))) ?>
                                        <?= mb_strlen($doc['description']) > 120 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>

                                <p class="card-text small text-muted">
                                    <i class="bi bi-file-earmark"></i> <?= strtoupper($doc['filetype']) ?>
                                    • <?= format_filesize($doc['filesize']) ?>
                                    • <?= date('d.m.Y', strtotime($doc['created_at'])) ?>
                                </p>

                                <div class="d-grid gap-2">
                                    <?php if (!empty($doc['short_url'])): ?>
                                        <a href="<?= htmlspecialchars($doc['short_url']) ?>" class="btn btn-primary" target="_blank">
                                            <i class="bi bi-link-45deg"></i> Öffnen
                                        </a>
                                    <?php else: ?>
                                        <a href="download_document.php?id=<?= $doc['document_id'] ?>" class="btn btn-primary" target="_blank">
                                            <i class="bi bi-download"></i> Herunterladen
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($is_admin): ?>
                                        <a href="?tab=documents&view=edit&id=<?= $doc['document_id'] ?>" class="btn btn-outline-secondary">
                                            <i class="bi bi-pencil"></i> Bearbeiten
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php
                }
                ?>
            </div>
        </div>
    </div>

    <?php
} // Ende List-View

// ============================================
// VIEW: UPLOAD
// ============================================

elseif ($view === 'upload' && $is_admin) {
    ?>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3>📤 Dokument hochladen</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="process_documents.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload">

                            <div class="mb-3">
                                <label class="form-label">Datei auswählen *</label>
                                <input type="file" name="document_file" class="form-control" required
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.rtf,.txt,.jpg,.jpeg,.png">
                                <div class="form-text">
                                    Erlaubte Dateitypen: PDF, DOC, DOCX, XLS, XLSX, RTF, TXT, JPG, PNG
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Titel *</label>
                                <input type="text" name="title" class="form-control" required
                                       placeholder="Aussagekräftiger Titel">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kategorie *</label>
                                <select name="category" class="form-select" required>
                                    <?php
                                    foreach (get_document_categories() as $key => $label) {
                                        echo "<option value='$key'>$label</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Beschreibung</label>
                                <textarea name="description" class="form-control" rows="3"
                                          placeholder="Ausführliche Beschreibung des Dokuments"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Version</label>
                                    <input type="text" name="version" class="form-control"
                                           placeholder="z.B. 2025, v1.2">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zugriffslevel</label>
                                    <select name="access_level" class="form-select">
                                        <option value="0">Alle Mitglieder</option>
                                        <option value="12">Ab Projektleitung</option>
                                        <option value="15">Ab Ressortleitung</option>
                                        <option value="18">Ab Assistenz</option>
                                        <option value="19">Nur Vorstand</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Stichworte</label>
                                <input type="text" name="keywords" class="form-control"
                                       placeholder="Komma-getrennte Stichworte für die Suche">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kurz-URL</label>
                                <input type="text" name="short_url" class="form-control"
                                       placeholder="https://link.mensa.de/xyz">
                                <div class="form-text">
                                    Optional: Eine kurze, einprägsame URL für dieses Dokument
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" onclick="window.location.href='?tab=documents'" class="btn btn-secondary">
                                    Abbrechen
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Hochladen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
} // Ende Upload-View

// ============================================
// VIEW: EDIT
// ============================================

elseif ($view === 'edit' && $is_admin && $document_id) {
    $doc = get_document_by_id($pdo, $document_id);

    if (!$doc) {
        echo '<div class="alert alert-danger">Dokument nicht gefunden</div>';
    } else {
        ?>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3>✏️ Dokument bearbeiten</h3>
                            <span class="badge bg-secondary"><?= htmlspecialchars($doc['filename']) ?></span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="process_documents.php">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="document_id" value="<?= $doc['document_id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label">Titel *</label>
                                    <input type="text" name="title" class="form-control" required
                                           value="<?= htmlspecialchars($doc['title']) ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Kategorie *</label>
                                    <select name="category" class="form-select" required>
                                        <?php
                                        foreach (get_document_categories() as $key => $label) {
                                            $selected = ($doc['category'] === $key) ? 'selected' : '';
                                            echo "<option value='$key' $selected>$label</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Beschreibung</label>
                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($doc['description']) ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Version</label>
                                        <input type="text" name="version" class="form-control"
                                               value="<?= htmlspecialchars($doc['version']) ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Zugriffslevel</label>
                                        <select name="access_level" class="form-select">
                                            <option value="0" <?= $doc['access_level'] == 0 ? 'selected' : '' ?>>Alle Mitglieder</option>
                                            <option value="12" <?= $doc['access_level'] == 12 ? 'selected' : '' ?>>Ab Projektleitung</option>
                                            <option value="15" <?= $doc['access_level'] == 15 ? 'selected' : '' ?>>Ab Ressortleitung</option>
                                            <option value="18" <?= $doc['access_level'] == 18 ? 'selected' : '' ?>>Ab Assistenz</option>
                                            <option value="19" <?= $doc['access_level'] == 19 ? 'selected' : '' ?>>Nur Vorstand</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Stichworte</label>
                                    <input type="text" name="keywords" class="form-control"
                                           value="<?= htmlspecialchars($doc['keywords']) ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Kurz-URL</label>
                                    <input type="text" name="short_url" class="form-control"
                                           value="<?= htmlspecialchars($doc['short_url']) ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Admin-Notizen</label>
                                    <textarea name="admin_notes" class="form-control" rows="2"><?= htmlspecialchars($doc['admin_notes']) ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?= $doc['status'] === 'active' ? 'selected' : '' ?>>Aktiv</option>
                                        <option value="archived" <?= $doc['status'] === 'archived' ? 'selected' : '' ?>>Archiviert</option>
                                        <option value="hidden" <?= $doc['status'] === 'hidden' ? 'selected' : '' ?>>Versteckt</option>
                                        <option value="outdated" <?= $doc['status'] === 'outdated' ? 'selected' : '' ?>>Veraltet</option>
                                    </select>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="button" onclick="window.location.href='?tab=documents'" class="btn btn-secondary">
                                            Zurück
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Speichern
                                        </button>
                                    </div>

                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="bi bi-trash"></i> Löschen
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Datei-Info -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>📊 Datei-Informationen</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Dateiname:</dt>
                                <dd class="col-sm-8"><code><?= htmlspecialchars($doc['filename']) ?></code></dd>

                                <dt class="col-sm-4">Originaldatei:</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($doc['original_filename']) ?></dd>

                                <dt class="col-sm-4">Dateigröße:</dt>
                                <dd class="col-sm-8"><?= format_filesize($doc['filesize']) ?></dd>

                                <dt class="col-sm-4">Dateityp:</dt>
                                <dd class="col-sm-8"><?= strtoupper($doc['filetype']) ?></dd>

                                <dt class="col-sm-4">Hochgeladen von:</dt>
                                <dd class="col-sm-8"><?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?></dd>

                                <dt class="col-sm-4">Hochgeladen am:</dt>
                                <dd class="col-sm-8"><?= date('d.m.Y H:i', strtotime($doc['created_at'])) ?> Uhr</dd>

                                <?php if ($doc['updated_at']): ?>
                                <dt class="col-sm-4">Aktualisiert am:</dt>
                                <dd class="col-sm-8"><?= date('d.m.Y H:i', strtotime($doc['updated_at'])) ?> Uhr</dd>
                                <?php endif; ?>

                                <dt class="col-sm-4">Downloads:</dt>
                                <dd class="col-sm-8"><?= get_document_download_stats($pdo, $doc['document_id']) ?></dd>
                            </dl>

                            <a href="download_document.php?id=<?= $doc['document_id'] ?>" class="btn btn-primary" target="_blank">
                                <i class="bi bi-download"></i> Dokument herunterladen
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Dokument löschen?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="process_documents.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="document_id" value="<?= $doc['document_id'] ?>">

                            <p><strong>Achtung:</strong> Diese Aktion kann nicht rückgängig gemacht werden!</p>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="permanent" value="1" id="permanentDelete">
                                <label class="form-check-label text-danger" for="permanentDelete">
                                    Dokument <strong>permanent</strong> löschen (inkl. Datei auf Server)
                                </label>
                            </div>

                            <p class="text-muted small">
                                Ohne diese Option wird das Dokument nur versteckt und kann später wiederhergestellt werden.
                            </p>

                            <div id="confirmArea" style="display: none;">
                                <label class="form-label text-danger">Zum Bestätigen "DELETE" eingeben:</label>
                                <input type="text" name="confirm" class="form-control" placeholder="DELETE">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-danger">Löschen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('permanentDelete').addEventListener('change', function() {
            document.getElementById('confirmArea').style.display = this.checked ? 'block' : 'none';
        });
        </script>
        <?php
    }
} // Ende Edit-View
?>
