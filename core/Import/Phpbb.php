<?php
declare(strict_types=1);

namespace RetroBB\Core\Import;

/**
 * phpBB 3.x reader. What maps and what doesn't:
 * - users: normal + founders (bots/Anonymous skipped; their posts become Guest's)
 * - structure: categories + postable forums (redirect links skipped, subforums
 *   flattened into their category — RetroBB is two levels)
 * - topics: approved only; sticky/announce/global pin; locked passes through
 * - posts: approved only, ordered by id; guest posts keep the poster's name
 * - skipped: polls, attachments, PMs, bans, ranks, avatars, permissions
 */
class Phpbb extends Base
{
    public const TABLES = ['users', 'forums', 'topics', 'posts'];

    /** Strip this post's BBCode uid suffixes: [b:1a2b3c] -> [b]. */
    public static function convertBody(string $text, string $uid): string
    {
        if ($uid !== '' && preg_match('/^[A-Za-z0-9]+$/', $uid)) {
            $text = str_replace(':' . $uid . ']', ']', $text);
        }
        // Magic-URL comment markers phpBB leaves around auto-linked URLs.
        $text = str_replace(['<!-- m -->', '<!-- w -->', '<!-- s', '<!-- e -->'], '', $text);
        return $text;
    }

    public function counts(): array
    {
        $u = $this->table('users');
        $f = $this->table('forums');
        $t = $this->table('topics');
        $p = $this->table('posts');
        return [
            'users' => $this->count("SELECT COUNT(*) FROM $u WHERE user_type IN (0,3)"),
            'forums' => $this->count("SELECT COUNT(*) FROM $f WHERE forum_type IN (0,1)"),
            'topics' => $this->count("SELECT COUNT(*) FROM $t WHERE topic_approved=1"),
            'posts' => $this->count("SELECT COUNT(*) FROM $p WHERE post_approved=1"),
        ];
    }

    /** @return normalized user rows ordered by id. */
    public function fetchUsers(int $off, int $lim): array
    {
        $u = $this->table('users');
        $st = $this->src->prepare(
            "SELECT user_id, username, user_email, user_password, group_id, user_regdate, user_posts
             FROM $u WHERE user_type IN (0,3) ORDER BY user_id LIMIT ? OFFSET ?"
        );
        $st->bindValue(1, $lim, \PDO::PARAM_INT);
        $st->bindValue(2, $off, \PDO::PARAM_INT);
        $st->execute();
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $hash = (string) $r['user_password'];
            $out[] = [
                'old_id' => (int) $r['user_id'],
                'username' => mb_substr(trim((string) $r['username']), 0, 50),
                'email' => mb_substr(trim((string) $r['user_email']), 0, 190),
                'passhash' => $hash,
                'scheme' => str_starts_with($hash, '$H$') ? 'phpbb' : 'modern',
                'salt' => '',
                'group' => self::groupFor((int) $r['group_id'] === 5, (int) $r['group_id'] === 4),
                'created' => date('Y-m-d H:i:s', max(0, (int) $r['user_regdate'])),
                'posts' => max(0, (int) $r['user_posts']),
            ];
        }
        return $out;
    }

    /** @return [categories[], forums[]] with parent links resolved to category keys. */
    public function fetchStructure(): array
    {
        $f = $this->table('forums');
        $rows = $this->src->query(
            "SELECT forum_id, parent_id, forum_type, forum_name, forum_desc FROM $f WHERE forum_type IN (0,1) ORDER BY forum_id"
        )->fetchAll();
        $cats = [];
        $forums = [];
        $catKeys = [];
        foreach ($rows as $r) {
            if ((int) $r['forum_type'] === 0) {
                $cats[] = ['old_id' => (int) $r['forum_id'], 'title' => mb_substr(trim((string) $r['forum_name']), 0, 190)];
                $catKeys[(int) $r['forum_id']] = true;
            }
        }
        foreach ($rows as $r) {
            if ((int) $r['forum_type'] !== 1) {
                continue;
            }
            $catKey = isset($catKeys[(int) $r['parent_id']]) ? (int) $r['parent_id'] : null;
            $forums[] = [
                'old_id' => (int) $r['forum_id'], 'catkey' => $catKey,
                'name' => mb_substr(trim((string) $r['forum_name']), 0, 190),
                'desc' => mb_substr(trim((string) ($r['forum_desc'] ?? '')), 0, 500),
            ];
        }
        return [$cats, $forums];
    }

    /** @return normalized topic rows ordered by id. */
    public function fetchTopics(int $off, int $lim): array
    {
        $t = $this->table('topics');
        $st = $this->src->prepare(
            "SELECT topic_id, forum_id, topic_title, topic_poster, topic_time, topic_views, topic_type, topic_status
             FROM $t WHERE topic_approved=1 ORDER BY topic_id LIMIT ? OFFSET ?"
        );
        $st->bindValue(1, $lim, \PDO::PARAM_INT);
        $st->bindValue(2, $off, \PDO::PARAM_INT);
        $st->execute();
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[] = [
                'old_id' => (int) $r['topic_id'],
                'forumkey' => (int) $r['forum_id'],
                'title' => mb_substr(trim((string) $r['topic_title']), 0, 120),
                'pinned' => (int) $r['topic_type'] >= 1 ? 1 : 0,
                'locked' => (int) $r['topic_status'] === 1 ? 1 : 0,
                'views' => max(0, (int) $r['topic_views']),
                'created' => date('Y-m-d H:i:s', max(0, (int) $r['topic_time'])),
                'starter_old' => (int) $r['topic_poster'],
            ];
        }
        return $out;
    }

    /** @return normalized post rows ordered by id. */
    public function fetchPosts(int $off, int $lim): array
    {
        $p = $this->table('posts');
        $st = $this->src->prepare(
            "SELECT post_id, topic_id, poster_id, post_time, post_subject, post_text, bbcode_uid, post_username
             FROM $p WHERE post_approved=1 ORDER BY post_id LIMIT ? OFFSET ?"
        );
        $st->bindValue(1, $lim, \PDO::PARAM_INT);
        $st->bindValue(2, $off, \PDO::PARAM_INT);
        $st->execute();
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[] = [
                'old_id' => (int) $r['post_id'],
                'topickey' => (int) $r['topic_id'],
                'author_old' => (int) $r['poster_id'],
                'guestname' => self::guestLabel((string) ($r['post_username'] ?? '')),
                'subject' => mb_substr(trim((string) ($r['post_subject'] ?? '')), 0, 120),
                'body' => self::convertBody((string) $r['post_text'], (string) ($r['bbcode_uid'] ?? '')),
                'created' => date('Y-m-d H:i:s', max(0, (int) $r['post_time'])),
            ];
        }
        return $out;
    }
}
