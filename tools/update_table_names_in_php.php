<?php
/**
 * update_table_names_in_php.php - Aktualisiert alle Tabellennamen in PHP-Dateien
 * Erstellt: 27.11.2025
 *
 * Dieses Skript ersetzt alle alten Tabellennamen durch die neuen Namen mit "sv"-Präfix.
 * Es durchsucht alle PHP-Dateien im Projekt und nimmt die Änderungen vor.
 *
 * WICHTIG: Vor Ausführung sollte ein Backup erstellt werden!
 *
 * Verwendung:
 * php migrations/update_table_names_in_php.php
 */

// Mapping: Alt → Neu
$table_mapping = [
    // Core-Tabellen
    'members' => 'svmembers',

    // Meeting-Tabellen
    'meetings' => 'svmeetings',
    'meeting_participants' => 'svmeeting_participants',
    'agenda_items' => 'svagenda_items',
    'agenda_comments' => 'svagenda_comments',

    // Protokoll-Tabellen
    'protocols' => 'svprotocols',
    'protocol_change_requests' => 'svprotocol_change_requests',

    // TODO-Tabellen
    'todos' => 'svtodos',
    'todo_log' => 'svtodo_log',

    // Admin-Log
    'admin_log' => 'svadmin_log',

    // Terminplanung-Tabellen
    'polls' => 'svpolls',
    'poll_dates' => 'svpoll_dates',
    'poll_participants' => 'svpoll_participants',
    'poll_responses' => 'svpoll_responses',

    // Meinungsbild-Tool-Tabellen
    'opinion_answer_templates' => 'svopinion_answer_templates',
    'opinion_polls' => 'svopinion_polls',
    'opinion_poll_options' => 'svopinion_poll_options',
    'opinion_poll_participants' => 'svopinion_poll_participants',
    'opinion_responses' => 'svopinion_responses',
    'opinion_response_options' => 'svopinion_response_options',

    // E-Mail-Warteschlange
    'mail_queue' => 'svmail_queue',

    // Dokumentenverwaltung
    'documents' => 'svdocuments',
    'document_downloads' => 'svdocument_downloads',

    // Referenten-Modul (optional)
    'Refname' => 'svRefname',
    'Refpool' => 'svRefpool',
    'PLZ' => 'svPLZ',
];

// Statistik
$total_files = 0;
$modified_files = 0;
$total_replacements = 0;

echo "==========================================================\n";
echo "Tabellennamen-Aktualisierung für Sitzungsverwaltung\n";
echo "==========================================================\n\n";

// Alle PHP-Dateien finden
$php_files = [];
$directories = [
    __DIR__ . '/..',
    __DIR__ . '/../opinion_views',
    __DIR__ . '/../tools',
    __DIR__ . '/../adapters',
    __DIR__ . '/../referenten',
    __DIR__ . '/../referenten/includes',
    __DIR__ . '/../referenten/templates',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $files = glob($dir . '/*.php');
    if ($files) {
        $php_files = array_merge($php_files, $files);
    }
}

echo "Gefundene PHP-Dateien: " . count($php_files) . "\n\n";

// Jede Datei durchgehen
foreach ($php_files as $file) {
    $total_files++;
    $content = file_get_contents($file);
    $original_content = $content;
    $file_replacements = 0;

    // Für jede Tabelle ersetzen
    foreach ($table_mapping as $old_name => $new_name) {
        // Verschiedene Muster ersetzen
        $patterns = [
            // FROM/JOIN/INTO/UPDATE Tabelle
            "/\\b(FROM|JOIN|INTO|UPDATE|TABLE)\\s+`?{$old_name}`?\\b/i" => "$1 {$new_name}",
            // CREATE TABLE Tabelle
            "/\\bCREATE\\s+TABLE\\s+(IF\\s+NOT\\s+EXISTS\\s+)?`?{$old_name}`?\\b/i" => "CREATE TABLE $1{$new_name}",
            // ALTER TABLE Tabelle
            "/\\bALTER\\s+TABLE\\s+`?{$old_name}`?\\b/i" => "ALTER TABLE {$new_name}",
            // DROP TABLE Tabelle
            "/\\bDROP\\s+TABLE\\s+(IF\\s+EXISTS\\s+)?`?{$old_name}`?\\b/i" => "DROP TABLE $1{$new_name}",
            // REFERENCES Tabelle
            "/\\bREFERENCES\\s+`?{$old_name}`?\\(/i" => "REFERENCES {$new_name}(",
            // SHOW COLUMNS FROM Tabelle
            "/\\bSHOW\\s+COLUMNS\\s+FROM\\s+`?{$old_name}`?\\b/i" => "SHOW COLUMNS FROM {$new_name}",
            // In Backticks
            "/`{$old_name}`/" => "`{$new_name}`",
        ];

        foreach ($patterns as $pattern => $replacement) {
            $new_content = preg_replace($pattern, $replacement, $content);
            if ($new_content !== $content) {
                $count = 0;
                $content = preg_replace($pattern, $replacement, $content, -1, $count);
                $file_replacements += $count;
            }
        }
    }

    // Wenn Änderungen vorgenommen wurden, speichern
    if ($content !== $original_content) {
        file_put_contents($file, $content);
        $modified_files++;
        $total_replacements += $file_replacements;

        $short_name = str_replace(__DIR__ . '/..', '', $file);
        echo "✓ {$short_name} ({$file_replacements} Ersetzungen)\n";
    }
}

echo "\n==========================================================\n";
echo "Fertig!\n";
echo "==========================================================\n";
echo "Durchsuchte Dateien: {$total_files}\n";
echo "Geänderte Dateien: {$modified_files}\n";
echo "Gesamt-Ersetzungen: {$total_replacements}\n";
echo "\n";
echo "WICHTIG: Bitte überprüfen Sie die Änderungen mit 'git diff'\n";
echo "         bevor Sie committen!\n";
echo "==========================================================\n";
?>
