<?php
declare(strict_types=1);

namespace RetroBB\Core;

/** WordPress/XenForo-style hook system. Actions do things, filters transform values. */
class Hooks
{
    /** @var array<string, array<int, callable[]>> */
    private static array $actions = [];
    /** @var array<string, array<int, callable[]>> */
    private static array $filters = [];
    /** @var array<string, string[]> template hook => view partial paths */
    private static array $templateHooks = [];

    public static function reset(): void
    {
        self::$actions = [];
        self::$filters = [];
        self::$templateHooks = [];
    }

    public static function add_action(string $hook, callable $cb, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $cb;
    }

    /** @param mixed ...$args */
    public static function do_action(string $hook, ...$args): void
    {
        if (empty(self::$actions[$hook])) {
            return;
        }
        ksort(self::$actions[$hook]);
        foreach (self::$actions[$hook] as $list) {
            foreach ($list as $cb) {
                $cb(...$args);
            }
        }
    }

    public static function add_filter(string $hook, callable $cb, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $cb;
    }

    public static function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (empty(self::$filters[$hook])) {
            return $value;
        }
        ksort(self::$filters[$hook]);
        foreach (self::$filters[$hook] as $list) {
            foreach ($list as $cb) {
                $value = $cb($value, ...$args);
            }
        }
        return $value;
    }

    public static function add_template_hook(string $location, string $viewFile): void
    {
        self::$templateHooks[$location][] = $viewFile;
    }

    public static function render_template_hook(string $location, array $vars = []): void
    {
        foreach (self::$templateHooks[$location] ?? [] as $file) {
            if (is_file($file)) {
                extract($vars, EXTR_SKIP);
                require $file;
            }
        }
    }
}
