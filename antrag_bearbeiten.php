<?php
/**
 * antrag_bearbeiten.php - Vollständige Antragsverwaltung mit kompaktem Layout
 * Rechte: aktiv > 10
 */

require_once 'session_config.php';
session_start();
require_once 'config.php';
require_once 'config_adapter.php';
require_once 'member_functions.php';
require_once 'includes/antragstypen_helper.php';
require_once 'includes/voting_helper.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Antragstypen-Config laden
$bart_config = lade_antragstypen_config($pdo);

// Voting-Config laden
$voting_config = lade_voting_config($pdo);

// Hilfsfunktionen
function getVerfuegungsberechtigte($pdo) {
    // Über Adapter: Alle mit aktiv >= 18
    $all_members = get_all_members($pdo);
    $verfuegungsberechtigte = array_filter($all_members, function($m) {
        return isset($m['aktiv']) && $m['aktiv'] >= 18;
    });

    // Nach aktiv DESC, dann Vorname sortieren
    usort($verfuegungsberechtigte, function($a, $b) {
        $aktiv_diff = ($b['aktiv'] ?? 0) - ($a['aktiv'] ?? 0);
        if ($aktiv_diff != 0) return $aktiv_diff;
        return strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
    });

    // Felder anpassen für Kompatibilität
    return array_map(function($m) {
        return [
            'ID' => $m['member_id'],
            'KurzN' => substr($m['first_name'], 0, 1) . '. ' . $m['last_name'],
            'Vorname' => $m['first_name'],
            'Funktion' => $m['funktion'] ?? ''
        ];
    }, $verfuegungsberechtigte);
}

function getAbstimmungsberechtigte($pdo, $bart, $antrst) {
    $all_members = get_all_members($pdo);

    // Für V und R: aktiv >= 14
    if ($bart === 'V' || $bart === 'R') {
        $members = array_filter($all_members, function($m) {
            return isset($m['aktiv']) && $m['aktiv'] >= 14;
        });

        // Für R: zusätzlich FVo oder FVv
        if ($bart === 'R') {
            // Prüfen ob Antragsteller FVo ist
            $antrst_member = get_member_by_id($pdo, $antrst);
            $antrst_funktion = $antrst_member['funktion'] ?? '';

            if ($antrst_funktion === 'FVo') {
                // FVv als zweiten Abstimmenden
                $zusatz = 'FVv';
            } else {
                // FVo als zweiten Abstimmenden
                $zusatz = 'FVo';
            }

            // Suche Vorstandsmitglied mit passender Funktion
            $vorstand_member = null;
            foreach ($all_members as $m) {
                if (($m['funktion'] ?? '') === $zusatz) {
                    $vorstand_member = $m;
                    break;
                }
            }

            if ($vorstand_member) {
                // FVo/FVv als erstes Element hinzufügen
                array_unshift($members, $vorstand_member);
            }
        }

        // Sortieren und formatieren
        $members = array_values($members);
        usort($members, function($a, $b) {
            $aktiv_diff = ($b['aktiv'] ?? 0) - ($a['aktiv'] ?? 0);
            if ($aktiv_diff != 0) return $aktiv_diff;
            return strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
        });

        return array_map(function($m) {
            return [
                'ID' => $m['member_id'],
                'KurzN' => substr($m['first_name'], 0, 1) . '. ' . $m['last_name'],
                'Vorname' => $m['first_name'],
                'Funktion' => $m['funktion'] ?? ''
            ];
        }, $members);
    }

    // Für B: nur Vorstände (aktiv = 19)
    $members = array_filter($all_members, function($m) {
        return isset($m['aktiv']) && (int)$m['aktiv'] === 19;
    });

    usort($members, function($a, $b) {
        $aktiv_diff = ($b['aktiv'] ?? 0) - ($a['aktiv'] ?? 0);
        if ($aktiv_diff != 0) return $aktiv_diff;
        return strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
    });

    return array_map(function($m) {
        return [
            'ID' => $m['member_id'],
            'KurzN' => substr($m['first_name'], 0, 1) . '. ' . $m['last_name'],
            'Vorname' => $m['first_name'],
            'Funktion' => $m['funktion'] ?? ''
        ];
    }, $members);
}

function berechneWartezeit($antrnr, $bart, $bart_config) {
    if (strlen($antrnr) < 7) return null;
    $jahr = '20' . substr($antrnr, 1, 2);
    $monat = substr($antrnr, 3, 2);
    $tag = substr($antrnr, 5, 2);
    $antragsdatum = strtotime("$jahr-$monat-$tag");
    if (!$antragsdatum) return null;

    // Wartezeit aus Config laden
    $wz = berechne_wartezeit($bart, $antragsdatum, $bart_config);

    if (!$wz['aktiv']) {
        return 'erfüllt'; // Keine Wartezeit konfiguriert
    }

    return $wz['erfuellt'] ? 'erfüllt' : date('d.m.Y', $wz['ablauf']);
}

function berechneMonatssumme($pdo, $member_id, $antrnr, $v_limit = 600) {
    $jahr_monat = substr($antrnr, 1, 4); // JJMM
    $stmt = $pdo->prepare("
        SELECT SUM(fin) as summe
        FROM " . TABLE_ANTRAEGE . "
        WHERE antrst = ?
        AND fin < ?
        AND SUBSTRING(antrnr, 2, 4) = ?
        AND antrnr != ?
    ");
    $stmt->execute([$member_id, $v_limit, $jahr_monat, $antrnr]);
    return (float)($stmt->fetchColumn() ?? 0);
}

// Benutzer-Daten über Adapter
$user = get_member_by_id($pdo, $_SESSION['member_id']);
if (!$user) {
    die("Benutzer nicht gefunden.");
}
$user_aktiv = (int)($user['aktiv'] ?? 0);

if ($user_aktiv <= 10) {
    die("Keine Berechtigung. Sie benötigen aktiv > 10 um Anträge zu bearbeiten.");
}

$antrnr = $_GET['antrnr'] ?? '';
if (!$antrnr) die("Keine Antragsnummer angegeben.");

// Antrag laden (ohne JOIN auf berechtigte)
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_ANTRAEGE . " WHERE antrnr = ?");
$stmt->execute([$antrnr]);
$antrag = $stmt->fetch();
if (!$antrag) die("Antrag nicht gefunden.");

// Antragsteller-Daten über Adapter laden
$antragsteller = get_member_by_id($pdo, $antrag['antrst']);
if ($antragsteller) {
    $antrag['Vorname'] = $antragsteller['first_name'];
    $antrag['Name'] = $antragsteller['last_name'];
    $antrag['AntragstellerKurz'] = substr($antragsteller['first_name'], 0, 1) . '. ' . $antragsteller['last_name'];
} else {
    $antrag['Vorname'] = 'Unbekannt';
    $antrag['Name'] = '';
    $antrag['AntragstellerKurz'] = 'Unbekannt';
}

// Berechtigungsprüfung je nach Status
$prefix = substr($antrnr, 0, 1);
$ist_admin = ($user_aktiv >= 19 || ($user['is_admin'] ?? 0) == 1);

if ($prefix === 'A') {
    // A-Anträge: Alle mit aktiv > 10 dürfen bearbeiten (bereits geprüft oben)
    $darf_bearbeiten = true;
} else {
    // B/VS/X/Z-Anträge: Nur Admins dürfen bearbeiten
    $darf_bearbeiten = $ist_admin;
}

if (!$darf_bearbeiten) {
    die("⚠️ Keine Berechtigung zur Bearbeitung.<br><br>" .
        "Dieser Antrag wurde bereits finalisiert und kann nur noch von Administratoren bearbeitet werden.<br>" .
        "Status: " . htmlspecialchars($prefix) . "-Antrag<br><br>" .
        "<a href='index.php?tab=proposals'>← Zurück zur Liste</a> | " .
        "<a href='antrag_ansehen.php?antrnr=" . urlencode($antrnr) . "'>Antrag ansehen</a>");
}

