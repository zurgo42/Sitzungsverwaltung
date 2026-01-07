<?php
/**
 * dokumentation.php - Benutzerhandbuch / Systemdokumentation
 *
 * Basiert auf README.md und bietet eine übersichtliche HTML-Darstellung
 * der Funktionen und Features der Sitzungsverwaltung
 */

// Konfiguration laden (für Footer)
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentation - Sitzungsverwaltung</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c5aa0 0%, #1a3d7a 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.95;
        }

        .nav-top {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-top a {
            color: #2c5aa0;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .nav-top a:hover {
            background: #2c5aa0;
            color: white;
        }

        .content {
            padding: 40px 30px;
        }

        h2 {
            color: #2c5aa0;
            font-size: 2em;
            margin: 40px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid #2c5aa0;
        }

        h3 {
            color: #1a3d7a;
            font-size: 1.5em;
            margin: 30px 0 15px 0;
        }

        h4 {
            color: #333;
            font-size: 1.2em;
            margin: 20px 0 10px 0;
        }

        p {
            margin: 15px 0;
            text-align: justify;
        }

        ul, ol {
            margin: 15px 0 15px 30px;
        }

        li {
            margin: 8px 0;
        }

        strong {
            color: #2c5aa0;
        }

        .feature-box {
            background: #f8f9fa;
            border-left: 4px solid #2c5aa0;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .badge {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
            margin-left: 8px;
        }

        .badge.new {
            background: #ff9800;
        }

        .scenario {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .advantages {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .advantage-card {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.9em;
        }

        .footer a {
            color: #2c5aa0;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .toc {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }

        .toc h3 {
            margin-top: 0;
        }

        .toc ul {
            list-style: none;
            margin-left: 0;
        }

        .toc li {
            margin: 10px 0;
        }

        .toc a {
            color: #2c5aa0;
            text-decoration: none;
            font-weight: 500;
        }

        .toc a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .header h1 {
                font-size: 1.8em;
            }

            .content {
                padding: 20px 15px;
            }

            h2 {
                font-size: 1.5em;
            }

            .advantages {
                grid-template-columns: 1fr;
            }
        }

        /* Scroll-to-Top Button */
        #scrollTop {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #2c5aa0;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: none;
            z-index: 1000;
            transition: all 0.3s;
        }

        #scrollTop:hover {
            background: #1a3d7a;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📋 Sitzungsverwaltung</h1>
            <p>Komplettlösung für professionelle Meeting-Organisation</p>
        </div>

        <!-- Navigation -->
        <div class="nav-top">
            <a href="index.php">← Zurück zur Anwendung</a>
            <a href="#toc">📑 Inhaltsverzeichnis</a>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Überblick -->
            <section id="ueberblick">
                <h2>Überblick</h2>
                <p>Die Sitzungsverwaltung unterstützt den gesamten Lebenszyklus von Meetings – von der Terminplanung über die Tagesordnung bis hin zum Protokoll und der Aufgabenverfolgung. Entwickelt für Organisationen, Vereine, Vorstände und Gremien, die ihre Sitzungen strukturiert und nachvollziehbar gestalten möchten.</p>
            </section>

            <!-- Inhaltsverzeichnis -->
            <div class="toc" id="toc">
                <h3>📑 Inhaltsverzeichnis</h3>
                <ul>
                    <li><a href="#terminplanung">📅 Terminplanung</a></li>
                    <li><a href="#meinungsbild">🗳️ Meinungsbild-Tool</a></li>
                    <li><a href="#meeting-management">📋 Meeting-Management</a></li>
                    <li><a href="#aufgaben">✅ Aufgaben-Management</a></li>
                    <li><a href="#dokumente">📁 Dokumentenverwaltung</a></li>
                    <li><a href="#protokoll">📝 Protokoll-System</a></li>
                    <li><a href="#mitglieder">👥 Mitglieder-Verwaltung</a></li>
                    <li><a href="#abwesenheiten">🏖️ Abwesenheits-Verwaltung</a></li>
                    <li><a href="#texte">📝 Kollaborative Texte</a></li>
                    <li><a href="#kommentare">💬 Kommentar-System</a></li>
                    <li><a href="#admin">🔧 Admin-Funktionen</a></li>
                    <li><a href="#szenarien">💡 Anwendungsszenarien</a></li>
                    <li><a href="#vorteile">✅ Vorteile</a></li>
                </ul>
            </div>

            <!-- Terminplanung -->
            <section id="terminplanung">
                <h2>📅 Terminplanung</h2>
                <div class="feature-box">
                    <h4>Demokratische Terminfindung mit dem integrierten Doodle-Tool</h4>
                    <ul>
                        <li>Erstellen Sie Umfragen mit mehreren Terminvorschlägen</li>
                        <li>Teilnehmer stimmen mit Ja/Nein/Vielleicht ab</li>
                        <li>Übersichtliche Auswertung zeigt den besten Termin</li>
                        <li>Automatische Meeting-Erstellung nach Festlegung</li>
                        <li><strong>E-Mail-Benachrichtigungen:</strong>
                            <ul>
                                <li>Einladung zur Terminabstimmung</li>
                                <li>Bestätigung nach Festlegung</li>
                                <li>Erinnerung X Tage vor dem Termin</li>
                            </ul>
                        </li>
                    </ul>
                    <p><strong>Ideal für:</strong> Gremiensitzungen, Vorstandssitzungen, Projektmeetings</p>
                </div>
            </section>

            <!-- Meinungsbild-Tool -->
            <section id="meinungsbild">
                <h2>🗳️ Meinungsbild-Tool</h2>
                <div class="feature-box">
                    <h4>Schnelle Umfragen und Stimmungsbilder erfassen</h4>

                    <h4>4 Zielgruppen:</h4>
                    <ul>
                        <li><strong>Individual (Link):</strong> Generieren Sie einen einzigartigen Link zum Teilen</li>
                        <li><strong>Meeting-Teilnehmer:</strong> Umfrage nur für eingeladene Personen eines Meetings</li>
                        <li><strong>Öffentlich:</strong> Jeder Besucher kann teilnehmen</li>
                        <li><strong>Externe Teilnehmer:</strong> <span class="badge new">NEU</span> Token-basierter Zugang für Gäste ohne Login (z.B. Beiratsmitglieder, Partner)</li>
                    </ul>

                    <h4>13 vorgefertigte Antwort-Templates:</h4>
                    <ul>
                        <li>Ja/Nein/Enthaltung</li>
                        <li>Passt-Skala (5-stufig)</li>
                        <li>Dafür/Dagegen</li>
                        <li>Gefällt mir</li>
                        <li>Numerische Skalen</li>
                        <li>Dringlichkeit, Wichtigkeit, Priorität</li>
                        <li>Plus: Freies Template für eigene Optionen (bis zu 10)</li>
                    </ul>

                    <h4>Flexible Einstellungen:</h4>
                    <ul>
                        <li>Mehrfachantworten erlauben/verbieten</li>
                        <li>Anonym oder mit Namensnennung</li>
                        <li>Individuelle Anonymität (User entscheidet selbst)</li>
                        <li>Laufzeit frei wählbar</li>
                        <li>Zwischenergebnisse nach X Tagen anzeigen</li>
                        <li>Automatische Löschung nach Ablauf</li>
                    </ul>

                    <p><strong>Ideal für:</strong> Meinungsbilder, Feature-Bewertungen, Stimmungsabfragen, Quick Polls</p>
                </div>
            </section>

            <!-- Meeting-Management -->
            <section id="meeting-management">
                <h2>📋 Meeting-Management</h2>
                <div class="feature-box">
                    <h4>Vollständiger Sitzungszyklus:</h4>

                    <h4>1. Meeting anlegen:</h4>
                    <ul>
                        <li>Titel, Datum, Ort (physisch oder virtuell)</li>
                        <li>Video-Link für Online-Meetings</li>
                        <li>Einladen von Teilnehmern</li>
                        <li>Sichtbarkeits-Einstellungen (🔒 Nur Eingeladene, 👔 Führungsteam, 🌐 Öffentlich)</li>
                        <li><span class="badge new">NEU</span> <strong>Meeting duplizieren:</strong> Für regelmäßige Sitzungen - kopiert alle Einstellungen mit +7 Tage</li>
                    </ul>

                    <h4>2. Tagesordnung erstellen:</h4>
                    <ul>
                        <li>Strukturierte TOPs mit Nummerierung</li>
                        <li><strong>8 verschiedene Kategorien:</strong> Information, Klärung, Diskussion, Aussprache, Antrag/Beschluss, Wahl, Bericht, Sonstiges</li>
                        <li>Prioritäten und Zeitschätzungen</li>
                        <li>Vertrauliche TOPs markieren</li>
                        <li>TOP-Gruppierungen</li>
                    </ul>

                    <h4>3. Kollaborative Vorbereitung:</h4>
                    <ul>
                        <li>Kommentare zu jedem TOP</li>
                        <li>Prioritäts-Bewertungen durch Teilnehmer</li>
                        <li>Zeitschätzungen anpassen</li>
                        <li>Automatische Berechnung der Gesamtdauer</li>
                    </ul>

                    <h4>4. Meeting durchführen:</h4>
                    <ul>
                        <li>Sitzungsleitung und Protokollführung festlegen</li>
                        <li>TOPs aktivieren und abarbeiten</li>
                        <li>Abstimmungen dokumentieren</li>
                        <li>Echtzeit-Protokollierung</li>
                    </ul>

                    <h4>5. Nachbereitung:</h4>
                    <ul>
                        <li>Protokoll erstellen (öffentlich und vertraulich getrennt)</li>
                        <li>Änderungsanfragen verwalten</li>
                        <li>TODOs erfassen und zuweisen</li>
                    </ul>
                </div>
            </section>

            <!-- Aufgaben-Management -->
            <section id="aufgaben">
                <h2>✅ Aufgaben-Management (TODOs)</h2>
                <div class="feature-box">
                    <h4>Nachverfolgung von Beschlüssen und Aufgaben:</h4>
                    <ul>
                        <li>TODOs aus Meetings oder unabhängig erstellen</li>
                        <li>Zuweisen an Mitglieder</li>
                        <li>Fälligkeitsdaten setzen</li>
                        <li>Status-Verfolgung (offen, in Bearbeitung, erledigt)</li>
                        <li>Private TODOs (nur für Ersteller sichtbar)</li>
                        <li>Automatische Benachrichtigungen</li>
                        <li>Verknüpfung mit Meeting-Protokollen</li>
                    </ul>
                </div>
            </section>

            <!-- Dokumentenverwaltung -->
            <section id="dokumente">
                <h2>📁 Dokumentenverwaltung</h2>
                <div class="feature-box">
                    <h4>Zentrale Ablage für Vereinsdokumente</h4>

                    <h4>Upload-Funktionen:</h4>
                    <ul>
                        <li>Unterstützte Formate: PDF, DOC, DOCX, XLS, XLSX, RTF, TXT, Bilder</li>
                        <li><span class="badge new">NEU</span> <strong>Externe Links:</strong> Verlinke auf Cloud-Dokumente (SharePoint, Google Drive, etc.) statt Upload</li>
                        <li>Metadaten: Titel, Beschreibung, Version, Stichworte</li>
                    </ul>

                    <h4>Kategorisierung:</h4>
                    <ul>
                        <li>Satzung, Ordnungen, Richtlinien</li>
                        <li>Formulare, MV-Unterlagen</li>
                        <li>Dokumentationen, Urteile, Medien</li>
                        <li>Automatische Kategorisierung</li>
                    </ul>

                    <h4>Zugriffskontrolle:</h4>
                    <ul>
                        <li>Rollenbasierte Berechtigungen (Level 0-19)</li>
                        <li>Dokumente für spezifische Gruppen freigeben</li>
                        <li>Admin-only Bearbeitung und Upload</li>
                    </ul>

                    <p><strong>Ideal für:</strong> Satzungen, Protokolle, Formulare, Handbücher, Berichte</p>
                </div>
            </section>

            <!-- Protokoll-System -->
            <section id="protokoll">
                <h2>📝 Protokoll-System</h2>
                <div class="feature-box">
                    <h4>Professionelle Dokumentation:</h4>
                    <ul>
                        <li><strong>Öffentliches Protokoll:</strong> Für allgemeine Verbreitung</li>
                        <li><strong>Vertrauliches Protokoll:</strong> Nur für berechtigte Personen</li>
                        <li>Getrennte Speicherung und Zugriffsrechte</li>
                        <li><strong>Rollenbasierte Filterung:</strong>
                            <ul>
                                <li>Vorstand/GF/Assistenz: Sehen alle Protokolle</li>
                                <li>Führungsteam: Sehen Protokolle von eigenen Meetings + öffentliche</li>
                                <li>Mitglieder: Sehen nur öffentliche Protokolle</li>
                            </ul>
                        </li>
                        <li>Markdown-Unterstützung für Formatierung</li>
                        <li>Änderungsanfragen von Teilnehmern</li>
                        <li>Versionierung</li>
                    </ul>
                </div>
            </section>

            <!-- Mitglieder-Verwaltung -->
            <section id="mitglieder">
                <h2>👥 Mitglieder-Verwaltung</h2>
                <div class="feature-box">
                    <h4>Rollen und Berechtigungen:</h4>
                    <ul>
                        <li><strong>Vorstand / Geschäftsführung:</strong> Volle Admin-Rechte, alle Protokolle und Meetings sichtbar</li>
                        <li><strong>Assistenz:</strong> Meetings anlegen und verwalten, alle Protokolle sichtbar</li>
                        <li><strong>Führungsteam:</strong> Erweiterte Rechte, Textbearbeitung, sehen eigene Meetings + öffentliche</li>
                        <li><strong>Mitglied:</strong> Basis-Teilnahmerechte, sehen nur öffentliche Protokolle</li>
                    </ul>

                    <h4>Features:</h4>
                    <ul>
                        <li>Mitgliedsnummern</li>
                        <li>Aktiv/Inaktiv-Status</li>
                        <li>Vertraulichkeits-Kennzeichnung</li>
                        <li>E-Mail-Benachrichtigungen</li>
                    </ul>
                </div>
            </section>

            <!-- Abwesenheits-Verwaltung -->
            <section id="abwesenheiten">
                <h2>🏖️ Abwesenheits-Verwaltung</h2>
                <div class="feature-box">
                    <h4>Transparente Vertretungsregelungen:</h4>
                    <ul>
                        <li>Zeitraum angeben (von - bis)</li>
                        <li>Vertretung auswählen</li>
                        <li>Optional: Grund angeben</li>
                        <li>Desktop: Tabellenansicht mit allen Details</li>
                        <li>Smartphone: Card-Layout für bessere Lesbarkeit</li>
                        <li>Farbliche Kennzeichnung (aktuell, zukünftig, vergangen)</li>
                        <li>Automatische Filterung nach Zeitraum</li>
                    </ul>
                    <p><strong>Ideal für:</strong> Urlaubsplanung, Vertretungsregelungen, Abwesenheitskalender</p>
                </div>
            </section>

            <!-- Kollaborative Texte -->
            <section id="texte">
                <h2>📝 Kollaborative Texte</h2>
                <div class="feature-box">
                    <h4>Gemeinsam an Texten arbeiten während Sitzungen:</h4>

                    <h4>Zwei Modi:</h4>
                    <ul>
                        <li><strong>Meeting-Modus:</strong> Alle Sitzungsteilnehmer können mitarbeiten</li>
                        <li><strong>Allgemein-Modus:</strong> Vorstand, GF, Assistenz und Führungsteam können allgemeine Texte erstellen</li>
                    </ul>

                    <h4>Absatz-basiertes Editieren:</h4>
                    <ul>
                        <li>Text ist in Absätze unterteilt</li>
                        <li>Jeder Absatz kann einzeln bearbeitet werden</li>
                        <li>Automatisches Lock-System verhindert Konflikte</li>
                        <li>Während ein Teilnehmer einen Absatz bearbeitet, sehen andere "🔒 Name bearbeitet"</li>
                        <li><strong>Schutz vor Datenverlust:</strong> Nur ein Absatz kann gleichzeitig bearbeitet werden</li>
                    </ul>

                    <h4>Live-Synchronisation:</h4>
                    <ul>
                        <li>Änderungen werden alle 1-2 Sekunden aktualisiert</li>
                        <li>Online-Status zeigt wer gerade aktiv ist</li>
                        <li>Änderungen anderer Teilnehmer erscheinen automatisch</li>
                    </ul>

                    <p><strong>Ideal für:</strong> Pressemeldungen, offizielle Briefe, gemeinsame Stellungnahmen</p>
                    <p style="color: #4CAF50; font-weight: 600;">DSGVO-konform: Alle Daten bleiben auf Ihrem Server – kein Google Docs notwendig!</p>
                </div>
            </section>

            <!-- Kommentar-System -->
            <section id="kommentare">
                <h2>💬 Intelligentes Kommentar-System</h2>
                <div class="feature-box">
                    <h4>Drei-Phasen-Kommentierung:</h4>

                    <h4>1. Vorbereitung:</h4>
                    <ul>
                        <li>Diskussionsbeiträge zu TOPs</li>
                        <li>Prioritäts-Bewertungen</li>
                        <li>Zeitschätzungen</li>
                    </ul>

                    <h4>2. Live während der Sitzung:</h4>
                    <ul>
                        <li>Echtzeit-Kommentare</li>
                        <li>Auto-Scroll zu neuesten Einträgen</li>
                        <li>Dynamisch wachsende Textfelder</li>
                    </ul>

                    <h4>3. Nachträglich (nach Sitzungsende):</h4>
                    <ul>
                        <li>Anmerkungen zum Protokollentwurf</li>
                        <li>Für alle Beteiligten (inkl. Protokollant und Sitzungsleitung)</li>
                        <li>Transparente Hinweise: "Kommentare werden nach Genehmigung verworfen"</li>
                    </ul>

                    <h4>Features:</h4>
                    <ul>
                        <li><strong>URL-Erkennung:</strong> Links werden automatisch klickbar</li>
                        <li><strong>Intelligente Alerts:</strong> Warnungen für Bild-/PDF-Links auf Mobilgeräten</li>
                        <li><strong>Auto-Resize:</strong> Textfelder passen sich dynamisch an</li>
                    </ul>
                </div>
            </section>

            <!-- Admin-Funktionen -->
            <section id="admin">
                <h2>🔧 Admin-Funktionen</h2>
                <div class="feature-box">
                    <h4>Backup & Restore:</h4>
                    <ul>
                        <li>Regelmäßige Datenbank-Sicherungen erstellen</li>
                        <li>Passwortgeschützt (System-Admin-Passwort)</li>
                        <li>Backup-Dateien herunterladen</li>
                        <li>Datenbank aus Backup wiederherstellen</li>
                        <li>Migration-Tools für Schema-Updates</li>
                    </ul>
                </div>
            </section>

            <!-- Anwendungsszenarien -->
            <section id="szenarien">
                <h2>💡 Typische Anwendungsszenarien</h2>

                <div class="scenario">
                    <h3>Szenario 1: Vorstandssitzung planen</h3>
                    <ol>
                        <li><strong>Termin finden:</strong> Tab "Termine" → "Neue Terminabstimmung" → 3-4 Terminvorschläge eintragen</li>
                        <li><strong>Bester Termin festlegen:</strong> Nach Ablauf der Frist Auswertung ansehen → "Festlegen" → Meeting wird automatisch erstellt</li>
                        <li><strong>Tagesordnung vorbereiten:</strong> TOPs hinzufügen (z.B. Finanzbericht, Personalplanung)</li>
                        <li><strong>Meeting durchführen:</strong> "Sitzung starten" → TOPs nacheinander aktivieren</li>
                        <li><strong>Nachbereitung:</strong> Protokoll finalisieren → TODOs zuweisen → "Meeting beenden"</li>
                    </ol>
                </div>

                <div class="scenario">
                    <h3>Szenario 2: Schnelles Meinungsbild einholen</h3>
                    <ol>
                        <li><strong>Umfrage erstellen:</strong> Tab "Meinungsbild" → Frage formulieren → Template wählen</li>
                        <li><strong>Link teilen:</strong> Generierten Link per E-Mail, Chat oder Social Media teilen</li>
                        <li><strong>Ergebnisse auswerten:</strong> Automatische Balkendiagramme → Kommentare lesen → Entscheidung treffen</li>
                    </ol>
                </div>

                <div class="scenario">
                    <h3>Szenario 3: Vertrauliche Gremiensitzung</h3>
                    <ol>
                        <li><strong>Meeting mit Vertraulichkeit:</strong> Meeting anlegen mit visibility_type = "invited_only"</li>
                        <li><strong>Getrennte Protokolle:</strong> Öffentliches + Vertrauliches Protokoll erstellen</li>
                        <li><strong>Zugriffskontrolle:</strong> Nur eingeladene Teilnehmer sehen das Meeting</li>
                    </ol>
                </div>
            </section>

            <!-- Vorteile -->
            <section id="vorteile">
                <h2>✅ Vorteile</h2>
                <div class="advantages">
                    <div class="advantage-card">
                        <strong>✅ Zeitersparnis</strong>
                        <p>Automatisierte Prozesse reduzieren manuellen Aufwand</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Transparenz</strong>
                        <p>Alle Informationen zentral und nachvollziehbar</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Demokratie</strong>
                        <p>Faire Terminfindung und Meinungsabfragen</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Struktur</strong>
                        <p>Standardisierte Abläufe für professionelle Meetings</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Nachvollziehbarkeit</strong>
                        <p>Vollständige Dokumentation aller Entscheidungen</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Flexibilität</strong>
                        <p>Anpassbar an verschiedene Organisationsformen</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Zugänglichkeit</strong>
                        <p>Webbasiert, von überall erreichbar</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Mobile-First</strong>
                        <p>Optimiert für Desktop und Smartphone</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Benutzerfreundlichkeit</strong>
                        <p>Intuitive Bedienung mit intelligenten Features</p>
                    </div>
                    <div class="advantage-card">
                        <strong>✅ Datensicherheit</strong>
                        <p>Backup & Restore-Funktionen inklusive</p>
                    </div>
                </div>
            </section>

            <!-- Systemanforderungen -->
            <section id="anforderungen">
                <h2>💻 Systemanforderungen (für Anwender)</h2>
                <ul>
                    <li>Moderner Webbrowser (Chrome, Firefox, Safari, Edge)</li>
                    <li>Internetverbindung</li>
                    <li>Optional: E-Mail-Konto für Benachrichtigungen</li>
                </ul>
            </section>

            <!-- Entwickelt für -->
            <section>
                <h2>🎯 Entwickelt für</h2>
                <p style="font-size: 1.1em; text-align: center; color: #2c5aa0; font-weight: 600;">
                    Organisationen, Vereine, Vorstände, Gremien und Teams, die ihre Meetings professionell organisieren und dokumentieren möchten.
                </p>
            </section>
        </div>

        <!-- Footer -->
        <div class="footer">
            <?php echo FOOTER_COPYRIGHT; ?> |
            <a href="<?php echo FOOTER_IMPRESSUM_URL; ?>" target="_blank">Impressum</a> |
            <a href="<?php echo FOOTER_DATENSCHUTZ_URL; ?>" target="_blank">Datenschutz</a>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <button id="scrollTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'});">↑</button>

    <script>
        // Show/Hide Scroll-to-Top Button
        window.onscroll = function() {
            const btn = document.getElementById('scrollTop');
            if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                btn.style.display = 'block';
            } else {
                btn.style.display = 'none';
            }
        };
    </script>
</body>
</html>
