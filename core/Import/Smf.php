<?php
declare(strict_types=1);

namespace RetroBB\Core\Import;

/**
 * SMF 2.x reader. What maps and what doesn't:
 * - users: activated accounts (guests post as Guest; their names are kept)
 * - structure: categories + boards (redirect boards skipped, child boards
 *   flattened into their category — RetroBB is two levels)
 * - topics: approved only; sticky passes through; titles come from each
 *   topic's first message (SMF stores no title on the topic itself)
 * - posts: approved messages ordered by id
 * - skipped: polls, attachments, PMs, likes, bans, avatars, permissions
 */
class Smf extends Base
{
    public const TABLES = ['members', 'categories', 'boards', 'topics', 'messages'];

    /** SMF quote dialect → ours: [quote author=Bob link=...] -> [quote=Bob]. */
    public static function convertBody(string $text): string
    {
        $text = (string) preg_replace('/\[quote author=(.+?)(?: link=[^\]]*)?\]/i', '[quote=$1]', $text);
        return $text;
    }

    public function counts(): array
    {
        $m = $this->table('members');
        $b = $this->table('boards');
        $t = $this->table('topics');
        $g = $this->table('messages');
        // Boards minus redirect boards; categories counted with forums.
        $forums = $this->count("SELECT COUNT(*) FROM $b WHERE redirect=''");
        $cats = $this->count('SELECT COUNT(*) FROM ' . $this->table('categories'));
        return [
            'users' => $this->count("SELECT COUNT(*) FROM $m WHERE is_activated=1"),
            'forums' => $forums + $cats,
            'topics' => $this->count("SELECT COUNT(*) FROM $t WHERE approved=1"),
            'posts' => $this->count("SELECT COUNT(*) FROM $g WHERE approved=1"),
        ];
    }

    /** @return normalized user rows ordered by id. */
    public function fetchUsers(int $off, int $lim): array
    {
        $m = $this->table('members');
        $st = $this->src->prepare(
            "SELECT id_member, member_name, email_address, passwd, password_salt, id_group, date_registered, posts
             FROM $m WHERE is_activated=1 ORDER BY id_member LIMIT ? OFFSET ?"
        );
        $st->bindValue(1, $lim, \PDO::PARAM_INT);
        $st->bindValue(2, $off, \PDO::PARAM_INT);
        $st->execute();
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[] = [
                'old_id' => (int) $r['id_member'],
                'username' => mb_substr(trim((string) $r['member_name']), 0, 50),
                'email' => mb_substr(trim((string) $r['email_address']), 0, 190),
                'passhash' => (string) $r['passwd'],
                'scheme' => 'smf',
                'salt' => (string) ($r['password_salt'] ?? ''),
                'group' => self::groupFor((int) $r['id_group'] === 1, (int) $r['id_group'] === 2),
                'created' => date('Y-m-d H:i:s', max(0, (int) $r['date_registered'])),
                'posts' => max(0, (int) $r['posts']),
            ];
        }
        return $out;
    }

    /** @return [categories[], forums[]] with parent links resolved to category keys. */
    public function fetchStructure(): array
    {
        $c = $this->table('categories');
        $cats = [];
        foreach ($this->src->query("SELECT id_cat, name FROM $c ORDER BY cat_order, id_cat")->fetchAll() as $r) {
            $cats[] = ['old_id' => (int) $r['id_cat'], 'title' => mb_substr(trim((string) $r['name']), 0, 190)];
        }
        $b = $this->table('boards');
        $forums = [];
        foreach ($this->src->query("SELECT id_board, id_cat, name, description FROM $b WHERE redirect='' ORDER BY board_order, id_board")->fetchAll() as $r) {
            $forums[] = [
                'old_id' => (int) $r['id_board'], 'catkey' => (int) $r['id_cat'],
                'name' => mb_substr(trim((string) $r['name']), 0, 190),
                'desc' => mb_substr(trim((string) ($r['description'] ?? '')), 0, 500),
            ];
        }
        return [$cats, $forums];
    }

    /** @return normalized topic rows ordered by id. */
    public function fetchTopics(int $off, int $lim): array
    {
        $t = $this->table('topics');
        $g = $this->table('messages');
        $st = $this->src->prepare(
            "SELECT t.id_topic, t.id_board, t.id_member_started, t.num_views, t.locked, t.is_sticky,
              (SELECT m.subject FROM $g m WHERE m.id_topic=t.id_topic ORDER BY m.id_msg LIMIT 1) AS title,
              (SELECT m.poster_time FROM $g m WHERE m.id_topic=t.id_topic ORDER BY m.id_msg LIMIT 1) AS first_time
             FROM $t t WHERE t.approved=1 ORDER BY t.id_topic LIMIT ? OFFSET ?"
        );
        $st->bindValue(1, $lim, \PDO::PARAM_INT);
        $st->bindValue(2, $off, \PDO::PARAM_INT);
        $st->execute();
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $title = trim((string) ($r['title'] ?? ''));
            $out[] = [
                'old_id' => (int) $r['id_topic'],
                'forumkey' => (int) $r['id_board'],
                'title' => mb_substr($title !== '' ? $title : '(no subject)', 0, 120),
                'pinned' => (int) $r['is_sticky'] === 1 ? 1 : 0,
                'locked' => (int) $r['locked'] === 1 ? 1 : 0,
                'views' => max(0, (int) $r['num_views']),
                'created' => date('Y-m-d H:i:s', max(0, (int) ($r['first_time'] ?? 0))),
                'starter_old' => (int) $r['id_member_started'],
            ];
        }
        return $out;
    }

    /** @return normalized post rows ordered by id. */
    public function fetchPosts(int $off, int $lim): array
    {
        $g = $this->table('messages');
        $st = $this->src->prepare(
            "SELECT id_msg, id_topic, id_member, poster_time, subject, body, poster_name
             FROM $g WHERE approved=1 ORDER BY id_msg LIMIT ? OFFSET ?"
        );
        $st->bindValue(1, $lim, \PDO::PARAM_INT);
        $st->bindValue(2, $off, \PDO::PARAM_INT);
        $st->execute();
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[] = [
                'old_id' => (int) $r['id_msg'],
                'topickey' => (int) $r['id_topic'],
                'author_old' => (int) $r['id_member'],
                'guestname' => self::guestLabel((string) ($r['poster_name'] ?? '')),
                'subject' => mb_substr(trim((string) ($r['subject'] ?? '')), 0, 120),
                'body' => self::convertBody((string) $r['body']),
                'created' => date('Y-m-d H:i:s', max(0, (int) $r['poster_time'])),
            ];
        }
        return $out;
    }
}
