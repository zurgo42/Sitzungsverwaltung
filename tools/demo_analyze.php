<?php
/**
 * demo_analyze.php - Analysiert die demo_data.json Datei
 *
 * Zeigt genau, welche Daten in der JSON-Datei enthalten sind, prüft die Abdeckung aller Funktionen
 * (Feature-Matrix), die Datenschutz-Ausschlüsse, den Erinnerungs-Antrag und die Rollen-Zuordnung.
 *
 * Browser:  tools/demo_analyze.php
 * CLI:      php tools/demo_analyze.php [Pfad/zur/demo_data.json]   (ohne Datenbank, Exit-Code 1 bei Fehlern)
 */

require_once __DIR__ . '/demo_common.php';

/**
 * Führt alle Prüfungen auf den geladenen Daten aus.
 * @param array $data  decodierte JSON (mit 'tables')
 * @param array $cols  optional: [tabelle => [spaltennamen]] aus der Datenbank (Schema-Abgleich)
 * @return array Liste aus ['group','label','status'(ok|fail|warn),'detail']
 */
function demo_analyze_checks(array $data, array $cols = [])
{
    $T = $data['tables'] ?? [];
    $out = [];
    $add = function ($group, $label, $ok, $detail = '', $warn_only = false) use (&$out) {
        $out[] = ['group' => $group, 'label' => $label, 'status' => $ok ? 'ok' : ($warn_only ? 'warn' : 'fail'), 'detail' => $detail];
    };
    $rows = function ($t) use ($T) {
        foreach ([$t, demo_real_table($t)] as $k) if (!empty($T[$k]) && is_array($T[$k])) return $T[$k];
        return [];
    };
    $where = function ($t, callable $f) use ($rows) { return array_values(array_filter($rows($t), $f)); };
    $distinct = function ($t, $col) use ($rows) { return array_values(array_unique(array_column($rows($t), $col))); };
    $has_all = function (array $have, array $need) { return array_values(array_diff($need, $have)); };
    $builder = (($data['generator'] ?? '') === 'tools/demo_build.php');

    // Bezugstag
    $anchor = $data['anchor_date'] ?? null;
    foreach ($rows('svconfig') as $c) if (($c['config_key'] ?? '') === DEMO_ANCHOR_KEY) $anchor = $anchor ?: $c['config_value'];
    if (!$anchor) $anchor = substr($data['export_date'] ?? date('Y-m-d'), 0, 10);
    $dayoff = function ($d) use ($anchor) { return (int)(new DateTime($anchor))->diff(new DateTime(substr($d, 0, 10)))->format('%r%a'); };

    // ---------- Admin-Einstellungen ----------
    $g = 'Admin-Einstellungen (svconfig, Ressorts)';
    $keys = array_column($rows('svconfig'), 'config_key');
    $need = ['bart_B_abstimmung_tage', 'meeting_system_url', 'voting_default_rule', 'bart_show_betrag_in_liste', 'bart_pflicht_bei_betrag',
             'bart_verfuegung_monatslimit', 'second_entity_name', 'agenda_reminder_default_emails', 'opinion_standalone_url',
             'terminplanung_standalone_url', 'mail_from_name', 'documents_mode', DEMO_ANCHOR_KEY];
    foreach (['V', 'R', 'B'] as $t) foreach (['aktiv', 'bezeichnung', 'beschreibung', 'betrag_aktiv', 'betrag_limit', 'wartezeit_aktiv', 'wartezeit_tage', 'freigabe_vereinfacht'] as $f) $need[] = "bart_{$t}_$f";
    for ($i = 0; $i <= 19; $i++) $need[] = "aktiv_level_$i";
    foreach (['einfach', 'absolut', 'mehrheit_stimmber', 'zweidrittel', 'einstimmig'] as $r) array_push($need, "voting_rule_{$r}_label", "voting_rule_{$r}_desc", "voting_enable_$r");
    foreach (['ressort', 'antrag', 'beschluss'] as $x) array_push($need, "term_{$x}_singular", "term_{$x}_plural");
    $miss = $has_all($keys, $need);
    $add($g, 'Alle Konfigurationsschlüssel (aktiv-Level, Antragstypen, Abstimmungsregeln, Terminologie, Mail/URLs)', !$miss, $miss ? 'fehlt: ' . implode(', ', array_slice($miss, 0, 8)) . (count($miss) > 8 ? ' ...' : '') : count($keys) . ' Schlüssel');
    $dauer = 7;
    foreach ($rows('svconfig') as $c) if ($c['config_key'] === 'bart_B_abstimmung_tage') $dauer = max(1, (int)$c['config_value']);
    $add($g, 'Ressorts (aktiv und inaktiv)', count($rows('svressorts')) >= 4 && count($distinct('svressorts', 'aktiv')) >= 2, count($rows('svressorts')) . ' Ressorts', true);
    $add($g, 'Laufzeit-Schlüssel nicht im Export (' . implode(', ', DEMO_RUNTIME_CONFIG_KEYS) . ')', !array_intersect($keys, DEMO_RUNTIME_CONFIG_KEYS));

    // ---------- Mitglieder und Berechtigungen ----------
    $g = 'Mitglieder und Berechtigungen';
    $mem = $rows('svmembers');
    $roles = array_map('strtolower', $distinct('svmembers', 'role'));
    $miss = $has_all($roles, ['vorstand', 'gf', 'assistenz', 'fuehrungsteam', 'mitglied']);
    $add($g, 'Alle Rollen (vorstand, gf, assistenz, fuehrungsteam, mitglied)', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : count($mem) . ' Mitglieder');
    $add($g, 'Admin-Flag gesetzt (und nicht bei allen)', count($where('svmembers', function ($m) { return !empty($m['is_admin']); })) > 0 && count($where('svmembers', function ($m) { return empty($m['is_admin']); })) > 0);
    $add($g, 'Vertraulich-Flag gesetzt (und nicht bei allen)', count($where('svmembers', function ($m) { return !empty($m['is_confidential']); })) > 0 && count($where('svmembers', function ($m) { return empty($m['is_confidential']); })) > 0);
    $add($g, 'Mindestens ein inaktives Mitglied (is_active = 0)', count($where('svmembers', function ($m) { return isset($m['is_active']) && (int)$m['is_active'] === 0; })) > 0, '', true);
    $no_af = $where('svmembers', function ($m) { return !isset($m['aktiv']) || !array_key_exists('funktion', $m) || $m['funktion'] === null || $m['funktion'] === ''; });
    $add($g, 'aktiv und funktion bei allen Mitgliedern gesetzt', !$no_af, $no_af ? count($no_af) . ' ohne Wert' : 'für VTool-Variante (berechtigte) nötig');
    // Rolle aus aktiv/Funktion ableiten (wie BerechtigteAdapter) und mit svmembers.role vergleichen
    $adapterFile = dirname(__DIR__) . '/adapters/MemberAdapter.php';
    if (is_file($adapterFile)) {
        require_once $adapterFile;
        $ad = new BerechtigteAdapter(null);
        $rm = new ReflectionMethod($ad, 'mapRole');
        $diff = [];
        foreach ($mem as $m) {
            $derived = $rm->invoke($ad, (string)($m['funktion'] ?? ''), (int)($m['aktiv'] ?? 0));
            if ($derived !== strtolower($m['role'] ?? '')) $diff[] = ($m['first_name'] ?? '?') . ': role=' . $m['role'] . ' aber aktiv/Funktion ergibt ' . $derived;
        }
        $add($g, 'Rolle stimmt mit der aus aktiv/Funktion abgeleiteten VTool-Rolle überein (Standalone = VTool)', !$diff, implode('; ', array_slice($diff, 0, 3)));
    }
    $voters = $where('svmembers', function ($m) { return (int)($m['aktiv'] ?? 0) === 19; });
    $add($g, 'Mindestens 3 Vorstände mit aktiv = 19, darunter FVo und FVv (Abstimmung B/R)', count($voters) >= 3 && in_array('FVo', array_column($voters, 'funktion')) && in_array('FVv', array_column($voters, 'funktion')), count($voters) . ' mit aktiv 19');
    $bad_mail = $where('svmembers', function ($m) { return !demo_is_fake_email($m['email'] ?? ''); });
    $add($g, 'Alle Mitglieder-Mailadressen sind Fake-Adressen (example.*)', !$bad_mail);
    $add($g, 'Alle Mitglieder haben ein Passwort-Hash (Demo-Passwort "' . DEMO_PASSWORD . '")', count($where('svmembers', function ($m) { return password_verify(DEMO_PASSWORD, $m['password_hash'] ?? ''); })) === count($mem) && $mem, 'welcome.php meldet mit diesem Passwort an');

    // ---------- Sitzungen ----------
    $g = 'Sitzungen, TOPs, Protokolle';
    $st = $distinct('svmeetings', 'status');
    $miss = $has_all($st, ['preparation', 'active', 'ended', 'protocol_ready', 'archived']);
    $add($g, 'Sitzungen in allen Status', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : implode(', ', $st));
    $add($g, 'Teilnehmer je Sitzung', count($rows('svmeeting_participants')) >= count($rows('svmeetings')) * 3, count($rows('svmeeting_participants')) . ' Teilnehmer-Zeilen');
    $add($g, 'Vertrauliche TOPs (is_confidential = 1, Nr. >= 101)', count($where('svagenda_items', function ($i) { return !empty($i['is_confidential']); })) > 0);
    $add($g, 'TOP-Kommentare mit Prioritätsbewertung', count($where('svagenda_comments', function ($c) { return $c['priority_rating'] !== null; })) > 0);
    $add($g, 'Live- und Nach-Sitzungs-Kommentare', $rows('svagenda_live_comments') && $rows('svagenda_post_comments'));
    $add($g, 'Persönliche Notizen', (bool)$rows('svagenda_personal_notes'));
    $add($g, 'Anhänge (nur Metadaten)', (bool)$rows('svagenda_attachments'));
    $add($g, 'TOP-Abstimmungen (offen und abgeschlossen) mit Stimmen', count($distinct('svvotings', 'status')) >= 2 && $rows('svvotes'));
    $add($g, 'TOPs mit Antragsbezug (antrnr) und Abstimmungsergebnis', count($where('svagenda_items', function ($i) { return !empty($i['antrnr']); })) > 0 && count($where('svagenda_items', function ($i) { return !empty($i['vote_result']); })) > 0);
    $add($g, 'Protokolle (public + confidential) und Änderungswünsche', count($distinct('svprotocols', 'protocol_type')) >= 2 && $rows('svprotocol_change_requests'));
    $add($g, 'Sitzungs-Beschlüsse (svbeschluesse) und MV-Beschlüsse (svmvbeschluesse)', $rows('svbeschluesse') && $rows('svmvbeschluesse'));

    // ---------- ToDos, Abwesenheiten ----------
    $g = 'ToDos und Abwesenheiten';
    $miss = $has_all($distinct('svtodos', 'status'), ['open', 'in progress', 'done']);
    $add($g, 'ToDos in den Status open / in progress / done (+ delayed)', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : implode(', ', $distinct('svtodos', 'status')));
    $td = $rows('svtodos');
    $overdue = $where('svtodos', function ($t) use ($dayoff) { return $t['status'] !== 'done' && !empty($t['due_date']) && $dayoff($t['due_date']) < 0; });
    $soon = $where('svtodos', function ($t) use ($dayoff) { return $t['status'] !== 'done' && !empty($t['due_date']) && $dayoff($t['due_date']) >= 0 && $dayoff($t['due_date']) <= 3; });
    $later = $where('svtodos', function ($t) use ($dayoff) { return $t['status'] !== 'done' && !empty($t['due_date']) && $dayoff($t['due_date']) > 3; });
    $add($g, 'ToDo-Fälligkeiten relativ zum Bezugstag: überfällig, bald fällig, später', $overdue && $soon && $later, count($overdue) . ' überfällig / ' . count($soon) . ' bald / ' . count($later) . ' später (Bezugstag ' . $anchor . ')');
    $add($g, 'Zugewiesene und private ToDos, ToDo-Historie', count($where('svtodos', function ($t) { return !empty($t['assigned_to_member_id']); })) === count($td) && $where('svtodos', function ($t) { return !empty($t['is_private']); }) && $rows('svtodo_log'));
    $cur = $where('svabsences', function ($a) use ($dayoff) { return $dayoff($a['start_date']) <= 0 && $dayoff($a['end_date']) >= 0 && !empty($a['substitute_member_id']); });
    $up = $where('svabsences', function ($a) use ($dayoff) { return $dayoff($a['start_date']) > 0 && !empty($a['substitute_member_id']); });
    $add($g, 'Abwesenheit mit Vertretung: eine laufende und eine kommende (relativ zum Bezugstag)', $cur && $up, count($cur) . ' laufend / ' . count($up) . ' kommend');

    // ---------- Umfragen ----------
    $g = 'Terminumfragen und Meinungsbilder';
    $miss = $has_all($distinct('svpolls', 'status'), ['open', 'finalized']);
    $add($g, 'Terminumfragen offen und finalisiert (mit Antworten, Teilnehmern, Terminvorschlägen)', !$miss && $rows('svpoll_responses') && $rows('svpoll_participants') && $rows('svpoll_dates'), $miss ? 'fehlt: ' . implode(', ', $miss) : '');
    $miss = $has_all($distinct('svopinion_polls', 'status'), ['active', 'ended']);
    $add($g, 'Meinungsbilder aktiv und beendet', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : '');
    $miss = $has_all($distinct('svopinion_polls', 'target_type'), ['individual', 'list', 'public']);
    $add($g, 'Meinungsbild-Zielgruppen individual / list / public', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : '');
    $add($g, 'Meinungsbild-Antworten mit gewählten Optionen, Vorlagen', $rows('svopinion_responses') && $rows('svopinion_response_options') && $rows('svopinion_answer_templates'));
    $tokens = array_filter(array_merge(array_column($rows('svopinion_polls'), 'access_token'), array_column($rows('svpolls'), 'access_token')));
    $real = array_filter($tokens, function ($t) { return strpos($t, 'demo-') !== 0; });
    $add($g, 'Externe Zugangs-Tokens sind erkennbar fiktiv ("demo-…")', !$real, $tokens ? count($tokens) . ' Token' : 'keine', true);

    // ---------- Dokumente, Texte ----------
    $g = 'Dokumente, Texte, Benachrichtigungen';
    $add($g, 'Dokumente in mehreren Kategorien (nur Metadaten)', count($distinct('svdocuments', 'category')) >= 5, count($rows('svdocuments')) . ' Dokumente, ' . count($distinct('svdocuments', 'category')) . ' Kategorien');
    $add($g, 'Kollaborative Texte aktiv und finalisiert, mit Absätzen/Versionen', count($distinct('svcollab_texts', 'status')) >= 2 && $rows('svcollab_text_paragraphs') && $rows('svcollab_text_versions'));
    $add($g, 'Benachrichtigungen (gelesen/ungelesen) und Einstellungen', count($distinct('svnotifications', 'is_read')) >= 2 && $rows('svnotification_prefs'));
    $add($g, 'Feedback-Einträge', (bool)$rows('feedback'), '', true);

    // ---------- Anträge ----------
    $g = 'Anträge und Beschlüsse';
    $ant = $rows('svantraege');
    $pre = function ($p) use ($where) { return $where('svantraege', function ($a) use ($p) { return strpos($a['antrnr'], $p) === 0; }); };
    $A = $pre('A'); $B = array_values(array_filter($pre('B'), function ($a) { return strlen($a['antrnr']) >= 8; })); $VS = $pre('VS'); $X = $pre('X'); $Z = $pre('Z');
    $add($g, 'Stufen A / B / VS / X / Z vorhanden', $A && $B && $VS && $X && $Z, count($A) . ' A, ' . count($B) . ' B, ' . count($VS) . ' VS, ' . count($X) . ' X, ' . count($Z) . ' Z');
    $add($g, 'A-Antrag ohne Titel (gerade angelegt)', count($where('svantraege', function ($a) { return $a['antrnr'][0] === 'A' && trim($a['titel'] ?? '') === ''; })) > 0);
    $miss = $has_all(array_column($ant, 'bart'), ['V', 'R', 'B']);
    $add($g, 'Beschlussarten V, R und B', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : '');
    $add($g, 'Finanzielle Beträge (fin/betrag)', count($where('svantraege', function ($a) { return (float)($a['betrag'] ?? 0) > 0; })) >= 3);
    $add($g, 'Ressorts zugeordnet', count($where('svantraege', function ($a) { return !empty($a['ressort1']); })) >= 5);
    $add($g, 'Verschiedene Abstimmungsregeln', count($distinct('svantraege', 'abstimmregel')) >= 3, implode(', ', $distinct('svantraege', 'abstimmregel')));
    $nob = array_column(array_filter($B, function ($a) { return empty($a['b_date']); }), 'antrnr');
    $add($g, 'Alle B-Anträge haben b_date', !$nob, $nob ? implode(', ', $nob) : '');
    // Stimmen-Zustände
    $votum = [];
    foreach ($B as $a) for ($i = 1; $i <= 6; $i++) if (!empty($a["VName$i"])) $votum[(int)($a["Votum$i"] ?? 0)] = true;
    $miss = $has_all(array_map('strval', array_keys($votum)), ['0', '1', '5', '6']);
    $add($g, 'Stimmen: fehlend (0), abgegeben (1), Bedenkzeit (5), befangen (6)', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : '');
    $add($g, 'Beschlossene Anträge haben Beschlussbuch-Eintrag (svbeschluesse), davon einer intern (int_ext = i)', count($rows('svbeschluesse')) >= count($VS) && count($where('svbeschluesse', function ($b) { return ($b['int_ext'] ?? '') === 'i'; })) > 0, count($rows('svbeschluesse')) . ' Einträge');

    // ---------- Erinnerungs-Antrag ----------
    $g = 'Erinnerungs-Antrag (24h-Abstimmungserinnerung)';
    $rem = $where('svantraege', function ($a) { return $a['antrnr'] === DEMO_REMINDER_ANTRNR; });
    $rem = $rem ? $rem[0] : null;
    $add($g, 'Antrag ' . DEMO_REMINDER_ANTRNR . ' vorhanden (als B-Antrag in Abstimmung)', (bool)$rem, $builder ? '' : 'nur im Stand von demo_build.php enthalten', !$builder);
    if ($rem) {
        $want = (new DateTime($anchor))->modify("-{$dauer} days")->format('Y-m-d');
        $add($g, 'b_date = Bezugstag - Abstimmungsdauer (' . $dauer . ' Tage) = ' . $want, ($rem['b_date'] ?? null) === $want, 'ist: ' . ($rem['b_date'] ?? 'leer'));
        $missing = [];
        for ($i = 1; $i <= 6; $i++) if (!empty($rem["VName$i"]) && in_array((int)($rem["Votum$i"] ?? 0), [0, 5], true)) $missing[] = $rem["VName$i"];
        $add($g, 'Mindestens eine Stimme fehlt', count($missing) >= 1, count($missing) . ' fehlend (Mitglieder-IDs ' . implode(',', $missing) . ')');
        $open = true;
        foreach (DEMO_REMINDER_OPEN_SLOTS as $slot) if (!in_array((int)($rem["Votum$slot"] ?? 0), [0, 5], true)) $open = false;
        $add($g, 'Zurückzusetzende Slots (DEMO_REMINDER_OPEN_SLOTS) sind offen', $open);
        $young = array_filter($B, function ($a) use ($want) { return $a['antrnr'] !== DEMO_REMINDER_ANTRNR && !empty($a['b_date']) && $a['b_date'] <= $want; });
        $add($g, 'Alle anderen B-Anträge haben jüngere b_date-Werte (werden nie automatisch ausgewertet)', !$young, $young ? implode(', ', array_column($young, 'antrnr')) : '');
    }
    $add($g, 'svvote_reminders nicht im Export', !isset($T['svvote_reminders']) || !$T['svvote_reminders']);

    // ---------- Kalender ----------
    $g = 'Kalender';
    $miss = $has_all($distinct('svevents', 'recurrence'), ['weekly', 'biweekly', 'monthly', 'yearly', 'none']);
    $add($g, 'Wiederholungen weekly / biweekly / monthly / yearly', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : '');
    $miss = $has_all($distinct('svevents', 'event_type'), ['sitzung', 'treffen', 'abwesenheit', 'frist', 'sonstiges']);
    $add($g, 'Alle Ereignistypen', !$miss, $miss ? 'fehlt: ' . implode(', ', $miss) : '');
    $add($g, 'Nur-Vorstand-Termin (target_type = roles, vorstand)', count($where('svevents', function ($e) { return $e['target_type'] === 'roles' && $e['target_roles'] === 'vorstand'; })) > 0);
    $add($g, 'Listen-Termin mit Teilnehmern', count($where('svevents', function ($e) { return $e['target_type'] === 'list'; })) > 0 && $rows('svevents_participants'));
    $add($g, 'Ganztägig, abgesagt (cancelled) und gelöscht (deleted)', count($where('svevents', function ($e) { return !empty($e['all_day']); })) > 0 && count(array_intersect($distinct('svevents', 'status'), ['cancelled', 'deleted'])) === 2);

    // ---------- Datenschutz ----------
    $g = 'Datenschutz';
    $found = array_values(array_filter(array_keys(DEMO_EXCLUDED_TABLES), function ($t) use ($T) { return !empty($T[$t]); }));
    $add($g, 'Keine Daten aus ausgeschlossenen Tabellen (' . implode(', ', array_keys(DEMO_EXCLUDED_TABLES)) . ')', !$found, $found ? 'enthalten: ' . implode(', ', $found) : '');
    $add($g, 'Tabelle ' . DEMO_DERIVED_TABLE . ' nicht in der JSON (wird aus svmembers abgeleitet)', empty($T[DEMO_DERIVED_TABLE]));
    $mails = demo_find_foreign_emails($T, 20);
    $add($g, 'Keine Mailadressen außerhalb von example.com/.org/.net', !$mails, $mails ? count($mails) . '+ Treffer, z. B. ' . $mails[0]['table'] . '.' . $mails[0]['column'] . ' (Zeile ' . $mails[0]['row'] . ')' : '');
    $ips = 0;
    foreach ($T as $tn => $trs) if (is_array($trs)) foreach ($trs as $r) if (is_array($r)) foreach ($r as $c => $v) if (stripos($c, 'ip_address') !== false && !empty($v)) { $ips++; }
    $add($g, 'Keine IP-Adressen', $ips === 0, $ips ? "$ips Werte in *ip_address*" : '');
    $sess = 0;
    foreach ($T as $tn => $trs) if (is_array($trs)) foreach ($trs as $r) if (is_array($r) && !empty($r['session_token']) && strpos($r['session_token'], 'demo-') !== 0) $sess++;
    $add($g, 'Session-Tokens externer Teilnehmer sind erkennbar fiktiv (demo-…)', $sess === 0, $sess ? "$sess echte Tokens" : '');
    $ext_bad = $where('svexternal_participants', function ($e) { return !demo_is_fake_email($e['email'] ?? '') || !empty($e['ip_address']); });
    $add($g, 'Externe Teilnehmer nur mit Fake-Mail und ohne IP', !$ext_bad);

    // ---------- Schema ----------
    if ($cols) {
        $g = 'Schema-Abgleich mit der Datenbank';
        $unknown = [];
        foreach ($T as $tn => $trs) {
            $real = demo_real_table($tn);
            if (!isset($cols[$real]) || !is_array($trs) || !$trs) continue;
            $names = [];
            foreach ($trs as $r) if (is_array($r)) $names += array_fill_keys(array_keys($r), true);
            $u = array_diff(array_keys($names), $cols[$real]);
            if ($u) $unknown[] = "$real: " . implode(', ', $u);
        }
        $add($g, 'Alle Spalten der JSON existieren in der Datenbank (sonst wird beim Import verworfen)', !$unknown, implode(' | ', $unknown), true);
    }
    return $out;
}

