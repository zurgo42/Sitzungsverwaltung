<?php
/**
 * config_proposals.php - Konfiguration für Antrags- und Beschlusssystem
 *
 * Diese Datei definiert alle Regeln und Schwellwerte für das Proposal-System.
 * Kopieren Sie diese Datei zu config_proposals.php und passen Sie die Werte an.
 */

// ============================================
// ORGANISATIONS-EINSTELLUNGEN
// ============================================

/**
 * Art der Organisation
 * - 'association' = Verein (e.V.)
 * - 'foundation' = Stiftung
 */
define('ORGANIZATION_TYPE', 'association');

/**
 * Name der Organisation (für E-Mails und Anzeige)
 */
define('ORGANIZATION_NAME', 'Mensa in Deutschland e.V.');

/**
 * Kurzname der Organisation (für Antragsnummern)
 * Wird als Präfix verwendet (z.B. MIND-A-2024-001)
 */
define('ORGANIZATION_SHORT', 'MIND');


// ============================================
// DECISION RULES - Entscheidungsregeln
// ============================================

/**
 * Automatische Bestimmung des decision_type basierend auf Betrag
 *
 * Regeln für MinD (Standard):
 * - 0 bis 600 EUR: single (Verfügung durch Einzelperson)
 * - 600 bis 3000 EUR: department (Ressortbeschluss mit Finanzfreigabe)
 * - ab 3000 EUR: board (Vorstandsbeschluss mit Abstimmung)
 *
 * Format: Array von Regeln, sortiert nach Betrag aufsteigend
 * Jede Regel: [max_amount, decision_type, description]
 */
$PROPOSAL_DECISION_RULES = [
    [
        'max_amount' => 600.00,
        'decision_type' => 'single',
        'description' => 'Verfügung (Freigabe durch Einzelperson)',
        'approvers_required' => 1,  // Anzahl benötigter Freigaben
        'requires_finance' => false  // Finanzfreigabe notwendig?
    ],
    [
        'max_amount' => 3000.00,
        'decision_type' => 'department',
        'description' => 'Ressortbeschluss (Ressortleitung + Finanzfreigabe)',
        'approvers_required' => 1,  // Ressortleitung
        'requires_finance' => true   // + Finanzfreigabe
    ],
    [
        'max_amount' => PHP_FLOAT_MAX,  // Unbegrenzt
        'decision_type' => 'board',
        'description' => 'Vorstandsbeschluss (Abstimmung erforderlich)',
        'approvers_required' => 0,  // Keine Einzelfreigaben
        'requires_finance' => false,
        'requires_vote' => true  // Vorstandsabstimmung erforderlich
    ]
];


// ============================================
// WARTEZEITEN - Waiting Periods
// ============================================

/**
 * Standard-Wartezeit für Anträge (in Tagen)
 * Anträge müssen diese Zeit im Status 'editing' bleiben,
 * bevor sie zur Abstimmung (Status 'voting') übergehen können.
 */
define('PROPOSAL_WAITING_PERIOD_DAYS', 7);

/**
 * Wartezeitverkürzung (Expedite) erlauben?
 * Wenn true, können Antragsteller eine Begründung für verkürzte Wartezeit angeben
 */
define('PROPOSAL_ALLOW_EXPEDITE', true);

/**
 * Anzahl der Vorstandsmitglieder, die Wartezeitverkürzung genehmigen müssen
 * Standard: 2 (zwei Vorstandsmitglieder müssen zustimmen)
 */
define('PROPOSAL_EXPEDITE_APPROVERS_REQUIRED', 2);


// ============================================
// ABSTIMMUNGS-EINSTELLUNGEN - Voting Settings
// ============================================

/**
 * Abstimmungsdauer für Vorstandsbeschlüsse (in Tagen)
 * Nach Ablauf dieser Frist wird die Abstimmung automatisch finalisiert
 */
define('PROPOSAL_VOTING_PERIOD_DAYS', 14);

/**
 * Quorum für Vorstandsbeschlüsse
 * Mindestanzahl oder -prozentsatz abgegebener Stimmen
 */
define('PROPOSAL_VOTING_QUORUM_TYPE', 'simple_majority');  // 'simple_majority', 'absolute_majority', 'percentage', 'count'
define('PROPOSAL_VOTING_QUORUM_VALUE', 50);  // Bei 'percentage': 50%, bei 'count': absolute Zahl

