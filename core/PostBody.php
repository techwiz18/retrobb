<?php
declare(strict_types=1);

namespace RetroBB\Core;

/**
 * The one place a topic post body is rendered and stored.
 *
 * `body_bbcode` and `body_html` are written together so the pair can never
 * drift, and views ask this module for display HTML instead of deciding
 * whether the stored form is trustworthy.
 */
class PostBody
{
    /** Render BBCode to HTML (escaping, link allowlist, mentions and the
        `post_body_html` plugin filter all live inside BBCode::toHtml). */
    public static function render(string $bbcode): string
    {
        return BBCode::toHtml($bbcode);
    }

    /** Stored HTML for display; legacy rows that predate a renderer fix are
        re-rendered from their BBCode. */
    public static function display(string $bbcode, string $html): string
    {
        return BBCode::needsRepair($html) ? self::render($bbcode) : $html;
    }

    /** Insert a post, writing both stored forms in one statement. */
    public static function insert(int $topicId, int $userId, string $bbcode, string $createdAt): int
    {
        $pdo = Db::pdo();
        $pdo->prepare('INSERT INTO posts (topic_id, user_id, body_bbcode, body_html, created_at) VALUES (?,?,?,?,?)')
            ->execute([$topicId, $userId, $bbcode, self::render($bbcode), $createdAt]);
        return (int) $pdo->lastInsertId();
    }

    /** Rewrite a post body; the edit also stamps `edited_at`. */
    public static function update(int $postId, string $bbcode): void
    {
        Db::pdo()->prepare('UPDATE posts SET body_bbcode=?, body_html=?, edited_at=? WHERE id=?')
            ->execute([$bbcode, self::render($bbcode), date('Y-m-d H:i:s'), $postId]);
    }
}