// ---------------------------------------------------------------- CLI
if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $file = $argv[1] ?? (__DIR__ . '/demo_data.json');
    if (!is_file($file)) { fwrite(STDERR, "Datei nicht gefunden: $file\n"); exit(2); }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data) || !isset($data['tables'])) { fwrite(STDERR, "Ungültige JSON: " . json_last_error_msg() . "\n"); exit(2); }
    $fails = 0; $warns = 0; $group = null;
    foreach (demo_analyze_checks($data) as $c) {
        if ($c['group'] !== $group) { $group = $c['group']; echo "\n== $group\n"; }
        echo sprintf("  [%s] %s%s\n", strtoupper($c['status']), $c['label'], $c['detail'] !== '' ? "  -> " . $c['detail'] : '');
        if ($c['status'] === 'fail') $fails++;
        if ($c['status'] === 'warn') $warns++;
    }
    echo "\nErgebnis: " . ($fails ? "$fails Fehler" : 'keine Fehler') . ", $warns Warnungen\n";
    exit($fails ? 1 : 0);
}

require_once __DIR__ . '/../config.php';

$demo_file = __DIR__ . '/demo_data.json';

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo-Daten Analyse</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .highlight {
            background-color: #fff3cd;
            font-weight: bold;
        }
        .good {
            background-color: #d4edda;
        }
        .bad {
            background-color: #f8d7da;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 300px;
        }
    </style>