$saved = false;
$error = null;
$message = null;

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save':
                speichereAntrag($pdo, $antrnr, $_POST, $antrag, $user);

                // Wartezeitverkürzung verarbeiten
                if (isset($_POST['wartezeit_verkuerzen']) && $_POST['wartezeit_verkuerzen'] == 1) {
                    if ($user['aktiv'] >= 18 && $antrag['antrst'] != $user['member_id']) {
                        wartezeitVerkuerzung($pdo, $antrnr, $antrag, $user);
                    }
                }

                $saved = true;
                $message = "Antrag gespeichert.";
                if ($prefix === 'A' && !$ist_admin) {
                    $message .= " <strong style='color: var(--warning);'>⚠️ Hinweis:</strong> Nach dem 'Verbindlich einstellen' kann der Antrag nur noch von Administratoren bearbeitet werden!";
                }
                break;

            case 'finalize':
                finalisiereAntrag($pdo, $antrnr, $_POST, $antrag, $user);
                $saved = true;
                $message = "Antrag wurde verbindlich eingestellt.";
                // Redirect to updated antrnr
                $neue_nr = $_POST['neue_antrnr'] ?? $antrnr;
                header("Location: antrag_bearbeiten.php?antrnr=" . urlencode($neue_nr) . "&msg=finalized");
                exit;

            case 'delete':
                verwerfenAntrag($pdo, $antrnr, $antrag, $user);
                header('Location: index.php?tab=proposals&msg=withdrawn');
                exit;

            case 'verkuerzung':
                wartezeitVerkuerzung($pdo, $antrnr, $antrag, $user);
                $saved = true;
                $message = "Wartezeitverkürzung gespeichert.";
                break;

            case 'request_verkuerzung':
                if ($antrag['antrst'] == $user['member_id'] && substr($antrnr, 0, 1) === 'A') {
                    $user_kurzn = substr($user['first_name'] ?? '', 0, 1) . '. ' . ($user['last_name'] ?? '');
                    $hinweis_wz = $antrag['hinweis'] ?? '';
                    if ($hinweis_wz) $hinweis_wz .= "\n---\n";
                    $hinweis_wz .= date('d.m.Y H:i') . ' (' . $user_kurzn . '): Wartezeitverkürzung beantragt.';
                    $pdo->prepare("UPDATE " . TABLE_ANTRAEGE . " SET hinweis = ? WHERE antrnr = ?")->execute([$hinweis_wz, $antrnr]);
                    $saved = true;
                    $message = "Wartezeitverkürzung beantragt. Zwei Vorstandsmitglieder müssen nun zustimmen.";
                }
                break;
        }

        // Antrag neu laden
        $stmt->execute([$antrnr]);
        $antrag = $stmt->fetch();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Speichern-Funktion
function speichereAntrag($pdo, $antrnr, $post, $antrag, $user) {
    global $bart_config; // Config aus globalem Scope

    $fin = floatval($post['fin'] ?? 0);

    // Admin kann Antragsteller ändern
    $antrst = $antrag['antrst'];
    if ($user['aktiv'] >= 19 && isset($post['antrst']) && $post['antrst'] != $antrag['antrst']) {
        $antrst = $post['antrst'];
    }

    // Monatssummen-Prüfung für Verfügungen
    $v_limit = (float)($bart_config['bart_V_betrag_limit'] ?? 500);
    $monatslimit = (float)($bart_config['bart_verfuegung_monatslimit'] ?? 2000);
    $monatssumme = berechneMonatssumme($pdo, $antrst, $antrnr, $v_limit);
    if ($fin <= $v_limit && ($monatssumme + $fin) > $monatslimit) {
        throw new Exception("Monatliche Verfügungsgrenze von " . number_format($monatslimit, 0, ',', '.') . "€ überschritten! Aktuelle Summe: " . number_format($monatssumme, 2, ',', '.') . "€");
    }

    // Betragsvalidierung basierend auf Antragstyp
    $current_bart = $antrag['bart'] ?? 'V';
    $betrag_check = validiere_betrag($current_bart, $fin, $bart_config);
    if (!$betrag_check['valid']) {
        throw new Exception($betrag_check['message']);
    }

    // Wichtig-Flag Logik - NUR bei action=save ändern, nicht bei finalize
    $wichtig = $antrag['wichtig'] ?? 0; // Aktuellen Wert beibehalten

    // DEBUG
    error_log("DEBUG speichereAntrag START: action=" . ($post['action'] ?? 'none') . ", wichtig_escalate=" . (isset($post['wichtig_escalate']) ? 'JA' : 'NEIN') . ", wichtig_reset=" . (isset($post['wichtig_reset']) ? 'JA' : 'NEIN') . ", wichtig_alt=" . $wichtig);

    // Nur wenn wirklich gespeichert wird (nicht beim Finalisieren)
    if (isset($post['action']) && $post['action'] === 'save') {
        // Wenn wichtig_reset gesetzt ist (Antragsteller nimmt zurück)
        if (isset($post['wichtig_reset']) && $antrag['wichtig'] == $antrag['antrst']) {
            $wichtig = 0;
            error_log("DEBUG: wichtig auf 0 gesetzt (reset)");
        }
        // Wenn wichtig_escalate gesetzt ist (neu oder weiterhin)
        elseif (isset($post['wichtig_escalate']) && !isset($post['wichtig_reset'])) {
            // Auch Antragsteller (aktiv < 18) darf Vorstandsbeschluss anfordern
            $wichtig = $user['member_id'];
            error_log("DEBUG: wichtig auf " . $user['member_id'] . " gesetzt (escalate)");
        } elseif (!isset($post['wichtig_escalate']) && !isset($post['wichtig_reset'])) {
            // Checkbox nicht gesetzt und kein Reset -> wichtig löschen
            $wichtig = 0;
            error_log("DEBUG: wichtig auf 0 gesetzt (keine checkbox)");
        }
    } else {
        error_log("DEBUG: action ist nicht 'save', wichtig bleibt bei " . $wichtig);
    }

    // bart berechnen - aber wichtig hat Vorrang
    // NEUE LOGIK: bart ist meist schon beim Erstellen gesetzt (aus antrag_neu.php)
    // Fallback für alte Anträge: automatische Berechnung basierend auf Betrag
    if ($wichtig > 0) {
        $bart = 'B'; // Immer Vorstandsbeschluss wenn wichtig gesetzt
        error_log("DEBUG: bart='B' (wichtig=" . $wichtig . ")");
    } elseif (!empty($antrag['bart']) && in_array($antrag['bart'], ['V', 'R', 'B'])) {
        // Vorhandenen bart beibehalten (wurde bei Erstellung gewählt)
        $bart = $antrag['bart'];
        error_log("DEBUG: bart='" . $bart . "' (bereits gesetzt)");
    } else {
        // Fallback: automatische Berechnung (für alte Anträge ohne gesetzten bart)
        $bart = ($monatssumme + $fin) >= $v_limit || $fin >= $v_limit ? ($fin <= ($bart_config['bart_R_betrag_limit'] ?? 3000) ? 'R' : 'B') : 'V';
        error_log("DEBUG: bart='" . $bart . "' (automatisch berechnet, fin=" . $fin . ", monatssumme=" . $monatssumme . ")");
    }

    // sofort-Wert
    $sofort = 0;
    if (isset($post['sofort_1'])) $sofort = 1;
    elseif (isset($post['sofort_2'])) $sofort = 2;

    // Hinweis anhängen
    $hinweis = $antrag['hinweis'] ?? '';
    if (!empty($post['neuerhinweis'])) {
        if (!empty($hinweis)) $hinweis .= "\n---\n";
        $user_kurzn = substr($user['first_name'] ?? '', 0, 1) . '. ' . ($user['last_name'] ?? '');
        $hinweis .= date('d.m.Y H:i') . ' (' . $user_kurzn . '): ' . $post['neuerhinweis'];
    }

    // File-Upload-Handling
    for ($i = 1; $i <= 4; $i++) {
        $file_field = "file$i";
        if (isset($_FILES[$file_field]) && $_FILES[$file_field]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/Scans/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $filename = $antrnr . '_f' . $i . '_' . basename($_FILES[$file_field]['name']);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES[$file_field]['tmp_name'], $filepath)) {
                $post[$file_field] = 'Scans/' . $filename;
            }
        } else {
            $post[$file_field] = $antrag[$file_field] ?? null;
        }
    }

    // Abstimmungsregel (nur Admin kann ändern, nur wenn Spalte vorhanden)
    $abstimmregel = $antrag['abstimmregel'] ?? 'einfach';
    if ($user['aktiv'] >= 19 && isset($post['abstimmregel'])) {
        $abstimmregel = $post['abstimmregel'];
    }

    $abstimmregel_sql = TABLE_ANTRAEGE_HAS_ABSTIMMREGEL ? "abstimmregel = ?," : "";
    $update = $pdo->prepare("
        UPDATE " . TABLE_ANTRAEGE . " SET
            antrst = ?, titel = ?, beschluss = ?, begr = ?, pers = ?, sach = ?,
            fintext = ?, fin = ?, bart = ?, verant = ?,
            ressort1 = ?, ressort2 = ?, wichtig = ?,
            int_ext = ?, verein = ?, hinweis = ?, thread = ?,
            file1 = ?, file2 = ?, file3 = ?, file4 = ?,
            filetext1 = ?, filetext2 = ?, filetext3 = ?, filetext4 = ?,
            sofort = ?, durch = ?, zufin = ?, zbem = ?,
            praesenz = ?, verf1 = ?, verf2 = ?, vorher = ?,
            $abstimmregel_sql
            lzugriff = NOW()
        WHERE antrnr = ?
    ");

    $params = [
        $antrst, $post['titel'], $post['beschluss'], $post['begr'] ?? null,
        $post['pers'] ?? null, $post['sach'] ?? null, $post['fintext'] ?? null,
        $fin, $bart, $post['verant'] ?? null,
        $post['ressort1'] ?? null, $post['ressort2'] ?? null, $wichtig,
        $post['int_ext'] ?? null, $post['verein'] ?? 'V', $hinweis, $post['thread'] ?? null,
        $post['file1'], $post['file2'], $post['file3'], $post['file4'],
        $post['filetext1'] ?? null, $post['filetext2'] ?? null,
        $post['filetext3'] ?? null, $post['filetext4'] ?? null,
        $sofort, $post['durch'] ?? null,
        isset($post['zufin']) ? 1 : 0, $post['zbem'] ?? null,
        $post['praesenz'] ?? null,
        !empty($post['verf1']) ? $post['verf1'] : null,
        !empty($post['verf2']) ? $post['verf2'] : null,
        isset($post['vorher']) ? 1 : 0,
    ];
    if (TABLE_ANTRAEGE_HAS_ABSTIMMREGEL) {
        $params[] = $abstimmregel;
    }
    $params[] = $antrnr;
    $update->execute($params);
}

