<?php
declare(strict_types=1);

namespace RetroBB\Core;

/** One owner for plugin lifecycle (issue #46): which plugins are enabled,
 *  loading them, and flipping the flag. */
class Plugins
{
    /** @return array<string,int> name => enabled; [] when the table is not
     *  there yet (installer), which reads as every plugin at its default. */
    public static function states(): array
    {
        try {
            $states = [];
            foreach (Db::pdo()->query('SELECT name, enabled FROM plugins')->fetchAll() as $r) {
                $states[$r['name']] = (int) $r['enabled'];
            }
            return $states;
        } catch (\Throwable) {
            return []; // db not migrated yet (installer)
        }
    }

    /** Missing row = enabled: a new drop-in runs until an admin says otherwise. */
    public static function isEnabled(string $name, ?array $states = null): bool
    {
        return (($states ?? self::states())[$name] ?? 1) !== 0;
    }

    /** Load one plugin's bootstrap regardless of its enabled state. Throws on
     *  failure, so the caller decides how loud that is. */
    public static function loadOne(string $name): void
    {
        $boot = dirname(__DIR__) . '/plugins/' . $name . '/bootstrap.php';
        if (is_file($boot)) {
            require_once $boot;
        }
    }

    /** Flip the flag in one atomic write, returning the state afterwards. A
     *  missing row is enabled, so the first toggle stores off; concurrent
     *  toggles each flip the row instead of read-write-back a stale value. */
    public static function toggle(string $name): bool
    {
        $pdo = Db::pdo();
        $pdo->prepare('INSERT INTO plugins (name, enabled) VALUES (?, 0) ON DUPLICATE KEY UPDATE enabled = 1 - enabled')
            ->execute([$name]);
        $cur = $pdo->prepare('SELECT enabled FROM plugins WHERE name=?');
        $cur->execute([$name]);
        return (int) $cur->fetchColumn() !== 0;
    }

    public static function load(): void
    {
        $dir = dirname(__DIR__) . '/plugins';
        if (!is_dir($dir)) {
            return;
        }
        $states = self::states();
        foreach (glob($dir . '/*', GLOB_ONLYDIR) as $plugDir) {
            $name = basename($plugDir);
            if (!self::isEnabled($name, $states)) {
                continue;
            }
            try {
                self::loadOne($name);
            } catch (\Throwable $t) {
                // never let a plugin whitescreen the board
                error_log("RetroBB plugin $name failed: " . $t->getMessage());
            }
        }
    }
}
