<?php
/**
 * demo_build.php - Deterministischer Demo-Daten-Generator
 *
 * Erzeugt tools/demo_data.json im selben Format wie demo_export.php aus frei erfundenen Daten
 * (keine Live-Datenbank nötig). Die Datei wird danach mit tools/demo_import.php eingespielt.
 *
 * AUFRUF
 *   CLI:      php tools/demo_build.php [--anchor=YYYY-MM-DD] [--out=Pfad] [--stdout] [--quiet]
 *   Browser:  tools/demo_build.php (System-Admin-Passwort, DEMO_MODE_ENABLED = true erforderlich)
 *   Altes demo.php im Hauptverzeichnis ruft ebenfalls diesen Builder auf.
 *
 * Deterministisch: gleicher Anker (--anchor, Standard = heute) => byte-gleiche JSON-Datei.
 * Alle relativen Datumsangaben beziehen sich auf den Anker; tools/update_demo_dates.php
 * verschiebt sie danach täglich (svconfig.demo_anchor_date merkt den Bezugstag).
 *
 * DEMO-LOGIN: alle Demo-Benutzer haben das Passwort "test123" (welcome.php meldet mit genau
 * diesem Passwort per Klick an). E-Mail-Adressen sind ausschließlich @example.com.
 *
 * ROLLEN-MAPPING (svmembers  <->  VTool berechtigte; Quelle der Wahrheit ist svmembers, die
 * Tabelle berechtigte wird beim Import daraus abgeleitet, siehe demo_common.php):
 *
 *  Mitglied (ID)          role           aktiv  Funktion  Admin  vertraulich
 *  Max Mustermann (1)     vorstand       19     FVo       ja     ja   (Finanzvorstand)
 *  Erika Musterfrau (2)   vorstand       19     FVv       nein   ja   (Vertreterin Finanzvorstand)
 *  Lukas Beispiel (3)     vorstand       19     Vo        nein   ja
 *  Julia Sommer (4)       gf             18     GF        ja     ja
 *  Thomas Winter (5)      assistenz      17     VA        ja     ja   (VA mit 17: aktiv 18 wäre im Adapter "gf")
 *  Sabine Herbst (6)      assistenz      11     SV        ja     ja
 *  Hans Lehmann (7)       fuehrungsteam  17     RL        nein   ja   (Ressortleitung Finanzen)
 *  Anna Frühling (8)      fuehrungsteam  15     RL        nein   nein
 *  Petra Nord (9)         fuehrungsteam  14     RL        nein   nein (zweite Ressortleitung)
 *  Karl Prüfer (10)       Mitglied       3      FP        nein   nein (Kassenprüfer, nur lesen)
 *  Mia Technik (11)       Mitglied       10     AD        ja     nein (technischer Admin)
 *  Rolf Ehemals (12)      Mitglied       0      Vx        nein   nein (ehemalig, is_active = 0)
 *
 * Abstimmungsberechtigte (wie in antrag_bearbeiten.php): B = aktiv 19 (Max, Erika, Lukas);
 * V = die 6 höchsten mit aktiv >= 14; R = Antragsteller + FVo (bzw. FVv).
 *
 * ERINNERUNGS-ANTRAG: DEMO_REMINDER_ANTRNR (B26010101) hat b_date = heute - Abstimmungsdauer,
 * d. h. letzter Abstimmungstag ist heute; die 24h-Erinnerung (voting_reminder.php) greift. Votum
 * von Lukas und Max fehlt. update_demo_dates.php hält diesen Zustand täglich aufrecht.
 *
 * Nicht enthalten (Datenschutz, siehe DEMO_EXCLUDED_TABLES): svmail_queue, svmail_notifications,
 * svpush_subscriptions, svexternal_access_log, svaudit_log, svadmin_log, svdocument_downloads,
 * svvote_reminders, svcollab_text_locks, protokoll, berechtigte (wird abgeleitet).
 */

require_once __DIR__ . '/demo_common.php';

class DemoBuilder
{
    /** @var array<string,array> */
    public $tables = [];
    private $anchor;
    private $ids = [];
    private $bDauer = 7;

    public function __construct($anchor_date)
    {
        $this->anchor = new DateTimeImmutable($anchor_date . ' 00:00:00', new DateTimeZone('Europe/Berlin'));
    }

    public function anchorDate() { return $this->anchor->format('Y-m-d'); }

    /** Datum/Zeit: $off Tage relativ zum Anker, Uhrzeit "H:i" oder "H:i:s" */
    public function dt($off, $time = '00:00')
    {
        $t = strlen($time) === 5 ? $time . ':00' : $time;
        return $this->anchor->modify(($off >= 0 ? '+' : '') . $off . ' days')->format('Y-m-d') . ' ' . $t;
    }
    public function day($off) { return substr($this->dt($off), 0, 10); }
    public function ymd($off) { return $this->anchor->modify(($off >= 0 ? '+' : '') . $off . ' days')->format('ymd'); }

    /** Zeile hinzufügen; fehlt die ID-Spalte, wird fortlaufend vergeben. Gibt die Zeile zurück. */
    public function add($table, array $row, $idcol = null)
    {
        if ($idcol !== null && !isset($row[$idcol])) {
            $this->ids[$table] = ($this->ids[$table] ?? 0) + 1;
            $row = [$idcol => $this->ids[$table]] + $row;
        } elseif ($idcol !== null) {
            $this->ids[$table] = max($this->ids[$table] ?? 0, (int)$row[$idcol]);
        }
        $this->tables[$table][] = $row;
        return $row;
    }

    public function build()
    {
        $this->members();
        $this->config();
        $this->ressorts();
        $this->meetings();
        $this->antraege();
        $this->agendaItems();
        $this->todos();
        $this->absences();
        $this->events();
        $this->termine();
        $this->meinungsbilder();
        $this->documents();
        $this->collabTexts();
        $this->notifications();
        $this->misc();
        $this->tables['svconfig'][] = $this->cfgRow(DEMO_ANCHOR_KEY, $this->anchorDate(), 'text',
            'Bezugstag der relativen Demo-Datumsangaben (wird von update_demo_dates.php täglich fortgeschrieben)', 'system');

        // Ausgabe in Import-Reihenfolge
        $ordered = [];
        foreach (demo_table_order() as $t) {
            if (isset($this->tables[$t])) $ordered[$t] = $this->tables[$t];
        }
        foreach ($this->tables as $t => $rows) {
            if (!isset($ordered[$t])) $ordered[$t] = $rows;
        }
        return [
            'export_date'    => $this->anchorDate() . ' 12:00:00',
            'export_version' => '1.1',
            'generator'      => 'tools/demo_build.php',
            'anchor_date'    => $this->anchorDate(),
            'tables'         => $ordered,
        ];
    }

    // =====================================================================
    // Mitglieder
    // =====================================================================
    private function members()
    {
        $hash = crypt(DEMO_PASSWORD, '$2y$10$DemoSitzungsSalt2026ab');
        // id, Vorname, Nachname, E-Mail, role, aktiv, Funktion, admin, vertraulich, aktiv(is_active)
        $m = [
            [1,  'Max',    'Mustermann', 'max',    'vorstand',      19, 'FVo', 1, 1, 1],
            [2,  'Erika',  'Musterfrau', 'erika',  'vorstand',      19, 'FVv', 0, 1, 1],
            [3,  'Lukas',  'Beispiel',   'lukas',  'vorstand',      19, 'Vo',  0, 1, 1],
            [4,  'Julia',  'Sommer',     'julia',  'gf',            18, 'GF',  1, 1, 1],
            [5,  'Thomas', 'Winter',     'thomas', 'assistenz',     17, 'VA',  1, 1, 1],
            [6,  'Sabine', 'Herbst',     'sabine', 'assistenz',     11, 'SV',  1, 1, 1],
            [7,  'Hans',   'Lehmann',    'hans',   'fuehrungsteam', 17, 'RL',  0, 1, 1],
            [8,  'Anna',   'Frühling',   'anna',   'fuehrungsteam', 15, 'RL',  0, 0, 1],
            [9,  'Petra',  'Nord',       'petra',  'fuehrungsteam', 14, 'RL',  0, 0, 1],
            [10, 'Karl',   'Prüfer',     'karl',   'Mitglied',      3,  'FP',  0, 0, 1],
            [11, 'Mia',    'Technik',    'mia',    'Mitglied',      10, 'AD',  1, 0, 1],
            [12, 'Rolf',   'Ehemals',    'rolf',   'Mitglied',      0,  'Vx',  0, 0, 0],
        ];
        foreach ($m as [$id, $fn, $ln, $mail, $role, $aktiv, $fkt, $adm, $conf, $act]) {
            $this->add('svmembers', [
                'member_id'         => $id,
                'membership_number' => sprintf('DEMO%04d', $id),
                'email'             => $mail . '@example.com',
                'password_hash'     => $hash,
                'first_name'        => $fn,
                'last_name'         => $ln,
                'role'              => $role,
                'aktiv'             => $aktiv,
                'funktion'          => $fkt,
                'is_admin'          => $adm,
                'is_active'         => $act,
                'is_confidential'   => $conf,
                'created_at'        => $this->dt(-400, '09:00'),
            ], 'member_id');
        }
    }

    private function mname($id)
    {
        foreach ($this->tables['svmembers'] as $m) {
            if ($m['member_id'] == $id) return $m['first_name'] . ' ' . $m['last_name'];
        }
        return '';
    }
    private function mkurz($id)
    {
        foreach ($this->tables['svmembers'] as $m) {
            if ($m['member_id'] == $id) return $m['first_name'] . mb_substr($m['last_name'], 0, 1);
        }
        return 'ID' . $id;
    }

    // =====================================================================
    // Admin-Einstellungen (svconfig)
    // =====================================================================
    private function cfgRow($key, $val, $type, $desc, $cat)
    {
        return ['config_key' => $key, 'config_value' => (string)$val, 'config_type' => $type,
                'description' => $desc, 'category' => $cat, 'updated_by' => null];
    }

    private function config()
    {
        $c = function ($key, $val, $type, $desc, $cat) {
            $this->tables['svconfig'][] = $this->cfgRow($key, $val, $type, $desc, $cat);
        };

        // Aktiv-Level 0-19 (Tab Admin > Berechtigungslevel)
        $levels = [
            0 => 'Beschlussdatenbanken-Zugriff', 1 => 'Spezielle Zugriffe (Wiederaufnahmen)', 2 => 'Kassenfunktionen',
            3 => 'Finanzprüfer', 4 => 'NN (reserviert)', 5 => 'NN (reserviert)', 6 => 'NN (reserviert)', 7 => 'NN (reserviert)',
            8 => 'Finanzverantwortliche in Teams', 9 => 'Finanzverantwortliche in Teams', 10 => 'Temporäre Aktive',
            11 => 'Teamleiter', 12 => 'Projektleiter', 13 => 'Projektleitung mit Budget', 14 => 'Zweiter Ressortleiter',
            15 => 'Ressortleitung', 16 => 'Sonderaufgaben', 17 => 'Ressortleitung Finanzen',
            18 => 'Geschäftsführung/Admin', 19 => 'Vorstandsmitglied',
        ];
        foreach ($levels as $n => $txt) $c("aktiv_level_$n", $txt, 'text', "Berechtigung für Level $n", 'aktiv_levels');

        // Antragstypen
        $typen = [
            'V' => ['Verfügung', 'Kleinere Ausgaben und operative Entscheidungen', 1, 500, 1, 3, 1],
            'R' => ['Ressortbeschluss', 'Entscheidungen innerhalb eines Ressorts/Bereichs', 1, 2000, 1, 7, 1],
            'B' => ['Vorstandsbeschluss', 'Entscheidungen des Vorstands', 0, 0, 1, 14, 0],
        ];
        $name = ['V' => 'Verfügung', 'R' => 'Ressortbeschluss', 'B' => 'Vorstandsbeschluss'];
        foreach ($typen as $t => [$bez, $besch, $betrag_aktiv, $limit, $wz_aktiv, $wz_tage, $vereinf]) {
            $c("bart_{$t}_aktiv", '1', 'boolean', "Typ $t ({$name[$t]}) aktiviert", 'antragstypen');
            $c("bart_{$t}_bezeichnung", $bez, 'text', "Anzeigename für Typ $t", 'antragstypen');
            $c("bart_{$t}_beschreibung", $besch, 'text', 'Erklärung des Typs', 'antragstypen');
            $c("bart_{$t}_betrag_aktiv", $betrag_aktiv, 'boolean', "Betragsgrenze für Typ $t aktiv", 'antragstypen');
            $c("bart_{$t}_betrag_limit", $limit, 'number', "Betragsgrenze für Typ $t in Euro", 'antragstypen');
            $c("bart_{$t}_wartezeit_aktiv", $wz_aktiv, 'boolean', "Wartezeit für Typ $t aktiv", 'antragstypen');
            $c("bart_{$t}_wartezeit_tage", $wz_tage, 'number', "Wartezeit für Typ $t in Tagen", 'antragstypen');
            $c("bart_{$t}_freigabe_vereinfacht", $vereinf, 'boolean', "Vereinfachte Freigabe für Typ $t", 'antragstypen');
        }
        $c('bart_B_abstimmung_tage', $this->bDauer, 'number', 'Abstimmungsdauer für Typ B in Tagen (Fristablauf-Auswertung via Cron)', 'antragstypen');
        $c('bart_show_betrag_in_liste', '1', 'boolean', 'Beträge in Antragsliste anzeigen', 'antragstypen');
        $c('bart_pflicht_bei_betrag', '100', 'number', 'Ab welchem Betrag ist Betragsangabe Pflicht', 'antragstypen');
        $c('bart_verfuegung_monatslimit', '2000', 'number', 'Maximale Summe aller Verfügungen pro Monat (Ressortbeschluss erforderlich bei Überschreitung)', 'antragstypen');

        // Abstimmungsregeln
        $c('voting_default_rule', 'einfach', 'text', 'Standard-Abstimmungsregel für neue Anträge', 'voting');
        $rules = [
            'einfach' => ['Einfache Mehrheit', 'Mehr Ja als Nein (Enthaltungen zählen nicht)'],
            'absolut' => ['Absolute Mehrheit', 'Mehr Ja als Nein+Enthaltung'],
            'mehrheit_stimmber' => ['Mehrheit der Stimmberechtigten', 'Mehr als 50% aller Stimmberechtigten stimmen Ja'],
            'zweidrittel' => ['2/3-Mehrheit', 'Ja-Stimmen >= 2 × Nein-Stimmen'],
            'einstimmig' => ['Einstimmigkeit', 'Alle Abstimmenden müssen Ja stimmen'],
        ];
        foreach ($rules as $k => [$label, $desc]) {
            $c("voting_rule_{$k}_label", $label, 'text', "Label für $label", 'voting');
            $c("voting_rule_{$k}_desc", $desc, 'text', "Beschreibung $label", 'voting');
        }
        foreach ($rules as $k => [$label]) $c("voting_enable_$k", '1', 'boolean', "$label aktiviert", 'voting');

        // Terminologie
        $term = [
            ['term_ressort_singular', 'Ressort', 'Singular-Form für Ressort/Abteilung/Bereich'],
            ['term_ressort_plural', 'Ressorts', 'Plural-Form für Ressort/Abteilung/Bereich'],
            ['term_antrag_singular', 'Antrag', 'Singular-Form für Antrag/Beschlussvorlage'],
            ['term_antrag_plural', 'Anträge', 'Plural-Form für Antrag/Beschlussvorlage'],
            ['term_beschluss_singular', 'Beschluss', 'Singular-Form für Beschluss'],
            ['term_beschluss_plural', 'Beschlüsse', 'Plural-Form für Beschluss'],
            ['term_vorstand', 'Vorstand', 'Bezeichnung für Vorstand/Führungsgremium'],
            ['term_geschaeftsfuehrer', 'Geschäftsführer', 'Bezeichnung für Geschäftsführer'],
            ['term_ressortleiter', 'Ressortleiter', 'Bezeichnung für Ressortleiter/Abteilungsleiter'],
            ['second_entity_name', 'Stiftung', 'Name der zweiten Einheit neben "Verein" (z.B. "Stiftung", leer = keine Differenzierung)'],
        ];
        foreach ($term as [$k, $v, $d]) $c($k, $v, 'text', $d, 'terminology');

        // Benachrichtigungen / Mail (nur Fake-Adressen und -URLs)
        $c('agenda_reminder_default_emails', 'vorstand@example.org', 'text', 'Standard-Empfänger für Agenda-Erinnerungsmail (kommagetrennt, leer = nur Teilnehmer)', 'notifications');
        $c('meeting_system_url', 'https://demo.example.org/Sitzungsverwaltung', 'text', 'Basis-URL des Sitzungssystems für Links in Mails (z.B. https://example.com/vorstand/Sitzungsverwaltung)', 'notifications');
        $c('opinion_standalone_url', 'https://demo.example.org/Sitzungsverwaltung/opinion_standalone.php', 'text', 'Öffentliche URL des Meinungsbild-Standalone-Links. Überschreibt den automatisch erzeugten Link.', 'notifications');
        $c('terminplanung_standalone_url', 'https://demo.example.org/Sitzungsverwaltung/terminplanung_standalone.php', 'text', 'Öffentliche URL des Terminplanung-Standalone-Links. Überschreibt den automatisch erzeugten Link.', 'notifications');
        $c('mail_from_name', 'Sitzungstool (Demo)', 'text', 'Absender-Name für Benachrichtigungs-Mails (z.B. Sitzungstool)', 'notifications');
        $c('notification_digest_hour', '7', 'text', 'Uhrzeit (Stunde) für den täglichen Benachrichtigungs-Digest', 'notifications');
        $c('base_url', 'https://demo.example.org/Sitzungsverwaltung', 'text', 'Basis-URL für Links in Benachrichtigungen (Fallback)', 'system');

        // System / Dokumenten-Tab
        $c('documents_mode', 'internal', 'text', 'Dokumenten-Tab-Modus (internal/external/include)', 'system');
        $c('documents_external_url', '', 'text', 'Externe URL für den Dokumenten-Tab', 'system');
        $c('documents_include_path', '', 'text', 'Pfad zum einzubindenden PHP-Skript für Dokumenten-Tab', 'system');
    }