// Finalisieren-Funktion
function finalisiereAntrag($pdo, $antrnr, $post, $antrag, $user) {
    // Speichern mit action=save, damit wichtig-Flag korrekt verarbeitet wird
    $post_save = $post;
    $post_save['action'] = 'save';
    speichereAntrag($pdo, $antrnr, $post_save, $antrag, $user);

    // Antrag neu laden
    $stmt = $pdo->prepare("SELECT * FROM " . TABLE_ANTRAEGE . " WHERE antrnr = ?");
    $stmt->execute([$antrnr]);
    $antrag = $stmt->fetch();

    if (empty($antrag['ressort1'])) throw new Exception("Ressort muss angegeben werden.");
    if (empty($antrag['verant']) || strlen($antrag['verant']) < 3) throw new Exception("Verantwortlicher muss angegeben werden.");

    $prefix = substr($antrnr, 0, 1);
    if ($prefix !== 'A') throw new Exception("Nur Anträge im Editiermodus können finalisiert werden.");

    // DEBUG: Ausgabe von bart
    error_log("DEBUG finalisiereAntrag: bart=" . $antrag['bart'] . ", wichtig=" . ($antrag['wichtig'] ?? '0') . ", fin=" . ($antrag['fin'] ?? '0') . ", verf1=" . ($antrag['verf1'] ?? 'leer') . ", verf2=" . ($antrag['verf2'] ?? 'leer'));

    // Abstimmende setzen je nach Beschlussart
    $vname_fields = [];

    if ($antrag['bart'] === 'V') {
        // Verfügung: Alle mit Verfügungsberechtigung (aktiv >= 14) über Adapter
        $all_members = get_all_members($pdo);
        $verfuegungsber_members = array_filter($all_members, function($m) {
            return isset($m['aktiv']) && $m['aktiv'] >= 14;
        });
        usort($verfuegungsber_members, function($a, $b) {
            $diff = ($b['aktiv'] ?? 0) - ($a['aktiv'] ?? 0);
            return $diff != 0 ? $diff : strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
        });
        $verfuegungsber = array_slice(array_column($verfuegungsber_members, 'member_id'), 0, 6);

        if (empty($verfuegungsber)) {
            throw new Exception("Keine Verfügungsberechtigten gefunden.");
        }

        for ($i = 0; $i < count($verfuegungsber); $i++) {
            $vname_fields['VName' . ($i + 1)] = $verfuegungsber[$i];
        }

    } elseif ($antrag['bart'] === 'R') {
        // Ressortbeschluss: Antragsteller + FVo (oder FVv wenn FVo = Antragsteller)
        $vname_fields['VName1'] = $antrag['antrst'];

        // FVo holen über Adapter
        $all_members = get_all_members($pdo);
        $fvo_member = null;
        foreach ($all_members as $m) {
            if (($m['funktion'] ?? '') === 'FVo' && ($m['aktiv'] ?? 0) >= 18) {
                $fvo_member = $m;
                break;
            }
        }
        $fvo = $fvo_member ? $fvo_member['member_id'] : null;

        if ($fvo && $fvo != $antrag['antrst']) {
            // FVo ist nicht der Antragsteller -> FVo als VName2
            $vname_fields['VName2'] = $fvo;
        } else {
            // FVo ist Antragsteller oder nicht vorhanden -> FVv als VName2
            $fvv_member = null;
            foreach ($all_members as $m) {
                if (($m['funktion'] ?? '') === 'FVv' && ($m['aktiv'] ?? 0) >= 18) {
                    $fvv_member = $m;
                    break;
                }
            }
            $fvv = $fvv_member ? $fvv_member['member_id'] : null;
            if ($fvv) {
                $vname_fields['VName2'] = $fvv;
            } else {
                throw new Exception("Kein Finanzvorstand (FVo/FVv) für Zweitstimme gefunden.");
            }
        }

    } elseif ($antrag['bart'] === 'B') {
        // Vorstandsbeschluss: Nur Vorstände (aktiv = 19) über Adapter
        $all_members = get_all_members($pdo);
        $vorstand_members = array_filter($all_members, function($m) {
            return isset($m['aktiv']) && (int)$m['aktiv'] === 19;
        });
        usort($vorstand_members, function($a, $b) {
            $diff = ($b['aktiv'] ?? 0) - ($a['aktiv'] ?? 0);
            return $diff != 0 ? $diff : strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
        });
        $vorstand = array_slice(array_column($vorstand_members, 'member_id'), 0, 6);

        if (empty($vorstand)) {
            throw new Exception("Keine Vorstandsmitglieder gefunden.");
        }

        for ($i = 0; $i < count($vorstand); $i++) {
            $vname_fields['VName' . ($i + 1)] = $vorstand[$i];
        }
    }

    // Neue Nummer generieren: A → B (zur Abstimmung)
    $neue_nr = 'B' . substr($antrnr, 1);

    // VName-Felder in UPDATE-Statement aufbauen
    $update_parts = ['antrnr = ?'];
    $update_values = [$neue_nr];

    foreach ($vname_fields as $field => $value) {
        $update_parts[] = "$field = ?";
        $update_values[] = $value;
    }

    $update_values[] = $antrnr; // WHERE-Bedingung

    $sql = "UPDATE " . TABLE_ANTRAEGE . " SET " . implode(', ', $update_parts) . " WHERE antrnr = ?";
    $update = $pdo->prepare($sql);
    $update->execute($update_values);

    $_POST['neue_antrnr'] = $neue_nr;

    // TODO: Email-Benachrichtigung an Abstimmende (später)
}

