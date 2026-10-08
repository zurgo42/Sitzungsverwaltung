<?php
/**
 * audit_pdo.php – Transparentes Audit-Logging aller DB-Schreibzugriffe
 *
 * AuditPDO erweitert PDO; alle vorbereiteten Statements gehen durch
 * AuditStatement, das nach jedem INSERT/UPDATE/DELETE/REPLACE einen
 * Eintrag in svaudit_log schreibt – mit interpolierten Parameterwerten,
 * so dass die gespeicherte Query direkt lesbar ist.
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
    private array $boundValues = [];
    private array $boundRefs   = [];

    protected function __construct(AuditPDO $audit) {
        $this->audit = $audit;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool {
        $this->boundValues[$param] = $value;
        return parent::bindValue($param, $value, $type);
    }

    public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool {
        $this->boundRefs[$param] = &$var;
        return parent::bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function execute(?array $params = null): bool {
        $result = parent::execute($params);
        if ($result) {
            // Werte zusammenführen: execute()-Array hat höchste Priorität,
            // sonst bindValue/bindParam-Werte (Refs zum Zeitpunkt von execute() auflösen)
            if ($params !== null) {
                $merged = $params;
            } else {
                $merged = $this->boundValues;
                foreach ($this->boundRefs as $k => &$v) {
                    $merged[$k] = $v;
                }
            }
            $this->audit->maybeLog($this->queryString, $this->rowCount(), $merged);
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
            $this->maybeLog($statement, is_int($result) ? $result : 0, []);
        }
        return $result;
    }

    /**
     * Schreibt einen Audit-Eintrag für INSERT/UPDATE/DELETE/REPLACE.
     * Wirft nie eine Exception – ein Audit-Fehler darf die Hauptaktion
     * nicht zum Scheitern bringen.
     */
    public function maybeLog(string $query, int $affected, array $params): void {
        if ($this->inAudit) return;

        $q = ltrim($query);
        $verb = strtoupper(strtok($q, " \t\n\r"));
        if (!in_array($verb, ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'], true)) return;

        $this->inAudit = true;
        try {
            $table = self::extractTable($verb, $q);
            if ($table === 'svaudit_log') return;

            $member_id = isset($_SESSION['member_id']) ? (int)$_SESSION['member_id'] : null;

            $ip = null;
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = substr($_SERVER['REMOTE_ADDR'], 0, 45);
            } elseif (php_sapi_name() === 'cli') {
                $ip = 'cli';
            }

            $script = php_sapi_name() === 'cli'
                ? basename($_SERVER['argv'][0] ?? 'cli')
                : (substr($_SERVER['SCRIPT_NAME'] ?? '', 0, 200) ?: null);

            // Parameter in Query einsetzen für lesbare Anzeige
            $display_q = self::interpolate($q, $params);

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
                substr($display_q, 0, 2000),
            ]);
        } catch (\Throwable $e) {
            error_log('svaudit_log write failed: ' . $e->getMessage());
        } finally {
            $this->inAudit = false;
        }
    }

    /**
     * Setzt Parameter in die Query ein (nur für die Audit-Anzeige, nicht für die DB-Ausführung).
     * Lange Werte (z. B. HTML) werden auf 300 Zeichen gekürzt.
     */
    private static function interpolate(string $query, array $params): string {
        if (empty($params)) return $query;

        $escape = static function (mixed $v): string {
            if ($v === null) return 'NULL';
            if (is_bool($v)) return $v ? '1' : '0';
            if (is_int($v) || is_float($v)) return (string)$v;
            $s = (string)$v;
            if (mb_strlen($s) > 300) {
                $s = mb_substr($s, 0, 300) . '…';
            }
            return "'" . str_replace("'", "''", $s) . "'";
        };

        $keys = array_keys($params);
        $isPositional = $keys === range(0, count($params) - 1);

        if ($isPositional) {
            $values = array_values($params);
            $i = 0;
            return preg_replace_callback('/\?/', function () use (&$i, $values, $escape) {
                return $escape($values[$i++] ?? null);
            }, $query);
        }

        // Named params (:name) — längste zuerst ersetzen, um Teilmatches zu vermeiden
        usort($keys, fn($a, $b) => strlen((string)$b) - strlen((string)$a));
        foreach ($keys as $key) {
            $placeholder = ':' . ltrim((string)$key, ':');
            $query = str_replace($placeholder, $escape($params[$key]), $query);
        }
        return $query;
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
