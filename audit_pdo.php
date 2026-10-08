<?php
/**
 * audit_pdo.php – Transparentes Audit-Logging aller DB-Schreibzugriffe
 *
 * AuditPDO erweitert PDO; alle vorbereiteten Statements gehen durch
 * AuditStatement, das nach jedem INSERT/UPDATE/DELETE/REPLACE einen
 * Eintrag in svaudit_log schreibt.
 *
 * Einbinden vor dem ersten „new PDO(…)":
 *     require_once __DIR__ . '/audit_pdo.php';
 * Dann einfach:
 *     $pdo = new AuditPDO($dsn, $user, $pass, $opts);
 */

if (!defined('SV_AUDIT_LOADED')) {
    define('SV_AUDIT_LOADED', true);

class AuditStatement extends PDOStatement {

    protected AuditPDO $audit;

    protected function __construct(AuditPDO $audit) {
        $this->audit = $audit;
    }

    public function execute(?array $params = null): bool {
        $result = parent::execute($params);
        if ($result) {
            $this->audit->maybeLog($this->queryString, $this->rowCount());
        }
        return $result;
    }
}

class AuditPDO extends PDO {

    private bool $inAudit = false;

    public function __construct(string $dsn, string $user, string $pass, array $opts = []) {
        parent::__construct($dsn, $user, $pass, $opts);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [AuditStatement::class, [$this]]);
    }

    /** Überschrieben, damit auch direkte exec()-Aufrufe geloggt werden */
    public function exec(string $statement): int|false {
        $result = parent::exec($statement);
        if ($result !== false) {
            $this->maybeLog($statement, is_int($result) ? $result : 0);
        }
        return $result;
    }

    /**
     * Schreibt einen Audit-Eintrag für INSERT/UPDATE/DELETE/REPLACE.
     * Wird von AuditStatement::execute() und exec() aufgerufen.
     * Wirft nie eine Exception – ein Audit-Fehler darf die Hauptaktion
     * nicht zum Scheitern bringen.
     */
    public function maybeLog(string $query, int $affected): void {
        if ($this->inAudit) return;

        $q = ltrim($query);
        $verb = strtoupper(strtok($q, " \t\n\r"));
        if (!in_array($verb, ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'], true)) return;

        $this->inAudit = true;
        try {
            $table = self::extractTable($verb, $q);
            if ($table === 'svaudit_log') return; // Sicherheitsgurt

            $member_id = isset($_SESSION['member_id']) ? (int)$_SESSION['member_id'] : null;

            $ip = null;
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = substr($_SERVER['REMOTE_ADDR'], 0, 45);
            } elseif (php_sapi_name() === 'cli') {
                $ip = 'cli';
            }

            if (php_sapi_name() === 'cli') {
                $script = basename($_SERVER['argv'][0] ?? 'cli');
            } else {
                $script = substr($_SERVER['SCRIPT_NAME'] ?? '', 0, 200) ?: null;
            }

            $stmt = parent::prepare(
                "INSERT INTO svaudit_log
                 (member_id, ip_address, script, action, table_name, affected_rows, query)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $member_id,
                $ip,
                $script,
                $verb,
                $table,
                $affected,
                substr($q, 0, 1000),
            ]);
        } catch (\Throwable $e) {
            error_log('svaudit_log write failed: ' . $e->getMessage());
        } finally {
            $this->inAudit = false;
        }
    }

    private static function extractTable(string $verb, string $q): ?string {
        $p = match ($verb) {
            'INSERT'  => '/^INSERT\s+(?:LOW_PRIORITY\s+|DELAYED\s+|HIGH_PRIORITY\s+)?(?:IGNORE\s+)?(?:INTO\s+)?[`"]?(\w+)[`"]?/i',
            'REPLACE' => '/^REPLACE\s+(?:LOW_PRIORITY\s+|DELAYED\s+)?(?:INTO\s+)?[`"]?(\w+)[`"]?/i',
            'UPDATE'  => '/^UPDATE\s+(?:LOW_PRIORITY\s+)?(?:IGNORE\s+)?[`"]?(\w+)[`"]?/i',
            'DELETE'  => '/^DELETE\s+(?:LOW_PRIORITY\s+|QUICK\s+|IGNORE\s+)?(?:FROM\s+)?[`"]?(\w+)[`"]?/i',
            default   => null,
        };
        return ($p && preg_match($p, $q, $m)) ? $m[1] : null;
    }
}

} // end if SV_AUDIT_LOADED
