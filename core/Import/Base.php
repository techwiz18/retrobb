<?php
declare(strict_types=1);

namespace RetroBB\Core\Import;

use PDO;

/** Shared source-database plumbing for board importers. */
abstract class Base
{
    protected PDO $src;
    protected string $pre;
    protected string $db;

    /** Connect to the source board. Throws with a human-readable message. */
    public static function connect(array $cfg): PDO
    {
        foreach (['host', 'db', 'user'] as $k) {
            if (trim((string) ($cfg[$k] ?? '')) === '' && $k !== 'user') {
                throw new \RuntimeException('Source host, database and user are required.');
            }
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) ($cfg['db'] ?? ''))) {
            throw new \RuntimeException('Source database name looks invalid.');
        }
        if (!preg_match('/^[A-Za-z0-9_]*$/', (string) ($cfg['prefix'] ?? ''))) {
            throw new \RuntimeException('Table prefix may only contain letters, numbers and underscores.');
        }
        try {
            return new PDO(
                'mysql:host=' . $cfg['host'] . ';port=' . ((int) ($cfg['port'] ?? 3306) ?: 3306) . ';dbname=' . $cfg['db'] . ';charset=utf8mb4',
                (string) ($cfg['user'] ?? ''),
                (string) ($cfg['pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (\PDOException $e) {
            throw new \RuntimeException('Could not connect to the source database (' . $e->getMessage() . ')');
        }
    }

    public function __construct(PDO $src, string $prefix, string $db)
    {
        $this->src = $src;
        $this->pre = $prefix;
        $this->db = $db;
    }

    protected function table(string $name): string
    {
        return '`' . str_replace('`', '', $this->db) . '`.' . $this->pre . $name;
    }

    /** Required source tables missing from that database. */
    public function missingTables(array $names): array
    {
        $missing = [];
        foreach ($names as $n) {
            try {
                $this->src->query('SELECT 1 FROM ' . $this->table($n) . ' LIMIT 1');
            } catch (\Throwable) {
                $missing[] = $this->pre . $n;
            }
        }
        return $missing;
    }

    protected function count(string $sql, array $params = []): int
    {
        $st = $this->src->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    /** username → group for imported staff (ids vary by board, mapped per source). */
    public static function groupFor(bool $isAdmin, bool $isMod): string
    {
        if ($isAdmin) {
            return 'admin';
        }
        return $isMod ? 'mod' : 'member';
    }

    /** Display name fallback for guest-authored content. */
    public static function guestLabel(string $name): string
    {
        $name = trim($name);
        return $name !== '' ? mb_substr($name, 0, 50) : 'Guest';
    }
}
