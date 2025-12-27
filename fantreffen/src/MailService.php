<?php
/**
 * MailService - E-Mail-Versand und Queue-Verwaltung für Fantreffen
 */

class MailService {
    private Database $db;
    private array $config;

    // Provider-Limits pro Stunde
    private array $providerLimits = [
        'gmx' => 10,
        'web' => 10,
        't-online' => 10,
        'yahoo' => 10,
        'default' => 20
    ];

    public function __construct(Database $db) {
        $this->db = $db;
        $this->config = [
            'from_email' => defined('MAIL_FROM') ? MAIL_FROM : 'noreply@aidafantreffen.de',
            'from_name' => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'AIDA Fantreffen',
            'base_url' => defined('BASE_URL') ? BASE_URL : '',
            'bcc_admin' => defined('MAIL_BCC_ADMIN') ? MAIL_BCC_ADMIN : ''
        ];
    }

    /**
     * Ermittelt den Provider einer E-Mail-Adresse
     */
    public function getProvider(string $email): string {
        $domain = strtolower(substr(strrchr($email, '@'), 1));

        if (strpos($domain, 'gmx') !== false) return 'gmx';
        if (strpos($domain, 'web.de') !== false) return 'web';
        if (strpos($domain, 't-online') !== false) return 't-online';
        if (strpos($domain, 'yahoo') !== false) return 'yahoo';

        return 'default';
    }