/**
 * Abstimmungsoptionen und ihre Bedeutung
 */
$PROPOSAL_VOTE_OPTIONS = [
    'yes' => [
        'label' => 'Ja / Zustimmung',
        'description' => 'Ich stimme dem Antrag zu',
        'weight' => 1  // Positiv
    ],
    'no' => [
        'label' => 'Nein / Ablehnung',
        'description' => 'Ich lehne den Antrag ab',
        'weight' => -1  // Negativ
    ],
    'abstain' => [
        'label' => 'Enthaltung',
        'description' => 'Ich enthalte mich der Stimme',
        'weight' => 0  // Neutral, zählt aber zum Quorum
    ],
    'refer_back' => [
        'label' => 'Rückverweisung',
        'description' => 'Der Antrag sollte überarbeitet werden',
        'weight' => 0  // Zählt als Ablehnung
    ],
    'request_time' => [
        'label' => 'Mehr Zeit benötigt',
        'description' => 'Ich benötige mehr Zeit zur Prüfung',
        'weight' => 0  // Verlängert die Abstimmungsfrist
    ]
];


// ============================================
// SICHTBARKEITS-EINSTELLUNGEN - Visibility
// ============================================

/**
 * Standard-Sichtbarkeit für neue Anträge
 * - 'public': Alle Mitglieder können sehen
 * - 'leadership': Nur Führungskreis (Vorstand, GF, Assistenz, RL)
 * - 'board': Nur Vorstand, GF, Assistenz
 * - 'draft': Nur Antragsteller und Admins
 */
define('PROPOSAL_DEFAULT_VISIBILITY', 'public');

/**
 * Darf Antragsteller die Sichtbarkeit ändern?
 */
define('PROPOSAL_ALLOW_VISIBILITY_CHANGE', true);


// ============================================
// FINANZ-EINSTELLUNGEN - Finance Settings
// ============================================

/**
 * Finanzverantwortliche (User-IDs oder Rollen)
 * Diese Personen können Finanzfreigaben erteilen
 *
 * Format: Array von User-IDs oder 'role:rolename'
 */
$PROPOSAL_FINANCE_APPROVERS = [
    'role:gf',        // Alle mit Rolle 'gf' (Geschäftsführung)
    'role:assistenz', // Alle mit Rolle 'assistenz'
    // 123,           // Oder spezifische User-ID
];

/**
 * Vereinfachte Freigabe erlauben?
 * - 'none': Keine vereinfachte Freigabe
 * - 'invoice_match': Bei Rechnungsübereinstimmung
 * - 'after_review': Nach erfolgter Prüfung
 */
define('PROPOSAL_ALLOW_SIMPLIFIED_APPROVAL', true);


// ============================================
// E-MAIL BENACHRICHTIGUNGEN
// ============================================

/**
 * E-Mail-Benachrichtigungen aktivieren?
 */
define('PROPOSAL_EMAIL_NOTIFICATIONS', true);

/**
 * Absender-E-Mail für Benachrichtigungen
 */
define('PROPOSAL_EMAIL_FROM', 'noreply@mensa.de');
define('PROPOSAL_EMAIL_FROM_NAME', 'Sitzungsverwaltung MinD');

/**
 * E-Mail-Benachrichtigungen bei folgenden Ereignissen senden:
 */
$PROPOSAL_EMAIL_EVENTS = [
    'proposal_submitted' => true,      // Neuer Antrag eingereicht
    'proposal_finalized' => true,      // Antrag verbindlich gestellt
    'proposal_voting_started' => true, // Abstimmung gestartet
    'proposal_voting_ending' => true,  // Abstimmung endet bald (24h vorher)
    'proposal_decided' => true,        // Entscheidung getroffen
    'proposal_withdrawn' => true,      // Antrag zurückgezogen
    'expedite_requested' => true,      // Wartezeitverkürzung beantragt
    'expedite_approved' => true,       // Wartezeitverkürzung genehmigt
    'comment_added' => true,           // Kommentar hinzugefügt
    'finance_approval_needed' => true, // Finanzfreigabe erforderlich
    'vote_reminder' => true            // Erinnerung an ausstehende Abstimmung
];

/**
 * Wer erhält Benachrichtigungen?
 */
