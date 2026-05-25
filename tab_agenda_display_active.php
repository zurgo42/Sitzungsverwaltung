<?php
/**
 * tab_agenda_display_active.php - TOP-Anzeige während aktiver Sitzung
 * Version 2.0 - Mit allen Features
 */

// Module laden
require_once 'module_todos.php';
require_once 'module_voting.php';
require_once 'module_agenda_overview.php';

if (empty($agenda_items)) {
    echo '<div class="info-box">Keine Tagesordnungspunkte vorhanden.</div>';
    return;
}

// Übersicht anzeigen (read-only während aktiver Sitzung)
render_simple_agenda_overview($agenda_items, $current_user, $current_meeting_id, $pdo);

// Aktiven TOP ermitteln
$stmt = $pdo->prepare("SELECT active_item_id FROM svmeetings WHERE meeting_id = ?");
$stmt->execute([$current_meeting_id]);
$active_item_id = $stmt->fetchColumn();
?>

<style>
/* Mobile-responsive live-comment form */
.live-comment-form {
    display: flex;
    gap: 8px;
    align-items: end;
}

@media (max-width: 768px) {
    .live-comment-form {
        flex-direction: column;
        align-items: stretch;
    }

    .live-comment-form button {
        width: 100%;
        margin-top: 4px;
    }
}
</style>

<h3 style="margin: 20px 0 15px 0;">🟢 Laufende Sitzung - Tagesordnungspunkte</h3>

<!-- Direktlink zur Sitzung -->
<?php
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['PHP_SELF']);

// Im SSO-Modus: sso_direct.php Link, sonst: normaler index.php Link
if (defined('DISPLAY_MODE_OVERRIDE') && DISPLAY_MODE_OVERRIDE === 'SSOdirekt') {
    $direct_link = $protocol . '://' . $host . $path . '/sso_direct.php?meeting_id=' . $current_meeting_id;
    $link_description = 'Direktlink zur Sitzung (SSO):';
} else {
    $direct_link = $protocol . '://' . $host . $path . '/index.php?tab=agenda&meeting_id=' . $current_meeting_id;
    $link_description = 'Link zur Sitzung:';
}
?>
<div style="margin: 15px 0; padding: 12px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
    <strong style="color: #1976d2;">🔗 <?php echo $link_description; ?></strong>
    <div style="margin-top: 8px; display: flex; gap: 10px; align-items: center;">
        <input type="text" id="directLinkInput" readonly value="<?php echo htmlspecialchars($direct_link); ?>"
               style="flex: 1; padding: 6px 10px; border: 1px solid #2196f3; border-radius: 4px; font-family: monospace; font-size: 13px;">
        <button onclick="copyDirectLink()"
                style="padding: 6px 16px; background: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
            📋 Kopieren
        </button>
    </div>
    <div style="margin-top: 6px; font-size: 12px; color: #666;">
        Teile diesen Link mit Teilnehmern für direkten Zugriff auf diese Sitzung.
    </div>
</div>
<script>
function copyDirectLink() {
    const input = document.getElementById('directLinkInput');
    input.select();
    input.setSelectionRange(0, 99999); // Für Mobile

    try {
        document.execCommand('copy');
        alert('✅ Link wurde in die Zwischenablage kopiert!');
    } catch (err) {
        // Fallback für moderne Browser
        navigator.clipboard.writeText(input.value).then(() => {
            alert('✅ Link wurde in die Zwischenablage kopiert!');
        }).catch(() => {
            alert('❌ Kopieren fehlgeschlagen. Bitte manuell kopieren.');
        });
    }
}
</script>

