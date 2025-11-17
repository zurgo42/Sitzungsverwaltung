<?php
/**
 * ADAPTER_USAGE_EXAMPLE.php - Beispiel zur Verwendung des Member-Adapters
 *
 * Zeigt, wie Sie Ihren bestehenden Code anpassen, um den Adapter zu nutzen
 */

// ============================================
// SCHRITT 1: Adapter initialisieren
// ============================================

require_once 'config.php';
require_once 'config_adapter.php';
require_once 'adapters/MemberAdapter.php';

// PDO-Verbindung
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Adapter erstellen (basierend auf Konfiguration)
$memberAdapter = MemberAdapterFactory::create($pdo, MEMBER_ADAPTER_TYPE);

// ============================================
// SCHRITT 2: Bestehenden Code anpassen
// ============================================

// VORHER (direkter DB-Zugriff):
// $stmt = $pdo->query("SELECT * FROM members ORDER BY last_name");
// $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// NACHHER (mit Adapter):
$members = $memberAdapter->getAllMembers();

// Die Struktur bleibt gleich! $members enthält die gleichen Felder:
// - member_id
// - first_name
// - last_name
// - email
// - role
// - is_admin
// - is_confidential
// etc.

// ============================================
// WEITERE BEISPIELE
// ============================================

// Mitglied nach ID abrufen
$member = $memberAdapter->getMemberById(1);

// Mitglied nach E-Mail suchen
$member = $memberAdapter->getMemberByEmail('test@example.com');

// Neues Mitglied erstellen
$new_id = $memberAdapter->createMember([
    'first_name' => 'Max',
    'last_name' => 'Mustermann',
    'email' => 'max@example.com',
    'role' => 'Mitglied',
    'is_admin' => 0,
    'is_confidential' => 0,
    'password_hash' => password_hash('test123', PASSWORD_DEFAULT)
]);

// Mitglied aktualisieren
$memberAdapter->updateMember(1, [
    'first_name' => 'Maxine',
    'last_name' => 'Musterfrau',
    'email' => 'maxine@example.com',
    'role' => 'vorstand',
    'is_admin' => 1,
    'is_confidential' => 1
]);

// Mitglied löschen
$memberAdapter->deleteMember(1);

// Login/Authentifizierung
$authenticated_member = $memberAdapter->authenticate('test@example.com', 'password123');
if ($authenticated_member) {
    $_SESSION['member_id'] = $authenticated_member['member_id'];
    // ... Login-Logik
}

// ============================================
// SCHRITT 3: Integration in bestehende Dateien
// ============================================

/**
 * So würden Sie z.B. functions.php anpassen:
 */

// In functions.php ganz oben:
require_once 'config_adapter.php';
require_once 'adapters/MemberAdapter.php';
global $memberAdapter;
$memberAdapter = MemberAdapterFactory::create($pdo, MEMBER_ADAPTER_TYPE);

// Dann können Sie bestehende Funktionen anpassen:
function get_all_members_OLD($pdo) {
    // ALT: Direkte DB-Abfrage
    return $pdo->query("SELECT * FROM members ORDER BY last_name")->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_members_NEW() {
    // NEU: Über Adapter
    global $memberAdapter;
    return $memberAdapter->getAllMembers();
}

// ============================================
// VORTEILE DIESER ARCHITEKTUR
// ============================================

/*
1. KEINE Änderungen an der Anwendungslogik nötig
   - $members hat immer die gleiche Struktur
   - Alle Funktionen arbeiten wie gewohnt

2. ZENTRALE Anpassungen
   - Nur der Adapter muss geändert werden
   - Keine verstreuten SQL-Queries im Code

3. TESTBARKEIT
   - Mock-Adapter für Tests erstellen
   - Keine echte Datenbank nötig

4. FLEXIBILITÄT
   - Umschalten zwischen Tabellen via Konfiguration
   - Mehrere Datenquellen parallel möglich

5. KEINE DATENDUPLIZIERUNG
   - Daten bleiben in Original-Tabelle
   - Immer aktuell, keine Sync nötig
*/

// ============================================
// SCHRITT 4: Schrittweise Migration
// ============================================

/**
 * Sie müssen NICHT alles auf einmal umstellen!
 * Schrittweise Vorgehensweise:
 *
 * 1. Adapter erstellen und testen
 * 2. Eine Datei umstellen (z.B. tab_admin.php)
 * 3. Testen, ob alles funktioniert
 * 4. Nächste Datei umstellen
 * 5. Schritt für Schritt das ganze System migrieren
 *
 * Während der Migration können BEIDE Ansätze parallel existieren!
 */

?>