    // =====================================================================
    // Ressorts
    // =====================================================================
    private function ressorts()
    {
        $r = [
            [1, 'Finanzen', 10, 1], [2, 'Personal', 20, 1], [3, 'IT & Digitalisierung', 30, 1],
            [4, 'Marketing & Kommunikation', 40, 1], [5, 'Veranstaltungen', 50, 1], [6, 'Mitgliederverwaltung', 60, 1],
            [7, 'Infrastruktur', 70, 1], [8, 'Bildung & Weiterbildung', 80, 1], [9, 'Ehrenamtsförderung (ruht)', 90, 0],
        ];
        foreach ($r as [$id, $name, $pos, $aktiv]) {
            $this->add('svressorts', [
                'ID' => $id, 'Code' => sprintf('R%02d', $id), 'Ressort' => $name,
                'Reihenfolge' => $pos, 'aktiv' => $aktiv, 'created_at' => $this->dt(-400, '09:00'),
            ], 'ID');
        }
    }

    // =====================================================================
    // Sitzungen, Teilnehmer
    // =====================================================================
    private function meetings()
    {
        // Sitzung 50 (preparation) beginnt "heute"; update_demo_dates.php setzt den Beginn bei jedem Aufruf neu
        $s50 = $this->dt(0, '09:00');
        $e50 = $this->dt(0, '11:00');

        $vid = 'https://meet.example.org/';
        // id, name, start, ende, deadline, ort, video, einberufer, vorsitz, protokoll, status, rem_sent
        $m = [
            [10, 'Vorstandssitzung (Archiv, vor 10 Wochen)', $this->dt(-70, '19:00'), $this->dt(-70, '20:30'), null, 'Online per Jitsi', $vid . 'demo-archiv-1', 4, 1, 5, 'archived'],
            [11, 'Vorstandssitzung (Archiv, vor 5 Wochen)',  $this->dt(-35, '19:00'), $this->dt(-35, '20:45'), null, 'Geschäftsstelle, Raum 2', null, 4, 2, 5, 'archived'],
            [20, 'Vorstandssitzung (beendet, Protokoll in Arbeit)', $this->dt(-1, '19:00'), $this->dt(-1, '20:45'), null, 'Online per Jitsi', $vid . 'demo-ended', 4, 1, 5, 'ended'],
            [30, 'Vorstandssitzung (Protokoll liegt zur Durchsicht vor)', $this->dt(-5, '19:00'), $this->dt(-5, '20:30'), null, 'Online per Jitsi', $vid . 'demo-protokoll', 4, 3, 6, 'protocol_ready'],
            [40, 'Vorstandssitzung (läuft gerade)', $this->dt(0, '08:00'), $this->dt(0, '23:00'), null, 'Online per Jitsi', $vid . 'demo-live', 4, 1, 5, 'active'],
            [50, 'Jour Fixe Vorstand (Beginn heute)', $s50, $e50, null, 'Online per Jitsi', $vid . 'demo-jourfixe', 4, 1, 5, 'preparation'],
            [51, 'Vorstandssitzung (nächste Woche)', $this->dt(7, '17:00'), $this->dt(7, '19:00'), $this->dt(6, '23:59'), 'Geschäftsstelle, Raum 1', $vid . 'demo-naechste', 4, 2, 5, 'preparation'],
            [63, 'Workshop Ressortleitungen', $this->dt(4, '19:00'), $this->dt(4, '20:30'), null, 'Online per Jitsi', $vid . 'demo-workshop', 4, 4, 6, 'preparation'],
            [64, 'Sondersitzung Haushalt 2027', $this->dt(2, '19:00'), $this->dt(2, '20:30'), $this->dt(2, '12:00'), 'Online per Jitsi', $vid . 'demo-haushalt', 4, 1, 5, 'preparation'],
            [65, 'Eilsitzung Projektfreigabe', $this->dt(1, '19:00'), $this->dt(1, '20:30'), $this->dt(0, '00:01'), 'Online per Jitsi', $vid . 'demo-eil', 4, 3, 5, 'preparation'],
        ];
        foreach ($m as [$id, $name, $start, $end, $dl, $loc, $video, $inv, $chair, $sec, $status]) {
            $row = [
                'meeting_id' => $id, 'meeting_name' => $name, 'meeting_date' => $start, 'expected_end_date' => $end,
                'submission_deadline' => $dl, 'location' => $loc, 'video_link' => $video,
                'invited_by_member_id' => $inv, 'chairman_member_id' => $chair, 'chairman_name' => $this->mname($chair),
                'secretary_member_id' => $sec, 'secretary_name' => $this->mname($sec),
                'status' => $status, 'visibility_type' => 'invited_only',
                'allow_decisions' => ($id == 63) ? 0 : 1,
                'send_agenda_reminder' => $dl ? 1 : 0, 'agenda_reminder_sent' => ($id == 65) ? 1 : 0,
                'agenda_reminder_emails' => ($id == 51) ? 'vorstand@example.org' : null,
                'agenda_reminder_intro' => null, 'protocol_intern' => '',
                'created_at' => date('Y-m-d H:i:s', strtotime($start . ' -8 days')),
            ];
            if (in_array($status, ['active', 'ended', 'protocol_ready', 'archived'], true)) $row['started_at'] = $start;
            if (in_array($status, ['ended', 'protocol_ready', 'archived'], true)) $row['ended_at'] = $end;
            $this->add('svmeetings', $row, 'meeting_id');
        }

        // Teilnehmer: [meeting_id => [member_id => attendance/status]]
        $all = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        $att = function ($ids, $absent = [], $partial = []) {
            $o = [];
            foreach ($ids as $i) $o[$i] = in_array($i, $absent) ? 'absent' : (in_array($i, $partial) ? 'partial' : 'present');
            return $o;
        };
        $parts = [
            10 => $att([1, 2, 3, 4, 5, 7], [3]),
            11 => $att([1, 2, 3, 4, 5, 8], [], [2]),
            20 => $att([1, 2, 3, 4, 5, 7, 8], [8], [3]),
            30 => $att([1, 2, 3, 4, 6, 9], [9]),
            40 => $att([1, 2, 3, 4, 5, 7], [3]),
            50 => $att($all),
            51 => $att([1, 2, 3, 4, 5, 6, 7]),
            63 => $att([4, 7, 8, 9, 5]),
            64 => $att([1, 2, 3, 4, 5]),
            65 => $att([1, 2, 3, 4]),
        ];
        foreach ($parts as $mid => $list) {
            foreach ($list as $member => $a) {
                $past = in_array($mid, [10, 11, 20, 30, 40], true);
                $this->add('svmeeting_participants', [
                    'meeting_id' => $mid, 'member_id' => $member,
                    'status' => $past ? ($a === 'absent' ? 'absent' : 'present') : (($member % 3 == 0) ? 'invited' : 'confirmed'),
                    'attendance_status' => $past ? $a : 'absent',
                    'invited_at' => $this->dt(-8, '10:00'),
                ], 'participant_id');
            }
        }
    }

    // =====================================================================
    // Anträge, Beschlüsse
    // =====================================================================
    private function votePad(array $v)
    {
        $o = [];
        for ($i = 1; $i <= 6; $i++) {
            $e = $v[$i - 1] ?? null;
            if ($e === null) continue;
            // [member_id, votum, VDat, VBegr, VProt, VBedenk]
            $o["VName$i"] = (string)$e[0];
            $o["Votum$i"] = (string)$e[1];
            if (!empty($e[2])) $o["VDat$i"] = $e[2];
            if (!empty($e[3])) $o["VBegr$i"] = $e[3];
            if (!empty($e[4])) $o["VProt$i"] = $e[4];
            if (!empty($e[5])) $o["VBedenk$i"] = $e[5];
        }
        return $o;
    }

    private function antrag($nr, $bart, $antrst, $titel, $beschluss, $begr, array $o = [])
    {
        $fin = $o['fin'] ?? 0;
        $row = [
            'antrnr' => $nr, 'bart' => $bart, 'antrst' => (string)$antrst, 'titel' => $titel,
            'beschluss' => $beschluss, 'begr' => $begr,
            'fin' => (string)$fin, 'betrag' => $fin ? number_format((float)$fin, 2, '.', '') : null,
            'fintext' => $o['fintext'] ?? null, 'pers' => $o['pers'] ?? null, 'sach' => $o['sach'] ?? null,
            'verein' => $o['verein'] ?? 'V', 'int_ext' => $o['int_ext'] ?? 'e',
            'ressort1' => isset($o['r1']) ? (string)$o['r1'] : null, 'ressort2' => isset($o['r2']) ? (string)$o['r2'] : null,
            'verant' => $o['verant'] ?? null, 'thread' => $o['thread'] ?? null, 'hinweis' => $o['hinweis'] ?? null,
            'wichtig' => $o['wichtig'] ?? '0', 'status' => $o['status'] ?? 'A',
            'abstimmregel' => $o['regel'] ?? 'einfach', 'verf1' => isset($o['verf1']) ? (string)$o['verf1'] : null,
            'verf2' => isset($o['verf2']) ? (string)$o['verf2'] : null,
            'lzugriff' => $o['lzugriff'] ?? null, 'warantrag' => $o['war'] ?? null, 'ergebnis' => $o['ergebnis'] ?? null,
            'filetext1' => $o['filetext1'] ?? null,
            'created_at' => $o['created'] ?? $this->dt(-2, '10:00'),
        ];
        if (isset($o['b_date'])) $row['b_date'] = $o['b_date'];
        if (isset($o['auto'])) $row['auto_abstimmung'] = $o['auto'];
        if (!empty($o['votes'])) $row += $this->votePad($o['votes']);
        $this->add('svantraege', $row);
        return $row;
    }