$PROPOSAL_EMAIL_RECIPIENTS = [
    // Bei proposal_submitted: Wer wird benachrichtigt?
    'proposal_submitted' => [
        'submitter' => true,           // Antragsteller (Bestätigung)
        'board' => true,               // Vorstandsmitglieder
        'department_leads' => true,    // Ressortleitungen
        'finance' => false             // Finanzverantwortliche
    ],

    // Bei proposal_voting_started: Wer wird benachrichtigt?
    'proposal_voting_started' => [
        'submitter' => true,
        'eligible_voters' => true,     // Alle Stimmberechtigten
        'board' => false,
        'all_members' => false         // Alle Mitglieder (bei public visibility)
    ],

    // Bei vote_reminder: Wer wird erinnert?
    'vote_reminder' => [
        'voters_pending' => true       // Nur die, die noch nicht abgestimmt haben
    ],

    // Default für andere Events
    'default' => [
        'submitter' => true,
        'board' => true
    ]
];

/**
 * Erinnerungs-E-Mails für ausstehende Abstimmungen
 * Array von Tagen vor Ablauf der Frist
 */
$PROPOSAL_EMAIL_REMINDERS = [
    7,   // 7 Tage vor Ablauf
    3,   // 3 Tage vor Ablauf
    1    // 1 Tag vor Ablauf
];


// ============================================
// CRONJOB-EINSTELLUNGEN
// ============================================

/**
 * Cronjob-Frequenzen (in Minuten)
 * Diese Werte bestimmen, wie oft die Cronjobs laufen
 */
define('PROPOSAL_CRON_FREQUENCY_HOURLY', 60);    // Stündlich: E-Mail-Benachrichtigungen
define('PROPOSAL_CRON_FREQUENCY_DAILY', 1440);   // Täglich: Abstimmungen finalisieren
define('PROPOSAL_CRON_FREQUENCY_MONTHLY', 43200); // Monatlich: Archivierung

/**
 * Uhrzeit für täglichen Cronjob (Format: HH:MM)
 * Zeitpunkt, zu dem Abstimmungen automatisch finalisiert werden
 */
define('PROPOSAL_CRON_DAILY_TIME', '03:00');


// ============================================
// ANTRAGSNUMMERN - Proposal Numbers
// ============================================

/**
 * Format für Antragsnummern
 *
 * Verfügbare Platzhalter:
 * {PREFIX} = Status-Präfix (A=editing, B=voting, V=approved, X=rejected, Z=withdrawn)
 * {YEAR} = Jahr (4-stellig)
 * {MONTH} = Monat (2-stellig)
 * {COUNTER} = Fortlaufende Nummer
 * {ORG} = Organisations-Kurzname
 *
 * Beispiele:
 * - '{PREFIX}-{YEAR}-{COUNTER:03d}' → A-2024-001
 * - '{ORG}-{PREFIX}-{YEAR}-{COUNTER:03d}' → MIND-A-2024-001
 * - '{PREFIX}{YEAR:02d}{COUNTER:04d}' → A24-0001 (kompakt)
 */
define('PROPOSAL_NUMBER_FORMAT', '{PREFIX}-{YEAR}-{COUNTER:03d}');

/**
 * Status-Präfixe für Antragsnummern
 * Diese Präfixe ändern sich mit dem Antrags-Status
 */
$PROPOSAL_NUMBER_PREFIXES = [
    'draft' => 'D',      // Entwurf (noch nicht eingereicht)
    'editing' => 'A',    // In Bearbeitung / Wartezeit
    'voting' => 'B',     // In Abstimmung
    'approved' => 'V',   // Genehmigt/Beschlossen
    'rejected' => 'X',   // Abgelehnt
    'withdrawn' => 'Z'   // Zurückgezogen
];


// ============================================
// DEPARTMENTS / RESSORTS
// ============================================

/**
 * Vordefinierte Departments/Ressorts
 * Werden beim ersten Systemstart in die Datenbank eingefügt
 *
 * WICHTIG: Dies ist nur die initiale Konfiguration.
 * Danach werden Departments aus der Datenbank geladen (proposal_departments Tabelle)
 */
