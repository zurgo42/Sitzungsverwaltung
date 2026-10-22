<?php
/**
 * demo_common.php - Gemeinsame Definitionen der Demo-Werkzeuge
 *
 * Wird von demo_build.php, demo_export.php, demo_import.php, demo_analyze.php und
 * update_demo_dates.php eingebunden. Enthält KEINE Logik mit Seiteneffekten.
 *
 * Inhalt:
 *  - DEMO_EXCLUDED_TABLES      Tabellen mit personenbezogenen/laufzeitabhängigen Daten (Datenschutz)
 *  - DEMO_BUILDER_ONLY_TABLES  Tabellen, die nur der Builder (mit Fake-Daten) befüllt, nie der Export
 *  - DEMO_RUNTIME_CONFIG_KEYS  svconfig-Schlüssel, die zur Laufzeit gesetzt werden und nicht exportiert werden
 *  - DEMO_REMINDER_ANTRNR      Kennung des Anspruchs-Antrags für die 24h-Abstimmungserinnerung
 *  - Rollen -> aktiv/Funktion  (Standalone svmembers <-> VTool berechtigte)
 *  - Hilfsfunktionen           (berechtigte ableiten, Fremd-Mailadressen finden, Spalten bereinigen)
 */

if (defined('DEMO_COMMON_LOADED')) return;
define('DEMO_COMMON_LOADED', true);

/** Passwort aller Demo-Benutzer (welcome.php loggt mit genau diesem Passwort ein) */
define('DEMO_PASSWORD', 'test123');

/** Antragsnummer des Antrags, der immer im 24h-Erinnerungsfenster der Abstimmung steht */
define('DEMO_REMINDER_ANTRNR', 'B26010101');

/**
 * Slots (VName1..6) des Erinnerungs-Antrags, deren Votum täglich zurückgesetzt wird,
 * damit immer mindestens eine Stimme fehlt (Besucher können dort abgestimmt haben).
 * Im Builder sind das die Vorstände Nr. 2 und 3 (Erika, Lukas).
 */
define('DEMO_REMINDER_OPEN_SLOTS', [2, 3]);

/** svconfig-Schlüssel: Datum, auf das sich alle relativen Demo-Datumsangaben beziehen */
// Vorgaben für automatisch angelegte TOPs (TOP 0/99): gleiche Quelle wie die Anwendung
require_once __DIR__ . '/../agenda_defaults.php';

define('DEMO_ANCHOR_KEY', 'demo_anchor_date');

// Drei Demo-Versionen: 'voll' und 'einfach' kommen aus demo_data.json (vorgegeben, von demo_build.php erzeugt),
// 'aktuell' ist der zuletzt mit demo_export.php gespeicherte eigene Stand (demo_data_aktuell.json).
define('DEMO_FILE_BASIS', __DIR__ . '/demo_data.json');
define('DEMO_FILE_AKTUELL', __DIR__ . '/demo_data_aktuell.json');

// Beispiel-Antrag für "automatisch zur Abstimmung stellen": laufende Nummer 77 (Präfix A = wartet, B = automatisch eingestellt).
// Wirkt nur, wenn für Typ B eine Wartezeit konfiguriert ist (bart_B_wartezeit_aktiv/-tage); siehe update_demo_dates.php
define('DEMO_AUTO_ANTRNR_SUFFIX', '77');

/**
 * Datenschutz: Diese Tabellen werden weder exportiert noch importiert; beim Import werden sie
 * (falls vorhanden) geleert. Begründung steht jeweils dabei.
 * Schlüssel = Tabellenname, Wert = Grund.
 */
define('DEMO_EXCLUDED_TABLES', [
    'svmail_queue'           => 'E-Mail-Warteschlange: Empfängeradressen, Betreff und Nachrichtentext',
    'svmail_notifications'   => 'Benachrichtigungs-Mails je Mitglied: Betreff und Nachrichtentext (Mail-Queue)',
    'svpush_subscriptions'   => 'Browser-Push-Abos: Endpoint-URL, Schlüssel, User-Agent',
    'svexternal_access_log'  => 'Zugriffslog externer Teilnehmer: Name, E-Mail, IP-Adresse, User-Agent',
    'svaudit_log'            => 'DB-Audit-Log: IP-Adressen und SQL mit Parameterwerten',
    'svadmin_log'            => 'Admin-Log: IP-Adresse, User-Agent, alte/neue Werte',
    'svdocument_downloads'   => 'Download-Log: IP-Adresse und Mitglied je Abruf',
    'svvote_reminders'       => 'Versandmarker der Abstimmungs-Erinnerung (muss nach Reset leer sein, damit die Erinnerung neu auslösbar ist)',
    'svcollab_text_locks'    => 'Laufzeit-Sperren (Sitzungsdaten) der kollaborativen Texte',
    'protokoll'              => 'Aktions-Protokoll (Log): Mitgliedsnummer, Kurzname, Aktionen',
]);