    private function antraege()
    {
        $d = $this->bDauer;
        $h = function ($off, $who, $txt, $time = '10:15') {
            return date('d.m.Y', strtotime($this->day($off))) . ' ' . $time . ' (' . $who . '): ' . $txt;
        };

        // --- A: in Bearbeitung ---
        $this->antrag('A' . $this->ymd(-1) . '01', 'V', 8, '', '', null, ['lzugriff' => $this->dt(-1, '16:40'), 'created' => $this->dt(-1, '16:38')]); // frisch angelegt, noch ohne Titel
        $this->antrag('A' . $this->ymd(-3) . '01', 'V', 7, 'Anschaffung von 20 Stühlen für den Vereinsraum',
            'Der Verein beschafft 20 stapelbare Stühle für den Vereinsraum zum Preis von bis zu 480 Euro.',
            'Die vorhandenen Stühle sind defekt bzw. nicht mehr in ausreichender Zahl vorhanden.',
            ['fin' => 480, 'fintext' => 'Anschaffung laut Angebot', 'r1' => 7, 'verant' => 'Anna Frühling', 'verf1' => 1,
             'thread' => 'https://forum.example.org/t/1234', 'sach' => 'Möbelhaus Beispiel, Angebot vom ' . $this->day(-4),
             'hinweis' => $h(-3, 'H. Lehmann', 'Zwei Angebote liegen vor, das günstigere habe ich verwendet.'),
             'lzugriff' => $this->dt(-3, '11:00'), 'created' => $this->dt(-3, '10:00')]);
        $this->antrag('A' . $this->ymd(-5) . '01', 'R', 9, 'Neues Veranstaltungskonzept Sommerfest 2027',
            'Das Sommerfest 2027 wird als zweitägige Veranstaltung mit einem Budget von 1.850 Euro geplant.',
            'Die Teilnehmerzahlen sind gestiegen; ein Tag reicht nicht mehr aus.',
            ['fin' => 1850, 'fintext' => 'Raummiete, Catering, Technik', 'r1' => 5, 'r2' => 4, 'verant' => 'Petra Nord',
             'verf1' => 1, 'verf2' => 2, 'lzugriff' => $this->dt(-4, '09:30'), 'created' => $this->dt(-5, '14:00'),
             'filetext1' => 'Budgetübersicht (Beispieldatei, nicht vorhanden)']);
        $this->antrag('A' . $this->ymd(-6) . '01', 'B', 4, 'Neue Beitragsordnung ab 2027',
            'Die Beitragsordnung wird gemäß Anlage geändert; der Regelbeitrag steigt um 5 Euro jährlich.',
            'Die Kosten sind seit der letzten Anpassung deutlich gestiegen.',
            ['wichtig' => '1', 'regel' => 'zweidrittel', 'r1' => 1, 'verant' => 'Julia Sommer', 'lzugriff' => $this->dt(-2, '08:20'),
             'created' => $this->dt(-6, '09:00'), 'hinweis' => $h(-2, 'M. Mustermann', 'Bitte Übergangsregelung für Studierende ergänzen.', '08:15')]);
        $this->antrag('A' . $this->ymd(-2) . '02', 'B', 5, 'Prüfung eines Umzugs der Geschäftsstelle',
            'Der Vorstand beauftragt die Geschäftsführung mit der Prüfung von zwei Alternativstandorten.',
            'Der Mietvertrag läuft Ende nächsten Jahres aus.',
            ['fin' => 12000, 'fintext' => 'Geschätzte jährliche Mehrkosten', 'int_ext' => 'i', 'r1' => 7, 'verant' => 'Thomas Winter',
             'lzugriff' => $this->dt(-2, '15:10'), 'created' => $this->dt(-2, '15:00'), 'verein' => 'S']);

        // Beispiel "automatisch zur Abstimmung stellen": Wartezeit endet in 2 Tagen, danach stellt der Cron den Antrag ein
        // (update_demo_dates.php hält den Zyklus aufrecht; nur wirksam, wenn für Typ B eine Wartezeit konfiguriert ist)
        $wz = 14;
        foreach ($this->tables['svconfig'] ?? [] as $c) { if ($c['config_key'] === 'bart_B_wartezeit_tage') $wz = max(2, (int)$c['config_value']); }
        $this->antrag('A' . $this->ymd(-($wz - 2)) . DEMO_AUTO_ANTRNR_SUFFIX, 'B', 7, 'Anschaffung von Präsentationstechnik für die Sitzungen',
            'Der Vorstand beschließt die Anschaffung eines Beamers mit Leinwand für die Geschäftsstelle.',
            'Für hybride Sitzungen wird dauerhaft geeignete Technik benötigt. Der Antrag ist so eingestellt, dass er nach Ablauf der Wartezeit automatisch zur Abstimmung gestellt wird.',
            ['fin' => 890, 'fintext' => 'Beamer, Leinwand und Zubehör', 'int_ext' => 'e', 'r1' => 3, 'verant' => 'Mia Technik', 'auto' => 1,
             'lzugriff' => $this->dt(-1, '14:20'), 'created' => $this->dt(-($wz - 2), '10:00')]);

        // --- B: in Abstimmung ---
        $vd = function ($off, $time = '18:30') { return $this->dt($off, $time); };
        $vd16 = function ($off, $time = '18:30') { return substr($this->dt($off, $time), 0, 16); };

        // Erinnerungs-Antrag: b_date = heute - Dauer (letzter Abstimmungstag = heute), Stimmen von Lukas und Max fehlen
        $this->antrag(DEMO_REMINDER_ANTRNR, 'B', 4, 'Ersatzbeschaffung Vereinsserver und Backup-Konzept',
            'Der Vorstand beschließt die Ersatzbeschaffung des Vereinsservers sowie ein tägliches Offsite-Backup.',
            'Der Server ist sieben Jahre alt, ein Ausfall würde die Mitgliederverwaltung lahmlegen.',
            ['fin' => 3200, 'fintext' => 'Server, Einrichtung und Backup-Speicher (3 Jahre)', 'wichtig' => '1', 'status' => 'E',
             'r1' => 3, 'verant' => 'Mia Technik', 'b_date' => $this->day(-$d), 'created' => $this->dt(-$d - 2, '09:00'),
             'lzugriff' => $this->dt(-$d, '09:05'), 'thread' => 'https://forum.example.org/t/1301',
             'hinweis' => $h(-$d + 1, 'J. Sommer', 'Zwei Angebote verglichen, Variante B ist wirtschaftlicher.', '11:00'),
             'votes' => [[2, 1, $vd16(-$d + 1, '20:10'), 'Notwendig und wirtschaftlich.'], [3, 0], [1, 0]]]);

        // Verfügungen (V) werden nie abgestimmt; dieses Beispiel ist daher ein Vorstandsbeschluss (als Abstimmung im Gesamtvorstand eingestellt)
        $this->antrag('B' . $this->ymd(-9) . '01', 'B', 4, 'Beauftragung externer Steuerberatung für das Rumpfgeschäftsjahr',
            'Der Verein beauftragt eine Steuerberatung für die Abschlussarbeiten zum Preis von bis zu 450 Euro.',
            'Die Abschlussarbeiten übersteigen die Kapazität der Geschäftsstelle.',
            ['fin' => 450, 'fintext' => 'Pauschalhonorar', 'wichtig' => '1', 'status' => 'E', 'r1' => 1, 'verant' => 'Hans Lehmann',
             'b_date' => $this->day(-2), 'created' => $this->dt(-9, '10:00'), 'lzugriff' => $this->dt(-2, '09:00'),
             'votes' => [
                 [2, 1, $vd16(-1, '19:00')],
                 [3, 1, $vd16(-1, '21:30'), 'Einverstanden.'],
                 [1, 5, $vd16(-1, '22:00'), null, 'Möchte noch ein Vergleichsangebot einholen.', $this->dt(2, '12:00')],   // Bedenkzeit läuft
                 [4, 6, $vd16(-2, '09:30'), 'Als Antragstellerin befangen.'],                                              // befangen
                 [7, 0],
                 [5, 2, $vd16(0, '07:45'), 'Preis erscheint zu hoch, bitte nachverhandeln.'],
             ]]);
        $this->antrag('B' . $this->ymd(-8) . '02', 'R', 9, 'Honorar für Referent beim Jahrestreffen',
            'Für den Vortrag beim Jahrestreffen wird ein Honorar von 1.200 Euro bewilligt.',
            'Der Referent wurde von der Mitgliederversammlung gewünscht.',
            ['fin' => 1200, 'fintext' => 'Honorar inkl. Reisekosten', 'status' => 'E', 'r1' => 5, 'verant' => 'Petra Nord',
             'verf1' => 9, 'verf2' => 1, 'b_date' => $this->day(-1), 'created' => $this->dt(-8, '12:00'), 'lzugriff' => $this->dt(-1, '10:00'),
             'votes' => [[9, 1, $vd16(-1, '10:05')], [1, 0]]]);
        $this->antrag('B' . $this->ymd(-3) . '01', 'B', 3, 'Satzungsänderung §7: Online-Mitgliederversammlung',
            'Der Vorstand legt der Mitgliederversammlung eine Änderung des §7 vor, die Online-Teilnahme ermöglicht.',
            'Die Beteiligung an Präsenzterminen sinkt seit Jahren.',
            ['wichtig' => '1', 'regel' => 'absolut', 'status' => 'E', 'r1' => 6, 'verant' => 'Lukas Beispiel',
             'b_date' => $this->day(0), 'created' => $this->dt(-3, '17:00'), 'lzugriff' => $this->dt(0, '07:30'),
             'votes' => [[2, 0], [3, 0], [1, 0]]]);
        $this->antrag('B' . $this->ymd(-11) . '01', 'B', 1, 'Rücklagenbildung für Immobilienerwerb',
            'Der Vorstand bildet eine zweckgebundene Rücklage von 25.000 Euro.',
            'Mittelfristig soll eine eigene Geschäftsstelle erworben werden.',
            ['fin' => 25000, 'fintext' => 'Einmalige Zuführung aus dem Jahresüberschuss', 'regel' => 'zweidrittel', 'int_ext' => 'i',
             'status' => 'E', 'r1' => 1, 'verant' => 'Max Mustermann', 'b_date' => $this->day(-3), 'created' => $this->dt(-11, '11:00'),
             'lzugriff' => $this->dt(-3, '12:00'),
             'votes' => [[2, 1, $vd16(-2, '20:00')], [3, 4, $vd16(-2, '21:00'), 'Bitte zuerst eine Alternativrechnung vorlegen (Rückverweisung).'], [1, 0]]]);

        // --- Beschlossen (VS) mit Beschlussbuch-Eintrag ---
        $vs = [
            // Nummer-Offset, laufende Nr, bart, antrst, titel, beschluss, begr, fin, fintext, int_ext, regel, r1, votes, dafuer
            [-1, 1, 'V', 7, 'Anschaffung eines Moderationskoffers', 'Der Verein beschafft einen Moderationskoffer für bis zu 180 Euro.', 'Workshops werden bisher mit privatem Material durchgeführt.', 180, 'Moderationskoffer inkl. Material', 'e', 'einfach', 5,
                [], ''],
            [-5, 1, 'R', 8, 'Druck von Flyern für die Herbstkampagne', 'Für die Herbstkampagne werden Flyer für bis zu 950 Euro gedruckt.', 'Die Kampagne soll neue Mitglieder gewinnen.', 950, 'Druckkosten 5.000 Stück', 'e', 'einfach', 4,
                [[8, 1], [1, 1]], 'einstimmig'],
            [-47, 2, 'B', 2, 'Mitgliederversammlung 2026: Termin und Ort', 'Die Mitgliederversammlung findet am Samstag, dem 5. Dezember, im Bürgerhaus statt.', 'Der Saal steht an diesem Termin zur Verfügung.', 0, null, 'e', 'einfach', 5,
                [[2, 1], [3, 1], [1, 3, null, 'Enthalte mich wegen terminlicher Überschneidung.']], 'MaxM: Enthaltung, ErikaM: Ja, LukasB: Ja'],
            [-70, 1, 'B', 4, 'Gehaltsrahmen Geschäftsstelle 2027', 'Der Gehaltsrahmen für die Geschäftsstelle wird für 2027 festgelegt (vertraulich, siehe Anlage).', 'Anpassung an die Entwicklung der Lebenshaltungskosten.', 0, null, 'i', 'zweidrittel', 2,
                [[2, 1], [3, 1], [1, 1]], 'einstimmig'],
        ];
        foreach ($vs as [$off, $nn, $bart, $antrst, $titel, $besch, $begr, $fin, $fintext, $ie, $regel, $res, $votes, $dafuer]) {
            $nr = 'VS' . $this->ymd($off) . sprintf('%02d', $nn);
            $direkt = ($bart === 'V');   // Verfügung: ohne Abstimmung beschlossen (war = A-Nummer, keine Stimmen, kein b_date)
            $war = ($direkt ? 'A' : 'B') . $this->ymd($off - 9) . sprintf('%02d', $nn);
            $v = [];
            foreach ($votes as $e) { $v[] = [$e[0], $e[1], $this->dt($off, '20:' . sprintf('%02d', 10 + count($v))), $e[3] ?? null]; }
            if ($direkt) {
                $v = [];
                $dafuer = 'Verfügung von ' . $this->mname($antrst);
            }
            $opt = [
                'fin' => $fin, 'fintext' => $fintext, 'int_ext' => $ie, 'regel' => $regel, 'r1' => $res, 'status' => 'G',
                'war' => $war, 'ergebnis' => 'angenommen', 'votes' => $v,
                'created' => $this->dt($off - 12, '10:00'), 'lzugriff' => $this->dt($off, '20:30'), 'verant' => $this->mname($antrst),
            ];
            if (!$direkt) $opt['b_date'] = $this->day($off - $this->bDauer);
            if ($direkt) $opt['verf1'] = $antrst;
            if ($bart === 'R' && count($votes) >= 2) { $opt['verf1'] = $votes[0][0]; $opt['verf2'] = $votes[1][0]; }
            $this->antrag($nr, $bart, $antrst, $titel, $besch, $begr, $opt);
            $ressort_name = $this->tables['svressorts'][$res - 1]['Ressort'];
            $this->add('svbeschluesse', [
                'antrnr' => $nr, 'fertig' => 'F', 'titel' => $titel, 'beschluss' => $besch, 'begr' => $begr,
                'fintext' => $fin ? number_format($fin, 2, ',', '.') . ' Euro – ' . $fintext : null,
                'ressort' => $ressort_name, 'int_ext' => $ie, 'dafuer' => $dafuer, 'abstimmregel' => $regel,
                'wichtig' => ($bart === 'B') ? '1' : '0',
                'anmerkungen' => ($ie === 'i') ? 'Nur für Vorstand, GF und Assistenz sichtbar (intern).' : null,
            ]);
        }

        // --- Abgelehnt / zurückgezogen (X) und gelöscht (Z) ---
        $this->antrag('X' . $this->ymd(-20) . '01', 'V', 11, 'Anschaffung eines Großbildschirms für den Besprechungsraum',
            'Der Verein beschafft einen 75-Zoll-Bildschirm für bis zu 650 Euro.',
            'Präsentationen sind im Besprechungsraum schlecht zu erkennen.',
            ['fin' => 650, 'fintext' => 'Bildschirm inkl. Wandhalterung', 'status' => 'X', 'r1' => 3, 'ergebnis' => 'abgelehnt',
             'b_date' => $this->day(-27), 'created' => $this->dt(-35, '09:00'), 'lzugriff' => $this->dt(-20, '20:00'),
             'votes' => [[2, 2, $this->dt(-22, '19:00'), 'Zu teuer, der vorhandene Beamer genügt.'], [3, 2, $this->dt(-22, '20:10')], [1, 1, $this->dt(-21, '08:00')], [4, 3, $this->dt(-21, '09:00')]]]);
        $this->antrag('X' . $this->ymd(-15) . '03', 'V', 8, 'Neues Vereinslogo (zurückgezogen)',
            'Es wird ein neues Logo beauftragt.', 'Das alte Logo wirkt veraltet.',
            ['fin' => 300, 'status' => 'X', 'r1' => 4, 'hinweis' => $h(-15, 'A. Frühling', 'Antrag zurückgezogen, wird mit dem Marketing-Konzept neu gestellt.', '16:20'),
             'created' => $this->dt(-16, '15:00'), 'lzugriff' => $this->dt(-15, '16:20')]);
        $this->antrag('Z' . $this->ymd(-25) . '01', 'R', 7, 'Mietvertrag Lagerraum (Duplikat)',
            'Der Verein mietet einen Lagerraum.', 'Doppelt eingereicht.',
            ['fin' => 1500, 'status' => 'Z', 'r1' => 7, 'hinweis' => $h(-25, 'T. Winter', 'Duplikat, siehe laufender Antrag zur Raumplanung.', '13:00'),
             'created' => $this->dt(-26, '12:00'), 'lzugriff' => $this->dt(-25, '13:00')]);

        // --- MV-Beschlüsse ---
        $mv = [
            ['MV2025-01', 'Entlastung des Vorstands', 'Der Vorstand wird für das Geschäftsjahr 2024 entlastet.', 'Prüfbericht ohne Beanstandungen.', 'angenommen', 41, 0, 3, 1, 'Entlastung'],
            ['MV2025-02', 'Beitragsanpassung 2026', 'Der Regelbeitrag wird ab 2026 um 3 Euro angehoben.', 'Kostensteigerung.', 'angenommen', 33, 6, 5, 1, 'Finanzen'],
            ['MV2025-03', 'Satzungsänderung §3 (Vereinszweck)', 'Der Vereinszweck wird um Weiterbildung ergänzt.', 'Neue Angebote.', 'abgelehnt', 18, 24, 2, 0, 'Satzung'],
            ['MV2024-04', 'Wahl der Kassenprüfer', 'Karl Prüfer und eine weitere Person werden zu Kassenprüfern gewählt.', null, 'angenommen', 44, 0, 0, 1, 'Wahlen'],
        ];
        foreach ($mv as [$nr, $titel, $text, $begr, $erg, $ja, $nein, $enth, $rel, $kat]) {
            $this->add('svmvbeschluesse', ['antrnr' => $nr, 'titel' => $titel, 'text' => $text, 'begr' => $begr, 'ergebnis' => $erg,
                'dafuer' => $ja, 'dagegen' => $nein, 'enth' => $enth, 'Relevant' => $rel, 'kategorie' => $kat,
                'created_at' => $this->dt(-300, '12:00')]);
        }
    }

