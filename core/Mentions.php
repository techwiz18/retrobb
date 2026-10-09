<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Mentions
{
    /** Extract @usernames from bbcode (3–30 chars, letters/numbers/space/_/-). */
    public static function extract(string $bbcode): array
    {
        preg_match_all('/@([A-Za-z0-9_\- ]{3,30})/', $bbcode, $m);
        $names = array_unique(array_map('trim', $m[1] ?? []));
        return array_values(array_filter($names, fn($n) => $n !== ''));
    }

    /** Render @mentions in already-escaped HTML as profile links (existing users only). */
    public static function renderHtml(string $html): string
    {
        if (!feature('mentions')) {
            return $html;
        }        return (string) preg_replace_callback(
            '/@([A-Za-z0-9_\- ]{3,30})/',
            function ($m) {
                $name = trim($m[1]);
                $u = self::resolveOne($name);
                if (!$u) {
                    return $m[0];
                }
                // The regex is greedy over spaces: "@bob welcome" matches the
                // whole tail, so link only the username part and keep the rest.
                $rest = mb_substr($name, mb_strlen($u['username']));
                $url = Slug::memberUrl(['id' => (int) $u['id'], 'username' => $u['username']]);
                return '@<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') . '</a>' . $rest;
            },
            $html
        );
    }

    /** Resolve @names to user ids (case-insensitive, excludes $excludeId, deduped). */
    public static function resolveIds(array $names, int $excludeId = 0): array
    {
        $ids = [];
        foreach (array_unique($names) as $name) {
            $u = self::resolveOne(trim((string) $name));
            if ($u && (int) $u['id'] !== $excludeId) {
                $ids[(int) $u['id']] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * Find a user by @name, shortening trailing words until something matches.
     * ("@bob welcome" => tries "bob welcome", then "bob".)
     */
    private static function resolveOne(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        try {
            $st = Db::pdo()->prepare('SELECT id, username FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1');
            while ($name !== '') {
                $st->execute([$name]);
                $row = $st->fetch();
                if ($row) {
                    return $row;
                }
                // Strip one trailing word and retry ("bob welcome" -> "bob").
                $pos = mb_strrpos($name, ' ');
                if ($pos === false) {
                    return null;
                }
                $name = trim(mb_substr($name, 0, $pos));
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }
}
