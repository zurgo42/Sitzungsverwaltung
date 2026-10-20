<?php
/**
 * module_config.php - Modulschalter (Bereiche der Sitzungsverwaltung ein-/ausschalten)
 *
 * Feste Bereiche (nicht schaltbar): Sitzungen/Tagesordnung, Protokolle, Termin finden, ToDo, Dokumente,
 * Nachrichten, Admin. Optional (hier schaltbar): Anträge/Beschlüsse, Meinungsbild, Kalender, MV-Beschlüsse,
 * Textbearbeitung, Abwesenheiten und der Hinweiskasten am Seitenanfang.
 *
 * Gespeichert in svconfig (Schlüssel module_<name>, Kategorie 'modules'). Fehlt ein Schlüssel, ist der Bereich
 * eingeschaltet - bestehende Installationen ändern sich also nicht. Diese Datei bleibt PHP-7.4-kompatibel.
 */

/** Definition der optionalen Bereiche: Schlüssel => [Bezeichnung, Beschreibung, in der Einfachausstattung an?] */
function sv_modul_definitionen() {
    return [
        'proposals'      => ['📋 Anträge/Beschlüsse', 'Anträge, Abstimmungen, Beschlussbuch', 0],
        'opinion'        => ['📊 Meinungsbild', 'Meinungsbilder und Umfragen mit Link für Externe', 0],
        'kalender'       => ['📅 Kalender', 'Gemeinsamer Kalender mit Terminen und Fristen', 0],
        'mv_beschluesse' => ['📜 MV-Beschlüsse', 'Beschlüsse der Mitgliederversammlung', 0],
        'texte'          => ['✍️ Textbearbeitung', 'Gemeinsames Bearbeiten von Texten', 0],
        'vertretung'     => ['🏖️ Abwesenheiten', 'Abwesenheiten und Vertretungen', 0],
        'hinweise'       => ['🔔 Hinweiskasten', 'Kasten mit offenen Aufgaben, Terminen und Abwesenheiten am Seitenanfang', 0],
    ];
}

/** Zuordnung Tab => Modul (Tabs ohne Eintrag sind immer verfügbar) */
function sv_modul_tab_zuordnung() {
    return [
        'proposals' => 'proposals', 'opinion' => 'opinion', 'kalender' => 'kalender',
        'mv_beschluesse' => 'mv_beschluesse', 'texte' => 'texte', 'vertretung' => 'vertretung',
    ];
}

/** Werte eines Profils: 'voll' = alles an, 'einfach' = nur feste Bereiche */
function sv_modul_profil($profil) {
    $werte = [];
    foreach (sv_modul_definitionen() as $key => $def) {
        $werte[$key] = ($profil === 'einfach') ? (int)$def[2] : 1;
    }
    return $werte;
}

/** Ist der Bereich eingeschaltet? Ohne Eintrag (oder bei Fehlern) gilt: ja. */
function sv_module_enabled($pdo, $key, $neu_laden = false) {
    static $cache = null;
    if ($cache === null || $neu_laden) {
        $cache = [];
        try {
            $st = $pdo->query("SELECT config_key, config_value FROM svconfig WHERE config_key LIKE 'module\\_%'");
            if ($st) {
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $cache[substr($r['config_key'], 7)] = ((string)$r['config_value'] !== '0');
                }
            }
        } catch (\Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? true;
}

/** Für eigenständige Skripte: bricht mit einer Meldung ab, wenn der Bereich ausgeschaltet ist */
function sv_module_guard($pdo, $key) {
    if (sv_module_enabled($pdo, $key)) return;
    $defs = sv_modul_definitionen();
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:600px;margin:40px auto">'
        . '<h3>Bereich nicht aktiviert</h3><p>' . htmlspecialchars($defs[$key][0] ?? $key)
        . ' ist in dieser Installation ausgeschaltet.</p><p><a href="index.php">Zur Startseite</a></p>';
    exit;
}

/** Schalter speichern (je Bereich 0/1) */
function sv_module_speichern($pdo, array $werte) {
    $defs = sv_modul_definitionen();
    $up = $pdo->prepare("
        INSERT INTO svconfig (config_key, config_value, config_type, description, category)
        VALUES (?, ?, 'boolean', ?, 'modules')
        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
    ");
    foreach ($defs as $key => $def) {
        $up->execute(['module_' . $key, empty($werte[$key]) ? '0' : '1', 'Bereich ein-/ausschalten: ' . $def[1]]);
    }
    sv_module_enabled($pdo, 'proposals', true);   // Zwischenspeicher neu laden
}

/** Profil ('voll' oder 'einfach') anwenden */
function sv_module_profil_anwenden($pdo, $profil) {
    sv_module_speichern($pdo, sv_modul_profil($profil === 'einfach' ? 'einfach' : 'voll'));
}
