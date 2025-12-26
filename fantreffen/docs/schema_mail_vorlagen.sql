-- =============================================================================
-- Fantreffen - Mail-Vorlagen Schema
-- =============================================================================
-- Ergänzung zu schema.sql - Mail-Vorlagen und Queue-Erweiterung
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Tabelle: fan_mail_vorlagen
-- Editierbare Mail-Vorlagen für verschiedene Anlässe
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fan_mail_vorlagen` (
    `vorlage_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `beschreibung` TEXT DEFAULT NULL,
    `betreff` VARCHAR(255) NOT NULL,
    `inhalt_html` TEXT NOT NULL,
    `inhalt_text` TEXT DEFAULT NULL,
    `platzhalter` JSON DEFAULT NULL,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `erstellt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`vorlage_id`),
    UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Erweiterung: fan_mail_queue um reise_id
-- -----------------------------------------------------------------------------
ALTER TABLE `fan_mail_queue`
    ADD COLUMN `reise_id` INT UNSIGNED DEFAULT NULL AFTER `mail_id`,
    ADD COLUMN `vorlage_code` VARCHAR(50) DEFAULT NULL AFTER `reise_id`,
    ADD KEY `idx_reise` (`reise_id`);

-- -----------------------------------------------------------------------------
-- Initiale Mail-Vorlagen einfügen
-- Platzhalter: {vorname}, {name}, {email}, {schiff}, {anfang}, {ende},
--              {treffen_ort}, {treffen_zeit}, {kabine}, {login_link}
-- -----------------------------------------------------------------------------

-- 1. Admin-Ernennung
INSERT INTO `fan_mail_vorlagen` (`code`, `name`, `beschreibung`, `betreff`, `inhalt_html`, `inhalt_text`, `platzhalter`) VALUES
('admin_ernennung', 'Admin-Ernennung', 'Wird automatisch gesendet, wenn jemand zum Reise-Admin ernannt wird',
'Du bist jetzt Admin für das Fantreffen auf der {schiff}',
'<html>
<head><style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background: #0a1f6e; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
.content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
.footer { background: #f5f5f5; padding: 15px; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }
.btn { display: inline-block; background: #0a1f6e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
</style></head>
<body>
<div class="container">
<div class="header"><h2>Du bist jetzt Admin!</h2></div>
<div class="content">
<p>Hallo {vorname},</p>
<p>du wurdest als <strong>Admin</strong> für das Fantreffen auf der <strong>{schiff}</strong> eingetragen.</p>
<p><strong>Reisezeitraum:</strong> {anfang} - {ende}</p>
<p>Als Admin kannst du:</p>
<ul>
<li>Die Teilnehmerliste einsehen und bearbeiten</li>
<li>Infomails an alle Teilnehmer versenden</li>
<li>Das Treffen bestätigen oder absagen</li>
</ul>
<p><a href="{login_link}" class="btn">Jetzt einloggen</a></p>
</div>
<div class="footer">Diese E-Mail wurde automatisch versendet.</div>
</div>
</body></html>',
'Hallo {vorname},

du wurdest als Admin für das Fantreffen auf der {schiff} eingetragen.

Reisezeitraum: {anfang} - {ende}

Als Admin kannst du:
- Die Teilnehmerliste einsehen und bearbeiten
- Infomails an alle Teilnehmer versenden
- Das Treffen bestätigen oder absagen

Login: {login_link}

Diese E-Mail wurde automatisch versendet.',
'["vorname", "name", "schiff", "anfang", "ende", "login_link"]');

-- 2. Anmeldebestätigung
INSERT INTO `fan_mail_vorlagen` (`code`, `name`, `beschreibung`, `betreff`, `inhalt_html`, `inhalt_text`, `platzhalter`) VALUES
('anmeldung_bestaetigung', 'Anmeldebestätigung', 'Wird automatisch bei Neuanmeldung gesendet',
'Deine Anmeldung zum Fantreffen auf der {schiff}',
'<html>
<head><style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background: #28a745; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
.content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
.footer { background: #f5f5f5; padding: 15px; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }
.info-box { background: white; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
</style></head>
<body>
<div class="container">
<div class="header"><h2>Anmeldung bestätigt!</h2></div>
<div class="content">
<p>Hallo {vorname},</p>
<p>vielen Dank für deine Anmeldung zum Fantreffen!</p>
<div class="info-box">
<strong>Schiff:</strong> {schiff}<br>
<strong>Reisezeitraum:</strong> {anfang} - {ende}<br>
<strong>Kabine:</strong> {kabine}
</div>
<p>Sobald das Treffen bestätigt wird, erhältst du eine weitere E-Mail mit allen Details zu Ort und Zeit.</p>
<p>Du kannst deine Anmeldung jederzeit unter {login_link} bearbeiten.</p>
</div>
<div class="footer">Diese E-Mail wurde automatisch versendet.</div>
</div>
</body></html>',
'Hallo {vorname},

vielen Dank für deine Anmeldung zum Fantreffen!

Schiff: {schiff}
Reisezeitraum: {anfang} - {ende}
Kabine: {kabine}

Sobald das Treffen bestätigt wird, erhältst du eine weitere E-Mail mit allen Details zu Ort und Zeit.

Du kannst deine Anmeldung jederzeit unter {login_link} bearbeiten.

Diese E-Mail wurde automatisch versendet.',
'["vorname", "name", "schiff", "anfang", "ende", "kabine", "login_link"]');

-- 3. Treffen bestätigt
INSERT INTO `fan_mail_vorlagen` (`code`, `name`, `beschreibung`, `betreff`, `inhalt_html`, `inhalt_text`, `platzhalter`) VALUES
('treffen_bestaetigt', 'Treffen bestätigt', 'Wird vom Admin ausgelöst wenn das Treffen bestätigt wurde',
'Das Fantreffen auf der {schiff} ist bestätigt!',
'<html>
<head><style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background: #0a1f6e; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
.content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
.footer { background: #f5f5f5; padding: 15px; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }
.info-box { background: white; border-left: 4px solid #0a1f6e; padding: 15px; margin: 20px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
</style></head>
<body>
<div class="container">
<div class="header"><h2>Das Fantreffen ist bestätigt!</h2></div>
<div class="content">
<p>Hallo {vorname},</p>
<p>tolle Neuigkeiten! Das Fantreffen auf der <strong>{schiff}</strong> wurde bestätigt.</p>
<div class="info-box">
<strong>Treffpunkt:</strong> {treffen_ort}<br>
<strong>Zeit:</strong> {treffen_zeit}<br>
<strong>Reisezeitraum:</strong> {anfang} - {ende}
</div>
{kabine_hinweis}
<p>Wir freuen uns auf dich!</p>
</div>
<div class="footer">Diese E-Mail wurde automatisch versendet.</div>
</div>
</body></html>',
'Hallo {vorname},

tolle Neuigkeiten! Das Fantreffen auf der {schiff} wurde bestätigt.

Treffpunkt: {treffen_ort}
Zeit: {treffen_zeit}
Reisezeitraum: {anfang} - {ende}

{kabine_hinweis}

Wir freuen uns auf dich!

Diese E-Mail wurde automatisch versendet.',
'["vorname", "name", "schiff", "anfang", "ende", "treffen_ort", "treffen_zeit", "kabine", "kabine_hinweis", "login_link"]');

-- 4. Kabine fehlt (Erinnerung)
INSERT INTO `fan_mail_vorlagen` (`code`, `name`, `beschreibung`, `betreff`, `inhalt_html`, `inhalt_text`, `platzhalter`) VALUES
('kabine_fehlt', 'Kabinennummer fehlt', 'Wird vom Admin ausgelöst für Teilnehmer ohne Kabinennummer',
'Bitte Kabinennummer eintragen - Fantreffen {schiff}',
'<html>
<head><style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background: #ffc107; color: #333; padding: 20px; border-radius: 5px 5px 0 0; }
.content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
.footer { background: #f5f5f5; padding: 15px; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }
.btn { display: inline-block; background: #0a1f6e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
</style></head>
<body>
<div class="container">
<div class="header"><h2>Kabinennummer fehlt!</h2></div>
<div class="content">
<p>Hallo {vorname},</p>
<p>für das Fantreffen auf der <strong>{schiff}</strong> ({anfang} - {ende}) fehlt noch deine Kabinennummer.</p>
<p>Bitte trage sie umgehend ein, damit wir dich an Bord finden können!</p>
<p><a href="{login_link}" class="btn">Jetzt Kabinennummer eintragen</a></p>
</div>
<div class="footer">Diese E-Mail wurde automatisch versendet.</div>
</div>
</body></html>',
'Hallo {vorname},

für das Fantreffen auf der {schiff} ({anfang} - {ende}) fehlt noch deine Kabinennummer.

Bitte trage sie umgehend ein, damit wir dich an Bord finden können!

Login: {login_link}

Diese E-Mail wurde automatisch versendet.',
'["vorname", "name", "schiff", "anfang", "ende", "login_link"]');

-- 5. Erinnerung (Tag vor Anreise)
INSERT INTO `fan_mail_vorlagen` (`code`, `name`, `beschreibung`, `betreff`, `inhalt_html`, `inhalt_text`, `platzhalter`) VALUES
('erinnerung_anreise', 'Erinnerung Anreisetag', 'Wird per Cronjob am Tag vor der Anreise gesendet',
'Morgen geht es los! Fantreffen auf der {schiff}',
'<html>
<head><style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background: #0a1f6e; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
.content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
.footer { background: #f5f5f5; padding: 15px; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }
.info-box { background: white; border-left: 4px solid #0a1f6e; padding: 15px; margin: 20px 0; }
</style></head>
<body>
<div class="container">
<div class="header"><h2>Morgen geht es los!</h2></div>
<div class="content">
<p>Hallo {vorname},</p>
<p>morgen beginnt deine Reise auf der <strong>{schiff}</strong> - und das Fantreffen wartet auf dich!</p>
<div class="info-box">
<strong>Treffpunkt:</strong> {treffen_ort}<br>
<strong>Zeit:</strong> {treffen_zeit}
</div>
<p>Wir freuen uns, dich zu sehen!</p>
</div>
<div class="footer">Diese E-Mail wurde automatisch versendet.</div>
</div>
</body></html>',
'Hallo {vorname},

morgen beginnt deine Reise auf der {schiff} - und das Fantreffen wartet auf dich!

Treffpunkt: {treffen_ort}
Zeit: {treffen_zeit}

Wir freuen uns, dich zu sehen!

Diese E-Mail wurde automatisch versendet.',
'["vorname", "name", "schiff", "treffen_ort", "treffen_zeit"]');