/**
 * Tabellen, die nur der Builder mit offensichtlich erfundenen Daten füllt.
 * Der Export aus einer Live-Datenbank überspringt sie (echte Adressen/Tokens/IPs).
 */
define('DEMO_BUILDER_ONLY_TABLES', [
    'svexternal_participants' => 'Externe Teilnehmer: Name, E-Mail, IP, Session-Token (Demo: nur Fake-Daten @example.org, ohne IP)',
]);

/**
 * Tabelle der Mitgliederquelle für VTool-Variante: wird nie exportiert, beim Import
 * (nur im Standalone-Betrieb) aus svmembers abgeleitet, damit Anträge/Terminplanung funktionieren.
 */
define('DEMO_DERIVED_TABLE', 'berechtigte');

/** svconfig-Schlüssel, die zur Laufzeit entstehen und nicht zum Demo-Stand gehören */
define('DEMO_RUNTIME_CONFIG_KEYS', [
    'nm_last_digest_date',     // Datum des letzten Digest-Versands (pseudo_cron)
    'protokoll_last_archive',  // Archivierungs-Monat der protokoll-Tabelle (pseudo_cron)
]);

/**
 * Spalten, die auch in an sich unkritischen Tabellen Token/Adressen enthalten können und beim
 * Export aus einer Live-Datenbank bereinigt werden. Wert: 'token' = durch Demo-Token ersetzen,
 * 'null' = auf NULL setzen.
 */
define('DEMO_SCRUB_COLUMNS', [
    'svpolls'            => ['access_token' => 'token'],
    'svopinion_polls'    => ['access_token' => 'token'],
    'svopinion_responses'=> ['session_token' => 'token'],
    'svpoll_responses'   => ['participant_email' => 'null', 'participant_name' => 'null'],
]);

/** E-Mail-Domains, die als Fake-Adressen erlaubt sind (inkl. Subdomains) */
define('DEMO_ALLOWED_MAIL_DOMAINS', ['example.com', 'example.org', 'example.net']);

/**
 * Rollen-Mapping Standalone (svmembers.role) <-> VTool (berechtigte.aktiv/Funktion).
 * Muss mit adapters/MemberAdapter.php (BerechtigteAdapter::mapRole) übereinstimmen: aus
 * aktiv/Funktion abgeleitet muss wieder dieselbe Rolle herauskommen.
 *
 *  svmembers.role  | aktiv   | Funktion (Demo)            | Anmerkung
 *  ----------------+---------+----------------------------+---------------------------------------------
 *  vorstand        | 19      | FVo, FVv, Vo               | Stimmrecht bei B-Anträgen (aktiv = 19)
 *  gf              | 18      | GF                         | aktiv 18 => Rolle gf (Admin, vertraulich)
 *  assistenz       | 17 / 11 | VA / SV                    | NICHT 18: aktiv 18 würde als gf abgeleitet
 *  fuehrungsteam   | 14-17   | RL (14/15/17)              | aktiv >= 14 darf bei V/R-Anträgen abstimmen;
 *                  |         |                            | nur RL ist in BerechtigteAdapter::shouldInclude() enthalten (PL/TM nicht)
 *  mitglied        | 0-10    | FP (3), AD (10), Vx (0)    | keine Funktion-Rolle, nur Lesen/Terminumfragen
 *
 * Flags (svmembers.is_admin / is_confidential == berechtigte.sv_admin / sv_confidential):
 * gf, assistenz und der technische Admin (AD) sind Admin; vorstand, gf, assistenz und die
 * Ressortleitung Finanzen sind vertraulich.
 */
define('DEMO_ROLE_MAPPING', [
    'vorstand'      => ['aktiv' => [19],         'funktion' => ['FVo', 'FVv', 'Vo']],
    'gf'            => ['aktiv' => [18],         'funktion' => ['GF']],
    'assistenz'     => ['aktiv' => [17, 11],     'funktion' => ['VA', 'SV']],
    'fuehrungsteam' => ['aktiv' => [14, 15, 17], 'funktion' => ['RL']],
    'mitglied'      => ['aktiv' => [0, 3, 10],   'funktion' => ['FP', 'AD', 'Vx']],
]);