    // =====================================================================
    // Tagesordnungspunkte, Kommentare, Protokolle, Abstimmungen
    // =====================================================================
    private function item($mid, $num, $title, $desc, $cat, $by, array $o = [])
    {
        $meeting = null;
        foreach ($this->tables['svmeetings'] as $m) { if ($m['meeting_id'] == $mid) $meeting = $m; }
        $row = [
            'meeting_id' => $mid, 'top_number' => $num, 'title' => $title, 'description' => $desc, 'category' => $cat,
            'proposal_text' => $o['proposal'] ?? null, 'antrnr' => $o['antrnr'] ?? null,
            'priority' => $o['prio'] ?? '5.00', 'estimated_duration' => $o['dur'] ?? 15, 'protocol_notes' => $o['notes'] ?? null,
            'is_confidential' => $num >= 100 ? 1 : 0, 'is_active' => $o['active'] ?? 0,
            'created_by_member_id' => $by,
            'created_at' => date('Y-m-d H:i:s', strtotime($meeting['created_at'] . ' +1 day')),
        ];
        foreach (['vote_yes', 'vote_no', 'vote_abstain', 'vote_result'] as $k) if (isset($o[$k])) $row[$k] = $o[$k];
        if (isset($o['updated'])) $row['updated_at'] = $o['updated'];
        if (isset($o['referent'])) $row['referent_member_id'] = $o['referent'];   // optionale Spalte (nicht in init-db)
        $row = $this->add('svagenda_items', $row, 'item_id');
        return $row['item_id'];
    }

    /** Kommentar mit Prioritätsbewertung/Dauerschätzung; passt Priorität/Dauer des TOP auf den Mittelwert an */
    private function comment($item, $by, $text, $prio, $dur, $when)
    {
        $this->add('svagenda_comments', ['item_id' => $item, 'member_id' => $by, 'comment_text' => $text,
            'priority_rating' => $prio, 'duration_estimate' => $dur, 'created_at' => $when], 'comment_id');
    }

    private function recomputeItemRatings()
    {
        $agg = [];
        foreach ($this->tables['svagenda_comments'] ?? [] as $c) {
            if ($c['priority_rating'] === null) continue;
            $agg[$c['item_id']]['p'][] = $c['priority_rating'];
            if ($c['duration_estimate'] !== null) $agg[$c['item_id']]['d'][] = $c['duration_estimate'];
        }
        foreach ($this->tables['svagenda_items'] as &$it) {
            if (!isset($agg[$it['item_id']])) continue;
            $p = $agg[$it['item_id']]['p'];
            $it['priority'] = number_format(array_sum($p) / count($p), 2, '.', '');
            if (!empty($agg[$it['item_id']]['d'])) {
                $dd = $agg[$it['item_id']]['d'];
                $it['estimated_duration'] = (int)round(array_sum($dd) / count($dd));
            }
        }
        unset($it);
    }

    /**
     * TOP 0 (Wahl Sitzungsleitung/Protokollführung, Prio 9,9), TOP 99 (Verschiedenes, Prio 0,1) und - ab Sitzungsstart -
     * TOP 999 (Sitzungsende, updated_at = Endzeitpunkt) für ALLE Sitzungen, wie process_meetings.php/process_agenda.php sie anlegen.
     */
    private function defaultTops()
    {
        foreach ($this->tables['svmeetings'] as $m) {
            $mid = $m['meeting_id'];
            $started = in_array($m['status'], ['active', 'ended', 'protocol_ready', 'archived'], true);
            $notes0 = null;
            if ($started) {
                $notes0 = "Sitzungsleitung: " . $m['chairman_name'] . "\nProtokollführung: " . $m['secretary_name'] . "\nGäste: keine";
            }
            $t0 = $this->item($mid, 0, SV_TOP0_TITLE, SV_TOP0_DESC, 'wahl',
                $m['invited_by_member_id'], ['dur' => 5, 'prio' => '9.90', 'notes' => $notes0]);
            $this->comment($t0, $m['invited_by_member_id'],
                "Vorschlag:\nSitzungsleitung: " . $m['chairman_name'] . "\nProtokollführer: " . $m['secretary_name'],
                null, null, date('Y-m-d H:i:s', strtotime($m['created_at'] . ' +1 hour')));
            $this->item($mid, 99, SV_TOP99_TITLE, SV_TOP99_DESC, 'sonstiges',
                $m['invited_by_member_id'], ['dur' => 0, 'prio' => '0.10']);
            if ($started) {
                $end = in_array($m['status'], ['ended', 'protocol_ready', 'archived'], true) ? $m['ended_at'] : $m['meeting_date'];
                $this->item($mid, 999, 'Sitzungsende', 'Automatisch erfasst', 'information', $m['invited_by_member_id'],
                    ['dur' => 1, 'prio' => '1.00', 'updated' => $end]);
            }
        }
    }

    /** Kurzprotokoll im Format von module_protocol.php::generate_protocol() (HTML-kodiert); liefert [öffentlich, intern] */
    private function renderProtocol($mid)
    {
        $meeting = null;
        foreach ($this->tables['svmeetings'] as $m) { if ($m['meeting_id'] == $mid) $meeting = $m; }
        $t = function ($x) { return htmlspecialchars((string)$x); };
        $lt = '&lt;'; $gt = '&gt;'; $q = '&quot;';
        $end = null;
        $items = [];
        foreach ($this->tables['svagenda_items'] as $it) {
            if ($it['meeting_id'] != $mid) continue;
            if ($it['top_number'] == 999) { $end = $it['updated_at'] ?? null; continue; }
            $items[] = $it;
        }
        usort($items, function ($a, $b) { return $a['top_number'] <=> $b['top_number']; });

        $present = [];
        foreach ($this->tables['svmeeting_participants'] as $pp) {
            if ($pp['meeting_id'] != $mid) continue;
            if ($pp['attendance_status'] === 'present') $present[] = $this->mname($pp['member_id']);
            elseif ($pp['attendance_status'] === 'partial') $present[] = $this->mname($pp['member_id']) . ' (zeitweise)';
        }
        $head = function ($title) use ($meeting, $end, $t, $lt, $gt, $q, $present) {
            $h = $lt . 'tr class=' . $q . 'mitr' . $q . $gt . $lt . 'td class=' . $q . 'mitr' . $q . $gt . $lt . 'h3' . $gt . $title . ':' . $lt . 'br' . $gt;
            $h .= $t($meeting['meeting_name']) . $lt . 'br' . $gt . 'am ' . date('d.m.Y H:i', strtotime($meeting['meeting_date'])) . ' Uhr';
            if ($end) {
                $h .= ' bis ';
                if (date('d.m.Y', strtotime($meeting['meeting_date'])) != date('d.m.Y', strtotime($end))) $h .= date('d.m.Y', strtotime($end)) . ' ';
                $h .= date('H:i', strtotime($end)) . ' Uhr ';
            }
            if (!strpos($meeting['location'], 'nline')) $h .= 'in ';
            $h .= $t($meeting['location']);
            $h .= $lt . '/h3' . $gt . $lt . 'strong' . $gt . 'Teilgenommen haben: ' . $lt . '/strong' . $gt . $t(implode(', ', $present));
            $h .= $lt . 'br' . $gt . $lt . 'strong' . $gt . 'Sitzungsleitung: ' . $lt . '/strong' . $gt . $t($meeting['chairman_name']);
            $h .= ', ' . $lt . 'strong' . $gt . 'Protokoll: ' . $lt . '/strong' . $gt . $t($meeting['secretary_name']);
            return $h;
        };
        $pub = $head('Protokoll');
        $int = '';
        $intExists = false;
        foreach ($items as $it) {
            if ($it['top_number'] == 0) {
                if (!empty($it['protocol_notes'])) {
                    $pub .= $lt . 'br' . $gt . $lt . 'br' . $gt . $lt . 'strong' . $gt . 'Eröffnung:' . $lt . '/strong' . $gt . $lt . 'br' . $gt . nl2br($t($it['protocol_notes']));
                }
                continue;
            }
            $conf = !empty($it['is_confidential']);
            $b = $lt . 'br' . $gt . $lt . 'br' . $gt . $lt . 'strong' . $gt . 'TOP ' . $it['top_number'] . ': ' . $t($it['title']) . $lt . '/strong' . $gt . $lt . 'br' . $gt;
            if ($it['category'] === 'antrag_beschluss' && !empty($it['proposal_text'])) {
                $b .= $lt . 'em' . $gt . 'Antragstext: ' . nl2br($t($it['proposal_text'])) . $lt . '/em' . $gt . $lt . 'br' . $gt;
            }
            $b .= !empty($it['protocol_notes']) ? nl2br($t($it['protocol_notes']))
                : $lt . 'em style=' . $q . 'color: #999;' . $q . $gt . '(noch kein Protokoll)' . $lt . '/em' . $gt;
            if (!empty($it['vote_result'])) {
                $b .= $lt . 'br' . $gt . $lt . 'strong' . $gt . 'Abstimmungsergebnis: ' . $lt . '/strong' . $gt;
                $b .= in_array($it['vote_result'], ['einvernehmlich', 'einstimmig'], true) ? ucfirst($it['vote_result'])
                    : ($it['vote_yes'] ?? 0) . ' Ja, ' . ($it['vote_no'] ?? 0) . ' Nein, ' . ($it['vote_abstain'] ?? 0) . ' Enthaltung - ' . ucfirst($it['vote_result']);
            }
            if ($conf) {
                if (!$intExists) { $int = $head('Internes Protokoll'); $intExists = true; }
                $int .= $b;
            } else {
                $pub .= $b;
            }
        }
        if ($end) {
            $pub .= $lt . 'br' . $gt . $lt . 'strong' . $gt . 'Sitzungsende: ' . date('H:i', strtotime($end)) . ' Uhr' . $lt . '/strong' . $gt;
            if ($intExists) {
                $int .= $lt . 'br' . $gt . $lt . 'strong' . $gt . 'Sitzungsende am ' . date('d.m.Y', strtotime($end)) . ' um ' . date('H:i', strtotime($end)) . ' Uhr' . $lt . '/strong' . $gt;
            }
        }
        $pub .= $lt . '/td' . $gt . $lt . '/tr' . $gt;
        if ($intExists) $int .= $lt . '/td' . $gt . $lt . '/tr' . $gt;
        return [$pub, $intExists ? $int : ''];
    }