// Löschen-Funktion
function verwerfenAntrag($pdo, $antrnr, $antrag, $user) {
    // Nur Antragsteller, Vorstand oder GF dürfen verwerfen
    $ist_antragsteller = ($antrag['antrst'] == $user['member_id']);
    $ist_vorstand_gf = ($user['aktiv'] >= 19 || $user['funktion'] === 'GF');

    if (!$ist_antragsteller && !$ist_vorstand_gf) {
        throw new Exception("Nur Antragsteller, Vorstand und GF dürfen Anträge verwerfen.");
    }

    // Antragsnummer zu X ändern (zurückgezogen)
    $neue_antrnr = 'X' . substr($antrnr, 1);
    $pdo->prepare("UPDATE " . TABLE_ANTRAEGE . " SET antrnr = ? WHERE antrnr = ?")->execute([$neue_antrnr, $antrnr]);
}

// Wartezeitverkürzung
function wartezeitVerkuerzung($pdo, $antrnr, $antrag, $user) {
    if ($user['aktiv'] < 18 || $antrag['antrst'] == $user['member_id']) {
        throw new Exception("Antragsteller darf nicht der Wartezeitverkürzung zustimmen.");
    }

    $verk1 = $antrag['verk1'] ?? 0;
    $verk2 = $antrag['verk2'] ?? 0;

    if (!$verk1) {
        $update = $pdo->prepare("UPDATE " . TABLE_ANTRAEGE . " SET verk1 = ? WHERE antrnr = ?");
        $update->execute([$user['member_id'], $antrnr]);
    } elseif (!$verk2 && $verk1 != $user['member_id']) {
        $update = $pdo->prepare("UPDATE " . TABLE_ANTRAEGE . " SET verk2 = ? WHERE antrnr = ?");
        $update->execute([$user['member_id'], $antrnr]);
    } else {
        throw new Exception("Wartezeitverkürzung bereits vollständig.");
    }
}

// Daten für UI
$ressorts_where = TABLE_RESSORTS_AKTIV ? "WHERE aktiv = 1" : "";
$ressorts = $pdo->query("SELECT CAST(" . TABLE_RESSORTS_KEY . " AS CHAR) as ressort, Ressort as klartext FROM " . TABLE_RESSORTS . " $ressorts_where ORDER BY Reihenfolge, " . TABLE_RESSORTS_KEY)->fetchAll();
$verfuegungsber = getVerfuegungsberechtigte($pdo);
$abstimmende = getAbstimmungsberechtigte($pdo, $antrag['bart'], $antrag['antrst']);
$wartezeit = berechneWartezeit($antrnr, $antrag['bart'], $bart_config);
$wartezeit_erfuellt = ($wartezeit === 'erfüllt' || ($antrag['verk1'] && $antrag['verk2']));
$second_entity_stmt = $pdo->query("SELECT config_value FROM svconfig WHERE config_key = 'second_entity_name' LIMIT 1");
$second_entity = $second_entity_stmt ? ($second_entity_stmt->fetchColumn() ?: '') : '';
$v_limit_display = (float)($bart_config['bart_V_betrag_limit'] ?? 500);
$monatssumme = berechneMonatssumme($pdo, $antrag['antrst'], $antrnr, $v_limit_display);

$blockiert = false;
$blockierung_grund = [];
if (empty($antrag['ressort1'])) { $blockiert = true; $blockierung_grund[] = "Ressort fehlt"; }
if (empty($antrag['verant']) || strlen($antrag['verant']) < 3) { $blockiert = true; $blockierung_grund[] = "Verantwortlicher fehlt"; }

// Prüfung auf fehlende Verfügungsberechtigte je nach Beschlussart
if ($antrag['bart'] === 'V' && empty($antrag['verf1'])) {
    $blockiert = true;
    $blockierung_grund[] = "Verfügungsberechtigter fehlt";
}
if ($antrag['bart'] === 'R') {
    if (empty($antrag['verf1']) || empty($antrag['verf2'])) {
        $blockiert = true;
        $blockierung_grund[] = "Beide Verfügungsberechtigte müssen angegeben sein";
    } elseif ($antrag['verf1'] == $antrag['verf2']) {
        $blockiert = true;
        $blockierung_grund[] = "Verfügungsberechtigte dürfen nicht identisch sein";
    }
}

$kann_finalisieren = !$blockiert && $wartezeit_erfuellt && substr($antrnr, 0, 1) === 'A' &&
                     ($antrag['antrst'] == $user['member_id'] || $user['aktiv'] >= 18);
$kann_verwerfen = ($antrag['antrst'] == $user['member_id'] || $user['aktiv'] >= 19 || $user['funktion'] === 'GF');
$kann_verkuerzen = ($user['aktiv'] >= 18 && $antrag['antrst'] != $user['member_id'] &&
                    (!$antrag['verk1'] || (!$antrag['verk2'] && $antrag['verk1'] != $user['member_id'])));

// Verf-Namen holen über Adapter
$verf1_name = $verf2_name = '';
if ($antrag['verf1']) {
    $verf1_member = get_member_by_id($pdo, $antrag['verf1']);
    $verf1_name = $verf1_member ? (substr($verf1_member['first_name'], 0, 1) . '. ' . $verf1_member['last_name']) : '';
}
if ($antrag['verf2']) {
    $verf2_member = get_member_by_id($pdo, $antrag['verf2']);
    $verf2_name = $verf2_member ? (substr($verf2_member['first_name'], 0, 1) . '. ' . $verf2_member['last_name']) : '';
}

// Verk-Namen holen (Wartezeitverkürzung) über Adapter
$verk1_name = $verk2_name = '';
if ($antrag['verk1']) {
    $verk1_member = get_member_by_id($pdo, $antrag['verk1']);
    $verk1_name = $verk1_member ? (substr($verk1_member['first_name'], 0, 1) . '. ' . $verk1_member['last_name']) : '';
}
if ($antrag['verk2']) {
    $verk2_member = get_member_by_id($pdo, $antrag['verk2']);
    $verk2_name = $verk2_member ? (substr($verk2_member['first_name'], 0, 1) . '. ' . $verk2_member['last_name']) : '';
}