/**
 * Import-Reihenfolge (Eltern vor Kindern). Fremdschlüsselprüfung ist beim Import ohnehin aus.
 * Die Namen svantraege/svbeschluesse/svmvbeschluesse/svressorts werden beim Import auf die
 * konfigurierten TABLE_*-Konstanten abgebildet, falls diese abweichen.
 */
function demo_table_order() {
    return [
        // Stammdaten und Admin-Einstellungen
        'svmembers', 'svconfig', 'svressorts', 'svopinion_answer_templates',
        // Sitzungen, Anträge, Kalender, Abwesenheiten, Umfragen, Dokumente
        'svmeetings', 'svantraege', 'svbeschluesse', 'svmvbeschluesse',
        'svevents', 'svevents_participants', 'svabsences',
        'svpolls', 'svopinion_polls', 'svdocuments', 'svprotocols',
        'svnotifications', 'svnotification_prefs', 'svcollab_texts', 'feedback',
        // Abhängige Tabellen
        'svmeeting_participants', 'svagenda_items', 'svtodos',
        'svpoll_dates', 'svpoll_participants', 'svopinion_poll_options', 'svopinion_poll_participants',
        'svexternal_participants', 'svcollab_text_paragraphs', 'svcollab_text_participants', 'svcollab_text_versions',
        'svtermine_user_hidden', 'svopinion_user_hidden',
        'svagenda_comments', 'svagenda_live_comments', 'svagenda_post_comments', 'svagenda_attachments',
        'svagenda_personal_notes', 'svprotocol_change_requests', 'svtodo_log', 'svvotings',
        'svpoll_responses', 'svopinion_responses', 'svvotes', 'svopinion_response_options',
    ];
}

/** Logische Namen der konfigurierbaren Tabellen (Standalone-Name => Konstante) */
function demo_table_constants() {
    return [
        'svantraege'      => 'TABLE_ANTRAEGE',
        'svbeschluesse'   => 'TABLE_BESCHLUESSE',
        'svmvbeschluesse' => 'TABLE_MVBESCHLUESSE',
        'svressorts'      => 'TABLE_RESSORTS',
    ];
}

/** Tatsächlicher Tabellenname laut config.php (Fallback: Standalone-Name) */
function demo_real_table($logical) {
    $map = demo_table_constants();
    if (isset($map[$logical]) && defined($map[$logical])) {
        $name = constant($map[$logical]);
        if (preg_match('/^[A-Za-z0-9_]+$/', $name)) return $name;
    }
    return $logical;
}

/** Tabellen, die zur Zielumgebung ohne Datenverlust-Risiko komplett geleert werden dürfen */
function demo_is_excluded($table) {
    return array_key_exists($table, DEMO_EXCLUDED_TABLES) || $table === DEMO_DERIVED_TABLE;
}

/** Wurde die Mitgliederquelle auf die echte VTool-Tabelle `berechtigte` umgestellt? */
function demo_member_source_is_berechtigte() {
    return defined('MEMBER_SOURCE') && MEMBER_SOURCE === 'berechtigte';
}

/** CREATE TABLE für die abgeleitete Kompatibilitätstabelle (nur Standalone-Demo) */
function demo_berechtigte_create_sql() {
    return "CREATE TABLE IF NOT EXISTS berechtigte (
        ID INT NOT NULL PRIMARY KEY,
        MNr VARCHAR(12) DEFAULT NULL,
        KurzN VARCHAR(12) DEFAULT NULL,
        Forumname VARCHAR(50) DEFAULT NULL,
        Vorname VARCHAR(50) DEFAULT NULL,
        Name VARCHAR(50) DEFAULT NULL,
        eMail VARCHAR(255) DEFAULT NULL,
        aktiv INT NOT NULL DEFAULT 0,
        Funktion VARCHAR(10) DEFAULT NULL,
        funktionsbeschreibung VARCHAR(255) DEFAULT NULL,
        aktivbis VARCHAR(10) DEFAULT NULL,
        vertretung INT DEFAULT NULL,
        sv_admin TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Admin-Zugang im Sitzungstool',
        sv_confidential TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Zugriff auf vertrauliche TOPs/Anträge',
        angelegt DATETIME DEFAULT NULL,
        INDEX idx_mnr (MNr),
        INDEX idx_aktiv (aktiv)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Demo: aus svmembers abgeleitet (VTool-Kompatibilität für tab_proposals, Terminplanung, Meinungsbild)'";
}

/**
 * Leitet die berechtigte-Zeilen aus svmembers-Zeilen ab (eine Quelle der Wahrheit: svmembers).
 * KurzN folgt der Konvention aus voting_helper.php (Vorname + Initial des Nachnamens).
 */
