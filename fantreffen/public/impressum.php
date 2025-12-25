<?php
/**
 * Impressum
 */

require_once __DIR__ . '/../src/Session.php';
Session::start();

$pageTitle = 'Impressum - AIDA Fantreffen';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <h1>Impressum</h1>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">Angaben gemäß § 5 TMG</h2>
                <p>
                    <strong>[Name]</strong><br>
                    [Straße und Hausnummer]<br>
                    [PLZ Ort]<br>
                    Deutschland
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">Kontakt</h2>
                <p>
                    E-Mail: <a href="mailto:info@aidafantreffen.de">info@aidafantreffen.de</a>
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">Hinweise</h2>
                <p>
                    <strong>Privates, nicht-kommerzielles Projekt</strong><br>
                    Diese Website dient ausschließlich der Organisation von privaten Fantreffen auf AIDA-Kreuzfahrten.
                    Es handelt sich um ein nicht-kommerzielles Projekt ohne Gewinnerzielungsabsicht.
                </p>
                <p>
                    <strong>Keine offizielle AIDA-Website</strong><br>
                    Diese Website steht in keiner Verbindung zu AIDA Cruises oder der Carnival Corporation.
                    "AIDA" ist eine eingetragene Marke der AIDA Cruises - German Branch of Costa Crociere S.p.A.
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">Haftungsausschluss</h2>

                <h5>Haftung für Inhalte</h5>
                <p>
                    Die Inhalte dieser Seiten wurden mit größter Sorgfalt erstellt.
                    Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte kann jedoch keine Gewähr übernommen werden.
                </p>

                <h5>Haftung für Links</h5>
                <p>
                    Diese Website enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben.
                    Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter verantwortlich.
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">Urheberrecht</h2>
                <p>
                    Die auf dieser Website verwendeten Schiffbilder dienen nur der Illustration und
                    unterliegen dem Urheberrecht ihrer jeweiligen Rechteinhaber.
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