// Alle User mit aktiv>=9 für Admin-Antragsteller-Auswahl über Adapter
$alle_antragsteller = [];
if ($user['aktiv'] >= 19) {
    $all_members = get_all_members($pdo);
    $filtered = array_filter($all_members, function($m) {
        return isset($m['aktiv']) && $m['aktiv'] >= 9;
    });
    usort($filtered, function($a, $b) {
        $diff = ($b['aktiv'] ?? 0) - ($a['aktiv'] ?? 0);
        return $diff != 0 ? $diff : strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
    });
    // Format anpassen für Kompatibilität
    $alle_antragsteller = array_map(function($m) {
        return [
            'ID' => $m['member_id'],
            'KurzN' => substr($m['first_name'], 0, 1) . '. ' . $m['last_name'],
            'Vorname' => $m['first_name'],
            'Name' => $m['last_name']
        ];
    }, $filtered);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrag: <?= htmlspecialchars($antrag['antrnr']) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="antrag-styles.css">
    <script>
        // Dark Mode automatisch von index.php übernehmen (Cookie/localStorage Sync)
        (function() {
            const hasCookie = document.cookie.split(';').some(c => c.trim().startsWith('darkMode='));
            if (hasCookie && document.cookie.includes('darkMode=enabled')) {
                document.documentElement.classList.add('dark-mode');
            } else if (!hasCookie) {
                const savedDarkMode = localStorage.getItem('darkMode');
                if (savedDarkMode === 'enabled') {
                    document.documentElement.classList.add('dark-mode');
                }
            }
        })();
    </script>
</head>
<body>
    <div class="container">
        <div style="margin-bottom: 20px;">
            <a href="index.php?tab=proposals" class="btn btn-secondary" style="display: inline-block;">← Zurück zur Antragsliste</a>
        </div>

        <div class="header">
            <div>
                <h1>Antrag bearbeiten</h1>
                <span class="antrnr">
                    <?= htmlspecialchars($antrag['antrnr']) ?>
                    <?php
                    $prefix = substr($antrnr, 0, 1);
                    if ($prefix === 'A') echo '<span class="status-badge status-editing">Editiermodus</span>';
                    elseif ($prefix === 'B') echo '<span class="status-badge status-voting">In Abstimmung</span>';
                    elseif (substr($antrnr, 0, 2) === 'VS') echo '<span class="status-badge status-finalized">Beschlossen</span>';
                    ?>
                </span>
            </div>
        </div>

        <?php if ($saved): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($message ?? 'Gespeichert') ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">✗ Fehler: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (substr($antrnr, 0, 1) === 'A'): ?>
            <div class="info-box">
                <strong>Editiermodus:</strong> Anträge werden erst mal im Editiermodus eingestellt und können von allen im Führungsteam bearbeitet werden.
                Bei Verfügungen und Ressortbeschlüssen entscheidet der Antragsteller dann, wann er (nach der in der Verfahrensordnung geregelten Wartezeit) den Antrag endgültig abschickt.
                <?php if ($wartezeit && $wartezeit !== 'erfüllt'): ?>
                <br><strong>Wartezeit bis <?= htmlspecialchars($wartezeit) ?></strong>
                    <?php if ($antrag['verk1'] && $antrag['verk2']): ?>
                        - Wartezeit verkürzt
                    <?php elseif ($antrag['verk1'] || $antrag['verk2']): ?>
                        - Wartezeitverkürzung teilweise bestätigt
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($blockiert): ?>
            <div class="alert alert-error">
                <strong>Der Antrag kann nicht zur Abstimmung gestellt werden:</strong><br>
                Wesentliche Angaben fehlen - kontrolliere das Ressort, den/die Freigabeberechtigten und die Angabe der für die Umsetzung Verantwortlichen.
                <br>Fehlende Felder: <?= implode(', ', $blockierung_grund) ?>
            </div>
        <?php endif; ?>

        <?php
        $v_limit = $v_limit_display;
        $monatslimit = (float)($bart_config['bart_verfuegung_monatslimit'] ?? 2000);
        $summe_mit_aktuell = $monatssumme + ($antrag['fin'] ?? 0);
        if ($monatssumme > 0 && ($antrag['fin'] ?? 0) < $v_limit):
        ?>
            <div class="alert alert-<?= $summe_mit_aktuell > $monatslimit ? 'error' : 'warning' ?>">
                <strong>Monatssumme Verfügungen:</strong> <?= number_format($monatssumme, 2, ',', '.') ?>€ + aktuell <?= number_format($antrag['fin'] ?? 0, 2, ',', '.') ?>€ = <?= number_format($summe_mit_aktuell, 2, ',', '.') ?>€
                <?php if ($summe_mit_aktuell > $monatslimit): ?>
                    <br><strong style="color: #d32f2f;">⚠️ Monatslimit überschritten! Ein Ressortbeschluss ist erforderlich.</strong>
                <?php else: ?>
                    (Max. <?= number_format($monatslimit, 0, ',', '.') ?>€/Monat, sonst Ressortbeschluss)
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <!-- ========== SEKTION 1: DATEN ZUM ANTRAG ========== -->
            <div class="form-section section-gray-1">
                <div class="section-header">Daten zum Antrag:
                    Antragsnummer <?= htmlspecialchars($antrag['antrnr']) ?>
                    <?php if ($user['aktiv'] >= 19): ?>
                        - Antragsteller:
                        <select name="antrst" style="display: inline-block; width: auto; padding: 2px 6px; font-size: 13px; margin-left: 5px;">
                            <?php foreach ($alle_antragsteller as $ast): ?>
                                <option value="<?= $ast['ID'] ?>" <?= $antrag['antrst'] == $ast['ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ast['Vorname'] . ' ' . $ast['Name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        - Antragsteller: <?= htmlspecialchars(($antrag['Vorname'] ?? '') . ' ' . ($antrag['Name'] ?? '')) ?>
                    <?php endif; ?>
                </div>
                <?php
                preg_match('/[A-Z]+(\d{6})/', $antrnr, $_antrnr_m);
                $_yymmdd = $_antrnr_m[1] ?? '';
                $_antrag_datum = $_yymmdd
                    ? date('d.m.Y', strtotime('20'.substr($_yymmdd,0,2).'-'.substr($_yymmdd,2,2).'-'.substr($_yymmdd,4,2)))
                    : '';
                if ($_antrag_datum): ?>
                <div style="font-size: 11px; color: #666; margin-bottom: 8px; padding: 2px 0;">
                    Antrag eingestellt am: <strong><?= $_antrag_datum ?></strong>
                    <?php if ($antrag['lzugriff']): ?>
                        &nbsp;|&nbsp; Letzte Änderung: <?= date('d.m.Y H:i', strtotime($antrag['lzugriff'])) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Zeile 1: Beschlussart - Abstimmung 1 - Abstimmung 2 -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Beschlussart</label>
                        <input type="text" value="<?= htmlspecialchars(get_typ_bezeichnung($antrag['bart'], $bart_config) ?? '') ?>" class="read-only" readonly>
                    </div>

                    <?php if ($antrag['bart'] === 'V' || $antrag['bart'] === 'R'): ?>
                        <div class="form-group">
                            <label for="verf1" class="<?= empty($antrag['verf1']) ? 'warning' : '' ?>">
                                Abstimmung durch (1.) <?= empty($antrag['verf1']) ? '<span class="required">*</span>' : '' ?>
                            </label>
                            <select id="verf1" name="verf1" class="<?= empty($antrag['verf1']) ? 'warning-border' : '' ?>">
                                <option value="">-- Bitte wählen --</option>
                                <?php foreach ($abstimmende as $m): ?>
                                    <?php if (!str_starts_with($m['KurzN'], 'ASt ')): ?>
                                        <option value="<?= $m['ID'] ?>" <?= ($antrag['verf1'] ?? '') == $m['ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['KurzN']) ?><?= $m['Funktion'] ? ' (' . $m['Funktion'] . ')' : '' ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($antrag['verf1'])): ?>
                                <div class="field-warning">
                                    ⚠️ Erforderlich für "Verbindlich einstellen"
                                </div>
                            <?php endif; ?>

                            <?php
                            // Prüfen wer wichtig gesetzt hat
                            $wichtig_von_antragsteller = (!empty($antrag['wichtig']) && $antrag['wichtig'] == $antrag['antrst']);
                            $wichtig_von_vorstand = (!empty($antrag['wichtig']) && $antrag['wichtig'] != $antrag['antrst']);
                            ?>

                            <!-- Checkbox unter Abstimmung -->
                            <div style="margin-top: 8px;">
                                <div class="checkbox-inline">
                                    <input type="checkbox" id="wichtig_escalate" name="wichtig_escalate" value="1" <?= !empty($antrag['wichtig']) ? 'checked' : '' ?>>
                                    <label for="wichtig_escalate" style="margin: 0; font-size: 12px;">Alternativ: Als Abstimmung im Gesamtvorstand einstellen</label>
                                </div>

                                <?php if ($wichtig_von_antragsteller): ?>
                                <div class="checkbox-inline" style="margin-top: 4px;">
                                    <input type="checkbox" id="wichtig_reset" name="wichtig_reset" value="1">
                                    <label for="wichtig_reset" style="margin: 0; font-size: 11px; color: #666;">Beschlussart entsprechend Betrag</label>
                                </div>
                                <?php endif; ?>

                                <?php if ($wichtig_von_vorstand): ?>
                                <div style="font-size: 11px; color: #666; margin-top: 2px;">
                                    (Von Vorstand festgelegt)
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($antrag['bart'] === 'R'): ?>
                        <div class="form-group">
                            <label for="verf2" class="<?= empty($antrag['verf2']) ? 'warning' : '' ?>">
                                Abstimmung durch (2. - Vorstand) <?= empty($antrag['verf2']) ? '<span class="required">*</span>' : '' ?>
                            </label>
                            <select id="verf2" name="verf2" class="<?= empty($antrag['verf2']) ? 'warning-border' : '' ?>">
                                <option value="">-- FVo/FVv --</option>
                                <?php foreach ($verfuegungsber as $m): ?>
                                    <?php if (($m['Funktion'] === 'FVo' || $m['Funktion'] === 'FVv') && !str_starts_with($m['KurzN'], 'ASt ')): ?>
                                        <option value="<?= $m['ID'] ?>" <?= ($antrag['verf2'] ?? '') == $m['ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($m['KurzN']) ?> (<?= $m['Funktion'] ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($antrag['verf2'])): ?>
                                <div class="field-warning">
                                    ⚠️ Erforderlich für "Verbindlich einstellen"
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($antrag['verf1']) && !empty($antrag['verf2']) && $antrag['verf1'] == $antrag['verf2']): ?>
                                <div class="field-warning">
                                    ⚠️ Die Verfügungsberechtigten dürfen nicht identisch sein!
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="form-group">
                            <!-- Bei V: Leer -->
                        </div>
                        <?php endif; ?>

                    <?php elseif ($antrag['bart'] === 'B'): ?>
                        <!-- Hidden input um wichtig-Flag zu erhalten -->
                        <?php if (!empty($antrag['wichtig'])): ?>
                            <input type="hidden" name="wichtig_escalate" value="1">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="praesenz">Abstimmung</label>
                            <select id="praesenz" name="praesenz" onchange="toggleFinalizeButton()">
                                <option value="0" <?= ($antrag['praesenz'] ?? 0) == 0 ? 'selected' : '' ?>>Online</option>
                                <option value="1" <?= ($antrag['praesenz'] ?? 0) == 1 ? 'selected' : '' ?>>Präsenzsitzung</option>
                            </select>
                            <div style="font-size: 11px; color: #666; margin-top: 4px;">
                                Vorstandsbeschluss
                                <?php if (!empty($antrag['wichtig']) && $antrag['wichtig'] != $antrag['antrst']): ?>
                                    (von Vorstand festgelegt)
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <?php if (!empty($antrag['meeting_id'])): ?>
                                <?php
                                // Sitzung und TOP laden
                                $meeting_stmt = $pdo->prepare("SELECT meeting_name, meeting_date FROM svmeetings WHERE meeting_id = ?");
                                $meeting_stmt->execute([$antrag['meeting_id']]);
                                $meeting_info = $meeting_stmt->fetch(PDO::FETCH_ASSOC);

                                $top_stmt = $pdo->prepare("SELECT top_number, title FROM svagenda_items WHERE antrnr = ?");
                                $top_stmt->execute([$antrnr]);
                                $top_info = $top_stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <?php if ($meeting_info): ?>
                                    <div style="padding: 8px; background: #e3f2fd; border-left: 3px solid #2196f3; border-radius: 4px;">
                                        <strong>📅 Sitzung:</strong> <?= htmlspecialchars($meeting_info['meeting_name']) ?><br>
                                        <small><?= date('d.m.Y H:i', strtotime($meeting_info['meeting_date'])) ?> Uhr</small>
                                        <?php if ($top_info): ?>
                                            <br><strong>📋 TOP <?= $top_info['top_number'] ?>:</strong> <?= htmlspecialchars($top_info['title']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="form-group"></div>
                        <div class="form-group"></div>
                    <?php endif; ?>
                </div>

                <!-- Zeile 4: Ressort - Mitwirkendes Ressort - Verantwortlich -->
                <div class="form-row" style="margin-top: 12px;">
                    <div class="form-group">
                        <label for="ressort1">Ressort <span class="required">*</span></label>
                        <select id="ressort1" name="ressort1" required>
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($ressorts as $r): ?>
                                <option value="<?= htmlspecialchars($r['ressort']) ?>" <?= (string)$antrag['ressort1'] === (string)$r['ressort'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['klartext'] ?? $r['ressort']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display: block; margin-top: 3px; color: #d32f2f; font-size: 11px;">Pflichtangabe</small>
                    </div>
                    <div class="form-group">
                        <label for="ressort2">Mitwirkendes Ressort</label>
                        <select id="ressort2" name="ressort2">
                            <option value="">-- Kein weiteres --</option>
                            <?php foreach ($ressorts as $r): ?>
                                <option value="<?= htmlspecialchars($r['ressort']) ?>" <?= (string)$antrag['ressort2'] === (string)$r['ressort'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['klartext'] ?? $r['ressort']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="verant">Verantwortlich für die Umsetzung <span class="required">*</span></label>
                        <input type="text" id="verant" name="verant" value="<?= htmlspecialchars($antrag['verant'] ?? '') ?>" required>
                        <small style="display: block; margin-top: 3px; color: #d32f2f; font-size: 11px;">Pflichtangabe</small>
                    </div>
                </div>

                <!-- Zeile 5: Verein/Zweite Einheit (optional) - Sichtbarkeit - Forum-ID -->
                <div class="form-row">
                    <?php if ($second_entity): ?>
                    <div class="form-group">
                        <label for="verein">Verein / <?= htmlspecialchars($second_entity) ?></label>
                        <select id="verein" name="verein">
                            <option value="V" <?= ($antrag['verein'] ?? 'V') === 'V' ? 'selected' : '' ?>>Verein</option>
                            <option value="S" <?= ($antrag['verein'] ?? '') === 'S' ? 'selected' : '' ?>><?= htmlspecialchars($second_entity) ?></option>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="verein" value="<?= htmlspecialchars($antrag['verein'] ?? 'V') ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="int_ext">Sichtbarkeit</label>
                        <select id="int_ext" name="int_ext">
                            <option value="e" <?= ($antrag['int_ext'] ?? 'e') === 'e' ? 'selected' : '' ?>>Extern (alle Mitglieder)</option>
                            <option value="n" <?= ($antrag['int_ext'] ?? '') === 'n' ? 'selected' : '' ?>>Nicht öffentlich (Führung)</option>
                            <option value="i" <?= ($antrag['int_ext'] ?? '') === 'i' ? 'selected' : '' ?>>Intern (nur Vorstand)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="thread">Forum-ID</label>
                        <input type="number" id="thread" name="thread" min="0" value="<?= htmlspecialchars($antrag['thread'] ?? '') ?>">
                        <?php if (($antrag['thread'] ?? 0) > 0): ?>
                            <a href="https://vorstand.mensa.de/forum/index.php?id=<?= $antrag['thread'] ?>" target="forum" style="font-size: 11px;">→ Forum</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Zeile 6: Abstimmungsregel (nur wenn Spalte vorhanden, für Admins und wenn mehrere Optionen) -->
                <?php
                $enabled_rules = get_enabled_voting_rules($voting_config);
                if (TABLE_ANTRAEGE_HAS_ABSTIMMREGEL && $user['aktiv'] >= 19 && count($enabled_rules) > 1):
                ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="abstimmregel">Abstimmungsregel</label>
                            <select id="abstimmregel" name="abstimmregel" style="width: 100%;">
                                <?php
                                $current_regel = $antrag['abstimmregel'] ?? get_default_voting_rule($voting_config);
                                foreach ($enabled_rules as $key => $rule):
                                ?>
                                    <option value="<?= htmlspecialchars($key) ?>"
                                            <?= $current_regel === $key ? 'selected' : '' ?>
                                            title="<?= htmlspecialchars($rule['desc']) ?>">
                                        <?= htmlspecialchars($rule['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; margin-top: 5px; color: #666; font-size: 11px;">
                                <?= get_voting_rule_description($current_regel, $voting_config) ?>
                            </small>
                        </div>
                        <div class="form-group"></div>
                        <div class="form-group"></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ========== SEKTION 2: ANTRAG ========== -->
            <div class="form-section section-gray-2">
                <div class="section-header">Antrag</div>

                <!-- Titel -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="titel">Titel <span class="required">*</span></label>
                        <input type="text" id="titel" name="titel" value="<?= htmlspecialchars($antrag['titel']) ?>" required>
                        <small style="display: block; margin-top: 3px; color: #d32f2f; font-size: 11px;">Pflichtangabe</small>
                    </div>
                </div>

                <!-- Beschlusstext (nur 2 Zeilen) -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="beschluss">Beschlusstext <span class="required">*</span></label>
                        <textarea id="beschluss" name="beschluss" required style="min-height: 50px; resize: vertical;"><?= htmlspecialchars($antrag['beschluss']) ?></textarea>
                        <small style="display: block; margin-top: 3px; color: #d32f2f; font-size: 11px;">Pflichtangabe</small>
                    </div>
                </div>

                <!-- Begründung -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="begr">Begründung</label>
                        <textarea id="begr" name="begr"><?= htmlspecialchars($antrag['begr'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Betrag -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="fin">Betrag (volle Euro)</label>
                        <input type="number" id="fin" name="fin" step="1" min="0" value="<?= htmlspecialchars($antrag['fin'] ?? '0') ?>" style="max-width: 150px;">
                        <?php
                        // Betragsgrenzen aus Config
                        $v_limit = $bart_config['bart_V_betrag_limit'] ?? 600;
                        $r_limit = $bart_config['bart_R_betrag_limit'] ?? 3000;
                        ?>
                        <div class="help-text">≤<?= $v_limit ?>€=Verfügung | <?= ($v_limit + 1) ?>-<?= $r_limit ?>€=Ressort | ><?= $r_limit ?>€=Vorstand</div>
                    </div>
                </div>

                <!-- Finanzielle - Personelle - Sachliche Auswirkungen -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="fintext">Finanzielle Auswirkungen</label>
                        <textarea id="fintext" name="fintext"><?= htmlspecialchars($antrag['fintext'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="pers">Personelle Auswirkungen</label>
                        <textarea id="pers" name="pers"><?= htmlspecialchars($antrag['pers'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="sach">Sachliche Auswirkungen</label>
                        <textarea id="sach" name="sach"><?= htmlspecialchars($antrag['sach'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ========== SEKTION 3: ANGEBOTE/UNTERLAGEN ========== -->
            <div class="form-section section-gray-3">
                <div class="section-header">Angebote / Unterlagen</div>

                <div class="form-row two-col">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="file-item">
                            <label for="file<?= $i ?>">Datei <?= $i ?></label>
                            <?php if (!empty($antrag["file$i"])): ?>
                                <div style="font-size: 11px; color: #666; margin-bottom: 4px;">
                                    Aktuell: <a href="<?= htmlspecialchars($antrag["file$i"]) ?>" target="_blank"><?= basename($antrag["file$i"]) ?></a>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="file<?= $i ?>" name="file<?= $i ?>" style="font-size: 12px; padding: 4px;">
                            <input type="text" name="filetext<?= $i ?>" placeholder="Beschreibung (z.B. 'Angebot')"
                                   value="<?= htmlspecialchars($antrag["filetext$i"] ?? '') ?>" style="margin-top: 4px;">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- ========== SEKTION 4: VEREINFACHTE FREIGABE (KOMPAKT) ========== -->
            <div class="form-section section-gray-4 compact-box">
                <div class="section-header" style="font-size: 13px; margin-bottom: 8px;">Vereinfachte Freigabe</div>

                <!-- Eine Zeile mit beiden Optionen -->
                <div class="checkbox-inline">
                    <input type="checkbox" id="sofort_1" name="sofort_1" value="1" <?= ($antrag['sofort'] ?? 0) == 1 ? 'checked' : '' ?>
                           onchange="toggleFinanzFreigabe()">
                    <label for="sofort_1" style="margin:0; font-size: 12px;">Wenn Rechnungsbetrag = Angebot, sofort überweisen</label>

                    <span style="margin-left: 20px;">ODER</span>

                    <input type="checkbox" id="sofort_2" name="sofort_2" value="2" <?= ($antrag['sofort'] ?? 0) == 2 ? 'checked' : '' ?>
                           onchange="toggleFinanzFreigabe()" style="margin-left: 20px;">
                    <label for="sofort_2" style="margin:0; font-size: 12px;">Nach Vorprüfung durch:</label>
                    <input type="text" name="durch" placeholder="Name" value="<?= htmlspecialchars($antrag['durch'] ?? '') ?>" style="width: 120px; margin-left: 8px; font-size: 12px;">
                    <span style="margin-left: 4px; font-size: 12px;">überweisen</span>
                </div>

                <?php
                // Prüfen ob User FVo oder FVv ist
                $ist_finanzvorstand = ($user['funktion'] === 'FVo' || $user['funktion'] === 'FVv');
                $sofort_aktiv = (($antrag['sofort'] ?? 0) > 0);
                $vorher_erteilt = (($antrag['vorher'] ?? 0) == 1);
                ?>

                <!-- Finanzvorstand-Freigabe (nur sichtbar wenn sofort aktiv) -->
                <div id="finanz_freigabe_box" style="<?= !$sofort_aktiv ? 'display: none;' : '' ?> margin-top: 8px; padding: 8px; background: #fff8dc; border-left: 3px solid #ffa500; border-radius: 4px;">

                    <?php if (!$vorher_erteilt): ?>
                        <div style="font-size: 12px; color: var(--warning); font-weight: 600; margin-bottom: 6px;">
                            ⚠️ Zustimmung durch Finanzvorstand (FVo bzw. FVv) erforderlich
                        </div>
                    <?php endif; ?>

                    <?php if ($ist_finanzvorstand): ?>
                        <div class="checkbox-inline">
                            <input type="checkbox" id="vorher" name="vorher" value="1" <?= $vorher_erteilt ? 'checked' : '' ?>>
                            <label for="vorher" style="margin:0; font-size: 12px; font-weight: 600;">Zustimmung Finanzvorstand</label>
                        </div>
                        <div style="font-size: 10px; color: #666; margin-top: 2px; padding-left: 20px;">
                            (Nur für FVo und FVv)
                        </div>
                    <?php endif; ?>

                    <?php if ($vorher_erteilt): ?>
                        <div style="margin-top: 4px; font-size: 11px; color: #2e7d32; font-weight: 600;">
                            ✓ Zustimmung Finanzvorstand bzw. Vertreter liegt vor
                        </div>
                    <?php endif; ?>
                </div>

                <div class="checkbox-inline" style="margin-top: 8px;">
                    <input type="checkbox" id="zufin" name="zufin" value="1" <?= ($antrag['zufin'] ?? 0) ? 'checked' : '' ?>>
                    <label for="zufin" style="margin:0; font-size: 12px;">Freigabe durch GF zusätzlich erforderlich</label>
                </div>

                <?php if (!empty($antrag['zbem'])): ?>
                <div style="margin-top: 6px; font-size: 11px; color: #666;">
                    <strong>Bemerkung Finanzreferat:</strong> <?= htmlspecialchars($antrag['zbem']) ?>
                </div>
                <?php endif; ?>
            </div>

            <script>
            function toggleFinanzFreigabe() {
                const box = document.getElementById('finanz_freigabe_box');
                const s1 = document.getElementById('sofort_1');
                const s2 = document.getElementById('sofort_2');

                if (s1.checked || s2.checked) {
                    box.style.display = 'block';
                    // Gegenseitig ausschließen
                    if (s1.checked) s2.checked = false;
                    if (s2.checked) s1.checked = false;
                } else {
                    box.style.display = 'none';
                }
            }
            </script>

            <!-- ========== SEKTION 5: BEMERKUNGEN/HINWEISE ========== -->
            <div class="form-section section-gray-5">
                <div class="section-header">Bemerkungen / Hinweise</div>

                <?php if (!empty($antrag['hinweis'])): ?>
                <div class="hint-box" style="margin-bottom: 10px;">
                    <strong>Bisherige Hinweise:</strong>
                    <div style="margin-top: 6px;">
                        <?= render_hinweis_text($antrag['hinweis']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="neuerhinweis">Neuer Hinweis (wird mit Zeitstempel angehängt)</label>
                    <textarea id="neuerhinweis" name="neuerhinweis" placeholder="Wird mit Zeitstempel angehängt..." style="min-height: 50px;"></textarea>
                </div>
            </div>

            <!-- ========== SEKTION 6: NÄCHSTE SCHRITTE ========== -->
            <div class="form-section section-gray-6">
                <div class="section-header">Nächste Schritte</div>

                <?php if ($wartezeit && $wartezeit !== 'erfüllt' && substr($antrnr, 0, 1) === 'A'): ?>
                    <div class="info-box" style="background: #e3f2fd; border-left-color: #2196f3; margin-bottom: 12px;">
                        <strong>⏳ Wartezeit läuft bis: <?= htmlspecialchars($wartezeit) ?></strong>
                        <?php
                        $wz_tage = $bart_config["bart_{$antrag['bart']}_wartezeit_tage"] ?? 7;
                        ?>
                        <div style="font-size: 11px; color: #666; margin-top: 4px;">
                            (Konfigurierte Wartezeit für diesen Antragstyp: <?= $wz_tage ?> Tage)
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($kann_verwerfen && substr($antrnr, 0, 1) === 'A'): ?>
                    <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107; margin-bottom: 12px;">
                        <strong>Nur für Vorstand, GF und Antragsteller:</strong>
                        Wenn der Antrag obsolet ist und nicht mehr benötigt wird, kannst du ihn löschen.
                    </div>
                <?php endif; ?>

                <?php if (substr($antrnr, 0, 1) === 'A' && $wartezeit && $wartezeit !== 'erfüllt' && !$antrag['verk1'] && !$antrag['verk2'] && $antrag['antrst'] == $user['member_id']): ?>
                    <div style="background: #fff8dc; padding: 10px; margin-bottom: 12px; border-radius: 4px; border-left: 3px solid #ffa500;">
                        <div style="font-size: 12px; font-weight: 600; margin-bottom: 6px;">Wartezeitverkürzung beantragen</div>
                        <div style="font-size: 11px; color: #555; margin-bottom: 8px;">
                            Zwei Vorstandsmitglieder, die nicht selbst Antragsteller sind, können die Wartezeit vorzeitig aufheben.
                        </div>
                        <button type="submit" form="wz-request-form" class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;">
                            ⏱ Wartezeitverkürzung anfordern
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($kann_verkuerzen && substr($antrnr, 0, 1) === 'A' && $wartezeit !== 'erfüllt'): ?>
                    <div style="background: #fff8dc; padding: 10px; margin-bottom: 12px; border-radius: 4px; border-left: 3px solid #ffa500;">
                        <div style="font-size: 12px; font-weight: 600; margin-bottom: 6px;">Wartezeitverkürzung bestätigen</div>
                        <div class="checkbox-inline">
                            <input type="checkbox" id="wartezeit_verkuerzen" name="wartezeit_verkuerzen" value="1" form="wz-approve-form" checked>
                            <label for="wartezeit_verkuerzen" style="margin: 0; font-size: 12px;">Ich stimme der Wartezeitverkürzung zu</label>
                        </div>
                        <div style="margin-top: 8px;">
                            <button type="submit" form="wz-approve-form" class="btn btn-primary" style="font-size: 11px; padding: 4px 10px;">
                                ✓ Zustimmung speichern
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (($antrag['verk1'] || $antrag['verk2']) && $wartezeit !== 'erfüllt'): ?>
                    <div style="background: #e8f5e9; padding: 10px; margin-bottom: 12px; border-radius: 4px; border-left: 3px solid #4caf50;">
                        <div style="font-size: 12px; color: #2e7d32;">
                            <?php if ($antrag['verk1'] && $antrag['verk2']): ?>
                                ✓ Zustimmung zur Verkürzung der Wartezeit liegt vor von <?= htmlspecialchars($verk1_name) ?> und <?= htmlspecialchars($verk2_name) ?>
                            <?php elseif ($antrag['verk1']): ?>
                                ✓ Zustimmung zur Verkürzung der Wartezeit liegt vor von <?= htmlspecialchars($verk1_name) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($wartezeit === 'erfüllt' && ($antrag['bart'] === 'V' || $antrag['bart'] === 'R')): ?>
                    <div class="status-box">
                        Die Wartezeit ist erfüllt; du kannst den Antrag zur Abstimmung stellen.
                        <?php if (($antrag['verf1'] ?? '') == $antrag['antrst']): ?>
                            <span style="color: var(--warning); font-weight: 600;">⚠️ Bitte nicht vergessen, dort dann abzustimmen!</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="actions" style="border-top: none; padding-top: 0; margin-top: 8px;">
                    <button type="submit" name="action" value="save" class="btn btn-primary">💾 Speichern</button>

                    <?php if ($kann_finalisieren): ?>
                        <button type="submit" name="action" value="finalize" class="btn btn-success" id="finalizeButton"
                                onclick="return confirm('Antrag verbindlich einstellen? Nicht mehr änderbar!');">
                            ✅ Verbindlich einstellen
                        </button>
                    <?php elseif (substr($antrnr, 0, 1) === 'A'): ?>
                        <button type="button" class="btn btn-success" disabled title="<?= implode(', ', $blockierung_grund) ?>">
                            Verbindlich einstellen <?= !$wartezeit_erfuellt ? '(Wartezeit)' : '' ?>
                        </button>
                    <?php endif; ?>

                    <?php if ($kann_verwerfen && substr($antrnr, 0, 1) === 'A'): ?>
                        <button type="submit" name="action" value="delete" class="btn btn-danger"
                                onclick="return confirm('Antrag löschen? Der Antrag wird als zurückgezogen markiert (X-Präfix).');">
                            🗑️ Löschen
                        </button>
                    <?php endif; ?>

                    <a href="index.php?tab=proposals" class="btn btn-secondary">❌ Abbrechen</a>
                </div>
            </div>

        </form>

        <!-- Mini-Formulare für Wartezeitverkürzung (außerhalb des Hauptformulars) -->
        <form id="wz-request-form" method="post" action="">
            <input type="hidden" name="action" value="request_verkuerzung">
        </form>
        <form id="wz-approve-form" method="post" action="">
            <input type="hidden" name="action" value="verkuerzung">
        </form>

    </div>

<script>
// Drag & Drop File Upload
document.addEventListener('DOMContentLoaded', function() {
    const fileItems = document.querySelectorAll('.file-item');

    fileItems.forEach((item, index) => {
        const fileInput = item.querySelector('input[type="file"]');
        const fileNumber = index + 1;

        // Styling für Drop Zone
        item.style.position = 'relative';
        item.style.border = '2px dashed #ddd';
        item.style.borderRadius = '8px';
        item.style.padding = '15px';
        item.style.transition = 'all 0.3s ease';

        // Drag Events
        ['dragenter', 'dragover'].forEach(eventName => {
            item.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                item.style.borderColor = '#2196f3';
                item.style.backgroundColor = '#f0f7ff';
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            item.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                item.style.borderColor = '#ddd';
                item.style.backgroundColor = 'transparent';
            });
        });

        item.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;

                // Zeige Dateinamen an
                const fileName = files[0].name;
                let feedback = item.querySelector('.drop-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'drop-feedback';
                    feedback.style.marginTop = '8px';
                    feedback.style.fontSize = '12px';
                    feedback.style.color = '#2196f3';
                    feedback.style.fontWeight = '600';
                    item.appendChild(feedback);
                }
                feedback.textContent = '✓ Datei ausgewählt: ' + fileName;
            }
        });

        // Auch bei normalem File-Input Feedback zeigen
        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length > 0) {
                const fileName = fileInput.files[0].name;
                let feedback = item.querySelector('.drop-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'drop-feedback';
                    feedback.style.marginTop = '8px';
                    feedback.style.fontSize = '12px';
                    feedback.style.color = '#2196f3';
                    feedback.style.fontWeight = '600';
                    item.appendChild(feedback);
                }
                feedback.textContent = '✓ Datei ausgewählt: ' + fileName;
            }
        });
    });
});

// Auto-expand textareas based on content
function autoExpandTextarea(textarea) {
    // Reset height to auto to get the correct scrollHeight
    textarea.style.height = 'auto';
    // Set height to scrollHeight + some padding
    const newHeight = Math.max(50, textarea.scrollHeight);
    textarea.style.height = newHeight + 'px';
}

// Apply auto-expand to all textareas
document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('textarea');

    textareas.forEach(function(textarea) {
        // Set initial height based on content
        autoExpandTextarea(textarea);

        // Add input event listener for dynamic expansion
        textarea.addEventListener('input', function() {
            autoExpandTextarea(this);
        });

        // Remove fixed height constraints for auto-expansion (except beschluss which should stay compact)
        if (textarea.id !== 'beschluss' && textarea.id !== 'neuerhinweis') {
            textarea.style.maxHeight = 'none';
            textarea.style.minHeight = '50px';
        }
    });
});

// Toggle Finalize Button visibility based on praesenz value
function toggleFinalizeButton() {
    const praesenzSelect = document.getElementById('praesenz');
    const finalizeButton = document.getElementById('finalizeButton');

    if (praesenzSelect && finalizeButton) {
        // Hide button wenn Präsenzsitzung (value="1") ausgewählt ist
        if (praesenzSelect.value === '1') {
            finalizeButton.style.display = 'none';
        } else {
            finalizeButton.style.display = '';
        }
    }
}

// Initial state setzen beim Laden der Seite
document.addEventListener('DOMContentLoaded', function() {
    toggleFinalizeButton();
});
</script>
</body>
</html>