function demo_derive_berechtigte(array $member_rows) {
    $out = [];
    foreach ($member_rows as $m) {
        $out[] = [
            'ID'              => (int)$m['member_id'],
            'MNr'             => $m['membership_number'] ?? null,
            'KurzN'           => mb_substr(($m['first_name'] ?? '') . mb_substr($m['last_name'] ?? '', 0, 1), 0, 12),
            'Forumname'       => null,
            'Vorname'         => $m['first_name'] ?? '',
            'Name'            => $m['last_name'] ?? '',
            'eMail'           => $m['email'] ?? '',
            'aktiv'           => (int)($m['aktiv'] ?? 0),
            'Funktion'        => $m['funktion'] ?? null,
            'funktionsbeschreibung' => null,
            'aktivbis'        => null,
            'vertretung'      => null,
            'sv_admin'        => (int)($m['is_admin'] ?? 0),
            'sv_confidential' => (int)($m['is_confidential'] ?? 0),
            'angelegt'        => $m['created_at'] ?? null,
        ];
    }
    return $out;
}

/** Ist die Mailadresse eine erlaubte Fake-Adresse (example.com/.org/.net)? */
function demo_is_fake_email($email) {
    $at = strrpos($email, '@');
    if ($at === false) return false;
    $domain = strtolower(substr($email, $at + 1));
    foreach (DEMO_ALLOWED_MAIL_DOMAINS as $ok) {
        if ($domain === $ok || substr($domain, -strlen($ok) - 1) === '.' . $ok) return true;
    }
    return false;
}

/**
 * Sucht in allen Tabellen nach Mailadressen außerhalb von example.*.
 * @param array $tables  ['tabelle' => [zeilen...]]
 * @return array Liste aus ['table','row','column','value'] (maximal $limit Treffer)
 */
function demo_find_foreign_emails(array $tables, $limit = 200) {
    $found = [];
    foreach ($tables as $table => $rows) {
        if (!is_array($rows)) continue;
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) continue;
            foreach ($row as $col => $val) {
                if (!is_string($val) || strpos($val, '@') === false) continue;
                if (!preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $val, $mm)) continue;
                foreach ($mm[0] as $addr) {
                    if (!demo_is_fake_email($addr)) {
                        $found[] = ['table' => $table, 'row' => $idx, 'column' => $col, 'value' => $addr];
                        if (count($found) >= $limit) return $found;
                    }
                }
            }
        }
    }
    return $found;
}

/** Bereinigt Token-/Adress-Spalten einer Zeile für den Export (siehe DEMO_SCRUB_COLUMNS) */
function demo_scrub_row($table, array $row, $row_index) {
    $rules = DEMO_SCRUB_COLUMNS[$table] ?? null;
    if (!$rules) return $row;
    foreach ($rules as $col => $mode) {
        if (!array_key_exists($col, $row) || $row[$col] === null || $row[$col] === '') continue;
        $row[$col] = ($mode === 'token') ? 'demo-' . $table . '-' . ($row_index + 1) : null;
    }
    return $row;
}

/**
 * Admin-Passwort-Schutz für Browser-Aufrufe (wie in demo_export.php/demo_import.php).
 * Gibt true zurück, wenn authentifiziert; sonst wurde das Formular ausgegeben (Aufrufer: exit).
 */
function demo_browser_auth() {
    if (!defined('SYSTEM_ADMIN_PASSWORD')) {
        http_response_code(500);
        echo 'SYSTEM_ADMIN_PASSWORD ist nicht definiert (config.php).';
        return false;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $error = null;
    if (isset($_POST['admin_password'])) {
        if (hash_equals((string)SYSTEM_ADMIN_PASSWORD, (string)$_POST['admin_password'])) {
            $_SESSION['system_admin_authenticated'] = true;
        } else {
            $error = 'Falsches Passwort!';
        }
    }
    if (!empty($_SESSION['system_admin_authenticated'])) return true;
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>System-Admin Authentifizierung</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 400px; margin: 100px auto; padding: 20px; }
            .login-box { background: #f9f9f9; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
            button { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .error { color: #d32f2f; margin-bottom: 10px; padding: 10px; background: #ffebee; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔒 System-Admin Zugang</h2>
            <p>Für den Zugriff auf die Demo-Werkzeuge wird das System-Admin-Passwort benötigt.</p>
            <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="POST">
                <input type="password" name="admin_password" placeholder="System-Admin-Passwort" required autofocus>
                <button type="submit">Anmelden</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    return false;
}