$PROPOSAL_DEPARTMENTS_INITIAL = [
    [
        'department_id' => 'finance',
        'name' => 'Finanzen',
        'description' => 'Finanzverantwortung und Budget',
        'leader_id' => null,  // Wird später zugewiesen
        'is_active' => 1
    ],
    [
        'department_id' => 'events',
        'name' => 'Veranstaltungen',
        'description' => 'Organisation und Durchführung von Events',
        'leader_id' => null,
        'is_active' => 1
    ],
    [
        'department_id' => 'membership',
        'name' => 'Mitgliedschaft',
        'description' => 'Mitgliederverwaltung und -betreuung',
        'leader_id' => null,
        'is_active' => 1
    ],
    [
        'department_id' => 'communication',
        'name' => 'Kommunikation',
        'description' => 'Öffentlichkeitsarbeit und interne Kommunikation',
        'leader_id' => null,
        'is_active' => 1
    ],
    [
        'department_id' => 'it',
        'name' => 'IT',
        'description' => 'IT-Infrastruktur und digitale Dienste',
        'leader_id' => null,
        'is_active' => 1
    ]
];


// ============================================
// DATEI-UPLOADS
// ============================================

/**
 * Dateianhänge erlauben?
 */
define('PROPOSAL_ALLOW_ATTACHMENTS', true);

/**
 * Maximale Anzahl von Dateianhängen pro Antrag
 */
define('PROPOSAL_MAX_ATTACHMENTS', 4);

/**
 * Maximale Dateigröße pro Anhang (in Bytes)
 * Standard: 10 MB
 */
define('PROPOSAL_MAX_FILE_SIZE', 10 * 1024 * 1024);

/**
 * Erlaubte Dateitypen (MIME-Types)
 */
$PROPOSAL_ALLOWED_FILE_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/png',
    'image/gif',
    'text/plain'
];

/**
 * Upload-Verzeichnis (relativ zum Projekt-Root)
 */
define('PROPOSAL_UPLOAD_DIR', 'uploads/proposals');


// ============================================
// SONSTIGE EINSTELLUNGEN
// ============================================

/**
 * Kommentare erlauben?
 */
define('PROPOSAL_ALLOW_COMMENTS', true);

/**
 * Änderungshistorie aufzeichnen?
 * Wenn true, werden alle Änderungen an Anträgen in proposal_changes protokolliert
 */
define('PROPOSAL_TRACK_CHANGES', true);

/**
 * Maximale Länge für Antragstitel
 */
define('PROPOSAL_MAX_TITLE_LENGTH', 500);

/**
 * Maximale Länge für Antragsbeschreibung
 */
define('PROPOSAL_MAX_DESCRIPTION_LENGTH', 10000);

/**
 * Anträge mit Status 'approved' archivieren?
 * Wenn true, werden alte beschlossene Anträge nach X Monaten archiviert
 */
define('PROPOSAL_AUTO_ARCHIVE', false);
define('PROPOSAL_ARCHIVE_AFTER_MONTHS', 24);


// ============================================
// HILFSFUNKTIONEN
// ============================================

/**
 * Bestimmt den decision_type basierend auf dem Betrag
 *
 * @param float $amount Finanzieller Betrag
 * @return string decision_type ('single', 'department', 'board')
 */
function get_decision_type_by_amount($amount) {
    global $PROPOSAL_DECISION_RULES;

    foreach ($PROPOSAL_DECISION_RULES as $rule) {
        if ($amount <= $rule['max_amount']) {
            return $rule['decision_type'];
        }
    }

    // Fallback: board (höchste Stufe)
    return 'board';
}

/**
 * Gibt die Regel für einen bestimmten decision_type zurück
 *
 * @param string $decision_type
 * @return array|null Regel oder null
 */
function get_decision_rule($decision_type) {
    global $PROPOSAL_DECISION_RULES;

    foreach ($PROPOSAL_DECISION_RULES as $rule) {
        if ($rule['decision_type'] === $decision_type) {
            return $rule;
        }
    }

    return null;
}

/**
 * Prüft ob ein User Finanzverantwortlicher ist
 *
 * @param array $user User-Array vom MemberAdapter
 * @return bool
 */
function is_finance_approver($user) {
    global $PROPOSAL_FINANCE_APPROVERS;

    if (!$user || !isset($user['member_id'])) {
        return false;
    }

    foreach ($PROPOSAL_FINANCE_APPROVERS as $approver) {
        // Rolle prüfen (z.B. 'role:gf')
        if (is_string($approver) && str_starts_with($approver, 'role:')) {
            $required_role = substr($approver, 5);
            if (strtolower($user['role'] ?? '') === strtolower($required_role)) {
                return true;
            }
        }
        // User-ID prüfen
        elseif (is_numeric($approver) && $user['member_id'] == $approver) {
            return true;
        }
    }

    return false;
}
?>