    /**
     * Lädt eine Mail-Vorlage aus der Datenbank
     */
    public function getVorlage(string $code): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM fan_mail_vorlagen WHERE code = ? AND aktiv = 1",
            [$code]
        );
    }

    /**
     * Ersetzt Platzhalter in einem Text
     */
    public function replacePlatzhalter(string $text, array $data): string {
        foreach ($data as $key => $value) {
            $text = str_replace('{' . $key . '}', $value ?? '', $text);
        }
        return $text;
    }

    /**
     * Fügt eine Mail zur Queue hinzu
     */
    public function queueMail(
        string $empfaenger,
        string $betreff,
        string $inhaltHtml,
        ?string $inhaltText = null,
        ?int $reiseId = null,
        ?string $vorlageCode = null,
        int $prioritaet = 5
    ): int {
        if (empty($inhaltText)) {
            $inhaltText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $inhaltHtml));
        }

        $this->db->insert('fan_mail_queue', [
            'reise_id' => $reiseId,
            'vorlage_code' => $vorlageCode,
            'empfaenger' => $empfaenger,
            'betreff' => $betreff,
            'inhalt_html' => $inhaltHtml,
            'inhalt_text' => $inhaltText,
            'prioritaet' => $prioritaet
        ]);

        return (int)$this->db->getPdo()->lastInsertId();
    }

    /**
     * Sendet eine Mail basierend auf einer Vorlage
     */
    public function sendFromVorlage(
        string $vorlageCode,
        string $empfaenger,
        array $platzhalter,
        ?int $reiseId = null,
        int $prioritaet = 5
    ): bool {
        $vorlage = $this->getVorlage($vorlageCode);
        if (!$vorlage) {
            error_log("Mail-Vorlage nicht gefunden: $vorlageCode");
            return false;
        }

        // Login-Link hinzufügen falls nicht vorhanden
        if (!isset($platzhalter['login_link'])) {
            $platzhalter['login_link'] = $this->config['base_url'] . '/login.php';
        }

        $betreff = $this->replacePlatzhalter($vorlage['betreff'], $platzhalter);
        $inhaltHtml = $this->replacePlatzhalter($vorlage['inhalt_html'], $platzhalter);
        $inhaltText = $this->replacePlatzhalter($vorlage['inhalt_text'] ?? '', $platzhalter);

        $this->queueMail($empfaenger, $betreff, $inhaltHtml, $inhaltText, $reiseId, $vorlageCode, $prioritaet);
        return true;
    }

    /**
     * Sendet Admin-Ernennungs-Mail
     */
    public function sendAdminErnennung(int $userId, int $reiseId, ?string $passwort = null): bool {
        $user = $this->db->fetchOne("SELECT * FROM fan_users WHERE user_id = ?", [$userId]);
        $reise = $this->db->fetchOne("SELECT * FROM fan_reisen WHERE reise_id = ?", [$reiseId]);

        if (!$user || !$reise) return false;

        // Teilnehmer-Daten laden (für Vorname)
        $teilnehmer = $this->db->fetchOne(
            "SELECT * FROM fan_teilnehmer WHERE user_id = ? ORDER BY position LIMIT 1",
            [$userId]
        );

        $platzhalter = [
            'vorname' => $teilnehmer['vorname'] ?? 'Hallo',
            'name' => $teilnehmer['name'] ?? '',
            'email' => $user['email'],
            'schiff' => $reise['schiff'],
            'anfang' => date('d.m.Y', strtotime($reise['anfang'])),
            'ende' => date('d.m.Y', strtotime($reise['ende'])),
            'passwort' => $passwort ?? ''
        ];

        // Bei neuem User Passwort-Info hinzufügen
        if ($passwort) {
            $platzhalter['passwort_info'] = "Deine Zugangsdaten:\nE-Mail: {$user['email']}\nPasswort: $passwort\n\nWICHTIG: Bitte ändere dein Passwort nach dem ersten Login!";
        } else {
            $platzhalter['passwort_info'] = '';
        }

        return $this->sendFromVorlage('admin_ernennung', $user['email'], $platzhalter, $reiseId, 8);
    }

    /**
     * Sendet Anmeldebestätigung
     */
    public function sendAnmeldebestaetigung(int $userId, int $reiseId, ?string $kabine = null): bool {
        $user = $this->db->fetchOne("SELECT * FROM fan_users WHERE user_id = ?", [$userId]);
        $reise = $this->db->fetchOne("SELECT * FROM fan_reisen WHERE reise_id = ?", [$reiseId]);
        $teilnehmer = $this->db->fetchOne(
            "SELECT * FROM fan_teilnehmer WHERE user_id = ? ORDER BY position LIMIT 1",
            [$userId]
        );

        if (!$user || !$reise) return false;

        return $this->sendFromVorlage('anmeldung_bestaetigung', $user['email'], [
            'vorname' => $teilnehmer['vorname'] ?? 'Hallo',
            'name' => $teilnehmer['name'] ?? '',
            'email' => $user['email'],
            'schiff' => $reise['schiff'],
            'anfang' => date('d.m.Y', strtotime($reise['anfang'])),
            'ende' => date('d.m.Y', strtotime($reise['ende'])),
            'kabine' => $kabine ?: 'noch nicht eingetragen'
        ], $reiseId, 7);
    }

    /**
     * Sendet "Treffen bestätigt" an alle Teilnehmer einer Reise
     */
    public function sendTreffenBestaetigt(int $reiseId): int {
        $reise = $this->db->fetchOne("SELECT * FROM fan_reisen WHERE reise_id = ?", [$reiseId]);
        if (!$reise) return 0;

        $anmeldungen = $this->db->fetchAll(
            "SELECT a.*, u.email, t.vorname, t.name, a.kabine
             FROM fan_anmeldungen a
             JOIN fan_users u ON a.user_id = u.user_id
             LEFT JOIN fan_teilnehmer t ON t.teilnehmer_id = a.teilnehmer1_id
             WHERE a.reise_id = ?",
            [$reiseId]
        );

        $count = 0;
        foreach ($anmeldungen as $a) {
            $kabineHinweis = '';
            if (empty($a['kabine'])) {
                $kabineHinweis = '<div class="warning"><strong>Wichtig:</strong> Deine Kabinennummer fehlt noch! Bitte trage sie umgehend ein: {login_link}</div>';
            }

            $this->sendFromVorlage('treffen_bestaetigt', $a['email'], [
                'vorname' => $a['vorname'] ?? 'Hallo',
                'name' => $a['name'] ?? '',
                'schiff' => $reise['schiff'],
                'anfang' => date('d.m.Y', strtotime($reise['anfang'])),
                'ende' => date('d.m.Y', strtotime($reise['ende'])),
                'treffen_ort' => $reise['treffen_ort'] ?? 'wird noch bekannt gegeben',
                'treffen_zeit' => $reise['treffen_zeit'] ? date('d.m.Y H:i', strtotime($reise['treffen_zeit'])) . ' Uhr' : 'wird noch bekannt gegeben',
                'kabine' => $a['kabine'] ?? '-',
                'kabine_hinweis' => $kabineHinweis
            ], $reiseId, 6);
            $count++;
        }

        return $count;
    }

    /**
     * Sendet "Kabine fehlt" an alle ohne Kabinennummer
     */
    public function sendKabineFehlt(int $reiseId): int {
        $reise = $this->db->fetchOne("SELECT * FROM fan_reisen WHERE reise_id = ?", [$reiseId]);
        if (!$reise) return 0;

        $anmeldungen = $this->db->fetchAll(
            "SELECT a.*, u.email, t.vorname, t.name
             FROM fan_anmeldungen a
             JOIN fan_users u ON a.user_id = u.user_id
             LEFT JOIN fan_teilnehmer t ON t.teilnehmer_id = a.teilnehmer1_id
             WHERE a.reise_id = ? AND (a.kabine IS NULL OR a.kabine = '')",
            [$reiseId]
        );

        $count = 0;
        foreach ($anmeldungen as $a) {
            $this->sendFromVorlage('kabine_fehlt', $a['email'], [
                'vorname' => $a['vorname'] ?? 'Hallo',
                'name' => $a['name'] ?? '',
                'schiff' => $reise['schiff'],
                'anfang' => date('d.m.Y', strtotime($reise['anfang'])),
                'ende' => date('d.m.Y', strtotime($reise['ende']))
            ], $reiseId, 6);
            $count++;
        }

        return $count;
    }

    /**
     * Verarbeitet die Mail-Queue (für Cronjob)
     * Berücksichtigt Provider-Limits
     */
    public function processQueue(array $limits = []): array {
        $limits = array_merge($this->providerLimits, $limits);

        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        // Zähle bereits gesendete Mails pro Provider in der letzten Stunde
        $sentCounts = [];
        $recentMails = $this->db->fetchAll(
            "SELECT empfaenger FROM fan_mail_queue
             WHERE gesendet IS NOT NULL AND gesendet > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        foreach ($recentMails as $m) {
            $provider = $this->getProvider($m['empfaenger']);
            $sentCounts[$provider] = ($sentCounts[$provider] ?? 0) + 1;
        }

        // Hole ausstehende Mails
        $pendingMails = $this->db->fetchAll(
            "SELECT * FROM fan_mail_queue
             WHERE gesendet IS NULL AND versuche < 3
             ORDER BY prioritaet DESC, erstellt ASC
             LIMIT 100"
        );

        foreach ($pendingMails as $mail) {
            $provider = $this->getProvider($mail['empfaenger']);
            $limit = $limits[$provider] ?? $limits['default'];
            $currentCount = $sentCounts[$provider] ?? 0;

            // Limit erreicht?
            if ($currentCount >= $limit) {
                $stats['skipped']++;
                continue;
            }

            $stats['processed']++;

            // Versuch zu senden
            $success = $this->sendMailDirect(
                $mail['empfaenger'],
                $mail['betreff'],
                $mail['inhalt_html'],
                $mail['inhalt_text']
            );

            if ($success) {
                $this->db->update('fan_mail_queue', [
                    'gesendet' => date('Y-m-d H:i:s'),
                    'versuche' => $mail['versuche'] + 1
                ], 'mail_id = ?', [$mail['mail_id']]);

                $sentCounts[$provider] = $currentCount + 1;
                $stats['sent']++;
            } else {
                $this->db->update('fan_mail_queue', [
                    'versuche' => $mail['versuche'] + 1,
                    'letzter_fehler' => 'Versand fehlgeschlagen'
                ], 'mail_id = ?', [$mail['mail_id']]);

                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Sendet eine Mail direkt (ohne Queue)
     */
    public function sendMailDirect(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): bool {
        $fromEmail = $this->config['from_email'];
        $fromName = $this->config['from_name'];

        if (empty($textBody)) {
            $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        }

        // Boundary für Multipart
        $boundary = md5(uniqid(time()));

        // Headers
        $headers = [];
        $headers[] = "From: " . mb_encode_mimeheader($fromName, 'UTF-8') . " <$fromEmail>";
        $headers[] = "Reply-To: $fromEmail";
        if (!empty($this->config['bcc_admin'])) {
            $headers[] = "Bcc: " . $this->config['bcc_admin'];
        }
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
        $headers[] = "X-Mailer: PHP/" . phpversion();

        // Body
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";

        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";

        $body .= "--$boundary--";

        return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers));
    }

    /**
     * Löscht alle Mails einer Reise aus der Queue
     */
    public function deleteMailsForReise(int $reiseId): int {
        return $this->db->delete('fan_mail_queue', 'reise_id = ?', [$reiseId]);
    }

    /**
     * Holt alle Vorlagen für Admin-Bearbeitung
     */
    public function getAllVorlagen(): array {
        return $this->db->fetchAll("SELECT * FROM fan_mail_vorlagen ORDER BY name");
    }

    /**
     * Aktualisiert eine Vorlage
     */
    public function updateVorlage(int $vorlageId, array $data): bool {
        $allowed = ['betreff', 'inhalt_html', 'inhalt_text', 'name', 'beschreibung', 'aktiv'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) return false;

        return $this->db->update('fan_mail_vorlagen', $updateData, 'vorlage_id = ?', [$vorlageId]) > 0;
    }
}
