<?php
declare(strict_types=1);

namespace RetroBB\Core;

class Slug
{
    public static function make(string $text, int $max = 80): string
    {
        $text = trim(mb_strtolower($text));
        // transliterate
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($trans !== false) {
            $text = $trans;
        }
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        if ($text === '') {
            $text = 'untitled';
        }
        if (strlen($text) > $max) {
            $text = substr($text, 0, $max);
            $text = rtrim($text, '-');
        }
        return $text;
    }

    public static function forumUrl(array $forum): string
    {
        return \url('/forum/' . self::rowSlug($forum, 'name') . '.f' . (int) $forum['id']);
    }

    public static function topicUrl(array $topic): string
    {
        return \url('/topic/' . self::rowSlug($topic, 'title') . '.t' . (int) $topic['id']);
    }

    public static function memberUrl(array $user): string
    {
        return \url('/members/' . self::rowSlug($user, 'username') . '.u' . (int) $user['id']);
    }

    /** Queries select the stored slug beside the id; fall back to the display name. */
    private static function rowSlug(array $row, string $nameField): string
    {
        $slug = $row['slug'] ?? '';
        return is_string($slug) && $slug !== '' ? $slug : self::make((string) ($row[$nameField] ?? ''));
    }

    /** Extract numeric id from suffix like "slug.f12", "slug.t45", "name.u1". Returns [slug, id] or null. */
    public static function parseSuffixed(string $segment, string $letter): ?array
    {
        if (!preg_match('/^(.*)\.' . preg_quote($letter, '/') . '(\d+)$/', $segment, $m)) {
            return null;
        }
        return [$m[1], (int) $m[2]];
    }
}