<!-- TEILNEHMERLISTE -->
<?php if ($is_secretary): ?>
    <style>
        .participant-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 8px;
            background: white;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .participant-row {
                display: block;
                padding: 8px;
                font-size: 12px;
            }
            .participant-row .participant-name {
                display: block;
                font-size: 13px;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
            }
            .participant-row label {
                display: inline-flex !important; /* Override inline style to keep buttons horizontal */
                align-items: center;
                font-size: 11px;
                margin-right: 8px;
                gap: 4px;
            }
            .participant-row label span {
                font-size: 11px;
            }
            .participant-row input[type="radio"] {
                transform: scale(0.9);
            }
        }
    </style>
    <details open style="margin: 20px 0; padding: 15px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 8px;">
        <summary style="cursor: pointer; font-weight: 600; color: #1976d2; font-size: 16px; margin-bottom: 10px;">
            👥 Teilnehmerverwaltung (klicken zum Auf-/Zuklappen)
        </summary>

        <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>">
            <input type="hidden" name="update_attendance" value="1">

            <div style="margin-bottom: 15px;">
                <button type="button" onclick="setAllPresent()" style="background: #4caf50; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                    ✅ Alle auf "Anwesend" setzen
                </button>
            </div>

            <div style="display: grid; gap: 10px;">
                <?php foreach ($participants as $p):
                    $stmt = $pdo->prepare("SELECT attendance_status FROM svmeeting_participants WHERE meeting_id = ? AND member_id = ?");
                    $stmt->execute([$current_meeting_id, $p['member_id']]);
                    $attendance = $stmt->fetch();
                    $status = $attendance['attendance_status'] ?? 'absent';
                ?>
                    <div class="participant-row">
                        <span class="participant-name" style="flex: 1; font-weight: 600;">
                            <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                        </span>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="present"
                                   class="attendance-radio"
                                   data-member="<?php echo $p['member_id']; ?>"
                                   <?php echo $status === 'present' ? 'checked' : ''; ?>>
                            <span>✅ Anwesend</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="partial"
                                   <?php echo $status === 'partial' ? 'checked' : ''; ?>>
                            <span>⏱️ Zeitweise</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="radio"
                                   name="attendance[<?php echo $p['member_id']; ?>]"
                                   value="absent"
                                   <?php echo $status === 'absent' ? 'checked' : ''; ?>>
                            <span>❌ Abwesend</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="submit" style="margin-top: 15px; background: #2196f3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                💾 Teilnehmerliste speichern
            </button>
        </form>

        <!-- Nicht eingeladene Teilnehmer hinzufügen -->
        <div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #2196f3;">
            <h4 style="margin: 0 0 10px 0; color: #1976d2;">➕ Nicht eingeladene Teilnehmer hinzufügen</h4>
            <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>">
                <input type="hidden" name="add_uninvited_participant" value="1">

                <?php
                // Alle Members laden, die NICHT eingeladen sind
                $stmt_invited = $pdo->prepare("SELECT member_id FROM svmeeting_participants WHERE meeting_id = ?");
                $stmt_invited->execute([$current_meeting_id]);
                $invited_ids = $stmt_invited->fetchAll(PDO::FETCH_COLUMN);

                // Aus $all_members Array filtern
                $uninvited_members = [];
                if (isset($all_members)) {
                    foreach ($all_members as $member) {
                        if (!in_array($member['member_id'], $invited_ids) && $member['is_active']) {
                            $uninvited_members[] = $member;
                        }
                    }
                } else {
                    // Fallback
                    $uninvited_members = get_all_members($pdo);
                    $uninvited_members = array_filter($uninvited_members, function($m) use ($invited_ids) {
                        return !in_array($m['member_id'], $invited_ids) && $m['is_active'];
                    });
                }
                // Sortieren
                usort($uninvited_members, function($a, $b) {
                    return strcmp($a['last_name'], $b['last_name']);
                });
                ?>

                <?php if (count($uninvited_members) > 0): ?>
                    <style>
                        .add-participant-container-active {
                            display: flex;
                            gap: 10px;
                            align-items: flex-end;
                        }
                        @media (max-width: 768px) {
                            .add-participant-container-active {
                                flex-direction: column;
                                align-items: stretch;
                                gap: 8px;
                            }
                            .add-participant-select-active {
                                width: 100% !important;
                            }
                            .add-participant-button-active {
                                width: 100%;
                                padding: 10px !important;
                            }
                        }
                    </style>
                    <div class="add-participant-container-active">
                        <div style="flex: 3;">
                            <select name="new_participant_id" required class="add-participant-select-active" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="">-- Teilnehmer auswählen --</option>
                                <?php foreach ($uninvited_members as $um):
                                    // Display-Name verwenden wenn vorhanden, sonst konvertieren
                                    $display_role = isset($um['role_display']) ? $um['role_display'] : get_role_display_name($um['role']);
                                ?>
                                    <option value="<?php echo $um['member_id']; ?>">
                                        <?php echo htmlspecialchars($um['first_name'] . ' ' . $um['last_name'] . ' (' . $display_role . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="add-participant-button-active" style="background: #4caf50; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
                            ➕ Hinzufügen
                        </button>
                    </div>
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Hinzugefügte Teilnehmer erhalten automatisch den Status "invited" und "present".
                    </small>
                <?php else: ?>
                    <p style="margin: 0; color: #666; font-style: italic;">Alle Mitglieder sind bereits eingeladen.</p>
                <?php endif; ?>
            </form>
        </div>

        <script>
        function setAllPresent() {
            document.querySelectorAll('.attendance-radio').forEach(radio => {
                if (radio.value === 'present') {
                    radio.checked = true;
                }
            });
        }
        </script>
    </details>
<?php else: ?>
    <?php render_readonly_participant_list($pdo, $current_meeting_id, $participants); ?>
<?php endif; ?>

<!-- NEUEN TOP HINZUFÜGEN (nur Sekretär) -->
<?php if ($is_secretary): ?>
<style>
    .top-add-form-footer {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 15px;
    }
    .top-add-submit-btn {
        background: #4caf50;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        white-space: nowrap;
    }
    @media (max-width: 768px) {
        .top-add-form-footer {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }
        .top-add-form-footer label {
            margin-bottom: 0;
        }
        .top-add-submit-btn {
            width: 100%;
            padding: 14px 20px;
            font-size: 15px;
        }
    }
</style>
<details style="margin: 20px 0; border: 2px solid #4caf50; border-radius: 8px; overflow: hidden;">
    <summary style="padding: 15px; background: #4caf50; color: white; font-size: 16px; font-weight: 600; cursor: pointer; list-style: none;">
        <span style="display: inline-block; width: 20px;">▶</span> ➕ Neuen Tagesordnungspunkt anlegen (während Sitzung)
    </summary>

<div style="padding: 15px; background: #f1f8e9;">
    <style>
        .top-form-group {
            margin-bottom: 15px;
        }
        .top-form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .priority-duration-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .top-submit-button {
            width: 100%;
            background: #4caf50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
        }
        @media (max-width: 768px) {
            .priority-duration-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .top-submit-button {
                padding: 14px 20px;
                font-size: 15px;
            }
        }
    </style>
    <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>" enctype="multipart/form-data">
        <input type="hidden" name="add_agenda_item_active" value="1">

        <div class="form-group top-form-group">
            <label style="font-weight: 600;">Titel:</label>
            <input type="text" name="title" required>
        </div>

        <div class="form-group">
            <label style="font-weight: 600;">Beschreibung:</label>
            <textarea name="description" rows="3"></textarea>
        </div>

        <div class="form-group">
            <label style="font-weight: 600;">Kategorie:</label>
            <?php render_category_select('category', 'active_new_category', '', 'toggleProposalField(\'active_new\')', ($meeting['allow_decisions'] ?? 1) == 1); ?>
        </div>

        <?php if (($meeting['allow_decisions'] ?? 1) == 1): ?>
        <!-- Vollständiges Antragsformular (nur sichtbar wenn Kategorie "Antrag/Beschluss" gewählt) -->
        <div id="active_new_proposal" style="display: none; border: 2px solid #4caf50; border-radius: 8px; padding: 20px; margin: 15px 0; background: #f9fff9;">
            <h4 style="margin-top: 0; color: #4caf50; border-bottom: 2px solid #4caf50; padding-bottom: 10px;">📋 Vollständiger Antrag (Vorstandsbeschluss)</h4>
            <small style="display: block; margin-bottom: 20px; color: #666;">
                Der Antrag wird direkt als Vorstandsbeschluss angelegt und kann in der Sitzung abgestimmt werden.
            </small>

            <?php
            // Ressorts laden
            $ressorts = [];
            try {
                $ressorts_stmt = $pdo->query("SELECT Code, Ressort FROM svressorts WHERE aktiv=1 ORDER BY Reihenfolge, Ressort");
                $ressorts = $ressorts_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Fehler beim Laden der Ressorts: " . $e->getMessage());
                echo '<div style="color: red; padding: 10px; background: #ffe0e0; margin: 10px 0;">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>

            <!-- ========== SEKTION 1: STAMMDATEN ========== -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <h5 style="margin-top: 0; color: #333; font-size: 14px; font-weight: 600; margin-bottom: 15px;">Stammdaten</h5>

                <!-- Ressorts -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="font-weight: 600;">Ressort <span style="color: red;">*</span></label>
                        <select name="proposal_ressort1" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($ressorts as $r): ?>
                                <option value="<?= htmlspecialchars($r['Code']) ?>"><?= htmlspecialchars($r['Ressort']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-weight: 600;">Mitwirkendes Ressort</label>
                        <select name="proposal_ressort2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">-- Optional --</option>
                            <?php foreach ($ressorts as $r): ?>
                                <option value="<?= htmlspecialchars($r['Code']) ?>"><?= htmlspecialchars($r['Ressort']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Verantwortlich -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 600;">Verantwortlich für die Umsetzung <span style="color: red;">*</span></label>
                    <input type="text" name="proposal_verant" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Name des Verantwortlichen">
                </div>

                <!-- Verein und Sichtbarkeit -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label style="font-weight: 600;">Verein/Stiftung</label>
                        <select name="proposal_verein" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="V">Verein</option>
                            <option value="S">Stiftung</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-weight: 600;">Sichtbarkeit</label>
                        <select name="proposal_int_ext" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="e" selected>Extern (alle Ms)</option>
                            <option value="n">Nicht öffentlich (Führung)</option>
                            <option value="i">Intern (nur Vorstand)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ========== SEKTION 2: ANTRAG ========== -->
            <div style="background: #ffffff; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #dee2e6;">
                <h5 style="margin-top: 0; color: #333; font-size: 14px; font-weight: 600; margin-bottom: 15px;">Antrag</h5>

                <!-- Titel -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 600;">Titel <span style="color: red;">*</span></label>
                    <input type="text" name="proposal_titel" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Titel des Antrags (kann vom TOP-Titel abweichen)">
                </div>

                <!-- Beschlusstext -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 600;">Beschlusstext <span style="color: red;">*</span></label>
                    <textarea name="proposal_beschluss" rows="3" style="width: 100%; padding: 8px; border: 1px solid #4caf50; border-radius: 4px; resize: vertical; min-height: 50px;" placeholder="Der konkrete Beschluss, über den abgestimmt wird"></textarea>
                </div>

                <!-- Begründung -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 600;">Begründung</label>
                    <textarea name="proposal_begr" rows="4" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Ausführliche Begründung des Antrags"></textarea>
                </div>

                <!-- Betrag -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 600;">Betrag (volle Euro)</label>
                    <input type="number" name="proposal_fin" step="1" min="0" value="0" style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <!-- Auswirkungen in einer Reihe -->
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label style="font-weight: 600;">Finanzielle Auswirkungen</label>
                        <textarea name="proposal_fintext" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    </div>
                    <div class="form-group">
                        <label style="font-weight: 600;">Personelle Auswirkungen</label>
                        <textarea name="proposal_pers" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    </div>
                    <div class="form-group">
                        <label style="font-weight: 600;">Sachliche Auswirkungen</label>
                        <textarea name="proposal_sach" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    </div>
                </div>
            </div>

            <!-- ========== SEKTION 3: ANGEBOTE/UNTERLAGEN ========== -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <h5 style="margin-top: 0; color: #333; font-size: 14px; font-weight: 600; margin-bottom: 15px;">Angebote / Unterlagen</h5>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div>
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Datei <?= $i ?></label>
                            <input type="file" name="proposal_file<?= $i ?>" style="font-size: 12px; padding: 4px; width: 100%; margin-bottom: 5px;">
                            <input type="text" name="proposal_filetext<?= $i ?>" placeholder="Beschreibung (z.B. 'Angebot')" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- ========== SEKTION 4: VEREINFACHTE FREIGABE ========== -->
            <div style="background: #fff8dc; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #ffa500;">
                <h5 style="margin-top: 0; color: #333; font-size: 14px; font-weight: 600; margin-bottom: 15px;">Vereinfachte Freigabe</h5>

                <div style="margin-bottom: 10px;">
                    <label style="display: inline-flex; align-items: center; margin-right: 20px;">
                        <input type="checkbox" name="proposal_sofort_1" value="1" style="margin-right: 8px;">
                        <span style="font-size: 12px;">Wenn Rechnungsbetrag = Angebot, sofort überweisen</span>
                    </label>

                    <span style="margin: 0 10px;">ODER</span>

                    <label style="display: inline-flex; align-items: center;">
                        <input type="checkbox" name="proposal_sofort_2" value="2" style="margin-right: 8px;">
                        <span style="font-size: 12px;">Nach Vorprüfung durch:</span>
                    </label>
                    <input type="text" name="proposal_durch" placeholder="Name" style="width: 120px; margin-left: 8px; padding: 4px; font-size: 12px; border: 1px solid #ddd; border-radius: 4px;">
                    <span style="margin-left: 4px; font-size: 12px;">überweisen</span>
                </div>

                <div style="margin-top: 10px;">
                    <label style="display: inline-flex; align-items: center;">
                        <input type="checkbox" name="proposal_zufin" value="1" style="margin-right: 8px;">
                        <span style="font-size: 12px;">Freigabe durch GF zusätzlich erforderlich</span>
                    </label>
                </div>
            </div>

            <!-- ========== SEKTION 5: BEMERKUNGEN/HINWEISE ========== -->
            <div style="background: #ffffff; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #dee2e6;">
                <h5 style="margin-top: 0; color: #333; font-size: 14px; font-weight: 600; margin-bottom: 15px;">Bemerkungen / Hinweise</h5>

                <div class="form-group">
                    <label style="font-weight: 600;">Hinweis (wird mit Zeitstempel gespeichert)</label>
                    <textarea name="proposal_hinweis" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Optionale Bemerkungen oder Hinweise zum Antrag"></textarea>
                </div>
            </div>

            <small style="display: block; margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;">
                <strong>Hinweis:</strong> Der Antrag wird als <strong>Vorstandsbeschluss (B)</strong> mit praesenz=1 erstellt und erscheint in der Antragsliste. Felder mit <span style="color: red;">*</span> sind Pflichtfelder.
            </small>
        </div>
        <?php else: ?>
        <!-- Einfaches Antragstext-Feld wenn Beschlüsse deaktiviert -->
        <div class="form-group" id="active_new_proposal" style="display:none;">
            <label style="font-weight: 600;">📄 Antragstext:</label>
            <textarea name="proposal_text" rows="4" style="width: 100%; padding: 8px; border: 1px solid #4caf50; border-radius: 4px;"></textarea>
        </div>
        <?php endif; ?>

        <?php
        // Priorität/Dauer nur für Führungsteam
        $is_leadership = in_array(strtolower($current_user['role'] ?? ''), ['vorstand', 'gf', 'assistenz', 'fuehrungsteam']);
        if ($is_leadership):
        ?>
        <div class="priority-duration-grid">
            <div class="form-group top-form-group">
                <label style="font-weight: 600;">Priorität (1-10):</label>
                <input type="number" name="priority" min="1" max="10" step="0.1" value="5" required>
            </div>

            <div class="form-group top-form-group">
                <label style="font-weight: 600;">Geschätzte Dauer (Min.):</label>
                <input type="number" name="duration" min="1" value="10" required>
            </div>
        </div>
        <?php else: ?>
        <!-- Hidden fields mit Standardwerten für nicht-Führungsteam -->
        <input type="hidden" name="priority" value="5">
        <input type="hidden" name="duration" value="10">
        <?php endif; ?>

        <div class="form-group top-form-group">
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" name="is_confidential" value="1" style="margin-right: 8px;">
                🔒 Vertraulich (nur für berechtigte Teilnehmer)
            </label>
        </div>

        <div class="form-group top-form-group">
            <button type="submit" class="top-submit-button">
                ✅ TOP hinzufügen
            </button>
        </div>
    </form>
</div>
</details>
<?php endif; ?>

<!-- TOPS ANZEIGEN -->

<style>
    /* Globale Box-Sizing für alle Elemente */
    .agenda-item *,
    .agenda-item *::before,
    .agenda-item *::after {
        box-sizing: border-box;
    }

    .top-header-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    .top-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .top-header-right {
        display: flex;
        gap: 5px;
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        /* TOP-Header Smartphone-Optimierung */
        .top-header-container {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .top-header-left {
            gap: 6px;
            font-size: 14px;
        }
        .top-header-left strong {
            font-size: 14px !important;
            flex: 1 1 100%;
        }
        .top-header-right {
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 6px;
        }
        .top-header-right button {
            font-size: 10px !important;
            padding: 3px 8px !important;
        }

        /* Alle Eingabefelder auf Smartphones */
        .agenda-item textarea,
        .agenda-item input[type="text"],
        .agenda-item input[type="number"],
        .agenda-item select {
            max-width: 100%;
            font-size: 14px !important;
        }

        /* Padding für bessere Lesbarkeit */
        .agenda-item {
            padding: 10px !important;
        }
    }
</style>

<?php 
// Berechtigung für vertrauliche TOPs prüfen
$can_see_confidential = (
    $current_user['is_admin'] == 1 ||
    $current_user['is_confidential'] == 1 ||
    in_array($current_user['role'], ['vorstand', 'gf']) ||
    $is_secretary ||
    $is_chairman
);

$laufende_nummer = 0;  // Laufende Nummer für priorisierte Reihenfolge (Anzeige)
$item_index = 0;       // Array-Index (für Berechnungen)
foreach ($agenda_items as $item):
    // TOP 999 (Marker) nicht anzeigen
    if ($item['top_number'] == 999) {
        $item_index++;
        continue;
    }

    // Vertrauliche TOPs nur für berechtigte User anzeigen
    if ($item['is_confidential'] && !$can_see_confidential) {
        $item_index++;  // Index weiterzählen für korrekte Berechnungen
        continue;
    }

    // Laufende Nummer hochzählen
    $laufende_nummer++;
    $item_index++;

    $is_active = ($item['item_id'] == $active_item_id);
    $border_color = $is_active ? '#f44336' : '#667eea';
    $border_width = $is_active ? '4px' : '3px';
?>
    <div class="agenda-item" id="top-<?php echo $item['item_id']; ?>"
         style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border: <?php echo $border_width; ?> solid <?php echo $border_color; ?>; border-radius: 8px; <?php echo $is_active ? 'box-shadow: 0 0 15px rgba(244,67,54,0.4);' : ''; ?>">
        
        <!-- TOP-Header -->
        <div class="top-header-container">
            <div class="top-header-left">
                <?php if ($is_active): ?>
                    <span style="background: #f44336; color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">
                        🔴 AKTIV
                    </span>
                <?php endif; ?>
                <strong style="font-size: 16px; color: #333;">
                    <?php echo $laufende_nummer; ?> - war TOP <?php echo $item['top_number']; ?>: <?php echo htmlspecialchars($item['title']); ?>
                </strong>
                <?php render_category_badge($item['category']); ?>
                <?php if ($item['is_confidential']): ?>
                    <span class="badge" style="background: #f39c12; color: white;">🔒 Vertraulich</span>
                <?php endif; ?>
            </div>

            <?php if ($is_secretary && $item['top_number'] != 999): ?>
                <div class="top-header-right">
                    <!-- Aktiv schalten via AJAX -->
                    <?php if (!$is_active): ?>
                        <button onclick="setActiveTop(<?php echo $item['item_id']; ?>, <?php echo $current_meeting_id; ?>)"
                                style="background: #f44336; color: white; padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                            🔴 Aktivieren
                        </button>
                    <?php else: ?>
                        <button onclick="unsetActiveTop(<?php echo $current_meeting_id; ?>)"
                                style="background: #999; color: white; padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                            ⚫ Deaktivieren
                        </button>
                    <?php endif; ?>
                    
                    <!-- Verschieben öffentlich/vertraulich -->
                    <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>" style="display: inline;" onsubmit="return confirm('TOP wirklich verschieben?');">
                        <input type="hidden" name="toggle_confidential" value="1">
                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                        <button type="submit" style="background: #2196f3; color: white; padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600;">
                            <?php echo $item['is_confidential'] ? '🔓 Öffentlich' : '🔒 Vertraulich'; ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Beschreibung -->
        <?php if ($item['description']): ?>
            <div style="color: #666; margin: 8px 0; font-size: 14px;">
                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
            </div>
        <?php endif; ?>

        <!-- Vollständiger Antrag bei verlinktem Antrag -->
        <?php if ($item['antrnr']): ?>
            <?php
            // Antragsdaten laden
            $antrag_stmt = $pdo->prepare("SELECT titel, beschluss FROM antraege WHERE antrnr = ?");
            $antrag_stmt->execute([$item['antrnr']]);
            $antrag_data = $antrag_stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <?php if ($antrag_data): ?>
            <div style="margin-bottom: 15px; border: 2px solid #4caf50; border-radius: 8px; padding: 15px; background: #f0f8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong style="color: #4caf50; font-size: 16px;">📋 Antrag <?php echo htmlspecialchars($item['antrnr']); ?></strong>
                    <a href="antrag_ansehen.php?antrnr=<?php echo urlencode($item['antrnr']); ?>"
                       target="_blank"
                       style="background: #4caf50; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px;">
                        🔗 Vollständigen Antrag öffnen
                    </a>
                </div>

                <div style="margin-bottom: 10px;">
                    <strong style="display: block; margin-bottom: 5px; color: #333;">Titel:</strong>
                    <div style="padding: 8px; background: white; border-radius: 4px; border: 1px solid #ddd;">
                        <?php echo nl2br(htmlspecialchars($antrag_data['titel'])); ?>
                    </div>
                </div>

                <div>
                    <strong style="display: block; margin-bottom: 5px; color: #333;">Beschlusstext:</strong>
                    <div style="padding: 8px; background: white; border-radius: 4px; border: 1px solid #ddd;">
                        <?php echo nl2br(htmlspecialchars($antrag_data['beschluss'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Meta-Info (nicht bei TOP 999) -->
        <?php if ($item['top_number'] != 999): ?>
            <div style="font-size: 12px; color: #999; margin: 8px 0;">
                Eingetragen von: <?php echo htmlspecialchars($item['creator_first'] . ' ' . $item['creator_last']); ?> |

                <!-- Priorität (editierbar für Sekretär bei aktivem TOP) -->
                <?php if ($is_active && $is_secretary): ?>
                    <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>" style="display: inline;">
                        <input type="hidden" name="update_active_priority" value="1">
                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                        <label>Priorität:</label>
                        <input type="number" name="priority" value="<?php echo htmlspecialchars($item['priority']); ?>"
                               min="1" max="10" step="0.1"
                               style="width: 50px; padding: 2px; border: 1px solid #2196f3; border-radius: 3px;">
                        <button type="submit" style="background: #2196f3; color: white; padding: 2px 8px; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">
                            💾
                        </button>
                    </form>
                <?php else: ?>
                    Priorität: <?php echo $item['priority']; ?>
                <?php endif; ?>
                |
                Dauer: <?php echo $item['estimated_duration']; ?> Min.
                
                <?php
                // Zeitbedarfsberechnung
                $remaining = calculate_remaining_time($pdo, $agenda_items, $item_index);
                if ($remaining['regular'] > 0 || $remaining['confidential'] > 0):
                ?>
                    <br><span style="background: #fff3cd; padding: 4px 8px; border-radius: 4px; display: inline-block;"><strong>Geschätzter Zeitbedarf ab hier:</strong>
                    <?php if ($remaining['regular'] > 0): ?>
                        <strong>Öffentlich: ~<?php echo $remaining['regular']; ?> Min.</strong>
                    <?php endif; ?>
                    <?php if ($remaining['confidential'] > 0): ?>
                        <strong>| Vertraulich: ~<?php echo $remaining['confidential']; ?> Min.</strong>
                    <?php endif; ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Diskussionsbeiträge aus Vorbereitung -->
        <div style="margin-top: 12px;">
            <h4 style="font-size: 14px; color: #666; margin-bottom: 8px;">💬 Diskussionsbeiträge (Vorbereitung)</h4>
            <?php
            $prep_comments = get_item_comments($pdo, $item['item_id']);
            if (!empty($prep_comments)):
            ?>
                <div style="background: white; border: 1px solid #ddd; border-radius: 5px; padding: 8px; max-height: 150px; overflow-y: auto;">
                    <?php foreach ($prep_comments as $comment): ?>
                        <?php render_comment_line($comment, 'full'); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="color: #999; font-size: 12px;">Keine Kommentare aus Vorbereitung</div>
            <?php endif; ?>
        </div>

        <!-- Dateianhänge (kompakt) -->
        <details style="margin: 10px 0;">
            <summary style="cursor: pointer; padding: 8px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                📎 Dateianhänge (<?php
                // Anzahl vorhandener Attachments
                $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM svagenda_attachments WHERE item_id = ?");
                $stmt_count->execute([$item['item_id']]);
                echo $stmt_count->fetchColumn();
                ?>)
            </summary>
            <div style="margin-top: 10px; padding: 10px; background: #fafafa; border: 1px solid #e0e0e0; border-radius: 4px;">
                <?php
                // Vorhandene Attachments laden
                $stmt_attachments = $pdo->prepare("
                    SELECT aa.*
                    FROM svagenda_attachments aa
                    WHERE aa.item_id = ?
                    ORDER BY aa.uploaded_at DESC
                ");
                $stmt_attachments->execute([$item['item_id']]);
                $attachments = $stmt_attachments->fetchAll(PDO::FETCH_ASSOC);

                // Mitgliederdaten über Adapter laden
                foreach ($attachments as &$att) {
                    if ($att['uploaded_by_member_id']) {
                        $member = get_member_by_id($pdo, $att['uploaded_by_member_id']);
                        if ($member) {
                            $att['first_name'] = $member['first_name'];
                            $att['last_name'] = $member['last_name'];
                        } else {
                            $att['first_name'] = 'Unbekannt';
                            $att['last_name'] = '';
                        }
                    } else {
                        $att['first_name'] = '';
                        $att['last_name'] = '';
                    }
                }
                unset($att);
                ?>

                <!-- Vorhandene Dateien -->
                <div id="attachments-list-<?php echo $item['item_id']; ?>">
                    <?php if (!empty($attachments)): ?>
                        <div style="margin-bottom: 10px;">
                            <?php foreach ($attachments as $att): ?>
                                <div style="padding: 6px; background: white; border: 1px solid #ddd; border-radius: 3px; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                                    <div>
                                        📄 <strong><?php echo htmlspecialchars($att['original_filename']); ?></strong>
                                        <small style="color: #666;">(<?php echo number_format($att['filesize'] / 1024, 1); ?> KB)</small>
                                    </div>
                                    <div>
                                        <a href="<?php echo htmlspecialchars($att['filepath']); ?>" download style="margin-right: 5px; font-size: 12px;">⬇️</a>
                                        <?php if ($att['uploaded_by_member_id'] == $current_user['member_id'] || $is_admin): ?>
                                            <button onclick="deleteAttachment(<?php echo $att['attachment_id']; ?>, <?php echo $item['item_id']; ?>)" style="background: none; border: none; cursor: pointer; font-size: 12px;">🗑️</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Drag & Drop Zone (kompakt) -->
                <div class="drop-zone" id="drop-zone-<?php echo $item['item_id']; ?>"
                     ondrop="handleDrop(event, <?php echo $item['item_id']; ?>)"
                     ondragover="allowDrop(event)"
                     ondragleave="dragLeave(event)"
                     style="border: 1px dashed #999; border-radius: 4px; padding: 15px; text-align: center; background: #fefefe; cursor: pointer; font-size: 12px;">

                    <input type="file" id="file-input-<?php echo $item['item_id']; ?>"
                           multiple
                           style="display: none;"
                           onchange="handleFileSelect(event, <?php echo $item['item_id']; ?>)">

                    <div onclick="document.getElementById('file-input-<?php echo $item['item_id']; ?>').click()">
                        <span style="font-size: 20px;">📎</span>
                        <span style="color: #666;">Dateien hier ablegen oder klicken</span>
                        <small style="display: block; color: #999; margin-top: 3px;">Max. 10 MB pro Datei</small>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div id="upload-progress-<?php echo $item['item_id']; ?>" style="display: none; margin-top: 8px;">
                    <div style="background: #e3f2fd; padding: 8px; border-radius: 3px; font-size: 12px;">
                        <strong>⏳ Uploading...</strong>
                        <div id="upload-status-<?php echo $item['item_id']; ?>"></div>
                    </div>
                </div>
            </div>
        </details>

        <!-- ABSTIMMUNG -->
        <?php
        // Prüfen ob aktive Abstimmung existiert
        $active_voting = get_active_voting($pdo, $item['item_id']);

        // Voting-UI rendern (zeigt Start-Button oder laufende Abstimmung)
        render_voting_ui($item, $active_voting, $current_user, $meeting, $pdo);

        // Abgeschlossene Abstimmungen anzeigen
        render_closed_votings($item['item_id'], $pdo);
        ?>

        <!-- LIVE-KOMMENTARE (dynamisch ein-/ausgeblendet je nach Aktiv-Status) -->
        <?php if ($item['top_number'] != 999): ?>
            <div id="live-comments-container-<?php echo $item['item_id']; ?>"
                 style="margin-top: 12px; padding: 12px; background: #ffebee; border: 2px solid #f44336; border-radius: 6px; <?php echo !$is_active ? 'display: none;' : ''; ?>">
                <h4 style="color: #c62828; margin-bottom: 8px;">💬 Live-Kommentare (während Sitzung)</h4>

                <!-- Live-Kommentare-Anzeige -->
                <div id="live-comments-<?php echo $item['item_id']; ?>" style="background: white; border: 1px solid #f44336; border-radius: 4px; padding: 8px; margin-bottom: 10px; max-height: 120px; overflow-y: auto;">
                    <?php
                    if ($is_active) {
                        $stmt = $pdo->prepare("
                            SELECT alc.*
                            FROM svagenda_live_comments alc
                            WHERE alc.item_id = ?
                            ORDER BY alc.created_at ASC
                        ");
                        $stmt->execute([$item['item_id']]);
                        $live_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Mitgliederdaten über Adapter laden
                        foreach ($live_comments as &$lc) {
                            $member = get_member_by_id($pdo, $lc['member_id']);
                            if ($member) {
                                $lc['first_name'] = $member['first_name'];
                                $lc['last_name'] = $member['last_name'];
                            } else {
                                $lc['first_name'] = 'Unbekannt';
                                $lc['last_name'] = '';
                            }
                        }
                        unset($lc);

                        if (!empty($live_comments)) {
                            foreach ($live_comments as $lc) {
                                render_comment_line($lc, 'time');
                            }
                        } else {
                            echo '<div style="color: #999; font-size: 12px;">Noch keine Kommentare</div>';
                        }
                    } else {
                        echo '<div style="color: #999; font-size: 12px;">Noch keine Kommentare</div>';
                    }
                    ?>
                </div>

                <!-- Formular für neuen Live-Kommentar -->
                <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>" class="live-comment-form">
                    <input type="hidden" name="add_live_comment" value="1">
                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">

                    <div style="flex: 1;">
                        <textarea name="comment_text"
                                  rows="2"
                                  placeholder="Ihr Beitrag zur laufenden Diskussion..."
                                  style="width: 100%; padding: 6px; border: 1px solid #f44336; border-radius: 4px; font-size: 13px;"
                                  required></textarea>
                    </div>

                    <button type="submit" style="background: #f44336; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; white-space: nowrap;">
                        💬 Senden
                    </button>
                </form>

                <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.6); border-radius: 4px; font-size: 12px; color: #666; font-style: italic;">
                    ℹ️ Kommentare in diesem Feld bleiben bis zur Protokollgenehmigung sichtbar und werden dann verworfen
                </div>
            </div>

            <!-- Eigenes TODO oder Mitschrift für diesen TOP erstellen -->
            <?php
            // Fälligkeitsdatum vorbelegen: Meeting-Datum + 7 Tage
            $default_due_date = date('Y-m-d', strtotime($meeting['meeting_date'] . ' +7 days'));

            // Persönliche Notiz laden (falls vorhanden)
            require_once 'personal_notes_functions.php';
            $personal_note = get_personal_note($pdo, $item['item_id'], $current_user['member_id']);
            $note_text = $personal_note['note_text'] ?? '';
            ?>

            <details style="margin-top: 15px; background: #fff8e1; border: 2px solid #ffc107; border-radius: 6px; overflow: hidden;">
                <summary style="padding: 10px 15px; cursor: pointer; font-weight: 600; color: #f57c00; font-size: 14px; user-select: none;">
                    <span style="display: inline-block; transform: rotate(0deg); transition: transform 0.2s;">▶</span>
                    📝 Eigenes TODO oder eigene Mitschrift zu diesem TOP erstellen
                </summary>

                <div style="padding: 15px; background: #fffef5;">
                    <!-- TODO-Formular -->
                    <h4 style="margin: 0 0 10px 0; color: #f57c00; font-size: 14px;">📌 ToDo anlegen</h4>
                    <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>"
                          onsubmit="return confirm('TODO wirklich anlegen?');">
                        <input type="hidden" name="quick_todo_create" value="<?php echo $item['item_id']; ?>">

                        <!-- Responsive Grid: Titel + Fällig bis -->
                        <div class="todo-form-grid" style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">
                                    Titel:
                                </label>
                                <input type="text" name="todo_title_<?php echo $item['item_id']; ?>" required
                                       placeholder="z.B. Recherche für nächste Sitzung"
                                       style="width: 100%; padding: 8px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px;">
                            </div>

                            <div class="todo-due-date-field">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">
                                    Fällig bis:
                                </label>
                                <input type="date" name="todo_due_date_<?php echo $item['item_id']; ?>" required
                                       value="<?php echo $default_due_date; ?>"
                                       style="width: 100%; padding: 8px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px;">
                            </div>
                        </div>

                        <div style="margin-bottom: 10px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px; color: #333;">
                                Beschreibung (optional):
                            </label>
                            <textarea name="todo_description_<?php echo $item['item_id']; ?>" rows="3"
                                      placeholder="Details zum TODO..."
                                      style="width: 100%; padding: 8px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px; resize: vertical;"></textarea>
                        </div>

                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <button type="submit"
                                    style="padding: 8px 16px; background: #ffc107; color: #333; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                                ✅ ToDo anlegen
                            </button>
                            <span style="font-size: 12px; color: #666;">
                                (Privat - nur für dich sichtbar)
                            </span>
                        </div>
                    </form>

                    <!-- Trennlinie -->
                    <hr style="margin: 20px 0; border: none; border-top: 1px solid #ffc107;">

                    <!-- Eigene Notizen mit Autosave -->
                    <h4 style="margin: 0 0 10px 0; color: #f57c00; font-size: 14px;">📄 Eigene Notizen zu diesem TOP</h4>
                    <div style="position: relative;">
                        <textarea
                            id="personal_note_<?php echo $item['item_id']; ?>"
                            class="personal-note-textarea"
                            data-item-id="<?php echo $item['item_id']; ?>"
                            placeholder="Hier kannst du deine persönlichen Notizen zu diesem TOP festhalten (wird automatisch gespeichert)..."
                            style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #ffc107; border-radius: 4px; font-size: 14px; resize: vertical; font-family: inherit;"
                        ><?php echo htmlspecialchars($note_text); ?></textarea>

                        <div id="autosave_status_<?php echo $item['item_id']; ?>"
                             style="position: absolute; bottom: 8px; right: 8px; font-size: 11px; color: #999; font-style: italic; pointer-events: none;">
                        </div>
                    </div>
                    <div style="font-size: 11px; color: #666; margin-top: 5px;">
                        💾 Änderungen werden automatisch gespeichert
                    </div>
                </div>

                <style>
                /* Rotate arrow when details open */
                details[open] > summary span {
                    transform: rotate(90deg) !important;
                }

                /* Responsive Grid für TODO-Form */
                @media (min-width: 769px) {
                    .todo-form-grid {
                        grid-template-columns: 2fr 1fr !important;
                    }
                }

                /* Auto-grow Textarea */
                .personal-note-textarea {
                    overflow-y: hidden;
                }
                </style>
            </details>
        <?php endif; ?>

        <?php if ($item['top_number'] != 999): ?>
            <?php if ($is_secretary): ?>
                <!-- PROTOKOLL-FORMULAR (nur für Sekretär) -->
                <div style="margin-top: 15px; padding: 12px; background: #f0f7ff; border: 2px solid #2196f3; border-radius: 6px;">
                    <h4 style="color: #1976d2; margin-bottom: 10px;">📝 Protokoll</h4>

                    <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>">
                        <input type="hidden" name="save_protocol" value="1">
                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">

                        <div class="form-group">
                            <label style="font-weight: 600;">Protokollnotizen:</label>
                            <textarea name="protocol_text"
                                      rows="5"
                                      placeholder="Notizen zu diesem TOP..."
                                      style="width: 100%; padding: 8px; border: 1px solid #2196f3; border-radius: 4px;"><?php echo htmlspecialchars($item['protocol_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <?php 
                    // Abstimmungsfelder nur bei Antrag/Beschluss
                    if ($item['category'] === 'antrag_beschluss') {
                        render_voting_fields($item['item_id'], $item);
                    }
                    
                    // ToDo-Vergabe (für Protokollant) - INNERHALB des Formulars!
                    render_todo_creation_form($pdo, $item, $current_meeting_id, $is_secretary, 'active', $participants);
                    ?>
                    
                    <button type="submit" style="background: #2196f3; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; margin-top: 10px;">
                        💾 Protokoll speichern
                    </button>
                </form>
                
                <?php 
                // Wiedervorlage-Feature
                if ($item['top_number'] != 0 && $item['top_number'] != 99):
                    // Zukünftige Meetings laden
                    $stmt_future = $pdo->prepare("
                        SELECT meeting_id, meeting_name, meeting_date, location
                        FROM svmeetings
                        WHERE meeting_id != ? 
                        AND (status = 'preparation' OR meeting_date > ?)
                        ORDER BY meeting_date ASC
                        LIMIT 20
                    ");
                    $stmt_future->execute([$current_meeting_id, $meeting['meeting_date']]);
                    $future_meetings = $stmt_future->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($future_meetings)):
                ?>
                    <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>" style="margin-top: 15px; padding: 12px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 5px;">
                        <input type="hidden" name="save_resubmit" value="1">
                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                        
                        <div style="font-weight: 600; color: #1976d2; margin-bottom: 10px;">
                            🔄 Wiedervorlage für spätere Sitzung
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="font-size: 13px;">Sitzung auswählen:</label>
                            <select name="resubmit_meeting_id" 
                                    style="width: 100%; padding: 6px; border: 1px solid #90caf9; border-radius: 4px; font-size: 13px;">
                                <option value="">-- keine --</option>
                                <?php foreach ($future_meetings as $fm): ?>
                                    <option value="<?php echo $fm['meeting_id']; ?>">
                                        <?php 
                                        echo date('d.m.Y', strtotime($fm['meeting_date'])) . ' - ';
                                        echo htmlspecialchars($fm['meeting_name']); 
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px;">
                                <input type="checkbox" 
                                       name="resubmit_confidential" 
                                       value="1"
                                       <?php echo $item['is_confidential'] ? 'checked' : ''; ?>
                                       style="width: auto;">
                                <span>🔒 Als vertraulichen TOP anlegen</span>
                            </label>
                        </div>
                        
                        <button type="submit" style="background: #2196f3; color: white; padding: 6px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">
                            🔄 Wiedervorlage speichern
                        </button>
                        
                        <div style="font-size: 11px; color: #666; margin-top: 8px;">
                            ℹ️ Der TOP wird mit "Wiedervorlage: [Titel]" in der gewählten Sitzung angelegt
                        </div>
                    </form>
                <?php 
                    endif;
                endif;
                ?>
                
            </div>
        <?php else: ?>
            <!-- Protokoll-Anzeige für andere Teilnehmer (Live-Update) -->
            <div style="margin-top: 15px; padding: 10px; background: #f0f7ff; border-left: 4px solid #2196f3; border-radius: 4px;">
                <strong style="color: #1976d2;">📝 Protokoll:</strong><br>
                <div id="protocol-display-<?php echo $item['item_id']; ?>" style="margin-top: 6px; color: #333; font-size: 14px;">
                    <?php echo nl2br(linkify_text($item['protocol_notes'] ?? 'Noch kein Protokolleintrag...')); ?>
                </div>
                <div id="vote-display-<?php echo $item['item_id']; ?>" style="margin-top: 8px;">
                    <?php if (!empty($item['vote_result'])): ?>
                        <div style="padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                            <strong>Abstimmung:</strong>
                            Ja: <?= (int)($item['vote_yes'] ?? 0) ?> |
                            Nein: <?= (int)($item['vote_no'] ?? 0) ?> |
                            Enthaltungen: <?= (int)($item['vote_abstain'] ?? 0) ?> →
                            <strong><?= htmlspecialchars($item['vote_result']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        
    </div>
<?php endforeach; ?>

<?php if ($can_edit_meeting): ?>
    <!-- Sitzung beenden Button -->
    <div style="margin-top: 20px; padding: 15px; background: #fff3e0; border: 2px solid #ff9800; border-radius: 8px;">
        <h4 style="color: #e65100; margin-bottom: 10px;">⏸️ Sitzung beenden</h4>
        <p style="color: #666; margin-bottom: 10px;">
            Wenn alle TOPs behandelt wurden, kannst du die Sitzung beenden und das Protokoll erstellen.
        </p>
        <form method="POST" action="?tab=agenda&meeting_id=<?php echo $current_meeting_id; ?>" onsubmit="return confirm('Sitzung jetzt beenden?');">
            <input type="hidden" name="end_meeting" value="1">
            <button type="submit" style="background: #ff9800; color: white; padding: 10px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 4px; cursor: pointer;">
                ⏸️ Sitzung jetzt beenden
            </button>
        </form>
    </div>
<?php endif; ?>

<script>
// AJAX: TOP aktivieren
function setActiveTop(itemId, meetingId) {
    fetch('api/meeting_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=set_active_top&item_id=${itemId}&meeting_id=${meetingId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        alert('AJAX-Fehler: ' + error);
    });
}

// AJAX: TOP deaktivieren
function unsetActiveTop(meetingId) {
    fetch('api/meeting_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=unset_active_top&meeting_id=${meetingId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
        }
    })
    .catch(error => {
        alert('AJAX-Fehler: ' + error);
    });
}

// ============================================
// LIVE-UPDATES für alle Teilnehmer
// ============================================

// Polling-Intervall: Alle 5 Sekunden Updates holen
let updateInterval = null;
const isSecretary = <?php echo $is_secretary ? 'true' : 'false'; ?>;

// Funktion: Protokoll-Updates für einen TOP holen
function updateProtocol(itemId) {
    fetch(`api/meeting_get_updates.php?item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error(`Live-Update Fehler TOP ${itemId}:`, data.error);
                return;
            }

            // Protokoll-Anzeige aktualisieren
            const protocolDiv = document.getElementById(`protocol-display-${itemId}`);
            if (protocolDiv && data.protocol_notes) {
                // Linkify direkt im JavaScript (einfache URL-Erkennung)
                let text = data.protocol_notes
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\n/g, '<br>');

                // URLs zu Links konvertieren
                text = text.replace(/\b((https?:\/\/|www\.)[^\s<]+)/gi, function(url) {
                    let href = url.startsWith('http') ? url : 'http://' + url;
                    let ext = url.split('.').pop().toLowerCase();
                    let isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
                    let isPdf = (ext === 'pdf');
                    let onclick = '';
                    if (isImage || isPdf) {
                        let type = isImage ? 'Bild' : 'PDF';
                        onclick = ` onclick="if(window.innerWidth <= 768) { alert('⚠️ ${type}-Datei wird in neuem Tab geöffnet'); }"`;
                    }
                    return `<a href="${href}" target="_blank" rel="noopener noreferrer"${onclick}>${url}</a>`;
                });

                protocolDiv.innerHTML = text;
            }

            // Aktiv-Status aktualisieren (roter Rand)
            const itemDiv = document.getElementById(`top-${itemId}`);
            if (itemDiv) {
                if (data.is_active) {
                    itemDiv.style.border = '4px solid #f44336';
                    itemDiv.style.boxShadow = '0 0 15px rgba(244,67,54,0.4)';
                } else {
                    itemDiv.style.border = '3px solid #667eea';
                    itemDiv.style.boxShadow = '';
                }
            }

            // Live-Kommentare-Container ein-/ausblenden je nach Aktiv-Status
            const commentsContainer = document.getElementById(`live-comments-container-${itemId}`);
            if (commentsContainer) {
                commentsContainer.style.display = data.is_active ? 'block' : 'none';
            }

            // Live-Kommentare aktualisieren (nur wenn TOP aktiv ist)
            if (data.is_active) {
                const commentsDiv = document.getElementById(`live-comments-${itemId}`);
                if (commentsDiv && data.live_comments) {
                    let html = '';
                    data.live_comments.forEach(comment => {
                        const time = new Date(comment.created_at).toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});

                        // Linkify comment text
                        let commentText = comment.comment_text
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;');

                        commentText = commentText.replace(/\b((https?:\/\/|www\.)[^\s<]+)/gi, function(url) {
                            let href = url.startsWith('http') ? url : 'http://' + url;
                            let ext = url.split('.').pop().toLowerCase();
                            let isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext);
                            let isPdf = (ext === 'pdf');
                            let onclick = '';
                            if (isImage || isPdf) {
                                let type = isImage ? 'Bild' : 'PDF';
                                onclick = ` onclick="if(window.innerWidth <= 768) { alert('⚠️ ${type}-Datei wird in neuem Tab geöffnet'); }"`;
                            }
                            return `<a href="${href}" target="_blank" rel="noopener noreferrer"${onclick}>${url}</a>`;
                        });

                        html += `<div style="padding: 4px 0; border-bottom: 1px solid #eee; font-size: 13px; line-height: 1.5;">
                            <strong style="color: #333;">${comment.first_name} ${comment.last_name}</strong> <span style="color: #999; font-size: 11px;">${time}:</span> <span style="color: #555;">${commentText}</span>
                        </div>`;
                    });
                    commentsDiv.innerHTML = html || '<div style="color: #999; font-size: 12px; padding: 4px;">Noch keine Kommentare</div>';

                    // Auto-Scroll zum Ende der Kommentare
                    commentsDiv.scrollTop = commentsDiv.scrollHeight;
                }
            }

            // Abstimmungsergebnis aktualisieren
            if (data.vote_result) {
                const voteDiv = document.getElementById(`vote-display-${itemId}`);
                if (voteDiv) {
                    try {
                        let voteHtml = `<strong>Abstimmung:</strong> `;
                        if (data.vote_result === 'einvernehmlich' || data.vote_result === 'einstimmig') {
                            voteHtml += data.vote_result;
                        } else {
                            voteHtml += `${data.vote_yes || 0} Ja, ${data.vote_no || 0} Nein, ${data.vote_abstain || 0} Enthaltung - ${data.vote_result}`;
                        }
                        voteDiv.innerHTML = voteHtml;
                    } catch (e) {
                        console.debug(`Konnte Abstimmung für TOP ${itemId} nicht aktualisieren:`, e);
                    }
                }
            }
        })
        .catch(error => {
            console.error(`Live-Update Netzwerk-Fehler TOP ${itemId}:`, error);
        });
}

// Funktion: Alle TOPs updaten
function updateAllProtocols() {
    const items = document.querySelectorAll('[id^="top-"]');
    items.forEach(item => {
        const match = item.id.match(/top-(\d+)/);
        if (match) {
            const itemId = match[1];
            updateProtocol(itemId);
        }
    });
}

// Live-Updates starten (nur im Status "active")
<?php if ($meeting['status'] === 'active'): ?>
    // Warten bis DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Initial-Update
            updateAllProtocols();

            // Polling alle 5 Sekunden
            updateInterval = setInterval(updateAllProtocols, 5000);
        });
    } else {
        // DOM bereits geladen
        updateAllProtocols();
        updateInterval = setInterval(updateAllProtocols, 5000);
    }

    // Beim Verlassen der Seite stoppen
    window.addEventListener('beforeunload', function() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    });
<?php endif; ?>

// ============================================
// Drag & Drop File Upload
// ============================================

function allowDrop(e) {
    e.preventDefault();
    e.currentTarget.style.background = '#e3f2fd';
    e.currentTarget.style.borderColor = '#1976d2';
}

function dragLeave(e) {
    e.currentTarget.style.background = '#f5f5f5';
    e.currentTarget.style.borderColor = '#2196f3';
}

function handleDrop(e, itemId) {
    e.preventDefault();
    e.currentTarget.style.background = '#f5f5f5';
    e.currentTarget.style.borderColor = '#2196f3';

    const files = e.dataTransfer.files;
    uploadFiles(files, itemId);
}

function handleFileSelect(e, itemId) {
    const files = e.target.files;
    uploadFiles(files, itemId);
}

function uploadFiles(files, itemId) {
    if (files.length === 0) return;

    const formData = new FormData();
    for (let file of files) {
        formData.append('files[]', file);
    }
    formData.append('item_id', itemId);

    // Progress anzeigen
    const progressDiv = document.getElementById('upload-progress-' + itemId);
    const statusDiv = document.getElementById('upload-status-' + itemId);
    progressDiv.style.display = 'block';
    statusDiv.innerHTML = 'Uploading ' + files.length + ' Datei(en)...';

    fetch('upload_agenda_attachment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '✅ ' + data.uploaded.length + ' Datei(en) erfolgreich hochgeladen!';

            // Seite neu laden um Attachments anzuzeigen
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            statusDiv.innerHTML = '❌ Fehler: ' + (data.error || 'Unbekannter Fehler');
        }

        if (data.errors && data.errors.length > 0) {
            statusDiv.innerHTML += '<br><small>' + data.errors.join('<br>') + '</small>';
        }

        // Progress nach 3 Sekunden ausblenden
        setTimeout(() => {
            progressDiv.style.display = 'none';
        }, 3000);
    })
    .catch(error => {
        statusDiv.innerHTML = '❌ Upload-Fehler: ' + error.message;
        console.error('Upload error:', error);
    });
}

function deleteAttachment(attachmentId, itemId) {
    if (!confirm('Datei wirklich löschen?')) return;

    fetch('delete_agenda_attachment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'attachment_id=' + attachmentId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Fehler: ' + (data.error || 'Löschen fehlgeschlagen'));
        }
    })
    .catch(error => {
        alert('Fehler beim Löschen: ' + error.message);
        console.error('Delete error:', error);
    });
}

// Drag & Drop Styling
document.addEventListener('DOMContentLoaded', function() {
    const dropZones = document.querySelectorAll('.drop-zone');
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', function(e) {
            this.style.transform = 'scale(1.02)';
        });
        zone.addEventListener('dragleave', function(e) {
            this.style.transform = 'scale(1)';
        });
        zone.addEventListener('drop', function(e) {
            this.style.transform = 'scale(1)';
        });
    });

    // Autosave für persönliche Notizen
    initPersonalNotes();
});

// Autosave-Funktion für persönliche Notizen
function initPersonalNotes() {
    const textareas = document.querySelectorAll('.personal-note-textarea');
    const saveTimers = {};

    textareas.forEach(textarea => {
        const itemId = textarea.dataset.itemId;
        const statusEl = document.getElementById('autosave_status_' + itemId);

        // Auto-grow Textarea
        function adjustHeight() {
            textarea.style.height = 'auto';
            textarea.style.height = Math.max(80, textarea.scrollHeight) + 'px';
        }

        textarea.addEventListener('input', function() {
            adjustHeight();

            // Debounce: Nach 1 Sekunde ohne Änderung speichern
            clearTimeout(saveTimers[itemId]);
            statusEl.textContent = '⏱️ Warte...';

            saveTimers[itemId] = setTimeout(() => {
                savePersonalNote(itemId, textarea.value, statusEl);
            }, 1000);
        });

        // Initial height
        adjustHeight();
    });
}

// Speichert persönliche Notiz via AJAX
function savePersonalNote(itemId, noteText, statusEl) {
    statusEl.textContent = '💾 Speichere...';

    const formData = new FormData();
    formData.append('item_id', itemId);
    formData.append('note_text', noteText);

    fetch('ajax_save_personal_note.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusEl.textContent = '✅ Gespeichert (' + data.timestamp + ')';
            setTimeout(() => {
                statusEl.textContent = '';
            }, 3000);
        } else {
            statusEl.textContent = '❌ Fehler';
            console.error('Save failed:', data.error);
        }
    })
    .catch(error => {
        statusEl.textContent = '❌ Fehler';
        console.error('Save error:', error);
    });
}

</script>