    private function agendaItems()
    {
        $o = $this;
        // ---------- Sitzung 50 (Vorbereitung) ----------
        $i1 = $this->item(50, 1, 'Begrüßung und Feststellung der Tagesordnung', 'Genehmigung der Tagesordnung und des letzten Protokolls.', 'information', 4, ['dur' => 5]);
        $i2 = $this->item(50, 2, 'Planung Sommerfest 2027', 'Ort, Budget und Programm. Das Konzept liegt als Antrag vor.', 'diskussion', 9, ['dur' => 30, 'referent' => 9]);
        $i3 = $this->item(50, 3, 'Ersatzbeschaffung Vereinsserver', 'Beschlussfassung über den Antrag zur Ersatzbeschaffung (laufende Abstimmung).', 'antrag_beschluss', 4, ['antrnr' => DEMO_REMINDER_ANTRNR, 'proposal' => 'Der Vorstand beschließt die Ersatzbeschaffung des Vereinsservers.', 'dur' => 10]);
        $i4 = $this->item(50, 4, 'Wahl: Vertretung im Ausschuss Finanzen', 'Ein Mitglied des Vorstands soll in den Ausschuss Finanzen entsandt werden.', 'wahl', 1, ['dur' => 10]);
        $i5 = $this->item(50, 101, 'Personalangelegenheit Geschäftsstelle (vertraulich)', 'Vertraulicher TOP, nur für Vorstand, GF und Assistenz sichtbar.', 'aussprache', 4, ['dur' => 20]);
        $this->comment($i2, 1, 'Ich würde gern über einen zweiten Veranstaltungstag sprechen.', 8, 35, $this->dt(-1, '09:12'));
        $this->comment($i2, 2, 'Die Raumfrage sollte vorher geklärt sein.', 7, 25, $this->dt(-1, '11:40'));
        $this->comment($i2, 8, 'Marketing braucht den Termin bis Ende Januar.', 9, 30, $this->dt(0, '07:55'));
        $this->comment($i3, 3, 'Bitte die Angebote als Anlage beifügen.', 6, 10, $this->dt(-1, '19:05'));
        $this->comment($i3, 7, 'Aus Sicht der Finanzen unproblematisch.', 5, 5, $this->dt(0, '06:50'));
        $this->comment($i4, 4, 'Vorschlag: Erika, da sie die Vertretung der Finanzen ist.', 4, 10, $this->dt(-1, '14:20'));
        $this->comment($i5, 1, 'Wir sollten dafür ausreichend Zeit einplanen.', 9, 25, $this->dt(-1, '20:00'));
        $this->add('svagenda_personal_notes', ['item_id' => $i2, 'member_id' => 1, 'note_text' => 'Zweiten Tag nur mit Zusatzbudget. Angebot Bürgerhaus mitbringen.', 'created_at' => $this->dt(-1, '21:00')], 'note_id');
        $this->add('svagenda_personal_notes', ['item_id' => $i3, 'member_id' => 3, 'note_text' => 'Rückfrage zum Backup-Standort stellen.', 'created_at' => $this->dt(0, '07:10')], 'note_id');
        $this->add('svagenda_attachments', ['item_id' => $i2, 'filename' => 'demo_sommerfest_budget.pdf', 'original_filename' => 'Budget Sommerfest 2027.pdf',
            'filepath' => 'uploads/agenda/demo_sommerfest_budget.pdf', 'filesize' => 184320, 'filetype' => 'application/pdf',
            'uploaded_by_member_id' => 9, 'uploaded_at' => $this->dt(-2, '16:00')], 'attachment_id');
        $this->add('svagenda_attachments', ['item_id' => $i3, 'filename' => 'demo_angebot_server.pdf', 'original_filename' => 'Angebot Server (Beispiel).pdf',
            'filepath' => 'uploads/agenda/demo_angebot_server.pdf', 'filesize' => 96256, 'filetype' => 'application/pdf',
            'uploaded_by_member_id' => 11, 'uploaded_at' => $this->dt(-3, '10:30')], 'attachment_id');

        // ---------- Sitzung 51 (nächste Woche) ----------
        $a1 = $this->item(51, 1, 'Genehmigung des Protokolls', 'Protokoll der letzten Sitzung.', 'information', 4, ['dur' => 5]);
        $a2 = $this->item(51, 2, 'Satzungsänderung §7 (Online-Mitgliederversammlung)', 'Beratung zum laufenden Antrag.', 'antrag_beschluss', 3, ['antrnr' => 'B' . $this->ymd(-3) . '01', 'dur' => 25]);
        $a3 = $this->item(51, 3, 'Bericht der Ressorts', 'Kurzberichte aus Finanzen, Veranstaltungen und IT.', 'bericht', 7, ['dur' => 20]);
        $this->comment($a2, 2, 'Bitte die Beteiligungszahlen der letzten fünf Jahre bereitstellen.', 8, 30, $this->dt(0, '08:10'));
        $this->comment($a3, 8, 'Ich schicke meinen Bericht vorab.', 3, 10, $this->dt(0, '08:30'));

        // ---------- Sitzungen 63, 64, 65 ----------
        $b1 = $this->item(63, 1, 'Zielbild der Ressorts 2027', 'Gemeinsamer Workshop zu Zielen und Kennzahlen.', 'diskussion', 4, ['dur' => 60]);
        $this->item(63, 2, 'Abschlussrunde', 'Zusammenfassung der Ergebnisse.', 'information', 4, ['dur' => 15]);
        $this->comment($b1, 7, 'Kennzahlen bitte vorher abstimmen.', 6, 50, $this->dt(0, '09:00'));
        $c1 = $this->item(64, 1, 'Entwurf Haushalt 2027', 'Vorstellung des Haushaltsentwurfs durch die Finanzen.', 'bericht', 1, ['dur' => 40]);
        $this->item(64, 2, 'Beschluss: Haushaltsrahmen', 'Beschlussfassung zum Rahmen.', 'antrag_beschluss', 1, ['dur' => 20, 'proposal' => 'Der Haushaltsrahmen 2027 wird in Höhe von 180.000 Euro festgelegt.']);
        $this->comment($c1, 7, 'Die Rücklagenbildung sollte separat ausgewiesen werden.', 8, 40, $this->dt(-1, '13:10'));
        $this->item(65, 1, 'Projektfreigabe Mitgliederportal', 'Freigabe der ersten Projektphase.', 'antrag_beschluss', 3, ['dur' => 20, 'proposal' => 'Die erste Projektphase des Mitgliederportals wird freigegeben.']);

        // ---------- Sitzung 40 (aktiv, läuft) ----------
        $l1 = $this->item(40, 1, 'Begrüßung und Genehmigung der Tagesordnung', 'Genehmigung der Tagesordnung.', 'information', 4, ['dur' => 5, 'notes' => 'Tagesordnung ohne Änderung genehmigt.']);
        $l2 = $this->item(40, 2, 'Rücklagenbildung für Immobilienerwerb', 'Aussprache zum laufenden Antrag.', 'antrag_beschluss', 1, ['antrnr' => 'B' . $this->ymd(-11) . '01', 'dur' => 25, 'active' => 1]);
        $l3 = $this->item(40, 3, 'Stimmungsbild: Termin Mitgliederversammlung', 'Der Vorstand holt ein Stimmungsbild ein.', 'diskussion', 2, ['dur' => 10]);
        $this->item(40, 101, 'Vertragsverhandlung (vertraulich)', 'Vertraulicher TOP zu laufenden Verhandlungen.', 'aussprache', 4, ['dur' => 15]);
        foreach ($this->tables['svmeetings'] as &$mm) { if ($mm['meeting_id'] == 40) $mm['active_item_id'] = $l2; }
        unset($mm);
        $this->add('svagenda_live_comments', ['item_id' => $l2, 'member_id' => 2, 'comment_text' => 'Die Rücklage sollte zweckgebunden im Haushalt ausgewiesen werden.', 'created_at' => $this->dt(0, '08:20')], 'comment_id');
        $this->add('svagenda_live_comments', ['item_id' => $l2, 'member_id' => 7, 'comment_text' => 'Zinsentwicklung bitte in die Begründung aufnehmen.', 'created_at' => $this->dt(0, '08:24')], 'comment_id');
        $this->add('svagenda_live_comments', ['item_id' => $l2, 'member_id' => 3, 'comment_text' => 'Ich bitte um eine Alternativrechnung.', 'created_at' => $this->dt(0, '08:27')], 'comment_id');
        $this->comment($l2, 7, 'Betrag aus Sicht der Finanzen vertretbar.', 8, 20, $this->dt(-1, '17:20'));
        // laufende Abstimmung zu einem Stimmungsbild (offen, Vorstand)
        $v1 = $this->add('svvotings', ['item_id' => $l3, 'initiated_by_member_id' => 1, 'voting_question' => 'Soll die Mitgliederversammlung im Dezember stattfinden?',
            'voting_type' => 'open', 'eligible_voters' => 'board', 'status' => 'active', 'created_at' => $this->dt(0, '08:40')], 'voting_id');
        $this->add('svvotes', ['voting_id' => $v1['voting_id'], 'member_id' => 1, 'vote' => 'yes', 'created_at' => $this->dt(0, '08:41')], 'vote_id');
        $this->add('svvotes', ['voting_id' => $v1['voting_id'], 'member_id' => 2, 'vote' => 'yes', 'created_at' => $this->dt(0, '08:42')], 'vote_id');

        // ---------- Sitzung 20 (beendet) ----------
        $e1 = $this->item(20, 1, 'Begrüßung und Genehmigung des Protokolls', 'Genehmigung des Protokolls der letzten Sitzung.', 'information', 4, ['dur' => 5, 'notes' => 'Protokoll der Vorsitzung ohne Einwände genehmigt.']);
        $e2 = $this->item(20, 2, 'Moderationskoffer', 'Beschlussfassung über die Anschaffung.', 'antrag_beschluss', 5, ['antrnr' => 'VS' . $this->ymd(-1) . '01', 'dur' => 10, 'vote_yes' => 6, 'vote_no' => 0, 'vote_abstain' => 0, 'vote_result' => 'einstimmig', 'notes' => 'Einstimmig beschlossen (VS' . $this->ymd(-1) . '01).']);
        $e3 = $this->item(20, 3, 'Planung Mitgliederversammlung', 'Termin, Ort und Tagesordnung.', 'diskussion', 2, ['dur' => 30, 'vote_yes' => 3, 'vote_no' => 1, 'vote_abstain' => 1, 'vote_result' => 'angenommen', 'notes' => 'Terminvorschlag 5. Dezember angenommen. ToDo: Saal reservieren.']);
        $e4 = $this->item(20, 4, 'Bericht Geschäftsführung', 'Mitgliederzahlen und Liquidität.', 'bericht', 4, ['dur' => 15, 'notes' => 'Liquidität unproblematisch. Mitgliederzahl stabil.']);
        $e5 = $this->item(20, 101, 'Personalgespräch (vertraulich)', 'Vertraulicher Teil.', 'aussprache', 4, ['dur' => 20, 'notes' => 'Vertraulicher Inhalt, siehe internes Protokoll.']);
        $this->comment($e3, 2, 'Vorschlag: 5. Dezember.', 7, 30, $this->dt(-3, '10:00'));
        $this->comment($e3, 7, 'Ich bin am 5. Dezember verhindert, schlage den 12. vor.', 7, 30, $this->dt(-3, '12:30'));
        $this->add('svagenda_post_comments', ['item_id' => $e3, 'member_id' => 8, 'comment_text' => 'Nachtrag: Die Bürgerhaus-Reservierung muss bis Monatsende bestätigt werden.', 'created_at' => $this->dt(0, '09:10')], 'comment_id');
        $this->add('svagenda_post_comments', ['item_id' => $e4, 'member_id' => 5, 'comment_text' => 'Bitte die Mitgliederzahlen im Protokoll mit Datum versehen.', 'created_at' => $this->dt(0, '10:30')], 'comment_id');
        $v2 = $this->add('svvotings', ['item_id' => $e3, 'initiated_by_member_id' => 4, 'voting_question' => 'Mitgliederversammlung am 5. Dezember?', 'voting_type' => 'secret',
            'eligible_voters' => 'all', 'status' => 'closed', 'result_summary' => '3 Ja, 1 Nein, 1 Enthaltung', 'created_at' => $this->dt(-1, '19:50'),
            'closed_at' => $this->dt(-1, '19:55'), 'closed_by_member_id' => 4], 'voting_id');
        foreach ([[1, 'yes'], [2, 'yes'], [3, 'abstain'], [7, 'no'], [5, 'yes']] as [$mem, $vt]) {
            $this->add('svvotes', ['voting_id' => $v2['voting_id'], 'member_id' => $mem, 'vote' => $vt, 'created_at' => $this->dt(-1, '19:52')], 'vote_id');
        }
        $this->add('svagenda_personal_notes', ['item_id' => $e3, 'member_id' => 7, 'note_text' => 'Den 12. Dezember als Alternative anbieten.', 'created_at' => $this->dt(-1, '19:40')], 'note_id');

        // ---------- Sitzung 30 (Protokoll liegt vor) ----------
        $p1 = $this->item(30, 1, 'Genehmigung der Tagesordnung', 'Tagesordnung und letztes Protokoll.', 'information', 4, ['dur' => 5, 'notes' => 'Tagesordnung ohne Änderung genehmigt.']);
        $p2 = $this->item(30, 2, 'Druck von Flyern für die Herbstkampagne', 'Beschlussfassung.', 'antrag_beschluss', 8, ['antrnr' => 'VS' . $this->ymd(-5) . '01', 'dur' => 10, 'vote_yes' => 2, 'vote_no' => 0, 'vote_abstain' => 0, 'vote_result' => 'einstimmig', 'notes' => 'Einstimmig angenommen.']);
        $p3 = $this->item(30, 3, 'Jahresplanung Veranstaltungen', 'Terminübersicht für das nächste Jahr.', 'diskussion', 9, ['dur' => 25, 'notes' => 'Termine werden im Kalender eingetragen.']);
        $p4 = $this->item(30, 101, 'Vertrauliche Mitteilung', 'Nur für berechtigte Teilnehmer.', 'information', 4, ['dur' => 10, 'notes' => 'Siehe internes Protokoll.']);

        // ---------- Archivierte Sitzungen ----------
        $r1 = $this->item(11, 1, 'Genehmigung des letzten Protokolls', 'Protokoll der letzten Sitzung.', 'information', 4, ['dur' => 5, 'notes' => 'Ohne Einwände genehmigt.']);
        $r2 = $this->item(11, 2, 'Beitragsordnung (Vorbesprechung)', 'Erste Aussprache.', 'diskussion', 4, ['dur' => 30, 'notes' => 'Entwurf wird von der GF überarbeitet.']);
        $r3 = $this->item(10, 1, 'Gehaltsrahmen Geschäftsstelle 2027', 'Beschlussfassung (vertraulich).', 'antrag_beschluss', 4, ['antrnr' => 'VS' . $this->ymd(-70) . '01', 'dur' => 20, 'vote_yes' => 3, 'vote_no' => 0, 'vote_abstain' => 0, 'vote_result' => 'einstimmig', 'notes' => 'Einstimmig beschlossen.']);
        $r4 = $this->item(10, 2, 'Jahresabschluss', 'Vorstellung durch die Finanzen.', 'bericht', 1, ['dur' => 25, 'notes' => 'Zur Kenntnis genommen.']);

        $this->defaultTops();
        $this->recomputeItemRatings();

        // ---------- Protokolle ----------
        $proto = [];
        foreach ([10, 11, 30] as $pm) $proto[$pm] = $this->renderProtocol($pm);
        foreach ($proto as $mid => $p) {
            if (!$p) continue;
            foreach ($this->tables['svmeetings'] as &$mm) {
                if ($mm['meeting_id'] == $mid) { $mm['protokoll'] = $p[0]; $mm['prot_intern'] = $p[1]; }
                if ($mm['meeting_id'] == $mid) $when = date('Y-m-d H:i:s', strtotime($mm['expected_end_date'] . ' +1 day'));
            }
            unset($mm);
            $pr = $this->add('svprotocols', ['meeting_id' => $mid, 'protocol_type' => 'public', 'content' => $p[0], 'created_at' => $when], 'protocol_id');
            $this->add('svprotocols', ['meeting_id' => $mid, 'protocol_type' => 'confidential', 'content' => $p[1], 'created_at' => $when], 'protocol_id');
            if ($mid == 30) {
                $this->add('svprotocol_change_requests', ['protocol_id' => $pr['protocol_id'], 'member_id' => 9, 'item_id' => $p3,
                    'change_request' => 'Bitte ergänzen: Das Sommerfest findet voraussichtlich im Juli statt.', 'created_at' => $this->dt(-3, '18:00')], 'request_id');
                $this->add('svprotocol_change_requests', ['protocol_id' => $pr['protocol_id'], 'member_id' => 2, 'item_id' => $p2,
                    'change_request' => 'Die Antragsnummer im Protokoll ist unvollständig.', 'created_at' => $this->dt(-3, '20:15')], 'request_id');
            }
        }
        // Sitzung 20: Protokoll aus den TOPs erzeugt (noch nicht zur Durchsicht freigegeben)
        $p20 = $this->renderProtocol(20);
        foreach ($this->tables['svmeetings'] as &$mm) {
            if ($mm['meeting_id'] == 20) { $mm['protokoll'] = $p20[0]; $mm['prot_intern'] = $p20[1]; }
        }
        unset($mm);
    }

