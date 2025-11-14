<?php
/**
 * config_adapter.php - Konfiguration für Datenbank-Adapter
 *
 * Hier definieren Sie, welchen Adapter das System verwenden soll
 */

// Welcher Adapter soll verwendet werden?
// Optionen: 'standard' (members Tabelle) oder 'berechtigte' (Ihre externe Tabelle)
define('MEMBER_ADAPTER_TYPE', 'standard');  // ÄNDERN SIE DIES auf 'berechtigte' wenn Sie Ihre Tabelle nutzen

// Alternative: Umgebungsvariable oder automatische Erkennung
// define('MEMBER_ADAPTER_TYPE', getenv('MEMBER_ADAPTER') ?: 'standard');

/**
 * Adapter-spezifische Konfigurationen
 */
$ADAPTER_CONFIG = [
    'berechtigte' => [
        // Falls separate Datenbank
        'db_host' => DB_HOST,  // oder anderer Host
        'db_name' => DB_NAME,  // oder andere DB
        'db_user' => DB_USER,
        'db_pass' => DB_PASS,

        // Spezielle Mappings können hier definiert werden
        'role_mapping' => [
            'Vorstand' => 'vorstand',
            'Geschäftsführung' => 'gf',
            'Assistenz' => 'assistenz',
            'Führungsteam' => 'fuehrungsteam',
            'Mitglied' => 'Mitglied'
        ],

        // Logik für aktiv-Werte
        'confidential_threshold' => 2  // aktiv >= 2 = vertraulich
    ]
];
?>
