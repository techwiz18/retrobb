<?php
declare(strict_types=1);

namespace RetroBB\Core;

/**
 * One definition per setting (issue #44): the default a fresh board seeds
 * plus the coercion/validation every reader and writer shares. Adding a
 * setting means adding one entry here — the migrator, feature flags and
 * per-page limits all read this list.
 *
 * Rule keys: default (seeded value), int [min,max], bool, enum [allowed],
 * text (max chars), url, feature (alias feature() accepts).
 */
final class Settings
{
    public const DEFS = [
        'board_name' => ['default' => 'RetroBB', 'text' => 80],
        'board_tagline' => ['default' => 'An old-school forum for the modern web', 'text' => 200],
        'board_url' => ['default' => '', 'url' => true],
        'default_skin' => ['default' => 'classic', 'enum' => ['classic', 'midnight', 'silver']],
        'default_theme' => ['default' => 'auto', 'enum' => ['light', 'dark', 'auto']],
        'posts_per_page' => ['default' => '15', 'int' => [5, 50]],
        'topics_per_page' => ['default' => '25', 'int' => [5, 50]],
        'flood_seconds' => ['default' => '30', 'int' => [0, 3600]],
        'edit_window_mins' => ['default' => '30', 'int' => [0, 3600]],
        'delete_window_mins' => ['default' => '30', 'int' => [0, 3600]],
        'captcha_provider' => ['default' => 'honeypot', 'enum' => ['honeypot', 'builtin', 'turnstile', 'hcaptcha', 'recaptcha']],
        'captcha_sitekey' => ['default' => '', 'text' => 500],
        'captcha_secret' => ['default' => '', 'text' => 500],
        'mail_from' => ['default' => '', 'text' => 500],
        'feature_avatars_upload' => ['default' => '1', 'bool' => true, 'feature' => 'avatars_upload'],
        'feature_avatars_link' => ['default' => '1', 'bool' => true, 'feature' => 'avatars_link'],
        'avatar_max_kb' => ['default' => '100', 'int' => [10, 2048]],
        'avatar_max_width' => ['default' => '200', 'int' => [16, 2000]],
        'avatar_max_height' => ['default' => '200', 'int' => [16, 2000]],
        'feature_alerts' => ['default' => '1', 'bool' => true, 'feature' => 'alerts'],
        'feature_pms' => ['default' => '1', 'bool' => true, 'feature' => 'pms'],
        'feature_reactions' => ['default' => '1', 'bool' => true, 'feature' => 'reactions'],
        'feature_mentions' => ['default' => '1', 'bool' => true, 'feature' => 'mentions'],
        'skin_selector' => ['default' => '1', 'bool' => true, 'feature' => 'skin_selector'],
        'theme_light' => ['default' => '1', 'bool' => true],
        'theme_dark' => ['default' => '1', 'bool' => true],
        'theme_auto' => ['default' => '1', 'bool' => true],
    ];

    /** key => seeded default, i.e. the rows a fresh board has. */
    public static function defaults(): array
    {
        static $out = null;
        if ($out === null) {
            $out = [];
            foreach (self::DEFS as $key => $def) {
                $out[$key] = (string) $def['default'];
            }
        }
        return $out;
    }

    /**
     * Validate/coerce a stored or submitted value. Unknown keys (plugins,
     * runtime caches such as update_latest) pass through untouched; a rule
     * that cannot be satisfied yields null so the caller can reject or fall
     * back to the default.
     */
    public static function coerce(string $key, string $raw): ?string
    {
        $def = self::DEFS[$key] ?? null;
        if ($def === null) {
            return $raw;
        }
        $raw = trim($raw);
        if (isset($def['int'])) {
            if (!preg_match('/^-?\d+$/', $raw)) {
                return (string) $def['default'];
            }
            return (string) max($def['int'][0], min($def['int'][1], (int) $raw));
        }
        if (!empty($def['bool'])) {
            return $raw === '1' ? '1' : '0';
        }
        if (isset($def['enum'])) {
            return in_array($raw, $def['enum'], true) ? $raw : null;
        }
        if (isset($def['text'])) {
            return mb_substr($raw, 0, $def['text']);
        }
        if (!empty($def['url'])) {
            return $raw === '' || preg_match('#^https?://[^/]+#i', $raw) ? $raw : null;
        }
        return $raw;
    }

    /** Coerced integer read (per-page limits, windows, avatar caps). */
    public static function int(string $key): int
    {
        $fallback = (string) (self::DEFS[$key]['default'] ?? '0');
        return (int) (self::coerce($key, setting($key, $fallback)) ?? $fallback);
    }

    /** Setting key behind a feature name; unknown names keep the old prefix rule. */
    public static function featureKey(string $name): string
    {
        foreach (self::DEFS as $key => $def) {
            if (($def['feature'] ?? null) === $name) {
                return $key;
            }
        }
        return 'feature_' . $name;
    }
}