</head>
<body>
    <h1>🔍 Demo-Daten Analyse</h1>

    <?php
    if (!file_exists($demo_file)) {
        echo '<div class="error">';
        echo '<h3>❌ Datei nicht gefunden</h3>';
        echo '<p>Die Datei <code>' . htmlspecialchars($demo_file) . '</code> existiert nicht.</p>';
        echo '</div>';
        exit;
    }

    $file_size = filesize($demo_file);
    $file_modified = filemtime($demo_file);

    echo '<div class="card">';
    echo '<h2>📄 Datei-Informationen</h2>';
    echo '<p><strong>Pfad:</strong> <code>' . htmlspecialchars($demo_file) . '</code></p>';
    echo '<p><strong>Größe:</strong> ' . number_format($file_size / 1024, 2) . ' KB (' . number_format($file_size) . ' Bytes)</p>';
    echo '<p><strong>Geändert:</strong> ' . date('d.m.Y H:i:s', $file_modified) . '</p>';
    echo '</div>';

    // JSON laden und analysieren
    try {
        $json_content = file_get_contents($demo_file);

        if ($json_content === false) {
            throw new Exception('Fehler beim Lesen der Datei');
        }

        $demo_data = json_decode($json_content, true);

        if ($demo_data === null) {
            $json_error = json_last_error_msg();
            throw new Exception('JSON Parse Error: ' . $json_error);
        }

        echo '<div class="card">';
        echo '<h2>📊 JSON-Struktur</h2>';
        echo '<p><strong>Export-Datum:</strong> ' . htmlspecialchars($demo_data['export_date'] ?? 'Nicht gesetzt') . '</p>';
        echo '<p><strong>Version:</strong> ' . htmlspecialchars($demo_data['export_version'] ?? 'Nicht gesetzt') . '</p>';
        echo '</div>';

        // Tabellen-Analyse
        $total_records = 0;
        $empty_tables = [];
        $filled_tables = [];

        echo '<div class="card">';
        echo '<h2>📋 Tabellen-Inhalt (Detailliert)</h2>';
        echo '<p style="color: #666; margin-bottom: 15px;"><strong>Wichtig:</strong> Dies zeigt, was WIRKLICH in der JSON-Datei steht!</p>';
        echo '<table>';
        echo '<tr><th>Tabelle</th><th>Anzahl Datensätze</th><th>Status</th><th>Erste Zeile (Beispiel)</th></tr>';

        foreach ($demo_data['tables'] as $table => $rows) {
            // WICHTIG: Prüfen ob $rows ein Array ist
            if (!is_array($rows)) {
                echo '<tr class="highlight">';
                echo '<td><strong>' . htmlspecialchars($table) . '</strong></td>';
                echo '<td colspan="3" style="color: red;">⚠️ FEHLER: Keine Array-Struktur! Typ: ' . gettype($rows) . '</td>';
                echo '</tr>';
                continue;
            }

            $count = count($rows);
            $total_records += $count;

            $row_class = '';
            $status = '';
            if ($count === 0) {
                $empty_tables[] = $table;
                $row_class = 'highlight';
                $status = '❌ LEER (0)';
            } else {
                $filled_tables[] = $table;
                $row_class = 'good';
                $status = '✅ ' . $count . ' Zeilen';
            }

            // Beispieldaten anzeigen (erste Zeile)
            $example = '';
            if ($count > 0) {
                $first_row = $rows[0];
                if (is_array($first_row) && !empty($first_row)) {
                    $example_fields = array_slice($first_row, 0, 3); // Erste 3 Felder
                    $example_parts = [];
                    foreach ($example_fields as $k => $v) {
                        $val_display = is_array($v) ? 'Array' : (is_null($v) ? 'NULL' : substr((string)$v, 0, 30));
                        $example_parts[] = htmlspecialchars($k . '=' . $val_display);
                    }
                    $example = '<small style="font-family: monospace;">' . implode(', ', $example_parts) . '</small>';
                } else {
                    $example = '<small style="color: red;">⚠️ Erste Zeile ist kein Array oder leer!</small>';
                }
            } else {
                $example = '<small style="color: #999;">-</small>';
            }

            echo '<tr class="' . $row_class . '">';
            echo '<td><strong>' . htmlspecialchars($table) . '</strong></td>';
            echo '<td><strong>' . $count . '</strong></td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $example . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '<p style="margin-top: 15px;"><strong>Gesamt-Datensätze (sollten importiert werden):</strong> ' . $total_records . '</p>';
        echo '</div>';

        // Warnung bei leeren wichtigen Tabellen
        $critical_tables = ['svmeetings', 'svagenda_items', 'svmeeting_participants'];
        $missing_critical = array_intersect($critical_tables, $empty_tables);

        if (!empty($missing_critical)) {
            echo '<div class="error">';
            echo '<h3>❌ PROBLEM: Wichtige Tabellen sind leer!</h3>';
            echo '<p>Die folgenden wichtigen Tabellen enthalten keine Daten:</p>';
            echo '<ul>';
            foreach ($missing_critical as $table) {
                echo '<li><strong>' . htmlspecialchars($table) . '</strong></li>';
            }
            echo '</ul>';
            echo '<p><strong>Ursache:</strong> Diese JSON-Datei wurde von einem System ohne Meeting-Daten exportiert!</p>';
            echo '<p><strong>Lösung:</strong> Exportiere die Daten vom RICHTIGEN Quellsystem (localhost mit echten Meetings).</p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h3>✅ JSON-Datei sieht gut aus!</h3>';
            echo '<p>Alle wichtigen Tabellen enthalten Daten.</p>';
            echo '</div>';
        }

        // Feature-Matrix, Datenschutz, Erinnerungs-Antrag, Rollen-Mapping
        $db_cols = [];
        try {
            foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $db_cols[$t] = array_column($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
            }
        } catch (Exception $e) {
            $db_cols = [];
        }
        $checks = demo_analyze_checks($demo_data, $db_cols);
        $n_ok = $n_warn = $n_fail = 0;
        foreach ($checks as $c) { if ($c['status'] === 'ok') $n_ok++; elseif ($c['status'] === 'warn') $n_warn++; else $n_fail++; }
        echo '<div class="card">';
        echo '<h2>🧩 Abdeckung und Datenschutz (Feature-Matrix)</h2>';
        echo '<p><strong>' . $n_ok . '</strong> bestanden, <strong>' . $n_warn . '</strong> Hinweise, <strong>' . $n_fail . '</strong> Fehler. '
           . 'Bezugstag der relativen Datumsangaben: <code>' . htmlspecialchars($demo_data['anchor_date'] ?? 'unbekannt') . '</code>'
           . (!empty($demo_data['generator']) ? ', erzeugt von <code>' . htmlspecialchars($demo_data['generator']) . '</code>' : ' (Export aus einer Live-Datenbank)') . '.</p>';
        echo '<table><tr><th style="width:60px">Status</th><th>Prüfung</th><th>Detail</th></tr>';
        $group = null;
        foreach ($checks as $c) {
            if ($c['group'] !== $group) {
                $group = $c['group'];
                echo '<tr><td colspan="3" style="background:#e9ecef;font-weight:bold">' . htmlspecialchars($group) . '</td></tr>';
            }
            $cls = $c['status'] === 'ok' ? 'good' : ($c['status'] === 'warn' ? 'highlight' : 'bad');
            $icon = $c['status'] === 'ok' ? '✅' : ($c['status'] === 'warn' ? '⚠️' : '❌');
            echo '<tr class="' . $cls . '"><td>' . $icon . '</td><td>' . htmlspecialchars($c['label']) . '</td><td><small>' . htmlspecialchars($c['detail']) . '</small></td></tr>';
        }
        echo '</table>';
        echo '<h3>Aus Datenschutzgründen nie exportiert/importiert</h3><ul>';
        foreach (array_merge(DEMO_EXCLUDED_TABLES, DEMO_BUILDER_ONLY_TABLES) as $t => $why) {
            $cnt = isset($demo_data['tables'][$t]) && is_array($demo_data['tables'][$t]) ? count($demo_data['tables'][$t]) : 0;
            echo '<li><code>' . htmlspecialchars($t) . '</code>: ' . htmlspecialchars($why)
               . (($cnt > 0 && isset(DEMO_EXCLUDED_TABLES[$t])) ? ' <strong style="color:#dc3545">⚠️ ' . $cnt . ' Zeilen in der JSON (werden beim Import ignoriert)</strong>' : '') . '</li>';
        }
        echo '</ul></div>';
        // Datenbank-Tabellen ohne Eintrag in der JSON (wichtigste Bereiche)
        $antraege_tbl = defined('TABLE_ANTRAEGE') ? TABLE_ANTRAEGE : 'svantraege';
        echo '<div class="card"><h2>🧾 Rollen-Zuordnung (Standalone svmembers ↔ VTool berechtigte)</h2>';
        echo '<table><tr><th>Mitglied</th><th>role</th><th>aktiv</th><th>funktion</th><th>Admin</th><th>vertraulich</th><th>aktiv?</th></tr>';
        foreach (($demo_data['tables']['svmembers'] ?? []) as $m) {
            echo '<tr><td>' . htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) . '</td><td>' . htmlspecialchars($m['role'] ?? '')
               . '</td><td>' . (int)($m['aktiv'] ?? 0) . '</td><td>' . htmlspecialchars((string)($m['funktion'] ?? '')) . '</td><td>' . (!empty($m['is_admin']) ? 'ja' : '') . '</td><td>'
               . (!empty($m['is_confidential']) ? 'ja' : '') . '</td><td>' . (isset($m['is_active']) && !$m['is_active'] ? 'nein' : 'ja') . '</td></tr>';
        }
        echo '</table></div>';
        // Zusammenfassung
        echo '<div class="info">';
        echo '<h3>📈 Zusammenfassung</h3>';
        echo '<p><strong>Tabellen mit Daten:</strong> ' . count($filled_tables) . ' (' . implode(', ', array_map('htmlspecialchars', $filled_tables)) . ')</p>';
        echo '<p><strong>Leere Tabellen:</strong> ' . count($empty_tables) . ' (' . implode(', ', array_map('htmlspecialchars', $empty_tables)) . ')</p>';
        echo '</div>';

        // Erste paar Zeilen der JSON anzeigen
        echo '<div class="card">';
        echo '<h3>🔍 JSON-Inhalt (Vorschau)</h3>';
        echo '<pre>' . htmlspecialchars(substr($json_content, 0, 2000)) . '...</pre>';
        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<h3>❌ Fehler beim Analysieren</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>

    <div class="card">
        <h3>🔗 Weiter zu...</h3>
        <p>
            <a href="demo_export.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;">📤 Export</a>
            <a href="demo_import.php" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">📥 Import</a>
        </p>
    </div>

</body>
</html>
