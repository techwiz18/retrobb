<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Plugins
{
    public static function load(): void
    {
        $dir = dirname(__DIR__) . '/plugins';
        if (!is_dir($dir)) {
            return;
        }
        $enabled = null;
        try {
            $rows = Db::pdo()->query('SELECT name, enabled FROM plugins')->fetchAll();
            $enabled = [];
            foreach ($rows as $r) {
                $enabled[$r['name']] = (int) $r['enabled'];
            }
        } catch (\Throwable) {
            $enabled = null; // db not migrated yet (installer)
        }
        foreach (glob($dir . '/*', GLOB_ONLYDIR) as $plugDir) {
            $name = basename($plugDir);
            if (is_array($enabled) && ($enabled[$name] ?? 1) === 0) {
                continue;
            }
            $boot = $plugDir . '/bootstrap.php';
            if (is_file($boot)) {
                try {
                    require_once $boot;
                } catch (\Throwable $t) {
                    // never let a plugin whitescreen the board
                    error_log("RetroBB plugin $name failed: " . $t->getMessage());
                }
            }
        }
    }
}