    // =====================================================================
    // ToDos
    // =====================================================================
    private function todos()
    {
        // Titel, zugewiesen an, Ersteller, Status, Fälligkeit (Tage rel. Anker), Sitzung, private
        $t = [
            ['Saal für Mitgliederversammlung reservieren', 8, 4, 'open', -4, 20, 0, 'Bürgerhaus anfragen, Reservierung schriftlich bestätigen lassen.'],
            ['Angebote für Backup-Speicher einholen', 11, 1, 'in progress', 1, 40, 0, 'Mindestens zwei vergleichbare Angebote.'],
            ['Einladung zur Mitgliederversammlung entwerfen', 5, 4, 'delayed', -10, 20, 0, 'Entwurf liegt noch nicht vor, wartet auf Rückmeldung zum Termin.'],
            ['Haushaltsentwurf 2027 vorbereiten', 7, 1, 'open', 7, 64, 0, 'Entwurf mit Rücklagenbildung getrennt ausweisen.'],
            ['Vorschläge für Ausschuss Finanzen sammeln', 6, 4, 'open', 21, null, 0, 'Bis zur übernächsten Sitzung.'],
            ['Flyer-Druck beauftragen', 8, 4, 'done', -6, 30, 0, 'Druckauftrag erteilt, Lieferung bestätigt.'],
            ['Protokoll der Vorsitzung versenden', 5, 4, 'done', -14, 11, 0, null],
            ['Geburtstagsgruß an Ehrenmitglied', 1, 1, 'open', 3, null, 1, 'Privates ToDo, nur für mich sichtbar.'],
            ['Vergleichsangebot Steuerberatung einholen', 1, 4, 'in progress', 2, 50, 0, 'Bedenkzeit im laufenden Antrag; Ergebnis den anderen mitteilen.'],
            ['Website: Terminübersicht aktualisieren', 11, 6, 'in progress', 14, null, 0, null],
            ['Mitgliederbrief Beitragsanpassung prüfen', 9, 4, 'open', 10, null, 0, 'Entwurf liegt im Textbereich bereit.'],
        ];
        foreach ($t as $i => [$title, $to, $by, $st, $due, $mid, $priv, $desc]) {
            $row = $this->add('svtodos', [
                'meeting_id' => $mid, 'item_id' => null, 'assigned_to_member_id' => $to, 'created_by_member_id' => $by,
                'title' => $title, 'description' => $desc, 'status' => $st, 'is_private' => $priv,
                'entry_date' => $this->day($due - 14), 'due_date' => $this->day($due),
                'completed_at' => $st === 'done' ? $this->dt($due, '14:30') : null,
                'protocol_link' => $mid ? 'index.php?tab=protokolle&meeting_id=' . $mid : null,
                'created_at' => $this->dt($due - 14, '10:00'),
            ], 'todo_id');
            $this->add('svtodo_log', ['todo_id' => $row['todo_id'], 'changed_by' => $by, 'change_type' => 'created', 'old_value' => null, 'new_value' => $title,
                'change_time' => $this->dt($due - 14, '10:00')], 'log_id');
            if ($st !== 'open') {
                $this->add('svtodo_log', ['todo_id' => $row['todo_id'], 'changed_by' => $to, 'change_type' => 'status-change', 'old_value' => 'open', 'new_value' => $st,
                    'change_time' => $this->dt(min($due, -1), '09:00')], 'log_id');
            }
        }
    }

    // =====================================================================
    // Abwesenheiten mit Vertretung
    // =====================================================================
    private function absences()
    {
        $a = [
            [4, -2, 5, 5, 'Urlaub', 'Urlaub'],
            [7, 14, 21, 8, 'Dienstreise', 'Dienstreise'],
            [1, 30, 44, 2, 'Urlaub (Vertretung Finanzvorstand)', 'Urlaub'],
            [9, -30, -24, 7, 'Krankheit', 'Krankheit'],
        ];
        foreach ($a as [$mem, $s, $e, $sub, $reason]) {
            $this->add('svabsences', ['member_id' => $mem, 'start_date' => $this->day($s), 'end_date' => $this->day($e),
                'substitute_member_id' => $sub, 'reason' => $reason, 'created_at' => $this->dt($s - 10, '10:00')], 'absence_id');
        }
    }

    // =====================================================================
    // Kalender
    // =====================================================================
    private function events()
    {
        $anchor = $this->anchor;
        // nächster Dienstag/Mittwoch... Startdaten liegen in der Vergangenheit/Zukunft, Serien laufen weiter
        $dow = (int)$anchor->format('N');           // 1 = Montag
        $lastTue = -(($dow - 2 + 7) % 7);           // letzter Dienstag (oder heute)
        $e = [
            ['Wöchentliche Vorstandsrunde', 'Kurze Abstimmung des Vorstands (nur Vorstand).', 'Online per Jitsi', $this->dt($lastTue - 28, '18:00'), $this->dt($lastTue - 28, '19:00'), 0, 'sitzung', 'weekly', $this->day(120), 'roles', 'vorstand', 4, 'active'],
            ['Team-Stand-up (14-tägig)', 'Kurzer Austausch aller Aktiven.', 'Online per Jitsi', $this->dt($lastTue - 14 + 1, '09:30'), $this->dt($lastTue - 14 + 1, '09:50'), 0, 'treffen', 'biweekly', $this->day(100), 'all', null, 5, 'active'],
            ['Monatlicher Kassenabschluss', 'Frist für Belege und Buchungen.', null, $this->dt(-12, '00:00'), null, 1, 'frist', 'monthly', $this->day(180), 'roles', 'vorstand,gf', 7, 'active'],
            ['Mitgliederstammtisch', 'Offener Treff für alle Mitglieder.', 'Gasthaus Zur Linde (Beispiel)', $this->dt(-9, '19:30'), $this->dt(-9, '22:00'), 0, 'treffen', 'monthly', null, 'all', null, 8, 'active'],
            ['Jahreshauptversammlung', 'Jährliche Mitgliederversammlung, Einladung folgt.', 'Bürgerhaus (Beispiel)', $this->dt(51, '10:00'), $this->dt(51, '16:00'), 0, 'sitzung', 'yearly', null, 'all', null, 4, 'active'],
            ['Betriebsausflug', 'Ganztägiger Ausflug der Geschäftsstelle.', 'Treffpunkt Hauptbahnhof', $this->dt(21, '00:00'), null, 1, 'treffen', 'none', null, 'all', null, 6, 'active'],
            ['Vorstandsklausur', 'Interne Klausur, nur für den Vorstand.', 'Seminarhaus am See (Beispiel)', $this->dt(35, '09:00'), $this->dt(36, '16:00'), 0, 'sitzung', 'none', null, 'roles', 'vorstand', 4, 'active'],
            ['Projektgruppe Website', 'Arbeitstreffen der Projektgruppe (Teilnehmerliste).', 'Online per Jitsi', $this->dt(5, '17:00'), $this->dt(5, '18:30'), 0, 'treffen', 'none', null, 'list', null, 11, 'active'],
            ['Urlaub Julia Sommer', 'Vertretung: Thomas Winter.', null, $this->dt(-2, '00:00'), $this->dt(5, '23:59'), 1, 'abwesenheit', 'none', null, 'all', null, 4, 'active'],
            ['Frist: Mitgliedsbeiträge prüfen', 'Bis dahin müssen die Zahlungseingänge geprüft sein.', null, $this->dt(12, '00:00'), null, 1, 'frist', 'none', null, 'all', null, 7, 'active'],
            ['Hausmeister-Termin Geschäftsstelle', 'Begehung der Räume.', 'Geschäftsstelle', $this->dt(9, '14:00'), $this->dt(9, '15:00'), 0, 'sonstiges', 'none', null, 'all', null, 6, 'active'],
            ['Workshop Zeitmanagement (abgesagt)', 'Wegen zu geringer Beteiligung abgesagt.', 'Geschäftsstelle', $this->dt(8, '10:00'), $this->dt(8, '13:00'), 0, 'sonstiges', 'none', null, 'all', null, 6, 'cancelled'],
            ['Alter Termin (gelöscht)', 'Wurde gelöscht.', null, $this->dt(15, '10:00'), null, 0, 'sonstiges', 'none', null, 'all', null, 6, 'deleted'],
        ];
        foreach ($e as [$title, $desc, $loc, $start, $end, $allday, $type, $rec, $recend, $target, $roles, $by, $status]) {
            $row = $this->add('svevents', [
                'title' => $title, 'description' => $desc, 'location' => $loc, 'start_datetime' => $start, 'end_datetime' => $end,
                'all_day' => $allday, 'event_type' => $type, 'recurrence' => $rec, 'recurrence_end_date' => $recend,
                'target_type' => $target, 'target_roles' => $roles, 'created_by_member_id' => $by, 'meeting_id' => null,
                'status' => $status, 'created_at' => $this->dt(-20, '10:00'),
            ], 'event_id');
            if ($target === 'list') {
                foreach ([7, 8, 9, 11] as $mem) $this->add('svevents_participants', ['event_id' => $row['event_id'], 'member_id' => $mem]);
            }
        }
    }

    // =====================================================================
    // Terminumfragen
    // =====================================================================
    private function termine()
    {
        $poll = function ($title, $desc, $by, $status, $created, $extra = []) {
            return $this->add('svpolls', array_merge([
                'title' => $title, 'description' => $desc, 'created_by_member_id' => $by, 'status' => $status,
                'created_at' => $created, 'reminder_enabled' => 0, 'reminder_days' => 1, 'reminder_recipients' => 'voters', 'reminder_sent' => 0,
            ], $extra), 'poll_id');
        };
        $date = function ($pid, $start, $end, $sort, $note = null) {
            return $this->add('svpoll_dates', ['poll_id' => $pid, 'suggested_date' => $start, 'suggested_end_date' => $end, 'sort_order' => $sort, 'notes' => $note, 'created_at' => $this->dt(-4, '10:00')], 'date_id')['date_id'];
        };
        $part = function ($pid, array $members) {
            foreach ($members as $mem) $this->add('svpoll_participants', ['poll_id' => $pid, 'member_id' => $mem, 'invited_at' => $this->dt(-4, '10:00')], 'participant_id');
        };
        $resp = function ($pid, $did, $mem, $vote, $comment = null, $ext = null) {
            $r = ['poll_id' => $pid, 'date_id' => $did, 'member_id' => $mem, 'vote' => $vote, 'comment' => $comment, 'created_at' => $this->dt(-2, '12:00')];
            if ($ext) { $r['external_participant_id'] = $ext; $r['participant_name'] = null; $r['participant_email'] = null; }
            $this->add('svpoll_responses', $r, 'response_id');
        };

        // 1) offen, ausgewählte Teilnehmer (target_type list), Erinnerung aktiv
        $p1 = $poll('Termin Sommerfest-Planung', 'Wann können wir uns zur ersten Planung des Sommerfests treffen?', 9, 'open', $this->dt(-4, '10:00'),
            ['location' => 'Online per Jitsi', 'video_link' => 'https://meet.example.org/demo-sommerfest', 'duration' => 90, 'target_type' => 'list',
             'reminder_enabled' => 1, 'reminder_days' => 2, 'reminder_recipients' => 'voters']);
        $d1 = $date($p1['poll_id'], $this->dt(8, '18:00'), $this->dt(8, '19:30'), 1);
        $d2 = $date($p1['poll_id'], $this->dt(9, '18:00'), $this->dt(9, '19:30'), 2);
        $d3 = $date($p1['poll_id'], $this->dt(12, '19:00'), $this->dt(12, '20:30'), 3, 'Abend nach der Sitzung');
        $part($p1['poll_id'], [1, 2, 4, 5, 7, 8, 9]);
        foreach ([[9, [1, 1, 0]], [8, [1, 0, 1]], [7, [-1, 1, 1]], [1, [0, 1, 1]], [4, [1, 1, -1]]] as [$mem, $v]) {
            foreach ([$d1, $d2, $d3] as $k => $did) $resp($p1['poll_id'], $did, $mem, $v[$k], ($mem == 7 && $k == 0) ? 'Da bin ich in einer Besprechung.' : null);
        }

        // 2) offen, individueller Link mit Token, externe Teilnehmer
        $p2 = $poll('Termin Fototermin Vereinsporträt', 'Terminabstimmung mit dem Fotografen (externer Link).', 6, 'open', $this->dt(-3, '09:00'),
            ['location' => 'Geschäftsstelle', 'duration' => 120, 'target_type' => 'individual', 'access_token' => 'demo-termine-token-0001']);
        $d4 = $date($p2['poll_id'], $this->dt(10, '10:00'), $this->dt(10, '12:00'), 1);
        $d5 = $date($p2['poll_id'], $this->dt(11, '14:00'), $this->dt(11, '16:00'), 2);
        $x1 = $this->add('svexternal_participants', ['poll_type' => 'termine', 'poll_id' => $p2['poll_id'], 'first_name' => 'Frieda', 'last_name' => 'Fotografin',
            'email' => 'frieda.fotografin@example.org', 'mnr' => null, 'session_token' => 'demo-extern-session-0001', 'ip_address' => null, 'consent_given' => 1,
            'created_at' => $this->dt(-2, '08:30'), 'last_activity' => $this->dt(-2, '08:35')], 'external_id');
        $resp($p2['poll_id'], $d4, null, 1, null, $x1['external_id']);
        $resp($p2['poll_id'], $d5, null, 0, 'Nachmittags ginge es nur mit Verzögerung.', $x1['external_id']);
        $resp($p2['poll_id'], $d4, 6, 1);
        $resp($p2['poll_id'], $d5, 6, 1);

        // 3) finalisiert (mit verknüpfter Sitzung 51)
        $p3 = $poll('Termin Vorstandsklausur', 'Abstimmung über den Klausurtermin im Herbst.', 4, 'finalized', $this->dt(-15, '09:00'),
            ['meeting_id' => 51, 'location' => 'Seminarhaus am See (Beispiel)', 'duration' => 480, 'target_type' => 'list', 'finalized_at' => $this->dt(-10, '15:00')]);
        $d6 = $date($p3['poll_id'], $this->dt(35, '09:00'), $this->dt(35, '17:00'), 1);
        $d7 = $date($p3['poll_id'], $this->dt(42, '09:00'), $this->dt(42, '17:00'), 2);
        $part($p3['poll_id'], [1, 2, 3, 4]);
        foreach ([1, 2, 3, 4] as $mem) { $resp($p3['poll_id'], $d6, $mem, 1); $resp($p3['poll_id'], $d7, $mem, ($mem == 3) ? -1 : 0); }
        foreach ($this->tables['svpolls'] as &$pp) { if ($pp['poll_id'] == $p3['poll_id']) $pp['final_date_id'] = $d6; }
        unset($pp);

        // 4) geschlossen (ohne Finalisierung)
        $p4 = $poll('Termin Online-Schulung Buchhaltung', 'Die Abstimmung wurde ohne Termin geschlossen.', 7, 'closed', $this->dt(-40, '09:00'),
            ['duration' => 60, 'target_type' => 'list']);
        $d8 = $date($p4['poll_id'], $this->dt(-30, '17:00'), $this->dt(-30, '18:00'), 1);
        $part($p4['poll_id'], [7, 8, 9, 10]);
        $resp($p4['poll_id'], $d8, 7, 1); $resp($p4['poll_id'], $d8, 8, -1); $resp($p4['poll_id'], $d8, 9, -1); $resp($p4['poll_id'], $d8, 10, 0);

        $this->add('svtermine_user_hidden', ['poll_id' => $p4['poll_id'], 'member_id' => 10, 'hidden_at' => $this->dt(-20, '10:00')]);
    }

