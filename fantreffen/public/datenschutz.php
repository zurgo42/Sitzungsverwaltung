<?php
/**
 * Datenschutzerklärung
 */

require_once __DIR__ . '/../src/Session.php';
Session::start();

$pageTitle = 'Datenschutzerklärung - AIDA Fantreffen';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-lg-10 mx-auto">
        <h1>Datenschutzerklärung</h1>
        <p class="text-muted">Stand: <?= date('d.m.Y') ?></p>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">1. Verantwortlicher</h2>
                <p>
                    Verantwortlich für die Datenverarbeitung auf dieser Website ist:<br>
                    <strong>[Name des Verantwortlichen]</strong><br>
                    [Adresse]<br>
                    E-Mail: <a href="mailto:info@aidafantreffen.de">info@aidafantreffen.de</a>
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">2. Welche Daten wir speichern</h2>
                <p>Bei der Nutzung unserer Website werden folgende personenbezogene Daten erhoben und gespeichert:</p>

                <h5>2.1 Registrierung (Benutzerkonto)</h5>
                <ul>
                    <li><strong>E-Mail-Adresse</strong> - zur Identifikation und Kommunikation</li>
                    <li><strong>Passwort</strong> - verschlüsselt gespeichert (bcrypt-Hash), nicht im Klartext</li>
                    <li><strong>Registrierungsdatum</strong></li>
                    <li><strong>Letzter Login</strong></li>
                </ul>

                <h5>2.2 Teilnehmerdaten</h5>
                <p>Für jede Person, die du für Fantreffen anmeldest, speichern wir:</p>
                <ul>
                    <li><strong>Vorname und Nachname</strong> - zur Identifikation beim Treffen</li>
                    <li><strong>Nickname</strong> (optional) - wird in der Teilnehmerliste angezeigt</li>
                    <li><strong>Mobilnummer</strong> (optional) - nur für Organisatoren sichtbar, wird <u>nicht veröffentlicht</u></li>
                </ul>

                <h5>2.3 Anmeldung zu Reisen</h5>
                <p>Bei der Anmeldung zu einer Fantreffen-Reise speichern wir:</p>
                <ul>
                    <li><strong>Kabinennummer</strong> (optional) - zur Koordination beim Treffen</li>
                    <li><strong>Bemerkungen</strong> (optional) - deine Hinweise an die Organisatoren</li>
                    <li><strong>Anmeldedatum</strong></li>
                    <li><strong>Zugeordnete Teilnehmer</strong></li>
                </ul>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">3. Zweck der Datenverarbeitung</h2>
                <p>Wir verarbeiten deine Daten ausschließlich für folgende Zwecke:</p>
                <ul>
                    <li>Organisation und Durchführung von Fantreffen auf AIDA-Kreuzfahrten</li>
                    <li>Erstellung von Teilnehmerlisten für die Treffen</li>
                    <li>Kommunikation über Treffpunkt, Zeit und wichtige Informationen</li>
                    <li>Erstellung von Namensschildern für die Treffen</li>
                </ul>
                <p>Deine Daten werden <strong>nicht</strong> für Werbezwecke verwendet und <strong>nicht</strong> an Dritte verkauft.</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">4. Sichtbarkeit deiner Daten</h2>

                <h5>4.1 Für andere Teilnehmer sichtbar</h5>
                <p>Wenn du dich für eine Reise angemeldet hast, sehen andere angemeldete Teilnehmer:</p>
                <ul>
                    <li>Abgekürzte Namen (z.B. "H... M...")</li>
                    <li>Abgekürzte Kabinennummer (z.B. "8...")</li>
                </ul>

                <h5>4.2 Für Organisatoren (Admins) sichtbar</h5>
                <p>Die Organisatoren der jeweiligen Reise sehen:</p>
                <ul>
                    <li>Vollständige Namen und Nicknames</li>
                    <li>Vollständige Kabinennummer</li>
                    <li>E-Mail-Adresse</li>
                    <li>Mobilnummer (falls angegeben)</li>
                    <li>Bemerkungen</li>
                </ul>

                <h5>4.3 Nicht sichtbar</h5>
                <ul>
                    <li>Nutzer, die nicht für die Reise angemeldet sind, sehen keine Teilnehmerliste</li>
                    <li>Passwörter sind niemals sichtbar (auch nicht für Admins)</li>
                </ul>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">5. Speicherdauer</h2>
                <p>
                    Deine Daten werden gespeichert, solange dein Benutzerkonto besteht.
                    Nach Löschung deines Kontos werden alle zugehörigen Daten (Teilnehmer, Anmeldungen) ebenfalls gelöscht.
                </p>
                <p>
                    Anmeldungen zu vergangenen Reisen können zur Dokumentation aufbewahrt werden,
                    werden aber nach spätestens 2 Jahren gelöscht.
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">6. Deine Rechte</h2>
                <p>Du hast folgende Rechte bezüglich deiner personenbezogenen Daten:</p>
                <ul>
                    <li><strong>Auskunft:</strong> Du kannst jederzeit Auskunft über deine gespeicherten Daten verlangen.</li>
                    <li><strong>Berichtigung:</strong> Du kannst deine Daten jederzeit in deinem Profil korrigieren.</li>
                    <li><strong>Löschung:</strong> Du kannst die Löschung deines Kontos und aller Daten verlangen.</li>
                    <li><strong>Widerruf:</strong> Du kannst deine Einwilligung zur Datenverarbeitung jederzeit widerrufen.</li>
                </ul>
                <p>
                    Für alle Anfragen wende dich bitte an:
                    <a href="mailto:info@aidafantreffen.de">info@aidafantreffen.de</a>
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">7. Technische Sicherheit</h2>
                <ul>
                    <li>Passwörter werden mit bcrypt verschlüsselt gespeichert</li>
                    <li>Die Verbindung zur Website ist SSL-verschlüsselt (HTTPS)</li>
                    <li>Zugriff auf die Datenbank ist auf autorisierte Personen beschränkt</li>
                    <li>CSRF-Schutz gegen Cross-Site-Request-Forgery-Angriffe</li>
                </ul>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">8. Cookies</h2>
                <p>
                    Diese Website verwendet nur technisch notwendige Session-Cookies, um dich eingeloggt zu halten.
                    Es werden <strong>keine</strong> Tracking-Cookies oder Cookies von Drittanbietern verwendet.
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">9. Externe Dienste</h2>
                <p>
                    Diese Website lädt keine externen Ressourcen von Drittanbietern.
                    Alle Styles und Icons sind lokal eingebunden.
                </p>
            </div>
        </div>

        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary">
                ← Zurück zur Startseite
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