    // =====================================================================
    // Meinungsbilder
    // =====================================================================
    private function meinungsbilder()
    {
        $tpl = [
            ['Ja/Nein/Enthaltung', 'Ja', 'Nein', 'Enthaltung'],
            ['Passt-Skala', 'passt sehr gut', 'passt gut', 'passt einigermaßen', 'passt schlecht', 'passt gar nicht'],
            ['Dafür/Dagegen', 'unbedingt dafür', 'eher dafür', 'neutral', 'eher dagegen', 'unbedingt dagegen'],
            ['Gefällt mir', 'gefällt mir sehr gut', 'gefällt mir gut', 'neutral', 'gefällt mir nicht', 'gefällt mir überhaupt nicht'],
            ['Skala 1-9', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['Dringlichkeit', 'Sofort!', 'sehr dringlich', 'dringlich', 'kann warten', 'nicht machen'],
            ['Wichtigkeit', 'unabdingbar', 'sehr wichtig', 'eher wichtig', 'nicht wichtig', 'Auf keinen Fall'],
            ['Wünsche', 'Sehr!', 'Würde mich freuen', 'ist mir egal', 'würde ich nicht wollen', 'Auf keinen Fall'],
            ['Häufigkeit', 'immer', 'sehr oft', 'häufig', 'ab und zu', 'selten', 'fast nie', 'nie'],
            ['Priorität', 'Absolutes Muss', 'sehr wichtig', 'wichtig', 'nice to have', 'unnötig', 'Auf keinen Fall'],
            ['Frei'],
            ['Nützlichkeit', 'sehr nützlich', 'etwas nützlich', 'überflüssig'],
            ['Bewertung', 'langweilig', 'Zeitvertreib', 'spannend'],
        ];
        foreach ($tpl as $t) {
            $row = ['template_name' => array_shift($t), 'created_at' => $this->dt(-400, '09:00')];
            foreach ($t as $k => $opt) $row['option_' . ($k + 1)] = $opt;
            $this->add('svopinion_answer_templates', $row, 'template_id');
        }

        $poll = function ($title, $by, $target, $listId, $token, $tplId, $multi, $anon, $status, $created, $ends, $delete) {
            return $this->add('svopinion_polls', [
                'title' => $title, 'creator_member_id' => $by, 'target_type' => $target, 'list_id' => $listId, 'access_token' => $token,
                'template_id' => $tplId, 'allow_multiple_answers' => $multi, 'is_anonymous' => $anon, 'duration_days' => 14,
                'show_intermediate_after_days' => 7, 'delete_after_days' => 30, 'status' => $status, 'created_at' => $created,
                'ends_at' => $ends, 'delete_at' => $delete,
            ], 'poll_id')['poll_id'];
        };
        $opts = function ($pid, array $texts) {
            $ids = [];
            foreach ($texts as $k => $txt) $ids[] = $this->add('svopinion_poll_options', ['poll_id' => $pid, 'option_text' => $txt, 'sort_order' => $k + 1, 'created_at' => $this->dt(-5, '10:00')], 'option_id')['option_id'];
            return $ids;
        };
        $answer = function ($pid, $mem, $session, $free, $optIds, $when, $force = 0) {
            $r = $this->add('svopinion_responses', ['poll_id' => $pid, 'member_id' => $mem, 'session_token' => $session, 'free_text' => $free,
                'force_anonymous' => $force, 'responded_at' => $when, 'created_at' => $when], 'response_id');
            foreach ($optIds as $oid) $this->add('svopinion_response_options', ['response_id' => $r['response_id'], 'option_id' => $oid]);
        };

        // 1) aktiv, Teilnehmer der Sitzung 51 (target_type list), Vorlage "Ja/Nein/Enthaltung"
        $m1 = $poll('Soll die Vorstandssitzung künftig hybrid stattfinden?', 4, 'list', 51, null, 1, 0, 0, 'active', $this->dt(-5, '10:00'), $this->dt(9, '23:59'), $this->dt(39, '23:59'));
        $o1 = $opts($m1, ['Ja', 'Nein', 'Enthaltung']);
        foreach ([1, 2, 3, 4, 5, 6, 7] as $mem) $this->add('svopinion_poll_participants', ['poll_id' => $m1, 'member_id' => $mem, 'invited_at' => $this->dt(-5, '10:00')], 'participant_id');
        $answer($m1, 1, null, 'Gut für Mitglieder mit weitem Anreiseweg.', [$o1[0]], $this->dt(-4, '09:00'));
        $answer($m1, 2, null, null, [$o1[0]], $this->dt(-4, '11:30'));
        $answer($m1, 5, null, 'Technik muss dann zuverlässig laufen.', [$o1[2]], $this->dt(-3, '08:20'));
        $answer($m1, 7, null, null, [$o1[1]], $this->dt(-2, '16:00'));

        // 2) aktiv, öffentlich und anonym, Mehrfachauswahl, freie Optionen
        $m2 = $poll('Welche Themen sollen auf der Mitgliederversammlung vorrangig behandelt werden?', 8, 'public', null, null, 11, 1, 1, 'active', $this->dt(-6, '10:00'), $this->dt(8, '23:59'), $this->dt(38, '23:59'));
        $o2 = $opts($m2, ['Beitragsordnung', 'Online-Teilnahme', 'Sommerfest 2027', 'Neue Geschäftsstelle', 'Mitgliederportal']);
        $sess = 0;
        foreach ([[0, 1], [1, 2], [1, 4], [0, 1, 4], [3], [1, 2], [2, 4], [1]] as $sel) {
            $sess++;
            $answer($m2, null, 'demo-session-' . sprintf('%03d', $sess), ($sess == 3) ? 'Bitte genug Zeit für Fragen einplanen.' : null, array_map(function ($i) use ($o2) { return $o2[$i]; }, $sel), $this->dt(-5 + ($sess % 4), '1' . ($sess % 10) . ':00'));
        }

        // 3) aktiv, individueller Link mit Zugangs-Token (nur Fake-Token)
        $m3 = $poll('Wie gefällt Ihnen das neue Veranstaltungskonzept?', 9, 'individual', null, 'demo-meinungsbild-token-0001', 4, 0, 0, 'active', $this->dt(-1, '10:00'), $this->dt(2, '23:59'), $this->dt(32, '23:59'));
        $o3 = $opts($m3, ['gefällt mir sehr gut', 'gefällt mir gut', 'neutral', 'gefällt mir nicht', 'gefällt mir überhaupt nicht']);
        $x2 = $this->add('svexternal_participants', ['poll_type' => 'meinungsbild', 'poll_id' => $m3, 'first_name' => 'Erwin', 'last_name' => 'Externer',
            'email' => 'erwin.externer@example.org', 'mnr' => null, 'session_token' => 'demo-extern-session-0002', 'ip_address' => null, 'consent_given' => 1,
            'created_at' => $this->dt(0, '08:00'), 'last_activity' => $this->dt(0, '08:05')], 'external_id');
        $answer($m3, 8, null, 'Zwei Tage sind eine gute Idee.', [$o3[0]], $this->dt(0, '07:40'));
        $r = $this->add('svopinion_responses', ['poll_id' => $m3, 'member_id' => null, 'external_participant_id' => $x2['external_id'], 'session_token' => 'demo-extern-session-0002',
            'free_text' => 'Aus Sicht eines Gastes sehr ansprechend.', 'force_anonymous' => 0, 'responded_at' => $this->dt(0, '08:05'), 'created_at' => $this->dt(0, '08:05')], 'response_id');
        $this->add('svopinion_response_options', ['response_id' => $r['response_id'], 'option_id' => $o3[1]]);

        // 4) beendet, Liste (Sitzung 30), Ergebnisse sichtbar
        $m4 = $poll('Soll das Protokoll künftig innerhalb von 48 Stunden versendet werden?', 4, 'list', 30, null, 1, 0, 0, 'ended', $this->dt(-14, '10:00'), $this->dt(-1, '23:59'), $this->dt(19, '23:59'));
        $o4 = $opts($m4, ['Ja', 'Nein', 'Enthaltung']);
        foreach ([1, 2, 3, 4, 6, 9] as $mem) $this->add('svopinion_poll_participants', ['poll_id' => $m4, 'member_id' => $mem, 'invited_at' => $this->dt(-14, '10:00')], 'participant_id');
        foreach ([[1, 0], [2, 0], [3, 0], [4, 0], [6, 2], [9, 0]] as [$mem, $oi]) $answer($m4, $mem, null, null, [$o4[$oi]], $this->dt(-12, '1' . $mem . ':00'));

        // 5) beendet, öffentlich, Skala
        $m5 = $poll('Wie wichtig ist Ihnen eine eigene Geschäftsstelle?', 1, 'public', null, null, 7, 0, 1, 'ended', $this->dt(-40, '10:00'), $this->dt(-26, '23:59'), $this->dt(4, '23:59'));
        $o5 = $opts($m5, ['unabdingbar', 'sehr wichtig', 'eher wichtig', 'nicht wichtig', 'Auf keinen Fall']);
        foreach ([1, 1, 2, 2, 2, 3, 0, 4] as $k => $oi) $answer($m5, null, 'demo-session-1' . sprintf('%02d', $k), null, [$o5[$oi]], $this->dt(-35 + $k, '12:00'));

        $this->add('svopinion_user_hidden', ['poll_id' => $m5, 'member_id' => 10, 'hidden_at' => $this->dt(-10, '10:00')]);
    }

    // =====================================================================
    // Dokumente
    // =====================================================================
    private function documents()
    {
        // Titel, Kategorie, Zugriffslevel, Status, Datei, Version, Beschreibung, Stichwörter, ggf. externe URL
        $d = [
            ['Satzung des Vereins', 'satzung', 0, 'active', 'satzung.pdf', 'Fassung 2024', 'Aktuelle Satzung (Beispieldokument).', 'satzung, verein, grundlagen', null],
            ['Geschäftsordnung des Vorstands', 'ordnungen', 10, 'active', 'geschaeftsordnung.pdf', '3.1', 'Regelt Sitzungen, Beschlüsse und Vertretung.', 'geschäftsordnung, vorstand', null],
            ['Beitragsordnung', 'ordnungen', 0, 'outdated', 'beitragsordnung_2024.pdf', '2024', 'Wird durch den laufenden Antrag ersetzt.', 'beitrag, gebühren', null],
            ['Reisekostenrichtlinie', 'richtlinien', 10, 'active', 'reisekosten.pdf', '1.2', 'Erstattung von Reise- und Übernachtungskosten.', 'reisekosten, erstattung', null],
            ['Antragsformular Verfügung', 'formulare', 0, 'active', 'antrag_verfuegung.docx', '1.0', 'Vorlage für Verfügungen bis 500 Euro.', 'formular, antrag', null],
            ['Unterlagen Mitgliederversammlung 2025', 'mv_unterlagen', 10, 'archived', 'mv2025_unterlagen.zip', null, 'Einladung, Tagesordnung und Protokoll.', 'mitgliederversammlung, protokoll', null],
            ['Handbuch Sitzungsverwaltung (Demo)', 'dokumentationen', 10, 'active', null, '2.0', 'Externe Dokumentation, verlinkt.', 'handbuch, anleitung', 'https://docs.example.org/sitzungsverwaltung'],
            ['Urteil Vereinsrecht (Beispiel)', 'urteile', 18, 'hidden', 'urteil_beispiel.pdf', null, 'Nur für Vorstand und GF sichtbar.', 'urteil, vereinsrecht', null],
            ['Pressefoto Vorstand', 'medien', 0, 'active', 'pressefoto.jpg', null, 'Foto für die Öffentlichkeitsarbeit (Platzhalter).', 'foto, presse', null],
            ['Checkliste Veranstaltungen', 'sonstige', 10, 'active', 'checkliste_veranstaltungen.pdf', '1.0', 'Leitfaden für die Planung von Veranstaltungen.', 'veranstaltung, checkliste', null],
        ];
        foreach ($d as $i => [$title, $cat, $lvl, $status, $file, $ver, $desc, $kw, $url]) {
            $ext = $file ? substr($file, strrpos($file, '.') + 1) : 'url';
            $types = ['pdf' => 'application/pdf', 'docx' => 'application/msword', 'zip' => 'application/zip', 'jpg' => 'image/jpeg', 'url' => 'text/uri-list'];
            $row = [
                'filename' => $file ? 'demo_' . $file : 'extern_link', 'original_filename' => $file ?: 'externer-link.url',
                'filepath' => $file ? 'uploads/documents/demo/' . $file : '', 'filesize' => $file ? 80000 + $i * 17321 : 0,
                'filetype' => $types[$ext] ?? 'application/octet-stream', 'title' => $title, 'description' => $desc,
                'keywords' => $kw, 'version' => $ver, 'short_url' => null, 'category' => $cat, 'access_level' => $lvl, 'status' => $status,
                'uploaded_by_member_id' => ($i % 2) ? 4 : 5, 'created_at' => $this->dt(-200 + $i * 12, '10:00'), 'updated_at' => $this->dt(-60 + $i * 5, '10:00'),
                'admin_notes' => ($status === 'hidden') ? 'Demo: nur Metadaten, keine Datei hinterlegt.' : null,
            ];
            if ($url) $row['external_url'] = $url;    // optionale Spalte (add_external_url_to_documents.sql)
            $this->add('svdocuments', $row, 'document_id');
        }
    }

    // =====================================================================
    // Kollaborative Texte
    // =====================================================================
    private function collabTexts()
    {
        $texts = [
            // Titel, Sitzung, Ersteller, Status, final_name, Absätze, Mitarbeitende
            ['Entwurf Datenschutzhinweis für die Website', null, 4, 'active', null,
                ['Wir verarbeiten personenbezogene Daten ausschließlich zur Mitgliederverwaltung und zur Durchführung von Veranstaltungen.', 'Die Daten werden nicht an Dritte weitergegeben, soweit keine gesetzliche Pflicht besteht.', 'Betroffene können jederzeit Auskunft, Berichtigung und Löschung verlangen.'], [4, 5, 6]],
            ['Einladungstext Vorstandssitzung (nächste Woche)', 51, 5, 'active', null,
                ['Liebe Vorstandsmitglieder, hiermit lade ich zur Sitzung ein.', 'Die Tagesordnung entnehmt ihr bitte dem Sitzungstool.', 'Anträge können bis zum Antragsschluss eingestellt werden.'], [4, 5, 1]],
            ['Leitbild des Vereins', null, 1, 'finalized', 'Leitbild 2026',
                ['Wir verstehen uns als offene Gemeinschaft, die Bildung und Begegnung fördert.', 'Wir handeln transparent und entscheiden gemeinsam.', 'Ehrenamtliches Engagement wird bei uns wertgeschätzt.'], [1, 2, 3, 4]],
        ];
        foreach ($texts as $n => [$title, $mid, $by, $status, $final, $paras, $who]) {
            $t = $this->add('svcollab_texts', ['meeting_id' => $mid, 'title' => $title, 'initiator_member_id' => $by, 'status' => $status,
                'created_at' => $this->dt(-20 + $n * 5, '10:00'), 'finalized_at' => $status === 'finalized' ? $this->dt(-8, '16:00') : null, 'final_name' => $final], 'text_id');
            foreach ($paras as $k => $content) {
                $this->add('svcollab_text_paragraphs', ['text_id' => $t['text_id'], 'paragraph_order' => $k + 1, 'content' => $content,
                    'last_edited_by' => $who[$k % count($who)], 'last_edited_at' => $this->dt(-3 - $k, '15:00'), 'created_at' => $this->dt(-20 + $n * 5, '10:00')], 'paragraph_id');
            }
            foreach ($who as $mem) {
                $this->add('svcollab_text_participants', ['text_id' => $t['text_id'], 'member_id' => $mem, 'last_seen' => $this->dt(-1, '09:00'), 'joined_at' => $this->dt(-18 + $n * 5, '10:00')], 'participant_id');
            }
            $this->add('svcollab_text_versions', ['text_id' => $t['text_id'], 'version_number' => 1, 'content' => $paras[0], 'created_by' => $by,
                'created_at' => $this->dt(-15 + $n * 3, '11:00'), 'version_note' => 'Erster Entwurf'], 'version_id');
            $this->add('svcollab_text_versions', ['text_id' => $t['text_id'], 'version_number' => 2, 'content' => implode("\n\n", $paras), 'created_by' => $who[count($who) - 1],
                'created_at' => $this->dt(-8, '11:00'), 'version_note' => $status === 'finalized' ? 'Finale Fassung' : 'Ergänzt um weitere Absätze'], 'version_id');
        }
    }

    // =====================================================================
    // Benachrichtigungen und Einstellungen
    // =====================================================================
    private function notifications()
    {
        // Mitglied, Typ, Titel, Text, Link, gelesen, Tage rel., Stunde, meeting, todo, item
        $n = [
            [1, 'reminder', 'Abstimmung endet heute', 'Bei Antrag ' . DEMO_REMINDER_ANTRNR . ' fehlt noch deine Stimme.', 'abstimmungen.php?antrnr=' . DEMO_REMINDER_ANTRNR, 0, 0, '00:05', null, null, null],
            [3, 'reminder', 'Abstimmung endet heute', 'Bei Antrag ' . DEMO_REMINDER_ANTRNR . ' fehlt noch deine Stimme.', 'abstimmungen.php?antrnr=' . DEMO_REMINDER_ANTRNR, 0, 0, '00:05', null, null, null],
            [1, 'meeting', 'Sitzung beginnt bald', 'Jour Fixe Vorstand beginnt heute.', 'index.php?tab=meetings&meeting_id=50', 0, 0, '00:10', 50, null, null],
            [1, 'assignment', 'Neues ToDo', 'Dir wurde das ToDo "Vergleichsangebot Steuerberatung einholen" zugewiesen.', 'index.php?tab=todos', 0, -1, '09:00', null, null, null],
            [2, 'comment', 'Neuer Kommentar', 'Max Mustermann hat TOP 2 "Planung Sommerfest 2027" kommentiert.', 'index.php?tab=meetings&meeting_id=50', 1, -1, '09:12', 50, null, null],
            [4, 'meeting', 'Sitzung beendet', 'Das Protokoll zur Sitzung kann nun bearbeitet werden.', 'index.php?tab=meetings&meeting_id=20', 0, -1, '21:00', 20, null, null],
            [5, 'todo', 'ToDo überfällig', 'Das ToDo "Einladung zur Mitgliederversammlung entwerfen" ist überfällig.', 'index.php?tab=todos', 0, -2, '07:30', null, null, null],
            [8, 'assignment', 'Neues ToDo', 'Dir wurde das ToDo "Saal für Mitgliederversammlung reservieren" zugewiesen.', 'index.php?tab=todos', 1, -3, '10:00', null, null, null],
            [9, 'system', 'Willkommen im Demo-System', 'Dies ist ein Demo-Zugang mit erfundenen Daten.', 'index.php', 1, -7, '10:00', null, null, null],
            [7, 'comment', 'Neuer Kommentar', 'Julia Sommer hat TOP 2 "Haushaltsrahmen" kommentiert.', 'index.php?tab=meetings&meeting_id=64', 0, -1, '13:10', 64, null, null],
            [3, 'meeting', 'Protokoll liegt vor', 'Das Protokoll der Sitzung liegt zur Durchsicht vor.', 'index.php?tab=meetings&meeting_id=30', 1, -4, '12:00', 30, null, null],
            [11, 'assignment', 'Neues ToDo', 'Dir wurde das ToDo "Website: Terminübersicht aktualisieren" zugewiesen.', 'index.php?tab=todos', 0, -5, '09:30', null, null, null],
        ];
        foreach ($n as [$mem, $type, $title, $msg, $link, $read, $off, $time, $mid, $tid, $iid]) {
            $this->add('svnotifications', ['member_id' => $mem, 'type' => $type, 'title' => $title, 'message' => $msg, 'link' => $link,
                'is_read' => $read, 'created_at' => $this->dt($off, $time), 'related_meeting_id' => $mid, 'related_todo_id' => $tid, 'related_item_id' => $iid], 'notification_id');
        }
        // Einstellungen im Benachrichtigungs-Center (E-Mail an/aus je Ereignis)
        $prefs = [1 => ['antrag_neu' => 1, 'antrag_abstimmung' => 1, 'antrag_beschlossen' => 1, 'todo_zugewiesen' => 1],
                  2 => ['antrag_abstimmung' => 1, 'top_kommentar' => 0],
                  5 => ['antrag_neu' => 0, 'top_neu' => 1, 'todo_zugewiesen' => 1],
                  8 => ['antrag_geaendert' => 1, 'antrag_hinweis' => 0]];
        foreach ($prefs as $mem => $list) foreach ($list as $ev => $on) $this->add('svnotification_prefs', ['member_id' => $mem, 'event_type' => $ev, 'email' => $on]);
    }

    private function misc()
    {
        // Feedback-Einträge (Tabelle "feedback", nur wenn vorhanden; ENABLE_FEEDBACK_SYSTEM)
        $fb = [
            [8, 'Die Antragsliste ist übersichtlich, ein Filter nach Ressort wäre schön.', -9, 0],
            [10, 'Als Kassenprüfer sehe ich die Beschlüsse, aber keine Anhänge. Ist das Absicht?', -6, 0],
            [9, 'Kalender: Der Monatsstammtisch erscheint doppelt (Test).', -4, 1],
            [5, 'Bitte die Erinnerung zur Abstimmung auch per Push anbieten.', -2, 0],
        ];
        foreach ($fb as [$mem, $txt, $off, $del]) {
            $this->add('feedback', ['member_id' => $mem, 'feedback_text' => $txt, 'created_at' => $this->dt($off, '11:00'), 'is_deleted' => $del], 'id');
        }
    }
}

// =========================================================================
// Hauptfunktion (CLI + Browser)
// =========================================================================

/** Daten bauen und als JSON schreiben. Rückgabe: ['data'=>..., 'file'=>..., 'bytes'=>..., 'rows'=>...] */
function demo_build_generate($anchor_date, $out_file = null)
{
    $b = new DemoBuilder($anchor_date);
    $data = $b->build();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('JSON-Kodierung fehlgeschlagen: ' . json_last_error_msg());
    $rows = 0;
    foreach ($data['tables'] as $r) $rows += count($r);
    $bytes = null;
    if ($out_file !== null) {
        if ($out_file === '-') { echo $json . "\n"; }
        else { $bytes = file_put_contents($out_file, $json . "\n"); if ($bytes === false) throw new RuntimeException("Konnte $out_file nicht schreiben."); }
    }
    return ['data' => $data, 'json' => $json, 'file' => $out_file, 'bytes' => $bytes, 'rows' => $rows];
}

/** Einstieg für CLI ($argv) und Browser; wird auch von demo.php verwendet */
function demo_build_main()
{
    date_default_timezone_set('Europe/Berlin');
    if (PHP_SAPI === 'cli') {
        $opts = getopt('', ['anchor::', 'out::', 'stdout', 'quiet', 'help']);
        if (isset($opts['help'])) {
            echo "Aufruf: php tools/demo_build.php [--anchor=YYYY-MM-DD] [--out=Pfad] [--stdout] [--quiet]\n";
            return 0;
        }
        $anchor = $opts['anchor'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) { fwrite(STDERR, "Ungültiger Anker: $anchor\n"); return 1; }
        $out = isset($opts['stdout']) ? '-' : ($opts['out'] ?? (__DIR__ . '/demo_data.json'));
        $res = demo_build_generate($anchor, $out);
        if (!isset($opts['quiet']) && $out !== '-') {
            fwrite(STDERR, "demo_data.json erzeugt: $out (" . number_format($res['bytes']) . " Bytes, " . $res['rows'] . " Datensätze, Anker $anchor)\n");
            fwrite(STDERR, "Weiter: tools/demo_import.php (Browser) einspielen. Demo-Passwort aller Benutzer: " . DEMO_PASSWORD . "\n");
        }
        return 0;
    }

    // ---- Browser ----
    if (!defined('DEMO_MODE_ENABLED') || !DEMO_MODE_ENABLED) {
        echo '<h1>Fehler</h1><p>Der Demo-Builder ist nur verfügbar, wenn <code>DEMO_MODE_ENABLED = true</code> in der config.php gesetzt ist.</p>';
        return 1;
    }
    if (!demo_browser_auth()) return 1;

    $anchor = $_POST['anchor'] ?? date('Y-m-d');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Demo-Daten erzeugen</title>'
       . '<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:20px;background:#f5f5f5}'
       . '.card{background:#fff;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 2px 4px rgba(0,0,0,.1)}'
       . '.ok{background:#d4edda;border-left:4px solid #28a745;padding:15px}.err{background:#f8d7da;border-left:4px solid #dc3545;padding:15px}'
       . 'table{border-collapse:collapse;width:100%}td,th{padding:6px 10px;border-bottom:1px solid #ddd;text-align:left}'
       . '.btn{display:inline-block;padding:10px 20px;background:#007bff;color:#fff;border-radius:5px;text-decoration:none;border:0;cursor:pointer;font-size:15px}</style></head><body>';
    echo '<h1>🛠️ Demo-Daten erzeugen</h1><div class="card"><p>Erzeugt <code>tools/demo_data.json</code> aus frei erfundenen Daten (alle Funktionen, '
       . 'Mitglieder aller Rollen, Passwort <code>' . htmlspecialchars(DEMO_PASSWORD) . '</code>). Danach mit dem Import einspielen.</p>'
       . '<form method="post"><label>Bezugstag (Anker): <input type="date" name="anchor" value="' . htmlspecialchars($anchor) . '"></label> '
       . '<button class="btn" type="submit" name="build" value="1">JSON erzeugen</button></form></div>';
    if (isset($_POST['build'])) {
        try {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) throw new RuntimeException('Ungültiger Anker.');
            $res = demo_build_generate($anchor, __DIR__ . '/demo_data.json');
            echo '<div class="ok"><h3>✅ demo_data.json erzeugt</h3><p>' . number_format($res['bytes']) . ' Bytes, ' . $res['rows'] . ' Datensätze, Anker ' . htmlspecialchars($anchor) . '</p></div>';
            echo '<div class="card"><table><tr><th>Tabelle</th><th>Zeilen</th></tr>';
            foreach ($res['data']['tables'] as $t => $rows) echo '<tr><td>' . htmlspecialchars($t) . '</td><td>' . count($rows) . '</td></tr>';
            // Aufruf über demo.php (Wurzel) oder tools/demo_build.php: Links entsprechend relativ setzen
            $tools_url = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'demo_build.php') ? '' : 'tools/';
            echo '</table><p><a class="btn" href="' . $tools_url . 'demo_analyze.php">🔍 Analysieren</a> <a class="btn" style="background:#28a745" href="' . $tools_url . 'demo_import.php">📥 Importieren</a></p></div>';
        } catch (Throwable $e) {
            echo '<div class="err"><h3>❌ Fehler</h3><p>' . htmlspecialchars($e->getMessage()) . '</p></div>';
        }
    }
    echo '</body></html>';
    return 0;
}

// Direktaufruf (nicht beim Einbinden durch demo.php)
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    if (PHP_SAPI !== 'cli') {
        require_once __DIR__ . '/../config.php';
    }
    exit(demo_build_main());
}
